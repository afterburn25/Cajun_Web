<?php
/** Pure commercial review and consent. No authentication, storage, invoice or provider authority. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers_document.php';
require_once CWP_CODE_ROOT.'/includes/foundation/billing-calendar/BillingCalendar.php';

use CajunWebPro\Foundation\BillingCalendar\BillingCalendar;

/** A selected project is an instruction, not proof of ownership or a newly created project. */
function hosting_checkout_project(array $project): array {
    if (($project['mode']??null)==='existing') {
        service_plans_keys($project,['mode','id']);service_plans_actor($project['id']);
        return ['mode'=>'existing','id'=>$project['id']];
    }
    service_plans_keys($project,['mode']);
    if ($project['mode']!=='new-hosting') throw new InvalidArgumentException('Choose an existing project or a new dedicated hosting project.');
    return ['mode'=>'new-hosting'];
}

final readonly class HostingCheckoutReview {
    public const MAX_BYTES=600000;
    private function __construct(private string $bytes,private HostingCatalogPublication $publication) {}

    /** Caller loads the authenticated primary client and project under its own native authority guard. */
    public static function create(string $requestId,HostingCatalogPublication $publication,int $clientId,array $project,int $at,array $current): self {
        $publication->assertCurrent($current,$at);
        return self::fromJson(service_plans_json(self::facts($requestId,$publication,$clientId,$project,$at)),$publication);
    }
    /** Historical validation uses exact retained publication bytes, never a new clock or login. */
    public static function fromJson(string $bytes,HostingCatalogPublication $publication): self {
        if (strlen($bytes)>self::MAX_BYTES) throw new InvalidArgumentException('Hosting checkout review exceeds its bound.');
        $d=json_decode($bytes,true,48,JSON_THROW_ON_ERROR);
        if (!is_array($d)) throw new InvalidArgumentException('Invalid hosting checkout review.');
        service_plans_keys($d,['format','kind','request_id','publication_id','publication_sha256','plan','client_id','project','quantity','reviewed_at','expires_at','billing','effects']);
        $expected=self::facts($d['request_id'],$publication,$d['client_id'],$d['project'],$d['reviewed_at']);
        if (service_plans_json($expected)!==$bytes) throw new InvalidArgumentException('Hosting checkout terms differ from the exact reviewed publication and calendar.');
        return new self($bytes,$publication);
    }
    private static function facts(string $requestId,HostingCatalogPublication $publication,int $clientId,array $project,int $at): array {
        service_plans_id($requestId);service_plans_actor($clientId);service_plans_time($at);
        $project=hosting_checkout_project($project);$p=$publication->data();$policy=$publication->checkoutPolicy();
        if ($policy===null) throw new InvalidArgumentException('This offer supports a request only, not invoice checkout.');
        $mapping=provider_plan_catalog_document($p['mapping_json']);
        if ($at<$p['created_at'] || $at>=$mapping['expires_at']) throw new InvalidArgumentException('Review is outside the retained publication lifetime.');
        $plan=ServicePlanDocument::fromJson($p['plan_json']);$model=$plan->model();
        $zone=new DateTimeZone($policy['timezone']);
        $date=(new DateTimeImmutable('@'.$at))->setTimezone($zone)->format('Y-m-d');
        $calendar=new BillingCalendar($date,$model->cadence,$policy['alignment']);
        $first=$calendar->period(0); // Preserve the existing end-exclusive, original-anchor calendar.
        // Derive tomorrow as a calendar date first. Today's midnight may not exist after a DST jump.
        $tomorrow=(new DateTimeImmutable($date.'T00:00:00',new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
        $midnight=(new DateTimeImmutable($tomorrow.'T00:00:00',$zone))->getTimestamp();
        $expires=min($at+$policy['review_ttl_seconds'],$midnight,$mapping['expires_at']);
        service_plans_time($expires);
        if ($expires<=$at) throw new InvalidArgumentException('The checkout review has no remaining lifetime.');
        return ['format'=>2,'kind'=>'hosting-checkout-review','request_id'=>$requestId,
            'publication_id'=>$p['id'],'publication_sha256'=>$publication->fingerprint(),
            'plan'=>['id'=>$plan->data()['id'],'revision'=>$plan->revision(),'sha256'=>$plan->fingerprint(),'json'=>$plan->json()],
            'client_id'=>$clientId,'project'=>$project,'quantity'=>1,'reviewed_at'=>$at,'expires_at'=>$expires,
            'billing'=>['timezone'=>$policy['timezone'],'alignment'=>$policy['alignment'],'anchor'=>$date,
                'cadence'=>$model->cadence,'currency'=>$model->currency,'amount_cents'=>$model->priceAmount,'setup_cents'=>0,
                'first_period'=>['index'=>0,'start'=>$first['start'],'end'=>$first['end'],'due'=>$date,'next_start'=>$first['end']],
                'due_after_days'=>0,'collection'=>'invoice-only'],
            'effects'=>['agreement'=>'activate-after-consent','first_invoice'=>'unpaid','automatic_charge'=>false,
                'provider_activation'=>false,'requires_verified_payment'=>true,'requires_owner_provisioning'=>true]];
    }
    public function json(): string { return $this->bytes; }
    public function fingerprint(): string { return hash('sha256',$this->bytes); }
    public function data(): array { return json_decode($this->bytes,true,48,JSON_THROW_ON_ERROR); }

    /** Inputs must be freshly authenticated/locked native facts. Passing values here never grants authority. */
    public function assertForConfirmation(HostingCatalogPublication $publication,array $current,int $primaryClientId,?int $lockedProjectId,?int $lockedProjectClientId,int $now): void {
        service_plans_actor($primaryClientId);service_plans_time($now);$d=$this->data();
        if ($d['client_id']!==$primaryClientId || $publication->data()['id']!==$d['publication_id']
            || !hash_equals($publication->fingerprint(),$d['publication_sha256'])) throw new RuntimeException('Checkout review is unavailable for this client or offer.');
        $publication->assertCurrent($current,$now);
        $date=(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone($d['billing']['timezone']))->format('Y-m-d');
        if ($now<$d['reviewed_at'] || $now>=$d['expires_at'] || $date!==$d['billing']['anchor']) throw new RuntimeException('These details changed. Reload and review the plan before trying again.');
        if ($d['project']['mode']==='existing') {
            if ($lockedProjectId!==$d['project']['id'] || $lockedProjectClientId!==$primaryClientId) throw new RuntimeException('The selected project is unavailable for this client.');
        } elseif ($lockedProjectId!==null || $lockedProjectClientId!==null) {
            throw new RuntimeException('A new hosting project must be created from the accepted instruction, not substituted.');
        }
    }
    /** Safe display facts only. Never serialize data(), plan.json, mapping or authority context to the browser. */
    public function historicalProjection(): array {
        $d=$this->data();return ['request_id'=>$d['request_id'],'review_sha256'=>$this->fingerprint(),
            'offer'=>$this->publication->historicalProjection(),'project'=>$d['project'],'billing'=>$d['billing'],
            'effects'=>$d['effects'],'reviewed_at'=>$d['reviewed_at'],'expires_at'=>$d['expires_at']];
    }
    public function publicProjection(array $current,int $primaryClientId,?int $lockedProjectId,?int $lockedProjectClientId,int $now): array {
        $this->assertForConfirmation($this->publication,$current,$primaryClientId,$lockedProjectId,$lockedProjectClientId,$now);
        return $this->historicalProjection();
    }
}

final readonly class HostingCheckoutConsent {
    public const MAX_BYTES=700000;
    private function __construct(private string $bytes,private HostingCheckoutReview $review) {}
    /** One explicit confirmation of the exact displayed composite terms; this creates no financial record. */
    public static function create(HostingCheckoutReview $review,string $presentedHash,bool $confirmed,HostingCatalogPublication $publication,array $current,int $primaryClientId,?int $lockedProjectId,?int $lockedProjectClientId,int $at): self {
        hosting_catalog_hash($presentedHash);
        if (!$confirmed || !hash_equals($review->fingerprint(),$presentedHash)) throw new InvalidArgumentException('Confirm the exact service and invoice terms.');
        $review->assertForConfirmation($publication,$current,$primaryClientId,$lockedProjectId,$lockedProjectClientId,$at);
        return self::fromJson(service_plans_json(['format'=>2,'kind'=>'hosting-checkout-consent','request_id'=>$review->data()['request_id'],
            'client_id'=>$primaryClientId,'review_sha256'=>$review->fingerprint(),'review_json'=>$review->json(),'accepted_at'=>$at]),$publication);
    }
    /** Completed historical replay does not require the offer still to be available. Caller checks ownership. */
    public static function fromJson(string $bytes,HostingCatalogPublication $publication): self {
        if (strlen($bytes)>self::MAX_BYTES) throw new InvalidArgumentException('Hosting checkout consent exceeds its bound.');
        $d=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($d)) throw new InvalidArgumentException('Invalid hosting checkout consent.');
        service_plans_keys($d,['format','kind','request_id','client_id','review_sha256','review_json','accepted_at']);
        if ($d['format']!==2 || $d['kind']!=='hosting-checkout-consent' || !is_string($d['review_json'])) throw new InvalidArgumentException('Invalid hosting checkout consent format.');
        $review=HostingCheckoutReview::fromJson($d['review_json'],$publication);$r=$review->data();
        service_plans_time($d['accepted_at']);hosting_catalog_hash($d['review_sha256']);
        $acceptedDate=(new DateTimeImmutable('@'.$d['accepted_at']))->setTimezone(new DateTimeZone($r['billing']['timezone']))->format('Y-m-d');
        if ($d['request_id']!==$r['request_id'] || $d['client_id']!==$r['client_id'] || !hash_equals($d['review_sha256'],$review->fingerprint())
            || $d['accepted_at']<$r['reviewed_at'] || $d['accepted_at']>=$r['expires_at'] || $acceptedDate!==$r['billing']['anchor']) throw new InvalidArgumentException('Consent does not bind the exact unexpired checkout review.');
        $canonical=['format'=>2,'kind'=>'hosting-checkout-consent','request_id'=>$r['request_id'],'client_id'=>$r['client_id'],
            'review_sha256'=>$review->fingerprint(),'review_json'=>$review->json(),'accepted_at'=>$d['accepted_at']];
        if (service_plans_json($canonical)!==$bytes) throw new InvalidArgumentException('Noncanonical hosting checkout consent.');
        return new self($bytes,$review);
    }
    public function json(): string { return $this->bytes; }
    public function fingerprint(): string { return hash('sha256',$this->bytes); }
    public function data(): array { return json_decode($this->bytes,true,64,JSON_THROW_ON_ERROR); }
    public function review(): HostingCheckoutReview { return $this->review; }
    public function assertClient(int $authenticatedClientId): void {
        service_plans_actor($authenticatedClientId);
        if ($authenticatedClientId!==$this->data()['client_id']) throw new RuntimeException('Hosting checkout history unavailable.');
    }
    /** Replay excludes the actual acceptance instant, never the reviewed date, price, project or identities. */
    public function inputHash(): string {
        $d=$this->data();return hash('sha256',service_plans_json(['request_id'=>$d['request_id'],'client_id'=>$d['client_id'],'review_sha256'=>$d['review_sha256']]));
    }
}
