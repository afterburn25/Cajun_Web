"""Portable guards for the separately named published-v1.26.1 upgrade gate."""
from pathlib import Path
import argparse
import json

ROOT = Path(__file__).resolve().parents[1]
MARKERS = {
    'software_onboarding_version', 'client_identity_schema_version', 'hosting_storefront_version',
    'gateway_receipts_schema_version', 'hosting_payments_schema_version',
    'project_private_notes_schema_version', 'mail_copies_schema_version',
    'mail_drafts_html_ready', 'mail_files_schema_version', 'mail_flags_schema_version',
    'mail_inbox_schema_version', 'mail_messages_schema_version', 'mail_replies_schema_version',
}


def main(output):
    checks = []
    def check(name, value):
        checks.append({'name': name, 'passed': bool(value)})
        assert value, name
    acceptance = (ROOT / 'tests/release_127_upgrade_acceptance.py').read_text(encoding='utf-8')
    probe = (ROOT / 'tests/release_127_upgrade_probe.php').read_text(encoding='utf-8')
    fetch = (ROOT / 'tools/fetch_release_127_baseline.py').read_text(encoding='utf-8')
    workflow = (ROOT / '.github/workflows/software-release-1261-upgrade.yml').read_text(encoding='utf-8')
    application = (ROOT / '.github/workflows/application-tests.yml').read_text(encoding='utf-8')
    apache = (ROOT / 'tests/release_127_apache_upgrade_acceptance.py').read_text(encoding='utf-8')
    loopback = (ROOT / 'tests/release_127_loopback_session.py').read_text(encoding='utf-8')
    artifacts = (ROOT / 'tools/acceptance_artifacts.py').read_text(encoding='utf-8')
    reports = json.loads((ROOT / 'tools/acceptance-reports.json').read_text(encoding='utf-8'))
    contract = json.loads((ROOT / 'tools/acceptance-contract.json').read_text(encoding='utf-8'))
    requirements = (ROOT / 'tools/requirements-release127.txt').read_text(encoding='utf-8')
    archive = (ROOT / 'tests/release_127_archive.py').read_text(encoding='utf-8')
    disabled = ('curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,'
                'stream_socket_server,stream_socket_sendto,socket_create,socket_create_pair,'
                'socket_import_stream,socket_connect,socket_send,socket_sendto,socket_sendmsg,'
                'socket_write,ftp_connect,ftp_ssl_connect,ftp_nb_connect,imap_open,ssh2_connect,'
                'ldap_connect,pg_connect,dns_get_record,gethostbyaddr,gethostbyname,gethostbynamel,'
                'checkdnsrr,snmpget,snmpgetnext,snmpwalk,mail,exec,shell_exec,system,passthru,'
                'popen,proc_open,pcntl_exec')
    compact = lambda source: ''.join(source.split()).replace("'", '').replace('"', '')
    check('Gate binds exact published source hash and 1.27.0 candidate',
          "BASELINE_SOURCE = 'f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391'" in acceptance
          and "VERSION != '1.27.0'" in acceptance)
    check('Fetcher pins archive bytes hash source and both asset identities',
          all(value in fetch for value in ['17_429_306', '7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3',
                                            '711656635', '711656643', '3072']))
    check('Every release migration marker is reviewed and probed', all(marker in acceptance and marker in probe for marker in MARKERS))
    check('Verified archive bytes are snapshotted once and the same bytes are extracted',
          all(term in archive for term in ['def published_snapshot(', 'def candidate_snapshot(',
                                           'def extract_snapshot(', 'io.BytesIO(data)', "target.open('xb')"])
          and 'extract_snapshot(published_bytes, installed)' in acceptance
          and 'extract_snapshot(update_bytes, installed)' in acceptance
          and 'unpack(' not in acceptance and 'extractall(' not in archive
    )
    check('Interrupted update recovery reapply and retained data are required',
          all(term in acceptance for term in ['structure_worker.php', 'Synthetic interruption after durable journal',
                                               "submit(owner, updater, 'recover'", 'reapplied',
                                               'recovery and reapply', 'same_retained']))
    check('Candidate-era records are retained and replay-safe across recovery and reapply',
          all(term in acceptance for term in ['candidate-data', 'candidate-replay',
                                               'candidate_snapshot', 'recovered', 'reapplied'])
          and all(term in probe for term in ['release127_candidate_create',
                                              "if($mode==='candidate-data')",
                                              "if($mode==='candidate-replay')"]))
    check('Representative 1.27 records cover identity payment notification notes and disabled mail',
          all(term in acceptance for term in ['representative-data', 'representative-replay',
                                               'representative_fixture_valid'])
          and all(term in probe for term in ['studio_client_registrations', 'payment_attempts',
                                              'gateway_receipts', 'notification_feed',
                                              'project_private_notes', 'mail_accounts',
                                              'mail_reply_drafts']))
    check('Gate records no-live-operation boundaries',
          all(term in acceptance for term in ['live provider', 'live gateway', 'customer messages', 'production deployment', 'release publication']))
    check('Hosted matrix covers both PHP and database families',
          'php: [\'8.2\', \'8.4\']' in workflow and 'database: [mariadb, mysql]' in workflow)
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
    check('Hosted first-attempt receipt and artifact bind exact source and run identity',
          all(term in workflow for term in ['CWP_RUN_ID: ${{ github.run_id }}',
                                             'CWP_RUN_ATTEMPT: ${{ github.run_attempt }}',
                                             "os.environ['CWP_RUN_ATTEMPT']=='1'",
                                             '${{ env.CWP_SOURCE_SHA }}-${{ github.run_id }}-attempt-${{ github.run_attempt }}']))
    check('Hosted gate fails if acceptance mutates tracked source',
          'Verify acceptance did not mutate tracked source' in workflow
          and 'git diff --exit-code' in workflow
          and 'git status --porcelain --untracked-files=no' in workflow)
    check('Application runs actual Apache root subfolder rewrite and nonrewrite upgrade gate',
          all(term in apache for term in ['root-rewrite', 'subfolder-rewrite',
                                          'root-no-rewrite', 'subfolder-no-rewrite',
                                          'apache2handler', 'Starting release: 1.26.1',
                                          'from release_127_loopback_session import LocalSession',
                                          'LocalSession.from_origin(origin)'])
          and 'tests/release_127_apache_upgrade_acceptance.py' in application
          and disabled in compact(application)
          and 'apache-release127-upgrade-results.json' in reports['final']
          and contract['release_127_upgrade_extension']['reports'] == [
              'apache-release127-input-guards-results.json',
              'apache-release127-upgrade-results.json',
          ])
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
    check('Apache progress logs scope receipts results and failures cross only the finite seal namespace',
          all(term in artifacts for term in ['RELEASE127_APACHE_QA',
                                              "lane == 'apache' and name in RELEASE127_APACHE_QA"]))
    check('Loopback probe accepts only the isolated release127 prefix', "preg_match('/^ug127[rs]_$/D'" in probe)
    Path(output).write_text(json.dumps({'passed': True, 'scope': 'Static release-127 gate contract; no DB, HTTP, provider, payment or deployment', 'checks': checks}, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'release-127 upgrade guards')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args(); main(args.report)
