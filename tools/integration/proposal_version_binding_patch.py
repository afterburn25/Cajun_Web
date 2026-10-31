#!/usr/bin/env python3
"""Apply the reviewed proposal-version correction on its isolated branch only."""
from pathlib import Path
import hashlib,json,os,subprocess
r=Path(__file__).resolve().parents[2]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/proposal-version-binding-20261031':raise SystemExit('Isolated proposal review branch only')
identities={
'site/app/marketing/proposals.php':['d18dfa218e2210f0df1a852da8dc318cc8cb036002de233a5562a7d3b801b6fb','b9cc44793c8b1010e2139a9a3f3d4a65a0943cfd772b6a72fdf8dad3e39614a5'],
'site/includes/layout/proposals.php':['71db3d7687193428940b5b51bd7740c5e8f075af77a4f5e09d15108f3a360413','a999eddef77aaf574bb3a4d1513b97716798827ad8e71220bcfcc044fe1f3e23'],
'site/portal/controllers/actions.php':['f26fefff5c1b37421e9f183900ebfc79d42f76e48cae7887c27151d4234a732f','c0c355e88465d6819a8f16f05ace512b82a28aef0a2c298ac87b3d3200a3dc3e'],
 'tests/proposals_native.php':['5ae9ec9a880d1df22306407f321d41345ce0dbea6b96a02589c944e03e3d748d','9f59f6cd0d44aaa5ae265ec48d260c2631a309bf3fc410e1f012769eedccea99'],
 'tests/proposals_cases.py':['1ee63bb4ff130305df35d4e86d312e066f2c1fab863406e5e8972eb8d5b8531f','d0fdf42221a4e292371f60f9e1e289782051c6973a6d2c28861498562aa32cda']}
receipt=r/'docs/PROPOSAL_VERSION_BINDING_SOURCE.json'
if receipt.exists():
 data=json.loads(receipt.read_text())
 if data['files']!=identities:raise SystemExit('Unknown proposal receipt')
 for path,hashes in identities.items():
  if hashlib.sha256((r/path).read_bytes()).hexdigest()!=hashes[1]:raise SystemExit('Recorded proposal source changed: '+path)
 print('Proposal binding source already recorded');raise SystemExit(0)
for path,hashes in identities.items():
 p=r/path
 if p.is_symlink() or hashlib.sha256(p.read_bytes()).hexdigest()!=hashes[0]:raise SystemExit('Original proposal source drift: '+path)
