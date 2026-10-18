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


def synthetic_baseline_126():
    # Deliberately different bytes from the retained 1.24 synthetic baseline.
    stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        for name, body in {'.htaccess': b'Deny public listing', 'includes/public.php': b'public synthetic 1.25 bytes'}.items():
            archive.writestr(zipfile.ZipInfo(name, date_time=(2026, 10, 12, 0, 0, 0)), body)
    return stream.getvalue()


def additional_baseline_checks(tmp, ctx, source, transfer, input_sha):
    obj = a.verify_bundle(transfer, 'inputs', ctx, input_sha)
    a.validate_published_baseline(source, obj['files'])
    a.validate_published_baseline_126(source, obj['files'])
    check('Both independently pinned published baselines validate in one sealed input',
          a.PUBLISHED_BASELINE != a.PUBLISHED_BASELINE_126 and synthetic_baseline() != synthetic_baseline_126())
    check('Additional baseline extends the public fixture set to exactly28 trees',
          len(a.FIXTURES) == 28 and a.VERSIONS[-2:] == ('1.24.0', '1.25.0'))
    check('Only the exact added published archive is admitted',
          a.input_allowed('test-runtime/' + a.PUBLISHED_BASELINE_126))
    for name in ['test-runtime/CajunWebPro-1.26.0-COMPLETE-UPLOAD.zip',
                 'qa/' + a.PUBLISHED_BASELINE_126,
                 'test-runtime/' + a.PUBLISHED_BASELINE_126 + '/extra.php',
                 'test-runtime/private/' + a.PUBLISHED_BASELINE_126]:
        check('Additional baseline cannot broaden the archive path allowlist ' + name, not a.input_allowed(name))
    prefix = 'test-runtime/released-1.25.0/'
    member = prefix + 'includes/public.php'
    for title, mutation in [
        ('missing', lambda f: f.pop(member)),
        ('extra', lambda f: f.update({prefix + 'extra.php': f[member]})),
        ('changed digest', lambda f: f[member].update(sha256='0' * 64)),
        ('changed size', lambda f: f[member].update(size=f[member]['size'] + 1)),
        ('traversal', lambda f: f.update({prefix + '../outside': f.pop(member)})),
    ]:
        facts = copy.deepcopy(obj['files']); mutation(facts)
        deny('Added baseline refuses ' + title + ' member evidence', lambda f=facts: a.validate_published_baseline_126(source, f))
    archive = source / 'test-runtime' / a.PUBLISHED_BASELINE_126
    raw = archive.read_bytes()
    try:
        archive.write_bytes(synthetic_baseline())
        deny('Original1.24 raw archive cannot impersonate the added1.25 baseline', lambda: a.validate_published_baseline_126(source, obj['files']))
        archive.write_bytes(raw[:-1] + bytes([raw[-1] ^ 1]))
        deny('Added baseline refuses mutated original ZIP bytes', lambda: a.validate_published_baseline_126(source, obj['files']))
        archive.unlink()
        deny('Added baseline cannot fall back when its raw original ZIP is absent', lambda: a.validate_published_baseline_126(source, obj['files']))
    finally:
        archive.write_bytes(raw)
    # Correctly re-sealed envelopes still cannot omit either original baseline or new fixture tree.
    for index, omitted in enumerate(['test-runtime/' + a.PUBLISHED_BASELINE,
                                     'test-runtime/' + a.PUBLISHED_BASELINE_126, prefix]):
        facts = {n: f for n, f in obj['files'].items() if n != omitted and not n.startswith(omitted if omitted.endswith('/') else omitted + '/')}
        folder = tmp / ('baseline-omission-' + str(index)); folder.mkdir()
        envelope = dict(obj, files=facts, archive=a.pack(source, facts, folder / 'inputs.zip'))
        a.write(folder / 'inputs.json', envelope)
        deny('Re-sealed input still requires original baseline family ' + omitted,
             lambda d=folder, i=index: a.receive_inputs(d, tmp / ('baseline-receive-' + str(i)), ctx, a.digest(d / 'inputs.json'), 'apache'))
    # Wrong new fixture bytes with coherent outer hashes must fail the immutable inner baseline.
    changed = tmp / 'baseline-member-changed'; shutil.copytree(source, changed)
    blob(changed / member, b'wrong but coherently sealed fixture member')
    facts = copy.deepcopy(obj['files']); facts[member] = a.file_facts(changed / member)
    folder = tmp / 'baseline-coherent-envelope'; folder.mkdir()
    envelope = dict(obj, files=facts, archive=a.pack(changed, facts, folder / 'inputs.zip'))
    a.write(folder / 'inputs.json', envelope)
    deny('Coherent outer input cannot replace an original1.25 member',
         lambda: a.receive_inputs(folder, tmp / 'baseline-coherent-child', ctx, a.digest(folder / 'inputs.json'), 'apache'))


