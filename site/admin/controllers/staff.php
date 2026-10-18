<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function staff_admin_action(string $action): never {
    check_csrf();$actor=require_admin();
    if(!consume_rate('staff-management-'.$actor['id'],20,900))throw new InvalidArgumentException('Please wait before making more account changes.');
    $password=posted('owner_password',1000);
    if($action==='staff_migrate'){
        if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm your backup and initial owner selection.');
        staff_migrate(gint($_POST,'owner_id',1,2147483647),$password);flash('Administrator access is enabled. Existing sign-in details were preserved.');
    }elseif($action==='staff_save'){
        $permissions=$_POST['permissions']??[];if(!is_array($permissions))throw new InvalidArgumentException('Choose valid access settings.');
        $id=staff_save(gint($_POST,'id',0,2147483647),posted('name',120),posted('email',190),posted('role',20),isset($_POST['enabled']),$permissions,$password);
        flash('Administrator saved. Previous sessions and setup/reset links were revoked. For a new account, create a private setup link below.');
    }elseif($action==='staff_invite'){
        $_SESSION['staff_setup_link']=staff_invite(gint($_POST,'id',1,2147483647),$password);
        flash('Private setup link created for 24 hours. No email was sent. Share it directly with this administrator. Their previous password, sessions and links no longer work until setup is completed.');
    }else throw new InvalidArgumentException('Unknown account action.');
    redirect(admin_url('staff'));
}
function staff_form_fields(array $u): void {
    admin_input('name','Name',$u['name']??'','text','required maxlength="120"');
    admin_input('email','Sign-in email',$u['email']??'','email','required maxlength="190" autocomplete="off"');
    admin_select('role','Role',['administrator'=>'Administrator — selected access only','owner'=>'Owner — all administration and account controls'],$u['role']??'administrator');
    $permissions=json_decode($u['permissions']??'[]',true)??[];
    foreach(staff_permissions() as $key=>$label){?><label class="check"><input type="checkbox" name="permissions[]" value="<?=esc($key)?>" <?=in_array($key,$permissions,true)?'checked':''?>><?=esc($label)?></label><?php }
    admin_check('enabled','Account enabled',$u['enabled']??1);
    admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');
}
function staff_admin_render(array $user,string $error): void {
    admin_head('Administrators','staff',$user);
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    if(!staff_ready()){
        ?><section class="workspace-card"><h2>Enable administrator access</h2><p>This adds account roles and setup links while preserving existing sign-in details. Choose the existing administrator who owns this installation. Other existing administrators keep website and client-workspace access. Email configuration, server maintenance and administrator management become owner-only.</p><form method="post"><?php admin_action('staff_migrate');admin_select('owner_id','Initial owner',array_column(all('SELECT id,name FROM '.table('users').' ORDER BY id'),'name','id'),$user['id']);admin_input('owner_password','Your current administrator password','','password','required autocomplete="current-password"');admin_check('confirm','I have a current database/file backup and confirm this owner.',false);?><button class="btn gold">Enable administrator access</button></form></section><?php
        admin_foot();return;
    }
    if(isset($_SESSION['staff_setup_link'])){$link=$_SESSION['staff_setup_link'];unset($_SESSION['staff_setup_link']);?><div class="notice info"><strong>Private setup link — displayed once</strong><p>Copy this link and send it through your chosen private channel. It expires after 24 hours. We have not sent any email.</p><a data-staff-invite href="<?=esc($link)?>" rel="noreferrer"><?=esc($link)?></a></div><?php }
    ?><p class="admin-intro">Owners manage administrator accounts. Administrators receive only the website, client-workspace and support access you select. Support access also requires an allowed client and separate write/download grants. No mailbox access or payment-provider credentials are granted. Owners have all current administration controls. Creating or replacing a setup link retires that account's previous password until the recipient completes setup.</p><section class="workspace-card"><h2>Add an administrator</h2><form method="post"><?php admin_action('staff_save');studio_hidden('id',0);staff_form_fields([]);?><button class="btn gold">Create administrator</button></form></section><h2 class="subheading">Your team</h2><div class="workspace-grid"><?php
    foreach(all('SELECT u.id,u.name,u.email,u.password_hash,p.role,p.enabled,p.permissions FROM '.table('users').' u JOIN '.table('staff_profiles').' p ON p.user_id=u.id ORDER BY u.id') as $u){
        ?><section class="workspace-card"><h3><?=esc($u['name'])?></h3><p><?=esc($u['role'])?> · <?=(int)$u['enabled']===0?'Disabled':($u['password_hash']===''?'Password setup needed':'Enabled')?><?=(int)$u['id']===(int)$user['id']?' · Your account':''?></p><form method="post"><?php admin_action('staff_save');studio_hidden('id',$u['id']);staff_form_fields($u);?><button class="btn outline">Save access and revoke old sessions</button></form><?php if((int)$u['enabled']===1&&(int)$u['id']!==(int)$user['id']):?><form method="post"><?php admin_action('staff_invite');studio_hidden('id',$u['id']);admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');?><button class="btn gold">Create / replace setup link</button></form><?php endif;?></section><?php
    }
    ?></div><p class="hint">Access changes invalidate existing sessions and pending setup/reset links. At least one enabled owner with completed password setup must remain. Account activity is recorded in System health.</p><?php admin_foot();
}
function staff_setup_render(string $error): never {
    header('Referrer-Policy: no-referrer');login_head('Set up your administrator account.');
    $code=$_SERVER['REQUEST_METHOD']==='POST'&&is_string($_POST['token']??null)&&preg_match('/^[a-f0-9]{64}$/D',$_POST['token'])?$_POST['token']:'';
    if($error)echo '<div class="notice error" role="alert">'.esc($error).'</div>';
    ?><p>Use the private link provided by an owner. Choose a unique passphrase; you will sign in separately after setup.</p><form method="post" action="<?=esc(admin_url('staff-setup'))?>"><?php admin_action('staff_activate');admin_input('token','Private setup code',$code,'password','required pattern="[a-f0-9]{64}" maxlength="64" autocomplete="off" data-setup-token');admin_input('password','New password','','password','required minlength="12" maxlength="72" autocomplete="new-password"');admin_input('confirm_password','Confirm password','','password','required minlength="12" maxlength="72" autocomplete="new-password"');?><p class="hint">The link fills in the setup code when JavaScript is enabled. Otherwise paste the 64-character code after #token= from your private link.</p><button class="btn gold">Set up account</button></form><script src="<?=esc(asset('staff-setup.js'))?>" defer></script><?php login_foot();exit;
}
