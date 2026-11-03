"""Published 1.25 -> current build, real owner UPDATE/recovery; no version invention.

One isolated subfolder/rewrite Apache installation. Existing upgrade producers are
imported for their launcher/form/archive helpers, never invoked or edited.
"""
from pathlib import Path, PurePosixPath
import base64
import hashlib
import json
import os
import re
import subprocess
import time
import zipfile

BASELINE_SOURCE = 'b79c67110829126916e3a65e616191542448bccc'
BASELINE_SHA = 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39'
BASELINE_NAME = 'CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
BASELINE_MEMBERS = 2895
OWNER = 'Synthetic nameserver upgrade owner passphrase'
PREFIX = 'rnsu_'
REPORT = 'apache-registered-nameservers-upgrade-results.json'
ROOT = Path(__file__).resolve().parents[1]


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
    files = archive_map(archive, BASELINE_SHA)
    history = json.loads((repo / 'tools/legacy-release-files.json').read_text(encoding='utf-8'))
    assert len(files) == BASELINE_MEMBERS
    assert history.get('1.25.0', {}).get('files') == files, 'Published 1.25 inventory must be explicitly integrated first'
    assert not any('registered_nameservers' in name or 'registered_nameserver_' in name for name in files)
    return archive, files


def target_inputs(repo):
    version = (repo / 'VERSION').read_text(encoding='utf-8').strip()
    assert re.fullmatch(r'[1-9][0-9]*\.[0-9]+\.[0-9]+', version)
    archive = repo / 'dist' / ('CajunWebPro-' + version + '-UPDATE.zip')
    descriptor = json.loads(archive.with_suffix('.manifest.json').read_text(encoding='utf-8'))
    source = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=repo, text=True).strip()
    assert re.fullmatch(r'[a-f0-9]{40}', source)
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
    for name in ('app/providers/registered_nameservers.php', 'app/providers/registered_nameservers_storage.php',
                 'database/schema/registered_nameservers_schema.php', 'app/providers/inventory.php'):
        assert files[stage + 'site/' + name] == digest(repo / 'site' / name)
    return archive, version, source, files


def fresh_path(path, root):
    path, root = Path(path), Path(root).resolve()
    assert path.is_absolute() and path.is_relative_to(root)
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


