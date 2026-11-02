<?php
/** CLI-only authority probe for an already installed disposable HTTP fixture. Never shipped. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1')exit(2);
$root=realpath($argv[1]??'');$runtime=realpath(__DIR__.'/../test-runtime');$mode=$argv[2]??'';
if(!$root||!$runtime||!str_starts_with($root,$runtime.DIRECTORY_SEPARATOR)||!is_file($root.'/.installed.php')||!in_array($mode,['snapshot','revoke','restore'],true))exit(2);
$input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);if(!is_array($input))exit(2);
$ownerId=array_key_exists('owner_id',$input)?$input['owner_id']:1;
if(!is_int($ownerId)||$ownerId<1||$ownerId>2147483647)exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';$cfg=config();$database=$cfg['db']??[];
if(($database['name']??'')!=='cwp_test'||($database['host']??'')!=='127.0.0.1'||(int)($database['port']??0)!==(int)getenv('CWP_TEST_PORT')||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D',$database['prefix']??''))exit(2);
$storage=realpath($cfg['storage']??'');
if(!$storage||!str_starts_with($storage,$runtime.DIRECTORY_SEPARATOR))exit(2);
function installed_authority_snapshot(string $storage,string $prefix):array {
    $tables=[];
    foreach(all("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,?)=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME",[strlen($prefix),$prefix]) as $item){
        $name=$item['TABLE_NAME'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$name)||!str_starts_with($name,$prefix))throw new LogicException('Unsafe fixture table.');
        $rows=array_map('serialize',all('SELECT * FROM `'.$name.'`'));sort($rows,SORT_STRING);
        $ddl=one('SHOW CREATE TABLE `'.$name.'`')['Create Table'];
        $tables[$name]=['rows'=>count($rows),'data'=>hash('sha256',serialize($rows)),'schema'=>hash('sha256',preg_replace('/ AUTO_INCREMENT=[0-9]+\b/','',$ddl))];
    }
    if(!$tables)throw new LogicException('Empty fixture cannot prove retained data.');
    $files=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage,FilesystemIterator::SKIP_DOTS)) as $file){
        if($file->isLink())throw new LogicException('Symlink in private fixture.');
        if($file->isFile())$files[substr($file->getPathname(),strlen($storage)+1)]=[hash_file('sha256',$file->getPathname()),$file->getPerms()&0777];
    }
    ksort($files);return ['tables'=>$tables,'files'=>$files];
}
$profile=one('SELECT * FROM '.table('staff_profiles').' WHERE user_id=?',[$ownerId]);
if(!$profile||(int)$profile['enabled']!==1)throw new LogicException('Expected enabled owner fixture.');
if($mode!=='snapshot'){
    $original=$input['original']??null;
    if(!is_array($original)||(int)($original['user_id']??0)!==$ownerId||($original['role']??'')!=='owner'||(int)($original['enabled']??0)!==1||!is_string($original['permissions']??null))throw new LogicException('Invalid original profile.');
    $revoked=$original;$revoked['role']='administrator';$revoked['permissions']='[]';
    $expected=$mode==='revoke'?$original:$revoked;
    if($profile!==$expected&&!($mode==='restore'&&$profile===$original))throw new LogicException('Concurrent fixture profile change.');
    $target=$mode==='revoke'?$revoked:$original;
    query('UPDATE '.table('staff_profiles').' SET role=?,permissions=? WHERE user_id=?',[$target['role'],$target['permissions'],$ownerId]);
    if(one('SELECT * FROM '.table('staff_profiles').' WHERE user_id=?',[$ownerId])!==$target)throw new LogicException('Fixture profile update failed.');
    $profile=$target;
}
echo json_encode(['profile'=>$profile,'snapshot'=>installed_authority_snapshot($storage,$database['prefix'])],JSON_THROW_ON_ERROR).PHP_EOL;
