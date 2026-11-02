<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/billing/design_payment_invoices.php';
require_once CWP_CODE_ROOT.'/app/billing/design_payment_forms.php';
function design_financial_mode():bool {
    $versions=[];foreach(['design_payments_schema_version','design_payment_invoices_schema_version'] as $key){$version=one('SELECT value FROM '.table('settings').' WHERE name=?',[$key])['value']??'';if(!in_array($version,['','1'],true))throw new RuntimeException('Financial storage needs owner review. Existing records were preserved.');$versions[$key]=$version;}
    foreach(['design_payment_events'=>'design_payments_schema_version','design_payment_heads'=>'design_payments_schema_version','design_payment_invoices'=>'design_payment_invoices_schema_version'] as $name=>$key)if($versions[$key]===''&&one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Retained financial records are not activated. Ask an owner to review storage.');
    return $versions['design_payments_schema_version']==='1';
}
function design_financial_page(array $query):int {$value=$query['financial_page']??'0';if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/',$value)||(int)$value>410)throw new InvalidArgumentException('Financial history page unavailable.');return (int)$value;}
function design_financial_staff_context(int $job,int $page=0):array {
    return project_progress_touch(fn()=>project_progress_locked(function()use($job,$page):array{
        design_payments_staff();$row=project_progress_job($job);$client=project_progress_client_row((int)$row['client_id'],false);$state=design_payments_validate($row,$page);$view=design_payments_view($row,$state,true);
        $proposal=one('SELECT * FROM '.table('studio_versions')." WHERE job_id=? AND kind='proposal' ORDER BY version_no DESC LIMIT 1",[$job]);
        $approved=$proposal&&$proposal['decision']==='approved'&&(int)$proposal['decided_by']===(int)$row['client_id'];$milestones=[];
        if(project_planning_ready()){$plan=project_planning_validate($row)['head']?->plan()->data();foreach($plan['milestones']??[] as $m)if($m['state']!=='cancelled')$milestones[$m['id']]=$m['title'];}
        return $view+['client_id'=>(int)$row['client_id'],'client_active'=>(int)$client['active']===1,'proposal'=>$approved?['id'=>(int)$proposal['id'],'version'=>(int)$proposal['version_no'],'content_sha256'=>design_payments_proposal($proposal)]:null,'milestones'=>$milestones];
    }),false);
}
function design_financial_seed(array $view):array {
    if(!$view['proposal'])throw new RuntimeException('The client must approve the latest project proposal before you publish financial terms.');
    $s=$view['schedule'];$items=[];$stages=[];
    foreach($s['items']??[] as $i)$items[]=['id'=>$i['id'],'description'=>$i['description'],'quantity'=>(string)$i['quantity'],'unit'=>design_financial_decimal_text($i['unit_cents']),'discount'=>design_financial_decimal_text($i['discount_cents']),'tax'=>design_financial_decimal_text($i['tax_cents'])];
    foreach($s['stages']??[] as $i)$stages[]=['id'=>$i['id'],'kind'=>$i['kind'],'title'=>$i['title'],'calculation'=>$i['calculation'],'value'=>design_financial_decimal_text($i['value']),'due_date'=>$i['due_date'],'milestone_id'=>$i['milestone_id']];
    return ['invalid'=>[],'job_id'=>(string)$view['project']['id'],'client_id'=>(string)$view['client_id'],'request_id'=>bin2hex(random_bytes(16)),'expected'=>$view['expected'],'proposal_id'=>(string)$view['proposal']['id'],'proposal_version'=>(string)$view['proposal']['version'],'proposal_sha256'=>$view['proposal']['content_sha256'],'title'=>$s['title']??'Website design agreement','terms'=>$s['terms']??'','form_action'=>'publish','items'=>$items?:[design_financial_new_item()],'stages'=>$stages?:[['id'=>bin2hex(random_bytes(16)),'kind'=>'full','title'=>'Full service','calculation'=>'remainder','value'=>'0.00','due_date'=>'','milestone_id'=>'']]];
}
function design_financial_admin_action(string $action):void {
    $job=gint($_GET,'id',1,2147483647);
    if($action==='design_financial_setup'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you have a current database backup.');
        if(!payments_ready()||!ledger_ready())throw new RuntimeException('Enable payment preparation and the invoice payment ledger before financial agreements.');
        project_progress_locked(function()use($job):void{design_payments_staff(false);project_progress_job($job);});
        design_payments_migrate(posted('owner_password',1000));design_payment_invoices_migrate(posted('owner_password',1000));
        flash('Financial agreement storage is ready. Existing records were preserved.');redirect(admin_url('studio-job',['id'=>$job]).'#financial');
    }
    if($action==='design_financial_form'){
        $a=design_financial_capture($_POST,true);$GLOBALS['cwp_financial_offer_attempt']=$a;$binding=design_financial_binding($a);$view=design_financial_staff_context($job);
        if($binding['job_id']!==$job||$binding['client_id']!==$view['client_id'])throw new RuntimeException('The financial draft belongs to another project or client.');
        if($view['frozen']&&$a['form_action']!=='publish')throw new RuntimeException('Issued financial terms are frozen. Review the existing invoices.');
        if(!in_array($a['form_action'],['publish','preview'],true)){$GLOBALS['cwp_financial_offer_attempt']=design_financial_draft_edit($a);$GLOBALS['cwp_financial_draft_notice']='Draft updated below. No financial offer was published.';return;}
        $form=design_financial_offer_form($a);
        if($a['form_action']==='preview'){$GLOBALS['cwp_financial_preview']=$form;$GLOBALS['cwp_financial_draft_notice']='Preview only. Review the exact terms and amounts below before publishing.';return;}
        $result=design_payments_publish($job,$form['client_id'],$form['request_id'],$form['expected'],$form['fields']);
    }elseif(in_array($action,['design_financial_withdraw','design_financial_issue'],true)){
        $a=design_financial_capture($_POST,false);$GLOBALS['cwp_financial_command_attempt']=['action'=>$action,'form'=>$a];$form=design_financial_command_form($a);
        if($form['job_id']!==$job)throw new RuntimeException('The financial request belongs to another project.');
        $result=$action==='design_financial_issue'?design_payments_issue($job,$form['client_id'],$form['request_id'],$form['expected'],$form['schedule_id'],$form['fingerprint'],$form['stage_id']):design_payments_withdraw($job,$form['client_id'],$form['request_id'],$form['expected'],$form['schedule_id'],$form['fingerprint'],$form['comment']);
    }else throw new InvalidArgumentException('Unknown financial action.');
    flash($result['replayed']?'This financial action was already saved. The original record was preserved.':($action==='design_financial_issue'?'The agreed invoice was issued. No payment was recorded.':'Financial agreement saved to the project history.'));
    if($action==='design_financial_issue')redirect(admin_url('invoice-payments',['invoice_id'=>$result['invoice_id']]));
    redirect(design_financial_event_link($job,$result['event_id'],true));
}
function design_financial_event_link(int $job,string $event,bool $staff):string {
    $row=one('SELECT sequence FROM '.table('design_payment_events').' WHERE id=? AND job_id=?',[$event,$job]);
    if(!$row)throw new RuntimeException('The saved financial action is unavailable.');
    $params=[$staff?'id':'job'=>$job,'financial_page'=>intdiv((int)$row['sequence'],10)+1];return ($staff?admin_url('studio-job',$params):studio_url($params)).'#financial-event-'.$event;
}
function design_financial_client_action(array $client,int $job):never {
    $a=design_financial_capture($_POST,false);$GLOBALS['cwp_financial_client_attempt']=$a;$form=design_financial_command_form($a);
    if($form['job_id']!==$job||$form['client_id']!==(int)$client['id'])throw new RuntimeException('The financial decision belongs to another project or client.');
    if($form['confirm']!=='1')throw new InvalidArgumentException('Confirm that your decision applies to these exact financial terms.');
    $result=design_payments_decide($job,(int)$client['id'],$form['request_id'],$form['expected'],$form['schedule_id'],$form['fingerprint'],$form['choice'],$form['comment']);
    flash($result['replayed']?'Your original financial decision was already recorded.':'Your decision was saved against these exact financial terms.');redirect(design_financial_event_link($job,$result['event_id'],false));
}
