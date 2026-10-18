<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
use CajunWebPro\Foundation\TwentyIInventory\InventoryPackage;
use CajunWebPro\Foundation\TwentyIStackCpAccess\StackCpAccessBinding;

function provider_stackcp_access_source_fields():array {
    return ['f'=>['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation','seq'],
        'z'=>['state','reason','scope','coverage','package_count','skipped','skip_reasons','finished_at','elapsed_ms','key_fingerprint'],
        'p'=>['refresh_id','package_id','name','names','enabled','created_at','created_epoch','type_ref','package_type_name'],
        's'=>['id','user_id','action','detail','created_at'],'t'=>['id','user_id','action','detail','created_at']];
}
/** At most 200 original package witnesses in one join; never retain a provider response body. */
function provider_stackcp_access_source_rows(array $pairs):array {
    if (!$pairs||count($pairs)>200) throw new LogicException('StackCP source batch is invalid.');$select=[];
    foreach (provider_stackcp_access_source_fields() as $alias=>$fields) foreach ($fields as $field) $select[]="$alias.$field AS {$alias}_$field";
    $f=table('provider_refreshes');$z=table('provider_refresh_results');$p=table('provider_observation_packages');$a=table('audit');
    $where=[];$args=[];foreach ($pairs as [$id,$package]) {provider_stackcp_hex($id);provider_stackcp_number($package);$where[]='(f.id=? AND p.package_id=?)';$args[]=$id;$args[]=$package;}
    $select[]="(SELECT COUNT(*) FROM $p pc WHERE pc.refresh_id=f.id) AS retained_packages";
    return all('SELECT '.implode(',',$select)." FROM $f f JOIN $z z ON z.id=f.id JOIN $p p ON p.refresh_id=f.id LEFT JOIN $a s ON BINARY s.action='provider.refresh_started' AND BINARY s.detail=BINARY CONCAT('Refresh ',f.id,' profile ',f.profile_id,' revision ',f.profile_revision) LEFT JOIN $a t ON BINARY t.action='provider.refresh_finished' AND BINARY t.detail=BINARY CONCAT('Refresh ',f.id,' complete') WHERE ".implode(' OR ',$where),$args);
}
function provider_stackcp_access_source_value(array $row):array {
    $values=[];foreach (provider_stackcp_access_source_fields() as $alias=>$fields) {foreach ($fields as $field) {if (!array_key_exists($alias.'_'.$field,$row)) throw new RuntimeException('Original StackCP hosting source needs recovery.');$values[$alias][$field]=$row[$alias.'_'.$field];}}
    ['f'=>$f,'z'=>$z,'p'=>$p,'s'=>$start,'t'=>$finish]=$values;
    if (!in_array($f['operation'],[null,InventoryRead::PACKAGES_LIST],true)||$z['state']!=='complete'||$z['reason']!==''||$z['coverage']!=='unknown'||(string)$z['skipped']!=='0'||$z['skip_reasons']!=='[]'||$z['scope']!=='twenty-i|'.$f['profile_id'].'|'.InventoryRead::PACKAGES_LIST||(string)$z['package_count']!==(string)$row['retained_packages']||$start['id']===null||$finish['id']===null||(string)$start['user_id']!==(string)$f['actor_id']||(string)$finish['user_id']!==(string)$f['actor_id']||(int)$finish['id']<=(int)$start['id']) throw new RuntimeException('Original StackCP hosting source needs recovery.');
    try {
        if (!in_array((string)$p['enabled'],['0','1'],true)) throw new InvalidArgumentException();
        $package=InventoryPackage::fromItem((object)['id'=>(int)$p['package_id'],'name'=>$p['name'],'names'=>json_decode($p['names'],true,8,JSON_THROW_ON_ERROR),'enabled'=>(string)$p['enabled']==='1','created'=>$p['created_at'],'typeRef'=>(int)$p['type_ref'],'packageTypeName'=>$p['package_type_name']]);
        if ($package->createdEpoch!==(int)$p['created_epoch']) throw new InvalidArgumentException();
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('Original StackCP package fields need recovery.',0,$e);}
    $hash=[];foreach ($values as $alias=>$value) $hash[$alias]=provider_stackcp_access_hash($value);
    return ['row'=>$f+$z,'package'=>$p,'start'=>$start,'sha256'=>hash('sha256',provider_stackcp_access_json($hash))];
}
function provider_stackcp_access_package_source(string $id,int $package):array {
    $rows=provider_stackcp_access_source_rows([[$id,$package]]);if (count($rows)!==1) throw new RuntimeException('Original StackCP hosting source needs recovery.');return provider_stackcp_access_source_value($rows[0]);
}
/** Validate originals in bounded batches after the entire prerequisite families passed keyed validation. */
function provider_stackcp_access_retained_sources(array $starts):void {
    if (!$starts) return;$pairs=[];foreach ($starts as $start) {$b=$start['binding'];$pairs[$b['source_id'].':'.$b['package_id']]=[$b['source_id'],$b['package_id']];}
    $sources=[];foreach (provider_stackcp_access_source_rows(array_values($pairs)) as $row) {$source=provider_stackcp_access_source_value($row);$key=$source['row']['id'].':'.$source['package']['package_id'];if (isset($sources[$key])) throw new RuntimeException('Original StackCP source audit is ambiguous.');$sources[$key]=$source;}
    $ids=array_column($starts,'id');$marks=implode(',',array_fill(0,count($ids),'?'));$reads=table('provider_stackcp_reads');$assignments=table('provider_stackcp_assignments');$auth=table('provider_stackcp_auth');$results=table('provider_stackcp_observations');
    $rows=all("SELECT r.id,a.document_json AS assignment_json,a.document_sha256 AS assignment_sha256,a.proof AS assignment_proof,k.document_json AS auth_json,k.document_sha256 AS auth_sha256,k.proof AS auth_proof,m.document_json AS member_json,m.document_sha256 AS member_sha256,m.proof AS member_proof,m.ordinal AS member_ordinal,m.id AS member_id,mr.document_json AS member_start_json,mr.document_sha256 AS member_start_sha256 FROM $reads r LEFT JOIN $assignments a ON a.id=r.assignment_id LEFT JOIN $auth k ON k.id=JSON_UNQUOTE(JSON_EXTRACT(r.document_json,'$.binding.auth_id')) LEFT JOIN $results m ON m.id=JSON_UNQUOTE(JSON_EXTRACT(r.document_json,'$.binding.membership_id')) LEFT JOIN $reads mr ON mr.id=m.id WHERE r.id IN ($marks)",$ids);
    $linked=[];foreach ($rows as $row) $linked[$row['id']]=$row;
    foreach ($starts as $start) {
        $b=$start['binding'];$row=$linked[$start['id']]??null;$source=$sources[$b['source_id'].':'.$b['package_id']]??null;
        if (!$row||!$source||$row['assignment_json']===null||$row['auth_json']===null) throw new RuntimeException('Original StackCP access authority needs recovery.');
        $assignment=provider_stackcp_document('assignment',json_decode($row['assignment_json'],true,8,JSON_THROW_ON_ERROR));$key=provider_stackcp_document('auth',json_decode($row['auth_json'],true,8,JSON_THROW_ON_ERROR));
        foreach (['profile_id','package_id','client_id','client_auth_version','link_id','link_sha256','event_id','event_sha256','audit_id','audit_sha256','profile_revision','key_fingerprint','stack_identity','auth_id'] as $field) if ($assignment[$field]!==$b[$field]) throw new RuntimeException('Original StackCP access assignment needs recovery.');
        if ($assignment['state']!=='unverified'||$assignment['id']!==$b['assignment_id']||$assignment['revision']!==$b['assignment_revision']||$assignment['profile_credentials_sha256']!==$b['credential_sha256']||$row['assignment_sha256']!==$b['assignment_sha256']||$row['assignment_proof']!==$b['assignment_proof']||!$key['enabled']||$key['id']!==$b['auth_id']||$key['revision']!==$b['auth_revision']||$key['ciphertext_sha256']!==$b['auth_ciphertext_sha256']||$row['auth_sha256']!==$b['auth_sha256']||$row['auth_proof']!==$b['auth_proof']||$assignment['auth_sha256']!==$row['auth_sha256']) throw new RuntimeException('Original StackCP access configuration needs recovery.');
        $f=$source['row'];if ($f['profile_id']!==$b['profile_id']||(int)$f['profile_revision']!==$b['profile_revision']||(int)$f['seq']!==$b['source_seq']||$f['key_fingerprint']!==$b['key_fingerprint']||$source['sha256']!==$b['source_sha256']||(int)$source['start']['id']<=$b['audit_id']||(int)$f['seq']>=(int)$start['common']['seq']) throw new RuntimeException('Original StackCP hosting authority needs recovery.');
        if ($b['operation']===StackCpAccessBinding::GRANTS) {
            if ($row['member_id']!==$b['membership_id']||$row['member_sha256']!==$b['membership_sha256']||$row['member_start_json']===null) throw new RuntimeException('Original StackCP membership evidence needs recovery.');
            $member=provider_stackcp_access_row('finished',['id'=>$row['member_id'],'ordinal'=>$row['member_ordinal'],'document_json'=>$row['member_json'],'document_sha256'=>$row['member_sha256'],'proof'=>$row['member_proof']]);
            $ms=provider_stackcp_access_document('started',json_decode($row['member_start_json'],true,16,JSON_THROW_ON_ERROR));provider_stackcp_access_finish_check($ms,$member);
            if ($member['read_sha256']!==$row['member_start_sha256']||$member['state']!=='complete'||!in_array($b['stack_identity'],$member['values'],true)||(int)$ms['common']['seq']!==$b['membership_seq']||$b['membership_seq']>=(int)$start['common']['seq']) throw new RuntimeException('Original StackCP membership evidence needs recovery.');
            $expected=$b;$expected['operation']=StackCpAccessBinding::MEMBERSHIP;$expected['membership_id']='';$expected['membership_seq']=0;$expected['membership_sha256']='';if ($ms['binding']!==$expected) throw new RuntimeException('Original StackCP membership context needs recovery.');
        }
    }
}
