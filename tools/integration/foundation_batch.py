#!/usr/bin/env python3
"""Exact, reviewed normal merges of nonshipping foundation sources only."""
from pathlib import Path
import difflib,json,os,subprocess,tempfile
ROOT=Path(__file__).resolve().parents[2]
MAIN='551dd0dc1007711215fd83467ce814fe2f0f895e'
EXPECTED_TREE='920be3f03da10b767711b4cb4cc27393cced33fb'
BRANCH='refs/heads/chatgpt/foundation-merge-group-20261030'
CONTROLS=['tools/integration/foundation_batch.py','.github/workflows/foundation-batch.yml','docs/FOUNDATION_MERGE_BATCH.json']
SOURCES=[(162,'e71b353c1ac02a9f0f0a7e3fba6f3bd9c8b7f13b'),(163,'ae6dda042ce8ed1ad97c00a2b0593edf3e646348'),(164,'e6999fcc0e3a73e30defe613cde23caa34059cad'),(165,'494170072885f3a1342c6d2652f7c445c99eb683'),(166,'6d7d4daeb547aafeb4f354e35e3465f124d8080a'),(167,'02555724470045caa95c97d2bd4e9b83b949f438'),(168,'a791eb17f9e5b74f00726c38fdced93716614361'),(169,'bb9d25a0a5a8e5a74e8427997acc065320e0345e'),(172,'e2b29a0514584b28132470ab7b85a9ba60ff6916')]
AREAS=['backups','pipeline','proposals','notifications','revisions','support-access','comparisons','service-plans','billing-calendar']
def git(*args,check=True,env=None):
    p=subprocess.run(['git',*args],cwd=ROOT,capture_output=True,env=env,timeout=120)
    if check and p.returncode:raise RuntimeError('Git '+args[0]+' failed: '+p.stderr.decode()[:1000])
    return p
def text(*args):return git(*args).stdout.decode().strip()
def verify():
    for number,sha in SOURCES:git('merge-base','--is-ancestor',sha,'HEAD')
    git('merge-base','--is-ancestor',MAIN,'HEAD')
    git('diff','--exit-code',MAIN,'HEAD','--','site','VERSION','tools/build.py','tools/structure','START-HERE.txt')
    with tempfile.TemporaryDirectory() as d:
        env={**os.environ,'GIT_INDEX_FILE':str(Path(d)/'index')}
        git('read-tree','HEAD',env=env)
        git('update-index','--force-remove','--',*CONTROLS,env=env)
        actual=git('write-tree',env=env).stdout.decode().strip()
    if actual!=EXPECTED_TREE:raise RuntimeError('Reviewed whole-tree identity mismatch: '+actual)
    if text('status','--porcelain','--untracked-files=no'):raise RuntimeError('Tracked source is dirty')
    print('Verified all nine original source ancestries and exact reviewed tree',actual)
def main():
    if '--verify' in __import__('sys').argv:verify();return
    if os.environ.get('GITHUB_REF')!=BRANCH:raise RuntimeError('Isolated merge-review branch only')
    receipt=ROOT/CONTROLS[2]
    if receipt.exists():verify();return
    if set(text('diff','--name-only',MAIN,'HEAD').splitlines())!=set(CONTROLS[:2]):raise RuntimeError('Unexpected starting candidate')
    if text('ls-remote','origin','refs/heads/main').split()[0]!=MAIN:raise RuntimeError('Main advanced; reconcile explicitly')
    records=[]
    for number,sha in SOURCES:
        base=text('merge-base','HEAD',sha)
        p=git('merge','--no-ff','--no-commit',sha,check=False)
        if p.returncode not in (0,1):raise RuntimeError('Merge machinery failed')
        conflicts=text('diff','--name-only','--diff-filter=U').splitlines();resolutions=[]
        for name in conflicts:
            ours=git('show',':2:'+name).stdout;theirs=git('show',':3:'+name).stdout
            original=git('show',base+':'+name).stdout
            if name=='tools/build.py':
                old=b"allowed={'.github','docs','tests','tools','brand','foundation','site'}"
                new=b"allowed={'.github','docs','tests','tools','brand','foundation','site','ops'}"
                if original.count(old)!=1 or original.replace(old,new)!=theirs or new not in ours:raise RuntimeError('Unexpected builder change')
                merged=ours;reason='Source only adds ops area already present in current builder; preserve complete current implementation.'
            elif name in {'CHANGELOG.md','SESSION_HANDOFF.md','docs/SESSION_LOG.md'}:
                a=original.decode().splitlines(keepends=True);b=theirs.decode().splitlines(keepends=True);blocks=[]
                for tag,i,j,k,l in difflib.SequenceMatcher(None,a,b,autojunk=False).get_opcodes():
                    if tag=='equal':continue
                    if tag!='insert':raise RuntimeError('Historical source is not append-only')
                    blocks.append(''.join(b[k:l]))
                if not blocks:raise RuntimeError('No source history additions')
                merged=ours+('\n\n## Historical source entries retained while integrating PR #'+str(number)+'\n\n'+''.join(blocks)).encode()
                reason='Preserve all current bytes and each incoming historical addition under its PR heading.'
            else:raise RuntimeError('Unreviewed conflict: '+name)
            (ROOT/name).write_bytes(merged);git('add','--',name);resolutions.append({'path':name,'reason':reason})
        git('diff','--check');git('commit','-m','Merge reviewed nonshipping foundation PR #'+str(number))
        records.append({'pr':number,'source':sha,'merge_commit':text('rev-parse','HEAD'),'resolutions':resolutions})
    for area in AREAS:
        p=ROOT/'.github/workflows'/f'{area}.yml';raw=p.read_bytes()
        if raw.count(b'  cancel-in-progress: true')!=1:raise RuntimeError('Unexpected pure workflow cancellation setting')
        p.write_bytes(raw.replace(b'  cancel-in-progress: true',b"  cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}"))
    git('add','--','.github/workflows');git('commit','-m','Keep main evidence while superseding obsolete development contract runs')
    verify()
    receipt.write_text(json.dumps({'schema':1,'baseline_main':MAIN,'reviewed_tree_excluding_controls':EXPECTED_TREE,'source_merges':records,'scope':'Nonshipping pure models, source continuity and contract CI. No new application feature is enabled and no release assets change.','review':'Owner-authorized ChatGPT self-review; not independent audit approval.','verification':'Exact-source PHP8.2/8.4, documentation and clean builder checks required before main merge.'},indent=2)+'\n')
    git('add','--',CONTROLS[2]);git('commit','-m','Record exact foundation merge identities and verification scope');verify()
if __name__=='__main__':main()
