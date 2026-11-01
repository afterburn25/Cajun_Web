#!/usr/bin/env python3
"""Expand the reviewed additive feature on its isolated development branch only."""
from pathlib import Path
import gzip, hashlib, json, os, subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='366d53b1ed6b367b32664f2b91ee073fc6813544'
BRANCH='refs/heads/chatgpt/notification-center-20261101'
PACKED='a3bb3b78aa15a6fd2f40bddd6e445c95ca11ad55a937a15ff765e7ce0dc7b585'
def sha(raw): return hashlib.sha256(raw).hexdigest()
def main():
    if os.environ.get('GITHUB_REF')!=BRANCH:raise SystemExit('Isolated notification feature branch only')
    packed=b''.join((ROOT/f'tools/integration/notification-center.{i}.gzpart').read_bytes() for i in range(18))
    if sha(packed)!=PACKED:raise SystemExit('Reviewed notification recipe changed')
    raw=gzip.decompress(packed)
    if len(raw)!=88406:raise SystemExit('Unexpected recipe length')
    data=json.loads(raw)
    if data['base']!=BASE or len(data['records'])!=28 or len(data['generated'])!=6:raise SystemExit('Unexpected source scope')
    rows={r['path']:r for r in data['records']}
    if len(rows)!=28 or set(rows)&set(data['generated']):raise SystemExit('Duplicate source path')
    facts={**rows,**data['generated']};current={}
    for name,row in facts.items():
        p=Path(name);target=ROOT/p
        if p.is_absolute() or '..' in p.parts or '\\' in name or not name.startswith(('site/','foundation/','tests/','docs/','tools/')):raise SystemExit('Unsafe source path')
        if target.is_symlink() or any(x.is_symlink() for x in target.parents):raise SystemExit('Symlink source refused')
        current[name]=target.read_bytes() if target.exists() else None
    if all(current[n] is not None and sha(current[n])==r['after'] for n,r in facts.items()):
        print('All34 notification source identities already match.');return
    for name,row in facts.items():
        if (None if current[name] is None else sha(current[name]))!=row['before']:raise SystemExit('Source drift before mutation: '+name)
    output={}
    for name,row in rows.items():
        if 'source' in row:
            if row['source']!='04457d25a6b577edadcb993f62986fd568033d15' or row['before'] is not None:raise SystemExit('Unreviewed original source')
            value=subprocess.check_output(['git','show',row['source']+':'+name],cwd=ROOT)
        elif 'content' in row:value=row['content'].encode()
        else:
            value=current[name];previous=len(value)+1
            for start,end,text in reversed(row['edits']):
                if not 0<=start<=end<previous:raise SystemExit('Overlapping source edits: '+name)
                value=value[:start]+text.encode()+value[end:];previous=start
        if sha(value)!=row['after']:raise SystemExit('Reviewed output mismatch: '+name)
        output[name]=value
    for name,value in output.items():
        p=ROOT/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(value)
    subprocess.run(['python3','-B','tools/build.py','--refresh'],cwd=ROOT,check=True)
    for name,row in facts.items():
        if sha((ROOT/name).read_bytes())!=row['after']:raise SystemExit('Canonical output mismatch: '+name)
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    print('Expanded34 exact notification source files. No remote ref, mail or production operation.')
if __name__=='__main__':main()
