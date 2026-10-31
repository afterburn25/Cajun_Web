<?php
/** Real SQL setup denials for the same authenticated owner; isolated fixture only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||config()['db']['prefix']!=='propci_')throw new LogicException('Isolated proposal authority fixture only.');
function p_setup_state():array {
    $snapshot=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,7)=? ORDER BY TABLE_NAME',['propci_']) as $item){
        $name=$item['TABLE_NAME'];if(!preg_match('/^propci_[a-z_]+$/D',$name))throw new LogicException('Invalid proposal fixture table.');
        $rows=array_map('serialize',all('SELECT * FROM `'.$name.'`'));sort($rows,SORT_STRING);
        $schema=one('SHOW CREATE TABLE `'.$name.'`')['Create Table'];
        $snapshot[$name]=[hash('sha256',serialize($rows)),preg_replace('/ AUTO_INCREMENT=[0-9]+\b/','',$schema)];
    }
    return $snapshot;
}
foreach(['disabled','revoked','expired','delegated','anonymous','support'] as $ownerMode){
    p_actor();$originalSession=$_SESSION;$originalGet=$_GET;$originalState=p_setup_state();
    if($ownerMode==='disabled')query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1');
    elseif($ownerMode==='delegated')query('UPDATE '.table('staff_profiles')." SET role='administrator' WHERE user_id=1");
    elseif($ownerMode==='revoked')$_SESSION['auth_version']='revoked-fixture';
    elseif($ownerMode==='expired')$_SESSION['last_activity']=time()-1801;
    elseif($ownerMode==='anonymous')$_SESSION=[];
    else $_GET['support']='invalid-review';
    try{
        $before=p_setup_state();$refusal=null;
        try{proposals_migrate();}catch(Throwable $e){$refusal=$e;}
        $expected=$ownerMode==='support'?'Leave support before setup.':'An active owner session is required to prepare proposals.';
        p_ok('Proposal setup refuses '.$ownerMode.' authority at the correct boundary',$refusal instanceof RuntimeException&&!($refusal instanceof PDOException)&&$refusal->getMessage()===$expected);
        p_ok('Refused '.$ownerMode.' setup preserves all records and schema',p_setup_state()===$before&&!db()->inTransaction());
    }finally{
        if(db()->inTransaction())db()->rollBack();
        query('UPDATE '.table('staff_profiles')." SET role='owner',enabled=1 WHERE user_id=1");
        $_SESSION=$originalSession;$_GET=$originalGet;
    }
    p_ok('Restored '.$ownerMode.' fixture retains exact original state',p_setup_state()===$originalState);
}
