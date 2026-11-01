#!/usr/bin/env python3
"""Reproduce source-bound communication fixture corrections on one isolated branch."""
from pathlib import Path
import gzip
import hashlib
import json
import os
ROOT=Path(__file__).resolve().parents[2]
BRANCH='refs/heads/chatgpt/communication-browser-20261101'
BASE='44d00efb72363d7ebfae81180a404d60af5d45c5'
def sha(raw):return hashlib.sha256(raw).hexdigest()
def main():
    if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated communication test branch only')
    packed=b''.join((ROOT/('tools/integration/communication-browser-recipe.'+str(i)+'.gzpart')).read_bytes() for i in range(4))
    if sha(packed)!='763567d61aede648a9cc0dfc802fa0b8efa7220a9346e46d0a6f7a2a695e069b':raise SystemExit('Reviewed recipe changed')
    raw=gzip.decompress(packed)
    if len(raw)!=42460:raise SystemExit('Unexpected recipe size')
    data=json.loads(raw)
    paths={'tests/'+name+'_cases.py' for name in ['attempts','mail_templates','mail_accounts','reviews','appointments','mail_connections','mail_inbox','mail_message','mail_notices','mail_drafts','mail_submissions']}
    if data['base']!=BASE or len(data['records'])!=11 or {r['path'] for r in data['records']}!=paths:raise SystemExit('Unreviewed fixture scope')
    if set(data['additions'])!={'tests/communication_browser_fixture_provenance.py','tests/communication_browser_fixture_contract.py','tests/communication_session_installed.py'}:raise SystemExit('Unknown additions')
    if set(data['derived'])!={'tests/acceptance_artifacts_test.py','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json','docs/STANDALONE_ACCEPTANCE_REPAIRS.json','docs/CORE_BROWSER_FIXTURE_REPAIRS.json'}:raise SystemExit('Unknown derived receipt')
    facts={r['path']:{'before':r['before'],'after':r['after']} for r in data['records']}
    facts.update(data['derived'])
    facts.update({p:{'before':None,'after':v['sha256']} for p,v in data['additions'].items()})
    receipt=(json.dumps(data['communication_receipt'],indent=2)+'\n').encode()
    facts['docs/COMMUNICATION_BROWSER_FIXTURE_REPAIRS.json']={'before':None,'after':sha(receipt)}
    original_facts={p:dict(v) for p,v in facts.items()}
    installed='tests/communication_session_installed.py'
    initial=data['additions'][installed]['content']
    if sha(initial.encode())!='79ca5c6bf362de1e352111a8cce33c10183b36a9f500e2ae834fbaf2761bfe5e':raise SystemExit('Unreviewed installed fixture')
    before_login="    before_users=db('SELECT * FROM ci_users ORDER BY id')\n"
    browser_start="    with sync_playwright() as pw:\n"
    if initial.count(before_login)!=1 or initial.count(browser_start)!=1:raise SystemExit('Nonunique login-timestamp correction')
    fixed=initial.replace(before_login,'').replace(browser_start,"    before_users=db('SELECT * FROM ci_users ORDER BY id')  # Initial sign-in legitimately records last_login.\n"+browser_start)
    if sha(fixed.encode())!='5aa4a57a60867b407d3ef35588e6cd6fe8f651f222e175a4def41cd6caf78ae5':raise SystemExit('Unexpected installed correction')
    data['additions'][installed]['content']=fixed
    data['additions'][installed]['sha256']=sha(fixed.encode())
    facts[installed]['after']=sha(fixed.encode())
    current={}
    for p in facts:
        path=ROOT/p
        if path.is_symlink() or any(x.is_symlink() for x in path.parents):raise SystemExit('Symlink source refused')
        current[p]=path.read_bytes() if path.exists() else None
    if all(current[p] is not None and sha(current[p])==r['after'] for p,r in facts.items()):
        print('All19 exact corrections already recorded.');return
    if all(current[p] is not None and sha(current[p])==r['after'] for p,r in original_facts.items()):
        (ROOT/installed).write_bytes(fixed.encode())
        print('Corrected only the installed retention baseline after real initial login.');return
    for p,r in facts.items():
        if (sha(current[p]) if current[p] is not None else None)!=r['before']:raise SystemExit('Source drift before mutation: '+p)
    outputs={}
    for row in data['records']:
        text=current[row['path']].decode()
        for old,new in row['edits']:
            if old==new or text.count(old)!=1:raise SystemExit('Nonunique reviewed edit: '+row['path'])
            text=text.replace(old,new,1)
        outputs[row['path']]=text.encode()
    for p,v in data['additions'].items():outputs[p]=v['content'].encode()
    outputs['docs/COMMUNICATION_BROWSER_FIXTURE_REPAIRS.json']=receipt
    p='docs/CORE_BROWSER_FIXTURE_REPAIRS.json'
    receipt=json.loads(current[p]);attempt=next(r for r in data['records'] if r['path']=='tests/attempts_cases.py');row=receipt['files'][attempt['path']]
    if row['after_sha256']!=attempt['before']:raise SystemExit('Existing trial receipt mismatch')
    row['edits'].extend(attempt['edits']);row['after_sha256']=attempt['after'];outputs[p]=(json.dumps(receipt,indent=2)+'\n').encode()
    p='tests/acceptance_artifacts_test.py';text=current[p].decode()
    for old,new in data['harness_edits']:
        if old==new or text.count(old)!=1:raise SystemExit('Nonunique harness edit')
        text=text.replace(old,new,1)
    outputs[p]=text.encode()
    p='docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json';receipt=json.loads(current[p]);row=receipt['files']['tests/acceptance_artifacts_test.py']
    if row['after_sha256']!=sha(current['tests/acceptance_artifacts_test.py']):raise SystemExit('Earlier harness receipt mismatch')
    row['edits'].extend(data['harness_edits']);row['after_sha256']=sha(outputs['tests/acceptance_artifacts_test.py']);outputs[p]=(json.dumps(receipt,indent=2)+'\n').encode()
    p='docs/STANDALONE_ACCEPTANCE_REPAIRS.json';receipt=json.loads(current[p])
    for name in ['tests/acceptance_artifacts_test.py','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json']:
        row=receipt['files'][name]
        if row['after']!=sha(current[name]):raise SystemExit('Earlier standalone receipt mismatch')
        row['edits'].append([current[name].decode(),outputs[name].decode(),1]);row['after']=sha(outputs[name])
    outputs[p]=(json.dumps(receipt,indent=2)+'\n').encode()
    if set(outputs)!=set(facts):raise SystemExit('Unexpected output set')
    for p,v in outputs.items():
        if sha(v)!=facts[p]['after']:raise SystemExit('Reviewed output differs: '+p)
    for p,v in outputs.items():
        path=ROOT/p;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(v)
    print('Expanded19 exact test and receipt files; shipping application and main are untouched.')
if __name__=='__main__':main()
