<?php
declare(strict_types=1);
if (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }
function setup_checks(): array {
    $rows=[];$storage='';$attempts=[];$note='';
    $package=setup_package_check();$rows[]=setup_package_row($package);
    if(!$package['ok'])return ['ok'=>false,'checks'=>$rows,'package'=>$package,'storage'=>'','storage_note'=>'All setup fields remain visible. Restore these website files before installation. No database or storage changes were made.','attempts'=>[],'php_uid'=>setup_worker_uid(),'host_report'=>setup_permission_report()];
    $rows[]=['label'=>'PHP runtime','ok'=>PHP_VERSION_ID>=80200,'message'=>'PHP '.PHP_VERSION];
    $rows[]=['label'=>'Secure connection','ok'=>https_request()||local_request(),'message'=>https_request()?'HTTPS is active.':(local_request()?'Local development connection.':'Enable HTTPS before sending credentials.')];
    foreach(['pdo_mysql'=>'MySQL connector','openssl'=>'Encryption','fileinfo'=>'File validation','gd'=>'Image processing'] as $ext=>$label)$rows[]=['label'=>$label,'ok'=>extension_loaded($ext),'message'=>extension_loaded($ext)?$ext.' available':'Enable '.$ext.' in your hosting PHP settings.'];
    $root=writable_probe(setup_root(),false,0755);$rows[]=['label'=>'Website directory','ok'=>$root['ok'],'message'=>$root['message'],'details'=>$root];
    try {
        if(!$root['ok'])throw new RuntimeException('PHP needs targeted write access to the website directory to save config.php and remove install.php. Website code permissions were not changed.');
        $s=setup_prepare_storage(posted('private_parent',1000));$storage=$s['storage'];$attempts=$s['attempts'];$note=$s['message'];
        if($s['ok']){
            foreach(['private'=>'Private storage','media'=>'Protected media'] as $key=>$label)$rows[]=['label'=>$label,'ok'=>true,'message'=>$s[$key]['message'],'details'=>$s[$key]];
        }else{$rows[]=['label'=>'Private storage','ok'=>false,'message'=>$s['message']];$rows[]=['label'=>'Protected media','ok'=>false,'message'=>'Waiting for a writable private location; this is not a separate missing-folder problem.'];}
    }catch(Throwable $e){$rows[]=['label'=>'Private storage','ok'=>false,'message'=>$e->getMessage()];$rows[]=['label'=>'Protected media','ok'=>false,'message'=>'No media or existing storage was overwritten. Resolve the private-storage check first.'];}
    return ['ok'=>!in_array(false,array_column($rows,'ok'),true),'checks'=>$rows,'storage'=>$storage,'storage_note'=>$note,'attempts'=>$attempts,'php_uid'=>setup_worker_uid(),'host_report'=>setup_permission_report(),'package'=>$package];
}

/** Cleanup only after committed config exists; no caller-provided paths. */