p=r/'site/app/marketing/proposals.php';s=p.read_text()
s=s.replace("function proposal_row(string $id): array {$row=one('SELECT * FROM '.table('proposals').' WHERE id=?',[$id]);", "function proposal_row(string $id,bool $lock=false): array {$row=one('SELECT * FROM '.table('proposals').' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);")
s=s.replace("function proposal_client_row(string $id,array $client): array {$row=proposal_row($id);", "function proposal_client_row(string $id,array $client,bool $lock=false): array {$row=proposal_row($id,$lock);")
start=s.index('/** The owning client approves the current version')
s=s[:start]+'''/** Apply only the exact immutable version the authenticated customer reviewed. */
function proposal_decide(string $id,string $kind,string $note,array $client,string $versionId='',string $contentHash=''): void {
    if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!preg_match('/^[a-f0-9]{32}$/D',$versionId)||!preg_match('/^[a-f0-9]{64}$/D',$contentHash))throw new InvalidArgumentException('Reload and review the exact proposal version before deciding.');
    if(!in_array($kind,['approve','changes'],true))throw new InvalidArgumentException('Choose approval or request changes.');
    staff_locked(function() use($id,$kind,$note,$client,$versionId,$contentHash): void {staff_transaction(function() use($id,$kind,$note,$client,$versionId,$contentHash): void {
        if(support_current()||array_key_exists('support',$_GET))throw new RuntimeException('Support sessions cannot decide proposals.');
        $current=studio_client();
        if(!proposals_ready()||!$current||(int)$current['id']!==(int)($client['id']??0)||!hash_equals((string)$current['auth_version'],(string)($client['auth_version']??'')))throw new RuntimeException('Current client authentication is required to decide this proposal.');
        $row=proposal_client_row($id,$current,true);$version=proposal_version_row($row,(int)$row['current_version']);
        if(!hash_equals((string)$version['id'],$versionId)||!hash_equals((string)$version['content_hash'],$contentHash))throw new RuntimeException('This proposal changed after you reviewed it. Reload and review the current version.');
        $pv=proposal_model_version($version);
        $verified=ProposalVersion::create($pv->id,$pv->number,$pv->deliverables,$pv->exclusions,$pv->scheduleNotes,$pv->milestones,$pv->authorId,$pv->createdAt);
        if(!hash_equals($pv->contentHash,$verified->contentHash))throw new RuntimeException('Recorded proposal content is inconsistent. Contact the studio before deciding.');
        $p=proposal_model($row);$kind=$kind==='approve'?ApprovalDecision::APPROVE:ApprovalDecision::REQUEST_CHANGES;
        $decision=new ApprovalDecision(bin2hex(random_bytes(16)),$versionId,'client:'.$current['id'],$kind,substr(trim($note),0,2000),gmdate('Y-m-d\\TH:i:s\\Z'));
        $p->decide($decision,gmdate('Y-m-d\\TH:i:s\\Z'));
        query('INSERT INTO '.table('proposal_decisions').' (id,proposal_id,version,decision,client_id,note,created_at) VALUES (?,?,?,?,?,?,?)',[$decision->id,$id,(int)$row['current_version'],$decision->kind==='approve'?'approved':'changes-requested',$current['id'],$decision->note,gmdate('Y-m-d H:i:s')]);
        query('UPDATE '.table('proposals').' SET status=?,decided_at=? WHERE id=?',[$decision->kind==='approve'?'approved':'changes-requested',gmdate('Y-m-d H:i:s'),$id]);
        proposals_event($id,$decision->kind==='approve'?'approved':'changes-requested','client',(int)$current['id'],'v'.$row['current_version']);
    });});
}
'''
p.write_text(s)
p=r/'site/portal/controllers/actions.php';s=p.read_text();old="proposal_decide(posted('proposal_id',32),posted('choice',20),posted('note',2000),$client)";assert s.count(old)==1;s=s.replace(old,"proposal_decide(posted('proposal_id',32),posted('choice',20),posted('note',2000),$client,posted('version_id',32),posted('content_hash',64))");p.write_text(s)
p=r/'site/includes/layout/proposals.php';s=p.read_text();old="name=\"proposal_id\" value=\"'.esc($row['id']).'\">'";assert s.count(old)==1;s=s.replace(old,"name=\"proposal_id\" value=\"'.esc($row['id']).'\"><input type=\"hidden\" name=\"version_id\" value=\"'.esc($current['id']).'\"><input type=\"hidden\" name=\"content_hash\" value=\"'.esc($current['content_hash']).'\">'")
assert "$current=proposal_version_row" in s;p.write_text(s)
p=r/'tests/proposals_native.php';s=p.read_text();s=s.replace('proposal_decide(', 'p_fresh_decide(')
insert='''function p_reviewed(string $id):array {$row=proposal_row($id);$v=proposal_version_row($row,(int)$row['current_version']);return [$v['id'],$v['content_hash']];}
function p_fresh_decide(string $id,string $kind,string $note,array $client):void {proposal_decide($id,$kind,$note,$client,...p_reviewed($id));}
function p_snapshot():array {$out=[];foreach(['proposals','proposal_versions','proposal_decisions','proposal_events'] as $t)$out[$t]=all('SELECT * FROM '.table($t).' ORDER BY id');return $out;}
function p_retained_denial(string $name,callable $work):void {$before=p_snapshot();p_deny($name,$work);p_ok($name.' preserves exact proposal and history',p_snapshot()===$before);}
'''
s=s.replace('function p_input(',insert+'function p_input(',1)
s=s.replace("    $client=p_client(1);p_deny('Change request needs a note'", "    $client=p_client(1);$reviewedV1=p_reviewed($id);p_deny('Change request needs a note'",1)
old="    proposal_send($id);$client=p_client(1);p_fresh_decide($id,'approve','',$client);"
new="""    proposal_send($id);$client=p_client(1);$reviewedV2=p_reviewed($id);
    p_retained_denial('Stale version-one approval cannot approve version two',fn()=>proposal_decide($id,'approve','',$client,...$reviewedV1));
    p_retained_denial('Stale change request cannot affect version two',fn()=>proposal_decide($id,'changes','Old tab changes',$client,...$reviewedV1));
    p_retained_denial('Missing reviewed identity cannot approve',fn()=>proposal_decide($id,'approve','',$client));
    p_retained_denial('Unknown choice cannot become a change request',fn()=>proposal_decide($id,'unexpected','Unexpected decision',$client,...$reviewedV2));
    p_retained_denial('Changed content fingerprint cannot approve',fn()=>proposal_decide($id,'approve','',$client,$reviewedV2[0],str_repeat('0',64)));
    $_GET['support']='invalid';p_retained_denial('Even invalid support context cannot decide',fn()=>proposal_decide($id,'approve','',$client,...$reviewedV2));unset($_GET['support']);
    $savedSession=$_SESSION;$_SESSION['client_auth']='revoked';p_retained_denial('Revoked session cannot reuse captured customer identity',fn()=>proposal_decide($id,'approve','',$client,...$reviewedV2));$_SESSION=$savedSession;
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');try{p_retained_denial('Disabled customer cannot approve from an open form',fn()=>proposal_decide($id,'approve','',$client,...$reviewedV2));}finally{query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');}
    $oldVersion=proposal_version_row(proposal_row($id),2);query('UPDATE '.table('proposal_versions').' SET schedule_notes=? WHERE id=?',['Unrecorded changed schedule',$oldVersion['id']]);try{p_retained_denial('Content tampering is rejected despite a retained hash field',fn()=>proposal_decide($id,'approve','',$client,...$reviewedV2));}finally{query('UPDATE '.table('proposal_versions').' SET schedule_notes=? WHERE id=?',[$oldVersion['schedule_notes'],$oldVersion['id']]);}
    $beforeDecision=p_snapshot();p_ok('Decision history failure rolls back decision and status',p_event_failure(fn()=>proposal_decide($id,'approve','',$client,...$reviewedV2))&&p_snapshot()===$beforeDecision);
    proposal_decide($id,'approve','',$client,...$reviewedV2);"""
