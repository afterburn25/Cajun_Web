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
    }elseif($action==='studio_job'){
        $id=gint($_POST,'id',0,2147483647);$client=gint($_POST,'client_id',1,2147483647);$title=posted('title',160);$stage=posted('stage',30);
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=?',[$client])||$title===''||!isset(studio_stages()[$stage]))throw new InvalidArgumentException('Choose a client, title and valid stage.');
        $summary=posted('summary',10000);$milestones=posted('milestones',15000);$now=gmdate('Y-m-d H:i:s');
        if($id){$old=studio_job($id);if((int)$old['client_id']!==$client)throw new InvalidArgumentException('Project ownership cannot be transferred through this form. Create a separate project.');query('UPDATE '.table('studio_jobs').' SET title=?,summary=?,stage=?,milestones=?,updated_at=? WHERE id=?',[$title,$summary,$stage,$milestones,$now,$id]);}
        else{query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,stage,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[$client,$title,$summary,$stage,$milestones,$now,$now]);$id=(int)db()->lastInsertId();}
        log_event($action,'Project '.$id);flash('Project saved.');redirect(admin_url('studio-job',['id'=>$id]));
    }elseif(in_array($action,['studio_message','studio_publish','studio_upload','studio_invoice','studio_paid'],true)){
        $id=gint($_POST,'job_id',1,2147483647);studio_job($id);
        if($action==='studio_message'){$body=posted('body',5000);if(strlen($body)<2)throw new InvalidArgumentException('Write a message.');query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,\'admin\',?,?,?)',[$id,$user['id'],$body,gmdate('Y-m-d H:i:s')]);}
        if($action==='studio_publish')studio_publish($id,posted('kind',20),posted('title',160),posted('body',30000),posted('preview_url',500));
        if($action==='studio_upload')studio_upload($id,'admin');
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
    }elseif($action==='software_asset_publish'){
        require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';
        $visible=isset($_POST['customer_visible'])&&$_POST['customer_visible']==='1';
        $id=software_asset_publish(posted('sku',60),posted('version',40),posted('title',120),
            posted('private_path',500),posted('current_password',200),$visible);
        flash('Verified private software ZIP version recorded as asset '.$id.' ('.($visible?'customer-visible':'owner-private').').');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_asset_visibility'){
        require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';
        $visible=gint($_POST,'customer_visible',0,1)===1;
        $changed=software_asset_visibility(gint($_POST,'asset_id',1,PHP_INT_MAX),$visible,posted('current_password',200));
        flash($changed?'Software release visibility updated.':'Software release visibility was already set that way.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_subscription_charge_reconcile'){
        require_once CWP_CODE_ROOT.'/app/billing/software_subscription_settle.php';
        $state=software_subscription_charge_reconcile(
            gint($_POST,'invoice_id',1,2147483647),
            posted('transaction_id',30),
            posted('current_password',200)
        );
        flash(match($state){
            'first_payment_recorded'=>'Verified recurring first payment reconciled and fulfillment retried.',
            'renewal_recorded'=>'Verified recurring renewal reconciled and paid-through access extended.',
            'replay'=>'That recurring transaction was already reconciled.',
            'sandbox_verified'=>'Sandbox recurring transaction verified only; no invoice or entitlement was changed.',
            'subscription_not_settleable'=>'The gateway transaction is verified, but the local subscription state is not settleable. Keep it in owner review.',
            default=>'Recurring transaction reconciliation returned '.$state.'.',
        });
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_subscription_reconcile'){
        require_once CWP_CODE_ROOT.'/app/billing/software_subscription_enroll.php';
        $state=software_subscription_enroll_reconcile(
            gint($_POST,'invoice_id',1,2147483647),
            posted('gateway_subscription_id',25),
            posted('current_password',200)
        );
        flash(match($state){
            'pending_first_payment'=>'Gateway subscription reconciled. Software remains locked until the first verified recurring charge.',
            'sandbox_test'=>'Sandbox subscription reconciled for testing only. No paid access was granted.',
            'cancelled'=>'Gateway subscription is already cancelled/expired; local enrollment was reconciled without granting access.',
            'review'=>'Gateway subscription is suspended/terminated and remains in owner review.',
            'already_linked'=>'That gateway subscription is already linked to this software invoice.',
            default=>'Subscription reconciliation completed with state '.$state.'.',
        });
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_charge_reconcile'){
        require_once CWP_CODE_ROOT.'/app/billing/software_charge.php';
        $state=software_charge_reconcile(
            gint($_POST,'invoice_id',1,2147483647),
            posted('transaction_id',30),
            posted('current_password',200)
        );
        flash($state==='paid'
            ?'Verified Authorize.net transaction reconciled. Paid fulfillment was retried safely.'
            :($state==='already_paid'
                ?'That verified Authorize.net transaction is already recorded as paid.'
                :'Sandbox transaction readback verified only. No invoice was marked paid and no license was released.'));
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_order_fulfill_retry'){
        if(support_current())throw new RuntimeException('Leave support mode first.');
        staff_require('staff.manage');
        staff_owner(posted('current_password',200));
        require_once CWP_CODE_ROOT.'/app/licenses/fulfill.php';
        $invoice=gint($_POST,'invoice_id',1,2147483647);
        if(!one('SELECT id FROM '.table('software_orders').' WHERE invoice_id=?',[$invoice]))
            throw new InvalidArgumentException('Software order not found.');
        $created=license_fulfill_invoice($invoice);
        if(!$created)throw new RuntimeException('Invoice is not fully paid or licensing has not been prepared.');
        flash('Paid software entitlement fulfillment checked and retried without minting duplicate units.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_notice_retry'){
        if(support_current())throw new RuntimeException('Delivery is unavailable during support mode.');
        staff_require('staff.manage');
        staff_owner(posted('current_password',200));
        require_once CWP_CODE_ROOT.'/app/licenses/software_notices.php';
        $sent=software_delivery_notice_send(gint($_POST,'invoice_id',1,2147483647));
        flash($sent?'Delivery notice sent or already completed.':'Delivery notice was not sent; inspect mail configuration and the retained delivery state.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_notice_reset'){
        if(support_current())throw new RuntimeException('Delivery recovery is unavailable during support mode.');
        staff_require('staff.manage');
        require_once CWP_CODE_ROOT.'/app/licenses/software_notices.php';
        $invoice=gint($_POST,'invoice_id',1,2147483647);
        if(!software_delivery_notice_reset($invoice,posted('current_password',200))){
            throw new RuntimeException('This notice is not eligible for retry-budget reset.');
        }
        flash('Delivery retry budget reset after owner review. Retry the notice when mail configuration is ready.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_dispute_record'){
        if(support_current())throw new RuntimeException('Payment dispute decisions are unavailable during support mode.');
        staff_require('staff.manage');
        require_once CWP_CODE_ROOT.'/app/billing/software_payment_review.php';
        $state=software_payment_dispute_record(posted('original_transaction_id',30),posted('case_reference',100),
            posted('evidence',500),posted('current_password',200));
        flash($state==='replay'?'That merchant dispute case is already recorded.':'Merchant dispute recorded for owner review; paid-through software access was not automatically revoked.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_product_policy'){
        require_once CWP_CODE_ROOT.'/app/licenses/software_products.php';
        if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
        if(support_current())throw new RuntimeException('Leave support mode first.');
        staff_require('staff.manage');
        $policy=software_product_policy_save(
            gint($_POST,'product_id',1,2147483647),
            gint($_POST,'revision',0,2147483647),
            posted('module_key',80),
            posted('dependencies',1200),
            gint($_POST,'update_months',0,120),
            gint($_POST,'max_installations',1,25),
            posted('current_password',200)
        );
        flash('Software entitlement policy saved as revision '.(int)$policy['revision'].'. Existing issued licenses keep their snapshotted rights.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_domain_policy'){
        require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
        if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
        if(support_current())throw new RuntimeException('Leave support mode first.');
        staff_require('staff.manage');
        staff_owner(posted('current_password',200));
        $hours=gint($_POST,'domain_cooldown_hours',0,720);
        save_setting('software_domain_change_cooldown_hours',(string)$hours);
        license_audit((int)$user['id'],'domain.policy','Customer software domain replacement cooldown set to '.$hours.' hours');
        flash('Software domain replacement cooldown updated.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_gateway_select'){
        require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
        require_once CWP_CODE_ROOT.'/app/billing/payments.php';
        if(!software_commerce_ready()||!payments_ready())throw new RuntimeException('Prepare software and payment configuration first.');
        if(support_current())throw new RuntimeException('Leave support mode first.');
        staff_require('staff.manage');
        staff_owner(posted('current_password',200));
        $mode=posted('gateway_mode',20);
        if(!in_array($mode,['disabled','sandbox'],true))throw new RuntimeException('Live payments remain gated pending integrated acceptance and payment reconciliation review.');
        if($mode==='sandbox'){
            $record=one('SELECT credentials FROM '.table('payment_gateways').' WHERE environment=?',['sandbox']);
            if(!$record||$record['credentials']==='')throw new RuntimeException('Configure encrypted Authorize.net sandbox credentials under payment settings first.');
        }
        save_setting('software_gateway_mode',$mode);
        license_audit((int)$user['id'],'gateway.mode','Software payment mode changed to '.$mode);
        flash('Software payment mode updated. Sandbox transactions do not count as customer payments.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_gateway_production_review'){
        require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
        if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
        if(support_current())throw new RuntimeException('Leave support mode first.');
        staff_require('staff.manage');
        $owner=staff_owner(posted('current_password',200));
        $approved=posted('confirm',1)==='1';
        if($approved&&(!defined('CWP_SOFTWARE_PRODUCTION_PAYMENTS')||CWP_SOFTWARE_PRODUCTION_PAYMENTS!==true)){
            throw new RuntimeException('The code-level software production-payment gate is still locked.');
        }
        save_setting('software_gateway_production_reviewed',$approved?'1':'0');
        license_audit((int)$owner['id'],'software.gateway.production_review',
            $approved?'Software production payment review recorded':'Software production payment review cleared');
        flash($approved
            ?'Software production review recorded. Production checkout still remains unavailable until the separate mode-selection release work is explicitly completed.'
            :'Software production review cleared.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_commerce_setup'){
        require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
        software_commerce_prepare(posted('current_password',200));
        flash('Software license and purchase storage is prepared. Existing licenses were retained.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='software_license_lifecycle'){
        require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
        if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
        if(support_current())throw new RuntimeException('Leave support mode first.');
        staff_require('staff.manage');
        require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
        $id=gint($_POST,'id',1,2147483647);
        $transition=posted('transition',20);
        $reason=posted('reason',200);
        $password=array_key_exists('current_password',$_POST)?posted('current_password',200):null;
        $result=software_grant_lifecycle_transition($id,$transition,$reason,(int)$user['id'],$password);
        flash('Software license is now '.$result['state'].'. Existing key and domain history were preserved.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_financing_setup'){
        require_once CWP_CODE_ROOT.'/database/migrations/license_financing_migrate.php';
        license_financing_prepare(posted('current_password',200));
        flash('Financed-site license enforcement is prepared. Existing licenses and billing records were preserved.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_offers_setup'){
        require_once CWP_CODE_ROOT.'/database/migrations/license_finance_offers_migrate.php';
        license_finance_offers_prepare(posted('current_password',200));
        flash('Client finance agreements are prepared. Existing licenses, recurring schedules, invoices, and payments were preserved.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_gateway_setup'){
        require_once CWP_CODE_ROOT.'/database/migrations/license_finance_gateway_migrate.php';
        license_finance_gateway_prepare(posted('current_password',200));
        flash('Financing payment gateway state is prepared. Card processing still follows the unified Authorize.Net mode and production gates.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_gateway_review'){
        staff_require('staff.manage');$owner=staff_owner(posted('current_password',200));
        $approved=posted('confirm',1)==='1';
        if($approved&&(!defined('CWP_FINANCE_PRODUCTION_PAYMENTS')||CWP_FINANCE_PRODUCTION_PAYMENTS!==true)){
            throw new RuntimeException('The code-level financing production-payment gate is still locked.');
        }
        save_setting('finance_gateway_production_reviewed',$approved?'1':'0');
        license_audit((int)$owner['id'],'financing.gateway.production_review',$approved?'Financing production payment review recorded':'Financing production payment review cleared');
        flash($approved?'Financing production review recorded. Unified checkout still must pass every other production gate.':'Financing production review cleared.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_charge_reconcile'){
        require_once CWP_CODE_ROOT.'/app/billing/finance_charge.php';
        $state=license_finance_charge_reconcile(gint($_POST,'invoice_id',1,2147483647),posted('transaction_id',30),posted('current_password',200));
        flash('Financing charge reconciliation: '.$state.'.');redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_subscription_reconcile'){
        require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
        $state=license_finance_subscription_reconcile(posted('finance_id',32),posted('gateway_subscription_id',25),posted('current_password',200));
        flash('Financing subscription reconciliation: '.$state.'.');redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_subscription_charge_reconcile'){
        require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
        $state=license_finance_subscription_charge_reconcile(posted('transaction_id',30),posted('current_password',200));
        flash('Financing recurring transaction reconciliation: '.$state.'.');redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_offer_create'){
        require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
        foreach(['cash_price','initial_payment','amount_financed','finance_charge'] as $moneyField){
            $money[$moneyField]=gcents(posted($moneyField,20));
            if($money[$moneyField]===null)throw new InvalidArgumentException('Enter valid USD financing amounts with at most two decimals.');
        }
        $offer=license_finance_offer_create(
            gint($_POST,'license_id',1,2147483647),posted('schedule_id',32),posted('finance_title',190),
            $money['cash_price'],$money['initial_payment'],$money['amount_financed'],$money['finance_charge'],
            license_finance_apr_bps(posted('apr_percent',8)),gint($_POST,'installments',1,360),gint($_POST,'grace_days',0,90),
            gint($_POST,'offer_days',1,30),gint($_POST,'payment_link_days',1,30),
            [
                'resale'=>false,
                'distribution'=>false,
                'exclusive_resale'=>false,
                'exclusive_distribution'=>false
            ],
            posted('agreement_text',12000),posted('current_password',200)
        );
        flash('Finance agreement created. Portal link: '.license_finance_offer_portal_url($offer['id']));
        redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_offer_withdraw'){
        require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
        license_finance_offer_withdraw(posted('finance_id',32),posted('current_password',200));
        flash('Unsigned finance agreement withdrawn. Its retained history was preserved.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_finance_offer_email'){
        require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
        $sender=posted('sender',43);
        if(!preg_match('/^([a-f0-9]{32}):([1-9][0-9]{0,9})$/D',$sender,$senderParts))throw new InvalidArgumentException('Choose a current sending account.');
        $draft=license_finance_offer_email_draft(posted('finance_id',32),$senderParts[1],(int)$senderParts[2]);
        flash('Finance-agreement email draft prepared. Review it before sending.');
        redirect(admin_url('mail-send',['draft_id'=>$draft]));
    }elseif($action==='studio_license_financing_bind'){
        throw new RuntimeException('Direct financing binding is retired. Create a customer finance agreement and have the customer sign it in their portal.');
    }elseif($action==='studio_license_financing_rights'){
        require_once CWP_CODE_ROOT.'/app/licenses/financing.php';
        $plan=license_financing_grant_rights(
            gint($_POST,'license_id',1,2147483647),
            [
                'resale'=>isset($_POST['allow_resale']),
                'distribution'=>isset($_POST['allow_distribution']),
                'exclusive_resale'=>isset($_POST['exclusive_resale']),
                'exclusive_distribution'=>isset($_POST['exclusive_distribution'])
            ],
            gint($_POST,'rights_invoice_id',1,2147483647),
            posted('evidence',200),posted('current_password',200)
        );
        flash('Commercial rights grant recorded. Financing payoff and distribution rights remain separate.');
        redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_financing_sync'){
        staff_require('staff.manage');
        require_once CWP_CODE_ROOT.'/app/licenses/financing.php';
        $plan=license_financing_sync(gint($_POST,'license_id',1,2147483647));
        if($plan===null)throw new InvalidArgumentException('Financing agreement not found.');
        flash('Financing state refreshed: '.$plan['state'].'.');
        redirect(admin_url('studio-licenses'));
    }elseif(in_array($action,['studio_license_create','studio_license_deactivate','studio_license_reactivate','studio_domain_add','studio_domain_deactivate','studio_domain_replace','studio_license_bundle'],true)&&!license_ready()){
        throw new RuntimeException('Prepare licensing storage first.');
    }elseif($action==='studio_license_legacy_review'){
        require_once CWP_CODE_ROOT.'/app/licenses/legacy.php';
        $resolved=license_resolve_legacy_invoice(gint($_POST,'invoice_id',1,2147483647),gint($_POST,'license_id',0,2147483647),posted('confirm',1)==='1',posted('evidence',200),posted('current_password',200));
        flash('Invoice linked to license '.(int)$resolved['id'].'. Existing activation and download settings were preserved; no email was sent.');redirect(admin_url('studio-licenses'));
    }elseif($action==='studio_license_create'){
        $customerId=gint($_POST,'customer_id',1,2147483647);$productId=posted('product_id',60);
        if($productId===''||!one('SELECT id FROM '.table('studio_clients').' WHERE id=?',[$customerId]))throw new InvalidArgumentException('Choose a client and product.');
        // A master key authorizes every domain — staff.manage is owner-only
        // (it is not a grantable permission), so only an owner mints one.
        if(isset($_POST['master'])&&$_POST['master']==='1')staff_require('staff.manage');
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
        $advanced=software_commerce_ready()&&array_key_exists('license_type',$_POST);
        if($advanced){
            require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
            $kind=posted('license_type',20);$licensee=posted('licensee_name',190);
            $master=$kind==='master';
            $purpose='';$masterPassword=null;
            if($master){
                staff_require('staff.manage');
                $masterPassword=posted('current_password',200);
                staff_owner($masterPassword); // refuse before opening the issuance transaction
                $purpose=posted('master_purpose',200);
            }
            $domain=$master?null:posted('domain',253);
            $raw=posted('expires_at',19);
            $expiry=$raw!==''?str_replace('T',' ',$raw).':00':null;
            if(db()->inTransaction())throw new LogicException('License issuance owns its transaction.');
            db()->beginTransaction();
            try{
                $result=software_grant_create($customerId,strtoupper($productId),$kind,$licensee,$domain,$expiry,
                    (int)$user['id'],null,null,$purpose,false,null,$masterPassword);
                db()->commit();$key=$result['key'];
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        }else{
            $manual=in_array($productId,['restaurant','salon','generic'],true);
            $catalog=one('SELECT sku,family FROM '.table('catalog_products').' WHERE sku=?',[$productId]);
            $registeredSoftware=$catalog&&(
                ($catalog['family']??'')==='software'
                ||preg_match('/^(?:SOFTWARE|APP)-[A-Z0-9_-]+$/D',(string)$catalog['sku'])===1
            );
            if($registeredSoftware){
                throw new InvalidArgumentException(software_commerce_ready()
                    ?'Use the software-license form for registered software products.'
                    :'Enable advanced software licensing before issuing this software product.');
            }
            $legacyWebsite=$catalog
                &&($catalog['family']??'')!=='software'
                &&preg_match('/^WEBSITE-[A-Z0-9_-]+$/D',(string)$catalog['sku'])===1;
            if(software_commerce_ready()&&!$manual&&!$legacyWebsite){
                throw new InvalidArgumentException('Use the software-license form for registered software products.');
            }
            $key=license_create($customerId,$productId,(int)$user['id'],isset($_POST['master'])&&$_POST['master']==='1');
        }
        $_SESSION['license_key_created']=$key;$target='studio-licenses';
    }elseif($action==='studio_license_deactivate'){
        require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
        $id=gint($_POST,'id',1,2147483647);if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$id]))throw new InvalidArgumentException('License not found.');
        if(software_commerce_ready()&&one('SELECT license_id FROM '.table('software_entitlements').' WHERE license_id=?',[$id])){
            throw new InvalidArgumentException('Use the explicit software suspend/revoke controls for this entitlement.');
        }
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        license_deactivate($id,(int)$user['id']);$target='studio-licenses';
    }elseif($action==='studio_license_reactivate'){
        require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
        $id=gint($_POST,'id',1,2147483647);if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$id]))throw new InvalidArgumentException('License not found.');
        if(software_commerce_ready()&&one('SELECT license_id FROM '.table('software_entitlements').' WHERE license_id=?',[$id])){
            throw new InvalidArgumentException('Use the explicit software resume/restore controls for this entitlement.');
        }
        require_once CWP_CODE_ROOT.'/app/licenses/service.php';
        license_reactivate($id,(int)$user['id']);flash('License reactivated — its registered sites come back online at their next validation.');$target='studio-licenses';
    }elseif($action==='studio_domain_add'){
        $licenseId=gint($_POST,'license_id',1,2147483647);$domain=posted('domain',255);
        if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$licenseId]))throw new InvalidArgumentException('License not found.');
        require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
        software_grant_staff_register_domain($licenseId,$domain,(int)$user['id']);$target='studio-licenses';
    }elseif($action==='studio_domain_deactivate'){
        $licenseId=gint($_POST,'license_id',1,2147483647);$domain=posted('domain',255);
        if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$licenseId]))throw new InvalidArgumentException('License not found.');
        require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
        software_grant_staff_deactivate_domain($licenseId,$domain,(int)$user['id']);$target='studio-licenses';
    }elseif($action==='studio_domain_replace'){
        $licenseId=gint($_POST,'license_id',1,2147483647);$old=posted('old_domain',255);$new=posted('new_domain',255);
        if(!one('SELECT id FROM '.table('website_licenses').' WHERE id=?',[$licenseId]))throw new InvalidArgumentException('License not found.');
        require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
        software_grant_staff_replace_domain($licenseId,$old,$new,(int)$user['id']);flash('Domain replaced — the old site goes offline at its next license check.');$target='studio-licenses';
    }elseif($action==='studio_license_bundle'){
        $id=gint($_POST,'id',1,2147483647);$license=one('SELECT * FROM '.table('website_licenses').' WHERE id=?',[$id]);
        if(!$license)throw new InvalidArgumentException('License not found.');
        if((int)$license['is_master']===1)throw new InvalidArgumentException('Master license keys are internal and cannot be bundled for delivery.');
        require_once CWP_CODE_ROOT.'/app/licenses/bundle.php';
        if(license_bundle_is_software_entitlement($id))throw new InvalidArgumentException('Software entitlements use versioned private releases instead of website bundles.');
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
