<?php
/** Actual dispatcher and workspace routing; bounded synthetic rendering dependencies. No SQL/network. */
declare(strict_types=1);
define('CWP_BOOT',true);
$root=sys_get_temp_dir().'/cwp-workspace-focus-'.bin2hex(random_bytes(8));
mkdir($root.'/app/providers',0700,true);mkdir($root.'/admin/views/provider_workspace',0700,true);
define('CWP_CODE_ROOT',$root);
copy(__DIR__.'/../site/app/providers/workspace.php',$root.'/app/providers/workspace.php');
file_put_contents($root.'/admin/views/provider_cloud.php','<?php if(!$cloudAssignmentFocused)throw new LogicException("Focus flag absent");echo "<div data-synthetic-cloud-assignment>Assignment test dependency</div>";');
foreach(['connection','services','cloud','domains','website','advanced'] as $section)file_put_contents($root.'/admin/views/provider_workspace/'.$section.'.php','<?php echo "<div data-test-section='.$section.'>Selected section</div>";');
$historyCalls=0;$confirmations=0;$checks=[];
function provider_settings_profile():array{return ['id'=>str_repeat('a',32),'label'=>'Synthetic connection','revision'=>1,'enabled'=>1,'has_credentials'=>true];}
function provider_checks_history(string $id,int $page):array{$GLOBALS['historyCalls']++;return [];}
function provider_cloud_site_request_confirmation_render():void{$GLOBALS['confirmations']++;}
function admin_url(string $view,array $query=[]):string{return '/admin/index.php?'.http_build_query(['view'=>$view]+$query);}
function page_url(string $page):string{return '/index.php?page='.$page;}
function esc(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function focus_render(array $query):string {$_GET=$query;$_POST=[];ob_start();try{require __DIR__.'/../site/admin/views/providers.php';return (string)ob_get_contents();}finally{ob_end_clean();}}
function focus_check(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
try{
 $html=focus_render(['focus'=>'cloud-assignment']);
 focus_check('Existing focused URL renders the assignment marker and narrow dependency',str_contains($html,'data-cloud-assignment-focus')&&str_contains($html,'data-synthetic-cloud-assignment'));
 focus_check('Focused page never projects unrelated connection history',$historyCalls===0);
 focus_check('Focused page never projects unrelated website confirmations',$confirmations===0);
 focus_check('Focused page offers a fixed return to the full workspace',str_contains($html,'All 20i workspaces')&&str_contains($html,'view=provider-settings'));
 foreach(['connection','services','cloud','domains','website','advanced'] as $section){$html=focus_render(['section'=>$section]);focus_check('Standard section remains available '.$section,str_contains($html,'data-test-section='.$section)&&str_contains($html,'data-provider-hub')&&!str_contains($html,'data-cloud-assignment-focus'));}
 foreach([[],['cloud-assignment'],42,'../cloud-assignment','CLOUD-ASSIGNMENT',null] as $i=>$bad){$html=focus_render(['focus'=>$bad]);focus_check('Invalid focus cannot select an include '.$i,!str_contains($html,'data-cloud-assignment-focus')&&str_contains($html,'data-provider-hub'));}
 focus_check('Full workspace still renders exactly seven navigation choices',count(provider_workspace_sections())===7);
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual dispatcher with synthetic render dependencies; not installed/database authorization','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
