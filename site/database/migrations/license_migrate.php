<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function license_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use ($password): void {
        if(support_current())throw new RuntimeException('Leave support before setup.');
        $actor=staff_owner($password);
        if(!studio_ready())throw new RuntimeException('Enable the client workspace first.');
        $keys=[
            'website_licenses'=>['license_key'=>['license_key']],
            'license_domains'=>['license_domain'=>['license_id','domain']],
            'license_bundles'=>[],
        ];
        foreach (license_schema(config()['db']['prefix']) as $name=>$sql) {
            if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])) catalog_validate_schema($name,$sql,$keys[$name]);
            else db()->exec($sql);
        }
        staff_transaction(function() use ($actor): void {
            if (!license_ready()) {
                foreach (array_keys(license_schema(config()['db']['prefix'])) as $name) if ((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()) throw new RuntimeException('Unrecognized license records exist. No data was adopted.');
                save_setting('license_schema_version','1');
            }
            license_audit((int)$actor['id'],'setup','Additive license storage verified');
        });
    });
}
