<?php
/** Closed hosting destination after authentication; never accepts a return URL. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function hosting_catalog_login_destination(array $query): array {
    if (array_key_exists('support',$query)) return [];
    if (($query['hosting']??null)==='checkout') {
        $id=$query['offer']??null;$hash=$query['offer_sha256']??null;
        if (is_string($id)&&preg_match('/\A[a-f0-9]{32}\z/',$id)&&is_string($hash)&&preg_match('/\A[a-f0-9]{64}\z/',$hash)) {
            $destination=['hosting'=>'checkout'];$category=$query['category']??null;$config=$query['config']??null;
            if ($category!==null || $config!==null) {
                if (!is_string($category) || !preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/',$category) || !is_string($config) || !preg_match('/\A[a-f0-9]{64}\z/',$config)) return ['hosting'=>'offers'];
                $destination+=['category'=>$category,'config'=>$config];
            }
            return $destination+['offer'=>$id,'offer_sha256'=>$hash];
        }
        return ['hosting'=>'offers'];
    }
    if (($query['hosting']??null)==='offers') {
        $page=$query['p']??'1';
        $category=$query['category']??'shared-hosting';$billing=$query['billing']??'all';
        if (!is_string($category)||!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/',$category)) $category='shared-hosting';
        if (!is_string($billing)||!in_array($billing,['all','monthly','annually'],true)) $billing='all';
        return ['hosting'=>'offers','category'=>$category,'billing'=>$billing,'p'=>is_string($page)&&preg_match('/\A[1-9][0-9]{0,5}\z/',$page)&&(int)$page<=100000?(int)$page:1];
    }
    return [];
}
