<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-receiving/bootstrap.php';
require __DIR__.'/../../foundation/mail-receiving/AttachmentIndex.php';
use CajunWebPro\Foundation\MailReceiving\{AttachmentIndex,ReceivingFailure};
$checks=[];
function check(string $name,callable $fn): void {global $checks;try {$ok=$fn()===true;}catch(Throwable $e){$ok=false;$detail=get_class($e).': '.$e->getMessage();}$checks[]=['name'=>$name,'passed'=>$ok,'detail'=>$detail??null];}
function denied(callable $fn,string $reason): bool {try {$fn();return false;}catch(ReceivingFailure $e){return $e->getMessage()===$reason;}catch(Throwable){return false;}}
function msg(string $mime,string $body): string {return "Subject: Test\r\nFrom: Test <test@example.net>\r\nMIME-Version: 1.0\r\n".$mime."\r\n\r\n".$body;}

$mixed="--m\r\nContent-Type: text/plain\r\n\r\nHello.\r\n--m\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"report.pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".base64_encode('PDFBYTES')."\r\n--m--\r\n";
$raw=msg('Content-Type: multipart/mixed; boundary=m',$mixed);

check('attachment part is listed with decoded size',function()use($raw){$l=AttachmentIndex::list($raw);return count($l)===1&&$l[0]['name']==='report.pdf'&&$l[0]['type']==='application/pdf'&&$l[0]['size']===8;});
check('extract returns the decoded attachment bytes',function()use($raw){$a=AttachmentIndex::extract($raw,0);return $a['name']==='report.pdf'&&$a['bytes']==='PDFBYTES'&&$a['type']==='application/pdf';});
check('plain body is not an attachment',function()use($raw){return AttachmentIndex::list($raw)[0]['name']!=='Hello.';});

$two="--m\r\nContent-Type: text/plain\r\n\r\nHi\r\n--m\r\nContent-Type: text/csv\r\nContent-Disposition: attachment; filename=\"a.csv\"\r\n\r\n1,2\r\n--m\r\nContent-Type: image/png\r\nContent-Disposition: attachment; filename=\"b.png\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".base64_encode("\x89PNG")."\r\n--m--\r\n";
$raw2=msg('Content-Type: multipart/mixed; boundary=m',$two);
check('index order is stable across list and extract',function()use($raw2){$l=AttachmentIndex::list($raw2);return count($l)===2&&$l[0]['name']==='a.csv'&&$l[1]['name']==='b.png'&&AttachmentIndex::extract($raw2,1)['bytes']==="\x89PNG";});
check('out-of-range index raises invalid argument',function()use($raw2){try {AttachmentIndex::extract($raw2,2);return false;}catch(InvalidArgumentException){return true;}});

check('named inline part is downloadable, not a body',function(){ $r=msg("Content-Type: text/html\r\nContent-Disposition: inline; filename=\"chart.html\"",'<p>chart</p>');$l=AttachmentIndex::list($r);return count($l)===1&&$l[0]['name']==='chart.html'&&AttachmentIndex::extract($r,0)['bytes']==='<p>chart</p>';});
check('unnamed non-text part without attachment disposition stays unlisted',fn()=>AttachmentIndex::list(msg("Content-Type: application/octet-stream\r\nContent-Transfer-Encoding: base64",base64_encode('x')))===[]);
check('plain-only message lists no attachments',fn()=>AttachmentIndex::list(msg('Content-Type: text/plain; charset=UTF-8','Hello'))===[]);

$evilRaw=msg('Content-Type: multipart/mixed; boundary=x',"--x\r\nContent-Type: application/x-msdownload\r\nContent-Disposition: attachment; filename=\"../../etc/pa\"ss\"word\"\r\n\r\nx\r\n--x--\r\n");
check('hostile filename is stripped to a safe name',function()use($evilRaw){$l=AttachmentIndex::list($evilRaw);return count($l)===1&&!str_contains($l[0]['name'],'/')&&!str_contains($l[0]['name'],'\\')&&!str_contains($l[0]['name'],'"');});
check('filenameless attachment gets a generated safe name',function(){$r=msg("Content-Type: multipart/mixed; boundary=z","--z\r\nContent-Type: application/octet-stream\r\nContent-Disposition: attachment\r\n\r\nbytes\r\n--z--\r\n");$l=AttachmentIndex::list($r);return count($l)===1&&$l[0]['name']==='attachment-1.bin';});
check('invalid declared type falls back to octet-stream',function(){$r=msg("Content-Type: multipart/mixed; boundary=z","--z\r\nContent-Type: bad type!\r\nContent-Disposition: attachment; filename=\"x.bin\"\r\n\r\nbytes\r\n--z--\r\n");return AttachmentIndex::list($r)[0]['type']==='application/octet-stream';});

check('oversized raw is refused before parsing',fn()=>denied(fn()=>AttachmentIndex::list(msg('Content-Type: text/plain',str_repeat('x',262145))),'limit'));
check('malformed raw is refused',fn()=>denied(fn()=>AttachmentIndex::list("no blank line"),'protocol'));
check('extract on a plain message refuses',function(){try {AttachmentIndex::extract(msg('Content-Type: text/plain','x'),0);return false;}catch(InvalidArgumentException){return true;}});

$done=count($checks);$failed=array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['scope'=>'Attachment index/extract over synthetic MIME; no socket, provider or application acceptance','passed'=>$done-count($failed)===$done,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($failed?1:0);
