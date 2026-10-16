<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/backups/archive.php';
require_once CWP_CODE_ROOT.'/includes/layout/backups.php';

function backups_admin_action(string $action): void {
    if(support_current())throw new RuntimeException('Leave support before managing backups.');
    if($action==='backups_setup'){backups_migrate(posted('owner_password',1000));flash('Backup storage and records are ready.');redirect(admin_url('backups'));}
    if($action==='backups_create'){if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm the snapshot scope before creating a backup.');$id=backups_create(['notes'=>posted('notes',5000),'retain_days'=>(int)posted('retain_days',6)],posted('request_key',64));flash('Backup snapshot recorded and verified.');redirect(admin_url('backups',['preview'=>$id]));}
    if($action==='backups_restore'){if(posted('confirm',1)!=='1')throw new InvalidArgumentException('Confirm this deliberate restore.');$id=posted('backup',32);backups_restore($id,posted('phrase',60),posted('owner_password',1000));flash('Backup applied. Every restored file was verified against its recorded hash.');redirect(admin_url('backups'));}
    if($action==='backups_prune'){$n=backups_prune(posted('owner_password',1000));flash($n.' expired backup'.($n===1?'':'s').' removed.');redirect(admin_url('backups'));}
}

function backups_admin_render(array $user,string $error): never {
    if(isset($_GET['download'])&&is_string($_GET['download'])){try{backups_download($_GET['download']);}catch(InvalidArgumentException|RuntimeException $e){http_response_code(404);fail_page('Backup archive unavailable: '.$e->getMessage());}}
    admin_head('Backups & restore','backups',$user);
    if($error!=='')echo '<p class="notice error" role="alert">'.esc($error).'</p>';
    backups_page($user);
    admin_foot();exit;
}
