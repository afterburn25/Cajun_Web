"""Installed published1.25 -> exact current client nameserver requests, with actual owner UPDATE/recovery.

Uses the existing release125 launcher, form and retention helpers without
running or editing their main. Archive validation is local to this new producer. New feature history is created only after the actual target update.
"""
from pathlib import Path, PurePosixPath
import base64
import hashlib
import zipfile
import json
import os
import re
import subprocess
import time
BASELINE_SOURCE = 'b79c67110829126916e3a65e616191542448bccc'
BASELINE_SHA = 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39'
BASELINE_NAME = 'CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
BASELINE_MEMBERS = 2895
BASELINE_BYTES = 15843208

ROOT = Path(__file__).resolve().parents[1]
OWNER = 'Synthetic client nameserver requests upgrade owner passphrase'
PREFIX = 'rnru_'
REPORT = 'apache-registered-nameserver-requests-upgrade-results.json'
FEATURE_PATHS = ('app/providers/registered_nameserver_requests.php',
                 'app/providers/registered_nameserver_requests_document.php',
                 'app/providers/registered_nameserver_requests_storage.php',
                 'database/schema/registered_nameserver_requests_schema.php',
                 'database/migrations/registered_nameserver_requests_migrate.php',
                 'app/providers/registered_nameserver_changes.php')
HISTORY_TABLES = ('provider_registrar_ns_requests_events',)
ACTIONS = ('apply', 'apply', 'recover', 'apply', 'apply', 'recover', 'apply')



def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def archive_map(path, expected_sha=None):
    if expected_sha is not None and digest(path) != expected_sha:
        raise AssertionError('Archive digest mismatch')
    with zipfile.ZipFile(path) as archive:
        entries = archive.infolist()
        names = [entry.filename for entry in entries]
        assert 0 < len(entries) <= 5000 and len(names) == len(set(names)) == len({n.casefold() for n in names})
        assert sum(entry.file_size for entry in entries) <= 256 * 1024 * 1024
        for entry in entries:
            assert entry.orig_filename == entry.filename, 'Archive name normalization is not evidence of a safe original path'
            name = PurePosixPath(entry.filename)
            assert not name.is_absolute() and str(name) == entry.filename
            assert all(p not in ('', '.', '..') for p in entry.filename.split('/'))
            assert not any(c in entry.filename for c in ('\\', ':', '\x00')) and all(ord(c) >= 32 for c in entry.filename)
            assert not entry.is_dir() and not entry.flag_bits & 1
            assert ((entry.external_attr >> 16) & 0o170000) in (0, 0o100000)
        assert archive.testzip() is None
        return {entry.filename: hashlib.sha256(archive.read(entry)).hexdigest() for entry in entries}


def baseline_inputs(repo):
    archive = repo / 'test-runtime' / BASELINE_NAME
    assert archive.is_file() and not archive.is_symlink() and archive.stat().st_size == BASELINE_BYTES
    files = archive_map(archive, BASELINE_SHA)
    history = json.loads((repo / 'tools/legacy-release-files.json').read_text(encoding='utf-8'))
    assert len(files) == BASELINE_MEMBERS
    assert history.get('1.25.0', {}).get('files') == files, 'Published 1.25 inventory must be explicitly integrated first'
    assert not any('registered_nameserver_requests' in name for name in files)
    return archive, files


