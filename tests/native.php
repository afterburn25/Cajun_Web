<?php
/** Isolated native MySQL/MariaDB integration tests. Never part of a deployment ZIP. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('CWP_TEST_DATABASE') !== 'cwp_test') { fwrite(STDERR,"Refusing tests without explicit isolated cwp_test database.\n"); exit(2); }
define('CWP_BOOT', true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/app/marketing/growth.php';
require __DIR__.'/../site/app/clients/workspace.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/admin/controllers/studio.php';
require __DIR__.'/../site/app/portfolio/browse.php';
$private = __DIR__.'/../test-runtime/private';
@mkdir($private.'/media',0700,true);
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/existing','storage'=>realpath($private),'key'=>str_repeat('synthetic-test-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:3306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'ci_']];
$_SERVER += ['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
start_session();
$results=[];
function ok(string $name, bool $condition): void { global $results; $results[]=['name'=>$name,'passed'=>$condition]; if(!$condition) throw new RuntimeException('FAILED: '.$name); }
function rejected(string $name, callable $f): void { try { $f(); } catch (InvalidArgumentException|RuntimeException $e) { if($e instanceof PDOException)throw $e;ok($name,true);return; } ok($name,false); }
function rowcount(string $table): int { return (int)query('SELECT COUNT(*) FROM '.table($table))->fetchColumn(); }
function admin_session(): void { $u=one('SELECT * FROM '.table('users').' WHERE id=1'); $_SESSION['uid']=1;$_SESSION['auth_version']=$u['auth_version'];$_SESSION['last_activity']=$_SESSION['login_at']=time(); }
function client_session(int $id): void { $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);$_SESSION['client_id']=$id;$_SESSION['client_auth']=$c['auth_version'];$_SESSION['client_last']=$_SESSION['client_login']=time(); }
function request_form(string $kind): array { $t=growth_token();$_SESSION['growth_tokens'][$t]=time()-3;$_POST['csrf']=csrf();return ['submission_token'=>$t,'kind'=>$kind,'name'=>'Synthetic prospect','email'=>'prospect@example.invalid','company'=>'Fixture','phone'=>'','timeline'=>'Flexible','message'=>'Please build a clearly organized business website.','consent'=>'1','website_address'=>'','project_type'=>'business','pages'=>'7','products'=>'0','options'=>[]]; }
try {
    ok('Native mysql PDO is loaded',extension_loaded('pdo_mysql')&&db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql');
    $server=(string)db()->getAttribute(PDO::ATTR_SERVER_VERSION);
    // This test schema is disposable. Never use application or customer credentials.
    foreach(all('SHOW TABLES') as $r){$name=(string)array_values($r)[0];if(!preg_match('/^ci_[a-z_]+$/D',$name))throw new RuntimeException('Unexpected table in isolated test DB.');db()->exec('DROP TABLE `'.$name.'`');}
    $legacy=json_decode(file_get_contents(__DIR__.'/../test-runtime/legacy-schema.json'),true,32,JSON_THROW_ON_ERROR);
    $definitions=$legacy['schema'];
    foreach($definitions as $key=>$sql)if(!str_starts_with($key,'studio_'))db()->exec($sql);
    foreach($legacy['settings'] as $key=>$value)save_setting($key,$value);
    save_setting('studio_schema_version','0');
    $now=gmdate('Y-m-d H:i:s');$pw='Synthetic owner passphrase 2026';
    query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (1,?,?,?,?,?)',['Owner','owner@example.invalid',password_hash($pw,PASSWORD_DEFAULT),str_repeat('a',32),$now]);
    query('INSERT INTO '.table('leads')." (reference,request_key,name,email,service,budget,timeline,message,consent,ip_hash,status,notes,created_at) VALUES (?,?,?,?,?,?,?,?,1,?,'new',?,?)",['OLD-LEAD',str_repeat('1',64),'Existing customer','existing@example.invalid','Redesign','Agreed','Flexible','Keep this original inquiry untouched.',str_repeat('2',64),'Original note',$now]);
    foreach(default_projects() as $i=>$v)query('INSERT INTO '.table('projects').' (slug,title,category,summary,description,challenge,solution,deliverables,cover,status,is_demo,featured,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,\'published\',1,1,?,?,?)',[...$v,$i,$now,$now]);
    foreach(default_services() as $i=>$v)query('INSERT INTO '.table('services').' (slug,title,summary,body,icon,sort_order,active) VALUES (?,?,?,?,?,?,1)',[...$v,$i]);
    save_setting('sentinel_original','Preserve original settings');
    query('INSERT INTO '.table('projects')." (slug,title,category,summary,description,challenge,solution,deliverables,cover,status,is_demo,featured,sort_order,created_at,updated_at) VALUES ('draft-fixture','Draft fixture','Internal','s','d','c','s','d','','draft',0,0,9,?,?)",[$now,$now]);
    $publicView=portfolio_view(null);
    ok('Adapter orders published portfolio deterministically',array_column($publicView['rows'],'slug')===['river-and-oak','magnolia-built','the-wellness-haus']);
    ok('Published categories feed the filter list',$publicView['categories']===['Construction','Hospitality','Wellness']);
    ok('Exact category selects its projects',portfolio_view('Construction')['visible']===[2]);
    ok('Unknown category matches nothing',portfolio_view('Retail')['visible']===[]);
    ok('Blank category stays unfiltered',portfolio_view(' ')['visible']===null);
    ok('Draft project never appears publicly',portfolio_row_by_slug('draft-fixture')===null&&count($publicView['rows'])===3);
    $record=one('SELECT * FROM '.table('projects').' WHERE id=1');$item=portfolio_item($record);
    ok('Adapter preserves explicit concept label',$item->kind==='concept');
    $record['is_demo']=0;ok('Adapter preserves approved client-work label',portfolio_item($record)->kind==='client-work');
    $record['category']=' 1 ';ok('Adapter normalizes numeric category as string',portfolio_item($record)->categories===['1']);
    $record['status']='unknown';ok('Unrecognized adapter status defaults to draft',portfolio_item($record)->status==='draft');
    $record['title']=' ';rejected('Malformed portfolio title is rejected',fn()=>portfolio_item($record));
    ok('Slug lookup does not case-fold',portfolio_row_by_slug('Magnolia-Built')===null);
    db()->beginTransaction();
    try{
        query('UPDATE '.table('projects')." SET category='1' WHERE id=1");
        query('UPDATE '.table('projects')." SET category='all' WHERE id=2");
        query('UPDATE '.table('projects')." SET category=' Café & Studio ' WHERE id=3");
        ok('Numeric category stays a string through adapter',portfolio_view('1')['categories']===['1','Café & Studio','all']);
        ok('Category all selects only its own project',portfolio_view('all')['visible']===[2]);
        $v=portfolio_view('Café & Studio');ok('Normalized Unicode category agrees with card data',$v['visible']===[3]&&$v['rows'][2]['category']==='Café & Studio');
    }finally{db()->rollBack();}
    $oldLead=one('SELECT * FROM '.table('leads').' WHERE id=1');
    $owner=one('SELECT * FROM '.table('users').' WHERE id=1');
    // Simulate an interrupted additive update that created only its first table.
    db()->exec(studio_schema('ci_')['studio_clients']);
    admin_session();studio_migrate();
    ok('Additive upgrade creates nine workspace tables',count(all("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'ci_studio_%'"))===9);
    ok('Upgrade retains populated lead',one('SELECT * FROM '.table('leads').' WHERE id=1')===$oldLead);
    ok('Upgrade retains owner account',one('SELECT * FROM '.table('users').' WHERE id=1')===$owner);
    ok('Upgrade retains original settings',setting('sentinel_original')==='Preserve original settings');
    ok('Upgrade saves schema marker',studio_ready());
    studio_migrate();ok('Repeated migration is idempotent',rowcount('leads')===1&&rowcount('users')===1);
    // Digit-bearing fields must be recognized; incompatible schemas still fail.
    db()->exec('ALTER TABLE '.table('studio_files').' MODIFY sha256 VARCHAR(64) NOT NULL');
    rejected('Migration rejects mismatched digest column type',fn()=>studio_migrate());
    ok('Failed migration leaves foreign column untouched',one('SHOW COLUMNS FROM '.table('studio_files')." LIKE 'sha256'")['Type']==='varchar(64)');
    db()->exec('ALTER TABLE '.table('studio_files').' MODIFY sha256 CHAR(64) NOT NULL');
    studio_migrate();ok('Matching digit-bearing digest column is accepted',studio_ready());
    $r=['enabled'=>true,'business'=>[100000,150000],'redesign'=>[120000,170000],'store'=>[200000,250000],'page'=>[5000,10000],'product'=>[100,200],'copy'=>[10000,15000]];
    save_setting('growth_prices',json_encode($r,JSON_THROW_ON_ERROR));
    $spec=['project_type'=>'business','pages'=>'7','options'=>['copy'],'low'=>'1','high'=>'2'];
    $e=growth_estimate($spec,$r);ok('Estimator uses trusted rules and ignores submitted amounts',$e['low']===120000&&$e['high']===185000);
    ok('Disabled price rules return consultation',growth_estimate($spec,[])['mode']==='consultation');
    ok('Custom integration returns consultation',growth_estimate(['project_type'=>'business','pages'=>5,'options'=>['integration']],$r)['mode']==='consultation');
    rejected('Estimator rejects array-valued project type',fn()=>growth_estimate(['project_type'=>[]],$r));
    rejected('Estimator rejects unknown options',fn()=>growth_estimate(['project_type'=>'business','options'=>['admin']],$r));
    rejected('Estimator rejects negative page counts',fn()=>growth_estimate(['project_type'=>'business','pages'=>'-1'],$r));
    rejected('Price parser rejects scientific notation',fn()=>gcents('1e5'));
    rejected('Price parser rejects reversed ranges',fn()=>grange([200,100]));
    $request=request_form('estimate');$first=growth_submit($request);$again=growth_submit($request);
    ok('Lead submission is idempotent',$first['reference']===$again['reference']&&rowcount('leads')===2);
    $lead=one('SELECT * FROM '.table('leads').' WHERE reference=?',[$first['reference']]);
    ok('Lead stores server-estimated amount',$lead['budget']==='$1,100.00 – $1,700.00 USD');
    ok('Lead persists with email disabled',$lead['notification_status']==='not_configured'||$lead['notification_status']==='disabled');
    $review=request_form('website-review');$review['current_url']='https://example.org';$rev=growth_submit($review);
    ok('Website review stores the requested URL',str_contains(one('SELECT message FROM '.table('leads').' WHERE reference=?',[$rev['reference']])['message'],'https://example.org'));
    $request=request_form('consultation');$request['timezone']='America/Chicago';$request['preferred_date']=(new DateTimeImmutable('+2 days',new DateTimeZone('America/Chicago')))->format('Y-m-d');$request['period']='Afternoon';$consult=growth_submit($request);
    ok('Consultation remains an unconfirmed request',str_contains(strtolower($consult['message']),'confirm')||str_contains(strtolower($consult['message']),'request'));
    $bad=request_form('estimate');$bad['consent']='0';rejected('Privacy acknowledgement required',fn()=>growth_submit($bad));
    $bad=request_form('estimate');$bad['website_address']='spam';rejected('Honeypot blocks automated submissions',fn()=>growth_submit($bad));
    $bad=request_form('estimate');$_SESSION['growth_tokens'][$bad['submission_token']]=time();rejected('Too-fast submission is blocked',fn()=>growth_submit($bad));
    $bad=request_form('estimate');$_SESSION['growth_tokens'][$bad['submission_token']]=time()-7201;rejected('Expired public form token rejected',fn()=>growth_submit($bad));
    $before=rowcount('leads');growth_submit(request_form('estimate'));growth_submit(request_form('estimate'));
    rejected('Public hourly limit blocks further saved leads',fn()=>growth_submit(request_form('estimate')));
    ok('Rate rejection does not create another lead',rowcount('leads')===$before+2);
    $_POST['csrf']='wrong';rejected('CSRF rejected before saving',fn()=>growth_submit(request_form_no_token()));
} catch(Throwable $e) { $error=$e; }
// Continue only when the first group passed; a test error must fail CI, not be hidden.
if(!isset($error))try {
    foreach([['Alice','alice@example.invalid'],['Bob','bob@example.invalid']] as [$name,$email])query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',[$name,$email,bin2hex(random_bytes(16)),$now]);
    foreach([1,2] as $cid)query('INSERT INTO '.table('studio_jobs').' (client_id,title,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$cid,'Project '.$cid,'Private project '.$cid,'Discovery → Design → Launch',$now,$now]);
    $url=studio_new_invite(1,one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version']);parse_str((string)parse_url($url,PHP_URL_QUERY),$parts);$token=$parts['invite'];
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');ok('Invitation stored as hash, not raw token',$c['invite_hash']===hash('sha256',$token)&&!str_contains(json_encode($c),$token));
    studio_activate($token,'Synthetic client passphrase 2026');
    ok('Activation hashes password and consumes invite',password_verify('Synthetic client passphrase 2026',one('SELECT password_hash FROM '.table('studio_clients').' WHERE id=1')['password_hash'])&&one('SELECT invite_hash FROM '.table('studio_clients').' WHERE id=1')['invite_hash']===null);
    rejected('Invitation replay rejected',fn()=>studio_activate($token,'Another synthetic passphrase 2026'));
    $expired=studio_new_invite(2,one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=2')['auth_version']);parse_str((string)parse_url($expired,PHP_URL_QUERY),$parts);
    query('UPDATE '.table('studio_clients').' SET invite_expires=? WHERE id=2',[time()-1]);
    rejected('Expired client invitation cannot activate',fn()=>studio_activate($parts['invite'],'Synthetic client passphrase 2026'));
    ok('Expired activation preserves unset password',one('SELECT password_hash FROM '.table('studio_clients').' WHERE id=2')['password_hash']==='');
    studio_login('alice@example.invalid','Synthetic client passphrase 2026');ok('Client authentication succeeds',(int)studio_client()['id']===1);
    rejected('Client cannot read another client project',fn()=>studio_job(2,1));
    admin_session();studio_publish(1,'design','Design one','Original version text','https://example.org/preview');$v1=(int)query('SELECT MAX(id) FROM '.table('studio_versions'))->fetchColumn();
    studio_publish(1,'design','Design two','Revised version text','https://example.org/preview');$v2=(int)query('SELECT MAX(id) FROM '.table('studio_versions'))->fetchColumn();
    rejected('Superseded approval rejected',fn()=>studio_decide(1,$v1,'approved',''));
    rejected('Another client cannot approve a project',fn()=>studio_decide(2,$v2,'approved',''));
    studio_decide(1,$v2,'approved','Looks good.');
    ok('Approval records exact version and client',one('SELECT decision,decided_by FROM '.table('studio_versions').' WHERE id=?',[$v2])==['decision'=>'approved','decided_by'=>1]);
    rejected('Duplicate approval rejected',fn()=>studio_decide(1,$v2,'approved',''));
    ok('Prior version content immutable',one('SELECT body FROM '.table('studio_versions').' WHERE id=?',[$v1])['body']==='Original version text');
    studio_publish(1,'proposal','Proposal one','Scope and fee for review','');$proposal=(int)query('SELECT MAX(id) FROM '.table('studio_versions'))->fetchColumn();
    rejected('Change request requires feedback',fn()=>studio_decide(1,$proposal,'changes',''));
    studio_decide(1,$proposal,'changes','Please clarify the content scope.');
    ok('Change request records feedback',one('SELECT decision FROM '.table('studio_versions').' WHERE id=?',[$proposal])['decision']==='changes');
    client_session(1);$oldAuth=$_SESSION['client_auth'];studio_new_invite(1,one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version']);ok('New invitation revokes older client session',studio_client()===null);
    studio_revision_save('growth_intro','First saved copy',hash('sha256',setting('growth_intro')));
    $expect=hash('sha256',setting('growth_intro'));studio_revision_save('growth_intro','Second saved copy',$expect);
    rejected('Content edit conflict rejected',fn()=>studio_revision_save('growth_intro','Stale edit',$expect));
    ok('Conflicting edit does not replace newer content',setting('growth_intro')==='Second saved copy');
    $old=one('SELECT old_value FROM '.table('studio_revisions')." WHERE setting_name='growth_intro' ORDER BY id DESC LIMIT 1")['old_value'];
    studio_revision_save('growth_intro',$old,hash('sha256',setting('growth_intro')));
    ok('Restore creates a new revision without deleting history',setting('growth_intro')==='First saved copy'&&rowcount('studio_revisions')===3);
    rejected('History rejects arbitrary configuration keys',fn()=>studio_revision_save('smtp_password','bad',hash('sha256','')));
    // Native test fixtures are outside the release source and use reserved .invalid identities.
    query('UPDATE '.table('studio_clients').' SET password_hash=?,invite_hash=NULL,invite_expires=NULL,active=1 WHERE id IN (1,2)',[password_hash('Synthetic client passphrase 2026',PASSWORD_DEFAULT)]);
    query('DELETE FROM '.table('rate_limits'));
    @mkdir(__DIR__.'/../test-runtime/site',0755,true);
    $config=$GLOBALS['cwp_config'];
    file_put_contents(__DIR__.'/../test-runtime/config-fixture.php','<?php return '.var_export($config,true).';');
    ok('All planned tables use InnoDB',count(all("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'ci_%' AND ENGINE <> 'InnoDB'"))===0);
} catch(Throwable $e){$error=$e;}
@mkdir(__DIR__.'/../qa',0755,true);
file_put_contents(__DIR__.'/../qa/native-results.json',json_encode(['driver'=>'pdo_mysql','server'=>$server??'not connected','checks'=>$results,'passed'=>!isset($error),'failure'=>isset($error)?get_class($error).': '.$error->getMessage():null],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
foreach($results as $r)echo ($r['passed']?'PASS ':'FAIL ').$r['name']."\n";
if(isset($error)){fwrite(STDERR,get_class($error).': '.$error->getMessage()."\n".$error->getTraceAsString()."\n");exit(1);}
echo 'Native checks: '.count($results)." passed.\n";
function request_form_no_token():array{return ['submission_token'=>str_repeat('a',48)];}
