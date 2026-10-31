"""Finish the pending proposal's schema validation; preserve all prior receipts."""
from pathlib import Path
import hashlib,json,os
ROOT=Path(__file__).resolve().parents[1]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/proposal-version-binding-20261031':raise SystemExit('Isolated proposal branch only')
receipt=ROOT/'docs/PROPOSAL_SCHEMA_REPAIR.json'
sha=lambda b:hashlib.sha256(b).hexdigest()
if receipt.exists():
    for path,digest in json.loads(receipt.read_text())['files'].items():
        if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Reviewed proposal schema repair drift: '+path)
    print('Proposal schema repair already recorded');raise SystemExit(0)
expected={'site/app/marketing/proposals.php':'b9cc44793c8b1010e2139a9a3f3d4a65a0943cfd772b6a72fdf8dad3e39614a5','tests/proposals_native.php':'cfcaa9034269468b1aa0541d11cc1b0ea961283f110e79f6482426bd9e97c451'}
for path,digest in expected.items():
    if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Proposal input drift: '+path)
def replace(path,old,new):
    p=ROOT/path;s=p.read_text()
    if s.count(old)!=1:raise SystemExit('Nonunique proposal schema correction: '+path)
    p.write_text(s.replace(old,new,1))
replace('site/app/marketing/proposals.php',
    "$row=proposal_row($id);$p=proposal_model($row);$version=(int)$row['current_version']+1;",
    "$row=proposal_row($id);$p=proposal_model($row);$version=(int)$row['current_version']+1;\n            if(one('SELECT id FROM '.table('proposal_versions').' WHERE proposal_id=? AND content_hash=?',[$id,proposal_content_hash($input)]))throw new InvalidArgumentException('Revise the deliverables, exclusions, schedule or milestones before adding a new proposal version.');")
replace('tests/proposals_native.php',
    "proposals_migrate($password);p_ok('Repeated setup is safe',proposals_ready());",
    "proposals_migrate($password);p_ok('Repeated setup is safe',proposals_ready());\n    require __DIR__.'/proposals_schema_native_cases.php';")
replace('tests/proposals_native.php',
    "proposal_revise($id,p_input('Revised proposal'));p_ok('Revision returns to draft at v2'",
    "$unchanged=p_snapshot();$duplicateRefused=false;try{proposal_revise($id,p_input('Only a different title'));}catch(InvalidArgumentException $e){$duplicateRefused=str_contains($e->getMessage(),'Revise the deliverables');}p_ok('Unchanged terms produce a clear refusal without duplicate version or writes',$duplicateRefused&&p_snapshot()===$unchanged);\n    proposal_revise($id,array_replace(p_input('Revised proposal'),['schedule_notes'=>'Revised schedule after the client request.']));p_ok('Revision returns to draft at v2'")
paths=list(expected)+['site/database/migrations/proposals_migrate.php','site/database/schema/proposals_validate_schema.php','tests/proposals_schema_native_cases.php','tests/proposals_schema_policy.php']
identities={path:sha((ROOT/path).read_bytes()) for path in paths}
current=ROOT/'docs/PROPOSAL_VERSION_BINDING_FINAL.json';data=json.loads(current.read_text());data['files'].update(identities)
current.write_text(json.dumps(data,indent=2)+'\n')
receipt.write_text(json.dumps({'source_commit':'596c71fb75259e9c27555572bbd8ad0ccb15435d','files':identities,'scope':'Validate actual proposal columns and ordinary/unique indexes; refuse lost historical schema and provide an explicit unchanged-terms error. Prior source identities remain in original receipts. Native/installed acceptance still required.'},indent=2)+'\n')
print('Recorded proposal schema correction and retained-history tests.')
