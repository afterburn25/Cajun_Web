<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/domain_registration_drafts_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function registration_drafts_locked():void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT IS_USED_LOCK(?)',[$key])->fetchColumn()!==(int)query('SELECT CONNECTION_ID()')->fetchColumn())throw new LogicException('Registration drafts require the current account transaction.');
}
function registration_drafts_tables():array {
    $out=[];foreach(domain_registration_drafts_schema(config()['db']['prefix']) as $name=>$sql)$out[$name]=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;return $out;
}
function registration_drafts_has_history():bool {
    return one('SELECT name FROM '.table('settings').' WHERE name IN (?,?,?) LIMIT 1',['registration_drafts_schema_version','registration_drafts_checkpoint','registration_drafts_checkpoint_proof'])!==null||one('SELECT id FROM '.table('audit').' WHERE LEFT(BINARY action,20)=BINARY ? LIMIT 1',['registration_drafts.'])!==null;
}
function registration_drafts_audit(int $actor,string $action,string $detail):int {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'registration_drafts.'.$action,$detail,gmdate('Y-m-d H:i:s')]);return (int)db()->lastInsertId();
}
function registration_drafts_detail(array $row):string {return 'Draft '.$row['draft_id'].' revision '.$row['revision'].' command '.$row['id'].' sha256 '.$row['document_sha256'];}
function registration_drafts_write_checkpoint(array $checkpoint):void {
    registration_drafts_locked();$json=registration_drafts_json(registration_drafts_checkpoint($checkpoint));save_setting('registration_drafts_checkpoint',$json);save_setting('registration_drafts_checkpoint_proof',registration_drafts_mac('checkpoint',$json));
}
function registration_drafts_read_checkpoint():array {
    try{
        $json=one('SELECT value FROM '.table('settings').' WHERE name=?',['registration_drafts_checkpoint'])['value']??'';$proof=one('SELECT value FROM '.table('settings').' WHERE name=?',['registration_drafts_checkpoint_proof'])['value']??'';
        if(strlen($json)>2048||!hash_equals(registration_drafts_mac('checkpoint',$json),$proof))throw new RuntimeException();
        $d=registration_drafts_checkpoint(json_decode($json,true,8,JSON_THROW_ON_ERROR));if(registration_drafts_json($d)!==$json)throw new RuntimeException();return $d;
    }catch(Throwable $e){throw new RuntimeException('Registration draft count history needs recovery.',0,$e);}
}
function registration_drafts_validate_schema(string $name,string $sql):void {
    $revision=$name==='domain_registration_revisions';catalog_validate_schema($name,$sql,$revision?['draft_revision'=>['draft_id','revision']]:[]);
    $ascii=$revision?['id','draft_id','state','action','actor_kind','document_sha256','proof']:['id','state','current_id','current_sha256'];
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){$collation=in_array($c['Field'],$ascii,true)?'ascii_bin':(in_array($c['Field'],['document_json','ciphertext'],true)?'utf8mb4_unicode_ci':null);if($c['Default']!==null||$c['Extra']!==''||$c['Collation']!==$collation)throw new RuntimeException('Registration draft schema needs recovery.');}
    $expected=$revision?['PRIMARY'=>[0,['id']],'draft_revision'=>[0,['draft_id','revision']],'revision_client'=>[1,['client_id']]]:['PRIMARY'=>[0,['id']],'client_drafts'=>[1,['client_id','updated_epoch']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Registration draft indexes need recovery.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Registration draft indexes need recovery.');
    $wanted=$revision?['draft_id'=>'domain_registration_drafts','client_id'=>'studio_clients']:['client_id'=>'studio_clients'];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($wanted))throw new RuntimeException('Registration draft relationships need recovery.');
    foreach($relations as $r){if(!isset($wanted[$r['COLUMN_NAME']])||$r['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$wanted[$r['COLUMN_NAME']]||$r['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Registration draft relationships need recovery.');unset($wanted[$r['COLUMN_NAME']]);}
}
/** Called only inside the shared transaction; no cached readiness or indexed fallback. */
function registration_drafts_ready():bool {
    registration_drafts_locked();
    foreach(['settings','audit','studio_clients'] as $name){$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Registration draft witnesses require transactional storage.');}
    $tables=registration_drafts_tables();$version=one('SELECT value FROM '.table('settings').' WHERE name=?',['registration_drafts_schema_version'])['value']??'';
    if($version===''){
        if(registration_drafts_has_history())throw new RuntimeException('Registration draft history needs recovery.');
        foreach($tables as $name=>$exists)if($exists&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()>0)throw new RuntimeException('Unmarked registration draft history needs recovery.');return false;
    }
    if($version!=='1'||in_array(false,$tables,true))throw new RuntimeException('Registration draft storage needs recovery.');
    foreach(domain_registration_drafts_schema(config()['db']['prefix']) as $name=>$sql)registration_drafts_validate_schema($name,$sql);
    $cp=registration_drafts_read_checkpoint();$key=registration_drafts_key();$keyHash=hash('sha256',$key);unset($key);
    if(!hash_equals($cp['key_sha256'],$keyHash))throw new RuntimeException('Restore the original registration key and draft history together.');
    $heads=table('domain_registration_drafts');$rows=table('domain_registration_revisions');$audit=table('audit');
    if((int)query("SELECT COUNT(*) FROM $heads")->fetchColumn()!==$cp['drafts']||(int)query("SELECT COUNT(*) FROM $rows")->fetchColumn()!==$cp['revisions']||(int)query("SELECT COUNT(*) FROM $audit WHERE BINARY action='registration_drafts.revision'")->fetchColumn()!==$cp['revisions']||(int)query("SELECT COUNT(*) FROM $audit WHERE LEFT(BINARY action,20)=BINARY 'registration_drafts.'")->fetchColumn()!==$cp['revisions']+1)throw new RuntimeException('Registration draft count history needs recovery.');
    if((int)query("SELECT COUNT(*) FROM $audit WHERE id=? AND user_id=? AND BINARY action='registration_drafts.prepared' AND BINARY detail=?",[$cp['prepared_audit_id'],$cp['prepared_by'],'Private registration drafts ready key sha256 '.$cp['key_sha256']])->fetchColumn()!==1)throw new RuntimeException('Registration draft setup history needs recovery.');
    $seen=0;$cursor=null;$lastAudit=0;$lastId=str_repeat('0',32);$lastHash=str_repeat('0',64);
    do{
        $page=all("SELECT * FROM $rows".($cursor===null?'':' WHERE BINARY id>BINARY ?').' ORDER BY BINARY id LIMIT 64',$cursor===null?[]:[$cursor]);
        foreach($page as $row){
            $d=registration_drafts_decode($row);$matches=all("SELECT id FROM $audit WHERE user_id=? AND BINARY action='registration_drafts.revision' AND BINARY detail=?",[$d['actor_kind']==='staff'?$d['actor_id']:0,registration_drafts_detail($row)]);
            if(count($matches)!==1||(int)$matches[0]['id']<=$cp['prepared_audit_id'])throw new RuntimeException('Registration draft audit history needs recovery.');
            if((int)$matches[0]['id']>$lastAudit){$lastAudit=(int)$matches[0]['id'];$lastId=$d['id'];$lastHash=$row['document_sha256'];}
            if(!one("SELECT id FROM $heads WHERE id=? AND client_id=?",[$d['draft_id'],$d['client_id']]))throw new RuntimeException('Registration draft parent history needs recovery.');
            $seen++;$cursor=$row['id'];
        }
    }while(count($page)===64);
    if($seen!==$cp['revisions']||$lastAudit!==$cp['last_audit_id']||$lastId!==$cp['last_id']||$lastHash!==$cp['last_sha256'])throw new RuntimeException('Registration draft latest history needs recovery.');
    $seen=0;$cursor=null;
    do{
        $page=all("SELECT * FROM $heads".($cursor===null?'':' WHERE BINARY id>BINARY ?').' ORDER BY BINARY id LIMIT 64',$cursor===null?[]:[$cursor]);
        foreach($page as $head){registration_drafts_check_chain($head,$cp['prepared_audit_id']);$cursor=$head['id'];$seen++;}
    }while(count($page)===64);
    if($seen!==$cp['drafts'])throw new RuntimeException('Registration draft head history needs recovery.');return true;
}
function registration_drafts_check_chain(array $head,int $preparedAudit):void {
    registration_drafts_id($head['id']);$prior=null;$priorHash=str_repeat('0',64);$auditId=$preparedAudit;$firstTime=0;
    $rows=all('SELECT * FROM '.table('domain_registration_revisions').' WHERE draft_id=? ORDER BY revision LIMIT 257',[$head['id']]);
    if(count($rows)<1||count($rows)>256||count($rows)!==(int)$head['revision'])throw new RuntimeException('Registration draft revision history needs recovery.');
    foreach($rows as $i=>$row){
        $d=registration_drafts_decode($row);$a=one('SELECT id FROM '.table('audit')." WHERE user_id=? AND BINARY action='registration_drafts.revision' AND BINARY detail=?",[$d['actor_kind']==='staff'?$d['actor_id']:0,registration_drafts_detail($row)]);
        if($d['draft_id']!==$head['id']||$d['client_id']!==(int)$head['client_id']||$d['revision']!==$i+1||$d['previous_sha256']!==$priorHash||($i===0&&$d['action']!=='save')||$d['state']!==registration_drafts_state($prior['state']??null,$d['action'],$d['actor_kind'])||($prior!==null&&$d['recorded_epoch']<$prior['recorded_epoch'])||!$a||(int)$a['id']<=$auditId||($d['action']!=='save'&&($prior===null||$d['content_hmac']!==$prior['content_hmac']||$d['domain']!==$prior['domain']||$d['requested_years']!==$prior['requested_years']||$d['privacy_service']!==$prior['privacy_service'])))throw new RuntimeException('Registration draft revision order needs recovery.');
        if($i===0)$firstTime=$d['recorded_epoch'];$auditId=(int)$a['id'];$prior=$d;$priorHash=$row['document_sha256'];
    }
    if($head['current_id']!==$prior['id']||$head['current_sha256']!==$priorHash||$head['state']!==$prior['state']||(int)$head['created_epoch']!==$firstTime||(int)$head['updated_epoch']!==$prior['recorded_epoch'])throw new RuntimeException('Registration draft head history needs recovery.');
}
function domain_registration_drafts_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Registration draft setup cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        $actor=registration_drafts_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $ready=staff_transaction(fn()=>registration_drafts_ready());$tables=registration_drafts_tables();
        foreach(domain_registration_drafts_schema(config()['db']['prefix']) as $name=>$sql){if($tables[$name])registration_drafts_validate_schema($name,$sql);}
        foreach(domain_registration_drafts_schema(config()['db']['prefix']) as $name=>$sql){if(!$tables[$name])db()->exec($sql);registration_drafts_validate_schema($name,$sql);}
        if(!$ready){$key=registration_drafts_key(true);$keyHash=hash('sha256',$key);unset($key);staff_transaction(function()use($actor,$keyHash):void{
            registration_drafts_owner();if(registration_drafts_ready())throw new RuntimeException('Registration draft setup changed. Reload the page.');
            $audit=registration_drafts_audit((int)$actor['id'],'prepared','Private registration drafts ready key sha256 '.$keyHash);
            save_setting('registration_drafts_schema_version','1');registration_drafts_write_checkpoint(['version'=>1,'drafts'=>0,'revisions'=>0,'last_id'=>str_repeat('0',32),'last_sha256'=>str_repeat('0',64),'last_audit_id'=>0,'key_sha256'=>$keyHash,'prepared_by'=>(int)$actor['id'],'prepared_audit_id'=>$audit]);registration_drafts_ready();
        });}
    });
}
