<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');

$state=['support'=>false,'client'=>['id'=>7],'permission'=>true,'job'=>true,'rows'=>[],'calls'=>[]];$checks=[];
function ar_ok(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function ar_refuses(string $name,callable $work,string $class=Throwable::class):void{try{$work();}catch(Throwable $e){ar_ok($name,$e instanceof $class);return;}ar_ok($name,false);}
function support_current():mixed{global $state;$state['calls'][]='support';return $state['support']?['client'=>['id'=>7]]:null;}
function studio_client():?array{global $state;$state['calls'][]='client';return $state['client'];}
function contact_require_permission(string $permission,int $clientId):void{global $state;$state['calls'][]='permission:'.$permission.':'.$clientId;if(!$state['permission'])throw new RuntimeException('denied');}
function studio_job(int $jobId,?int $clientId=null):array{global $state;$state['calls'][]='job:'.$jobId.':'.$clientId;if(!$state['job'])throw new InvalidArgumentException('denied');return ['id'=>$jobId,'client_id'=>$clientId];}
function table(string $name):string{return 'ci_'.$name;}
function all(string $sql,array $params=[]):array{global $state;$state['calls'][]=['sql'=>$sql,'params'=>$params];return $state['rows'];}

require __DIR__.'/../site/app/projects/action_items.php';
try{
    $state['rows']=[['id'=>'12','kind'=>'proposal','version_no'=>'2','decision'=>'pending'],['id'=>'11','kind'=>'proposal','version_no'=>'1','decision'=>'pending'],['id'=>'10','kind'=>'design','version_no'=>'1','decision'=>'approved']];
    $actions=project_current_review_actions(4,7);
    ar_ok('Current authorized project yields privacy-minimal latest action',count($actions)===1&&$actions[0]->reviewId===12&&$actions[0]->kind==='proposal'&&$actions[0]->version===2);
    $sql=end($state['calls']);
    ar_ok('Adapter selects only bounded review authority fields',$sql['params']===[4]&&str_contains($sql['sql'],'SELECT id,kind,version_no,decision')&&str_contains($sql['sql'],'LIMIT 1001')&&!preg_match('/SELECT\s+\*/i',$sql['sql']));
    ar_ok('Client and contact permission are reauthorized before project read',array_slice($state['calls'],0,4)===['support','client','permission:projects:7','job:4:7']);
    $state['support']=true;$state['calls']=[];$state['rows']=[['id'=>'13','kind'=>'design','version_no'=>'2','decision'=>'pending']];
    ar_ok('Support sessions receive no customer action projection',project_current_review_actions(4,7)===[]&&$state['calls']===['support']);
    $state['support']=false;$state['client']=['id'=>8];$state['calls']=[];
    ar_refuses('Different current client is refused',fn()=>project_current_review_actions(4,7),InvalidArgumentException::class);
    ar_ok('Different client refusal occurs before permission, project or review query',$state['calls']===['support','client']);
    $state['client']=['id'=>7];$state['permission']=false;$state['calls']=[];
    ar_refuses('Revoked project permission is refused',fn()=>project_current_review_actions(4,7),RuntimeException::class);
    ar_ok('Permission refusal occurs before project or review query',$state['calls']===['support','client','permission:projects:7']);
    $state['permission']=true;$state['job']=false;$state['calls']=[];
    ar_refuses('Foreign project is refused',fn()=>project_current_review_actions(4,7),InvalidArgumentException::class);
    ar_ok('Foreign project refusal occurs before review query',end($state['calls'])==='job:4:7');
    $state['job']=true;
    foreach([
        [['id'=>'01','kind'=>'design','version_no'=>'1','decision'=>'pending']],
        [['id'=>'1','kind'=>'design','version_no'=>'0','decision'=>'pending']],
        [['id'=>'1','kind'=>'design','version_no'=>'1','decision'=>'pending','title'=>'private']],
    ] as $i=>$bad){$state['rows']=$bad;ar_refuses('Malformed database projection refuses '.$i,fn()=>project_current_review_actions(4,7));}
    ar_refuses('Invalid project identity refuses before authority query',fn()=>project_current_review_actions(0,7),InvalidArgumentException::class);
    $source=file_get_contents(__DIR__.'/../site/portal/views/workspace.php');
    ar_ok('Workspace uses current-authority action adapter',str_contains($source,'project_current_review_actions('));
    ar_ok('Workspace derives review link from authorized job and minimal review identity',str_contains($source,"'focus_review'=>\$action->reviewId")&&str_contains($source,"'#review-'.\$action->reviewId"));
    $reviews=file_get_contents(__DIR__.'/../site/app/projects/reviews.php');
    ar_ok('Decision revalidation locks the latest version after the shared project lock',str_contains($reviews,'ORDER BY version_no DESC LIMIT 1 FOR UPDATE')&&!str_contains($reviews,'SELECT MAX(version_no)'));
    ar_ok('Packaged model exactly mirrors source model',hash_file('sha256',__DIR__.'/../foundation/projects/ProjectActionItems.php')===hash_file('sha256',__DIR__.'/../site/includes/foundation/projects/ProjectActionItems.php'));
    echo json_encode(['passed'=>true,'count'=>count($checks),'checks'=>$checks,'scope'=>'Offline current-authority project review adapter and view contract only; no message, billing, payment, mutation, schema, inventory or live operation.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>$e->getMessage()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit(1);}
