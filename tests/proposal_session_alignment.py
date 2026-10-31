"""Use current owner sessions for the proposal setup; retain exact historical receipts."""
from pathlib import Path
import hashlib,json,os
ROOT=Path(__file__).resolve().parents[1]
if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/proposal-version-binding-20261031':raise SystemExit('Isolated proposal branch only')
receipt=ROOT/'docs/PROPOSAL_SESSION_ALIGNMENT_SOURCE.json';sha=lambda raw:hashlib.sha256(raw).hexdigest()
if receipt.exists():
    for path,digest in json.loads(receipt.read_text())['files'].items():
        if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Proposal session source drift: '+path)
    print('Proposal setup session policy already recorded');raise SystemExit(0)
expected={'site/database/migrations/proposals_migrate.php':'4d83f2178e9f00abe52d774da91ea07ab8aa9193effb851fb6d1461ffd12caec','site/includes/layout/proposals.php':'a999eddef77aaf574bb3a4d1513b97716798827ad8e71220bcfcc044fe1f3e23','tests/proposals_native.php':'d2aba33d044e14b0914f1136f0e3ae191eb0033339ec79d4e834a8e570893bc0','tests/proposals_cases.py':'5cb22428086f5878e4ac4fe28c7f0151077b2b5a028a2604c66dffc785e2e48e'}
for path,digest in expected.items():
    if sha((ROOT/path).read_bytes())!=digest:raise SystemExit('Proposal session predecessor drift: '+path)
def edit(path,old,new,count=1):
    p=ROOT/path;text=p.read_text()
    if text.count(old)!=count:raise SystemExit('Proposal session hook mismatch: '+path)
    p.write_text(text.replace(old,new))
edit('site/database/migrations/proposals_migrate.php',"string $password): void", "string $password=''): void")
edit('site/database/migrations/proposals_migrate.php',"$actor=staff_owner($password);", "$actor=admin_user();\n        if(!staff_ready()||!$actor||($actor['role']??'')!=='owner'||!staff_can('staff.manage',$actor))throw new RuntimeException('An active owner session is required to prepare proposals.');")
edit('site/includes/layout/proposals.php',"proposals_migrate(posted('owner_password',72));", "proposals_migrate();")
edit('site/includes/layout/proposals.php',"admin_input('owner_password','Current owner password','','password','required autocomplete=\"current-password\"');", "echo '<p class=\"hint\">Your current owner session authorizes setup. No second password is needed.</p>';")
edit('tests/proposals_native.php',"p_deny('Setup requires owner reauthentication',fn()=>proposals_migrate('wrong'));", "require __DIR__.'/proposals_owner_native_cases.php';")
edit('tests/proposals_cases.py',"    r=submit(admin,url,'proposal_setup',{'owner_password':OWNER,'confirm':'1','csrf':'invalid'});", "    check('Proposal setup uses the current owner session without a second password',soup(r).select_one('input[type=password]') is None)\n    r=submit(admin,url,'proposal_setup',{'confirm':'1','csrf':'invalid'});")
edit('tests/proposals_cases.py',"    r=submit(admin,url,'proposal_setup',{'owner_password':'wrong','confirm':'1'});check('Proposal setup reauthenticates owner','password' in r.text.lower() and not db(\"SELECT value FROM ci_settings WHERE name='proposals_schema_version'\"))", "    r=submit(admin,url,'proposal_setup',{'confirm':''});check('Proposal setup requires explicit backup confirmation','Confirm that you saved' in r.text and not db(\"SELECT value FROM ci_settings WHERE name='proposals_schema_version'\"))")
edit('tests/proposals_cases.py',"r=submit(admin,url,'proposal_setup',{'owner_password':OWNER,'confirm':'1'});", "r=submit(admin,url,'proposal_setup',{'confirm':'1'});")
edit('tests/proposals_cases.py',"'$2495.88'", "'$2,495.88'",2)
identities={path:sha((ROOT/path).read_bytes()) for path in expected}
identities['tests/proposals_owner_native_cases.php']=sha((ROOT/'tests/proposals_owner_native_cases.php').read_bytes())
for file in ['docs/PROPOSAL_VERSION_BINDING_FINAL.json','docs/PROPOSAL_SCHEMA_REPAIR.json']:
    p=ROOT/file;data=json.loads(p.read_text())
    for path,digest in identities.items():
        if path in data['files']:data['files'][path]=digest
    p.write_text(json.dumps(data,indent=2)+'\n')
receipt.write_text(json.dumps({'predecessors':expected,'files':identities,'scope':'Owner-requested session-only proposal setup; retain first login, CSRF and explicit backup consent. Actual disabled/revoked/expired/delegated/anonymous/support denials are tested against disposable SQL. Display tests match the existing thousands-separated currency formatter.'},indent=2)+'\n')
print('Recorded session-only proposal setup and retained authority tests.')
