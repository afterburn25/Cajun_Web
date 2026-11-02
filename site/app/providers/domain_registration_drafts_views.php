<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_registration_drafts.php';
use CajunWebPro\Foundation\TwentyIDomainRegistration\RegistrationDraft;

function registration_drafts_text(#[SensitiveParameter] array $input,string $name,int $limit):string {
    $v=$input[$name]??'';if(!is_string($v)||strlen($v)>$limit||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new InvalidArgumentException('Review the draft field: '.str_replace('_',' ',$name).'.');return $v;
}
function registration_drafts_number(array $input,string $name,int $maximum,bool $zero=false):int {
    $v=registration_drafts_text($input,$name,10);if(!preg_match($zero?'/\A(?:0|[1-9][0-9]{0,9})\z/':'/\A[1-9][0-9]{0,9}\z/',$v)||(int)$v>$maximum)throw new InvalidArgumentException('Reload the draft form and review its requested term.');return (int)$v;
}
function registration_drafts_posted_content(#[SensitiveParameter] array $input,int $client):RegistrationDraft {
    $contact=[];foreach(['organisation'=>200,'name'=>200,'address'=>1024,'telephone'=>64,'email'=>254,'cc'=>2,'pc'=>64,'sp'=>200,'city'=>200] as $field=>$limit)$contact[$field]=registration_drafts_text($input,'contact_'.$field,$limit);$contact['extension']=[];
    $privacy=registration_drafts_text($input,'privacy_service',1);if(!in_array($privacy,['0','1'],true))throw new InvalidArgumentException('Choose whether to request privacy.');
    $ns=$input['nameservers']??null;if(!is_string($ns)||strlen($ns)>3500||preg_match('//u',$ns)!==1||str_contains($ns,"\0"))throw new InvalidArgumentException('Enter the explicit nameservers, one per line.');
    $names=explode("\n",str_replace("\r\n","\n",$ns));$names=array_map('trim',$names);
    return RegistrationDraft::fromArray(['version'=>1,'id'=>registration_drafts_text($input,'draft_id',32),'client_id'=>$client,'domain'=>registration_drafts_text($input,'domain',253),'requested_years'=>registration_drafts_number($input,'requested_years',10),'privacy_service'=>$privacy==='1','contact'=>$contact,'nameservers'=>$names,'registry_acceptance'=>null]);
}
/** Bounded retry projection never substitutes new authority for rejected captured fields. */
function registration_drafts_form(array $initial,array $input,string $action):?array {
    if($input===[])return $initial;
    if(($input['action']??null)!==$action||!is_string($input['draft_id']??null)||$input['draft_id']!==$initial['draft_id'])return null;
    foreach($initial as $key=>$value){$v=$input[$key]??'';$initial[$key]=is_string($v)&&strlen($v)<=128&&preg_match('//u',$v)===1&&!preg_match('/[\x00-\x1f\x7f]/',$v)?$v:'';}return $initial;
}
function registration_drafts_retry_text(#[SensitiveParameter] array $input,string $key,string $fallback,int $limit=3500):string {
    if($input===[])return $fallback;$v=$input[$key]??'';return is_string($v)&&strlen($v)<=$limit&&preg_match('//u',$v)===1&&!str_contains($v,"\0")?$v:'';
}
function registration_drafts_url(bool $owner,array $query=[]):string {return $owner?admin_url('registration-drafts',$query):studio_url(['registration_drafts'=>1]+$query);}
function registration_drafts_hidden(array $fields,string $action):void {
    echo csrf_field().'<input type="hidden" name="action" value="'.esc($action).'">';foreach($fields as $key=>$value)echo '<input type="hidden" name="'.esc($key).'" value="'.esc((string)$value).'">';
}
function registration_drafts_editor(bool $owner,array $context,#[SensitiveParameter] array $input=[]):void {
    $draft=$context['content']??null;$data=$draft?registration_drafts_content($draft):null;$summary=$context['summary']??null;
    $id=$summary['id']??$context['new_id'];$client=$summary['client_id']??$context['client_id'];$revision=$summary['revision']??0;$head=$summary['sha256']??str_repeat('0',64);
    $prefix=$owner?'registration_drafts_owner_':'registration_drafts_client_';$initial=['draft_id'=>$id,'expected_revision'=>(string)$revision,'expected_head'=>$head,'command_id'=>bin2hex(random_bytes(16))];if($owner)$initial+=['client_id'=>(string)$client,'client_version'=>$context['client_version']];
    ?><section class="workspace-card registration-draft-editor"><h2><?= $draft?'Edit private draft':'Create a private draft' ?></h2><p>Save the domain, requested term and contact details for studio review. Availability, price, registry eligibility and purchase are separate. Nothing here registers a domain or authorizes a payment.</p>
    <?php if($owner):?><p>Client: <?=esc($context['client_name'])?>. Owner edits are recorded and require the client to submit the revised draft.</p><?php endif;?>
    <?php if($summary):?><p>Current status: <strong><?=esc(ucfirst($summary['state']))?></strong> · Revision <?=esc((string)$revision)?></p><?php endif;?>
    <?php if($input!==[]):?><p class="notice">The submitted form was not completed. Its captured draft revision is retained below where valid. <a href="<?=esc(registration_drafts_url($owner,$summary?['draft'=>$id]:($owner?['client'=>$client]:[])))?>">Reload the current draft</a> before starting a different action.</p><?php endif;?>
    <?php $form=registration_drafts_form($initial,$input,$prefix.'save');if($form!==null):$values=($input['action']??null)===$prefix.'save'?$input:[];?>
    <form method="post" class="registration-draft-save"><?php registration_drafts_hidden($form,$prefix.'save');?>
      <div class="form-grid"><label>Domain name<input name="domain" maxlength="253" value="<?=esc(registration_drafts_retry_text($values,'domain',$data['domain']??''))?>" placeholder="example.com" required></label><label>Requested years<input name="requested_years" type="number" min="1" max="10" value="<?=esc(registration_drafts_retry_text($values,'requested_years',isset($data['requested_years'])?(string)$data['requested_years']:''))?>" required></label></div>
      <label>Requested privacy<select name="privacy_service" required><?php $privacy=registration_drafts_retry_text($values,'privacy_service',$data===null?'':($data['privacy_service']?'1':'0'));?><option value="" <?= $privacy===''?'selected':'' ?>>Choose a preference</option><option value="1" <?= $privacy==='1'?'selected':'' ?>>Request privacy service</option><option value="0" <?= $privacy==='0'?'selected':'' ?>>Do not request privacy service</option></select></label><p>Requested years and privacy are preferences for review. Supported registry terms, eligibility and fees have not been confirmed.</p>
      <h3>Private registrant contact</h3><div class="form-grid">
      <?php foreach(['organisation'=>['Organization (optional)',200],'name'=>['Full name',200],'telephone'=>['Telephone including country code',64],'email'=>['Email',254],'cc'=>['Country code (two uppercase letters)',2],'pc'=>['Postal code (if applicable)',64],'sp'=>['State or province (if applicable)',200],'city'=>['City',200]] as $field=>[$label,$limit]):?><label><?=esc($label)?><input name="contact_<?=esc($field)?>" maxlength="<?=$limit?>" value="<?=esc(registration_drafts_retry_text($values,'contact_'.$field,$data['contact'][$field]??'', $limit))?>" <?=in_array($field,['organisation','pc','sp'],true)?'':'required'?>></label><?php endforeach;?></div>
      <label>Street address<input name="contact_address" maxlength="1024" value="<?=esc(registration_drafts_retry_text($values,'contact_address',$data['contact']['address']??'',1024))?>" required></label>
      <label>Nameservers, one per line<textarea name="nameservers" maxlength="3500" rows="4" required><?=esc(registration_drafts_retry_text($values,'nameservers',$data===null?'':implode("\n",$data['nameservers'])))?></textarea></label><p>Enter the nameservers you want reviewed. Registry-specific identity fields and agreements will be handled separately; this draft does not record registry consent.</p>
      <?php if($owner):?><?php endif;?><button class="btn gold">Save private draft</button>
    </form><?php endif;?>
    <?php if(!$owner&&$summary):$actions=match($summary['state']){'draft'=>['submit'=>'Submit this revision for studio review','withdraw'=>'Withdraw draft'],'submitted'=>['withdraw'=>'Withdraw draft'],'withdrawn'=>['reopen'=>'Reopen draft'],default=>[]};foreach($actions as $action=>$label):$f=registration_drafts_form($initial+[],$input,$prefix.$action);if($f===null)continue;if($input===[])$f['command_id']=bin2hex(random_bytes(16));?>
      <form method="post" class="registration-draft-<?=$action?>"><?php registration_drafts_hidden($f,$prefix.$action);?><label><input type="checkbox" name="confirm" value="1" required <?=($input['action']??null)===$prefix.$action&&($input['confirm']??null)==='1'?'checked':''?>> <?=esc($label)?>. This is not a domain purchase or payment authorization.</label><button class="btn outline"><?=esc($action==='submit'?'Submit for review':$label)?></button></form>
    <?php endforeach;endif;?></section>
    <?php if($summary):?><section class="workspace-card registration-draft-history"><h2>Draft history</h2><p>Earlier revisions are retained. Contact details are shown only in the current private editor.</p><ul><?php foreach($context['history'] as $event):?><li>Revision <?=esc((string)$event['revision'])?> · <?=esc(ucfirst($event['state']))?> · <?=esc($event['action'])?> by <?= $event['actor_kind']==='staff'?'Studio owner':'Client' ?> · <?=esc(gmdate('Y-m-d H:i',$event['recorded_epoch']))?> UTC</li><?php endforeach;?></ul></section><?php endif;
}
function registration_drafts_list_view(bool $owner,array $rows,int $page,bool $hasMore):void {
    ?><section class="workspace-card registration-draft-list"><h2>Saved registration drafts</h2><?php if(!$rows):?><p>No saved drafts on this page.</p><?php else:?><ul><?php foreach($rows as $r):?><li><a href="<?=esc(registration_drafts_url($owner,['draft'=>$r['id']]))?>"><?=esc($r['domain'])?></a> · <?=esc(ucfirst($r['state']))?> · <?=esc((string)$r['requested_years'])?> requested year(s) · <?= $r['privacy_service']?'Privacy requested':'Privacy not requested' ?></li><?php endforeach;?></ul><?php endif;?><div class="actions"><?php if($page>1):?><a class="btn outline" href="<?=esc(registration_drafts_url($owner,['draft_page'=>$page-1]))?>">Previous</a><?php endif;if($hasMore):?><a class="btn outline" href="<?=esc(registration_drafts_url($owner,['draft_page'=>$page+1]))?>">Next</a><?php endif;?></div></section><?php
}
