<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function staff_migrate(int $ownerId,#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($ownerId,$password): void {
        $actor=require_admin();
        if (!password_verify($password,$actor['password_hash'])) throw new InvalidArgumentException('Your current password is not correct.');
        if (staff_ready()&&!staff_can('staff.manage',$actor)) throw new RuntimeException('Only an owner can verify administrator access.');
        $chosen=one('SELECT id,password_hash FROM '.table('users').' WHERE id=?',[$ownerId]);
        if (!$chosen||$chosen['password_hash']==='') throw new InvalidArgumentException('Choose an existing administrator as the initial owner.');
        // Validate every existing table before creating any missing one; no ALTER/DROP or silent adoption.
        $schema=staff_schema(config()['db']['prefix']);$missing=[];
        foreach($schema as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))staff_validate_schema($name);
            else $missing[$name]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        if(staff_ready())return;
        staff_transaction(function() use($ownerId): void {
            if((int)query('SELECT COUNT(*) FROM '.table('staff_profiles'))->fetchColumn()!==0||(int)query('SELECT COUNT(*) FROM '.table('staff_invitations'))->fetchColumn()!==0)throw new RuntimeException('Unrecognized staff records already exist. No accounts were overwritten.');
            foreach(all('SELECT id FROM '.table('users').' ORDER BY id') as $u)query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$u['id'],(int)$u['id']===$ownerId?'owner':'administrator',json_encode(['website.manage','clients.manage'],JSON_THROW_ON_ERROR)]);
            save_setting('staff_schema_version','1');log_event('staff.upgrade','Initial owner '.$ownerId);
        });
    });
}
