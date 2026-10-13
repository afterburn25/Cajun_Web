<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function appointments_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use ($password): void {
        appointments_actor(false);$actor=staff_owner($password);
        $keys=['appointment_settings'=>[],'appointment_settings_revisions'=>['appointment_settings_revision'=>['revision']],'appointments'=>['appointment_lead'=>['lead_id']],'appointment_revisions'=>['appointment_revision'=>['appointment_id','revision']]];
        foreach (appointments_schema(config()['db']['prefix']) as $name=>$sql) {
            if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])) catalog_validate_schema($name,$sql,$keys[$name]);else db()->exec($sql);
            $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
            $linked=in_array($name,['appointments','appointment_revisions'],true);$column=$name==='appointments'?'lead_id':'appointment_id';$parent=$name==='appointments'?'leads':'appointments';
            if ((!$linked && $relations) || ($linked && (count($relations)!==1 || $relations[0]['COLUMN_NAME']!==$column || $relations[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$parent || $relations[0]['REFERENCED_COLUMN_NAME']!=='id' || $relations[0]['DELETE_RULE']!=='CASCADE' || !in_array($relations[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)))) throw new RuntimeException('Incompatible appointment relationships. Existing data was preserved.');
        }
        staff_transaction(function() use ($actor): void {
            if (!appointments_ready()) {
                foreach (array_keys(appointments_schema(config()['db']['prefix'])) as $name) if ((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()) throw new RuntimeException('Unrecognized scheduling records exist. No data was adopted.');
                appointments_seed((int)$actor['id']);save_setting('appointments_schema_version','1');
            } else {appointments_settings(true);if ((int)query('SELECT COUNT(*) FROM '.table('appointment_settings'))->fetchColumn()!==1) throw new RuntimeException('Unrecognized calendar configurations exist. Existing records were preserved.');}
            appointments_audit((int)$actor['id'],'setup','Additive scheduling storage verified');
        });
    });
}
