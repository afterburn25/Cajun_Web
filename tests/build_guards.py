"""Exercise real builder rejection paths in an isolated copy, never shipping source."""
from pathlib import Path
import importlib.util,json,os,shutil,subprocess,tempfile
ROOT=Path(__file__).resolve().parents[1];QA=ROOT/'qa';checks=[]
(ROOT/'test-runtime').mkdir(exist_ok=True)
WORK=Path(tempfile.mkdtemp(prefix='build-guards-',dir=ROOT/'test-runtime'))
spec=importlib.util.spec_from_file_location('cwp_build',ROOT/'tools/build.py');build=importlib.util.module_from_spec(spec);spec.loader.exec_module(build)
WORK.mkdir(parents=True,exist_ok=True);build.SITE=WORK/'site';shutil.copytree(ROOT/'site',build.SITE)
def check(name,value):
    checks.append({'name':name,'passed':bool(value)})
    if not value:raise AssertionError(name)
def rejects(name,work):
    try:work()
    except (RuntimeError,FileNotFoundError):check(name,True)
    else:check(name,False)
def temporary(name,data,test):
    p=build.SITE/name;old=p.read_bytes() if p.exists() else None;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data)
    try:rejects(test,build.inventory)
    finally:
        if old is None:p.unlink()
        else:p.write_bytes(old)
try:
    commit=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
    long_name='site/includes/foundation/mail-receiving/vendor/zbateson/mail-mime-parser/src/Header/Consumer/QuotedStringMimeLiteralPartTokenSplitPatternTrait.php'
    check('Builder reads the exact long dependency Git blob without filesystem path ambiguity',build.source_bytes(commit,long_name)==(ROOT/long_name).read_bytes())
    check('Builder accepts exact organized scaffold inventory',len(build.inventory())>100)
    check('Installer inventory uses portable case-sensitive path order',list(build.inventory())==sorted(build.inventory()))
    temporary('storage/client.zip',b'customer bytes','Builder rejects runtime storage contents')
    temporary('upload/staging.php',b'<?php exit; ?>private','Builder rejects upload staging contents')
    temporary('storage/index.php',b'<?php echo "changed";','Builder rejects altered scaffold metadata')
    temporary('config.php',b'<?php return [];','Builder rejects installed configuration')
    temporary('includes/mail-master.key',b'synthetic key material','Builder rejects email credential keys in code directories')
    temporary('business.php',b'<?php echo 1;','Builder rejects new root business modules')
    temporary('assets/accidental.png',b'image','Builder rejects images mixed with styles/scripts')
    temporary('includes/.htaccess',b'Require all granted\n','Builder rejects weakened internal server denial')
    temporary('app/billing/stale.php',b"<?php require CWP_CODE_ROOT.'/app/core.php';",'Builder rejects stale moved code references')
    temporary('app/clients/old-route.php',b"<?php echo site_url('portal.php');",'Builder rejects old URL generation')
    temporary('install/views/form.php',b'<img src="assets/logo.png">','Builder rejects a stale literal installer image')
    temporary('assets/css/missing.css',b"body{background:url('../../images/missing.png')}",'Builder rejects missing CSS images')
    p=build.SITE/'includes/security/csrf.php';old=p.read_bytes();p.unlink()
    try:rejects('Builder rejects a missing canonical module',build.inventory)
    finally:p.write_bytes(old)
    p=build.SITE/'images/linked.png';p.symlink_to(build.SITE/'images/icons/favicon.png')
    try:rejects('Builder rejects symlinked release entries',build.inventory)
    finally:p.unlink()
    rejects('Archive builder rejects traversal members',lambda:build.archive(WORK,'unsafe.zip',{'../escape':b'x'},'fixture','fixture'))
    rejects('Archive builder rejects duplicate case aliases',lambda:build.archive(WORK,'duplicate.zip',{'A.php':b'a','a.php':b'b'},'fixture','fixture'))
    check('Negative checks restore the original shipping copy',build.inventory()=={p.relative_to(ROOT/'site').as_posix():build.sha(p.read_bytes()) for p in (ROOT/'site').rglob('*') if p.is_file()})
finally:
    QA.mkdir(exist_ok=True);(QA/'build-guards-results.json').write_text(json.dumps({'passed':sum(c['passed'] for c in checks),'checks':checks},indent=2)+'\n')
print('PASS:',len(checks),'build rejection checks')
