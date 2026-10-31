"""Real HTTP installer/updater + retained SQL, on disposable loopback fixtures.

This is NOT Apache/rewrite or real-provider acceptance. Private fixture bytes and
credentials are never uploaded as evidence. Existing records are compared by
primary-key/column hashes, not merely counts or a version label.
"""
from pathlib import Path
import hashlib
import json
import os
import re
import shutil
import subprocess
import time
import traceback
from urllib.parse import urlsplit
import requests
from bs4 import BeautifulSoup
from release_126_upgrade_cases import fields, unpack, OWNER, BASELINE_SHA

ROOT = Path(__file__).resolve().parents[1]
WORK = ROOT / 'test-runtime/admin-20i-populated'
OUT = ROOT / 'qa/admin-20i-populated'
ORIGIN = 'http://127.0.0.1:8093'
VERSION = (ROOT / 'VERSION').read_text().strip()
checks = []
scopes = []


def check(name, result):
    checks.append({'name': name, 'passed': bool(result)})
    (OUT / 'progress.json').write_text(json.dumps({'complete': False, 'checks': checks}, indent=2) + '\n')
    if not result:
        raise AssertionError(name)


class LocalSession(requests.Session):
    def send(self, request, **kwargs):
        parsed = urlsplit(request.url)
        if parsed.scheme != 'http' or parsed.hostname != '127.0.0.1' or parsed.port != 8093:
            raise RuntimeError('Non-loopback HTTP request refused')
        return super().send(request, **kwargs)


def submit(session, target, action, values=None):
    target, data = fields(session.get(target, timeout=120), action)
    data.update(values or {})
    return session.post(target, data=data, timeout=120)


def same_retained(before, after, *, allow_settings_additions=True):
    if before['configuration_sha256'] != after['configuration_sha256'] or before['lock_sha256'] != after['lock_sha256']:
        return False
    for name, fact in before['private_files'].items():
        if after['private_files'].get(name) != fact:
            return False
    for name, table in before['tables'].items():
        target = after['tables'].get(name)
        if not target:
            return False
        for key, columns in table['rows'].items():
            if target['rows'].get(key) != columns:
                return False
        # Only migration settings and append-only audit may gain rows.
        if name not in ('audit', 'settings') and target['count'] != table['count']:
            return False
        if name == 'settings' and not allow_settings_additions and target['count'] != table['count']:
            return False
    return True


def assert_no_error(response):
    return response.status_code == 200 and not re.search(
        r'PHP (?:Fatal error|Warning|Notice)|We could not complete this request', response.text)


