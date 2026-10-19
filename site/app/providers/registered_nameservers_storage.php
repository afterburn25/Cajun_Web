<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/registered_nameservers_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function registered_nameservers_guard(bool $transaction=true):void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(($transaction&&!db()->inTransaction())||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()!==1)throw new LogicException('Nameserver history requires the owning account guard.');
}
function registered_nameservers_tables():array {
    $out=[];foreach(registered_nameservers_schema(config()['db']['prefix']) as $name=>$sql)$out[$name]=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?',[config()['db']['prefix'].$name])!==null;
    $prefix=config()['db']['prefix'].'registered_nameserver_';$found=array_column(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen($prefix),$prefix]),'TABLE_NAME');
    $expected=[];foreach($out as $name=>$exists)if($exists)$expected[]=config()['db']['prefix'].$name;sort($expected);sort($found);if($found!==$expected)throw new RuntimeException('Unknown nameserver storage requires recovery.');
    return $out;
}
function registered_nameservers_audit(int $actor,string $kind,string $detail):void {
    if(!in_array($kind,['prepared','bound','observed'],true)||$actor<1)throw new LogicException('Invalid nameserver audit.');
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'registered_nameservers.'.$kind,$detail,gmdate('Y-m-d H:i:s')]);
}
function registered_nameservers_validate_schema(string $name,string $sql):void {
    catalog_validate_schema($name,$sql,[]);$indexes=[];
    $e=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?',[config()['db']['prefix'].$name]);if(strtolower($e['ENGINE']??'')!=='innodb')throw new RuntimeException('Nameserver storage must be transactional.');
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$ascii=in_array($c['Field'],['id','link_id','binding_sha256','document_sha256'],true);$text=in_array($c['Field'],['binding_json','nameservers_json'],true);if($c['Default']!==null||$c['Extra']!==''||$c['Collation']!==($ascii?'ascii_bin':($text?'utf8mb4_unicode_ci':null)))throw new RuntimeException('Incompatible nameserver columns.');}
    foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible nameserver indexes.');$indexes[$i['Key_name']][0]=(int)$i['Non_unique'];$indexes[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}foreach($indexes as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($indexes);$expected=['PRIMARY'=>[0,['id']]];if($name==='registered_nameserver_bindings')$expected['link_reads']=[1,['link_id']];if($indexes!==$expected)throw new RuntimeException('Incompatible nameserver indexes.');
    $f=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND BINARY k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);$target=$name==='registered_nameserver_bindings'?'provider_refreshes':'registered_nameserver_bindings';
    if(count($f)!==1||$f[0]['COLUMN_NAME']!=='id'||$f[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$f[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($f[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible nameserver relationships.');
}
/** Local integrity only. A successful history parse does not grant current read authority. */
function registered_nameservers_ready():bool {
    foreach(['audit','settings','provider_refreshes','provider_refresh_results'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Prepare transactional provider inventory first.');}
    $tables=registered_nameservers_tables();$schema=registered_nameservers_schema(config()['db']['prefix']);foreach($tables as $name=>$exists)if($exists)registered_nameservers_validate_schema($name,$schema[$name]);
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['registered_nameservers_schema_version'])['value']??'';
    $count=one('SELECT value FROM '.table('settings').' WHERE name=?',['registered_nameservers_intent_count'])['value']??null;$results=one('SELECT value FROM '.table('settings').' WHERE name=?',['registered_nameservers_result_count'])['value']??null;
    $shared=(int)query('SELECT COUNT(*) FROM '.table('provider_refreshes').' WHERE BINARY operation=?',['registrar.nameservers.read'])->fetchColumn();$audits=all('SELECT id,user_id,action,detail FROM '.table('audit').' WHERE BINARY LEFT(action,?)=BINARY ? ORDER BY id',[strlen('registered_nameservers.'),'registered_nameservers.']);
    if($version===''){
        if($count!==null||$results!==null||$shared>0||$audits)throw new RuntimeException('Nameserver history needs recovery from the original database.');
        // Only the exact empty creation prefix left by this two-statement setup may resume.
        if($tables['registered_nameserver_sets']&&!$tables['registered_nameserver_bindings'])throw new RuntimeException('Unrecognized nameserver preparation state.');
        foreach($tables as $name=>$exists)if($exists&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)throw new RuntimeException('Unmarked nameserver history needs recovery.');
        return false;
    }
    if($version!=='1'||in_array(false,$tables,true)||!is_string($count)||!preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/',$count)||!is_string($results)||!preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/',$results))throw new RuntimeException('Nameserver storage needs recovery.');
    $bindings=all('SELECT id FROM '.table('registered_nameserver_bindings').' ORDER BY id');$sets=(int)query('SELECT COUNT(*) FROM '.table('registered_nameserver_sets'))->fetchColumn();
    $counts=['prepared'=>0,'bound'=>0,'observed'=>0];$prepared=null;
    foreach($audits as $a){$kind=substr($a['action'],strlen('registered_nameservers.'));if(!array_key_exists($kind,$counts)||(int)$a['user_id']<1)throw new RuntimeException('Nameserver audit history needs recovery.');$counts[$kind]++;if($kind==='prepared'){if($a['detail']!=='Registered nameserver observations ready')throw new RuntimeException('Nameserver setup audit needs recovery.');$prepared=$a;}}
    if((string)count($bindings)!==$count||$shared!==count($bindings)||$counts!==['prepared'=>1,'bound'=>count($bindings),'observed'=>$sets])throw new RuntimeException('Nameserver history counts need recovery.');
    if(one('SELECT b.id FROM '.table('registered_nameserver_bindings').' b LEFT JOIN '.table('provider_refreshes').' f ON f.id=b.id WHERE f.id IS NULL OR BINARY f.operation<>? LIMIT 1',['registrar.nameservers.read'])||one('SELECT d.id FROM '.table('registered_nameserver_sets').' d LEFT JOIN '.table('registered_nameserver_bindings').' b ON b.id=d.id WHERE b.id IS NULL LIMIT 1'))throw new RuntimeException('Nameserver binding history needs recovery.');
    $resultCount=(string)query('SELECT COUNT(*) FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id WHERE BINARY f.operation=?',['registrar.nameservers.read'])->fetchColumn();$complete=(int)query('SELECT COUNT(*) FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id WHERE BINARY f.operation=? AND BINARY r.state=?',['registrar.nameservers.read','complete'])->fetchColumn();
    if($resultCount!==$results||$complete!==$sets)throw new RuntimeException('Nameserver result history needs recovery.');
    foreach($bindings as $binding){$read=registered_nameservers_read_row($binding['id']);$a=one('SELECT id FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['registered_nameservers.bound','Read '.$binding['id'].' binding '.$read['binding']->sha256]);if(!$a||(int)$a['id']<=(int)$prepared['id'])throw new RuntimeException('Nameserver setup order needs recovery.');}
    return true;
}
function registered_nameservers_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Nameserver preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_settings_owner($password);if(!provider_registrar_ready())throw new RuntimeException('Prepare registered domains first.');$ready=registered_nameservers_ready();$tables=registered_nameservers_tables();
        foreach(registered_nameservers_schema(config()['db']['prefix']) as $name=>$sql){if(!$tables[$name])db()->exec($sql);registered_nameservers_validate_schema($name,$sql);}
        if(!$ready)staff_transaction(function()use($actor):void{save_setting('registered_nameservers_schema_version','1');save_setting('registered_nameservers_intent_count','0');save_setting('registered_nameservers_result_count','0');registered_nameservers_audit((int)$actor['id'],'prepared','Registered nameserver observations ready');});
        if(!registered_nameservers_ready())throw new RuntimeException('Nameserver preparation is incomplete.');
    });
}
