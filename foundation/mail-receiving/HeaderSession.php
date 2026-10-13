<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\MailReceiving;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }
use DirectoryTree\ImapEngine\Connection\ImapParser;
use DirectoryTree\ImapEngine\Connection\Streams\StreamInterface;
use DirectoryTree\ImapEngine\Connection\Responses\{Response,TaggedResponse,UntaggedResponse,ContinuationResponse};
use DirectoryTree\ImapEngine\Connection\Responses\Data\{ListData,ResponseCodeData};
use DirectoryTree\ImapEngine\Connection\Tokens\{Atom,Number,Literal,Token};

/** Maintained syntax parser behind a fixed read-only command sequence; no arbitrary command API. */
final class HeaderSession
{
    private ImapParser $parser;
    private BoundedTokenizer $tokenizer;
    private int $tag=0;
    private int $responses=0;
    private \Closure $authorize;
    public function __construct(private StreamInterface $stream, callable $authorize)
    {
        $this->authorize=\Closure::fromCallable($authorize);
        $this->tokenizer=new BoundedTokenizer($stream);
        $this->parser=new ImapParser($this->tokenizer);
    }

    private static function word(mixed $token): string
    {
        if(!$token instanceof Atom) throw new ReceivingFailure('protocol');
        return strtoupper($token->value);
    }
    private static function number(mixed $token, bool $zero=false): string
    {
        if(!$token instanceof Number || !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$token->value)
            || strlen($token->value)===10 && strcmp($token->value,'4294967295')>0 || !$zero && $token->value==='0') throw new ReceivingFailure('protocol');
        return $token->value;
    }
    private function next(): Response
    {
        if(++$this->responses>160) throw new ReceivingFailure('limit');
        $response=$this->parser->next();
        if(!$response instanceof Response || count($response->tokens())<1) throw new ReceivingFailure('protocol');
        return $response;
    }
    private function command(string $command): string
    {
        $tag='CWR'.str_pad((string)++$this->tag,3,'0',STR_PAD_LEFT);
        $this->stream->fwrite($tag.' '.$command."\r\n"); return $tag;
    }
    private function replies(string $tag, string $failure='protocol'): array
    {
        $rows=[];
        while(true) {
            $row=$this->next(); $tokens=$row->tokens();
            if($row instanceof TaggedResponse) {
                if(!($tokens[0]??null) instanceof Token || $tokens[0]->value!==$tag) throw new ReceivingFailure('protocol');
                $status=self::word($tokens[1]??null);
                if(in_array($status,['NO','BAD'],true)) throw new ReceivingFailure($failure);
                if($status!=='OK') throw new ReceivingFailure('protocol');
                $this->tokenizer->assertBoundary();
                $rows[]=$row; return $rows;
            }
            if(!$row instanceof UntaggedResponse || self::wordOrNumber($tokens[1]??null)==='BYE') throw new ReceivingFailure('connection');
            $rows[]=$row;
        }
    }
    private static function wordOrNumber(mixed $token): string
    {
        return $token instanceof Number ? self::number($token,true) : self::word($token);
    }
    private function capabilities(): array
    {
        $caps=[]; $found=false;
        foreach($this->replies($this->command('CAPABILITY')) as $row) {
            if($row instanceof TaggedResponse) continue;
            $tokens=$row->tokens(); $type=self::word($tokens[1]??null);
            if($type==='OK') continue;
            if($type!=='CAPABILITY' || $found) throw new ReceivingFailure('protocol');
            $found=true;
            foreach(array_slice($tokens,2) as $token) $caps[]=self::word($token);
        }
        if(!$found) throw new ReceivingFailure('protocol');
        return $caps;
    }
    private function authorize(): void
    {
        try { ($this->authorize)(); }
        catch(\Throwable) { throw new ReceivingFailure('access_changed'); }
        if($this->stream instanceof ReceivingStream) $this->stream->assertWithinBudget();
    }

    /** The caller must open a verified stream and serialize grants before running this session. */
    public function read(string $encryption, #[\SensitiveParameter] string $user, #[\SensitiveParameter] string $password): array
    {
        $started=hrtime(true);
        try {
            if(!in_array($encryption,['ssl','starttls'],true)) throw new ReceivingFailure('protocol');
            if($user==='' || $password==='' || strlen($user)>190 || strlen($password)>1024 || str_contains($user,"\0") || str_contains($password,"\0")) throw new ReceivingFailure('credential');
            $greeting=$this->next();
            if(!$greeting instanceof UntaggedResponse || self::word($greeting->tokens()[1]??null)!=='OK') throw new ReceivingFailure('protocol');
            $caps=$this->capabilities();
            if($encryption==='starttls') {
                if(!in_array('STARTTLS',$caps,true)) throw new ReceivingFailure('upgrade');
                $this->replies($this->command('STARTTLS'),'upgrade');
                $this->tokenizer->assertBoundary();
                if($this->stream->setSocketSetCrypto(true,null)!==true) throw new ReceivingFailure('tls');
                $caps=$this->capabilities();
            }
            if(!in_array($this->stream->meta()['crypto']['protocol']??'', ['TLSv1.2','TLSv1.3'],true)) throw new ReceivingFailure('tls');
            if(!in_array('AUTH=PLAIN',$caps,true)) throw new ReceivingFailure('mechanism');
            $tag=$this->command('AUTHENTICATE PLAIN'); $challenge=$this->next();
            if($challenge instanceof TaggedResponse && ($challenge->tokens()[0]->value??null)===$tag && in_array(self::word($challenge->tokens()[1]??null),['NO','BAD'],true)) throw new ReceivingFailure('authentication');
            if(!$challenge instanceof ContinuationResponse || count($challenge->tokens())!==1) throw new ReceivingFailure('protocol');
            $this->authorize();
            $this->stream->fwrite(base64_encode("\0".$user."\0".$password)."\r\n");
            $this->replies($tag,'authentication'); $this->authorize();
            [$exists,$validity]=$this->examine();
            $rows=$exists ? $this->fetch(max(1,$exists-19),$exists) : [];
            $this->authorize();
            if((hrtime(true)-$started)/1e9>20) throw new ReceivingFailure('timeout');
            try { $this->command('LOGOUT'); } catch(\Throwable) {}
            return ['folder'=>'INBOX','uidvalidity'=>$validity,'total'=>$exists,'messages'=>$rows];
        } catch(ReceivingFailure $e) { throw $e; }
        catch(\Throwable) { throw new ReceivingFailure('protocol'); }
        finally { $this->stream->close(); }
    }

    private function examine(): array
    {
        $exists=null; $validity=null; $readOnly=false;
        foreach($this->replies($this->command('EXAMINE "INBOX"')) as $row) {
            $tokens=$row->tokens();
            if($row instanceof UntaggedResponse && ($tokens[1]??null) instanceof Number) {
                $type=self::word($tokens[2]??null);
                if(count($tokens)!==3 || !in_array($type,['EXISTS','RECENT'],true)) throw new ReceivingFailure('protocol');
                $value=self::number($tokens[1],true);
                if($type==='EXISTS') {
                    if($exists!==null || (float)$value>PHP_INT_MAX) throw new ReceivingFailure('protocol');
                    $exists=(int)$value;
                }
                continue;
            }
            $type=self::word($tokens[1]??null);
            if($type==='FLAGS' && $row instanceof UntaggedResponse && count($tokens)===3 && $tokens[2] instanceof ListData) continue;
            if($type!=='OK') throw new ReceivingFailure('protocol');
            if(($tokens[2]??null) instanceof ResponseCodeData) {
                $code=$tokens[2]->tokens(); $name=self::word($code[0]??null);
                if($name==='UIDVALIDITY') {
                    if($validity!==null || count($code)!==2) throw new ReceivingFailure('protocol');
                    $validity=self::number($code[1]);
                }
                if($name==='READ-WRITE') throw new ReceivingFailure('protocol');
                if($name==='READ-ONLY' && $row instanceof TaggedResponse && count($code)===1) $readOnly=true;
            }
        }
        if($exists===null || $validity===null || !$readOnly) throw new ReceivingFailure('protocol');
        return [$exists,$validity];
    }

    private function fetch(int $first,int $last): array
    {
        $this->authorize(); $messages=[]; $uids=[];
        foreach($this->replies($this->command('FETCH '.$first.':'.$last.' (UID FLAGS RFC822.SIZE BODY.PEEK[HEADER])')) as $row) {
            if($row instanceof TaggedResponse) continue;
            $tokens=$row->tokens();
            if(count($tokens)!==4 || self::word($tokens[2]??null)!=='FETCH' || !$tokens[3] instanceof ListData) throw new ReceivingFailure('mailbox_changed');
            $sequence=(int)self::number($tokens[1]);
            if($sequence<$first || $sequence>$last || isset($messages[$sequence])) throw new ReceivingFailure('mailbox_changed');
            $parts=$tokens[3]->tokens(); $values=[];
            for($i=0;$i<count($parts);$i++) {
                $key=self::word($parts[$i]);
                if(isset($values[$key]) || !in_array($key,['UID','FLAGS','RFC822.SIZE','BODY'],true)) throw new ReceivingFailure('protocol');
                $value=$parts[++$i]??null;
                if($key==='BODY') {
                    if(!$value instanceof ResponseCodeData || count($value->tokens())!==1 || self::word($value->tokens()[0])!=='HEADER') throw new ReceivingFailure('protocol');
                    $value=$parts[++$i]??null;
                    if(!$value instanceof Literal) throw new ReceivingFailure('protocol');
                    $values[$key]=HeaderDecoder::decode($value->value);
                } elseif($key==='FLAGS') {
                    if(!$value instanceof ListData) throw new ReceivingFailure('protocol');
                    $values[$key]=[];
                    foreach($value->tokens() as $flag) $values[$key][]=self::word($flag);
                } else $values[$key]=self::number($value,$key==='RFC822.SIZE');
            }
            if(count($values)!==4 || isset($uids[$values['UID']])) throw new ReceivingFailure('mailbox_changed');
            $uids[$values['UID']]=true;
            $messages[$sequence]=['uid'=>$values['UID'],'seen'=>in_array('\\SEEN',$values['FLAGS'],true),'bytes'=>$values['RFC822.SIZE']]+$values['BODY'];
        }
        if(count($messages)!==$last-$first+1) throw new ReceivingFailure('mailbox_changed');
        krsort($messages,SORT_NUMERIC);
        return array_values($messages);
    }
}
