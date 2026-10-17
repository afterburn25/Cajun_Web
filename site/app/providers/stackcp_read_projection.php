<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding,StackCpAccessObservation};

/** Internal synchronous read computation. Instances never escape a fixed entry point.
 * Family reuse lasts only this call under the owning mutex; nothing is cached across
 * calls, requests, transactions or writes. Public mutation APIs still start fresh.
 */
final class ProviderStackCpReadProjection {
    private array $families=[];
    private ?bool $accessReady=null;
    private function __construct(private ?ProviderStackCpAuthVault $vault=null) {}
    private function getVault(): ProviderStackCpAuthVault { return $this->vault??=provider_stackcp_vault(); }
    public static function ready(string $kind,?ProviderStackCpAuthVault $vault=null): bool { return (new self($vault))->familyReady($kind); }
    public static function auth(string $profileId,ProviderStackCpAuthVault $vault): array { return (new self($vault))->authContext($profileId,$vault); }
    public static function assignment(string $profileId,int $packageId,ProviderStackCpAuthVault $vault): array { return (new self($vault))->assignmentContext($profileId,$packageId,$vault); }
    public static function authHistory(string $profileId,int $before=0): array {
        return staff_locked(fn()=>staff_transaction(fn()=>(new self())->authHistoryRows($profileId,$before)));
    }
    public static function assignmentHistory(string $profileId,int $packageId,int $before=0): array {
        return staff_locked(fn()=>staff_transaction(fn()=>(new self())->assignmentHistoryRows($profileId,$packageId,$before)));
    }
    /** Safe metadata only; no callback, reusable context or provider request. */
    public static function ownerSnapshot(int $page=1): array {
        if ($page<1 || $page>500) throw new InvalidArgumentException('Invalid StackCP assignment page.');
        if (db()->inTransaction()) throw new LogicException('StackCP owner read must own its transaction.');
        return staff_locked(fn()=>staff_transaction(fn()=>(new self())->ownerPage($page)));
    }
    private function familyReady(string $kind): bool {
        provider_stackcp_guard();if (array_key_exists($kind,$this->families)) return $this->families[$kind];$vault=$this->vault;$f=provider_stackcp_family($kind);$settings=table('settings');$audit=table('audit');$name=$f['table'];
            foreach (['settings','audit','provider_profiles','users'] as $required) {
                $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$required]);
                if (strtolower($engine['ENGINE']??'')!=='innodb') throw new RuntimeException('StackCP prerequisites require transactional storage.');
            }
            $version=one("SELECT value FROM $settings WHERE name=?",[$f['setting'].'_schema_version'])['value']??'';
            if ($version==='') {
                $witness=one("SELECT id FROM $audit WHERE BINARY action IN (?,?) LIMIT 1",['provider.'.$f['action'].'_prepared','provider.'.$f['action'].'_version'])!==null || one("SELECT name FROM $settings WHERE name IN (?,?) LIMIT 1",[$f['setting'].'_count',$f['setting'].'_proof'])!==null;
                if ($witness || (provider_stackcp_exists($kind) && (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)) throw new RuntimeException('Restore the original StackCP prerequisite history.');
                return $this->families[$kind]=false;
            }
            if ($version!=='1' || !provider_settings_ready() || !provider_stackcp_exists($kind)) throw new RuntimeException('StackCP prerequisite storage needs recovery.');
            provider_stackcp_schema_validate($kind);$vault??=provider_stackcp_vault();$this->vault=$vault;$proof=provider_stackcp_proof($vault,$kind.'-prepared','1');
            if ($kind==='assignment' && (!provider_inventory_ready() || !$this->familyReady('auth'))) throw new RuntimeException('StackCP assignment prerequisites need recovery.');
            if (!hash_equals($proof,one("SELECT value FROM $settings WHERE name=?",[$f['setting'].'_proof'])['value']??'')) throw new RuntimeException('StackCP preparation proof needs recovery.');
            $prepared='provider.'.$f['action'].'_prepared';
            if ((int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action=?",[$prepared])->fetchColumn()!==1 || (int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action=? AND BINARY detail=?",[$prepared,provider_stackcp_prepared_detail($kind,$proof)])->fetchColumn()!==1) throw new RuntimeException('StackCP preparation audit needs recovery.');
            $count=provider_stackcp_counter($kind);$table=table($name);$action='provider.'.$f['action'].'_version';
            if ($count!==(int)query("SELECT COUNT(*) FROM $table")->fetchColumn() || $count!==(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action=?",[$action])->fetchColumn()) throw new RuntimeException('StackCP history counts disagree.');
            $seen=0;$chains=[];$lastAudit=0;
            // At most 10,000 rows and 200 rows per fetch. No per-row database round trips.
            while ($seen<$count) {
                $detail="CONCAT(BINARY '".$f['label']." version ',r.id,' ',r.document_sha256,' ',r.proof)";
                $rows=all("SELECT r.*,(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail) AS audit_count,(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail AND a.user_id=r.actor_id) AS actor_count,(SELECT MIN(a.id) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail) AS journal_audit_id FROM $table r WHERE r.ordinal>? ORDER BY r.ordinal LIMIT 200",[$action,$action,$action,$seen]);
                if (!$rows) throw new RuntimeException('StackCP history order needs recovery.');
                foreach ($rows as $row) {
                    $d=provider_stackcp_row($kind,$row,$vault);$previous=$chains[$d['scope_id']]??null;
                    if ($d['ordinal']!==++$seen || (int)$row['audit_count']!==1 || (int)$row['actor_count']!==1 || (int)$row['journal_audit_id']<=$lastAudit || $d['revision']!==($previous['revision']??0)+1 || $d['previous_sha256']!==($previous['sha256']??str_repeat('0',64)) || $d['recorded_epoch']<($previous['epoch']??0)) throw new RuntimeException('StackCP history chain needs recovery.');
                    $chains[$d['scope_id']]=['revision'=>$d['revision'],'sha256'=>$row['document_sha256'],'epoch'=>$d['recorded_epoch']];$lastAudit=(int)$row['journal_audit_id'];
                }
            }
            if ($kind==='assignment') provider_stackcp_assignment_sources($vault);
            return $this->families[$kind]=true;
    }
    private function authContext(string $profileId,ProviderStackCpAuthVault $vault): array {
        provider_stackcp_guard();$actor=provider_settings_manager();provider_stackcp_hex($profileId);
            if (!$this->familyReady('auth')) return ['prepared'=>false,'status'=>'unprepared','login_ready'=>false];
            $p=provider_settings_load($profileId);$row=provider_stackcp_latest('auth',$profileId);$d=$row?provider_stackcp_row('auth',$row,$vault):null;
            $review=['profile_id'=>$profileId,'profile_revision'=>(int)$p['revision'],'profile_credentials_sha256'=>hash('sha256',$p['credentials']),'profile_enabled'=>(int)$p['enabled'],'auth_id'=>$d['id']??'','auth_sha256'=>$row['document_sha256']??'','actor_id'=>(int)$actor['id'],'actor_auth_version'=>$actor['auth_version']];
            $status=$d===null?'not_configured':(!$d['enabled']?'disabled':'configured');
            if ($d!==null && $d['enabled'] && ($d['profile_revision']!==(int)$p['revision'] || !hash_equals($d['profile_credentials_sha256'],hash('sha256',$p['credentials'])) || (int)$p['enabled']!==1 || $p['credentials']==='' || !provider_stackcp_actor_current($d['actor_id'],$d['actor_auth_version']))) $status='stale';
            return ['prepared'=>true,'status'=>$status,'login_ready'=>false,'review_sha256'=>provider_stackcp_proof($vault,'auth-review',provider_stackcp_json($review)),'profile'=>$p,'row'=>$row,'document'=>$d,'actor'=>$actor];
    }
    private function assignmentContext(string $profileId,int $packageId,ProviderStackCpAuthVault $vault): array {
        provider_stackcp_guard();$actor=provider_settings_manager();provider_stackcp_hex($profileId);provider_stackcp_number($packageId);
            if (!$this->familyReady('assignment')) return ['prepared'=>false,'status'=>'unprepared','login_ready'=>false];
            $auth=$this->authContext($profileId,$vault);$scope=$profileId.':'.$packageId;$row=provider_stackcp_latest('assignment',$scope);$d=$row?provider_stackcp_row('assignment',$row,$vault):null;$binding=provider_stackcp_local_binding($profileId,$packageId);
            $review=['profile_id'=>$profileId,'package_id'=>$packageId,'assignment_id'=>$d['id']??'','assignment_sha256'=>$row['document_sha256']??'','binding'=>$binding,'auth_review'=>$auth['review_sha256'],'actor_id'=>(int)$actor['id'],'actor_auth_version'=>$actor['auth_version']];
            $status=$d===null?'not_assigned':$d['state'];
            if ($d!==null && $d['state']!=='revoked') {
                $same=$binding!==null && $auth['status']==='configured' && $auth['document']['id']===$d['auth_id'] && hash_equals($auth['row']['document_sha256'],$d['auth_sha256']) && provider_stackcp_actor_current($d['actor_id'],$d['actor_auth_version']);
                if ($same) foreach ($binding as $key=>$value) if ($d[$key]!==$value) {$same=false;break;}
                if (!$same) $status='stale';
            }
            return ['prepared'=>true,'status'=>$status,'login_ready'=>false,'review_sha256'=>provider_stackcp_proof($vault,'assignment-review',provider_stackcp_json($review)),'profile_id'=>$profileId,'package_id'=>$packageId,'row'=>$row,'document'=>$d,'binding'=>$binding,'auth'=>$auth,'actor'=>$actor];
    }
    private function authHistoryRows(string $profileId,int $before=0): array {
        provider_settings_manager();provider_stackcp_hex($profileId);$vault=$this->getVault();if (!$this->familyReady('auth')) return [];
                $rows=all('SELECT * FROM '.table('provider_stackcp_auth').' WHERE profile_id=?'.($before>0?' AND ordinal<?':'').' ORDER BY ordinal DESC LIMIT 21',$before>0?[$profileId,$before]:[$profileId]);$out=[];
                foreach ($rows as $r) {$d=provider_stackcp_row('auth',$r,$vault);$out[]=['id'=>$d['id'],'ordinal'=>$d['ordinal'],'revision'=>$d['revision'],'state'=>$d['enabled']?'configured_unverified':'disabled','actor_id'=>$d['actor_id'],'recorded_epoch'=>$d['recorded_epoch']];}return $out;
    }
    private function assignmentHistoryRows(string $profileId,int $packageId,int $before=0): array {
        provider_settings_manager();provider_stackcp_hex($profileId);provider_stackcp_number($packageId);$vault=$this->getVault();if (!$this->familyReady('assignment')) return [];
                $scope=$profileId.':'.$packageId;$rows=all('SELECT * FROM '.table('provider_stackcp_assignments').' WHERE scope_id=?'.($before>0?' AND ordinal<?':'').' ORDER BY ordinal DESC LIMIT 21',$before>0?[$scope,$before]:[$scope]);$out=[];
                foreach ($rows as $r) {$d=provider_stackcp_row('assignment',$r,$vault);$out[]=['id'=>$d['id'],'ordinal'=>$d['ordinal'],'revision'=>$d['revision'],'state'=>$d['state'],'stack_identity'=>$d['stack_identity'],'client_id'=>$d['client_id'],'actor_id'=>$d['actor_id'],'recorded_epoch'=>$d['recorded_epoch'],'provider_verified'=>false];}return $out;
    }
    private function ownerPage(int $page): array {
        provider_stackcp_guard(true);provider_settings_manager();
        $out=['state'=>'account_missing','auth'=>null,'auth_history'=>[],'packages'=>[],'page'=>$page,'has_more'=>false,'error'=>null];
        try {
            $profile=provider_settings_profile();if (!$profile) return $out;$id=$profile['id'];
            $auth=provider_stackcp_auth_safe($this->authContext($id,$this->getVault()));
            if (!$auth['prepared']) {$out['auth']=$auth;$out['state']='auth_unprepared';return $out;}
            $history=$this->authHistoryRows($id);$out['auth']=$auth;$out['auth_history']=$history;$out['state']='inventory_unprepared';
            if (!provider_inventory_ready()) return $out;
            $out['state']='assignment_unprepared';if (!$this->familyReady('assignment')) return $out;
            $out['state']='ready';provider_settings_manager();
            $packages=all('SELECT package_id FROM '.table('provider_package_links').' WHERE profile_id=? UNION SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(document_json,\'$.package_id\')) AS UNSIGNED) AS package_id FROM '.table('provider_stackcp_assignments').' WHERE profile_id=? ORDER BY package_id LIMIT 21 OFFSET '.(($page-1)*20),[$id,$id]);
            $out['has_more']=count($packages)>20;
            foreach (array_slice($packages,0,20) as $row) {
                $package=(int)$row['package_id'];
                try {
                    $c=$this->assignmentContext($id,$package,$this->getVault());
                    $view=provider_stackcp_assignment_safe($c)+['original_link_id'=>$c['document']['link_id']??''];
                    $out['packages'][]=['package_id'=>$package,'view'=>$view,'history'=>$this->assignmentHistoryRows($id,$package),'error'=>null];
                } catch (InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$out['packages'][]=['package_id'=>$package,'view'=>null,'history'=>[],'error'=>$e->getMessage()];}
            }
        } catch (InvalidArgumentException|RuntimeException $e) {if($e instanceof PDOException)throw $e;$out['error']=$e->getMessage();}
        return $out;
    }
    /** Fixed entry points: each public call starts fresh; internal reuse never escapes. */
    public static function accessReady(): bool { return (new self())->accessFamilyReady(); }
    public static function accessBase(string $profile,int $package): array { return (new self())->accessBaseContext($profile,$package); }
    public static function accessBinding(string $profile,int $package,string $operation): StackCpAccessBinding { return (new self())->accessBindingContext($profile,$package,$operation); }
    public static function accessProjection(string $profile,int $package): array { return (new self())->accessProjectionValue($profile,$package); }
    public static function ownerAccessSnapshot(int $page=1): array {
        if ($page<1 || $page>500) throw new InvalidArgumentException('Invalid StackCP access page.');
        if (db()->inTransaction()) throw new LogicException('StackCP access owner read must own its transaction.');
        return staff_locked(fn()=>staff_transaction(fn()=>(new self())->ownerAccessPage($page)));
    }

    private function accessFamilyReady():bool {
        provider_stackcp_guard();if ($this->accessReady!==null) return $this->accessReady;$settings=table('settings');$audit=table('audit');$f=table('provider_refreshes');$z=table('provider_refresh_results');$reads=table('provider_stackcp_reads');$results=table('provider_stackcp_observations');
        $marker=one("SELECT value FROM $settings WHERE name=?",['provider_stackcp_access_schema_version'])['value']??'';
        $operations=[StackCpAccessBinding::MEMBERSHIP,StackCpAccessBinding::GRANTS];
        if ($marker==='') {
            $witness=one("SELECT name FROM $settings WHERE name IN (?,?,?) LIMIT 1",['provider_stackcp_access_proof','provider_stackcp_access_checkpoint','provider_stackcp_access_checkpoint_proof'])||one("SELECT id FROM $audit WHERE BINARY action IN (?,?,?) LIMIT 1",['provider.stackcp_access_prepared','provider.stackcp_access_started','provider.stackcp_access_finished']);
            foreach (['started','finished'] as $kind) if (provider_stackcp_access_exists(provider_stackcp_access_table($kind))&&(int)query('SELECT COUNT(*) FROM '.table(provider_stackcp_access_table($kind)))->fetchColumn()>0) $witness=true;
            if ($witness) throw new RuntimeException('Restore the original StackCP access observation history.');
            if (provider_stackcp_access_exists('provider_refreshes')) {
                $hasOperation=one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'provider_refreshes','operation'])!==null;
                if (!$hasOperation) {
                    $inventoryVersion=one("SELECT value FROM $settings WHERE name=?",['provider_inventory_schema_version'])['value']??'';
                    // Explicit legacy inventories predate operation-scoped reads. Never adopt or repair them on GET.
                    if (!in_array($inventoryVersion,['1','2','3'],true)) throw new RuntimeException('StackCP access inventory history needs recovery.');
                } elseif (one("SELECT id FROM $f WHERE BINARY operation IN (?,?) LIMIT 1",$operations)) throw new RuntimeException('Restore the original StackCP access observation history.');
            }
            return $this->accessReady=false;
        }
        if ($marker!=='1'||!provider_stackcp_access_exists('provider_stackcp_reads')||!provider_stackcp_access_exists('provider_stackcp_observations')||!$this->familyReady('assignment')) throw new RuntimeException('StackCP access storage needs recovery.');
        provider_stackcp_access_validate_schema();$prepared=provider_stackcp_proof(provider_stackcp_vault(),'access-prepared','1');
        if (!hash_equals($prepared,one("SELECT value FROM $settings WHERE name=?",['provider_stackcp_access_proof'])['value']??'')||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.stackcp_access_prepared'")->fetchColumn()!==1||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='provider.stackcp_access_prepared' AND BINARY detail=?",['StackCP access observation storage ready '.$prepared])->fetchColumn()!==1) throw new RuntimeException('StackCP access preparation proof needs recovery.');
        $checkpoint=provider_stackcp_access_checkpoint();
        if ((int)query("SELECT COUNT(*) FROM $f WHERE BINARY operation IN (?,?)",$operations)->fetchColumn()!==$checkpoint['started']||(int)query("SELECT COUNT(*) FROM $z z JOIN $reads r ON r.id=z.id")->fetchColumn()!==$checkpoint['finished']) throw new RuntimeException('StackCP common observation counts disagree.');
        foreach (['started','finished'] as $kind) {
            $table=table(provider_stackcp_access_table($kind));$action='provider.stackcp_access_'.$kind;
            if ((int)query("SELECT COUNT(*) FROM $table")->fetchColumn()!==$checkpoint[$kind]||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action=?",[$action])->fetchColumn()!==$checkpoint[$kind]) throw new RuntimeException('StackCP access history counts disagree.');
            $seen=0;$previous=str_repeat('0',64);$lastAudit=0;$lastCommonAudit=0;$lastSeq=0;
            while ($seen<$checkpoint[$kind]) {
                $detail="CONCAT(BINARY 'StackCP access $kind ',r.id,' ',r.document_sha256,' ',r.proof)";
                $select=['r.*'];foreach (provider_stackcp_access_common_keys($kind==='finished') as $field) $select[]=((in_array($field,provider_stackcp_access_common_keys(),true)?'f.':'z.').$field.' AS c_'.$field);
                $select[]="(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail) AS own_audit_count";
                $select[]="(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail AND a.user_id=f.actor_id) AS own_actor_count";
                $select[]="(SELECT MIN(a.id) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $detail) AS own_audit_id";
                $commonDetail=$kind==='started'?"CONCAT(BINARY 'Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision)":"CONCAT(BINARY 'Refresh ',f.id,' ',z.state,IF(z.reason='','',' '),z.reason)";
                $commonAction='provider.refresh_'.($kind==='started'?'started':'finished');
                $select[]="(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $commonDetail) AS common_audit_count";
                $select[]="(SELECT COUNT(*) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $commonDetail AND a.user_id=f.actor_id) AS common_actor_count";
                $select[]="(SELECT MIN(a.id) FROM $audit a WHERE BINARY a.action=? AND BINARY a.detail=BINARY $commonDetail) AS common_audit_id";
                $join='';if ($kind==='finished') {$select[]='s.document_json AS start_json';$select[]='s.document_sha256 AS start_sha256';$join=" LEFT JOIN $z z ON z.id=r.id LEFT JOIN $reads s ON s.id=r.id";}
                $rows=all('SELECT '.implode(',',$select)." FROM $table r LEFT JOIN $f f ON f.id=r.id $join WHERE r.ordinal>? ORDER BY r.ordinal LIMIT 200",[$action,$action,$action,$commonAction,$commonAction,$commonAction,$seen]);
                if (!$rows) throw new RuntimeException('StackCP access history order needs recovery.');$starts=[];
                foreach ($rows as $row) {
                    $d=provider_stackcp_access_row($kind,$row);$common=[];foreach (provider_stackcp_access_common_keys($kind==='finished') as $field) $common[$field]=$row['c_'.$field];
                    if ($d['ordinal']!==++$seen||$d['previous_sha256']!==$previous||$d['common']!==provider_stackcp_access_common($common,$kind==='finished')||(int)$row['own_audit_count']!==1||(int)$row['own_actor_count']!==1||(int)$row['common_audit_count']!==1||(int)$row['common_actor_count']!==1||(int)$row['own_audit_id']<=$lastAudit||(int)$row['common_audit_id']<=$lastCommonAudit) throw new RuntimeException('StackCP access history correspondence needs recovery.');
                    if ($kind==='started') {if ((int)$common['seq']<=$lastSeq||(int)$row['own_audit_id']<=(int)$row['common_audit_id']) throw new RuntimeException('StackCP access intent sequence needs recovery.');$lastSeq=(int)$common['seq'];$starts[]=$d;}
                    else {if ($row['start_json']===null||$d['read_sha256']!==$row['start_sha256']||(int)$row['own_audit_id']>=(int)$row['common_audit_id']) throw new RuntimeException('StackCP access result source needs recovery.');$start=provider_stackcp_access_document('started',json_decode($row['start_json'],true,16,JSON_THROW_ON_ERROR));provider_stackcp_access_finish_check($start,$d);}
                    $lastAudit=(int)$row['own_audit_id'];$lastCommonAudit=(int)$row['common_audit_id'];$previous=$row['document_sha256'];
                }
                if ($starts) provider_stackcp_access_retained_sources($starts);
            }
            if ($previous!==$checkpoint[$kind.'_sha256']) throw new RuntimeException('StackCP access history head needs recovery.');
        }
        // A finish audit with its common result removed is an independent surviving witness.
        if (one("SELECT r.id FROM $reads r JOIN $audit a ON BINARY a.action='provider.refresh_finished' AND BINARY a.detail LIKE CONCAT('Refresh ',r.id,' %') LEFT JOIN $z z ON z.id=r.id WHERE z.id IS NULL LIMIT 1")) throw new RuntimeException('StackCP common result history needs recovery.');
        return $this->accessReady=true;
    }
    private function accessBaseContext(string $profile,int $package):array {
        provider_stackcp_guard();$actor=provider_settings_manager();$vault=provider_stackcp_vault();$c=$this->assignmentContext($profile,$package,$vault);
        if (!$c['prepared']||$c['status']!=='unverified'||$c['binding']===null||$c['auth']['status']!=='configured') throw new ProviderStackCpAccessUnavailable('Review a current protected StackCP assignment first.');
        $assignment=$c['document'];$auth=$c['auth']['document'];$local=$c['binding'];provider_stackcp_access_package_history($profile);$latest=service_observations_reads($profile)[0]??null;$p=provider_settings_load($profile);
        if (!$latest||!service_observations_fresh($latest)||!provider_inventory_link_context_matches($local,$p,$latest)) throw new ProviderStackCpAccessUnavailable('Refresh hosting inventory for this exact assignment first.');
        $source=provider_stackcp_access_package_source($latest['id'],$package);if ((int)$source['start']['id']<=$local['audit_id']) throw new ProviderStackCpAccessUnavailable('Refresh hosting inventory after this assignment or reapproval.');
        $b=['operation'=>StackCpAccessBinding::MEMBERSHIP,'profile_id'=>$profile,'profile_revision'=>$local['profile_revision'],'credential_sha256'=>$local['profile_credentials_sha256'],'key_fingerprint'=>$local['key_fingerprint'],'package_id'=>$package,'client_id'=>$local['client_id'],'client_auth_version'=>$local['client_auth_version'],'link_id'=>$local['link_id'],'link_sha256'=>$local['link_sha256'],'event_id'=>$local['event_id'],'event_sha256'=>$local['event_sha256'],'audit_id'=>$local['audit_id'],'audit_sha256'=>$local['audit_sha256'],'source_id'=>$source['row']['id'],'source_seq'=>(int)$source['row']['seq'],'source_sha256'=>$source['sha256'],'actor_id'=>(int)$actor['id'],'actor_auth_version'=>$actor['auth_version'],'assignment_id'=>$assignment['id'],'assignment_revision'=>$assignment['revision'],'assignment_sha256'=>$c['row']['document_sha256'],'assignment_proof'=>$c['row']['proof'],'auth_id'=>$auth['id'],'auth_revision'=>$auth['revision'],'auth_sha256'=>$c['auth']['row']['document_sha256'],'auth_proof'=>$c['auth']['row']['proof'],'auth_ciphertext_sha256'=>$auth['ciphertext_sha256'],'stack_identity'=>$assignment['stack_identity'],'membership_id'=>'','membership_seq'=>0,'membership_sha256'=>''];
        return (new StackCpAccessBinding($b))->data;
    }
    private function accessBindingContext(string $profile,int $package,string $operation):StackCpAccessBinding {
        $b=$this->accessBaseContext($profile,$package);
        if ($operation===StackCpAccessBinding::MEMBERSHIP) return new StackCpAccessBinding($b);
        if ($operation!==StackCpAccessBinding::GRANTS) throw new InvalidArgumentException('Choose a supported StackCP observation.');
        $r=provider_stackcp_access_latest($profile,$package,$b['stack_identity'],StackCpAccessBinding::MEMBERSHIP);$saved=$r?provider_stackcp_access_saved($r):null;
        if (!$saved||!provider_stackcp_access_recent($saved)||$saved['binding']->data!==$b||!in_array($b['stack_identity'],$saved['finish']['values'],true)) throw new ProviderStackCpAccessUnavailable('Refresh package membership and review this exact selected Stack user first.');
        $b['operation']=$operation;$b['membership_id']=$r['id'];$b['membership_seq']=(int)$saved['start']['common']['seq'];$b['membership_sha256']=$saved['row']['document_sha256'];return new StackCpAccessBinding($b);
    }
    private function accessProjectionValue(string $profile,int $package):array {
        provider_stackcp_guard();provider_settings_manager();provider_stackcp_hex($profile);provider_stackcp_number($package);
        $out=['prepared'=>$this->accessFamilyReady(),'profile_id'=>$profile,'package_id'=>$package,'status'=>'unprepared','login_ready'=>false,'remote_scope_restricted'=>false,'membership'=>null,'grants'=>null,'reviews'=>[]];if (!$out['prepared']) return $out;
        try {$base=$this->accessBaseContext($profile,$package);}catch (ProviderStackCpAccessUnavailable $e) {$out['status']='assignment_or_source_unavailable';return $out;}
        $out['stack_identity']=$base['stack_identity'];$out['status']='not_observed';$memberBinding=new StackCpAccessBinding($base);$out['reviews'][StackCpAccessBinding::MEMBERSHIP]=$memberBinding->sha256;
        try {$grantBinding=$this->accessBindingContext($profile,$package,StackCpAccessBinding::GRANTS);$out['reviews'][StackCpAccessBinding::GRANTS]=$grantBinding->sha256;}catch (ProviderStackCpAccessUnavailable) {$grantBinding=null;}
        foreach (['membership'=>StackCpAccessBinding::MEMBERSHIP,'grants'=>StackCpAccessBinding::GRANTS] as $key=>$operation) {
            $r=provider_stackcp_access_latest($profile,$package,$base['stack_identity'],$operation);if (!$r) continue;$saved=provider_stackcp_access_saved($r);$finish=$saved['finish'];$current=$key==='membership'?$memberBinding:$grantBinding;
            $same=$current!==null&&hash_equals($current->sha256,$saved['binding']->sha256);$state=$finish['state']??'unfinished';$currentSaved=$same&&provider_stackcp_access_recent($saved);$match=false;
            if ($currentSaved) {$observation=new StackCpAccessObservation($saved['binding'],$finish['observed_epoch'],'complete','',$finish['values']);$match=$observation->selectedMatch();}
            // For a whole-user grants receipt owned by another package/client, expose only the stale state.
            $out[$key]=['id'=>$r['id'],'state'=>$same?$state:'context_changed','reason'=>$same?($finish['reason']??''):'','observed_epoch'=>$same?($finish['observed_epoch']??0):0,'current'=>$currentSaved,'matches'=>$match,'empty'=>$same&&$finish!==null&&$state==='complete'&&$finish['values']===[],'count'=>$same&&$finish!==null&&$state==='complete'?count($finish['values']):null];
        }
        if (($out['membership']['current']??false)&&($out['membership']['matches']??false)&&($out['grants']['current']??false)&&($out['grants']['matches']??false)) $out['status']='observed_match';
        elseif ($out['membership']!==null||$out['grants']!==null) $out['status']='not_verified';return $out;
    }
    private function ownerAccessPage(int $page): array {
        provider_stackcp_guard(true);provider_settings_manager();
        $out=['state'=>'account_missing','profile_id'=>null,'packages'=>[],'page'=>$page,'has_more'=>false,'error'=>null];
        try {
            $profile=provider_settings_profile();if (!$profile) return $out;$id=$profile['id'];$out['profile_id']=$id;
            $out['state']='unprepared';if (!$this->accessFamilyReady()) return $out;
            $out['state']='ready';provider_settings_manager();
            $packages=all('SELECT package_id FROM '.table('provider_package_links').' WHERE profile_id=? UNION SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(document_json,\'$.package_id\')) AS UNSIGNED) AS package_id FROM '.table('provider_stackcp_assignments').' WHERE profile_id=? ORDER BY package_id LIMIT 21 OFFSET '.(($page-1)*20),[$id,$id]);
            $out['has_more']=count($packages)>20;
            foreach (array_slice($packages,0,20) as $row) $out['packages'][]=$this->accessProjectionValue($id,(int)$row['package_id']);
        } catch (InvalidArgumentException|RuntimeException $e) {if ($e instanceof PDOException) throw $e;$out['error']=$e->getMessage();}
        return $out;
    }
}
