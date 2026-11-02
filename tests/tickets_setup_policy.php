<?php
/** Actual setup mutation methods with explicit schema/SQL doubles. No real database or network. */
declare(strict_types=1);
$source=file_get_contents(__DIR__.'/../site/database/migrations/support_tickets_migrate.php');
$offset=strpos($source,'/** Recheck the authenticated owner;');
if($offset===false||substr_count($source,'function support_tickets_migrate(')!==1)throw new LogicException('Expected unique current setup declarations.');
$checks=[];$state=[];
final class TicketSetupConnection {
    public bool $tx=false;
    public function inTransaction():bool{return $this->tx;}
    public function exec(string $sql):void{
        if($this->tx||!str_starts_with($sql,'CREATE:'))throw new LogicException('Unexpected fixture DDL');
        $name=substr($sql,7);$GLOBALS['state']['tables'][$name]=['valid'=>true,'rows'=>0];$GLOBALS['state']['ddl'][]=$name;
    }
}
$connection=new TicketSetupConnection();
function db():TicketSetupConnection{return $GLOBALS['connection'];}
function config():array{return ['db'=>['prefix'=>'synthetic_']];}
function table(string $name):string{return '`synthetic_'.$name.'`';}
function support_tickets_schema(string $prefix):array{return array_combine(['tickets','events','staff_reads','client_reads'],array_map(fn($n)=>'CREATE:'.$n,['tickets','events','staff_reads','client_reads']));}
function staff_locked(callable $work):mixed{$GLOBALS['state']['locked']=true;try{return $work();}finally{$GLOBALS['state']['locked']=false;}}
function support_tickets_staff(bool $ready):array{if(!$GLOBALS['state']['locked'])throw new LogicException('Owner must be read under the existing lock');return $GLOBALS['state']['actor'];}
function staff_can(string $permission,array $actor):bool{return $permission==='staff.manage'&&$actor['role']==='owner';}
function admin_session_matches(array $actor):bool{return $GLOBALS['state']['session'];}
function one(string $sql,array $params):?array{
    if(str_contains($sql,'SELECT value FROM'))return $GLOBALS['state']['version']===''?null:['value'=>$GLOBALS['state']['version']];
    if(str_contains($sql,'information_schema.tables')){if(!str_starts_with($params[0],'synthetic_'))throw new LogicException('Unsafe synthetic table');$n=substr($params[0],10);return isset($GLOBALS['state']['tables'][$n])?['TABLE_NAME'=>$params[0]]:null;}
    throw new LogicException('Unexpected synthetic lookup');
}
function query(string $sql):object{
    if(!preg_match('/^SELECT COUNT\(\*\) FROM `synthetic_([a-z_]+)`$/D',$sql,$m))throw new LogicException('Unexpected synthetic count');
    $count=$GLOBALS['state']['tables'][$m[1]]['rows'];return new class($count){public function __construct(private int $count){}public function fetchColumn():int{return $this->count;}};
}
function support_tickets_validate_schema(string $name,string $sql):void{if(!$GLOBALS['state']['tables'][$name]['valid'])throw new RuntimeException('Synthetic incompatible schema.');}
function staff_transaction(callable $work):mixed{
    $old=$GLOBALS['state'];db()->tx=true;
    try{if($GLOBALS['state']['tx_change'])$GLOBALS['state']['actor']['auth_version']='changed-during-setup';return $work();}
    catch(Throwable $e){$GLOBALS['state']=$old;throw $e;}finally{db()->tx=false;}
}
function support_tickets_ready():bool{return $GLOBALS['state']['version']==='1';}
function save_setting(string $name,string $value):void{if(!db()->tx||$name!=='support_tickets_schema_version')throw new LogicException('Unexpected marker write');$GLOBALS['state']['version']=$value;}
function support_tickets_audit(int $actor,string $action,string $detail):void{if(!db()->tx||$action!=='setup'||$actor!==1)throw new LogicException('Unexpected audit');if($GLOBALS['state']['fail_audit'])throw new RuntimeException('Synthetic audit failure.');$GLOBALS['state']['audit']++;}
eval(substr($source,$offset));
function seed_setup(string $version=''):void{$GLOBALS['state']=['version'=>$version,'tables'=>[],'actor'=>['id'=>1,'role'=>'owner','auth_version'=>'current-owner'],'session'=>true,'locked'=>false,'ddl'=>[],'audit'=>0,'tx_change'=>false,'fail_audit'=>false];db()->tx=false;}
function setup_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new AssertionError($name);}
function setup_refused(string $name,string $message):void{$before=$GLOBALS['state'];$error=null;try{support_tickets_migrate();}catch(Throwable $e){$error=$e;}setup_ok($name.' returns the intended refusal',$error instanceof RuntimeException&&str_contains($error->getMessage(),$message));setup_ok($name.' preserves all modeled records and definitions',$GLOBALS['state']===$before&&!db()->tx);}
try{
    seed_setup();support_tickets_migrate();setup_ok('Fresh setup creates exactly four empty additive tables',count($state['tables'])===4&&count($state['ddl'])===4);setup_ok('Fresh setup records marker and audit under transaction',$state['version']==='1'&&$state['audit']===1&&!db()->tx&&!$state['locked']);
    $before=$state['ddl'];support_tickets_migrate();setup_ok('Repeated preparation validates without repeated DDL',$state['ddl']===$before&&$state['audit']===2);
    foreach(['tickets','events','staff_reads','client_reads'] as $missing){seed_setup('1');foreach(support_tickets_schema('') as $n=>$sql)if($n!==$missing)$state['tables'][$n]=['valid'=>true,'rows'=>3];setup_refused('Lost established '.$missing,'Established ticket storage is incomplete');}
    seed_setup();$state['tables']['client_reads']=['valid'=>true,'rows'=>1];setup_refused('Unrecognized later nonempty table','Unrecognized ticket records');
    seed_setup();$state['tables']['client_reads']=['valid'=>false,'rows'=>0];setup_refused('Incompatible later table','Synthetic incompatible');
    seed_setup('2');setup_refused('Unknown storage version','Unrecognized ticket storage version');
    foreach(['role','session'] as $mode){seed_setup();if($mode==='role')$state['actor']['role']='administrator';else $state['session']=false;setup_refused('Invalid current owner '.$mode,'An active owner session');}
    seed_setup();$state['tx_change']=true;$error=null;try{support_tickets_migrate();}catch(Throwable $e){$error=$e;}setup_ok('Changed owner during metadata transaction is refused',$error instanceof RuntimeException&&str_contains($error->getMessage(),'Owner authority changed'));setup_ok('Changed owner cannot activate additive tables or create success audit',count($state['tables'])===4&&$state['version']===''&&$state['audit']===0&&!db()->tx);
    seed_setup();$state['fail_audit']=true;$error=null;try{support_tickets_migrate();}catch(Throwable $e){$error=$e;}setup_ok('Failed audit does not claim setup success',$error instanceof RuntimeException&&$error->getMessage()==='Synthetic audit failure.');setup_ok('Failed audit rolls back marker while retaining harmless empty tables',$state['version']===''&&$state['audit']===0&&count($state['tables'])===4&&!db()->tx);
    seed_setup();db()->tx=true;$before=$state;$error=null;try{support_tickets_migrate();}catch(Throwable $e){$error=$e;}setup_ok('Caller-owned transaction is refused without touching it',$error instanceof LogicException&&db()->tx&&$state===$before);db()->tx=false;
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual setup/owner methods with explicit schema and SQL doubles; not native database acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
