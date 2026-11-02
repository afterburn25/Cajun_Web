#!/usr/bin/env python3
"""Complete reviewed fixture corrections without changing updater authentication."""
from pathlib import Path
import hashlib,json,os,subprocess
ROOT=Path(__file__).resolve().parents[2]
BASE='2f11bc1fa202020880ead9abc1ee72d19205cac5'
RECEIPT=ROOT/'docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json'
changes={}
def digest(raw):return hashlib.sha256(raw).hexdigest()
def change(path,old,new):
    p=ROOT/path
    if path not in changes:
        before=subprocess.check_output(['git','show',BASE+':'+path],cwd=ROOT)
        if p.is_symlink() or p.read_bytes()!=before:raise SystemExit('Source drift: '+path)
        changes[path]={'before_sha256':digest(before),'edits':[]}
    text=p.read_text()
    if text.count(old)!=1:raise SystemExit('Non-unique correction: '+path+' '+old[:80])
    changes[path]['edits'].append([old,new]);p.write_text(text.replace(old,new,1))
def create(path,text):
    p=ROOT/path
    if p.exists() or p.is_symlink():raise SystemExit('Unexpected existing file: '+path)
    p.parent.mkdir(parents=True,exist_ok=True);p.write_text(text);changes[path]={'before_sha256':None}
