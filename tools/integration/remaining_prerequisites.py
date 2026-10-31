#!/usr/bin/env python3
"""Reviewed normal merges of detached models and retained source documentation."""
from pathlib import Path
import hashlib,json,os,subprocess,tempfile,sys
ROOT=Path(__file__).resolve().parents[2]
MAIN='5f8efc3db5a8e3c24a1669e7ba1ca25cd9767443'
BRANCH='refs/heads/chatgpt/remaining-prerequisites-20261030'
EXPECTED_TREE='bc8f170b5bfb19d07643240d60884dad33a84ebc'
VPS_WORKFLOW='9e4f133c46ebc779e235a3172b2e07f45144efd6'
SOURCES=[(144,'fcf865891165510aae25f064346809c45eeb3cd0'),(154,'3bdf17f2358402186cdbaf5631ebba9bb26ba8c7'),(155,'8b257538af84aef6cec9c62d83619c53fe0ecde7'),(256,'fb39599806c6c135683f42ac66e6c02d7ea75f09')]
CONTROLS=['tools/integration/remaining_prerequisites.py','.github/workflows/remaining-prerequisites.yml','docs/REMAINING_PREREQUISITES_RECEIPT.json']
SHARED={'AGENTS.md','README.md','CHANGELOG.md','PROJECT_STATUS.md','SESSION_HANDOFF.md','docs/AGENT_COORDINATION.md','docs/ROADMAP.md','docs/SESSION_LOG.md','docs/HOSTING_AND_BILLING_ROADMAP.md'}
GUIDE='''# Remaining prerequisite source consolidation

This group records original histories from PRs #144, #154, #155 and #256. Three are retained 20i source/evidence documentation; the fourth is the detached VPS lifecycle model and its synthetic tests.

Current main instructions, release status, operating guidance and all application files are preserved. Historical conflicting prose remains verbatim in `docs/evidence/remaining-prerequisite-history.json` as source deltas, and in the original merge parents. It is historical evidence, not a new claim that a feature or external operation works.

The VPS model keeps uncertain attempts and their provider references until explicit reconciliation. Its evidence objects are supplied by an adapter; the pure model cannot authenticate remote outcomes. This merge adds no database storage, provider transport, SSH, order, charge, live VPS action or admin screen. Application-side integration and acceptance remain separate.

Review is owner-authorized ChatGPT self-review, not an independent audit. Both PHP 8.2/8.4 default/minimal complete tests, documentation, unchanged shipping-tree checks and exact clean-source build acceptance are required before main integration. No published or delivered installation archive is replaced.
'''
def blob(raw):return hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest()
def git(*args,check=True,env=None):
    p=subprocess.run(['git',*args],cwd=ROOT,capture_output=True,env=env,timeout=120)
    if check and p.returncode:raise RuntimeError('Git '+args[0]+' failed: '+p.stderr.decode()[:1000])
    return p
def text(*args):return git(*args).stdout.decode().strip()
def verify():
    for _,sha in SOURCES:git('merge-base','--is-ancestor',sha,'HEAD')
    git('merge-base','--is-ancestor',MAIN,'HEAD')
    git('diff','--exit-code',MAIN,'HEAD','--','site','VERSION','tools/build.py','tools/structure','START-HERE.txt','AGENTS.md','README.md')
    with tempfile.TemporaryDirectory() as d:
        env={**os.environ,'GIT_INDEX_FILE':str(Path(d)/'index')};git('read-tree','HEAD',env=env)
        git('update-index','--force-remove','--',*CONTROLS,env=env)
        actual=git('write-tree',env=env).stdout.decode().strip()
    if actual!=EXPECTED_TREE:raise RuntimeError('Reviewed source-tree mismatch: '+actual)
    if text('status','--porcelain','--untracked-files=no'):raise RuntimeError('Dirty tracked source')
    print('All four original source ancestors and exact reviewed tree verified:',actual)
