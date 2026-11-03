<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_changes.php';

/** Closed eligibility denials only. Storage corruption, SQL and engine errors propagate. */
function provider_php_controls_screen_optional(callable $read,array $denials): ?array
{
    try { return $read(); }
    catch (RuntimeException $e) {
        if (get_class($e)!==RuntimeException::class || !in_array($e->getMessage(),$denials,true)) throw $e;
        return null;
    }
}
function provider_php_controls_screen_denials(): array
{
    return [
        'This hosting assignment is unavailable.',
        'This hosting account or assignment needs owner confirmation.',
        'Refresh hosting inventory for this exact assignment first.',
        'Refresh hosting inventory after this assignment or reapproval.',
        'The assigned package is absent from the hosting observation.',
        'PHP changes are not enabled for this client assignment.',
        'The owner must review PHP change permission again.',
        'This hosting assignment changed.',
        'The original PHP request authorization or hosting assignment changed.',
    ];
}

/** Latest intent only, scoped to the exact currently authorized actor and source. */
function provider_php_controls_screen_read(array $context,string $kind): ?array
{
    provider_php_controls_guard(true);
    $latest=provider_php_controls_lifecycle_latest($context['binding']->data['link_id'],$kind);
    if ($latest===null || $latest['read']['document']['context']!==$context['document']) return null;
    $read=$latest['read']['document'];$result=$latest['result']['document']??null;$usable=false;
    if ($result!==null) {
        // Validate structure independently: only the specific wall-clock refusal is optional.
        provider_php_controls_lifecycle_result($result,$read);
        try { provider_php_controls_lifecycle_fresh($read,$result,time());$usable=true; }
        catch (InvalidArgumentException $e) { if ($e->getMessage()!=='Refresh these exact PHP observations.') throw $e; }
    }
    $out=['id'=>$read['id'],'state'=>$result['state']??'pending','reason'=>$result['reason']??'',
        'finished_epoch'=>$result['finished_epoch']??null];
    if ($kind==='current') $out['value']=$result['value']??null;
    else $out['options']=$result['options']??[];
    $out['usable']=$usable;return $out;
}

/** The recorded enabled flag is historical; it does not establish a current grant. */
function provider_php_controls_screen_delegation(string $linkId,?array $context): array
{
    provider_php_controls_guard(true);
    $old=provider_php_controls_permission($linkId);$d=$old['document']??null;
    $enable=provider_php_controls_screen_optional(fn()=>provider_php_controls_delegation_review($linkId,true),
        [...provider_php_controls_screen_denials(),'The primary client must have a current sign-in account.']);
    // Revocation deliberately uses the retained binding, not a fresh inventory read.
    $disable=$old===null?null:provider_php_controls_delegation_review($linkId,false);
    $current=false;
    if (($d['enabled']??false) && $context!==null) {
        $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1',[$d['client_id']]);
        $owner=one('SELECT * FROM '.table('users').' WHERE id=?',[$d['owner_id']]);$owner=$owner?staff_profile($owner):null;
        $current=$client!==null && $client['password_hash']!=='' && $owner!==null && $owner['role']==='owner'
            && $owner['password_hash']!=='' && hash_equals($d['client_auth_version'],$client['auth_version'])
            && hash_equals($d['owner_auth_version'],$owner['auth_version'])
            && provider_php_assignment_matches($old['binding']);
    }
    $project=static fn(?array $r):?array=>$r===null?null:['expected_permission_id'=>$r['permission_id'],'review_sha256'=>$r['review_sha256']];
    return ['enabled'=>$d['enabled']??false,'current'=>$current,'enable'=>$project($enable),'disable'=>$project($disable)];
}

/** Display pagination only: full storage validation still covers all retained history. */
function provider_php_controls_screen_history(string $linkId,?int $clientId,int $page): array
{
    provider_php_controls_guard(true);$visible=0;$skip=($page-1)*10;$history=[];$before=null;
    do {
        $where='link_id=?';$args=[$linkId];
        if ($clientId!==null) { $where.=' AND actor_client_id=?';$args[]=$clientId; }
        if ($before!==null) { $where.=' AND seq<?';$args[]=$before; }
        $rows=all('SELECT id,seq FROM '.table('provider_php_changes').' WHERE '.$where.' ORDER BY seq DESC LIMIT 32',$args);
        foreach ($rows as $row) {
            $before=(int)$row['seq'];$request=provider_php_controls_lifecycle_get('request',$row['id']);
            if ($request===null) throw new RuntimeException('Restore original PHP request history.');
            $access=provider_php_controls_screen_optional(fn()=>provider_php_controls_change_access($request['document'],$clientId),provider_php_controls_screen_denials());
            if ($access===null) continue;
            if ($visible++<$skip) continue;
            if (count($history)===10) return ['history'=>$history,'has_more'=>true];
            $summary=provider_php_controls_summary_locked($row['id']);
            $terminal=provider_php_controls_lifecycle_get('terminal',$row['id'])['document']??null;
            $summary['can_verify']=($terminal['phase']??null)==='attempted' && ($terminal['ledger']??null)!==null
                && $terminal['ledger']['status']!=='claimed'
                && one('SELECT id FROM '.table('provider_php_change_checks')." WHERE request_id=? AND BINARY disposition='observed'",[$row['id']])===null;
            $summary['recovery_sha256']=null;
            if ($clientId===null && $terminal===null) {
                $ledger=provider_php_controls_change_ledger($request['document'],time());
                if ($ledger!==null && $ledger['status']!=='claimed') $summary['recovery_sha256']=provider_php_controls_lifecycle_sha($ledger);
            }
            $history[]=$summary;
        }
    } while (count($rows)===32);
    return ['history'=>$history,'has_more'=>false];
}

