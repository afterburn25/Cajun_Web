<?php
/** Pure escaped-rendering contract for the direct-staff private-note partial. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require dirname(__DIR__).'/foundation/projects/ProjectPrivateNote.php';

use CajunWebPro\Foundation\Projects\ProjectPrivateNote;

$GLOBALS['pn_view_ready']=true;
function project_private_notes_ready():bool{return $GLOBALS['pn_view_ready'];}
function project_private_notes_list(int $projectId):array{
    return [['note'=>ProjectPrivateNote::create(12,$projectId,7,100,"Private </textarea><script>alert('note')</script> & detail"),'staff_name'=>"Owner <script>alert('name')</script>"]];
}
function admin_url(string $view='dashboard',array $params=[]):string{return '/admin/?'.http_build_query(['view'=>$view]+$params);}
function esc(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_action(string $action):void{echo '<input name="action" value="'.esc($action).'">';}
function studio_hidden(string $name,mixed $value):void{echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';}
function admin_textarea(string $name,string $label,mixed $value='',int $rows=5,int $max=6000):void{echo '<label>'.esc($label).'<textarea name="'.esc($name).'" rows="'.$rows.'" maxlength="'.$max.'">'.esc($value).'</textarea></label>';}
function pn_view(int $id):string{ob_start();require CWP_CODE_ROOT.'/admin/views/studio/private_notes.php';return ob_get_clean();}

$checks=[];
function pnv_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
try{
    $html=pn_view(21);
    pnv_ok('Private body and staff identity are escaped before HTML rendering',!str_contains($html,"<script>alert('note')</script>")&&!str_contains($html,"<script>alert('name')</script>")&&str_contains($html,'&lt;/textarea&gt;&lt;script&gt;alert(&#039;note&#039;)&lt;/script&gt;')&&str_contains($html,'Owner &lt;script&gt;'));
    pnv_ok('Rendered note has exact private identity and UTC time',str_contains($html,'id="private-note-12"')&&str_contains($html,'1970-01-01 00:01:40 UTC'));
    pnv_ok('Form posts only the private-note action project identity and bounded body',str_contains($html,'value="studio_private_note"')&&str_contains($html,'name="job_id" value="21"')&&str_contains($html,'name="private_note_body"')&&str_contains($html,'maxlength="5000"'));
    pnv_ok('Screen discloses immutable direct-staff non-projection boundary',str_contains($html,'directly signed-in staff')&&str_contains($html,'never added to the client conversation')&&str_contains($html,'Save immutable private note'));
    $GLOBALS['pn_view_ready']=false;$setup=pn_view(21);
    pnv_ok('Unprepared storage renders only an additive setup route',str_contains($setup,'Version &amp; database')&&!str_contains($setup,'private_note_body')&&!str_contains($setup,'Private &lt;/textarea&gt;'));
    echo json_encode(['passed'=>true,'checks'=>$checks,'executions'=>count($checks),'scope'=>'Pure escaped admin partial only; authorization and SQL are separately tested.'],JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $error){
    echo json_encode(['passed'=>false,'checks'=>$checks,'executions'=>count($checks),'failure'=>$error->getMessage()],JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(1);
}
