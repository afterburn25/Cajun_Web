"""Verify every support-ticket shipping change while preserving the prior proposal source layer."""
from pathlib import Path
import hashlib,json,subprocess
from software_authority_provenance import source_revision, predecessor_bytes
BASE='cb2838df5336c0acca8274ce65c3cb614b8d4195'
SOURCE='9b1d45b1a830fcce287c93227e011c93df15aaaf'
AREAS=['site','foundation','tools/structure','tools/build.py','VERSION','START-HERE.txt']
def validate(root):
    receipt=json.loads((root/'docs/TICKETS_COMBINED_SOURCE.json').read_text())
    if receipt['base']!=BASE or receipt['ticket_source']!=SOURCE:raise AssertionError('Unknown ticket source identities')
    paths=receipt['shipping']
    revision=source_revision(root)
    changed=set(subprocess.check_output(['git','diff','--name-only',BASE,revision,'--',*AREAS],cwd=root,text=True).splitlines())
    if changed!=set(paths):raise AssertionError('Unreviewed ticket shipping difference: '+repr(changed.symmetric_difference(paths)))
    for path,row in paths.items():
        p=Path(path)
        if p.is_absolute() or '..' in p.parts:raise AssertionError('Unsafe ticket receipt path')
        target=root/p
        if target.is_symlink() or hashlib.sha256(predecessor_bytes(root,path,target.read_bytes())).hexdigest()!=row['after']:raise AssertionError('Changed ticket output: '+path)
        old=subprocess.run(['git','show',BASE+':'+path],cwd=root,capture_output=True)
        if row['before'] is None:
            if old.returncode==0:raise AssertionError('Unexpected ticket addition: '+path)
        elif old.returncode or hashlib.sha256(old.stdout).hexdigest()!=row['before']:raise AssertionError('Changed ticket predecessor: '+path)
    if (root/'VERSION').read_bytes()!=subprocess.check_output(['git','show',BASE+':VERSION'],cwd=root):raise AssertionError('Do not replace the already-delivered version')
    return BASE
if __name__=='__main__':
    validate(Path(__file__).resolve().parents[1]);print('All ticket shipping changes match their exact reviewed source; the previous combined source remains preserved.')
