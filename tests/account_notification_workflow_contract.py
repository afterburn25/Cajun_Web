"""Prove current-source admission retains the existing focused acceptance gates.

The source-building workflow retains an immutable historical predecessor. Its
complete acceptance and checkpoint jobs are the command/condition/report
baseline here; the current workflow may add only the exact reviewed UX-09
native commands before being projected back to those predecessor bytes. This
is source and workflow verification, not a hosted SQL or browser result.
"""
from pathlib import Path
import hashlib
import json
import re
import shlex

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = '.github/workflows/account-notifications-acceptance.yml'
LEGACY = '.github/workflows/account-notifications-combined.yml'
PROTECTED = '.github/workflows/application-tests.yml'
LEGACY_SHA256 = '978f1d4d2f6e9ef26436d0b21b5c3136440544fe179c1a890370a144d621cde8'
PROTECTED_SHA256 = 'c3a2cf408833a8dcf5ec661a8e5e89b8201bb49019885cb97ddfeb56dc0da185'
PROTECTED_ADDITIONS = (
    '          "$PHP_BINARY" tests/client_identity_policy.php > qa/client-identity-policy-results.json\n',
    '          "$PHP_BINARY" tests/client_identity_native.php\n',
)
PROTECTED_ADDITIONS_AFTER = '          "$PHP_BINARY" tests/client_authority_native.php\n'
PROTECTED_PAYMENT_ADDITIONS = (
    '          CWP_TEST_HOSTING_CHECKOUT=1 "$PHP_BINARY" -d display_errors=0 -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail,exec,shell_exec,system,passthru,popen,proc_open tests/hosting_checkout_native.php > qa/hosting-checkout-native.stdout.log 2> qa/hosting-checkout-native.stderr.log\n',
    '          test ! -s qa/hosting-checkout-native.stderr.log\n',
    '          CWP_TEST_HOSTING_PAYMENT=1 "$PHP_BINARY" -d display_errors=0 -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail,exec,shell_exec,system,passthru,popen,proc_open tests/hosting_payment_native.php > qa/hosting-payment-native.stdout.log 2> qa/hosting-payment-native.stderr.log\n',
    '          test ! -s qa/hosting-payment-native.stderr.log\n',
)
PROTECTED_PAYMENT_ADDITIONS_AFTER = '          "$PHP_BINARY" tests/software_commerce_native.php\n'
PROTECTED_PAYMENT_ENV_ADDITION = '      CWP_TEST_HOSTING_PAYMENT_FOREIGN_DATABASE: cwp_hpay_foreign\n'
PROTECTED_PAYMENT_ENV_AFTER = '      CWP_TEST_PASSWORD: local-ci-only\n'
PROTECTED_PAYMENT_SETUP_ADDITION = ('          docker exec -e MYSQL_PWD=isolated-ci-root-only ${{ job.services.mariadb.id }} mysql -uroot -e '
                                    '"CREATE DATABASE cwp_hpay_foreign; GRANT SELECT,CREATE,DROP,REFERENCES ON cwp_hpay_foreign.* TO \'cwp_test\'@\'%\';"\n')
PROTECTED_PAYMENT_SETUP_AFTER = ('          if [ "$CWP_DATABASE" = "mysql-8.4" ]; then\n'
                                 "            docker exec -e MYSQL_PWD=isolated-ci-root-only ${{ job.services.mariadb.id }} mysql -uroot -e 'SET GLOBAL log_bin_trust_function_creators=1'\n"
                                 "            docker exec -e MYSQL_PWD=isolated-checkout-root-only ${{ job.services.checkout_mariadb.id }} mysql -uroot -e 'SET GLOBAL log_bin_trust_function_creators=1'\n"
                                 '          fi\n')
PROTECTED_BROWSER_PREDECESSOR = '        run: .ci-venv/bin/python tests/browser_acceptance.py\n'
PROTECTED_BROWSER_SUCCESSOR = ('        run: |\n'
                               '          .ci-venv/bin/python tests/browser_acceptance.py\n'
                               '          .ci-venv/bin/python tests/hosting_checkout_http_browser.py --full-suite\n')
