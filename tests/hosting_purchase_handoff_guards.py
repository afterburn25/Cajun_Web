"""Portable negative checks for the handoff runner; no SQL, HTTP or browser execution."""
import copy
import json
from pathlib import Path
import stat
import tempfile
import zipfile

from hosting_purchase_handoff_browser import PHASES, extract_package, fresh_directory, sha, validate_joined


def main():
    checks = []; skipped = []

    def check(name, value):
        if not value: raise AssertionError(name)
        checks.append({'name': name, 'passed': True})

    def refuses(name, work):
        try: work()
        except (RuntimeError, ValueError, KeyError, TypeError, zipfile.BadZipFile): check(name, True)
        else: raise AssertionError(name)

    source, fixture_hash = '1' * 40, '2' * 64
    proof = {'passed': True, 'source': source, 'fixture_sha256': fixture_hash,
             'immediate_purchase_proven': False, 'live_provider_or_payment': False,
             'checks': [{'name': 'Synthetic offline record only', 'passed': True}], 'phases': []}
    reports = {}
    for phase in PHASES:
        offset = 172800 if phase == 'prepare' else 0
        value = {'passed': True, 'phase': phase, 'simulated_application_clock': phase == 'prepare',
                 'clock': {'php_epoch': 2000000000 - offset, 'parent_epoch': 2000000000,
                           'database_epoch': 2000000000, 'offset_seconds': offset},
                 'checks': [{'name': 'Synthetic ' + str(i), 'passed': True} for i in range(4)]}
        if phase == 'due':
            value['joined'] = {'client_id': 1, 'package_id': 876001, 'synthetic_transport':
                              [{'method': 'POST', 'operation': 'packages.create'}, {'method': 'GET', 'operation': 'packages.list'}]}
        raw = json.dumps(value).encode(); reports[phase] = raw
        proof['phases'].append({'phase': phase, 'exit_code': 0, 'stderr_bytes': 0, 'stderr_sha256': sha(b''),
                                'report': phase + '-results.json', 'report_sha256': sha(raw), 'named_checks': 4})
    check('Complete synthetic four-phase envelope passes offline validation', validate_joined(proof, reports, source, fixture_hash)['phase'] == 'due')
    for key, value in [('passed', 1), ('source', '3' * 40), ('fixture_sha256', '4' * 64),
                       ('error', {}), ('immediate_purchase_proven', True), ('live_provider_or_payment', True), ('checks', [])]:
        changed = copy.deepcopy(proof); changed[key] = value
        refuses('Refuse changed joined envelope ' + key, lambda: validate_joined(changed, reports, source, fixture_hash))
    for key, value in [('exit_code', 1), ('stderr_bytes', 1), ('stderr_sha256', '5' * 64),
                       ('report', 'other.json'), ('report_sha256', '6' * 64), ('named_checks', 3)]:
        changed = copy.deepcopy(proof); changed['phases'][1][key] = value
        refuses('Refuse changed original phase ' + key, lambda: validate_joined(changed, reports, source, fixture_hash))
    changed = copy.deepcopy(proof); changed['phases'].reverse()
    refuses('Refuse reordered native phases', lambda: validate_joined(changed, reports, source, fixture_hash))
    missing = dict(reports); del missing['portal-other']
    refuses('Refuse omitted other-client native report', lambda: validate_joined(proof, missing, source, fixture_hash))
    variants = [
        ('failed named outcome', lambda r: r['checks'][0].update(passed=False)),
        ('truthy non-boolean outcome', lambda r: r['checks'][0].update(passed=1)),
        ('duplicate outcome name', lambda r: r['checks'][1].update(name=r['checks'][0]['name'])),
        ('shifted due PHP time', lambda r: r['clock'].update(php_epoch=1999999000)),
        ('shifted database time', lambda r: r['clock'].update(database_epoch=1999999000)),
        ('boolean clock epoch', lambda r: r['clock'].update(parent_epoch=True)),
        ('wrong due scope', lambda r: r.update(simulated_application_clock=True)),
        ('wrong client', lambda r: r['joined'].update(client_id=2)),
        ('wrong package', lambda r: r['joined'].update(package_id=876002)),
        ('extra provider operation', lambda r: r['joined']['synthetic_transport'].append({'method': 'POST', 'operation': 'packages.create'})),
    ]
    for label, mutate in variants:
        values = dict(reports); record = json.loads(values['due']); mutate(record); values['due'] = json.dumps(record).encode()
        changed = copy.deepcopy(proof); changed['phases'][1]['report_sha256'] = sha(values['due'])
        refuses('Refuse sealed ' + label, lambda: validate_joined(changed, values, source, fixture_hash))
    for label, raw in [('duplicate JSON key', reports['due'].replace(b'"passed": true', b'"passed": true,"passed": true', 1)),
                       ('nonfinite JSON', reports['due'][:-1] + b',"ignored":NaN}')]:
        values = dict(reports); values['due'] = raw
        changed = copy.deepcopy(proof); changed['phases'][1]['report_sha256'] = sha(raw)
        refuses('Refuse sealed ' + label, lambda: validate_joined(changed, values, source, fixture_hash))

    with tempfile.TemporaryDirectory(prefix='hosting-handoff-guards-') as temp:
        root = Path(temp); path = root / 'fresh'
        fresh_directory(path); check('Fresh runtime directory is created', path.is_dir())
        (path / 'witness').write_bytes(b'keep')
        refuses('Existing runtime refuses without overwrite', lambda: fresh_directory(path))
        check('Existing runtime witness remains exact', (path / 'witness').read_bytes() == b'keep')
        file = root / 'file'; file.write_bytes(b'keep')
        refuses('File parent refuses', lambda: fresh_directory(file / 'child'))
        target = root / 'linked'
        try: target.symlink_to(path, target_is_directory=True)
        except OSError: skipped.append('Actual symlink creation unavailable; Linux native runner guard remains required')
        else: refuses('Existing linked parent refuses', lambda: fresh_directory(target / 'child'))
        archive = root / 'COMPLETE.zip'
        def make(entries):
            with zipfile.ZipFile(archive, 'w') as z:
                for name, raw, mode in entries:
                    info = zipfile.ZipInfo(name); info.external_attr = mode << 16; z.writestr(info, raw)
            return {'source_commit': source, 'artifact': archive.name, 'sha256': sha(archive.read_bytes()),
                    'files': {n: sha(raw) for n, raw, _ in entries}}
        manifest = make([('index.php', b'php', stat.S_IFREG | 0o644)])
        out = root / 'extracted'; out.mkdir()
        check('Exact bounded package extracts offline', extract_package(archive, manifest, out, source) == 1 and (out / 'index.php').read_bytes() == b'php')
        for key, value in [('source_commit', '7' * 40), ('artifact', 'OTHER.zip'), ('sha256', '8' * 64), ('files', {})]:
            changed = copy.deepcopy(manifest); changed[key] = value
            refuses('Refuse changed package ' + key, lambda: extract_package(archive, changed, root / 'absent', source))
        for label, entries in [
            ('traversal', [('../escape', b'x', stat.S_IFREG)]),
            ('absolute path', [('/escape', b'x', stat.S_IFREG)]),
            ('backslash', [('a\\b', b'x', stat.S_IFREG)]),
            ('drive colon', [('C:escape', b'x', stat.S_IFREG)]),
            ('empty segment', [('a//b', b'x', stat.S_IFREG)]),
            ('case collision', [('A', b'x', stat.S_IFREG), ('a', b'y', stat.S_IFREG)]),
            ('symlink member', [('link', b'../escape', stat.S_IFLNK | 0o777)]),
            ('empty archive', []),
        ]:
            changed = make(entries)
            refuses('Refuse package ' + label, lambda: extract_package(archive, changed, root / 'absent', source))
            check('Unsafe ' + label + ' writes no output', not (root / 'absent').exists())
        manifest = make([('index.php', b'php', stat.S_IFREG | 0o644)]); manifest['files']['index.php'] = '9' * 64
        refuses('Refuse wrong original member hash', lambda: extract_package(archive, manifest, root / 'absent', source))
    # Same original report predicates in the explicit full-suite flat layout.
    import hosting_purchase_handoff_browser as runner
    flat = copy.deepcopy(proof)
    for record in flat['phases']: record['report'] = 'hosting-purchase-' + record['phase'] + '-results.json'
    check('Full-suite original four phase reports retain exact validation', validate_joined(flat,reports,source,fixture_hash,True)['phase']=='due')
    refuses('Focused report names cannot masquerade as full-suite evidence',lambda:validate_joined(proof,reports,source,fixture_hash,True))
    refuses('Full-suite report names cannot masquerade as focused evidence',lambda:validate_joined(flat,reports,source,fixture_hash))
    saved=(runner.ROOT,runner.QA,runner.WEB)
    try:
        with tempfile.TemporaryDirectory(prefix='hosting-handoff-flat-') as temporary:
            root=Path(temporary).resolve();qa=root/'qa';qa.mkdir();(qa/'unrelated.json').write_bytes(b'keep')
            runner.ROOT=root;runner.QA=qa/'hosting-purchase-http';runner.WEB=root/'test-runtime/hosting-purchase-web'
            directory,outputs=runner.prepare_handoff_outputs(True)
            check('Full-suite browser outputs are flat and closed',directory==qa and {p.name for p in outputs.values()}=={'hosting-purchase-http-results.json','hosting-purchase-http-progress.txt','hosting-purchase-http-commands.json','hosting-purchase-http-server.log'})
            outputs['progress.json'].write_bytes(b'synthetic diagnostics')
            (qa/runner.CAPTURE_NAMES[0]).write_bytes(b'synthetic not PNG')
            check('Full-suite browser excludes unrelated QA from owned file inventory',set(runner.handoff_report_files(qa,outputs,True))=={'hosting-purchase-http-progress.txt',runner.CAPTURE_NAMES[0]})
            outputs['progress.json'].unlink();(qa/runner.CAPTURE_NAMES[0]).unlink()
            for path in [*outputs.values(),*(qa/n for n in runner.CAPTURE_NAMES)]:
                path.write_bytes(b'keep')
                refuses('Full-suite refuses existing owned output '+path.name,lambda:runner.prepare_handoff_outputs(True))
                check('Refusal preserves output and does not create runtime '+path.name,path.read_bytes()==b'keep' and not runner.WEB.exists())
                path.unlink()
            check('Full-suite browser leaves unrelated QA exact',(qa/'unrelated.json').read_bytes()==b'keep' and not runner.QA.exists())
    finally:runner.ROOT,runner.QA,runner.WEB=saved
    print(json.dumps({'passed': True, 'scope': 'Portable synthetic envelope and temporary ZIP/path checks only', 'checks': checks, 'skipped': skipped}, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
