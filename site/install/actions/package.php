<?php
declare(strict_types=1);
if (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }
function setup_package_check(): array {
    $issues=[]; $passed=0;
    foreach(setup_expected_files() as $relative=>$expected) {
        $path=CWP_ROOT.'/'.$relative;
        clearstatcache(true,$path);
        // No following a symlink in app/assets or in the actual file.
        $walk=CWP_ROOT; $linked=false;
        foreach(explode('/',$relative) as $piece){$walk.='/'.$piece;if(@is_link($walk)){$linked=true;break;}}
        if($linked){$problem='Symbolic link: use the regular file from this package.';}
        elseif(!@is_file($path)){$problem='Missing file.';}
        elseif(!@is_readable($path)){$problem='PHP cannot read this file.';}
        elseif(!hash_equals($expected,(string)@hash_file('sha256',$path))){$problem='Different or incomplete file: merge the complete '.CWP_INSTALLER_BUILD.' package here.';}
        else{$passed++;continue;}
        $issues[]=['path'=>$relative,'problem'=>$problem];
    }
    return ['ok'=>!$issues,'passed'=>$passed,'expected'=>count(setup_expected_files()),'issues'=>$issues];
}
function setup_package_row(array $package): array {
    return ['label'=>'Complete website files','ok'=>$package['ok'],
      'message'=>$package['ok']?$package['passed'].' / '.$package['expected'].' required files verified.':
       'Missing or mismatched files: '.implode(', ',array_column($package['issues'],'path')).'. Extract the COMPLETE-UPLOAD ZIP into this directory with overwrite enabled.'];
}
/** Invalidate only verified site PHP files before including schema on this request. */
function setup_refresh_verified_php(): void {
    if(!function_exists('opcache_invalidate'))return;
    foreach(setup_expected_files() as $path=>$hash){
        if(str_ends_with($path,'.php'))@opcache_invalidate(CWP_ROOT.'/'.$path,true);
    }
}
