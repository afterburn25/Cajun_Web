<?php
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../site');require CWP_CODE_ROOT.'/app/providers/plan_catalog_document.php';
$checks=[];
function pc_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pc_bad(string $name,callable $f):void {try{$f();}catch(InvalidArgumentException|RuntimeException|TypeError|JsonException){pc_ok($name,true);return;}pc_ok($name,false);}
$config=['reseller_id'=>42,'type'=>'811','label'=>'Reviewed package','region'=>'Reviewed region','limits'=>['websites'=>1,'disk_mb'=>0],'cost'=>['amount'=>'12.5000','currency'=>'GBP','period_months'=>12,'tax'=>'excluded','basis'=>'shared-account','reference'=>'Synthetic account review']];
$config=provider_plan_catalog_config($config);
pc_ok('Cost stays exact decimal and separate from cents',$config['cost']['amount']==='12.5000');pc_ok('Unknown wholesale cost is explicit null',provider_plan_catalog_cost(null)===null);
foreach(['12e2',' 12.50','12.50 ','-1','+1','01.50','NaN','Infinity','1,50','1.12345','1000000000','',12.50,500] as $i=>$amount)pc_bad('Reject malformed provider cost '.$i,fn()=>provider_plan_catalog_cost(array_replace($config['cost'],['amount'=>$amount])));
foreach([['currency'=>'gbp'],['currency'=>'US'],['currency'=>'USDX'],['period_months'=>0],['period_months'=>121],['period_months'=>'12'],['tax'=>'free'],['basis'=>'per-client'],['reference'=>"x\nsecret"],['unknown'=>'field']] as $i=>$change)pc_bad('Reject ambiguous cost context '.$i,fn()=>provider_plan_catalog_cost(array_replace($config['cost'],$change)));
foreach([['type'=>'811/x'],['type'=>'0'],['reseller_id'=>0],['limits'=>[]],['limits'=>['websites'=>null]],['limits'=>['unlimited'=>1]],['limits'=>['disk_mb'=>-1]],['limits'=>['disk_mb'=>'1']],['region'=>''],['label'=>"a\rb"],['price'=>50]] as $i=>$change)pc_bad('Reject unsupported catalog configuration '.$i,fn()=>provider_plan_catalog_config(array_replace($config,$change)));
pc_ok('Limits preserve explicit zero and canonical names',array_keys($config['limits'])===['disk_mb','websites']&&$config['limits']['disk_mb']===0);
$doc=['format'=>1,'id'=>str_repeat('a',32),'plan_id'=>str_repeat('b',32),'revision'=>1,'previous_id'=>null,'previous_sha256'=>null,'plan_revision'=>1,'plan_sha256'=>str_repeat('c',64),'profile_id'=>str_repeat('d',32),'profile_revision'=>1,'credential_sha256'=>str_repeat('e',64),'created_by'=>1,'owner_auth'=>'synthetic-owner-context','created_at'=>100,'expires_at'=>86500,'enabled'=>true,'configuration'=>$config,'evidence'=>'owner-account-review'];
pc_ok('Typed document retains owner-review evidence',provider_plan_catalog_document(service_plans_json($doc))===$doc);
foreach([['format'=>2],['revision'=>129],['enabled'=>1],['expires_at'=>100],['expires_at'=>100+31*86400],['previous_id'=>str_repeat('b',32)],['evidence'=>'provider-verified'],['owner_auth'=>''],['plan_revision'=>0],['credential_sha256'=>'secret']] as $i=>$change)pc_bad('Reject invalid review document '.$i,fn()=>provider_plan_catalog_document(service_plans_json(array_replace($doc,$change))));
pc_bad('Reject noncanonical document whitespace',fn()=>provider_plan_catalog_document(' '.service_plans_json($doc)));
$p=provider_plan_catalog_package($config,str_repeat('a',32));pc_ok('Mapping creates typed package with no currency or price inference',$p->providerPackageType==='811'&&$p->limits===['disk_mb'=>0,'websites'=>1]);
$out=['passed'=>true,'scope'=>'Pure synthetic typed catalog/cost validation; no database, browser or provider call.','php'=>PHP_VERSION,'checks'=>$checks];if(isset($argv[1]))file_put_contents($argv[1],json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'PASS: '.count($checks).' catalog document checks'.PHP_EOL;
