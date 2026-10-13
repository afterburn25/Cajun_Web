<?php
/** Batched parity against actual release bytes plus independent expected results. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$checks=[];
function check(string $name,bool $value): void {
    global $checks;$checks[]=['name'=>$name,'passed'=>$value];
    if(!$value)throw new RuntimeException($name);
}
function evaluate(string $which,array $cases): array {
    $process=proc_open([PHP_BINARY,__DIR__.'/estimator_oracle.php',$which],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start isolated estimator oracle.');
    fwrite($pipes[0],json_encode($cases,JSON_THROW_ON_ERROR));fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Estimator oracle failed: '.$err);
    return json_decode($out,true,512,JSON_THROW_ON_ERROR);
}
$base=['enabled'=>true];
foreach(['business','redesign','store','page','product','copy','brand','migration','booking','accounts','integration'] as $key)$base[$key]=[100,200];
$variants=['priced'=>$base,'disabled'=>array_replace($base,['enabled'=>false]),'exact-cap'=>array_replace($base,['business'=>[99999999,99999999]]),'invalid-range'=>array_replace($base,['business'=>[0,100000000]]),'missing-before-malformed'=>array_replace($base,['business'=>[null,null],'page'=>[300,200]]),'zero'=>array_replace($base,['business'=>[0,0]])];
foreach(['business','store','page','product','copy'] as $key){
    $variants['unpriced-'.$key]=array_replace($base,[$key=>[null,null]]);
    $rules=$base;unset($rules[$key]);$variants['missing-'.$key]=$rules;
}
$cases=[];$names=[];
foreach(['business','redesign','store','custom'] as $type)foreach([1,5,6,100] as $pages)foreach([0,7,10000] as $products)foreach([[],['copy'],['copy','copy','brand'],['integration']] as $options)foreach($variants as $variant=>$rules){
    $cases[]=['input'=>['project_type'=>$type,'pages'=>(string)$pages,'products'=>(string)$products,'options'=>$options,'low'=>'1','high'=>'2'],'rules'=>$rules];
    $names[]='Published estimator parity: '.$type.' pages '.$pages.' products '.$products.' options '.json_encode($options).' '.$variant;
}
$valid=['project_type'=>'business','pages'=>'5','products'=>'0','options'=>[]];
foreach(['project_type'=>'unknown','pages'=>'101','products'=>['untrusted'],'options'=>['unknown'],'low'=>['untrusted']] as $key=>$value){
    $cases[]=['input'=>array_replace($valid,[$key=>$value]),'rules'=>$base];$names[]='Published validation or ignored field: '.$key;
}
try{
    $expected=evaluate('baseline',$cases);$actual=evaluate('current',$cases);
    check('Both actual calculators return every submitted case',count($expected)===count($cases)&&count($actual)===count($cases));
    foreach($cases as $i=>$case)check($names[$i],$actual[$i]===$expected[$i]);
    $explicit=[
        ['input'=>array_replace($valid,['pages'=>'7']),'rules'=>$base],
        ['input'=>array_replace($valid,['project_type'=>'store','pages'=>'6','products'=>'7','options'=>['copy','copy']]),'rules'=>$base],
        ['input'=>$valid,'rules'=>array_replace($base,['business'=>[99999999,99999999]])],
        ['input'=>array_replace($valid,['pages'=>'6']),'rules'=>array_replace($base,['business'=>[99999999,99999999]])],
        ['input'=>$valid,'rules'=>array_replace($base,['business'=>[0,0]])],
        ['input'=>$valid,'rules'=>array_replace($base,['business'=>[0,100000000]])],
    ];
    $out=evaluate('current',$explicit);
    check('Seven-page business counts only two additional pages',$out[0]['value']['low']===300&&$out[0]['value']['high']===600&&$out[0]['value']['label']==='$3.00 – $6.00 USD');
    check('Store quantities and duplicate option produce exact range',$out[1]['value']['low']===1000&&$out[1]['value']['high']===2000);
    check('Exact configured cap remains a priced range',$out[2]['value']['high']===99999999);
    check('Valid amounts above total cap require consultation',$out[3]['value']['mode']==='consultation'&&$out[3]['value']['high']===null);
    check('Explicit zero-price rule is not treated as missing',$out[4]['value']['mode']==='range'&&$out[4]['value']['high']===0);
    check('Out-of-range configured price remains a validation error',($out[5]['error']??'')===InvalidArgumentException::class);
}finally{
    $qa=__DIR__.'/../qa';if(!is_dir($qa))mkdir($qa,0755,true);
    file_put_contents($qa.'/estimator-adapter-results.json',json_encode(['passed'=>count(array_filter($checks,fn($c)=>$c['passed'])),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS: '.count($checks)." estimator adapter parity/boundary checks against verified 1.11.1; no database or network access.\n";
