<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
use CajunWebPro\Foundation\MailReceiving\{ReadSession,ReadProfile,ReceivingFailure};
use DirectoryTree\ImapEngine\Connection\Streams\FakeStream;
final class MessageFixture extends FakeStream {
 private $memory;
 public function __construct(string $bytes) {$this->memory=fopen('php://memory','w+b');fwrite($this->memory,$bytes);rewind($this->memory);$this->open();$this->meta['crypto']['protocol']='TLSv1.3';}
 public function fgets(): string|false {return fgets($this->memory);}
 public function read(int $length): string|false {return $length===0?'':fread($this->memory,$length);}
 public function close(): void {if(is_resource($this->memory))fclose($this->memory);parent::close();}
 public function commands(): array {return $this->written;}
}
function prefix(string $validity='7',int $exists=2):string {return "* OK synthetic\r\n* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nCWR001 OK done\r\n+ \r\nCWR002 OK authenticated\r\n* $exists EXISTS\r\n* OK [UIDVALIDITY $validity] stable\r\nCWR003 OK [READ-ONLY] examined\r\n";}
function metadata(string $uid,int $bytes,string $tag='004'):string {return "* 2 FETCH (UID $uid FLAGS (\\Seen) RFC822.SIZE $bytes)\r\nCWR$tag OK done\r\n";}
function payload(string $uid,string $raw,?int $bytes=null):string {$bytes??=strlen($raw);return "* 2 FETCH (UID $uid FLAGS (\\Seen) RFC822.SIZE $bytes BODY[] {".strlen($raw)."}\r\n".$raw.")\r\nCWR005 OK done\r\n";}
function runCase(string $wire,int $revoke=0,string $validity='7',string $uid='45'):array {$s=new MessageFixture($wire);$calls=0;try {$out=(new ReadSession($s,function()use(&$calls,$revoke){if(++$calls===$revoke)throw new RuntimeException('secret');},ReadProfile::Message))->message('ssl','synthetic@example.net','synthetic password',$validity,$uid);return[$out,$s,$calls];}catch(ReceivingFailure $e){return[$e,$s,$calls];}}
$checks=[];
function check(string $name,bool $passed):void {global$checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function reject(string $name,string $wire,string $code='protocol'):void {[$out,$s]=runCase($wire);check($name,$out instanceof ReceivingFailure && $out->getMessage()===$code && !$out->getPrevious() && !$s->opened());}
$raw="Subject: one\r\nContent-Type: text/plain\r\n\r\nActual body";
$size=strlen($raw);$wire=prefix().metadata('45',$size).payload('45',$raw);
[$out,$s,$calls]=runCase($wire);
check('exact immutable identity and full raw bytes returned',is_array($out) && $out['uid']==='45' && $out['uidvalidity']==='7' && $out['folder']==='INBOX' && $out['raw']===$raw && $out['bytes']===(string)$size);
check('observed flags retained without any flag write',is_array($out) && $out['seen']===true && !array_filter($s->commands(),fn($c)=>preg_match('/STORE|SELECT|APPEND|EXPUNGE/',$c)));
check('size preflight and exact UID body commands are fixed',$s->commands()[4]==="CWR004 UID FETCH 45 (UID FLAGS RFC822.SIZE)\r\n" && $s->commands()[5]==="CWR005 UID FETCH 45 (UID FLAGS RFC822.SIZE BODY.PEEK[])\r\n");
check('current authority checked at all five session boundaries',$calls===5);
check('successful raw message closes stream',!$s->opened());
[$out,$s]=runCase(prefix('8').metadata('45',$size).payload('45',$raw));
check('changed UIDVALIDITY never requests any UID', $out instanceof ReceivingFailure && $out->getMessage()==='mailbox_changed' && !array_filter($s->commands(),fn($c)=>str_contains($c,'UID FETCH')));
[$out,$s]=runCase(prefix('7',0));
check('empty current mailbox cannot resolve an old identity',$out instanceof ReceivingFailure && $out->getMessage()==='mailbox_changed' && !array_filter($s->commands(),fn($c)=>str_contains($c,'UID FETCH')));
[$out,$s]=runCase(prefix().metadata('45',262145));
check('oversize preflight prevents body command',$out instanceof ReceivingFailure && $out->getMessage()==='limit' && !array_filter($s->commands(),fn($c)=>str_contains($c,'BODY.PEEK')));
reject('deleted UID absent at preflight',prefix()."CWR004 OK no rows\r\n",'mailbox_changed');
reject('different UID at preflight',prefix().metadata('46',$size),'mailbox_changed');
reject('different UID at body response',prefix().metadata('45',$size).payload('46',$raw),'mailbox_changed');
reject('changed size between requests',prefix().metadata('45',$size+1).payload('45',$raw),'mailbox_changed');
reject('literal byte count must equal size metadata',prefix().metadata('45',$size+1).payload('45',$raw,$size+1),'mailbox_changed');
reject('no body response never returns partial message',prefix().metadata('45',$size)."CWR005 OK no rows\r\n",'mailbox_changed');
reject('duplicate preflight rows refused',prefix().str_replace('CWR004 OK done',"* 1 FETCH (UID 45 FLAGS () RFC822.SIZE $size)\r\nCWR004 OK done",metadata('45',$size)),'mailbox_changed');
reject('duplicate body rows refused',prefix().metadata('45',$size).str_replace('CWR005 OK done',"* 1 FETCH (UID 45 FLAGS () RFC822.SIZE $size BODY[] {0}\r\n)\r\nCWR005 OK done",payload('45',$raw)),'mailbox_changed');
reject('body received in metadata-only response refused',prefix().str_replace('CWR005','CWR004',payload('45',$raw)));
reject('header section cannot substitute for complete MIME',str_replace('BODY[]','BODY[HEADER]',$wire));
reject('partial origin response cannot substitute for complete MIME',str_replace('BODY[]','BODY[]<0>',$wire));
reject('oversized literal refused before raw payload',prefix().metadata('45',1)."* 2 FETCH (UID 45 FLAGS () RFC822.SIZE 1 BODY[] {262145}\r\n",'limit');
reject('truncated literal cannot succeed',prefix().metadata('45',$size).substr(payload('45',$raw),0,-35));
reject('expunge between exact UID requests fails closed',prefix().metadata('45',$size)."* 1 EXPUNGE\r\n".payload('45',$raw),'mailbox_changed');
reject('response beyond captured sequence range refused',str_replace('* 2 FETCH','* 3 FETCH',$wire),'mailbox_changed');
reject('duplicate metadata keys refused',str_replace('UID 45 FLAGS','UID 45 UID 45 FLAGS',$wire));
reject('leading-zero returned UID refused',str_replace('UID 45 FLAGS','UID 045 FLAGS',$wire));
reject('out-of-range returned size refused',str_replace('RFC822.SIZE '.$size,'RFC822.SIZE 4294967296',$wire));
reject('non-read-only mailbox refused',str_replace('[READ-ONLY]','[READ-WRITE]',$wire));
reject('wrong completion tag refused',str_replace('CWR005 OK','CWR099 OK',$wire));
reject('failed final tag does not expose raw message',str_replace('CWR005 OK done','CWR005 NO private details',$wire));
for($i=1;$i<=5;$i++) {[$out,$s]=runCase($wire,$i);check('revocation at session boundary '.$i.' refuses all content',$out instanceof ReceivingFailure && $out->getMessage()==='access_changed' && !$s->opened());}
foreach(['0','01','4294967296','-1',"45\r\nSTORE ALL",''] as $uid) {[$out,$s]=runCase($wire,0,'7',$uid);check('invalid request UID '.json_encode($uid).' rejected before commands',$out instanceof ReceivingFailure && $out->getMessage()==='protocol' && $s->commands()===[] && !$s->opened());}
$big=str_repeat('a',262144);[$out]=runCase(prefix().metadata('45',262144).payload('45',$big));check('exact maximum raw literal supported in isolated parser fixture',is_array($out) && strlen($out['raw'])===262144);
[$out]=runCase(prefix('4294967295').metadata('4294967295',$size).payload('4294967295',$raw),0,'4294967295','4294967295');check('maximum uint32 identity stays exact text',is_array($out) && $out['uid']==='4294967295' && $out['uidvalidity']==='4294967295');
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['scope'=>'Exact-message identity and fixed read-only commands in memory; no socket/application acceptance','passed'=>$passed,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
