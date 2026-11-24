"""Published 1.26.1 -> 1.27.0 HTTP updater and retained SQL on loopback fixtures.

This is NOT Apache/rewrite or real-provider acceptance. Private fixture bytes and
credentials are never uploaded as evidence. Existing records are compared by
primary-key/column hashes, not merely counts or a version label.
"""
from pathlib import Path
import hashlib
import json
import os
import re
import subprocess
import time
import traceback
import requests
from bs4 import BeautifulSoup
from release_126_upgrade_cases import fields, OWNER
from release_127_archive import candidate_snapshot, extract_snapshot, published_snapshot
from release_127_loopback_session import LocalSession

ROOT = Path(__file__).resolve().parents[1]
WORK = ROOT / 'test-runtime/release127-populated'
OUT = ROOT / 'qa/release127-populated'
ORIGIN = 'http://127.0.0.1:8093'
VERSION = (ROOT / 'VERSION').read_text().strip()
BASELINE_SHA = '7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3'
BASELINE_BYTES = 17_429_306
BASELINE_SOURCE = 'f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391'
MIGRATIONS = {
    'software_onboarding_version': '1', 'client_identity_schema_version': '1',
    'hosting_storefront_version': '1', 'gateway_receipts_schema_version': '1',
    'hosting_payments_schema_version': '1', 'project_private_notes_schema_version': '1',
    'mail_copies_schema_version': '1', 'mail_drafts_html_ready': '1',
    'mail_files_schema_version': '1', 'mail_flags_schema_version': '1',
    'mail_inbox_schema_version': '2', 'mail_messages_schema_version': '2',
    'mail_replies_schema_version': '1',
}
BASELINE_MARKERS = {marker: ('1' if marker in {'mail_inbox_schema_version', 'mail_messages_schema_version'} else '')
                    for marker in MIGRATIONS}
DISABLED_FUNCTIONS = ('curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,'
                      'stream_socket_server,stream_socket_sendto,socket_create,socket_create_pair,'
                      'socket_import_stream,socket_connect,socket_send,socket_sendto,socket_sendmsg,'
                      'socket_write,ftp_connect,ftp_ssl_connect,ftp_nb_connect,imap_open,ssh2_connect,'
                      'ldap_connect,pg_connect,dns_get_record,gethostbyaddr,gethostbyname,gethostbynamel,'
                      'checkdnsrr,snmpget,snmpgetnext,snmpwalk,mail,exec,shell_exec,system,passthru,'
                      'popen,proc_open,pcntl_exec')
PHP_OPTIONS = ['-d', 'allow_url_fopen=0', '-d', 'disable_functions=' + DISABLED_FUNCTIONS]
checks = []
scopes = []


def check(name, result):
    checks.append({'name': name, 'passed': bool(result)})
    (OUT / 'progress.json').write_text(json.dumps({'complete': False, 'checks': checks}, indent=2) + '\n')
    if not result:
        raise AssertionError(name)


def submit(session, target, action, values=None):
    target, data = fields(session.get(target, timeout=120), action)
    data.update(values or {})
    return session.post(target, data=data, timeout=120)


def same_retained(before, after, *, allow_settings_additions=True, allow_table_additions=(),
                  allow_null_columns=None):
    allow_null_columns = allow_null_columns or {}
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
            target_columns = target['rows'].get(key)
            allowed = set(allow_null_columns.get(name, ()))
            if (not isinstance(target_columns, dict)
                    or any(target_columns.get(column) != digest for column, digest in columns.items())
                    or set(target_columns) - set(columns) != allowed
                    or any(target_columns[column] != hashlib.sha256(b'null').hexdigest()
                           for column in allowed)):
                return False
        # Only migration settings and append-only audit may gain rows.
        if name not in ('audit', 'settings') and name not in allow_table_additions and target['count'] != table['count']:
            return False
        if name == 'settings' and not allow_settings_additions and target['count'] != table['count']:
            return False
    return True


def assert_no_error(response):
    return response.status_code == 200 and not re.search(
        r'PHP (?:Fatal error|Warning|Notice)|We could not complete this request', response.text)


def candidate_fixture_valid(result):
    state = result.get('state', {})
    return (result.get('network_calls') == 0 and result.get('live_operations') == 0
            and state.get('transport_guard') is True and state.get('network_calls') == 0
            and state.get('live_operations') == 0 and state.get('setting') is True
            and state.get('private_present') is True and state.get('private_bytes', 0) > 32
            and re.fullmatch(r'[0-9a-f]{64}', state.get('private_sha256', '')) is not None
            and state.get('audit_rows') == 1)


