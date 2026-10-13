<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
use CajunWebPro\Foundation\MailReceiving\{BodyDecoder,BodyHtml,ReceivingFailure};
$GLOBALS['message_global_log_calls']=0;
ZBateson\MailMimeParser\MailMimeParser::setGlobalLogger(new class extends Psr\Log\AbstractLogger {
 public function log($level,string|Stringable $message,array $context=[]):void {++$GLOBALS['message_global_log_calls'];throw new RuntimeException('Shared logger must not receive message content');}
});
ZBateson\MailMimeParser\MailMimeParser::addGlobalPhpDiContainerDefinition(['maxHeaderCount'=>0]);
$checks=[];
function check(string $name,callable $fn): void {global $checks;try {$ok=$fn()===true;}catch(Throwable $e){$ok=false;$detail=get_class($e).': '.$e->getMessage();}$checks[]=['name'=>$name,'passed'=>$ok,'detail'=>$detail??null];}
function denied(callable $fn,?string $reason=null): bool {try {$fn();return false;}catch(ReceivingFailure $e){return $reason===null || $e->getMessage()===$reason;}}
function msg(string $mime,string $body): string {return "Subject: =?UTF-8?B?Q2Fmw6k=?=\r\nFrom: Test <test@example.net>\r\nDate: Tue, 13 Oct 2026 12:00:00 +0000\r\nMIME-Version: 1.0\r\n".$mime."\r\n\r\n".$body;}
$plain=msg('Content-Type: text/plain; charset=UTF-8',"First\r\nSecond\t👩‍💻");
check('plain preserves lines tabs and emoji joiner',fn()=>BodyDecoder::decode($plain)['text']==="First\nSecond\t👩‍💻");
check('subject uses the bounded maintained header decoder',fn()=>BodyDecoder::decode($plain)['subject']==='Café');
check('missing HTML is null rather than a synthetic empty body',fn()=>BodyDecoder::decode($plain)['html']===null && !BodyDecoder::decode($plain)['has_html']);
check('empty inline body is represented explicitly',fn()=>BodyDecoder::decode(msg('Content-Type: text/plain',''))['text']==='');
check('control and bidi formatting removed from text',fn()=>BodyDecoder::decode(msg('Content-Type: text/plain; charset=UTF-8',"a\0b\u{202E}c"))['text']==='a b c');
check('HTML elements attributes and remote media removed',fn()=>BodyDecoder::decode(msg('Content-Type: text/html; charset=UTF-8','<p onclick="alert(1)">Hello<script>alert(1)</script><img src="https://tracker.invalid"></p>'))['html']==='<p>Hello</p>');
check('encoded executable link removed and safe attributes forced',fn()=>BodyHtml::render('<a href="jav&#x61;script:alert(1)">x</a>')==='<a rel="noopener noreferrer" target="_blank">x</a>');
check('provider style class id and form controls absent',fn()=>BodyHtml::render('<div class=x id=y style="position:fixed"><form><input name=q></form><b>text</b></div>')==='<div><b>text</b></div>');
check('complete HTML document becomes a safe body fragment',fn()=>BodyHtml::render('<!doctype html><html><head><style>body{background:red}</style></head><body><p>safe</p></body></html>')==='<p>safe</p>');
check('document wrappers discard events styles and all head content',fn()=>BodyHtml::render('<html onclick="alert(1)"><head><title>Private title</title><meta http-equiv="refresh" content="0;url=https://tracker.invalid"><link rel="stylesheet" href="https://tracker.invalid/style"></head><body onload="alert(2)" style="background:url(https://tracker.invalid/pixel)"><p>safe</p><script>alert(3)</script></body></html>')==='<p>safe</p>');
check('body wrapper retains visible content without wrapper attributes',fn()=>BodyHtml::render('<body id="admin" class="overlay" style="position:fixed"><p>fragment</p></body>')==='<p>fragment</p>');
check('document wrapper does not unwrap unknown active subtrees',fn()=>BodyHtml::render('<html><body><form><p>hidden form</p></form><svg><text>hidden SVG</text></svg><p>visible</p></body></html>')==='<p>visible</p>');
$alternative="--m\r\nContent-Type: text/plain\r\n\r\nPlain\r\n--m\r\nContent-Type: text/html\r\n\r\n<p>Formatted</p>\r\n--m--\r\n";
check('multipart alternate returns each supported body once',function()use($alternative){$r=BodyDecoder::decode(msg('Content-Type: multipart/alternative; boundary=m',$alternative));return $r['text']==='Plain' && $r['html']==='<p>Formatted</p>' && $r['additional_inline_parts']===0;});
check('attached text does not become the message body',fn()=>BodyDecoder::decode(msg("Content-Type: text/plain\r\nContent-Disposition: attachment; filename=secret.txt",'Attachment secret'))['text']===null);
check('named inline file is excluded as attachment content',fn()=>BodyDecoder::decode(msg("Content-Type: text/html\r\nContent-Disposition: inline; filename=secret.html",'<p>secret</p>'))['html']===null);
check('attachment-only message remains body unavailable',fn()=>BodyDecoder::decode(msg("Content-Type: application/octet-stream\r\nContent-Transfer-Encoding: base64",base64_encode('Private attachment')))['text']===null);
$attached="--a\r\nContent-Type: multipart/alternative; boundary=b\r\nContent-Disposition: attachment\r\n\r\n--b\r\nContent-Type: text/plain\r\n\r\nAttached inner text\r\n--b--\r\n--a--\r\n";
check('attached multipart ancestors exclude all descendant bodies',fn()=>BodyDecoder::decode(msg('Content-Type: multipart/mixed; boundary=a',$attached))['text']===null);
$two="--m\r\nContent-Type: text/plain\r\n\r\nFirst\r\n--m\r\nContent-Type: text/plain\r\n\r\nSecond\r\n--m--\r\n";
check('extra inline text parts have an honest omitted count',function()use($two){$r=BodyDecoder::decode(msg('Content-Type: multipart/mixed; boundary=m',$two));return $r['text']==='First' && $r['additional_inline_parts']===1;});
check('base64 decoded with UTF-8',fn()=>BodyDecoder::decode(msg("Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64",base64_encode('Café body')))['text']==='Café body');
check('quoted-printable charset conversion uses bounded stream',fn()=>BodyDecoder::decode(msg("Content-Type: text/plain; charset=ISO-8859-1\r\nContent-Transfer-Encoding: quoted-printable",'Caf=E9'))['text']==='Café');
check('raw MIME excess refused',fn()=>denied(fn()=>BodyDecoder::decode(str_repeat('x',262145)),'limit'));
check('decoded plaintext excess refused',fn()=>denied(fn()=>BodyDecoder::decode(msg('Content-Type: text/plain; charset=UTF-8',str_repeat('x',131073))),'limit'));
check('charset expansion cannot bypass decoded limit',fn()=>denied(fn()=>BodyDecoder::decode(msg('Content-Type: text/plain; charset=ISO-8859-1',str_repeat("\xe9",65537))),'limit'));
check('decoded HTML excess refused before sanitizer truncation',fn()=>denied(fn()=>BodyDecoder::decode(msg('Content-Type: text/html; charset=UTF-8',str_repeat('x',65537))),'limit'));
check('missing raw header separator refused',fn()=>denied(fn()=>BodyDecoder::decode('Subject: x'),'protocol'));
check('duplicate subject claims refused',fn()=>denied(fn()=>BodyDecoder::decode("Subject: a\r\nSubject: b\r\n\r\nx"),'protocol'));
check('oversized raw header block refused',fn()=>denied(fn()=>BodyDecoder::decode("X-Long: ".str_repeat('x',16384)."\r\n\r\nx"),'limit'));
$many=str_repeat("--m\r\nContent-Type: text/plain\r\n\r\nx\r\n",34)."--m--\r\n";
check('message part budget error refuses every body',fn()=>denied(fn()=>BodyDecoder::decode(msg('Content-Type: multipart/mixed; boundary=m',$many))));
$deep="Content-Type: text/plain\r\n\r\nleaf";for($i=0;$i<10;$i++)$deep="Content-Type: multipart/mixed; boundary=d$i\r\n\r\n--d$i\r\n$deep\r\n--d$i--\r\n";
check('MIME nesting budget refuses every body',fn()=>denied(fn()=>BodyDecoder::decode($deep)));
check('HTML DOM nesting bounded before visiting',fn()=>denied(fn()=>BodyHtml::render(str_repeat('<div>',40).'x'.str_repeat('</div>',40)),'limit'));
check('HTML DOM node count bounded',fn()=>denied(fn()=>BodyHtml::render(str_repeat('<p>x</p>',1100)),'limit'));
check('invalid UTF-8 refused by HTML boundary',fn()=>denied(fn()=>BodyHtml::render("<p>\xff</p>"),'protocol'));
check('parser tolerates complete MIME without closing boundary explicitly',function()use($alternative){$r=BodyDecoder::decode(msg('Content-Type: multipart/alternative; boundary=m',str_replace('--m--','',$alternative)));return $r['text']==='Plain' && str_starts_with($r['html'],'<p>Formatted</p>');});
check('message decoder ignores shared MIME definitions and loggers',fn()=>$GLOBALS['message_global_log_calls']===0);
ZBateson\MailMimeParser\MailMimeParser::resetGlobalPhpDiContainerDefinitions();
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['scope'=>'Bounded MIME and sanitized body fixtures; no application acceptance','passed'=>$passed,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
