<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function reviews_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use ($password): void {
        reviews_actor(false);$actor=staff_owner($password);
        $keys=['website_reviews'=>['review_lead'=>['lead_id']],'website_review_reports'=>['review_report'=>['review_id']],'website_review_revisions'=>['review_revision'=>['review_id','revision']]];
        foreach (reviews_schema(config()['db']['prefix']) as $name=>$sql) {
            if (one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])) catalog_validate_schema($name,$sql,$keys[$name]);
            else db()->exec($sql);
            $column=$name==='website_reviews'?'lead_id':'review_id';$parent=$name==='website_reviews'?'leads':'website_reviews';
            $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
            if (count($relations)!==1 || $relations[0]['COLUMN_NAME']!==$column || $relations[0]['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$parent || $relations[0]['REFERENCED_COLUMN_NAME']!=='id' || $relations[0]['DELETE_RULE']!=='CASCADE' || !in_array($relations[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)) {
                throw new RuntimeException('Incompatible review record relationships. Existing data was preserved.');
            }
        }
        staff_transaction(function() use ($actor): void {
            if (!reviews_ready()) {
                foreach (array_keys(reviews_schema(config()['db']['prefix'])) as $name) if ((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()) throw new RuntimeException('Unrecognized review records exist. No data was adopted.');
                save_setting('website_reviews_schema_version','1');
            }
            reviews_audit((int)$actor['id'],'setup','Additive review storage verified');
        });
    });
}
