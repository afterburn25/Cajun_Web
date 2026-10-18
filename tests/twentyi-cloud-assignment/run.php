<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/twentyi-cloud-assignment/CloudAssignmentBinding.php';
use CajunWebPro\Foundation\TwentyICloudAssignment\CloudAssignmentBinding;
$checks=[];
function ca_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function ca_no(string $name,callable $f):void{try{$f();}catch(InvalidArgumentException){ca_ok($name,true);return;}ca_ok($name,false);}
$data=['profile_id'=>str_repeat('a',32),'profile_revision'=>1,'credential_sha256'=>str_repeat('b',64),'key_fingerprint'=>str_repeat('c',64),'server_id'=>2601,'client_id'=>1,'link_id'=>str_repeat('d',32),'link_revision'=>2,'link_sha256'=>str_repeat('e',64),'event_id'=>5,'event_proof'=>str_repeat('f',64),'source_id'=>str_repeat('1',32),'source_seq'=>17,'start_proof'=>str_repeat('2',64),'finish_proof'=>str_repeat('3',64),'server_sha256'=>str_repeat('4',64)];
try{
    $b=new CloudAssignmentBinding($data);ca_ok('Exact ordered cloud binding is canonical',$b->data===$data&&$b->json===json_encode($data,JSON_THROW_ON_ERROR)&&$b->sha256===hash('sha256',$b->json));
    ca_ok('Validated returns detached equal value',CloudAssignmentBinding::validated($b)!==$b&&CloudAssignmentBinding::validated($b)->json===$b->json);
    foreach($data as $key=>$value){$missing=$data;unset($missing[$key]);ca_no('Missing '.$key.' refuses',fn()=>new CloudAssignmentBinding($missing));$wrong=$data;$wrong[$key]=is_int($value)?(string)$value:123;ca_no('Wrong type '.$key.' refuses',fn()=>new CloudAssignmentBinding($wrong));
        if(is_int($value)){foreach([0,-1,1000000000000000000] as $bad){$wrong=$data;$wrong[$key]=$bad;ca_no('Out of bounds '.$key.' '.$bad.' refuses',fn()=>new CloudAssignmentBinding($wrong));}}
        else{foreach(['',strtoupper($value.'a'),$value."\n"] as $i=>$bad){$wrong=$data;$wrong[$key]=$bad;ca_no('Malformed identity '.$key.' variant '.$i.' refuses',fn()=>new CloudAssignmentBinding($wrong));}}
    }
    $extra=$data;$extra['authority']=true;ca_no('Unexpected authority field refuses',fn()=>new CloudAssignmentBinding($extra));ca_no('Reordered document refuses',fn()=>new CloudAssignmentBinding(array_reverse($data,true)));
    $mutable=2601;$referenced=$data;$referenced['server_id']=&$mutable;$detached=new CloudAssignmentBinding($referenced);$mutable=2602;ca_ok('Constructor detaches referenced caller fields',$detached->data['server_id']===2601&&$detached->json===$b->json);
    $reflection=new ReflectionClass(CloudAssignmentBinding::class);$restored=$reflection->newInstanceWithoutConstructor();$mutable=2601;$referenced['server_id']=&$mutable;
    foreach(['data'=>$referenced,'json'=>$b->json,'sha256'=>$b->sha256] as $key=>$value)$reflection->getProperty($key)->setValue($restored,$value);
    $validated=CloudAssignmentBinding::validated($restored);$mutable=2602;ca_ok('Restored reference is detached by validated snapshot',$validated->data['server_id']===2601&&$restored->data['server_id']===2602);ca_no('Changed restored source is rejected against retained derived fields',fn()=>CloudAssignmentBinding::validated($restored));
    foreach(['json'=>'{}','sha256'=>str_repeat('0',64)] as $field=>$value){$forged=$reflection->newInstanceWithoutConstructor();foreach(['data'=>$data,'json'=>$b->json,'sha256'=>$b->sha256] as $key=>$original)$reflection->getProperty($key)->setValue($forged,$key===$field?$value:$original);ca_no('Forged derived '.$field.' refuses',fn()=>CloudAssignmentBinding::validated($forged));}
    $maximum=$data;$maximum['server_id']=999999999999999999;ca_ok('Exact largest local server identity retained',(new CloudAssignmentBinding($maximum))->data['server_id']===999999999999999999);
    $out=['scope'=>'Portable immutable local binding checks only; no provider, database or application execution.','checks'=>$checks];if(isset($argv[1]))file_put_contents($argv[1],json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo count($checks)." cloud assignment binding checks passed\n";
}catch(Throwable $e){if(isset($argv[1]))file_put_contents($argv[1],json_encode(['checks'=>$checks,'failure'=>$e->getMessage()],JSON_PRETTY_PRINT));fwrite(STDERR,$e->getMessage()."\n");exit(1);}
