"""Guard the held copied-draft exact-source Linux SQL acceptance workflow."""
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = ROOT / '.github/workflows/mail-copied-draft-native.yml'


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
    check('Development cancellation is ref-scoped while main evidence is retained',
          'group: mail-copied-draft-native-${{ github.event.pull_request.number || github.ref }}' in text
          and "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}" in text)
    check('Push and pull-request admission include the workflow and copied-draft tests',
          text.count("'tests/mail_copied_draft_*.php'") == 2
          and text.count("'tests/mail_copied_draft_native_workflow_contract.py'") == 2
          and text.count("'site/admin/**'") == 2
          and text.count("'tests/mail-compose/**'") == 2
          and text.count("'tests/mail-receiving/attachment_*.php'") == 2
          and text.count("'tests/database_schema_registry_policy.php'") == 2
          and text.count("'tests/mail_message_cases.py'") == 2
          and text.count("'.github/workflows/mail-copied-draft-native.yml'") == 2
          and text.count('branches: [main]') == 2)
    check('Manual redispatch cannot masquerade as a new first source run',
          'workflow_dispatch:' not in text)
    immutable = '${{ github.event.pull_request.head.sha || github.sha }}'
    check('Source checkout is bound to the immutable event commit',
          text.count('ref: ' + immutable) == 1
          and text.count('CWP_SOURCE_SHA: ' + immutable) == 1)
    check('Matrix checkout and jobs are bound to the admitted source output',
          text.count('ref: ${{ needs.source.outputs.sha }}') == 1
          and text.count('CWP_SOURCE_SHA: ${{ needs.source.outputs.sha }}') == 2)
    check('Every checkout is pinned and discards credentials',
          text.count('actions/checkout@11d5960a326750d5838078e36cf38b85af677262') == 2
          and text.count('persist-credentials: false') == 2)
    check('Only first-attempt evidence is admissible and run provenance is retained',
          text.count('CWP_RUN_ID: ${{ github.run_id }}') == 2
          and text.count('CWP_RUN_ATTEMPT: ${{ github.run_attempt }}') == 2
          and 'test "$CWP_RUN_ATTEMPT" = "1"' in text
          and text.count('${{ github.run_id }}-attempt-${{ github.run_attempt }}') == 2)
    check('Every hosted lane refuses the withdrawn Ubuntu mirror image before package networking',
          text.count("raise SystemExit('Unexpected Ubuntu mirror list')") == 2
          and text.count("b'http://azure.archive.ubuntu.com/ubuntu/\\tpriority:1\\n'") == 2
          and text.count("print('Supported Ubuntu runner mirror list verified')") == 2)
    check('Source admission runs the reviewed Python message contract and retains its result',
          text.count('python3 tests/mail_message_cases.py') == 1
          and text.count("printf 'passed\\n' > qa/mail-copied-draft-native/source/mail-message-source-cases.log") == 1)
    check('Acceptance retains the complete PHP and database matrix',
          "php: ['8.2', '8.4']" in text and 'database: [mariadb, mysql]' in text
          and "image: ${{ matrix.database == 'mariadb' && 'mariadb:10.11' || 'mysql:8.4' }}" in text)
    check('The disposable PHP image retains PDO MySQL and mbstring',
          'apt-get install -y --no-install-recommends libonig-dev' in text
          and 'docker-php-ext-install pdo_mysql mbstring' in text)
    check('The assigned synthetic user has a bounded retained PDO readiness proof',
          'for($i=0;$i<60;$i++)' in text and 'usleep(500000)' in text
          and 'readiness.stdout.log' in text and 'readiness.stderr.log' in text)
    check('Only the exact native copied-draft suite runs in the SQL matrix',
          text.count('php tests/mail_copied_draft_create_native.php\n') == 1)
    portable = (
        ('tests/mail-compose/origin.php', 'origin', '17'),
        ('tests/mail-compose/reply.php', 'reply', '60'),
        ('tests/mail-compose/reply_all.php', 'reply-all', '37'),
        ('tests/mail-compose/thread.php', 'thread', '45'),
        ('tests/mail-compose/copied_source.php', 'copied-source', '21'),
        ('tests/mail_copied_draft_storage.php', 'storage', '20'),
        ('tests/mail_copied_draft_admin_policy.php', 'admin-policy', '23'),
        ('tests/mail_copied_draft_create_adapter.php', 'adapter', '20'),
        ('tests/database_schema_registry_policy.php', 'schema-registry', '381'),
        ('tests/mail-receiving/attachment_test.php', 'attachment', '14'),
        ('tests/mail-receiving/attachment_security_test.php', 'attachment-security', '27'),
    )
    check('Every native cell runs the reviewed portable surface in normal and no-INI modes',
          'for mode in normal noini; do' in text
          and 'php_flags="-n -d extension=mbstring"' in text
          and all(text.count('php $php_flags ' + path + ' > "$cell/portable-$mode-' + report + '.json"') == 1
                  for path, report, _ in portable))
    check('Portable evidence is complete, named and exact-count validated before acceptance',
          "for mode in ('normal', 'noini'):" in text
          and "assert len(report['checks']) == count" in text
          and all(("'" + report + "': " + count) in text for _, report, count in portable))
    check('The workflow requires successful complete named reports',
          text.count("assert report['passed'] is True") == 2
          and "assert len(report['checks']) >= 21" in text
          and text.count("assert all(check['passed'] is True for check in report['checks'])") == 2)
    native_run = text.split('      - name: Run isolated copied-draft SQL acceptance\n', 1)[1]
    check('A failed suite retains its JSON before the original status is enforced',
          '|| status=$?' in native_run
          and native_run.index('if [ -f qa/mail-copied-draft-create-native-results.json ]; then')
          < native_run.index('test "$status" -eq 0'))
    check('Source and every matrix cell retain unconditional evidence',
          text.count('actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02') == 2
          and text.count('if: always()') == 3
          and 'mail-copied-draft-${{ matrix.php }}-${{ matrix.database }}-${{ needs.source.outputs.sha }}-${{ github.run_id }}-attempt-${{ github.run_attempt }}' in text
          and 'if-no-files-found: error' in text)
    check('A PHP image build failure retains cell logs before its status is enforced',
          'build.stdout.log' in text and 'build.stderr.log' in text
          and text.index('mkdir -p "$cell"') < text.index('docker build --build-arg PHP_VERSION='))
    check('No failure softening retry dispatch or secret/live-operation channel exists',
          all(token not in text for token in ('continue-on-error', 'set +e', '|| true',
                                               'secrets.', 'smtp',
                                               'workflow_run:', 'curl ', 'ssh ')))
    check('The final checkpoint requires both source admission and the whole matrix',
          'needs: [source, native]' in text)
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
        ('lost mbstring', 'docker-php-ext-install pdo_mysql mbstring', 'docker-php-ext-install pdo_mysql'),
        ('lost admin path admission', "'site/admin/**'", "'site/app/mail/**'"),
        ('lost portable reply all', 'php $php_flags tests/mail-compose/reply_all.php', 'true # portable reply-all removed'),
        ('lost runner-image guard', "raise SystemExit('Unexpected Ubuntu mirror list')", "print('ignored Ubuntu mirror list')"),
        ('lost source message contract', 'python3 tests/mail_message_cases.py', ': # source message contract removed'),
        ('softened failure', 'git diff --exit-code', 'git diff --exit-code || true'),
        ('lost report gate', "assert report['passed'] is True", 'assert True'),
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
    return {'passed': True, 'scope': __doc__ + ' No hosted, installed, mail-transport or live-operation result.',
            'checks': checks}


if __name__ == '__main__':
    print(json.dumps(self_test(WORKFLOW.read_text()), indent=2))
