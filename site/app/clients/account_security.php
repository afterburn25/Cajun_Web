<?php
/** Customer self-service credentials. This is not an administrator/support impersonation action. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';

/** Re-read the actual primary customer; never trust a client ID submitted in a form. */
function studio_account_identity(): array {
    if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Account security is unavailable during client review.');
    // Contact identity support is a separate integration. Never silently fall
    // back to the company's primary account when an additional identity exists.
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Sign in with the primary client account to manage these security settings.');
    $client=studio_client();
    if(!$client)throw new RuntimeException('Your client session ended. Sign in again before changing your password.');
    return $client;
}

/** Commit credentials, recovery-invitation revocation and audit atomically. */
function studio_account_password_change(#[\SensitiveParameter] string $current,#[\SensitiveParameter] string $password,#[\SensitiveParameter] string $confirmation): void {
    if(session_status()!==PHP_SESSION_ACTIVE||headers_sent())throw new RuntimeException('A secure client session is required. Reload the account page.');
    $expected=studio_account_identity();
    if(!hash_equals($password,$confirmation))throw new InvalidArgumentException('The new passwords do not match.');
    validate_password($password);
    if(strlen($current)>1000||str_contains($current,"\0"))throw new InvalidArgumentException('Your current password is not correct.');
    if(hash_equals($current,$password))throw new InvalidArgumentException('Choose a new password different from your current password.');
    $version=staff_locked(fn()=>staff_transaction(function()use($expected,$current,$password):string {
        $actor=studio_account_identity();
        if((int)$actor['id']!==(int)$expected['id']||!hash_equals($actor['auth_version'],$expected['auth_version']))throw new RuntimeException('Your client session changed. Reload and sign in again.');
        $row=one('SELECT * FROM '.table('studio_clients').' WHERE id=? AND active=1 FOR UPDATE',[(int)$actor['id']]);
        if(!$row||!hash_equals($row['auth_version'],$actor['auth_version']))throw new RuntimeException('Your client session changed. Reload and sign in again.');
        if(!password_verify($current,$row['password_hash']))throw new InvalidArgumentException('Your current password is not correct.');
        $hash=password_hash($password,PASSWORD_DEFAULT);$version=bin2hex(random_bytes(16));
        // Hashing may take time; an expired or replaced session cannot gain a
        // final write merely by having passed the earlier check.
        $fresh=studio_account_identity();
        if((int)$fresh['id']!==(int)$actor['id']||!hash_equals($fresh['auth_version'],$actor['auth_version']))throw new RuntimeException('Your client session changed. Reload and sign in again.');
        query('UPDATE '.table('studio_clients').' SET password_hash=?,auth_version=?,invite_hash=NULL,invite_expires=NULL WHERE id=?',[$hash,$version,(int)$actor['id']]);
        studio_client_audit(0,'client.password_change',(int)$actor['id']);
        return $version;
    }));
    // Other sessions retain the old auth_version and fail the existing
    // request-by-request authentication check. Do not extend absolute lifetime.
    if(!session_regenerate_id(true)){
        unset($_SESSION['client_id'],$_SESSION['client_auth'],$_SESSION['client_last'],$_SESSION['client_login']);
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        throw new RuntimeException('Your password was updated. Sign in again with your new password.');
    }
    $_SESSION['client_auth']=$version;$_SESSION['client_last']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
}
