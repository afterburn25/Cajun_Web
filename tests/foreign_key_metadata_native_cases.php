<?php
/** Additive real native FK metadata regressions; no external I/O or privileged setup. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||!in_array(getenv('CWP_TEST_DATABASE'),['cwp_test','cwp_fk_metadata_test'],true))exit(2);
require_once CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/database/schema/service_plans_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/recurring_billing_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/service_plans_migrate.php';
require_once CWP_CODE_ROOT.'/database/migrations/recurring_billing_migrate.php';
// Pre-optimization row-multiset oracle. The candidate is taken from current source,
// and the actual application validators decide every positive/negative schema case.
const FKM_ORIGINAL='SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL';
function fkm_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function fkm_identifier(string $value):string {if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]*\z/D',$value))throw new LogicException('Unsafe fixture identifier');return '`'.$value.'`';}
function fkm_source_query(string $path):string {
    $queries=[];
    foreach(token_get_all(file_get_contents($path)) as $token)if(is_array($token)&&$token[0]===T_CONSTANT_ENCAPSED_STRING&&str_starts_with($token[1],"'SELECT k.COLUMN_NAME,"))$queries[]=substr($token[1],1,-1);
    if(count($queries)!==1||str_contains($queries[0],'\\'))throw new LogicException('Cannot bind the current metadata query');
    return $queries[0];
}
function fkm_sorted(array $rows):array {usort($rows,static fn(array $a,array $b):int=>strcmp(json_encode($a,JSON_THROW_ON_ERROR),json_encode($b,JSON_THROW_ON_ERROR)));return $rows;}
function fkm_equal(string $label,string $name,string $sql):array {
    global $fkm_observations;
    $table=config()['db']['prefix'].$name;
    $original=fkm_sorted(all(FKM_ORIGINAL,[$table]));
    $candidate=fkm_sorted(all($sql,array_fill(0,substr_count($sql,'?'),$table)));
    fkm_ok($label.' preserves the exact original row multiset',$candidate===$original);
    $fkm_observations[]=['case'=>$label,'rows'=>count($candidate),'rows_sha256'=>hash('sha256',json_encode($candidate,JSON_THROW_ON_ERROR))];
    return $candidate;
}
function fkm_validate(string $family,string $name,array $schema,bool $checkout):void {
    if($family==='plans')service_plans_validate_schema($name,$schema[$name],$checkout);
    else recurring_billing_validate_schema($name,$schema[$name],$checkout);
}
function fkm_valid(string $label,string $family,string $name,array $schema,bool $checkout):void {fkm_validate($family,$name,$schema,$checkout);fkm_ok($label,true);}
function fkm_refused(string $label,string $family,string $name,array $schema,bool $checkout):void {
    try{fkm_validate($family,$name,$schema,$checkout);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;fkm_ok($label,str_contains($e->getMessage(),'relationship'));return;}
    fkm_ok($label,false);
}
function fkm_fk_name(string $name,string $column):string {
    $rows=all('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name,$column]);
    if(count($rows)!==1)throw new RuntimeException('Expected one unmodified fixture relationship');return $rows[0]['CONSTRAINT_NAME'];
}
function fkm_drop_fk(string $name,string $constraint):void {db()->exec('ALTER TABLE '.table($name).' DROP FOREIGN KEY '.fkm_identifier($constraint));}
function fkm_add_fk(string $name,string $constraint,string $parentSchema,string $parentTable,string $parentColumn='id',string $delete='RESTRICT',string $update='RESTRICT'):void {
    if(!in_array($delete,['RESTRICT','CASCADE'],true)||!in_array($update,['RESTRICT','CASCADE'],true))throw new LogicException('Unsupported fixture rule');
    db()->exec('ALTER TABLE '.table($name).' ADD CONSTRAINT '.fkm_identifier($constraint).' FOREIGN KEY (created_by) REFERENCES '.fkm_identifier($parentSchema).'.'.fkm_identifier($parentTable).' ('.fkm_identifier($parentColumn).') ON DELETE '.$delete.' ON UPDATE '.$update);
}
function fkm_run_native_cases(array $families,?string $foreign,string $namespace):array {
    global $checks,$fkm_observations;
    if(!$families||array_diff($families,['plans','recurring'])||!preg_match('/\A[a-z][a-z0-9_]{2,10}_\z/D',$namespace))throw new LogicException('Unexpected metadata fixture scope');
    if(!in_array(config()['db']['name'],['cwp_test','cwp_fk_metadata_test'],true)||($foreign!==null&&$foreign!=='cwp_fk_metadata_foreign'))throw new LogicException('Unexpected metadata fixture database');
    $sources=['plans'=>CWP_CODE_ROOT.'/database/migrations/service_plans_migrate.php','recurring'=>CWP_CODE_ROOT.'/database/migrations/recurring_billing_migrate.php'];
    $queries=array_map('fkm_source_query',$sources);$originalPrefix=config()['db']['prefix'];
    $before=count($checks);$fkm_observations??=[];$observedBefore=count($fkm_observations);
    try {
        $identity=one('SELECT DATABASE() AS name,@@port AS port,VERSION() AS version');
        // Container forwarding can make the server port differ from the connection endpoint.
        fkm_ok('Connection selects only the explicit disposable native database',$identity['name']===config()['db']['name']);
        $ownedSchemas=[config()['db']['name']];if($foreign!==null)$ownedSchemas[]=$foreign;
        foreach($ownedSchemas as $database)fkm_ok('Owned metadata prefix is empty in '.$database,(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=? AND BINARY LEFT(TABLE_NAME,?)=?',[$database,strlen($namespace),$namespace])->fetchColumn()===0);
        fkm_ok('Both validators expose the same source-bound metadata query',$queries['plans']===$queries['recurring']);
        foreach([false,true] as $checkout){
            $variant=$checkout?'checkout':'legacy';$prefix=$namespace.($checkout?'c_':'l_');$GLOBALS['cwp_config']['db']['prefix']=$prefix;
            foreach(schema_statements($prefix) as $statement)db()->exec($statement);
            $schemas=['plans'=>service_plans_schema($prefix,$checkout),'recurring'=>recurring_billing_schema($prefix,$checkout)];
            foreach($schemas as $family=>$schema)foreach($schema as $name=>$statement){db()->exec($statement);if(in_array($family,$families,true)){fkm_equal($variant.' '.$name,$name,$queries[$family]);fkm_valid($variant.' '.$name.' passes the actual validator',$family,$name,$schema,$checkout);}}
            $parent=$prefix.'users';$local=config()['db']['name'];
            if($foreign!==null)db()->exec('CREATE TABLE '.fkm_identifier($foreign).'.'.fkm_identifier($parent).' (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
            db()->exec('ALTER TABLE '.fkm_identifier($parent).' ADD diagnostic_other_id INT UNSIGNED NOT NULL, ADD UNIQUE INDEX diagnostic_other_id(diagnostic_other_id)');
            db()->exec('CREATE TABLE '.fkm_identifier($prefix.'wrong_parent').' (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
            db()->exec('CREATE TABLE '.fkm_identifier($prefix.'composite_parent').' (entity_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,version INT UNSIGNED NOT NULL,PRIMARY KEY(entity_id,version)) ENGINE=InnoDB');
            foreach(['plans'=>['service_plans','service_plan_events','plan_id','revision'],'recurring'=>['recurring_billing_schedules','recurring_billing_events','schedule_id','revision']] as $family=>$target){
                if(!in_array($family,$families,true))continue;
                [$name,$eventName,$compositeColumn,$compositeRevision]=$target;$schema=$schemas[$family];$query=$queries[$family];$label=$variant.' '.$family;
                $constraint=fkm_fk_name($name,'created_by');$base=fkm_equal($label.' baseline',$name,$query);
                $duplicate=$prefix.$family.'_duplicate';
                fkm_add_fk($name,$duplicate,$local,$parent);
                $rows=fkm_equal($label.' two named constraints on one column',$name,$query);
                $created=array_values(array_filter($rows,static fn(array $row):bool=>$row['COLUMN_NAME']==='created_by'));
                fkm_ok($label.' preserves both identical projected occurrences',count($rows)===count($base)+1&&count($created)===2&&$created[0]===$created[1]);
                fkm_refused($label.' actual validator refuses the extra named relationship',$family,$name,$schema,$checkout);
                fkm_drop_fk($name,$duplicate);fkm_valid($label.' duplicate removal is visible immediately',$family,$name,$schema,$checkout);

                $composite=$prefix.$family.'_composite';
                db()->exec('ALTER TABLE '.table($eventName).' ADD CONSTRAINT '.fkm_identifier($composite).' FOREIGN KEY ('.fkm_identifier($compositeColumn).','.fkm_identifier($compositeRevision).') REFERENCES '.fkm_identifier($prefix.'composite_parent').' (entity_id,version) ON DELETE RESTRICT');
                $eventRows=fkm_equal($label.' composite foreign key',$eventName,$query);
                fkm_ok($label.' keeps both composite-key column rows',count(array_filter($eventRows,static fn(array $row):bool=>$row['REFERENCED_TABLE_NAME']===$prefix.'composite_parent'))===2);
                fkm_refused($label.' actual validator refuses the extra composite relationship',$family,$eventName,$schema,$checkout);
                fkm_drop_fk($eventName,$composite);fkm_valid($label.' composite removal restores the current schema',$family,$eventName,$schema,$checkout);

                fkm_drop_fk($name,$constraint);fkm_equal($label.' missing relationship',$name,$query);
                fkm_refused($label.' second validation sees a removed relationship',$family,$name,$schema,$checkout);
                fkm_add_fk($name,$constraint,$local,$parent);fkm_valid($label.' third validation sees the restored relationship',$family,$name,$schema,$checkout);
                foreach([['wrong target table',$prefix.'wrong_parent','id','RESTRICT','RESTRICT'],['wrong target column',$parent,'diagnostic_other_id','RESTRICT','RESTRICT'],['wrong delete rule',$parent,'id','CASCADE','RESTRICT'],['wrong update rule',$parent,'id','RESTRICT','CASCADE']] as [$case,$targetTable,$targetColumn,$delete,$update]){
                    fkm_drop_fk($name,$constraint);fkm_add_fk($name,$constraint,$local,$targetTable,$targetColumn,$delete,$update);
                    fkm_equal($label.' '.$case,$name,$query);fkm_refused($label.' refuses '.$case,$family,$name,$schema,$checkout);
                    fkm_drop_fk($name,$constraint);fkm_add_fk($name,$constraint,$local,$parent);fkm_valid($label.' restoration after '.$case.' is current',$family,$name,$schema,$checkout);
                }

                if($foreign!==null){
                    fkm_drop_fk($name,$constraint);fkm_add_fk($name,$constraint,$foreign,$parent);
                    $rows=fkm_equal($label.' cross-schema referenced parent',$name,$query);
                    fkm_ok($label.' cross-schema parent is still present',count($rows)===count($base)&&count(array_filter($rows,static fn(array $row):bool=>$row['COLUMN_NAME']==='created_by'&&$row['REFERENCED_TABLE_NAME']===$parent))===1);
                    // Preserve existing behavior: these validators compare target table/column,
                    // not referenced schema. The optimization must not silently hide this FK.
                    fkm_valid($label.' preserves existing cross-schema validator behavior',$family,$name,$schema,$checkout);
                    fkm_drop_fk($name,$constraint);fkm_add_fk($name,$constraint,$local,$parent);

                    $foreignChild=$prefix.$name;
                    db()->exec('CREATE TABLE '.fkm_identifier($foreign).'.'.fkm_identifier($foreignChild).' (id INT UNSIGNED PRIMARY KEY,created_by INT UNSIGNED NOT NULL,CONSTRAINT '.fkm_identifier($constraint).' FOREIGN KEY (created_by) REFERENCES '.fkm_identifier($foreign).'.'.fkm_identifier($parent).' (id) ON DELETE CASCADE) ENGINE=InnoDB');
                }
                $other=$prefix.'other_'.$name;
                db()->exec('CREATE TABLE '.fkm_identifier($other).' (id INT UNSIGNED PRIMARY KEY,created_by INT UNSIGNED NOT NULL,CONSTRAINT '.fkm_identifier($other.'_fk').' FOREIGN KEY (created_by) REFERENCES '.fkm_identifier($parent).' (id) ON DELETE CASCADE) ENGINE=InnoDB');
                $rows=fkm_equal($label.($foreign!==null?' foreign child and other prefix':' other prefix'),$name,$query);
                fkm_ok($label.($foreign!==null?' excludes same-name foreign child and other-prefix constraints':' excludes other-prefix constraints'),$rows===$base);
                fkm_valid($label.' unrelated constraints do not contaminate validation',$family,$name,$schema,$checkout);

                $preserved=$prefix.$family.'_preserved_events';
                db()->exec('RENAME TABLE '.table($eventName).' TO '.fkm_identifier($preserved));
                fkm_ok($label.' missing table metadata remains empty',fkm_equal($label.' missing table',$eventName,$query)===[]);
                $pdoFailure=null;
                try{fkm_validate($family,$eventName,$schema,$checkout);}catch(PDOException $e){$pdoFailure=$e;}
                fkm_ok($label.' missing-table PDO failure propagates unchanged',$pdoFailure instanceof PDOException&&($pdoFailure->errorInfo[0]??null)==='42S02'&&(int)($pdoFailure->errorInfo[1]??0)===1146);
                db()->exec('RENAME TABLE '.fkm_identifier($preserved).' TO '.table($eventName));
                fkm_valid($label.' restoring the missing table permits a fresh validation',$family,$eventName,$schema,$checkout);
            }
        }
    } finally {$GLOBALS['cwp_config']['db']['prefix']=$originalPrefix;}
    return ['scope'=>'Actual native schema validators and original-query multiset equivalence','families'=>$families,'legacy_and_checkout'=>true,'cross_schema_executed'=>$foreign!==null,'named_checks'=>count($checks)-$before,'observations'=>array_slice($fkm_observations,$observedBefore),'source_sha256'=>array_map(static fn(string $path):string=>hash_file('sha256',$path),$sources),'query_sha256'=>array_map(static fn(string $sql):string=>hash('sha256',$sql),$queries),'original_query_sha256'=>hash('sha256',FKM_ORIGINAL),'database_version'=>$identity['version'],'configured_connection_port'=>(int)config()['db']['port'],'server_port'=>(int)$identity['port']];
}
