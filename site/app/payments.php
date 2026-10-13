<?php
/** Protected gateway settings and invoice request preparation. Trials are a separate sandbox module. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;
require_once __DIR__.'/catalog.php';
require_once __DIR__.'/authorize.php';
require_once __DIR__.'/payment-attempts.php';
require_once __DIR__.'/payment-notifications.php';
require_once __DIR__.'/invoice-ledger.php';

function payments_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'payment_gateways'=>'CREATE TABLE '.$t('payment_gateways').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,environment VARCHAR(10) NOT NULL UNIQUE,credentials LONGTEXT NOT NULL,revision INT UNSIGNED NOT NULL,validation VARCHAR(30) NOT NULL,validated_at DATETIME NULL,updated_at DATETIME NOT NULL)'.$e,
        'payment_requests'=>'CREATE TABLE '.$t('payment_requests').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,invoice_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,purpose VARCHAR(20) NOT NULL,status VARCHAR(20) NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) NOT NULL,expires_at BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,INDEX invoice_lookup(invoice_id,id))'.$e,
    ];
}
function payments_ready(): bool {return staff_ready()&&studio_ready()&&setting('payments_schema_version')==='1';}
function payments_audit(int $actor,string $action,string $detail): void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'payments.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
function payments_owner(#[\SensitiveParameter] string $password): array {
    if(support_current())throw new RuntimeException('Payment changes are unavailable during a support session.');
    return staff_owner($password);
}
function payments_require(): array {
    if(!payments_ready())throw new RuntimeException('Enable payment preparation first.');
    if(support_current())throw new RuntimeException('Payment changes are unavailable during a support session.');
    $actor=staff_require('payments.manage');staff_require('clients.manage');return $actor;
}
function payments_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $missing=[];foreach(payments_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                catalog_validate_schema($name,$sql,$name==='payment_gateways'?['environment'=>['environment']]:['request_key'=>['request_key']]);
                if($name==='payment_requests')foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $col)if($col['Field']==='invoice_hash'&&$col['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible payment request identifiers.');
            }else $missing[]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor): void {
            if(setting('payments_schema_version')!=='1')foreach(array_keys(payments_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized payment records exist. No data was adopted.');
            save_setting('payments_schema_version','1');payments_audit((int)$actor['id'],'setup','Additive payment schema 1; charging disabled');
        });
    });
}
function payments_environment(string $env): void {if(!in_array($env,['sandbox','production'],true))throw new InvalidArgumentException('Choose Sandbox or Production.');}
function payments_fields(): array {return ['api_login_id'=>'API Login ID','transaction_key'=>'Transaction Key','signature_key'=>'Signature Key','public_client_key'=>'Public Client Key'];}
/** A separate master key is never stored in the database or served directory. */
function payments_master(bool $create=false): string {
    $storage=realpath(config()['storage']);$root=realpath(CWP_ROOT);$document=empty($_SERVER['DOCUMENT_ROOT'])?false:realpath($_SERVER['DOCUMENT_ROOT']);
    if(!$storage||!$root||!$document||!is_dir($storage))throw new RuntimeException('Payment credentials need verified private storage outside the document root. Ask the hosting owner to configure private storage.');
    $normal=fn($p)=>strtolower(str_replace('\\','/',rtrim($p,'/\\'))).'/';
    if(str_starts_with($normal($storage),$normal($root))||str_starts_with($normal($storage),$normal($document)))throw new RuntimeException('Payment credentials need private storage outside the document root. Existing site storage was not moved.');
    $path=$storage.'/payment-master.key';
    if(is_link($path))throw new RuntimeException('The payment master key cannot be a symbolic link.');
    if(!is_file($path)){
        if(!$create||(int)query('SELECT COUNT(*) FROM '.table('payment_gateways')." WHERE credentials<>''")->fetchColumn()>0)throw new RuntimeException('Payment master key unavailable. Restore the original protected key; existing credentials were not replaced.');
        write_private_file($path,random_bytes(32));
    }
    clearstatcache(true,$path);
    if((fileperms($path)&0777)!==0600||filesize($path)!==32)throw new RuntimeException('The payment master key requires owner-only permissions and intact contents.');
    $key=file_get_contents($path);if($key===false||strlen($key)!==32)throw new RuntimeException('Payment master key unavailable.');return $key;
}
function payments_seal(string $env,#[\SensitiveParameter] array $data): string {
    $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($data,JSON_THROW_ON_ERROR),'aes-256-gcm',payments_master(true),OPENSSL_RAW_DATA,$iv,$tag,'cwp-payments-v1|'.config()['db']['prefix'].'|'.$env);
    if($cipher===false)throw new RuntimeException('Could not protect payment credentials.');return base64_encode($iv.$tag.$cipher);
}
function payments_unseal(string $env,#[\SensitiveParameter] string $encoded): array {
    if($encoded==='')return array_fill_keys(array_keys(payments_fields()),'');
    $raw=base64_decode($encoded,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Protected payment credentials are damaged.');
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',payments_master(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'cwp-payments-v1|'.config()['db']['prefix'].'|'.$env);
    if($plain===false)throw new RuntimeException('Protected payment credentials could not be opened. Restore the original key and database together.');
    $data=json_decode($plain,true,8,JSON_THROW_ON_ERROR);if(!is_array($data)||array_keys($data)!==array_keys(payments_fields()))throw new RuntimeException('Invalid protected payment record.');return $data;
}
/** Metadata projection only; neither ciphertext nor credentials is rendered. */
function payments_gateway(string $env): array {
    payments_environment($env);
    $r=one('SELECT id,environment,revision,validation,validated_at,updated_at FROM '.table('payment_gateways').' WHERE environment=?',[$env]);
    return $r?:['environment'=>$env,'revision'=>0,'validation'=>'not_configured','validated_at'=>null,'updated_at'=>null];
}
function payments_save(string $env,int $revision,#[\SensitiveParameter] array $input,#[\SensitiveParameter] string $password,bool $clear=false): void {
    payments_environment($env);if(!payments_ready())throw new RuntimeException('Enable payment preparation first.');
    staff_locked(fn()=>staff_transaction(function() use($env,$revision,$input,$password,$clear): void {
        $actor=payments_owner($password);$old=one('SELECT * FROM '.table('payment_gateways').' WHERE environment=? FOR UPDATE',[$env]);
        if((int)($old['revision']??0)!==$revision)throw new RuntimeException('These settings changed. Reload before replacing credentials.');
        $encoded='';if(!$clear){
            $data=payments_unseal($env,$old['credentials']??'');
            foreach(payments_fields() as $field=>$label){$value=$input[$field]??'';if(!is_string($value))throw new InvalidArgumentException('Enter valid credential fields.');$value=trim($value);
                if($value!==''){
                    $pattern=match($field){'api_login_id'=>'/^[a-zA-Z0-9_-]{1,25}$/D','transaction_key'=>'/^[a-zA-Z0-9]{16}$/D','signature_key'=>'/^[a-fA-F0-9]{128}$/D',default=>'/^[a-zA-Z0-9_+-]{1,128}$/D'};
                    if(!preg_match($pattern,$value))throw new InvalidArgumentException('Check the format of '.$label.'.');$data[$field]=$value;
                }
            }
            if(in_array('',$data,true))throw new InvalidArgumentException('Enter all four credentials when setting up an environment. Blank fields keep saved values on later edits.');
            $encoded=payments_seal($env,$data);
        }
        $now=gmdate('Y-m-d H:i:s');$state=$clear?'not_configured':'not_verified';
        query('INSERT INTO '.table('payment_gateways').' (environment,credentials,revision,validation,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE credentials=VALUES(credentials),revision=VALUES(revision),validation=VALUES(validation),validated_at=NULL,updated_at=VALUES(updated_at)',[$env,$encoded,$revision+1,$state,$now]);
        payments_audit((int)$actor['id'],$clear?'credentials.clear':'credentials.save',$env.' revision '.($revision+1));
    }));
}
/** Optional transport is restricted to CLI fixture tests, never selected by HTTP input. */
function payments_verify(string $env,int $revision,#[\SensitiveParameter] string $password,?callable $transport=null): string {
    payments_environment($env);if($env!=='sandbox')throw new RuntimeException('Production connection and charging are disabled in this release.');
    if(!payments_ready())throw new RuntimeException('Enable payment preparation first.');
    if($transport!==null&&PHP_SAPI!=='cli')throw new LogicException('Fixture transport is CLI-only.');
    $credentials=staff_locked(function() use($env,$revision,$password): array {
        $actor=payments_owner($password);
        if(!consume_rate('payment-verify-'.$actor['id'],3,900))throw new RuntimeException('Wait 15 minutes before another connection check.');
        $r=one('SELECT * FROM '.table('payment_gateways').' WHERE environment=?',[$env]);
        if(!$r||$r['credentials']===''||(int)$r['revision']!==$revision)throw new RuntimeException('Save current sandbox credentials before checking them.');
        return payments_unseal($env,$r['credentials']);
    });
    $result=authorize_authenticate($credentials,$transport);
    staff_locked(fn()=>staff_transaction(function() use($env,$revision,$password,$result): void {
        $actor=payments_owner($password);$current=payments_gateway($env);
        if((int)$current['revision']!==$revision)throw new RuntimeException('Settings changed during the check. Check the latest credentials again.');
        query('UPDATE '.table('payment_gateways').' SET validation=?,validated_at=? WHERE environment=?',[$result,gmdate('Y-m-d H:i:s'),$env]);
        payments_audit((int)$actor['id'],'sandbox.check','Revision '.$revision.' '.$result.'; authentication only');
    }));return $result;
}
function payments_purposes(): array {return ['full'=>'Full service invoice','deposit'=>'Agreed deposit invoice','milestone'=>'Agreed milestone invoice','final'=>'Agreed final balance invoice'];}
function payments_invoice(int $id): array {
    $i=one('SELECT i.*,j.client_id,c.active FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE i.id=?',[$id]);
    if(!$i||(int)$i['active']!==1||$i['status']!=='issued'||$i['currency']!=='USD'||(int)$i['amount_cents']<1||(int)$i['amount_cents']>99999999)throw new RuntimeException('Choose an unpaid USD invoice for an active client.');return $i;
}
function payments_invoice_hash(array $i): string {$base=hash('sha256',json_encode([(int)$i['id'],(int)$i['job_id'],(int)$i['client_id'],(int)$i['amount_cents'],$i['currency'],$i['title'],$i['reference'],$i['instructions'],catalog_snapshot((int)$i['id'])],JSON_THROW_ON_ERROR));if(!ledger_ready())return $base;$revision=ledger_balance($i)['revision'];return $revision?hash('sha256',$base.'|ledger|'.$revision):$base;}
function payments_prepare(int $invoice,string $purpose,int $days,string $fingerprint,string $key): int {
    if(!isset(payments_purposes()[$purpose])||$days<1||$days>30||!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[a-f0-9]{64}$/D',$fingerprint))throw new InvalidArgumentException('Review a current invoice, purpose and expiry of 1–30 days.');
    return staff_locked(fn()=>staff_transaction(function() use($invoice,$purpose,$days,$fingerprint,$key): int {
        $actor=payments_require();$hash=hash('sha256',json_encode([$invoice,$purpose,$days,$fingerprint],JSON_THROW_ON_ERROR));
        $old=one('SELECT * FROM '.table('payment_requests').' WHERE request_key=? FOR UPDATE',[$key]);
        if($old){if((int)$old['actor_id']!==(int)$actor['id']||!hash_equals($old['request_hash'],$hash))throw new RuntimeException('This request was already submitted with different details.');return (int)$old['id'];}
        $i=payments_invoice($invoice);if(!hash_equals(payments_invoice_hash($i),$fingerprint))throw new RuntimeException('The invoice changed. Reload and review its current amount.');
        attempts_assert_clear($invoice);
        query('UPDATE '.table('payment_requests')." SET status='superseded' WHERE invoice_id=? AND status='prepared'",[$invoice]);
        query('INSERT INTO '.table('payment_requests').' (invoice_id,job_id,client_id,actor_id,request_key,request_hash,invoice_hash,purpose,status,amount_cents,currency,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$invoice,$i['job_id'],$i['client_id'],$actor['id'],$key,$hash,$fingerprint,$purpose,'prepared',ledger_balance($i)['due_cents'],$i['currency'],time()+$days*86400,gmdate('Y-m-d H:i:s')]);
        $id=(int)db()->lastInsertId();payments_audit((int)$actor['id'],'request.prepare','Request '.$id.' invoice '.$invoice.'; charging disabled');return $id;
    }));
}
function payments_revoke(int $id): void {
    staff_locked(fn()=>staff_transaction(function() use($id): void {
        $actor=payments_require();$r=one('SELECT * FROM '.table('payment_requests').' WHERE id=? FOR UPDATE',[$id]);
        if(!$r||$r['status']!=='prepared')throw new RuntimeException('Only a prepared request can be revoked.');
        attempts_assert_clear((int)$r['invoice_id']);
        query('UPDATE '.table('payment_requests')." SET status='revoked' WHERE id=?",[$id]);payments_audit((int)$actor['id'],'request.revoke','Request '.$id.' invoice '.$r['invoice_id']);
    }));
}
function payments_request_state(array $r): string {
    if($r['status']!=='prepared')return $r['status'];if((int)$r['expires_at']<=time())return 'expired';
    try{$i=payments_invoice((int)$r['invoice_id']);return hash_equals($r['invoice_hash'],payments_invoice_hash($i))?'prepared':'invoice changed';}catch(RuntimeException){return 'unavailable';}
}
/** Called only inside the authenticated invoice view; ownership is also rechecked here. */
function payments_client_view(array $invoice): void {
    if(!payments_ready()||!($client=studio_client()))return;
    $r=one('SELECT r.* FROM '.table('payment_requests').' r JOIN '.table('studio_jobs').' j ON j.id=r.job_id WHERE r.invoice_id=? AND r.client_id=? AND j.client_id=? ORDER BY r.id DESC LIMIT 1',[$invoice['id'],$client['id'],$client['id']]);
    if(!$r)return;
    echo '<p class="payment-request"><strong>'.esc(payments_purposes()[$r['purpose']]??'Invoice request').'</strong> · '.esc(payments_request_state($r)).'<br>Online payment is not available yet. Preparing this request does not record a payment. Follow the studio’s invoice instructions.</p>';
}
