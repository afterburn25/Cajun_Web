<?php
/** Actual browsing parser and read-class bodies; SQL and publication validation are explicit models. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',getenv('CWP_TEST_CODE_ROOT')?:dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/hosting/catalog_billing.php';
$checks=[];$fragments=[];
function hcb_ok(string $name,bool $passed): void {
    foreach ($GLOBALS['checks'] as $check) if ($check['name']===$name) throw new LogicException('Duplicate assertion.');
    $GLOBALS['checks'][]=['name'=>$name,'passed'=>$passed];if (!$passed) throw new RuntimeException($name);
}
function hcb_bad(string $name,callable $call): void {
    try {$call();} catch (InvalidArgumentException|RuntimeException|TypeError $error) {hcb_ok($name,true);return;}
    hcb_ok($name,false);
}
function hcb_load(): void {
    $source=file_get_contents(CWP_CODE_ROOT.'/app/hosting/catalog_offers_context.php');
    $start=strpos($source,'final class HostingCatalogRead {');$end=strpos($source,"\n}\n\nfunction hosting_catalog_read_scope",$start===false?0:$start);
    if ($start===false||$end===false) throw new LogicException('Actual read class unavailable.');
    $body=substr($source,$start,$end+2-$start);$GLOBALS['fragments']['HostingCatalogRead']=hash('sha256',$body);eval($body);
    $source=file_get_contents(CWP_CODE_ROOT.'/app/hosting/catalog_offers.php');$start=strpos($source,'function hosting_catalog_public(');$end=strpos($source,"\n}",$start===false?0:$start);
    if ($start===false||$end===false) throw new LogicException('Actual public entrypoint unavailable.');
    $body=substr($source,$start,$end+2-$start);$GLOBALS['fragments']['hosting_catalog_public']=hash('sha256',$body);eval($body);
}
function hcb_reset(): void {
    $GLOBALS['hcb']=['lock'=>false,'locks'=>0,'ready'=>true,'ready_calls'=>0,'queries'=>[],'origin_checks'=>0,'source_reads'=>0,'projection_calls'=>[],
        'scope_valid'=>true,'profile_revision'=>3,'source_revision'=>3,'source_state'=>'complete','bad_projection'=>null,'unavailable'=>[],
        'products'=>[],'projections'=>[],'import_pages'=>[],'provider_calls'=>0,'writes'=>0];
    // Opposite-period rows precede the monthly products. Sorting is product order, not publication time.
    for ($id=1;$id<=44;$id++) {
        $term=$id<=22?'year':'month';$publication=str_pad(dechex($id),32,'0',STR_PAD_LEFT);
        $GLOBALS['hcb']['products'][]=['id'=>$id,'publication'=>$publication,'term'=>$term,'sort_order'=>0,'status'=>'draft','revision'=>2,'publication_revision'=>2,'withdrawn'=>false,'latest'=>true];
        $GLOBALS['hcb']['projections'][$publication]=['publication_id'=>$publication,'publication_sha256'=>hash('sha256','publication '.$id),'title'=>'Hosting option '.$id,
            'summary'=>'Exact independently published option','amount_cents'=>$term==='year'?11301:1500,'currency'=>'USD','cadence'=>$term==='year'?'annually':'monthly',
            'image_id'=>str_pad(dechex(100+$id),32,'0',STR_PAD_LEFT),'inclusions'=>['One package'],'exclusions'=>[],'limits'=>['sites'=>1],'support'=>'Reviewed support',
            'checkout_policy'=>$id===44?null:['format'=>1,'timezone'=>'UTC','alignment'=>'day']];
    }
}
function recurring_billing_locked(callable $work): mixed {
    if ($GLOBALS['hcb']['lock']) throw new LogicException('Nested read guard.');
    $GLOBALS['hcb']['lock']=true;$GLOBALS['hcb']['locks']++;
    try {return $work();} finally {$GLOBALS['hcb']['lock']=false;}
}
function hosting_catalog_guard(): void {if (!$GLOBALS['hcb']['lock']) throw new LogicException('Owning read guard required.');}
function hosting_catalog_ready(): bool {hosting_catalog_guard();$GLOBALS['hcb']['ready_calls']++;return $GLOBALS['hcb']['ready'];}
function service_plans_actor(int $id): void {if ($id<1) throw new InvalidArgumentException('Invalid product.');}
function table(string $name): string {return 'test_'.$name;}
function catalog_ready(): bool {return true;}
function service_plans_ready(): bool {return true;}
function staff_ready(): bool {return true;}
function provider_settings_ready(): bool {return true;}
function provider_catalog_import_ready(): bool {if (!$GLOBALS['hcb']['scope_valid']) throw new RuntimeException('Retained import history unavailable.');return true;}
function provider_package_types_ready(): bool {return true;}
function provider_plan_catalog_state(): array {return ['ready'=>true];}
function all(string $sql,array $parameters=[]): array {
    hosting_catalog_guard();$GLOBALS['hcb']['queries'][]=[$sql,$parameters];
    if (str_contains($sql,'FROM test_provider_profiles')) return [['id'=>str_repeat('a',32),'revision'=>$GLOBALS['hcb']['profile_revision'],'enabled'=>1,'credentials'=>'PRIVATE_SYNTHETIC_CIPHERTEXT']];
    if (!str_starts_with($sql,'SELECT p.id,p.product_id FROM test_hosting_catalog_publications')) throw new LogicException('Unexpected modeled query.');
    foreach (["c.status='draft'",'c.revision=p.product_revision','w.id IS NULL','n.product_id=p.product_id AND n.seq>p.seq'] as $predicate) if (!str_contains($sql,$predicate)) throw new LogicException('A retained selection condition disappeared.');
    if (!preg_match('/ ORDER BY c.sort_order,c.id LIMIT 21 OFFSET ([0-9]+)\z/',$sql,$m)) throw new LogicException('Unexpected modeled pagination.');
    $term=null;
    if (str_contains($sql,' AND BINARY c.term=?')) {
        if (count($parameters)!==1||!in_array($parameters[0],['month','year'],true)||strpos($sql,' AND BINARY c.term=?')>strpos($sql,' ORDER BY')) throw new LogicException('Unbounded or misplaced period predicate.');
        $term=$parameters[0];
    } elseif ($parameters!==[]) throw new LogicException('Unmodeled parameters.');
    $rows=array_values(array_filter($GLOBALS['hcb']['products'],fn($row)=>$row['status']==='draft'&&$row['revision']===$row['publication_revision']&&!$row['withdrawn']&&$row['latest']&&($term===null||$row['term']===$term)));
    usort($rows,fn($a,$b)=>[$a['sort_order'],$a['id']]<=>[$b['sort_order'],$b['id']]);
    return array_map(fn($row)=>['id'=>$row['publication'],'product_id'=>$row['id']],array_slice($rows,(int)$m[1],21));
}
function one(string $sql,array $parameters=[]): ?array {
    hosting_catalog_guard();$GLOBALS['hcb']['source_reads']++;
    if (!str_contains($sql,'ORDER BY f.seq DESC LIMIT 1')||$parameters!==['package.types',str_repeat('a',32)]) throw new LogicException('Newest source boundary changed.');
    return ['id'=>str_repeat('b',32),'state'=>$GLOBALS['hcb']['source_state'],'profile_id'=>str_repeat('a',32),'profile_revision'=>$GLOBALS['hcb']['source_revision'],'key_fingerprint'=>hash('sha256','private-synthetic-key')];
}
function provider_package_types_observation(array $source): array {return ['observed_epoch'=>100,'types'=>[['id'=>'811','label'=>'Original type']]];}
function provider_catalog_import_records_in_scope(array $ids): array {$GLOBALS['hcb']['import_pages'][]=$ids;return array_map(fn($id)=>['id'=>7,'product_id'=>$id],$ids);}
function provider_catalog_import_origins(array $records): void {hosting_catalog_guard();$GLOBALS['hcb']['origin_checks']++;}
function hosting_catalog_current_context(object $publication,array $scope): array {
    hosting_catalog_guard();if (!isset($scope['imports'][$publication->product])) throw new LogicException('Product escaped current source scope.');
    return ['valid'=>true];
}
function hosting_catalog_publication(string $id): object {
    return new class($id) {
        public int $product;
        public function __construct(public string $id) {$this->product=(int)hexdec($id);}
        public function publicProjection(array $context,int $now): array {
            hosting_catalog_guard();$GLOBALS['hcb']['projection_calls'][]=$this->id;
            if (in_array($this->product,$GLOBALS['hcb']['unavailable'],true)) throw new RuntimeException('Saved offer is no longer current.');
            $offer=$GLOBALS['hcb']['projections'][$this->id];
            if ($GLOBALS['hcb']['bad_projection']===$this->product) $offer['cadence']='annually';
            return $offer;
        }
    };
}
function provider_settings_unseal(): never {throw new LogicException('Browsing cannot decrypt credentials.');}
function query(): never {throw new LogicException('Browsing cannot write business records.');}
function hcb_ids(array $page): array {return array_map(fn($offer)=>(int)hexdec($offer['publication_id']),$page['offers']);}

try {
    hcb_load();
    foreach (['all','monthly','annually'] as $value) hcb_ok('Accept exact billing choice '.$value,hosting_catalog_billing($value)===$value);
    foreach ([null,true,false,1,[],['monthly'],new stdClass(),'month','year','annual','Monthly',' monthly','annually ',"monthly\0",'weekly',''] as $i=>$bad) hcb_bad('Reject unsupported or malformed billing '.$i,fn()=>hosting_catalog_billing($bad));
    hcb_ok('Missing browsing query preserves all and page one',hosting_catalog_billing_query([])===['billing'=>'all','page'=>1]);
    hcb_ok('Portal filter and last allowed page parse exactly',hosting_catalog_billing_query(['billing'=>'annually','p'=>'100000'])===['billing'=>'annually','page'=>100000]);
    hcb_ok('Public hosting page ignores the site routing page',hosting_catalog_billing_query(['page'=>'hosting','hosting_page'=>'2','billing'=>'monthly'],'hosting_page')===['billing'=>'monthly','page'=>2]);
    hcb_ok('Unrelated query values cannot become a browsing choice',hosting_catalog_billing_query(['return'=>'https://invalid.example','price'=>'1','offer'=>str_repeat('f',32)])===['billing'=>'all','page'=>1]);
    foreach ([null,1,0,true,[],['2'],'0','01','100001','-1','1.0','1e2',' 2','2 ',str_repeat('9',100),''] as $i=>$bad) hcb_bad('Reject invalid page input '.$i,fn()=>hosting_catalog_billing_query(['p'=>$bad]));
    hcb_bad('Explicit null billing is not a missing selection',fn()=>hosting_catalog_billing_query(['billing'=>null]));
    hcb_bad('Internal page key cannot select arbitrary query fields',fn()=>hosting_catalog_billing_query(['redirect'=>'1'],'redirect'));
    hcb_reset();$original=[$GLOBALS['hcb']['products'],$GLOBALS['hcb']['projections']];
    $all=hosting_catalog_public();hcb_ok('Default all preserves first twenty products and continuation',hcb_ids($all)===range(1,20)&&$all['has_more']&&$all['unavailable']===false);
    hcb_ok('Default all keeps the prior unfiltered query and no bound period',!str_contains($GLOBALS['hcb']['queries'][0][0],'BINARY c.term')&&$GLOBALS['hcb']['queries'][0][1]===[]);
    $monthly=hosting_catalog_public(1,'monthly');hcb_ok('Monthly products beyond twenty annual products remain reachable',hcb_ids($monthly)===range(23,42)&&$monthly['has_more']&&$monthly['unavailable']===false);
    $second=hosting_catalog_public(2,'monthly');hcb_ok('Second monthly page contains only remaining matches',hcb_ids($second)===[43,44]&&!$second['has_more']);
    $annual=hosting_catalog_public(1,'annually');$annual2=hosting_catalog_public(2,'annually');
    hcb_ok('Annual selection paginates independently of monthly products',hcb_ids($annual)===range(1,20)&&$annual['has_more']&&hcb_ids($annual2)===[21,22]&&!$annual2['has_more']);
    hcb_ok('All retains monthly and annual alternatives without family collapse',hcb_ids(hosting_catalog_public(2))===range(21,40)&&hcb_ids(hosting_catalog_public(3))===[41,42,43,44]);
    $empty=hosting_catalog_public(3,'monthly');hcb_ok('A later matching page is empty without a false continuation',$empty===['offers'=>[],'has_more'=>false,'unavailable'=>false]);
    hcb_ok('Exact publication identity hash amount and own image survive selection',$monthly['offers'][0]===$original[1][str_pad(dechex(23),32,'0',STR_PAD_LEFT)]&&$annual['offers'][0]===$original[1][str_repeat('0',31).'1']);
    hcb_ok('Annual price stays the full published charge',$annual['offers'][0]['amount_cents']===11301&&$annual['offers'][0]['cadence']==='annually');
    hcb_ok('Request-only and invoice-checkout products both survive the same filter',$second['offers'][0]['checkout_policy']!==null&&$second['offers'][1]['checkout_policy']===null);
    hcb_ok('Public return shape stays unchanged',array_keys($monthly)===['offers','has_more','unavailable']);
    hcb_ok('Selection never changes original products or publication snapshots',$original===[$GLOBALS['hcb']['products'],$GLOBALS['hcb']['projections']]);
    hcb_ok('Read scope validates at most twenty returned products at once',max(array_map('count',$GLOBALS['hcb']['import_pages']))===20&&count($GLOBALS['hcb']['import_pages'])===$GLOBALS['hcb']['origin_checks']);
    hcb_ok('Reading uses no provider operation or business write',$GLOBALS['hcb']['provider_calls']===0&&$GLOBALS['hcb']['writes']===0&&!$GLOBALS['hcb']['lock']);
    hcb_reset();foreach ([0,100001] as $page) hcb_bad('Reject invalid direct page '.$page,fn()=>hosting_catalog_public($page,'monthly'));
    foreach (['weekly',' monthly',"annually\0",['monthly']] as $i=>$bad) hcb_bad('Reject direct filter before read scope '.$i,fn()=>hosting_catalog_public(1,$bad));
    hcb_ok('Invalid browsing values acquire no lock or source',$GLOBALS['hcb']['locks']===0&&$GLOBALS['hcb']['queries']===[]);
    hcb_reset();$GLOBALS['hcb']['ready']=false;hcb_ok('Never prepared offers remain unavailable for selection without setup',hosting_catalog_public(1,'monthly')===['offers'=>[],'has_more'=>false,'unavailable'=>false]&&$GLOBALS['hcb']['queries']===[]);
    foreach (['failed',null] as $state) {hcb_reset();$GLOBALS['hcb']['source_state']=$state;hcb_bad('Latest '.($state??'unfinished').' catalog is not replaced by older data',fn()=>hosting_catalog_public(1,'monthly'));}
    hcb_reset();$GLOBALS['hcb']['source_revision']=2;hcb_bad('Changed provider settings retain the existing source gate',fn()=>hosting_catalog_public(1,'annually'));
    hcb_reset();$GLOBALS['hcb']['scope_valid']=false;hcb_bad('Missing retained import or variant history cannot become an empty filter',fn()=>hosting_catalog_public(1,'monthly'));
    hcb_reset();$GLOBALS['hcb']['unavailable']=[23];$page=hosting_catalog_public(1,'monthly');hcb_ok('Unavailable current offer is excluded with truthful partial notice',hcb_ids($page)===range(24,42)&&$page['unavailable']===true&&$page['has_more']);
    hcb_reset();$GLOBALS['hcb']['bad_projection']=23;$page=hosting_catalog_public(1,'monthly');hcb_ok('A mismatched projected cadence cannot enter selected results',!in_array(23,hcb_ids($page),true)&&$page['unavailable']===true);
    hcb_reset();$GLOBALS['hcb']['products'][22]['withdrawn']=true;$GLOBALS['hcb']['products'][23]['revision']=3;$GLOBALS['hcb']['products'][24]['latest']=false;$GLOBALS['hcb']['products'][25]['status']='retired';
    $page=hosting_catalog_public(1,'monthly');hcb_ok('Period filtering retains withdrawal revision latest and draft predicates',hcb_ids($page)===range(27,44)&&!$page['has_more']);
    hcb_reset();$GLOBALS['hcb']['products'][43]['sort_order']=-1;hcb_ok('Product display order remains authoritative within the period',hcb_ids(hosting_catalog_public(1,'monthly'))===[44,...range(23,41)]);
} catch (Throwable $error) {$checks[]=['name'=>'Unexpected failure: '.get_class($error).' '.$error->getMessage(),'passed'=>false];}
$passed=!array_filter($checks,fn($check)=>!$check['passed']);
echo json_encode(['passed'=>$passed,'scope'=>'Actual pure query parser and extracted read-class/entrypoint with modeled SQL, readiness, source and publication boundaries. No native SQL, provider, browser or invoice execution.','checks'=>$checks,'source_fragments'=>$fragments],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($passed?0:1);
