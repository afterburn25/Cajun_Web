<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require __DIR__.'/../../foundation/mail-compose/DraftOrigin.php';

use CajunWebPro\Foundation\MailCompose\{DraftOrigin, ReplyDraft};

$checks=[];
function origin_check(string $name, callable $test): void {
    global $checks;
    try {$ok=$test()===true;} catch (Throwable $e) {$ok=false;$error=get_class($e).': '.$e->getMessage();}
    $checks[]=['name'=>$name,'passed'=>$ok]+(isset($error)?['error'=>$error]:[]);
}
function origin_denied(callable $test): bool {
    try {$test();return false;} catch (InvalidArgumentException|JsonException) {return true;}
}
function prepared_origin(string $mode='reply'): ReplyDraft {
    return ReplyDraft::prepare($mode,
        "From: Source <source@example.invalid>\r\n".
        "Date: Tue, 14 Oct 2025 10:00:00 +0000\r\n".
        "Subject: Synthetic origin\r\n".
        "Message-ID: <source@example.invalid>\r\n".
        "Content-Type: text/plain; charset=UTF-8\r\n\r\n".
        "Synthetic copied body."
    );
}
function synthetic_origin(string $mode='reply'): DraftOrigin {
    return DraftOrigin::create(
        str_repeat('1',32),7,str_repeat('2',32),3,'9','44',str_repeat('3',32),str_repeat('4',32),5,
        prepared_origin($mode)
    );
}

origin_check('canonical origin round trips with an exact fingerprint',function(){
    $origin=synthetic_origin();
    $copy=DraftOrigin::fromJson($origin->json);
    return $copy->json===$origin->json && $copy->fingerprint===hash('sha256',$origin->json);
});
origin_check('origin binds source, author, outgoing account and exact message identity',function(){
    $v=synthetic_origin()->data;
    return $v['draft_id']===str_repeat('1',32) && $v['author_id']===7
        && $v['source_account_id']===str_repeat('2',32) && $v['source_revision']===3
        && $v['uidvalidity']==='9' && $v['uid']==='44' && $v['read_id']===str_repeat('3',32)
        && $v['initial_account_id']===str_repeat('4',32) && $v['initial_account_revision']===5;
});
origin_check('reply origin preserves bounded conversation headers',fn()=>synthetic_origin()->data['thread']===['in_reply_to'=>'<source@example.invalid>','references'=>['<source@example.invalid>']]);
origin_check('forward origin cannot inherit reply headers',fn()=>synthetic_origin('forward')->data['thread']===['in_reply_to'=>null,'references'=>[]]);
origin_check('content encoding round trips exactly',function(){
    $content=prepared_origin()->content;
    return DraftOrigin::contentFromJson(DraftOrigin::contentJson($content))==$content;
});
origin_check('content JSON preserves Unicode without escaped slashes',function(){
    $content=prepared_origin()->content;
    $json=DraftOrigin::contentJson($content);
    return !str_contains($json,'\\/') && DraftOrigin::contentFromJson($json)->body===$content->body;
});
foreach([
    'float format'=>fn($json)=>str_replace('"format":1','"format":1.0',$json),
    'duplicate key'=>fn($json)=>str_replace('"format":1','"format":1,"format":1',$json),
    'out-of-range UID'=>fn($json)=>str_replace('"uid":"44"','"uid":"4294967296"',$json),
    'unknown omission'=>fn($json)=>str_replace('"omissions":[]','"omissions":["unknown"]',$json),
    'changed key order'=>function($json){$value=json_decode($json,true,8,JSON_THROW_ON_ERROR);$format=$value['format'];unset($value['format']);$value['format']=$format;return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);},
] as $label=>$mutate) origin_check('origin refuses '.$label,fn()=>origin_denied(fn()=>DraftOrigin::fromJson($mutate(synthetic_origin()->json))));
origin_check('content rejects unknown fields',fn()=>origin_denied(fn()=>DraftOrigin::contentFromJson('{"to":[],"cc":[],"bcc":[],"subject":"s","body":"b","extra":true}')));
origin_check('content rejects noncanonical address normalization',fn()=>origin_denied(fn()=>DraftOrigin::contentFromJson('{"to":["A@EXAMPLE.invalid"],"cc":[],"bcc":[],"subject":"s","body":"b"}')));
origin_check('oversized origin is refused before parsing',fn()=>origin_denied(fn()=>DraftOrigin::fromJson(str_repeat('x',16385))));

$passed=!array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['scope'=>'Canonical copied-draft origin and content binding with synthetic values; no database, authority, network, application, MIME delivery or persistence acceptance','passed'=>$passed,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
