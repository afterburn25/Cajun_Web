<?php
/** Offline contract tests; no database, API, customer mail or live access. */
declare(strict_types=1);
define('CWP_BOOT', true);
require dirname(__DIR__).'/site/app/licenses/software_license_policy.php';

function expect(bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
    echo 'PASS '.$description.PHP_EOL;
}
function rejects(callable $fn, string $description): void {
    try { $fn(); } catch (InvalidArgumentException) { echo 'PASS '.$description.PHP_EOL; return; }
    throw new RuntimeException('FAIL: '.$description);
}
$now = new DateTimeImmutable('2026-10-25 12:00:00', new DateTimeZone('UTC'));
$trial = SoftwareLicensePolicy::issue('trial', 'Test Customer', 'WWW.Example.COM',
    '2026-11-08 12:00:00', false, $now);
expect($trial['domain'] === 'example.com' && $trial['expires_at'] === '2026-11-08 12:00:00',
    'Trial binds normalized domain with finite expiry');
expect(SoftwareLicensePolicy::authorized([
    'type'=>'trial','active'=>true,'domain'=>$trial['domain'],
    'expires_at'=>$trial['expires_at'],'internal_only'=>false], 'www.example.com', $now),
    'Trial authorizes matching www/apex installation');
expect(!SoftwareLicensePolicy::authorized([
    'type'=>'trial','active'=>true,'domain'=>'example.com',
    'expires_at'=>$trial['expires_at'],'internal_only'=>false], 'other.example.com', $now),
    'Trial denies another installation domain');
expect(!SoftwareLicensePolicy::authorized([
    'type'=>'trial','active'=>true,'domain'=>'example.com',
    'expires_at'=>$trial['expires_at'],'internal_only'=>false], 'example.com',
    new DateTimeImmutable('2026-11-08 12:00:00', new DateTimeZone('UTC'))),
    'Trial denies exactly at expiration');
expect(!SoftwareLicensePolicy::authorized([
    'type'=>'trial','active'=>false,'domain'=>'example.com',
    'expires_at'=>$trial['expires_at'],'internal_only'=>false], 'example.com', $now),
    'Deactivated trial stays denied');
$perpetual = SoftwareLicensePolicy::issue('standard','Paid Customer','example.com',null,false,$now);
expect($perpetual['expires_at'] === null &&
    SoftwareLicensePolicy::authorized(['type'=>'standard','active'=>true,'domain'=>'example.com',
      'expires_at'=>null,'internal_only'=>false],'example.com',$now),
    'Purchased standard entitlement is non-expiring');
$master = SoftwareLicensePolicy::issue('master','Internal test owner',null,null,true,$now);
expect($master['internal_only'] &&
    SoftwareLicensePolicy::authorized(['type'=>'master','active'=>true,'domain'=>null,
      'expires_at'=>null,'internal_only'=>true],'some-site.example',$now),
    'Authorized staff master permits arbitrary domain');
expect(!SoftwareLicensePolicy::authorized(['type'=>'master','active'=>true,
    'expires_at'=>null,'internal_only'=>false],'some-site.example',$now),
    'Customer-deliverable master is never authorized');
rejects(fn()=>SoftwareLicensePolicy::issue('master','Buyer',null,null,false,$now),
    'Customer cannot issue master key');
rejects(fn()=>SoftwareLicensePolicy::issue('trial','Buyer','example.com',null,false,$now),
    'Trial cannot be perpetual');
rejects(fn()=>SoftwareLicensePolicy::issue('temporary','Buyer','example.com','2026-10-25 12:00:00',false,$now),
    'Temporary license cannot expire in the past or present');
rejects(fn()=>SoftwareLicensePolicy::issue('standard','Buyer','example.com','2026-11-01 00:00:00',false,$now),
    'Standard paid license cannot silently acquire expiry');
rejects(fn()=>SoftwareLicensePolicy::issue('trial','', 'example.com','2026-11-01 00:00:00',false,$now),
    'Trial requires customer name');
rejects(fn()=>SoftwareLicensePolicy::issue('trial','Buyer', 'bad..site','2026-11-01 00:00:00',false,$now),
    'Malformed domains are refused');
rejects(fn()=>SoftwareLicensePolicy::expiry('2026-02-30 00:00:00'),
    'Impossible calendar dates are refused');
// Regression coverage: never accept a different registered host or a revoked grant.
expect(!SoftwareLicensePolicy::authorized([
    'type'=>'standard','active'=>true,'domain'=>'example.com',
    'expires_at'=>null,'internal_only'=>false], 'example.com.attacker.test', $now),
    'Domain comparison rejects suffix-spoofed hosts');
expect(!SoftwareLicensePolicy::authorized([
    'type'=>'standard','active'=>false,'domain'=>'example.com',
    'expires_at'=>null,'internal_only'=>false], 'example.com', $now),
    'Revoked perpetual license is denied');
rejects(fn()=>SoftwareLicensePolicy::issue('temporary','Buyer','example.com',null,false,$now),
    'Temporary grant always requires expiry');
rejects(fn()=>SoftwareLicensePolicy::issue('trial','Buyer','https://example.com/path',
    '2026-11-01 00:00:00',false,$now),
    'Installation domain cannot contain URL scheme or path');
echo 'Software license issue/authorization contract: PASS'.PHP_EOL;
