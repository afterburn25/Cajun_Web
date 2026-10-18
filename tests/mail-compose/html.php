<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-compose/ComposeHtml.php';
require __DIR__.'/../../foundation/mail-compose/DraftContent.php';
use CajunWebPro\Foundation\MailCompose\{ComposeHtml,DraftContent};
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;

$checks=[];$messages=[];$complete=false;
function check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function denied(string $name,callable $test,string $reason):void{
    try{$test();}catch(ReceivingFailure $e){check($name,$e->getMessage()===$reason);return;}
    check($name,false);
}
try {
    if(extension_loaded('dom')) {
        $clean=ComposeHtml::sanitize('<p>Hello <strong>client</strong>.</p><ul><li>One</li></ul>');
        check('Basic formatting survives sanitization',str_contains($clean,'<strong>client</strong>')&&str_contains($clean,'<ul><li>One</li></ul>'));
        $link=ComposeHtml::sanitize('<p><a href="https://example.invalid/path" onclick="alert(1)">Site</a> <a href="javascript:bad()">Drop</a></p>');
        check('Links keep safe schemes and gain noopener target attributes',str_contains($link,'href="https://example.invalid/path"')&&str_contains($link,'rel="noopener noreferrer"')&&str_contains($link,'target="_blank"'));
        check('Event handlers and javascript schemes are removed',!str_contains($link,'onclick')&&!str_contains($link,'javascript')&&substr_count($link,'href')===1);
        $hostile=ComposeHtml::sanitize('<div><script>bad()</script><style>x{}</style><form><input></form><iframe src="x"></iframe><img src="https://x.invalid/i.png"><svg><a>y</a></svg><object></object></div><p>Keep</p>');
        check('Scripts styles forms iframes images and embedded content are stripped',!preg_match('/script|style|form|input|iframe|img|svg|object/',$hostile)&&str_contains($hostile,'<p>Keep</p>'));
        check('A fragment that reduces to nothing sanitizes to empty',ComposeHtml::sanitize('<script>x</script><style>y</style>')==='');
        $mailto=ComposeHtml::sanitize('<a href="mailto:client@example.invalid">Mail</a>');
        check('Mailto and https links survive with the same forced attributes',str_contains($mailto,'mailto:client&#64;example.invalid')&&str_contains($mailto,'rel="noopener noreferrer"'));
        check('Relative links are removed',!str_contains(ComposeHtml::sanitize('<a href="/local">x</a>'),'href'));
        check('Style and class attributes do not survive',ComposeHtml::sanitize('<p style="color:red" class="x">T</p>')==='<p>T</p>');
        check('Tables survive for formatted layouts',str_contains(ComposeHtml::sanitize('<table><tr><th>H</th></tr><tr><td>C</td></tr></table>'),'<td>C</td>'));
        denied('Input over the byte bound is refused',fn()=>ComposeHtml::sanitize('<p>'.str_repeat('x',61440).'</p>'),'limit');
        $canonical=ComposeHtml::sanitize('<p>'.str_repeat('y',60000).'</p>');
        check('Accepted canonical output near the bound stays within the output bound',strlen($canonical)<=ComposeHtml::MAX_BYTES&&$canonical!=='');
        check('Accepted canonical output revalidates unchanged on resubmission',ComposeHtml::sanitize($canonical)===$canonical);
        check('The same canonical value stays valid as a saved formatted alternative',(new DraftContent([],[],[],'','Plain',$canonical))->html===$canonical);
        denied('Input over the tag-count bound is refused',fn()=>ComposeHtml::sanitize(str_repeat('<b></b>',2100)),'limit');
        denied('Invalid UTF-8 is refused',fn()=>ComposeHtml::sanitize("<p>\xC3\x28</p>"),'protocol');
        denied('NUL bytes are refused',fn()=>ComposeHtml::sanitize("<p>x\0y</p>"),'protocol');
        $round=ComposeHtml::sanitize('<p>Round <em>trip</em></p>');
        check('Sanitized output is stable through a second pass',ComposeHtml::sanitize($round)===$round);
        $content=new DraftContent(['client@example.invalid'],[],[],'Subject','Plain body',$round);
        check('Sanitized fragments are accepted as the formatted alternative',$content->html===$round);
    } else {
        $checks[]=['name'=>'DOM extension unavailable; sanitizer checks skipped in this runtime','passed'=>true];
    }
    foreach(['<script>bad()</script>','<img src=x>','<p onclick="x">t</p>','<p class="x">t</p>','<p id="x">t</p>','<p style="x:y">t</p>','<iframe srcdoc="x"></iframe>','<form></form>','<svg><a>x</a></svg>','<link href=x>','<object></object>','<noscript>x</noscript>'] as $i=>$unsafe) {
        $denied=false;try{new DraftContent([],[],[],'','',$unsafe);}catch(InvalidArgumentException){$denied=true;}
        check('DraftContent refuses unsanitized markup '.$i,$denied);
    }
    $denied=false;try{new DraftContent([],[],[],'','',str_repeat('<p>x</p>',3000));}catch(InvalidArgumentException){$denied=true;}
    check('DraftContent enforces the tag-count bound',$denied);
    $denied=false;try{new DraftContent([],[],[],'','',str_repeat('x',61441));}catch(InvalidArgumentException){$denied=true;}
    check('DraftContent enforces the fragment byte bound',$denied);
    $denied=false;try{new DraftContent([],[],[],'','',"<p>\xC3\x28</p>");}catch(InvalidArgumentException){$denied=true;}
    check('DraftContent refuses invalid UTF-8 markup',$denied);
    check('An empty fragment means plain text only',(new DraftContent([],[],[],'','',''))->html===null);
    check('Existing five-argument construction stays plain text',(new DraftContent([],[],[],'','B'))->html===null);
    $complete=true;
} finally {
    if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$complete&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Isolated outbound HTML sanitizer and draft formatted alternative only; synthetic fixture markup','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." named formatted-compose checks\n";