def main():
    if os.environ.get('CWP_TEST_DATABASE') != 'cwp_test' or os.environ.get('CWP_TEST_HOST') != '127.0.0.1':
        raise SystemExit('Disposable loopback database only')
    if VERSION != '1.26.1' or WORK.exists():
        raise SystemExit('Expected fresh 1.26.1 upgrade fixture; never overwrite evidence')
    rc = ROOT / 'test-runtime/rc126-original'
    published = ROOT / 'test-runtime/CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
    update = ROOT / f'dist/CajunWebPro-{VERSION}-UPDATE.zip'
    if not rc.is_dir() or not update.is_file():
        raise SystemExit('Exact RC snapshot and clean-commit UPDATE package required')
    if hashlib.sha256(published.read_bytes()).hexdigest() != BASELINE_SHA:
        raise SystemExit('Published baseline bytes changed')
    WORK.mkdir(parents=True)
    OUT.mkdir(parents=True, exist_ok=False)
    image = os.environ['CWP_TEST_IMAGE']
    docker = ['docker', 'run', '--rm', '--network', 'host', '--user', f'{os.getuid()}:{os.getgid()}',
              '-v', f'{ROOT}:/workspace', '-w', '/workspace']
    for name in ('CWP_TEST_DATABASE', 'CWP_TEST_HOST', 'CWP_TEST_PORT', 'CWP_TEST_USER', 'CWP_TEST_PASSWORD'):
        docker += ['-e', name]

    def probe(installed, mode):
        path = '/workspace/' + installed.relative_to(ROOT).as_posix()
        raw = subprocess.check_output(docker + [image, 'php', 'tests/admin_20i_upgrade_probe.php', path, mode],
                                      cwd=ROOT, timeout=120)
        return json.loads(raw)

    complete = False
    try:
        for baseline, initial in [('125', '1.25.0'), ('126', '1.26.0')]:
            for nested in (False, True):
                label = baseline + ('-subfolder' if nested else '-root')
                start = len(checks)
                workspace = WORK / label
                web = workspace / 'web'
                installed = web / 'studio' if nested else web
                installed.mkdir(parents=True)
                private_parent = workspace / 'private-parent'
                private_parent.mkdir(mode=0o700)
                if baseline == '125':
                    unpack(published, installed, BASELINE_SHA)
                else:
                    shutil.copytree(rc, installed, dirs_exist_ok=True)
                base = ORIGIN + ('/studio' if nested else '')
                prefix = 'ug' + baseline + ('s_' if nested else 'r_')
                server = 'cwp-upgrade-' + label
                subprocess.run(docker[:2] + ['-d'] + docker[2:] + ['--name', server, image, 'php',
                               '-d', 'display_errors=0', '-S', '127.0.0.1:8093', '-t',
                               '/workspace/' + web.relative_to(ROOT).as_posix()], check=True, capture_output=True)
                try:
                    anon = LocalSession()
                    for _ in range(60):
                        try:
                            response = anon.get(base + '/install.php', timeout=2)
                            break
                        except requests.ConnectionError:
                            time.sleep(.25)
                    else:
                        raise RuntimeError('Disposable installer server unavailable')
                    response = submit(anon, base + '/install.php', 'install', {
                        'db_host': '127.0.0.1', 'db_port': os.environ['CWP_TEST_PORT'], 'db_name': 'cwp_test',
                        'db_user': os.environ['CWP_TEST_USER'], 'db_password': os.environ['CWP_TEST_PASSWORD'],
                        'db_prefix': prefix, 'owner_name': 'Synthetic upgrade owner',
                        'owner_email': 'organized@example.invalid', 'owner_password': OWNER, 'owner_confirm': OWNER,
                        'site_url': base, 'confirm_install': '1', 'seed': '1', 'preview': '1',
                        'private_parent': '/workspace/' + private_parent.relative_to(ROOT).as_posix()})
                    result = response.json()
                    check(label + ' actual baseline installer succeeds', response.status_code == 200 and result.get('ok') is True)
                    check(label + ' original code seeded without provider calls', probe(installed, 'seed') == {'seeded': True, 'version': initial})
                    before = probe(installed, 'snapshot')
                    check(label + ' populated original has customers invoices products and encrypted provider records',
                          all(before['tables'][t]['count'] > 0 for t in ['studio_clients', 'studio_jobs', 'studio_invoices', 'catalog_products', 'provider_profiles']))
                    (OUT / (label + '-before.json')).write_text(json.dumps(before, indent=2) + '\n')
                    admin = base + '/admin/index.php'
                    owner = LocalSession()
                    response = submit(owner, admin + '?view=login', 'login', {'email': 'organized@example.invalid', 'password': OWNER})
                    check(label + ' original owner signs in via real HTTP', assert_no_error(response) and 'view=login' not in response.url and 'view=catalog' in response.text)
                    unpack(update, installed)
                    updater = base + f'/cwp-update-{VERSION}/owner-update.php'
                    response = owner.get(updater, timeout=120)
                    check(label + ' staged updater recognizes current owner without repeat password',
                          assert_no_error(response) and 'Signed in as' in response.text and 'name="owner_password"' not in response.text)
                    target, data = fields(response, 'apply')
                    response = owner.post(target, data={**data, 'csrf': 'invalid', 'backup_confirm': '1'}, timeout=120)
                    denied = probe(installed, 'snapshot')
                    check(label + ' invalid CSRF refuses updater without changing retained SQL',
                          response.status_code == 200 and 'Your session expired. Refresh the page and try again.' in response.text
                          and '<h2>Complete</h2>' not in response.text and denied['version'] == initial
                          and same_retained(before, denied, allow_settings_additions=False))
                    response = submit(owner, updater, 'apply')
                    check(label + ' backup confirmation remains required', 'Confirm a recoverable backup' in response.text and probe(installed, 'snapshot')['version'] == initial)
                    response = submit(owner, updater, 'inspect', {'backup_confirm': '1'})
                    check(label + ' exact installed version accepted by owner updater', assert_no_error(response) and f'Starting release: {initial}' in response.text and not BeautifulSoup(response.text, 'html.parser').select('.error'))
                    restricted = LocalSession()
                    submit(restricted, admin + '?view=login', 'login', {'email': 'restricted@example.invalid', 'password': OWNER})
                    response = submit(restricted, updater, 'apply', {'backup_confirm': '1'})
                    check(label + ' authenticated nonowner cannot apply update', 'An active owner session is required' in response.text and probe(installed, 'snapshot')['version'] == initial)
                    response = submit(owner, updater, 'apply', {'backup_confirm': '1'})
                    after = probe(installed, 'snapshot')
                    check(label + ' authenticated owner applies actual UPDATE over HTTP', assert_no_error(response) and '<h2>Complete</h2>' in response.text and after['version'] == VERSION)
                    check(label + ' all preexisting SQL column values and private bytes retained', same_retained(before, after, allow_settings_additions=False))
                    check(label + ' provider credential still decrypts after update', probe(installed, 'capabilities')['provider_secret_retained'] is True)
                    if nested:
                        response = submit(owner, updater, 'recover', {'backup_confirm': '1'})
                        final = probe(installed, 'snapshot')
                        check(label + ' owner-authenticated HTTP recovery restores baseline version', '<h2>Rolled-back</h2>' in response.text and final['version'] == initial)
                        check(label + ' HTTP recovery preserves populated SQL and private data', same_retained(before, final, allow_settings_additions=False))
                    else:
                        response = submit(owner, admin + '?view=studio-upgrade', 'studio_migrate', {'confirm': '1'})
                        check(label + ' actual database update action succeeds', assert_no_error(response) and 'Database update completed' in response.text)
                        catalog = admin + '?view=catalog&import_source=package.types'
                        response = owner.get(catalog, timeout=120)
                        check(label + ' migrated catalog shows retained product and direct plan setup', assert_no_error(response) and 'UPGRADE-RETAIN' in response.text and 'catalog_import_catalog_prepare' in response.text)
                        response = submit(owner, catalog, 'catalog_import_catalog_prepare')
                        check(label + ' new catalog schema prepares through authenticated form without password', assert_no_error(response) and probe(installed, 'capabilities').get('package_types_ready') is True)
                        final = probe(installed, 'snapshot')
                        check(label + ' additive schema preparation retains every original row and key', same_retained(before, final))
                        for view in ['dashboard', 'catalog', 'provider-settings', 'studio-upgrade']:
                            response = owner.get(admin + '?view=' + view, timeout=120)
                            check(label + ' upgraded ' + view + ' loads without repeated password fields', assert_no_error(response) and 'view=login' not in response.url and not BeautifulSoup(response.text, 'html.parser').select('input[name="current_password"],input[name="owner_password"]'))
                        response = submit(owner, admin + '?view=studio-upgrade', 'studio_migrate', {'confirm': '1'})
                        check(label + ' repeated database update preserves existing records', assert_no_error(response) and same_retained(final, probe(installed, 'snapshot')))
                        probe(installed, 'revoke')
                        response = owner.get(updater, timeout=120)
                        check(label + ' revoked owner session cannot authorize updater', 'name="owner_password"' in response.text and 'Signed in as' not in response.text)
                    (OUT / (label + '-after.json')).write_text(json.dumps(final, indent=2) + '\n')
                    scopes.append({'scope': label, 'baseline': initial, 'checks': len(checks) - start,
                                   'sql_tables_retained': len(before['tables']), 'passed': True})
                    for session in [anon, owner, restricted]:
                        session.close()
                finally:
                    log = subprocess.run(['docker', 'logs', server], capture_output=True, text=True)
                    # Synthetic HTTP access/server errors only; never export config.php or private files.
                    text = log.stdout + log.stderr
                    (OUT / (label + '-server.log')).write_text(text)
                    subprocess.run(['docker', 'rm', '-f', server], capture_output=True)
                check(label + ' no PHP errors or warnings during installed upgrade', re.search(r'PHP (?:Fatal error|Warning|Notice|Parse error|Deprecated):', text) is None)
        complete = True
    except Exception as error:
        checks.append({'name': type(error).__name__ + ': ' + str(error), 'passed': False})
        (OUT / 'failure.txt').write_text(traceback.format_exc())
        raise
    finally:
        report = {'passed': complete and len(scopes) == 4 and all(c['passed'] for c in checks),
                  'source_sha': os.environ.get('CWP_SOURCE_SHA'), 'version': VERSION, 'scopes': scopes, 'checks': checks,
                  'scope': 'Actual installers, SQL record retention and owner HTTP updater; synthetic records, no provider/merchant calls. Root upgrades include database/catalog preparation; subfolder scopes test file rollback before schema changes.',
                  'excluded': ['Apache/rewrite', 'real provider catalog', 'finance payoff-state fixture', 'live site', 'full historical regression suite']}
        (OUT / 'results.json').write_text(json.dumps(report, indent=2) + '\n')
        print(json.dumps({'passed': report['passed'], 'scopes': len(scopes), 'checks': len(checks)}))


if __name__ == '__main__':
    main()
