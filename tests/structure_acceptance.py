"""Real filesystem interruption/recovery and artifact guard acceptance, unprivileged."""
from pathlib import Path
import hashlib,json,os,shutil,subprocess,zipfile
ROOT=Path(__file__).resolve().parents[1];QA=ROOT/'qa';RUNTIME=ROOT/'test-runtime/structure';checks=[]
PHP=shutil.which('php8.3') or shutil.which('php')
def check(name,value):
    checks.append({'name':name,'passed':bool(value)})
    if not value:raise AssertionError(name)
def run(root,action='apply',stop=0):return subprocess.run([PHP,str(ROOT/'tests/structure_worker.php'),str(root),action,str(stop)],capture_output=True,text=True)
def fixture(name):
    root=RUNTIME/name;shutil.copytree(ROOT/'test-runtime/released-1.10.0',root)
    private=RUNTIME/(name+'-private');private.mkdir(mode=0o700)
    config={'storage':str(private),'key':'synthetic-config-preserve-only'}
    # Use PHP's JSON decoder; no shell interpolation of fixture paths or data.
    import base64
    data=base64.b64encode(json.dumps(config).encode()).decode()
    (root/'config.php').write_text("<?php return json_decode(base64_decode('"+data+"'),true);")
    (root/'.installed.php').write_bytes(b'<?php exit; ?>retained installed guard')
    (private/'customer.php').write_bytes(b'<?php exit; ?>retained private customer bytes')
    with zipfile.ZipFile(next((ROOT/'dist').glob('*-UPDATE.zip'))) as z:z.extractall(root)
    return root,private
