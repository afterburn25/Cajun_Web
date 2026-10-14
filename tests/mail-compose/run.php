<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../../foundation/mail-compose/DraftContent.php';
require __DIR__.'/../../foundation/email-templates/PlaceholderRegistry.php';
use CajunWebPro\Foundation\MailCompose\DraftContent;
use CajunWebPro\Foundation\EmailTemplates\{EmailTemplate,PlaceholderRegistry};

$checks=[];
function check(string $name,bool $passed):void {
    global $checks;$checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed)throw new RuntimeException('Failed named check: '.$name);
}
function rejects(string $name,callable $test):void {
    try{$test();}catch(InvalidArgumentException){check($name,true);return;}
    check($name,false);
}
function content(array $to=[],array $cc=[],array $bcc=[],string $subject='',string $body=''):DraftContent {
    return new DraftContent($to,$cc,$bcc,$subject,$body);
}
$empty=content();check('An unfinished empty draft does not require recipients or content',$empty->to===[]&&$empty->subject===''&&$empty->body==='');
$valid=content([' Alice+web@EXAMPLE.COM '],['second@example.net'],['private@example.org'],'A website update',"Hello\r\nCafé\rTabbed\ttext");
check('Domain normalization preserves local-part bytes and separate recipient groups',$valid->to===['Alice+web@example.com']&&$valid->cc===['second@example.net']&&$valid->bcc===['private@example.org']);
check('Body normalizes line endings and preserves Unicode and tabs',$valid->body==="Hello\nCafé\nTabbed\ttext");
check('Different local-part case is not silently deduplicated',count(content(['Staff@example.org','staff@example.org'])->to)===2);
check('Bare plus and apostrophe addresses are supported',content(["o'brien+web@example.org"])->to===["o'brien+web@example.org"]);
check('ASCII punycode domain can be entered explicitly',content(['studio@xn--bcher-kva.example'])->to===['studio@xn--bcher-kva.example']);
$all=array_map(fn($i)=>'recipient'.$i.'@example.org',range(1,20));
check('Twenty recipients across three fields are accepted',count(content(array_slice($all,0,8),array_slice($all,8,8),array_slice($all,16))->bcc)===4);
rejects('Combined limit is enforced across To Cc and Bcc',fn()=>content(array_slice($all,0,8),array_slice($all,8,8),[...array_slice($all,16),'extra@example.org']));
foreach([
    'same field'=>[['same@example.org','same@example.org'],[],[]],
    'visible and Bcc'=>[['same@example.org'],[],['same@example.org']],
    'Cc and Bcc'=>[[],['same@EXAMPLE.ORG'],['same@example.org']],
] as $name=>$lists)rejects('Duplicate recipient refused '.$name,fn()=>content(...$lists));
foreach([
    'map'=>['recipient'=>'person@example.org'],
    'sparse'=>[1=>'person@example.org'],
    'integer'=>[42],
    'boolean'=>[true],
    'nested'=>[['person@example.org']],
    'object'=>[(object)['address'=>'person@example.org']],
    'null'=>[null],
] as $name=>$value)rejects('Recipient list rejects '.$name,fn()=>content($value));
foreach([
    '', 'not-an-address','person@example.org,second@example.org','Person <person@example.org>',
    'person(comment)@example.org','"quoted"@example.org','person@[127.0.0.1]',
    'person@localhost',"person@example.org\r\nBcc: other@example.org",
    "\tperson@example.org",'person @example.org',"person@example.org\0",
    'üser@example.org','person@bücher.example','double..dot@example.org',
    '.start@example.org','end.@example.org','person@-example.org',
    str_repeat('a',65).'@example.org',
] as $i=>$address)rejects('Unsupported or injected address '.$i,fn()=>content([$address]));
foreach(["Line\r\nBcc: injected@example.org","tail\n","\ttab","bad\0","bad\x7F","\xC3\x28"] as $i=>$subject)rejects('Unsafe subject '.$i,fn()=>content(subject:$subject));
foreach(["bad\0","bad\x0B","bad\x1F","bad\x7F","\xC3\x28"] as $i=>$body)rejects('Unsafe body '.$i,fn()=>content(body:$body));
check('Exact UTF-8 subject byte limit',strlen(content(subject:str_repeat('é',100))->subject)===200);
rejects('Unicode subject over byte limit',fn()=>content(subject:str_repeat('é',100).'x'));
check('Exact UTF-8 body byte limit',strlen(content(body:str_repeat('é',10000))->body)===20000);
rejects('Unicode body over byte limit',fn()=>content(body:str_repeat('é',10000).'x'));
check('Body limit applies after newline normalization',strlen(content(body:str_repeat("\r\n",20000))->body)===20000);
$hostile='</textarea><img src=x onerror=alert(1)><script>window.bad=1</script>';
check('Hostile HTML remains literal text for later escaped rendering',content(subject:'<b>literal</b>',body:$hostile)->body===$hostile);
$registry=new PlaceholderRegistry(['client_name'=>'Client name','project_name'=>'Project name']);
function template(PlaceholderRegistry $registry,string $visibility='personal',bool $transactional=false):EmailTemplate {
    return EmailTemplate::draft('template','Website update','Hello {{client_name}}','Project {{project_name}}',$visibility,'owner',$transactional,'owner',100,$registry);
}
$source=template($registry);
rejects('Draft template cannot be inserted',fn()=>DraftContent::fromTemplate($source,'owner',['client_name'=>'A','project_name'=>'B']));
$active=$source->activate('owner',101);
$snapshot=DraftContent::fromTemplate($active,'owner',['client_name'=>'A','project_name'=>'B'],['client@example.org']);
check('Active personal template produces editable plaintext snapshot',$snapshot->subject==='Hello A'&&$snapshot->body==='Project B'&&$snapshot->to===['client@example.org']);
rejects('Personal template remains private from another actor',fn()=>DraftContent::fromTemplate($active,'other',['client_name'=>'A','project_name'=>'B']));
$shared=template($registry,'shared')->activate('owner',101);
check('Canonical shared insertion policy is reused',DraftContent::fromTemplate($shared,'other',['client_name'=>'A','project_name'=>'B'])->body==='Project B');
rejects('An empty actor cannot insert even a shared template',fn()=>DraftContent::fromTemplate($shared,'',['client_name'=>'A','project_name'=>'B']));
rejects('Archived template cannot be inserted',fn()=>DraftContent::fromTemplate($active->archive('owner',102),'owner',['client_name'=>'A','project_name'=>'B']));
rejects('Transactional template is excluded from ad hoc compose',fn()=>DraftContent::fromTemplate(template($registry,'shared',true)->activate('owner',101),'owner',['client_name'=>'A','project_name'=>'B']));
rejects('Missing placeholder prevents insertion',fn()=>DraftContent::fromTemplate($active,'owner',['client_name'=>'A']));
rejects('Empty required placeholder prevents insertion',fn()=>DraftContent::fromTemplate($active,'owner',['client_name'=>'A','project_name'=>'']));
rejects('Nonstring replacement is not implicitly cast',fn()=>DraftContent::fromTemplate($active,'owner',['client_name'=>42,'project_name'=>'B']));
rejects('Resolved subject cannot inject a header',fn()=>DraftContent::fromTemplate($active,'owner',['client_name'=>"A\r\nBcc:other@example.org",'project_name'=>'B']));
rejects('Resolved subject still obeys byte cap',fn()=>DraftContent::fromTemplate($active,'owner',['client_name'=>str_repeat('a',201),'project_name'=>'B']));
rejects('Resolved body still obeys byte cap',fn()=>DraftContent::fromTemplate($active,'owner',['client_name'=>'A','project_name'=>str_repeat('a',20001)]));
check('Placeholder values remain literal rather than recursively expanded',DraftContent::fromTemplate($active,'owner',['client_name'=>'{{project_name}}','project_name'=>'Private'])->subject==='Hello {{project_name}}');
$changed=$active->edit('Different','Different','owner',102,$registry);
check('Later source edits cannot rewrite the inserted snapshot',$snapshot->subject==='Hello A'&&$changed->subject==='Different');
check('Source template and canonical placeholders remain intact',$active->subject==='Hello {{client_name}}'&&$active->body==='Project {{project_name}}');
check('Content component exposes no transport or permission method',!method_exists(DraftContent::class,'send')&&!method_exists(DraftContent::class,'authorize'));
$result=['passed'=>true,'scope'=>'Isolated draft content and canonical template insertion only; no DB, authority adapter, UI, MIME, SMTP or remote account tested','checks'=>$checks];
if(isset($argv[1]))file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo 'PASS: '.count($checks)." named draft-content checks\n".$result['scope']."\n";
