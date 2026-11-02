<?php
/** Disposable finance fixture: helpers retained from the recurring native suite. No installed config. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||getenv('CWP_RECURRING_TEST_PREFIX')!=='rbill_rel_')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';require __DIR__.'/../site/database/schema/base.php';require_once __DIR__.'/../site/app/billing/recurring_billing_invoices.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
$prefix=getenv('CWP_RECURRING_TEST_PREFIX');if(!preg_match('/\Arbill_[a-z0-9_]{0,8}\z/',$prefix))exit(2);
$private=__DIR__.'/../test-runtime/release-finance-private';@mkdir($private,0700,true);@mkdir(__DIR__.'/../qa',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-recurring-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>$prefix]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/../site');start_session();
$checks=[];$password='Synthetic recurring owner passphrase';
function rb_id(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function rb_ok(string $name,bool $pass):void {global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function rb_deny(string $name,callable $fn):void {try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException|JsonException $e){if($e instanceof PDOException)throw $e;rb_ok($name,true);return;}rb_ok($name,false);}
function rb_staff(int $id=1):void {$u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);$_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];}
function rb_client(int $id=1):void {$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time()];unset($GLOBALS['cwp_support']);$_GET=[];}
function rb_fields(int $id,int $source=100):array {$s=service_plans_validate(service_plans_row(rb_id($source),true),true);return ['format'=>1,'id'=>rb_id($id),'client_id'=>1,'project_id'=>1,'source_subscription'=>['id'=>rb_id($source),'revision'=>$s->revision(),'sha256'=>$s->fingerprint()],'title'=>'Agreed recurring care','terms'=>'Explicit whole-period invoice terms. No automatic payment.','currency'=>'USD','amount_cents'=>$s->model()->agreedPrice,'cadence'=>'monthly','anchor'=>gmdate('Y-m-d',time()+4*86400),'alignment'=>'day-of-month','timezone'=>'UTC','due_after_days'=>14,'collection'=>'invoice-only'];}
function rb_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function rb_fail(callable $fn,string $table='audit'):bool {$trigger=config()['db']['prefix'].'fail';db()->exec('CREATE TRIGGER `'.$trigger.'` BEFORE INSERT ON '.table($table)." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic storage failure'");try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER `'.$trigger.'`');}return $failed;}
/** Synthetic historical source/consent fixture, built with the real canonical documents. */
function rb_historical_source(int $id,int $price=1001):void {
    $at=1570000000;$terms=['name'=>'Care','cadence'=>'monthly','price'=>$price,'currency'=>'USD','inclusions'=>['Care'],'exclusions'=>[],'limits'=>[],'support'=>'Business hours'];
    $p0=ServicePlanDocument::create(rb_id($id+1),1,$at,$terms);$p=$p0->append(rb_id($id+2),'activate',1,$at+1,[]);
    query('INSERT INTO '.table('service_plans').' (id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,1,?,?,?,?,1)',[rb_id($id+1),$at,$p0->json(),$p->json(),$p->fingerprint()]);
    query('INSERT INTO '.table('service_plan_events').' (id,plan_id,actor_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,1,1,?,?,?,?)',[rb_id($id+2),rb_id($id+1),$at+1,service_plans_json(['revision'=>0,'command'=>'activate','arguments'=>[]]),$p0->fingerprint(),$p->fingerprint()]);
    $s0=ServiceSubscriptionDocument::create(rb_id($id),1,1,$at+2,$p);$s1=$s0->append(rb_id($id+3),'consent','client:1',$at+3,['terms_hash'=>$p->model()->termsHash()]);$s=$s1->append(rb_id($id+4),'activate','staff:1',$at+4,[]);
    query('INSERT INTO '.table('service_subscriptions').' (id,plan_id,plan_revision,plan_sha256,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,1,?,1,1,?,?,?,?,2)',[rb_id($id),rb_id($id+1),$p->fingerprint(),$at+2,$s0->json(),$s->json(),$s->fingerprint()]);
    foreach([[$s0,$s1,null,1],[$s1,$s,1,null]] as [$before,$after,$staff,$client]){$e=$after->data()['events'][array_key_last($after->data()['events'])];query('INSERT INTO '.table('service_subscription_events').' (id,subscription_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[$e['id'],rb_id($id),$staff,$client,$after->revision(),$e['at'],service_plans_json(['revision'=>$before->revision(),'command'=>$e['command'],'arguments'=>$e['arguments']]),$before->fingerprint(),$after->fingerprint()]);}
}
