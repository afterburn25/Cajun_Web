<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/backups/archive.php';

function backups_page(array $user): void {
    if(!backups_ready()){
        echo '<section class="workspace-card"><h2>Prepare backup storage</h2><p>Adds the backups record table and a private archive directory outside the web root. Snapshots cover the deployable source tree only — configuration, keys, private storage and customer uploads are structurally excluded and never enter an archive.</p>';
        if(staff_can('staff.manage',$user)){echo '<form method="post">';admin_action('backups_setup');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I understand backups cover deployed files only, not the database.',false);echo '<button class="btn gold">Prepare backup storage</button></form>';}else echo '<p>Ask an owner to prepare backup storage.</p>';
        echo '</section>';return;
    }
    $preview=is_string($_GET['preview']??null)?$_GET['preview']:'';
    if($preview!==''){backups_preview_section($preview);}
    ?><section class="workspace-card"><h2>Create a backup snapshot</h2><p>Records every deployable file's hash and stores a private ZIP archive. Restores can only rewind files the manifest covers; database rows and private storage are not touched.</p>
    <?php if(staff_can('staff.manage',$user)):?><form method="post"><?php admin_action('backups_create');studio_hidden('request_key',bin2hex(random_bytes(32)));admin_input('retain_days','Keep for (days, 7–365)','90','number','required min="7" max="365"');admin_textarea('notes','Backup notes','',3,5000);admin_check('confirm','This snapshot contains deployable files only — no database, configuration, keys or private storage.',false);?><button class="btn gold">Create backup</button></form><?php else:echo '<p>Ask an owner to create or restore backups.</p>';endif;?>
    <h2>Stored backups</h2><?php
    $rows=all('SELECT * FROM '.table('backups').' ORDER BY created_at DESC LIMIT 100');
    if(!$rows)echo '<p>No backups have been recorded.</p>';
    foreach($rows as $row):$expired=strtotime($row['retain_until'])<strtotime(gmdate('Y-m-d'));?><article class="workspace-version"><h3>Backup <?=esc(substr($row['id'],0,8))?> · <?=esc($row['created_at'])?> UTC</h3>
    <p><?=(int)$row['items']?> items · <?=esc(number_format((int)$row['bytes']/1048576,1))?> MiB · keep until <?=esc($row['retain_until'])?><?php if($expired)echo ' · <strong>past retention</strong>';if($row['status']==='applied')echo ' · restored '.esc($row['restored_at']);if($row['notes']!=='')echo ' — '.esc($row['notes']);?></p>
    <div class="button-row"><a class="btn outline small" href="<?=esc(admin_url('backups',['download'=>$row['id']]))?>">Download archive</a><a class="btn outline small" href="<?=esc(admin_url('backups',['preview'=>$row['id']]))?>">Preview restore</a></div></article><?php endforeach;
    if(staff_can('staff.manage',$user)):?><details><summary>Prune expired backups</summary><p>Deletes archives and records past their retention date. Nothing within retention is removed.</p><form method="post"><?php admin_action('backups_prune');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','Remove only backups past their retention date.',false);?><button class="btn outline">Remove expired backups</button></form></details><?php endif;?></section><?php
}

function backups_preview_section(string $id): void {
    try{$row=backups_row($id);$plan=backups_preview($row);}catch(InvalidArgumentException|RuntimeException $e){echo '<section class="workspace-card"><p class="notice error">'.esc($e->getMessage()).'</p></section>';return;}
    $counts=['missing'=>0,'differs'=>0,'identical'=>0];
    foreach($plan->impact as $item)$counts[$item['current']]++;
    echo '<section class="workspace-card"><h2>Restore preview — backup '.esc(substr($row['id'],0,8)).'</h2>';
    echo '<p>Live comparison of recorded hashes against current files: <strong>'.$counts['differs'].'</strong> would change, <strong>'.$counts['missing'].'</strong> would be restored as missing, <strong>'.$counts['identical'].'</strong> already match and are left alone.</p>';
    echo '<details><summary>Items that would change</summary><ul class="backup-impact">';
    foreach($plan->impact as $item)if($item['current']!=='identical')echo '<li><code>'.esc($item['item']).'</code> — '.$item['current'].'</li>';
    echo '</ul></details>';
    if($plan->manifest->expired(time())){echo '<p class="notice warning">This backup is past its retention date; it cannot be restored. Create a fresh archive.</p></section>';return;}
    echo '<p>Restoring overwrites changed deployable files in place. To proceed, type <code>'.esc(backups_restore_phrase($row)).'</code> and confirm with your owner password.</p>';
    echo '<form method="post">';admin_action('backups_restore');studio_hidden('backup',$row['id']);admin_input('phrase','Type the confirmation phrase','','text','required maxlength="60"');admin_input('owner_password','Your current owner password','','password','required autocomplete="current-password"');admin_check('confirm','I reviewed the impact list and accept that changed files will be overwritten.',false);echo '<button class="btn gold">Restore this backup</button></form><p><a class="btn outline small" href="'.esc(admin_url('backups')).'">Back to backup list</a></p></section>';
}
