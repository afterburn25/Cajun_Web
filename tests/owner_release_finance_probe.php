<?php
/** Populate or inspect ONLY an installed disposable RC fixture, never a live site. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==3)exit(2);
$allowed=realpath(getcwd().'/test-runtime/owner-release');$root=realpath($argv[1]);$mode=$argv[2];
if(!$allowed||!$root||!str_starts_with($root,$allowed.DIRECTORY_SEPARATOR)||is_link($argv[1])||!in_array($mode,['seed','verify','snapshot'],true))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';$c=config();
if($c['db']['name']!=='cwp_test'||$c['db']['host']!=='127.0.0.1'||!preg_match('/^own(?:r|n)[rs]_$/D',$c['db']['prefix'])||!str_starts_with($c['site_url'],'http://127.0.0.1:'))exit(2);
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>parse_url($c['site_url'],PHP_URL_HOST).':'.parse_url($c['site_url'],PHP_URL_PORT),'SCRIPT_NAME'=>'/admin/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root;start_session();require_once $root.'/app/clients/workspace.php';require_once $root.'/app/staff/accounts.php';
require_once $root.'/app/licenses/financing_offers.php';require_once $root.'/app/licenses/finance_installments.php';require_once $root.'/app/billing/finance_subscription.php';
$password='Synthetic owner release passphrase';
function owner_actor():void{$u=one('SELECT * FROM '.table('users').' WHERE id=1');if(!$u||$u['email']!=='release@example.invalid')throw new RuntimeException('Wrong fixture owner');$_SESSION=['uid'=>1,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];}
function rid(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function historical_source(int $id):void{
    $at=1570000000;$terms=['name'=>'Fixture care','cadence'=>'monthly','price'=>2500,'currency'=>'USD','inclusions'=>['Care'],'exclusions'=>[],'limits'=>[],'support'=>'Business hours'];
    $p0=ServicePlanDocument::create(rid($id+1),1,$at,$terms);$p=$p0->append(rid($id+2),'activate',1,$at+1,[]);
    query('INSERT INTO '.table('service_plans').' (id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,1,?,?,?,?,1)',[rid($id+1),$at,$p0->json(),$p->json(),$p->fingerprint()]);
    query('INSERT INTO '.table('service_plan_events').' (id,plan_id,actor_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,1,1,?,?,?,?)',[rid($id+2),rid($id+1),$at+1,service_plans_json(['revision'=>0,'command'=>'activate','arguments'=>[]]),$p0->fingerprint(),$p->fingerprint()]);
    $s0=ServiceSubscriptionDocument::create(rid($id),1,1,$at+2,$p);$s1=$s0->append(rid($id+3),'consent','client:1',$at+3,['terms_hash'=>$p->model()->termsHash()]);$s=$s1->append(rid($id+4),'activate','staff:1',$at+4,[]);
    query('INSERT INTO '.table('service_subscriptions').' (id,plan_id,plan_revision,plan_sha256,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,1,?,1,1,?,?,?,?,2)',[rid($id),rid($id+1),$p->fingerprint(),$at+2,$s0->json(),$s->json(),$s->fingerprint()]);
    foreach([[$s0,$s1,null,1],[$s1,$s,1,null]] as [$before,$after,$staff,$client]){$e=$after->data()['events'][array_key_last($after->data()['events'])];query('INSERT INTO '.table('service_subscription_events').' (id,subscription_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$e['id'],rid($id),$staff,$client,$after->revision(),$e['at'],service_plans_json(['revision'=>$before->revision(),'command'=>$e['command'],'arguments'=>$e['arguments']]),$before->fingerprint(),$after->fingerprint()]);}
}
function snapshot():array{
    $out=['version'=>CWP_VERSION,'config'=>hash_file('sha256',CWP_ROOT.'/config.php'),'lock'=>hash_file('sha256',CWP_ROOT.'/.installed.php'),'tables'=>[],'private'=>[]];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,?)=? ORDER BY TABLE_NAME',[strlen(config()['db']['prefix']),config()['db']['prefix']]) as $t){
        $name=substr($t['TABLE_NAME'],strlen(config()['db']['prefix']));if($name==='rate_limits')continue;if(!preg_match('/^[a-z][a-z0-9_]*$/D',$name))throw new RuntimeException('Invalid fixture table');
        $rows=[];foreach(all('SELECT * FROM '.table($name)) as $row){if($name==='users')unset($row['last_login']);ksort($row);$rows[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));}sort($rows);$out['tables'][$name]=$rows;
    }
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(config()['storage'],FilesystemIterator::SKIP_DOTS)) as $file){
        $name=substr($file->getPathname(),strlen(config()['storage'])+1);if(preg_match('~^(?:structure-[^/]+|sessions)(?:/|$)~',$name))continue;
        if($file->isLink())throw new RuntimeException('Private symlink');if($file->isFile())$out['private'][$name]=hash_file('sha256',$file->getPathname());
    }ksort($out['private']);return $out;
}
owner_actor();
if($mode==='seed'){
    if(CWP_VERSION!=='1.26.0'||(int)query('SELECT COUNT(*) FROM '.table('studio_clients'))->fetchColumn()!==0)throw new RuntimeException('Fresh exact RC fixture required');
    $now=gmdate('Y-m-d H:i:s');query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,active,created_at) VALUES (?,?,?,1,?)',['Retained finance client','finance@example.invalid',str_repeat('e',32),$now]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,stage,summary,milestones,created_at,updated_at) VALUES (1,?,?,?,?,?,?)',['Retained financed project','design','Original description','Original milestones',$now,$now]);
    require_once $root.'/database/migrations/license_migrate.php';license_migrate($password);catalog_migrate($password);payments_migrate($password);ledger_migrate($password);service_plans_migrate($password);recurring_billing_migrate($password);license_financing_prepare($password);license_finance_offers_prepare($password);license_finance_gateway_prepare($password);
    foreach(['active','suspended','paid_off'] as $i=>$state){
        owner_actor();$source=50000+$i*10;historical_source($source);$s=service_plans_validate(service_plans_row(rid($source),true),true);
        $fields=['format'=>1,'id'=>rid(51000+$i),'client_id'=>1,'project_id'=>1,'source_subscription'=>['id'=>rid($source),'revision'=>$s->revision(),'sha256'=>$s->fingerprint()],'title'=>'Retained '.$state,'terms'=>'Explicit synthetic installment terms. No network payment.','currency'=>'USD','amount_cents'=>2500,'cadence'=>'monthly','anchor'=>gmdate('Y-m-d',time()+2*86400),'alignment'=>'day-of-month','timezone'=>'UTC','due_after_days'=>0,'collection'=>'invoice-only'];
        recurring_billing_offer($fields);$key=license_create(1,'RETAIN-'.strtoupper($state),1,false,null);$id=(int)license_get($key)['id'];license_register_domain($id,str_replace('_','-',$state).'.example.invalid',1);
        $offer=license_finance_offer_create($id,$fields['id'],'Retained '.$state.' agreement',10000,0,10000,0,0,4,5,1,7,['resale'=>false,'distribution'=>false,'exclusive_resale'=>false,'exclusive_distribution'=>false],'Synthetic retained agreement. No resale or distribution rights are granted. These are isolated test records only.',$password);
        $_SESSION=['client_id'=>1,'client_auth'=>str_repeat('e',32),'client_login'=>time(),'client_last'=>time()];license_finance_offer_sign($offer['id'],1,'Retained finance client',true,true);owner_actor();
        $doc=recurring_billing_validate(recurring_billing_row($fields['id']));$terms=\CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($doc->data()['terms']);
        if($state!=='active'){
            $last=$state==='paid_off'?3:0;
            for($p=0;$p<=$last;$p++){
                $period=$terms->period($p);$issued=license_finance_issue_period($offer['id'],$p,strtotime($period['start'].' 12:00:00 UTC'));
                if($state==='paid_off'){$invoice=ledger_invoice((int)$issued['invoice_id']);$entry=ledger_insert($invoice,1,'gateway',2500,'authorize_net','Synthetic retained paid receipt',$period['due'],null,null,hash('sha256','owner-upgrade-'.$i.'-'.$p));query('UPDATE '.table('studio_invoices').' SET status=?,paid_note=?,paid_at=? WHERE id=?',['paid','Synthetic retained entry '.$entry,$now,$invoice['id']]);}
            }
            $date=(new DateTimeImmutable($period['due'].'T00:00:00Z'))->modify('+6 days')->format('Y-m-d');license_financing_sync($id,$date);
        }
        if(license_financing_plan($id)['state']!==$state)throw new RuntimeException('Did not prepare '.$state.' state');
    }
    write_private_file(config()['storage'].'/retained-upload.php',CWP_GUARD.'SYNTHETIC RETAINED CUSTOMER FILE');
    echo json_encode(['seeded'=>true,'states'=>array_column(all('SELECT state FROM '.table('license_financing').' ORDER BY license_id'),'state')],JSON_THROW_ON_ERROR);exit;
}
if($mode==='snapshot'){echo json_encode(snapshot(),JSON_THROW_ON_ERROR);exit;}
$before=snapshot();$paid=one('SELECT l.* FROM '.table('website_licenses').' l JOIN '.table('license_financing')." f ON f.license_id=l.id WHERE f.state='paid_off'");$denied=false;
try{license_deactivate((int)$paid['id'],1);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$denied=str_contains($e->getMessage(),'permanently unlocked');}
$after=snapshot();$valid=$denied&&$before===$after&&license_financing_authorized((int)$paid['id'])===true&&(int)license_get($paid['license_key'])['is_active']===1;
$states=array_column(all('SELECT state FROM '.table('license_financing').' ORDER BY license_id'),'state');
echo json_encode(['permanent_unlock'=>$valid,'states'=>$states,'payment_mode'=>setting('software_gateway_mode','disabled')],JSON_THROW_ON_ERROR);
if(!$valid||$states!==['active','suspended','paid_off'])exit(1);
