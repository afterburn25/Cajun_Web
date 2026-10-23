<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-submission/SubmissionTransport.php';
use CajunWebPro\Foundation\MailCompose\DraftContent;
use CajunWebPro\Foundation\MailSubmission\{SubmissionSnapshot,PreparedMime,SubmissionTransport,SmtpSession};
use CajunWebPro\Foundation\MailTransport\{ProbeChannel,ProbeFailure};
$checks=[];$complete=false;
function check(string $name,bool $pass):void {global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}

/** Synthetic transcript, not a socket/TLS or production channel implementation. */
final class Transcript implements ProbeChannel
{
    public array $written=[];
    public array $pending=[];
    public bool $closed=false;
    public int $upgrades=0;
    public int $recipients=0;
    public bool $data=false;
    public string $body='';
    public int $terminators=0;
    private bool $auth=false;
    private int $login=0;
    public function __construct(public array $options=[],public bool $tls=true)
    {$this->pending=$options['greeting']??['220 fixture'];}
    public function line():string
    {
        if(!$this->pending)throw new ProbeFailure('connection');
        $next=array_shift($this->pending);if($next instanceof Throwable)throw $next;
        return $next;
    }
    public function write(#[SensitiveParameter] string $wire):void
    {
        $this->written[]=$wire;$line=substr($wire,0,-2);
        if($this->data) {
            if($line==='.') {
                $this->terminators++;$this->data=false;
                if(isset($this->options['end_error']))throw $this->options['end_error'];
                $this->pending=$this->options['final']??['250 accepted'];
            } else {
                if(isset($this->options['body_error']))throw $this->options['body_error'];
                $this->body.=(str_starts_with($line,'..')?substr($line,1):$line)."\r\n";
            }
            return;
        }
        if($this->auth) {$this->auth=false;$this->pending=$this->options['authentication']??['235 authenticated'];return;}
        if($this->login>0) {$this->pending=$this->login===1?['334 Password']:($this->options['authentication']??['235 authenticated']);$this->login=$this->login===1?2:0;return;}
        if(str_starts_with($line,'EHLO '))$this->pending=$this->options['hello']??['250-fixture','250-STARTTLS','250 AUTH '.($this->options['mechanisms']??'PLAIN LOGIN')];
        elseif($line==='STARTTLS')$this->pending=$this->options['upgrade']??['220 ready'];
        elseif($line==='AUTH PLAIN') {$this->pending=$this->options['challenge']??['334 '];$this->auth=true;}
        elseif($line==='AUTH LOGIN') {$this->pending=['334 Username'];$this->login=1;}
        elseif(str_starts_with($line,'MAIL FROM:'))$this->pending=$this->options['sender']??['250 sender'];
        elseif(str_starts_with($line,'RCPT TO:')) {$this->recipients++;$this->pending=$this->options['recipient'.$this->recipients]??['250 recipient'];}
        elseif($line==='DATA') {$this->pending=$this->options['data_reply']??['354 continue'];$this->data=$this->pending===['354 continue'];}
        else throw new RuntimeException('Unexpected synthetic command.');
    }
    public function startTls():void {$this->upgrades++;if(isset($this->options['tls_error']))throw $this->options['tls_error'];$this->tls=!isset($this->options['no_tls']);}
    public function isTls():bool {return $this->tls;}
    public function close():void {$this->closed=true;if(isset($this->options['close_error']))throw $this->options['close_error'];}
}
function message(?DraftContent $content=null):PreparedMime
{
    return PreparedMime::fromSnapshot(new SubmissionSnapshot(str_repeat('1',32),7,str_repeat('2',32),3,str_repeat('a',32),4,'sender@example.invalid',
        $content??new DraftContent(['first@example.invalid'],['second@example.invalid'],['private@example.invalid'],'Résumé','Literal body'),1791964800));
}
function submit(Transcript $c,?PreparedMime $m=null,?Closure $authorize=null,string $mode='ssl',string $user='fixture-user',string $password='fixture-password')
{return (new SubmissionTransport)->submit($m??message(),str_repeat('b',32),$c,$mode,$user,$password,$authorize??static function(){});}
function has(Transcript $c,string $wire):bool {return in_array($wire."\r\n",$c->written,true);}
try {
    $m=message();$c=new Transcript();$o=submit($c,$m);
    check('A verified final250 means server acceptance only',$o->status==='accepted'&&$o->smtpCode===250&&$o->phase==='confirmation'&&$c->closed);
    check('Outcome binds submission, private snapshot, MIME and one exact attempt',$o->submissionId===$m->snapshot->id&&$o->snapshotFingerprint===$m->snapshot->fingerprint&&$o->mimeSha256===$m->sha256&&$o->attemptId===str_repeat('b',32));
    check('Envelope includes To Cc and Bcc exactly once',$c->recipients===3&&has($c,'RCPT TO:<first@example.invalid>')&&has($c,'RCPT TO:<second@example.invalid>')&&has($c,'RCPT TO:<private@example.invalid>'));
    check('Delivered DATA preserves prepared bytes with no private Bcc header',$c->body===$m->bytes&&!str_contains($c->body,'private@example.invalid'));
    check('A single session transmits one DATA and one terminator',count(array_filter($c->written,fn($v)=>$v==="DATA\r\n"))===1&&$c->terminators===1);
    foreach([1,2,3] as $i) {
        $c=new Transcript(['recipient'.$i=>['550 hidden address rejected']]);$o=submit($c);
        check('Recipient rejection '.$i.' aborts every recipient before DATA',$o->status==='not_submitted'&&$o->phase==='recipient'&&!has($c,'DATA')&&$c->terminators===0&&$c->recipients===$i&&$c->closed);
    }
    foreach([450,550] as $code) {
        $c=new Transcript(['final'=>[$code.' response includes secret']]);$o=submit($c);
        check('Explicit final'.$code.' records rejection without retransmission',$o->status==='rejected'&&$o->reason==='server_rejected'&&$o->smtpCode===$code&&$c->terminators===1);
    }
    foreach([[],['250-incomplete'],['250-continued','550 inconsistent'],['garbled'],['251 unexpected'],['250 '.str_repeat('x',507)],['250 accepted' . "\nBcc: secret"],[new ProbeFailure('timeout')]] as $i=>$final) {
        $c=new Transcript(['final'=>$final]);$o=submit($c);
        check('Ambiguous final response '.$i.' remains unknown and never retries',$o->status==='unknown'&&$o->phase==='confirmation'&&$c->terminators===1&&$c->closed);
    }
    foreach([new ProbeFailure('connection'),new ProbeFailure('timeout'),new InvalidArgumentException('secret-data'),new RuntimeException('secret-data')] as $i=>$error) {
        $c=new Transcript(['end_error'=>$error]);$o=submit($c);
        check('Terminator write failure '.$i.' remains unknown including unexpected exceptions',$o->status==='unknown'&&$o->smtpCode===null&&!str_contains(json_encode($o),'secret-data')&&$c->terminators===1);
    }
    $c=new Transcript(['body_error'=>new ProbeFailure('connection')]);$o=submit($c);
    check('Body interruption before terminator is not submitted',$o->status==='not_submitted'&&$o->phase==='data'&&$c->terminators===0&&$c->closed);
    $c=new Transcript(['close_error'=>new RuntimeException('closing secret')]);$o=submit($c);
    check('Close failure cannot undo verified server acceptance',$o->status==='accepted'&&$c->closed);
    foreach(['greeting'=>['421 unavailable'],'hello'=>['500 no EHLO'],'authentication'=>['535 bad credentials'],'sender'=>['550 sender denied'],'data_reply'=>['554 unavailable']] as $key=>$reply) {
        $c=new Transcript([$key=>$reply]);$o=submit($c);
        check('Known '.$key.' failure cannot submit message content',$o->status==='not_submitted'&&$c->body===''&&$c->terminators===0&&$c->closed);
    }
    $c=new Transcript(['hello'=>['500 no EHLO']]);submit($c);
    check('EHLO refusal does not fall back to HELO or authenticate',count($c->written)===1&&!has($c,'AUTH PLAIN'));
    foreach(['ssl','starttls'] as $mode) {
        $c=new Transcript([], $mode==='ssl');$o=submit($c,mode:$mode);
        check($mode.' requires TLS and uses maintained authentication',$o->status==='accepted'&&has($c,'AUTH PLAIN')&&$c->upgrades===($mode==='ssl'?0:1));
        if($mode==='starttls')check('STARTTLS clears and renegotiates EHLO capabilities',count(array_filter($c->written,fn($v)=>str_starts_with($v,'EHLO ')))===2);
    }
    foreach([
        ['hello'=>['250-fixture','250 AUTH PLAIN']],['upgrade'=>['454 unavailable']],
        ['tls_error'=>new ProbeFailure('tls')],['no_tls'=>true],
    ] as $i=>$options) {
        $c=new Transcript($options,false);$o=submit($c,mode:'starttls');
        check('TLS downgrade or upgrade failure '.$i.' exposes no credentials',$o->status==='not_submitted'&&!has($c,'AUTH PLAIN')&&$c->body==='');
    }
    foreach(['CRAM-MD5','XOAUTH2',''] as $i=>$mechanisms) {
        $c=new Transcript(['mechanisms'=>$mechanisms]);$o=submit($c);
        check('Unsupported mechanism '.$i.' does not fall back or expose credentials',$o->status==='not_submitted'&&$o->reason==='mechanism'&&count($c->written)===1);
    }
    $c=new Transcript(['mechanisms'=>'LOGIN']);$o=submit($c);
    check('Advertised LOGIN is supported without implicit mechanism fallback',$o->status==='accepted'&&has($c,'AUTH LOGIN')&&!has($c,'AUTH PLAIN'));
    foreach([['', 'pass'],['user',''],[str_repeat('u',191),'pass'],['user',str_repeat('p',1025)],["bad\0user",'pass'],['user',"bad\0pass"]] as $i=>[$user,$password]) {
        $c=new Transcript();$o=submit($c,user:$user,password:$password);
        check('Malformed credentials '.$i.' are refused before channel reads/writes',$o->status==='not_submitted'&&$o->reason==='credential'&&!$c->written&&$c->pending===['220 fixture']&&$c->closed);
    }
    $c=new Transcript();$o=submit($c,user:str_repeat('u',190),password:str_repeat('p',1024));
    check('Maximum stored credentials fit bounded maintained PLAIN encoding',$o->status==='accepted');
    foreach(['sender','recipient1','data_reply'] as $kind) {
        $c=new Transcript([$kind=>['299 unexpected']]);$o=submit($c);
        check('Unexpected positive '.$kind.' reply is a protocol refusal, not server rejection',$o->status==='not_submitted'&&$o->reason==='protocol'&&$c->terminators===0);
    }
    foreach(['',"\n",'.',"...\n\n",str_repeat('é',10000)] as $i=>$body) {
        $prepared=message(new DraftContent([],[],['private@example.invalid'],'',$body));$c=new Transcript();$o=submit($c,$prepared);
        check('Empty/Bcc-only/literal plaintext case '.$i.' retains exact prepared DATA',$o->status==='accepted'&&$c->body===$prepared->bytes&&$c->recipients===1);
    }
    foreach([false,true] as $tls) {
        $c=new Transcript([], $tls);$o=submit($c,mode:$tls?'starttls':'ssl');
        check('Initial TLS mode mismatch '.(int)$tls.' is refused before I/O',$o->status==='not_submitted'&&$o->reason==='tls'&&!$c->written);
    }
    $c=new Transcript();$o=submit($c,mode:'none');
    check('Plaintext mode cannot reach protocol I/O',$o->reason==='tls'&&!$c->written);
    foreach(['initial','credential','sender','recipient','data','terminator'] as $where) {
        $c=new Transcript();$seen=0;
        $auth=function()use($c,$where,&$seen):void {
            $seen++;
            $should=match($where) {
                'initial'=>$seen===1,'credential'=>has($c,'AUTH PLAIN'),
                'sender'=>has($c,base64_encode("\0fixture-user\0fixture-password")),
                'recipient'=>has($c,'MAIL FROM:<sender@example.invalid>'),
                'data'=>$c->recipients===3,'terminator'=>$c->body!=='',
            };
            if($should)throw new ProbeFailure('access_changed');
        };
        $o=submit($c,authorize:$auth);
        check('Current authority loss before '.$where.' prevents submission',$o->status==='not_submitted'&&$o->reason==='access_changed'&&$c->terminators===0&&$c->closed);
    }
    foreach([['220'."\t".'bad'],['220-continued','250 inconsistent'],array_fill(0,33,'220-more'),['220 '.str_repeat('x',507)],['220 '."\0".'bad']] as $i=>$greeting) {
        $c=new Transcript(['greeting'=>$greeting]);$o=submit($c);
        check('Malformed or excessive greeting '.$i.' fails before commands',$o->status==='not_submitted'&&!$c->written&&$c->closed);
    }
    $to=[];for($i=0;$i<20;$i++)$to[]='recipient'.$i.'@example.invalid';
    $large=message(new DraftContent($to,[],[],'Maximum',str_repeat('x',20000)));$c=new Transcript();$o=submit($c,$large);
    check('Twenty recipients and maximum plaintext body fit bounded DATA without mutation',$o->status==='accepted'&&$c->recipients===20&&$c->body===$large->bytes&&$c->terminators===1);
    $c=new Transcript();$session=new SmtpSession($c,static function(){});$session->begin();$session->hello();$session->authenticate('u','p');$session->mail('sender@example.invalid');$session->recipient('first@example.invalid');$session->data("Subject: dots\r\n\r\n.first\r\n..second");
    check('Maintained DATA primitive performs dot stuffing and fixture recovers literal lines',has($c,'..first')&&has($c,'...second')&&$c->body==="Subject: dots\r\n\r\n.first\r\n..second\r\n");$session->close();
    $c=new Transcript(['final'=>['550 secret-password recipient@example.invalid']]);ob_start();$o=submit($c);$output=ob_get_clean();
    check('Protocol diagnostics never output or retain server text/addresses',$output===''&&!str_contains(json_encode($o),'secret-password')&&!str_contains(json_encode($o),'recipient@example.invalid'));
    $c=new Transcript();$s=new SmtpSession($c,static function(){});$s->do_debug=4;ob_start();$s->begin();$s->hello();$out=ob_get_clean();$s->close();
    check('Even an accidental debug setting produces no protocol output',$out==='');
    foreach(['write_bytes','total_bytes','write_count','reply_count'] as $limit) {
        $c=new Transcript();$s=new SmtpSession($c,static function(){});$failed=false;
        try {
            if($limit==='write_bytes')$s->client_send(str_repeat('x',2047)."\r\n");
            // A test sink accepts each bounded write without adding SMTP replies.
            else {
                $sink=new class implements ProbeChannel {
                    public function line():string{return '250 ok';}
                    public function write(#[SensitiveParameter] string $data):void{}
                    public function startTls():void{}
                    public function isTls():bool{return true;}
                    public function close():void{}
                };
                $s=new SmtpSession($sink,static function(){});
                if($limit==='total_bytes')for($i=0;$i<520;$i++)$s->client_send(str_repeat('x',2046)."\r\n");
                elseif($limit==='write_count')for($i=0;$i<32769;$i++)$s->client_send("x\r\n");
                else for($i=0;$i<161;$i++)$s->begin();
            }
        }catch(ProbeFailure $e){$failed=$e->getMessage()==='limit';}finally{$s->close();}
        check('Session enforces '.$limit.' cap',$failed);
    }
    $c=new Transcript();$s=new SmtpSession($c,static function(){});try{$s->connect('untrusted.example.invalid');$refused=false;}catch(LogicException){$refused=true;}$s->close();
    check('Inherited default socket opener is disabled',$refused&&!$c->written);
    $c=new Transcript();try{(new SubmissionTransport)->submit($m,str_repeat('b',32)."\n",$c,'ssl','u','p',static function(){});$bad=false;}catch(InvalidArgumentException){$bad=true;}
    check('Malformed attempt identity closes without I/O',$bad&&$c->closed&&!$c->written);
    $complete=true;
} finally {
    if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$complete,'php'=>PHP_VERSION,'scope'=>'synthetic maintained SMTP transcript; no socket, TLS, storage or application acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." maintained SMTP session checks\n";
