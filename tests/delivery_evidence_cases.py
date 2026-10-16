"""Closed public delivery evidence cases; no application, browser, DB or network."""
import ast
import copy
import hashlib
import io
import shutil
import zipfile
from unittest.mock import patch


def run(a, root, tmp, check, deny, context, fixtures, outcomes):
    # Execute only the existing pure archive generator, never its HTTP/browser module.
    module = ast.parse((root/'tests/delivery_cases.py').read_text(encoding='utf-8'))
    functions = [n for n in module.body if isinstance(n, ast.FunctionDef) and n.name == 'archive']
    check('Delivery evidence uses one actual synthetic archive generator', len(functions) == 1)
    namespace = {'io': io, 'zipfile': zipfile}
    exec(compile(ast.Module(body=functions, type_ignores=[]), 'delivery_archive_generator', 'exec'), namespace)
    with zipfile.ZipFile(io.BytesIO(namespace['archive']())) as z:
        check('Actual synthetic delivery has exactly three public regular members',
              z.testzip() is None and len(z.namelist()) == 3
              and set(z.namelist()) == {'HANDOVER.md', 'LICENSES.md', 'index.html'}
              and all(not i.is_dir() for i in z.infolist()))
        content = {'qa/client-website-extracted/'+n: z.read(n) for n in z.namelist()}
    for name in content:
        check('Native/browser admits exact synthetic delivery member '+name, a.evidence_allowed(name, 'native-browser'))
        check('Synthetic delivery evidence cannot become pristine input '+name, not a.input_allowed(name))
        for lane in ['build', 'recovery', 'apache', 'unknown']:
            check('Exact delivery member belongs only to native/browser '+lane+' '+name,
                  not a.evidence_allowed(name, lane))
    refused = [
        'qa/client-website-extracted/extra.html', 'qa/client-website-extracted/index.php',
        'qa/client-website-extracted/config.php', 'qa/client-website-extracted/.installed.php',
        'qa/client-website-extracted/.env', 'qa/client-website-extracted/provider-master.key',
        'qa/client-website-extracted/secret.pem', 'qa/client-website-extracted/database.sql',
        'qa/client-website-extracted/sub/index.html', 'qa/other-extracted/index.html',
        'qa/client-website-extracted/INDEX.html', 'qa/Client-website-extracted/index.html',
        'QA/client-website-extracted/index.html', 'qa/client-website-extracted/index.html/',
        'qa/client-website-extracted//index.html', 'qa/client-website-extracted/../index.html',
        'qa/client-website-extracted\\index.html', '/qa/client-website-extracted/index.html',
        'qa/client-website-extracted/index.html:stream', 'qa/client-website-extracted/index.html\x00',
    ]
    for name in refused:
        try: allowed = a.evidence_allowed(name, 'native-browser')
        except ValueError: allowed = False
        check('Delivery allowlist rejects extra/private/noncanonical member '+repr(name), not allowed)
    with patch.object(a, 'no_private_name', side_effect=ValueError('synthetic private refusal')):
        deny('Exact delivery allowlist cannot bypass the original private-name guard',
             lambda: a.evidence_allowed('qa/client-website-extracted/index.html', 'native-browser'))

    ctx = context(); source = tmp/'source'; fixtures(source, ctx)
    input_dir = tmp/'inputs'; input_sha = a.create_inputs(source, ctx, input_dir)
    incoming = tmp/'partitions'; bindings = {}; roots = {}
    for i, lane in enumerate(a.LANES):
        target = source if lane == 'build' else tmp/lane
        roots[lane] = target
        if lane != 'build':
            target.mkdir(parents=True); a.receive_inputs(input_dir, target, ctx, input_sha, lane)
            report = {'native-browser': 'http-results.json', 'recovery': 'structure-results.json', 'apache': 'apache-results.json'}[lane]
            a.write(target/'qa'/report, {'passed': True, 'checks': [{'name': 'Synthetic delivery transfer report', 'passed': True}]})
        if lane == 'native-browser':
            for name, data in content.items():
                path = target/name; path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(data)
        digest = a.seal(target, ctx, incoming/lane, lane, outcomes(lane), input_sha)
        bindings[lane] = {'result': 'success', 'artifact_id': str(i+1), 'sha256': digest,
                          **({'input_sha256': input_sha} if lane == 'build' else {}),
                          **({'checkpoint_id': '9', 'checkpoint_digest': '6'*64} if lane == 'native-browser' else {})}
    sealed = a.verify_bundle(incoming/'native-browser', 'partition', ctx, bindings['native-browser']['sha256'])
    check('Actual seal records all three exact member digests',
          all(sealed['files'][n] == {'size': len(b), 'sha256': hashlib.sha256(b).hexdigest()} for n, b in content.items()))
    check('Immutable browser checkpoint preserves actual extracted public bytes',
          all((roots['native-browser']/'.acceptance/checkpoint'/n).read_bytes() == b for n, b in content.items()))
    expected = {'browser': ['build-guards-results.json', 'parallel-helper-tests.json', 'http-results.json'],
                'final': ['build-guards-results.json', 'parallel-helper-tests.json', 'http-results.json', 'structure-results.json', 'apache-results.json']}
    final = tmp/'final'; result = a.aggregate(final, ctx, incoming, bindings, expected)
    check('Actual four-lane aggregation retains exact public delivery bytes and named reports',
          result['complete'] and result['full_partition_checks_passed']
          and set(result['reports']) == set(expected['final'])
          and all((final/n).read_bytes() == b for n, b in content.items()))
    check('Final retains native/browser ownership for every public delivery member',
          all(n in result['partitions']['native-browser']['files']
              and all(n not in result['partitions'][lane]['files'] for lane in ['build', 'recovery', 'apache']) for n in content))

    for lane in ['build', 'recovery', 'apache']:
        target = tmp/('wrong-lane-'+lane)
        for name, data in content.items():
            path = target/name; path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(data)
        deny('Actual seal rejects public delivery extraction in '+lane,
             lambda t=target, l=lane: a.seal(t, ctx, tmp/('wrong-out-'+l), l, outcomes(l), input_sha))
    extra = tmp/'extra-native'; shutil.copytree(roots['native-browser']/'qa', extra/'qa')
    (extra/'qa/client-website-extracted/extra.html').write_bytes(b'extra synthetic public file')
    deny('Actual native seal refuses a fourth file beside the allowlisted members',
         lambda: a.seal(extra, ctx, tmp/'extra-output', 'native-browser', outcomes('native-browser'), input_sha))

    changed = tmp/'changed-content'; shutil.copytree(incoming/'native-browser', changed)
    archive = changed/'partition.zip'
    with zipfile.ZipFile(archive) as z: members = {n: z.read(n) for n in z.namelist()}
    members['qa/client-website-extracted/index.html'] = b'changed public output'
    with zipfile.ZipFile(archive, 'w') as z:
        for name, data in members.items(): z.writestr(name, data)
    envelope = a.load(changed/'partition.json'); envelope['archive'] = a.file_facts(archive); a.write(changed/'partition.json', envelope)
    deny('Rehashed outer archive cannot hide changed allowlisted delivery bytes',
         lambda: a.verify_bundle(changed, 'partition', ctx, a.digest(changed/'partition.json')))
    path = 'qa/client-website-extracted/index.html'; link = tmp/'allowed-name-symlink.zip'
    with zipfile.ZipFile(link, 'w') as z:
        info = zipfile.ZipInfo(path); info.create_system = 3; info.external_attr = 0o120777 << 16; z.writestr(info, b'target')
    deny('Allowlisted delivery pathname does not admit a ZIP symlink',
         lambda: a.inspect_archive(link, {path: {'size': 6, 'sha256': hashlib.sha256(b'target').hexdigest()}}))
