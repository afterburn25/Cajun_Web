<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements_views.php';
require_once CWP_CODE_ROOT.'/includes/layout/plans.php';
provider_settings_manager();service_plans_owner();
?>
<section class="workspace-card"><h2>Renewal service terms</h2><p>Link a pending one-time USD service agreement to this client’s exact renewal request. Prices are entered by the studio. Registry eligibility, provider cost, payment and submitting the renewal are separate.</p>
<?php if(!domain_renewal_agreements_ready()):?><form method="post"><?php admin_action('provider_inventory_renewal_agreements_prepare');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Enable renewal agreements</button></form>
<?php else:$requestId=(string)($_GET['request']??'');$screen=domain_renewal_agreements_owner_request($requestId,max(1,(int)($_GET['offer_page']??1)));$history=$screen['history'];$request=$history['request'];$r=$request['request']; ?>
<h3><?=esc($r['domain'])?></h3><p><?=esc((string)$r['requested_years'])?> requested year<?= $r['requested_years']===1?'':'s' ?> · Request <?=esc(str_replace('_',' ',$request['state']))?></p>
<p>Requested privacy: <?= $r['renew_privacy']===null?'Studio recommendation requested':($r['renew_privacy']?'Renew privacy':'Do not renew privacy') ?>.</p>
<?php foreach($history['offers'] as $offer):$b=$offer['binding']->data();$agreement=$offer['agreement'];?><p><a href="<?=esc(admin_url('service-agreement',['record'=>$b['agreement_id']]))?>">Offer <?=esc((string)$b['revision'])?> · USD <?=esc(number_format($b['price_minor']/100,2))?> · <?=esc(service_plans_agreement_label($agreement))?></a> · accept by <?=esc(gmdate('Y-m-d H:i',$b['expires_epoch']))?> UTC</p><?php endforeach;?>
<?php if(!$screen['can_offer']):?><p class="notice">Review the current registered-domain assignment and pending request before offering terms. End any earlier agreement first; its history will remain here.</p><?php else:?>
<p><a class="btn outline" href="<?=esc(admin_url('service-plans',['client'=>$r['client_id']]))?>">Create or offer one-time service terms</a></p>
<?php if(!$screen['candidates']):?><p>No unconsented one-time USD agreements on this page. Offer the client a one-time service plan, then return here to link its exact terms.</p><?php endif;?>
<?php foreach($screen['candidates'] as $candidate):$c=$candidate->data();$values=domain_renewal_agreements_form($candidate,$request,$screen['previous_sha256'],$_POST);?><article class="workspace-card"><?php service_plans_terms_view(ServicePlanDocument::fromJson(service_plans_json($c['plan'])));?><form method="post"><?php admin_action('provider_inventory_renewal_agreement_bind');foreach(array_diff_key($values,array_flip(['offer_expires','renew_privacy'])) as $key=>$value):?><input type="hidden" name="<?=esc($key)?>" value="<?=esc($value)?>"><?php endforeach;?>
<label>Quoted privacy scope<select name="renew_privacy" required><?php if($r['renew_privacy']===null):?><option value="">Choose the scope to quote</option><?php endif;?><?php if($r['renew_privacy']!==false):?><option value="1"<?= $values['renew_privacy']==='1'?' selected':'' ?>>Include renewal of privacy</option><?php endif;?><?php if($r['renew_privacy']!==true):?><option value="0"<?= $values['renew_privacy']==='0'?' selected':'' ?>>Do not renew privacy</option><?php endif;?></select></label>
<?php admin_input('offer_expires','Accept by (UTC)',$values['offer_expires'],'datetime-local','required');admin_input('current_password','Your current owner password','','password','required autocomplete="current-password"');?>
<label class="check"><input type="checkbox" name="confirm" value="1" required> I reviewed this exact domain, requested years, privacy scope and service terms.</label><button class="btn gold">Offer these renewal terms</button></form></article><?php endforeach;?>
<nav class="button-row" aria-label="Pending agreement pages"><?php if($screen['page']>1):?><a class="btn outline" href="<?=esc(admin_url('domain-renewal-agreements',['request'=>$requestId,'offer_page'=>$screen['page']-1]))?>">Newer agreements</a><?php endif;?><?php if($screen['page']<10000):?><a class="btn outline" href="<?=esc(admin_url('domain-renewal-agreements',['request'=>$requestId,'offer_page'=>$screen['page']+1]))?>">Older agreements</a><?php endif;?></nav>
<?php endif;endif;?></section>
