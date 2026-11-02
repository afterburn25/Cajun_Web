<?php
/** Execute actual service/storage bodies over a bounded state model. SQL semantics remain a native gate. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/providers/catalog_variants_document.php';
require CWP_CODE_ROOT.'/app/providers/catalog_import_document.php';
require CWP_CODE_ROOT.'/database/schema/provider_catalog_variant_schema.php';
$checks=[];$fragments=[];
function cvc_load(string $path,array $names): void {
    $bytes=file_get_contents(CWP_CODE_ROOT.'/'.$path);
    foreach($names as $name){$start=strpos($bytes,'function '.$name.'(');$end=$start===false?false:strpos($bytes,"\n}",$start);if($end===false)throw new LogicException('Actual body unavailable: '.$name);$body=substr($bytes,$start,$end+2-$start);$GLOBALS['fragments'][$name]=hash('sha256',$body);eval($body);}
}
cvc_load('app/providers/catalog_variants_storage.php',['provider_catalog_variant_exists','provider_catalog_variant_validate_record','provider_catalog_variant_ready','provider_catalog_variant_resolve','provider_catalog_variant_image_detail']);
cvc_load('app/providers/catalog_import_storage.php',['provider_catalog_import_canonical_record','provider_catalog_import_canonical_record_in_scope','provider_catalog_import_product_predicate','provider_catalog_image_used','provider_catalog_import_records','provider_catalog_import_records_in_scope']);
cvc_load('app/providers/catalog_import.php',['provider_catalog_import_origins']);
cvc_load('app/providers/catalog_variants.php',['provider_catalog_variant_owner','provider_catalog_variant_product','provider_catalog_variant_snapshot','provider_catalog_variant_preview','provider_catalog_variant_replay_authority','provider_catalog_variant_create']);
cvc_load('database/migrations/provider_catalog_variant_migrate.php',['provider_catalog_variant_prepare']);
function cvc_ok(string $name,bool $passed): void {$GLOBALS['checks'][]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function cvc_bad(string $name,callable $call): void {try{$call();}catch(InvalidArgumentException|RuntimeException|LogicException){cvc_ok($name,true);return;}cvc_ok($name,false);}
function cvc_scope(callable $call): mixed {return staff_locked(fn()=>staff_transaction($call));}
function config(): array {return ['db'=>['prefix'=>'t_']];}
function table(string $name): string {return 't_'.$name;}
function provider_catalog_import_exists(): bool {return $GLOBALS['m']['canonical_exists'];}
function provider_catalog_variant_schema_valid(): void {if(!$GLOBALS['m']['schema_valid'])throw new RuntimeException('Unknown schema');}
function provider_catalog_import_validate_record(array $row): void {if(!isset($GLOBALS['m']['imports'][$row['id']])||$GLOBALS['m']['imports'][$row['id']]!=$row)throw new RuntimeException('Physical import mismatch');}
function provider_catalog_import_ready(): bool {provider_catalog_variant_ready();return $GLOBALS['m']['canonical_ready'];}
function provider_catalog_import_transaction(): void {if(!$GLOBALS['m']['lock']||!$GLOBALS['m']['tx'])throw new LogicException('Owning transaction required');}
function provider_catalog_import_owner(?string $password=null): array {$GLOBALS['m']['owner_calls']++;if(!$GLOBALS['m']['owner'])throw new RuntimeException('Owner required');if($password!==null&&$password!=='owner-password')throw new InvalidArgumentException('Wrong password');return $GLOBALS['m']['actor'];}
function provider_catalog_import_context(string $source): array {provider_catalog_import_transaction();if($source!=='package.types'||!$GLOBALS['m']['context_valid'])throw new RuntimeException('Current source unavailable');return $GLOBALS['m']['context'];}
function provider_package_types_ready(): bool {return true;}
function provider_package_types_observation(array $saved): array {return ['types'=>[['id'=>'811','label'=>'Original hosting type']]];}
function provider_catalog_import_record(int $id): ?array {foreach($GLOBALS['m']['imports'] as $row)if((int)$row['product_id']===$id)return $row;foreach($GLOBALS['m']['variants'] as $row)if((int)$row['product_id']===$id)return provider_catalog_variant_resolve($row);return null;}
function provider_settings_profile(): ?array {return $GLOBALS['m']['profile'];}
function provider_inventory_authority(string $id,int $revision): array {if(!$GLOBALS['m']['authority'])throw new RuntimeException('Authority changed');return [null,['id'=>$id,'environment'=>'live','credentials'=>'synthetic-ciphertext']];}
function provider_settings_unseal(string $id,string $environment,string $credentials): string {return $GLOBALS['m']['secret'];}
function provider_inventory_fingerprint(string $secret): string {return hash('sha256',$secret);}
function staff_locked(callable $call): mixed {$old=$GLOBALS['m']['lock'];$GLOBALS['m']['lock']=true;try{return $call();}finally{$GLOBALS['m']['lock']=$old;}}
function staff_transaction(callable $call): mixed {if($GLOBALS['m']['tx'])throw new LogicException('Nested transaction');$before=$GLOBALS['m'];$GLOBALS['m']['tx']=true;try{return $call();}catch(Throwable $e){$GLOBALS['m']=$before;throw $e;}finally{$GLOBALS['m']['tx']=false;}}
function db(): object {return new class {public function inTransaction(): bool {return $GLOBALS['m']['tx'];}public function exec(string $sql): int {$GLOBALS['m']['exists']=true;return 0;}};}
function save_setting(string $key,string $value): void {$GLOBALS['m']['settings'][$key]=$value;}
function catalog_audit(int $actor,string $action,string $detail): void {if($GLOBALS['m']['fail_audit']===$action)throw new RuntimeException('Synthetic audit fault');$GLOBALS['m']['audits'][]=['action'=>'catalog.'.$action,'user_id'=>$actor,'detail'=>$detail];}
function catalog_product_input(array $data): array {foreach(['amount'=>'amount_cents','setup'=>'setup_cents','cost'=>'cost_cents'] as $key=>$target){$data[$target]=$data[$key]===''?null:(int)round((float)$data[$key]*100);unset($data[$key]);}$data['sort_order']=(int)$data['sort_order'];return $data;}
function catalog_save_in_transaction(int $id,int $revision,array $input): int {provider_catalog_import_transaction();foreach($GLOBALS['m']['products'] as $row)if($row['sku']===$input['sku'])throw new InvalidArgumentException('Duplicate SKU');$id=$GLOBALS['m']['next_product']++;$snapshot=catalog_product_input($input);$GLOBALS['m']['products'][$id]=$snapshot+['id'=>$id,'revision'=>1];$GLOBALS['m']['revisions'][$id][1]=json_encode($snapshot,JSON_THROW_ON_ERROR);return $id;}
function one(string $sql,array $params=[]): ?array {
    $m=$GLOBALS['m'];
    if(str_starts_with($sql,'SELECT f.*,r.*'))return ['id'=>$params[0],'operation'=>'package.types','profile_id'=>$m['imports'][3]['profile_id'],'profile_revision'=>2,'key_fingerprint'=>$m['imports'][3]['source_key_fingerprint']];
    if(str_contains($sql,'information_schema.tables'))return $m['exists']?['TABLE_NAME'=>'t_catalog_provider_variants']:null;
    if(str_contains($sql,'FROM t_settings'))return isset($m['settings'][$params[0]])?['value'=>$m['settings'][$params[0]]]:null;
    if(str_contains($sql,'FROM t_catalog_provider_imports')){foreach($m['imports'] as $row){$match=str_contains($sql,'WHERE product_id=')?(int)$row['product_id']===(int)$params[0]:(str_contains($sql,'WHERE media_id=')?$row['media_id']===$params[0]:(int)$row['id']===(int)$params[0]);if($match)return $row;}return null;}
    if(str_contains($sql,'FROM t_catalog_provider_variants')){foreach($m['variants'] as $row){$match=str_contains($sql,'WHERE product_id=')?(int)$row['product_id']===(int)$params[0]:(str_contains($sql,'WHERE media_id=')?$row['media_id']===$params[0]:$row['id']===$params[0]);if($match)return $row;}return null;}
    if(str_contains($sql,'FROM t_catalog_revisions')){$revision=$params[1]??1;$snapshot=$m['revisions'][(int)$params[0]][(int)$revision]??null;return $snapshot===null?null:['snapshot'=>$snapshot];}
    if(str_contains($sql,'FROM t_catalog_products'))return $m['products'][(int)$params[0]]??null;
    throw new LogicException('Unmodeled one: '.$sql);
}
function all(string $sql,array $params=[]): array {
    $m=$GLOBALS['m'];
    if(str_contains($sql,'information_schema.tables'))return $m['exists']?[['TABLE_NAME'=>'t_catalog_provider_variants']]:[];
    if(str_contains($sql,'FROM t_settings')){$rows=[];foreach($m['settings'] as $name=>$value)if(str_starts_with($name,'provider_catalog_variant_'))$rows[]=['name'=>$name,'value'=>$value];return $rows;}
    if(str_contains($sql,'FROM t_audit')&&str_contains($sql,'GROUP BY')){$counts=[];foreach($m['audits'] as $row)if(str_starts_with($row['action'],'catalog.provider_variant.'))$counts[$row['action']]=($counts[$row['action']]??0)+1;$rows=[];foreach($counts as $action=>$n)$rows[]=['action'=>$action,'n'=>$n];return $rows;}
    if(str_contains($sql,'FROM t_audit'))return array_values(array_filter($m['audits'],fn($row)=>$row['action']===$params[0]&&str_starts_with($row['detail'],rtrim($params[1],'%'))));
    if(str_contains($sql,'FROM t_catalog_provider_variants')){if(str_contains($sql,'ORDER BY id LIMIT 64')&&!str_contains($sql,'WHERE'))$GLOBALS['m']['full_variant_scans']=($GLOBALS['m']['full_variant_scans']??0)+1;$rows=array_values($m['variants']);if(str_contains($sql,'WHERE canonical_import_id='))$rows=array_values(array_filter($rows,fn($row)=>(int)$row['canonical_import_id']===(int)$params[0]));if(str_contains($sql,'WHERE id>'))$rows=array_values(array_filter($rows,fn($row)=>$row['id']>$params[0]));usort($rows,fn($a,$b)=>strcmp($a['id'],$b['id']));return array_slice($rows,0,str_contains($sql,'LIMIT 201')?201:64);}
    throw new LogicException('Unmodeled all: '.$sql);
}
function query(string $sql,array $params=[]): object {
    $n=0;$m=&$GLOBALS['m'];
    if(str_starts_with($sql,'INSERT INTO t_catalog_provider_variants')){$keys=['id','canonical_import_id','canonical_product_id','product_id','document_json','document_sha256','actor_id','created_at'];$row=array_combine($keys,$params)+['media_id'=>null,'media_revision'=>0];$m['variants'][$row['id']]=$row;}
    elseif(str_contains($sql,'COUNT(*)')&&str_contains($sql,'FROM t_audit'))foreach($m['audits'] as $row){if($row['action']===$params[0]&&(count($params)<3||($row['user_id']===$params[1]&&$row['detail']===$params[2])))$n++;}
    elseif(str_contains($sql,'COUNT(*)')&&str_contains($sql,'FROM t_catalog_provider_variants')){$n=count($m['variants']);if($params)$n=count(array_filter($m['variants'],fn($row)=>$row['canonical_import_id']===$params[0]));}
    else throw new LogicException('Unmodeled query: '.$sql);
    return new class($n){public function __construct(private int $n){}public function fetchColumn(): int {return $this->n;}};
}
function cvc_reset(): void {
    $_SESSION=[];$_GET=[];$origin=['id'=>3,'profile_id'=>str_repeat('a',32),'type_ref'=>'811','product_id'=>17,'source_revision'=>2,'refresh_id'=>str_repeat('b',32),'source_key_fingerprint'=>hash('sha256','synthetic-key'),'type_name'=>'Original hosting type','source_hash'=>'','actor_id'=>9,'created_at'=>'2023-11-14 22:13:20','media_id'=>str_repeat('e',32),'media_revision'=>1];
    $origin['source_hash']=hash('sha256',json_encode([$origin['profile_id'],2,$origin['refresh_id'],$origin['source_key_fingerprint'],'811',$origin['type_name']],JSON_THROW_ON_ERROR));
    $GLOBALS['m']=['exists'=>false,'schema_valid'=>true,'canonical_exists'=>true,'canonical_ready'=>true,'lock'=>false,'tx'=>false,'owner'=>true,'owner_calls'=>0,'actor'=>['id'=>9,'auth_version'=>'owner-v1'],'profile'=>['id'=>$origin['profile_id'],'revision'=>2],'authority'=>true,'secret'=>'synthetic-key','context_valid'=>true,'context'=>['profile_id'=>$origin['profile_id'],'source_key_fingerprint'=>$origin['source_key_fingerprint'],'hash'=>hash('sha256','current-source'),'document'=>ProviderCatalogImportDocument::fromRows([['type_ref'=>'811','package_type_name'=>'Current type name']])],'settings'=>['provider_catalog_import_version'=>'1'],'audits'=>[],'variants'=>[],'imports'=>[3=>$origin],'products'=>[17=>['id'=>17,'revision'=>4,'sku'=>'HOST-MONTH','title'=>'Monthly hosting','family'=>'hosting','status'=>'draft','term'=>'month','amount_cents'=>2500]],'revisions'=>[17=>[4=>'{}']],'next_product'=>20,'fail_audit'=>null];
}
try{
    cvc_reset();$original=$GLOBALS['m']['imports'];
    cvc_ok('Clean absent store reads without creating it',provider_catalog_variant_ready()===false&&!$GLOBALS['m']['exists']);
    $preview=provider_catalog_variant_preview(17);cvc_ok('Unprepared preview retains canonical family and exact selection',$preview['ready']===false&&$preview['selected_product']['revision']===4&&count($preview['variants'])===1);
    foreach(['client_id','client_auth','contact_id','contact_auth','support'] as $key){$_SESSION=[$key=>null];$calls=$GLOBALS['m']['owner_calls'];cvc_bad('Raw '.$key.' refused before owner normalization',fn()=>provider_catalog_variant_owner());cvc_ok('No owner normalization for '.$key,$GLOBALS['m']['owner_calls']===$calls);}$_SESSION=[];
    $_GET=['support'=>null];cvc_bad('Raw support query refused',fn()=>provider_catalog_variant_owner());$_GET=[];
    $GLOBALS['m']['owner']=false;cvc_bad('Non-owner cannot prepare',fn()=>provider_catalog_variant_prepare('owner-password'));$GLOBALS['m']['owner']=true;
    cvc_bad('Wrong password cannot prepare',fn()=>provider_catalog_variant_prepare('wrong'));
    $GLOBALS['m']['exists']=true;$GLOBALS['m']['schema_valid']=false;cvc_bad('Unknown empty schema refused before preparation',fn()=>provider_catalog_variant_prepare('owner-password'));cvc_ok('Unknown schema creates no settings or audits',count($GLOBALS['m']['settings'])===1&&$GLOBALS['m']['audits']===[]);$GLOBALS['m']['schema_valid']=true;
    $GLOBALS['m']['fail_audit']='provider_variant.setup';cvc_bad('Preparation audit failure rolls back markers',fn()=>provider_catalog_variant_prepare('owner-password'));cvc_ok('Exact empty DDL remains resumable',!isset($GLOBALS['m']['settings']['provider_catalog_variant_version'])&&$GLOBALS['m']['exists']);$GLOBALS['m']['fail_audit']=null;
    provider_catalog_variant_prepare('owner-password');cvc_ok('Prepared empty graph has one witness and zero counter',provider_catalog_variant_ready()&&$GLOBALS['m']['settings']['provider_catalog_variant_count']==='0'&&count($GLOBALS['m']['audits'])===1);
    provider_catalog_variant_prepare('owner-password');cvc_ok('Preparation replay does not append history',count($GLOBALS['m']['audits'])===1);
    $preview=provider_catalog_variant_preview(17);$id=str_repeat('c',32);$call=fn()=>provider_catalog_variant_create($id,17,4,$preview['review_sha256'],'HOST-YEAR','year','owner-password');
    $before=$GLOBALS['m'];$GLOBALS['m']['fail_audit']='provider_variant.create';cvc_bad('Creation audit failure rolls back product mapping and count',$call);cvc_ok('Creation rollback preserves original product and all imports',$GLOBALS['m']['products']===$before['products']&&$GLOBALS['m']['variants']===[]&&$GLOBALS['m']['imports']===$original);$GLOBALS['m']['fail_audit']=null;
    $created=$call();cvc_ok('One explicit creation returns independent child',$created===20&&provider_catalog_variant_ready()&&count($GLOBALS['m']['variants'])===1);
    $row=provider_catalog_import_record(20);cvc_ok('Normalized pair retains exact source and independent empty image',$row['id']===3&&$row['product_id']===20&&$row['source_hash']===$original[3]['source_hash']&&$row['media_id']===null&&$row['media_revision']===0);
    cvc_ok('Canonical physical row and media remain unchanged',$GLOBALS['m']['imports']===$original);
    cvc_ok('Resolver authenticates canonical and variant together',cvc_scope(fn()=>provider_catalog_import_canonical_record($row))===$original[3]);
    cvc_bad('Forged child source pair refused',fn()=>cvc_scope(fn()=>provider_catalog_import_canonical_record(array_replace($row,['id'=>4]))));
    cvc_bad('Correct canonical ID with unbound child ID refused',fn()=>cvc_scope(fn()=>provider_catalog_import_canonical_record(array_replace($row,['product_id'=>99]))));
    cvc_bad('Forged normalized image refused',fn()=>cvc_scope(fn()=>provider_catalog_import_canonical_record(array_replace($row,['media_id'=>str_repeat('f',32)]))));
    cvc_ok('Whole page resolves canonical and variant',count(cvc_scope(fn()=>provider_catalog_import_records([17,20])))===2);
    cvc_bad('Same-scope record lookup requires owning transaction',fn()=>provider_catalog_import_records_in_scope([17,20]));
    cvc_bad('Same-scope canonical pair requires owning transaction',fn()=>provider_catalog_import_canonical_record_in_scope($row,true));
    cvc_bad('Same-scope child refuses absent prepared family',fn()=>cvc_scope(fn()=>provider_catalog_import_canonical_record_in_scope($row,false)));
    cvc_ok('Same-scope canonical original needs no variant claim',cvc_scope(fn()=>provider_catalog_import_canonical_record_in_scope($original[3],false))===$original[3]);
    cvc_bad('Internal review snapshot refuses missing owning transaction',fn()=>provider_catalog_variant_snapshot(17,$GLOBALS['m']['actor']));
    $GLOBALS['m']['products'][17]['revision']=5;$GLOBALS['m']['revisions'][17][5]='{}';$GLOBALS['m']['products'][20]['revision']=2;$GLOBALS['m']['products'][20]['amount_cents']=24000;
    $GLOBALS['m']['context_valid']=false;$writes=$GLOBALS['m']['variants'];cvc_ok('Exact completed replay survives later commercial edits without fresh import',$call()===20&&$GLOBALS['m']['variants']===$writes);$GLOBALS['m']['context_valid']=true;
    cvc_bad('Changed replay SKU refused',fn()=>provider_catalog_variant_create($id,17,4,$preview['review_sha256'],'CHANGED','year','owner-password'));
    $GLOBALS['m']['secret']='rotated-key';cvc_bad('Replay refuses changed credentials',$call);$GLOBALS['m']['secret']='synthetic-key';
    $GLOBALS['m']['actor']['auth_version']='owner-v2';cvc_bad('Replay refuses changed owner authentication',$call);$GLOBALS['m']['actor']['auth_version']='owner-v1';
    cvc_bad('Stale selected revision cannot create another option',fn()=>provider_catalog_variant_create(str_repeat('d',32),17,4,$preview['review_sha256'],'HOST-YEAR-2','year','owner-password'));
    $GLOBALS['m']['revisions'][20][2]=json_encode($GLOBALS['m']['products'][20]);$childPreview=provider_catalog_variant_preview(20);cvc_ok('Selecting child shows same canonical family',$childPreview['origin_product']['id']===17&&count($childPreview['variants'])===2);
    $child=provider_catalog_variant_create(str_repeat('d',32),20,2,$childPreview['review_sha256'],'HOST-MONTH-2','month','owner-password');cvc_ok('Child selection creates sibling under original canonical source',$child===21&&$GLOBALS['m']['variants'][str_repeat('d',32)]['canonical_product_id']===17);
    $GLOBALS['m']['full_variant_scans']=0;$origins=cvc_scope(fn()=>provider_catalog_import_origins(array_fill(0,20,provider_catalog_import_record(20))));
    cvc_ok('Actual origins validates complete variant store once for twenty child records',$GLOBALS['m']['full_variant_scans']===1&&$origins===[20=>['origin_operation'=>'package.types','origin_name'=>'Original hosting type']]);
    $GLOBALS['m']['full_variant_scans']=0;cvc_scope(fn()=>provider_catalog_import_records_in_scope([17,20,21]));
    cvc_ok('Same-scope lookup validates pairs without another whole-store scan',$GLOBALS['m']['full_variant_scans']===0);
    cvc_ok('Selector covers both source kinds after validation',str_contains(provider_catalog_import_product_predicate('p'),'pcv.product_id=p.id'));
    foreach(['p.id','p WHERE 1=1','P','',str_repeat('a',17)] as $alias)cvc_bad('Selector refuses alias '.json_encode($alias),fn()=>provider_catalog_import_product_predicate($alias));
    cvc_ok('Original image remains retained',provider_catalog_image_used(str_repeat('e',32)));
    $saved=$GLOBALS['m'];unset($GLOBALS['m']['variants'][$id]);cvc_bad('Missing established variant cannot fall back to ordinary product',fn()=>provider_catalog_import_product_predicate('p'));$GLOBALS['m']=$saved;
    $GLOBALS['m']['settings']['provider_catalog_variant_count']='1';cvc_bad('Wrong independent counter refused',fn()=>provider_catalog_variant_ready());$GLOBALS['m']=$saved;
    $GLOBALS['m']['audits']=array_values(array_filter($GLOBALS['m']['audits'],fn($event)=>$event['action']!=='catalog.provider_variant.create'));cvc_bad('Lost independent creation audit refused',fn()=>provider_catalog_variant_ready());$GLOBALS['m']=$saved;
    $GLOBALS['m']['variants'][$id]['media_id']=str_repeat('f',32);cvc_bad('Unaudited variant image refused',fn()=>provider_catalog_variant_ready());$GLOBALS['m']=$saved;
    $GLOBALS['m']['variants'][$id]['media_id']=str_repeat('f',32);$GLOBALS['m']['variants'][$id]['media_revision']=1;catalog_audit(9,'provider_variant.image',provider_catalog_variant_image_detail(20,1,str_repeat('f',32)));
    cvc_ok('Audited independent image is retained without changing canonical media',provider_catalog_variant_ready()&&provider_catalog_image_used(str_repeat('f',32))&&$GLOBALS['m']['imports']===$original);
    $GLOBALS['m']=$saved;$GLOBALS['m']['settings']['provider_catalog_variant_unknown']='1';cvc_bad('Unknown family setting refused',fn()=>provider_catalog_variant_ready());$GLOBALS['m']=$saved;
    $GLOBALS['m']['canonical_exists']=false;cvc_bad('Lost canonical table is not adopted',fn()=>provider_catalog_variant_ready());$GLOBALS['m']=$saved;
    $result=['passed'=>true,'scope'=>'actual extracted service/resolver/readiness with modeled transactions; no SQL/provider/runtime acceptance','checks'=>$checks,'source_fragments'=>$fragments];
}catch(Throwable $e){$result=['passed'=>false,'checks'=>$checks,'failure'=>$e::class.': '.$e->getMessage(),'source_fragments'=>$fragments];}
$json=json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;if(isset($argv[1]))file_put_contents($argv[1],$json);echo $json;exit($result['passed']?0:1);
