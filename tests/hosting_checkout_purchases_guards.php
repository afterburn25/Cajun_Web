<?php
/** Actual reader/coordinator functions with modeled SQL and authority. No native database or HTTP. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',getenv('CWP_TEST_CODE_ROOT')?:__DIR__.'/../site');
$checks=[];$fragments=[];$calls=[];$model=[];$_SESSION=[];
function hpg_load(string $file,string $name): void {
    $tokens=token_get_all(file_get_contents(CWP_CODE_ROOT.'/'.$file));$raw=null;
    for ($i=0;$i<count($tokens);$i++) {
        if (!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION) continue;
        $j=$i+1;while (is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)) $j++;
        if (!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||$tokens[$j][1]!==$name) continue;
        $raw='';$depth=0;$entered=false;
        for ($k=$i;$k<count($tokens);$k++) {$t=$tokens[$k];$raw.=is_array($t)?$t[1]:$t;if ($t==='{') {$depth++;$entered=true;} elseif ($t==='}'&&--$depth===0&&$entered) break;}
        break;
    }
    if ($raw===null) throw new LogicException('Missing actual function '.$name);
    $executed=$raw;
    if ($name==='service_orders_context') {
        $include="require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers_storage.php';";
        if (substr_count($raw,$include)!==1) throw new LogicException('Unexpected origin include seam.');
        $executed=str_replace($include,'/* Origin is an explicit modeled storage seam in this test. */',$raw);
    }
    $GLOBALS['fragments'][]=['file'=>$file,'function'=>$name,'sha256'=>hash('sha256',$raw),'executed_sha256'=>hash('sha256',$executed)];eval($executed);
}
foreach (['hosting_purchases_orders_ready','hosting_purchases_item','hosting_purchases_page','hosting_purchases_owner_page','hosting_purchases_client_page'] as $name) hpg_load('app/hosting/checkout_purchases.php',$name);
foreach (['hosting_checkout_reject_contact','hosting_checkout_primary_client'] as $name) hpg_load('app/hosting/catalog_checkout.php',$name);
hpg_load('app/hosting/order_views.php','service_orders_context');
set_error_handler(static function(int $level,string $message,string $file,int $line): never {throw new ErrorException($message,0,$level,$file,$line);});
function hpg_ok(string $name,bool $value): void {$GLOBALS['checks'][]=['name'=>$name,'passed'=>$value];if (!$value) throw new RuntimeException($name);}
function hpg_bad(string $name,callable $call,string $kind=RuntimeException::class): void {try {$call();}catch (Throwable $e) {if (!$e instanceof $kind) throw $e;hpg_ok($name,true);return;}hpg_ok($name,false);}
final class HpgDocument {
    public function __construct(private array $data,private array $status=[]) {}
    public function data(): array {return $this->data;}
    public function model(): object {return new class($this->data) {
        public int $agreedPrice=2500;public object $plan;
        public function __construct(private array $data) {$this->plan=(object)['currency'=>'USD'];}
        public function state(): string {return $this->data['state']??'active';}
    };}
    public function state(): array {return $this->status;}
}
function recurring_billing_locked(callable $call): mixed {
    $GLOBALS['calls'][]='lock';if ($GLOBALS['model']['locked']??false) throw new LogicException('Inherited transaction.');
    $GLOBALS['model']['locked']=true;try {return $call();}finally {$GLOBALS['model']['locked']=false;}
}
function hosting_catalog_guard(): void {if (!($GLOBALS['model']['locked']??false)) throw new LogicException('Missing owning guard.');}
function service_plans_owner(bool $ready=true): array {
    $GLOBALS['calls'][]=['owner',$ready];hosting_catalog_guard();
    if (isset($GLOBALS['cwp_settings'])) throw new LogicException('Stale settings cache.');
    if (!($GLOBALS['model']['owner']??true)) throw new RuntimeException('Owner denied.');return ['id'=>1];
}
function staff_can(string $capability,array $actor): bool {$GLOBALS['calls'][]=['permission',$capability,$actor['id']];return $GLOBALS['model']['financial']??true;}
function service_plans_current_client(int $client): array {
    hosting_catalog_guard();$GLOBALS['calls'][]=['client',$client];
    if (isset($GLOBALS['cwp_settings'])) throw new LogicException('Stale settings cache.');
    if ($client!==($GLOBALS['model']['current_client']??7)||($GLOBALS['model']['support']??false)||($GLOBALS['model']['revoked']??false)) throw new RuntimeException('Client denied.');return ['id'=>$client];
}
function hosting_checkout_ready(): bool {$GLOBALS['calls'][]='checkout-ready';if ($GLOBALS['model']['history_error']??false) throw new RuntimeException('History needs recovery.');return $GLOBALS['model']['ready']??true;}
function service_orders_storage(): array {return $GLOBALS['model']['storage']??['version'=>'1','existing'=>['service_orders','service_order_events'],'complete'=>true];}
function service_orders_require(): void {if (!service_orders_storage()['complete']) throw new RuntimeException('Order history missing.');}
function service_orders_schema(string $prefix): array {return ['service_orders'=>'orders-schema','service_order_events'=>'events-schema'];}
function service_orders_validate_schema(string $name,string $sql): void {$GLOBALS['calls'][]=['schema',$name];if ($GLOBALS['model']['schema_error']??false) throw new RuntimeException('Schema differs.');}
function config(): array {return ['db'=>['prefix'=>'test_']];}
function table(string $name): string {return '`'.$name.'`';}
function one(string $sql,array $params=[]): ?array {
    $GLOBALS['calls'][]=['one',$sql,$params];
    if (str_contains($sql,'`audit`')) return ($GLOBALS['model']['order_witness']??false)?['id'=>1]:null;
    if (str_contains($sql,'SELECT 1 AS present')) return ($GLOBALS['model']['unmarked_rows']??false)?['present'=>1]:null;
    if (str_contains($sql,'WHERE agreement_id=?')) return ($GLOBALS['model']['has_order']??false)?['id'=>'order']:null;
    if (str_contains($sql,'WHERE invoice_id=?')) return null;
    throw new LogicException('Unexpected exact query '.$sql);
}
function all(string $sql,array $params=[]): array {
    $GLOBALS['calls'][]=['all',$sql,$params];
    if ($GLOBALS['model']['pdo']??false) throw new PDOException('Database unavailable.');
    if (str_contains($sql,'`hosting_checkout_results`')) {
        if (!preg_match('/ORDER BY created_epoch DESC,id DESC LIMIT 21 OFFSET ([0-9]+)$/',$sql,$match)) throw new LogicException('Unbounded or unstable purchase query.');
        if (($GLOBALS['model']['return_foreign']??false)) return [['client_id'=>8]];
        $rows=$GLOBALS['model']['rows']??[['client_id'=>7],['client_id'=>8]];
        if (str_contains($sql,'WHERE client_id=?')) $rows=array_values(array_filter($rows,fn($row)=>$row['client_id']===$params[0]));
        return array_slice($rows,(int)$match[1],21);
    }
    if (str_contains($sql,'`studio_invoices`')) {
        if (str_contains($sql,'WHERE i.id=?')) return $params===[4]?[['id'=>4,'reference'=>'ORIGINAL-4','title'=>'Original hosting']]:[];
        if (!str_contains($sql,'ORDER BY i.id DESC LIMIT 51')) throw new LogicException('Legacy selector changed.');
        return array_map(fn($id)=>['id'=>$id,'reference'=>'NEW-'.$id,'title'=>'New invoice'],range(120,70));
    }
    throw new LogicException('Unexpected list query.');
}
function hosting_checkout_result_row(array $row): array {
    if ($GLOBALS['model']['result_error']??false) throw new RuntimeException('Corrupt checkout.');
    return ['document'=>['request_id'=>str_repeat('1',32),'client_id'=>$row['client_id'],'project_id'=>9,'agreement_id'=>str_repeat('2',32),'invoice_id'=>4,'accepted_at'=>1700000000],
        'invoice'=>['reference'=>'ORIGINAL-4','status'=>$GLOBALS['model']['invoice_status']??'issued','amount_cents'=>'2500','currency'=>'USD','private_receipt'=>'PRIVATE'],
        'publication'=>new HpgDocument(['product'=>['title'=>'Original <Gold>'],'mapping_json'=>'PRIVATE'])];
}
function service_plans_row(string $id,bool $one=true,bool $two=true): array {return ['id'=>$id];}
function service_plans_validate(array $row,bool $subscriptions=true): HpgDocument {return new HpgDocument(['id'=>$row['id'],'client_id'=>$GLOBALS['model']['agreement_client']??7,'format'=>$GLOBALS['model']['format']??2,'state'=>$GLOBALS['model']['agreement_state']??'active']);}
function recurring_billing_job(int $id,int $client): array {if ($GLOBALS['model']['project_error']??false) throw new RuntimeException('Project ownership changed.');return ['id'=>$id,'client_id'=>$client,'title'=>'Hosting project','private'=>'PRIVATE'];}
function service_plans_client_row(int $id,bool $active=true): array {return ['id'=>$id,'name'=>'Client <Name>','email'=>'PRIVATE'];}
function hosting_checkout_recovery(): never {throw new RuntimeException('History needs recovery.');}
function service_orders_validate(array $row): HpgDocument {
    if ($GLOBALS['model']['order_error']??false) throw new RuntimeException('Corrupt order.');
    return new HpgDocument(['id'=>str_repeat('3',32),'agreement'=>['id'=>str_repeat('2',32)],'client_id'=>7,'invoice'=>['id'=>$GLOBALS['model']['order_invoice']??4]],
        ['phase'=>$GLOBALS['model']['phase']??'offered','domain_name'=>($GLOBALS['model']['phase']??'offered')==='accepted'?'example.test':null,'manual'=>['private'=>'PRIVATE']]);
}
function service_orders_owner(): void {}
function hosting_catalog_agreement_origin(string $agreement,int $client): ?array {return ($GLOBALS['model']['missing_origin']??false)?null:['invoice_id'=>4];}
function service_orders_invoice(HpgDocument $source,int $invoice): array {if ($GLOBALS['model']['invoice_ineligible']??false) throw new RuntimeException('Invoice not eligible.');return ['sha256'=>str_repeat('a',64),'amount_cents'=>2500];}
function hpg_reset(array $overrides=[]): void {$GLOBALS['model']=$overrides;$GLOBALS['calls']=[];$_SESSION=[];unset($GLOBALS['cwp_settings']);}
try {
    hpg_reset();$GLOBALS['cwp_settings']=['stale'=>true];$data=hosting_purchases_client_page(7);
    hpg_ok('Client query binds current client and returns only their purchase',count($data['items'])===1&&in_array(['all','SELECT * FROM `hosting_checkout_results` WHERE client_id=? ORDER BY created_epoch DESC,id DESC LIMIT 21 OFFSET 0',[7]],$calls,true));
    $item=$data['items'][0];
    hpg_ok('Client projection has only the agreed public fields',array_keys($item)===['request_id','created_epoch','offer_title','invoice','project','agreement','order']&&!str_contains(json_encode($item),'PRIVATE'));
    hpg_ok('Original invoice and offer are retained',$item['invoice']['id']===4&&$item['offer_title']==='Original <Gold>'&&$item['invoice']['amount_cents']===2500);
    hpg_reset(['rows'=>[['client_id'=>7]]]);$GLOBALS['cwp_settings']=['stale'=>true];$data=hosting_purchases_owner_page();
    hpg_ok('Owner reader rechecks current financial authority after locking',array_slice($calls,0,3)===['lock',['owner',false],['permission','payments.manage',1]]&&$data['items'][0]['client']===['id'=>7,'name'=>'Client <Name>']);
    foreach (['owner','financial'] as $key) {hpg_reset([$key=>false]);hpg_bad('Denied '.$key.' cannot read purchases',fn()=>hosting_purchases_owner_page());hpg_ok('Denied '.$key.' stops before history scan',!in_array('checkout-ready',$calls,true));}
    foreach (['contact_id','contact_auth'] as $key) {hpg_reset();$_SESSION[$key]=null;hpg_bad('Contact provenance rejected '.$key,fn()=>hosting_purchases_client_page(7));hpg_ok('Contact rejected before authority normalization '.$key,$calls===[]);}
    foreach (['support','revoked'] as $key) {hpg_reset([$key=>true]);hpg_bad('Current client refusal '.$key,fn()=>hosting_purchases_client_page(7));}
    hpg_reset();hpg_bad('Foreign client argument cannot select another account',fn()=>hosting_purchases_client_page(8));
    hpg_reset(['return_foreign'=>true]);hpg_bad('Defensive row ownership rejects a foreign result',fn()=>hosting_purchases_client_page(7));
    foreach (['history_error','result_error','project_error','schema_error','pdo'] as $key) {hpg_reset([$key=>true]);hpg_bad('Unavailable history is an error '.$key,fn()=>hosting_purchases_client_page(7));}
    hpg_reset(['ready'=>false]);hpg_ok('Unprepared checkout is explicitly unavailable',hosting_purchases_client_page(7)===['items'=>[],'page'=>1,'has_more'=>false,'ready'=>false]);
    hpg_reset(['rows'=>array_fill(0,41,['client_id'=>7])]);$data=hosting_purchases_client_page(7,2);hpg_ok('Second page returns twenty with a further-page marker',count($data['items'])===20&&$data['has_more']&&$data['page']===2);
    $data=hosting_purchases_client_page(7,3);hpg_ok('Final page returns only the retained remainder',count($data['items'])===1&&!$data['has_more']);
    foreach ([0,100001] as $page) {hpg_reset();hpg_bad('Out of bounds page refused '.$page,fn()=>hosting_purchases_client_page(7,$page),InvalidArgumentException::class);}
    hpg_reset(['locked'=>true]);hpg_bad('Inherited transaction is rejected',fn()=>hosting_purchases_client_page(7),LogicException::class);
    hpg_reset();hpg_bad('Internal reader cannot run without owning transaction',fn()=>hosting_purchases_page(1,null),LogicException::class);
    $empty=['version'=>'','existing'=>[],'complete'=>false];hpg_reset(['storage'=>$empty]);hpg_ok('Never prepared order storage leaves setup unstarted',hosting_purchases_client_page(7)['items'][0]['order']===null);
    hpg_reset(['storage'=>$empty,'order_witness'=>true]);hpg_bad('Erased order storage does not masquerade as unstarted',fn()=>hosting_purchases_client_page(7));
    hpg_reset(['storage'=>['version'=>'','existing'=>['service_orders'],'complete'=>false],'unmarked_rows'=>true]);hpg_bad('Unmarked populated order storage requires recovery',fn()=>hosting_purchases_client_page(7));
    hpg_reset(['storage'=>['version'=>'1','existing'=>[],'complete'=>false]]);hpg_bad('Incomplete prepared storage requires recovery',fn()=>hosting_purchases_client_page(7));
    hpg_reset(['order_witness'=>true]);hpg_bad('Missing order with retained offered witness requires recovery',fn()=>hosting_purchases_client_page(7));
    foreach (['offered','accepted','cancelled','withdrawn','declined'] as $phase) {
        hpg_reset(['has_order'=>true,'phase'=>$phase,'invoice_status'=>'paid']);$item=hosting_purchases_client_page(7)['items'][0];
        hpg_ok('Local phase remains distinct from invoice and agreement '.$phase,$item['order']['phase']===$phase&&array_keys($item['order'])===['id','phase','domain_name']&&$item['invoice']['status']==='paid'&&$item['agreement']['state']==='active'&&!str_contains(json_encode($item),'PRIVATE'));
    }
    hpg_reset(['has_order'=>true,'order_error'=>true]);hpg_bad('Corrupt order cannot be displayed',fn()=>hosting_purchases_client_page(7));
    hpg_reset(['has_order'=>true,'order_invoice'=>5]);hpg_bad('Different order invoice cannot be substituted',fn()=>hosting_purchases_client_page(7));
    hpg_reset();$context=service_orders_context(str_repeat('2',32),7);
    hpg_ok('Checkout selects original invoice regardless of newer same-priced invoices',array_column($context['choices'],'id')===[4]&&!$context['more']);
    hpg_reset(['missing_origin'=>true]);hpg_bad('Missing checkout origin cannot fall back to another invoice',fn()=>service_orders_context(str_repeat('2',32),7));
    hpg_reset(['invoice_ineligible'=>true]);hpg_ok('Ineligible original invoice is not replaced',service_orders_context(str_repeat('2',32),7)['choices']===[]);
    hpg_reset(['format'=>1]);$context=service_orders_context(str_repeat('2',32),7);hpg_ok('Legacy chooser retains its fifty-row bound and more indicator',count($context['choices'])===50&&$context['more']&&$context['choices'][0]['id']===120);
    echo json_encode(['status'=>'passed','scope'=>'Actual source functions; modeled SQL and authority, no native database or HTTP','assertions'=>count($checks),'checks'=>$checks,'source_fragments'=>$fragments],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {fwrite(STDERR,$e::class.': '.$e->getMessage()."\n");exit(1);}
