<?php
/** Bounded local observations; passive readers never connect to a mailbox. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/mail/messages.php';
require_once CWP_CODE_ROOT.'/database/schema/mail_notices_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/mail_notices_migrate.php';

function mail_notices_ready(): bool {
    return (one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_notices_schema_version'])['value']??'')==='1';
}
/** Navigation eligibility only; all data operations recheck the current session and grants. */
function mail_notices_access(array $actor): bool {
    $permissions=json_decode($actor['permissions']??'[]',true);
    return !support_current()&&staff_ready()&&(($actor['role']??'')==='owner'||(is_array($permissions)&&in_array('mail.read',$permissions,true)));
}
function mail_notices_actor(bool $ready=true): array {
    $actor=mail_accounts_actor();$permissions=json_decode($actor['permissions'],true);
    // Global eligibility only. The projection and item actions still require exact assignments.
    if($actor['role']!=='owner'&&(!is_array($permissions)||!in_array('mail.read',$permissions,true)))throw new RuntimeException('Email notices are unavailable to this administrator.');
    if($ready&&(!mail_notices_ready()||!mail_inbox_ready()||!mail_messages_ready()))throw new RuntimeException('An owner must prepare email notices in Email accounts first.');
    return $actor;
}
function mail_notices_handle(string $handle): void {
    if(!preg_match('/\A[a-f0-9]{32}\z/D',$handle))throw new InvalidArgumentException('This email notice is unavailable.');
}
function mail_notices_same(array $left,array $right): bool {
    foreach(['account_id','account_revision','folder','uidvalidity','uid'] as $key) {
        foreach([$left,$right] as $tuple)if(!isset($tuple[$key])||(!is_string($tuple[$key])&&!(is_int($tuple[$key])&&$key==='account_revision')))return false;
        if((string)$left[$key]!== (string)$right[$key])return false;
    }
    return true;
}
/** Called within the successful inbox terminal transaction, while the staff mutex is held. */
function mail_notices_observe(array $started,#[\SensitiveParameter] array $batch,?callable $newHandle=null): void {
    if(!db()->inTransaction())throw new LogicException('Email observations require the inbox result transaction.');
    if(!mail_notices_ready())return;
    [$actor]=mail_inbox_authority($started['account_id'],(int)$started['account_revision']);
    if((int)$actor['id']!==(int)$started['actor_id'])throw new RuntimeException('Email observation authority changed.');
    $batch=mail_inbox_batch($batch);
    $read=one('SELECT q.*,r.result_code,r.finished_at,r.uidvalidity,r.message_count,r.mailbox_total FROM '.table('mail_inbox_reads').' q JOIN '.table('mail_inbox_results').' r ON r.id=q.id WHERE q.id=?',[$started['id']]);
    if(!$read||$read['result_code']!=='ok'||$read['account_id']!==$started['account_id']||(int)$read['account_revision']!==(int)$started['account_revision']||(int)$read['actor_id']!==(int)$actor['id'])throw new LogicException('Email observations require a matching successful inbox result.');
    if($read['uidvalidity']!==$batch['uidvalidity']||(int)$read['message_count']!==count($batch['messages'])||(int)$read['mailbox_total']!==$batch['total'])throw new LogicException('Email observations do not match the accepted inbox window.');
    $old=all('SELECT * FROM '.table('mail_notice_items').' WHERE account_id=?',[$read['account_id']]);
    $planned=[];$used=[];
    foreach($batch['messages'] as $slot=>$message) {
        $tuple=['account_id'=>$read['account_id'],'account_revision'=>(int)$read['account_revision'],'folder'=>'INBOX','uidvalidity'=>$batch['uidvalidity'],'uid'=>$message['uid']];
        $matched=null;foreach($old as $item)if(mail_notices_same($item,$tuple)){$matched=$item;break;}
        $handle=$matched['handle']??($newHandle??static fn()=>bin2hex(random_bytes(16)))();
        if(!is_string($handle))throw new RuntimeException('Email notice identifier unavailable.');
        mail_notices_handle($handle);
        $collision=one('SELECT * FROM '.table('mail_notice_items').' WHERE handle=?',[$handle]);
        if(isset($used[$handle])||($collision&&!mail_notices_same($collision,$tuple)))throw new RuntimeException('Email notice identifier collision. Refresh the inbox again.');
        $used[$handle]=true;$planned[]=['handle'=>$handle]+$tuple+['slot'=>$slot,'server_seen'=>(int)$message['seen'],'flags_observed_at'=>$read['finished_at'],'existing'=>$matched!==null];
    }
    // Plan/collision checks precede every mutation. Retire only the old window's absent tuples.
    foreach($old as $item)if(!isset($used[$item['handle']]))query('DELETE FROM '.table('mail_notice_items').' WHERE handle=?',[$item['handle']]);
    query('INSERT INTO '.table('mail_notice_windows').' (account_id,account_revision,folder,uidvalidity,request_id,checked_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE account_revision=VALUES(account_revision),folder=VALUES(folder),uidvalidity=VALUES(uidvalidity),request_id=VALUES(request_id),checked_at=VALUES(checked_at)',[$read['account_id'],$read['account_revision'],'INBOX',$batch['uidvalidity'],$read['id'],$read['finished_at']]);
    foreach($planned as $item) {
        if($item['existing'])query('UPDATE '.table('mail_notice_items').' SET slot=?,server_seen=?,flags_observed_at=? WHERE handle=?',[$item['slot'],$item['server_seen'],$item['flags_observed_at'],$item['handle']]);
        else {unset($item['existing']);query('INSERT INTO '.table('mail_notice_items').' (handle,account_id,account_revision,folder,uidvalidity,uid,slot,server_seen,flags_observed_at) VALUES (?,?,?,?,?,?,?,?,?)',array_values($item));}
    }
}
/** Success is local to this actor; a returned server flag is a separate observed fact. */
function mail_notices_opened(array $started,#[\SensitiveParameter] array $content): void {
    if(!db()->inTransaction())throw new LogicException('Email opened state requires the message result transaction.');
    if(!mail_notices_ready())return;
    [$actor]=mail_message_authority($started['account_id'],(int)$started['account_revision']);
    if((int)$actor['id']!==(int)$started['actor_id']||!is_bool($content['seen']??null)||!mail_notices_same($started,['account_id'=>$started['account_id'],'account_revision'=>$started['account_revision']]+$content))throw new LogicException('Invalid exact-message observation.');
    $result=one('SELECT q.account_id,q.account_revision,q.actor_id,q.folder,q.uidvalidity,q.uid,r.finished_at FROM '.table('mail_message_reads').' q JOIN '.table('mail_message_results')." r ON r.id=q.id AND r.result_code='ok' WHERE q.id=?",[$started['id']]);
    if(!$result||!mail_notices_same($result,$started)||(int)$result['actor_id']!==(int)$actor['id'])throw new LogicException('Opened state requires a matching successful message result.');
    $item=one('SELECT i.handle FROM '.table('mail_notice_items').' i JOIN '.table('mail_notice_windows').' w ON w.account_id=i.account_id AND w.account_revision=i.account_revision AND w.folder=i.folder AND w.uidvalidity=i.uidvalidity WHERE i.account_id=? AND i.account_revision=? AND i.folder=? AND i.uidvalidity=? AND i.uid=?',[$started['account_id'],$started['account_revision'],'INBOX',$started['uidvalidity'],$started['uid']]);
    if(!$item)return;
    query('UPDATE '.table('mail_notice_items').' SET server_seen=?,flags_observed_at=? WHERE handle=?',[(int)$content['seen'],$result['finished_at'],$item['handle']]);
    query('INSERT INTO '.table('mail_notice_states').' (handle,actor_id,opened_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE opened_at=VALUES(opened_at)',[$item['handle'],$actor['id'],$result['finished_at']]);
}
/** Current-grant predicate is applied before any account labels/counts are selected. */
function mail_notices_accounts_sql(array $actor): array {
    $now=time();
    $sql=' FROM '.table('mail_accounts').' a JOIN '.table('mail_account_grants')." g ON g.account_id=a.id WHERE g.user_id=? AND g.can_read=1 AND g.enabled=1 AND a.enabled=1 AND a.credentials<>'' AND g.issued_at<=? AND (g.expires_at IS NULL OR g.expires_at>?)";
    return [$sql,[(int)$actor['id'],$now,$now]];
}
function mail_notices_projection(array $actor): array {
    [$accounts,$args]=mail_notices_accounts_sql($actor);
    // Use an authorized account subquery so filtering precedes all presentation fields.
    $sql=' FROM (SELECT a.id,a.label,a.address,a.revision'.$accounts.') a JOIN '.table('mail_notice_windows')." w ON w.account_id=a.id AND w.account_revision=a.revision AND w.folder='INBOX' JOIN ".table('mail_notice_items').' i ON i.account_id=w.account_id AND i.account_revision=w.account_revision AND i.folder=w.folder AND i.uidvalidity=w.uidvalidity LEFT JOIN '.table('mail_notice_states').' s ON s.handle=i.handle AND s.actor_id=?';
    return [$sql,[...$args,(int)$actor['id']]];
}
function mail_notices_count(): int {
    [$sql,$args]=mail_notices_projection(mail_notices_actor());
    return count(all('SELECT i.handle'.$sql.' WHERE i.server_seen=0 AND s.opened_at IS NULL AND s.dismissed_at IS NULL LIMIT 100',$args));
}
function mail_notices_passive_count(): int {
    $last=array_intersect_key($_SESSION,array_flip(['last_activity','client_last']));
    try{return mail_notices_count();}
    finally{foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$last))$_SESSION[$key]=$last[$key];else unset($_SESSION[$key]);}}
}
function mail_notices_passive_summary(): array {
    $last=array_intersect_key($_SESSION,array_flip(['last_activity','client_last']));
    try {
        [$sql,$args]=mail_notices_accounts_sql(mail_notices_actor());
        if(!one('SELECT a.id'.$sql.' LIMIT 1',$args))throw new RuntimeException('No current email notice access.');
        $count=mail_notices_count();return ['count'=>$count,'capped'=>$count>=100,'url'=>admin_url('mail-notices')];
    }finally{foreach(['last_activity','client_last'] as $key){if(array_key_exists($key,$last))$_SESSION[$key]=$last[$key];else unset($_SESSION[$key]);}}
}
function mail_notices_feed(int $page=1): array {
    [$sql,$args]=mail_notices_projection(mail_notices_actor());$page=max(1,min($page,100000));
    return all('SELECT i.handle,i.account_id,i.account_revision,i.folder,i.uidvalidity,i.uid,i.server_seen,i.flags_observed_at,a.label,a.address,w.checked_at,s.opened_at,s.dismissed_at'.$sql.' ORDER BY w.checked_at DESC,a.id,i.slot,i.handle LIMIT 21 OFFSET '.(($page-1)*20),$args);
}
function mail_notices_accounts(int $page=1): array {
    [$sql,$args]=mail_notices_accounts_sql(mail_notices_actor());$page=max(1,min($page,100000));
    return all('SELECT a.id,a.label,a.address,a.revision,(SELECT w.checked_at FROM '.table('mail_notice_windows').' w WHERE w.account_id=a.id AND w.account_revision=a.revision) AS checked_at,(SELECT r.outcome FROM '.table('mail_inbox_reads').' q LEFT JOIN '.table('mail_inbox_results').' r ON r.id=q.id WHERE q.account_id=a.id AND q.account_revision=a.revision ORDER BY q.started_epoch DESC,q.id DESC LIMIT 1) AS latest_outcome'.$sql.' ORDER BY a.label,a.id LIMIT 21 OFFSET '.(($page-1)*20),$args);
}
/** The form carries the independent full identity, not merely an opaque lookup handle. */
function mail_notices_resolve(string $handle,array $tuple): array {
    mail_notices_handle($handle);$actor=mail_notices_actor();[$sql,$args]=mail_notices_projection($actor);
    $row=one('SELECT i.handle,i.account_id,i.account_revision,i.folder,i.uidvalidity,i.uid'.$sql.' WHERE i.handle=?',[...$args,$handle]);
    if(!$row||!mail_notices_same($row,$tuple)||!mail_accounts_can('mail.read',$row['account_id']))throw new RuntimeException('This email notice is unavailable. Refresh your recent email notices.');
    return $row;
}
function mail_notices_dismiss(string $handle,array $tuple): void {
    staff_locked(fn()=>staff_transaction(function()use($handle,$tuple):void {
        $row=mail_notices_resolve($handle,$tuple);$actor=mail_notices_actor();
        query('INSERT INTO '.table('mail_notice_states').' (handle,actor_id,dismissed_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE dismissed_at=VALUES(dismissed_at)',[$row['handle'],$actor['id'],gmdate('Y-m-d H:i:s')]);
        mail_accounts_audit((int)$actor['id'],'notice_dismissed','Notice '.$handle);
    }));
}
