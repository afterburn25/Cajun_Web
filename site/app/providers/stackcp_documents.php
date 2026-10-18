<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function provider_stackcp_json(array $value): string { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
function provider_stackcp_hex(mixed $value, int $length=32): string {
    if (!is_string($value) || preg_match('/\A[a-f0-9]{'.$length.'}\z/', $value)!==1) throw new InvalidArgumentException('Invalid StackCP review identity.');
    return $value;
}
function provider_stackcp_number(mixed $value, int $minimum=1): int {
    if (!is_int($value) || $value<$minimum || $value>2147483647) throw new InvalidArgumentException('Invalid StackCP review number.');
    return $value;
}
function provider_stackcp_identity(mixed $value): string {
    if (!is_string($value) || preg_match('/\Astack-user:([1-9][0-9]{0,9})\z/', $value, $match)!==1 || (int)$match[1]>2147483647) throw new InvalidArgumentException('Enter an allocated Stack-user identity in the supported local format.');
    return $value;
}
/** Strict reconstruction detaches references and never grants provider authority. */
function provider_stackcp_document(string $kind, array $input): array {
    $common=['version','kind','id','ordinal','scope_id','profile_id','revision','actor_id','actor_auth_version','recorded_epoch','previous_sha256'];
    $extra=match($kind) {
        'auth'=>['profile_revision','profile_credentials_sha256','enabled','ciphertext_sha256'],
        'assignment'=>['link_id','package_id','client_id','client_auth_version','state','stack_identity','profile_revision','profile_credentials_sha256','key_fingerprint','link_sha256','event_id','event_sha256','audit_id','audit_sha256','auth_id','auth_sha256'],
        default=>throw new LogicException('Unknown StackCP document family.'),
    };
    if (array_keys($input)!==[...$common,...$extra] || $input['version']!==1 || $input['kind']!==$kind) throw new InvalidArgumentException('Invalid StackCP review document.');
    $out=['version'=>1,'kind'=>$kind];
    foreach (array_slice($common,2) as $key) $out[$key]=match($key) {
        'id','profile_id','actor_auth_version'=>provider_stackcp_hex($input[$key]),
        'previous_sha256'=>provider_stackcp_hex($input[$key],64),
        'scope_id'=>is_string($input[$key])?$input[$key]:throw new InvalidArgumentException('Invalid StackCP scope.'),
        default=>provider_stackcp_number($input[$key]),
    };
    foreach ($extra as $key) $out[$key]=match($key) {
        'enabled'=>is_bool($input[$key])?$input[$key]:throw new InvalidArgumentException('Invalid StackCP configuration state.'),
        'state'=>in_array($input[$key],['unverified','revoked'],true)?$input[$key]:throw new InvalidArgumentException('Invalid StackCP assignment state.'),
        'stack_identity'=>provider_stackcp_identity($input[$key]),
        'link_id','client_auth_version','auth_id'=>provider_stackcp_hex($input[$key]),
        'profile_credentials_sha256','ciphertext_sha256','key_fingerprint','link_sha256','event_sha256','audit_sha256','auth_sha256'=>provider_stackcp_hex($input[$key],64),
        default=>provider_stackcp_number($input[$key]),
    };
    $scope=$out['profile_id'].($kind==='assignment'?':'.$out['package_id']:'');
    if ($out['scope_id']!==$scope) throw new InvalidArgumentException('Invalid StackCP review scope.');
    return $out;
}