def main():
    if '--verify' in sys.argv:verify();return
    if os.environ.get('GITHUB_REF')!=BRANCH:raise RuntimeError('Isolated reviewed merge branch only')
    receipt=ROOT/CONTROLS[2]
    if receipt.exists():verify();return
    if set(text('diff','--name-only',MAIN,'HEAD').splitlines())!=set(CONTROLS[:2]+['.github/workflows/vps.yml']):raise RuntimeError('Unreviewed starting source')
    workflow=(ROOT/'.github/workflows/vps.yml').read_bytes()
    if blob(workflow)!=VPS_WORKFLOW:raise RuntimeError('Reviewed VPS workflow must be recorded via the authorized connector first')
    records=[];history=[]
    for number,sha in SOURCES:
        before=text('rev-parse','HEAD');base=text('merge-base','HEAD',sha)
        changed=text('diff','--name-only',base,sha).splitlines()
        if not all(p.endswith('.md') or p.startswith(('docs/evidence/','foundation/vps/','tests/vps/')) or p=='.github/workflows/vps.yml' for p in changed):raise RuntimeError('Source scope changed')
        p=git('merge','--no-ff','--no-commit',sha,check=False)
        if p.returncode not in (0,1):raise RuntimeError('Merge machinery failed')
        conflicts=text('diff','--name-only','--diff-filter=U').splitlines();res=[]
        for name in sorted(set(changed)&SHARED | set(conflicts)):
            if name=='.github/workflows/vps.yml':
                ours=git('show',':2:'+name).stdout;theirs=git('show',':3:'+name).stdout.decode()
                expected=theirs.replace('group: vps-${{ github.ref }}-${{ github.sha }}','group: vps-${{ github.ref }}').replace('cancel-in-progress: true',"cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}").replace('actions/upload-artifact@v4','actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02').encode()
                if ours!=expected or ours!=workflow:raise RuntimeError('Unreviewed workflow conflict')
                data=ours;reason='Retain all original VPS test commands with main-exempt branch-scoped concurrency and pinned artifact action.'
            else:
                if not name.endswith('.md'):raise RuntimeError('Unreviewed conflict: '+name)
                current=git('show',before+':'+name).stdout;source=git('show',sha+':'+name).stdout
                history.append({'pr':number,'source':sha,'base':base,'path':name,'retained_current_blob':blob(current),'source_blob':blob(source),'historical_source_delta':git('diff','--no-ext-diff','--no-renames',base,sha,'--',name).stdout.decode()})
                data=current;reason='Preserve newer main guidance; retain the exact incoming historical delta in the evidence JSON and original merge ancestor.'
            path=ROOT/name
            if path.is_symlink() or any(x.is_symlink() for x in path.parents):raise RuntimeError('Symlink resolution refused')
            path.write_bytes(data);git('add','--',name);res.append({'path':name,'reason':reason})
        if text('diff','--name-only','--diff-filter=U'):raise RuntimeError('Unresolved merge')
        git('diff','--check');git('commit','-m','Merge remaining prerequisite source PR #'+str(number))
        records.append({'pr':number,'source':sha,'merge_commit':text('rev-parse','HEAD'),'resolutions':res})
    p=ROOT/'docs/evidence/remaining-prerequisite-history.json'
    p.write_text(json.dumps({'schema':1,'baseline_main':MAIN,'scope':'Dated source text, not current operating instructions. No historical feature/release claim is promoted to current evidence.','source_notes':history},indent=2)+'\n')
    (ROOT/'docs/REMAINING_PREREQUISITES.md').write_text(GUIDE)
    git('add','--','docs');git('commit','-m','Preserve superseded historical text separately from current operating guidance');verify()
    receipt.write_text(json.dumps({'schema':1,'main':MAIN,'reviewed_tree':EXPECTED_TREE,'sources':records,'scope':'Nonshipping source/model history only; exact-head supported PHP and clean build results required before main merge.'},indent=2)+'\n')
    git('add','--',CONTROLS[2]);git('commit','-m','Record four prerequisite source merge identities');verify()
if __name__=='__main__':main()
