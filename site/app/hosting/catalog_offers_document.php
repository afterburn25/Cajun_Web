<?php
/** Pure publication/request contracts. No SQL, authentication, provider call or authority grant. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/plan_catalog_document.php';
require_once CWP_CODE_ROOT.'/app/providers/catalog_import_document.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/InventoryPackageType.php';

function hosting_catalog_hash(mixed $value): void {
    if (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/',$value)) throw new InvalidArgumentException('Invalid hosting offer fingerprint.');
}
function hosting_catalog_text(mixed $value,int $max,bool $empty=false,bool $multiline=false): void {
    if (!is_string($value) || strlen($value)>$max || (!$empty&&trim($value)==='') || preg_match('//u',$value)!==1 || preg_match($multiline?'/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/':'/[\x00-\x1f\x7f]/',$value)) throw new InvalidArgumentException('Invalid hosting offer text.');
}
/** Exact selected product fields; internal cost/provider reference never enter public projection. */
function hosting_catalog_product(array $p): array {
    service_plans_keys($p,['id','revision','sku','title','family','status','term','currency','amount_cents','setup_cents','summary','features','terms','media_id']);
    service_plans_actor($p['id']);service_plans_actor($p['revision']);
    if (!is_string($p['sku']) || !preg_match('/\A[A-Z0-9][A-Z0-9_-]{1,59}\z/',$p['sku']) || $p['family']!=='hosting' || $p['status']!=='draft' || !in_array($p['term'],['month','year'],true) || $p['currency']!=='USD' || !is_int($p['amount_cents']) || $p['amount_cents']<1 || $p['amount_cents']>99999999 || $p['setup_cents']!==0) throw new InvalidArgumentException('Choose a priced monthly or annual hosting draft without a separate setup fee.');
    hosting_catalog_text($p['title'],120);hosting_catalog_text($p['summary'],1000,false,true);hosting_catalog_text($p['features'],4000,true,true);hosting_catalog_text($p['terms'],4000,true,true);
    if ($p['media_id']!==null) service_plans_id($p['media_id']);
    return $p;
}
/** Original import hash formula is deliberately unchanged; its SQL audit witness remains mandatory. */
function hosting_catalog_import(array $i): array {
    service_plans_keys($i,['id','product_id','profile_id','source_revision','refresh_id','source_key_fingerprint','type_ref','type_name','source_hash']);
    foreach (['id','product_id','source_revision'] as $k) service_plans_actor($i[$k]);
    service_plans_id($i['profile_id']);service_plans_id($i['refresh_id']);hosting_catalog_hash($i['source_key_fingerprint']);hosting_catalog_hash($i['source_hash']);
    if (!is_string($i['type_ref']) || !preg_match('/\A[1-9][0-9]{0,17}\z/',$i['type_ref'])) throw new InvalidArgumentException('Invalid imported hosting type.');
    hosting_catalog_text($i['type_name'],120);
    $original=hash('sha256',json_encode([$i['profile_id'],$i['source_revision'],$i['refresh_id'],$i['source_key_fingerprint'],$i['type_ref'],$i['type_name']],JSON_THROW_ON_ERROR));
    if (!hash_equals($original,$i['source_hash'])) throw new InvalidArgumentException('Imported hosting provenance differs.');
    return $i;
}
/** Called with a complete, authenticated, history-validated saved list, never wire/HTTP input. */
function hosting_catalog_types(array $types): array {
    $doc=ProviderCatalogImportDocument::fromPackageTypes($types);
    return array_map(static fn(array $r):array=>['id'=>$r['type_ref'],'label'=>$r['name']],$doc->types);
}
function hosting_catalog_types_hash(array $types): string { return hash('sha256',service_plans_json(hosting_catalog_types($types))); }
function hosting_catalog_observation(array $o,string $type): array {
    service_plans_keys($o,['refresh_id','profile_id','profile_revision','key_fingerprint','observed_epoch','types']);
    service_plans_id($o['refresh_id']);service_plans_id($o['profile_id']);service_plans_actor($o['profile_revision']);hosting_catalog_hash($o['key_fingerprint']);service_plans_time($o['observed_epoch']);
    if (!is_array($o['types'])) throw new InvalidArgumentException('Invalid observed hosting list.');
    $doc=ProviderCatalogImportDocument::fromPackageTypes($o['types']);
    $selected=$doc->type($type);
    return ['refresh_id'=>$o['refresh_id'],'profile_id'=>$o['profile_id'],'profile_revision'=>$o['profile_revision'],'key_fingerprint'=>$o['key_fingerprint'],'observed_epoch'=>$o['observed_epoch'],'types_sha256'=>hosting_catalog_types_hash($o['types']),'selected'=>['id'=>$selected['type_ref'],'label'=>$selected['name']]];
}
function hosting_catalog_observation_binding(array $o): void {
    service_plans_keys($o,['refresh_id','profile_id','profile_revision','key_fingerprint','observed_epoch','types_sha256','selected']);
    service_plans_id($o['refresh_id']);service_plans_id($o['profile_id']);service_plans_actor($o['profile_revision']);hosting_catalog_hash($o['key_fingerprint']);hosting_catalog_hash($o['types_sha256']);service_plans_time($o['observed_epoch']);
    \CajunWebPro\Foundation\TwentyIInventory\InventoryPackageType::fromStored($o['selected']);
}
function hosting_catalog_actor(array $a): void {
    service_plans_keys($a,['id','auth_version']);service_plans_actor($a['id']);hosting_catalog_text($a['auth_version'],128);
}
/** Current facts are keyed maps, not serialized documents; ordering never changes their meaning. */
function hosting_catalog_matches(array $actual,array $expected): bool {
    service_plans_keys($actual,array_keys($expected));
    foreach($expected as $key=>$value)if($actual[$key]!==$value)return false;
    return true;
}