def snapshot(root):return {p.relative_to(root).as_posix():p.read_bytes() for p in root.rglob('*') if p.is_file() and 'cwp-update-1.11.0' not in p.parts}
try:
    check('Structure filesystem worker is unprivileged',os.geteuid()!=0)
    RUNTIME.mkdir(parents=True,exist_ok=True)
    complete=next((ROOT/'dist').glob('*-COMPLETE-UPLOAD.zip'));full=next((ROOT/'dist').glob('*-FULL-PROJECT.zip'))
    with zipfile.ZipFile(complete) as z:
        names=set(z.namelist());check('Fresh production root uses explicit allowlist',{n for n in names if '/' not in n}=={'.htaccess','index.php','install.php','favicon.ico','robots.php','sitemap.php'})
        check('Fresh organized directories contain real implementation',all(any(n.startswith(d+'/') for n in names) for d in ['admin/controllers','admin/views','portal/controllers','portal/views','includes/security','database/migrations','images/branding','storage','upload']))
        check('Production ZIP excludes tests docs and runtime data',not any(n.startswith(('tests/','docs/')) or n.endswith('/config.php') for n in names) and {n for n in names if n.startswith(('storage/','upload/'))}=={'storage/.htaccess','storage/index.php','upload/.htaccess','upload/index.php'})
        baseline=json.loads((ROOT/'docs/STRUCTURE_BASELINE_1.11-CANDIDATE.json').read_text())['files'];mapping=json.loads((ROOT/'tools/directory-map.json').read_text())['paths']
        for old,new in mapping.items():
            if new.startswith('images/'):
                original=baseline[old];digest=original['sha256'] if isinstance(original,dict) else original
                check('Original image bytes preserved: '+new,hashlib.sha256(z.read(new)).hexdigest()==digest)
    with zipfile.ZipFile(full) as z:
        check('Full project includes source docs tests tools brand foundations and CI',all(any(n.startswith(d+'/') for n in z.namelist()) for d in ['site','docs','tests','tools','brand','foundation','.github']))
        check('Full project has no runtime/debris',not any(n.startswith(('.git/','qa/','test-runtime/','dist/')) for n in z.namelist()))
    root,private=fixture('resume');original=snapshot(root);(root/'app/custom.php').write_bytes(b'<?php echo "custom preserved";');(root/'assets/custom.css').write_text('/* custom retained */')
    r=run(root,stop=15);check('Real migration interruption is reported',r.returncode!=0 and 'Synthetic interruption' in r.stderr)
    journal=(private/'structure-1.11.0/journal.php').read_bytes();check('Durable guarded journal and maintenance policy precede retirement',journal.startswith(b'<?php http_response_code(404); exit; ?>\n') and b'Temporary owner-authenticated' in (root/'.htaccess').read_bytes())
    r=run(root);check('Interrupted migration retries to completion',r.returncode==0 and json.loads(r.stdout)['state']=='complete')
    check('Customized unknown modules and assets preserved',(root/'app/custom.php').read_bytes()==b'<?php echo "custom preserved";' and (root/'assets/custom.css').read_text()=='/* custom retained */')
    check('Configuration installed guard and private data unchanged',all((root/n).read_bytes()==original[n] for n in ['config.php','.installed.php']) and (private/'customer.php').read_bytes()==b'<?php exit; ?>retained private customer bytes')
    check('Known old implementation retired; compatibility endpoint is tiny',not (root/'app/core.php').exists() and (root/'admin.php').stat().st_size<300)
    r=run(root);check('Completed migration retry is idempotent',r.returncode==0 and json.loads(r.stdout)['state']=='complete')
    r=run(root,'recover');check('Completed structure update can restore exact original files',r.returncode==0 and all((root/n).read_bytes()==b for n,b in original.items()))
    check('Recovery removes only unchanged introduced files',not (root/'admin/index.php').exists() and (root/'app/custom.php').exists())
    r=run(root,'recover');check('Recovery retry is idempotent',r.returncode==0 and json.loads(r.stdout)['state']=='rolled-back')
    root,private=fixture('failed');original=snapshot(root);r=run(root,stop=7);check('Second interrupted update leaves explicit failure',r.returncode!=0)
    r=run(root,'recover');check('Failed update recovery restores pre-switch website exactly',r.returncode==0 and all((root/n).read_bytes()==b for n,b in original.items()))
    root,private=fixture('custom-entry');(root/'admin.php').write_text('<?php /* customized owner code */');original=snapshot(root);r=run(root)
    check('Customized privileged endpoint stops switch without overwrite',r.returncode!=0 and 'Customized' in r.stderr and snapshot(root)==original)
    root,private=fixture('missing-payload');(root/'cwp-update-1.11.0/site/includes/security/csrf.php').unlink();original=snapshot(root);r=run(root)
    check('Incomplete staged upload cannot start switching',r.returncode!=0 and 'missing or mismatched' in r.stderr and snapshot(root)==original)
    root,private=fixture('overlap');p=subprocess.Popen([PHP,str(ROOT/'tests/structure_worker.php'),str(root),'hold'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    check('First migration owns retained lock',p.stdout.readline().strip()=='LOCKED');r=run(root);check('Overlapping migration refused',r.returncode!=0 and 'Another update' in r.stderr);p.wait(timeout=10)
    r=run(root);check('Lock releases without deleting its inode',r.returncode==0 and (private/'structure-1.11.0/mutex.php').is_file())
    root,private=fixture('denied');root.chmod(0o555)
    try:r=run(root);check('Unprivileged replacement denial is actionable',r.returncode!=0 and ('Cannot' in r.stderr or 'cannot' in r.stderr))
    finally:root.chmod(0o755)
    r=run(root);check('Denied filesystem operation can retry after permission correction',r.returncode==0)
    root,private=fixture('changed-recovery');r=run(root);check('Recovery edit fixture migrated',r.returncode==0);(root/'admin/index.php').write_text('<?php /* edit after migration */')
    r=run(root,'recover');check('Recovery preserves subsequent customization and stays protected',r.returncode!=0 and 'subsequently edited' in r.stderr and b'edit after migration' in (root/'admin/index.php').read_bytes() and b'Temporary owner-authenticated' in (root/'.htaccess').read_bytes())
finally:
    QA.mkdir(exist_ok=True);(QA/'structure-results.json').write_text(json.dumps({'passed':sum(c['passed'] for c in checks),'checks':checks},indent=2)+'\n')
print('PASS:',len(checks),'structure/package/recovery checks')