assert s.count(old)==1;s=s.replace(old,new)
s=s.replace("$password='Synthetic proposal owner passphrase';$checks=[];", """$password='Synthetic proposal owner passphrase';$checks=[];$complete=false;
register_shutdown_function(static function()use(&$checks,&$complete):void {$out=__DIR__.'/../qa';if(!is_dir($out))mkdir($out,0700,true);file_put_contents($out.'/proposal-version-native.json',json_encode(['passed'=>$complete&&!in_array(false,array_column($checks,'passed'),true),'checks'=>$checks,'scope'=>'Actual disposable SQL proposal decision and exact-state preservation; no live customer operation'],JSON_PRETTY_PRINT));});""")
s=s.replace("echo 'proposals native: '","$complete=true;echo 'proposals native: '");p.write_text(s)
p=r/'tests/proposals_cases.py';s=p.read_text();old="    r=submit(alice,portal+'?proposal='+pid,'proposal_decide',{'choice':'changes','note':'   ','confirm':'1'});";new="    stale_form=form_data(form_for(r,'proposal_decide'))\n    check('Decision form binds exact version and content',len(stale_form.get('version_id',''))==32 and len(stale_form.get('content_hash',''))==64)\n"+old;assert old in s;s=s.replace(old,new,1)
old="    r=submit(alice,portal+'?proposal='+pid,'proposal_decide',{'choice':'approve','note':'','confirm':'1'});"
new="""    def decision_state():
        return {'proposal':db('SELECT * FROM ci_proposals WHERE id=?',[pid]),'versions':db('SELECT * FROM ci_proposal_versions WHERE proposal_id=? ORDER BY version',[pid]),'decisions':db('SELECT * FROM ci_proposal_decisions WHERE proposal_id=? ORDER BY version',[pid]),'events':db('SELECT * FROM ci_proposal_events WHERE proposal_id=? ORDER BY id',[pid])}
    before=decision_state()
    stale=alice.post(portal+'?proposal='+pid,data={**stale_form,'choice':'approve','note':'','confirm':'1'})
    check('Open version-one form cannot silently approve version two','changed after you reviewed' in stale.text and decision_state()==before)
    fresh_form=form_data(form_for(alice.get(portal+'?proposal='+pid),'proposal_decide'));missing={**fresh_form,'choice':'approve','note':'','confirm':'1'};missing.pop('version_id',None)
    refused=alice.post(portal+'?proposal='+pid,data=missing)
    check('Missing form version is refused without financial or proposal effects','review the exact proposal version' in refused.text and decision_state()==before)
"""+old
assert old in s;s=s.replace(old,new,1)
old="    r=bob.post(portal,data={'action':'proposal_decide','proposal_id':pid,'choice':'approve','confirm':'1','csrf':token(bob.get(portal))},allow_redirects=True);check('Other client cannot decide the proposal',db('SELECT COUNT(*) AS n FROM ci_proposal_decisions WHERE proposal_id=?',[pid])[0]['n']==1)"
new="    before=decision_state();r=bob.post(portal,data={'action':'proposal_decide','proposal_id':pid,'choice':'approve','confirm':'1','version_id':fresh_form['version_id'],'content_hash':fresh_form['content_hash'],'csrf':token(bob.get(portal))},allow_redirects=True);check('Other client cannot decide the proposal',decision_state()==before)"
assert old in s;s=s.replace(old,new);p.write_text(s)
for path,hashes in identities.items():
 if hashlib.sha256((r/path).read_bytes()).hexdigest()!=hashes[1]:raise SystemExit('Proposal correction output mismatch: '+path)
receipt.write_text(json.dumps({'base_commit':'97c8b71c1ac807ee9e71b62331f00d78386c0ac2','files':identities,'scope':'Exact reviewed-version correction for pending PR178. No main integration, deployment, payment or customer approval. Native and installed results remain separate.'},indent=2)+'\n')
subprocess.run(['git','diff','--check'],cwd=r,check=True)
print('Verified five corrected proposal source files.')
