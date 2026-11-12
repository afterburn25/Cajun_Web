"""Guard the held project-action exact-source supported SQL workflow."""
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = ROOT / '.github/workflows/project-action-items-native.yml'


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
          'group: project-action-items-native-${{ github.event.pull_request.number || github.ref }}' in text
          and "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}" in text)
    check('Push and pull-request admission include workflow tests and source families',
          text.count("'tests/project_action_items_native_workflow_contract.py'") == 2
          and text.count("'.github/workflows/project-action-items-native.yml'") == 2
          and text.count("'site/app/projects/**'") == 2
          and text.count("'site/app/clients/**'") == 2
          and text.count("'site/portal/views/workspace.php'") == 2
          and text.count("'site/portal/controllers/actions.php'") == 2
          and text.count("'site/portal/controllers/router.php'") == 2
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
    check('Source admission runs model runtime mirror and syntax contracts',
          'python3 tests/project_action_items_native_workflow_contract.py' in text
          and 'php tests/project-actions/records.php' in text
          and 'php tests/project_action_items_runtime.php' in text
          and 'cmp -s foundation/projects/ProjectActionItems.php site/includes/foundation/projects/ProjectActionItems.php' in text)
    check('Acceptance retains PHP and database support matrix',
          "php: ['8.2', '8.4']" in text and 'database: [mariadb, mysql]' in text
          and "image: ${{ matrix.database == 'mariadb' && 'mariadb:10.11' || 'mysql:8.4' }}" in text)
    check('Disposable PHP retains required PDO MySQL and mbstring extensions',
          'docker-php-ext-install pdo_mysql mbstring' in text)
    check('Assigned synthetic user has bounded retained readiness proof',
          'for($i=0;$i<60;$i++)' in text and 'usleep(500000)' in text
          and 'readiness.stdout.log' in text and 'readiness.stderr.log' in text)
    check('Only the exact project action native suite runs in the SQL matrix',
          text.count('php tests/project_action_items_native.php\n') == 1)
    check('Matrix lint output is retained separately from native JSON',
          text.count('qa/project-action-items-native/${{ matrix.php }}-${{ matrix.database }}/lint.log') == 3
          and "' > \"$cell/results.json\" 2> \"$cell/stderr.log\" || status=$?" in text)
    check('Native results require all fourteen or more named checks',
          "assert report['passed'] is True" in text
          and "assert len(report['checks']) >= 14" in text
          and "assert all(check['passed'] is True for check in report['checks'])" in text)
    native_run = text.split('      - name: Run isolated project action SQL acceptance\n', 1)[1]
    check('Failed native execution retains stdout and stderr before status enforcement',
          '> "$cell/results.json" 2> "$cell/stderr.log" || status=$?' in native_run
          and native_run.index('test -f "$cell/results.json"') < native_run.index('test "$status" -eq 0'))
    check('Source and every matrix cell retain unconditional evidence',
          text.count('actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02') == 2
          and text.count('if: always()') == 3
          and 'project-action-items-${{ matrix.php }}-${{ matrix.database }}-${{ needs.source.outputs.sha }}-${{ github.run_id }}-attempt-${{ github.run_attempt }}' in text
          and text.count('if-no-files-found: error') == 2)
    check('Image build failure retains logs before enforcing its status',
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
                                               'secrets.', 'authorize.net', 'smtp', 'ssh ',
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
        ('lost workspace trigger', "'site/portal/views/workspace.php'", "'site/portal/views/other.php'"),
        ('lost matrix identity', 'tree=$(git rev-parse HEAD^{tree})', 'tree=unknown'),
        ('lost PDO MySQL', 'docker-php-ext-install pdo_mysql mbstring', 'docker-php-ext-install mbstring'),
        ('softened failure', 'git diff --exit-code', 'git diff --exit-code || true'),
        ('lost report pass gate', "assert report['passed'] is True", 'assert True'),
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
    return {'passed': True,
            'scope': __doc__ + ' No hosted, installed-browser, package, customer-message, payment, provider or live-operation result.',
            'checks': checks}


if __name__ == '__main__':
    print(json.dumps(self_test(WORKFLOW.read_text()), indent=2))
