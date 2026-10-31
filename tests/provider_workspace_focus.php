<?php
/** Actual shipping template with a null-profile render fixture; no database/network. */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',realpath(__DIR__.'/../site'));
function provider_settings_profile():?array{return null;}
function esc(mixed $text):string{return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}
function admin_url(string $view,array $query=[]):string{return '/admin/index.php?'.http_build_query(['view'=>$view]+$query);}
function page_url(string $page):string{return '/index.php?page='.$page;}
function provider_cloud_site_request_confirmation_render():void{throw new LogicException('Unrelated website confirmation was invoked during focused rendering.');}
function focus_render(array $query,string $file):string{$_GET=$query;$_POST=[];ob_start();try{require $file;return ob_get_contents();}finally{ob_end_clean();}}
$checks=[];
function focus_check(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
try{
 $template=$argv[1]??CWP_CODE_ROOT.'/admin/views/providers.php';
 $html=focus_render(['focus'=>'cloud-assignment'],$template);
 focus_check('Legacy focused route renders its dedicated view',str_contains($html,'data-cloud-assignment-focus')&&str_contains($html,'data-provider-cloud'));
 focus_check('Focused view omits connection editor and unrelated workspaces',!str_contains($html,'data-provider-hub')&&!str_contains($html,'data-provider-editor')&&!str_contains($html,'data-cloudsite-create'));
 focus_check('Focused route retains the return link',str_contains($html,esc(admin_url('provider-settings'))));
 focus_check('No password reentry in focused empty state',!str_contains($html,'type="password"'));
 $html=focus_render([],$template);
 focus_check('Ordinary entry retains seven-section workspace',str_contains($html,'data-provider-hub')&&str_contains($html,'20i workspaces')&&str_contains($html,'Import 20i plans'));
 $html=focus_render(['focus'=>['cloud-assignment']],$template);
 focus_check('Malformed focus cannot enter the narrow route',str_contains($html,'data-provider-hub')&&!str_contains($html,'data-cloud-assignment-focus'));
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual provider template; explicit null-profile dependencies, not installed SQL or provider acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($failure)?1:0);
