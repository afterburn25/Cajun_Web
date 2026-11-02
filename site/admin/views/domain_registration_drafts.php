<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
$page=isset($_GET['draft_page'])?registration_drafts_number($_GET,'draft_page',10000):1;$screen=domain_registration_drafts_list(null,$page);
?>
<section class="workspace-card"><h1>Private registration drafts</h1><p>Review a client's proposed domain and private registrant contact. These drafts do not reserve a name, quote a fee, record registry consent or submit a registration.</p></section>
<?php if(!$screen['ready']):?>
<section class="workspace-card"><h2>Enable private draft storage</h2><p>Contact details are encrypted using a dedicated key in private server storage. Back up that key together with the database; replacing a missing key cannot recover saved contacts.</p><form method="post"><?=csrf_field()?><input name="action" type="hidden" value="registration_drafts_prepare"><button class="btn gold">Enable drafts</button></form></section>
<?php else:
    $id=isset($_GET['draft'])?registration_drafts_text($_GET,'draft',32):'';$context=null;
    if($id!=='')$context=domain_registration_drafts_get($id);
    elseif(isset($_GET['client'])){
        $clientId=registration_drafts_number($_GET,'client',4294967295);$target=domain_registration_drafts_owner_target($clientId);
        $newId=$_POST===[]?bin2hex(random_bytes(16)):(is_string($_POST['draft_id']??null)&&preg_match('/\A[a-f0-9]{32}\z/',$_POST['draft_id'])?$_POST['draft_id']:'');
        $context=['new_id'=>$newId,'client_id'=>$clientId,'client_version'=>$target['auth_version'],'client_name'=>$target['name']];
    }
    if($context!==null)registration_drafts_editor(true,$context,$_SERVER['REQUEST_METHOD']==='POST'?$_POST:[]);
?>
<?php $clientPage=isset($_GET['client_page'])?registration_drafts_number($_GET,'client_page',10000):1;$choices=domain_registration_drafts_owner_clients($clientPage);?>
<section class="workspace-card"><h2>Start a draft for a client</h2><form method="get"><input type="hidden" name="view" value="registration-drafts"><input type="hidden" name="client_page" value="<?=esc((string)$clientPage)?>"><label>Client<select name="client" required><option value="">Choose an active client</option><?php foreach($choices['clients'] as $choice):?><option value="<?=esc((string)$choice['id'])?>"><?=esc($choice['name'])?></option><?php endforeach;?></select></label><button class="btn outline">Open draft</button></form><p>Active clients · page <?=esc((string)$clientPage)?>. Only active clients can receive an editable draft.</p><div class="actions"><?php if($clientPage>1):?><a class="btn outline" href="<?=esc(registration_drafts_url(true,['client_page'=>$clientPage-1]))?>">Previous clients</a><?php endif;if($choices['has_more']):?><a class="btn outline" href="<?=esc(registration_drafts_url(true,['client_page'=>$clientPage+1]))?>">More clients</a><?php endif;?></div></section>
<?php registration_drafts_list_view(true,$screen['drafts'],$page,$screen['has_more']);endif;?>
