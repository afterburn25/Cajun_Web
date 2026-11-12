<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
require __DIR__.'/../../foundation/mail-receiving/AttachmentIndex.php';

use CajunWebPro\Foundation\MailReceiving\{AttachmentIndex,ReceivingFailure};

$checks=[];
function security_check(string $name,callable $work):void {
    global $checks;
    try{$passed=$work()===true;}catch(Throwable $error){$passed=false;$detail=get_class($error).': '.$error->getMessage();}
    $checks[]=['name'=>$name,'passed'=>$passed]+(isset($detail)?['detail'=>$detail]:[]);
}
function security_refused(callable $work):bool {
    try{$work();return false;}catch(ReceivingFailure|InvalidArgumentException){return true;}
}
function security_message(string $type,string $body):string {
    return "Subject: Synthetic attachment\r\nFrom: Sender <sender@example.invalid>\r\nMIME-Version: 1.0\r\nContent-Type: ".$type."\r\n\r\n".$body;
}
function security_mixed(array $parts,string $boundary='secure'):string {
    $body='';
    foreach($parts as $part)$body.='--'.$boundary."\r\n".$part."\r\n";
    return security_message('multipart/mixed; boundary='.$boundary,$body.'--'.$boundary."--\r\n");
}

$encoded=security_mixed(["Content-Type: application/pdf\r\nContent-Disposition: attachment; filename*=UTF-8''caf%C3%A9.pdf\r\n\r\nPDF"]);
security_check('RFC encoded filename becomes bounded download-safe ASCII',function()use($encoded){$name=AttachmentIndex::list($encoded)[0]['name'];return $name!==''&&strlen($name)<=80&&preg_match('/\A[\x20-\x7E]+\z/D',$name)===1&&!str_contains($name,'/');});

$continued=security_mixed(["Content-Type: text/plain\r\nContent-Disposition: attachment; filename*0*=UTF-8''quarter; filename*1*=%2Etxt\r\n\r\ncontinued"]);
security_check('continued filename remains deterministic across list and extract',function()use($continued){$list=AttachmentIndex::list($continued);$item=AttachmentIndex::extract($continued,0);return count($list)===1&&$list[0]['name']===$item['name']&&$item['bytes']==='continued';});

$folded=security_mixed(["Content-Type: text/plain\r\nContent-Disposition: attachment; filename=\"safe.txt\r\n\tInjected-Header\"\r\n\r\nfolded"]);
security_check('folded filename cannot inject response controls',function()use($folded){$name=AttachmentIndex::list($folded)[0]['name'];return !preg_match('/[\r\n\t"\\\\\/]/',$name)&&strlen($name)<=80;});

foreach(['.','..','   '] as $i=>$unsafeName){
    $raw=security_mixed(["Content-Type: application/octet-stream\r\nContent-Disposition: attachment; filename=\"".$unsafeName."\"\r\n\r\nbytes"]);
    security_check('dot or blank filename receives generated fallback '.$i,fn()=>AttachmentIndex::list($raw)[0]['name']==='attachment-1.bin');
}

$longName=str_repeat('a',100).'.txt';
$long=security_mixed(["Content-Type: text/plain\r\nContent-Disposition: attachment; filename=\"".$longName."\"\r\n\r\nlong"]);
security_check('overlong filename is capped at eighty ASCII bytes',fn()=>strlen(AttachmentIndex::list($long)[0]['name'])===80);

$duplicates=security_mixed([
    "Content-Type: text/plain\r\nContent-Disposition: attachment; filename=\"same.txt\"\r\n\r\nfirst",
    "Content-Type: text/plain\r\nContent-Disposition: attachment; filename=\"same.txt\"\r\n\r\nsecond",
]);
security_check('duplicate names preserve stable distinct indexes',function()use($duplicates){$list=AttachmentIndex::list($duplicates);return array_column($list,'name')===['same.txt','same.txt']&&AttachmentIndex::extract($duplicates,0)['bytes']==='first'&&AttachmentIndex::extract($duplicates,1)['bytes']==='second';});
security_check('negative attachment index is refused',fn()=>security_refused(fn()=>AttachmentIndex::extract($duplicates,-1)));

