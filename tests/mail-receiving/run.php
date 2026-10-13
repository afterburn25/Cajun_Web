<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
use DirectoryTree\ImapEngine\Connection\{ImapConnection,ImapParser};
use DirectoryTree\ImapEngine\Connection\Streams\FakeStream;
use DirectoryTree\ImapEngine\Connection\Responses\UntaggedResponse;
use CajunWebPro\Foundation\MailReceiving\BoundedTokenizer;
use ZBateson\MailMimeParser\Message;
/** Synthetic in-memory fixture only; production stream integration remains separate. */
class ReceivingFixtureStream extends FakeStream {
    private $memory;
    public function __construct(string $bytes){$this->memory=fopen('php://memory','w+b');fwrite($this->memory,$bytes);rewind($this->memory);}
    public function fgets(): string|false {return fgets($this->memory);}
    public function read(int $length): string|false {return $length===0?'':fread($this->memory,$length);}
    public function close(): void {if(is_resource($this->memory))fclose($this->memory);parent::close();}
    public function writes(): array {return $this->written;}
}
$checks=[];
function inbox_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function inbox_parse(string $bytes): mixed {$s=new ReceivingFixtureStream($bytes);$s->open();try{return(new ImapParser(new BoundedTokenizer($s)))->next();}finally{$s->close();}}
function inbox_deny(string $name,string $bytes): void {try{inbox_parse($bytes);}catch(RuntimeException|DirectoryTree\ImapEngine\Exceptions\ImapStreamException){inbox_ok($name,true);return;}inbox_ok($name,false);}
inbox_ok('Maintained parser handles bounded ordinary EXISTS',inbox_parse("* 2 EXISTS\r\n") instanceof UntaggedResponse);
$header="Subject: Café\r\n\r\n";
inbox_ok('Valid UTF-8 header literal is accepted by byte length',inbox_parse('* 1 FETCH (UID 44 BODY[HEADER] {'.strlen($header)."}\r\n".$header.")\r\n") instanceof UntaggedResponse);
inbox_ok('Empty valid literal is accepted',inbox_parse("* 1 FETCH (BODY[HEADER] {0}\r\n)\r\n") instanceof UntaggedResponse);
inbox_deny('Truncated literal is refused',"* 1 FETCH (BODY[HEADER] {99}\r\nshort");
foreach(['-1','1.5','2x','99999999999999999999','001','2+'] as $n)inbox_deny('Refuse malformed literal size '.$n,"* 1 FETCH (BODY[HEADER] {".$n."}\r\nabc)\r\n");
inbox_deny('Refuse excessive header literal before allocation',"* 1 FETCH (BODY[HEADER] {16385}\r\n");
inbox_deny('Refuse nested lists before deep recursion','* X '.str_repeat('(',40).'x'.str_repeat(')',40)."\r\n");
inbox_deny('Refuse token floods','* X '.str_repeat('x ',4100)."\r\n");
$stream=new ReceivingFixtureStream("* OK Synthetic greeting\r\nTAG1 OK Logged in\r\n* 2 EXISTS\r\n* OK [UIDVALIDITY 7] stable\r\nTAG2 OK [READ-ONLY] examined\r\nTAG3 OK Logged out\r\n");
$connection=new ImapConnection($stream);$connection->connect('synthetic.example.invalid',993,['encryption'=>'ssl']);$connection->login('synthetic-user','synthetic-pass');$rows=$connection->examine('INBOX');
inbox_ok('Pinned library accepts synthetic read-only EXAMINE replies',$rows->count()===2);$connection->logout();
inbox_ok('Synthetic library exchange never selects or changes a mailbox',!array_filter($stream->writes(),fn($line)=>preg_match('/\b(SELECT|STORE|APPEND|EXPUNGE)\b/',$line)));
$message=Message::from("From: =?UTF-8?B?Sm9zw6k=?= <synthetic@example.invalid>\r\nSubject: =?UTF-8?B?Q2Fmw6k=?=\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSynthetic body",false);
inbox_ok('Pinned MIME parser decodes UTF-8 subject',$message->getHeaderValue('subject')==='Café');
inbox_ok('Pinned MIME parser returns synthetic plain text',$message->getTextContent()==='Synthetic body');
echo json_encode(['scope'=>'Maintained dependency API and bounded-tokenizer memory fixtures; no application/socket/provider acceptance','passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
