<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
use CajunWebPro\Foundation\MailReceiving\{HeaderSession,HeaderDecoder,ReceivingFailure};
use DirectoryTree\ImapEngine\Connection\Streams\FakeStream;

final class HeaderFixture extends FakeStream
{
    private $memory;
    public function __construct(string $bytes) {$this->memory=fopen('php://memory','w+b');fwrite($this->memory,$bytes);rewind($this->memory);$this->open();$this->meta['crypto']['protocol']='TLSv1.3';}
    public function fgets(): string|false {return fgets($this->memory);}
    public function read(int $length): string|false {return $length===0?'':fread($this->memory,$length);}
    public function close(): void {if(is_resource($this->memory))fclose($this->memory);parent::close();}
    public function commands(): array {return $this->written;}
}
$checks=[];
function ok(string $name,bool $value): void {global $checks;$checks[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}
function prefix(int $exists=2,string $validity='7'): string {return "* OK synthetic\r\n* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nCWR001 OK done\r\n+ \r\nCWR002 OK authenticated\r\n* FLAGS (\\Seen \\Answered)\r\n* $exists EXISTS\r\n* 0 RECENT\r\n* OK [UIDVALIDITY $validity] stable\r\nCWR003 OK [READ-ONLY] examined\r\n";}
function row(int $sequence,string $uid,?string $header=null,string $flags='\\Seen'): string {$header??="From: =?UTF-8?B?Sm9zw6k=?= <synthetic@example.net>\r\nSubject: =?UTF-8?B?Q2Fmw6k=?=\r\nDate: Tue, 13 Oct 2026 10:30:00 -0500\r\n\r\n";return '* '.$sequence.' FETCH (UID '.$uid.' FLAGS ('.$flags.') RFC822.SIZE 2048 BODY[HEADER] {'.strlen($header)."}\r\n".$header.")\r\n";}
function exercise(string $bytes,int $revoke=0,string $folder='INBOX'): array {$s=new HeaderFixture($bytes);$calls=0;try {$out=(new HeaderSession($s,function()use(&$calls,$revoke){if(++$calls===$revoke)throw new RuntimeException('private authority detail');}))->read('ssl','synthetic@example.net','synthetic password',$folder);return [$out,$s,$calls];}catch(ReceivingFailure $e){return [$e,$s,$calls];}}
function deny(string $name,string $bytes,string $code='protocol'): void {[$out,$s]=exercise($bytes);ok($name,$out instanceof ReceivingFailure && $out->getMessage()===$code && !$out->getPrevious() && !$s->opened());}
$normal=prefix().row(1,'44').row(2,'45',null,'')."CWR004 OK fetched\r\n";
[$result,$stream,$calls]=exercise($normal);
ok('Complete two-message batch has stable folder and UIDVALIDITY identity',is_array($result) && $result['folder']==='INBOX' && $result['uidvalidity']==='7' && $result['total']===2);
ok('Messages returned newest first with exact UID and seen metadata',array_column($result['messages'],'uid')===['45','44'] && $result['messages'][0]['seen']===false && $result['messages'][1]['seen']===true);
ok('Maintained MIME decoder returns UTF-8 subject and sender claim',$result['messages'][0]['subject']==='Café' && str_contains($result['messages'][0]['from'],'José'));
ok('Authority rechecked before credentials after authentication before fetch and before return',$calls===4);
ok('Session closes the stream after a successful batch',!$stream->opened());
ok('Only EXAMINE and BODY.PEEK are used for mailbox reads',in_array("CWR003 EXAMINE \"INBOX\"\r\n",$stream->commands(),true) && in_array("CWR004 FETCH 1:2 (UID FLAGS RFC822.SIZE BODY.PEEK[HEADER])\r\n",$stream->commands(),true) && !preg_match('/\b(SELECT|SEARCH|STORE|APPEND|EXPUNGE)\b/',implode('',$stream->commands())));
$many=prefix(100,'4294967295');for($i=81;$i<=100;$i++)$many.=row($i,(string)($i+1000));$many.="CWR004 OK done\r\n";
[$result,$stream]=exercise($many);
ok('Large mailbox requests only the captured newest twenty sequence positions',is_array($result) && count($result['messages'])===20 && $result['messages'][0]['uid']==='1100' && $result['messages'][19]['uid']==='1081' && str_contains(implode('',$stream->commands()),'FETCH 81:100 '));
ok('Maximum unsigned 32-bit UIDVALIDITY preserved as text',$result['uidvalidity']==='4294967295');
[$result,$stream]=exercise(prefix(0));ok('A proven empty mailbox skips FETCH',is_array($result) && $result['messages']===[] && !str_contains(implode('',$stream->commands()),'FETCH'));
foreach([1,2,3,4] as $revoke){[$result,$stream]=exercise($normal,$revoke);ok('Revocation at authorization boundary '.$revoke.' yields no result',$result instanceof ReceivingFailure && $result->getMessage()==='access_changed');if($revoke===1)ok('Pre-credential revocation transmits no password payload',!str_contains(implode('',$stream->commands()),base64_encode("\0synthetic@example.net\0synthetic password")));if($revoke<4)ok('Early revocation never fetches headers at boundary '.$revoke,!str_contains(implode('',$stream->commands()),'FETCH'));}
deny('Wrong completion tag refused',str_replace('CWR004 OK','OTHER OK',$normal));
deny('Failed FETCH is not an empty successful inbox',str_replace('CWR004 OK','CWR004 NO',$normal));
deny('PREAUTH greeting is refused',str_replace('* OK synthetic','* PREAUTH synthetic',$normal));
deny('Missing credential mechanism is refused',str_replace('AUTH=PLAIN','AUTH=XOAUTH2',$normal),'mechanism');
deny('Authentication refusal is finite and private',str_replace('CWR002 OK authenticated','CWR002 NO private-server-rejection',$normal),'authentication');
deny('Unexpected continuation challenge is refused',str_replace("+ \r\n","+ cHJpdmF0ZQ==\r\n",$normal));
deny('Read-write mailbox selection is refused',str_replace('[READ-ONLY]','[READ-WRITE]',$normal));
deny('Missing UIDVALIDITY is refused',str_replace("* OK [UIDVALIDITY 7] stable\r\n",'',$normal));
deny('Duplicate UIDVALIDITY is refused',str_replace('[UIDVALIDITY 7] stable',"[UIDVALIDITY 7] stable\r\n* OK [UIDVALIDITY 7] again",$normal));
deny('Out-of-range UIDVALIDITY is refused',str_replace('UIDVALIDITY 7','UIDVALIDITY 4294967296',$normal));
deny('Malformed UID integer is refused',str_replace('UID 44','UID 044',$normal));
deny('Duplicate message UID is refused',str_replace('UID 45','UID 44',$normal),'mailbox_changed');
deny('Duplicate sequence position is refused',str_replace('* 2 FETCH','* 1 FETCH',$normal),'mailbox_changed');
deny('Sequence outside captured range is refused',str_replace('* 2 FETCH','* 3 FETCH',$normal),'mailbox_changed');
deny('Missing fetched row requires refresh',prefix().row(1,'44')."CWR004 OK done\r\n",'mailbox_changed');
deny('Expunge during fetch requires refresh',prefix()."* 1 EXPUNGE\r\n".row(1,'44').row(2,'45')."CWR004 OK done\r\n",'mailbox_changed');
deny('Unsolicited flags-only fetch cannot replace a complete message',prefix()."* 1 FETCH (FLAGS (\\Seen))\r\nCWR004 OK done\r\n",'mailbox_changed');
deny('Wrong body section is refused',str_replace('BODY[HEADER]','BODY[TEXT]',$normal));
deny('Missing body literal refused',str_replace('BODY[HEADER] {','BODY[TEXT] {',$normal));
deny('Extra body bytes in header literal refused',prefix(1).row(1,'44',"Subject: hello\r\n\r\n<script>body</script>")."CWR004 OK done\r\n");
deny('Malformed oversized literal refused before payload',prefix(1)."* 1 FETCH (UID 44 BODY[HEADER] {999999}\r\n",'limit');
deny('Header comment nesting bounded before MIME parsing',prefix(1).row(1,'44','From: '.str_repeat('(',13).'sender'.str_repeat(')',13)."\r\n\r\n")."CWR004 OK done\r\n",'limit');
deny('Excessive unfolded field bounded before MIME parsing',prefix(1).row(1,'44','Subject: '.str_repeat('x',4100)."\r\n\r\n")."CWR004 OK done\r\n",'limit');
deny('Control byte in header is refused',prefix(1).row(1,'44',"Subject: private\0claim\r\n\r\n")."CWR004 OK done\r\n");
deny('Bare LF in header is refused',prefix(1).row(1,'44',"Subject: claim\n\n")."CWR004 OK done\r\n");
deny('Duplicate subject claims refused',prefix(1).row(1,'44',"Subject: one\r\nSubject: two\r\n\r\n")."CWR004 OK done\r\n");
$decoded=HeaderDecoder::decode("Subject: =?UTF-8?B?SGkK4oCuPHNjcmlwdD4=?=\r\nContent-Type: multipart/mixed; boundary=hostile\r\n\r\n");
ok('Decoded control and bidi format characters normalized while markup remains untrusted text',$decoded['subject']==='Hi <script>');
$decoded=HeaderDecoder::decode("Subject: folded\r\n\tclaim\r\nReceived: one\r\nReceived: two\r\n\r\n");ok('Folded and repeated unrelated headers remain compatible',$decoded['subject']==='folded claim');
$decoded=HeaderDecoder::decode('Subject: '.str_repeat('é',700)."\r\n\r\n");ok('Display truncation is explicit and preserves complete UTF-8 characters',$decoded['headers_truncated']===true && strlen($decoded['subject'])===1000 && mb_check_encoding($decoded['subject'],'UTF-8'));
[$result,$stream]=exercise($normal,0,'Sent Items');
ok('Named-folder read examines only the quoted safe folder name',is_array($result) && $result['folder']==='Sent Items' && in_array("CWR003 EXAMINE \"Sent Items\"\r\n",$stream->commands(),true));
[$result,$stream]=exercise(str_replace('CWR003 OK [READ-ONLY] examined','CWR003 NO folder unavailable',$normal));
ok('EXAMINE refusal reports the folder outcome',$result instanceof ReceivingFailure && $result->getMessage()==='folder');
[$result,$stream]=exercise($normal,0,'bad"name');ok('Quoted-special folder name refuses before network work',$result instanceof ReceivingFailure && $result->getMessage()==='folder'&&!$stream->commands());
[$result,$stream]=exercise($normal,0,'wild*card');ok('Wildcard folder name refuses before network work',$result instanceof ReceivingFailure && $result->getMessage()==='folder'&&!$stream->commands());
[$result,$stream]=exercise($normal,0,'escape\\name');ok('Backslash folder name refuses before network work',$result instanceof ReceivingFailure && $result->getMessage()==='folder'&&!$stream->commands());
deny('MIME token overflow cannot silently return partially parsed headers',prefix(1).row(1,'44','Subject: '.str_repeat('a ',1800)."\r\n\r\n")."CWR004 OK done\r\n");
ZBateson\MailMimeParser\MailMimeParser::setGlobalLogger(new class extends Psr\Log\AbstractLogger {public function log($level,string|Stringable $message,array $context=[]):void {throw new RuntimeException('Global logger must not receive mailbox data');}});
ZBateson\MailMimeParser\MailMimeParser::addGlobalPhpDiContainerDefinition(['maxHeaderCount'=>0]);
$decoded=HeaderDecoder::decode("Subject: =?UTF-8?B?Q2Fmw6k=?=\r\n\r\n");ok('Receiving decoder ignores shared MIME settings and global loggers',$decoded['subject']==='Café');
ZBateson\MailMimeParser\MailMimeParser::resetGlobalPhpDiContainerDefinitions();
echo json_encode(['scope'=>'Read-only session, identity, authorization callbacks and hostile header memory fixtures; no database or live provider','passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
