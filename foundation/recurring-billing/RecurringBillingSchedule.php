<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\RecurringBilling;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/RecurringBillingTerms.php';

use InvalidArgumentException;
use JsonException;

/** Retained decisions and pure period eligibility; native authority and invoicing are separate. */
final readonly class RecurringBillingSchedule
{
    public const MAX_EVENTS = 256;
    public const MAX_BYTES = 524288;

    private function __construct(
        private string $bytes,
        private RecurringBillingTerms $terms,
        private array $state,
    ) {}

    public static function create(RecurringBillingTerms $terms, mixed $createdBy, mixed $createdAt): self
    {
        if ($terms->data()['format'] !== 1) throw new InvalidArgumentException('Use the explicit checkout schedule command for checkout terms.');
        self::identity($createdBy);
        $terms->billingDate($createdAt);
        return self::fromJson(self::encode(['format'=>1, 'terms'=>$terms->data(),
            'created_by'=>$createdBy, 'created_at'=>$createdAt, 'events'=>[]]));
    }

    public static function fromJson(mixed $bytes): self
    {
        if (!is_string($bytes) || strlen($bytes)>self::MAX_BYTES) {
            throw new InvalidArgumentException('Recurring schedule document is invalid or too large.');
        }
        try {$data=json_decode($bytes,true,12,JSON_THROW_ON_ERROR);}
        catch(JsonException $e) {throw new InvalidArgumentException('Invalid recurring schedule JSON.',0,$e);}
        if (is_array($data) && ($data['format']??null)===2) return self::fromCheckout($data,$bytes);
        self::keys($data,['format','terms','created_by','created_at','events']);
        if ($data['format']!==1 || !is_array($data['events']) || !array_is_list($data['events']) || count($data['events'])>self::MAX_EVENTS) {
            throw new InvalidArgumentException('Unsupported recurring schedule format or history.');
        }
        $terms=RecurringBillingTerms::fromJson(self::encode($data['terms']));
        if ($terms->data()['format']!==1) throw new InvalidArgumentException('Legacy schedule cannot wrap checkout terms.');
        self::identity($data['created_by']);
        $terms->billingDate($data['created_at']);
        $state=['phase'=>'offered','last_boundary'=>null,'end_period'=>null,
            'last_at'=>$data['created_at'],'revision'=>0,'boundaries'=>[]];
        $canonical=['format'=>1,'terms'=>$terms->data(),'created_by'=>$data['created_by'],
            'created_at'=>$data['created_at'],'events'=>[]];
        $seen=[];
        foreach ($data['events'] as $raw) {
            self::keys($raw,['id','command','actor','at','arguments']);
            $event=self::event($raw['id'],$raw['command'],$raw['actor'],$raw['at'],$raw['arguments']);
            if (isset($seen[$event['id']])) throw new InvalidArgumentException('A recurring schedule event identity is repeated.');
            $seen[$event['id']]=true;
            $state=self::apply($terms,$state,$event);
            $canonical['events'][]=$event;
        }
        if (self::encode($canonical)!==$bytes) {
            throw new InvalidArgumentException('Recurring schedule must retain its exact canonical representation.');
        }
        return new self($bytes,$terms,$state);
    }

    /** Fixed client decision and automatic local start; no legacy activation override exists. */
    public static function createCheckout(RecurringBillingTerms $terms, mixed $eventId): self
    {
        $d=$terms->data();
        if ($d['format']!==2) throw new InvalidArgumentException('Checkout schedule requires explicit checkout terms.');
        return self::fromJson(self::encode(['format'=>2,'terms'=>$d,'created_by_client'=>$d['client_id'],
            'created_at'=>$d['checkout_origin']['accepted_at'],'events'=>[self::checkoutEvent($terms,$eventId)]]));
    }

    private static function checkoutEvent(RecurringBillingTerms $terms,mixed $id): array
    {
        if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/',$id)) throw new InvalidArgumentException('Invalid checkout schedule event identity.');
        $d=$terms->data();
        return ['id'=>$id,'command'=>'checkout_accept','actor'=>'client:'.$d['client_id'],
            'at'=>$d['checkout_origin']['accepted_at'],'arguments'=>['terms_sha256'=>$terms->fingerprint(),
                'consent_sha256'=>$d['checkout_origin']['consent_sha256'],'period_index'=>0,'effect'=>'automatic-local-period-zero-start']];
    }

    /** Historical structural replay; callers still validate retained rows and native authority. */
    private static function fromCheckout(array $data,string $bytes): self
    {
        self::keys($data,['format','terms','created_by_client','created_at','events']);
        if ($data['format']!==2 || !is_array($data['events']) || !array_is_list($data['events'])
            || count($data['events'])<1 || count($data['events'])>self::MAX_EVENTS) {
            throw new InvalidArgumentException('Invalid checkout schedule history.');
        }
        $terms=RecurringBillingTerms::fromJson(self::encode($data['terms']));$d=$terms->data();
        if ($d['format']!==2 || $data['created_by_client']!==$d['client_id']
            || $data['created_at']!==$d['checkout_origin']['accepted_at']
            || $terms->billingDate($data['created_at'])!==$d['anchor']) {
            throw new InvalidArgumentException('Checkout schedule must start on its exact accepted local date.');
        }
        $first=$data['events'][0];
        if (!is_array($first) || $first!==self::checkoutEvent($terms,$first['id']??null)) {
            throw new InvalidArgumentException('Checkout schedule needs its exact initial client decision and local-start effect.');
        }
        $state=['phase'=>'running','last_boundary'=>0,'end_period'=>null,
            'last_at'=>$data['created_at'],'revision'=>1,'boundaries'=>[['index'=>0,'phase'=>'running']]];
        $seen=[$first['id']=>true];$events=[$first];
        foreach (array_slice($data['events'],1) as $raw) {
            self::keys($raw,['id','command','actor','at','arguments']);
            $event=self::event($raw['id'],$raw['command'],$raw['actor'],$raw['at'],$raw['arguments']);
            if (isset($seen[$event['id']])) throw new InvalidArgumentException('A recurring schedule event identity is repeated.');
            $seen[$event['id']]=true;$state=self::apply($terms,$state,$event);$events[]=$event;
        }
        $canonical=['format'=>2,'terms'=>$d,'created_by_client'=>$d['client_id'],
            'created_at'=>$d['checkout_origin']['accepted_at'],'events'=>$events];
        if (self::encode($canonical)!==$bytes) throw new InvalidArgumentException('Checkout schedule must retain its exact canonical representation.');
        return new self($bytes,$terms,$state);
    }

    public function append(mixed $id,mixed $command,mixed $actor,mixed $at,mixed $arguments): self
    {
        $data=$this->data();
        if ($data['format']===2 && $id===$data['events'][0]['id']) {
            if (['id'=>$id,'command'=>$command,'actor'=>$actor,'at'=>$at,'arguments'=>$arguments]!==$data['events'][0]) throw new InvalidArgumentException('Checkout event identity already records a different decision.');
            return $this;
        }
        $event=self::event($id,$command,$actor,$at,$arguments);
        foreach ($data['events'] as $old) {
            if ($old['id']===$event['id']) {
                if ($old!==$event) throw new InvalidArgumentException('Recurring event identity already records a different decision.');
                return $this;
            }
        }
        $data['events'][]=$event;
        return self::fromJson(self::encode($data));
    }

    public function data(): array {return json_decode($this->bytes,true,12,JSON_THROW_ON_ERROR);}
    public function json(): string {return $this->bytes;}
    public function fingerprint(): string {return hash('sha256',$this->bytes);}
    public function revision(): int {return $this->state['revision'];}
    /** Last recorded phase may describe a future scheduled boundary. */
    public function phase(): string {return $this->state['phase'];}

    public function assessment(mixed $periodIndex,mixed $asOfEpoch): array
    {
        $date=$this->terms->billingDate($asOfEpoch);
        if ($asOfEpoch<$this->state['last_at']) throw new InvalidArgumentException('Assessment cannot precede the latest retained decision.');
        $period=$this->terms->period($periodIndex);
        $reason=match($this->state['phase']) {
            'offered'=>'awaiting-consent', 'declined'=>'declined', 'withdrawn'=>'withdrawn',
            'accepted'=>'not-activated', default=>null,
        };
        if ($reason===null) {
            $phase='before-activation';
            foreach ($this->state['boundaries'] as $boundary) {
                if ($boundary['index']>$periodIndex) break;
                $phase=$boundary['phase'];
            }
            if ($this->state['end_period']!==null && $periodIndex>=$this->state['end_period']) $phase='ended';
            $reason=$phase!=='running' ? $phase
                : ($period['start']>$date ? 'not-started' : ($period['amount_cents']===0 ? 'zero-price' : 'eligible'));
        }
        return ['period'=>$period,'eligible'=>$reason==='eligible','reason'=>$reason];
    }

    private static function apply(RecurringBillingTerms $terms,array $state,array $event): array
    {
        $date=$terms->billingDate($event['at']);
        if ($event['at']<$state['last_at']) throw new InvalidArgumentException('Recurring decisions must be in recorded time order.');
        if ($state['revision']>=self::MAX_EVENTS-1 && $event['command']!=='end') {
            throw new InvalidArgumentException('The final history entry is reserved for ending this schedule.');
        }
        [$kind,$actor]=explode(':',$event['actor']);
        if ($kind==='client' && (int)$actor!==$terms->data()['client_id']) {
            throw new InvalidArgumentException('The declared client does not match these terms.');
        }
        $command=$event['command'];$args=$event['arguments'];
        $allowed=match($command) {
            'accept','decline'=>$kind==='client' && $state['phase']==='offered',
            'withdraw'=>$kind==='staff' && in_array($state['phase'],['offered','accepted'],true),
            'activate'=>$kind==='staff' && $state['phase']==='accepted',
            'pause'=>$kind==='staff' && $state['phase']==='running',
            'resume'=>$kind==='staff' && $state['phase']==='paused',
            'end'=>in_array($state['phase'],['running','paused'],true),
        };
        if (!$allowed) throw new InvalidArgumentException('This recurring decision is not allowed for the declared actor and phase.');
        if ($command==='accept' && !hash_equals($terms->fingerprint(),$args['terms_sha256'])) {
            throw new InvalidArgumentException('The client must accept these exact recurring invoice terms.');
        }
        $next=match($command) {
            'accept'=>'accepted','decline'=>'declined','withdraw'=>'withdrawn',
            'activate','resume'=>'running','pause'=>'paused','end'=>'ended',
        };
        if (array_key_exists('period_index',$args)) {
            $period=$terms->period($args['period_index']);
            if ($period['start']<=$date || ($command!=='end' && $state['last_boundary']!==null && $period['index']<$state['last_boundary'])) {
                throw new InvalidArgumentException('Choose a future whole period without reversing a scheduled boundary.');
            }
            if ($command==='end') {
                // Ending future billing overrides later planned boundaries without erasing history.
                $state['end_period']=$period['index'];
            } else {
                $state['last_boundary']=$period['index'];
                $state['boundaries'][]=['index'=>$period['index'],'phase'=>$next];
            }
        }
        $state['phase']=$next;$state['last_at']=$event['at'];++$state['revision'];
        return $state;
    }

    private static function event(mixed $id,mixed $command,mixed $actor,mixed $at,mixed $arguments): array
    {
        if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/',$id)
            || !is_string($command) || !in_array($command,['accept','decline','withdraw','activate','pause','resume','end'],true)
            || !is_string($actor) || !preg_match('/\A(staff|client):([1-9][0-9]{0,9})\z/',$actor,$parts)
            || (int)$parts[2]>2147483647 || !is_int($at) || $at<0 || $at>RecurringBillingTerms::MAX_EPOCH) {
            throw new InvalidArgumentException('Invalid recurring decision identity, command, actor or time.');
        }
        $fields=match($command) {
            'accept'=>['terms_sha256'],'decline','withdraw'=>['reason'],'activate'=>['period_index'],
            'pause','resume','end'=>['period_index','reason'],
        };
        self::keys($arguments,$fields);$canonical=[];
        foreach($fields as $field) {
            $value=$arguments[$field];
            if ($field==='terms_sha256' && (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/',$value))) {
                throw new InvalidArgumentException('Invalid accepted recurring terms fingerprint.');
            }
            if ($field==='period_index' && (!is_int($value) || $value<0 || $value>119999)) {
                throw new InvalidArgumentException('Invalid recurring boundary index.');
            }
            if ($field==='reason' && (!is_string($value) || strlen($value)>1000 || !preg_match('//u',$value)
                || !preg_match('/[^\s\p{Z}]/u',$value) || preg_match('/[\x00-\x08\x0b-\x1f\x7f]/',$value))) {
                throw new InvalidArgumentException('A recurring decision needs bounded literal reason text.');
            }
            $canonical[$field]=$value;
        }
        return ['id'=>$id,'command'=>$command,'actor'=>$actor,'at'=>$at,'arguments'=>$canonical];
    }

    private static function identity(mixed $id): void
    {
        if (!is_int($id) || $id<1 || $id>2147483647) throw new InvalidArgumentException('Invalid declared schedule creator.');
    }

    private static function keys(mixed $data,array $fields): void
    {
        if (!is_array($data) || count($data)!==count($fields)) throw new InvalidArgumentException('Invalid recurring schedule fields.');
        foreach($fields as $field) if(!array_key_exists($field,$data)) throw new InvalidArgumentException('Missing recurring schedule field.');
    }

    private static function encode(mixed $data): string
    {
        return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
}
