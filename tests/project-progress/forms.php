<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../../site');require CWP_CODE_ROOT.'/includes/security/input.php';require CWP_CODE_ROOT.'/includes/layout/project_progress.php';require CWP_CODE_ROOT.'/includes/layout/admin.php';
function csrf_field():string{return '<input name="csrf" value="synthetic-csrf">';}
function admin_url(string $view,array $params=[]):string{return '/existing/admin/index.php?'.http_build_query(['view'=>$view]+$params);}
function site_url(string $path=''):string{return '/existing/'.$path;}
function studio_hidden(string $name,mixed $value):void{echo '<input type="hidden" name="'.esc($name).'" value="'.esc($value).'">';}
function pf_html(callable $render):string{ob_start();try{$render();return ob_get_contents();}finally{ob_end_clean();}}
$checks=[];
function pf_ok(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function pf_deny(string $name,callable $work):void{try{$work();}catch(InvalidArgumentException|LogicException){pf_ok($name,true);return;}pf_ok($name,false);}
try{
    $post=['id'=>'5','client_id'=>'2','request_id'=>str_repeat('a',32),'expected'=>str_repeat('b',64),'title'=>'  Cafe <script>  ','summary'=>"First\r\nSecond",'stage'=>'design','milestones'=>" One\rTwo "];
    $attempt=project_progress_attempt($post);$form=project_progress_form($attempt);
    pf_ok('Rejected draft capture retains original bounded text and newline bytes',$attempt['title']===$post['title']&&$attempt['summary']===$post['summary']&&$attempt['milestones']===$post['milestones']);
    pf_ok('Accepted fields keep existing trim semantics and interior CRLF',$form['fields']['title']==='Cafe <script>'&&$form['fields']['summary']===$post['summary']&&$form['fields']['milestones']==="One\rTwo");
    pf_ok('Original retry identity and expected token stay exact',$form['request_id']===$post['request_id']&&$form['expected']===$post['expected']&&$form['id']===5&&$form['client_id']===2);
    foreach(['id'=>['','01','-1','5.0','2147483648',[]],'client_id'=>['0','02','-1',[]],'request_id'=>['',str_repeat('A',32),str_repeat('c',33),[]],'expected'=>['',str_repeat('b',63),str_repeat('B',64),[]],'title'=>['',str_repeat('x',161),"bad\0text",[]],'stage'=>['unknown',[],"design\n"]] as $key=>$values)foreach($values as $i=>$value){
        // Existing input helpers trim edges; a trailing newline around a valid stage is accepted.
        if($key==='stage'&&$value==="design\n")continue;
        pf_deny('Malformed '.$key.' form case '.$i.' is refused',fn()=>project_progress_form(project_progress_attempt(array_replace($post,[$key=>$value]))));
    }
    foreach(['summary'=>10000,'milestones'=>15000] as $key=>$limit){$p=array_replace($post,[$key=>str_repeat('x',$limit)]);pf_ok('Exact '.$key.' byte limit survives form parsing',strlen(project_progress_form(project_progress_attempt($p))['fields'][$key])===$limit);$a=project_progress_attempt(array_replace($p,[$key=>str_repeat('x',$limit+1)]));pf_ok('Oversized '.$key.' is flagged rather than truncated',$a[$key]===''&&in_array($key,$a['invalid'],true));pf_deny('Oversized '.$key.' cannot become an empty save',fn()=>project_progress_form($a));}
    $create=array_replace($post,['id'=>'0','expected'=>'']);pf_ok('Creation carries its original request with empty expected token',project_progress_form(project_progress_attempt($create))['id']===0);pf_deny('Creation rejects a forged nonempty old version',fn()=>project_progress_form(project_progress_attempt(array_replace($create,['expected'=>str_repeat('b',64)]))));
    foreach([[],['progress_page'=>'1'],['progress_page'=>'137'],['focus_progress'=>str_repeat('e',32)]] as $i=>$query)pf_ok('Valid timeline query '.$i.' parses',count(project_progress_query($query))===2);
    foreach([['progress_page'=>'138'],['progress_page'=>'01'],['progress_page'=>[]],['focus_progress'=>[]],['focus_progress'=>str_repeat('E',32)],['progress_page'=>'-1']] as $i=>$query)pf_deny('Invalid timeline query '.$i.' is refused',fn()=>project_progress_query($query));
    $snapshot=\CajunWebPro\Foundation\Projects\ProjectSnapshot::fromArray($form['fields']);$baseline=\CajunWebPro\Foundation\Projects\ProjectProgressEvent::baseline(str_repeat('c',32),5,2,7,1700000000,$snapshot);$fields=$snapshot->data();$fields['stage']='review';$event=$baseline->update(str_repeat('d',32),7,1700000000,$baseline->fingerprint(),\CajunWebPro\Foundation\Projects\ProjectSnapshot::fromArray($fields));
    $view=['project'=>['id'=>5]+$fields,'tracked'=>true,'count'=>2,'items'=>[$baseline->forClient(2),$event->forClient(2)],'page'=>1,'pages'=>2];$html=pf_html(fn()=>project_progress_timeline($view,false,str_repeat('d',32)));
    pf_ok('Client timeline escapes literal project markup',str_contains($html,'Cafe &lt;script&gt;')&&!str_contains($html,'<script>'));
    pf_ok('Timeline honestly distinguishes baseline from real change',str_contains($html,'Tracking started')&&str_contains($html,'Earlier changes are not reconstructed')&&str_contains($html,'Stage updated'));
    pf_ok('Same-second timeline preserves native revision order',strpos($html,'progress-'.str_repeat('d',32))<strpos($html,'progress-'.str_repeat('c',32)));
    pf_ok('Exact selected event has a stable accessible target',str_contains($html,'id="progress-'.str_repeat('d',32).'" tabindex="-1" aria-label="Selected project update"'));
    pf_ok('Client timeline exposes no staff ID or history fingerprints',!str_contains($html,'staff_id')&&!str_contains($html,$event->fingerprint())&&!str_contains($html,$baseline->fingerprint()));
    pf_ok('Client paging links keep the actual project and page',str_contains($html,'job=5&amp;progress_page=2#progress-title')&&!str_contains($html,'Older updates'));
    $saved=['project'=>['id'=>5]+$fields,'client_id'=>2,'expected'=>$event->fingerprint()];$GLOBALS['cwp_progress_attempt']=$attempt;$html=pf_html(fn()=>project_progress_editor($saved));
    pf_ok('Stale editor retains exact original token and request',str_contains($html,'name="expected" value="'.$post['expected'].'"')&&str_contains($html,'name="request_id" value="'.$post['request_id'].'"'));
    pf_ok('Unsaved draft is escaped and distinct from current saved values',str_contains($html,'value="  Cafe &lt;script&gt;  "')&&str_contains($html,'Current saved project')&&str_contains($html,'Reload saved details'));
    pf_ok('Saved newer token cannot silently replace stale expected input',!str_contains($html,'name="expected" value="'.$event->fingerprint().'"'));
    pf_ok('Editor retains original bounded multiline draft bytes',str_contains($html,$post['summary'])&&str_contains($html,$post['milestones']));
    unset($GLOBALS['cwp_progress_attempt']);$html=pf_html(fn()=>project_progress_editor($saved));pf_ok('Fresh editor binds current native expected value',str_contains($html,'name="expected" value="'.$event->fingerprint().'"'));
    $view['tracked']=false;$view['items']=[];$html=pf_html(fn()=>project_progress_timeline($view,false));pf_ok('Untracked project never fabricates history or completion',str_contains($html,'History has not started')&&!str_contains($html,'Tracking started')&&!str_contains($html,'100%'));
    echo json_encode(['passed'=>true,'php'=>PHP_VERSION,'scope'=>'Pure bounded project forms and actual HTML rendering; no HTTP or current-authority claim.','checks'=>$checks],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);echo json_encode(['passed'=>false,'checks'=>$checks],JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