def target_inputs(repo):
    version = (repo / 'VERSION').read_text(encoding='utf-8').strip()
    assert re.fullmatch(r'[1-9][0-9]*\.[0-9]+\.[0-9]+', version)
    archive = repo / 'dist' / ('CajunWebPro-' + version + '-UPDATE.zip')
    descriptor = json.loads(archive.with_suffix('.manifest.json').read_text(encoding='utf-8'))
    source = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=repo, text=True).strip()
    assert re.fullmatch(r'[a-f0-9]{40}', source)
    assert source != BASELINE_SOURCE, 'Target must be a different exact build, even when the version is unchanged'
    subprocess.check_call(['git', 'diff', '--quiet', 'HEAD', '--', 'site/', 'tools/update/', 'VERSION',
                           'tools/legacy-release-files.json'], cwd=repo)
    assert descriptor['source_commit'] == source and descriptor['version'] == version
    assert descriptor['artifact'] == archive.name
    files = archive_map(archive, descriptor['sha256'])
    assert files == descriptor['files']
    stage = 'cwp-update-' + version + '/'
    tracked = subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', source, '--', 'site/'], cwd=repo, text=True).splitlines()
    payload = {n[5:]: digest(repo / n) for n in tracked if n != 'site/install.php'
               and not n.startswith(('site/install/', 'site/storage/', 'site/upload/'))}
    assert {n[len(stage + 'site/'):]: sha for n, sha in files.items() if n.startswith(stage + 'site/')} == payload
    with zipfile.ZipFile(archive) as package:
        assert package.read(stage + 'legacy-release-files.json') == (repo / 'tools/legacy-release-files.json').read_bytes()
        assert package.read(stage + 'owner-update.php') == (repo / 'tools/update/owner-update.php').read_bytes()
        match = re.fullmatch(rb"<\?php\nif \(!defined\('CWP_BOOT'\)\) \{ http_response_code\(404\); exit; \}\nreturn json_decode\(base64_decode\('([A-Za-z0-9+/=]+)'\),true,64,JSON_THROW_ON_ERROR\);\n", package.read(stage + 'manifest.php'))
        assert match, 'Exact current build manifest envelope required'
        staged = json.loads(base64.b64decode(match[1], validate=True))
        assert staged['source_commit'] == source and staged['version'] == version and staged['files'] == payload
        assert staged['history_sha256'] == digest(repo / 'tools/legacy-release-files.json')
    return archive, version, source, files


def fresh_path(path, root):
    path, root = Path(path), Path(root).resolve()
    assert path.is_absolute() and path == path.resolve(strict=False) and path.is_relative_to(root)
    for node in [path, *path.parents]:
        assert not node.is_symlink(), 'Symlink in fixture path'
        if node == root:
            break
    assert not path.exists(), 'Never reset an existing fixture or output'


def code_matches(installed, files, target=False, version=None):
    # Installer entry/support are intentionally removed by the real installer.
    if target:
        prefix = 'cwp-update-' + version + '/site/'
        wanted = {n[len(prefix):]: sha for n, sha in files.items() if n.startswith(prefix)}
    else:
        wanted = {n: sha for n, sha in files.items() if not n.startswith('install/') and n != 'install.php'}
    return bool(wanted) and all((installed / n).is_file() and not (installed / n).is_symlink()
                               and digest(installed / n) == sha for n, sha in wanted.items())


def launch_private(launcher, work, web):
    """Reuse the exact Apache launcher but keep its raw diagnostics outside QA/web."""
    original = launcher.QA
    launcher.QA = work
    try:
        return launcher.launch(work, web, True)
    finally:
        launcher.QA = original


def log_summary(path):
    """Closed public diagnostics only; raw logs remain in private test-runtime."""
    if not path.is_file() or path.is_symlink():
        return {'present': False, 'bytes': None, 'sha256': None, 'within_bound': False, 'php_diagnostic': None}
    size = path.stat().st_size
    if size > 4 * 1024 * 1024:
        return {'present': True, 'bytes': size, 'sha256': None, 'within_bound': False, 'php_diagnostic': None}
    raw = path.read_bytes()
    return {'present': True, 'bytes': size, 'sha256': hashlib.sha256(raw).hexdigest(), 'within_bound': True,
            'php_diagnostic': re.search(rb'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', raw) is not None}



def changes_target_inputs(repo):
    archive, version, source, files = target_inputs(repo)
    for name in FEATURE_PATHS:
        actual = (repo / 'site' / name).read_bytes()
        original = subprocess.check_output(['git', 'show', source + ':site/' + name], cwd=repo)
        assert actual == original or actual.replace(b'\r\n', b'\n') == original.replace(b'\r\n', b'\n')
        assert files['cwp-update-' + version + '/site/' + name] == digest(repo / 'site' / name)
    return archive, version, source, files


