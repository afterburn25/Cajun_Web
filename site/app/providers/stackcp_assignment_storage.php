<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/stackcp_auth_storage.php';
function provider_stackcp_assignment_prepare(#[SensitiveParameter] string $password): void {provider_stackcp_prepare_family('assignment',$password);}
function provider_stackcp_record_hash(array $row): string {return hash('sha256',provider_stackcp_json(array_map(static fn($value)=>$value===null?null:(string)$value,$row)));}
/** Canonical documents have already been checked globally; join their original immutable sources in bounded pages. */
function provider_stackcp_assignment_sources(ProviderStackCpAuthVault $vault): void {
    provider_stackcp_guard();$after=0;$table=table('provider_stackcp_assignments');$events=table('provider_package_link_events');$audit=table('audit');$auth=table('provider_stackcp_auth');
    $eventFields=['id','profile_id','package_id','client_id','event','actor_id','recorded_at'];$auditFields=['id','user_id','action','detail','created_at'];
    $select=[];foreach ($eventFields as $field) $select[]='e.'.$field.' AS ev_'.$field;foreach ($auditFields as $field) $select[]='a.'.$field.' AS au_'.$field;
    do {
        $rows=all('SELECT r.ordinal,r.document_json,k.document_json AS auth_json,k.document_sha256 AS auth_sha256,'.implode(',',$select)." FROM $table r LEFT JOIN $auth k ON k.id=JSON_UNQUOTE(JSON_EXTRACT(r.document_json,'$.auth_id')) LEFT JOIN $events e ON e.id=JSON_UNQUOTE(JSON_EXTRACT(r.document_json,'$.event_id')) LEFT JOIN $audit a ON a.id=JSON_UNQUOTE(JSON_EXTRACT(r.document_json,'$.audit_id')) WHERE r.ordinal>? ORDER BY r.ordinal LIMIT 200",[$after]);
        foreach ($rows as $r) {
            $d=provider_stackcp_document('assignment',json_decode($r['document_json'],true,8,JSON_THROW_ON_ERROR));$e=[];$a=[];
            foreach ($eventFields as $field) $e[$field]=$r['ev_'.$field];foreach ($auditFields as $field) $a[$field]=$r['au_'.$field];
            if ($r['auth_json']===null || $e['id']===null || $a['id']===null) throw new RuntimeException('Original StackCP assignment sources need recovery.');
            $authDocument=provider_stackcp_document('auth',json_decode($r['auth_json'],true,8,JSON_THROW_ON_ERROR));
            if (!$authDocument['enabled'] || $authDocument['id']!==$d['auth_id'] || $authDocument['profile_id']!==$d['profile_id'] || $authDocument['profile_revision']!==$d['profile_revision'] || !hash_equals($authDocument['profile_credentials_sha256'],$d['profile_credentials_sha256']) || !hash_equals($r['auth_sha256'],$d['auth_sha256']) || $e['profile_id']!==$d['profile_id'] || (int)$e['package_id']!==$d['package_id'] || (int)$e['client_id']!==$d['client_id'] || !in_array($e['event'],['link','relink'],true) || !hash_equals($d['event_sha256'],provider_stackcp_record_hash($e)) || !hash_equals($d['audit_sha256'],provider_stackcp_record_hash($a))) throw new RuntimeException('Original StackCP assignment sources need recovery.');
            $after=(int)$r['ordinal'];
        }
    } while (count($rows)===200);
}
