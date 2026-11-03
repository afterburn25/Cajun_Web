<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/php_controls_changes.php';

/** Exact form values: option strings are never trimmed, case-folded or coerced. */
function provider_php_controls_http_text(#[SensitiveParameter] array $fields,string $name,int $limit,bool $empty=false): string
{
    $value=$fields[$name]??'';
    if (!is_string($value) || strlen($value)>$limit || (!$empty && $value==='')) {
        throw new InvalidArgumentException('Reload the PHP settings form and check the entered values.');
    }
    return $value;
}

/** Call before studio_client() can normalize a retained contact session. */
function provider_php_controls_http_session_gate(): void
{
    if (array_key_exists('contact_id',$_SESSION) || array_key_exists('contact_auth',$_SESSION)
        || array_key_exists('support',$_GET) || support_current()) {
        fail_page('Sign in directly as the primary client to use PHP settings.',403);
    }
}

function provider_php_controls_http_link(bool $owner,bool $post=false): string
{
    if (($owner && ($_GET['view']??null)!=='provider-php-controls')
        || (!$owner && ($_GET['php_controls']??null)!=='1')) {
        throw new InvalidArgumentException('Open the PHP settings page for this website first.');
    }
    $link=provider_php_controls_http_text($_GET,'service_link',32);
    provider_php_controls_lifecycle_id($link);
    if ($post && provider_php_controls_http_text($_POST,'link_id',32)!==$link) {
        throw new InvalidArgumentException('The selected website changed. Reload its PHP settings.');
    }
    return $link;
}

/** All operations use fixed application wrappers; HTTP can supply no transport dependency. */
function provider_php_controls_http_action(bool $owner,?int $clientId,string $action): array
{
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') throw new InvalidArgumentException('Submit the PHP settings form.');
    check_csrf();
    if ($owner) provider_settings_manager();
    else {
        provider_php_controls_http_session_gate();
        if ($clientId===null) throw new RuntimeException('Sign in to use PHP settings.');
        staff_locked(fn()=>staff_transaction(fn()=>provider_php_controls_primary_client($clientId)));
    }
    $allowed=['php_controls_prepare','php_controls_delegate','php_controls_read','php_controls_review','php_controls_change','php_controls_verify','php_controls_recover'];
    if (!in_array($action,$allowed,true) || (!$owner && in_array($action,['php_controls_prepare','php_controls_delegate','php_controls_recover'],true))) {
        throw new InvalidArgumentException('This PHP settings action is unavailable.');
    }
    $link=provider_php_controls_http_link($owner,true);
    $text=static fn(string $key,int $limit,bool $empty=false):string=>provider_php_controls_http_text($_POST,$key,$limit,$empty);
    $password=$text('current_password',200,true);
    $message='PHP settings updated.';$tone='success';
    if ($action==='php_controls_prepare') {
        provider_php_controls_prepare($password);
        provider_php_controls_lifecycle_prepare($password);
        $message='PHP settings are enabled. Client permission is managed separately.';
    } elseif ($action==='php_controls_delegate') {
        $enabled=$text('enabled',1);
        if (!in_array($enabled,['0','1'],true) || $text('confirmed',1)!=='1') throw new InvalidArgumentException('Confirm the reviewed client permission.');
        provider_php_controls_delegate($link,$text('expected_permission_id',32,true),$text('event_id',32),$enabled==='1',$text('review_sha256',64),$password);
        $message=$enabled==='1'?'The assigned primary client may manage PHP settings.':'Client permission to change PHP is off.';
    } elseif ($action==='php_controls_read') {
        $kind=$text('kind',12);
        if (!in_array($kind,['current','available'],true)) throw new InvalidArgumentException('Choose which PHP information to check.');
        $result=$owner?provider_php_controls_read_owner($link,$kind,$password):provider_php_controls_read_client($clientId,$link,$kind);
        $message=$result['state']==='complete'?($kind==='current'?'The current PHP version was saved.':'Available PHP versions were saved.'):'The check did not return usable PHP information. Check the status below.';
        $tone=$result['state']==='complete'?'success':'error';
    } elseif ($action==='php_controls_review' || $action==='php_controls_change') {
        $request=$text('request_id',32);provider_php_controls_lifecycle_id($request);
        $current=$text('current_read_id',32);$options=$text('options_read_id',32);$desired=$text('desired',128);
        if ($action==='php_controls_review') {
            $review=$owner?provider_php_controls_review_owner($link,$current,$options,$desired):provider_php_controls_review_client($clientId,$link,$current,$options,$desired);
            return ['kind'=>'review','review'=>$review+['request_id'=>$request],'message'=>'','tone'=>'success'];
        }
        $hash=$text('review_sha256',64);$confirmed=$text('confirmed',1)==='1';
        $result=$owner?provider_php_controls_change_owner($request,$link,$current,$options,$desired,$hash,$confirmed,$password)
            :provider_php_controls_change_client($clientId,$request,$link,$current,$options,$desired,$hash,$confirmed,$password);
        $message=provider_php_controls_http_result($result);$tone=$result['readback']==='observed'?'success':'error';
    } else {
        $request=$text('request_id',32);provider_php_controls_lifecycle_id($request);
        // Bind the opaque request to the website in the URL before a check/recovery can act.
        staff_locked(fn()=>staff_transaction(function()use($request,$link,$clientId,$owner):void {
            if (!provider_php_controls_lifecycle_ready()) throw new RuntimeException('PHP settings history is unavailable.');
            $record=provider_php_controls_lifecycle_get('request',$request);
            if ($record===null || $record['document']['review']['context']['binding']['link_id']!==$link) throw new RuntimeException('This change request is unavailable for the selected website.');
            provider_php_controls_change_access($record['document'],$owner?null:$clientId);
        }));
        if ($action==='php_controls_verify') {
            $result=$owner?provider_php_controls_verify_owner($request,$password):provider_php_controls_verify_client($clientId,$request,$password);
            $message=provider_php_controls_http_result($result);$tone=$result['readback']==='observed'?'success':'error';
        } else {
            if ($text('confirmed',1)!=='1') throw new InvalidArgumentException('Confirm recovery of the reviewed local request record.');
            provider_php_controls_recover_owner($request,$text('ledger_sha256',64),$password);
            $message='The saved request record was recovered. Check the PHP version separately; recovery does not send another change.';
        }
    }
    return ['kind'=>'updated','review'=>null,'message'=>$message,'tone'=>$tone];
}

function provider_php_controls_http_result(array $result): string
{
    if ($result['readback']==='observed') return 'A later check reported the requested PHP version.';
    if ($result['readback']==='different') return 'The later check reported a different PHP version. This request still needs review.';
    if ($result['readback']==='unavailable') return 'The later check could not confirm a PHP version. This request still needs review.';
    if ($result['phase']==='not_sent') return 'The change request was not sent.';
    return 'Your request is recorded. Check the PHP version separately before requesting another change.';
}

/** Re-display only the same still-valid review after an input/password error. */
function provider_php_controls_http_retry(bool $owner,?int $clientId,array &$screen,?array $review): array
{
    $retry=[];
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') return [$review,$retry];
    $action=$_POST['action']??null;
    if (!is_string($action) || !str_starts_with($action,'php_controls_')) return [$review,$retry];
    try {
        $link=provider_php_controls_http_link($owner,true);
        if ($link!==$screen['link_id']) throw new RuntimeException('Website changed.');
        if (in_array($action,['php_controls_review','php_controls_change'],true)) {
            $id=provider_php_controls_http_text($_POST,'request_id',32);provider_php_controls_lifecycle_id($id);
            $desired=provider_php_controls_http_text($_POST,'desired',128);
            $retry=['request_id'=>$id,'desired'=>$desired];
            if ($action==='php_controls_change' && $screen['can_select']) {
                $current=provider_php_controls_http_text($_POST,'current_read_id',32);$options=provider_php_controls_http_text($_POST,'options_read_id',32);
                $fresh=$owner?provider_php_controls_review_owner($link,$current,$options,$desired):provider_php_controls_review_client($clientId,$link,$current,$options,$desired);
                if (!hash_equals($fresh['review_sha256'],provider_php_controls_http_text($_POST,'review_sha256',64))) throw new RuntimeException('Review changed.');
                $review=$fresh+['request_id'=>$id];
            }
        } elseif ($owner && $action==='php_controls_delegate') {
            $enabled=provider_php_controls_http_text($_POST,'enabled',1);
            if (!in_array($enabled,['0','1'],true)) throw new InvalidArgumentException('Invalid delegation.');
            $candidate=$screen['delegation'][$enabled==='1'?'enable':'disable']??null;
            $expected=provider_php_controls_http_text($_POST,'expected_permission_id',32,true);
            $hash=provider_php_controls_http_text($_POST,'review_sha256',64);
            $event=provider_php_controls_http_text($_POST,'event_id',32);provider_php_controls_lifecycle_id($event);
            if ($candidate===null || $candidate['expected_permission_id']!==$expected || !hash_equals($candidate['review_sha256'],$hash)) throw new RuntimeException('Delegation changed.');
            $retry['delegation']=['enabled'=>$enabled==='1','expected_permission_id'=>$expected,'event_id'=>$event,'review_sha256'=>$hash];
        }
    } catch (InvalidArgumentException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        $review=null;
        // A failed captured approval must not be replaced by a new approval on the same response.
        if ($owner && $action==='php_controls_delegate' && $screen['delegation']!==null) {
            $screen['delegation']['enable']=null;$screen['delegation']['disable']=null;
        }
    }
    return [$review,$retry];
}
