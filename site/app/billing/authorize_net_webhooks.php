<?php
/** Authorize.net webhook authenticity. Validate before reading events. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function software_anet_signature_matches(string $body,string $header,string $keyHex):bool{
    if(strlen($body)>131072) return false;
    if(!preg_match('/^sha512=([a-fA-F0-9]{128})$/D',trim($header),$match))return false;
    if(!preg_match('/^[a-fA-F0-9]{128}$/D',$keyHex))return false;
    $key=hex2bin($keyHex);
    return is_string($key)&&hash_equals(strtolower($match[1]),hash_hmac('sha512',$body,$key));
}
/** The webhook alone never unlocks an entitlement; reconcile a gateway transaction. */
function software_anet_event_details(string $body):array{
    try{$event=json_decode($body,true,12,JSON_THROW_ON_ERROR);}
    catch(JsonException $e){throw new InvalidArgumentException('Invalid gateway event JSON.',0,$e);}
    if(!is_array($event)||!is_string($event['eventType']??null)||
        !is_array($event['payload']??null))throw new InvalidArgumentException('Invalid gateway event.');
    $rawEntity=$event['payload']['entityName']??null;
    $rawResource=$event['payload']['id']??null;
    if($rawEntity==='subscription'&&!is_string($rawResource)){
        throw new InvalidArgumentException('Invalid subscription event identity.');
    }
    $entity=(string)($rawEntity??'');
    $resource=(string)($rawResource??'');
    return ['type'=>$event['eventType'],'event_id'=>(string)($event['notificationId']??''),
        'entity'=>$entity,'resource_id'=>$resource,
        // Backward-compatible alias used by payment reconciliation.
        'transaction_id'=>$resource];
}

/** Return the exact gateway subscription identity or refuse the event before routing. */
function software_anet_subscription_event_id(array $event):string{
    $id=$event['resource_id']??null;
    if(($event['entity']??'')!=='subscription'||!is_string($id)||!preg_match('/^[0-9]{1,25}$/D',$id)){
        throw new InvalidArgumentException('Invalid subscription event entity.');
    }
    return $id;
}