def main():
    assert os.name == 'posix' and os.geteuid() != 0
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test' and os.environ.get('CWP_TEST_HOST') == '127.0.0.1'
    import requests
    from acceptance_environment import selected, php_matches
    from release_125_upgrade_acceptance import launch
    from release_125_upgrade_cases import fields, unpack
    from release_125_retention import retained, exact_business
    php = os.environ['PHP_BINARY']
    assert Path(php).is_absolute() and Path(php).is_file()
    baseline, baseline_files = baseline_inputs(ROOT)
    update, version, source, update_files = target_inputs(ROOT)
    runtime = ROOT / 'test-runtime/registered-nameservers-upgrade'
    qa = ROOT / 'qa'
    fresh_path(runtime, ROOT)
    outputs = [REPORT, 'apache-registered-nameservers-upgrade-retention.json',
               'apache-release125-rns-upgrade.log', 'apache-release125-rns-upgrade-launcher.log']
    for name in outputs:
        fresh_path(qa / name, ROOT)
    qa.mkdir(exist_ok=True)
    work = runtime / 'rns-upgrade'
    web = work / 'web'
    installed = web / 'subfolder'
    installed.mkdir(parents=True)
    unpack(baseline, installed, BASELINE_SHA)
    checks, phases, runtime_fact, helper_failures = [], {}, {}, []
    failure = None
    proc = launcher = None

    def check(name, value):
        assert isinstance(value, bool)
        checks.append({'name': name, 'passed': value})
        if not value:
            raise AssertionError(name)

    def command(mode, phase=None):
        result = subprocess.run([php, str(ROOT / 'tests/registered_nameservers_upgrade_fixture.php'),
                                 str(installed), mode, str(web)], capture_output=True, text=True, timeout=180)
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
        proc, launcher, origin = launch(work, web, True)
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
        probe = installed / 'rns-runtime-probe.php'
        probe.write_text("<?php header('Content-Type: application/json');echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI]);", encoding='utf-8')
        try:
            response = requests.get(base + '/rns-runtime-probe.php', timeout=10)
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
                     'db_prefix': PREFIX, 'owner_name': 'Nameserver upgrade owner', 'owner_email': 'rns-upgrade@example.invalid',
                     'owner_password': OWNER, 'owner_confirm': OWNER, 'site_url': base,
                     'confirm_install': '1', 'seed': '1', 'preview': '1'})
        response = session.post(target, data=form, timeout=120)
        body = response.json()
        check('Actual immutable published 1.25 installation completes', response.status_code == 200 and body.get('ok') is True)
        check('Private store is outside the served tree', not Path(body['storage']).resolve().is_relative_to(web.resolve()))
        check('Published code bytes match before updating', code_matches(installed, baseline_files))
        phases['baseline'] = command('snapshot')['snapshot']
        check('Published baseline has no nameserver history', not any(n.startswith('registered_nameserver_') for n in phases['baseline']['tables']))
        config = digest(installed / 'config.php')
        guard = digest(installed / '.installed.php')
        unpack(update, installed)
        updater = base + '/cwp-update-' + version + '/owner-update.php'

        def action(which):
            target, form = fields(session.get(updater, timeout=120), which)
            form.update({'email': 'rns-upgrade@example.invalid', 'owner_password': OWNER, 'backup_confirm': '1'})
            response = session.post(target, data=form, timeout=120)
            expected = '<h2>Complete</h2>' if which == 'apply' else '<h2>Rolled-back</h2>'
            check('Owner updater ' + which + ' action ' + str(sum(c['name'].startswith('Owner updater') for c in checks) + 1),
                  response.status_code == 200 and expected in response.text and 'class="error"' not in response.text)
            check('Configuration and installed guard retained after action ' + str(len(checks)),
                  digest(installed / 'config.php') == config and digest(installed / '.installed.php') == guard)

        action('apply')
        check('First update serves exact current build bytes', code_matches(installed, update_files, True, version))
        phases['updated_unprepared'] = command('snapshot')['snapshot']
        check('Update does not enable nameserver schema', not any(n.startswith('registered_nameserver_') for n in phases['updated_unprepared']['tables']))
        check('First update retains original business and private data', retained(phases['baseline'], phases['updated_unprepared']))
        command('seed')
        phases['healthy'] = command('snapshot')['snapshot']
        action('apply')
        check('Exact UPDATE retry retains nameserver history', exact_business(phases['healthy'], command('snapshot')['snapshot']))
        action('recover')
        check('Recovery restores actual published code and removes new reader', code_matches(installed, baseline_files)
              and not (installed / 'app/providers/registered_nameservers.php').exists())
        phases['recovered'] = command('snapshot')['snapshot']
        check('Old files retain all nameserver bindings sets and audits', exact_business(phases['healthy'], phases['recovered']))
        action('apply')
        check('Reapplication restores exact current reader bytes', code_matches(installed, update_files, True, version))
        command('verify')
        check('Reapplication preserves original nameserver timestamps and witnesses', exact_business(phases['healthy'], command('snapshot')['snapshot']))
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
        log = qa / 'apache-release125-rns-upgrade.log'
        if log.exists():
            clean = re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', log.read_text(encoding='utf-8', errors='replace')) is None
            checks.append({'name': 'Terminated Apache log has no PHP diagnostic', 'passed': clean})
        retention = qa / 'apache-registered-nameservers-upgrade-retention.json'
        with retention.open('x', encoding='utf-8') as handle:
            json.dump({'source': source, 'baseline_source': BASELINE_SOURCE, 'phases': phases}, handle, indent=2)
        passed = failure is None and 'final_damaged' in phases and all(c['passed'] is True for c in checks)
        with (qa / REPORT).open('x', encoding='utf-8') as handle:
            json.dump({'passed': passed, 'source': source, 'target_version': version, 'baseline_source': BASELINE_SOURCE,
                       'baseline_sha256': BASELINE_SHA, 'update_sha256': digest(update), 'selected': selected(),
                       'runtime': runtime_fact, 'checks': checks, 'failure': failure, 'helper_failures': helper_failures,
                       'retention_sha256': digest(retention), 'scope': 'One real Apache subfolder/rewrite installed update/recovery; synthetic GET only; intentional final damaged test database; no browser or live provider acceptance'}, handle, indent=2)
    return 0 if passed else 1


if __name__ == '__main__':
    raise SystemExit(main())
