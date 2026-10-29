<?php
/** Authenticated client finance-agreement review and signature. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';
require_once CWP_CODE_ROOT.'/app/billing/finance_subscription.php';

function license_finance_money(int $cents):string{return '$'.number_format($cents/100,2,'.',',');}

function license_finance_portal_render(?array $client,string $id,string $error=''):never{
    if(!$client||support_current())fail_page('Sign in to your own account to review financing.',403);
    header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
    $financeGateway=null;
    if(license_finance_gateway_ready()){
        try{
            require_once CWP_CODE_ROOT.'/app/billing/finance_authorize_net.php';
            $financeGateway=license_finance_anet_settings();
            authorize_net_security_headers($financeGateway['mode']);
        }catch(Throwable){$financeGateway=null;}
    }
    $row=null;$terms=null;
    if($id!==''&&$id!=='1'){
        try{$row=license_finance_offer_row($id,(int)$client['id']);$terms=license_finance_offer_terms($row);}
        catch(Throwable $e){fail_page('Finance agreement unavailable.',404);}
    }
    site_head('Financing Agreement','portal');support_banner();
    echo '<section class="section"><div class="container"><p><a href="'.esc(studio_url()).'">Client workspace</a> / Financing</p><h1>Financing agreement</h1>';
    show_flash();if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    if(!license_finance_offers_ready()){
        echo '<p>Financing agreements are not available yet.</p></div></section>';site_foot(false);exit;
    }
    if($id===''||$id==='1'){
        $rows=license_finance_offer_list_client((int)$client['id']);
        if(!$rows)echo '<p>No financing agreements are currently assigned to your account.</p>';
        foreach($rows as $row){
            $state=license_finance_offer_status($row);
            echo '<article class="workspace-card"><span class="workspace-status">'.esc($state).'</span><h2>'.esc($row['title']).'</h2>';
            echo '<p><a class="btn outline" href="'.esc(studio_url(['finance'=>$row['id']])).'">Review agreement →</a></p></article>';
        }
        echo '</div></section>';site_foot(false);exit;
    }
    $state=license_finance_offer_status($row);
    $rights=$terms['rights'];
    echo '<article class="workspace-card"><span class="workspace-status">'.esc($state).'</span><h2>'.esc($terms['title']).'</h2>';
    echo '<p>'.esc($row['project_title']).' · License <code>'.esc(substr($row['license_key'],0,8)).'…</code></p>';
    echo '<div class="ledger-totals">';
    foreach([
        'Cash price'=>license_finance_money((int)$terms['cash_price_cents']),
        'Initial payment'=>license_finance_money((int)$terms['initial_payment_cents']),
        'Amount financed'=>license_finance_money((int)$terms['amount_financed_cents']),
        'Finance charge'=>license_finance_money((int)$terms['finance_charge_cents']),
        'Total customer cost'=>license_finance_money((int)$terms['total_customer_cost_cents']),
        'Stated APR'=>license_finance_apr_label((int)$terms['apr_basis_points']),
    ] as $label=>$value)echo '<div><small>'.esc($label).'</small><strong>'.esc($value).'</strong></div>';
    echo '</div>';
    echo '<h3>Payment schedule</h3><p>'.(int)$terms['installments'].' '.esc($terms['cadence']).' installment'.((int)$terms['installments']===1?'':'s').' of <strong>'.esc(license_finance_money((int)$terms['installment_amount_cents'])).'</strong>. First scheduled period begins '.esc($terms['anchor']).'. Invoice due dates use the retained '.(int)$terms['due_after_days'].'-day invoice term and '.(int)$terms['grace_days'].'-day license grace period.</p>';
    echo '<p><strong>Domain:</strong> the license remains attached to your CajunWebPro customer account. You may move the approved domain through the client portal, but changing domains does not transfer ownership of the license.</p>';
    $rightsText=[];
    if($rights['resale'])$rightsText[]=$rights['exclusive_resale']?'exclusive resale':'resale';
    if($rights['distribution'])$rightsText[]=$rights['exclusive_distribution']?'exclusive distribution':'distribution';
    echo '<p><strong>Signed agreement rights:</strong> '.esc($rightsText?implode(' + ',$rightsText).' rights expressly granted':'site-use only; resale and distribution are not granted').'.</p>';
    echo '<h3>Agreement terms</h3><div class="prose">'.nl2br(esc($terms['agreement_text'])).'</div>';
    echo '<p class="hint">Agreement fingerprint: <code>'.esc($row['terms_sha256']).'</code>. Signing records your authenticated client account, typed signer name, account email, this exact fingerprint and the UTC signing time.</p>';
    if($state==='offered'){
        echo '<form method="post">'.support_csrf_field().'<input type="hidden" name="action" value="finance_offer_sign"><input type="hidden" name="finance_id" value="'.esc($row['id']).'">';
        echo '<label>Type your legal/authorized signer name<input name="signer_name" maxlength="190" required></label>';
        echo '<label class="check"><input type="checkbox" name="agree_terms" value="1" required> I have reviewed and agree to these exact financing and payment terms.</label>';
        echo '<label class="check"><input type="checkbox" name="agree_electronic" value="1" required> I consent to sign and receive this agreement electronically through my authenticated client account.</label>';
        echo '<button class="btn gold">Sign agreement & continue to payment</button></form>';
        echo '<details><summary>Decline this agreement</summary><form method="post">'.support_csrf_field().'<input type="hidden" name="action" value="finance_offer_decline"><input type="hidden" name="finance_id" value="'.esc($row['id']).'"><label>Reason<textarea name="reason" maxlength="500" required></textarea></label><button class="btn outline">Decline agreement</button></form></details>';
    }elseif($state==='signed'){
        echo '<p class="notice info">Signed by '.esc((string)$row['signed_name']).' using '.esc((string)$row['signed_email']).' on '.esc((string)$row['signed_at']).' UTC.</p>';
        $financeSub=license_finance_gateway_ready()?license_finance_subscription_row((string)$row['id']):null;
        $financePlan=license_financing_sync((int)$row['license_id']);
        if($financePlan){
            echo '<p><strong>License financing:</strong> '.esc(ucwords(str_replace('_',' ',(string)$financePlan['state']))).($financePlan['paid_off_at']!==null?' · permanently paid off '.esc((string)$financePlan['paid_off_at']).' UTC':'').'</p>';
            echo '<p><strong>Current commercial rights:</strong> '.esc(license_financing_rights_label($financePlan)).'.</p>';
            $rightsGrants=license_financing_rights_grants_table_present()
                ? all('SELECT invoice_id,allow_resale,allow_distribution,exclusive_resale,exclusive_distribution,created_at FROM '.table('license_financing_rights_grants').' WHERE license_id=? ORDER BY id DESC LIMIT 10',
                    [$financePlan['license_id']])
                : [];
            if($rightsGrants){
                echo '<details><summary>Paid commercial-rights evidence</summary><ul>';
                foreach($rightsGrants as $grant){
                    $grantRights=license_financing_rights(
                        (int)$grant['allow_resale']===1,(int)$grant['allow_distribution']===1,
                        (int)$grant['exclusive_resale']===1,(int)$grant['exclusive_distribution']===1
                    );
                    $parts=[];
                    if($grantRights['resale'])$parts[]=$grantRights['exclusive_resale']?'exclusive resale':'resale';
                    if($grantRights['distribution'])$parts[]=$grantRights['exclusive_distribution']?'exclusive distribution':'distribution';
                    echo '<li>Invoice #'.(int)$grant['invoice_id'].' · '.esc($parts?implode(' + ',$parts):'no additional rights').' · '.esc((string)$grant['created_at']).' UTC</li>';
                }
                echo '</ul></details>';
            }
        }
        if($financeSub)echo '<p><strong>Automatic installments:</strong> '.esc(ucwords(str_replace('_',' ',(string)$financeSub['status']))).($financeSub['gateway_subscription_id']!==null?' · gateway subscription '.esc((string)$financeSub['gateway_subscription_id']):'').'</p>';
        if($financeSub&&license_financing_recovery_link_allowed((string)$financeSub['status'])){
            $recovery=one('SELECT p.* FROM '.table('payment_requests').' p JOIN '.table('recurring_billing_invoices').' r ON r.invoice_id=p.invoice_id WHERE r.schedule_id=? AND p.purpose=? AND p.status=? ORDER BY r.period_index,p.id DESC LIMIT 1',
                [$row['schedule_id'],'financing','prepared']);
            $recoveryUrl=null;
            if($recovery){
                try{$recoveryUrl=payments_link_url($recovery);}catch(Throwable){$recoveryUrl=null;}
                if($recoveryUrl!==null){
                    $label=match((string)$financeSub['status']){
                        'cancelled'=>'Automatic financing billing is cancelled.',
                        'reversal_recovery'=>'A prior financing payment was fully refunded or voided.',
                        default=>'Automatic financing billing is suspended.'
                    };
                    echo '<p class="notice warning">'.esc($label).' <a href="'.esc($recoveryUrl).'">Pay the outstanding installment securely</a> to restore financing access.</p>';
                }
            }
            if($financeSub['status']==='billing_suspended'&&$recoveryUrl===null&&$financeGateway&&$financePlan&&in_array((string)$financePlan['state'],['active','grace'],true)){
                try{$replacement=license_finance_subscription_replacement_contract($row);}catch(Throwable){$replacement=null;}
                if($replacement){
                    echo '<h3>Restore automatic installment payments</h3>';
                    echo '<p class="notice info">Your issued installment is caught up. CajunWebPro will confirm that the prior subscription is permanently retired, preserve it in history, and create a replacement only for the remaining signed installments.</p>';
                    echo '<p class="hint">Replacement begins '.esc($replacement['start_date']).' and covers '.(int)$replacement['occurrences'].' remaining installment'.((int)$replacement['occurrences']===1?'':'s').'. The signed financing agreement, amount, cadence, license key, and customer remain unchanged.</p>';
                    echo '<form method="post" data-authorize-net data-login="'.esc($financeGateway['login']).'" data-client-key="'.esc($financeGateway['client_key']).'">'.support_csrf_field();
                    echo '<input type="hidden" name="action" value="finance_subscription_reenroll"><input type="hidden" name="finance_id" value="'.esc($row['id']).'">';
                    echo '<input type="hidden" name="descriptor"><input type="hidden" name="payment_token">';
                    echo '<label>Card number<input data-card-number autocomplete="cc-number" inputmode="numeric" minlength="13" maxlength="23" required></label>';
                    echo '<label>Expiration month (MM)<input data-card-month autocomplete="cc-exp-month" inputmode="numeric" pattern="[0-9]{2}" maxlength="2" required></label>';
                    echo '<label>Expiration year (YY)<input data-card-year autocomplete="cc-exp-year" inputmode="numeric" pattern="[0-9]{2}" maxlength="2" required></label>';
                    echo '<label>Security code<input data-card-code autocomplete="cc-csc" inputmode="numeric" minlength="3" maxlength="4" required></label>';
                    echo '<p class="hint">No same-day installment is charged by this form. The replacement starts at the first future unpaid period retained in your signed schedule.</p>';
                    if($financeGateway['mode']==='sandbox')echo '<p class="notice info">Sandbox only. No real financing payment will be settled.</p>';
                    echo '<p role="alert" data-payment-error></p><button class="btn gold" type="submit">Restore automatic installments</button></form>';
                }
            }
        }elseif($financeSub&&in_array((string)$financeSub['status'],['replacing','review'],true)){
            echo '<p class="notice warning">Automatic financing enrollment is being reconciled. Do not submit another card until billing review is complete.</p>';
        }
        $financePaidOff=$financePlan&&($financePlan['paid_off_at']!==null||(string)$financePlan['state']==='paid_off');
        try{$initialSatisfied=license_finance_offer_initial_paid($row);}catch(Throwable){$initialSatisfied=false;}
        if((int)$terms['initial_payment_cents']>0&&!$financePaidOff){
            if($initialSatisfied){
                echo '<p class="notice info">Initial payment is verified. You can now enroll the remaining signed installments.</p>';
            }else{
                $url=null;
                if($row['payment_request_id']!==null){
                    try{$request=one('SELECT * FROM '.table('payment_requests').' WHERE id=?',[$row['payment_request_id']]);$url=$request?payments_link_url($request):null;}catch(Throwable){}
                }
                if($url!==null)echo '<p><a class="btn gold" href="'.esc($url).'">Continue to initial payment →</a></p>';
                elseif(!$financeSub)echo '<form method="post">'.support_csrf_field().'<input type="hidden" name="action" value="finance_offer_payment"><input type="hidden" name="finance_id" value="'.esc($row['id']).'"><button class="btn gold">Prepare initial payment page</button></form>';
            }
        }
        if(!$financeSub&&$initialSatisfied&&!$financePaidOff){
            if($financeGateway){
                echo '<h3>Set up automatic installment payments</h3>';
                echo '<form method="post" data-authorize-net data-login="'.esc($financeGateway['login']).'" data-client-key="'.esc($financeGateway['client_key']).'">'.support_csrf_field();
                echo '<input type="hidden" name="action" value="finance_subscription_enroll"><input type="hidden" name="finance_id" value="'.esc($row['id']).'">';
                echo '<input type="hidden" name="descriptor"><input type="hidden" name="payment_token">';
                echo '<label>Card number<input data-card-number autocomplete="cc-number" inputmode="numeric" minlength="13" maxlength="23" required></label>';
                echo '<label>Expiration month (MM)<input data-card-month autocomplete="cc-exp-month" inputmode="numeric" pattern="[0-9]{2}" maxlength="2" required></label>';
                echo '<label>Expiration year (YY)<input data-card-year autocomplete="cc-exp-year" inputmode="numeric" pattern="[0-9]{2}" maxlength="2" required></label>';
                echo '<label>Security code<input data-card-code autocomplete="cc-csc" inputmode="numeric" minlength="3" maxlength="4" required></label>';
                echo '<p class="hint">This token enrolls the exact signed installment amount, cadence, start date, and finite occurrence count. It does not create a same-day charge.</p>';
                if($financeGateway['mode']==='sandbox')echo '<p class="notice info">Sandbox only. No real financing payment will be settled.</p>';
                echo '<p role="alert" data-payment-error></p><button class="btn gold" type="submit">Set up automatic installments</button></form>';
            }else echo '<p class="notice warning">Automatic installment enrollment is not available yet. Contact the studio before the first scheduled payment date.</p>';
        }
    }elseif($state==='declined')echo '<p>This agreement was declined. Contact the studio if you want revised terms.</p>';
    elseif($state==='withdrawn')echo '<p>The studio withdrew this agreement. Contact the studio for current terms.</p>';
    elseif($state==='expired')echo '<p>This agreement expired before signature. Contact the studio for a new offer.</p>';
    echo '<p><button class="btn outline" type="button" onclick="window.print()">Print / save agreement</button></p></article>';
    if($financeGateway){
        $external=$financeGateway['mode']==='sandbox'?'https://jstest.authorize.net/v1/Accept.js':'https://js.authorize.net/v1/Accept.js';
        echo '<script nonce="'.nonce().'" defer src="'.esc($external).'"></script><script nonce="'.nonce().'" defer src="'.esc(site_url('assets/js/authorize-net-checkout.js')).'"></script>';
    }
    echo '</div></section>';site_foot(false);exit;
}
