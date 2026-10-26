<?php
/** Last successful validation is an observed check, not proof an app is running. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function software_usage_record(int $licenseId,string $domain):void{
    if ($licenseId<1) return;
    if (!software_commerce_ready()) return;
    $canonical=license_canonical_domain($domain);
    // Existing request-level limiter still applies; failed telemetry must not
    // turn an otherwise-authorized customer's installation offline.
    try {
        query('INSERT INTO '.table('software_usage').' (license_id,domain,first_seen,last_seen,check_count) VALUES (?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1) ON DUPLICATE KEY UPDATE check_count=IF(last_seen < UTC_TIMESTAMP() - INTERVAL 60 SECOND,LEAST(check_count+1,4294967295),check_count),last_seen=IF(last_seen < UTC_TIMESTAMP() - INTERVAL 60 SECOND,UTC_TIMESTAMP(),last_seen)',
            [$licenseId,$canonical]);
        // Retain active-installation observations, but purge old observations
        // for domains that have been deactivated for at least 180 days.
        query('DELETE u FROM '.table('software_usage').' u LEFT JOIN '.table('license_domains').' d ON d.license_id=u.license_id AND d.domain=u.domain AND d.is_active=1 WHERE u.license_id=? AND d.id IS NULL AND u.last_seen < UTC_TIMESTAMP() - INTERVAL 180 DAY',
            [$licenseId]);
    } catch (Throwable $error) {
        report_error($error);
    }
}
function software_usage_for_customer(int $licenseId,int $clientId):array{
    if(!software_commerce_ready())return [];
    if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=? AND customer_id=? AND is_master=0',[$licenseId,$clientId]))return [];
    return all('SELECT domain,first_seen,last_seen,check_count FROM '.table('software_usage').' WHERE license_id=? ORDER BY last_seen DESC LIMIT 20',[$licenseId]);
}
