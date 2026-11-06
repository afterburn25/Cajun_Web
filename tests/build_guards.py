"""Exercise real builder rejection paths in an isolated copy, never shipping source."""
from pathlib import Path
import importlib.util,json,os,shutil,subprocess,tempfile
ROOT=Path(__file__).resolve().parents[1];QA=ROOT/'qa';checks=[]
WORK=Path(tempfile.mkdtemp(prefix='cwp-build-guards-'))
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
def release_fixture(root,commit='a'*40,version='9.8.7'):
    root.mkdir()
    reports=[]
    for kind,files in {
        'FULL-PROJECT':{'docs/readme.txt':b'full project\n'},
        'COMPLETE-UPLOAD':{'index.php':b'<?php echo "complete";\n','storage/.htaccess':b'Require all denied\n'},
        'UPDATE':{'app/update.php':b'<?php echo "update";\n'},
    }.items():
        reports.append(build.archive(root,f'CajunWebPro-{version}-{kind}.zip',files,commit,version))
    aggregate={'version':version,'source_commit':commit,'layout':'organized','files':{'index.php':build.sha(b'fixture')},
               'artifacts':[{key:value for key,value in row.items() if key!='files'} for row in reports]}
    build.write(root/'manifest.json',json.dumps(aggregate,indent=2)+'\n')
    build.write(root/'START-HERE.txt','Synthetic release fixture.\n')
    files=sorted([root/'manifest.json',root/'START-HERE.txt']+
                 [root/row['artifact'] for row in reports]+
                 [root/(row['artifact'].removesuffix('.zip')+suffix) for row in reports
                  for suffix in ('.manifest.json','.directories.txt')],key=lambda path:path.name)
    build.write(root/'SHA256SUMS.txt',''.join(build.sha(path.read_bytes())+'  '+path.name+'\n' for path in files))
    return commit,version
