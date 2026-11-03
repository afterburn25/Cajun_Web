<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_authority.php';

/**
 * Internal sealed profile for an already reviewed PHP-control context.
 *
 * The caller holds the owning mutex across its complete operation. This short
 * transaction rechecks the real staff/client identity and the exact assignment;
 * no transaction survives into DNS, credential use or provider transport.
 * Never expose this row to a view. After unsealing, the caller must also compare
 * the secret's fingerprint with the context before any request is sent.
 */
function provider_php_controls_connection(array $context): array
{
    if (db()->inTransaction()) {
        throw new LogicException('PHP connections cannot inherit a transaction.');
    }
    provider_php_controls_guard();
    return staff_transaction(function () use ($context): array {
        $current = provider_php_controls_reauthorize($context);
        $binding = $current['binding']->data;
        $profile = provider_settings_load($binding['profile_id'], true);
        if ($profile['id'] !== $binding['profile_id']
            || $profile['provider_code'] !== 'twenty-i'
            || $profile['environment'] !== 'live'
            || (int) $profile['revision'] !== $binding['profile_revision']
            || (int) $profile['enabled'] !== 1
            || !is_string($profile['credentials']) || $profile['credentials'] === ''
            || !hash_equals($binding['credential_sha256'], hash('sha256', $profile['credentials']))) {
            throw new RuntimeException('The reviewed PHP connection changed. Review it again.');
        }
        return $profile;
    });
}
