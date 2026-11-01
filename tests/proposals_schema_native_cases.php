<?php
/** Invoked after the proposal fixture creates its four real SQL tables. */
declare(strict_types=1);
$schemaBefore=p_snapshot();
foreach(['proposals','proposal_versions','proposal_events','proposal_decisions'] as $schemaTable){
    $temporary='propci_saved_'.$schemaTable;
    db()->exec('RENAME TABLE '.table($schemaTable).' TO `'.$temporary.'`');
    try{
        $refused=false;try{proposals_migrate($password);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$refused=str_contains($e->getMessage(),'Restore the original proposal database');}
        p_ok('Missing '.$schemaTable.' cannot silently become empty history',$refused&&!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['propci_'.$schemaTable]));
    }finally{db()->exec('RENAME TABLE `'.$temporary.'` TO '.table($schemaTable));}
    p_ok('Restored '.$schemaTable.' retains exact original state',p_snapshot()===$schemaBefore);
}
foreach(['future','0'] as $badVersion){save_setting('proposals_schema_version',$badVersion);try{p_deny('Unsupported proposal marker refuses migration: '.$badVersion,fn()=>proposals_migrate($password));p_ok('Unsupported marker preserves rows',p_snapshot()===$schemaBefore);}finally{save_setting('proposals_schema_version','1');}}
db()->exec('ALTER TABLE '.table('proposals').' ADD INDEX unexpected(title)');
try{p_deny('Unexpected proposal index refuses migration',fn()=>proposals_migrate($password));p_ok('Schema refusal preserves every existing row',p_snapshot()===$schemaBefore);}finally{db()->exec('ALTER TABLE '.table('proposals').' DROP INDEX unexpected');}
proposals_migrate($password);p_ok('Repaired schema still validates all ordinary and unique indexes',proposals_ready()&&p_snapshot()===$schemaBefore);
