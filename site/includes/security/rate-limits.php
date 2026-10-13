<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function ip_key(string $scope): string { return hash_hmac('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),config()['key']); }

function consume_rate(string $key,int $limit,int $window): bool {
    $bucket=hash('sha256',$key.'|'.(string)floor(time()/$window));
    query('INSERT INTO '.table('rate_limits').' (bucket,attempts,expires) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1',[$bucket,time()+$window]);
    $r=one('SELECT attempts FROM '.table('rate_limits').' WHERE bucket=?',[$bucket]);
    return (int)$r['attempts'] <= $limit;
}
