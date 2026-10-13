<?php
/** CLI-only queries against explicitly disposable acceptance fixtures. Never shipped. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('CWP_TEST_DATABASE') !== 'cwp_test') exit(2);
define('CWP_BOOT', true);
require __DIR__.'/../site/app/core.php';
$GLOBALS['cwp_config'] = require __DIR__.'/../test-runtime/config-fixture.php';
$request = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
if (($request['operation'] ?? '') === 'seed-upgrade') {
    require __DIR__.'/../test-runtime/legacy/app/schema.php';
    $GLOBALS['cwp_config']['db']['prefix'] = 'upgrade_';
    $GLOBALS['cwp_config']['site_url'] = 'http://127.0.0.1:8091/upgrade';
    $private = __DIR__.'/../test-runtime/upgrade-private';
    if (!is_dir($private)) mkdir($private, 0700, true);
    if (!is_dir($private.'/media')) mkdir($private.'/media', 0700);
    $GLOBALS['cwp_config']['storage'] = realpath($private);
    foreach (schema_statements('upgrade_') as $sql) db()->exec($sql);
    foreach (default_settings() as $key=>$value) save_setting($key,$value);
    save_setting('sentinel_original','Keep original 1.0.8 settings');
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
        ['Upgrade owner','upgrade@example.invalid',password_hash('Synthetic upgrade passphrase 2026',PASSWORD_DEFAULT),str_repeat('c',32),$now]);
    query('INSERT INTO '.table('leads')." (reference,request_key,name,email,service,budget,timeline,message,consent,ip_hash,status,notes,created_at) VALUES (?,?,?,?,?,?,?,?,1,?,'new',?,?)",
        ['UPGRADE-LEAD',str_repeat('3',64),'Old inquiry','old@example.invalid','Design','Discuss','Flexible','Preserve the original inquiry.',str_repeat('4',64),'Keep this note',$now]);
    foreach(default_projects() as $i=>$v) query('INSERT INTO '.table('projects')." (slug,title,category,summary,description,challenge,solution,deliverables,cover,status,is_demo,featured,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'published',1,1,?,?,?)",[...$v,$i,$now,$now]);
    file_put_contents($private.'/media/retained.php', CWP_GUARD.'Original private media');
    file_put_contents(__DIR__.'/../test-runtime/upgrade-config.php','<?php return '.var_export(config(),true).';');
    echo json_encode(['ok'=>true]);
} else {
    $stmt=query($request['sql'],$request['params']??[]);
    echo json_encode($stmt->columnCount() ? $stmt->fetchAll() : ['changed'=>$stmt->rowCount()],JSON_THROW_ON_ERROR);
}
