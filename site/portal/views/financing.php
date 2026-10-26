<?php
/** Authenticated client finance-agreement review and signature. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/licenses/financing_offers.php';

function license_finance_money(int $cents):string{return '$'.number_format($cents/100,2,'.',',');}

function license_finance_portal_render(?array $client,string $id,string $error=''):never{
    if(!$client||support_current())fail_page('Sign in to your own account to review financing.',403);
    header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');
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
    try{$row=license_finance_offer_row($id,(int)$client['id']);$terms=license_finance_offer_terms($row);}
    catch(Throwable $e){http_response_code(404);echo '<p class="notice error">Finance agreement unavailable.</p></div></section>';site_foot(false);exit;}
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
    echo '<p><strong>Commercial rights:</strong> '.esc($rightsText?implode(' + ',$rightsText).' rights expressly granted':'site-use only; resale and distribution are not granted').'.</p>';
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
        if((int)$terms['initial_payment_cents']>0){
            $url=null;
            if($row['payment_request_id']!==null){
                try{$request=one('SELECT * FROM '.table('payment_requests').' WHERE id=?',[$row['payment_request_id']]);$url=$request?payments_link_url($request):null;}catch(Throwable){}
            }
            if($url!==null)echo '<p><a class="btn gold" href="'.esc($url).'">Continue to payment →</a></p>';
            else echo '<form method="post">'.support_csrf_field().'<input type="hidden" name="action" value="finance_offer_payment"><input type="hidden" name="finance_id" value="'.esc($row['id']).'"><button class="btn gold">Prepare current payment page</button></form>';
        }else echo '<p class="notice info">No initial payment is due. Your signed installment schedule is active.</p>';
    }elseif($state==='declined')echo '<p>This agreement was declined. Contact the studio if you want revised terms.</p>';
    elseif($state==='withdrawn')echo '<p>The studio withdrew this agreement. Contact the studio for current terms.</p>';
    elseif($state==='expired')echo '<p>This agreement expired before signature. Contact the studio for a new offer.</p>';
    echo '<p><button class="btn outline" type="button" onclick="window.print()">Print / save agreement</button></p></article>';
    echo '</div></section>';site_foot(false);exit;
}