def fixtures(root, ctx):
    packages(root, ctx)
    for n in a.FIXTURES:
        blob(root / 'test-runtime' / n / '.htaccess', b'Deny public listing')
        blob(root / 'test-runtime' / n / 'includes/public.php')
    for n in a.FIXTURE_JSON: a.write(root / 'test-runtime' / n, {'public': True})
    blob(root / 'test-runtime' / a.PUBLISHED_BASELINE, synthetic_baseline())
    blob(root / 'test-runtime' / a.PUBLISHED_BASELINE_126, synthetic_baseline_126())
    blob(root / 'test-runtime/released-1.25.0/includes/public.php', b'public synthetic 1.25 bytes')
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


def baseline_driver_predecessor(path, data, contract):
    delta = contract['published125_input_driver_delta'][path]
    a.require(set(delta) == {'predecessor_sha256', 'current_sha256', 'append', 'replacements'}, 'Unexpected baseline driver delta fields')
    a.require(hashlib.sha256(data).hexdigest() == delta['current_sha256'], 'Changed current baseline driver bytes')
    if delta['append']:
        suffix = delta['append'].encode()
        a.require(data.endswith(suffix) and data.count(suffix) == 1, 'Missing exact baseline preparation suffix')
        data = data[:-len(suffix)]
    for edit in reversed(delta['replacements']):
        a.require(set(edit) == {'before', 'after', 'count'} and type(edit['count']) is int and edit['count'] == 1, 'Invalid finite baseline driver edit')
        before, after = edit['before'].encode(), edit['after'].encode()
        a.require(before and after and data.count(after) == edit['count'], 'Missing exact baseline driver extension')
        data = data.replace(after, before)
    a.require(hashlib.sha256(data).hexdigest() == delta['predecessor_sha256'] == contract['unchanged_test_sha256'][path], 'Baseline driver predecessor changed')
    return data


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
    check('Input binds all27 public fixture trees plus exact package/checksum files', len(a.FIXTURES - {'released-1.25.0'}) == 27 and 'dist/SHA256SUMS.txt' in obj['files'])
    additional_baseline_checks(tmp, ctx, source, transfer, input_sha)
    for lane in a.LANES[1:]:
        target = tmp / ('receive-' + lane); target.mkdir()
        (target / 'qa').mkdir()
        a.receive_inputs(transfer, target, ctx, input_sha, lane)
        check('Exact input materializes safely for ' + lane, (target / 'test-runtime/legacy/.htaccess').read_bytes() == b'Deny public listing')
        check('Both original published archives and distinct1.25 members reach ' + lane,
              (target / 'test-runtime' / a.PUBLISHED_BASELINE).read_bytes() == synthetic_baseline()
              and (target / 'test-runtime' / a.PUBLISHED_BASELINE_126).read_bytes() == synthetic_baseline_126()
              and (target / 'test-runtime/released-1.25.0/includes/public.php').read_bytes() == b'public synthetic 1.25 bytes')
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
    contract=a.load(ROOT/'tools/acceptance-contract.json')
    from registered_nameserver_changes_acceptance import verify as nameserver_changes_acceptance
    changes_predecessor = nameserver_changes_acceptance(a, ROOT, tmp, check, deny, contract)
    driver_paths = {'tests/prepare_legacy.py'}
    check('Published1.25 input inverse is limited to the original preparation source', set(contract['published125_input_driver_delta']) == driver_paths)
    for driver in sorted(driver_paths):
        data = ROOT.joinpath(driver).read_bytes().replace(b'\r\n', b'\n')
        check('Published1.25 input extension reverses exactly ' + driver,
              hashlib.sha256(baseline_driver_predecessor(driver, data, contract)).hexdigest() == contract['unchanged_test_sha256'][driver])
        deny('Changed preparation bytes cannot bypass baseline inverse',
             lambda p=driver,d=data: baseline_driver_predecessor(p, d + b'\n# changed', contract))
        wrong = copy.deepcopy(contract); wrong['published125_input_driver_delta'][driver]['append'] += '# missing exact suffix'
        deny('Changed preparation inverse description is refused',
             lambda p=driver,d=data,c=wrong: baseline_driver_predecessor(p, d, c))
    for path,expected_hash in contract['unchanged_test_sha256'].items():
        original_bytes=ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')
        if path in driver_paths: original_bytes=baseline_driver_predecessor(path, original_bytes, contract)
        if path=='tests/browser_acceptance.py':
            nameserver_addition=contract['registered_nameservers']['browser_addition'].encode()
            check('Nameserver browser callback occurs once',original_bytes.count(nameserver_addition)==1)
            original_bytes=original_bytes.replace(nameserver_addition,b'',1)
        check('Original test source retained '+path, hashlib.sha256(original_bytes).hexdigest()==expected_hash)
    workflow=changes_predecessor['workflow']
    native=workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    native_commands=[line.strip() for line in native.splitlines()]
    # New write controls extend the recipe without changing predecessor assertions.
    write_contract=contract['registered_nameserver_changes'];write_commands=write_contract['additions']
    check('Nameserver change extension retains complete164-command recipe',len(native_commands)==164 and native_commands==contract['native_commands'])
    write_position=native_commands.index(write_contract['additions_after'])+1
    check('Seven nameserver change commands occur once after original reader native fixture',len(write_commands)==len(set(write_commands))==7 and native_commands[write_position:write_position+7]==write_commands and all(native_commands.count(command)==1 for command in write_commands))
    native_commands=[command for command in native_commands if command not in write_commands]
    check('Nameserver changes retain exact157 predecessor commands',len(native_commands)==write_contract['predecessor_native_count']==157 and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==write_contract['predecessor_native_sha256'])
    contract['native_commands']=[command for command in contract['native_commands'] if command not in write_commands]
    for path,expected_hash in write_contract['source_sha256'].items():
        check('Nameserver change producer source retained '+path,hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
    write_original_sources={}
    check('Nameserver change predecessor test delta is limited to the existing renderer',set(write_contract['predecessor_source_delta'])=={'tests/registered_nameservers_views.php'})
    for path,delta in write_contract['predecessor_source_delta'].items():
        data=ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n').decode('utf-8')
        check('Nameserver change pending status test addition is finite '+path,data.count(delta['after'])==1)
        original=data.replace(delta['after'],delta['before'],1).encode('utf-8')
        check('Nameserver change test addition preserves all predecessor assertions '+path,hashlib.sha256(original).hexdigest()==delta['predecessor_sha256']==contract['registered_nameservers']['source_sha256'][path])
        write_original_sources[path]=original
    write_reports=a.load(ROOT/'tools/acceptance-reports.json')
    check('Nameserver change reports are required once in both aggregates',len(write_contract['reports'])==len(set(write_contract['reports']))==7 and all(write_reports['browser'].count(name)==write_reports['final'].count(name)==1 for name in write_contract['reports']))
    nameserver_contract=contract['registered_nameservers'];nameserver_commands=nameserver_contract['additions']
    check('Nameserver extension retains complete157-command recipe',len(native_commands)==157 and native_commands==contract['native_commands'])
    nameserver_position=native_commands.index(nameserver_contract['additions_after'])+1
    check('Five nameserver commands occur once in the intended position',len(nameserver_commands)==len(set(nameserver_commands))==5 and native_commands[nameserver_position:nameserver_position+5]==nameserver_commands and all(native_commands.count(command)==1 for command in nameserver_commands))
    native_commands=[command for command in native_commands if command not in nameserver_commands]
    check('Nameserver extension preserves exact152 predecessor commands',len(native_commands)==nameserver_contract['predecessor_native_count']==152 and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==nameserver_contract['predecessor_native_sha256'])
    for path,expected_hash in nameserver_contract['source_sha256'].items():
        check('Nameserver fixture source retained '+path,hashlib.sha256(write_original_sources.get(path,changes_predecessor['source'].get(path,ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')))).hexdigest()==expected_hash)
    nameserver_server=changes_predecessor['source']['tests/provider_http_server.py'].decode('utf-8')
    for index,change in enumerate(reversed(nameserver_contract['server_changes'])):
        check('Nameserver synthetic GET addition is finite '+str(index),nameserver_server.count(change['after'])==change['count'])
        nameserver_server=nameserver_server.replace(change['after'],change['before'])
    check('Nameserver synthetic GET preserves all prior provider fixture bytes',hashlib.sha256(nameserver_server.encode('utf-8')).hexdigest()==nameserver_contract['server_predecessor_sha256'])
    nameserver_upgrade=nameserver_contract['upgrade']
    nameserver_apache_header='      - name: Apache protection with and without rewriting\n        id: test\n        run: |\n'
    nameserver_apache=[line.strip() for line in workflow.split(nameserver_apache_header,1)[1].split('      - name:',1)[0].splitlines()]
    check('Nameserver upgrade appends exactly two Apache commands',len(nameserver_upgrade['commands'])==2 and nameserver_apache==nameserver_upgrade['predecessor_commands']+nameserver_upgrade['commands'] and len(nameserver_apache)==len(set(nameserver_apache))==6)
    check('Nameserver upgrade preserves all four prior Apache commands',hashlib.sha256(json.dumps(nameserver_apache[:-2],separators=(',',':')).encode()).hexdigest()==nameserver_upgrade['predecessor_sha256'])
    for path,expected_hash in nameserver_upgrade['source_sha256'].items():
        check('Nameserver upgrade fixture source retained '+path,hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
    nameserver_reports=a.load(ROOT/'tools/acceptance-reports.json')
    check('Nameserver native reports required in both aggregates',all(name in nameserver_reports['browser'] and name in nameserver_reports['final'] for name in nameserver_contract['reports']))
    check('Nameserver Apache reports required only in final aggregate',all(name in nameserver_reports['final'] and name not in nameserver_reports['browser'] for name in nameserver_upgrade['reports']))
    contract['native_commands']=[command for command in contract['native_commands'] if command not in nameserver_commands]
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


def main():
    parser=argparse.ArgumentParser();parser.add_argument('--report',type=Path,required=True);args=parser.parse_args()
    error=None
    try:
        # Scoped constants describe this suite's two-file synthetic protocol fixture only.
        # The separate published-baseline guards execute with the unchanged production constants.
        check('Production1.24 baseline constants retain exact immutable values',
              a.PUBLISHED_BASELINE == 'CajunWebPro-1.24.0-COMPLETE-UPLOAD.zip'
              and a.PUBLISHED_BASELINE_SHA256 == 'aa7a84b4c0d1201a4eddf2fe68340b90d4860117ceff4d959d165e6feafa731e' and a.PUBLISHED_BASELINE_MEMBERS == 2591)
        check('Production1.25 baseline constants bind the actual published COMPLETE archive',
              a.PUBLISHED_BASELINE_126 == 'CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
              and a.PUBLISHED_BASELINE_126_SHA256 == 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39' and a.PUBLISHED_BASELINE_126_MEMBERS == 2895)
        with patch.object(a, 'PUBLISHED_BASELINE_SHA256', hashlib.sha256(synthetic_baseline()).hexdigest()), patch.object(a, 'PUBLISHED_BASELINE_MEMBERS', 2), patch.object(a, 'PUBLISHED_BASELINE_126_SHA256', hashlib.sha256(synthetic_baseline_126()).hexdigest()), patch.object(a, 'PUBLISHED_BASELINE_126_MEMBERS', 2):
            with tempfile.TemporaryDirectory(prefix='cwp-artifact-tests-') as temp:run(Path(temp))
    except Exception as exc:error=exc
    report={'passed':error is None,'scope':'Offline synthetic temporary files and exact transfer helpers; no application, provider, database or CI execution.', 'checks':checks}
    if error:report['error']=type(error).__name__+': '+str(error)
    a.write(args.report,report)
    print(json.dumps({'passed':report['passed'],'checks':len(checks),'error':report.get('error')}))
    if error:raise error


if __name__=='__main__':main()
