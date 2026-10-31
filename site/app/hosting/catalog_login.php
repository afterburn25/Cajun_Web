<?php
/** Closed hosting destination after authentication; never accepts a return URL. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function hosting_catalog_login_destination(array $query): array {
    if (array_key_exists('support',$query)) return [];
    if (($query['hosting']??null)==='checkout') {
        $id=$query['offer']??null;$hash=$query['offer_sha256']??null;
        if (is_string($id)&&preg_match('/\A[a-f0-9]{32}\z/',$id)&&is_string($hash)&&preg_match('/\A[a-f0-9]{64}\z/',$hash)) {
            return ['hosting'=>'checkout','offer'=>$id,'offer_sha256'=>$hash];
        }
        return ['hosting'=>'offers'];
    }
    if (($query['hosting']??null)==='offers') {
        $page=$query['p']??'1';
        return ['hosting'=>'offers','p'=>is_string($page)&&preg_match('/\A[1-9][0-9]{0,5}\z/',$page)&&(int)$page<=100000?(int)$page:1];
    }
    return [];
}
