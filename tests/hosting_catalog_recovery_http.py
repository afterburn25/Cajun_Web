"""One paused hofc_ phase, real packaged staged-owner HTTP, synthetic complete journal.

Linux UI job only, after exact-source packages exist. No published-baseline or
interrupted-upgrade acceptance, browser screenshots, provider calls or retries.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import socket
import stat
import subprocess
import sys
import time
import zipfile

import requests
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[1]
PHASES = ('absent', 'empty', 'published', 'withdrawn')
BASE = 'http://127.0.0.1:8092'
PASSWORD = 'Synthetic hosting catalog owner'
SOURCE_PATHS = ('tests/hosting_catalog_recovery_http.py', 'tests/hosting_catalog_recovery_http.php',
    'tests/hosting_catalog_native.php', 'tests/hosting_catalog_recovery_native.php',
    '.github/workflows/hosting-catalog-offers.yml', 'tools/update/owner-update.php',
    'tools/build.py', 'site/app/updates/structure.php', 'site/app/hosting/catalog_recovery.php',
    'site/app/hosting/catalog_offers_storage.php', 'site/includes/auth/admin.php',
    'site/includes/security/csrf.php', 'site/includes/security/rate-limits.php',
    'site/app/staff/accounts.php', 'site/includes/runtime.php')


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def write(path, data):
    path.write_text(json.dumps(data, indent=2) + '\n', encoding='utf-8')


def fresh_directory(root, relative):
    """Every existing ancestor must be a real contained directory, including QA/runtime."""
    assert root.is_dir() and not root.is_symlink() and root.resolve() == root
    parts = relative.split('/')
    assert parts and all(p and p not in ('.','..') and '\\' not in p and ':' not in p for p in parts)
    target = root.joinpath(*parts)
    assert not os.path.lexists(target), 'Preserve previous evidence/runtime'
    current = root
    for part in parts:
        current = current / part
        assert not current.is_symlink(), 'Symlinked fixture parent is forbidden'
        if current.exists(): assert current.is_dir()
        else: current.mkdir(mode=0o700)
        assert current.resolve() == current and current.is_relative_to(root)
    return target


def package(archive, source):
    """Exact existing package contract, checked before extraction into a new site."""
    mp = archive.with_suffix('.manifest.json')
    m = json.loads(mp.read_text(encoding='utf-8'))
    assert m['source_commit'] == source and m['artifact'] == archive.name
    assert m['sha256'] == sha(archive.read_bytes())
    with zipfile.ZipFile(archive) as z:
        infos = z.infolist(); names = [i.filename for i in infos]
        assert names and len(names) == len(set(n.casefold() for n in names))
        assert set(names) == set(m['files'])
        assert all(n and not n.startswith('/') and '\\' not in n and ':' not in n
                   and not any(p in ('', '.', '..') for p in n.split('/')) for n in names)
        assert all(not i.is_dir() and stat.S_IFMT(i.external_attr >> 16) in (0, stat.S_IFREG) for i in infos)
        assert z.testzip() is None
        assert all(sha(z.read(n)) == m['files'][n] for n in names)
    return m, {'name': archive.name, 'sha256': m['sha256'], 'bytes': archive.stat().st_size,
               'members': len(names), 'manifest_sha256': sha(mp.read_bytes()), 'source': source}


def files(root):
    result = {}
    for p in sorted(root.rglob('*')):
        assert not p.is_symlink(), 'Unexpected fixture symlink'
        result[p.relative_to(root).as_posix()] = {'mode': stat.S_IMODE(p.stat().st_mode),
            **({'directory': True} if p.is_dir() else {'sha256': sha(p.read_bytes()), 'bytes': p.stat().st_size})}
    return result


def form(response):
    assert response.status_code == 200
    soup = BeautifulSoup(response.text, 'html.parser')
    forms = soup.select('form[method="post"]')
    assert len(forms) == 1 and forms[0].select_one('button[name="action"][value="recover"]') is not None
    token = forms[0].select_one('input[name="csrf"]')
    assert token is not None and re.fullmatch(r'[a-f0-9]{64}', token.get('value', ''))
    return token['value']


def main(phase):
    assert phase in PHASES and os.name == 'posix' and os.geteuid() != 0
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test' and os.environ.get('CWP_TEST_HOSTING_RECOVERY_HTTP') == '1'
    php = os.environ['PHP_BINARY']; assert Path(php).is_absolute() and Path(php).is_file()
    qa = fresh_directory(ROOT, 'qa/hosting-catalog-recovery-http/' + phase)
    runtime = fresh_directory(ROOT, 'test-runtime/hosting-recovery-http/' + phase)
    site = runtime / 'site'; private = runtime / 'private'; sessions = runtime / 'sessions'
    site.mkdir(mode=0o700); sessions.mkdir(mode=0o700)
    checks = []; commands = []; responses = []; source = {}; packages = []; seed = {}; failure = None
    server = None; log = None; prepared = False; completed = False; restored = False
    role_changed = damaged = False
    server_start_stage = None

    def check(name, ok):
        checks.append({'name': 'Packaged recovery ' + phase + ': ' + name, 'passed': bool(ok)})
        write(qa / 'progress.json', {'complete': False, 'checks': checks})
        if not ok: raise AssertionError(name)

    def fixture(op):
        child = subprocess.run([php, str(ROOT / 'tests/hosting_catalog_recovery_http.php'), op, phase],
            cwd=ROOT, capture_output=True, timeout=60)
        record = {'operation': op, 'returncode': child.returncode, 'stdout_sha256': sha(child.stdout),
                  'stderr_sha256': sha(child.stderr)}
        if child.stderr:
            try:
                d = json.loads(child.stderr)
                if (set(d) == {'operation','class','message','file','line'} and d['operation'] == op
                    and d['message'] in ('Disposable SQL operation failed.', 'Packaged recovery fixture precondition failed.')
                    and isinstance(d['class'], str) and re.fullmatch(r'[A-Za-z_\\][A-Za-z0-9_\\]{0,119}', d['class'])
                    and isinstance(d['file'], str) and re.fullmatch(r'[A-Za-z0-9_.-]{1,120}', d['file'])
                    and type(d['line']) is int and 1 <= d['line'] <= 1000000): record['diagnostic'] = d
            except (ValueError, TypeError): pass
        commands.append(record); write(qa / 'commands.json', commands)
        assert child.returncode == 0 and child.stderr == b'', 'Fixture failed: ' + op
        return json.loads(child.stdout)

    def state():
        return {'site': files(site), 'private': files(private), 'db': fixture('state')}

    try:
        head = subprocess.check_output(['git','rev-parse','HEAD'], cwd=ROOT, text=True).strip()
        check('checkout equals actual workflow source', head == os.environ['GITHUB_SHA'])
        source = {'source': head, 'tree': subprocess.check_output(['git','rev-parse','HEAD^{tree}'], cwd=ROOT, text=True).strip(),
            'run': os.environ['GITHUB_RUN_ID'], 'attempt': os.environ['GITHUB_RUN_ATTEMPT'], 'files': {}}
        for path in SOURCE_PATHS:
            raw = (ROOT / path).read_bytes()
            check('source bytes match Git: ' + path, raw == subprocess.check_output(['git','show',head + ':' + path], cwd=ROOT))
            source['files'][path] = sha(raw)
        manifests = {}
        for kind in ('COMPLETE-UPLOAD', 'UPDATE'):
            archives = list((ROOT / 'dist').glob('CajunWebPro-*-' + kind + '.zip'))
            check('one exact ' + kind + ' archive exists', len(archives) == 1)
            m, descriptor = package(archives[0], head); manifests[kind] = m; packages.append(descriptor)
            with zipfile.ZipFile(archives[0]) as z:
                check(kind + ' does not overwrite the other package', not any((site / n).exists() for n in z.namelist()))
                z.extractall(site)
        version = manifests['COMPLETE-UPLOAD']['version']; stage = 'cwp-update-' + version
        check('COMPLETE and UPDATE have the same version', manifests['UPDATE']['version'] == version)
        update_files = manifests['UPDATE']['files']; complete_files = manifests['COMPLETE-UPLOAD']['files']
        check('all staged application bytes equal this COMPLETE package', all(
            complete_files.get(n.removeprefix(stage + '/site/')) == digest
            for n, digest in update_files.items() if n.startswith(stage + '/site/')))
        check('UPDATE contains only its exact staged root', all(n.startswith(stage + '/') for n in update_files))
        check('real staged owner tool equals Git', (site / stage / 'owner-update.php').read_bytes()
            == subprocess.check_output(['git','show',head + ':tools/update/owner-update.php'], cwd=ROOT))
        native_private = ROOT / 'test-runtime/hosting-catalog-private'
        check('native private source is a real contained directory', native_private.is_dir()
            and not native_private.is_symlink() and native_private.resolve() == native_private)
        before_private = files(native_private)
        shutil.copytree(native_private, private)
        check('phase has an exact separate private-storage copy', files(private) == before_private)
        seed = fixture('prepare'); prepared = True
        check('real packaged code created the synthetic complete journal', seed['prepared'] is True and seed['phase'] == phase
              and seed['source'] == head and seed['version'] == version and seed['operations'] == 4 and seed['journal'] == 'complete')
        initial = state()
        server_start_stage = 'port_preflight'
        with socket.socket() as probe:
            # Match the PHP CLI server: reuse a closed connection, never a live listener.
            probe.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            probe.bind(('127.0.0.1',8092))
        server_start_stage = 'server_log_open'
        log = open(qa / 'server.log','wb')
        server_start_stage = None
        server = subprocess.Popen([php,'-d','session.save_path=' + str(sessions),'-S','127.0.0.1:8092','-t',str(site)],
            cwd=runtime, stdout=log, stderr=log)
        deadline = time.monotonic() + 15
        while True:
            if server.poll() is not None: raise RuntimeError('PHP server stopped')
            try:
                with socket.create_connection(('127.0.0.1',8092), timeout=.5): break
            except OSError:
                if time.monotonic() >= deadline: raise RuntimeError('PHP server did not become ready')
                time.sleep(.1)
        session = requests.Session(); session.trust_env = False
        url = BASE + '/' + stage + '/owner-update.php'
        response = session.get(url, timeout=15, allow_redirects=False); csrf = form(response)
        check('anonymous owner page is a real staged form', 'Owner email' in response.text and 'Signed in as' not in response.text)

        def post(label, expected, *, password=PASSWORD, email='hosting-staff1@example.invalid', confirm='1',
                 token=None, action='recover', status=200, rate=1, audit=None, login=False, recover=False):
            nonlocal csrf
            before = state()
            response = session.post(url, data={'csrf': csrf if token is None else token, 'email': email,
                'owner_password': password,'backup_confirm':confirm,'action':action}, timeout=15, allow_redirects=False)
            soup = BeautifulSoup(response.text, 'html.parser')
            alerts = [a.get_text(strip=True) for a in soup.select('[role="alert"]')]
            check(label + ' returns the exact expected surface', response.status_code == status and
                (alerts == [expected] if status == 200 and not audit else expected in response.text)
                and PASSWORD not in response.text and 'local-ci-only' not in response.text)
            after = state(); b = before['db']; a = after['db']
            check(label + ' preserves all business rows and table definitions', a['stable_sha256'] == b['stable_sha256'] and a['schema_sha256'] == b['schema_sha256'])
            expected_actions = sorted(b['audit_actions'] + ([audit] if audit else []))
            check(label + ' has only the specified auth/rate/audit effects', a['rate_attempts'] - b['rate_attempts'] == rate
                and a['audit_actions'] == expected_actions and a['owner_login_changed'] == (b['owner_login_changed'] or login))
            if not recover:
                check(label + ' preserves every installed/private/journal byte and mode', before['site'] == after['site'] and before['private'] == after['private'])
            else:
                check(label + ' restores the real synthetic operations', a['journal'] == 'rolled-back'
                    and all(sha((site / n).read_bytes()) == digest for n,digest in seed['original_files'].items())
                    and not (site / seed['added']).exists())
                allowed = {'.htaccess','assets/recovery-http-changed.txt','assets/recovery-http-retired.txt','assets/recovery-http-added.txt'}
                check(label + ' preserves every other installed file and media', {n:v for n,v in before['site'].items() if n not in allowed}
                    == {n:v for n,v in after['site'].items() if n not in allowed}
                    and {n:v for n,v in before['private'].items() if not n.startswith('structure-')}
                    == {n:v for n,v in after['private'].items() if not n.startswith('structure-')})
            responses.append({'name':label,'status':response.status_code,'body_sha256':sha(response.content),
                'expected_surface':expected,'before_database_sha256':b['rows_sha256'],'after_database_sha256':a['rows_sha256'],
                'before_files_sha256':sha(json.dumps(before['site'],sort_keys=True).encode()),
                'after_files_sha256':sha(json.dumps(after['site'],sort_keys=True).encode()),
                'before_private_sha256':sha(json.dumps(before['private'],sort_keys=True).encode()),
                'after_private_sha256':sha(json.dumps(after['private'],sort_keys=True).encode())})
            write(qa / 'responses.json', responses)
            if status == 200: csrf = form(response)
            return response

        # Change only the isolated staging copy, never source or the built package.
        victim = site / stage / 'site/app/hosting/catalog_recovery.php'; original = victim.read_bytes()
        victim.write_bytes(original + b'\n// isolated staged-manifest refusal probe\n')
        try:
            bad = session.get(url, timeout=15, allow_redirects=False)
            check('mismatched staged package GET is refused', bad.status_code == 503 and 'Update support is missing or mismatched.' in bad.text)
            post('mismatched staged package POST','Update support is missing or mismatched.',status=503,rate=0)
        finally: victim.write_bytes(original)
        check('staged manifest probe restores exact original files', files(site) == initial['site'])
        post('wrong CSRF','Your session expired. Refresh the page and try again.',token='0' * 64,rate=0)
        post('anonymous wrong password','Owner authentication failed.',password='wrong')
        post('administrator cannot authenticate as owner','Owner authentication failed.',email='hosting-staff2@example.invalid')
        post('owner authenticates and inspects','<h2>Complete</h2>',action='inspect',audit='structure.inspect',login=True)
        post('current password is required','Your current owner password is required.',password='wrong')
        post('backup confirmation is required','Confirm a recoverable backup of website files, database and configured private storage first.',confirm='')
        if phase == 'empty':
            fixture('demote'); role_changed = True
            try: post('current role is rechecked after owner login','Your current owner password is required.')
            finally: fixture('restore-role'); role_changed = False
        if phase == 'published':
            fixture('damage'); damaged = True
            try: post('corrupt established history','Hosting offer history needs recovery. Restore its original tables, settings and audit records.')
            finally: fixture('restore-damage'); damaged = False
        if phase in ('absent','empty'):
            post('valid empty-history recovery','<h2>Rolled-back</h2>',audit='structure.recover',recover=True)
        else:
            post('retained publication prevents file recovery','Published hosting offers need the current website files to preserve their history and images. Automatic file recovery is blocked. Restore a matching backup of website files, database and private storage with your hosting provider if recovery is needed.')
            check('refused phase retains the complete original journal', fixture('state')['journal'] == 'complete')
        check('native private storage remains unchanged', files(native_private) == before_private)
        completed = True
    except Exception as error:
        # Do not dump HTTP bodies, request values, fixture stdout or local configuration.
        failure = {'class':type(error).__name__,'message':'Packaged recovery HTTP did not finish; inspect last named check and finite command diagnostic.',
                   'last_check':checks[-1]['name'] if checks else None}
        if server_start_stage is not None:
            failure['server_start'] = {'stage': server_start_stage, 'errno': error.errno
                if isinstance(error, OSError) and type(error.errno) is int and 1 <= error.errno <= 4095 else None}
        checks.append({'name':'Packaged recovery ' + phase + ': completes without unexpected failure','passed':False})
    finally:
        if server is not None:
            server.terminate()
            try: server.wait(timeout=10)
            except subprocess.TimeoutExpired: server.kill(); server.wait(timeout=10)
        if log is not None: log.close()
        if (qa / 'server.log').exists():
            server_log = (qa / 'server.log').read_text(encoding='utf-8', errors='replace')
            checks.append({'name':'Packaged recovery ' + phase + ': server log has no PHP warning notice fatal parse or deprecation',
                'passed':re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated)|Uncaught (?:Error|Exception)|CWP audit write failed', server_log, re.I) is None})
        if prepared:
            try:
                if role_changed: fixture('restore-role')
                if damaged: fixture('restore-damage')
                restored = fixture('cleanup')['restored'] is True
            except Exception:
                restored = False
            checks.append({'name':'Packaged recovery ' + phase + ': restores only verified HTTP bookkeeping and exact parent rows','passed':restored})
        passed = completed and restored and all(c['passed'] is True for c in checks)
        write(qa / 'results.json', {'passed':passed,'phase':phase,'source':source,'packages':packages,'runtime':seed,
            'checks':checks,'failure':failure,'scope':'Same-source packaged updater HTTP with synthetic complete four-operation journal. No published-baseline, interrupted-upgrade, Apache, pixel, provider or release acceptance.',
            'state_limits':'SQL row equality excludes AUTO_INCREMENT allocation gaps; only verified login/rate/updater-audit rows are restored after HTTP. Session files remain private runtime evidence and are not uploaded.'})
    return 0 if passed else 1


if __name__ == '__main__':
    if len(sys.argv) != 2 or sys.argv[1] not in PHASES: raise SystemExit(2)
    raise SystemExit(main(sys.argv[1]))