$nested="--outer\r\nContent-Type: multipart/alternative; boundary=inner\r\n\r\n--inner\r\nContent-Type: text/plain\r\n\r\nbody\r\n--inner\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"nested.pdf\"\r\n\r\nnested\r\n--inner--\r\n--outer--\r\n";
$nestedRaw=security_message('multipart/mixed; boundary=outer',$nested);
security_check('nested attachment is indexed without treating multipart containers as files',function()use($nestedRaw){$list=AttachmentIndex::list($nestedRaw);return count($list)===1&&$list[0]['name']==='nested.pdf'&&AttachmentIndex::extract($nestedRaw,0)['bytes']==='nested';});

$attachedMessage=security_mixed(["Content-Type: message/rfc822\r\nContent-Disposition: attachment; filename=\"forwarded.eml\"\r\n\r\nSubject: Nested\r\nFrom: nested@example.invalid\r\n\r\nNested body"]);
security_check('attached message stays an attachment with a safe name',function()use($attachedMessage){$list=AttachmentIndex::list($attachedMessage);return count($list)===1&&$list[0]['name']==='forwarded.eml'&&str_contains(AttachmentIndex::extract($attachedMessage,0)['bytes'],'Nested body');});

$tooMany=[];
for($i=0;$i<33;$i++)$tooMany[]="Content-Type: text/plain\r\nContent-Disposition: attachment; filename=\"part-".$i.".txt\"\r\n\r\n".$i;
$tooManyRaw=security_mixed($tooMany,'many');
security_check('message exceeding the bounded part count is refused',fn()=>security_refused(fn()=>AttachmentIndex::list($tooManyRaw)));

$malformed=security_mixed(["Content-Type: application/octet-stream\r\nContent-Disposition: attachment; filename=\"bad.bin\"\r\nContent-Transfer-Encoding: base64\r\n\r\n@@@@not-base64@@@@"]);
security_check('malformed transfer encoding never yields downloadable bytes',fn()=>security_refused(fn()=>AttachmentIndex::extract($malformed,0)));

$validQuoted=security_mixed(["Content-Type: text/plain\r\nContent-Disposition: attachment; filename=\"quoted.txt\"\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nline=20one=0Aline=20two"]);
security_check('canonical quoted-printable remains downloadable',fn()=>AttachmentIndex::extract($validQuoted,0)['bytes']==="line one\nline two");
$invalidQuoted=security_mixed(["Content-Type: text/plain\r\nContent-Disposition: attachment; filename=\"bad-quoted.txt\"\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\ninvalid=QZ"]);
security_check('malformed quoted-printable is refused',fn()=>security_refused(fn()=>AttachmentIndex::extract($invalidQuoted,0)));

$latin1=security_mixed(["Content-Type: text/plain; charset=ISO-8859-1\r\nContent-Disposition: attachment; filename=\"latin1.txt\"\r\nContent-Transfer-Encoding: 8bit\r\n\r\ncaf\xE9"]);
security_check('binary extraction preserves exact transfer-decoded attachment bytes and size',function()use($latin1){$list=AttachmentIndex::list($latin1);$item=AttachmentIndex::extract($latin1,0);return $item['bytes']==="caf\xE9"&&strlen($item['bytes'])===4&&$list[0]['size']===4;});

foreach([
    'lowercase escape'=>"lower=2fcase",
    'overlong physical line'=>str_repeat('A',77),
    'trailing whitespace'=>"trailing \t",
    'raw NUL'=>"A\0B",
] as $name=>$body){
    $raw=security_mixed(["Content-Type: application/octet-stream\r\nContent-Disposition: attachment; filename=\"bad-quoted.bin\"\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".$body]);
    security_check('quoted-printable refuses '.$name,fn()=>security_refused(fn()=>AttachmentIndex::extract($raw,0)));
}

$highBit7=security_mixed(["Content-Type: application/octet-stream\r\nContent-Disposition: attachment; filename=\"bad-7bit.bin\"\r\nContent-Transfer-Encoding: 7bit\r\n\r\nA\xE9B"]);
security_check('7bit transfer refuses high-bit bytes',fn()=>security_refused(fn()=>AttachmentIndex::extract($highBit7,0)));

$names=array_column($checks,'name');
if(count($names)!==count(array_unique($names)))throw new RuntimeException('Duplicate attachment security check names.');
$passed=!array_filter($checks,fn($check)=>!$check['passed']);
echo json_encode(['scope'=>'Synthetic attachment parsing and filename safety only; no socket, authority, application, database or download-response acceptance','passed'=>$passed,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
