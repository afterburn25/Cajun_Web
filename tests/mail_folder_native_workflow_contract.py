"""Guard the held mailbox-folder exact-source supported SQL workflow."""
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = ROOT / '.github/workflows/mail-folder-native.yml'


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def validate(text):
    checks = []

    def check(name, condition):
        require(condition, name)
        checks.append({'name': name, 'passed': True})

    check('Workflow is one ordinary YAML document', '\t' not in text and '\n---\n' not in text)
    check('Repository access remains read-only', text.count('\npermissions:\n  contents: read\n') == 1)
    check('Development cancellation is scoped while main evidence is retained',
          'group: mail-folder-native-${{ github.event.pull_request.number || github.ref }}' in text
          and "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}" in text)
    check('Push and pull-request admission bind workflow contract and application seams',
          text.count("'tests/mail_folder_native_workflow_contract.py'") == 2
          and text.count("'.github/workflows/mail-folder-native.yml'") == 2
          and text.count("'site/app/mail/**'") == 2
          and text.count("'site/app/staff/**'") == 2
          and text.count("'site/includes/**'") == 2
          and text.count("'site/database/**'") == 2
          and text.count("'site/app/settings/database_schemas.php'") == 2
          and text.count("'site/install/inventory.generated.php'") == 2
          and text.count("'tests/current_owner_authority.php'") == 2
          and text.count("'tests/database_schema_registry_policy.php'") == 2
          and text.count("'tests/communication_session_installed.py'") == 2
          and text.count("'foundation/mail-compose/**'") == 2
          and text.count("'tests/mail-compose/**'") == 2
          and text.count("'tests/mail_copied_draft_*'") == 2
          and text.count("'tests/mail-receiving/**'") == 2
          and text.count("'.github/scripts/prepare_ubuntu_apt.py'") == 2
          and text.count('branches: [main]') == 2)
    check('Manual redispatch cannot replace a natural first attempt', 'workflow_dispatch:' not in text)
    immutable = '${{ github.event.pull_request.head.sha || github.sha }}'
    check('Source checkout is bound to the immutable event commit',
          text.count('ref: ' + immutable) == 1 and text.count('CWP_SOURCE_SHA: ' + immutable) == 1)
    check('Matrix checkout and jobs are bound to admitted source',
          text.count('ref: ${{ needs.source.outputs.sha }}') == 1
          and text.count('CWP_SOURCE_SHA: ${{ needs.source.outputs.sha }}') == 2)
    check('Every checkout is pinned and discards credentials',
          text.count('actions/checkout@11d5960a326750d5838078e36cf38b85af677262') == 2
          and text.count('persist-credentials: false') == 2)
    check('Only attempt one is admitted and run provenance is retained',
          text.count('CWP_RUN_ID: ${{ github.run_id }}') == 2
          and text.count('CWP_RUN_ATTEMPT: ${{ github.run_attempt }}') == 2
          and text.count('test "$CWP_RUN_ATTEMPT" = "1"') == 2
          and text.count('${{ github.run_id }}-attempt-${{ github.run_attempt }}') == 2)
    check('Source admission covers workflow self-test syntax and exact mirrors',
          'python3 tests/mail_folder_native_workflow_contract.py' in text
          and 'php -l "$file"' in text
          and 'python3 -m py_compile tests/mail_inbox_cases.py tests/mail_inbox_http_server.py tests/mail_message_cases.py tests/communication_session_installed.py' in text
          and 'php tests/database_schema_registry_policy.php' in text
          and 'for file in HeaderSession.php InboxReader.php MessageReader.php ReadSession.php ReceivingFailure.php' in text
          and 'cmp -s "foundation/mail-receiving/$file" "site/includes/foundation/mail-receiving/$file"' in text)
    check('Every hosted lane uses reviewed signed-source image admission and retains failure evidence before networking',
          text.count('sudo python3 -I .github/scripts/prepare_ubuntu_apt.py prepare-ubuntu') == 2
          and text.count('prepare-ubuntu >') == 2
          and text.count('|| status=$?') >= 2
          and text.count('sudo test -f qa/ubuntu-apt-source-preflight.json') == 2
          and text.count('sudo mv qa/ubuntu-apt-source-preflight.json') == 2
          and text.count('if [ -f qa/ubuntu-apt-source.json ]; then sudo mv qa/ubuntu-apt-source.json') == 2
          and text.count('runner-image.log') == 2
          and text.index('prepare_ubuntu_apt.py prepare-ubuntu') < text.index('docker build --build-arg PHP_VERSION='))
    check('Acceptance retains PHP and database support matrix',
          "php: ['8.2', '8.4']" in text and 'database: [mariadb, mysql]' in text
          and "image: ${{ matrix.database == 'mariadb' && 'mariadb:10.11' || 'mysql:8.4' }}" in text)
    check('Disposable PHP retains PDO MySQL mbstring and DOM',
          'apt-get install -y --no-install-recommends libonig-dev libxml2-dev' in text
          and 'docker-php-ext-install pdo_mysql mbstring dom' in text
          and 'php -n -r' in text
          and 'function_exists("mb_convert_encoding")' in text
          and 'function_exists("ctype_digit")' in text
          and 'class_exists("DOMDocument")' in text
          and 'noini-capabilities.stdout.log' in text
          and 'noini-capabilities.stderr.log' in text)
    check('Disposable MySQL permits the native audit-trigger fixtures',
          "if [ '${{ matrix.database }}' = 'mysql' ]; then" in text
          and '${{ job.services.database.id }}' in text
          and "SET GLOBAL log_bin_trust_function_creators=1" in text
          and 'mysql-global.stdout.log' in text and 'mysql-global.stderr.log' in text)
    check('Assigned synthetic user has bounded retained readiness proof',
          'for($i=0;$i<60;$i++)' in text and 'usleep(500000)' in text
          and 'readiness.stdout.log' in text and 'readiness.stderr.log' in text)
    check('Only synthetic local receiving copy and native suites run',
          'php_flags="-n"' in text
          and text.count('php $php_flags tests/mail-receiving/session_test.php') == 1
          and text.count('php $php_flags tests/mail-receiving/message_session_test.php') == 1
          and text.count('php $php_flags tests/mail-receiving/attachment_test.php') == 1
          and text.count('php $php_flags tests/mail-compose/reply_all.php') == 1
          and text.count('php $php_flags tests/mail_copied_draft_create_adapter.php') == 1
          and text.count('inbox=0; php tests/mail_inbox_native.php') == 1
          and text.count('message=0; php tests/mail_message_native.php') == 1
          and text.count('notices=0; php tests/mail_notices_native.php') == 1
          and text.count('copied=0; php tests/mail_copied_draft_create_native.php') == 1
          and 'example.invalid' not in text
          and 'example.net' not in text)
    check('Synthetic private storage retains the production permission boundary',
          'mkdir -p "$cell" test-runtime/mail-folder-private qa' in text
          and 'chmod 700 test-runtime/mail-folder-private' in text
          and 'realpath("test-runtime/mail-folder-private")' in text)
    check('Every native outcome and failure channel is retained before enforcement',
          'inbox.stdout.log' in text and 'inbox.stderr.log' in text
          and 'message.stdout.log' in text and 'message.stderr.log' in text
          and 'notices.stdout.log' in text and 'notices.stderr.log' in text
          and 'copied.stdout.log' in text and 'copied.stderr.log' in text
          and 'status.txt' in text
          and text.index('status.txt') < text.index('test "$inbox" -eq 0'))
    check('Reports require complete passing named folder and migration assertions',
          "assert report['passed'] is True" in text
          and "assert all(check['passed'] is True for check in report['checks'])" in text
          and 'Legacy inbox migration preserves request and result with INBOX backfill' in text
          and 'Declared-current refusal does not heal the table' in text
          and 'Folder-scoped request records the exact allowed folder' in text
          and 'Legacy message migration preserves request and result while widening folder identity' in text
          and 'Declared-current refusal does not widen the table' in text
          and 'Folder-scoped message read binds and records the exact folder' in text
          and 'Folder-scoped attachment binds bytes and history to the exact folder' in text
          and 'Revoked read grant records a bounded failure, not bytes' in text
          and 'Non-inbox copied-draft source refuses before DNS or transport' in text
          and 'Refused non-inbox copied draft leaves no request or network trace' in text
          and 'Non-inbox folder success leaves the inbox notice window and latest outcome unchanged' in text
          and 'Non-inbox message open records its folder without creating notice state' in text
          and 'One exact read creates one protected derived draft' in text)
    check('Portable normal and no-INI reports are exact and complete',
          "for mode in ('normal', 'noini')" in text
          and "'receiving': 17, 'header-session': 56, 'message-session': 46" in text
          and "'body': 34, 'attachment': 14, 'attachment-security': 27" in text
          and "'origin': 20, 'reply': 60, 'reply-all': 37, 'thread': 45" in text
          and "'copied-source': 19, 'storage': 21, 'admin-policy': 23" in text
          and "'adapter': 19, 'schema-registry': 401" in text
          and "assert len(report['checks']) == count" in text)
    check('Native report counts retain every predecessor and composed assertion',
          "'inbox': (cell / 'inbox-results.json', 72)" in text
          and "'message': (cell / 'message-results.json', 94)" in text
          and "'notices': (cell / 'notices-results.json', 81)" in text
          and "'copied': (cell / 'copied-results.json', 21)" in text
          and "assert len(report['checks']) >=" not in text)
    check('Passing acceptance requires empty stderr from every PHP test execution',
          "for name in ('inbox.stderr.log', 'message.stderr.log'," in text
          and "'notices.stderr.log', 'copied.stderr.log')" in text
          and 'path = cell / name' in text
          and 'assert path.stat().st_size == 0, path' in text)
    check('Source and every matrix cell retain unconditional evidence',
          text.count('actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02') == 2
          and text.count('if: always()') == 3
          and 'mail-folder-${{ matrix.php }}-${{ matrix.database }}-${{ needs.source.outputs.sha }}-${{ github.run_id }}-attempt-${{ github.run_attempt }}' in text
          and text.count('if-no-files-found: error') == 2)
    check('Image build failure retains logs before enforcing status',
          'build.stdout.log' in text and 'build.stderr.log' in text
          and text.index('mkdir -p "$cell"') < text.index('docker build --build-arg PHP_VERSION='))
    build = text.split('      - name: Prepare disposable official PHP\n', 1)[1]
    check('Every matrix artifact carries exact self-contained identity before build failure',
          'actual=$(git rev-parse HEAD)' in build
          and 'tree=$(git rev-parse HEAD^{tree})' in build
          and 'test "$actual" = "$CWP_SOURCE_SHA"' in build
          and 'test "$CWP_RUN_ATTEMPT" = "1"' in build
          and "printf 'source=%s\\ntree=%s\\nrun_id=%s\\nrun_attempt=%s\\nphp=%s\\ndatabase=%s\\n'" in build
          and '> "$cell/identity.txt"' in build
          and build.index('> "$cell/identity.txt"') < build.index('docker build --build-arg PHP_VERSION='))
    check('Workflow contains no softened failure secret or live-operation channel',
          all(token not in text for token in ('continue-on-error', 'set +e', '|| true',
                                               'secrets.', 'smtp', 'imap.example', 'ssh ',
                                               'workflow_run:', 'curl ')))
    check('Final checkpoint requires source admission and complete matrix', 'needs: [source, native]' in text)
    return checks


