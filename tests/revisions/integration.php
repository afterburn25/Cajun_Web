<?php
declare(strict_types=1);

/** Offline revision adapter, mirror and escaped-view contract; no SQL or browser. */
$root=dirname(__DIR__,2);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',$root.'/site');
require $root.'/site/app/settings/revisions.php';

$checks=[];
function revision_integration_ok(string $name,bool $passed):void {
    global $checks;
    $checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed)throw new RuntimeException($name);
}

foreach(['ApprovalContext.php','ContentDocument.php','ContentRevision.php','RevisionDiff.php'] as $file){
    $canonical=$root.'/foundation/revisions/'.$file;
    $shipping=$root.'/site/includes/foundation/revisions/'.$file;
    revision_integration_ok('Shipping revision mirror matches canonical '.$file,
        is_file($shipping)&&hash_file('sha256',$shipping)===hash_file('sha256',$canonical));
}

$row=['id'=>42,'setting_name'=>'growth_intro','old_value'=>"kept\nremoved <tag>",'new_value'=>"kept\nadded & value",'author_id'=>7,'created_at'=>'2026-11-11 12:00:00'];
$diff=studio_revision_diff($row);
revision_integration_ok('Persisted row adapter binds stable revision identities',$diff->fromRevisionId==='r42-before'&&$diff->toRevisionId==='r42-after');
revision_integration_ok('Persisted row adapter reports exact line sets',$diff->removed===['removed <tag>']&&$diff->added===['added & value']&&$diff->changedLineCount()===2);
$same=studio_revision_diff([...$row,'new_value'=>$row['old_value']]);
revision_integration_ok('Persisted row adapter reports an identical restore honestly',$same->identical()&&$same->changedLineCount()===0);
$reordered=studio_revision_diff([...$row,'old_value'=>"first\nsecond",'new_value'=>"second\nfirst"]);
revision_integration_ok('Reordered persisted content is not identical',!$reordered->identical()&&$reordered->changedLineCount()===0);
$duplicates=studio_revision_diff([...$row,'old_value'=>"a\na\nb",'new_value'=>"a\nb\nc"]);
revision_integration_ok('Persisted duplicate lines retain exact unmatched multiplicity',$duplicates->removed===['a']&&$duplicates->added===['c']&&$duplicates->changedLineCount()===2);

$view=file_get_contents($root.'/site/admin/views/studio/history.php');
$css=file_get_contents($root.'/site/assets/css/admin.css');
revision_integration_ok('History view uses the reviewed adapter and preserves full values',
    is_string($view)&&str_contains($view,'studio_revision_diff($r)')&&str_contains($view,'Full saved values')
    &&str_contains($view,"esc(\$r['old_value'])")&&str_contains($view,"esc(\$r['new_value'])"));
revision_integration_ok('History view escapes every added and removed line',
    substr_count($view,'esc($line)')===2&&!str_contains($view,'echo $line'));
revision_integration_ok('History view reserves identical copy for byte-identical values',
    str_contains($view,'$same=$diff->identical()')
    &&str_contains($view,'line occurrence')
    &&str_contains($view,'Line order or repetition changed — review the full saved values')
    &&!str_contains($view,"\$same=(string)\$r['old_value']===(string)\$r['new_value']"));
revision_integration_ok('History view retains explicit restore confirmation and optimistic hash',
    str_contains($view,"admin_check('confirm','Restore the Before value as a new revision.',false)")
    &&str_contains($view,"hash('sha256',setting(\$r['setting_name']))"));
revision_integration_ok('History diff styles distinguish additions and removals',
    is_string($css)&&str_contains($css,'.diff-add{')&&str_contains($css,'.diff-remove{')&&str_contains($css,'text-decoration:line-through'));

echo 'PASS: '.count($checks)." revision integration checks; offline and read-only.\n";
