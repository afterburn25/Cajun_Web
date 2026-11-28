<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../foundation/projects/ProjectActionItems.php';
use CajunWebPro\Foundation\Projects\{ProjectActionItems,ProjectReviewAction};

$checks=[];
function pa_ok(string $name,bool $passed):void {global $checks;if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate check name.');$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pa_refuses(callable $fn):bool {try{$fn();return false;}catch(InvalidArgumentException){return true;}}
function pa_readonly(callable $fn):bool {try{$fn();return false;}catch(Error){return true;}}
function pa_row(int $id,string $kind,int $version,string $decision='pending'):array {return ['id'=>$id,'kind'=>$kind,'version_no'=>$version,'decision'=>$decision];}

pa_ok('empty authorized review set has no actions',ProjectActionItems::pendingReviews([])===[]);
$one=ProjectActionItems::pendingReviews([pa_row(7,'design',1)]);
pa_ok('one pending review yields one privacy-minimal action',count($one)===1&&$one[0] instanceof ProjectReviewAction&&$one[0]->reviewId===7&&$one[0]->kind==='design'&&$one[0]->version===1);
pa_ok('action JSON carries no project content or customer identity',array_keys(json_decode(json_encode($one[0],JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR))===['reviewId','kind','version']);
pa_ok('action identity is readonly',pa_readonly(function()use($one):void{$one[0]->reviewId=8;}));

$mixed=ProjectActionItems::pendingReviews([
    pa_row(10,'design',1),pa_row(12,'proposal',1),pa_row(14,'design',2,'approved'),pa_row(16,'proposal',2),
]);
pa_ok('decided latest review suppresses its older pending kind',count($mixed)===1&&$mixed[0]->reviewId===16&&$mixed[0]->kind==='proposal'&&$mixed[0]->version===2);
$superseded=ProjectActionItems::pendingReviews([pa_row(30,'design',3),pa_row(20,'design',1),pa_row(25,'design',2)]);
pa_ok('only highest version per kind remains actionable',count($superseded)===1&&$superseded[0]->reviewId===30&&$superseded[0]->version===3);
$outOfOrder=ProjectActionItems::pendingReviews([pa_row(90,'proposal',1),pa_row(3,'design',2),pa_row(80,'design',1),pa_row(4,'proposal',2)]);
pa_ok('input order and id order do not replace version authority',array_map(fn($a)=>$a->reviewId,$outOfOrder)===[4,3]);
$both=ProjectActionItems::pendingReviews([pa_row(2,'design',1),pa_row(9,'proposal',1)]);
pa_ok('action output is deterministic newest identity first',array_map(fn($a)=>$a->reviewId,$both)===[9,2]);
$rows=[pa_row(1,'design',1)];$copied=ProjectActionItems::pendingReviews($rows);$rows[0]['id']=99;
pa_ok('projection detaches caller rows',$copied[0]->reviewId===1);

foreach([
    [pa_row(1,'design',1),pa_row(1,'proposal',1)],
    [pa_row(1,'design',1),pa_row(2,'design',1)],
    [pa_row(1,'proposal',1),pa_row(2,'proposal',1,'approved')],
] as $i=>$duplicate)pa_ok('duplicate identity refuses '.$i,pa_refuses(fn()=>ProjectActionItems::pendingReviews($duplicate)));

foreach([
    null,false,'row',['id'=>1],['id'=>1,'kind'=>'design','version_no'=>1,'decision'=>'pending','title'=>'private'],
    ['kind'=>'design','id'=>1,'version_no'=>1,'decision'=>'pending'],
] as $i=>$row)pa_ok('malformed row refuses '.$i,pa_refuses(fn()=>ProjectActionItems::pendingReviews([$row])));

foreach([0,-1,4294967296,'1',1.0] as $i=>$id){$row=pa_row(1,'design',1);$row['id']=$id;pa_ok('invalid review id refuses '.$i,pa_refuses(fn()=>ProjectActionItems::pendingReviews([$row])));}
foreach([0,-1,4294967296,'1',1.0] as $i=>$version){$row=pa_row(1,'design',1);$row['version_no']=$version;pa_ok('invalid version refuses '.$i,pa_refuses(fn()=>ProjectActionItems::pendingReviews([$row])));}
foreach(['','Design','invoice','design ','proposal\n',1,null] as $i=>$kind){$row=pa_row(1,'design',1);$row['kind']=$kind;pa_ok('invalid review kind refuses '.$i,pa_refuses(fn()=>ProjectActionItems::pendingReviews([$row])));}
foreach(['','paid','superseded','PENDING',1,null] as $i=>$decision){$row=pa_row(1,'design',1);$row['decision']=$decision;pa_ok('invalid decision refuses '.$i,pa_refuses(fn()=>ProjectActionItems::pendingReviews([$row])));}
pa_ok('associative outer rows refuse',pa_refuses(fn()=>ProjectActionItems::pendingReviews(['first'=>pa_row(1,'design',1)])));
pa_ok('row limit is inclusive',count(ProjectActionItems::pendingReviews(array_map(fn($i)=>pa_row($i+1,$i%2?'design':'proposal',$i+1,'approved'),range(0,ProjectActionItems::MAX_REVIEW_ROWS-1))))===0);
pa_ok('row limit overflow refuses',pa_refuses(fn()=>ProjectActionItems::pendingReviews(array_fill(0,ProjectActionItems::MAX_REVIEW_ROWS+1,pa_row(1,'design',1)))));
pa_ok('direct action constructor rejects invalid identity',pa_refuses(fn()=>new ProjectReviewAction(0,'design',1))&&pa_refuses(fn()=>new ProjectReviewAction(1,'invoice',1))&&pa_refuses(fn()=>new ProjectReviewAction(1,'design',0)));

$passed=!array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['passed'=>$passed,'count'=>count($checks),'scope'=>'Pure already-authorized pending-review projection only; no SQL, route, customer message, billing item, mutation or live operation.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
