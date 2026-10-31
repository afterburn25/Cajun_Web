<?php
/** Direct mutation-boundary test with an in-memory transaction/query double. No SQL/network. */
declare(strict_types=1);
define('CWP_BOOT',true);
final class PayoffTransactionDouble {
    public bool $active=false;
    public bool $locked=false;
    public array $events=[];
    public int $updates=0;
    public int $audits=0;
    public function inTransaction():bool{return $this->active;}
    public function beginTransaction():bool{$this->events[]='begin';$this->active=true;return true;}
    public function commit():bool{$this->events[]='commit';$this->active=false;return true;}
    public function rollBack():bool{$this->events[]='rollback';$this->active=false;return true;}
}
$connection=new PayoffTransactionDouble();$financeTable=true;$financing=null;
function db():PayoffTransactionDouble{return $GLOBALS['connection'];}
function config():array{return ['db'=>['prefix'=>'payoff_guard_']];}
function table(string $name):string{return '`payoff_guard_'.$name.'`';}
function one(string $sql,array $args=[]):?array{
    global $financeTable,$financing;
    if(str_contains($sql,'WHERE id = ? FOR UPDATE')){if(!db()->active)throw new LogicException('Missing transaction');db()->locked=true;db()->events[]='license-lock';return ['id'=>$args[0]];}
    if(str_contains($sql,'information_schema.tables')){if(!db()->locked)throw new LogicException('Payoff checked before lock');return $financeTable?['TABLE_NAME'=>'payoff_guard_license_financing']:null;}
    if(str_contains($sql,'SELECT state,paid_off_at')){if(!db()->locked)throw new LogicException('Missing license lock');db()->events[]='payoff-read';return $financing;}
    throw new LogicException('Unexpected fixture query');
}
function query(string $sql,array $args=[]):void{
    if(!db()->active||!db()->locked)throw new LogicException('Mutation outside lock/transaction');
    if(str_starts_with($sql,'UPDATE ')){db()->updates++;db()->events[]='update';return;}
    if(str_starts_with($sql,'INSERT INTO `payoff_guard_audit`')){db()->audits++;return;}
    throw new LogicException('Unexpected fixture mutation');
}
require __DIR__.'/../site/app/licenses/service.php';
$checks=[];
function assert_guard(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
try{
    foreach([
        'unfinanced'=>[false,null,false],
        'other-financed-license'=>[true,null,false],
        'active'=>[true,['state'=>'active','paid_off_at'=>null],false],
        'grace'=>[true,['state'=>'grace','paid_off_at'=>null],false],
        'suspended'=>[true,['state'=>'suspended','paid_off_at'=>null],false],
        'fully-paid'=>[true,['state'=>'paid_off','paid_off_at'=>'2026-01-01 12:00:00'],true],
        'paid-marker-without-state'=>[true,['state'=>'active','paid_off_at'=>'2026-01-01 12:00:00'],true],
        'paid-state-without-marker'=>[true,['state'=>'paid_off','paid_off_at'=>null],true],
    ] as $label=>[$financeTable,$financing,$mustRefuse]){
        $connection=new PayoffTransactionDouble();$refused=false;
        try{license_deactivate(7,1);}catch(RuntimeException){$refused=true;}
        assert_guard($label.' follows payoff policy',$refused===$mustRefuse);
        assert_guard($label.' preserves atomic mutation boundary',$mustRefuse
            ? $connection->updates===0&&$connection->audits===0&&end($connection->events)==='rollback'
            : $connection->updates===1&&$connection->audits===1&&end($connection->events)==='commit');
        assert_guard($label.' releases its transaction',!$connection->active&&array_slice($connection->events,0,2)===['begin','license-lock']);
    }
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual license_deactivate with an in-memory transaction/query double; not SQL or upgrade acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($failure)?1:0);
