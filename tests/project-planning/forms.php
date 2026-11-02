<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../../site');require CWP_CODE_ROOT.'/includes/security/input.php';require CWP_CODE_ROOT.'/includes/layout/project_planning.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';
use CajunWebPro\Foundation\ProjectPlanning\{ProjectPlan,ProjectPlanEvent};
function csrf_field():string{return '<input name="csrf" value="synthetic-csrf">';}
function admin_url(string $view,array $params=[]):string{return '/existing/admin/index.php?'.http_build_query(['view'=>$view]+$params);}
function site_url(string $path=''):string{return '/existing/'.$path;}
function studio_hidden(string $name,mixed $value):void{echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';}
function f_html(callable $fn):string{ob_start();try{$fn();return ob_get_contents();}finally{ob_end_clean();}}
$checks=[];
function f_ok(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function f_deny(string $name,callable $fn):void{try{$fn();}catch(InvalidArgumentException|LogicException){f_ok($name,true);return;}f_ok($name,false);}
try{
    $items=[['id'=>str_repeat('1',32),'title'=>'Content <img src=x onerror=alert(1)>','details'=>"Exact\r\ntext\t ",'state'=>'completed','owner'=>'client','due_date'=>null,'depends_on'=>[]],['id'=>str_repeat('2',32),'title'=>'Design','details'=>'Second milestone','state'=>'planned','owner'=>'studio','due_date'=>'2028-02-29','depends_on'=>[str_repeat('1',32)]]];
    $post=['id'=>'5','client_id'=>'2','request_id'=>str_repeat('a',32),'expected'=>str_repeat('b',64),'scope'=>"  Scope <script>\r\nNext line\r\t  ",'assigned_contact'=>'Studio <contact>','target_date'=>'2028-02-29','client_action'=>'Send materials','blockers'=>'','milestones_json'=>project_progress_json($items)];
    $attempt=project_planning_attempt($post);$form=project_planning_form($attempt);
    f_ok('Plan form preserves exact surrounding and multiline text',$form['fields']['scope']===$post['scope']&&$form['fields']['milestones']===$items);
    f_ok('Original plan request version project and client stay exact',$form['request_id']===$post['request_id']&&$form['expected']===$post['expected']&&$form['id']===5&&$form['client_id']===2);
    foreach(['id'=>['0','01','-1','2147483648',[]],'client_id'=>['0','02',[]],'request_id'=>['',str_repeat('A',32),str_repeat('a',33)],'expected'=>['',str_repeat('B',64),[]],'target_date'=>['2025-02-29','2028-2-29','0000-01-01','2028-01-32'],'scope'=>["bad\0text","\xff",[]],'assigned_contact'=>['   ',"Contact\nName"]] as $key=>$values)foreach($values as $i=>$value)f_deny('Malformed '.$key.' case '.$i.' refuses',fn()=>project_planning_form(project_planning_attempt(array_replace($post,[$key=>$value]))));
    foreach(['scope'=>10000,'assigned_contact'=>160,'client_action'=>4000,'blockers'=>4000] as $key=>$limit){
        $p=array_replace($post,[$key=>str_repeat('x',$limit)]);f_ok('Exact '.$key.' byte limit accepted',strlen(project_planning_form(project_planning_attempt($p))['fields'][$key])===$limit);
        $a=project_planning_attempt(array_replace($p,[$key=>str_repeat('x',$limit+1)]));f_ok('Oversized '.$key.' flagged without silent truncation',$a[$key]===''&&in_array($key,$a['invalid'],true));f_deny('Oversized '.$key.' cannot become an empty accepted save',fn()=>project_planning_form($a));
    }
    f_ok('Empty calendar target becomes null',project_planning_form(project_planning_attempt(array_replace($post,['target_date'=>''])))['fields']['target_date']===null);
    foreach(['{}','null','[null]','[{}]',"\xff",' '.project_progress_json($items),str_replace('"title":"Design"','"title":"First","title":"Design"',$post['milestones_json']),str_repeat('[',20).str_repeat(']',20),str_repeat('x',262145)] as $i=>$raw)f_deny('Malformed milestone serialization case '.$i.' refuses',fn()=>project_planning_form(project_planning_attempt(array_replace($post,['milestones_json'=>$raw]))));
    $bad=$items;$bad[0]['depends_on']=[str_repeat('2',32)];$raw=project_progress_json($bad);f_ok('Shape-valid cyclic draft stays available for correction',project_planning_draft_milestones($raw)===$bad);f_deny('Cyclic draft cannot save',fn()=>project_planning_form(project_planning_attempt(array_replace($post,['milestones_json'=>$raw]))));
    $bad=$items;$bad[0]['due_date']='bad';f_ok('Invalid milestone date remains repairable',project_planning_draft_milestones(project_progress_json($bad))===$bad);
    foreach(['null','[[]]','[{"id":[]}]'] as $i=>$raw)f_ok('Unrenderable milestone shape '.$i.' stays disabled',project_planning_draft_milestones($raw)===null);
    foreach([[],['plan_page'=>'1'],['plan_page'=>'410'],['focus_plan'=>str_repeat('e',32)]] as $i=>$query)f_ok('Valid planning query '.$i.' parses',count(project_planning_query($query))===2);
    foreach([['plan_page'=>'411'],['plan_page'=>'01'],['plan_page'=>[]],['focus_plan'=>[]],['focus_plan'=>str_repeat('E',32)],['plan_page'=>'-1']] as $i=>$query)f_deny('Invalid planning query '.$i.' refuses',fn()=>project_planning_query($query));
    $first=ProjectPlanEvent::start(str_repeat('c',32),5,2,7,1700000000,ProjectPlan::fromArray($form['fields']));$new=$form['fields'];$new['client_action']='Review design';$last=$first->update(str_repeat('d',32),7,1700000000,$first->fingerprint(),ProjectPlan::fromArray($new));
    $view=['project'=>['id'=>5,'title'=>'Project'],'planned'=>true,'plan'=>$new,'count'=>2,'items'=>[$first->forClient(2),$last->forClient(2)],'page'=>1,'pages'=>2];
    $html=f_html(fn()=>project_planning_dashboard($view));f_ok('Dashboard escapes literal markup in scope contact and milestone titles',str_contains($html,'&lt;script&gt;')&&str_contains($html,'Studio &lt;contact&gt;')&&str_contains($html,'&lt;img src=x')&&!str_contains($html,'<script>')&&!str_contains($html,'<img'));
    f_ok('Dashboard shows real ownership status dates and named prerequisites',str_contains($html,'Responsible: Client')&&str_contains($html,'Completed')&&str_contains($html,'2028-02-29')&&str_contains($html,'Depends on:'));
    $html=f_html(fn()=>project_planning_timeline($view,false,str_repeat('d',32)));
    f_ok('Timeline distinguishes first real plan and actual changed fields',str_contains($html,'Plan created')&&str_contains($html,'Client action updated')&&str_contains($html,'Earlier planning discussions are not reconstructed'));
    f_ok('Timeline preserves revision order for same-second saved plans',strpos($html,'planning-'.str_repeat('d',32))<strpos($html,'planning-'.str_repeat('c',32)));
    f_ok('Exact planning destination is accessible and expanded',str_contains($html,'id="planning-'.str_repeat('d',32).'" tabindex="-1"')&&str_contains($html,'<details open>'));
    f_ok('History paging retains client project',str_contains($html,'job=5&amp;plan_page=2#planning-history')&&!str_contains($html,'Older plans'));
    f_ok('Public planning history exposes no internal metadata',!str_contains($html,'staff_id')&&!str_contains($html,$last->fingerprint())&&!str_contains($html,$first->fingerprint()));
    $saved=$view+['client_id'=>2,'expected'=>$last->fingerprint()];$GLOBALS['cwp_planning_attempt']=$attempt;$html=f_html(fn()=>project_planning_editor($saved));
    f_ok('Stale planning editor retains original request and expected value',str_contains($html,'name="expected" value="'.$post['expected'].'"')&&str_contains($html,'name="request_id" value="'.$post['request_id'].'"')&&!str_contains($html,'name="expected" value="'.$last->fingerprint().'"'));
    f_ok('Draft stays distinct from current saved plan with explicit reload',str_contains($html,'Your unsaved plan')&&str_contains($html,'current saved plan is shown below')&&str_contains($html,'Reload the saved plan'));
    f_ok('No-JavaScript form retains one full milestone value',substr_count($html,'name="milestones_json"')===1&&str_contains($html,'value="'.esc($post['milestones_json']).'"')&&str_contains($html,'fieldset disabled data-planning-controls'));
    f_ok('Visible milestone inputs have no submitted names',!preg_match('/name="(?:title|state|owner|due_date|depends_on|details)"/',$html));
    f_ok('Saved milestone identities remain distinct from draft additions',str_contains($html,'data-retained="'.esc(project_progress_json(array_column($items,'id'))).'"'));
    f_ok('Draft text and JSON are escaped without inline script interpolation',str_contains($html,esc($post['scope']))&&!str_contains($html,'<script>'));
    unset($GLOBALS['cwp_planning_attempt']);$html=f_html(fn()=>project_planning_editor($saved));f_ok('Fresh editor uses current native version',str_contains($html,'name="expected" value="'.$last->fingerprint().'"'));
    $GLOBALS['cwp_planning_attempt']=array_replace($attempt,['milestones_json'=>'[{}]']);$html=f_html(fn()=>project_planning_editor($saved));f_ok('Malformed draft retains raw milestones and prevents editor enhancement',str_contains($html,'data-editable="0"')&&str_contains($html,'value="[{}]"'));unset($GLOBALS['cwp_planning_attempt']);
    $view['planned']=false;$view['plan']=null;$view['items']=[];$html=f_html(fn()=>project_planning_dashboard($view));f_ok('Unplanned dashboard does not fabricate progress or milestones',str_contains($html,'has not saved a structured plan')&&!str_contains($html,'100%'));
    echo json_encode(['passed'=>true,'php'=>PHP_VERSION,'scope'=>'Bounded planning forms and real rendering, no HTTP/current-authority acceptance.','checks'=>$checks],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,(string)$e.PHP_EOL);echo json_encode(['passed'=>false,'checks'=>$checks],JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
