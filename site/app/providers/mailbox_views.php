<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/mailboxes.php';

/** Display only permissions revalidated for the actual signed-in actor. */
function provider_mailbox_options(?int $clientId=null):array {
    return staff_locked(function()use($clientId):array {
        provider_mailbox_actor($clientId);if(!provider_mailbox_ready())return [];
        $links=all('SELECT id,domain FROM '.table('provider_domain_links').($clientId===null?'':' WHERE client_id=?').' ORDER BY domain LIMIT 100',$clientId===null?[]:[$clientId]);$out=[];
        foreach($links as $link){
            try{$p=provider_mailbox_policy($link['id']);if(!$p)continue;provider_mailbox_authority($p['id'],$clientId);$out[]=['id'=>$p['id'],'domain'=>$link['domain']];}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
        }return $out;
    });
}
function provider_mailbox_form_value(string $field,int $length):string {
    $v=$_POST[$field]??'';if(!is_string($v)||strlen($v)>$length)throw new InvalidArgumentException('Check the mailbox form and try again.');return $v;
}
function provider_mailbox_submit(?int $clientId,#[SensitiveParameter] ?string $ownerPassword=null):void {
    if(($_POST['mailbox_confirm']??null)!=='1')throw new InvalidArgumentException('Confirm that you want to create this mailbox.');
    $password=provider_mailbox_form_value('mailbox_password',128);
    if($password!==provider_mailbox_form_value('mailbox_password_confirm',128))throw new InvalidArgumentException('Mailbox passwords do not match.');
    provider_mailbox_run(provider_mailbox_form_value('mailbox_policy',32),provider_mailbox_form_value('mailbox_request',32),provider_mailbox_form_value('mailbox_local',64),$password,$clientId,$ownerPassword);
}