def updater_retained(before, after, actions, version):
    """Only the exact real updater audit append is permitted; all original cells survive."""
    from release_125_retention import retained
    from copy import deepcopy
    retained(before, after)
    assert actions and all(action in ('apply', 'recover') for action in actions)
    assert set(before['tables']) == set(after['tables']) and before['private_files'] == after['private_files']
    old, new = deepcopy(before), deepcopy(after)
    old.pop('version'); new.pop('version')
    a, b = old['tables']['audit'], new['tables']['audit']
    added = set(b['rows']) - set(a['rows'])
    assert b['count'] == a['count'] + len(actions) and len(added) == len(actions)
    h = lambda value: hashlib.sha256(json.dumps(value, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
    owners = before['tables']['users']['rows']
    assert len(owners) == 1
    owner = next(iter(owners.values()))['id']
    expected = sorted((h('structure.' + action), h('Version ' + version + '; state ' + ('complete' if action == 'apply' else 'rolled-back')), owner) for action in actions)
    actual = []
    for key in added:
        row = b['rows'][key]
        assert set(row) == {'id', 'user_id', 'action', 'detail', 'created_at'}
        actual.append((row['action'], row['detail'], row['user_id']))
        del b['rows'][key]
    assert sorted(actual) == expected
    b['count'] -= len(added)
    assert old == new, 'Updater changed an original row, column, table or private byte'
    return True


def fixture_inputs(repo, source):
    paths = ('tests/registered_nameserver_requests_upgrade.py',
             'tests/registered_nameserver_requests_upgrade_fixture.php',
             'tests/registered_nameserver_requests_upgrade_test.py',
             'tests/release_125_upgrade_acceptance.py', 'tests/release_125_upgrade_cases.py',
             'tests/release_125_retention.py', 'tests/acceptance_environment.py')
    out = {}
    for name in paths:
        path = repo / name
        assert path.is_file() and not path.is_symlink()
        actual = path.read_bytes()
        original = subprocess.check_output(['git', 'show', source + ':' + name], cwd=repo)
        assert actual.replace(b'\r\n', b'\n') == original.replace(b'\r\n', b'\n'), 'Fixture/helper differs from exact accepted Git source'
        out[name] = {'sha256': hashlib.sha256(actual).hexdigest(), 'git_sha256': hashlib.sha256(original).hexdigest()}
    return out


def main():
    assert os.name == 'posix' and os.geteuid() != 0
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test' and os.environ.get('CWP_TEST_HOST') == '127.0.0.1'
    import requests
    from acceptance_environment import selected, php_matches
    import release_125_upgrade_acceptance as apache_launcher
    from release_125_upgrade_cases import fields, unpack
    from release_125_retention import retained, exact_business
    php = os.environ['PHP_BINARY']
    assert Path(php).is_absolute() and Path(php).is_file()
    baseline, baseline_files = baseline_inputs(ROOT)
    update, version, source, update_files = changes_target_inputs(ROOT)
    fixture_sources = fixture_inputs(ROOT, source)
    runtime = ROOT / 'test-runtime/registered-nameserver-requests-upgrade'
    qa = ROOT / 'qa'
    fresh_path(runtime, ROOT)
    outputs = [REPORT, 'apache-registered-nameserver-requests-upgrade-retention.json']
    for name in outputs:
        fresh_path(qa / name, ROOT)
    qa.mkdir(exist_ok=True)
    work = runtime / 'rnru-upgrade'
    web = work / 'web'
    installed = web / 'subfolder'
    installed.mkdir(parents=True)
    unpack(baseline, installed, BASELINE_SHA)
    checks, phases, runtime_fact, helper_failures, apache_logs = [], {}, {}, [], {}
    completed_actions = []
    failure = None
    proc = launcher = None

    def check(name, value):
        assert isinstance(value, bool)
        assert not any(item['name'] == name for item in checks), 'Duplicate named result'
        checks.append({'name': name, 'passed': value})
        if not value:
            raise AssertionError(name)

    def command(mode, phase=None):
        result = subprocess.run([php, str(ROOT / 'tests/registered_nameserver_requests_upgrade_fixture.php'),
                                 str(installed), mode, str(web)], capture_output=True, text=True, timeout=600)
        assert mode in {'preflight','snapshot','seed','verify','baseline-seed','unprepared','damage','refuse'}
        ordinal = len(list(work.glob('helper-*.stdout')))
        (work / ('helper-' + str(ordinal) + '-' + mode + '.stdout')).write_text(result.stdout, encoding='utf-8')
        (work / ('helper-' + str(ordinal) + '-' + mode + '.stderr')).write_text(result.stderr, encoding='utf-8')
        assert len(result.stdout) <= 16 * 1024 * 1024 and len(result.stderr) <= 65536
        body = json.loads(result.stdout)
        if body.get('passed') is not True:
            detail = body.get('failure', {})
            safe = {'mode': mode, 'exit_code': result.returncode}
            for key, pattern in [('class', r'[A-Za-z_\\][A-Za-z0-9_\\]{0,120}'),
                                 ('file', r'[A-Za-z0-9_.-]{1,120}'), ('sqlstate', r'[A-Z0-9]{5}')]:
                if isinstance(detail.get(key), str) and re.fullmatch(pattern, detail[key]):
                    safe[key] = detail[key]
            if type(detail.get('line')) is int and 0 < detail['line'] <= 100000:
                safe['line'] = detail['line']
            helper_failures.append(safe)
        assert result.returncode == 0 and not result.stderr, ('Installed helper failed', mode, result.returncode)
        assert body['passed'] is True
        for item in body['checks']:
            check((phase or mode) + ': ' + item['name'], item['passed'])
        return body

    try:
        command('preflight')
        proc, launcher, origin = launch_private(apache_launcher, work, web)
        base = origin + '/subfolder'
        ready = None
        for _ in range(60):
            assert proc.poll() is None, 'Apache stopped before installation'
            try:
                ready = requests.get(base + '/install.php', timeout=2)
                break
            except requests.RequestException:
                time.sleep(.1)
        check('Published installer served by actual Apache', ready is not None and ready.status_code == 200
              and 'Apache/' in ready.headers.get('Server', ''))
        probe = installed / 'rnru-runtime-probe.php'
        probe.write_text("<?php header('Content-Type: application/json');echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI]);", encoding='utf-8')
        try:
            response = requests.get(base + '/rnru-runtime-probe.php', timeout=10)
            runtime_fact = response.json()
            check('Actual mod_php matches selected runtime', response.status_code == 200
                  and set(runtime_fact) == {'version', 'sapi'} and runtime_fact['sapi'] == 'apache2handler'
                  and php_matches(runtime_fact['version'], selected()['php']))
        finally:
            probe.unlink()
        session = requests.Session()
        target, form = fields(session.get(base + '/install.php', timeout=30), 'install')
        form.update({'db_host': '127.0.0.1', 'db_port': os.environ['CWP_TEST_PORT'], 'db_name': 'cwp_test',
                     'db_user': os.environ['CWP_TEST_USER'], 'db_password': os.environ['CWP_TEST_PASSWORD'],
                     'db_prefix': PREFIX, 'owner_name': 'client nameserver requests upgrade owner', 'owner_email': 'rnru-upgrade@example.invalid',
                     'owner_password': OWNER, 'owner_confirm': OWNER, 'site_url': base,
                     'confirm_install': '1', 'seed': '1', 'preview': '1'})
        response = session.post(target, data=form, timeout=120)
        body = response.json()
        check('Actual immutable published 1.25 installation completes', response.status_code == 200 and body.get('ok') is True)
        check('Private store is outside the served tree', not Path(body['storage']).resolve().is_relative_to(web.resolve()))
        check('Published code bytes match before updating', code_matches(installed, baseline_files))
        command('baseline-seed')
        phases['baseline'] = command('snapshot')['snapshot']
        check('Original installed version is the immutable published 1.25 baseline', phases['baseline']['version'] == '1.25.0')
        check('Published baseline has no new client request event history', not set(HISTORY_TABLES).intersection(phases['baseline']['tables']))
        config = digest(installed / 'config.php')
        guard = digest(installed / '.installed.php')
        unpack(update, installed)
        updater = base + '/cwp-update-' + version + '/owner-update.php'

        def action(which):
            assert len(completed_actions) < len(ACTIONS) and which == ACTIONS[len(completed_actions)]
            before = command('snapshot')['snapshot']
            target, form = fields(session.get(updater, timeout=120), which)
            form.update({'email': 'rnru-upgrade@example.invalid', 'owner_password': OWNER, 'backup_confirm': '1'})
            response = session.post(target, data=form, timeout=120)
            expected = '<h2>Complete</h2>' if which == 'apply' else '<h2>Rolled-back</h2>'
            check('Owner updater ' + which + ' action ' + str(sum(c['name'].startswith('Owner updater') for c in checks) + 1),
                  response.status_code == 200 and expected in response.text and 'class="error"' not in response.text)
            check('Configuration and installed guard retained after action ' + str(len(checks)),
                  digest(installed / 'config.php') == config and digest(installed / '.installed.php') == guard)
            completed_actions.append(which)
            check('Only exact updater audit appended in action ' + str(len(completed_actions)),
                  updater_retained(before, command('snapshot')['snapshot'], [which], version))

        action('apply')
        check('First update serves exact current build bytes', code_matches(installed, update_files, True, version))
        phases['updated_unprepared'] = command('snapshot')['snapshot']
        check('Installed target version and source describe the actual build without relabeling', phases['updated_unprepared']['version'] == version and source != BASELINE_SOURCE)
        check('Update does not prepare optional client request history', not set(HISTORY_TABLES).intersection(phases['updated_unprepared']['tables']))
        check('First update retains original business and private data', retained(phases['baseline'], phases['updated_unprepared']))
        command('unprepared')
        command('seed')
        phases['healthy'] = command('snapshot')['snapshot']
        check('Candidate preparation retains every original published record and private key',
              retained(phases['baseline'], phases['healthy']))
        action('apply')
        check('Exact UPDATE retry retains populated request outcomes and original fences', exact_business(phases['healthy'], command('snapshot')['snapshot']))
        action('recover')
        check('Recovery restores published files and removes candidate request service', code_matches(installed, baseline_files)
              and not (installed / 'app/providers/registered_nameserver_requests.php').exists())
        phases['recovered'] = command('snapshot')['snapshot']
        check('Old files retain every request event head grant ledger readback account and key', exact_business(phases['healthy'], phases['recovered']))
        action('apply')
        check('Reapplication restores exact candidate source bytes', code_matches(installed, update_files, True, version))
        command('verify')
        phases['reapplied'] = command('snapshot')['snapshot']
        check('Historical replay preserves original timestamps actors proofs and pending fences', exact_business(phases['healthy'], phases['reapplied']))
        command('damage')
        phases['damaged'] = command('snapshot')['snapshot']
        action('apply')
        command('refuse', 'refuse-after-retry')
        action('recover')
        check('Damaged history recovery still restores only old files', code_matches(installed, baseline_files)
              and exact_business(phases['damaged'], command('snapshot')['snapshot']))
        action('apply')
        command('refuse', 'refuse-after-reapply')
        phases['final_damaged'] = command('snapshot')['snapshot']
        check('File retry recovery reapply never recreate lost database history', exact_business(phases['damaged'], phases['final_damaged']))
        check('Final installed files still match the target build', code_matches(installed, update_files, True, version))
        check('Exactly seven original owner actions completed in the agreed order', tuple(completed_actions) == ACTIONS)
    except BaseException as error:
        failure = {'type': type(error).__name__}  # No raw exception/config/provider values.
    finally:
        if proc is not None:
            proc.terminate()
            try:
                proc.wait(timeout=15)
            except subprocess.TimeoutExpired:
                proc.kill(); proc.wait()
        if launcher is not None:
            launcher.close()
        for name in ['apache-release125-rnru-upgrade.log', 'apache-release125-rnru-upgrade-launcher.log']:
            summary = log_summary(work / name)
            apache_logs[name] = summary
            checks.append({'name': 'Private terminated log is bounded without PHP diagnostics ' + name,
                           'passed': summary['present'] and summary['within_bound'] and summary['php_diagnostic'] is False})
        retention = qa / 'apache-registered-nameserver-requests-upgrade-retention.json'
        with retention.open('x', encoding='utf-8') as handle:
            json.dump({'source': source, 'baseline_source': BASELINE_SOURCE, 'phases': phases,
                       'completed_actions': completed_actions}, handle, indent=2)
        passed = failure is None and 'final_damaged' in phases and all(c['passed'] is True for c in checks)
        with (qa / REPORT).open('x', encoding='utf-8') as handle:
            json.dump({'passed': passed, 'source': source, 'target_version': version, 'baseline_source': BASELINE_SOURCE,
                       'baseline_sha256': BASELINE_SHA, 'update_sha256': digest(update), 'selected': selected(),
                       'runtime': runtime_fact, 'fixture_sources': fixture_sources, 'checks': checks, 'failure': failure, 'helper_failures': helper_failures, 'apache_logs': apache_logs,
                       'retention_sha256': digest(retention), 'scope': 'One real published1.25 Apache subfolder/rewrite install and seven owner UPDATE/recovery actions. Published client/provider/registrar assignment history plus candidate pending/cancelled/declined/observed/unresolved client requests; two synthetic gateway sends only, no provider network. Exact historical replay sends nothing; intentional final missing-head database. Complete-journal file rollback, not interrupted file application; no browser/live acceptance'}, handle, indent=2)
    return 0 if passed else 1


if __name__ == '__main__':
    raise SystemExit(main())
