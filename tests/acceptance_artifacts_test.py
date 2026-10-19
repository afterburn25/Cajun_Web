"""Offline temporary-file regressions for actual CI artifact helpers. No network/DB."""
import argparse
import copy
import hashlib
import importlib.util
import io
import json
import re
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
from unittest.mock import patch
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('acceptance_artifacts', ROOT / 'tools/acceptance_artifacts.py')
a = importlib.util.module_from_spec(spec); spec.loader.exec_module(a)
checks = []


def check(name, condition):
    checks.append({'name': name, 'passed': bool(condition)})
    if not condition: raise AssertionError(name)


def deny(name, callback):
    try: callback()
    except (ValueError, OSError, KeyError, TypeError, zipfile.BadZipFile): check(name, True)
    else: check(name, False)


def blob(path, data=b'public synthetic bytes'):
    path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(data)


def context():
    return {'schema': 1, 'environment': {'php':'8.3','database':'mariadb-10.11'}, 'repository': 'synthetic/repository', 'run_id': '123', 'attempt': '1',
            'ci_sha': 'a' * 40, 'source_sha': 'b' * 40, 'tree': 'c' * 40,
            'workflow_sha256': 'd' * 64, 'helper_sha256': 'e' * 64, 'reports_sha256': 'f' * 64, 'contract_sha256': '1' * 64}


def packages(root, ctx):
    dist = root / 'dist'; dist.mkdir(parents=True)
    for kind in ['FULL-PROJECT', 'COMPLETE-UPLOAD', 'UPDATE']:
        p = dist / ('CajunWebPro-synthetic-' + kind + '.zip')
        member = 'index.php' if kind == 'COMPLETE-UPLOAD' else 'public/index.php'
        with zipfile.ZipFile(p, 'w') as z: z.writestr(member, b'<?php /* synthetic public */')
        a.write(p.with_suffix('.manifest.json'), {'source_commit': ctx['ci_sha'], 'artifact': p.name,
                'sha256': a.digest(p), 'files': {member: hashlib.sha256(b'<?php /* synthetic public */').hexdigest()}})
        blob(p.with_suffix('.directories.txt'), b'public\n')
    a.write(dist / 'manifest.json', {'source_commit': ctx['ci_sha']})
    blob(dist / 'START-HERE.txt', b'Synthetic package')
    entries = sorted(dist.iterdir())
    blob(dist / 'SHA256SUMS.txt', ''.join(a.digest(p) + '  ' + p.name + '\n' for p in entries).encode())


def named(path, passed=True):
    a.write(path, {'passed': passed, 'checks': [{'name': 'Synthetic exact check', 'passed': passed}]})


def synthetic_baseline():
    # Unit protocol bytes only. Actual upgrade acceptance uses the pinned published asset.
    stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        for name, body in {'.htaccess': b'Deny public listing', 'includes/public.php': b'public synthetic bytes'}.items():
            archive.writestr(zipfile.ZipInfo(name, date_time=(2026, 10, 12, 0, 0, 0)), body)
    return stream.getvalue()


def fixtures(root, ctx):
    packages(root, ctx)
    for n in a.FIXTURES:
        blob(root / 'test-runtime' / n / '.htaccess', b'Deny public listing')
        blob(root / 'test-runtime' / n / 'includes/public.php')
    for n in a.FIXTURE_JSON: a.write(root / 'test-runtime' / n, {'public': True})
    blob(root / 'test-runtime' / a.PUBLISHED_BASELINE, synthetic_baseline())
    for n in a.BUILD_QA:
        if n.endswith('.json'): named(root / 'qa' / n)
        else: blob(root / 'qa' / n)


def outcomes(lane): return {k: 'success' for k in a.STEP_KEYS[lane]}


def valid_port_context(workflow):
    for job in ('native-and-browser', 'apache'):
        match=re.search(r'^  '+job+r':\n(.*?)(?=^  [a-z][a-z-]*:|\Z)',workflow,re.M|re.S)
        if not match:return False
        body=match.group(1)
        env=body.split('    env:\n',1)[1].split('    steps:\n',1)[0]
        if 'job.services' in env or 'CWP_TEST_PORT:' in env:return False
        setup=body.split('        id: setup\n',1)[1].split('      - name:',1)[0]
        if 'CWP_ASSIGNED_PORT: ${{ job.services.mariadb.ports[3306] }}' not in setup:return False
        if 'printf \'CWP_TEST_PORT=%s\\n\' "$CWP_ASSIGNED_PORT" >> "$GITHUB_ENV"' not in setup:return False
    return True


