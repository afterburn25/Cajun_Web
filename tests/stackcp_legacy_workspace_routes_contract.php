<?php
/** Preserve the complete immutable historical source; reject any unrelated adaptation. */
declare(strict_types=1);
require __DIR__.'/stackcp_legacy_workspace_routes.php';
$raw=file_get_contents(__DIR__.'/fixtures/stackcp_access_read_original.json');
if(hash('sha256',$raw)!=='2c7e731489c862f021514c53efa2ef4893c3f4333c215cfa69b9eb3a321f138e')throw new RuntimeException('Historical source archive changed.');
$baseline=json_decode($raw,true,64,JSON_THROW_ON_ERROR);$checks=[];
function sr_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
try{
 foreach($baseline['units'] as $name=>$unit){
  sr_ok('Original unit identity retained '.$name,hash('sha256',$unit['code'])===$unit['sha256']);
  $out=sap_reviewed_workspace_routes($name,$unit['code']);
  if($name!=='provider_stackcp_access_admin_render')sr_ok('Other unit unchanged '.$name,$out===$unit['code']);
  else{
   $old="admin_url('provider-settings',['stackcp_access_page'=>";$new="admin_url('provider-settings',['section'=>'advanced','stackcp_access_page'=>";
   sr_ok('Both page directions target the advanced workspace',substr_count($out,$new)===2);
   sr_ok('Exact inverse retains every other HTML and policy byte',str_replace($new,$old,$out)===$unit['code']);
   foreach(['changed-source'=>$unit['code'].' ', 'already-patched'=>$out,'wrong-field'=>str_replace('stackcp_access_page','other_page',$unit['code'])] as $label=>$bad){$denied=false;try{sap_reviewed_workspace_routes($name,$bad);}catch(RuntimeException){$denied=true;}sr_ok('Unreviewed variation refused '.$label,$denied);}
  }
 }
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Exact historical unit and two-route adapter checks; not SQL performance acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
