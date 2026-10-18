<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/support/sessions.php';

/** Recognized additive shapes only; never adopt a missing established table. */
function license_upgrade_shape(string $name,string $sql,string $version,bool $finished): void {
    $columns=array_column(all('SHOW FULL COLUMNS FROM '.table($name)),'Field');
    $keys=match($name){'website_licenses'=>['license_key'=>['license_key'],'invoice_lookup'=>['invoice_id']],'license_domains'=>['license_domain'=>['license_id','domain']],default=>[]};
    if($name==='website_licenses') {
        if(!in_array('is_master',$columns,true)) {
            if($finished||$version!=='')throw new RuntimeException('The established master-license column is missing. Preserve the database for recovery.');
            $sql=str_replace(',is_master TINYINT NOT NULL DEFAULT 0','',$sql);
        }
        if(!in_array('invoice_id',$columns,true)) {
            if($finished||in_array($version,['2','3'],true))throw new RuntimeException('The established license invoice column is missing. Preserve the database for recovery.');
            $sql=str_replace([',invoice_id INT UNSIGNED NULL',',UNIQUE invoice_lookup(invoice_id)'],'',$sql);unset($keys['invoice_lookup']);
        }
    }
    if($name==='license_bundles'&&!in_array('sha256',$columns,true)) {
        if($finished||$version==='3')throw new RuntimeException('The established bundle checksum column is missing. Preserve the database for recovery.');
        $sql=str_replace(',sha256 CHAR(64) NULL','',$sql);
    }
    catalog_validate_schema($name,$sql,$keys);
}

/** Explicit owner preparation; preflight every table before journal or DDL. */
function license_migrate(#[\SensitiveParameter] string $password): void {
    if(db()->inTransaction())throw new LogicException('License preparation requires its own transaction.');
    staff_locked(function() use ($password): void {
        if(support_current())throw new RuntimeException('Leave support before setup.');
        $actor=staff_owner($password);
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $version=(string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['license_schema_version'])['value']??'');
        if(!in_array($version,['','1','2','3'],true))throw new RuntimeException('Unsupported license schema version. Preserve the database for recovery.');
        $prefix=config()['db']['prefix'];$schema=license_schema($prefix);$present=[];
        foreach($schema as $name=>$sql)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$prefix.$name]))$present[]=$name;
        $cutover=one('SELECT value FROM '.table('settings').' WHERE name=?',['license_invoice_cutover']);
        $pending=one('SELECT value FROM '.table('settings').' WHERE name=?',['license_upgrade_pending']);
        $journal=null;
        if($pending!==null) {
            $journal=json_decode((string)$pending['value'],true,8,JSON_THROW_ON_ERROR);
            if(!is_array($journal)||array_keys($journal)!==['format','from','invoice_max','present']||$journal['format']!==1||!is_string($journal['from'])||!in_array($journal['from'],['','1','2','3'],true)||!is_int($journal['invoice_max'])||$journal['invoice_max']<0||$journal['invoice_max']>4294967295||!is_array($journal['present'])||!in_array($journal['present'],[[],array_keys($schema)],true))throw new RuntimeException('License upgrade history needs recovery.');
        }
        if($cutover!==null) {
            $max=license_invoice_cutover();
            if($version!=='3'||$present!==array_keys($schema)||($journal!==null&&$journal['invoice_max']!==$max))throw new RuntimeException('Established licensing storage needs recovery.');
        } else {
            if($journal!==null&&$journal['from']!==$version)throw new RuntimeException('License upgrade history needs recovery.');
            if($present!==array_keys($schema)) {
                // Only a journaled fresh preparation may resume partial CREATEs.
                if($version!==''||($present!==[]&&($journal===null||$journal['present']!==[]))||($journal!==null&&$journal['present']!==[]))throw new RuntimeException('Established licensing tables are missing. Preserve the database for recovery.');
                // Absence is not fresh installation when independent license audit survives.
                // Only the exact single begin witness can justify unfinished empty CREATEs.
                $history=(int)query('SELECT COUNT(*) FROM '.table('audit')." WHERE action LIKE 'license.%'")->fetchColumn();
                if($journal===null) {
                    if($history!==0)throw new RuntimeException('Licensing audit history needs recovery. Existing records were not recreated.');
                } else {
                    $begins=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=BINARY ? AND BINARY detail=BINARY ?', ['license.setup.begin','Preserved license upgrade; invoice cutover '.$journal['invoice_max']])->fetchColumn();
                    if($history!==1||$begins!==1)throw new RuntimeException('Licensing audit history needs recovery. Existing records were not recreated.');
                }
            }
            if($journal!==null&&$journal['present']===[])foreach($present as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Interrupted fresh licensing storage contains records. Preserve the database for recovery.');
        }
        foreach($present as $name)license_upgrade_shape($name,$schema[$name],$version,$cutover!==null);
        if($cutover===null&&$journal===null) {
            $max=(int)query('SELECT COALESCE(MAX(id),0) FROM '.table('studio_invoices'))->fetchColumn();
            $journal=['format'=>1,'from'=>$version,'invoice_max'=>$max,'present'=>$present];
            staff_transaction(function() use($journal,$actor):void {
                save_setting('license_upgrade_pending',json_encode($journal,JSON_THROW_ON_ERROR));
                license_audit((int)$actor['id'],'setup.begin','Preserved license upgrade; invoice cutover '.$journal['invoice_max']);
            });
        }
        foreach($schema as $name=>$sql) {
            if(!in_array($name,$present,true))db()->exec($sql);
            else {
                $columns=array_column(all('SHOW FULL COLUMNS FROM '.table($name)),'Field');
                if($name==='website_licenses'&&!in_array('invoice_id',$columns,true))db()->exec('ALTER TABLE `'.$prefix.$name.'` ADD COLUMN invoice_id INT UNSIGNED NULL AFTER product_id, ADD UNIQUE KEY invoice_lookup (invoice_id)');
                if($name==='website_licenses'&&!in_array('is_master',$columns,true))db()->exec('ALTER TABLE `'.$prefix.$name.'` ADD COLUMN is_master TINYINT NOT NULL DEFAULT 0 AFTER is_active');
                if($name==='license_bundles'&&!in_array('sha256',$columns,true))db()->exec('ALTER TABLE `'.$prefix.$name.'` ADD COLUMN sha256 CHAR(64) NULL AFTER title');
            }
            license_upgrade_shape($name,$sql,'3',true);
        }
        staff_transaction(function() use($actor,$cutover,$journal):void {
            if($cutover===null)save_setting('license_invoice_cutover',(string)$journal['invoice_max']);
            save_setting('license_schema_version','3');
            license_audit((int)$actor['id'],'setup','Additive license storage verified; existing records preserved');
        });
    });
}
