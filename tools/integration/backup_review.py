#!/usr/bin/env python3
"""Reproduce a locally reviewed normal merge; never update a remote ref or deploy."""
from pathlib import Path
import argparse,base64,hashlib,json,os,subprocess,urllib.request
ROOT=Path(__file__).resolve().parents[2]
MAIN='1f3e6dc7c424af74d407220fe71cf3f045ab2499'
INCOMING='24da7935ce591d94797e91e794804351dcbfa3a3'
TREE='4bff5305dc8c704b90e669bc02c6bfc61278f9b3'
def git(*args,cwd=ROOT):return subprocess.check_output(['git',*args],cwd=cwd)
def digest(raw):return hashlib.sha256(raw).hexdigest()
def request(path,payload=None):
 token=os.environ['SOURCE_TOKEN']
 data=None if payload is None else json.dumps(payload).encode()
 r=urllib.request.Request('https://api.github.com/repos/afterburn25/Cajun_Web/'+path,data=data,headers={'Authorization':'Bearer '+token,'Accept':'application/vnd.github+json','User-Agent':'CWP-backup-review','Content-Type':'application/json'},method='GET' if payload is None else 'POST')
 with urllib.request.urlopen(r,timeout=60) as response:return json.load(response)
def main():
 parser=argparse.ArgumentParser();parser.add_argument('--publish-object',action='store_true');args=parser.parse_args()
 if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/backups-review-driver-20261030':raise SystemExit('Isolated review driver only')
 a=json.loads((ROOT/'tools/integration/backup-review-runtime.json').read_text());b=json.loads((ROOT/'tools/integration/backup-review-tests.json').read_text())
 if (a['main'],a['incoming'],a['expected_tree'])!=(MAIN,INCOMING,TREE):raise SystemExit('Unexpected reviewed source')
 records=a['records']+b['records']
 if len(records)!=29 or len({r['path'] for r in records})!=29:raise SystemExit('Unexpected recipe scope')
 dest=Path(os.environ['RUNNER_TEMP'])/'cwp-backup-source'
 if dest.exists():raise SystemExit('Stale review workspace refused')
 subprocess.run(['git','worktree','add','--detach',str(dest),MAIN],cwd=ROOT,check=True)
 for row in records:
  path=Path(row['path'])
  if path.is_absolute() or '..' in path.parts or '\\' in row['path'] or not (row['path'].startswith(('site/','tests/','docs/','tools/','.github/workflows/')) or row['path']=='CHANGELOG.md'):raise SystemExit('Unsafe source path')
  if 'content' in row:raw=row['content'].encode()
  else:
   if row['base_ref'] not in (MAIN,INCOMING):raise SystemExit('Unreviewed source ref')
   raw=git('show',row['base_ref']+':'+row['path'])
   if digest(raw)!=row['before_sha256']:raise SystemExit('Before-source mismatch: '+row['path'])
   previous=len(raw)+1
   for start,end,text in reversed(row['edits']):
    if not 0<=start<=end<previous:raise SystemExit('Overlapping source edits')
    raw=raw[:start]+text.encode()+raw[end:];previous=start
  if digest(raw)!=row['after_sha256']:raise SystemExit('After-source mismatch: '+row['path'])
  target=dest/path
  if target.is_symlink() or any(p.is_symlink() for p in target.parents):raise SystemExit('Symlink source refused')
  target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(raw)
 for path,sha in [('tests/backups_installed_seed.php','87756fa3df4a4e08a2a3a195fb37724188c890c63bc41e81d5678b1da9371301'),('tests/backups_installed_browser.py','f4b8c95a393b8399068a1bf11fa4905f4d1af7d5c0f77849194f237a98928c6c')]:
  raw=(ROOT/path).read_bytes()
  if digest(raw)!=sha:raise SystemExit('Installed fixture drift: '+path)
  (dest/path).write_bytes(raw)
 history={}
 for path in ['SESSION_HANDOFF.md','docs/SESSION_LOG.md']:
  history[path]=git('diff','--full-index','e397d9a6e293850e8dff61a593e3ba39ee178400',INCOMING,'--',path).decode()
 (dest/'docs/BACKUPS_HISTORY_INTEGRATION.json').write_text(json.dumps({'base':'5f8efc3db5a8e3c24a1669e7ba1ca25cd9767443','incoming':INCOMING,'preserved_historical_deltas':history},indent=2)+'\n')
 subprocess.run(['python3','-B','tools/build.py','--refresh'],cwd=dest,check=True)
 subprocess.run(['git','diff','--check'],cwd=dest,check=True)
 subprocess.run(['git','add','--all'],cwd=dest,check=True)
 tree=git('write-tree',cwd=dest).decode().strip()
 if tree!=TREE:raise SystemExit('Whole reviewed merge tree mismatch: '+tree)
 env={**os.environ,'GIT_AUTHOR_NAME':'CajunWebPro reviewed integration','GIT_AUTHOR_EMAIL':'integration-review@users.noreply.github.com','GIT_COMMITTER_NAME':'CajunWebPro reviewed integration','GIT_COMMITTER_EMAIL':'integration-review@users.noreply.github.com'}
 message='Integrate backup runtime drill and database export with current owner session authority'
 local=subprocess.check_output(['git','commit-tree',tree,'-p',MAIN,'-p',INCOMING,'-m',message],cwd=dest,env=env,text=True).strip()
 subprocess.run(['git','checkout','--detach',local],cwd=dest,check=True)
 remote=None
 if args.publish_object:
  if request('git/ref/heads/main')['object']['sha']!=MAIN:raise SystemExit('Main advanced; reconcile first')
  if request('pulls/197')['head']['sha']!=INCOMING:raise SystemExit('Backup source advanced')
  changes=[]
  for line in git('diff','--raw','--no-abbrev',MAIN,local,cwd=dest).decode().splitlines():
   meta,path=line.split('\t');oldmode,newmode,oldsha,newsha,status=meta.split();oldmode=oldmode.lstrip(':')
   if status not in ('A','M') or newmode!='100644':raise SystemExit('Unexpected change kind')
   raw=git('show',local+':'+path,cwd=dest)
   blob=request('git/blobs',{'content':base64.b64encode(raw).decode(),'encoding':'base64'})['sha']
   if blob!=newsha:raise SystemExit('Stored blob mismatch')
   changes.append({'path':path,'mode':'100644','type':'blob','sha':blob})
  base=git('rev-parse',MAIN+'^{tree}').decode().strip()
  stored=request('git/trees',{'base_tree':base,'tree':changes})['sha']
  if stored!=TREE:raise SystemExit('Remote resolved tree mismatch')
  remote=request('git/commits',{'message':message+'\n\nOwner-authorized development candidate. Source conflicts resolved against current main with full-file hashes and whole-tree verification. No branch update, release approval or deployment. Hosted test results remain separate.','tree':stored,'parents':[MAIN,INCOMING]})['sha']
  print('Staged immutable candidate object (no ref updated):',remote)
 report={'passed':True,'tree':TREE,'main':MAIN,'incoming':INCOMING,'local_commit':local,'staged_commit':remote,'scope':'Exact reviewed source replay; no test or merge success inferred'}
 (ROOT/'qa').mkdir(exist_ok=True);(ROOT/'qa/backup-review-source.json').write_text(json.dumps(report,indent=2)+'\n')
 if 'GITHUB_OUTPUT' in os.environ:
  with open(os.environ['GITHUB_OUTPUT'],'a') as out:
   out.write('source_dir='+str(dest)+'\nlocal_commit='+local+'\nstaged_commit='+(remote or '')+'\n')
 print(json.dumps(report))
if __name__=='__main__':main()
