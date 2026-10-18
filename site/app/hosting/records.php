<?php
/** Owner-only local hosting records. No provider call, dispatch claim or HTTP route. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/hosting/storage.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/includes/foundation/hosting/HostingJournal.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_records_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/hosting_records_migrate.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,HostingPackage};

function hosting_records_ready():bool {
    return hosting_storage_ready('records');
}
function hosting_records_owner(bool $ready=true):array {
    if (support_current() || array_key_exists('support',$_GET)) throw new RuntimeException('Leave client support before using hosting records.');
    $actor=admin_user();
    if (!$actor || !staff_ready() || !studio_ready() || $actor['role']!=='owner' || !staff_can('staff.manage',$actor) || !staff_can('clients.manage',$actor)) throw new RuntimeException('An active owner account and prepared client workspace are required.');
    if ($ready) hosting_storage_require('records');
    return $actor;
}
function hosting_records_password(#[\SensitiveParameter] string $password,bool $ready=true):array {
    $actor=hosting_records_owner($ready);
    if (!password_verify($password,$actor['password_hash'])) throw new InvalidArgumentException('Your current password is not correct.');
    return $actor;
}
function hosting_records_id(string $id):void {
    if (!preg_match('/\A[a-f0-9]{32}\z/',$id)) throw new InvalidArgumentException('Hosting record unavailable.');
}
function hosting_records_client(int $id,bool $active):array {
    if ($id<1) throw new InvalidArgumentException('Client unavailable.');
    // Client administration also has legacy writers outside the staff mutex.
    // A mutation needs a current locking read, not the transaction's older snapshot.
    $client=one('SELECT id,active FROM '.table('studio_clients').' WHERE id=?'.($active && db()->inTransaction()?' FOR UPDATE':''),[$id]);
    if (!$client || ($active && (int)$client['active']!==1)) throw new InvalidArgumentException('Client unavailable.');
    return $client;
}
function hosting_records_json(array $value):string {
    return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
function hosting_records_audit(int $actor,string $action,string $detail):void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'hosting_records.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
/** Internal loader; callers must check current owner access and explicit client selection. */
function hosting_records_row(string $id,int $client,bool $lock=false):array {
    hosting_records_id($id);
    $row=one('SELECT * FROM '.table('hosting_records').' WHERE id=? AND client_id=?'.($lock?' FOR UPDATE':''),[$id,$client]);
    if (!$row) throw new InvalidArgumentException('Hosting record unavailable.');
    return $row;
}
/** Compare immutable creation terms, journal bytes and every retained event before adoption. */
function hosting_records_journal(array $row):HostingJournal {
    try {
        $initial=HostingJournal::fromJson($row['initial_json'],$row['id'],'client:'.$row['client_id']);
        $journal=HostingJournal::fromJson($row['journal_json'],$row['id'],'client:'.$row['client_id']);
        $start=json_decode($initial->json(),true,16,JSON_THROW_ON_ERROR);
        $current=json_decode($journal->json(),true,16,JSON_THROW_ON_ERROR);
        $events=$current['events'];$current['events']=[];
        if ($initial->revision()!==0 || $current!==$start || $start['created_at']!==(int)$row['created_epoch'] || $journal->revision()!==(int)$row['revision'] || !hash_equals($journal->fingerprint(),$row['journal_sha256'])) throw new RuntimeException('Hosting record mismatch.');
        $retained=all('SELECT * FROM '.table('hosting_record_events').' WHERE account_id=? ORDER BY revision',[$row['id']]);
        if (count($retained)!==count($events)) throw new RuntimeException('Missing hosting events.');
        $previous=$initial->fingerprint();$prefix=$start;
        foreach ($events as $index=>$event) {
            $saved=$retained[$index];$prefix['events'][]=$event;$next=hash('sha256',hosting_records_json($prefix));
            $input=hosting_records_json(['revision'=>$index,'command'=>$event['command'],'arguments'=>$event['arguments']]);
            if ($saved['id']!==$event['id'] || (int)$saved['revision']!==$index+1 || 'staff:'.$saved['actor_id']!==$event['actor'] || (int)$saved['recorded_epoch']!==$event['at'] || $saved['input_json']!==$input || !hash_equals($previous,$saved['before_sha256']) || !hash_equals($next,$saved['after_sha256'])) throw new RuntimeException('Hosting event mismatch.');
            $previous=$next;
        }
        return $journal;
    } catch (Throwable $e) {
        if ($e instanceof PDOException) throw $e;
        throw new RuntimeException('Saved hosting records are inconsistent. Existing data was preserved.');
    }
}
/** An approved local package snapshot, not an order, provider entitlement or paid service. */
function hosting_records_create(string $id,int $client,HostingPackage $package,#[\SensitiveParameter] string $password):string {
    return staff_locked(fn()=>staff_transaction(function()use($id,$client,$package,$password):string {
        $actor=hosting_records_password($password);
        return hosting_records_create_locked($id,$client,$package,(int)$actor['id']);
    }));
}
/** Internal writer shared by explicit owner creation and atomic order association. */
function hosting_records_create_locked(string $id,int $client,HostingPackage $package,int $actorId):string {
        if(!db()->inTransaction())throw new LogicException('Hosting creation needs its owning transaction.');
        hosting_storage_require('records');hosting_records_id($id);hosting_records_client($client,true);
        $old=one('SELECT * FROM '.table('hosting_records').' WHERE id=? FOR UPDATE',[$id]);
        $journal=HostingJournal::create($id,$package,'client:'.$client,$old?(int)$old['created_epoch']:time());
        if ($old) {
            hosting_records_journal($old);
            if ((int)$old['client_id']!==$client || (int)$old['created_by']!==$actorId || $old['initial_json']!==$journal->json()) throw new InvalidArgumentException('This hosting request identity was already used.');
            return $id;
        }
        $epoch=json_decode($journal->json(),true)['created_at'];
        query('INSERT INTO '.table('hosting_records').' (id,client_id,created_by,created_epoch,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,?,?,?,?,?,0)',[$id,$client,$actorId,$epoch,$journal->json(),$journal->json(),$journal->fingerprint()]);
        hosting_records_audit($actorId,'created','Local account '.$id.' client '.$client);return $id;
}
/** Local commands only. Remote attempts/results and reconciliation require a separate adapter. */
function hosting_records_change(string $id,int $client,int $revision,string $event,string $command,array $arguments,#[\SensitiveParameter] string $password):HostingJournal {
    return staff_locked(fn()=>staff_transaction(function()use($id,$client,$revision,$event,$command,$arguments,$password):HostingJournal {
        $actor=hosting_records_password($password);hosting_records_client($client,true);hosting_records_id($event);
        if ($revision<0 || $revision>=HostingJournal::MAX_EVENTS) throw new InvalidArgumentException('Invalid hosting revision.');
        if (!in_array($command,['link_domain','set_grace','open_dispute','close_dispute','cancel_order','request_cancellation'],true)) throw new InvalidArgumentException('This hosting operation is not available.');
        $row=hosting_records_row($id,$client,true);$journal=hosting_records_journal($row);
        $input=hosting_records_json(['revision'=>$revision,'command'=>$command,'arguments'=>$arguments]);
        $old=one('SELECT * FROM '.table('hosting_record_events').' WHERE id=?',[$event]);
        if ($old) {
            if ($old['account_id']!==$id || (int)$old['actor_id']!==(int)$actor['id'] || $old['input_json']!==$input) throw new InvalidArgumentException('This hosting event identity was already used.');
            return $journal;
        }
        if ($journal->revision()!==$revision) throw new RuntimeException('This hosting record changed. Reload before saving.');
        return hosting_records_append_locked($row,$event,$command,$arguments,(int)$actor['id']);
    }));
}
/** Internal atomic writer; current authorization or an exact committed result must precede it. */
function hosting_records_append_locked(array $row,string $event,string $command,array $arguments,int $actor):HostingJournal {
    if(!db()->inTransaction())throw new LogicException('Hosting event requires its owning transaction.');
    $journal=hosting_records_journal($row);$revision=$journal->revision();
    $document=json_decode($journal->json(),true);$events=$document['events'];
    $at=max(time(),$events?$events[array_key_last($events)]['at']:$document['created_at']);
    $next=$journal->append($event,$command,'staff:'.$actor,$at,$arguments);
    $input=hosting_records_json(['revision'=>$revision,'command'=>$command,'arguments'=>$arguments]);
    query('INSERT INTO '.table('hosting_record_events').' (id,account_id,revision,actor_id,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?)',[$event,$row['id'],$revision+1,$actor,$at,$input,$journal->fingerprint(),$next->fingerprint()]);
    $updated=query('UPDATE '.table('hosting_records').' SET journal_json=?,journal_sha256=?,revision=? WHERE id=? AND client_id=? AND revision=? AND journal_sha256=?',[$next->json(),$next->fingerprint(),$revision+1,$row['id'],$row['client_id'],$revision,$journal->fingerprint()]);
    if($updated->rowCount()!==1)throw new RuntimeException('This hosting record changed. Reload before saving.');
    hosting_records_audit($actor,'changed','Local account '.$row['id'].' event '.$event.' revision '.($revision+1).' '.$command);return $next;
}
function hosting_records_get(string $id,int $client):HostingJournal {
    if(db()->inTransaction())throw new LogicException('Hosting reads cannot inherit a transaction.');
    return staff_locked(function()use($id,$client):HostingJournal {
        hosting_records_owner();hosting_records_client($client,false);return hosting_records_journal(hosting_records_row($id,$client));
    });
}