try:
    commit=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
    long_name='site/includes/foundation/mail-receiving/vendor/zbateson/mail-mime-parser/src/Header/Consumer/QuotedStringMimeLiteralPartTokenSplitPatternTrait.php'
    check('Builder reads the exact long dependency Git blob without filesystem path ambiguity',build.source_bytes(commit,long_name)==(ROOT/long_name).read_bytes())
    version_root=WORK/'version-check';version_root.mkdir();(version_root/'VERSION').write_text((ROOT/'VERSION').read_text())
    check('Root VERSION runtime and installer agree',build.release_version(version_root,build.SITE)==(ROOT/'VERSION').read_text().strip())
    (version_root/'VERSION').write_text('0.0.0\n')
    rejects('Builder rejects mismatched root VERSION',lambda:build.release_version(version_root,build.SITE))
    (version_root/'VERSION').unlink()
    rejects('Builder rejects missing root VERSION',lambda:build.release_version(version_root,build.SITE))
    check('Builder accepts exact organized scaffold inventory',len(build.inventory())>100)
    check('Installer inventory uses portable case-sensitive path order',list(build.inventory())==sorted(build.inventory()))
    temporary('storage/client.zip',b'customer bytes','Builder rejects runtime storage contents')
    temporary('upload/staging.php',b'<?php exit; ?>private','Builder rejects upload staging contents')
    temporary('storage/index.php',b'<?php echo "changed";','Builder rejects altered scaffold metadata')
    temporary('config.php',b'<?php return [];','Builder rejects installed configuration')
    temporary('includes/mail-master.key',b'synthetic key material','Builder rejects email credential keys in code directories')
    temporary('includes/provider-master.key',b'synthetic key material','Builder rejects provider credential keys in code directories')
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
    source_repo=WORK/'recorded-source';source_repo.mkdir()
    subprocess.run(['git','init'],cwd=source_repo,check=True,capture_output=True)
    subprocess.run(['git','config','user.name','Build guard'],cwd=source_repo,check=True)
    subprocess.run(['git','config','user.email','build-guard@example.invalid'],cwd=source_repo,check=True)
    (source_repo/'source.txt').write_text('recorded\n',encoding='utf-8')
    subprocess.run(['git','add','source.txt'],cwd=source_repo,check=True)
    subprocess.run(['git','commit','-m','Recorded source'],cwd=source_repo,check=True,capture_output=True)
    source_commit=subprocess.check_output(['git','rev-parse','HEAD'],cwd=source_repo,text=True).strip()
    recorded,tracked=build.recorded_source(source_repo,source_commit)
    check('Recorded-source guard accepts one clean exact commit',recorded==source_commit and tracked==['source.txt'])
    rejects('Recorded-source guard requires a full commit identity',lambda:build.recorded_source(source_repo,source_commit[:12]))
    (source_repo/'overlay.txt').write_text('unrecorded\n',encoding='utf-8')
    rejects('Recorded-source guard rejects untracked branch overlays',lambda:build.recorded_source(source_repo,source_commit))
    (source_repo/'overlay.txt').unlink()
    (source_repo/'source.txt').write_text('modified\n',encoding='utf-8')
    rejects('Recorded-source guard rejects modified tracked source',lambda:build.recorded_source(source_repo,source_commit))
    (source_repo/'source.txt').write_text('recorded\n',encoding='utf-8')
    release=WORK/'release';fixture_commit,fixture_version=release_fixture(release)
    version,packages=build.validate_release_outputs(release,fixture_commit)
    check('Release validator binds all three packages to manifest version and source',
          version==fixture_version and len(packages)==3)
    staged=WORK/'rc-upload';result=build.stage_rc_upload(release,staged,fixture_commit)
    note=(staged/'RC-TEST-ONLY.txt').read_bytes()
    check('RC staging validates before extraction and uses the manifest version',
          result['version']==fixture_version and (staged/'index.php').is_file())
    check('RC test-only note has one real trailing newline and no literal escape',
          note.endswith(b'\n') and not note.endswith(b'\n\n') and b'\\n' not in note and fixture_version.encode() in note)
    rejects('RC note validator rejects a literal backslash-n',
            lambda:build.validate_rc_note(build.rc_note(fixture_version)[:-1]+b'\\n',fixture_version))
    rejects('RC staging refuses an existing destination',lambda:build.stage_rc_upload(release,staged,fixture_commit))
    tampered=release/f'CajunWebPro-{fixture_version}-COMPLETE-UPLOAD.zip';original=tampered.read_bytes();tampered.write_bytes(original+b'tamper')
    rejects('RC staging refuses a package that differs from its recorded archive hash',
            lambda:build.stage_rc_upload(release,WORK/'tampered-stage',fixture_commit))
    tampered.write_bytes(original)
    complete_manifest=release/f'CajunWebPro-{fixture_version}-COMPLETE-UPLOAD.manifest.json'
    original_manifest=complete_manifest.read_bytes();wrong=json.loads(original_manifest);wrong['version']='0.0.0'
    complete_manifest.write_text(json.dumps(wrong,indent=2)+'\n',encoding='utf-8')
    rejects('RC staging refuses a package version that differs from the release manifest',
            lambda:build.stage_rc_upload(release,WORK/'wrong-version-stage',fixture_commit))
    complete_manifest.write_bytes(original_manifest)
    directories=release/f'CajunWebPro-{fixture_version}-COMPLETE-UPLOAD.directories.txt'
    original_directories=directories.read_bytes();directories.write_text('unreviewed/\n',encoding='utf-8')
    rejects('RC staging refuses a directory list that differs from its package manifest',
            lambda:build.stage_rc_upload(release,WORK/'wrong-directories-stage',fixture_commit))
    directories.write_bytes(original_directories)
    workflow=(ROOT/'.github/workflows/rc126-test-package.yml').read_text(encoding='utf-8')
    check('RC workflow never imports or commits legacy branch overlays',
          all(token not in workflow for token in ('git fetch','git restore','git commit','import_files','chatgpt/')))
    check('RC workflow derives its version and contains no historical hard-coded version','1.26.0' not in workflow)
    check('RC workflow refreshes and refuses generated drift before the exact-source build',
          'python3 tools/build.py --refresh' in workflow and 'git diff --exit-code' in workflow)
    check('RC workflow delegates validated staging to the recorded-source builder',
          'python3 tools/build.py --commit "$GITHUB_SHA" --stage-rc rc-upload' in workflow)
    check('RC workflow preserves exact-main evidence while retaining branch cancellation',
          "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}" in workflow)
    check('RC artifact naming uses the release manifest version',
          'steps.package.outputs.version' in workflow and 'CajunWebPro-1.26' not in workflow)
    check('Negative checks restore the original shipping copy',build.inventory()=={p.relative_to(ROOT/'site').as_posix():build.sha(p.read_bytes()) for p in (ROOT/'site').rglob('*') if p.is_file()})
    from build_git_blob_batch import run as batch_checks
    batch_checks(build,WORK,check)
finally:
    QA.mkdir(exist_ok=True);(QA/'build-guards-results.json').write_text(json.dumps({'passed':sum(c['passed'] for c in checks),'checks':checks},indent=2)+'\n')
print('PASS:',len(checks),'build rejection checks')