PROTECTED_APT_MIRRORS = r'''          sudo python3 - <<'CWP_UBUNTU_MIRRORS'
          from pathlib import Path

          source = Path('/etc/apt/apt-mirrors.txt')
          if source.is_symlink() or not source.is_file() or source.stat().st_size > 4096:
              raise SystemExit('Expected regular Ubuntu mirror list is unavailable')
          original = source.read_bytes()
          expected = (
              b'http://azure.archive.ubuntu.com/ubuntu/\tpriority:1\n'
              b'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
              b'https://security.ubuntu.com/ubuntu/\tpriority:3\n'
          )
          if original != expected:
              raise SystemExit('Unexpected Ubuntu mirror list')
          selected = (
              b'https://archive.ubuntu.com/ubuntu/\tpriority:1\n'
              b'https://security.ubuntu.com/ubuntu/\tpriority:2\n'
              b'http://azure.archive.ubuntu.com/ubuntu/\tpriority:3\n'
          )
          source.write_bytes(selected)
          if source.read_bytes() != selected:
              raise SystemExit('Ubuntu mirror selection was not retained')
          print('Canonical Ubuntu archives selected before Azure fallback')
          CWP_UBUNTU_MIRRORS
'''
PROTECTED_APT_MIRRORS_COUNT = 4
PROTECTED_APT_WRAPPER = '''          cwp_apt_get() {
            local cwp_apt_attempt
            for cwp_apt_attempt in 1 2 3; do
              if sudo apt-get "$@"; then
                return 0
              fi
              if [ "$cwp_apt_attempt" -eq 3 ]; then
                return 1
              fi
              echo "::warning::apt-get attempt ${cwp_apt_attempt}/3 failed; retrying on a fresh connection" >&2
              sleep 5
            done
          }
'''
PROTECTED_APT_WRAPPER_COUNT = 4
PROTECTED_APT_CALL_SUCCESSOR = 'cwp_apt_get -o Acquire::Retries=1'
PROTECTED_APT_CALL_PREDECESSOR = 'sudo apt-get -o Acquire::Retries=5'
PROTECTED_APT_CALL_COUNT = 16
IMMUTABLE = '${{ github.event.pull_request.head.sha || github.sha }}'
PARENTS = ('84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd',
           'ebcbbb56336249fee2073c8f02e9d6998740b64e')
DEPENDENCIES = {'site/**', 'foundation/**', 'tests/**', 'tools/**', 'docs/*.json',
                'VERSION', 'START-HERE.txt', WORKFLOW, LEGACY, PROTECTED}


def digest(value):
    return hashlib.sha256(value.encode()).hexdigest()


def require(passed, message):
    if not passed:
        raise AssertionError(message)


def jobs(text):
    require(text.count('\njobs:\n') == 1, 'One ordinary jobs mapping is required')
    body = text.split('\njobs:\n', 1)[1]
    entries = list(re.finditer(r'^  ([a-z][a-z0-9-]*):\n', body, re.M))
    names = [item.group(1) for item in entries]
    require(names == ['source', 'acceptance', 'checkpoint'], 'Unexpected workflow job graph')
    return {item.group(1): body[item.start():entries[i + 1].start() if i + 1 < len(entries) else len(body)]
            for i, item in enumerate(entries)}


def flow_list(value):
    require(value.startswith('[') and value.endswith(']'), 'Expected bounded event list')
    values = [item.strip().strip("'\"") for item in value[1:-1].split(',')]
    require(all(re.fullmatch(r'[A-Za-z0-9_./*-]+', item) for item in values)
            and len(values) == len(set(values)), 'Invalid or duplicate event dependency')
    return set(values)


def events(text):
    require(text.count('\non:\n') == 1 and text.count('\npermissions:\n') == 1,
            'Unexpected event or permission mapping')
    body = text.split('\non:\n', 1)[1].split('\npermissions:\n', 1)[0]
    result = {}
    current = None
    for line in body.splitlines():
        heading = re.fullmatch(r'  ([a-z_]+):', line)
        if heading:
            current = heading.group(1)
            require(current not in result, 'Duplicate event')
            result[current] = {}
            continue
        field = re.fullmatch(r'    (branches|paths): (.+)', line)
        require(field is not None and current is not None, 'Unbounded event configuration')
        key, value = field.groups()
        require(key not in result[current], 'Duplicate event field')
        result[current][key] = flow_list(value)
    return result


def proof_commands(source):
    """Compare actual retained test invocations, ignoring only output redirection."""
    commands = []
    for line in source.splitlines():
        command = line.strip().split(' > ', 1)[0]
        if re.match(r'^(python3|php(?: -n)?) tests/', command):
            commands.append(tuple(shlex.split(command)))
    return commands


