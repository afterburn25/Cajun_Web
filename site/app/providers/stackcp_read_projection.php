<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Internal synchronous read computation. Instances never escape a fixed entry point.
 * Family reuse lasts only this call under the owning mutex; nothing is cached across
 * calls, requests, transactions or writes. Public mutation APIs still start fresh.
 */
final class ProviderStackCpReadProjection {
    private array $families=[];
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
}