def main():
    if os.environ.get('GITHUB_REF')!='refs/heads/chatgpt/combined-acceptance-repair-20261031':raise SystemExit('Isolated correction branch only')
    if RECEIPT.exists():
        receipt=json.loads(RECEIPT.read_text())
        if receipt['base_tree']!=BASE:raise SystemExit('Unknown repair receipt')
        for path,row in receipt['files'].items():
            if digest((ROOT/path).read_bytes())!=row['after_sha256']:raise SystemExit('Recorded correction changed: '+path)
        print('Remaining fixture repairs already recorded');return
    path='tests/provider_catalog_import_native.php';text=(ROOT/path).read_text();start=text.index('    // Model a later complete observation under replacement credentials;');end=text.index("    $keyPath=$private.'/provider-master.key';",start)
    change(path,text[start:end],"""    // Corrupting an existing source must be refused, not mistaken for a later read.
    $savedResult=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$preview['refresh_id']]);$newFingerprint=provider_inventory_fingerprint('SYNTHETIC_DIFFERENT_IMPORT_KEY');$sameContextState=pim_state();
    query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$replacement,$profile]);query('UPDATE '.table('provider_refresh_results').' SET key_fingerprint=? WHERE id=?',[$newFingerprint,$preview['refresh_id']]);
    try{pim_deny('Mutated historical import source is refused before projection',fn()=>provider_catalog_import_preview());pim_ok('Corrupt source refusal retains exact imported products and provenance',pim_state()===$sameContextState);}
    finally{query('UPDATE '.table('provider_refresh_results').' SET key_fingerprint=? WHERE id=?',[$savedResult['key_fingerprint'],$preview['refresh_id']]);query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$cipher,$profile]);}
    $savedRefresh=one('SELECT * FROM '.table('provider_refreshes').' WHERE id=?',[$preview['refresh_id']]);$savedPackages=all('SELECT * FROM '.table('provider_observation_packages').' WHERE refresh_id=? ORDER BY package_id',[$preview['refresh_id']]);$laterId=bin2hex(random_bytes(16));
    $insert=static function(string $tableName,array $row):void {
        if(!in_array($tableName,['provider_refreshes','provider_refresh_results','provider_observation_packages'],true))throw new LogicException('Unexpected fixture table.');
        foreach(array_keys($row) as $column)if(!preg_match('/^[a-z_]+$/D',$column))throw new LogicException('Unexpected fixture column.');
        query('INSERT INTO '.table($tableName).' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    };
    try{
        $later=$savedRefresh;unset($later['seq']);$later['id']=$laterId;$insert('provider_refreshes',$later);
        $later=$savedResult;$later['id']=$laterId;$later['key_fingerprint']=$newFingerprint;$insert('provider_refresh_results',$later);
        foreach($savedPackages as $package){$package['id']=bin2hex(random_bytes(16));$package['refresh_id']=$laterId;$insert('provider_observation_packages',$package);}
        query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$replacement,$profile]);
        $different=provider_catalog_import_preview();$matched=array_values(array_filter($different['types'],fn($type)=>$type['type_ref']==='1'));
        pim_ok('Changed credentials preserve an earlier-source draft without relabelling it',count($matched)===1&&$matched[0]['product_id']===$product&&$matched[0]['earlier_credentials']===true&&!str_contains(json_encode($different),$newFingerprint));
        pim_deny('Matching type under changed credentials cannot reuse the earlier import',fn()=>provider_catalog_import_create($different['hash'],'1','NOT-REIMPORTED','month',$password));
        pim_ok('Changed-context refusal preserves exact original catalog and provenance',pim_state()===$sameContextState);
        ob_start();provider_catalog_import_admin_view(admin_user());$changedHtml=ob_get_clean();pim_ok('Preview explains an existing draft from changed connection details',str_contains($changedHtml,'Your 20i connection details changed')&&!str_contains($changedHtml,$newFingerprint));
        pim_ok('Later observation never alters the historical import source',one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$preview['refresh_id']])===$savedResult&&all('SELECT * FROM '.table('provider_observation_packages').' WHERE refresh_id=? ORDER BY package_id',[$preview['refresh_id']])===$savedPackages);
    }finally{
        query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$cipher,$profile]);
        query('DELETE FROM '.table('provider_observation_packages').' WHERE refresh_id=?',[$laterId]);query('DELETE FROM '.table('provider_refresh_results').' WHERE id=?',[$laterId]);query('DELETE FROM '.table('provider_refreshes').' WHERE id=?',[$laterId]);
    }
    pim_ok('Exact credential and observation restoration restores the original preview',provider_settings_load($profile)['credentials']===$cipher&&one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$preview['refresh_id']])===$savedResult&&provider_catalog_import_preview()['hash']===$hash);
""")
    change('tests/apache_acceptance.py',"            check(label+' authenticated owner recovery page remains reachable in maintenance',r.status_code==200 and 'Current owner password' in r.text)","""            recovery_form=BeautifulSoup(r.text,'html.parser').select_one('form')
            check(label+' owner recovery sign-in remains reachable in maintenance',r.status_code==200 and recovery_form is not None and recovery_form.select_one('input[name=owner_password][type=password][required]') is not None and recovery_form.select_one('input[name=email][type=email][required]') is not None)
            check(label+' interrupted recovery retains CSRF and explicit backup consent',recovery_form.select_one('input[name=csrf]') is not None and recovery_form.select_one('input[name=backup_confirm][type=checkbox][required]') is not None)
            denied=session.post(url,data={'csrf':csrf(r),'email':'apache@example.invalid','owner_password':'Incorrect synthetic password','backup_confirm':'1','action':'apply'},timeout=90)
            check(label+' incorrect initial owner password cannot resume migration',denied.status_code==200 and 'Owner authentication failed.' in denied.text and '<h2>Complete</h2>' not in denied.text and requests.get(base+'/legacy/admin.php').status_code==403)
            r=session.get(url)""")
    change('tests/apache_acceptance.py',"fields['current_password']='Incorrect fixture password'","fields['email']='invalid-fixture-email'")
    change('tests/apache_acceptance.py',"r.status_code==200 and 'current password' in r.text.lower() and 'Location' not in r.headers","r.status_code==200 and 'A name and valid email are required.' in r.text and 'Location' not in r.headers")
    change('tests/organized_update_cases.py',"    r=action('apply',owner_password='Wrong fixture password')\n    check(label+' organized update requires current owner password','current owner password is required' in r.text and not (private/RECOVERY/'journal.php').exists())","""    anonymous=requests.Session();unsigned=anonymous.get(owner)
    denied=anonymous.post(owner,data={'csrf':token(unsigned),'email':'organized@example.invalid','owner_password':'Wrong fixture password','backup_confirm':'1','action':'apply'},timeout=90)
    check(label+' organized update refuses unauthenticated wrong-password application','Owner authentication failed.' in denied.text and not (private/RECOVERY/'journal.php').exists())
    anonymous.close()
    check(label+' authenticated updater does not require another password',BeautifulSoup(r.text,'html.parser').select_one('input[type=password]') is None and 'Your current owner session authorizes this action.' in r.text)""")
    change('tests/checkout_authority_contract.php',"require __DIR__.'/current_owner_authority.php';","require __DIR__.'/current_owner_authority.php';\nrequire __DIR__.'/checkout_endpoint.php';")
    change('tests/checkout_authority_contract.php'," scope_env();$base=['db'",""" putenv('CWP_TEST_CHECKOUT_DOCKER_PORT');scope_env();
 scope_ok('Unmapped fixture uses the original exact server port',cwp_test_checkout_endpoint(43319,43319));
 scope_ok('Internal port is refused without explicit Docker mapping',!cwp_test_checkout_endpoint(43319,3306));
 putenv('CWP_TEST_CHECKOUT_DOCKER_PORT=3306');
 scope_ok('Explicit loopback Docker mapping distinguishes client and server ports',cwp_test_checkout_endpoint(43319,3306));
 foreach([0,3306,43318,43320] as $port)scope_ok('Mapping never admits another client port '.$port,!cwp_test_checkout_endpoint($port,3306));
 foreach([0,43319,3307] as $port)scope_ok('Mapping never admits another server port '.$port,!cwp_test_checkout_endpoint(43319,$port));
 foreach(['3307','3306x',' 3306','0'] as $mapping){putenv('CWP_TEST_CHECKOUT_DOCKER_PORT='.$mapping);scope_ok('Invalid explicit mapping refused '.$mapping,!cwp_test_checkout_endpoint(43319,3306));}
 putenv('CWP_TEST_CHECKOUT_DOCKER_PORT=3306');putenv('CWP_TEST_HOST=example.invalid');scope_ok('Docker mapping cannot admit a remote host',!cwp_test_checkout_endpoint(43319,3306));
 scope_env();putenv('CWP_TEST_DATABASE=production');scope_ok('Docker mapping cannot admit a production database',!cwp_test_checkout_endpoint(43319,3306));
 putenv('CWP_TEST_CHECKOUT_DOCKER_PORT');
 scope_env();$base=['db'""")
    contract=json.loads((ROOT/'tools/acceptance-contract.json').read_text());old=contract['provider_catalog_import']['source_sha256']['tests/provider_catalog_import_native.php'];new=digest((ROOT/'tests/provider_catalog_import_native.php').read_bytes())
    change('tools/acceptance-contract.json',old,new)
    create('tests/combined_repair_provenance.py',"""\"\"\"Validate exact corrected files and reconstruct their complete predecessor bytes.\"\"\"
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
RECEIPTS=['docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json','docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json']
def predecessor(root,path,value):
    found=[]
    for name in RECEIPTS:
        row=json.loads((root/name).read_text())['files'].get(path)
        if row is not None:found.append(row)
    if len(found)!=1:raise AssertionError('Unknown or overlapping correction: '+path)
    row=found[0]
    if hashlib.sha256(value).hexdigest()!=row['after_sha256']:raise AssertionError('Repaired source changed: '+path)
    if row['before_sha256'] is None:return None
    text=value.decode()
    for old,new in reversed(row['edits']):
        if text.count(new)!=1:raise AssertionError('Nonunique correction inverse: '+path)
        text=text.replace(new,old,1)
    raw=text.encode()
    if hashlib.sha256(raw).hexdigest()!=row['before_sha256']:raise AssertionError('Prior source changed outside correction: '+path)
    return raw
def validate(root=ROOT):
    checks=[];seen=set()
    for name in RECEIPTS:
        data=json.loads((root/name).read_text())
        if data['base_tree']!='2f11bc1fa202020880ead9abc1ee72d19205cac5':raise AssertionError('Unknown reviewed base tree')
        for path in data['files']:
            if path in seen or '..' in Path(path).parts or Path(path).is_absolute():raise AssertionError('Unsafe correction path')
            seen.add(path);predecessor(root,path,(root/path).read_bytes());checks.append({'name':'Exact repaired file and predecessor retained '+path,'passed':True})
    if not checks:raise AssertionError('Missing correction evidence')
    return checks
if __name__=='__main__':print(json.dumps({'passed':True,'scope':'Exact source identities and predecessor inverses only','checks':validate()},indent=2))
""")
    change('tests/acceptance_artifacts_test.py',"    contract=a.load(ROOT/'tools/acceptance-contract.json')","    from combined_repair_provenance import validate as validate_repairs\n    for outcome in validate_repairs(ROOT):check(outcome['name'],outcome['passed'])\n    contract=a.load(ROOT/'tools/acceptance-contract.json')")
    change('tests/acceptance_artifacts_test.py',"        if path=='tests/apache_acceptance.py':\n            appendix=software_apache_appendix.encode()","        if path=='tests/apache_acceptance.py':\n            from combined_repair_provenance import predecessor as repaired_predecessor\n            retained=repaired_predecessor(ROOT,path,retained)\n            appendix=software_apache_appendix.encode()")
    create('tests/combined_runtime_repair.py',"""\"\"\"Retain all reviewed shipping paths except the exact focused-view correction.\"\"\"
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
BASE='771556e701103a3db9f757da05da9b2357a4247b'
AREAS=['site','foundation','tools/structure','tools/build.py','VERSION','START-HERE.txt']
changed=set(subprocess.check_output(['git','diff','--name-only',BASE,'HEAD','--',*AREAS],cwd=ROOT,text=True).splitlines())
if changed!={'site/admin/views/providers.php','site/install/inventory.generated.php'}:raise SystemExit('Unreviewed shipping changes: '+repr(changed))
path='site/admin/views/providers.php';row=json.loads((ROOT/'docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json').read_text())['files'][path]
old=subprocess.check_output(['git','show',BASE+':'+path],cwd=ROOT)
if hashlib.sha256(old).hexdigest()!=row['before_sha256'] or hashlib.sha256((ROOT/path).read_bytes()).hexdigest()!=row['after_sha256']:raise SystemExit('Cloud focus source identity changed')
print('Every other combined shipping file retains its exact reviewed bytes.')
""")
    change('.github/workflows/1261-main-reconcile.yml','git diff --exit-code 771556e701103a3db9f757da05da9b2357a4247b HEAD -- site foundation tools/structure tools/build.py VERSION START-HERE.txt','python3 tests/combined_runtime_repair.py')
    change('.github/workflows/1261-main-reconcile.yml',"'runtime_matches_reviewed_combination':True","'runtime_matches_reviewed_combination':False,'runtime_matches_reviewed_cloud_focus_correction':True")
    for path,row in changes.items():row['after_sha256']=digest((ROOT/path).read_bytes())
    RECEIPT.write_text(json.dumps({'schema':1,'base_tree':BASE,'files':changes,'scope':'Fixture and provenance corrections. Updater/service authentication is not changed; installed acceptance remains required.'},indent=2)+'\n')
    subprocess.run(['git','diff','--check'],cwd=ROOT,check=True)
    print('Applied remaining fixture corrections:',len(changes))
if __name__=='__main__':main()
