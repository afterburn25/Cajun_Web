"""Offline temporary-file regressions for actual CI artifact helpers. No network/DB."""
import argparse
import copy
import hashlib
import importlib.util
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
    return {'schema': 1, 'repository': 'synthetic/repository', 'run_id': '123', 'attempt': '1',
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


def fixtures(root, ctx):
    packages(root, ctx)
    for n in a.FIXTURES:
        blob(root / 'test-runtime' / n / '.htaccess', b'Deny public listing')
        blob(root / 'test-runtime' / n / 'includes/public.php')
    for n in a.FIXTURE_JSON: a.write(root / 'test-runtime' / n, {'public': True})
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
    expected = {'browser':['build-guards-results.json','parallel-helper-tests.json','http-results.json'],
                'final':['build-guards-results.json','parallel-helper-tests.json','http-results.json','structure-results.json','apache-results.json']}
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
    # Retain reviewed driver bytes and the complete predecessor command sequence.
    contract=a.load(ROOT/'tools/acceptance-contract.json')
    for path,expected_hash in contract['unchanged_test_sha256'].items():
        check('Original test source retained '+path, hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
    workflow=(ROOT/'.github/workflows/application-tests.yml').read_text()
    native=workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    native_commands=[line.strip() for line in native.splitlines()]
    check('All119 native commands retain exact reviewed order and arguments', native_commands==contract['native_commands'])
    baseline=contract['native_command_baseline'];additions=baseline['additions']
    directory_baseline=contract['directory_control_command_baseline'];directory_additions=directory_baseline['additions']
    preserved=[command for command in native_commands if command not in additions and command not in directory_additions]
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


def main():
    parser=argparse.ArgumentParser();parser.add_argument('--report',type=Path,required=True);args=parser.parse_args()
    error=None
    try:
        with tempfile.TemporaryDirectory(prefix='cwp-artifact-tests-') as temp:run(Path(temp))
    except Exception as exc:error=exc
    report={'passed':error is None,'scope':'Offline synthetic temporary files and exact transfer helpers; no application, provider, database or CI execution.', 'checks':checks}
    if error:report['error']=type(error).__name__+': '+str(error)
    a.write(args.report,report)
    print(json.dumps({'passed':report['passed'],'checks':len(checks),'error':report.get('error')}))
    if error:raise error


if __name__=='__main__':main()
