<?php
/** Offline signed-event contract tests: no merchant credentials or gateway traffic. */
declare(strict_types=1);
define('CWP_BOOT', true);
require dirname(__DIR__).'/site/app/billing/authorize_net_webhooks.php';

function ensure(bool $ok, string $case): void {
    if (!$ok) throw new RuntimeException('FAIL '.$case);
}
$key = str_repeat('ab', 64);
$event = json_encode([
    'notificationId' => 'evt-test',
    'eventType' => 'net.authorize.payment.authcapture.created',
    'payload' => ['id' => '123456789']
], JSON_THROW_ON_ERROR);
$signature = 'sha512='.strtoupper(hash_hmac('sha512', $event, hex2bin($key)));
ensure(software_anet_signature_matches($event, $signature, $key), 'valid signature');
ensure(!software_anet_signature_matches($event.' ', $signature, $key), 'changed payload');
ensure(!software_anet_signature_matches($event, 'sha256='.substr($signature, 7), $key), 'wrong algorithm');
ensure(!software_anet_signature_matches($event, $signature, 'bad-key'), 'malformed key');
ensure(!software_anet_signature_matches(str_repeat('x', 131073), $signature, $key), 'oversized body');
$details = software_anet_event_details($event);
ensure($details['transaction_id'] === '123456789', 'transaction extracted');
ensure($details['event_id'] === 'evt-test', 'event id extracted');
try {
    software_anet_event_details('{"payload":{}}');
    throw new RuntimeException('Malformed event unexpectedly accepted');
} catch (InvalidArgumentException $expected) {
    // Expected refusal of missing event type.
}
echo 'Signed webhook contract: PASS'.PHP_EOL;
