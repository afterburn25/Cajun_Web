<?php
/** Actual SQL, original entrypoint bodies, and independent PHP owner changes. No real gateway. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
require_once __DIR__.'/software_commerce_authority_boundaries.php';

function swa_client_session(array $client):void{
    unset($_SESSION['contact_id'],$_SESSION['contact_auth'],$_GET['support']);$GLOBALS['cwp_support']=null;
    $_SESSION['client_id']=(int)$client['id'];$_SESSION['client_auth']=$client['auth_version'];
    $_SESSION['client_last']=$_SESSION['client_login']=time();
}
function swa_invoke(array $case):array{
    try{
        $result=match($case['action']){
            'order'=>software_order_place($case['client'],$case['product'],1,1,$case['domain'],$case['key'],false,0),
            'charge'=>SoftwareClientAuthority\software_charge_run($case['client'],$case['invoice'],$case['key'],'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-authority-token'),
            'enroll'=>SoftwareClientAuthority\software_subscription_enroll($case['client'],$case['invoice'],'COMMON.ACCEPT.INAPP.PAYMENT','synthetic-authority-token'),
            'cancel'=>SoftwareClientAuthority\software_subscription_cancel($case['client'],$case['invoice']),
            default=>throw new LogicException('Unknown authority action.')
        };
        return ['allowed'=>true,'result'=>$result,'exception'=>null,'message'=>null];
    }catch(Throwable $error){return ['allowed'=>false,'result'=>null,'exception'=>get_class($error),'message'=>$error->getMessage()];}
}
function swa_case(string $action,array $products):array{
    $tag=bin2hex(random_bytes(6));$now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Authority '.$tag,$tag.'@example.invalid','disabled',bin2hex(random_bytes(16)),$now]);
    $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[(int)db()->lastInsertId()]);swa_client_session($client);
    $loaded=studio_client();if(!$loaded)throw new AssertionError('Valid customer must be loaded before the authority transition.');
    $recurring=in_array($action,['enroll','cancel'],true);$product=$products[$recurring?'month':'once'];$invoice=0;
    if($action!=='order')$invoice=software_order_place($loaded,$product,1,1,$tag.'.example',hash('sha256','fixture-'.$tag),$recurring,0);
    if($action==='cancel'){
        $id=(string)++$GLOBALS['swa_gateway_id'];
        query('INSERT INTO '.table('software_subscriptions').' (invoice_id,client_id,gateway_subscription_id,interval_months,amount_cents,status,consented_at,created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$invoice,$client['id'],$id,1,1200,'active',$now,$now]);
        $GLOBALS['swa_provider'][$id]=['name'=>'CajunWebPro software '.$invoice,'status'=>'active','amount'=>'12.00',
            'paymentSchedule'=>['interval'=>['length'=>1,'unit'=>'months'],'startDate'=>gmdate('Y-m-d',time()+2*86400),'totalOccurrences'=>9999],
            'order'=>['invoiceNumber'=>'CWP-'.$invoice]];
    }
    return ['action'=>$action,'client'=>$loaded,'product'=>$product,'invoice'=>$invoice,'domain'=>'buy-'.$tag.'.example','key'=>hash('sha256','action-'.$tag)];
}
function swa_owner_revoke(array $client,bool $active):array{
    if(db()->inTransaction())throw new AssertionError('Owner must commit on an independent connection before admission.');
    $process=proc_open([PHP_BINARY,__DIR__.'/software_commerce_authority_worker.php','revoke'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Owner authority worker failed to start.');
    fwrite($pipes[0],json_encode(['id'=>(int)$client['id'],'expected'=>$client['auth_version'],'active'=>$active],JSON_THROW_ON_ERROR));fclose($pipes[0]);
    $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
    if($exit!==0||$stderr!=='')throw new AssertionError('Owner authority worker failed: '.$stderr);
    $result=json_decode($stdout,true,16,JSON_THROW_ON_ERROR);
    if(!$result['changed']||$result['active']!==(int)$active||$result['transaction']
        ||$result['connection']===(int)query('SELECT CONNECTION_ID()')->fetchColumn())throw new AssertionError('Owner transition did not commit on a separate connection.');
    $GLOBALS['swa_owner_receipts'][]=$result;return $result;
}
function swa_authority_denial(array $outcome):bool{
    return !$outcome['allowed']&&$outcome['exception']===RuntimeException::class
        &&in_array($outcome['message'],['Your client session is no longer current. Sign in again.',
            'Sign in as the selected primary client outside support access.',
            'Purchases cannot be made in support mode.','Payment is unavailable during support access.',
            'Subscription changes are blocked in support mode.','Subscription cancellation unavailable.',
            'Customer account no longer active.'],true);
}
function swa_check_denied(array $case,string $label,callable $check):void{
    $before=cwp_test_authority_snapshot();$GLOBALS['swa_calls']=[];$outcome=swa_invoke($case);
    $check(swa_authority_denial($outcome),$label.' refuses the stale or ineligible customer');
    $check($GLOBALS['swa_calls']===[],$label.' makes no gateway request');
    $check(!db()->inTransaction(),$label.' leaves no transaction open');
    $check(cwp_test_authority_snapshot()===$before,$label.' preserves all stored rows, schema and private bytes');
    $GLOBALS['swa_outcomes'][]=['label'=>$label]+$outcome;
}

/** A real held client lock makes each entrypoint wait and reread the committed version. */
function swa_lock_case(array $case,callable $check):void{
    $directory=sys_get_temp_dir().'/cwp-software-authority-'.bin2hex(random_bytes(8));
    if(!mkdir($directory,0700))throw new RuntimeException('Authority lock evidence directory unavailable.');
    $process=null;$blocked=false;$status=null;
    $before=cwp_test_authority_snapshot();
    db()->beginTransaction();
    one('SELECT id FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[$case['client']['id']]);
    // A separately covered actual studio_client_save produces this same row/version transition.
    query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=?',[bin2hex(random_bytes(16)),$case['client']['id']]);
    $committed=cwp_test_authority_snapshot();
    try{
        $process=proc_open([PHP_BINARY,__DIR__.'/software_commerce_authority_worker.php','mutate',$directory.'/ready.json'],
            [0=>['pipe','r'],1=>['file',$directory.'/stdout.json','w'],2=>['file',$directory.'/stderr.log','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Waiting authority worker failed to start.');
        $workerGatewayId=$GLOBALS['swa_gateway_id'];$GLOBALS['swa_gateway_id']+=10;
        fwrite($pipes[0],json_encode(['case'=>$case,'session'=>$_SESSION,'provider'=>$GLOBALS['swa_provider'],
            'gateway_id'=>$workerGatewayId],JSON_THROW_ON_ERROR));fclose($pipes[0]);
        $deadline=microtime(true)+10;
        do{
            $ready=is_file($directory.'/ready.json')?json_decode(file_get_contents($directory.'/ready.json'),true):null;
            if(isset($ready['connection'])){
                $row=one('SELECT INFO FROM information_schema.processlist WHERE ID=?',[(int)$ready['connection']]);
                if($row&&str_contains((string)$row['INFO'],config()['db']['prefix'].'studio_clients')&&str_contains((string)$row['INFO'],'FOR UPDATE')){$blocked=true;break;}
            }
            $status=proc_get_status($process);if(!$status['running'])break;usleep(20000);
        }while(microtime(true)<$deadline);
        db()->commit();
        $deadline=microtime(true)+10;
        do{$status=proc_get_status($process);if(!$status['running'])break;usleep(20000);}while(microtime(true)<$deadline);
        if($status['running'])throw new RuntimeException('Waiting authority worker did not finish.');
        $stderr=file_get_contents($directory.'/stderr.log');if($stderr!=='')throw new AssertionError('Waiting authority worker diagnostics: '.$stderr);
        $outcome=json_decode(file_get_contents($directory.'/stdout.json'),true,32,JSON_THROW_ON_ERROR);$label='Locked '.$case['action'];
        $check($blocked,$label.' waits for the actual client row lock');
        $check(swa_authority_denial($outcome),$label.' refuses after the revocation commits');
        $check($outcome['calls']===[]&&!$outcome['transaction'],$label.' closes its transaction without a gateway request');
        $check(cwp_test_authority_snapshot()===$committed,$label.' preserves the committed account and every other row');
        $check($before!==$committed,$label.' observes the independently committed version change');
        $GLOBALS['swa_outcomes'][]=['label'=>$label,'observed_wait'=>$blocked]+$outcome;
    }finally{
        if(db()->inTransaction())db()->rollBack();
        if(is_resource($process)){if(proc_get_status($process)['running'])proc_terminate($process);proc_close($process);}
        foreach(glob($directory.'/*') as $file)unlink($file);rmdir($directory);
    }
}

function software_commerce_authority_checks(callable $assert):void{
    if(config()['db']['prefix']!=='swci_'||query('SELECT DATABASE()')->fetchColumn()!=='cwp_test'||db()->inTransaction()){
        throw new AssertionError('Authority regression requires the isolated native software fixture.');
    }
    $savedSession=$_SESSION;$savedGet=$_GET;$savedSupport=$GLOBALS['cwp_support']??null;$checks=[];
    $check=static function(bool $passed,string $name)use(&$checks):void{$checks[]=['name'=>$name,'passed'=>$passed];};
    $originalCount=$GLOBALS['n'];$GLOBALS['swa_calls']=[];$GLOBALS['swa_provider']=[];$GLOBALS['swa_gateway_id']=910000000;
    $GLOBALS['swa_owner_receipts']=[];$GLOBALS['swa_outcomes']=[];$products=[];$now=gmdate('Y-m-d H:i:s');
    try{
        foreach(['once','month'] as $term){
            query('INSERT INTO '.table('catalog_products').' (sku,title,family,status,term,currency,amount_cents,setup_cents,cost_cents,provider_ref,summary,features,terms,sort_order,revision,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                ['SOFTWARE-AUTH-'.strtoupper($term),'Authority regression '.$term,'software','published',$term,'USD',1200,0,null,'','Synthetic authority fixture','','Synthetic terms',0,1,$now,$now]);
            $products[$term]=(int)db()->lastInsertId();
        }
        foreach(['order','charge','enroll','cancel'] as $action){
            $case=swa_case($action,$products);$GLOBALS['swa_calls']=[];$outcome=swa_invoke($case);
            $expected=match($action){'order'=>is_int($outcome['result'])&&$outcome['result']>0,'charge'=>$outcome['result']==='declined',
                'enroll'=>$outcome['result']==='pending_first_payment','cancel'=>$outcome['result']==='cancelled'};
            $check($outcome['allowed']&&$expected,'Current '.$action.' positive control reaches its expected local outcome');
            $check(count($GLOBALS['swa_calls'])===match($action){'order'=>0,'cancel'=>2,default=>1}
                &&!array_filter($GLOBALS['swa_calls'],fn(array $call):bool=>$call['transaction'])&&!db()->inTransaction(),
                'Current '.$action.' positive control contacts only synthetic transport after commit');
        }
        foreach([true,false] as $active){
            foreach(['order','charge','enroll','cancel'] as $action){
                $case=swa_case($action,$products);swa_owner_revoke($case['client'],$active);
                swa_check_denied($case,($active?'Version revoked ':'Account disabled ').$action,$check);
            }
        }
        foreach(['wrong_client','missing_version','idle_expired','absolute_expired','support_selector','support_context','contact_id','contact_auth'] as $condition){
            foreach(['order','charge','enroll','cancel'] as $action){
                $case=swa_case($action,$products);
                switch($condition){
                    case 'wrong_client':$_SESSION['client_id']=2;break;
                    case 'missing_version':unset($_SESSION['client_auth']);break;
                    case 'idle_expired':$_SESSION['client_last']=time()-1801;break;
                    case 'absolute_expired':$_SESSION['client_login']=time()-28801;break;
                    case 'support_selector':$_GET['support']='';break;
                    case 'support_context':$GLOBALS['cwp_support']=['client'=>$case['client']];break;
                    case 'contact_id':$_SESSION['contact_id']='';break;
                    case 'contact_auth':$_SESSION['contact_auth']='';break;
                }
                swa_check_denied($case,$condition.' '.$action,$check);
            }
        }
        unset($_GET['support']);$GLOBALS['cwp_support']=null;
        foreach(['order','charge','enroll','cancel'] as $action)swa_lock_case(swa_case($action,$products),$check);
        foreach(['charge','enroll','cancel'] as $action){
            $case=swa_case($action,$products);$GLOBALS['swa_calls']=[];
            $GLOBALS['swa_after_reservation']=static fn()=>swa_owner_revoke($case['client'],false);
            $outcome=swa_invoke($case);
            $expected=match($action){'charge'=>'declined','enroll'=>'pending_first_payment','cancel'=>'cancelled'};
            $row=one('SELECT active,auth_version FROM '.table('studio_clients').' WHERE id=?',[$case['client']['id']]);
            $table=$action==='charge'?'software_charges':'software_subscriptions';
            $stored=one('SELECT status FROM '.table($table).' WHERE invoice_id=?',[$case['invoice']]);
            $check($outcome['allowed']&&$outcome['result']===$expected&&($stored['status']??'')===$expected,
                'Admitted '.$action.' retains its actual synthetic gateway outcome after later revocation');
            $check((int)$row['active']===0&&!hash_equals($case['client']['auth_version'],$row['auth_version'])
                &&!db()->inTransaction()&&!array_filter($GLOBALS['swa_calls'],fn(array $call):bool=>$call['transaction']),
                'Admitted '.$action.' preserves the committed revocation and closes all transactions');
        }
        foreach(['app/billing/software_orders.php','app/billing/software_charge_guard.php','app/billing/software_client.php',
            'app/clients/identity.php','app/clients/accounts.php'] as $path){
            $GLOBALS['swa_source'][$path]=['file_sha256'=>is_file(CWP_CODE_ROOT.'/'.$path)?hash_file('sha256',CWP_CODE_ROOT.'/'.$path):null];
        }
        $report=['passed'=>!array_filter($checks,fn(array $row):bool=>!$row['passed']),'original_checks_passed'=>$originalCount,
            'checks'=>$checks,'source'=>$GLOBALS['swa_source'],'owner_workers'=>$GLOBALS['swa_owner_receipts'],'outcomes'=>$GLOBALS['swa_outcomes'],
            'scope'=>'Native SQL and actual separate-process studio_client_save; actual function bodies with only gateway transport/configuration synthetic. Four held-row-lock cases use an explicit SQL version transition. No HTTP, real merchant or external provider evidence.'];
        file_put_contents(__DIR__.'/../qa/software-commerce-native-authority.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
    }finally{$_SESSION=$savedSession;$_GET=$savedGet;$GLOBALS['cwp_support']=$savedSupport;$GLOBALS['swa_after_reservation']=null;}
    foreach($checks as $row)$assert($row['passed'],$row['name']);
}
