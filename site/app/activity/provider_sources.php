<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** A broken provider history is unavailable, never a successful empty inbox. */
class ActivityProviderUnavailable extends RuntimeException {}

function activity_provider_key(string $key): bool {
    return preg_match('/\Asubdomain-(?:request|terminal|check):[a-f0-9]{32}\z/', $key) === 1;
}

/** Share the existing account mutex and transaction; do not nest transactions. */
function activity_snapshot(callable $work): mixed {
    if (db()->inTransaction()) {
        $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
        if ((int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$key])->fetchColumn()!==1) {
            throw new LogicException('Activity snapshot requires the owning account mutex.');
        }
        return $work();
    }
    return staff_locked(fn()=>staff_transaction($work));
}

/** Reject anonymous polling before acquiring the shared mutex; authenticate inside it. */
function activity_authenticated_snapshot(string $audience,callable $work): mixed {
    if (!in_array($audience,['admin','client'],true)) throw new InvalidArgumentException('Invalid activity audience.');
    if (empty($_SESSION[$audience==='admin'?'uid':'client_id'])) throw new RuntimeException('Sign in to view your activity.');
    return activity_snapshot(fn()=>$work(activity_actor($audience)));
}

/** Caller holds one snapshot. The provider module owns source integrity and authority. */
function activity_provider_rows_locked(string $audience,array $actor): array {
    require_once CWP_CODE_ROOT.'/app/providers/subdomain_activity.php';
    try {
        $snapshot=provider_subdomain_activity_snapshot_locked($audience,$actor);
    } catch (PDOException $e) {
        throw $e;
    } catch (RuntimeException $e) {
        throw new ActivityProviderUnavailable('Subdomain updates are temporarily unavailable.',0,$e);
    }
    if (!in_array($snapshot['status'],['ready','absent','excluded'],true)) throw new LogicException('Invalid activity source state.');
    $rows=[];
    foreach ($snapshot['rows'] as $row) {
        $key=$row['event_key'];
        if (!activity_provider_key($key)||isset($rows[$key])) throw new LogicException('Invalid or repeated provider activity key.');
        $row['read_at']=null;
        $rows[$key]=$row;
    }
    if ($rows===[]) return [];
    foreach (all('SELECT event_key,read_at FROM '.table('activity_reads').' WHERE actor_type=? AND actor_id=? AND LEFT(event_key,10)=?',[ $actor['type'],$actor['id'],'subdomain-']) as $read) {
        if (isset($rows[$read['event_key']])) $rows[$read['event_key']]['read_at']=$read['read_at'];
    }
    return $rows;
}

function activity_project_source(array $actor,string $key): array {
    [$sql,$args]=activity_projection($actor);
    $row=one('SELECT * FROM ('.$sql.') events WHERE event_key=?',[...$args,$key]);
    if (!$row) throw new InvalidArgumentException('Activity item unavailable.');
    return $row;
}

function activity_project_count(array $actor): int {
    [$sql,$args]=activity_projection($actor);
    return count(all('SELECT e.event_key FROM ('.$sql.') e LEFT JOIN '.table('activity_reads').' r ON r.actor_type=? AND r.actor_id=? AND r.event_key=e.event_key WHERE r.id IS NULL LIMIT 100',[...$args,$actor['type'],$actor['id']]));
}

/** Apply the same strict (UTC time, ASCII key) cursor as the project SQL projection. */
function activity_merge_rows(array $projectRows,array $providerRows,bool $unread,?array $cursor): array {
    $rows=$projectRows;
    $after=function(array $row)use($cursor): bool {
        if ($cursor===null) return true;
        $time=strcmp($row['occurred_at'],$cursor['occurred_at']);
        if ($time!==0) return $time<0;
        $seq=($row['sort_sequence']??0)<=>($cursor['sort_sequence']??0);
        if ($seq!==0) return $seq<0;
        return strcmp($row['event_key'],$cursor['event_key'])<0;
    };
    foreach ($providerRows as $row) {
        if ($unread&&$row['read_at']!==null) continue;
        if (!$after($row)) continue;
        $rows[]=$row;
    }
    usort($rows,static fn($a,$b)=>strcmp($b['occurred_at'],$a['occurred_at'])?:($b['sort_sequence']??0)<=>($a['sort_sequence']??0)?:strcmp($b['event_key'],$a['event_key']));
    $more=count($rows)>30;
    $rows=array_slice($rows,0,30);
    return ['rows'=>$rows,'next'=>$more?$rows[count($rows)-1]['event_key']:''];
}