def representative_fixture_valid(result):
    state = result.get('state', {})
    counts = state.get('counts', {})
    states = state.get('states', {})
    return (result.get('network_calls') == 0 and result.get('live_operations') == 0
            and state.get('transport_guard') is True and state.get('network_calls') == 0
            and state.get('live_operations') == 0 and state.get('marker') is True
            and state.get('audit_rows') == 1 and len(counts) == 14
            and all(value == 1 for value in counts.values())
            and len(states) == 7 and all(value is True for value in states.values())
            and re.fullmatch(r'[0-9a-f]{64}', state.get('fingerprint', '')) is not None)


def main():
    if os.environ.get('CWP_TEST_DATABASE') != 'cwp_test' or os.environ.get('CWP_TEST_HOST') != '127.0.0.1':
        raise SystemExit('Disposable loopback database only')
    if os.environ.get('CWP_TEST_DISABLED_FUNCTIONS') != DISABLED_FUNCTIONS:
        raise SystemExit('Exact outbound PHP transport guard is required')
    if not os.environ.get('CWP_RUN_ID', '').isdigit() or os.environ.get('CWP_RUN_ATTEMPT') != '1':
        raise SystemExit('Fresh first-attempt hosted run identity is required')
    if re.fullmatch(r'[0-9a-f]{40}', os.environ.get('CWP_SOURCE_TREE', '')) is None:
        raise SystemExit('Exact candidate tree identity is required')
    if VERSION != '1.27.0' or WORK.exists():
        raise SystemExit('Expected fresh 1.27.0 upgrade fixture; never overwrite evidence')
    published = ROOT / 'test-runtime/CajunWebPro-1.26.1-COMPLETE-UPLOAD.zip'
    update = ROOT / f'dist/CajunWebPro-{VERSION}-UPDATE.zip'
    if not update.is_file():
        raise SystemExit('Exact clean-commit 1.27.0 UPDATE package required')
    published_bytes = published_snapshot(published, BASELINE_BYTES, BASELINE_SHA)
    update_bytes, candidate_identity = candidate_snapshot(
        update, os.environ.get('CWP_SOURCE_SHA', ''), VERSION)
    WORK.mkdir(parents=True)
    OUT.mkdir(parents=True, exist_ok=False)
    image = os.environ['CWP_TEST_IMAGE']
    docker = ['docker', 'run', '--rm', '--network', 'host', '--user', f'{os.getuid()}:{os.getgid()}',
              '-v', f'{ROOT}:/workspace', '-w', '/workspace']
    for name in ('CWP_TEST_DATABASE', 'CWP_TEST_HOST', 'CWP_TEST_PORT', 'CWP_TEST_USER', 'CWP_TEST_PASSWORD'):
        docker += ['-e', name]

    def probe(installed, mode):
        path = '/workspace/' + installed.relative_to(ROOT).as_posix()
        raw = subprocess.check_output(docker + [image, 'php', *PHP_OPTIONS,
                                      'tests/release_127_upgrade_probe.php', path, mode],
                                      cwd=ROOT, timeout=120)
        return json.loads(raw)

    complete = False
    try:
        for baseline, initial in [('1261', '1.26.1')]:
            for nested in (False, True):
                label = baseline + ('-subfolder' if nested else '-root')
                start = len(checks)
                workspace = WORK / label
                web = workspace / 'web'
                installed = web / 'studio' if nested else web
                installed.mkdir(parents=True)
                private_parent = workspace / 'private-parent'
                private_parent.mkdir(mode=0o700)
                extract_snapshot(published_bytes, installed)
                base = ORIGIN + ('/studio' if nested else '')
                prefix = 'ug127' + ('s_' if nested else 'r_')
                server = 'cwp-upgrade-' + label
                subprocess.run(docker[:2] + ['-d'] + docker[2:] + ['--name', server, image, 'php',
                               *PHP_OPTIONS, '-d', 'display_errors=0', '-S', '127.0.0.1:8093', '-t',
                               '/workspace/' + web.relative_to(ROOT).as_posix()], check=True, capture_output=True)
                try:
                    anon = LocalSession(8093)
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
                    owner = LocalSession(8093)
                    response = submit(owner, admin + '?view=login', 'login', {'email': 'organized@example.invalid', 'password': OWNER})
                    check(label + ' original owner signs in via real HTTP', assert_no_error(response) and 'view=login' not in response.url and 'view=catalog' in response.text)
                    extract_snapshot(update_bytes, installed)
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
                    restricted = LocalSession(8093)
                    submit(restricted, admin + '?view=login', 'login', {'email': 'restricted@example.invalid', 'password': OWNER})
                    response = submit(restricted, updater, 'apply', {'backup_confirm': '1'})
                    check(label + ' authenticated nonowner cannot apply update', 'An active owner session is required' in response.text and probe(installed, 'snapshot')['version'] == initial)
                    path = '/workspace/' + installed.relative_to(ROOT).as_posix()
                    interrupted = subprocess.run(docker + [image, 'php', *PHP_OPTIONS,
                                                 'tests/structure_worker.php', path, 'apply', '2'],
                                                 capture_output=True, text=True, timeout=120)
                    check(label + ' update interruption is retained after a durable journal',
                          interrupted.returncode != 0 and 'Synthetic interruption after durable journal' in interrupted.stderr)
                    check(label + ' interrupted update enters maintenance protection', owner.get(admin, timeout=120).status_code == 403)
                    response = submit(owner, updater, 'apply', {'backup_confirm': '1'})
                    after = probe(installed, 'snapshot')
                    check(label + ' authenticated owner applies actual UPDATE over HTTP', assert_no_error(response) and '<h2>Complete</h2>' in response.text and after['version'] == VERSION)
                    check(label + ' all preexisting SQL column values and private bytes retained', same_retained(before, after, allow_settings_additions=False))
                    check(label + ' provider credential still decrypts after update', probe(installed, 'capabilities')['provider_secret_retained'] is True)
                    markers_before = probe(installed, 'release-state')
                    check(label + ' new optional release schemas remain absent before owner review',
                          markers_before['markers'] == BASELINE_MARKERS
                          and markers_before['side_effect_rows'] == 0
                          and markers_before['mail_drafts_body_html'] is False
                          and markers_before['legacy_mail_draft'] == {
                              'present': True, 'subject': 'Published 1.26.1 retained draft',
                              'body': 'Legacy plain-text body', 'body_html': None})
                    candidate = probe(installed, 'candidate-data')
                    check(label + ' synthetic candidate-era SQL and private witness is created without transport',
                          candidate.get('created') is True and candidate_fixture_valid(candidate))
                    candidate_snapshot = probe(installed, 'snapshot')
                    check(label + ' candidate-era witness adds data without changing the upgraded baseline',
                          same_retained(after, candidate_snapshot))
                    candidate_replay = probe(installed, 'candidate-replay')
                    check(label + ' candidate-era replay is refused without changing retained data',
                          candidate_replay.get('duplicate_refused') is True
                          and candidate_replay.get('unchanged') is True
                          and candidate_fixture_valid(candidate_replay))
                    if nested:
                        response = submit(owner, updater, 'recover', {'backup_confirm': '1'})
                        recovered = probe(installed, 'snapshot')
                        check(label + ' owner-authenticated HTTP recovery restores baseline files',
                              '<h2>Rolled-back</h2>' in response.text and recovered['version'] == initial)
                        check(label + ' HTTP recovery preserves baseline and candidate-era SQL and private data',
                              same_retained(candidate_snapshot, recovered, allow_settings_additions=False))
                        extract_snapshot(update_bytes, installed)
                        updater = base + f'/cwp-update-{VERSION}/owner-update.php'
                        response = submit(owner, updater, 'inspect', {'backup_confirm': '1'})
                        check(label + ' recovered site reviews the exact successor update again',
                              assert_no_error(response) and f'Starting release: {initial}' in response.text
                              and not BeautifulSoup(response.text, 'html.parser').select('.error'))
                        response = submit(owner, response.url, 'apply', {'backup_confirm': '1'})
                        reapplied = probe(installed, 'snapshot')
                        check(label + ' recovered site reapplies exact 1.27.0 through owner HTTP',
                              assert_no_error(response) and '<h2>Complete</h2>' in response.text
                              and reapplied['version'] == VERSION)
                        check(label + ' reapplication preserves baseline and candidate-era records exactly',
                              same_retained(candidate_snapshot, reapplied, allow_settings_additions=False))
                        candidate_replay = probe(installed, 'candidate-replay')
                        check(label + ' candidate-era replay remains idempotent after recovery and reapply',
                              candidate_replay.get('duplicate_refused') is True
                              and candidate_replay.get('unchanged') is True
                              and candidate_fixture_valid(candidate_replay))
                        check(label + ' optional schemas remain absent after recovery and reapply',
                              probe(installed, 'release-state') == markers_before)
                        final = reapplied
                    else:
                        database_url = admin + '?view=studio-upgrade'
                        database_page = owner.get(database_url, timeout=120)
                        check(label + ' every 1.27.0 release migration is offered before review', assert_no_error(database_page)
                              and all(f'value="{marker}"' in database_page.text for marker in MIGRATIONS))
                        response = submit(owner, database_url, 'studio_database_plan', {'database_updates[]': MIGRATIONS})
                        check(label + ' owner receives one exact dependency-ordered migration review',
                              assert_no_error(response) and 'Review selected database updates' in response.text)
                        response = submit(owner, response.url, 'studio_database_apply', {'confirm': '1'})
                        check(label + ' actual reviewed release migrations succeed', assert_no_error(response) and 'Database updates completed:' in response.text)
                        release_state = probe(installed, 'release-state')
                        check(label + ' every release marker reaches its exact current version with no commercial or messaging rows',
                              release_state['markers'] == MIGRATIONS
                              and release_state['side_effect_rows'] == 0
                              and release_state['hosting_payments_enabled'] is False
                              and release_state['mail_drafts_body_html'] is True
                              and release_state['legacy_mail_draft'] == {
                                  'present': True, 'subject': 'Published 1.26.1 retained draft',
                                  'body': 'Legacy plain-text body', 'body_html': None})
                        migrated = probe(installed, 'snapshot')
                        check(label + ' additive schema preparation retains every original and candidate-era row and key',
                              same_retained(candidate_snapshot, migrated,
                                            allow_null_columns={'mail_drafts': {'body_html'}}))
                        representative = probe(installed, 'representative-data')
                        check(label + ' representative identity payment notification note and disabled-mail rows are created without transport',
                              representative.get('created') is True and representative_fixture_valid(representative))
                        representative_snapshot = probe(installed, 'snapshot')
                        check(label + ' representative 1.27.0 records preserve all earlier retained data',
                              same_retained(migrated, representative_snapshot, allow_table_additions={
                                  'studio_client_registrations', 'studio_client_tokens', 'studio_invoices',
                                  'payment_attempts', 'payment_attempt_events', 'invoice_payments',
                                  'gateway_receipts', 'notification_feed', 'notification_deliveries',
                                  'project_private_notes', 'mail_accounts', 'mail_account_revisions',
                                  'mail_message_reads', 'mail_message_results', 'mail_drafts',
                                  'mail_draft_revisions', 'mail_reply_drafts',
                              }))
                        representative_replay = probe(installed, 'representative-replay')
                        check(label + ' representative 1.27.0 replay is refused without mutation',
                              representative_replay.get('duplicate_refused') is True
                              and representative_replay.get('unchanged') is True
                              and representative_fixture_valid(representative_replay))
                        check(label + ' representative replay preserves the exact database and private snapshot',
                              representative_snapshot == probe(installed, 'snapshot'))
                        candidate_replay = probe(installed, 'candidate-replay')
                        check(label + ' candidate-era witness remains idempotent after every release migration',
                              candidate_replay.get('duplicate_refused') is True
                              and candidate_replay.get('unchanged') is True
                              and candidate_fixture_valid(candidate_replay))
                        final = representative_snapshot
                        for view in ['dashboard', 'catalog', 'provider-settings', 'studio-upgrade']:
                            response = owner.get(admin + '?view=' + view, timeout=120)
                            check(label + ' upgraded ' + view + ' loads without repeated password fields', assert_no_error(response) and 'view=login' not in response.url and not BeautifulSoup(response.text, 'html.parser').select('input[name="current_password"],input[name="owner_password"]'))
                        page = owner.get(database_url, timeout=120);csrf = BeautifulSoup(page.text, 'html.parser').select_one('input[name="csrf"]')['value']
                        response = owner.post(database_url, data={'csrf': csrf, 'action': 'studio_migrate', 'confirm': '1'}, timeout=120)
                        check(label + ' legacy database action is refused and preserves existing records',
                              assert_no_error(response) and 'Use Version &amp; database' in response.text
                              and final == probe(installed, 'snapshot'))
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
        report = {'passed': complete and len(scopes) == 2 and all(c['passed'] for c in checks),
                  'source_sha': os.environ.get('CWP_SOURCE_SHA'),
                  'source_tree': os.environ.get('CWP_SOURCE_TREE'), 'version': VERSION,
                  'candidate': candidate_identity, 'scopes': scopes, 'checks': checks,
                  'run_id': os.environ.get('CWP_RUN_ID'), 'run_attempt': os.environ.get('CWP_RUN_ATTEMPT'),
                  'baseline_source': BASELINE_SOURCE, 'baseline_sha256': BASELINE_SHA,
                  'scope': 'Actual published 1.26.1 installers, immutable in-process archive snapshots, populated SQL retention, interrupted owner HTTP updater, recovery/reapply, and every reviewed 1.27.0 additive migration; synthetic records and loopback only.',
                  'excluded': ['live provider', 'live gateway', 'customer messages', 'production deployment', 'release publication']}
        (OUT / 'results.json').write_text(json.dumps(report, indent=2) + '\n')
        print(json.dumps({'passed': report['passed'], 'scopes': len(scopes), 'checks': len(checks)}))


if __name__ == '__main__':
    main()
