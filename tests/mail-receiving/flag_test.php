<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
use CajunWebPro\Foundation\MailReceiving\{FlagSession,ReceivingFailure};
use DirectoryTree\ImapEngine\Connection\Streams\FakeStream;

final class FlagFixture extends FakeStream
{
    private $memory;
    public function __construct(string $bytes) {$this->memory=fopen('php://memory','w+b');fwrite($this->memory,$bytes);rewind($this->memory);$this->open();$this->meta['crypto']['protocol']='TLSv1.3';}
    public function fgets(): string|false {return fgets($this->memory);}
    public function read(int $length): string|false {return $length===0?'':fread($this->memory,$length);}
    public function fwriteLiteral(string $data): int|false {return strlen($data);}
    public function commands(): array {return $this->written;}
    public function close(): void {if(is_resource($this->memory))fclose($this->memory);parent::close();}
}
$checks=[];
function ok(string $name,bool $value): void {global $checks;$checks[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}
function prefix(): string {return "* OK synthetic\r\n* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nCWF001 OK done\r\n+ \r\nCWF002 OK authenticated\r\n";}
function selected(string $validity='1001'): string {return "* 3 EXISTS\r\n* FLAGS (\\Seen \\Answered)\r\n* OK [UIDVALIDITY ".$validity."] ok\r\nCWF003 OK [READ-WRITE] done\r\n";}
function exercise(string $bytes,int $revoke=0,string $folder='INBOX',string $validity='1001',string $uid='7',bool $seen=true): array {$s=new FlagFixture($bytes);$calls=0;try {$r=(new FlagSession($s,function()use(&$calls,$revoke){if(++$calls===$revoke)throw new RuntimeException('private authority detail');}))->mark('ssl','synthetic@example.net','synthetic password',$folder,$validity,$uid,$seen);return [null,$s,$calls,$r];}catch(ReceivingFailure $e){return [$e,$s,$calls,null];}}
function deny(string $name,string $bytes,string $code='protocol',string $folder='INBOX'): void {[$out,$s]=exercise($bytes,0,$folder);ok($name,$out instanceof ReceivingFailure && $out->getMessage()===$code && !$out->getPrevious() && !$s->opened());}

$normal=prefix().selected()."* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\nCWF005 OK stored\r\n* 1 FETCH (UID 7 FLAGS (\\Seen))\r\nCWF006 OK done\r\n";
[$out,$stream,$calls,$result]=exercise($normal);
ok('Mark read returns the applied flag state',$out===null && $result===['folder'=>'INBOX','uidvalidity'=>'1001','uid'=>'7','seen'=>true,'applied'=>true,'confirmed'=>true]);
ok('Wire uses the fixed bounded flag sequence',$stream->commands()===["CWF001 CAPABILITY\r\n","CWF002 AUTHENTICATE PLAIN\r\n",base64_encode("\0synthetic@example.net\0synthetic password")."\r\n",'CWF003 SELECT "INBOX"'."\r\n","CWF004 UID FETCH 7 (UID FLAGS)\r\n","CWF005 UID STORE 7 +FLAGS.SILENT (\\Seen)\r\n","CWF006 UID FETCH 7 (UID FLAGS)\r\n","CWF007 LOGOUT\r\n"]);
ok('Authority rechecked before credentials, select proof, write, verify and return',$calls===6);
ok('Session closes the stream after a completed mark',!$stream->opened());
$already=prefix().selected()."* 1 FETCH (UID 7 FLAGS (\\Seen))\r\nCWF004 OK done\r\n";
$unmark=$already."CWF005 OK stored\r\n* 1 FETCH (UID 7 FLAGS ())\r\nCWF006 OK done\r\n";
[$out,$stream,$calls,$result]=exercise($unmark,0,'INBOX','1001','7',false);
ok('Mark unread issues the remove-flag write',($stream->commands()[5]??'')==="CWF005 UID STORE 7 -FLAGS.SILENT (\\Seen)\r\n" && $result['seen']===false && $result['applied']===true);
[$out,$stream,$calls,$result]=exercise($already);
ok('Already-seen message returns unchanged without a STORE',$out===null && $result['applied']===false && !str_contains(implode('',$stream->commands()),'STORE'));
ok('Unchanged mark still rechecks authority at each boundary',$calls===3);
[$out,$stream]=exercise($unmark,0,'Sent','1001','7',false);
ok('Non-INBOX folder marks unread in that folder',($stream->commands()[3]??'')==='CWF003 SELECT "Sent"'."\r\n" && ($stream->commands()[5]??'')==="CWF005 UID STORE 7 -FLAGS.SILENT (\\Seen)\r\n");
deny('UIDVALIDITY mismatch refuses before any write',prefix().selected('9999')."* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\n",'mailbox_changed');
$gone=prefix().selected()."CWF004 OK done\r\n";
[$out,$stream]=exercise($gone);ok('A message absent from the folder refuses instead of writing',$out instanceof ReceivingFailure && $out->getMessage()==='mailbox_changed' && !str_contains(implode('',$stream->commands()),'STORE'));
deny('STORE refusal is a bounded flags result',prefix().selected()."* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\nCWF005 NO denied\r\n",'flags');
deny('READ-ONLY select refuses instead of writing',prefix()."* 3 EXISTS\r\n* OK [UIDVALIDITY 1001] ok\r\nCWF003 OK [READ-ONLY] done\r\n",'flags');
deny('PERMANENTFLAGS excluding \\Seen refuses the persistent write',prefix()."* 3 EXISTS\r\n* FLAGS (\\Seen \\Deleted)\r\n* OK [UIDVALIDITY 1001] ok\r\n* OK [PERMANENTFLAGS (\\Deleted)] ok\r\nCWF003 OK [READ-WRITE] done\r\n* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\n",'flags');
[$out,$stream,$calls,$result]=exercise(prefix()."* 3 EXISTS\r\n* FLAGS (\\Seen \\Deleted)\r\n* OK [UIDVALIDITY 1001] ok\r\n* OK [PERMANENTFLAGS (\\Seen \\Deleted)] ok\r\nCWF003 OK [READ-WRITE] done\r\n* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\nCWF005 OK stored\r\n* 1 FETCH (UID 7 FLAGS (\\Seen))\r\nCWF006 OK done\r\n");
ok('PERMANENTFLAGS including \\Seen permits the persistent write',$out===null && $result['confirmed']===true);
[$out,$stream,$calls,$result]=exercise(prefix()."* 3 EXISTS\r\n* FLAGS (\\Seen \\Deleted)\r\n* OK [UIDVALIDITY 1001] ok\r\n* OK [PERMANENTFLAGS (\\*)] ok\r\nCWF003 OK [READ-WRITE] done\r\n* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\nCWF005 OK stored\r\n* 1 FETCH (UID 7 FLAGS (\\Seen))\r\nCWF006 OK done\r\n");
ok('PERMANENTFLAGS wildcard permits the persistent write',$out===null && $result['confirmed']===true);
[$out,$stream,$calls,$result]=exercise(prefix().selected()."* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\nCWF005 OK stored\r\nCWF006 OK done\r\n");
ok('Tagged OK with the UID expunged does not claim a persistent change',$out===null && $result['applied']===true && $result['confirmed']===false);
[$out,$stream,$calls,$result]=exercise(prefix().selected()."* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\nCWF005 OK stored\r\n* 1 FETCH (UID 7 FLAGS ())\r\nCWF006 OK done\r\n");
ok('Tagged OK with unchanged post-write state is unconfirmed',$out===null && $result['applied']===true && $result['confirmed']===false);
deny('Missing READ-WRITE confirmation is refused',prefix()."* 3 EXISTS\r\n* OK [UIDVALIDITY 1001] ok\r\nCWF003 OK done\r\n");
deny('Missing folder is a bounded result',prefix()."CWF003 NO no such folder\r\n",'folder');
deny('Rejected credentials are bounded',str_replace("CWF002 OK authenticated","CWF002 NO denied",$normal),'authentication');
deny('Missing credential mechanism is refused',str_replace('AUTH=PLAIN','AUTH=XOAUTH2',prefix().selected()."* 1 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\nCWF005 OK stored\r\n"),'mechanism');
deny('Duplicate FETCH rows are refused as a changed mailbox',prefix().selected()."* 1 FETCH (UID 7 FLAGS ())\r\n* 2 FETCH (UID 7 FLAGS ())\r\nCWF004 OK done\r\n",'mailbox_changed');
deny('A FETCH row for another UID is refused',prefix().selected()."* 1 FETCH (UID 9 FLAGS ())\r\nCWF004 OK done\r\n",'mailbox_changed');
deny('PREAUTH greeting is refused',str_replace('* OK synthetic','* PREAUTH synthetic',$normal));
foreach([1,2,3,4] as $revoke){[$out,$stream]=exercise($normal,$revoke);ok('Revocation at authorization boundary '.$revoke.' records no flag change',$out instanceof ReceivingFailure && $out->getMessage()==='access_changed');if($revoke===1)ok('Pre-credential revocation transmits no password payload',!str_contains(implode('',$stream->commands()),base64_encode("\0synthetic@example.net\0synthetic password")));if($revoke<4)ok('Early revocation never writes at boundary '.$revoke,!str_contains(implode('',$stream->commands()),'STORE'));}
foreach([5,6] as $revoke){[$out,$stream,$calls,$result]=exercise($normal,$revoke);ok('Post-write revocation at boundary '.$revoke.' reports an unconfirmed change',$out===null && $result['applied']===true && $result['confirmed']===false && str_contains(implode('',$stream->commands()),'STORE'));}
foreach(['INBOX','Sent','Sent Items','Archive-2026'] as $folder){[$out]=exercise($normal,0,$folder);ok('Bounded provider folder accepted: '.$folder,$out===null);}
foreach(['IN"BOX','Sen\\t','In*','%BOX',str_repeat('x',65),''] as $folder){[$out,$stream,$calls]=exercise($normal,0,$folder);ok('Unsafe folder refused before any network: '.json_encode($folder),$out instanceof ReceivingFailure && $out->getMessage()==='folder' && $calls===0 && $stream->commands()===[]);}
foreach(['0','abc','4294967296'] as $uid){[$out,$stream]=exercise($normal,0,'INBOX','1001',$uid);ok('Unsafe UID refused before any network: '.$uid,$out instanceof ReceivingFailure && $out->getMessage()==='protocol' && $stream->commands()===[]);}
echo json_encode(['scope'=>'Bounded SELECT + UID STORE \\Seen session over an in-memory stream; no socket, provider or application acceptance','passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT),PHP_EOL;
