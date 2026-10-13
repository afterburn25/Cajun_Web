<?php
/** Client support sessions retain the real staff identity and never set client login keys. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;
require_once __DIR__.'/staff.php';

function support_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'support_clients'=>'CREATE TABLE '.$t('support_clients').' (client_id INT UNSIGNED PRIMARY KEY,allowed TINYINT NOT NULL,updated_by INT UNSIGNED NOT NULL,updated_at BIGINT UNSIGNED NOT NULL)'.$e,
        'support_sessions'=>'CREATE TABLE '.$t('support_sessions')." (id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id INT UNSIGNED NOT NULL,actor_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,client_id INT UNSIGNED NOT NULL,client_version CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,started_at BIGINT UNSIGNED NOT NULL,last_seen_at BIGINT UNSIGNED NOT NULL,expires_at BIGINT UNSIGNED NOT NULL,ended_at BIGINT UNSIGNED NULL,end_reason VARCHAR(40) NOT NULL DEFAULT '',context VARCHAR(160) NOT NULL,INDEX browser_sessions(binding_hash,ended_at))".$e,
        'support_events'=>'CREATE TABLE '.$t('support_events').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,support_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,actor_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,action VARCHAR(40) NOT NULL,object_type VARCHAR(20) NOT NULL,object_id VARCHAR(64) NOT NULL,outcome VARCHAR(40) NOT NULL,occurred_at BIGINT UNSIGNED NOT NULL,INDEX session_events(support_id,id))'.$e,
    ];
}
function support_ready(): bool {return staff_ready()&&studio_ready()&&setting('support_schema_version')==='1';}
function support_current(): ?array {return $GLOBALS['cwp_support']??null;}
function support_csrf_token(string $id): string {return hash_hmac('sha256','cwp-support:'.$id,csrf());}
function support_csrf_field(): string {
    $ctx=support_current();return $ctx?'<input type="hidden" name="csrf" value="'.esc(support_csrf_token($ctx['session']['id'])).'">':csrf_field();
}
function support_check_csrf(string $id): void {
    if(!is_string($_POST['csrf']??null)||!hash_equals(support_csrf_token($id),$_POST['csrf']))throw new RuntimeException('Your support form expired. Refresh this session and try again.');
}
function support_binding(): string {
    $key=$_SESSION['support_binding']??'';
    return is_string($key)&&preg_match('/^[a-f0-9]{64}$/D',$key)?hash('sha256',$key):'';
}
function support_can(string $permission): bool {
    return support_ready()&&in_array($permission,['support.login','support.write','support.download'],true)&&staff_can('support.login')&&staff_can($permission);
}
function support_audit(array $row,string $action,string $type='',string $object='',string $outcome='allowed'): void {
    // Unlike best-effort general activity logging, support operations fail closed on audit failure.
    query('INSERT INTO '.table('support_events').' (support_id,actor_id,client_id,action,object_type,object_id,outcome,occurred_at) VALUES (?,?,?,?,?,?,?,?)',[$row['id']??null,$row['actor_id'],$row['client_id'],$action,$type,$object,$outcome,time()]);
}
function support_schema_check(string $name,string $sql): void {
    $cols=all('SHOW FULL COLUMNS FROM '.table($name));
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Support tables must use InnoDB.');
    preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT|TINYINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\))([^,]*)/',$sql,$types,PREG_SET_ORDER);
    foreach($types as $i=>$expected){
        $actual=$cols[$i]??[];$type=preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($actual['Type']??''));
        $nullable=!str_contains($expected[3],'NOT NULL')&&!str_contains($expected[3],'PRIMARY KEY');
        if($type!==strtolower($expected[2])||($actual['Null']??'')!==($nullable?'YES':'NO'))throw new RuntimeException('Incompatible '.$name.' columns. Existing data was not replaced.');
    }
    preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) (?:INT|BIGINT|VARCHAR|CHAR|TINYINT)/',$sql,$m);
    if(array_column($cols,'Field')!==$m[1])throw new RuntimeException('Incompatible support columns. Existing data was not replaced.');
    foreach($cols as $c)if(in_array($c['Field'],['binding_hash','actor_version','client_version','support_id'],true)||($name==='support_sessions'&&$c['Field']==='id')){
        if($c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible support identifier collation.');
    }
    $primary=$name==='support_clients'?'client_id':'id';$keys=all('SHOW INDEX FROM '.table($name)." WHERE Key_name='PRIMARY'");
    if(count($keys)!==1||$keys[0]['Column_name']!==$primary)throw new RuntimeException('Incompatible support primary key.');
}
function support_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=staff_owner($password);
        if(!studio_ready())throw new RuntimeException('Enable the client workspace in Version & database first.');
        $missing=[];foreach(support_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))support_schema_check($name,$sql);
            else $missing[$name]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor): void {
            if(setting('support_schema_version')!=='1')foreach(array_keys(support_schema(config()['db']['prefix'])) as $name){
                if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized support records exist. No data was adopted.');
            }
            save_setting('support_schema_version','1');save_setting('support_policy_confirmed','1');
            support_audit(['actor_id'=>$actor['id'],'client_id'=>0],'policy.confirmed');
        });
    });
}
function support_policy(int $clientId,bool $allowed,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function() use($clientId,$allowed,$password): void {
        $actor=staff_owner($password);if(!support_ready())throw new RuntimeException('Enable client support first.');
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[$clientId]))throw new InvalidArgumentException('Client not found.');
        query('INSERT INTO '.table('support_clients').' (client_id,allowed,updated_by,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$clientId,(int)$allowed,$actor['id'],time()]);
        support_audit(['actor_id'=>$actor['id'],'client_id'=>$clientId],$allowed?'client.allowed':'client.restricted','client',(string)$clientId);
        if(!$allowed)foreach(all('SELECT * FROM '.table('support_sessions').' WHERE client_id=? AND ended_at IS NULL FOR UPDATE',[$clientId]) as $row)support_end_row($row,'client-restricted');
    }));
}
function support_end_row(array $row,string $reason): void {
    if($row['ended_at']!==null)return;
    query('UPDATE '.table('support_sessions').' SET ended_at=?,end_reason=? WHERE id=? AND ended_at IS NULL',[time(),$reason,$row['id']]);
    support_audit($row,'session.end','session',$row['id'],$reason);
}
/** Caller holds the staff mutex and a transaction. Never fall back to an actual client login. */
function support_check(string $id,bool $touch=true): ?array {
    if(!support_ready()||!preg_match('/^[a-f0-9]{32}$/D',$id)||support_binding()==='')return null;
    $row=one('SELECT * FROM '.table('support_sessions').' WHERE id=? AND binding_hash=? FOR UPDATE',[$id,support_binding()]);
    if(!$row||$row['ended_at']!==null)return null;
    $actor=admin_user();$client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[$row['client_id']]);
    $policy=one('SELECT allowed FROM '.table('support_clients').' WHERE client_id=? FOR UPDATE',[$row['client_id']]);
    $reason='';
    if(time()>=(int)$row['expires_at']||time()-(int)$row['last_seen_at']>=300)$reason='expired';
    elseif(!$actor||(int)$actor['id']!==(int)$row['actor_id']||!hash_equals($row['actor_version'],$actor['auth_version'])||!staff_can('support.login',$actor)||setting('support_policy_confirmed')!=='1')$reason='staff-revoked';
    elseif(!$client||(int)$client['active']!==1||!hash_equals($row['client_version'],$client['auth_version'])||(int)($policy['allowed']??0)!==1)$reason='client-revoked';
    if($reason!==''){support_end_row($row,$reason);return null;}
    if($touch)query('UPDATE '.table('support_sessions').' SET last_seen_at=? WHERE id=?',[time(),$id]);
    return ['session'=>$row,'actor'=>$actor,'client'=>$client];
}
function support_resolve(string $id): ?array {return staff_locked(fn()=>staff_transaction(fn()=>support_check($id)));}
function support_start(int $clientId): string {
    $id=staff_locked(fn()=>staff_transaction(function() use($clientId): string {
        if(!support_ready()||setting('support_policy_confirmed')!=='1')throw new RuntimeException('Enable and configure client support first.');
        $actor=staff_require('support.login');
        $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=? FOR UPDATE',[$clientId]);
        $policy=one('SELECT allowed FROM '.table('support_clients').' WHERE client_id=? FOR UPDATE',[$clientId]);
        if(!$client||(int)$client['active']!==1||(int)($policy['allowed']??0)!==1)throw new InvalidArgumentException('This client is unavailable for support access.');
        if(support_binding()==='')$_SESSION['support_binding']=bin2hex(random_bytes(32));
        foreach(all('SELECT * FROM '.table('support_sessions').' WHERE binding_hash=? AND ended_at IS NULL FOR UPDATE',[support_binding()]) as $row){
            if(support_check($row['id'],false))throw new RuntimeException('End the current support session before opening another client. Resume it from Client support.');
        }
        $id=bin2hex(random_bytes(16));$now=time();
        query('INSERT INTO '.table('support_sessions').' (id,binding_hash,actor_id,actor_version,client_id,client_version,started_at,last_seen_at,expires_at,context) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,support_binding(),$actor['id'],$actor['auth_version'],$clientId,$client['auth_version'],$now,$now,$now+900,'Client record']);
        support_audit(['id'=>$id,'actor_id'=>$actor['id'],'client_id'=>$clientId],'session.start','client',(string)$clientId);return $id;
    }));
    session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));return $id;
}
function support_end(string $id): void {
    staff_locked(fn()=>staff_transaction(function() use($id): void {
        if(!support_ready()||support_binding()===''||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Support session unavailable.');
        $row=one('SELECT * FROM '.table('support_sessions').' WHERE id=? AND binding_hash=? FOR UPDATE',[$id,support_binding()]);
        if(!$row)throw new InvalidArgumentException('Support session unavailable.');
        support_end_row($row,'returned-to-admin');
    }));
    unset($GLOBALS['cwp_support']);$_SESSION['csrf']=bin2hex(random_bytes(32));
}
function support_end_browser(string $reason): void {
    if(!support_ready()||support_binding()==='')return;
    staff_locked(fn()=>staff_transaction(function() use($reason): void {
        foreach(all('SELECT * FROM '.table('support_sessions').' WHERE binding_hash=? AND ended_at IS NULL FOR UPDATE',[support_binding()]) as $row)support_end_row($row,$reason);
    }));
    unset($_SESSION['support_binding'],$GLOBALS['cwp_support']);
}
/** Keep refresh, action authorization, resource scope, mutation and audit in one transaction. */
function support_message(string $id,int $jobId,string $body): void {
    $result=staff_locked(fn()=>staff_transaction(function() use($id,$jobId,$body): bool {
        $ctx=support_check($id);if(!$ctx)return false;
        if(!staff_can('support.write',$ctx['actor'])){support_audit($ctx['session'],'message.send','project',(string)$jobId,'denied');return false;}
        $job=one('SELECT id FROM '.table('studio_jobs').' WHERE id=? AND client_id=? FOR UPDATE',[$jobId,$ctx['client']['id']]);
        if(!$job){support_audit($ctx['session'],'message.send','project',(string)$jobId,'denied');return false;}
        if(strlen($body)<2||strlen($body)>5000)throw new InvalidArgumentException('Write a message between 2 and 5000 bytes.');
        query('INSERT INTO '.table('studio_messages').' (job_id,author_type,author_id,body,created_at) VALUES (?,?,?,?,?)',[$jobId,'admin',$ctx['actor']['id'],$body,gmdate('Y-m-d H:i:s')]);
        support_audit($ctx['session'],'message.send','message',(string)db()->lastInsertId());return true;
    }));
    if(!$result)throw new RuntimeException('This support action is no longer permitted. Return to admin.');
}
function support_record(string $action,string $type='',string $object='',string $outcome='allowed'): void {
    $ctx=support_current();if(!$ctx)throw new LogicException('Support context required.');support_audit($ctx['session'],$action,$type,$object,$outcome);
}
function support_upload(string $id,int $jobId): void {
    // studio_upload owns its transaction and removes a newly written file if the audit fails.
    staff_locked(function() use($id,$jobId): void {
        $ctx=staff_transaction(fn()=>support_check($id));
        if(!$ctx)throw new RuntimeException('Support session ended. Return to admin.');
        $GLOBALS['cwp_support']=$ctx;
        if(!staff_can('support.write',$ctx['actor'])){support_record('file.upload','project',(string)$jobId,'denied');throw new RuntimeException('Support upload permission is required.');}
        try{studio_job($jobId,(int)$ctx['client']['id']);}catch(InvalidArgumentException $e){support_record('file.upload','project',(string)$jobId,'denied');throw $e;}
        studio_upload($jobId,'admin');
    });
}
