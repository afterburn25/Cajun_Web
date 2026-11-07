<?php
/** Rendered Version & database status must not collapse feature state into studio_ready(). */
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');define('CWP_VERSION','1.26.1');
$GLOBALS['schema_settings']=[];
function setting(string $name):string{return $GLOBALS['schema_settings'][$name]??'';}
function esc(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function studio_ready():bool{return setting('studio_schema_version')==='2';}
function contacts_ready():bool{return setting('contacts_schema_version')==='1';}
function admin_action(string $action):void{echo '<input name="action" value="'.esc($action).'">';}
function admin_check(string $name,string $label,bool $checked):void{echo '<label>'.esc($label).'<input name="'.esc($name).'" type="checkbox"></label>';}
require CWP_CODE_ROOT.'/app/settings/database_schemas.php';
foreach(database_schema_versions() as $marker=>$version)$GLOBALS['schema_settings'][$marker]=$version;
function dv_render():string{ob_start();require CWP_CODE_ROOT.'/admin/views/studio/upgrade.php';return ob_get_clean();}
$checks=[];$failure=null;
function dv_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
try{
    $html=dv_render();
    dv_ok('All-current render identifies complete required registry without hiding optional distinction',str_contains($html,'76 current · 0 requiring update or recovery · 0 optional and not prepared · 0 unsupported')&&str_contains($html,'Every required registered schema is current'));
    dv_ok('All registry families and exact installed targets render',substr_count($html,'data-schema-state="current"')===76&&str_contains($html,'20i Settings')&&str_contains($html,'installed 5 · target 5'));
    $GLOBALS['schema_settings']['contacts_schema_version']='';$GLOBALS['schema_settings']['license_schema_version']='2';$GLOBALS['schema_settings']['service_plans_checkout_schema_version']='preparing-1';$GLOBALS['schema_settings']['provider_settings_schema_version']='99';
    $html=dv_render();
    dv_ok('Optional absence is not called a required update',str_contains($html,'72 current · 2 requiring update or recovery · 1 optional and not prepared · 1 unsupported')&&str_contains($html,'Contacts</strong> — Optional'));
    dv_ok('Known update and interrupted state are visible',str_contains($html,'License</strong>: A reviewed additive update is available.')&&str_contains($html,'Service Plans Checkout</strong>: A supported interrupted update can be resumed.'));
    dv_ok('Unsupported marker blocks false all-current claim',str_contains($html,'Unsupported database state detected.')&&!str_contains($html,'Every required registered schema is current'));
    dv_ok('Core action stays explicitly bounded instead of claiming all schemas',str_contains($html,'creates or verifies only the core client-workspace')&&str_contains($html,'Verify &amp; apply core workspace update'));
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>$failure===null,'checks'=>count($checks),'failure'=>$failure]).PHP_EOL;exit($failure===null?0:1);
