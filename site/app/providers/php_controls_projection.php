<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_lifecycle_storage.php';

/** Local history only: an old shared observation cannot become current again after a change. */
function provider_php_controls_observation_current(string $linkId,int $readStarted): bool
{
    provider_php_controls_guard();
    provider_php_controls_lifecycle_id($linkId);
    provider_php_controls_lifecycle_number($readStarted);
    if (!provider_php_controls_lifecycle_ready()) return true;
    $link=one('SELECT profile_id,package_id FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
    if ($link===null) return false;
    if (provider_php_controls_lifecycle_pending($link['profile_id'],(int)$link['package_id'])!==null) return false;
    $last=one('SELECT id FROM '.table('provider_php_changes').' WHERE profile_id=? AND package_id=? ORDER BY seq DESC LIMIT 1',[$link['profile_id'],(int)$link['package_id']]);
    if ($last===null) return true;
    $terminal=provider_php_controls_lifecycle_get('terminal',$last['id']);
    // Same-second reads remain historical: timestamp precision cannot prove ordering.
    return $terminal!==null && $readStarted>$terminal['document']['recorded_epoch'];
}