def validate(workflow, legacy, protected):
    checks = []

    def check(name, passed):
        require(passed, name)
        checks.append({'name': name, 'passed': True})

    check('Legacy source builder and focused workflow remain byte-identical', digest(legacy) == LEGACY_SHA256)
    protected_predecessor = protected
    for addition in reversed(PROTECTED_ADDITIONS):
        check('Reviewed UX-09 application command is present exactly once',
              protected_predecessor.count(addition) == 1)
        protected_predecessor = protected_predecessor.replace(addition, '', 1)
    protected_block = PROTECTED_ADDITIONS_AFTER + ''.join(PROTECTED_ADDITIONS)
    check('Reviewed UX-09 application commands are additive and ordered',
          protected.count(protected_block) == 1)
    for addition in reversed(PROTECTED_PAYMENT_ADDITIONS):
        check('Reviewed UX-08 application command is present exactly once',
              protected_predecessor.count(addition) == 1)
        protected_predecessor = protected_predecessor.replace(addition, '', 1)
    payment_block = PROTECTED_PAYMENT_ADDITIONS_AFTER + ''.join(PROTECTED_PAYMENT_ADDITIONS)
    check('Reviewed UX-08 native commands are additive and ordered', protected.count(payment_block) == 1)
    for name, addition, after in (
        ('environment declaration', PROTECTED_PAYMENT_ENV_ADDITION, PROTECTED_PAYMENT_ENV_AFTER),
        ('least-privilege foreign fixture bootstrap', PROTECTED_PAYMENT_SETUP_ADDITION, PROTECTED_PAYMENT_SETUP_AFTER),
    ):
        check('Reviewed UX-08 payment '+name+' is present exactly once',
              protected_predecessor.count(addition) == 1)
        check('Reviewed UX-08 payment '+name+' is additive and ordered',
              protected.count(after + addition) == 1)
        protected_predecessor = protected_predecessor.replace(addition, '', 1)
    check('Reviewed UX-08 browser continuation retains the original command first',
          protected_predecessor.count(PROTECTED_BROWSER_SUCCESSOR) == 1)
    protected_predecessor = protected_predecessor.replace(
        PROTECTED_BROWSER_SUCCESSOR, PROTECTED_BROWSER_PREDECESSOR, 1)
    check('Protected application workflow has every reviewed canonical mirror selection',
          protected_predecessor.count(PROTECTED_APT_MIRRORS) == PROTECTED_APT_MIRRORS_COUNT)
    protected_predecessor = protected_predecessor.replace(PROTECTED_APT_MIRRORS, '')
    check('Protected application workflow has every reviewed bounded reconnect wrapper',
          protected_predecessor.count(PROTECTED_APT_WRAPPER) == PROTECTED_APT_WRAPPER_COUNT)
    protected_predecessor = protected_predecessor.replace(PROTECTED_APT_WRAPPER, '')
    check('Protected application workflow routes every reviewed apt call through bounded reconnects',
          protected_predecessor.count(PROTECTED_APT_CALL_SUCCESSOR) == PROTECTED_APT_CALL_COUNT
          and protected_predecessor.count(PROTECTED_APT_CALL_PREDECESSOR) == 0)
    protected_predecessor = protected_predecessor.replace(
        PROTECTED_APT_CALL_SUCCESSOR, PROTECTED_APT_CALL_PREDECESSOR)
    check('Protected complete application workflow retains its byte-identical predecessor',
          digest(protected_predecessor) == PROTECTED_SHA256)
    check('Workflow has ordinary indentation and no alternate YAML documents',
          '\t' not in workflow and not re.search(r'^(---|\.\.\.)', workflow, re.M))
    actual, historical = jobs(workflow), jobs(legacy)
    check('Every matrix command, browser condition, source binding and report is retained exactly',
          actual['acceptance'] == historical['acceptance'])
    check('The final success checkpoint still depends on source and the whole matrix',
          actual['checkpoint'] == historical['checkpoint'])
    triggers = events(workflow)
    check('Current-source events are bounded push, pull request and manual events',
          set(triggers) == {'push', 'pull_request', 'workflow_dispatch'} and triggers['workflow_dispatch'] == {})
    check('Push admission includes only main and the isolated acceptance branch',
          set(triggers['push']) == {'branches', 'paths'}
          and triggers['push']['branches'] == {'main', 'chatgpt/account-notifications-acceptance-20261101'})
    check('Pull-request admission follows main integration',
          set(triggers['pull_request']) == {'branches', 'paths'}
          and triggers['pull_request']['branches'] == {'main'})
    for event in ('push', 'pull_request'):
        check('All source, receipt and workflow dependencies trigger ' + event,
              triggers[event]['paths'] == DEPENDENCIES)
    check('The entire new workflow has read-only repository permission',
          len(re.findall(r'^\s*permissions:', workflow, re.M)) == 1
          and '\npermissions:\n  contents: read\nconcurrency:\n' in workflow)
    check('Development concurrency is scoped to the pull request or branch',
          '\nconcurrency:\n  group: account-notifications-acceptance-${{ github.event.pull_request.number || github.ref }}\n' in workflow)
    check('Main retains each immutable acceptance run',
          "\n  cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}\njobs:\n" in workflow)
    source = actual['source']
    check('Current source admission cannot silently skip or soften failure',
          '    if:' not in source.split('    steps:\n', 1)[0]
          and 'continue-on-error' not in source and 'set +e' not in source and '|| true' not in source)
    check('Source checkout and evidence identity use the same immutable event commit',
          source.count('      CWP_SOURCE_SHA: ' + IMMUTABLE + '\n') == 1
          and source.count('          ref: ' + IMMUTABLE + '\n') == 1
          and source.count('          fetch-depth: 0\n') == 1
          and source.count('          persist-credentials: false\n') == 1)
    check('Only the pinned checkout and evidence upload actions run during admission',
          re.findall(r'^      - uses: (.+)$', source, re.M) == [
              'actions/checkout@11d5960a326750d5838078e36cf38b85af677262',
              'actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02'])
    check('Current-source admission never invokes legacy source reconstruction or credentials',
          'tools/integration/' not in source and 'github.token' not in source
          and 'secrets.' not in source and 'SOURCE_TOKEN' not in source
          and not re.search(r'\bgit\s+(?:push|commit|commit-tree|checkout|reset|update-ref|merge(?!-base))\b', source))
    for parent in PARENTS:
        check('Published parent ancestry is required ' + parent,
              source.count('          git merge-base --is-ancestor ' + parent + ' HEAD\n') == 1)
    current_commands = proof_commands(source)
    for command in proof_commands(historical['source']):
        check('Original source proof still executes ' + ' '.join(command), current_commands.count(command) == 1)
    for script in ('account_notification_workflow_contract.py', 'account_notification_fixture_provenance.py',
                   'provider_browser_fixture_provenance.py', 'notification_integration_contract.py',
                   'tickets_integration_contract.py'):
        check('Current-source proof executes ' + script,
              current_commands.count(('python3', 'tests/' + script)) == 1)
    check('Canonical source is rebuilt and must remain unchanged before admission',
          '          python3 tools/build.py --refresh > qa/account-notification-source/build.log\n' in source
          and '          git diff --exit-code\n' in source
          and source.index('          git diff --exit-code\n') < source.index('        id: record\n'))
    check('Admission binds actual HEAD to the requested SHA and requires clean source',
          '      sha: ${{ steps.record.outputs.sha }}\n' in source
          and '          actual=$(git rev-parse HEAD)\n'
              '          test "$actual" = "$CWP_SOURCE_SHA"\n'
              '          test -z "$(git status --porcelain)"\n'
              '          printf \'sha=%s\\n\' "$actual" >> "$GITHUB_OUTPUT"\n' in source)
    marker = '      - name: Retain exact source and failure evidence\n'
    old_evidence = historical['source'].split(marker, 1)[1]
    expected_evidence = old_evidence.replace('account-notification-source-${{ github.sha }}',
                                            'account-notification-current-source-' + IMMUTABLE)
    check('Exact source, dirty state and failure evidence retain unconditional upload',
          source.count(marker) == 1 and source.split(marker, 1)[1] == expected_evidence)
    return checks


