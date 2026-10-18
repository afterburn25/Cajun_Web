<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
$page=isset($_GET['draft_page'])?registration_drafts_number($_GET,'draft_page',10000):1;$screen=domain_registration_drafts_list((int)$client['id'],$page);
if(!$screen['ready']):?><p class="notice">The studio has not enabled private registration drafts yet.</p><?php else:
    $id=isset($_GET['draft'])?registration_drafts_text($_GET,'draft',32):'';
    if($id!=='')$context=domain_registration_drafts_get($id,(int)$client['id']);
    else{$newId=$_POST===[]?bin2hex(random_bytes(16)):(is_string($_POST['draft_id']??null)&&preg_match('/\A[a-f0-9]{32}\z/',$_POST['draft_id'])?$_POST['draft_id']:'');$context=['new_id'=>$newId,'client_id'=>(int)$client['id']];}
    registration_drafts_editor(false,$context,$_SERVER['REQUEST_METHOD']==='POST'?$_POST:[]);
    if($id!==''):?><p><a class="btn outline" href="<?=esc(registration_drafts_url(false))?>">Start another draft</a></p><?php endif;
    registration_drafts_list_view(false,$screen['drafts'],$page,$screen['has_more']);
endif;