/** A GET projection has no schema, credential, provider, permission or lifecycle writes. */
function provider_php_controls_screen_for(?int $clientId,string $linkId,int $page): array
{
    if (db()->inTransaction()) throw new LogicException('PHP settings screens cannot inherit a transaction.');
    provider_php_controls_lifecycle_id($linkId);$page=max(1,min(100000,$page));
    return staff_locked(fn()=>staff_transaction(function()use($clientId,$linkId,$page):array {
        provider_php_controls_guard(true);unset($GLOBALS['cwp_settings']);
        if ($clientId===null) provider_settings_manager(false);
        else provider_php_controls_primary_client($clientId);
        // Do not short-circuit these validators or catch their recovery failures.
        $delegation=provider_php_controls_ready();$lifecycle=provider_php_controls_lifecycle_ready();
        $php=provider_php_ready();$clients=studio_ready();$operations=provider_operations_ready();
        $setup=!$php||!$clients||!$operations?'prerequisites':(!$delegation?'delegation':(!$lifecycle?'lifecycle':'ready'));
        $screen=['link_id'=>$linkId,'setup'=>$setup,'access'=>false,'blocked'=>false,'can_select'=>false,
            'current'=>null,'available'=>null,'delegation'=>null,'history'=>[],'page'=>$page,'has_more'=>false];
        $link=one('SELECT profile_id,package_id,client_id FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
        if ($clientId!==null && ($link===null || (int)$link['client_id']!==$clientId)) throw new RuntimeException('This hosting assignment is unavailable.');
        $context=null;
        if ($delegation && $php && $clients) {
            $context=provider_php_controls_screen_optional(fn()=>$clientId===null
                ?provider_php_controls_owner_context($linkId):provider_php_controls_client_context($clientId,$linkId),provider_php_controls_screen_denials());
            if ($clientId===null) $screen['delegation']=provider_php_controls_screen_delegation($linkId,$context);
        }
        if ($lifecycle && $link!==null) $screen['blocked']=provider_php_controls_lifecycle_pending($link['profile_id'],(int)$link['package_id'])!==null;
        if ($setup!=='ready' || $context===null) return $screen;
        $screen['access']=true;
        $screen['current']=provider_php_controls_screen_read($context,'current');
        $screen['available']=provider_php_controls_screen_read($context,'available');
        if (!$screen['blocked'] && ($screen['current']['usable']??false) && ($screen['available']['usable']??false)) {
            foreach ($screen['available']['options'] as $choice) {
                if ($choice['value']===$screen['current']['value']) continue;
                try { $review=provider_php_controls_change_review($context,$screen['current']['id'],$screen['available']['id'],$choice['value']); }
                catch (InvalidArgumentException $e) {
                    // A wall-clock boundary can pass between projection and admission.
                    if ($e->getMessage()!=='Refresh these exact PHP observations.') throw $e;
                    break;
                }
                $admitted=provider_php_controls_screen_optional(function()use($context,$review):array {
                    provider_php_controls_change_admission($context,$review);return [];
                },['A previous PHP change is unresolved. Check its result before a new approval.','Save new PHP observations before approving another change.']);
                $screen['can_select']=$admitted!==null;break;
            }
        }
        $history=provider_php_controls_screen_history($linkId,$clientId,$page);
        $screen['history']=$history['history'];$screen['has_more']=$history['has_more'];return $screen;
    }));
}
function provider_php_controls_screen_owner(string $linkId,int $page=1): array
{
    return provider_php_controls_screen_for(null,$linkId,$page);
}
function provider_php_controls_screen_client(int $clientId,string $linkId,int $page=1): array
{
    return provider_php_controls_screen_for($clientId,$linkId,$page);
}
