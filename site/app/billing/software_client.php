<?php
/** Current customer authority at the software purchase/billing reservation. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Lock the account before admitting a new customer mutation, never a gateway result. */
function software_current_client(int $id):array {
    if(!db()->inTransaction())throw new LogicException('Software client authority requires the mutation transaction.');
    if($id<1 || !studio_ready() || support_current() || array_key_exists('support',$_GET)
        || array_key_exists('contact_id',$_SESSION) || array_key_exists('contact_auth',$_SESSION)
        || (int)($_SESSION['client_id']??0)!==$id){
        throw new RuntimeException('Sign in as the selected primary client outside support access.');
    }
    // Account edits and invitations lock this same row before rotating authority.
    // A locking read sees a committed revocation even after an earlier page read.
    $client=one('SELECT id,name,active,auth_version FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[$id]);
    $now=time();$version=$_SESSION['client_auth']??null;
    if(!$client || (int)$client['active']!==1 || !is_string($version)
        || !hash_equals((string)$client['auth_version'],$version)
        || $now-(int)($_SESSION['client_last']??0)>1800
        || $now-(int)($_SESSION['client_login']??0)>28800){
        throw new RuntimeException('Your client session is no longer current. Sign in again.');
    }
    $_SESSION['client_last']=$now;
    return $client;
}
