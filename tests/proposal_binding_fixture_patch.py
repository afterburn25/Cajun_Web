"""Finalize strict failure evidence and restored test sessions; retain source receipts."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[1]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/proposal-version-binding-20261031':raise SystemExit('Isolated proposal branch only')
receipt=ROOT/'docs/PROPOSAL_VERSION_BINDING_FINAL.json'
expected={'tests/proposals_native.php':('9f59f6cd0d44aaa5ae265ec48d260c2631a309bf3fc410e1f012769eedccea99','cfcaa9034269468b1aa0541d11cc1b0ea961283f110e79f6482426bd9e97c451'),'tests/proposals_cases.py':('d0fdf42221a4e292371f60f9e1e289782051c6973a6d2c28861498562aa32cda','5cb22428086f5878e4ac4fe28c7f0151077b2b5a028a2604c66dffc785e2e48e')}
sha=lambda b:hashlib.sha256(b).hexdigest()
if not receipt.exists():
 for path,(before,after) in expected.items():
  if sha((ROOT/path).read_bytes())!=before:raise SystemExit('Fixture source drift: '+path)
 p=ROOT/'tests/proposals_native.php';s=p.read_text()
 old="function p_retained_denial(string $name,callable $work):void {$before=p_snapshot();p_deny($name,$work);p_ok($name.' preserves exact proposal and history',p_snapshot()===$before);}"
 new="""function p_retained_denial(string $name,callable $work):void {
    $messages=['Stale version-one approval cannot approve version two'=>'changed after you reviewed','Stale change request cannot affect version two'=>'changed after you reviewed','Missing reviewed identity cannot approve'=>'review the exact proposal version','Unknown choice cannot become a change request'=>'Choose approval or request changes','Changed content fingerprint cannot approve'=>'changed after you reviewed','Even invalid support context cannot decide'=>'Support sessions cannot decide','Revoked session cannot reuse captured customer identity'=>'Current client authentication','Disabled customer cannot approve from an open form'=>'Current client authentication','Content tampering is rejected despite a retained hash field'=>'content is inconsistent'];
    if(!isset($messages[$name]))throw new LogicException('Define the exact expected proposal refusal.');
    $before=p_snapshot();$session=$_SESSION;$error=null;try{$work();}catch(Throwable $e){$error=$e;}finally{$_SESSION=$session;}
    p_ok($name,$error!==null&&!($error instanceof PDOException)&&str_contains($error->getMessage(),$messages[$name]));
    p_ok($name.' preserves exact proposal and history',p_snapshot()===$before&&!db()->inTransaction());
}"""
 if s.count(old)!=1:raise SystemExit('Nonunique native fixture helper')
 p.write_text(s.replace(old,new,1))
 p=ROOT/'tests/proposals_cases.py';s=p.read_text();old='Missing form version is refused without financial or proposal effects';new='Missing form version is refused without proposal or history effects'
 if s.count(old)!=1:raise SystemExit('Nonunique HTTP assertion label')
 p.write_text(s.replace(old,new,1))
 for path,(before,after) in expected.items():
  if sha((ROOT/path).read_bytes())!=after:raise SystemExit('Fixture correction output mismatch: '+path)
 first=json.loads((ROOT/'docs/PROPOSAL_VERSION_BINDING_SOURCE.json').read_text())
 current={path:hashes[1] for path,hashes in first['files'].items()}
 for path,hashes in expected.items():current[path]=hashes[1]
 receipt.write_text(json.dumps({'base_commit':first['base_commit'],'files':current,'scope':'Current exact application and fixture identities. Initial correction receipt retained as history. No native or browser success inferred.'},indent=2)+'\n')
data=json.loads(receipt.read_text())
for path,digest in data['files'].items():
 if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Recorded current proposal source differs: '+path)
subprocess.run(['python3','tests/proposal_schema_repair.py'],cwd=ROOT,check=True)
subprocess.run(['python3','tests/proposal_session_alignment.py'],cwd=ROOT,check=True)
result=subprocess.run(['php','tests/proposals_schema_policy.php'],cwd=ROOT,capture_output=True,text=True)
qa=ROOT/'qa/proposal-binding';qa.mkdir(parents=True,exist_ok=True)
(qa/'schema-policy.json').write_text(result.stdout)
if result.returncode or result.stderr:raise SystemExit('Proposal schema policy failed: '+result.stderr)
print('Verified current proposal source and strict fixture/schema/session checks.')
