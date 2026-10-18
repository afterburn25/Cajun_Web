<?php
/** Real document/service bodies with modeled authority, SQL and transactions; no native DB or provider. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/app/providers/catalog_import_document.php';
$source=__DIR__.'/../site/app/providers/catalog_import.php';
$tokens=token_get_all(file_get_contents($source));$loaded=[];
foreach($tokens as $index=>$token){
    if(!is_array($token)||$token[0]!==T_FUNCTION)continue;
    $j=$index+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
    $name=is_array($tokens[$j]??null)?$tokens[$j][1]:'';
    if(!in_array($name,['provider_catalog_import_create','provider_catalog_import_create_locked','provider_catalog_import_batch'],true))continue;
    $body='';$depth=0;$started=false;
    for($j=$index;$j<count($tokens);$j++){
        $part=$tokens[$j];$body.=is_array($part)?$part[1]:$part;
        if($part==='{'){$depth++;$started=true;}elseif($part==='}'&&--$depth===0&&$started)break;
    }
    eval($body);$loaded[]=$name;
}
$checks=[];$lock=false;$tx=false;$state=[];$context=[];$actor=['id'=>1];$stats=[];$badContext=false;$badOwner=false;$auditFailure=0;
function pcb_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pcb_deny(string $name,callable $call):void{try{$call();}catch(InvalidArgumentException|RuntimeException){pcb_ok($name,true);return;}pcb_ok($name,false);}
function pcb_document(int $count):ProviderCatalogImportDocument{return ProviderCatalogImportDocument::fromRows(array_map(static fn($id)=>['type_ref'=>(string)$id,'package_type_name'=>'Type '.$id],$count?range(1,$count):[]));}
function pcb_reset(int $count=125):void{
    global $state,$context,$stats,$badContext,$badOwner,$auditFailure;
    $state=['products'=>[],'imports'=>[],'count'=>0,'audit'=>[],'next'=>1];
    $context=['profile_id'=>str_repeat('a',32),'revision'=>3,'refresh_id'=>str_repeat('b',32),'source_key_fingerprint'=>str_repeat('c',64),'hash'=>str_repeat('d',64),'document'=>pcb_document($count),'source_operation'=>'package.types'];
    $stats=['writes'=>0,'locks'=>0,'transactions'=>0,'queries'=>0,'max_parameters'=>0,'origins'=>0,'passwords'=>0];$badContext=false;$badOwner=false;$auditFailure=0;
}
function pcb_import(string $id):int{
    global $state,$context;
    $product=$state['next']++;$name=$context['document']->type($id)['name'];
    $state['products'][$product]=['id'=>$product,'sku'=>'CUSTOM-'.$id,'title'=>'Owner wording','amount'=>'18.25','image'=>'original-image','term'=>'year'];
    $state['imports'][$id]=['type_ref'=>$id,'product_id'=>$product,'profile_id'=>$context['profile_id'],'source_revision'=>$context['revision'],'refresh_id'=>$context['refresh_id'],'source_key_fingerprint'=>$context['source_key_fingerprint'],'type_name'=>$name,'source_hash'=>hash('sha256',json_encode([$context['profile_id'],$context['revision'],$context['refresh_id'],$context['source_key_fingerprint'],$id,$name],JSON_THROW_ON_ERROR)),'media_id'=>'original-image','media_revision'=>2];$state['count']++;
    return $product;
}
function staff_locked(callable $call):mixed{global $lock,$stats;if($lock)throw new LogicException('Nested model lock');$lock=true;$stats['locks']++;try{return $call();}finally{$lock=false;}}
function staff_transaction(callable $call):mixed{global $tx,$state,$stats;if($tx)throw new LogicException('Nested model transaction');$before=$state;$tx=true;$stats['transactions']++;try{return $call();}catch(Throwable $e){$state=$before;throw $e;}finally{$tx=false;}}
function provider_catalog_import_transaction():void{global $lock,$tx;if(!$lock||!$tx)throw new LogicException('Missing model guard');}
function provider_catalog_import_owner(?string $password=null):array{global $actor,$badOwner,$stats;provider_catalog_import_transaction();if($password!==null){$stats['passwords']++;if($password!=='synthetic-password')throw new InvalidArgumentException('Password refused');}if($badOwner)throw new RuntimeException('Owner access changed');return $actor;}
function provider_catalog_import_context(string $source='packages.list'):array{global $context,$badContext;provider_catalog_import_transaction();if(!in_array($source,['packages.list','package.types'],true))throw new InvalidArgumentException('Source refused');if($badContext)throw new RuntimeException('Newest source unavailable');return array_replace($context,['source_operation'=>$source]);}
function provider_catalog_import_origins(array $records):array{global $stats;provider_catalog_import_transaction();$stats['origins']++;foreach($records as $r)if(isset($r['corrupt']))throw new RuntimeException('Original provenance damaged');return [];}
function provider_catalog_import_record(int $product):?array{global $state;foreach($state['imports'] as $row)if($row['product_id']===$product)return $row;return null;}
function table(string $name):string{return $name;}
function all(string $sql,array $params=[]):array{
    global $state,$stats;$stats['queries']++;$stats['max_parameters']=max($stats['max_parameters'],count($params));
    if(str_starts_with($sql,'SELECT * FROM catalog_provider_imports WHERE profile_id=? AND type_ref IN (')){$profile=array_shift($params);return array_values(array_filter($state['imports'],static fn($r)=>$r['profile_id']===$profile&&in_array($r['type_ref'],$params,true)));}
    if(str_starts_with($sql,'SELECT id,sku FROM catalog_products WHERE sku IN ('))return array_values(array_filter($state['products'],static fn($p)=>in_array(strtoupper($p['sku']),$params,true)));
    throw new LogicException('Unexpected modeled SELECT');
}
function one(string $sql,array $params=[]):?array{
    global $state;
    if($sql==='SELECT value FROM settings WHERE name=?'&&$params===['provider_catalog_import_count'])return ['value'=>(string)$state['count']];
    if($sql==='SELECT product_id FROM catalog_provider_imports WHERE profile_id=? AND type_ref=?'){$row=$state['imports'][$params[1]]??null;return $row&&$row['profile_id']===$params[0]?['product_id'=>$row['product_id']]:null;}
    throw new LogicException('Unexpected modeled row lookup');
}
function query(string $sql,array $params=[]):void{
    global $state;
    if(!str_starts_with($sql,'INSERT INTO catalog_provider_imports '))throw new LogicException('Unexpected modeled write');
    $row=array_combine(['profile_id','type_ref','product_id','source_revision','refresh_id','source_key_fingerprint','type_name','source_hash','actor_id','created_at'],$params);
    if(isset($state['imports'][$row['type_ref']]))throw new RuntimeException('Duplicate import');
    $state['imports'][$row['type_ref']]=$row+['media_id'=>null,'media_revision'=>0];
}
function catalog_product_input(array $input):array{if(!preg_match('/\A[A-Z0-9][A-Z0-9_-]{1,59}\z/D',$input['sku']))throw new InvalidArgumentException('Invalid modeled SKU');return $input;}
function catalog_save_in_transaction(int $id,int $expected,array $input):int{global $state,$stats;provider_catalog_import_transaction();catalog_product_input($input);if($id!==0||$expected!==0)throw new LogicException('Only new drafts');foreach($state['products'] as $product)if(strcasecmp($product['sku'],$input['sku'])===0)throw new RuntimeException('Duplicate SKU');$id=$state['next']++;$stats['writes']++;$state['products'][$id]=['id'=>$id]+$input;return $id;}
function save_setting(string $name,string $value):void{global $state;if($name!=='provider_catalog_import_count')throw new LogicException('Unexpected setting');$state['count']=(int)$value;}
function catalog_audit(int $actor,string $action,string $detail):void{global $state,$auditFailure,$stats;if($auditFailure&&$stats['writes']===$auditFailure)throw new RuntimeException('Modeled audit failure');$state['audit'][]=[$actor,$action,$detail];}
function pcb_batch(string $prefix='HOST',int $offset=0,string $source='package.types'):array{global $context;return provider_catalog_import_batch($context['hash'],'month',$prefix,'synthetic-password',$source,$offset);}
try{
    pcb_ok('Exact production single-create shared writer and batch bodies loaded',count($loaded)===3);
    $doc=pcb_document(125);$p=$doc->batchPlan([],'month','HOST');
    pcb_ok('Planner covers whole saved document beyond preview page with at most fifty creations',count($p['selected'])===50&&count($p['missing'])===125&&$p['total_types']===125&&$p['remaining_count']===75);
    pcb_ok('Every generated SKU binds the exact canonical type identifier',!array_filter($p['missing'],static fn($row)=>$row['sku']!=='HOST-'.$row['type_ref']));
    pcb_ok('Planner uses stable document order rather than provider input order',$doc->batchPlan([],'month','HOST')===ProviderCatalogImportDocument::fromRows(array_reverse(array_map(static fn($id)=>['type_ref'=>(string)$id,'package_type_name'=>'Type '.$id],range(1,125))))->batchPlan([],'month','HOST'));
    foreach(['','lower',' BAD','BAD ','-BAD','A/B','A.B',str_repeat('A',41),"A\n"] as $i=>$bad)pcb_deny('Reject unsafe or noncanonical prefix '.$i,fn()=>$doc->batchPlan([],'month',$bad));
    foreach(['','annual','MONTH'] as $term)pcb_deny('Explicit supported period required '.json_encode($term),fn()=>$doc->batchPlan([],$term,'HOST'));
    foreach([-1,126] as $offset)pcb_deny('Reject out-of-document cursor '.$offset,fn()=>$doc->batchPlan([],'month','HOST',$offset));
    foreach([['01'],[1],['1','1'],['999'],['x'=>'1']] as $i=>$ids)pcb_deny('Existing identity shape must be exact '.$i,fn()=>$doc->batchPlan($ids,'month','HOST'));
    $max=ProviderCatalogImportDocument::fromRows([['type_ref'=>str_repeat('9',18),'package_type_name'=>'Maximum']])->batchPlan([],'year',str_repeat('A',40));
    pcb_ok('Maximum prefix plus eighteen-digit ID stays within catalog SKU length',strlen($max['selected'][0]['sku'])===59);
    pcb_ok('Empty valid source has honest zero work',pcb_document(0)->batchPlan([],'once','H')===['missing'=>[],'selected'=>[],'existing_count'=>0,'remaining_count'=>0,'next_offset'=>null,'total_types'=>0]);
    $existing=array_column($doc->types,'type_ref');$p=$doc->batchPlan($existing,'month','HOST');pcb_ok('All-existing document has no work or continuation',$p['selected']===[]&&$p['existing_count']===125&&$p['remaining_count']===0&&$p['next_offset']===null);
    $p=$doc->batchPlan([],'month','HOST',100);pcb_ok('Advanced cursor does not hide earlier missing types',count($p['selected'])===25&&$p['remaining_count']===100&&$p['next_offset']===0);
    $p=$doc->batchPlan([],'month','HOST',125);pcb_ok('Past-last cursor writes nothing and points to earlier missing types',$p['selected']===[]&&$p['remaining_count']===125&&$p['next_offset']===0);
    pcb_reset();$original=pcb_import('1');$before=$state['products'][$original];$origin=$state['imports']['1'];
    $first=pcb_batch();pcb_ok('Service imports only missing types in the fifty-index window within one modeled transaction',$first['created_count']===49&&count($first['created_ids'])===49&&$stats['locks']===1&&$stats['transactions']===1&&$stats['passwords']===1&&$first['existing_count']===1&&$first['remaining_count']===75&&$first['source_operation']==='package.types');
    pcb_ok('Existing SKU wording price term image and original provenance are unchanged',$state['products'][$original]===$before&&$state['imports']['1']===$origin);
    pcb_ok('New rows remain unpriced drafts with no image or inferred provider terms',!array_filter($first['created_ids'],static fn($id)=>$state['products'][$id]['family']!=='hosting'||$state['products'][$id]['status']!=='draft'||$state['products'][$id]['amount']!==''||$state['products'][$id]['term']!=='month')&&!array_filter($state['imports'],static fn($r)=>$r['type_ref']!=='1'&&($r['media_id']!==null||$r['media_revision']!==0)));
    pcb_ok('All original matching provenance is validated once before writes',$stats['origins']===1);
    $before=$state;$repeat=pcb_batch();pcb_ok('Same incomplete form retry creates no unseen next batch',$repeat['created_count']===0&&$repeat['remaining_count']===75&&$repeat['next_offset']===$first['next_offset']&&$state===$before);
    $second=pcb_batch('HOST',$first['next_offset']);$third=pcb_batch('HOST',$second['next_offset']);
    pcb_ok('Continuation completes remaining document with finite fifty then twenty-five writes',$second['created_count']===50&&$third['created_count']===25&&$third['remaining_count']===0&&$third['next_offset']===null&&count($state['imports'])===125);
    $before=$state;$again=pcb_batch();pcb_ok('Completed repeat creates nothing and preserves entire modeled state',$again['created_ids']===[]&&$again['existing_count']===125&&$again['remaining_count']===0&&$state===$before);
    pcb_reset(8192);$full=pcb_batch();pcb_ok('Maximum saved catalog still writes only fifty and uses chunked indexed queries',$full['created_count']===50&&$full['remaining_count']===8142&&$stats['queries']===128&&$stats['max_parameters']===129);
    pcb_reset();$last=$context['document']->types[array_key_last($context['document']->types)];$state['products'][900]=['id'=>900,'sku'=>'HOST-'.$last['type_ref']];$before=$state;
    pcb_deny('Collision beyond first fifty refuses before any insert',fn()=>pcb_batch());pcb_ok('Far collision preserves whole state without transient writes',$state===$before&&$stats['writes']===0);
    pcb_reset();$state['products'][900]=['id'=>900,'sku'=>'host-1'];pcb_deny('Case-insensitive existing SKU collision refuses',fn()=>pcb_batch());
    pcb_reset();pcb_import('99');$state['imports']['99']['source_key_fingerprint']=str_repeat('e',64);$before=$state;pcb_deny('Earlier credential original anywhere in document refuses batch',fn()=>pcb_batch());pcb_ok('Credential refusal does not rewrite original or create current-looking copies',$state===$before&&$stats['writes']===0);
    pcb_reset();pcb_import('99');$state['imports']['99']['corrupt']=true;pcb_deny('Damaged original provenance refuses before writes',fn()=>pcb_batch());pcb_ok('Damaged history causes no transient inserts',$stats['writes']===0);
    pcb_reset();$before=$state;$auditFailure=3;pcb_deny('Mid-batch modeled audit failure propagates',fn()=>pcb_batch());pcb_ok('Transaction rollback restores products origins counts and prior audit after actual model inserts',$state===$before&&$stats['writes']===3&&!$lock&&!$tx);
    pcb_reset();$state['count']=999999980;$before=$state;pcb_deny('Counter capacity checked for whole selected batch before writes',fn()=>pcb_batch());pcb_ok('Capacity failure has no writes',$state===$before&&$stats['writes']===0);
    pcb_reset();$badContext=true;pcb_deny('Newest unavailable or pending context blocks batch',fn()=>pcb_batch());pcb_ok('Failed context leaves products untouched',$state['products']===[]&&$stats['writes']===0);
    pcb_reset();$badOwner=true;pcb_deny('Current owner authority denial propagates before writes',fn()=>pcb_batch());
    pcb_reset();pcb_deny('Wrong current owner password refuses',fn()=>provider_catalog_import_batch($context['hash'],'month','HOST','wrong'));
    pcb_deny('Stale reviewed hash refuses',fn()=>provider_catalog_import_batch(str_repeat('f',64),'month','HOST','synthetic-password'));
    pcb_deny('Malformed reviewed hash refuses',fn()=>provider_catalog_import_batch('bad','month','HOST','synthetic-password'));
    pcb_deny('Unsupported saved source refuses',fn()=>pcb_batch('HOST',0,'cloud.products'));
    pcb_ok('All authorization and source refusals leave empty model store',$state['products']===[]&&$state['imports']===[]&&$stats['writes']===0);
    pcb_reset(2);$legacy=pcb_batch('OLD',0,'packages.list');pcb_ok('Legacy saved inventory remains an explicitly selected supported source',$legacy['source_operation']==='packages.list'&&$legacy['created_count']===2);
    pcb_reset(2);$id=provider_catalog_import_create($context['hash'],'1','SINGLE','year','synthetic-password','package.types');$before=$state;
    $again=provider_catalog_import_create($context['hash'],'1','OTHER','month','synthetic-password','package.types');pcb_ok('Original single-create replay still returns unchanged owner product',$again===$id&&$state===$before&&$state['products'][$id]['sku']==='SINGLE'&&$state['products'][$id]['term']==='year');
    $rest=pcb_batch();pcb_ok('Batch and single-create share one provenance format without overwriting originals',$rest['created_count']===1&&$rest['existing_count']===1&&$state['imports']['1']['source_hash']===$before['imports']['1']['source_hash']);
    $guarded=false;try{provider_catalog_import_create_locked($context,$actor,'2','UNGUARDED','month');}catch(LogicException){$guarded=true;}pcb_ok('Extracted writer rejects missing owning transaction and lock',$guarded);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected batch fixture error: '.$e::class.': '.$e->getMessage(),'passed'=>false];}
$passed=!array_filter($checks,static fn($check)=>$check['passed']!==true);
echo json_encode(['passed'=>$passed,'scope'=>'Real pure planner and exact production batch/single writer bodies with modeled authority, SQL, audit and transaction seams; no native database, HTTP, provider, upload or publication execution.','source_sha256'=>hash_file('sha256',$source),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