/** Closed opt-in terms only; this value never grants SQL, payment or provider authority. */
function hosting_checkout_policy(array $policy): array {
    $fixed=['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting','collection'=>'invoice-only','first_period'=>'full-period-today',
        'requires_verified_payment'=>true,'requires_owner_provisioning'=>true];
    service_plans_keys($policy,[...array_keys($fixed),'timezone','alignment','review_ttl_seconds']);
    foreach($fixed as $key=>$value)if($policy[$key]!==$value)throw new InvalidArgumentException('Unsupported hosting checkout policy.');
    if (!is_string($policy['timezone']) || !in_array($policy['timezone'],DateTimeZone::listIdentifiers(DateTimeZone::ALL),true)
        || !in_array($policy['alignment'],['day-of-month','month-end'],true)
        || !is_int($policy['review_ttl_seconds']) || $policy['review_ttl_seconds']<1 || $policy['review_ttl_seconds']>900) {
        throw new InvalidArgumentException('Choose an explicit billing timezone, calendar alignment and bounded review lifetime.');
    }
    return $fixed+['timezone'=>$policy['timezone'],'alignment'=>$policy['alignment'],'review_ttl_seconds'=>$policy['review_ttl_seconds']];
}

final readonly class HostingCatalogPublication {
    public const MAX_BYTES=600000;
    private function __construct(private string $bytes) {}
    /** Caller separately owns current owner/password/history checks and the account transaction. */
    public static function create(string $id,int $at,array $product,array $import,array $observation,ServicePlanDocument $plan,string $mappingJson,array $current): self {
        $p=hosting_catalog_product($product);$i=hosting_catalog_import($import);
        $d=['format'=>1,'id'=>$id,'created_at'=>$at,'publisher'=>['id'=>$current['publisher']['id']??null,'auth_version'=>$current['publisher']['auth_version']??null],'product'=>$p,'import'=>$i,'observation'=>hosting_catalog_observation($observation,$i['type_ref']),'plan_json'=>$plan->json(),'mapping_json'=>$mappingJson];
        $result=self::fromJson(service_plans_json($d));$result->checkCurrent($current,$at,true);return $result;
    }
    /** New owner-reviewed opt-in. Caller must authorize financial/catalog scope under its native guard. */
    public static function createCheckout(string $id,int $at,array $product,array $import,array $observation,ServicePlanDocument $plan,string $mappingJson,array $current,array $policy): self {
        $original=self::create($id,$at,$product,$import,$observation,$plan,$mappingJson,$current);
        $data=$original->data();$data['format']=2;$data['checkout_policy']=hosting_checkout_policy($policy);
        $result=self::fromJson(service_plans_json($data));$result->checkCurrent($current,$at,true);return $result;
    }
    public function checkoutPolicy(): ?array { return $this->data()['checkout_policy']??null; }
    /** Historical parsing never depends on a present login, credential, current clock or publication state. */
    public static function fromJson(string $bytes): self {
        if (strlen($bytes)>self::MAX_BYTES) throw new InvalidArgumentException('Hosting publication exceeds its bound.');
        $d=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($d)) throw new InvalidArgumentException('Invalid hosting publication.');
        service_plans_keys($d,($d['format']??null)===2?['format','id','created_at','publisher','product','import','observation','plan_json','mapping_json','checkout_policy']:['format','id','created_at','publisher','product','import','observation','plan_json','mapping_json']);
        if (!in_array($d['format'],[1,2],true) || !is_string($d['plan_json']) || !is_string($d['mapping_json'])) throw new InvalidArgumentException('Invalid hosting publication format.');
        if ($d['format']===2 && (!is_array($d['checkout_policy']) || hosting_checkout_policy($d['checkout_policy'])!==$d['checkout_policy'])) throw new InvalidArgumentException('Noncanonical hosting checkout policy.');
        service_plans_id($d['id']);service_plans_time($d['created_at']);hosting_catalog_actor($d['publisher']);
        $p=hosting_catalog_product($d['product']);$i=hosting_catalog_import($d['import']);$o=$d['observation'];hosting_catalog_observation_binding($o);
        $plan=ServicePlanDocument::fromJson($d['plan_json']);$model=$plan->model();$m=provider_plan_catalog_document($d['mapping_json']);
        $planData=$plan->data();$events=$planData['events'];$planAt=$events?$events[array_key_last($events)]['at']:$planData['created_at'];
        if ($model->status()!=='active' || $m['plan_id']!==$planData['id'] || $m['plan_revision']!==$plan->revision() || $m['plan_sha256']!==$plan->fingerprint() || $p['title']!==$model->name || $p['amount_cents']!==$model->priceAmount || $p['currency']!==$model->currency || ['month'=>'monthly','year'=>'annually'][$p['term']]!==$model->cadence) throw new InvalidArgumentException('Hosting product and exact active plan terms differ.');
        if ($i['product_id']!==$p['id'] || $i['profile_id']!==$o['profile_id'] || !hash_equals($i['source_key_fingerprint'],$o['key_fingerprint']) || $m['profile_id']!==$o['profile_id'] || $m['profile_revision']!==$o['profile_revision'] || $m['configuration']['type']!==$i['type_ref'] || $o['selected']['id']!==$i['type_ref']) throw new InvalidArgumentException('Hosting import, observation and provider mapping identities differ.');
        if (!$m['enabled'] || $d['created_at']<$m['created_at'] || $d['created_at']>=$m['expires_at'] || $d['created_at']<$planAt || $d['created_at']<$o['observed_epoch']) throw new InvalidArgumentException('Hosting publication was not current when reviewed.');
        if (service_plans_json($d)!==$bytes) throw new InvalidArgumentException('Noncanonical hosting publication.');
        return new self($bytes);
    }
    public function json(): string { return $this->bytes; }
    public function fingerprint(): string { return hash('sha256',$this->bytes); }
    public function data(): array { return json_decode($this->bytes,true,32,JSON_THROW_ON_ERROR); }
    public function assertCurrent(array $current,int $now): void { $this->checkCurrent($current,$now,false); }
    /** Current context must come from fresh SQL/authority validation under the owning guard, never POST. */
    private function checkCurrent(array $c,int $now,bool $creating): void {
        service_plans_time($now);service_plans_keys($c,['publication','product','plan','mapping','profile','catalog','publisher','mapping_reviewer','support_session']);
        $d=$this->data();$p=$d['product'];$o=$d['observation'];$m=provider_plan_catalog_document($d['mapping_json']);
        $plan=ServicePlanDocument::fromJson($d['plan_json']);
        service_plans_keys($c['publication'],['id','sha256','enabled']);
        service_plans_keys($c['product'],['id','revision','sha256']);service_plans_keys($c['plan'],['id','revision','sha256','status']);service_plans_keys($c['mapping'],['id','sha256']);
        service_plans_keys($c['profile'],['id','revision','enabled','credential_sha256','key_fingerprint']);service_plans_keys($c['catalog'],['refresh_id','state','profile_id','profile_revision','key_fingerprint','observed_epoch','types_sha256']);
        foreach (['publisher','mapping_reviewer'] as $key) {
            service_plans_keys($c[$key],['id','role','enabled','auth_version']);
            if ($c[$key]['role']!=='owner' || $c[$key]['enabled']!==true) throw new RuntimeException('Hosting review authority is no longer current.');
            hosting_catalog_actor(['id'=>$c[$key]['id'],'auth_version'=>$c[$key]['auth_version']]);
        }
        $pubHash=$creating?null:$this->fingerprint();
        if ($c['support_session']!==false || !hosting_catalog_matches($c['publication'],['id'=>$d['id'],'sha256'=>$pubHash,'enabled'=>true]) || $now<$d['created_at'] || $now>=$m['expires_at']) throw new RuntimeException('Hosting offer is not currently available.');
        if (!hosting_catalog_matches($c['product'],['id'=>$p['id'],'revision'=>$p['revision'],'sha256'=>hash('sha256',service_plans_json($p))]) || !hosting_catalog_matches($c['plan'],['id'=>$m['plan_id'],'revision'=>$plan->revision(),'sha256'=>$plan->fingerprint(),'status'=>'active']) || !hosting_catalog_matches($c['mapping'],['id'=>$m['id'],'sha256'=>hash('sha256',$d['mapping_json'])])) throw new RuntimeException('Hosting commercial or mapping revision changed.');
        // These are DIFFERENT algorithms: encrypted credential SHA versus saved inventory HMAC.
        if (!hosting_catalog_matches($c['profile'],['id'=>$o['profile_id'],'revision'=>$o['profile_revision'],'enabled'=>true,'credential_sha256'=>$m['credential_sha256'],'key_fingerprint'=>$o['key_fingerprint']])) throw new RuntimeException('Hosting provider credential context changed.');
        if (!hosting_catalog_matches($c['catalog'],['refresh_id'=>$o['refresh_id'],'state'=>'complete','profile_id'=>$o['profile_id'],'profile_revision'=>$o['profile_revision'],'key_fingerprint'=>$o['key_fingerprint'],'observed_epoch'=>$o['observed_epoch'],'types_sha256'=>$o['types_sha256']])) throw new RuntimeException('The latest hosting catalog observation differs or is incomplete.');
        if ($c['publisher']['id']!==$d['publisher']['id'] || !hash_equals($d['publisher']['auth_version'],$c['publisher']['auth_version']) || $c['mapping_reviewer']['id']!==$m['created_by'] || !hash_equals($m['owner_auth'],$c['mapping_reviewer']['auth_version'])) throw new RuntimeException('Hosting review authority changed.');
    }
    /** Only this closed value reaches a public card. Caller escapes all text and resolves local image_id. */
    public function publicProjection(array $current,int $now): array {
        $this->assertCurrent($current,$now);return $this->historicalProjection();
    }
    /** Caller checks request ownership. This preserves agreed terms, never current availability or authority. */
    public function historicalProjection(): array {
        $d=$this->data();$p=$d['product'];$m=ServicePlanDocument::fromJson($d['plan_json'])->model();
        return ['publication_id'=>$d['id'],'publication_sha256'=>$this->fingerprint(),'product_id'=>$p['id'],'product_revision'=>$p['revision'],'sku'=>$p['sku'],'title'=>$m->name,'summary'=>$p['summary'],'amount_cents'=>$m->priceAmount,'currency'=>$m->currency,'cadence'=>$m->cadence,'inclusions'=>$m->inclusions,'exclusions'=>$m->exclusions,'limits'=>$m->limits,'support'=>$m->supportExpectation,'image_id'=>$p['media_id']]+($d['format']===2?['checkout_policy'=>$d['checkout_policy']]:[]);
    }
}

