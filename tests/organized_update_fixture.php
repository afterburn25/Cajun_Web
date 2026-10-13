<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$root=realpath($argv[1]);$base=realpath(__DIR__.'/../test-runtime');
if(!$root || !$base || !str_starts_with($root,$base.DIRECTORY_SEPARATOR))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);
require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';
if(config()['db']['name']!=='cwp_test')exit(2);
if(($argv[2]??'')==='seed'){
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',['Organized retained client','organized-client@example.invalid',str_repeat('e',32),$now]);
    query('INSERT INTO '.table('studio_jobs').' (client_id,title,stage,summary,milestones,created_at,updated_at) VALUES (?,?,?,?,?,?,?)',[1,'Organized retained website','design','Retain summary','Retain progress',$now,$now]);
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,instructions,created_at) VALUES (1,?,?,?,?,?)',['ORGANIZED-RETAIN','Retained website invoice',25500,'Preserve invoice instructions',$now]);
}
$result=[];
foreach(['settings','services','projects','studio_clients','studio_jobs','studio_invoices'] as $name){
    $rows=query('SELECT * FROM '.table($name))->fetchAll();
    $encoded=array_map(fn($row)=>json_encode($row,JSON_THROW_ON_ERROR),$rows);sort($encoded);
    $result[$name]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($encoded,JSON_THROW_ON_ERROR))];
}
echo json_encode($result,JSON_THROW_ON_ERROR);
