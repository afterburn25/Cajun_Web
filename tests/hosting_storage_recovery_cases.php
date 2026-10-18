<?php
/** Native retained-table loss fixtures; callers use disposable CI databases only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);

function hosting_test_recovery(string $module,array $names,callable $ready,callable $prepare,callable $read,callable $snapshot,callable $check):void {
    $marker='hosting_'.$module.'_schema_version';
    $deny=function(string $label,callable $work)use($check):void {
        try{$work();}
        catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$check($label,str_contains($e->getMessage(),'Restore the original database'));return;}
        $check($label,false);
    };
    $exists=fn(string $name)=>one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])!==null;
    foreach($names as $name){
        $before=$snapshot();$saved=table('retained_'.$name);$settings=all('SELECT * FROM '.table('settings').' ORDER BY name');$audits=all('SELECT * FROM '.table('audit').' ORDER BY id');
        $rows=all('SELECT * FROM '.table($name).' ORDER BY id');
        db()->exec('RENAME TABLE '.table($name).' TO '.$saved);
        try{
            $check('Missing established '.$name.' is not ready',!$ready());
            $deny('Missing '.$name.' refuses ordinary access with recovery guidance',$read);
            $deny('Missing '.$name.' setup requires restoration',$prepare);
            $check('Refused '.$name.' setup creates no empty replacement or audit',
                !$exists($name)&&all('SELECT * FROM '.$saved.' ORDER BY id')===$rows
                &&all('SELECT * FROM '.table('settings').' ORDER BY name')===$settings
                &&all('SELECT * FROM '.table('audit').' ORDER BY id')===$audits);
            save_setting($marker,'');
            $deny('Lost marker cannot recreate established '.$name,$prepare);
            $check('Lost marker and '.$name.' retain original table and audit',
                !$exists($name)&&all('SELECT * FROM '.$saved.' ORDER BY id')===$rows
                &&all('SELECT * FROM '.table('audit').' ORDER BY id')===$audits);
        }finally{
            save_setting($marker,'1');
            // Refused setup must not have created a table under the original name.
            // If it did, fail rather than deleting it to make restoration appear successful.
            if($exists($name))throw new RuntimeException('Unexpected replacement table created during recovery refusal.');
            db()->exec('RENAME TABLE '.$saved.' TO '.table($name));
        }
        $check('Restoring '.$name.' recovers exact original history without setup',$ready()&&$snapshot()===$before);
    }
    // Loss of the whole family and marker is still detectable through its original setup audit.
    $before=$snapshot();$moved=[];$audits=all('SELECT * FROM '.table('audit').' ORDER BY id');
    try{
        foreach($names as $name){db()->exec('RENAME TABLE '.table($name).' TO '.table('retained_'.$name));$moved[]=$name;}
        save_setting($marker,'');
        $check('Entire missing '.$module.' family is not ready',!$ready());
        $deny('Original '.$module.' setup audit prevents empty reinstallation',$prepare);
        $check('Whole-family refusal preserves setup history without replacement tables',
            !array_filter($names,$exists)&&all('SELECT * FROM '.table('audit').' ORDER BY id')===$audits);
    }finally{
        save_setting($marker,'1');
        foreach(array_reverse($moved) as $name){
            if($exists($name))throw new RuntimeException('Unexpected replacement family created during recovery refusal.');
            db()->exec('RENAME TABLE '.table('retained_'.$name).' TO '.table($name));
        }
    }
    $check('Whole '.$module.' family restores exact original bindings and history',$ready()&&$snapshot()===$before);
}