def self_test(root=ROOT):
    workflow = (root / WORKFLOW).read_text()
    legacy = (root / LEGACY).read_text()
    protected = (root / PROTECTED).read_text()
    checks = validate(workflow, legacy, protected)
    changes = (
        ('lost SQL command', '          php tests/account_security_native.php >', '          true # tests/account_security_native.php >'),
        ('reduced PHP matrix', "php: ['8.2','8.4']", "php: ['8.4']"),
        ('replaced database matrix', 'database: [mysql,mariadb]', 'database: [mysql]'),
        ('skipped browser', "        if: matrix.php == '8.4'", '        if: false'),
        ('weakened report gate', "len(r['checks'])>=minimum", "len(r['checks'])>=0"),
        ('moving checkout ref', '          ref: ' + IMMUTABLE, '          ref: main'),
        ('write permission', '  contents: read', '  contents: write'),
        ('legacy reconstruction', '          git diff --exit-code\n', '          python3 tools/integration/owner_navigation_fix.py\n          git diff --exit-code\n'),
        ('missing parent', '          git merge-base --is-ancestor ' + PARENTS[0] + ' HEAD\n', ''),
        ('missing retained policy', '          php -n tests/account_security_policy.php > qa/account-notification-source/account-policy-minimal.json\n', ''),
        ('lost receipt dependency', "'docs/*.json', ", ''),
        ('lost protected workflow dependency', ", '.github/workflows/application-tests.yml'", ''),
        ('unbounded push branches', 'branches: [main, chatgpt/account-notifications-acceptance-20261101]', "branches: ['**']"),
        ('per-commit cancellation group', 'group: account-notifications-acceptance-${{ github.event.pull_request.number || github.ref }}', 'group: account-notifications-acceptance-${{ github.sha }}'),
        ('cancelled main evidence', "cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}", 'cancel-in-progress: true'),
        ('unbound source output', '          test "$actual" = "$CWP_SOURCE_SHA"\n', ''),
        ('dirty source admission', '          test -z "$(git status --porcelain)"\n', ''),
        ('lost failure evidence', '      - name: Retain exact source and failure evidence\n        if: always()', '      - name: Retain exact source and failure evidence\n        if: success()'),
    )
    for name, old, new in changes:
        require(old in workflow, 'Mutation fixture disappeared: ' + name)
        changed = workflow.replace(old, new, 1)
        try:
            validate(changed, legacy, protected)
        except AssertionError:
            checks.append({'name': 'Regression rejected: ' + name, 'passed': True})
        else:
            raise AssertionError('Regression accepted: ' + name)
    for name, old, full in (('legacy source-builder drift', legacy + '\n', protected),
                            ('protected acceptance drift', legacy, protected + '\n')):
        try:
            validate(workflow, old, full)
        except AssertionError:
            checks.append({'name': 'Regression rejected: ' + name, 'passed': True})
        else:
            raise AssertionError('Regression accepted: ' + name)
    for name, changed in (
        ('lost UX-09 policy command', protected.replace(PROTECTED_ADDITIONS[0], '', 1)),
        ('changed UX-09 native command', protected.replace('tests/client_identity_native.php',
                                                            'tests/client_identity_policy.php', 1)),
        ('lost UX-08 payment command', protected.replace(PROTECTED_PAYMENT_ADDITIONS[2], '', 1)),
        ('lost UX-08 payment foreign-schema environment', protected.replace(PROTECTED_PAYMENT_ENV_ADDITION, '', 1)),
        ('lost UX-08 payment foreign-schema bootstrap', protected.replace(PROTECTED_PAYMENT_SETUP_ADDITION, '', 1)),
        ('broadened UX-08 payment foreign-schema grant', protected.replace('GRANT SELECT,CREATE,DROP,REFERENCES', 'GRANT ALL', 1)),
        ('changed UX-08 browser continuation', protected.replace(
            'tests/hosting_checkout_http_browser.py --full-suite',
            'tests/hosting_checkout_http_browser.py', 1)),
        ('lost canonical application mirror selection', protected.replace(PROTECTED_APT_MIRRORS, '', 1)),
        ('lost bounded application reconnect wrapper', protected.replace(PROTECTED_APT_WRAPPER, '', 1)),
        ('lost bounded application mirror retry', protected.replace(
            PROTECTED_APT_CALL_SUCCESSOR, PROTECTED_APT_CALL_PREDECESSOR, 1)),
        ('extra bounded application mirror retry', protected + PROTECTED_APT_CALL_SUCCESSOR + '\n'),
        ('extra protected workflow command', protected.replace(
            PROTECTED_ADDITIONS[1], PROTECTED_ADDITIONS[1] + '          true # unreviewed command\n', 1)),
    ):
        try:
            validate(workflow, legacy, changed)
        except AssertionError:
            checks.append({'name': 'Regression rejected: ' + name, 'passed': True})
        else:
            raise AssertionError('Regression accepted: ' + name)
    return {'passed': True, 'scope': __doc__.splitlines()[0] + ' Not hosted or installed acceptance.',
            'workflow_sha256': digest(workflow), 'legacy_sha256': digest(legacy), 'checks': checks}


if __name__ == '__main__':
    print(json.dumps(self_test(), indent=2))
