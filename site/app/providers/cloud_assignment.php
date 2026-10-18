<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/cloud_assignment_storage.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-cloud-assignment/CloudAssignmentBinding.php';
use CajunWebPro\Foundation\TwentyICloudAssignment\CloudAssignmentBinding;
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventoryCloudServer};

function provider_cloud_assignment_audit(string $action,string $detail,int $actor):array {
    $rows=all('SELECT * FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=? ORDER BY id LIMIT 2',[$action,$detail]);
    if(count($rows)!==1||(int)$rows[0]['user_id']!==$actor)provider_cloud_assignment_error();return provider_cloud_assignment_fields($rows[0],['id','user_id','action','detail','created_at']);
}
function provider_cloud_assignment_start_audit(array $r):array{return provider_cloud_assignment_audit('provider.refresh_started','Refresh '.$r['id'].' profile '.$r['profile_id'].' revision '.$r['profile_revision'],(int)$r['actor_id']);}
function provider_cloud_assignment_finish_audit(array $f,array $r):array{return provider_cloud_assignment_audit('provider.refresh_finished','Refresh '.$f['id'].' '.$r['state'].($r['reason']!==''?' '.$r['reason']:''),(int)$f['actor_id']);}
function provider_cloud_assignment_event_audit(array $e):array{return provider_cloud_assignment_audit('provider.cloud_'.$e['event'],'Cloud link '.$e['link_id'].' profile '.$e['profile_id'].' server '.$e['server_id'].' client '.$e['client_id'].' revision '.$e['revision'],(int)$e['actor_id']);}
function provider_cloud_assignment_profile_context(array $profile):array{return ['environment'=>$profile['environment'],'credential_sha256'=>hash('sha256',$profile['credentials'])];}
function provider_cloud_assignment_owner_auth(int $id):string {
    $row=one('SELECT u.auth_version,p.role,p.enabled FROM '.table('users').' u JOIN '.table('staff_profiles').' p ON p.user_id=u.id WHERE u.id=?',[$id]);
    if(!$row||$row['role']!=='owner'||(int)$row['enabled']!==1)throw new RuntimeException('An active original owner approval is required for this cloud assignment.');return hash('sha256',$row['auth_version']);
}
function provider_cloud_assignment_source(string $id):?array{return one('SELECT * FROM '.table('provider_refreshes').' WHERE id=? AND BINARY operation=?',[$id,InventoryRead::CLOUD_SERVERS_LIST]);}
function provider_cloud_assignment_server(string $readId,int $serverId):array {
    $row=one('SELECT * FROM '.table('provider_observation_cloud').' WHERE refresh_id=? AND server_id=?',[$readId,$serverId]);if(!$row)throw new RuntimeException('The cloud server was not present in this saved observation.');
    try{$server=InventoryCloudServer::fromItem(json_decode($row['metadata'],false,16,JSON_THROW_ON_ERROR));}catch(JsonException|InvalidArgumentException){provider_cloud_assignment_error();}
    if($server->id!==$serverId)provider_cloud_assignment_error();return ['row'=>$row,'server'=>$server,'sha256'=>hash('sha256',provider_cloud_assignment_json(provider_cloud_assignment_fields($row,['id','refresh_id','server_id','metadata','unmapped_count'])))];
}
function provider_cloud_assignment_witness(string $kind,string $sourceId):array {
    $row=provider_cloud_assignment_evidence($kind,$sourceId);if(!$row)provider_cloud_assignment_error();return ['row'=>$row,'document'=>provider_cloud_assignment_decode($row)];
}
/** Verify original immutable source; never requires it to remain the current assignment. */
function provider_cloud_assignment_retained(array $row,array $head):array {
    $d=provider_cloud_assignment_decode($row);$s=$d['source'];$x=$d['extra'];
    if($d['kind']==='start'){
        $f=provider_cloud_assignment_source($d['source_id']);if(!$f||provider_cloud_assignment_start_fields($f)!==$s||$d['profile_id']!==$f['profile_id']||$d['actor_id']!==(int)$f['actor_id']||array_keys($x)!==['audit','credentials','owner_auth']||$x['audit']!==provider_cloud_assignment_start_audit($f)||$d['legacy']!==((int)$f['seq']<=$head['baseline_seq']))provider_cloud_assignment_error();
        if($d['legacy']){if($x['credentials']!==null||$x['owner_auth']!==null)provider_cloud_assignment_error();}
        elseif(!is_array($x['credentials'])||array_keys($x['credentials'])!==['environment','credential_sha256']||$x['credentials']['environment']!=='live'||!is_string($x['credentials']['credential_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/D',$x['credentials']['credential_sha256'])||!is_string($x['owner_auth'])||!preg_match('/\A[a-f0-9]{64}\z/D',$x['owner_auth']))provider_cloud_assignment_error();
    }elseif($d['kind']==='finish'){
        $f=provider_cloud_assignment_source($d['source_id']);$r=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$d['source_id']]);$start=provider_cloud_assignment_witness('start',$d['source_id']);
        if(!$f||!$r||provider_cloud_assignment_result_fields($r)!==$s||$d['profile_id']!==$f['profile_id']||$d['actor_id']!==(int)$f['actor_id']||$d['legacy']!==$start['document']['legacy']||array_keys($x)!==['audit','start_proof','cloud']||$x['audit']!==provider_cloud_assignment_finish_audit($f,$r)||$x['start_proof']!==$start['row']['proof']||$x['cloud']!==provider_cloud_assignment_cloud_digest($f['id'])||!in_array($r['state'],['complete','partial','unavailable','refused','access_changed','credential'],true))provider_cloud_assignment_error();
        if((int)$r['package_count']!==$x['cloud']['count']||(!in_array($r['state'],['complete','partial'],true)&&$x['cloud']['count']!==0))provider_cloud_assignment_error();
    }else{
        $e=one('SELECT * FROM '.table('provider_cloud_link_events').' WHERE id=?',[$d['source_id']]);
        if(!$e||provider_cloud_assignment_event_fields($e)!==$s||$d['profile_id']!==$e['profile_id']||$d['actor_id']!==(int)$e['actor_id']||$d['legacy']!==((int)$e['id']<=$head['baseline_event_id'])||array_keys($x)!==['audit','snapshot','owner_auth','credentials','anchor']||$x['audit']!==provider_cloud_assignment_event_audit($e)||!in_array($e['event'],['link','relink','unlink'],true))provider_cloud_assignment_error();
        if($x['snapshot']!==null){if(!is_array($x['snapshot'])||provider_cloud_assignment_link_fields($x['snapshot'])!==$x['snapshot'])provider_cloud_assignment_error();foreach(['profile_id','server_id','client_id','revision','key_fingerprint','profile_revision','refresh_id'] as $key)if($x['snapshot'][$key]!==$s[$key])provider_cloud_assignment_error();if($x['snapshot']['id']!==$s['link_id'])provider_cloud_assignment_error();}
        if($d['legacy']){if($x['owner_auth']!==null||$x['anchor']!==null||$x['credentials']!==null)provider_cloud_assignment_error();}
        else{
            if(!is_string($x['owner_auth'])||!preg_match('/\A[a-f0-9]{64}\z/D',$x['owner_auth'])||$x['snapshot']===null)provider_cloud_assignment_error();
            if($e['event']==='unlink'){if($x['anchor']!==null)provider_cloud_assignment_error();}
            else{provider_cloud_assignment_anchor($x['anchor']);$anchor=$x['anchor'];if($anchor['source_id']!==$s['refresh_id']||$anchor['profile_id']!==$s['profile_id']||$anchor['server_id']!==(int)$s['server_id']||$anchor['profile_revision']!==(int)$s['profile_revision']||$anchor['key_fingerprint']!==$s['key_fingerprint']||$anchor['credential_sha256']!==($x['credentials']['credential_sha256']??null))provider_cloud_assignment_error();}
        }
        if($x['credentials']!==null&&(!is_array($x['credentials'])||array_keys($x['credentials'])!==['environment','credential_sha256']||$x['credentials']['environment']!=='live'||!is_string($x['credentials']['credential_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/D',$x['credentials']['credential_sha256'])))provider_cloud_assignment_error();
    }
    return $d;
}
/** Bounded-memory full authentication precedes all kind/source/client indexed lookups. */
function provider_cloud_assignment_ready():bool {
    provider_cloud_assignment_guard();$head=provider_cloud_assignment_head();$exists=provider_cloud_assignment_exists();$a=table('audit');
    $audits=(int)query("SELECT COUNT(*) FROM $a WHERE BINARY action IN ('provider.cloud_assignment_prepared','provider.cloud_assignment_evidence')")->fetchColumn();
    if(!$head){if($audits||($exists&&(int)query('SELECT COUNT(*) FROM '.table('provider_cloud_assignment_evidence'))->fetchColumn()))provider_cloud_assignment_error();return false;}
    if(!$exists||!provider_inventory_ready())provider_cloud_assignment_error();provider_cloud_assignment_validate_schema();
    foreach(['audit','settings','provider_refreshes','provider_refresh_results','provider_observation_cloud','provider_cloud_links','provider_cloud_link_events'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')provider_cloud_assignment_error();}
    if($audits!==$head['count']+1||(int)query('SELECT COUNT(*) FROM '.table('provider_cloud_assignment_evidence'))->fetchColumn()!==$head['count'])provider_cloud_assignment_error();
    provider_cloud_assignment_audit('provider.cloud_assignment_prepared',provider_cloud_assignment_preparation_detail($head),$head['prepared_by']);provider_cloud_assignment_common_history();
    $cursor=null;$seen=0;$previous=str_repeat('0',64);$counts=['start'=>0,'finish'=>0,'event'=>0];
    do{$rows=all('SELECT * FROM '.table('provider_cloud_assignment_evidence').($cursor===null?'':' WHERE id>?').' ORDER BY id LIMIT 64',$cursor===null?[]:[$cursor]);foreach($rows as $r){$d=provider_cloud_assignment_retained($r,$head);if($d['id']!==++$seen||$r['previous_sha256']!==$previous)provider_cloud_assignment_error();provider_cloud_assignment_audit('provider.cloud_assignment_evidence','Cloud assignment evidence '.$d['id'].' '.$r['document_sha256'].' '.$r['proof'],$d['actor_id']);$previous=$r['document_sha256'];$cursor=$d['id'];++$counts[$d['kind']];}}while(count($rows)===64);
    if($seen!==$head['count']||$previous!==$head['tail'])provider_cloud_assignment_error();
    $f=table('provider_refreshes');$r=table('provider_refresh_results');$e=table('provider_cloud_link_events');$l=table('provider_cloud_links');
    if($counts['start']!==(int)query("SELECT COUNT(*) FROM $f WHERE BINARY operation='cloudservers.list'")->fetchColumn()||$counts['finish']!==(int)query("SELECT COUNT(*) FROM $f f JOIN $r r ON r.id=f.id WHERE BINARY f.operation='cloudservers.list'")->fetchColumn()||$counts['event']!==(int)query("SELECT COUNT(*) FROM $e")->fetchColumn())provider_cloud_assignment_error();
    if(one('SELECT c.id FROM '.table('provider_observation_cloud')." c LEFT JOIN $f f ON f.id=c.refresh_id LEFT JOIN $r r ON r.id=f.id WHERE f.id IS NULL OR BINARY f.operation<>'cloudservers.list' OR r.id IS NULL OR BINARY r.state NOT IN ('complete','partial') LIMIT 1"))provider_cloud_assignment_error();
    // Both directions cover a removed current row or a removed unlink event.
    if(one("SELECT e.id FROM $e e LEFT JOIN $l l ON l.id=e.link_id WHERE NOT EXISTS (SELECT 1 FROM $e x WHERE BINARY x.link_id=BINARY e.link_id AND x.id>e.id) AND ((BINARY e.event='unlink' AND l.id IS NOT NULL) OR (BINARY e.event<>'unlink' AND l.id IS NULL)) LIMIT 1"))provider_cloud_assignment_error();
    $cursor=null;$links=0;do{$rows=all("SELECT * FROM $l".($cursor===null?'':' WHERE BINARY id>BINARY ?').' ORDER BY BINARY id LIMIT 64',$cursor===null?[]:[$cursor]);foreach($rows as $link){++$links;if(!preg_match('/\A[a-f0-9]{32}\z/D',$link['id']))provider_cloud_assignment_error();provider_cloud_assignment_assignment($link);$cursor=$link['id'];}}while(count($rows)===64);if($links!==(int)query("SELECT COUNT(*) FROM $l")->fetchColumn())provider_cloud_assignment_error();return true;
}
function provider_cloud_assignment_assignment(array $link):array {
    $event=one('SELECT * FROM '.table('provider_cloud_link_events').' WHERE BINARY link_id=? ORDER BY id DESC LIMIT 1',[$link['id']]);if(!$event||$event['event']==='unlink')provider_cloud_assignment_error();$w=provider_cloud_assignment_witness('event',(string)$event['id']);
    if($w['document']['source']!==provider_cloud_assignment_event_fields($event)||$w['document']['extra']['snapshot']!==provider_cloud_assignment_link_fields($link))provider_cloud_assignment_error();return ['link'=>$link,'event'=>$event,'witness'=>$w];
}
/** Guard called inside the existing refresh transaction, before inserting its intent/result. */
function provider_cloud_assignment_before_read():void{provider_cloud_assignment_ready();}
function provider_cloud_assignment_started(array $started,bool $legacy=false):void {
    provider_cloud_assignment_guard();if(!provider_cloud_assignment_head())return;$f=provider_cloud_assignment_source($started['id']);if(!$f)provider_cloud_assignment_error();
    foreach(['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation'] as $key)if((string)$f[$key]!==(string)$started[$key])provider_cloud_assignment_error();
    $profile=$legacy?null:provider_settings_load($f['profile_id']);if($profile&&((int)$profile['revision']!==(int)$f['profile_revision']||(int)$profile['enabled']!==1||$profile['credentials']===''))provider_cloud_assignment_error();
    provider_cloud_assignment_append('start',$f['id'],$f['profile_id'],(int)$f['actor_id'],$legacy,provider_cloud_assignment_start_fields($f),['audit'=>provider_cloud_assignment_start_audit($f),'credentials'=>$profile?provider_cloud_assignment_profile_context($profile):null,'owner_auth'=>$legacy?null:provider_cloud_assignment_owner_auth((int)$f['actor_id'])]);
}
function provider_cloud_assignment_finished(array $started):void {
    provider_cloud_assignment_guard();if(!provider_cloud_assignment_head())return;$f=provider_cloud_assignment_source($started['id']);$r=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$started['id']]);if(!$f||!$r)provider_cloud_assignment_error();$w=provider_cloud_assignment_witness('start',$f['id']);
    provider_cloud_assignment_append('finish',$f['id'],$f['profile_id'],(int)$f['actor_id'],$w['document']['legacy'],provider_cloud_assignment_result_fields($r),['audit'=>provider_cloud_assignment_finish_audit($f,$r),'start_proof'=>$w['row']['proof'],'cloud'=>provider_cloud_assignment_cloud_digest($f['id'])]);
}
function provider_cloud_assignment_record_event(array $event,?array $snapshot,?array $anchor,bool $legacy=false):void {
    // A legacy fingerprint/revision does not establish which sealed credential produced it.
    // Keep that context unknown until a fresh protected read and explicit owner reapproval.
    provider_cloud_assignment_guard();$profile=provider_settings_load($event['profile_id']);$credentials=!$legacy&&$snapshot&&(int)$profile['revision']===(int)$snapshot['profile_revision']&&$profile['credentials']!==''?provider_cloud_assignment_profile_context($profile):null;
    provider_cloud_assignment_append('event',(string)$event['id'],$event['profile_id'],(int)$event['actor_id'],$legacy,provider_cloud_assignment_event_fields($event),['audit'=>provider_cloud_assignment_event_audit($event),'snapshot'=>$snapshot?provider_cloud_assignment_link_fields($snapshot):null,'owner_auth'=>$legacy?null:provider_cloud_assignment_owner_auth((int)$event['actor_id']),'credentials'=>$credentials,'anchor'=>$anchor]);
}
function provider_cloud_assignment_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Cloud assignment preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_inventory_ready())throw new RuntimeException('Prepare cloud inventory first.');if(staff_transaction(fn()=>provider_cloud_assignment_ready()))return;
        if(provider_cloud_assignment_exists())provider_cloud_assignment_validate_schema();else foreach(provider_cloud_assignment_schema(config()['db']['prefix']) as $sql)db()->exec($sql);provider_cloud_assignment_validate_schema();
        staff_transaction(function()use($actor):void{
            provider_cloud_assignment_common_history();if(one('SELECT f.id FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE BINARY f.operation=? AND r.id IS NULL LIMIT 1',[InventoryRead::CLOUD_SERVERS_LIST]))throw new RuntimeException('Finish or restore the unfinished cloud refresh before preparing assignment history.');
            $head=['version'=>1,'preparation_id'=>bin2hex(random_bytes(16)),'prepared_by'=>(int)$actor['id'],'prepared_epoch'=>time(),'baseline_seq'=>(int)(query('SELECT COALESCE(MAX(seq),0) FROM '.table('provider_refreshes').' WHERE BINARY operation=?',[InventoryRead::CLOUD_SERVERS_LIST])->fetchColumn()),'baseline_event_id'=>(int)query('SELECT COALESCE(MAX(id),0) FROM '.table('provider_cloud_link_events'))->fetchColumn(),'count'=>0,'tail'=>str_repeat('0',64)];provider_cloud_assignment_save_head($head);provider_settings_audit((int)$actor['id'],'cloud_assignment_prepared',provider_cloud_assignment_preparation_detail($head));
            $cursor=null;do{$rows=all('SELECT * FROM '.table('provider_refreshes').' WHERE BINARY operation=?'.($cursor===null?'':' AND seq>?').' ORDER BY seq LIMIT 64',$cursor===null?[InventoryRead::CLOUD_SERVERS_LIST]:[InventoryRead::CLOUD_SERVERS_LIST,$cursor]);foreach($rows as $f){provider_cloud_assignment_started($f,true);provider_cloud_assignment_finished($f);$cursor=(int)$f['seq'];}}while(count($rows)===64);
            $cursor=null;do{$rows=all('SELECT * FROM '.table('provider_cloud_link_events').($cursor===null?'':' WHERE id>?').' ORDER BY id LIMIT 64',$cursor===null?[]:[$cursor]);foreach($rows as $e){$later=one('SELECT id FROM '.table('provider_cloud_link_events').' WHERE BINARY link_id=? AND id>? LIMIT 1',[$e['link_id'],$e['id']]);$link=!$later&&$e['event']!=='unlink'?one('SELECT * FROM '.table('provider_cloud_links').' WHERE id=?',[$e['link_id']]):null;provider_cloud_assignment_record_event($e,$link,null,true);$cursor=(int)$e['id'];}}while(count($rows)===64);
            if(!provider_cloud_assignment_ready())provider_cloud_assignment_error();
        });
    });
}
/** Exact original complete read evidence; this does not assert currentness. */
function provider_cloud_assignment_anchor(mixed $a):void {
    if(!is_array($a)||array_keys($a)!==['profile_id','profile_revision','credential_sha256','key_fingerprint','source_id','source_seq','start_proof','finish_proof','server_id','server_sha256'])provider_cloud_assignment_error();
    foreach(['profile_id','source_id','credential_sha256','key_fingerprint','start_proof','finish_proof','server_sha256'] as $key){$length=in_array($key,['profile_id','source_id'],true)?32:64;if(!is_string($a[$key])||!preg_match('/\A[a-f0-9]{'.$length.'}\z/D',$a[$key]))provider_cloud_assignment_error();}
    foreach(['profile_revision','source_seq','server_id'] as $key)if(!is_int($a[$key])||$a[$key]<1||$a[$key]>999999999999999999)provider_cloud_assignment_error();
    $f=provider_cloud_assignment_source((string)$a['source_id']);$r=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$a['source_id']]);$start=provider_cloud_assignment_witness('start',(string)$a['source_id']);$finish=provider_cloud_assignment_witness('finish',(string)$a['source_id']);
    if(!$f||!$r||$start['document']['legacy']||$finish['document']['legacy']||$f['profile_id']!==$a['profile_id']||(int)$f['profile_revision']!==$a['profile_revision']||(int)$f['seq']!==$a['source_seq']||$r['state']!=='complete'||(int)$r['skipped']!==0||$r['reason']!==''||$r['skip_reasons']!=='[]'||$r['coverage']!=='unknown'||$r['scope']!=='twenty-i|'.$f['profile_id'].'|'.InventoryRead::CLOUD_SERVERS_LIST||$r['key_fingerprint']!==$a['key_fingerprint']||($start['document']['extra']['credentials']['credential_sha256']??null)!==$a['credential_sha256']||$start['row']['proof']!==$a['start_proof']||$finish['row']['proof']!==$a['finish_proof']||!is_int($a['server_id'])||$a['server_id']<1||$a['server_id']>999999999999999999)provider_cloud_assignment_error();
    if(provider_cloud_assignment_server($f['id'],$a['server_id'])['sha256']!==$a['server_sha256'])provider_cloud_assignment_error();
}
/** Admission uses latest intent, never the last successful row or wall-clock ordering. */
function provider_cloud_assignment_read(string $profileId,int $revision,string $refreshId,int $serverId):array {
    provider_cloud_assignment_guard();if(!provider_cloud_assignment_ready())throw new RuntimeException('Prepare cloud assignment history first.');
    $profile=provider_settings_load($profileId);
    if((int)$profile['revision']!==$revision||(int)$profile['enabled']!==1||$profile['credentials']==='')throw new RuntimeException('Review this cloud assignment under the current enabled provider settings.');
    $f=one('SELECT * FROM '.table('provider_refreshes').' WHERE BINARY profile_id=? AND BINARY operation=? ORDER BY seq DESC LIMIT 1',[$profileId,InventoryRead::CLOUD_SERVERS_LIST]);
    $detail="CONCAT(BINARY 'Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision)";
    $byAudit=one('SELECT f.id FROM '.table('provider_refreshes').' f JOIN '.table('audit')." a ON a.user_id=f.actor_id AND BINARY a.action='provider.refresh_started' AND BINARY a.detail=BINARY $detail WHERE BINARY f.profile_id=? AND BINARY f.operation=? ORDER BY a.id DESC LIMIT 1",[$profileId,InventoryRead::CLOUD_SERVERS_LIST]);
    if(($f['id']??null)!==($byAudit['id']??null))provider_cloud_assignment_error();
    $r=$f?one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$f['id']]):null;
    if(!$f||$f['id']!==$refreshId||!$r||$r['state']!=='complete'||(int)$f['profile_revision']!==$revision||(int)$f['started_epoch']<time()-900||(int)$f['started_epoch']>time()+30)throw new RuntimeException('Refresh the cloud server list and review the latest complete observation from the last 15 minutes.');
    $start=provider_cloud_assignment_witness('start',$f['id']);$finish=provider_cloud_assignment_witness('finish',$f['id']);if($start['document']['legacy'])throw new RuntimeException('Refresh the cloud server list after preparing assignment history.');
    if($start['document']['extra']['credentials']!==provider_cloud_assignment_profile_context($profile))throw new RuntimeException('The provider credential context changed. Refresh before approving this cloud assignment.');
    $server=provider_cloud_assignment_server($refreshId,$serverId);$anchor=['profile_id'=>$profileId,'profile_revision'=>$revision,'credential_sha256'=>hash('sha256',$profile['credentials']),'key_fingerprint'=>$r['key_fingerprint'],'source_id'=>$refreshId,'source_seq'=>(int)$f['seq'],'start_proof'=>$start['row']['proof'],'finish_proof'=>$finish['row']['proof'],'server_id'=>$serverId,'server_sha256'=>$server['sha256']];provider_cloud_assignment_anchor($anchor);
    return ['profile'=>$profile,'source'=>$f,'result'=>$r,'server'=>$server,'anchor'=>$anchor];
}
/** Internal inspection may reuse a caller-owned transaction, but never an unlocked one. */
function provider_cloud_assignment_inspect(callable $read):mixed {
    if(db()->inTransaction()){provider_cloud_assignment_guard();return $read();}return staff_locked(fn()=>staff_transaction($read));
}
function provider_cloud_assignment_observation(string $profileId,int $revision,string $refreshId,int $serverId):array {
    provider_settings_manager();return provider_cloud_assignment_inspect(function()use($profileId,$revision,$refreshId,$serverId):array{$r=provider_cloud_assignment_read($profileId,$revision,$refreshId,$serverId);return $r['result']+$r['source'];});
}
/** Called after the ordinary link mutation, under the same transaction and mutex. */
function provider_cloud_assignment_event(array $link,string $kind,int $actor,?array $anchor):void {
    provider_cloud_assignment_guard();if(!in_array($kind,['link','relink','unlink'],true))throw new LogicException('Unsupported cloud assignment event.');
    query('INSERT INTO '.table('provider_cloud_link_events').' (link_id,profile_id,server_id,client_id,revision,event,actor_id,recorded_at,key_fingerprint,profile_revision,refresh_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$link['id'],$link['profile_id'],$link['server_id'],$link['client_id'],$link['revision'],$kind,$actor,gmdate('Y-m-d H:i:s'),$link['key_fingerprint'],$link['profile_revision'],$link['refresh_id']]);
    $id=(string)db()->lastInsertId();provider_settings_audit($actor,'cloud_'.$kind,'Cloud link '.$link['id'].' profile '.$link['profile_id'].' server '.$link['server_id'].' client '.$link['client_id'].' revision '.$link['revision']);
    $event=one('SELECT * FROM '.table('provider_cloud_link_events').' WHERE id=?',[$id]);if(!$event)provider_cloud_assignment_error();provider_cloud_assignment_record_event($event,$link,$anchor);
}
function provider_cloud_assignment_link(string $profileId,int $serverId,int $clientId,int $revision,string $refreshId,#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Cloud assignment changes cannot run inside a caller transaction.');
    staff_locked(fn()=>staff_transaction(function()use($profileId,$serverId,$clientId,$revision,$refreshId,$password):void{
        $actor=provider_settings_owner($password);$read=provider_cloud_assignment_read($profileId,$revision,$refreshId,$serverId);
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new InvalidArgumentException('Active client account not found.');
        if(one('SELECT id FROM '.table('provider_cloud_links').' WHERE profile_id=? AND server_id=?',[$profileId,$serverId]))throw new RuntimeException('That cloud server is already linked. Review and unlink it before changing its client.');
        $l=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$profileId,'server_id'=>$serverId,'client_id'=>$clientId,'revision'=>1,'linked_by'=>(int)$actor['id'],'linked_at'=>gmdate('Y-m-d H:i:s'),'key_fingerprint'=>$read['result']['key_fingerprint'],'profile_revision'=>$revision,'refresh_id'=>$refreshId];
        query('INSERT INTO '.table('provider_cloud_links').' (id,profile_id,server_id,client_id,revision,linked_by,linked_at,key_fingerprint,profile_revision,refresh_id) VALUES (?,?,?,?,?,?,?,?,?,?)',array_values($l));provider_cloud_assignment_event($l,'link',(int)$actor['id'],$read['anchor']);provider_cloud_assignment_ready();
    }));
}
function provider_cloud_assignment_relink(string $linkId,int $expected,int $revision,string $refreshId,#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Cloud assignment changes cannot run inside a caller transaction.');
    staff_locked(fn()=>staff_transaction(function()use($linkId,$expected,$revision,$refreshId,$password):void{
        $actor=provider_settings_owner($password);if(!provider_cloud_assignment_ready())throw new RuntimeException('Prepare cloud assignment history first.');$link=provider_cloud_link_current($linkId,$expected);provider_cloud_assignment_assignment($link);
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new RuntimeException('The assigned client account is inactive. Review the account before approving.');
        $read=provider_cloud_assignment_read($link['profile_id'],$revision,$refreshId,(int)$link['server_id']);if($expected>=4294967295)throw new RuntimeException('Cloud assignment revision is exhausted. Unlink and create a new reviewed assignment.');
        $link['revision']=$expected+1;$link['key_fingerprint']=$read['result']['key_fingerprint'];$link['profile_revision']=$revision;$link['refresh_id']=$refreshId;
        query('UPDATE '.table('provider_cloud_links').' SET revision=?,key_fingerprint=?,profile_revision=?,refresh_id=? WHERE id=?',[$link['revision'],$link['key_fingerprint'],$revision,$refreshId,$linkId]);provider_cloud_assignment_event($link,'relink',(int)$actor['id'],$read['anchor']);provider_cloud_assignment_ready();
    }));
}
function provider_cloud_assignment_unlink(string $linkId,int $expected,#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Cloud assignment changes cannot run inside a caller transaction.');
    staff_locked(fn()=>staff_transaction(function()use($linkId,$expected,$password):void{
        $actor=provider_settings_owner($password);if(!provider_cloud_assignment_ready())throw new RuntimeException('Prepare cloud assignment history first.');$link=provider_cloud_link_current($linkId,$expected);provider_cloud_assignment_assignment($link);
        query('DELETE FROM '.table('provider_cloud_links').' WHERE id=?',[$linkId]);provider_cloud_assignment_event($link,'unlink',(int)$actor['id'],null);provider_cloud_assignment_ready();
    }));
}
/** Current control authority; original event/anchor remains immutable after later reads. */
function provider_cloud_assignment_binding(string $linkId,?string $refreshId=null):CloudAssignmentBinding {
    provider_cloud_assignment_guard();if(!provider_cloud_assignment_ready())throw new RuntimeException('Prepare cloud assignment history first.');
    $link=one('SELECT * FROM '.table('provider_cloud_links').' WHERE id=?',[$linkId]);if(!$link)throw new RuntimeException('This cloud assignment is unavailable.');$a=provider_cloud_assignment_assignment($link);$d=$a['witness']['document'];
    if($d['legacy'])throw new RuntimeException('Re-approve this saved cloud assignment after a fresh cloud inventory read.');
    if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new RuntimeException('The assigned client account is inactive.');
    if(!hash_equals($d['extra']['owner_auth'],provider_cloud_assignment_owner_auth($d['actor_id'])))throw new RuntimeException('The original owner authority changed. Re-approve this cloud assignment.');
    $latest=one('SELECT id FROM '.table('provider_refreshes').' WHERE BINARY profile_id=? AND BINARY operation=? ORDER BY seq DESC LIMIT 1',[$link['profile_id'],InventoryRead::CLOUD_SERVERS_LIST]);
    $read=provider_cloud_assignment_read($link['profile_id'],(int)$link['profile_revision'],$refreshId??($latest['id']??''),(int)$link['server_id']);
    if(!hash_equals($link['key_fingerprint'],$read['result']['key_fingerprint'])||$d['extra']['credentials']!==provider_cloud_assignment_profile_context($read['profile']))throw new RuntimeException('The approved cloud account context changed. Re-approve this assignment.');
    $v=$read['anchor'];return new CloudAssignmentBinding(['profile_id'=>$link['profile_id'],'profile_revision'=>(int)$link['profile_revision'],'credential_sha256'=>$v['credential_sha256'],'key_fingerprint'=>$link['key_fingerprint'],'server_id'=>(int)$link['server_id'],'client_id'=>(int)$link['client_id'],'link_id'=>$link['id'],'link_revision'=>(int)$link['revision'],'link_sha256'=>hash('sha256',provider_cloud_assignment_json(provider_cloud_assignment_link_fields($link))),'event_id'=>(int)$a['event']['id'],'event_proof'=>$a['witness']['row']['proof'],'source_id'=>$v['source_id'],'source_seq'=>$v['source_seq'],'start_proof'=>$v['start_proof'],'finish_proof'=>$v['finish_proof'],'server_sha256'=>$v['server_sha256']]);
}
function provider_cloud_assignment_current(string $linkId,int $linkRevision,int $profileRevision,string $refreshId):CloudAssignmentBinding {
    provider_settings_manager();return provider_cloud_assignment_inspect(function()use($linkId,$linkRevision,$profileRevision,$refreshId):CloudAssignmentBinding{$b=provider_cloud_assignment_binding($linkId,$refreshId);if($b->data['link_revision']!==$linkRevision||$b->data['profile_revision']!==$profileRevision)throw new RuntimeException('This cloud assignment changed. Reload and review it before continuing.');return $b;});
}
/** Safe saved projection. Original client/assignment and current credential boundary precede facts. */
function provider_cloud_assignment_project(array $link):array {
    provider_cloud_assignment_guard();$a=provider_cloud_assignment_assignment($link);$d=$a['witness']['document'];$profile=provider_settings_load($link['profile_id']);
    $out=['server_id'=>(int)$link['server_id'],'server'=>null,'context_changed'=>true,'observed_at'=>null,'linked_at'=>$link['linked_at'],'current'=>false,'history_status'=>'assignment_changed'];
    if((int)$profile['enabled']!==1||(int)$profile['revision']!==(int)$link['profile_revision']||$profile['credentials']===''||$d['extra']['credentials']!==provider_cloud_assignment_profile_context($profile))return $out;
    $f=one('SELECT f.*,r.key_fingerprint,r.state FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE BINARY f.profile_id=? AND BINARY f.operation=? AND f.profile_revision=? AND BINARY r.key_fingerprint=? AND BINARY r.state IN (?,?) ORDER BY f.seq DESC LIMIT 1',[$link['profile_id'],InventoryRead::CLOUD_SERVERS_LIST,$link['profile_revision'],$link['key_fingerprint'],'complete','partial']);
    $out['context_changed']=false;$out['history_status']='not_observed';if(!$f)return $out;
    $row=one('SELECT id FROM '.table('provider_observation_cloud').' WHERE refresh_id=? AND server_id=?',[$f['id'],$link['server_id']]);if(!$row)return $out;
    $server=provider_cloud_assignment_server($f['id'],(int)$link['server_id'])['server'];$out['server']=['id'=>$server->id,'name'=>$server->name,'location'=>$server->location,'product_spec'=>$server->productSpec,'configuration'=>$server->configuration];$out['observed_at']=$f['started_at'];$out['history_status']='historical';
    try{provider_cloud_assignment_binding($link['id'],$f['id']);$out['current']=true;$out['history_status']='observed';}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;/* Valid historical sharing survives a stale read or changed original-owner authority. */}
    return $out;
}
function provider_cloud_assignment_client_services(int $clientId):array {
    $client=studio_client();if(!$client||(int)$client['id']!==$clientId||!provider_inventory_ready()||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))return [];
    return provider_cloud_assignment_inspect(function()use($clientId):array{
        $currentClient=studio_client();if(!$currentClient||(int)$currentClient['id']!==$clientId||!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))return [];
        if(!provider_cloud_assignment_ready()){
            // Only existence is consulted; unknown original credentials cannot authorize facts.
            if(one('SELECT id FROM '.table('provider_cloud_links').' WHERE client_id=? LIMIT 1',[$clientId]))throw new RuntimeException('Cloud assignment history requires owner review before client details are available.');
            return [];
        }
        $out=[];foreach(all('SELECT * FROM '.table('provider_cloud_links').' WHERE client_id=? ORDER BY server_id',[$clientId]) as $link)$out[]=provider_cloud_assignment_project($link);return $out;
    });
}
/** Owner page preparation state and admission are local reads with no credential opening. */
function provider_cloud_assignment_view(string $profileId,int $revision,?string $refreshId):array {
    provider_settings_manager();return provider_cloud_assignment_inspect(function()use($profileId,$revision,$refreshId):array{
        $ready=provider_cloud_assignment_ready();$out=['ready'=>$ready,'can_approve'=>false,'reason'=>$ready?'Refresh cloud inventory before approving an assignment.':'Prepare cloud assignment history before making approvals.','current_links'=>[]];if(!$ready)return $out;
        if($refreshId){$row=one('SELECT server_id FROM '.table('provider_observation_cloud').' WHERE refresh_id=? ORDER BY server_id LIMIT 1',[$refreshId]);if($row){try{provider_cloud_assignment_read($profileId,$revision,$refreshId,(int)$row['server_id']);$out['can_approve']=true;$out['reason']='';}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$out['reason']=$e->getMessage();}}}
        foreach(all('SELECT * FROM '.table('provider_cloud_links').' WHERE profile_id=? ORDER BY server_id',[$profileId]) as $link){$projection=provider_cloud_assignment_project($link);$out['current_links'][$link['id']]=['current'=>$projection['current'],'history_status'=>$projection['history_status'],'context_changed'=>$projection['context_changed']];}return $out;
    });
}
