<?php
/** Pure shipping-boundary contract for private project notes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);
$read=static fn(string $path):string=>file_get_contents($root.'/'.$path)?:throw new RuntimeException('Missing source: '.$path);
$adapter=$read('site/app/projects/private_notes.php');
$migration=$read('site/database/migrations/project_private_notes_migrate.php');
$schema=$read('site/database/schema/project_private_notes_schema.php');
$view=$read('site/admin/views/studio/private_notes.php');
$router=$read('site/admin/controllers/router.php');
$staff=$read('site/app/staff/accounts.php');
$events=$read('site/app/notifications/events.php');
$registry=$read('site/app/settings/database_schemas.php');
$base=$read('site/database/schema/base.php');
$builder=$read('tools/build.py');
$checks=[];
function pnp_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
try{
    pnp_ok('Runtime requires exact direct clients-manage authority and current session',str_contains($adapter,"staff_require('clients.manage')")&&str_contains($adapter,'admin_session_matches($actor)'));
    pnp_ok('Runtime refuses active support state explicit support query and retained binding',str_contains($adapter,'support_current()')&&str_contains($adapter,"array_key_exists('support',\$_GET)")&&str_contains($adapter,"support_binding()!==''"));
    pnp_ok('Create serializes authority canonicalization note and audit under one lock and transaction',str_contains($adapter,'return staff_locked(function()')&&str_contains($adapter,'return staff_transaction(function()')&&strpos($adapter,'project_private_notes_actor();$body=ProjectPrivateNote::canonicalBody($body)')<strpos($adapter,"INSERT INTO '.table('project_private_notes')")&&strpos($adapter,"INSERT INTO '.table('project_private_notes')")<strpos($adapter,"INSERT INTO '.table('audit')"));
    pnp_ok('Create derives actor and time on the server',str_contains($adapter,'(int)$actor[\'id\']')&&str_contains($adapter,'$created=time()')&&!str_contains($adapter,'created_epoch,body) VALUES (?,?,?,?,?)'));
    pnp_ok('Adapter exposes no update or delete path for immutable note records',preg_match("/(?:UPDATE|DELETE FROM) '.table\('project_private_notes'\)/",$adapter)!==1);
    pnp_ok('Adapter has no public message notification activity mail payment or provider projection',!preg_match('/studio_messages|notification_(?:emit|project)|activity_|send_message|mail_|payment|provider_/',$adapter));
    pnp_ok('Every list row is model-validated and page order is verified',str_contains($adapter,'project_private_notes_record($row)')&&str_contains($adapter,'ProjectPrivateNote::verifyStaffPage')&&str_contains($adapter,"ORDER BY n.id DESC LIMIT '.ProjectPrivateNote::MAX_PAGE"));
    pnp_ok('Schema is isolated binary text with retaining project and staff relationships',str_contains($schema,'body TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL')&&substr_count($schema,'ON DELETE RESTRICT ON UPDATE RESTRICT')===2);
    pnp_ok('Migration rejects unknown adoption and audits readiness atomically',str_contains($migration,'Unrecognized private project notes exist. No data was adopted.')&&str_contains($migration,'staff_transaction(function()')&&str_contains($migration,"project.private_notes.setup"));
    pnp_ok('Fresh install and registry use a separate optional marker',str_contains($base,"'project_private_notes_schema_version'=>'1'")&&str_contains($registry,"'project_private_notes_schema_version'=>'1'")&&str_contains($registry,"database/migrations/project_private_notes_migrate.php"));
    pnp_ok('Admin action is explicitly mapped to clients-manage permission',str_contains($staff,"'studio_private_note'")&&str_contains($staff,"return 'clients.manage'"));
    pnp_ok('Project screen and mutation are private no-store and support-refused',str_contains($router,"\$view==='studio-job'")&&str_contains($router,'Cache-Control: no-store, private')&&str_contains($router,"['studio-clients','studio-job','studio-licenses'"));
    pnp_ok('View escapes staff identity and note body before rendering',str_contains($view,'esc($entry[\'staff_name\'].')&&str_contains($view,'nl2br(esc($note[\'body\'])'));
    pnp_ok('View clearly discloses non-projection and immutable behavior',str_contains($view,'never added to the client conversation, notifications, activity, email, or support sessions')&&str_contains($view,'Save immutable private note'));
    pnp_ok('Project notification binds exact source row project and stored author type',str_contains($events,'WHERE m.id=? AND m.job_id=? AND m.author_type=?')&&str_contains($events,"\$authorType=\$from==='staff'?'admin':\$from"));
    pnp_ok('Canonical project model is included in deployment mirroring',str_contains($builder,"'proposals','projects','desk'")&&is_file($root.'/foundation/projects/ProjectPrivateNote.php'));
    echo json_encode(['passed'=>true,'checks'=>$checks,'executions'=>count($checks),'scope'=>'Static shipping-boundary policy only; native database and browser behavior are separate.'],JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $error){
    echo json_encode(['passed'=>false,'checks'=>$checks,'executions'=>count($checks),'failure'=>$error->getMessage()],JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(1);
}
