"""Portable guards for the separately named published-v1.26.1 upgrade gate."""
from pathlib import Path
import argparse
import ast
from copy import deepcopy
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
MIGRATION_TARGETS = {
    'software_onboarding_version': '1', 'client_identity_schema_version': '1',
    'hosting_storefront_version': '1', 'gateway_receipts_schema_version': '1',
    'notification_feed_version': '1', 'hosting_payments_schema_version': '1',
    'project_private_notes_schema_version': '1', 'mail_copies_schema_version': '1',
    'mail_drafts_html_ready': '1', 'mail_files_schema_version': '1',
    'mail_flags_schema_version': '1', 'mail_inbox_schema_version': '2',
    'mail_messages_schema_version': '2', 'mail_replies_schema_version': '1',
}
MARKERS = set(MIGRATION_TARGETS)


def main(output):
    checks = []
    def check(name, value):
        checks.append({'name': name, 'passed': bool(value)})
        assert value, name
    acceptance = (ROOT / 'tests/release_127_upgrade_acceptance.py').read_text(encoding='utf-8')
    probe = (ROOT / 'tests/release_127_upgrade_probe.php').read_text(encoding='utf-8')
    fetch = (ROOT / 'tools/fetch_release_127_baseline.py').read_text(encoding='utf-8')
    workflow = (ROOT / '.github/workflows/software-release-1261-upgrade.yml').read_text(encoding='utf-8')
    service_plans = (ROOT / '.github/workflows/service-plan-records.yml').read_text(encoding='utf-8')
    apache = (ROOT / 'tests/release_127_apache_upgrade_acceptance.py').read_text(encoding='utf-8')
    structure = (ROOT / 'site/app/updates/structure.php').read_text(encoding='utf-8')
    loopback = (ROOT / 'tests/release_127_loopback_session.py').read_text(encoding='utf-8')
    history = json.loads((ROOT / 'tools/legacy-release-files.json').read_text(encoding='utf-8'))
    requirements = (ROOT / 'tools/requirements-release127.txt').read_text(encoding='utf-8')
    archive = (ROOT / 'tests/release_127_archive.py').read_text(encoding='utf-8')
    builder = (ROOT / 'tools/build.py').read_text(encoding='utf-8')
    defaults = (ROOT / 'site/config/defaults.php').read_text(encoding='utf-8')
    disabled = ('curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,'
                'stream_socket_server,stream_socket_sendto,socket_create,socket_create_pair,'
                'socket_import_stream,socket_connect,socket_send,socket_sendto,socket_sendmsg,'
                'socket_write,ftp_connect,ftp_ssl_connect,ftp_nb_connect,imap_open,ssh2_connect,'
                'ldap_connect,pg_connect,dns_get_record,gethostbyaddr,gethostbyname,gethostbynamel,'
                'checkdnsrr,snmpget,snmpgetnext,snmpwalk,mail,exec,shell_exec,system,passthru,'
                'popen,proc_open,pcntl_exec')
    compact = lambda source: ''.join(source.split()).replace("'", '').replace('"', '')
    acceptance_tree = ast.parse(acceptance)
    apache_tree = ast.parse(apache)
    archive_tree = ast.parse(archive)
    setting_change_allowlists = [
        ast.literal_eval(keyword.value)
        for node in ast.walk(acceptance_tree)
        if isinstance(node, ast.Call)
        and isinstance(node.func, ast.Name)
        and node.func.id == 'same_retained'
        for keyword in node.keywords
        if keyword.arg == 'allow_setting_changes'
    ]
    retention_functions = [
        node for node in acceptance_tree.body
        if isinstance(node, ast.FunctionDef)
        and node.name in {'snapshot_digest', 'same_retained'}
    ]
    retention_namespace = {'hashlib': hashlib, 'json': json, 're': re}
    exec(compile(ast.Module(body=retention_functions, type_ignores=[]),
                 '<release-retention-helpers>', 'exec'), retention_namespace)
    snapshot_digest = retention_namespace['snapshot_digest']
    same_retained = retention_namespace['same_retained']
    marker = 'mail_inbox_schema_version'
    marker_key = snapshot_digest({'name': marker})
    before_retention = {
        'configuration_sha256': 'configuration', 'lock_sha256': 'lock',
        'private_files': {'retained.bin': {'sha256': 'private', 'bytes': 7}},
        'tables': {'settings': {'count': 1, 'rows': {marker_key: {
            'name': snapshot_digest(marker), 'value': snapshot_digest('1'),
        }}}},
    }
    after_retention = deepcopy(before_retention)
    after_retention['tables']['settings']['rows'][marker_key]['value'] = snapshot_digest('2')
    retention_upload = workflow.partition(
        '      - name: Preserve hashed retention evidence and failures')[2].partition(
            '\n\n  apache-upgrade:')[0]
    populated_uploads = [
        line.strip() for line in retention_upload.splitlines()
        if line.strip().startswith('qa/release127-populated/')
    ]
    expected_populated_uploads = {
        'qa/release127-populated/results.json',
        'qa/release127-populated/progress.json',
        'qa/release127-populated/failure.txt',
        'qa/release127-populated/1261-root-before.json',
        'qa/release127-populated/1261-root-database-apply.json',
        'qa/release127-populated/1261-root-after.json',
        'qa/release127-populated/1261-root-server.log',
        'qa/release127-populated/1261-subfolder-before.json',
        'qa/release127-populated/1261-subfolder-database-apply.json',
        'qa/release127-populated/1261-subfolder-after.json',
        'qa/release127-populated/1261-subfolder-server.log',
    }
    assigned_names = {
        node.id for node in ast.walk(acceptance_tree)
        if isinstance(node, ast.Name) and isinstance(node.ctx, ast.Store)
    }
    def assigned_literal(tree, name):
        for node in ast.walk(tree):
            if (isinstance(node, ast.Assign)
                    and any(isinstance(target, ast.Name) and target.id == name for target in node.targets)):
                return ast.literal_eval(node.value)
        raise AssertionError('Missing literal assignment: ' + name)
    acceptance_maintenance = assigned_literal(acceptance_tree, 'MAINTENANCE')
    apache_maintenance = assigned_literal(apache_tree, 'MAINTENANCE')
    staged_policy = assigned_literal(apache_tree, 'STAGED_POLICY')
    private_guard = assigned_literal(archive_tree, 'PRIVATE_GUARD')
    marker_nodes = [
        node for node in acceptance_tree.body
        if (isinstance(node, ast.Assign)
            and any(isinstance(target, ast.Name)
                    and target.id in {'MIGRATIONS', 'BASELINE_MARKERS'}
                    for target in node.targets))
    ]
    marker_namespace = {}
    exec(compile(ast.Module(body=marker_nodes, type_ignores=[]),
                 '<release-marker-contract>', 'exec'), {'__builtins__': {}}, marker_namespace)
    release_migrations = marker_namespace.get('MIGRATIONS')
    baseline_markers = marker_namespace.get('BASELINE_MARKERS')
    release_state_section = probe.partition("if($mode==='release-state'){")[2].partition(
        'echo json_encode($state,JSON_THROW_ON_ERROR);exit;')[0]
    release_state_match = re.search(r'\$markers=\[([^\]]+)\];', release_state_section)
    release_state_markers = ([] if release_state_match is None else
                             re.findall(r"'([a-z][a-z0-9_]*)'", release_state_match.group(1)))
    check('Gate binds exact published source hash and 1.27.0 candidate',
          "BASELINE_SOURCE = 'f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391'" in acceptance
          and "VERSION != '1.27.0'" in acceptance)
    check('Fetcher pins archive bytes hash source and both asset identities',
          all(value in fetch for value in ['17_429_306', '7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3',
                                            '711656635', '711656643', '3072']))
    check('Fetcher uses exact public release downloads without REST quota dependency',
          "RELEASE_BASE_URL = 'https://github.com/afterburn25/Cajun_Web/releases/download/v1.26.1/'" in fetch
          and "'api.github.com'" not in fetch)
    check('Updater history recognizes only the exact published 1.26.1 inventory',
          list(history)[-2:] == ['1.26.0', '1.26.1']
          and history['1.26.1']['source_commit'] == 'f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391'
          and history['1.26.1']['archive_sha256'] == '7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3'
          and history['1.26.1']['manifest_sha256'] == 'a903b2850ab4ff25ed94ceddd1794370341865183ee515a72bd816cd5324d0ee'
          and len(history['1.26.1']['files']) == 3072
          and history['1.26.1']['files'].get('config/defaults.php') is not None)
    check('Every release migration marker and target is reviewed and probed in exact order',
          list(release_migrations.items()) == list(MIGRATION_TARGETS.items())
          and release_state_markers == list(release_migrations)
          and len(release_state_markers) == len(set(release_state_markers)))
    check('Published notification baseline is absent and its reviewed target is version 1',
          isinstance(baseline_markers, dict)
          and set(baseline_markers) == MARKERS
          and baseline_markers['notification_feed_version'] == ''
          and release_migrations['notification_feed_version'] == '1')
    check('Formatted-draft migration proves the real legacy column addition and retained row',
          all(term in probe for term in ['Published 1.26.1 retained draft',
                                         "column_name=?',[$c['db']['prefix'].'mail_drafts','body_html']",
                                         "'mail_drafts_body_html'=>$htmlColumn!==null"])
          and "allow_null_columns={'mail_drafts': {'body_html'}}" in acceptance
          and "markers_before['mail_drafts_body_html'] is False" in acceptance
          and "release_state['mail_drafts_body_html'] is True" in acceptance)
    check('Retention comparison accepts only the exact reviewed existing setting transition',
          same_retained(before_retention, after_retention,
                        allow_setting_changes={marker: ('1', '2')})
          and not same_retained(before_retention, after_retention))
    check('Release retention call allows only the three published marker upgrades',
          setting_change_allowlists == [{
              'mail_inbox_schema_version': ('1', '2'),
              'mail_messages_schema_version': ('1', '2'),
              'payments_schema_version': ('1', '2'),
          }])
    wrong_source = deepcopy(before_retention)
    wrong_source['tables']['settings']['rows'][marker_key]['value'] = snapshot_digest('0')
    check('Retention comparison refuses a wrong source value',
          not same_retained(wrong_source, after_retention,
                            allow_setting_changes={marker: ('1', '2')}))
    missing_source = deepcopy(before_retention)
    missing_source['tables']['settings']['rows'].pop(marker_key)
    missing_source['tables']['settings']['count'] = 0
    missing_both = deepcopy(missing_source)
    check('Retention comparison requires every declared transition row to exist',
          not same_retained(missing_source, after_retention,
                            allow_setting_changes={marker: ('1', '2')})
          and not same_retained(missing_source, missing_both,
                                allow_setting_changes={marker: ('1', '2')}))
    check('Retention comparison refuses a partially observed transition allowlist',
          not same_retained(before_retention, after_retention,
                            allow_setting_changes={
                                marker: ('1', '2'),
                                'mail_messages_schema_version': ('1', '2'),
                            }))
    wrong_value = deepcopy(after_retention)
    wrong_value['tables']['settings']['rows'][marker_key]['value'] = snapshot_digest('3')
    check('Retention comparison refuses a wrong target value or marker allowance',
          not same_retained(before_retention, wrong_value,
                            allow_setting_changes={marker: ('1', '2')})
          and not same_retained(before_retention, after_retention,
                                allow_setting_changes={'mail_messages_schema_version': ('1', '2')}))
    extra_column = deepcopy(after_retention)
    extra_column['tables']['settings']['rows'][marker_key]['unexpected'] = snapshot_digest(None)
    check('Retention comparison refuses extra columns during an allowed marker transition',
          not same_retained(before_retention, extra_column,
                            allow_setting_changes={marker: ('1', '2')}))
    check('Verified archive bytes are snapshotted once and the same bytes are extracted',
          all(term in archive for term in ['def published_snapshot(', 'def candidate_snapshot(',
                                           'def extract_snapshot(', 'io.BytesIO(data)', "target.open('xb')"])
          and 'extract_snapshot(published_bytes, installed)' in acceptance
          and 'extract_snapshot(update_bytes, installed)' in acceptance
          and 'unpack(' not in acceptance and 'extractall(' not in archive
    )
    check('Candidate archive and manifest evidence bind expected source and version once',
          'candidate_snapshot(path, expected_source, expected_version)' in archive
          and 'manifest_data = manifest_path.read_bytes()' in archive
          and "'manifest_sha256': hashlib.sha256(manifest_data).hexdigest()" in archive
          and "manifest['source_commit'] != expected_source" in archive
          and "manifest['version'] != expected_version" in archive)
    check('Extraction refuses unsafe existing parents before creating descendants',
          'if destination.is_symlink() or not destination.is_dir()' in archive
          and 'Archive extraction parent is unsafe' in archive
          and 'target.parent.mkdir(parents=True' not in archive)
    check('Interrupted update recovery reapply and retained data are required',
          all(term in acceptance for term in ['structure_worker.php', 'Synthetic interruption after durable journal',
                                               "submit(owner, updater, 'recover'", 'reapplied',
                                               'recovery and reapply', 'same_retained']))
    check('Native interruption binds durable evidence and exact policy without claiming Apache enforcement',
          all(term in acceptance for term in [
              'journal_state = interrupted_journal(',
              "ordinary_file_bytes(installed / '.htaccess', installed) == MAINTENANCE",
              'completed update restores exact candidate server policy',
          ])
          and 'owner.get(admin, timeout=120).status_code == 403' not in acceptance)
    check('Both interruption gates bind the complete production maintenance policy bytes',
          acceptance_maintenance == apache_maintenance
          and hashlib.sha256(acceptance_maintenance).hexdigest()
          == '853270df2d0ba7bd8cf198eecbabe32aa4f7445fb8b2e75e3d476ed2317a6a73'
          and 'return "# Temporary owner-authenticated structure update. Retry or recover using the staged owner page.\\nOptions -Indexes\\nRequire all denied\\n<Files \\"owner-update.php\\">\\nRequire all granted\\n</Files>\\n";' in structure)
    check('Private journal helper binds the production guard canonical path modes and exact applying state',
          hashlib.sha256(private_guard).hexdigest()
          == '22ea5d668d4d1acbf63c6649bb5f9352cc735a73300e10b9c4379a97a4b408a5'
          and 'const CWP_GUARD = "<?php http_response_code(404); exit; ?>\\n";' in defaults
          and all(term in archive for term in [
              "any(part in ('', '.', '..') for part in raw.split('/')[1:])",
              'candidate.resolve(strict=True) != candidate',
              'stat.S_ISLNK(info.st_mode)',
              'stat.S_IMODE(structure_info.st_mode) != 0o700',
              'stat.S_IMODE(journal_info.st_mode) != 0o600',
              "data.get('root') != expected_root",
              "data.get('version') != starting_version",
              'Expected journal root does not map to the exact host installation',
              "data.get('target') != version", "data.get('commit') != source_commit",
              "data.get('state') != 'applying'", "policy.get('source') != '.htaccess'",
              'policy.get(\'new\') != hashlib.sha256(normal_policy).hexdigest()',
          ]))
    check('Docker journal root maps one exact virtual source mount to the canonical host install',
          all(term in acceptance for term in [
              'starting_version=initial, expected_root=path',
              "virtual_root='/workspace', host_root=ROOT",
          ])
          and all(term in archive for term in [
              'recorded.is_relative_to(virtual)',
              '(host / recorded.relative_to(virtual)).resolve(strict=True)',
              '!= installed.resolve(strict=True)',
              'Expected journal root differs from the canonical host installation',
          ]))
    check('Candidate-era records are retained and replay-safe across recovery and reapply',
          all(term in acceptance for term in ['candidate-data', 'candidate-replay',
                                               'candidate_data_snapshot', 'recovered', 'reapplied'])
          and all(term in probe for term in ['release127_candidate_create',
                                              "if($mode==='candidate-data')",
                                              "if($mode==='candidate-replay')"]))
    check('Candidate archive verifier is never shadowed by a local snapshot variable',
          'candidate_snapshot' not in assigned_names)
    check('Representative 1.27 records cover identity payment notification notes and disabled mail',
          all(term in acceptance for term in ['representative-data', 'representative-replay',
                                               'representative_fixture_valid'])
          and all(term in probe for term in ['studio_client_registrations', 'payment_attempts',
                                              'gateway_receipts', 'notification_feed',
                                              'project_private_notes', 'mail_accounts',
                                              'mail_reply_drafts']))
    check('Representative pending identity distinguishes a present SQL NULL from a missing column',
          "array_key_exists('verified_at',$rows['studio_client_registrations'])" in probe
          and "$rows['studio_client_registrations']['verified_at']===null" in probe
          and "['verified_at']??false" not in probe)
    check('Representative notification storage is explicitly reviewed before fixture creation',
          "'notification_feed_version': '1'" in acceptance
          and "'notification_feed_version'=>'1'" in probe
          and all("'" + table + "'" in release_state_section for table in [
              'notification_feed', 'notification_prefs', 'notification_deliveries']))
    check('Representative fixture requires each exact current release marker version',
          "'mail_messages_schema_version'=>'2'" in probe
          and 'foreach($requiredMarkers as $marker=>$version)' in probe
          and 'setting($marker)!==$version' in probe
          and "setting($marker)!=='1'" not in probe)
    check('Gate records no-live-operation boundaries',
          all(term in acceptance for term in ['live provider', 'live gateway', 'customer messages', 'production deployment', 'release publication']))
    check('Hosted matrix covers both PHP and database families',
          'php: [\'8.2\', \'8.4\']' in workflow and 'database: [mariadb, mysql]' in workflow)
    check('Development queue groups by PR or ref and preserves main runs',
          'group: release-1261-upgrade-${{ github.event.pull_request.number || github.ref }}' in workflow
          and "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}" in workflow)
    check('Every hosted PHP and database container is content-digest pinned',
          all(digest in workflow for digest in [
              'sha256:842748bc4526d4c3c56501fd314e2d75d1005e8a45b7a40f9768ee820df0a6d6',
              'sha256:6b13a8678b38ca38e4e3950be57b664ed8c8706131a0586111f68792aede4c58',
              'sha256:e5fe75b2b9de0eb9f6f9a4d9c97272fb3a7480e389ccd487a786bab2a12cc0d7',
              'sha256:cd05748e60471137face0ba0a256c9c48669198aee1f12132f413fc91fd8948f',
          ]))
    check('Workflow uses only exact checkout and artifact action identities',
          'actions/checkout@11d5960a326750d5838078e36cf38b85af677262' in workflow
          and 'actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02' in workflow)
    check('Hosted gate uses exact disabled transports in workflow Python and PHP',
          all(disabled in compact(source) for source in [workflow, acceptance, probe])
          and 'allow_url_fopen=0' in workflow and 'allow_url_fopen=0' in acceptance
          and 'filter_var(ini_get(\'allow_url_fopen\')' in probe)
    check('Hosted dependencies are immutable binary distributions with hashes',
          'pip" install --disable-pip-version-check --only-binary=:all: --require-hashes' in workflow
          and requirements.count('--hash=sha256:') == 8
          and all(name in requirements for name in ['beautifulsoup4==', 'requests==', 'urllib3==']))
    check('Every new package source path preserves signed-source receipts',
          'Acquire::AllowInsecureRepositories=false' in workflow
          and 'APT::Get::AllowUnauthenticated=false' in workflow
          and 'cwp-package-source-receipt.txt' in workflow
          and 'prepare_ubuntu_apt.py verify-releases' in workflow
          and all(name in workflow for name in ['qa/ubuntu-apt-source-preflight.json',
                                                 'qa/ubuntu-apt-source.json',
                                                 'qa/ubuntu-apt-release-metadata.json']))
    check('Hosted first-attempt receipt and artifact bind exact source and run identity',
          all(term in workflow for term in ['CWP_RUN_ID: ${{ github.run_id }}',
                                             'CWP_RUN_ATTEMPT: ${{ github.run_attempt }}',
                                             "os.environ['CWP_RUN_ATTEMPT']=='1'",
                                             '${{ env.CWP_SOURCE_SHA }}-${{ github.run_id }}-attempt-${{ github.run_attempt }}']))
    check('Hosted gate fails if acceptance mutates tracked source',
          'Verify acceptance did not mutate tracked source' in workflow
          and 'git diff --exit-code' in workflow
          and 'git status --porcelain --untracked-files=no' in workflow)
    check('Standalone release workflow runs actual Apache root subfolder rewrite and nonrewrite gate',
          all(term in apache for term in ['root-rewrite', 'subfolder-rewrite',
                                          'root-no-rewrite', 'subfolder-no-rewrite',
                                          'apache2handler', 'Starting release: 1.26.1',
                                          'from release_127_loopback_session import LocalSession',
                                          'LocalSession.from_origin(origin)'])
          and 'apache-upgrade:' in workflow
          and 'tests/release_127_apache_upgrade_acceptance.py' in workflow
          and disabled in compact(workflow)
          and 'prepare_ubuntu_apt.py prepare-ubuntu' in workflow
          and 'prepare_ubuntu_apt.py verify-releases' in workflow
          and 'qa/release127-apache/results.json' in workflow)
    check('Service-plan storage gate uses only its runner-assigned database port',
          service_plans.count("ports: ['3306/tcp']") == 1
          and '33306' not in service_plans
          and service_plans.count("CWP_TEST_PORT: ${{ job.services.database.ports['3306'] }}") == 1
          and '-e CWP_TEST_DATABASE -e CWP_TEST_HOST -e CWP_TEST_PORT' in service_plans)
    check('Actual Apache gate interrupts and enforces exact maintenance policy in every topology',
          all(term in apache for term in [
              "'tests/structure_worker.php'", "'apply', '2'",
              'Synthetic interruption after durable journal',
              'journal_state = interrupted_journal(',
              "ordinary_file_bytes(installed / '.htaccess', installed) == MAINTENANCE",
              'admin_blocked.status_code == 403', 'portal_blocked.status_code == 403',
              'staged_blocked.status_code == 403', 'staged_owner.status_code == 200',
              "ordinary_file_bytes(installed / '.htaccess', installed) == normal_policy",
          ])
          and "admin_blocked = session.get(base + '/admin/index.php?view=dashboard'" in apache
          and "staged_owner = session.get(updater" in apache)
    check('Apache gate independently binds and enforces the staged policy before and after root maintenance',
          hashlib.sha256(staged_policy).hexdigest()
          == '46da8c49ab996bc1b6c0bb307b274dbc9e866726036d2e74f70e2b72e2d8322e'
          and "update[stage+'.htaccess']=b'Options -Indexes\\nRequire all denied\\n<Files \"owner-update.php\">\\nRequire all granted\\n</Files>\\n'" in builder
          and all(term in apache for term in [
              "ordinary_file_bytes(staged_policy, stage) == STAGED_POLICY",
              'staged nonowner file is denied while root policy is normal',
              'completed update retains the exact staged deny policy',
              'staged nonowner file remains denied after root policy restoration',
          ]))
    check('Both HTTP gates refuse ambient explicit and cross-port proxy transport',
          all(term in loopback for term in [
              'class LocalSession(requests.Session)',
              'self.trust_env = False',
              "'HTTP_PROXY': 'http://proxy.invalid:8080'",
              "'HTTPS_PROXY': 'http://proxy.invalid:8080'",
              "'ALL_PROXY': 'http://proxy.invalid:8080'",
              "'NO_PROXY': ''",
              'self.merge_environment_settings(',
              "settings.get('proxies')",
              "kwargs.get('proxies')",
              'Non-loopback or cross-port request refused',
              'Explicit proxy request refused',
          ])
          and 'from release_127_loopback_session import LocalSession' in acceptance
          and all(term in acceptance for term in [
              'LocalSession(8093)',
              'anon = LocalSession(8093)',
              'owner = LocalSession(8093)',
              'restricted = LocalSession(8093)',
          ])
          and 'from release_127_loopback_session import LocalSession' in apache
          and 'LocalSession.from_origin(origin)' in apache)
    check('Failure-always upload retains only the finite release evidence and bounded diagnostics',
          len(populated_uploads) == len(expected_populated_uploads)
          and set(populated_uploads) == expected_populated_uploads
          and 'if: always()' in retention_upload
          and 'actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02' in retention_upload
          and 'if-no-files-found: warn' in retention_upload
          and 'retention-days: 14' in retention_upload)
    check('Apache artifact upload names only finite public receipts logs results and candidate proof',
          all(term in workflow for term in [
              'qa/release127-apache/results.json', 'qa/release127-apache/progress.json',
              'qa/release127-apache/failure.txt', 'qa/release127-apache/root-rewrite.json',
              'qa/release127-apache/subfolder-no-rewrite.json',
              'qa/release127-apache/*-apache.log', 'qa/release127-apache/*-launcher.log',
              'dist/CajunWebPro-1.27.0-UPDATE.manifest.json', 'dist/SHA256SUMS.txt'])
          and '\n            qa/release127-apache/\n' not in workflow)
    check('Loopback probe accepts only the isolated release127 prefix', "preg_match('/^ug127[rs]_$/D'" in probe)
    Path(output).write_text(json.dumps({'passed': True, 'scope': 'Static release-127 gate contract; no DB, HTTP, provider, payment or deployment', 'checks': checks}, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'release-127 upgrade guards')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args(); main(args.report)
