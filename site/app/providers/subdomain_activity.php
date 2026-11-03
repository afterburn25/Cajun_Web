<?php
/** Read-only activity from authenticated subdomain request and observation history. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/subdomain_requests.php';

/** Closed presentation data: no map paths, notes, proofs, credentials or provider messages. */
function provider_subdomain_activity_row(string $family,string $sourceId,string $kind,array $request,int $epoch): array {
    $titles=[
        'subdomain_requested'=>'A client requested a subdomain. Review the request before deciding.',
        'subdomain_withdrawn'=>'The client withdrew this subdomain request.',
        'subdomain_declined'=>'The studio declined your subdomain request. Open it to read the reason.',
        'subdomain_reviewed'=>'The studio reviewed your request. The provider result is not confirmed.',
        'subdomain_not_sent'=>'This request was not sent to the hosting provider.',
        'subdomain_uncertain'=>'The provider request may have been sent. A separate hosting check is needed.',
        'subdomain_observed'=>'The requested hostname was observed in a separate hosting check. This does not confirm DNS or website readiness.',
        'subdomain_different'=>'The requested hostname was not found in this hosting check. The request remains unresolved.',
        'subdomain_unavailable'=>'The hosting check was unavailable. The request remains unresolved.',
    ];
    $families=[
        'request'=>['subdomain_requested','subdomain_withdrawn','subdomain_declined','subdomain_reviewed'],
        'terminal'=>['subdomain_not_sent','subdomain_uncertain'],
        'check'=>['subdomain_observed','subdomain_different','subdomain_unavailable'],
    ];
    if (!isset($families[$family])||!in_array($kind,$families[$family],true)||$epoch<0||$epoch>2147483647) throw new LogicException('Invalid subdomain activity source.');
    provider_subdomain_requests_id($sourceId);
    provider_subdomain_requests_id($request['id']);
    if (!is_int($request['client_id'])||$request['client_id']<1||!is_string($request['payload']['subdomain'])
        ||!\CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsObservation::validName($request['payload']['subdomain'])) throw new LogicException('Invalid subdomain activity subject.');
    return [
        'event_key'=>'subdomain-'.$family.':'.$sourceId,
        'kind'=>$kind,'source_id'=>$sourceId,'target_id'=>$request['id'],'job_id'=>null,
        'client_id'=>$request['client_id'],'project_title'=>$request['payload']['subdomain'],
        'item_title'=>$titles[$kind],'occurred_at'=>gmdate('Y-m-d H:i:s',$epoch),
    ];
}

/**
 * The caller owns the staff mutex and SQL snapshot. Never starts a transaction,
 * prepares storage, resolves credentials, calls a provider, or writes read state.
 * Existing full-history validators remain authoritative, including reciprocal v2
 * owner origins. Their internal graph revalidation is deliberately not bypassed.
 */
function provider_subdomain_activity_snapshot_locked(string $audience,array $activityActor): array {
    provider_subdomain_requests_guard(true);
    if (!in_array($audience,['admin','client'],true)||($activityActor['type']??null)!==$audience
        ||!is_int($activityActor['id']??null)||$activityActor['id']<1||!is_bool($activityActor['provider_excluded']??null)) throw new LogicException('Invalid activity authority.');
    $excluded=['status'=>'excluded','rows'=>[]];
    // The activity layer captures presence before any client-session normalization.
    if ($activityActor['provider_excluded']||array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)
        ||support_current()||array_key_exists('support',$_GET)) return $excluded;
    if ($audience==='admin') {
        $owner=admin_user();
        if (!$owner||(int)$owner['id']!==$activityActor['id']||!staff_ready()||$owner['role']!=='owner'
            ||(int)$owner['enabled']!==1||!staff_can('staff.manage',$owner)) return $excluded;
    } else {
        // Repeat the actual direct-client check; the caller's numeric ID is no grant.
        provider_subdomain_requests_actor($activityActor['id']);
    }

    $events=provider_subdomain_requests_events();
    // Even clean intake absence cannot hide surviving orphaned owner v2 evidence.
    $ownerReady=provider_subdomain_add_ready();
    if ($events===null) return ['status'=>'absent','rows'=>[]];
    if (!$ownerReady) throw new RuntimeException('Subdomain activity history needs recovery.');
    $graph=provider_subdomain_requests_project($events);
    $rows=[];
    foreach ($graph['requests'] as $r) {
        $request=$r['request'];
        if ($audience==='client') {
            if ($request['client_id']!==$activityActor['id']) continue;
            try { provider_subdomain_requests_current($r); }
            catch (SubdomainRequestUnavailable) { continue; }
        }
        foreach ([$request,...$r['events']] as $event) {
            $kind=match($event['kind']) {
                'request'=>$audience==='admin'?'subdomain_requested':null,
                'cancel'=>$audience==='admin'?'subdomain_withdrawn':null,
                'decline'=>$audience==='client'?'subdomain_declined':null,
                'dispatch'=>$audience==='client'?'subdomain_reviewed':null,
                default=>throw new LogicException('Invalid authenticated subdomain event.'),
            };
            if ($kind!==null) $rows[]=provider_subdomain_activity_row('request',$event['id'],$kind,$request,$event['recorded_epoch']);
        }
        if ($audience!=='client'||$r['state']!=='dispatched') continue;
        $dispatch=$r['events'][array_key_last($r['events'])];
        // Parse once per visible request; checks and terminal reuse this exact owner record.
        $ownerRequest=provider_subdomain_add_request($dispatch['payload']['owner_request_id']);
        $terminal=provider_subdomain_add_terminal($ownerRequest);
        if ($terminal!==null) {
            $kind=match($terminal['document']['phase']) {
                'not_sent'=>'subdomain_not_sent','attempted'=>'subdomain_uncertain',
                default=>throw new RuntimeException('Subdomain activity completion needs recovery.'),
            };
            $rows[]=provider_subdomain_activity_row('terminal',$ownerRequest['row']['id'],$kind,$request,$terminal['document']['ended_epoch']);
        }
        // No synthetic "interrupted" timestamp/event: the durable review remains visible.
        foreach (all('SELECT * FROM '.table('provider_subdomain_add_checks').' WHERE request_id=? ORDER BY BINARY id',[$ownerRequest['row']['id']]) as $check) {
            $document=provider_subdomain_add_check_document($check,$ownerRequest);
            $kind=match($document['disposition']) {
                'observed'=>'subdomain_observed','different'=>'subdomain_different','unavailable'=>'subdomain_unavailable',
                default=>throw new RuntimeException('Subdomain activity readback needs recovery.'),
            };
            $rows[]=provider_subdomain_activity_row('check',$check['id'],$kind,$request,$document['recorded_epoch']);
        }
    }
    $seen=[];
    foreach ($rows as $row) {
        if (isset($seen[$row['event_key']])) throw new RuntimeException('Subdomain activity identity needs recovery.');
        $seen[$row['event_key']]=true;
    }
    usort($rows,static fn($a,$b)=>strcmp($b['occurred_at'],$a['occurred_at'])?:strcmp($b['event_key'],$a['event_key']));
    return ['status'=>'ready','rows'=>$rows];
}
