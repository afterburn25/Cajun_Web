<?php
/** Real SQL row-lock regression; disposable test storage, no provider calls. */
declare(strict_types=1);

function software_domain_concurrency_action(string $action,int $licenseId,string $oldDomain,string $newDomain,int $actor):void {
    match($action){
        'register'=>software_grant_register_domain($licenseId,$newDomain,$actor),
        'deactivate'=>software_grant_deactivate_domain($licenseId,$oldDomain,$actor),
        'replace'=>software_grant_replace_domain($licenseId,$oldDomain,$newDomain,$actor),
        default=>throw new InvalidArgumentException('Unknown domain concurrency action.')
    };
}

if(($argv[1]??'')==='domain-lock-worker'){
    if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
    $prefix=$argv[2]??'';
    if(!preg_match('/\A[a-z][a-z0-9_]{1,30}_\z/',$prefix))exit(2);
    define('CWP_BOOT',true);
    require __DIR__.'/../site/includes/bootstrap.php';
    $GLOBALS['cwp_config']=require __DIR__.'/../test-runtime/config-fixture.php';
    $GLOBALS['cwp_config']['db']['prefix']=$prefix;
    require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
    if(query('SELECT DATABASE()')->fetchColumn()!=='cwp_test')exit(2);
    $receipt=$argv[8]??'';
    if(!is_dir(dirname($receipt))||is_file($receipt.'.ready.json'))exit(2);
    file_put_contents($receipt.'.ready.json',json_encode(['connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR));
    try{
        software_domain_concurrency_action($argv[3],(int)$argv[4],$argv[5],$argv[6],(int)$argv[7]);
        $result=['allowed'=>true,'exception'=>null];
    }catch(Throwable $error){
        $result=['allowed'=>false,'exception'=>get_class($error),'message'=>$error->getMessage()];
    }
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

/** Stage a concurrent lifecycle change, then prove the waiting mutation rechecks it. */
function software_domain_lifecycle_concurrency_checks(int $clientId,string $sku,int $actor,callable $assert):void {
    if(getenv('CWP_TEST_DATABASE')!=='cwp_test'||query('SELECT DATABASE()')->fetchColumn()!=='cwp_test'){
        throw new RuntimeException('Disposable concurrency database required.');
    }
    $directory=sys_get_temp_dir().'/cwp-domain-lock-'.bin2hex(random_bytes(8));
    if(!mkdir($directory,0700))throw new RuntimeException('Concurrency evidence directory unavailable.');
    $prefix=config()['db']['prefix'];
    foreach(['suspended','expired'] as $state){
        foreach(['register','deactivate','replace'] as $action){
            $tag=$state.'-'.$action.'-'.bin2hex(random_bytes(5));
            $oldDomain=$tag.'.example';$newDomain='next-'.$oldDomain;
            $kind=$state==='expired'?'trial':'standard';
            $expires=$state==='expired'?gmdate('Y-m-d H:i:s',time()+3600):null;
            db()->beginTransaction();
            try{
                $grant=software_grant_create($clientId,$sku,$kind,'Domain lock regression',$oldDomain,$expires,$actor);
                db()->commit();
            }catch(Throwable $error){db()->rollBack();throw $error;}
            $licenseId=(int)$grant['id'];
            if($action==='register'&&(int)$grant['max_installations']<2)throw new RuntimeException('Concurrency fixture needs a spare installation.');
            $domains=all('SELECT domain,is_active FROM '.table('license_domains').' WHERE license_id=? ORDER BY domain',[$licenseId]);
            $receipt=$directory.'/'.$tag;
            db()->beginTransaction();
            license_lock($licenseId);
            if($state==='suspended')query('UPDATE '.table('website_licenses').' SET is_active=0 WHERE id=?',[$licenseId]);
            else query('UPDATE '.table('software_entitlements').' SET expires_at=? WHERE license_id=?',[gmdate('Y-m-d H:i:s',time()-60),$licenseId]);
            $process=null;
            try{
                $process=proc_open([PHP_BINARY,__FILE__,'domain-lock-worker',$prefix,$action,(string)$licenseId,$oldDomain,$newDomain,(string)$actor,$receipt],
                    [0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$receipt.'.stdout.json','w'],2=>['file',$receipt.'.stderr.log','w']],$pipes);
                if(!is_resource($process))throw new RuntimeException('Concurrency worker failed to start.');
                $blocked=false;$deadline=microtime(true)+15;
                do{
                    $ready=is_file($receipt.'.ready.json')?json_decode(file_get_contents($receipt.'.ready.json'),true):null;
                    if(isset($ready['connection'])){
                        $current=one('SELECT INFO FROM information_schema.processlist WHERE ID=?',[(int)$ready['connection']]);
                        if($current&&str_contains((string)$current['INFO'],$prefix.'website_licenses')&&str_contains((string)$current['INFO'],'FOR UPDATE')){
                            $blocked=true;break;
                        }
                    }
                    if(!proc_get_status($process)['running'])throw new RuntimeException('Concurrency worker ended before lock observation: '.file_get_contents($receipt.'.stderr.log').file_get_contents($receipt.'.stdout.json'));
                    usleep(20000);
                }while(microtime(true)<$deadline);
                $assert($blocked,$state.' '.$action.' waits for the held license row lock');
                db()->commit();
                $deadline=microtime(true)+15;
                do{$status=proc_get_status($process);if(!$status['running'])break;usleep(20000);}while(microtime(true)<$deadline);
                if($status['running'])throw new RuntimeException('Concurrency worker did not finish after lock release.');
                $stderr=file_get_contents($receipt.'.stderr.log');
                if($stderr!=='')throw new RuntimeException('Concurrency worker diagnostics: '.$stderr);
                $response=json_decode(file_get_contents($receipt.'.stdout.json'),true,512,JSON_THROW_ON_ERROR);
                $expected=$state==='suspended'?'This software license is suspended or revoked.':'This software license has expired.';
                $assert(!$response['allowed']&&$response['exception']===RuntimeException::class&&$response['message']===$expected,
                    $state.' '.$action.' is denied after acquiring the license lock');
                $after=all('SELECT domain,is_active FROM '.table('license_domains').' WHERE license_id=? ORDER BY domain',[$licenseId]);
                $assert($after===$domains,$state.' '.$action.' denial preserves all installation domains');
                $current=one('SELECT l.is_active,e.expires_at FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.id=?',[$licenseId]);
                $assert($state==='suspended'?(int)$current['is_active']===0:(string)$current['expires_at']<gmdate('Y-m-d H:i:s'),
                    $state.' '.$action.' denial preserves the committed lifecycle state');
                // Positive control on the same fixture after an explicit test recovery.
                if($state==='suspended')license_reactivate($licenseId,$actor);
                else{
                    db()->beginTransaction();license_lock($licenseId);
                    query('UPDATE '.table('software_entitlements').' SET expires_at=? WHERE license_id=?',[gmdate('Y-m-d H:i:s',time()+3600),$licenseId]);db()->commit();
                }
                software_domain_concurrency_action($action,$licenseId,$oldDomain,$newDomain,$actor);
                $assert(all('SELECT domain,is_active FROM '.table('license_domains').' WHERE license_id=? ORDER BY domain',[$licenseId])!==$domains,
                    $state.' '.$action.' positive control permits an active unexpired grant');
            }finally{
                if(db()->inTransaction())db()->rollBack();
                if(is_resource($process)){
                    if(proc_get_status($process)['running'])proc_terminate($process);
                    proc_close($process);
                }
            }
        }
    }
    foreach(glob($directory.'/*') as $file)unlink($file);
    rmdir($directory);
}