final readonly class HostingCatalogRequest {
    private function __construct(private string $bytes) {}
    /** Client authentication/CSRF and locking belong to the calling service; these are strict supplied facts. */
    public static function create(string $id,HostingCatalogPublication $publication,array $client,int $at,array $current): self {
        if ($publication->checkoutPolicy()!==null) throw new InvalidArgumentException('Use checkout confirmation for this hosting offer.');
        $publication->assertCurrent($current,$at);self::client($client);
        return self::fromJson(service_plans_json(['format'=>1,'id'=>$id,'publication_id'=>$publication->data()['id'],'publication_sha256'=>$publication->fingerprint(),'client_id'=>$client['id'],'quantity'=>1,'created_at'=>$at]));
    }
    public static function fromJson(string $bytes): self {
        if (strlen($bytes)>2048) throw new InvalidArgumentException('Hosting request exceeds its bound.');
        $d=json_decode($bytes,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($d)) throw new InvalidArgumentException('Invalid hosting request.');
        service_plans_keys($d,['format','id','publication_id','publication_sha256','client_id','quantity','created_at']);
        if ($d['format']!==1 || $d['quantity']!==1) throw new InvalidArgumentException('Invalid hosting request format or quantity.');
        service_plans_id($d['id']);service_plans_id($d['publication_id']);hosting_catalog_hash($d['publication_sha256']);service_plans_actor($d['client_id']);service_plans_time($d['created_at']);
        if (service_plans_json($d)!==$bytes) throw new InvalidArgumentException('Noncanonical hosting request.');
        return new self($bytes);
    }
    private static function client(array $client): void {
        service_plans_keys($client,['id','active','support_session']);service_plans_actor($client['id']);
        if ($client['active']!==true || $client['support_session']!==false) throw new RuntimeException('A current client session is required.');
    }
    public function json(): string { return $this->bytes; }
    public function fingerprint(): string { return hash('sha256',$this->bytes); }
    public function data(): array { return json_decode($this->bytes,true,8,JSON_THROW_ON_ERROR); }
    public function assertClient(array $client): void {
        self::client($client);if ($client['id']!==$this->data()['client_id']) throw new RuntimeException('Hosting request unavailable.');
    }
    /** Exact replay input excludes creation clock and client password; history survives password rotation. */
    public function inputHash(): string {
        $d=$this->data();return hash('sha256',service_plans_json(['publication_id'=>$d['publication_id'],'publication_sha256'=>$d['publication_sha256'],'client_id'=>$d['client_id'],'quantity'=>1]));
    }
    public function assertPublication(HostingCatalogPublication $publication): void {
        if ($publication->checkoutPolicy()!==null) throw new RuntimeException('A checkout offer cannot be converted through a legacy hosting request.');
        $d=$this->data();if ($publication->data()['id']!==$d['publication_id'] || !hash_equals($publication->fingerprint(),$d['publication_sha256']) || $d['created_at']<$publication->data()['created_at']) throw new RuntimeException('Hosting request publication differs.');
    }
}
