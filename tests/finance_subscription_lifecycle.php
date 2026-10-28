<?php
/** Real lifecycle service with strict in-memory SQL doubles; no gateway traffic. */
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
$fixture=[];
function reset_fixture(string $status='active'):void {
    global $fixture;
    $fixture=['prepared'=>true,'status'=>$status,'current_id'=>'12345','history_id'=>'12344',
        'reads'=>0,'updates'=>[],'audits'=>[],'locked'=>false];
}
function setting(string $name,mixed $default=null):mixed {
    global $fixture;
    return $name==='license_finance_gateway_schema_version'?($fixture['prepared']?'3':null):$default;
}
function config():array {return ['db'=>['prefix'=>'isolated_','name'=>'isolated']];}
function table(string $name):string {return 'isolated_'.$name;}
function one(string $sql,array $params=[]):?array {
    global $fixture;
    $fixture['reads']++;
    if(str_contains($sql,'information_schema.tables'))return ['TABLE_NAME'=>$params[0]];
    if(str_contains($sql,'information_schema.columns'))return ['COLUMN_NAME'=>$params[1]];
    if(!$fixture['locked']||!db()->inTransaction())throw new RuntimeException('Lifecycle read is not locked and transactional.');
    if($sql==='SELECT * FROM isolated_license_finance_subscriptions WHERE gateway_subscription_id=? FOR UPDATE') {
        return $params===[$fixture['current_id']]
            ?['id'=>7,'offer_id'=>str_repeat('a',32),'gateway_subscription_id'=>$fixture['current_id'],'status'=>$fixture['status']]
            :null;
    }
    if($sql==='SELECT id FROM isolated_license_finance_subscription_history WHERE gateway_subscription_id=?') {
        return $params===[$fixture['history_id']]?['id'=>6]:null;
    }
    throw new RuntimeException('Unexpected database read: '.$sql);
}
final class LifecycleDatabase {
    private bool $active=false;
    function inTransaction():bool {return $this->active;}
    function beginTransaction():void {$this->active=true;}
    function commit():void {$this->active=false;}
    function rollBack():void {$this->active=false;}
}
function db():LifecycleDatabase {static $database=new LifecycleDatabase;return $database;}
final class LifecycleStatement {function fetchColumn():int {return 1;}}
function query(string $sql,array $params=[]):LifecycleStatement {
    global $fixture;
    if($sql==='SELECT GET_LOCK(?,5)')$fixture['locked']=true;
    elseif($sql==='SELECT RELEASE_LOCK(?)')$fixture['locked']=false;
    else {
        if(!$fixture['locked']||!db()->inTransaction())throw new RuntimeException('Lifecycle mutation is not locked and transactional.');
        if($sql==='UPDATE isolated_license_finance_subscriptions SET status=?,updated_at=? WHERE id=?'&&$params[2]===7) {
            $fixture['status']=$params[0];$fixture['updates'][]=$params[0];
        }elseif($sql==='INSERT INTO isolated_audit (user_id, action, detail, created_at) VALUES (?, ?, ?, ?)') {
            $fixture['audits'][]=$params;
        }else throw new RuntimeException('Unexpected database mutation: '.$sql);
    }
    return new LifecycleStatement;
}
require CWP_CODE_ROOT.'/app/billing/finance_subscription.php';
$checks=[];
function check_case(string $name,callable $test):void {
    global $checks,$fixture;
    try {
        if(!$test())throw new RuntimeException('Unexpected lifecycle result or state.');
        if($fixture['locked']||db()->inTransaction())throw new RuntimeException('Lifecycle lock or transaction was retained.');
        $checks[]=['name'=>$name,'passed'=>true];
    }catch(Throwable $error){$checks[]=['name'=>$name,'passed'=>false,'error'=>$error->getMessage()];}
}
function lifecycle_event(string $event,string $id='12345'):string {
    return license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.'.$event,$id);
}
function unchanged(string $state):bool {
    global $fixture;
    return $fixture['status']===$state&&$fixture['updates']===[]&&$fixture['audits']===[];
}
function fixture_value(string $name):mixed {global $fixture;return $fixture[$name];}
$events=['failed'=>'payment_failed','suspended'=>'billing_suspended','terminated'=>'review','cancelled'=>'cancelled','expired'=>'cancelled'];
foreach(['review','paid_off','cancelled','reversal_recovery','replacing'] as $state) {
    foreach($events as $event=>$target) {
        reset_fixture($state);
        check_case($state.' survives delayed '.$event,
            fn()=>lifecycle_event($event)==='replay'&&unchanged($state));
    }
}
reset_fixture('billing_suspended');
check_case('Confirmed suspension survives a delayed failure and keeps catch-up eligibility',
    fn()=>lifecycle_event('failed')==='replay'&&unchanged('billing_suspended')
        &&license_financing_recovery_link_allowed(fixture_value('status')));
foreach($events as $event=>$target) {
    reset_fixture();
    check_case('Active subscription accepts '.$event,function()use($event,$target):bool {
        global $fixture;
        return lifecycle_event($event)==='finance_subscription_'.$target&&$fixture['status']===$target
            &&$fixture['updates']===[$target]&&count($fixture['audits'])===1
            &&$fixture['audits'][0][1]==='license.financing.subscription.lifecycle';
    });
}
foreach(['suspended'=>'billing_suspended','cancelled'=>'cancelled','terminated'=>'review'] as $event=>$target) {
    reset_fixture('payment_failed');
    check_case('Payment failure can advance to '.$target,
        fn()=>lifecycle_event($event)==='finance_subscription_'.$target&&fixture_value('status')===$target);
}
foreach(['cancelled'=>'cancelled','terminated'=>'review'] as $event=>$target) {
    reset_fixture('billing_suspended');
    check_case('Suspension can advance to '.$target,
        fn()=>lifecycle_event($event)==='finance_subscription_'.$target&&fixture_value('status')===$target);
}
reset_fixture('payment_failed');
check_case('Duplicate failure is idempotent',fn()=>lifecycle_event('failed')==='replay'&&unchanged('payment_failed'));
reset_fixture();
check_case('Unknown event does not mutate',fn()=>lifecycle_event('unexpected')==='ignored'&&unchanged('active'));
check_case('Archived gateway identity cannot mutate its replacement',fn()=>lifecycle_event('failed','12344')==='historical_subscription'&&unchanged('active'));
check_case('Unknown gateway identity cannot mutate another agreement',fn()=>lifecycle_event('failed','99999')==='unknown_subscription'&&unchanged('active'));
check_case('Malformed identity cannot mutate another agreement',fn()=>lifecycle_event('failed','not-an-id')==='unknown_subscription'&&unchanged('active'));
reset_fixture();$fixture['prepared']=false;
check_case('Unprepared storage is not queried',fn()=>lifecycle_event('failed')==='unknown_subscription'&&fixture_value('reads')===0);
foreach(['reversal_recovery','replacing'] as $state) {
    reset_fixture($state);
    check_case($state.' retains its ordinary settlement fence after delayed suspension',
        fn()=>lifecycle_event('suspended')==='replay'&&license_financing_subscription_settlement_status(fixture_value('status'))===null);
}
reset_fixture('cancelled');
check_case('Cancellation remains cancelled when a later verified payment settles',
    fn()=>lifecycle_event('failed')==='replay'&&license_financing_subscription_settlement_status(fixture_value('status'))==='cancelled');
$passed=!in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'scope'=>'Actual lifecycle service with strict in-memory database doubles; not native SQL, gateway, or installed acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
