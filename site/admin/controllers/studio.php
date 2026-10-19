<?php
/** Authenticated administration for the public growth and private client workflows. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_admin_views(): array {return ['studio-clients'=>'Client workspace','studio-job'=>'Client project','studio-pipeline'=>'Sales pipeline','studio-content'=>'Sales content','studio-prices'=>'Estimator prices','studio-portfolio'=>'Portfolio previews','studio-history'=>'Content history','studio-upgrade'=>'Version & database','studio-licenses'=>'Website licenses'];}
require_once CWP_CODE_ROOT.'/app/settings/revisions.php';
function studio_admin_action(string $action,array $user): never {
    require_admin();check_csrf();$target='studio-clients';
    require_once CWP_CODE_ROOT.'/app/licenses/service.php';
    if($action==='studio_migrate'){if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm that you have a backup before updating.');studio_migrate();flash('Database update completed. Existing content was preserved.');redirect(admin_url('studio-upgrade'));}
    if(!studio_ready())throw new RuntimeException('Enable the workspace tables under Version & database first.');
    if($action==='studio_client'){
        $id=gint($_POST,'id',0,2147483647);$name=posted('name',120);$email=strtolower(posted('email',190));
        $GLOBALS['cwp_client_attempt']=['name'=>$name,'email'=>$email,'active'=>$id===0 || isset($_POST['active'])];
        studio_client_save($id,is_string($_POST['expected']??null)?$_POST['expected']:'',$name,$email,$GLOBALS['cwp_client_attempt']['active']);
        flash('Client saved. Older sessions and outstanding invitations were revoked.');redirect(admin_url('studio-clients'));
    }elseif($action==='studio_invite'){
        $id=gint($_POST,'id',1,2147483647);
        $_SESSION['studio_invite_link']=studio_new_invite($id,is_string($_POST['expected']??null)?$_POST['expected']:'');flash('New private invitation created. Share it directly with this client; it expires after 24 hours. No email was sent.');redirect(admin_url('studio-clients'));
    }elseif($action==='studio_contacts_setup'){
        contacts_migrate(posted('current_password',200));flash('Client contacts storage prepared.');redirect(admin_url('studio-upgrade'));
    }elseif($action==='studio_contact'){
        if(!contacts_ready())throw new RuntimeException('Prepare contacts storage under Version & database first.');
        $id=gint($_POST,'id',0,2147483647);$clientId=gint($_POST,'client_id',1,2147483647);$perms=array_filter(array_map('strval',(array)($_POST['perms']??[])));
        contact_save($id,$clientId,is_string($_POST['expected']??null)?$_POST['expected']:'',posted('name',120),strtolower(posted('email',190)),$perms,isset($_POST['enabled']));
        flash('Contact saved. Its sessions and outstanding invitations were revoked.');redirect(admin_url('studio-clients'));
    }elseif($action==='studio_contact_invite'){
        if(!contacts_ready())throw new RuntimeException('Prepare contacts storage under Version & database first.');
        $id=gint($_POST,'id',1,2147483647);$clientId=gint($_POST,'client_id',1,2147483647);
        $_SESSION['studio_invite_link']=contact_invite($id,$clientId,is_string($_POST['expected']??null)?$_POST['expected']:'');flash('New private contact invitation created. Share it directly with this contact; it expires after 24 hours. No email was sent.');redirect(admin_url('studio-clients'));
    }elseif($action==='studio_job'){
        $id=gint($_POST,'id',0,2147483647);$client=gint($_POST,'client_id',1,2147483647);$title=posted('title',160);$stage=posted('stage',30);
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=?',[$client])||$title===''||!isset(studio_stages()[$stage]))throw new InvalidArgumentException('Choose a client, title and valid stage.');
        $summary=posted('summary',10000);$milestones=posted('milestones',15000);$now=gmdate('Y-m-d H:i:s');
        if($id){$old=studio_job($id);if((int)$old['client_id']!==$client)throw new InvalidArgumentException('Project ownership cannot be transferred through this form. Create a separate project.');query('UPDATE '.table('studio_jobs').' SET title=?,summary=?,stage=?,milestones=?,updated_at=? WHERE id=?',[$title,$summary,$stage,$milestones,$now,$id]);}
        else{query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$client,$title,$summary,$stage,$milestones,$now,$now]);$id=(int)db()->lastInsertId();}
        log_event($action,'Project '.$id);flash('Project saved.');redirect(admin_url('studio-job',['id'=>$id]));
    }elseif(in_array($action,['studio_message','studio_note','studio_milestone','studio_publish','studio_upload','studio_preview','studio_invoice','studio_paid'],true)){
        $id=gint($_POST,'job_id',1,2147483647);studio_job($id);
        if($action==='studio_message'){$body=posted('body',5000);if(strlen($body)<2)throw new InvalidArgumentException('Write a message.');query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,\'admin\',?,?,?)',[$id,$user['id'],$body,gmdate('Y-m-d H:i:s')]);}
        if($action==='studio_note'){$body=posted('body',5000);if(strlen($body)<2)throw new InvalidArgumentException('Write a staff note.');query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,\'internal\',?,?,?)',[$id,$user['id'],$body,gmdate('Y-m-d H:i:s')]);}
        if($action==='studio_milestone'){if(!studio_milestones_ready())throw new RuntimeException('Run the workspace database update before tracking milestones.');$mid=gint($_POST,'id',0,2147483647);$title=posted('title',160);$state=posted('state',20);$target=posted('target_date',10);if($title===''||!isset(studio_milestone_states()[$state]))throw new InvalidArgumentException('Enter a milestone title and valid state.');if($target!==''&&(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$target,$dm)||!checkdate((int)$dm[2],(int)$dm[3],(int)$dm[1])))throw new InvalidArgumentException('Target dates use a valid YYYY-MM-DD calendar date.');$now=gmdate('Y-m-d H:i:s');
            if($mid){if(!one('SELECT id FROM '.table('studio_milestones').' WHERE id=? AND job_id=?',[$mid,$id]))throw new InvalidArgumentException('Milestone not found on this project.');query('UPDATE '.table('studio_milestones').' SET title=?,state=?,target_date=?,updated_at=? WHERE id=?',[$title,$state,$target,$now,$mid]);}
            else query('INSERT INTO '.table('studio_milestones').' (job_id,title,state,target_date,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,$title,$state,$target,$now,$now]);}
        if($action==='studio_publish')studio_publish($id,posted('kind',20),posted('title',160),posted('body',30000),posted('preview_url',500));
        if($action==='studio_upload')studio_upload($id,'admin');
        if($action==='studio_preview')studio_preview_upload($id,gint($_POST,'version_id',1,2147483647));
        if($action==='studio_invoice'){$amount=gcents(posted('amount',20));$title=posted('title',160);if($amount===null||$amount<=0||$title==='')throw new InvalidArgumentException('Enter an invoice title and positive amount.');query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (?,?,?,?,?,?)',[$id,'INV-'.strtoupper(bin2hex(random_bytes(6))),$title,$amount,posted('instructions',6000),gmdate('Y-m-d H:i:s')]);}
        if($action==='studio_paid'){$invoice=gint($_POST,'invoice_id',1,2147483647);if(ledger_ready()){ledger_require();redirect(admin_url('invoice-payments',['invoice_id'=>$invoice]));}$note=posted('paid_note',500);if(strlen($note)<5||posted('confirm',1)!=='1')throw new InvalidArgumentException('Record a payment reference and confirm you independently verified receipt.');if(catalog_snapshot($invoice)){catalog_transition($id,$invoice,'paid',$note);}else{payments_manual_paid($id,$invoice,$note);}}
        log_event($action,'Project '.$id);flash('Project updated.');redirect(admin_url('studio-job',['id'=>$id]));
    }elseif($action==='studio_pipeline'){
        $id=gint($_POST,'id',1,2147483647);$stage=posted('status',20);if(!isset(studio_pipeline_stages()[$stage])||!one('SELECT id FROM '.table('leads').' WHERE id=?',[$id]))throw new InvalidArgumentException('Choose a valid inquiry and stage.');
        $follow=posted('follow_up',10);if($follow!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$follow)||date('Y-m-d',strtotime($follow))!==$follow))throw new InvalidArgumentException('Use a valid follow-up date.');
        db()->beginTransaction();try{query('UPDATE '.table('leads').' SET status=?,notes=? WHERE id=?',[$stage,posted('notes',10000),$id]);save_setting('growth_follow_'.$id,$follow);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}$target='studio-pipeline';
    }elseif($action==='studio_prices'){
        $r=['enabled'=>isset($_POST['enabled'])];foreach(growth_types()+['page'=>'Additional page','product'=>'Product']+growth_options() as $key=>$unused)$r[$key]=grange([gcents(posted($key.'_min',20)),gcents(posted($key.'_max',20))]);
        studio_revision_save('growth_prices',json_encode($r,JSON_THROW_ON_ERROR),posted('expected',64));$target='studio-prices';
    }elseif($action==='studio_content'){
        $name=posted('setting_name',100);$value=posted('value',24000);
        if(!in_array($name,['growth_intro','growth_faq','growth_packages'],true))throw new InvalidArgumentException('Unknown content section.');
        if($name==='growth_faq'||$name==='growth_packages'){$rows=json_decode($value,true,32,JSON_THROW_ON_ERROR);$width=$name==='growth_faq'?2:3;if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>12)throw new InvalidArgumentException('Enter a JSON list containing between 1 and 12 items.');foreach($rows as $row){if(!is_array($row)||!array_is_list($row)||count($row)!==$width)throw new InvalidArgumentException('Each content item has '.$width.' text fields.');foreach($row as $text)if(!is_string($text)||strlen($text)>6000)throw new InvalidArgumentException('Use bounded text values.');}}
        studio_revision_save($name,$value,posted('expected',64));$target='studio-content';
    }elseif($action==='studio_restore_content'){
        $id=gint($_POST,'id',1,2147483647);$rev=one('SELECT * FROM '.table('studio_revisions').' WHERE id=?',[$id]);if(!$rev)throw new InvalidArgumentException('Revision not found.');if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm this deliberate restore.');studio_revision_save($rev['setting_name'],$rev['old_value'],posted('expected',64));$target='studio-history';
    }elseif($action==='studio_media'){
        $mediaId=upload_image('image');if(!$mediaId)throw new InvalidArgumentException('Choose a portfolio image.');$target='studio-portfolio';
    }elseif($action==='studio_portfolio'){
        $id=gint($_POST,'id',1,2147483647);if(!one('SELECT id FROM '.table('projects').' WHERE id=?',[$id]))throw new InvalidArgumentException('Portfolio project not found.');
        $v=['caption'=>posted('caption',500),'walkthrough'=>safe_url(posted('walkthrough',500),true)];foreach(['desktop','mobile','before'] as $key){$value=posted($key,32);if($value!==''&&(!preg_match('/^[a-f0-9]{32}$/D',$value)||!one('SELECT id FROM '.table('media').' WHERE id=?',[$value])))throw new InvalidArgumentException('Choose an uploaded image from the media library.');$v[$key]=$value;}
        save_setting('growth_portfolio_'.$id,json_encode($v,JSON_THROW_ON_ERROR));$target='studio-portfolio';
    }elseif($action==='studio_license_setup'){
        require_once CWP_CODE_ROOT.'/database/migrations/license_migrate.php';
        license_migrate(posted('current_password',200));flash('Website licensing storage prepared.');redirect(admin_url('studio-licenses'));
    }elseif(in_array($action,['studio_license_create','studio_license_deactivate','studio_license_reactivate','studio_domain_add','studio_domain_deactivate','studio_domain_replace','studio_license_bundle'],true)&&!license_ready()){
        throw new RuntimeException('Prepare licensing storage first.');
    }elseif($action==='studio_license_legacy_review'){
        require_once CWP_CODE_ROOT.'/app/licenses/legacy.php';
        $resolved=license_resolve_legacy_invoice(gint($_POST,'invoice_id',1,2147483647),gint($_POST,'license_id',0,2147483647),posted('confirm',1)==='1',posted('evidence',200),posted('current_password',200));
        flash('Invoice linked to license '.(int)$resolved['id'].'. Existing activation and download settings were preserved; no email was sent.');redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_create'){
        $customerId=gint($_POST,'customer_id',1,2147483647);$productId=posted('product_id',50);
        if($productId===''||!one('SELECT id FROM '.table('studio_clients').' WHERE id=?',[$customerId]))throw new InvalidArgumentException('Choose a client and product.');
        // A master key authorizes every domain — staff.manage is owner-only
        // (it is not a grantable permission), so only an owner mints one.
        if(isset($_POST['master'])&&$_POST['master']==='1')staff_require('staff.manage');
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        $key=license_create($customerId,$productId,(int)$user['id'],isset($_POST['master'])&&$_POST['master']==='1');$_SESSION['license_key_created']=$key;$target='studio-licenses';
    }elseif($action==='studio_license_deactivate'){
        $id=gint($_POST,'id',1,2147483647);if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$id]))throw new InvalidArgumentException('License not found.');
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        license_deactivate($id,(int)$user['id']);$target='studio-licenses';
    }elseif($action==='studio_license_reactivate'){
        $id=gint($_POST,'id',1,2147483647);if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$id]))throw new InvalidArgumentException('License not found.');
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        license_reactivate($id,(int)$user['id']);flash('License reactivated — its registered sites come back online at their next validation.');$target='studio-licenses';
    }elseif($action==='studio_domain_add'){
        $licenseId=gint($_POST,'license_id',1,2147483647);$domain=posted('domain',255);
        if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$licenseId]))throw new InvalidArgumentException('License not found.');
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        license_register_domain($licenseId,$domain,(int)$user['id']);$target='studio-licenses';
    }elseif($action==='studio_domain_deactivate'){
        $licenseId=gint($_POST,'license_id',1,2147483647);$domain=posted('domain',255);
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        license_deactivate_domain($licenseId,$domain,(int)$user['id']);$target='studio-licenses';
    }elseif($action==='studio_domain_replace'){
        $licenseId=gint($_POST,'license_id',1,2147483647);$old=posted('old_domain',255);$new=posted('new_domain',255);
        if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$licenseId]))throw new InvalidArgumentException('License not found.');
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        license_replace_domain($licenseId,$old,$new,(int)$user['id']);flash('Domain replaced — the old site goes offline at its next license check.');$target='studio-licenses';
    }elseif($action==='studio_license_bundle'){
        $id=gint($_POST,'id',1,2147483647);$license=one('SELECT * FROM '.table('website_licenses').' WHERE id=?',[$id]);
        if(!$license)throw new InvalidArgumentException('License not found.');
        if((int)$license['is_master']===1)throw new InvalidArgumentException('Master license keys are internal and cannot be bundled for delivery.');
        $industry=posted('industry',50);
        require_once CWP_CODE_ROOT.'/app/licenses/fulfill.php';
        $coreDir=license_core_source_dir();
        if($coreDir===null)throw new RuntimeException('Website Core source not found. Configure license_core_dir.');
        $storage=config()['storage']??'';if($storage==='')throw new RuntimeException('Private storage is not configured.');
        $zipPath=license_bundle($license['license_key'],$industry,$coreDir,$storage.'/bundles');
        license_bundle_store($id,$zipPath,$industry.' bundle');
        flash('Bundle created: '.basename($zipPath));$target='studio-licenses';
    }else throw new InvalidArgumentException('Unknown studio action.');
    log_event($action);flash('Saved.');redirect(admin_url($target));
}
function studio_pipeline_stages(): array {return ['new'=>'New inquiry','contacted'=>'Consultation','qualified'=>'Qualified','proposal'=>'Proposal sent','won'=>'Won','closed'=>'Not proceeding','spam'=>'Spam'];}
function studio_hidden(string $name,mixed $value): void {echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';}
require_once CWP_CODE_ROOT.'/admin/views/studio.php';
