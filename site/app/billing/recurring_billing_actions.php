<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_views.php';
use CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms;

function recurring_ui_url(bool $staff,array $params=[]):string {
    return $staff?admin_url('recurring-billing',$params):studio_url(['recurring'=>1]+$params);
}
function recurring_ui_action(string $action,?int $client=null):void {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('Submit the reviewed form to continue.');
    $staff=$client===null;$q=recurring_ui_query($_GET,$staff);
    $a=recurring_ui_capture($_POST);$GLOBALS['cwp_recurring_attempt']=$a;recurring_ui_valid($a);
    if($a['action']!==$action)throw new InvalidArgumentException('Choose a valid recurring billing action.');
    recurring_billing_locked(fn()=>recurring_ui_authority($client));
    if($action==='recurring_setup'&&$staff){
        recurring_ui_confirm($a);$password=$_POST['owner_password']??null;
        if(!is_string($password)||strlen($password)>1000)throw new InvalidArgumentException('Enter your current owner password.');
        recurring_billing_migrate($password);flash('Recurring billing is ready. Existing records were preserved.');redirect(recurring_ui_url(true));
    }
    if(!$q['job']||recurring_ui_number($a['job_id'])!==$q['job'])throw new InvalidArgumentException('The form belongs to another project.');
    if($client!==null&&recurring_ui_number($a['client_id'])!==$client)throw new RuntimeException('This billing action is unavailable for your account.');
    if($action==='recurring_offer'&&$staff){
        if($q['source']===''||$a['source_id']!==$q['source'])throw new InvalidArgumentException('The form belongs to another service agreement.');
        if(!in_array($a['intent'],['preview','publish'],true))throw new InvalidArgumentException('Choose preview or publish.');
        $source=recurring_ui_offer_source($a,$q['job']);$fields=recurring_ui_offer_fields($a,$source);
        if($a['intent']==='preview'){
            recurring_billing_locked(function()use($fields,$a):void{
                recurring_billing_staff();$terms=RecurringBillingTerms::fromArray($fields);$family=recurring_billing_family($a['source_id']);
                recurring_billing_source($terms,true,true,$family['source'],time());
                if($a['previous_id']!==($family['row']['id']??'')||$a['previous_sha256']!==($family['document']?->fingerprint()??''))throw new RuntimeException('Review the current preceding recurring agreement.');
                if($family['document'])recurring_billing_replacement($family['document'],$terms);recurring_billing_check_floor($family['floor'],$terms);
            });
            $GLOBALS['cwp_recurring_preview']=$fields;return;
        }
        recurring_ui_confirm($a);$doc=recurring_billing_offer($fields,$a['previous_id']===''?null:$a['previous_id'],$a['previous_sha256']===''?null:$a['previous_sha256']);
        flash('Billing terms saved for the client to review. No invoice or payment was created.');redirect(recurring_ui_url(true,['job'=>$q['job'],'schedule'=>$doc->data()['terms']['id']]));
    }
    if($action!=='recurring_command'||$q['schedule']===''||$a['schedule_id']!==$q['schedule'])throw new InvalidArgumentException('The form belongs to another recurring agreement.');
    // The protected context validates project binding and complete history before parsing dates.
    $view=recurring_ui_context($q,$client);if(!isset($view['document']))throw new RuntimeException('Recurring agreement unavailable.');
    $form=recurring_ui_command($a,$view['document'],$staff);
    if($form['client_id']!==$view['client']['id'])throw new RuntimeException('The form belongs to another client.');
    if($form['command']==='issue'){
        if($q['period_date']===''||$q['period_date']!==$a['period_date'])throw new InvalidArgumentException('Review the exact invoice period before issuing it.');
        $result=recurring_billing_issue($form['schedule_id'],$form['client_id'],$form['revision'],$form['sha256'],$form['args']['period_index'],$form['request_id']);
        flash($result['replayed']?'The original invoice was preserved.':'The agreed period invoice was issued. No payment was recorded.');
        redirect(admin_url('invoice-payments',['invoice_id'=>$result['invoice_id']]));
    }
    if($staff)recurring_billing_change($form['schedule_id'],$form['client_id'],$form['revision'],$form['request_id'],$form['command'],$form['args']);
    else recurring_billing_decide($form['schedule_id'],$client,$form['revision'],$form['request_id'],$form['command'],$form['args'],true);
    flash('Your reviewed billing decision is saved in the agreement history.');redirect(recurring_ui_url($staff,['job'=>$q['job'],'schedule'=>$q['schedule']]));
}
