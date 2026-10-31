<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
    if($action==='installer_cleanup'){$deleted=cleanup_installer_files();$remaining=array_keys(array_filter($deleted,fn($gone)=>!$gone));log_event('installer.cleanup',$remaining?'Physical removal needs host assistance':'Installer absent');flash($remaining?'Still on disk: '.implode(', ',$remaining).'. The installer is blocked. Remove these files in File Manager or ask your host to align ownership.':'Installer PHP, private key, and installer-only CSS/JavaScript are absent from the server.',$remaining?'warning':'success');redirect(admin_url('health'));}
    if($action==='storage_repair'){$results=[];foreach([config()['storage'],config()['storage'].'/media'] as $dir)$results[]=writable_probe($dir,true,0700);$ok=!in_array(false,array_column($results,'ok'),true);log_event('storage.permissions',$ok?'Passed':'Needs host attention');flash($ok?'Private-storage checks passed using owner-only directory permissions.':'One or more storage checks failed. Review the owner and path details below.',$ok?'success':'warning');redirect(admin_url('health'));}
    if($action==='clear_limits'){query('DELETE FROM '.table('rate_limits').' WHERE expires<?',[time()]);query('DELETE FROM '.table('resets').' WHERE expires<?',[time()]);log_event('maintenance.expired');flash('Expired rate-limit and password-reset records removed. Inquiries were not deleted.');redirect(admin_url('health'));}
    if($action==='media_cleanup'){
        $removed=staff_locked(function():int {
            staff_require('staff.manage');
            require_once CWP_CODE_ROOT.'/app/billing/catalog.php';
            require_once CWP_CODE_ROOT.'/app/providers/catalog_import_storage.php';
            require_once CWP_CODE_ROOT.'/app/hosting/catalog_offers_storage.php';
            $importReady=provider_catalog_import_ready();$hostingReady=hosting_catalog_ready();
            $media=all('SELECT id,filename FROM '.table('media'));$removed=0;
            foreach($media as $m){
                $uses=one('SELECT COUNT(*) AS n FROM '.table('projects').' WHERE media_id=?',[$m['id']]);
                if((int)$uses['n']===0&&!growth_media_used($m['id'])&&!($importReady&&provider_catalog_image_used($m['id']))&&!($hostingReady&&hosting_catalog_image_used($m['id']))&&preg_match('/^[a-f0-9]{32}\.php$/D',$m['filename'])){
                    $p=config()['storage'].'/media/'.$m['filename'];if(is_link($p))continue;
                    if(!is_file($p)||@unlink($p)){query('DELETE FROM '.table('media').' WHERE id=?',[$m['id']]);$removed++;}
                }
            }
            log_event('media.cleanup',(string)$removed);return $removed;
        });
        flash($removed.' unused media item(s) removed.');redirect(admin_url('health'));
    }
