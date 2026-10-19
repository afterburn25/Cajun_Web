<?php
/** Closed browsing choices only; a billing filter never changes an offer or checkout. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function hosting_catalog_billing(mixed $value='all'): string {
    if (!is_string($value) || !in_array($value,['all','monthly','annually'],true)) {
        throw new InvalidArgumentException('Choose all, monthly or annual hosting plans.');
    }
    return $value;
}

/** The fixed page key is selected by the route, never by an incoming query value. */
function hosting_catalog_billing_query(array $query,string $pageKey='p'): array {
    if (!in_array($pageKey,['p','hosting_page'],true)) throw new InvalidArgumentException('Choose an available hosting route.');
    $billing=hosting_catalog_billing(array_key_exists('billing',$query)?$query['billing']:'all');
    $page=array_key_exists($pageKey,$query)?$query[$pageKey]:'1';
    if (!is_string($page) || !preg_match('/\A[1-9][0-9]{0,5}\z/',$page) || (int)$page>100000) {
        throw new InvalidArgumentException('Choose an available hosting page.');
    }
    return ['billing'=>$billing,'page'=>(int)$page];
}