def run(tmp):
    def remove_partition(path):
        resolved=path.resolve();require_parent=tmp.resolve()
        assert resolved.is_relative_to(require_parent) and resolved.name=='native-browser'
        shutil.rmtree(resolved)
    ctx = context(); source = tmp / 'source'; fixtures(source, ctx)
    # Exercise the actual entry points and GitHub output file, including the
    # numeric suffix used by both immutable artifact-manifest outputs.
    cli_root=tmp/'cli-source';fixtures(cli_root,ctx);job_output=tmp/'github-output'
    with patch.object(a,'ROOT',cli_root),patch.object(a,'identity',return_value=ctx),patch.dict(a.os.environ,{'GITHUB_OUTPUT':str(job_output),'CWP_STEP_OUTCOMES':json.dumps(outcomes('build')),'CWP_INPUT_SHA256':''}):
        with patch.object(sys,'argv',['acceptance_artifacts.py','pack-inputs']):a.main()
        expected='sha256='+a.digest(cli_root/'.acceptance/inputs/inputs.json')+'\n'
        check('Actual pack-inputs entry point writes its sha256 manifest digest to GitHub output',job_output.read_text()==expected)
        with patch.object(sys,'argv',['acceptance_artifacts.py','seal','--lane','build']):a.main()
        expected+='sha256='+a.digest(cli_root/'.acceptance/partition/partition.json')+'\n'
        check('Actual seal entry point appends its sha256 manifest digest to GitHub output',job_output.read_text()==expected)
        for label,name in [('empty',''),('digit prefix','9sha'),('line break','sha256\ninjected'),('assignment','sha256=x')]:
            deny('Job output refuses '+label+' name',lambda n=name:a.output(n,'a'*64))
        for label,value in [('line feed','a\ninjected=yes'),('carriage return','a\rinjected=yes')]:
            deny('Job output refuses '+label+' injection',lambda v=value:a.output('sha256',v))
        check('Rejected output fields never append to the GitHub output file',job_output.read_text()==expected)
    transfer = tmp / 'input'; input_sha = a.create_inputs(source, ctx, transfer)
    obj = a.verify_bundle(transfer, 'inputs', ctx, input_sha)
    check('Input binds all27 public fixture trees plus exact package/checksum files', len(a.FIXTURES) == 27 and 'dist/SHA256SUMS.txt' in obj['files'])
    for lane in a.LANES[1:]:
        target = tmp / ('receive-' + lane); target.mkdir()
        (target / 'qa').mkdir()
        a.receive_inputs(transfer, target, ctx, input_sha, lane)
        check('Exact input materializes safely for ' + lane, (target / 'test-runtime/legacy/.htaccess').read_bytes() == b'Deny public listing')
        check('Only native/browser inherits build QA ' + lane, (target / 'qa/build-results.txt').exists() == (lane == 'native-browser'))
        check('Each child saves same-attempt immutable input receipt ' + lane, a.load(target / '.acceptance/input-receipt.json') == {'identity': ctx, 'input_sha256': input_sha, 'lane': lane})
        deny('Input materialization never overwrites an earlier child ' + lane, lambda t=target,l=lane: a.receive_inputs(transfer,t,ctx,input_sha,l))
    for field, value in [('attempt', '2'), ('run_id', '124'), ('ci_sha', '1' * 40), ('source_sha', '2' * 40), ('tree', '3' * 40), ('repository', 'foreign/repo'), ('workflow_sha256', '4' * 64), ('reports_sha256', '5' * 64)]:
        changed = {**ctx, field: value}
        deny('Input refuses mismatched ' + field, lambda c=changed: a.verify_bundle(transfer, 'inputs', c, input_sha))
    for environment in ({'php':'8.2','database':'mariadb-10.11'},{'php':'8.3','database':'mysql-8.4'}):
        changed={**ctx,'environment':environment}
        deny('Input refuses mismatched runtime '+json.dumps(environment,sort_keys=True),lambda c=changed:a.verify_bundle(transfer,'inputs',c,input_sha))
    deny('Input refuses missing expected digest', lambda: a.verify_bundle(transfer, 'inputs', ctx, ''))
    deny('Input refuses changed manifest bytes', lambda: a.verify_bundle(transfer, 'inputs', ctx, '0' * 64))
    broken = tmp / 'broken'; shutil.copytree(transfer, broken)
    archive = broken / 'inputs.zip'; data = archive.read_bytes(); archive.write_bytes(data[:-1] + bytes([data[-1] ^ 1]))
    deny('Input refuses corrupt outer bytes before extraction', lambda: a.verify_bundle(broken, 'inputs', ctx, input_sha))
    duplicate=tmp/'duplicate.json';blob(duplicate,b'{"identity":1,"identity":2}')
    deny('Duplicate JSON keys cannot hide competing identities',lambda:a.load(duplicate))
    bad_source=tmp/'private-source';shutil.copytree(source,bad_source);blob(bad_source/'test-runtime/legacy/config.php',b'private synthetic')
    deny('Actual input packing rejects private configuration inside public tree',lambda:a.create_inputs(bad_source,ctx,tmp/'private-input'))
    changed=tmp/'changed-member';shutil.copytree(transfer,changed)
    p=changed/'inputs.zip'
    with zipfile.ZipFile(p) as z:members={n:z.read(n) for n in z.namelist()}
    members['test-runtime/legacy/.htaccess']=b'altered public member'
    with zipfile.ZipFile(p,'w') as z:
        for n,data in members.items():z.writestr(n,data)
    manifest=a.load(changed/'inputs.json');manifest['archive']=a.file_facts(p);a.write(changed/'inputs.json',manifest)
    deny('Rehashed outer ZIP still refuses member content mismatch',lambda:a.verify_bundle(changed,'inputs',ctx,a.digest(changed/'inputs.json')))
    del members['test-runtime/legacy/.htaccess']
    with zipfile.ZipFile(p,'w') as z:
        for n,data in members.items():z.writestr(n,data)
    manifest['archive']=a.file_facts(p);a.write(changed/'inputs.json',manifest)
    deny('Rehashed ZIP still refuses missing member',lambda:a.verify_bundle(changed,'inputs',ctx,a.digest(changed/'inputs.json')))
    for name in ['../escaped', '/absolute', 'a/../b', 'a\\b', 'C:/outside', 'a//b', './a', 'a/./b', 'a\x00b']:
        deny('Noncanonical member refused ' + repr(name), lambda n=name: a.safe_name(n))
    for name in ['test-runtime/private/key.txt', 'test-runtime/config-fixture.php', 'test-runtime/legacy/config.php', 'test-runtime/legacy/.installed.php', 'test-runtime/legacy/server.key', 'qa/session.php', 'test-runtime/build-guards-private/fake.php']:
        try: allowed = a.input_allowed(name)
        except ValueError: allowed = False
        check('Private/unapproved input refused ' + name, not allowed)
    # Test malformed ZIPs with internally matching outer metadata: member rules must still reject.
    for title, names, mode in [('duplicate', ['same','same'], None), ('case collision', ['Same','same'], None), ('traversal', ['../outside'], None), ('symlink', ['link'], 0o120777)]:
        p = tmp / (title.replace(' ', '-') + '.zip'); files = {}
        import warnings
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', UserWarning)
            with zipfile.ZipFile(p, 'w') as z:
                for n in names:
                    info = zipfile.ZipInfo(n)
                    if mode: info.external_attr = mode << 16
                    z.writestr(info, b'x'); files[n] = {'size': 1, 'sha256': hashlib.sha256(b'x').hexdigest()}
        deny('ZIP refuses ' + title + ' independently of envelope hash', lambda p=p,f=files: a.inspect_archive(p, f))
    # A complete four-partition synthetic run exercises the real seal and aggregate paths.
    incoming = tmp / 'partitions'; bindings = {}; lane_roots = {}
    for i,lane in enumerate(a.LANES):
        root = tmp / ('job-' + lane); root.mkdir(); lane_roots[lane] = root
        if lane == 'build': shutil.copytree(source / 'dist', root / 'dist'); shutil.copytree(source / 'qa', root / 'qa')
        elif lane == 'native-browser': shutil.copytree(source / 'qa', root / 'qa'); named(root / 'qa/http-results.json'); blob(root / 'qa/actual-view.png', b'exact image bytes')
        elif lane == 'recovery': named(root / 'qa/structure-results.json')
        else: named(root / 'qa/apache-results.json')
        d = a.seal(root,ctx,incoming/lane,lane,outcomes(lane),input_sha)
        bindings[lane] = {'result':'success','artifact_id':str(i+1),'sha256':d,**({'input_sha256':input_sha} if lane=='build' else {}),**({'checkpoint_id':'9','checkpoint_digest':'6'*64} if lane=='native-browser' else {})}
    expected = {'browser':['build-guards-results.json','parallel-helper-tests.json','http-results.json','build-environment-results.json'],
                'final':['build-guards-results.json','parallel-helper-tests.json','http-results.json','structure-results.json','apache-results.json','build-environment-results.json']}
    count = 0
    def combined(b=bindings, inc=incoming, cp=False, exp=expected):
        nonlocal count
        count += 1; root = tmp / ('aggregate-' + str(count)); root.mkdir()
        result = a.aggregate(root,ctx,inc,b,exp,cp)
        return result, root
    result, target = combined()
    check('Complete exact four-partition run assembles all named reports', result['complete'] and result['full_partition_checks_passed'] and set(result['reports']) == set(expected['final']))
    check('Final carries exact browser screenshot bytes', (target/'qa/actual-view.png').read_bytes()==b'exact image bytes')
    check('Checkpoint is the same immutable QA snapshot used by final', all(p.read_bytes()==(target/'qa'/p.name).read_bytes() for p in (lane_roots['native-browser']/'.acceptance/checkpoint/qa').iterdir()))
    check('Final packages remain exact original build', all(a.digest(p)==a.digest(target/'dist'/p.name) for p in (source/'dist').iterdir()))
    check('Final records every immutable partition ID and digest', all(result['partitions'][n]['artifact_id']==bindings[n]['artifact_id'] and result['partitions'][n]['manifest_sha256']==bindings[n]['sha256'] for n in a.LANES))
    b=copy.deepcopy(bindings)
    for lane in ['recovery','apache']:b[lane]={'result':'skipped','artifact_id':'','sha256':''}
    result,_=combined(b,cp=True)
    check('Deliberate checkpoint has complete limited evidence, never full acceptance', result['complete'] and not result['full_partition_checks_passed'] and result['mode']=='checkpoint')
    b['apache']['result']='cancelled';result,_=combined(b,cp=True)
    check('Checkpoint does not disguise cancellation as intentional skip', not result['complete'])
    for lane in a.LANES:
        for state in ['failure','cancelled','skipped']:
            b=copy.deepcopy(bindings);b[lane]['result']=state;result,root=combined(b)
            check('Non-successful '+lane+' '+state+' remains incomplete with partial evidence', not result['complete'] and (root/'qa/http-results.json').exists())
        b=copy.deepcopy(bindings);b[lane]['artifact_id']='';result,root=combined(b)
        check('Missing '+lane+' artifact cannot become success', not result['complete'] and lane not in result['partitions'])
    b=copy.deepcopy(bindings);b['native-browser']['sha256']='0'*64;result,root=combined(b)
    check('Corrupt partition digest is refused while other evidence survives', not result['complete'] and 'native-browser' not in result['partitions'] and (root/'qa/structure-results.json').exists())
    b=copy.deepcopy(bindings);b['build']['input_sha256']='0'*64;result,_=combined(b)
    check('Different input generation cannot mix into final', not result['complete'] and set(result['partitions'])=={'build'})
    b=copy.deepcopy(bindings);b['apache']['artifact_id']=b['recovery']['artifact_id'];result,_=combined(b)
    check('Duplicate artifact IDs never masquerade as independent jobs', not result['complete'])
    b=copy.deepcopy(bindings);b['native-browser']['checkpoint_id']='';result,_=combined(b)
    check('Missing checkpoint upload cannot pass final acceptance', not result['complete'] and 'native-browser' in result['partitions'])
    for title, change in [('cross-attempt',lambda o:o['identity'].update(attempt='2')),('missing steps',lambda o:o.update(steps={})),('wrong lane',lambda o:o.update(lane='build'))]:
        inc=tmp/('changed-'+title);shutil.copytree(incoming,inc);path=inc/'native-browser/partition.json';o=a.load(path);change(o);a.write(path,o)
        b=copy.deepcopy(bindings);b['native-browser']['sha256']=a.digest(path);result,_=combined(b,inc)
        check('Internally hashed '+title+' partition is refused', not result['complete'] and 'native-browser' not in result['partitions'])
    # Re-seal an explicitly failed report; truthful output is preserved rather than discarded.
    failed_root=tmp/'failed-native';shutil.copytree(lane_roots['native-browser'],failed_root);named(failed_root/'qa/http-results.json',False)
    inc=tmp/'failed-partitions';shutil.copytree(incoming,inc);remove_partition(inc/'native-browser')
    # The copied immutable checkpoint is not an input to the new synthetic seal.
    # Use a different snapshot root by omitting the prior metadata tree from copies.
    old=failed_root/'.acceptance/checkpoint'
    if old.exists():
        assert old.resolve().is_relative_to(tmp.resolve());shutil.rmtree(old)
    b=copy.deepcopy(bindings);b['native-browser']['sha256']=a.seal(failed_root,ctx,inc/'native-browser','native-browser',outcomes('native-browser'),input_sha)
    result,root=combined(b,inc)
    check('Named failure beats successful job labels and remains available', not result['complete'] and result['reports']['http-results.json']['failed_names']==['Synthetic exact check'] and a.load(root/'qa/http-results.json')['passed'] is False)
    exp=copy.deepcopy(expected);exp['final'].append('missing-native-results.json');result,_=combined(exp=exp)
    check('Missing expected named report cannot pass', not result['complete'])
    exp=copy.deepcopy(expected);exp['final'].remove('apache-results.json');result,_=combined(exp=exp)
    check('Unexpected named report forces expectation review', not result['complete'])
    # Shared build facts may repeat only byte-identically in native/browser.
    root=tmp/'collision-native';shutil.copytree(lane_roots['native-browser'],root);blob(root/'qa/build-results.txt',b'changed build')
    inc=tmp/'collision-parts';shutil.copytree(incoming,inc);remove_partition(inc/'native-browser')
    old=root/'.acceptance/checkpoint'
    if old.exists():
        assert old.resolve().is_relative_to(tmp.resolve());shutil.rmtree(old)
    b=copy.deepcopy(bindings);b['native-browser']['sha256']=a.seal(root,ctx,inc/'native-browser','native-browser',outcomes('native-browser'),input_sha)
    result,_=combined(b,inc)
    check('Changed duplicate build evidence cannot silently overwrite', not result['complete'] and 'native-browser' not in result['partitions'])
    inc=tmp/'case-alias-parts';shutil.copytree(incoming,inc);b=copy.deepcopy(bindings)
    for lane,name in [('native-browser','Apache-error.log'),('apache','apache-error.log')]:
        root=tmp/('case-alias-'+lane);shutil.copytree(lane_roots[lane]/'qa',root/'qa');blob(root/'qa'/name,b'case collision')
        target=inc/lane;assert target.resolve().is_relative_to(tmp.resolve());shutil.rmtree(target)
        b[lane]['sha256']=a.seal(root,ctx,target,lane,outcomes(lane),input_sha)
    result,_=combined(b,inc)
    check('Cross-partition case alias is refused by explicit global ownership before extraction',
          not result['complete'] and 'apache' not in result['partitions'] and 'apache: Case-colliding evidence path' in result['errors'])
    root=tmp/'integer-report';path=root/'qa/counts.json'
    rows=[{'name':'First count','passed':True},{'name':'Second count','passed':True}]
    a.write(path,{'passed':2,'checks':rows,'failed':0})
    check('Existing integer aggregate report accepts exact passing execution count',a.reports(root)['counts.json']['passed'])
    a.write(path,{'passed':1,'checks':rows,'failed':0})
    check('Incorrect integer aggregate count cannot mask named outcomes',not a.reports(root)['counts.json']['passed'])
    a.write(path,{'passed':2,'checks':rows,'error':'synthetic failure'})
    check('Integer aggregate with failure detail remains failed',not a.reports(root)['counts.json']['passed'])
    for lane,name in [('apache','qa/http-results.json'),('recovery','qa/apache-results.json'),('native-browser','qa/structure-results.json'),('build','test-runtime/private/key.txt')]:
        check('Evidence ownership refuses '+lane+' '+name, not a.evidence_allowed(name,lane))
    snapshot_root=tmp/'access-snapshot-report';snapshot_name='stackcp-access-read-snapshot-native-results.json';seed_name='stackcp-access-read-snapshot-seed.json'
    named(snapshot_root/'qa'/snapshot_name);a.write(snapshot_root/'qa'/seed_name,{'passed':True,'scope':'Synthetic metadata, not acceptance','prefix':'sapr_','linked_packages':21,'local_assignments':20})
    check('Finite access snapshot seed stays diagnostic metadata beside its required report',set(a.reports(snapshot_root))=={snapshot_name} and a.evidence_allowed('qa/'+seed_name,'native-browser') and not a.input_allowed('qa/'+seed_name))
    named(snapshot_root/'qa'/snapshot_name,False)
    check('Successful seed cannot mask a failed access snapshot native report',not a.reports(snapshot_root)[snapshot_name]['passed'])
    from delivery_evidence_cases import run as delivery_evidence_cases
    delivery_evidence_cases(a, ROOT, tmp/'delivery-evidence', check, deny, context, fixtures, outcomes)
    from acceptance_environment_test import run as environment_checks
    environment_checks(a,check,deny,tmp)
    # Retain reviewed driver bytes and the complete predecessor command sequence.
    contract,workflow,subdomain=subdomain_add_predecessor(ROOT)
    for path,expected_hash in contract['unchanged_test_sha256'].items():
        check('Original test source retained '+path, hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
    native=workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    native_commands=[line.strip() for line in native.splitlines()]
    import_contract=contract['provider_catalog_import'];import_commands=import_contract['additions']
    check('Import additions retain the complete selected-runtime recipe',len(native_commands)==152 and native_commands==contract['native_commands'])
    import_position=native_commands.index(import_contract['additions_after'])+1
    check('Three import commands occur once after the existing catalog native fixture',len(import_commands)==len(set(import_commands))==3 and native_commands[import_position:import_position+3]==import_commands and all(native_commands.count(command)==1 for command in import_commands))
    native_commands=[command for command in native_commands if command not in import_commands]
    check('Import additions preserve the exact149 predecessor commands',len(native_commands)==import_contract['predecessor_native_count']==149 and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==import_contract['predecessor_native_sha256'])
    for path,expected_hash in import_contract['source_sha256'].items():
        check('Import fixture source retained '+path,hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
    access_snapshot=contract['stackcp_access_snapshot_native_command_baseline'];access_snapshot_command='"$PHP_BINARY" tests/stackcp_access_read_snapshot_native.php'
    check('All149 native commands equal the exact current selected-runtime recipe',len(native_commands)==149 and native_commands==[command for command in contract['native_commands'] if command not in import_commands])
    access_snapshot_position=native_commands.index(access_snapshot['additions_after'])+1
    check('Access snapshot comparison occurs exactly once after prerequisite snapshot',access_snapshot['additions']==[access_snapshot_command] and native_commands[access_snapshot_position:access_snapshot_position+1]==[access_snapshot_command] and native_commands.count(access_snapshot_command)==1)
    native_commands=[command for command in native_commands if command!=access_snapshot_command]
    check('Access snapshot addition retains exact148 source-bound commands',len(native_commands)==access_snapshot['count']==148 and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==access_snapshot['sha256'])
    check('All148 native commands retain exact selected-runtime order and arguments', native_commands==[command for command in contract['native_commands'] if command!=access_snapshot_command and command not in import_commands])
    predecessor=contract['release_environment_predecessor']
    native_commands=['php8.3 '+command[len('"$PHP_BINARY" '):] if command.startswith('"$PHP_BINARY" ') else command for command in native_commands]
    check('Only explicit executable selection changes all148 predecessor commands',len(native_commands)==predecessor['count']==148 and native_commands==predecessor['commands'] and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==predecessor['sha256'])
    snapshot=contract['stackcp_snapshot_native_command_baseline'];snapshot_additions=snapshot['additions']
    snapshot_position=native_commands.index(snapshot['additions_after'])+1
    check('Snapshot native comparison is inserted exactly once after StackCP access',
          snapshot_additions==['php8.3 tests/stackcp_read_snapshot_native.php']
          and native_commands[snapshot_position:snapshot_position+1]==snapshot_additions
          and native_commands.count(snapshot_additions[0])==1)
    native_commands=[command for command in native_commands if command not in snapshot_additions]
    check('Snapshot addition retains all147 predecessor native commands exactly',
          len(native_commands)==snapshot['count']==147
          and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==snapshot['sha256'])
    cloud=contract['cloud_assignment_native_command_baseline'];cloud_additions=cloud['additions']
    cloud_preserved=[command for command in native_commands if command not in cloud_additions]
    check('All139 pre-cloud-assignment native commands retain exact order and arguments',
          len(cloud_preserved)==cloud['count']==139 and hashlib.sha256(json.dumps(cloud_preserved,separators=(',',':')).encode()).hexdigest()==cloud['sha256'])
    cloud_position=native_commands.index(cloud['additions_after'])+1
    check('Eight cloud assignment commands occur once immediately after cloud confirmation',
          len(cloud_additions)==len(set(cloud_additions))==8 and native_commands[cloud_position:cloud_position+8]==cloud_additions
          and all(native_commands.count(command)==1 for command in cloud_additions))
    access=contract['stackcp_access_command_baseline'];access_additions=access['additions']
    access_preserved=[command for command in cloud_preserved if command not in access_additions]
    check('All131 pre-StackCP-access commands retain exact order and arguments',
          len(access_preserved)==access['count']==131 and hashlib.sha256(json.dumps(access_preserved,separators=(',',':')).encode()).hexdigest()==access['sha256'])
    access_position=native_commands.index(access['additions_after'])+1
    check('Eight StackCP access commands occur once after prerequisite native checks',
          len(access_additions)==len(set(access_additions))==8 and native_commands[access_position:access_position+8]==access_additions
          and all(native_commands.count(command)==1 for command in access_additions))
    registration=contract['registration_draft_command_baseline'];registration_additions=registration['additions']
    registration_preserved=[command for command in access_preserved if command not in registration_additions]
    check('All126 pre-registration-draft commands retain exact order and arguments',
          len(registration_preserved)==registration['count']==126 and hashlib.sha256(json.dumps(registration_preserved,separators=(',',':')).encode()).hexdigest()==registration['sha256'])
    registration_position=native_commands.index(registration['additions_after'])+1
    check('Five registration contract draft pure and native commands occur once after renewal dispatch',
          len(registration_additions)==len(set(registration_additions))==5 and native_commands[registration_position:registration_position+5]==registration_additions
          and all(native_commands.count(command)==1 for command in registration_additions))
    stackcp=contract['stackcp_native_command_baseline'];stackcp_additions=stackcp['additions']
    stackcp_preserved=[command for command in registration_preserved if command not in stackcp_additions]
    check('All119 pre-StackCP native commands retain exact order and arguments',
          len(stackcp_preserved)==stackcp['count']==119 and hashlib.sha256(json.dumps(stackcp_preserved,separators=(',',':')).encode()).hexdigest()==stackcp['sha256'])
    stackcp_position=native_commands.index(stackcp['additions_after'])+1
    check('Seven StackCP reports cover pure handoff prerequisites views and native once',
          len(stackcp_additions)==len(set(stackcp_additions))==7 and native_commands[stackcp_position:stackcp_position+7]==stackcp_additions
          and all(native_commands.count(command)==1 for command in stackcp_additions))
    baseline=contract['native_command_baseline'];additions=baseline['additions']
    directory_baseline=contract['directory_control_command_baseline'];directory_additions=directory_baseline['additions']
    preserved=[command for command in stackcp_preserved if command not in additions and command not in directory_additions]
    check('All105 predecessor native commands retain exact order and arguments',
          len(preserved)==baseline['count']==105 and hashlib.sha256(json.dumps(preserved,separators=(',',':')).encode()).hexdigest()==baseline['sha256'])
    position=native_commands.index(baseline['additions_after'])+1
    check('Six renewal native commands occur once immediately after agreement checks',
          len(additions)==len(set(additions))==6 and native_commands[position:position+6]==additions
          and all(native_commands.count(command)==1 for command in additions))
    directory_position=native_commands.index(directory_baseline['additions_after'])+1
    check('Eight directory control commands occur once immediately after Force SSL controls',
          len(directory_additions)==len(set(directory_additions))==8 and native_commands[directory_position:directory_position+8]==directory_additions
          and all(native_commands.count(command)==1 for command in directory_additions))
    check('Public fixture preparation runs only once in the build job', workflow.count('python3 tests/prepare_legacy.py')==1)
    check('Only one exact source package build remains', workflow.count('python3 tools/build.py --commit')==1)
    check('Assigned ports use permitted step context and GITHUB_ENV in both DB jobs', valid_port_context(workflow))
    for job in ('native-and-browser','apache'):
        start=workflow.index('  '+job+':\n');pos=workflow.index('      CWP_TEST_HOST: 127.0.0.1\n',start)+len('      CWP_TEST_HOST: 127.0.0.1\n')
        original_bug=workflow[:pos]+'      CWP_TEST_PORT: ${{ job.services.mariadb.ports[3306] }}\n'+workflow[pos:]
        check('Original invalid job-level service context is rejected for '+job, not valid_port_context(original_bug))

    subdomain_add_checks(tmp,subdomain)

def subdomain_add_inverse(data, delta):
    if hashlib.sha256(data).hexdigest()!=delta['after_sha256']:
        raise ValueError('Subdomain add current source changed')
    text=data.decode('utf-8')
    for item in reversed(delta['replacements']):
        if text.count(item['after'])!=1:raise ValueError('Subdomain add inverse is not unique')
        text=text.replace(item['after'],item['before'],1)
    result=text.encode('utf-8')
    if hashlib.sha256(result).hexdigest()!=delta['before_sha256']:
        raise ValueError('Subdomain add predecessor changed')
    return result


def subdomain_add_predecessor(root):
    current=a.load(root/'tools/acceptance-contract.json');launch=current['subdomain_add']
    contract=copy.deepcopy(current);del contract['subdomain_add']
    commands=launch['native_additions'];position=contract['native_commands'].index(launch['native_after'])+1
    if contract['native_commands'][position:position+7]!=commands or any(contract['native_commands'].count(c)!=1 for c in commands):
        raise ValueError('Subdomain add native recipe changed')
    del contract['native_commands'][position:position+7]
    for path,old_hash in launch['predecessor_test_pins'].items():contract['unchanged_test_sha256'][path]=old_hash
    if hashlib.sha256(json.dumps(contract,indent=2).encode()+b'\n').hexdigest()!=launch['predecessor_contract_sha256']:
        raise ValueError('Subdomain add predecessor contract changed')
    workflow=subdomain_add_inverse((root/'.github/workflows/application-tests.yml').read_bytes().replace(b'\r\n',b'\n'),launch['workflow_delta']).decode()
    return contract,workflow,launch


def subdomain_add_predecessor_source(root,path,launch):
    data=root.joinpath(path).read_bytes().replace(b'\r\n',b'\n')
    delta=launch['source_deltas'].get(path)
    return subdomain_add_inverse(data,delta) if delta else data


def subdomain_add_checks(tmp,launch):
    current=a.load(ROOT/'tools/acceptance-contract.json');registry=a.load(ROOT/'tools/acceptance-reports.json')
    check('Subdomain add retains exact152 original commands before seven additions',len(current['native_commands'])==159 and len(current['native_commands'])-len(launch['native_additions'])==152)
    for path,digest in launch['source_sha256'].items():
        check('Subdomain add current source bound '+path,hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==digest)
    for path,delta in launch['source_deltas'].items():
        check('Subdomain add exact source inverse '+path,hashlib.sha256(subdomain_add_predecessor_source(ROOT,path,launch)).hexdigest()==delta['before_sha256'])
    reports=launch['reports'];old=copy.deepcopy(registry)
    check('Subdomain add has seven unique separately produced reports',len(reports)==len(set(reports))==7 and launch['screenshots']==[])
    for phase,total,prior in [('browser',171,164),('final',215,208)]:
        check('Subdomain add '+phase+' exact required report inventory',len(registry[phase])==total and len(set(registry[phase]))==total and all(registry[phase].count(name)==1 for name in reports))
        old[phase]=[name for name in registry[phase] if name not in reports]
        check('Subdomain add '+phase+' preserves every predecessor report',len(old[phase])==prior)
    check('Subdomain add report inverse is exact whole predecessor',hashlib.sha256(json.dumps(old,indent=2).encode()+b'\n').hexdigest()==launch['predecessor_reports_sha256'])
    workflow=(ROOT/'.github/workflows/application-tests.yml').read_text()
    check('Subdomain add native explicitly opts in without changing selected database environment',launch['native_additions'][-1]=='CWP_TEST_SUBDOMAIN_ADD=1 "$PHP_BINARY" tests/provider_subdomain_add_native.php' and valid_port_context(workflow))
    focused=(ROOT/launch['focused_path']).read_bytes().replace(b'\r\n',b'\n')
    prior_focused=subdomain_add_inverse(focused,launch['focused_delta'])
    check('Subdomain add preserves exact original focused workflow by finite inverse',hashlib.sha256(prior_focused).hexdigest()==launch['focused_delta']['before_sha256'])
    focused_text=focused.decode()
    check('Subdomain add reuses four existing PHP/database matrix jobs',"php: ['8.2', '8.4']" in focused_text and "image: 'mariadb:10.11'" in focused_text and "image: 'mysql:8.4'" in focused_text and 'timeout-minutes: 35' in focused_text and 'cancel-in-progress: false' in focused_text)
    for command in launch['native_additions']:
        expected=command.replace('"$PHP_BINARY"','php').replace(' -d extension=tokenizer -d extension=pdo','')
        check('Subdomain add focused exact producer '+expected,focused_text.count(expected)==1)
    for name in reports:
        check('Subdomain add focused always retains exact named output '+name,focused_text.count('            qa/'+name+'\n')==1 and 'if: always()' in focused_text)
    for label,data in [('missing addition',workflow.replace(launch['native_additions'][0],'')),('extra command',workflow+'\n# unreviewed\n'),('changed original recipe',workflow.replace('tests/license_native.php','tests/other_native.php'))]:
        deny('Subdomain add workflow inverse refuses '+label,lambda data=data:subdomain_add_inverse(data.encode(),launch['workflow_delta']))
    # Exercise the real seal/aggregate predicates with all required synthetic report names.
    # These files are deliberately synthetic helper fixtures, never runtime acceptance evidence.
    root=tmp/'subdomain-add';source=root/'source';ctx=context();fixtures(source,ctx)
    incoming=root/'partitions';bindings={};job_roots={};input_sha='5'*64
    for index,lane in enumerate(a.LANES):
        job=root/('job-'+lane);job.mkdir(parents=True);job_roots[lane]=job
        if lane=='build':shutil.copytree(source/'dist',job/'dist');shutil.copytree(source/'qa',job/'qa')
        elif lane=='native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA:named(job/'qa'/name)
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/'+name,lane):named(job/'qa'/name)
        digest=a.seal(job,ctx,incoming/lane,lane,outcomes(lane),input_sha)
        bindings[lane]={'result':'success','artifact_id':str(index+21),'sha256':digest,**({'input_sha256':input_sha} if lane=='build' else {}),**({'checkpoint_id':'29','checkpoint_digest':'6'*64} if lane=='native-browser' else {})}
    count=0
    def combined():
        nonlocal count
        count+=1
        return a.aggregate(root/('combined-'+str(count)),ctx,incoming,bindings,registry)
    result=combined()
    check('Subdomain add real aggregation requires all215 named reports',result['complete'] is True and set(result['reports'])==set(registry['final']))
    def reseal():
        job=job_roots['native-browser'];checkpoint=job/'.acceptance/checkpoint'
        if checkpoint.exists():
            assert checkpoint.resolve().is_relative_to(root.resolve());shutil.rmtree(checkpoint)
        partition=incoming/'native-browser'
        assert partition.resolve().is_relative_to(root.resolve());shutil.rmtree(partition)
        bindings['native-browser']['sha256']=a.seal(job,ctx,incoming/'native-browser','native-browser',outcomes('native-browser'),input_sha)
    for name in reports:
        path=job_roots['native-browser']/'qa'/name;path.unlink();reseal();result=combined()
        check('Subdomain add missing report cannot pass '+name,result['complete'] is False and any('Missing reports: '+name==error for error in result['errors']))
        named(path,False);reseal();result=combined()
        check('Subdomain add failed report cannot pass '+name,result['complete'] is False and result['reports'][name]['passed'] is False)
        named(path)
    named(job_roots['native-browser']/'qa/subdomain-add-extra-results.json');reseal();result=combined()
    check('Subdomain add unexpected report cannot pass',result['complete'] is False and 'Unexpected reports: subdomain-add-extra-results.json' in result['errors'])
    for name in reports:
        check('Subdomain add report belongs only to native-browser '+name,a.evidence_allowed('qa/'+name,'native-browser') and not any(a.evidence_allowed('qa/'+name,lane) for lane in ('build','apache','recovery')))
    check('Subdomain add private state remains excluded',not a.evidence_allowed('test-runtime/subdomain-add-private/state.json','native-browser') and not a.evidence_allowed('qa/subdomain-add-private/state.json','native-browser'))


# The predecessor's actual functions and predicates above stay byte-for-byte intact.
_run_before_client_subdomain = run


def client_subdomain_inverse(data, delta):
    if hashlib.sha256(data).hexdigest() != delta['after_sha256']:
        raise ValueError('Client subdomain current source changed')
    text = data.decode('utf-8')
    for item in reversed(delta['replacements']):
        if text.count(item['after']) != 1:
            raise ValueError('Client subdomain inverse is not unique')
        text = text.replace(item['after'], item['before'], 1)
    result = text.encode('utf-8')
    if hashlib.sha256(result).hexdigest() != delta['before_sha256']:
        raise ValueError('Client subdomain predecessor changed')
    return result


def client_subdomain_current():
    current = a.load(ROOT/'tools/acceptance-contract.json')
    section = current['client_subdomain_requests']
    # Authenticate every current shipping/producer/route byte before any historical
    # comparison substitutes its exact predecessor. Never silently accept old pins.
    for path, digest in section['source_sha256'].items():
        if hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() != digest:
            raise ValueError('Client subdomain current source mismatch: '+path)
    registry = a.load(ROOT/'tools/acceptance-reports.json')
    old = copy.deepcopy(current)
    del old['client_subdomain_requests']
    commands = section['native_additions']
    position = old['native_commands'].index(section['native_after'])+1
    if len(commands) != 9 or old['native_commands'][position:position+9] != commands or any(old['native_commands'].count(c) != 1 for c in commands):
        raise ValueError('Client subdomain native recipe changed')
    del old['native_commands'][position:position+9]
    for pin in section['repins']:
        cursor = old
        for key in pin['keys'][:-1]: cursor = cursor[key]
        key = pin['keys'][-1]
        if cursor[key] != pin['after']: raise ValueError('Client subdomain current pin changed')
        cursor[key] = pin['before']
    dump = lambda value: json.dumps(value, indent=2).encode()+b'\n'
    if hashlib.sha256(dump(old)).hexdigest() != section['predecessor_contract_sha256']:
        raise ValueError('Client subdomain predecessor contract changed')
    prior_registry = copy.deepcopy(registry)
    for phase in ('browser', 'final'):
        if any(prior_registry[phase].count(n) != 1 for n in section['reports']):
            raise ValueError('Client subdomain report is missing or duplicated')
        prior_registry[phase] = [n for n in prior_registry[phase] if n not in section['reports']]
    if hashlib.sha256(dump(prior_registry)).hexdigest() != section['predecessor_reports_sha256']:
        raise ValueError('Client subdomain predecessor reports changed')
    historical = {'tools/acceptance-contract.json': dump(old), 'tools/acceptance-reports.json': dump(prior_registry)}
    for path, delta in section['source_deltas'].items():
        historical[path] = client_subdomain_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n'), delta)
    return current, registry, section, historical


def client_subdomain_checks(tmp, current, registry, section, historical):
    check('Client subdomain preserves all159 prior native commands before nine additions', len(current['native_commands']) == 168 and len(json.loads(historical['tools/acceptance-contract.json'])['native_commands']) == 159)
    for path, digest in section['source_sha256'].items():
        check('Client subdomain current source bound '+path, hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest)
    for path, delta in section['source_deltas'].items():
        check('Client subdomain complete source inverse '+path, hashlib.sha256(historical[path]).hexdigest() == delta['before_sha256'])
        for label, changed in [('current bytes', (ROOT/path).read_bytes().replace(b'\r\n', b'\n')+b'\n'), ('predecessor hash', None)]:
            altered = copy.deepcopy(delta)
            if changed is None:
                changed = (ROOT/path).read_bytes().replace(b'\r\n', b'\n'); altered['before_sha256'] = '0'*64
            deny('Client subdomain inverse refuses '+label+' '+path, lambda changed=changed, altered=altered: client_subdomain_inverse(changed, altered))
    reports = section['reports']
    check('Client subdomain has nine separate mandatory reports and no captures', len(reports) == len(set(reports)) == 9 and section['screenshots'] == [])
    for phase, total, prior in [('browser', 180, 171), ('final', 224, 215)]:
        check('Client subdomain '+phase+' retains every original report in order', len(registry[phase]) == total and len(set(registry[phase])) == total and [n for n in registry[phase] if n not in reports] == json.loads(historical['tools/acceptance-reports.json'])[phase] and len(registry[phase])-len(reports) == prior)
    workflow = (ROOT/'.github/workflows/application-tests.yml').read_text()
    focused = (ROOT/section['focused_path']).read_text()
    for path, text in [('.github/workflows/application-tests.yml', workflow), (section['focused_path'], focused)]:
        prior = historical[path].decode()
        check('Client subdomain preserves all branch triggers and concurrency '+path, text.split('jobs:\n', 1)[0].split('paths:')[0] == prior.split('jobs:\n', 1)[0].split('paths:')[0] and text.split('concurrency:\n', 1)[1].split('jobs:\n', 1)[0] == prior.split('concurrency:\n', 1)[1].split('jobs:\n', 1)[0])
    check('Client subdomain retains focused four combinations timeout and no cancellation', "php: ['8.2', '8.4']" in focused and "image: 'mariadb:10.11'" in focused and "image: 'mysql:8.4'" in focused and 'timeout-minutes: 35' in focused and 'cancel-in-progress: false' in focused)
    check('Client subdomain retains mapped-port context and explicit isolated opt-in', valid_port_context(workflow) and section['native_additions'][-1] == 'CWP_TEST_SUBDOMAIN_REQUESTS=1 "$PHP_BINARY" tests/provider_subdomain_requests_native.php')
    for command in section['native_additions']:
        docker = command.replace('"$PHP_BINARY"', 'php').replace(' -d extension=pdo', '')
        check('Client subdomain focused exact producer '+docker, focused.count('              '+docker+'\n') == 1)
    for name in reports:
        check('Client subdomain focused preserves named output '+name, focused.count('            qa/'+name+'\n') == 1 and 'if: always()' in focused)
    # Actual artifact sealing and aggregation, using explicitly synthetic reports.
    root = tmp/'client-subdomain'; source = root/'source'; ctx = context(); fixtures(source, ctx)
    incoming = root/'partitions'; bindings = {}; jobs = {}; input_sha = '5'*64
    for index, lane in enumerate(a.LANES):
        job = root/('job-'+lane); job.mkdir(parents=True); jobs[lane] = job
        if lane == 'build': shutil.copytree(source/'dist', job/'dist'); shutil.copytree(source/'qa', job/'qa')
        elif lane == 'native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA: named(job/'qa'/name)
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/'+name, lane): named(job/'qa'/name)
        digest = a.seal(job, ctx, incoming/lane, lane, outcomes(lane), input_sha)
        bindings[lane] = {'result':'success', 'artifact_id':str(index+31), 'sha256':digest, **({'input_sha256':input_sha} if lane=='build' else {}), **({'checkpoint_id':'39','checkpoint_digest':'6'*64} if lane=='native-browser' else {})}
    seq = 0
    def combined():
        nonlocal seq
        seq += 1
        return a.aggregate(root/('combined-'+str(seq)), ctx, incoming, bindings, registry)
    def reseal():
        job = jobs['native-browser']
        for path in (job/'.acceptance/checkpoint', incoming/'native-browser'):
            if path.exists():
                assert path.resolve().is_relative_to(root.resolve()); shutil.rmtree(path)
        bindings['native-browser']['sha256'] = a.seal(job, ctx, incoming/'native-browser', 'native-browser', outcomes('native-browser'), input_sha)
    result = combined()
    check('Client subdomain actual aggregate requires all224 named reports', result['complete'] is True and set(result['reports']) == set(registry['final']))
    for name in reports:
        path = jobs['native-browser']/'qa'/name; path.unlink(); reseal(); result = combined()
        check('Client subdomain missing report refuses '+name, result['complete'] is False and 'Missing reports: '+name in result['errors'])
        named(path, False); reseal(); result = combined()
        check('Client subdomain failed report refuses '+name, result['complete'] is False and result['reports'][name]['passed'] is False)
        named(path)
        check('Client subdomain evidence owns only native-browser '+name, a.evidence_allowed('qa/'+name, 'native-browser') and not any(a.evidence_allowed('qa/'+name, lane) for lane in ('build','apache','recovery')))
    named(jobs['native-browser']/'qa/subdomain-requests-unexpected-results.json'); reseal(); result = combined()
    check('Client subdomain unexpected report cannot pass', result['complete'] is False and 'Unexpected reports: subdomain-requests-unexpected-results.json' in result['errors'])
    check('Client subdomain private fixture state is never exported', not a.evidence_allowed('test-runtime/subdomain-requests-private/state.json', 'native-browser') and not a.input_allowed('test-runtime/subdomain-requests-private/state.json'))


def run(tmp):
    current, registry, section, historical = client_subdomain_current()
    # Finite exact paths only, and all actual current pins were checked above.
    # The original test predicates execute unchanged against their whole preimages.
    originals = {str((ROOT/path).resolve()): data for path, data in historical.items()}
    read_bytes, read_text = Path.read_bytes, Path.read_text
    def historical_bytes(path):
        return originals.get(str(path.resolve()), None) if str(path.resolve()) in originals else read_bytes(path)
    def historical_text(path, *args, **kwargs):
        key = str(path.resolve())
        return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path, *args, **kwargs)
    with patch.object(Path, 'read_bytes', historical_bytes), patch.object(Path, 'read_text', historical_text):
        _run_before_client_subdomain(tmp)
    client_subdomain_checks(tmp, current, registry, section, historical)


_run_before_subdomain_browser = run


def subdomain_browser_inverse(data, delta):
    if hashlib.sha256(data).hexdigest() != delta['after_sha256']:
        raise ValueError('Subdomain browser current source mismatch')
    text = data.decode('utf-8')
    for item in reversed(delta['replacements']):
        if text.count(item['after']) != 1: raise ValueError('Subdomain browser ambiguous inverse')
        text = text.replace(item['after'], item['before'], 1)
    result = text.encode('utf-8')
    if hashlib.sha256(result).hexdigest() != delta['before_sha256']:
        raise ValueError('Subdomain browser predecessor mismatch')
    return result


def subdomain_browser_current():
    current = a.load(ROOT/'tools/acceptance-contract.json')
    section = current['client_subdomain_browser']
    # Authenticate current source before any predecessor substitution.
    for path, digest in section['source_sha256'].items():
        if hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() != digest:
            raise ValueError('Subdomain browser source changed: '+path)
    registry = a.load(ROOT/'tools/acceptance-reports.json')
    prior = copy.deepcopy(current); del prior['client_subdomain_browser']
    command = section['native_addition']; position = prior['native_commands'].index(section['native_after'])+1
    if prior['native_commands'][position] != command or prior['native_commands'].count(command) != 1:
        raise ValueError('Subdomain browser offline recipe changed')
    del prior['native_commands'][position]
    for pin in section['repins']:
        cursor = prior
        for key in pin['keys'][:-1]: cursor = cursor[key]
        key = pin['keys'][-1]
        if cursor[key] != pin['after']: raise ValueError('Subdomain browser current pin changed')
        cursor[key] = pin['before']
    dump = lambda value: json.dumps(value, indent=2).encode()+b'\n'
    if hashlib.sha256(dump(prior)).hexdigest() != section['predecessor_contract_sha256']:
        raise ValueError('Subdomain browser predecessor contract changed')
    old_reports = copy.deepcopy(registry)
    for phase in ('browser', 'final'):
        if any(old_reports[phase].count(name) != 1 for name in section['reports']):
            raise ValueError('Subdomain browser reports missing or duplicated')
        old_reports[phase] = [name for name in old_reports[phase] if name not in section['reports']]
    if hashlib.sha256(dump(old_reports)).hexdigest() != section['predecessor_reports_sha256']:
        raise ValueError('Subdomain browser predecessor reports changed')
    historical = {'tools/acceptance-contract.json':dump(prior), 'tools/acceptance-reports.json':dump(old_reports)}
    for path, delta in section['source_deltas'].items():
        historical[path] = subdomain_browser_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n'), delta)
    return current, registry, section, historical


def subdomain_browser_checks(tmp, current, registry, section, historical):
    check('Subdomain browser retains168 recipes and adds one offline handler',len(current['native_commands'])==169 and len(json.loads(historical['tools/acceptance-contract.json'])['native_commands'])==168)
    for path,digest in section['source_sha256'].items():
        check('Subdomain browser current source bound '+path,hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==digest)
    for path,delta in section['source_deltas'].items():
        check('Subdomain browser exact complete inverse '+path,hashlib.sha256(historical[path]).hexdigest()==delta['before_sha256'])
        deny('Subdomain browser changed current bytes refuse '+path,lambda path=path,delta=delta:subdomain_browser_inverse((ROOT/path).read_bytes().replace(b'\r\n',b'\n')+b'\n',delta))
        changed=copy.deepcopy(delta);changed['before_sha256']='0'*64
        deny('Subdomain browser changed prior hash refuses '+path,lambda path=path,changed=changed:subdomain_browser_inverse((ROOT/path).read_bytes().replace(b'\r\n',b'\n'),changed))
    for phase,total in [('browser',182),('final',226)]:
        check('Subdomain browser preserves every prior '+phase+' report in order',len(registry[phase])==len(set(registry[phase]))==total and [n for n in registry[phase] if n not in section['reports']]==json.loads(historical['tools/acceptance-reports.json'])[phase])
    workflow=(ROOT/'.github/workflows/application-tests.yml').read_text()
    before=historical['.github/workflows/application-tests.yml'].decode()
    check('Subdomain browser preserves full workflow triggers permissions concurrency and environments',workflow.split('jobs:\n',1)[0]==before.split('jobs:\n',1)[0])
    check('Subdomain browser offline handler command is exact and once',workflow.count('          '+section['native_addition']+'\n')==1 and section['native_addition']=='python -B tests/subdomain_requests_http_contract.py --report qa/subdomain-requests-http-contract-results.json')
    check('Subdomain browser keeps two distinct required reports',section['reports']==['subdomain-requests-http-contract-results.json','subdomain-requests-http-browser-results.json'])
    captures=section['screenshots']
    expected=[f'Subdomain-Requests-{state}-{size}.png' for state in ['Client-Pending','Owner-Review','Client-Observed'] for size in ['Desktop','Mobile']]
    check('Subdomain browser has exact six declared captures',captures==expected)

    # Execute the actual caller's result predicate in isolation. These are synthetic
    # files for guard behavior, not application screenshots or browser acceptance.
    import ast as subdomain_ast
    callback_tree=subdomain_ast.parse((ROOT/'tests/provider_subdomain_requests_cases.py').read_text())
    callback_constants={n.targets[0].id:subdomain_ast.literal_eval(n.value) for n in callback_tree.body if isinstance(n,subdomain_ast.Assign) and len(n.targets)==1 and isinstance(n.targets[0],subdomain_ast.Name) and n.targets[0].id in {'CAPTURES','REPORT'}}
    check('Subdomain browser callback declares the exact required capture filenames',list(callback_constants['CAPTURES'])==captures)
    check('Subdomain browser callback declares the exact required report',callback_constants['REPORT']=='subdomain-requests-http-browser-results.json')
    caller_tree=subdomain_ast.parse((ROOT/'tests/browser_acceptance.py').read_text())
    label='Installed client subdomain workflow reports passed outcomes and all six real captures'
    matches=[n for n in subdomain_ast.walk(caller_tree) if isinstance(n,subdomain_ast.Call) and isinstance(n.func,subdomain_ast.Name) and n.func.id=='check' and len(n.args)==2 and isinstance(n.args[0],subdomain_ast.Constant) and n.args[0].value==label]
    check('Subdomain browser actual caller has one final result predicate',len(matches)==1)
    predicate=compile(subdomain_ast.Expression(matches[0].args[1]),'<actual subdomain browser result predicate>','eval')
    qa=tmp/'subdomain-browser-caller';qa.mkdir()
    png=b'\x89PNG\r\n\x1a\nSynthetic guard bytes, not captured pixels'
    for name in captures:(qa/name).write_bytes(png)
    good={'passed':True,'checks':[{'name':'Synthetic caller predicate only','passed':True}],'captures':captures.copy()}
    def accepted(value):return eval(predicate,{'subdomain_browser_result':value,'subdomain_captures':captures,'QA':qa})
    check('Subdomain browser actual caller accepts complete typed result',accepted(good) is True)
    invalid=[('missing result',None),('truthy passed',{**good,'passed':1}),('empty checks',{**good,'checks':[]}),('failed check',{**good,'checks':[{'name':'failed','passed':False}]}),('blank name',{**good,'checks':[{'name':' ','passed':True}]}),('missing capture',{**good,'captures':captures[:-1]}),('duplicate capture',{**good,'captures':captures[:-1]+[captures[0]]}),('foreign capture',{**good,'captures':captures[:-1]+['foreign.png']})]
    for name,value in invalid:check('Subdomain browser actual caller refuses '+name,not accepted(value))
    target=qa/captures[0]
    target.unlink();check('Subdomain browser actual caller refuses absent file',not accepted(good))
    for name,data in [('empty file',b''),('non-PNG',b'not a PNG file'),('signature only',png[:8])]:
        target.write_bytes(data);check('Subdomain browser actual caller refuses '+name,not accepted(good))
    target.write_bytes(png)
    original_is_link=Path.is_symlink
    with patch.object(Path,'is_symlink',lambda path: True if path==target else original_is_link(path)):
        check('Subdomain browser actual caller refuses symlink predicate',not accepted(good))

    root=tmp/'subdomain-browser-artifacts';source=root/'source';ctx=context();fixtures(source,ctx)
    incoming=root/'partitions';bindings={};jobs={};input_sha='5'*64
    for index,lane in enumerate(a.LANES):
        job=root/('job-'+lane);job.mkdir(parents=True);jobs[lane]=job
        if lane=='build':shutil.copytree(source/'dist',job/'dist');shutil.copytree(source/'qa',job/'qa')
        elif lane=='native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA:named(job/'qa'/name)
            for name in captures:blob(job/'qa'/name,png+name.encode())
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/'+name,lane):named(job/'qa'/name)
        digest=a.seal(job,ctx,incoming/lane,lane,outcomes(lane),input_sha)
        bindings[lane]={'result':'success','artifact_id':str(index+51),'sha256':digest,**({'input_sha256':input_sha} if lane=='build' else {}),**({'checkpoint_id':'59','checkpoint_digest':'6'*64} if lane=='native-browser' else {})}
    seq=0
    def combine():
        nonlocal seq
        seq+=1;destination=root/('combined-'+str(seq))
        return destination,a.aggregate(destination,ctx,incoming,bindings,registry)
    def reseal():
        job=jobs['native-browser']
        for path in (job/'.acceptance/checkpoint',incoming/'native-browser'):
            if path.exists():
                assert path.resolve().is_relative_to(root.resolve());shutil.rmtree(path)
        bindings['native-browser']['sha256']=a.seal(job,ctx,incoming/'native-browser','native-browser',outcomes('native-browser'),input_sha)
    destination,result=combine()
    check('Subdomain browser aggregate requires all226 reports',result['complete'] is True and set(result['reports'])==set(registry['final']))
    for name in captures:check('Subdomain browser transfer preserves exact synthetic bytes '+name,(destination/'qa'/name).read_bytes()==png+name.encode())
    for name in section['reports']:
        path=jobs['native-browser']/'qa'/name;path.unlink();reseal();_,result=combine()
        check('Subdomain browser missing named report refuses '+name,result['complete'] is False and 'Missing reports: '+name in result['errors'])
        named(path,False);reseal();_,result=combine()
        check('Subdomain browser failed named report refuses '+name,result['complete'] is False and result['reports'][name]['passed'] is False)
        named(path)
        check('Subdomain browser report belongs only to native-browser '+name,a.evidence_allowed('qa/'+name,'native-browser') and not any(a.evidence_allowed('qa/'+name,lane) for lane in ('build','apache','recovery')))
    named(jobs['native-browser']/'qa/subdomain-requests-unexpected-browser-results.json');reseal();_,result=combine()
    check('Subdomain browser unexpected report cannot complete',result['complete'] is False and 'Unexpected reports: subdomain-requests-unexpected-browser-results.json' in result['errors'])
    deny('Subdomain browser private TLS key remains excluded',lambda:a.evidence_allowed('test-runtime/provider-http/server.key','native-browser'))


def run(tmp):
    current,registry,section,historical=subdomain_browser_current()
    originals={str((ROOT/path).resolve()):data for path,data in historical.items()}
    read_bytes,read_text=Path.read_bytes,Path.read_text
    def previous_bytes(path):
        key=str(path.resolve());return originals[key] if key in originals else read_bytes(path)
    def previous_text(path,*args,**kwargs):
        key=str(path.resolve());return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path,*args,**kwargs)
    with patch.object(Path,'read_bytes',previous_bytes),patch.object(Path,'read_text',previous_text):
        _run_before_subdomain_browser(tmp)
    subdomain_browser_checks(tmp,current,registry,section,historical)


def main():
    parser=argparse.ArgumentParser();parser.add_argument('--report',type=Path,required=True);args=parser.parse_args()
    error=None
    try:
        # Scoped constants describe this suite's two-file synthetic protocol fixture only.
        # The separate published-baseline guards execute with the unchanged production constants.
        with patch.object(a, 'PUBLISHED_BASELINE_SHA256', hashlib.sha256(synthetic_baseline()).hexdigest()), patch.object(a, 'PUBLISHED_BASELINE_MEMBERS', 2):
            with tempfile.TemporaryDirectory(prefix='cwp-artifact-tests-') as temp:run(Path(temp))
    except Exception as exc:error=exc
    report={'passed':error is None,'scope':'Offline synthetic temporary files and exact transfer helpers; no application, provider, database or CI execution.', 'checks':checks}
    if error:report['error']=type(error).__name__+': '+str(error)
    a.write(args.report,report)
    print(json.dumps({'passed':report['passed'],'checks':len(checks),'error':report.get('error')}))
    if error:raise error


if __name__=='__main__':main()
