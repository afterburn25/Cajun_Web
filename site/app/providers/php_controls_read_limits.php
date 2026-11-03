<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_lifecycle_storage.php';

/** Admission only, under the same mutex/transaction as the new durable intent. */
function provider_php_controls_read_limit(string $profileId,array $actor,int $now): void
{
    provider_php_controls_guard(true);
    provider_php_controls_lifecycle_id($profileId);
    $actor=provider_php_controls_lifecycle_actor($actor);
    provider_php_controls_lifecycle_number($now);
    foreach (['provider_php_control_reads','provider_refreshes','provider_checks'] as $name) {
        if (one('SELECT id FROM '.table($name).' WHERE profile_id=? AND started_epoch>? LIMIT 1',[$profileId,$now-30])) {
            throw new RuntimeException('Wait 30 seconds between reads or checks of this profile.');
        }
    }
    $column=$actor['kind']==='staff'?'actor_staff_id':'actor_client_id';
    $count=(int)query('SELECT COUNT(*) FROM '.table('provider_php_control_reads').' WHERE '.$column.'=? AND started_epoch>?',[$actor['id'],$now-3600])->fetchColumn();
    if ($actor['kind']==='staff') {
        foreach (['provider_refreshes','provider_checks'] as $name) {
            $count+=(int)query('SELECT COUNT(*) FROM '.table($name).' WHERE actor_id=? AND started_epoch>?',[$actor['id'],$now-3600])->fetchColumn();
        }
    }
    if ($count>=20) throw new RuntimeException('Provider read limit reached. Try again later.');
}

/**
 * Root-owned legacy admission hook, after its existing limits and before INSERT.
 * Absent clean lifecycle history preserves the old separate buckets. Established
 * malformed history is an error from ready(), never a function_exists bypass.
 */
function provider_php_controls_old_read_limit(string $bucket,string $profileId,int $actorId,int $now): void
{
    provider_php_controls_guard(true);
    if (!in_array($bucket,['refresh','check'],true)) throw new LogicException('Invalid existing provider read bucket.');
    provider_php_controls_lifecycle_id($profileId);
    provider_php_controls_lifecycle_number($actorId,1);
    provider_php_controls_lifecycle_number($now);
    if (!provider_php_controls_lifecycle_ready()) return;
    if (one('SELECT id FROM '.table('provider_php_control_reads').' WHERE profile_id=? AND started_epoch>? LIMIT 1',[$profileId,$now-30])) {
        throw new RuntimeException('Wait 30 seconds after the PHP control read.');
    }
    $old=$bucket==='refresh'?'provider_refreshes':'provider_checks';
    $count=(int)query('SELECT COUNT(*) FROM '.table($old).' WHERE actor_id=? AND started_epoch>?',[$actorId,$now-3600])->fetchColumn();
    $count+=(int)query('SELECT COUNT(*) FROM '.table('provider_php_control_reads').' WHERE actor_staff_id=? AND started_epoch>?',[$actorId,$now-3600])->fetchColumn();
    if ($count>=20) throw new RuntimeException('Provider read limit reached. Try again later.');
}
