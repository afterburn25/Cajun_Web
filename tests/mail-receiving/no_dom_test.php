<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$checks=[];
function without_dom_check(string $name,bool $passed): void {
    global $checks;
    $checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed) throw new RuntimeException($name);
}
without_dom_check('This runner actually has no DOM extension',!extension_loaded('dom'));
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
use CajunWebPro\Foundation\MailReceiving\{HeaderDecoder,BodyDecoder,BodyHtml,ReceivingFailure};
$headers=HeaderDecoder::decode("Subject: =?UTF-8?B?Q2Fmw6k=?=\r\n\r\n");
without_dom_check('Existing header decoding works without DOM',$headers['subject']==='Café');
$plain=BodyDecoder::decode("Content-Type: text/plain; charset=UTF-8\r\n\r\nPlain Café");
without_dom_check('Plain message reading works without DOM',$plain['text']==='Plain Café' && $plain['format_available']===false);
$raw="Content-Type: multipart/alternative; boundary=m\r\n\r\n--m\r\nContent-Type: text/plain\r\n\r\nPlain alternative\r\n--m\r\nContent-Type: text/html\r\n\r\n<p>Formatted</p>\r\n--m--\r\n";
$both=BodyDecoder::decode($raw);
without_dom_check('Unavailable formatted view preserves the plaintext alternative',$both['text']==='Plain alternative' && $both['html']===null && $both['has_html']===true && $both['format_available']===false);
$only=BodyDecoder::decode("Content-Type: text/html\r\n\r\n<p>HTML only</p>");
without_dom_check('HTML-only content reports unavailable format rather than invented plaintext',$only['text']===null && $only['html']===null && $only['has_html']===true && $only['format_available']===false);
$refused=false;try{BodyHtml::render('<p>test</p>');}catch(ReceivingFailure $e){$refused=$e->getMessage()==='protocol' && !$e->getPrevious();}
without_dom_check('Direct formatting without DOM fails with a finite local code',$refused);
echo json_encode(['scope'=>'Actual no-DOM PHP runtime compatibility; no application acceptance','passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
