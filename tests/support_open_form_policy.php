<?php
/** Render the shared form without loading the unrelated full studio controller. */
declare(strict_types=1);
define('CWP_BOOT',true);
$permitted=true;$owner=true;$allowed=0;
function support_can(string $permission):bool{return $GLOBALS['permitted'];}
function support_owner_review_allowed(?array $actor):bool{return $GLOBALS['owner'];}
function admin_user():?array{return ['id'=>1];}
function one(string $sql,array $params=[]):?array{return ['allowed'=>$GLOBALS['allowed']];}
function table(string $name):string{return '`synthetic_'.$name.'`';}
function admin_url(string $view):string{return '/sub/admin/index.php?view='.$view;}
function esc(string $text):string{return htmlspecialchars($text,ENT_QUOTES,'UTF-8');}
function admin_action(string $action):void{echo '<input type="hidden" name="csrf" value="synthetic-token"><input type="hidden" name="action" value="'.esc($action).'">';}
require __DIR__.'/../site/admin/controllers/support.php';
$checks=[];
function render_form(int $id):string{ob_start();try{support_open_form($id);return (string)ob_get_contents();}finally{ob_end_clean();}}
function check_form(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
try{
    $html=render_form(42);
    check_form('Owner review form renders without studio_hidden dependency',str_contains($html,'Open client portal (owner review)')&&str_ends_with($html,'</form>'));
    check_form('Existing action route CSRF and numeric client ID retained',str_contains($html,'action="/sub/admin/index.php?view=support"')&&str_contains($html,'name="csrf"')&&str_contains($html,'name="client_id" value="42"')&&str_contains($html,'value="support_start"'));
    $owner=false;$allowed=0;check_form('Unapproved staff client omitted',render_form(42)==='');
    $allowed=1;check_form('Approved staff client receives complete form',str_contains(render_form(42),'>Open client portal</button></form>'));
    $permitted=false;check_form('Missing support permission receives no form',render_form(42)==='');
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Shared form rendering with synthetic access-policy doubles; no SQL or HTTP','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($failure)?1:0);
