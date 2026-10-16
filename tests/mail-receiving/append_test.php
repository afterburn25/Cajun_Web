<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
use CajunWebPro\Foundation\MailReceiving\{AppendSession,ReceivingFailure};
use DirectoryTree\ImapEngine\Connection\Streams\FakeStream;

final class AppendFixture extends FakeStream
{
    private $memory;
    private array $literals=[];
    public function __construct(string $bytes) {$this->memory=fopen('php://memory','w+b');fwrite($this->memory,$bytes);rewind($this->memory);$this->open();$this->meta['crypto']['protocol']='TLSv1.3';}
    public function fgets(): string|false {return fgets($this->memory);}
    public function read(int $length): string|false {return $length===0?'':fread($this->memory,$length);}
    public function fwriteLiteral(string $data): int|false {$this->literals[]=$data;return strlen($data);}
    public function literals(): array {return $this->literals;}
    public function commands(): array {return $this->written;}
    public function close(): void {if(is_resource($this->memory))fclose($this->memory);parent::close();}
}
$checks=[];
function ok(string $name,bool $value): void {global $checks;$checks[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}
const MIME="From: Synthetic <synthetic@example.net>\r\nTo: dest@example.net\r\nSubject: Sent copy\r\nMessage-ID: <copy-1@example.invalid>\r\nDate: Tue, 13 Oct 2026 12:00:00 +0000\r\nMIME-Version: 1.0\r\nContent-Type: text/plain\r\n\r\nExact saved body\r\n";
function prefix(): string {return "* OK synthetic\r\n* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nCWA001 OK done\r\n+ \r\nCWA002 OK authenticated\r\n";}
function exercise(string $bytes,int $revoke=0,string $folder='Sent',string $message=MIME): array {$s=new AppendFixture($bytes);$calls=0;try {(new AppendSession($s,function()use(&$calls,$revoke){if(++$calls===$revoke)throw new RuntimeException('private authority detail');}))->append('ssl','synthetic@example.net','synthetic password',$folder,$message);return [null,$s,$calls];}catch(ReceivingFailure $e){return [$e,$s,$calls];}}
function deny(string $name,string $bytes,string $code='protocol',string $folder='Sent'): void {[$out,$s]=exercise($bytes,0,$folder);ok($name,$out instanceof ReceivingFailure && $out->getMessage()===$code && !$out->getPrevious() && !$s->opened());}

$normal=prefix()."+ \r\nCWA003 OK appended\r\n";
[$out,$stream,$calls]=exercise($normal);
ok('Accepted APPEND returns without error',$out===null);
ok('Wire uses the fixed bounded append sequence',$stream->commands()===["CWA001 CAPABILITY\r\n","CWA002 AUTHENTICATE PLAIN\r\n",base64_encode("\0synthetic@example.net\0synthetic password")."\r\n",'CWA003 APPEND "Sent" (\\Seen) {'.strlen(MIME)."}\r\n","\r\n","CWA004 LOGOUT\r\n"]);
ok('Literal transmits the exact saved MIME bytes once',$stream->literals()===[MIME]);
ok('Authority rechecked before credentials, payload, acceptance and return',$calls===4);
ok('Session closes the stream after an accepted append',!$stream->opened());
deny('Server refusing the folder is a bounded result',prefix()."+ \r\nCWA003 NO no such folder\r\n",'folder');
deny('APPEND BAD is a bounded folder result',prefix()."+ \r\nCWA003 BAD rejected\r\n",'folder');
deny('Immediate tagged refusal without continuation refuses before payload',prefix()."CWA003 NO refused\r\n",'folder');
deny('Missing continuation is refused',prefix()."* EXISTS 1\r\nCWA003 OK stored\r\n");
deny('Missing credential mechanism is refused',str_replace('AUTH=PLAIN','AUTH=XOAUTH2',$normal),'mechanism');
deny('Rejected credentials are bounded',str_replace("CWA002 OK authenticated","CWA002 NO denied",$normal),'authentication');
deny('PREAUTH greeting is refused',str_replace('* OK synthetic','* PREAUTH synthetic',$normal));
deny('Literal reply in an append session is refused',prefix()."+ \r\n* 1 FETCH (BODY[] {4}\r\nabcd)\r\nCWA003 OK appended\r\n",'limit');
foreach([1,2,3,4] as $revoke){[$out,$stream]=exercise($normal,$revoke);ok('Revocation at authorization boundary '.$revoke.' records no copy',$out instanceof ReceivingFailure && $out->getMessage()==='access_changed');if($revoke===1)ok('Pre-credential revocation transmits no password payload',!str_contains(implode('',$stream->commands()),base64_encode("\0synthetic@example.net\0synthetic password")));if($revoke<3)ok('Early revocation never appends at boundary '.$revoke,$stream->literals()===[]);}
foreach(['INBOX.Sent','Sent Items','Archive-2026'] as $folder){[$out]=exercise($normal,0,$folder);ok('Plain provider folder name accepted: '.$folder,$out===null);}
foreach(['Sent"folder','Sen\\t','Sent*','%Sent','with space inside'.str_repeat('x',60),''] as $folder){[$out,$stream,$calls]=exercise($normal,0,$folder);ok('Unsafe folder refused before any network: '.json_encode($folder),$out instanceof ReceivingFailure && $out->getMessage()==='folder' && $calls===0 && $stream->commands()===[]);}
[$out,$stream]=exercise($normal,0,'Sent',str_repeat('x',70000));ok('Oversized message refused before any network',$out instanceof ReceivingFailure && $out->getMessage()==='protocol' && $stream->commands()===[]);
[$out,$stream]=exercise($normal,0,'Sent',"short");ok('Malformed message refused before any network',$out instanceof ReceivingFailure && $out->getMessage()==='protocol' && $stream->commands()===[]);
[$out,$stream]=exercise($normal,0,'Sent',"From: a\r\n\r\nbody\x80bytes");ok('Non-ASCII payload refused before any network',$out instanceof ReceivingFailure && $out->getMessage()==='protocol' && $stream->commands()===[]);
echo json_encode(['scope'=>'Bounded APPEND session over an in-memory stream; no socket, provider or application acceptance','passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT),PHP_EOL;
