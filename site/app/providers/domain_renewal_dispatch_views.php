<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
/** Every layout receives bounded public projections; no provider or payment work occurs while rendering. */
function domain_renewal_dispatch_status(?array $dispatch):string {
    return match($dispatch['status']??'awaiting_review'){
      'provider_reported_success'=>'20i reported this renewal successful.',
      'provider_reported_failure'=>'20i reported this renewal failed. This does not prove no balance charge occurred. The studio must investigate before any further renewal.',
      'not_sent'=>'No provider dispatch was started. This approval is closed and needs owner review.',
      'failed'=>'The provider request was rejected. This does not prove no balance charge occurred. The studio must investigate before any further renewal.',
      'uncertain'=>'The renewal outcome is unconfirmed. It may have been applied and charged. The studio must verify it before any further renewal.',
      default=>'Awaiting separate payment verification and owner approval. No renewal has been submitted through this allocation.',
    };
}
function domain_renewal_dispatch_card(array $c):void {
    ?><section class="workspace-card renewal-invoice-purpose"><h3>Domain renewal invoice purpose</h3><dl class="details-grid"><div><dt>Registered domain</dt><dd><?=esc($c['domain'])?></dd></div><div><dt>Renewal term</dt><dd><?=esc((string)$c['years'])?> year<?= $c['years']===1?'':'s' ?></dd></div><div><dt>Privacy choice</dt><dd><?= $c['renew_privacy']?'Include renewal of privacy':'Do not renew privacy' ?></dd></div><div><dt>Agreed studio fee</dt><dd>USD <?=esc(number_format($c['price_minor']/100,2,'.',','))?> · one time</dd></div><div><dt>Reserved invoice</dt><dd><?=esc($c['invoice_reference'])?></dd></div></dl><p>This invoice is permanently reserved for these accepted renewal terms. Payment does not submit the renewal or confirm registry eligibility. The studio separately approves any charge to its 20i balance.</p><p><?=esc(domain_renewal_dispatch_status($c['dispatch']))?></p><?php if(in_array($c['agreement_state'],['cancelled','abandoned','paused'],true)):?><p class="notice">This agreement is <?=esc($c['agreement_state'])?>. Contact the studio before paying. Its original invoice purpose and payment history remain recorded.</p><?php endif;?></section><?php
}
/** Retry forms keep their captured scope. Invalid posted fields never silently adopt fresh approval. */
function domain_renewal_dispatch_form(string $action,array $initial,array $input):array {
    if(($input['action']??null)!==$action)return $initial;foreach($initial as $key=>$value){$v=$input[$key]??'';$initial[$key]=is_string($v)&&strlen($v)<=128&&preg_match('//u',$v)===1&&!str_contains($v,"\0")?$v:'';}return $initial;
}
/** Project options carry their reviewed identity; a failed POST never selects a replacement. */
function domain_renewal_dispatch_creation_view(array $c,array $input=[]):void {
    $action='provider_renewal_dispatch_create_invoice';$retry=($input['action']??null)===$action;$creation=$c['create_invoice']??null;$projects=$c['projects']??[];
    $matched=false;
    if($retry&&is_string($input['project_choice']??null))foreach($projects as $project)if($input['project_choice']===$project['id'].':'.$project['sha256'])$matched=true;
    $current=$c['offer']??[];$request=$c['request']['request']['id']??null;
    $scopeMatches=($input['request_id']??null)===$request&&($input['binding_sha256']??null)===($current['binding_sha256']??null)&&($input['agreement_sha256']??null)===($current['agreement_sha256']??null);
    if($retry&&(!$matched||$creation===null||!$scopeMatches)){
        echo '<p class="notice error">The submitted project or renewal terms are no longer available in this review. Reload this page and review the current terms before creating an invoice.</p>';return;
    }
    if($creation===null)return;
    $r=$c['request']['request'];$o=$c['offer'];$page=$c['project_page']??1;
    if($projects){
        $f=domain_renewal_dispatch_form($action,['allocation_id'=>bin2hex(random_bytes(16)),'request_id'=>$r['id'],'binding_sha256'=>$o['binding_sha256'],'agreement_sha256'=>$o['agreement_sha256'],'project_choice'=>''],$input);
        ?><form method="post" class="renewal-invoice-creation"><?=csrf_field()?><input type="hidden" name="action" value="<?=esc($action)?>">
        <?php foreach($f as $key=>$value)if($key!=='project_choice'):?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endif;?>
        <h3>Create the renewal invoice</h3><p><strong>USD <?=esc(number_format($o['price_minor']/100,2,'.',','))?></strong> for <?=esc($o['domain'])?>, <?=esc((string)$o['years'])?> year(s), <?= $o['renew_privacy']?'including privacy renewal':'without privacy renewal' ?>.</p>
        <label>Client project<select name="project_choice" required><option value="">Choose a project</option><?php foreach($projects as $project):$choice=$project['id'].':'.$project['sha256'];?><option value="<?=esc($choice)?>"<?= $f['project_choice']===$choice?' selected':'' ?>><?=esc($project['title'])?></option><?php endforeach;?></select></label>
        <p>The invoice will appear in this client’s project. Its fee and renewal term come from the accepted agreement.</p>
        <label><input type="checkbox" name="create_invoice_confirmed" value="1" required> Create one invoice and reserve it for these accepted renewal terms.</label>
        <label>Current owner password<input type="password" name="owner_password" autocomplete="current-password" required></label><button class="btn">Create renewal invoice</button></form><?php
    }else{echo '<p>No client projects are shown on this page. Add a project for this client or choose another project page before creating the invoice.</p>';}
    if($page>1||($c['projects_has_more']??false)){
        echo '<nav class="renewal-project-pages" aria-label="Renewal invoice project pages">';
        if($page>1)echo '<a href="'.esc(admin_url('domain-renewal-dispatch',['request'=>$r['id'],'project_page'=>$page-1,'invoice_page'=>$c['page']])).'">Newer projects</a> ';
        if($c['projects_has_more']??false)echo '<a href="'.esc(admin_url('domain-renewal-dispatch',['request'=>$r['id'],'project_page'=>$page+1,'invoice_page'=>$c['page']])).'">Older projects</a>';
        echo '</nav>';
    }
}
function domain_renewal_dispatch_owner_view(array $c,array $input=[]):void {
    $retry=$input['action']??null;$sendMatch=false;$invoiceMatch=false;
    if($retry==='provider_renewal_dispatch_send')foreach($c['allocations'] as $a)if($a['review_sha256']!==null&&is_string($input['allocation_id']??null)&&$input['allocation_id']===$a['public']['allocation_id'])$sendMatch=true;
    if($retry==='provider_renewal_dispatch_allocate')foreach($c['invoices'] as $i)if(is_string($input['invoice_id']??null)&&$input['invoice_id']===(string)$i['id'])$invoiceMatch=true;
    $badSend=$retry==='provider_renewal_dispatch_send'&&!$sendMatch;$badInvoice=$retry==='provider_renewal_dispatch_allocate'&&!$invoiceMatch;
    $r=$c['request']['request'];?><section class="workspace-card renewal-dispatch-owner"><h2>Arrange this domain renewal</h2><p>Renewal request for <strong><?=esc($r['domain'])?></strong>. Create an invoice for the accepted one-time USD fee or reserve an existing unpaid invoice. Payment verification and renewal approval are separate steps.</p>
    <?php if(!$c['prepared']):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="provider_renewal_dispatch_prepare"><label>Current owner password<input type="password" name="owner_password" autocomplete="current-password" required></label><button>Prepare renewal payment and dispatch records</button></form><?php else:?>
    <?php if($c['message']!==null):?><p class="notice"><?=esc($c['message'])?></p><?php endif;?>
    <?php if($badSend||$badInvoice):?><p class="notice error">The submitted invoice or allocation is no longer available in this review. Reload this page and review its current terms before starting a new action.</p><?php endif;?>
    <?php domain_renewal_dispatch_creation_view($c,$input);?>
    <?php foreach($c['allocations'] as $a):domain_renewal_dispatch_card($a['public']);?><p><a href="<?=esc(admin_url('invoice-payments',['invoice_id'=>$a['public']['invoice_id']]))?>">Review invoice and payment</a> · <a href="<?=esc(admin_url('payments',['invoice_id'=>$a['public']['invoice_id']]))?>">Prepare payment request</a></p><?php if($a['message']!==null):?><p class="notice"><?=esc($a['message'])?></p><?php endif;if($a['review_sha256']!==null&&!$badSend):$f=domain_renewal_dispatch_form('provider_renewal_dispatch_send',['dispatch_id'=>bin2hex(random_bytes(16)),'allocation_id'=>$a['public']['allocation_id'],'review_sha256'=>$a['review_sha256']],($input['allocation_id']??null)===$a['public']['allocation_id']?$input:[]);?>
      <form method="post" class="renewal-dispatch-approval"><?=csrf_field()?><input type="hidden" name="action" value="provider_renewal_dispatch_send"><?php foreach($f as $key=>$value):?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach;?><h3>Approve one renewal submission</h3><p>This submits <?=esc((string)$a['public']['years'])?> year(s) for <?=esc($a['public']['domain'])?> with <?= $a['public']['renew_privacy']?'privacy renewal included':'no privacy renewal' ?>. 20i charges the studio’s balance. The studio fee above is not a provider cost quote.</p>
      <?php foreach(['eligible_years'=>'I verified that this domain is currently eligible for the exact requested renewal term.','privacy_scope'=>'I verified the exact agreed privacy choice is supported.','provider_cost'=>'I checked the current provider cost and accept it; this site has no verified automatic renewal quote.','balance_charge'=>'I authorize this one charge to the studio’s 20i balance.'] as $key=>$label):?><label><input type="checkbox" name="<?=esc($key)?>" value="1" required> <?=esc($label)?></label><?php endforeach;?><p>An unconfirmed or failed outcome is not a no-charge confirmation. This approval cannot be resent.</p><label>Current owner password<input type="password" name="owner_password" autocomplete="current-password" required></label><button>Approve and submit this renewal once</button></form>
    <?php endif;endforeach;?>
    <?php foreach($badInvoice?[]:$c['invoices'] as $i):$o=$c['offer'];$f=domain_renewal_dispatch_form('provider_renewal_dispatch_allocate',['allocation_id'=>bin2hex(random_bytes(16)),'request_id'=>$r['id'],'binding_sha256'=>$o['binding_sha256'],'agreement_sha256'=>$o['agreement_sha256'],'invoice_id'=>(string)$i['id'],'invoice_sha256'=>$i['sha256']],($input['invoice_id']??null)===(string)$i['id']?$input:[]);?>
      <form method="post" class="renewal-invoice-allocation"><?=csrf_field()?><input type="hidden" name="action" value="provider_renewal_dispatch_allocate"><?php foreach($f as $key=>$value):?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach;?><h3>Reserve <?=esc($i['reference'])?></h3><p><?=esc($i['title'])?> · USD <?=esc(number_format($i['amount_minor']/100,2,'.',','))?> for <?=esc($o['domain'])?>, <?=esc((string)$o['years'])?> year(s), <?= $o['renew_privacy']?'including privacy renewal':'without privacy renewal' ?>.</p><label><input type="checkbox" name="allocation_confirmed" value="1" required> Reserve this unpaid invoice permanently for these exact accepted renewal terms before any payment activity.</label><label>Current owner password<input type="password" name="owner_password" autocomplete="current-password" required></label><button>Reserve invoice for this renewal</button></form>
    <?php endforeach;if(($c['create_invoice']??null)!==null&&!$c['invoices']):?><p>No matching existing unpaid invoice is shown on this page.</p><?php endif;?>
    <?php endif;?></section><?php
}