def self_test(text):
    checks = validate(text)
    mutations = (
        ('reduced PHP matrix', "php: ['8.2', '8.4']", "php: ['8.4']"),
        ('reduced database matrix', 'database: [mariadb, mysql]', 'database: [mariadb]'),
        ('moving source checkout', 'ref: ${{ github.event.pull_request.head.sha || github.sha }}', 'ref: main'),
        ('write permission', 'contents: read', 'contents: write'),
        ('cancelled main evidence', "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}", 'cancel-in-progress: true'),
        ('rerun admission', 'test "$CWP_RUN_ATTEMPT" = "1"', 'test -n "$CWP_RUN_ATTEMPT"'),
        ('lost shared mail trigger', "'site/app/mail/**'", "'site/app/mail/inbox.php'"),
        ('lost matrix identity', 'tree=$(git rev-parse HEAD^{tree})', 'tree=unknown'),
        ('lost PDO MySQL', 'docker-php-ext-install pdo_mysql mbstring dom', 'docker-php-ext-install mbstring dom'),
        ('lost DOM', 'docker-php-ext-install pdo_mysql mbstring dom', 'docker-php-ext-install pdo_mysql mbstring'),
        ('lost no-INI isolation', 'php_flags="-n"', 'php_flags=""'),
        ('lost no-INI mbstring capability', 'function_exists("mb_convert_encoding")', 'function_exists("missing_mbstring")'),
        ('lost no-INI ctype capability', 'function_exists("ctype_digit")', 'function_exists("missing_ctype")'),
        ('lost no-INI DOM capability', 'class_exists("DOMDocument")', 'class_exists("MissingDOMDocument")'),
        ('softened failure', 'git diff --exit-code', 'git diff --exit-code || true'),
        ('lost named migration gate', 'Declared-current refusal does not heal the table', 'Unrelated assertion'),
        ('lost runner-image guard', 'sudo python3 -I .github/scripts/prepare_ubuntu_apt.py prepare-ubuntu', 'true # runner admission omitted'),
        ('lost runner failure receipt', 'sudo test -f qa/ubuntu-apt-source-preflight.json', 'true # preflight receipt omitted'),
        ('lost portable reply all', 'php $php_flags tests/mail-compose/reply_all.php', 'true # reply all omitted'),
        ('changed copied-source report count', "'copied-source': 19", "'copied-source': 21"),
        ('lost retained storage report count', "'storage': 21", "'storage': 20"),
        ('lost retained adapter report count', "'adapter': 19", "'adapter': 20"),
        ('changed exact native report count', "'message': (cell / 'message-results.json', 94)", "'message': (cell / 'message-results.json', 30)"),
        ('lost failure evidence', 'if: always()', 'if: success()'),
    )
    for name, old, new in mutations:
        require(old in text, 'Mutation fixture disappeared: ' + name)
        try:
            validate(text.replace(old, new, 1))
        except AssertionError:
            checks.append({'name': 'Regression rejected: ' + name, 'passed': True})
        else:
            raise AssertionError('Regression accepted: ' + name)
    return {
        'passed': True,
        'scope': __doc__ + ' No hosted, installed-browser, package, customer-message, provider or live-operation result.',
        'checks': checks,
    }


if __name__ == '__main__':
    print(json.dumps(self_test(WORKFLOW.read_text()), indent=2))
