<?php
/** Real loopback PDO failures against the exact migration fixture helper; no application migration. */
declare(strict_types=1);

if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
$options=getopt('',['source:','report:']);
$source=$options['source']??__DIR__.'/provider_inventory_upgrade_native.php';
$reportPath=$options['report']??null;
$host=getenv('CWP_TEST_HOST')?:'127.0.0.1';
$port=getenv('CWP_TEST_PORT')?:'33306';
if(!is_string($source)||($reportPath!==null&&!is_string($reportPath))||$host!=='127.0.0.1'||!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
$probeChecks=[];$cases=[];$checks=[];$errors=[];$created=false;
$auditProbePrefix='puaf_'.bin2hex(random_bytes(6)).'_';

function audit_probe_check(string $name,bool $passed):void {
    $GLOBALS['probeChecks'][]=['name'=>$name,'passed'=>$passed];
}
function audit_probe_function(string $source,string $name):string {
    $tokens=token_get_all($source,TOKEN_PARSE);$matches=[];
    foreach($tokens as $i=>$token){
        if(!is_array($token)||$token[0]!==T_FUNCTION)continue;
        $j=$i+1;
        while(isset($tokens[$j])&&is_array($tokens[$j])&&in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$j++;
        if(!isset($tokens[$j])||!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING||$tokens[$j][1]!==$name)continue;
        $body='';$depth=0;$opened=false;
        for($k=$i;$k<count($tokens);$k++){
            $part=$tokens[$k];$body.=is_array($part)?$part[1]:$part;
            if($part==='{'){$depth++;$opened=true;}
            elseif($part==='}'&&--$depth===0&&$opened){$matches[]=$body;break;}
        }
    }
    if(count($matches)!==1)throw new RuntimeException('Expected one exact source function: '.$name);
    return $matches[0];
}
// Only these connection/name seams are supplied. Both the assertion and injected
// failure helper below are extracted from the source under test without rewriting.
function db():PDO {return $GLOBALS['auditProbeDb'];}
function config():array {return ['db'=>['prefix'=>$GLOBALS['auditProbePrefix']]];}
function table(string $name):string {
    if(!preg_match('/^[a-z0-9_]+$/D',$name))throw new RuntimeException('Invalid synthetic table name.');
    return '`'.$GLOBALS['auditProbePrefix'].$name.'`';
}
function audit_probe_trigger_count():int {
    $statement=db()->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=?');
    $statement->execute([$GLOBALS['auditProbePrefix'].'audit_fail']);
    return (int)$statement->fetchColumn();
}
function audit_probe_case(string $name,callable $callback):array {
    $GLOBALS['checks']=[];$raised=null;$surfaced=null;$completed=false;
    try{
        pu_audit_failure(3,function()use($callback,&$raised,&$completed):void {
            try{$callback();$completed=true;}catch(PDOException $e){$raised=$e;throw $e;}
        });
    }catch(Throwable $e){$surfaced=$e;}
    $result=['raised'=>$raised,'surfaced'=>$surfaced,'completed'=>$completed,'assertions'=>$GLOBALS['checks']];
    $GLOBALS['cases'][]=['name'=>$name,'pdo_error_info'=>$raised?->errorInfo,
        'surfaced_type'=>$surfaced===null?null:get_class($surfaced),
        'same_exception'=>$raised!==null&&$surfaced===$raised,'helper_assertions'=>$GLOBALS['checks']];
    audit_probe_check($name.': helper finally removes its injected trigger',audit_probe_trigger_count()===0);
    return $result;
}

try{
    $bytes=file_get_contents($source);
    if($bytes===false)throw new RuntimeException('Could not read the migration fixture source.');
    $functions=[];
    foreach(['pu_ok','pu_audit_failure'] as $name){
        $body=audit_probe_function($bytes,$name);$functions[$name]=hash('sha256',$body);eval($body);
    }
    $auditProbeDb=new PDO('mysql:host='.$host.';port='.$port.';dbname=cwp_test;charset=utf8mb4',getenv('CWP_TEST_USER')?:'cwp_test',getenv('CWP_TEST_PASSWORD')?:'local-ci-only',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $statement=db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(table_name,?)=?');
    $statement->execute([strlen($auditProbePrefix),$auditProbePrefix]);
    $unused=(int)$statement->fetchColumn()===0&&audit_probe_trigger_count()===0;
    audit_probe_check('Unique synthetic prefix has no existing tables or trigger',$unused);
    if(!$unused)throw new RuntimeException('Synthetic prefix is already in use.');
    db()->exec('CREATE TABLE '.table('audit').' (id INT PRIMARY KEY,action VARCHAR(64) NOT NULL) ENGINE=InnoDB');$created=true;
    $expectedAssertion='Migration audit failure is surfaced from v3';
    $expected=['45000',1644,'Synthetic migration audit failure'];

    $case=audit_probe_case('Exact injected audit failure',fn()=>db()->exec('INSERT INTO '.table('audit')." (id,action) VALUES (1,'provider.inventory_prepared')"));
    audit_probe_check('Injected trigger produces the actual expected PDO error tuple',$case['raised'] instanceof PDOException&&$case['raised']->errorInfo===$expected);
    audit_probe_check('Exact injected audit failure is accepted by the actual helper',$case['surfaced']===null&&!$case['completed']);
    audit_probe_check('Exact injected failure retains the original successful assertion',$case['assertions']===[['name'=>$expectedAssertion,'passed'=>true]]);

    $unrelated=[
        ['Missing table',fn()=>db()->query('SELECT * FROM '.table('missing')),['42S02',1146]],
        ['Different SQLSTATE',fn()=>db()->exec("SIGNAL SQLSTATE '45001' SET MYSQL_ERRNO=1644, MESSAGE_TEXT='Synthetic migration audit failure'"),['45001',1644,'Synthetic migration audit failure']],
        ['Different driver error',fn()=>db()->exec("SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO=1645, MESSAGE_TEXT='Synthetic migration audit failure'"),['45000',1645,'Synthetic migration audit failure']],
        ['Different error message',fn()=>db()->exec("SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO=1644, MESSAGE_TEXT='Unrelated synthetic SQL failure'"),['45000',1644,'Unrelated synthetic SQL failure']],
    ];
    foreach($unrelated as [$name,$callback,$tuple]){
        $case=audit_probe_case($name,$callback);
        audit_probe_check($name.': real PDO reports the intended unrelated error',$case['raised'] instanceof PDOException&&array_slice($case['raised']->errorInfo,0,count($tuple))===$tuple);
        audit_probe_check($name.': identical PDO exception escapes the actual helper',$case['raised'] instanceof PDOException&&$case['surfaced']===$case['raised']);
        audit_probe_check($name.': unrelated failure never records audit-failure success',$case['assertions']===[]);
    }

    $case=audit_probe_case('Successful callback without injected error',fn()=>db()->query('SELECT 1')->fetchColumn());
    audit_probe_check('Missing injected error retains the original failing assertion',$case['completed']&&$case['raised']===null&&$case['assertions']===[['name'=>$expectedAssertion,'passed'=>false]]);
    audit_probe_check('Missing injected error still fails through the actual assertion helper',$case['surfaced'] instanceof RuntimeException&&!($case['surfaced'] instanceof PDOException)&&$case['surfaced']->getMessage()===$expectedAssertion);
}catch(Throwable $e){
    $errors[]=get_class($e).': '.$e->getMessage();
}finally{
    if($created){
        try{
            db()->exec('DROP TRIGGER IF EXISTS `'.$auditProbePrefix.'audit_fail`');
            db()->exec('DROP TABLE '.table('audit'));
            $statement=db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(table_name,?)=?');
            $statement->execute([strlen($auditProbePrefix),$auditProbePrefix]);
            audit_probe_check('Diagnostic removes only its own synthetic table and trigger',(int)$statement->fetchColumn()===0&&audit_probe_trigger_count()===0);
        }catch(Throwable $e){$errors[]='Synthetic fixture cleanup: '.get_class($e).': '.$e->getMessage();}
    }
}
$passed=$errors===[]&&$probeChecks!==[]&&!in_array(false,array_column($probeChecks,'passed'),true);
$report=['passed'=>$passed,'scope'=>'Actual extracted migration fixture helpers and real PDO failures on an isolated synthetic loopback database prefix. No application migration, preserved failing tables, browser, provider or engine-repair claim.',
    'source_sha256'=>isset($bytes)&&is_string($bytes)?hash('sha256',$bytes):null,
    'helper_sha256'=>$functions??[],'php'=>PHP_VERSION,'database_version'=>isset($auditProbeDb)?db()->getAttribute(PDO::ATTR_SERVER_VERSION):null,
    'synthetic_prefix'=>$auditProbePrefix,'count'=>count($probeChecks),'checks'=>$probeChecks,'cases'=>$cases,'errors'=>$errors];
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if($reportPath!==null&&file_put_contents($reportPath,$json)===false)exit(2);
echo $json;
exit($passed?0:1);
