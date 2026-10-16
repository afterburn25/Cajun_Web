<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/proposals/Proposal.php';
require_once CWP_CODE_ROOT.'/includes/foundation/proposals/ProposalVersion.php';
require_once CWP_CODE_ROOT.'/includes/foundation/proposals/ApprovalDecision.php';
require_once CWP_CODE_ROOT.'/database/schema/proposals_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/proposals_migrate.php';
use CajunWebPro\Foundation\Proposals\{Proposal,ProposalVersion,ApprovalDecision};

function proposals_ready(): bool {return staff_ready()&&studio_ready()&&setting('proposals_schema_version')==='1';}
function proposals_require(): array {if(support_current())throw new RuntimeException('Leave support before managing proposals.');if(!proposals_ready())throw new RuntimeException('An owner must enable proposals first.');return staff_require('clients.manage');}
function proposals_event(string $proposal,string $event,string $actorType,int $actorId,string $detail=''): void {query('INSERT INTO '.table('proposal_events').' (proposal_id,event,actor_type,actor_id,detail,created_at) VALUES (?,?,?,?,?,?)',[$proposal,$event,$actorType,$actorId,substr($detail,0,500),gmdate('Y-m-d H:i:s')]);}

/** Validate and normalize proposal form data into the model's structure. Milestone lines are "Label | amount". */
function proposal_input(array $data): array {
    $title=trim((string)($data['title']??''));if($title===''||strlen($title)>160)throw new InvalidArgumentException('Enter a proposal title under 160 characters.');
    $lines=fn(string $raw,int $max,int $limit): array => array_slice(array_values(array_filter(array_map(fn($l)=>substr(trim($l),0,$max),preg_split('/\R/',$raw)),fn($l)=>$l!=='')),0,$limit);
    $deliverables=$lines((string)($data['deliverables']??''),300,40);if($deliverables===[])throw new InvalidArgumentException('List at least one deliverable.');
    $exclusions=$lines((string)($data['exclusions']??''),300,40);
    $schedule=substr(trim((string)($data['schedule_notes']??'')),0,6000);if($schedule==='')throw new InvalidArgumentException('Describe the delivery schedule.');
    $milestones=[];foreach(preg_split('/\R/',(string)($data['milestones']??'')) as $line){$line=trim($line);if($line==='')continue;
        if(!preg_match('/^(.{1,200}?)\s*\|\s*([0-9]+(?:\.[0-9]{1,2})?)$/',$line,$m))throw new InvalidArgumentException('Each milestone line needs "Label | amount" without commas or currency symbols.');
        $cents=gcents($m[2]);if($cents===null||$cents<0)throw new InvalidArgumentException('Milestone amounts must be valid non-negative USD amounts.');$milestones[]=['label'=>trim($m[1]),'amountCents'=>$cents];}
    if(count($milestones)>40)throw new InvalidArgumentException('Keep milestones under 40 rows.');
    return ['title'=>$title,'deliverables'=>$deliverables,'exclusions'=>$exclusions,'schedule_notes'=>$schedule,'milestones'=>$milestones];
}
/** The foundation's canonical hash of the exact content a client will review. */
function proposal_content_hash(array $input): string {return hash('sha256',json_encode(['deliverables'=>array_values($input['deliverables']),'exclusions'=>array_values($input['exclusions']),'scheduleNotes'=>$input['schedule_notes'],'milestones'=>$input['milestones']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}

function proposal_row(string $id): array {$row=one('SELECT * FROM '.table('proposals').' WHERE id=?',[$id]);if(!$row)throw new InvalidArgumentException('Proposal not found.');return $row;}
function proposal_version_row(array $row,int $version): array {$v=one('SELECT * FROM '.table('proposal_versions').' WHERE proposal_id=? AND version=?',[$row['id'],$version]);if(!$v)throw new RuntimeException('Recorded proposal version is missing.');return $v;}
function proposal_versions(string $id): array {return all('SELECT * FROM '.table('proposal_versions').' WHERE proposal_id=? ORDER BY version',[$id]);}
function proposal_history(string $id): array {return all('SELECT * FROM '.table('proposal_events').' WHERE proposal_id=? ORDER BY id DESC LIMIT 200',[$id]);}
function proposals_all(): array {return all('SELECT p.*,c.name AS client_name FROM '.table('proposals').' p JOIN '.table('studio_clients').' c ON c.id=p.client_id ORDER BY p.created_at DESC LIMIT 200');}
/** Proposals a signed-in client may review — drafts and withdrawals stay staff-side. */
function proposals_for_client(int $clientId): array {return proposals_ready()?all("SELECT p.* FROM ".table('proposals')." p WHERE p.client_id=? AND p.status IN ('sent','changes-requested','approved') ORDER BY p.created_at DESC LIMIT 50",[$clientId]):[];}
function proposal_client_row(string $id,array $client): array {$row=proposal_row($id);if((int)$row['client_id']!==(int)$client['id'])throw new InvalidArgumentException('Proposal not found.');return $row;}

function proposal_model_version(array $v): ProposalVersion {
    return new ProposalVersion($v['id'],(int)$v['version'],json_decode($v['deliverables'],true,512,JSON_THROW_ON_ERROR),json_decode($v['exclusions'],true,512,JSON_THROW_ON_ERROR)?:[],(string)$v['schedule_notes'],json_decode($v['milestones'],true,512,JSON_THROW_ON_ERROR)?:[],'staff:'.$v['created_by'],$v['created_at'],$v['content_hash']);
}
/** Rebuild the contract object by replaying the durable record — the model enforces every state rule. */
function proposal_model(array $row): Proposal {
    $p=new Proposal($row['id'],(string)$row['client_id'],$row['title'],'staff:'.$row['created_by'],$row['created_at']);
    $versions=[];foreach(proposal_versions($row['id']) as $v)$versions[(int)$v['version']]=proposal_model_version($v);
    $decisions=[];foreach(all('SELECT * FROM '.table('proposal_decisions').' WHERE proposal_id=? ORDER BY version',[$row['id']]) as $d)$decisions[]=$d;
    $next=1;
    foreach(all('SELECT * FROM '.table('proposal_events').' WHERE proposal_id=? ORDER BY id',[$row['id']]) as $e){
        if($e['event']==='version-added')$p->addVersion($versions[$next++],'staff:'.$e['actor_id'],$e['created_at']);
        elseif($e['event']==='revised')$p->revise($versions[$next++],'staff:'.$e['actor_id'],$e['created_at']);
        elseif($e['event']==='sent')$p->send($e['actor_type'].':'.$e['actor_id'],$e['created_at']);
        elseif(in_array($e['event'],['approved','changes-requested'],true)){$d=array_shift($decisions);$p->decide(new ApprovalDecision($d['id'],$versions[$d['version']]->id,'client:'.$d['client_id'],$d['decision']==='approved'?ApprovalDecision::APPROVE:ApprovalDecision::REQUEST_CHANGES,$d['note'],$e['created_at']),$e['created_at']);}
        elseif($e['event']==='withdrawn')$p->withdraw('staff:'.$e['actor_id'],$e['created_at']);
    }
    return $p;
}
function proposal_persist_version(array $row,array $input,int $version,int $actorId,string $vid): void {
    query('INSERT INTO '.table('proposal_versions').' (id,proposal_id,version,title,deliverables,exclusions,schedule_notes,milestones,content_hash,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$vid,$row['id'],$version,$input['title'],json_encode($input['deliverables'],JSON_UNESCAPED_SLASHES),json_encode($input['exclusions'],JSON_UNESCAPED_SLASHES),$input['schedule_notes'],json_encode($input['milestones'],JSON_UNESCAPED_SLASHES),proposal_content_hash($input),$actorId,gmdate('Y-m-d H:i:s')]);
}
/** Create a draft proposal with version 1 under exact-request replay protection. */
function proposal_create(int $clientId,array $data,string $key): string {
    $actor=proposals_require();$input=proposal_input($data);
    $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$clientId]);if(!$client)throw new InvalidArgumentException('Choose a client workspace account.');
    if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Reload the form before creating a proposal.');
    $request=hash('sha256',json_encode([$clientId,$input],JSON_THROW_ON_ERROR));
    return staff_locked(function() use($actor,$clientId,$input,$key,$request): string {
        $old=one('SELECT * FROM '.table('proposals').' WHERE request_key=?',[$key]);
        if($old){if((int)$old['created_by']!==(int)$actor['id']||!hash_equals($old['request_hash']??'',$request))throw new RuntimeException('This proposal request was already used with different details.');return $old['id'];}
        $id=bin2hex(random_bytes(16));
        staff_transaction(function() use($id,$clientId,$input,$key,$request,$actor): void {
            $now=gmdate('Y-m-d H:i:s');
            query('INSERT INTO '.table('proposals').' (id,client_id,title,status,current_version,request_key,request_hash,created_by,created_at) VALUES (?,?,?,?,1,?,?,?,?)',[$id,$clientId,$input['title'],'draft',$key,$request,$actor['id'],$now]);
            proposal_persist_version(['id'=>$id],$input,1,(int)$actor['id'],bin2hex(random_bytes(16)));
            proposals_event($id,'version-added','staff',(int)$actor['id'],'v1');
        });
        return $id;
    });
}
/** Drafts gain a new version; change-requested proposals revise and return to draft. */
function proposal_revise(string $id,array $data): void {
    $actor=proposals_require();$input=proposal_input($data);
    staff_locked(function() use($id,$input,$actor): void {
        staff_transaction(function() use($id,$input,$actor): void {
            $row=proposal_row($id);$p=proposal_model($row);$version=(int)$row['current_version']+1;
            $vid=bin2hex(random_bytes(16));
            $pv=ProposalVersion::create($vid,$version,$input['deliverables'],$input['exclusions'],$input['schedule_notes'],$input['milestones'],'staff:'.$actor['id'],gmdate('Y-m-d\TH:i:s\Z'));
            $draft=$p->state()===Proposal::DRAFT;
            if($draft)$p->addVersion($pv,'staff:'.$actor['id'],gmdate('Y-m-d\TH:i:s\Z'));else $p->revise($pv,'staff:'.$actor['id'],gmdate('Y-m-d\TH:i:s\Z'));
            proposal_persist_version($row,$input,$version,(int)$actor['id'],$vid);
            query('UPDATE '.table('proposals').' SET title=?,current_version=?,status=?,decided_at=NULL WHERE id=?',[$input['title'],$version,'draft',$id]);
            proposals_event($id,$draft?'version-added':'revised','staff',(int)$actor['id'],'v'.$version);
        });
    });
}
function proposal_send(string $id): void {
    $actor=proposals_require();
    staff_locked(function() use($id,$actor): void {staff_transaction(function() use($id,$actor): void {
        $row=proposal_row($id);proposal_model($row)->send('staff:'.$actor['id'],gmdate('Y-m-d\TH:i:s\Z'));
        query('UPDATE '.table('proposals')." SET status='sent',sent_at=? WHERE id=?",[gmdate('Y-m-d H:i:s'),$id]);
        proposals_event($id,'sent','staff',(int)$actor['id'],'v'.$row['current_version']);
    });});
}
function proposal_withdraw(string $id): void {
    $actor=proposals_require();
    staff_locked(function() use($id,$actor): void {staff_transaction(function() use($id,$actor): void {
        proposal_model(proposal_row($id))->withdraw('staff:'.$actor['id'],gmdate('Y-m-d\TH:i:s\Z'));
        query('UPDATE '.table('proposals')." SET status='withdrawn' WHERE id=?",[$id]);
        proposals_event($id,'withdrawn','staff',(int)$actor['id']);
    });});
}
/** The owning client approves the current version or requests changes — decisions pin the exact version id so superseded terms cannot be approved. */
function proposal_decide(string $id,string $kind,string $note,array $client): void {
    if(support_current())throw new RuntimeException('Support sessions cannot decide proposals.');
    staff_locked(function() use($id,$kind,$note,$client): void {staff_transaction(function() use($id,$kind,$note,$client): void {
        $row=proposal_client_row($id,$client);$p=proposal_model($row);$version=proposal_version_row($row,(int)$row['current_version']);
        $kind=$kind==='approve'?ApprovalDecision::APPROVE:ApprovalDecision::REQUEST_CHANGES;
        $decision=new ApprovalDecision(bin2hex(random_bytes(16)),$version['id'],'client:'.$client['id'],$kind,substr(trim($note),0,2000),gmdate('Y-m-d\TH:i:s\Z'));
        $p->decide($decision,gmdate('Y-m-d\TH:i:s\Z'));
        query('INSERT INTO '.table('proposal_decisions').' (id,proposal_id,version,decision,client_id,note,created_at) VALUES (?,?,?,?,?,?,?)',[$decision->id,$id,(int)$row['current_version'],$decision->kind==='approve'?'approved':'changes-requested',$client['id'],$decision->note,gmdate('Y-m-d H:i:s')]);
        query('UPDATE '.table('proposals').' SET status=?,decided_at=? WHERE id=?',[$decision->kind==='approve'?'approved':'changes-requested',gmdate('Y-m-d H:i:s'),$id]);
        proposals_event($id,$decision->kind==='approve'?'approved':'changes-requested','client',(int)$client['id'],'v'.$row['current_version']);
    });});
}
