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
ensure(software_anet_subscription_event_id(['entity'=>'subscription','resource_id'=>'1'])==='1',
    'short subscription identity accepted');
ensure(software_anet_subscription_event_id(['entity'=>'subscription','resource_id'=>str_repeat('9',25)])===str_repeat('9',25),
    'maximum subscription identity accepted');
foreach([
    ['entity'=>'transaction','resource_id'=>'123'],
    ['entity'=>'subscription','resource_id'=>123],
    ['entity'=>'subscription','resource_id'=>true],
    ['entity'=>'subscription','resource_id'=>''],
    ['entity'=>'subscription','resource_id'=>'12x'],
    ['entity'=>'subscription','resource_id'=>str_repeat('9',26)],
    ['entity'=>'subscription'],
] as $invalid){
    try{
        software_anet_subscription_event_id($invalid);
        throw new RuntimeException('Malformed subscription identity unexpectedly accepted');
    }catch(InvalidArgumentException $expected){
        // Every malformed production/sandbox subscription event stops before lifecycle routing.
    }
}
foreach([123,true] as $rawId){
    $malformed=json_encode([
        'notificationId'=>'evt-malformed-subscription',
        'eventType'=>'net.authorize.customer.subscription.updated',
        'payload'=>['entityName'=>'subscription','id'=>$rawId],
    ],JSON_THROW_ON_ERROR);
    $malformedSignature='sha512='.strtoupper(hash_hmac('sha512',$malformed,hex2bin($key)));
    ensure(software_anet_signature_matches($malformed,$malformedSignature,$key),
        'signed malformed subscription event reaches parser');
    try{
        software_anet_event_details($malformed);
        throw new RuntimeException('Non-string signed subscription identity unexpectedly accepted');
    }catch(InvalidArgumentException $expected){
        // Authenticity does not make a malformed provider identity routable.
    }
}
try {
    software_anet_event_details('{"payload":{}}');
    throw new RuntimeException('Malformed event unexpectedly accepted');
} catch (InvalidArgumentException $expected) {
    // Expected refusal of missing event type.
}
try {
    software_anet_event_details('{"payload":');
    throw new RuntimeException('Malformed JSON unexpectedly accepted');
} catch (InvalidArgumentException $expected) {
    // Permanent malformed JSON is classified as invalid, not transient gateway failure.
}
echo 'Signed webhook contract: PASS'.PHP_EOL;
