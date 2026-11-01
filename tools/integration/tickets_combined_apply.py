#!/usr/bin/env python3
"""Reproduce the reviewed ticket integration; no remote ref, SQL or live operation."""
from pathlib import Path
import gzip,hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='cb2838df5336c0acca8274ce65c3cb614b8d4195'
INCOMING='9b1d45b1a830fcce287c93227e011c93df15aaaf'
DIGEST='95c7fa00218f1e6967a35f4c42753a4a07455d190259604eaf4eaf007879eed7'
PARTS=['0','1','2a','2b','2c','3']
def sha(raw):return hashlib.sha256(raw).hexdigest()
def source_allowed(row):
    # These two new fixtures intentionally derive from already-tested proposal
    # helpers. Permit only those exact base/source/target tuples, not arbitrary
    # cross-path reads. The packed recipe and every input/output hash still bind
    # the complete contents.
    derived={
        'tests/tickets_installed_browser.py':'tests/proposal_binding_browser.py',
        'tests/tickets_installed_seed.php':'tests/proposal_binding_seed.php',
    }
    return (row['source_ref'] in (BASE,INCOMING) and row['source_path']==row['path']) or (
        row['source_ref']==BASE and derived.get(row['path'])==row['source_path'])
def main():
    if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/tickets-combined-20261031':raise SystemExit('Isolated ticket integration branch only')
    packed=b''.join((ROOT/('tools/integration/tickets-source.'+part+'.gzpart')).read_bytes() for part in PARTS)
    if len(packed)!=18941 or sha(packed)!=DIGEST:raise SystemExit('Reviewed ticket recipe changed')
    raw=gzip.decompress(packed)
    if len(raw)!=71600:raise SystemExit('Unexpected recipe size')
    recipe=json.loads(raw)
    if (recipe['schema'],recipe['base'],recipe['incoming'])!=(1,BASE,INCOMING):raise SystemExit('Unreviewed ticket inputs')
    rows=recipe['records']
    if len(rows)!=62 or len({row['path'] for row in rows})!=62:raise SystemExit('Unexpected ticket change scope')
    correction_bytes=(ROOT/'tools/integration/tickets-runtime-corrections.json').read_bytes()
    if sha(correction_bytes)!='d44993b5c0a82eb260fe634d5b7f757e0b3b1381b4fe6430baebfaf5b8655757':raise SystemExit('Reviewed ticket corrections changed')
    correction_doc=json.loads(correction_bytes)
    if correction_doc['predecessor']!='a96c671a8d5a21ecaa87be33aafc7065c063dccc':raise SystemExit('Unknown ticket correction predecessor')
    corrections={item['path']:item for item in correction_doc['records']}
    if len(correction_doc['records'])!=6 or set(corrections)!={'docs/TICKETS_COMBINED_SOURCE.json','site/app/support/ticket_notifications.php','site/install/inventory.generated.php','site/portal/controllers/tickets.php','tests/support_ticket_screen_native_cases.php','tests/ticket_cases.py'}:raise SystemExit('Unexpected ticket correction scope')
    outputs=[]
    for row in rows:
        path=Path(row['path'])
        if path.is_absolute() or '..' in path.parts or '\\' in row['path'] or not (row['path'].startswith(('site/','foundation/','tests/','docs/')) or row['path']=='tools/build.py'):raise SystemExit('Unsafe ticket path')
        target=ROOT/path
        if target.is_symlink() or any(p.is_symlink() for p in target.parents):raise SystemExit('Symlink ticket input refused')
        current=target.read_bytes() if target.exists() else None
        current_hash=sha(current) if current is not None else None
        correction=corrections.get(row['path'])
        allowed=(row['target_before'],row['output'])+((correction['after'],) if correction else ())
        if current_hash not in allowed:raise SystemExit('Current target drift: '+row['path'])
        if 'content' in row:
            output=row['content'].encode('utf-8')
        else:
            if not source_allowed(row):raise SystemExit('Unexpected original source: '+row['path'])
            output=subprocess.check_output(['git','show',row['source_ref']+':'+row['source_path']],cwd=ROOT)
            if sha(output)!=row['input']:raise SystemExit('Original source identity differs: '+row['path'])
            limit=len(output)+1
            for start,end,text in reversed(row['edits']):
                if not 0<=start<=end<limit:raise SystemExit('Overlapping ticket edits')
                output=output[:start]+text.encode('utf-8')+output[end:];limit=start
        if sha(output)!=row['output']:raise SystemExit('Reviewed ticket output differs: '+row['path'])
        if correction:
            if sha(output)!=correction['before']:raise SystemExit('Ticket correction predecessor differs: '+row['path'])
            limit=len(output)+1
            for start,end,text in reversed(correction['edits']):
                if not 0<=start<=end<limit:raise SystemExit('Overlapping runtime correction')
                output=output[:start]+text.encode('utf-8')+output[end:];limit=start
            if sha(output)!=correction['after']:raise SystemExit('Ticket runtime correction differs: '+row['path'])
        if current!=output:outputs.append((target,output))
    for target,output in outputs:
        target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(output)
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    print('Verified all62 exact ticket source outputs; changed',len(outputs),'files. Database/browser acceptance remains separate.')
if __name__=='__main__':main()
