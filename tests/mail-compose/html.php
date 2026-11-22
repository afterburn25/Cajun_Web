<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/mail-compose/DraftContent.php';
use CajunWebPro\Foundation\MailCompose\{ComposeHtml,DraftContent};
use CajunWebPro\Foundation\MailReceiving\ReceivingFailure;

$checks=[];$complete=false;
function check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function denied(string $name,callable $test,string $reason):void{
    try{$test();}catch(ReceivingFailure $e){check($name,$e->getMessage()===$reason);return;}
    check($name,false);
}
function draft_denied(string $name,string $html):void{
    $denied=false;try{new DraftContent([],[],[],'','',$html);}catch(InvalidArgumentException){$denied=true;}
    check($name,$denied);
}
try {
    $plain=new DraftContent([],[],[],'','0');
    check('Plain five-argument drafts do not load the formatted sanitizer',
        $plain->body==='0'&&$plain->html===null&&!class_exists(ComposeHtml::class,false));
    check('An explicit empty formatted alternative remains plain text only',
        (new DraftContent([],[],[],'','Plain',''))->html===null&&!class_exists(ComposeHtml::class,false));
    require __DIR__.'/../../foundation/mail-compose/ComposeHtml.php';
    check('Formatted and draft bounds remain identical',ComposeHtml::MAX_BYTES===DraftContent::MAX_HTML_BYTES);

    if(extension_loaded('dom')) {
        $clean=ComposeHtml::sanitize('<p>Hello <strong>client</strong>.</p><ul><li>One</li></ul>');
        check('Basic formatting survives sanitization',str_contains($clean,'<strong>client</strong>')&&str_contains($clean,'<ul><li>One</li></ul>'));
        $link=ComposeHtml::sanitize('<p><a href="https://example.invalid/path" onclick="alert(1)">Site</a> <a href="javascript:bad()">Drop</a></p>');
        check('Links keep safe schemes and gain noopener target attributes',str_contains($link,'href="https://example.invalid/path"')&&str_contains($link,'rel="noopener noreferrer"')&&str_contains($link,'target="_blank"'));
        check('Event handlers and javascript schemes are removed',!str_contains($link,'onclick')&&!str_contains($link,'javascript')&&substr_count($link,'href')===1);
        $mailto=ComposeHtml::sanitize('<a href="mailto:client@example.invalid">Mail</a>');
        check('Mailto links survive with forced safe attributes',str_contains($mailto,'mailto:client&#64;example.invalid')&&str_contains($mailto,'rel="noopener noreferrer"')&&str_contains($mailto,'target="_blank"'));
        foreach(['javascript:alert(1)','data:text/html,bad','file:///private','/relative'] as $scheme) {
            check('Unsafe or relative link is removed: '.$scheme,!str_contains(ComposeHtml::sanitize('<a href="'.$scheme.'">x</a>'),'href'));
        }
        $hostile=ComposeHtml::sanitize('<unknown data-x="1"><script>bad()</script><style>x{}</style><form><input></form><iframe srcdoc="x"></iframe><img src="https://x.invalid/i.png"><svg><a>y</a></svg><object></object></unknown><p style="x:y" class="x" id="y">Keep</p>');
        check('Unknown active resource and presentation markup is removed',!preg_match('/unknown|data-x|script|style=|class=|id=|form|input|iframe|img|svg|object/',$hostile)&&str_contains($hostile,'<p>Keep</p>'));
        check('Tables survive for formatted layouts',str_contains(ComposeHtml::sanitize('<table><tr><th>H</th></tr><tr><td>C</td></tr></table>'),'<td>C</td>'));
        denied('Input over the byte bound is refused',fn()=>ComposeHtml::sanitize('<p>'.str_repeat('x',61440).'</p>'),'limit');
        denied('Input over the tag-count bound is refused',fn()=>ComposeHtml::sanitize(str_repeat('<b></b>',2100)),'limit');
        denied('Parsed node count is bounded',fn()=>ComposeHtml::sanitize('<p>'.str_repeat('x<br>',1024).'</p>'),'limit');
        denied('Parsed nesting depth is bounded',fn()=>ComposeHtml::sanitize(str_repeat('<div>',34).'x'.str_repeat('</div>',34)),'limit');
        denied('Invalid UTF-8 is refused',fn()=>ComposeHtml::sanitize("<p>\xC3\x28</p>"),'protocol');
        denied('NUL bytes are refused',fn()=>ComposeHtml::sanitize("<p>x\0y</p>"),'protocol');
        $canonical=ComposeHtml::sanitize('<p>Round <em>trip</em> <a href="https://example.invalid/">Site</a></p>');
        $portable="<pre>\nportable</pre>";
        check('Accepted canonical output is an exact portable fixed point',ComposeHtml::sanitize($canonical)===$canonical&&ComposeHtml::sanitize($portable)===$portable);
        check('DraftContent accepts only that exact canonical value',(new DraftContent([],[],[],'','Plain',$canonical))->html===$canonical);
    } else {
        denied('DOM extension unavailable; sanitizer execution fails with protocol refusal',fn()=>ComposeHtml::sanitize('<p>Formatted</p>'),'protocol');
        draft_denied('Formatted input fails closed without DOM','<p>Formatted</p>');
    }

    foreach([
        '<a href="javascript:alert(1)" data-x="1">x</a>',
        '<a href="data:text/html,bad">x</a>',
        '<a href="file:///private">x</a>',
        '<a href="/relative">x</a>',
        '<p data-x="1">x</p>',
        '<unknown>x</unknown>',
        '<p onclick="x">x</p>',
        '<iframe srcdoc="x"></iframe>',
        '<p style="x:y">x</p>',
        '<p class="x">x</p>',
        '<p id="x">x</p>',
        '<script>bad()</script>',
        '<img src="https://example.invalid/x.png">',
    ] as $i=>$unsafe) draft_denied('DraftContent refuses noncanonical markup '.$i,$unsafe);
    draft_denied('DraftContent refuses malformed UTF-8',"<p>\xC3\x28</p>");
    draft_denied('DraftContent refuses NUL bytes',"<p>x\0y</p>");
    draft_denied('DraftContent refuses excessive tags',str_repeat('<b></b>',2100));
    draft_denied('DraftContent refuses excessive nesting',str_repeat('<div>',34).'x'.str_repeat('</div>',34));
    draft_denied('DraftContent refuses excessive bytes',str_repeat('x',ComposeHtml::MAX_INPUT_BYTES+1));
    $complete=true;
} finally {
    if(isset($argv[1]))file_put_contents($argv[1],json_encode(['passed'=>$complete&&!in_array(false,array_column($checks,'passed'),true),'scope'=>'Isolated outbound HTML sanitizer and exact canonical draft alternative only; synthetic fixture markup; no persistence, transport or network','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." named formatted-compose checks\n";
