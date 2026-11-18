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
import os
import re
import shlex
import shutil
import subprocess
import tempfile
import textwrap

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = '.github/workflows/account-notifications-acceptance.yml'
LEGACY = '.github/workflows/account-notifications-combined.yml'
PROTECTED = '.github/workflows/application-tests.yml'
APT_PREPARATION_SCRIPT = '.github/scripts/prepare_ubuntu_apt.py'
APT_PREPARATION_SHA256 = '37f6a8f12196a7a991dc2d3ed8ec346dd6d51257c39f58c16bc2409af6ad297a'
LEGACY_SHA256 = '978f1d4d2f6e9ef26436d0b21b5c3136440544fe179c1a890370a144d621cde8'
PROTECTED_SHA256 = 'c3a2cf408833a8dcf5ec661a8e5e89b8201bb49019885cb97ddfeb56dc0da185'
PROTECTED_BUILD_TIMEOUT_PREDECESSOR = ('  build:\n'
                                       '    runs-on: ubuntu-24.04\n'
                                       '    timeout-minutes: 45\n')
PROTECTED_BUILD_TIMEOUT_SUCCESSOR = ('  build:\n'
                                     '    runs-on: ubuntu-24.04\n'
                                     '    # Exact-source composition completed in 51m on image 20261111.127.1 but\n'
                                     '    # exceeded 83m on 20261111.370.1 after the package and all build guards\n'
                                     '    # had passed. Keep every assertion and allow bounded hosted-runner margin.\n'
                                     '    timeout-minutes: 180\n')
PROTECTED_ADDITIONS = (
    '          "$PHP_BINARY" tests/client_identity_policy.php > qa/client-identity-policy-results.json\n',
    '          "$PHP_BINARY" tests/client_identity_native.php\n',
)
PROTECTED_ADDITIONS_AFTER = '          "$PHP_BINARY" tests/client_authority_native.php\n'
PROTECTED_PROJECT_NOTE_ADDITIONS = (
    '          "$PHP_BINARY" tests/project-notes/records.php --deployment > qa/project-private-note-records.json\n',
    '          "$PHP_BINARY" tests/project_private_notes_policy.php > qa/project-private-notes-policy.json\n',
    '          "$PHP_BINARY" tests/project_private_notes_view.php > qa/project-private-notes-view.json\n',
    '          "$PHP_BINARY" tests/project_private_notes_native.php\n',
)
PROTECTED_PROJECT_NOTE_BLOCKS = (
    ('          "$PHP_BINARY" tests/estimator_adapter.php\n', PROTECTED_PROJECT_NOTE_ADDITIONS[:3]),
    ('          "$PHP_BINARY" tests/activity_native.php\n', PROTECTED_PROJECT_NOTE_ADDITIONS[3:]),
)
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
PROTECTED_APT_MIRRORS = '          sudo python3 -I .github/scripts/prepare_ubuntu_apt.py prepare-ubuntu\n'
PROTECTED_APT_MIRRORS_COUNT = 4
PROTECTED_APT_TRIGGER_ADDITION = ", '.github/scripts/**'"
PROTECTED_APT_TRIGGER_ADDITION_COUNT = 2
PROTECTED_APT_WRAPPER = '''          cwp_apt_get() {
            local cwp_apt_attempt
            for cwp_apt_attempt in 1 2 3; do
              if sudo apt-get -o Dir::Etc::sourcelist=/dev/null -o Dir::Etc::sourceparts=/etc/apt/cwp-sources.list.d -o Dir::State::lists=/var/lib/apt/cwp-lists "$@"; then
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
PROTECTED_PPA_IMPORT_PREDECESSOR = '          import re, subprocess, tempfile\n'
PROTECTED_PPA_IMPORT_SUCCESSOR = '          import os, re, stat, subprocess, tempfile\n'
PROTECTED_PPA_ISOLATION = r'''          isolated = Path('/etc/apt/cwp-sources.list.d')
          target = isolated / source.name
          metadata = isolated.lstat()
          if (not stat.S_ISDIR(metadata.st_mode) or metadata.st_uid != 0 or metadata.st_gid != 0
                  or stat.S_IMODE(metadata.st_mode) != 0o755 or os.path.lexists(target)):
              raise SystemExit('Expected empty protected APT isolation directory')
          isolated_fd = os.open(isolated, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
          if (os.fstat(isolated_fd).st_dev, os.fstat(isolated_fd).st_ino) != (metadata.st_dev, metadata.st_ino):
              raise SystemExit('APT isolation directory changed while opening')
          descriptor = os.open(target.name, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW,
                               0o644, dir_fd=isolated_fd)
          with os.fdopen(descriptor, 'wb') as stream:
              os.fchown(stream.fileno(), 0, 0)
              os.fchmod(stream.fileno(), 0o644)
              stream.write(changed)
              stream.flush()
              os.fsync(stream.fileno())
          os.fsync(isolated_fd)
          target_descriptor = os.open(target.name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=isolated_fd)
          with os.fdopen(target_descriptor, 'rb') as target_stream:
              target_metadata = os.fstat(target_stream.fileno())
              target_data = target_stream.read(65537)
          os.close(isolated_fd)
          if (not stat.S_ISREG(target_metadata.st_mode) or target_metadata.st_nlink != 1
                  or (target_metadata.st_uid, target_metadata.st_gid, stat.S_IMODE(target_metadata.st_mode)) != (0, 0, 0o644)
                  or target_data != changed):
              raise SystemExit('Isolated Noble PHP PPA source was not retained')
'''
PROTECTED_APT_ISOLATION_ACTIVATION = r'''          sudo test ! -e /etc/apt/apt.conf.d/zz-cwp-source-isolation
          sudo mv --no-clobber /etc/apt/cwp-source-isolation.conf.pending /etc/apt/apt.conf.d/zz-cwp-source-isolation
          sudo test ! -e /etc/apt/cwp-source-isolation.conf.pending
          test "$(sudo stat -c '%u:%g:%a:%h:%F' /etc/apt/apt.conf.d/zz-cwp-source-isolation)" = '0:0:644:1:regular file'
          printf 'Dir::Etc::sourcelist "/dev/null";\nDir::Etc::sourceparts "/etc/apt/cwp-sources.list.d";\nDir::State::lists "/var/lib/apt/cwp-lists";\n' | sudo cmp -s - /etc/apt/apt.conf.d/zz-cwp-source-isolation
          python3 -I .github/scripts/prepare_ubuntu_apt.py verify-effective
'''
PROTECTED_APT_ISOLATION_COUNT = 4
PROTECTED_PPA_PREDECESSOR = r'''            sudo python3 - <<'CWP_PHP_MIRROR'
          from pathlib import Path
          import re, subprocess, tempfile
          source = Path('/etc/apt/sources.list.d/ondrej-ubuntu-php-noble.sources')
          primary = 'https://ppa.launchpadcontent.net/ondrej/php/ubuntu/'
          mirror = 'https://ppa.setup-php.com/ondrej/php/ubuntu/'
          fingerprint = 'B8DC7E53946656EFBCE4C1DD71DAEAAB4AD4CAB6'
          if source.is_symlink() or not source.is_file() or source.stat().st_size > 65536:
              raise SystemExit('Expected regular Noble PHP PPA source is unavailable')
          original = source.read_bytes()
          text = original.decode('utf-8')
          for field, expected in [('Types', 'deb'), ('URIs', primary), ('Suites', 'noble'), ('Components', 'main')]:
              if re.findall(r'^' + field + r': (.*)$', text, re.M) != [expected]:
                  raise SystemExit('Unexpected Noble PHP PPA source field: ' + field)
          if re.search(r'^(?:Trusted|Allow-Insecure|Allow-Weak|Allow-Downgrade-To-Insecure):', text, re.M | re.I):
              raise SystemExit('Unsafe repository trust override refused')
          keys = re.findall(r'^Signed-By:([^\n]*(?:\n[ \t][^\n]*)*)', text, re.M)
          if len(keys) != 1:
              raise SystemExit('Exactly one publisher Signed-By field is required')
          signed_by = '\n'.join('' if line.strip() == '.' else line.strip() for line in keys[0].splitlines()).strip()
          if signed_by.startswith('-----BEGIN PGP PUBLIC KEY BLOCK-----'):
              key = signed_by.encode('ascii')
          else:
              key_path = Path(signed_by)
              if key_path.parent not in (Path('/usr/share/keyrings'), Path('/etc/apt/keyrings')) or key_path.is_symlink() or not key_path.is_file() or key_path.stat().st_size > 65536:
                  raise SystemExit('Unexpected publisher key file')
              key = key_path.read_bytes()
          with tempfile.TemporaryDirectory(prefix='cwp-public-key-inspection-') as key_home:
              shown = subprocess.run(['gpg', '--batch', '--no-options', '--homedir', key_home, '--no-autostart', '--with-colons', '--show-keys'], input=key, capture_output=True, check=True).stdout.decode('utf-8')
          if len(re.findall(r'^pub:', shown, re.M)) != 1 or re.findall(r'^fpr:::::::::([A-F0-9]{40}):', shown, re.M)[:1] != [fingerprint]:
              raise SystemExit('Unexpected PHP PPA signing identity')
          changed = re.sub(r'^URIs: ' + re.escape(primary) + r'$', 'URIs: ' + mirror, text, count=1, flags=re.M).encode('utf-8')
          if changed.replace(mirror.encode(), primary.encode(), 1) != original or changed == original:
              raise SystemExit('Only the exact repository URI may change')
          source.write_bytes(changed)
          print('Noble PHP PPA mirror selected; original publisher Signed-By retained: ' + fingerprint)
          CWP_PHP_MIRROR
'''
PROTECTED_PPA_SUCCESSOR = '            sudo python3 -I .github/scripts/prepare_ubuntu_apt.py prepare-php-ppa\n'
PROTECTED_APT_RELEASES = '          python3 -I .github/scripts/prepare_ubuntu_apt.py verify-releases\n'
PROTECTED_APT_CALL_SUCCESSOR = 'cwp_apt_get -o Acquire::Retries=1'
PROTECTED_APT_CALL_PREDECESSOR = 'sudo apt-get -o Acquire::Retries=5'
PROTECTED_APT_CALL_COUNT = 16
PROTECTED_APT_LIST_CLEANUP = ' -o APT::Get::List-Cleanup=true'
PROTECTED_APT_LIST_CLEANUP_COUNT = 4
IMMUTABLE = '${{ github.event.pull_request.head.sha || github.sha }}'
PARENTS = ('84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd',
           'ebcbbb56336249fee2073c8f02e9d6998740b64e')
DEPENDENCIES = {'site/**', 'foundation/**', 'tests/**', 'tools/**', 'docs/*.json', '.github/scripts/**',
                'VERSION', 'START-HERE.txt', WORKFLOW, LEGACY, PROTECTED}


def digest(value):
    return hashlib.sha256(value.encode()).hexdigest()


def require(passed, message):
    if not passed:
        raise AssertionError(message)


def executable_ubuntu_source_guard_fixtures():
    """Execute the actual embedded guard in root-owned disposable POSIX fixtures."""
    if os.name != 'posix' or shutil.which('sudo') is None:
        return []
    sudo = subprocess.run(['sudo', '-n', 'true'], capture_output=True)
    if sudo.returncode != 0:
        return []
    script_source = (ROOT / APT_PREPARATION_SCRIPT).read_text(encoding='utf-8')
    require(hashlib.sha256(script_source.encode()).hexdigest() == APT_PREPARATION_SHA256,
            'Executable APT preparation script identity changed')
    body = script_source.split('\nCOMMANDS = {', 1)[0]
    expected_mirror = (
        'http://azure.archive.ubuntu.com/ubuntu/\tpriority:1\n'
        'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
        'https://security.ubuntu.com/ubuntu/\tpriority:3\n'
    )
    selected_mirror = (
        'https://archive.ubuntu.com/ubuntu/\tpriority:1\n'
        'https://security.ubuntu.com/ubuntu/\tpriority:2\n'
        'http://azure.archive.ubuntu.com/ubuntu/\tpriority:3\n'
    )
    canonical_source = (
        'Types: deb\nURIs: mirror+file:/etc/apt/apt-mirrors.txt\n'
        'Suites: noble noble-updates noble-backports\n'
        'Components: main universe restricted multiverse\n'
        'Signed-By: /etc/apt/cwp-keyrings/ubuntu-archive-keyring.gpg\n\n'
        'Types: deb\nURIs: mirror+file:/etc/apt/apt-mirrors.txt\n'
        'Suites: noble-security\nComponents: main universe restricted multiverse\n'
        'Signed-By: /etc/apt/cwp-keyrings/ubuntu-archive-keyring.gpg\n'
    )

    def source(archive, security=None):
        security_uri = (('mirror+file:/etc/apt/apt-mirrors.txt' if archive.startswith('mirror+')
                         else 'http://security.ubuntu.com/ubuntu/')
                        if security is None else security)
        return (
            'Types: deb\nURIs: ' + archive + '\n'
            'Suites: noble noble-updates noble-backports\n'
            'Components: main universe restricted multiverse\n'
            'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n\n'
            'Types: deb\nURIs: ' + security_uri + '\n'
            'Suites: noble-security\nComponents: main universe restricted multiverse\n'
            'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n'
        )

    checks = []
    fixture_key = b'fixture Ubuntu archive keyring'
    fixture_key_hash = hashlib.sha256(fixture_key).hexdigest()

    def run_case(name, ubuntu_source, mirror_text, expected_profile=None, extra_source=None,
                 hardlink=False, apt_extra='', keyring_directory_mode=0o777,
                 keyring_mode=0o777):
        with tempfile.TemporaryDirectory(prefix='cwp-ubuntu-apt-guard-') as folder:
            root = Path(folder)
            apt = root / 'etc' / 'apt'
            sources = apt / 'sources.list.d'
            apt_conf = apt / 'apt.conf.d'
            apt_state = root / 'var' / 'lib' / 'apt'
            keyrings = root / 'usr' / 'share' / 'keyrings'
            isolated_keyrings = apt / 'cwp-keyrings'
            qa = root / 'qa'
            sources.mkdir(parents=True)
            apt_conf.mkdir()
            apt_state.mkdir(parents=True)
            keyrings.mkdir(parents=True)
            qa.mkdir()
            mirror = apt / 'apt-mirrors.txt'
            ubuntu = sources / 'ubuntu.sources'
            keyring = keyrings / 'ubuntu-archive-keyring.gpg'
            release = root / 'os-release'
            mirror.write_text(mirror_text, encoding='ascii')
            ubuntu.write_text(ubuntu_source, encoding='ascii')
            keyring.write_bytes(fixture_key)
            release.write_text('ID=ubuntu\nVERSION_ID="24.04"\nVERSION_CODENAME=noble\n', encoding='ascii')
            if extra_source is not None:
                (sources / 'shadow.sources').write_text(extra_source, encoding='ascii')
            if hardlink:
                os.link(ubuntu, sources / 'ubuntu-hardlink.sources')
            script = body
            replacements = {
                "Path('/usr/lib/os-release')": 'Path(' + repr(str(release)) + ')',
                "Path('/etc/apt/apt-mirrors.txt')": 'Path(' + repr(str(mirror)) + ')',
                "Path('/etc/apt/sources.list.d/ubuntu.sources')": 'Path(' + repr(str(ubuntu)) + ')',
                "Path('/usr/share/keyrings/ubuntu-archive-keyring.gpg')": 'Path(' + repr(str(keyring)) + ')',
                "Path('/etc/apt/sources.list')": 'Path(' + repr(str(apt / 'sources.list')) + ')',
                "Path('/etc/apt/sources.list.d')": 'Path(' + repr(str(sources)) + ')',
                "Path('/etc/apt/cwp-sources.list.d')": 'Path(' + repr(str(apt / 'cwp-sources.list.d')) + ')',
                "Path('/etc/apt/cwp-keyrings')": 'Path(' + repr(str(isolated_keyrings)) + ')',
                "Path('/var/lib/apt/cwp-lists')": 'Path(' + repr(str(apt_state / 'cwp-lists')) + ')',
                "Path('/etc/apt/cwp-source-isolation.conf.pending')": 'Path(' + repr(str(apt / 'cwp-source-isolation.conf.pending')) + ')',
                "Path('/etc/apt/apt.conf.d/zz-cwp-source-isolation')": 'Path(' + repr(str(apt_conf / 'zz-cwp-source-isolation')) + ')',
                '80a36b0a6de2f69f49d2df75ef473ccde121e9e190b9ea01d20a4f63778d5c31': fixture_key_hash,
                "keyring, 4096, 'Ubuntu Noble archive keyring', 3607,":
                    "keyring, 4096, 'Ubuntu Noble archive keyring', " + str(len(fixture_key)) + ',',
            }
            for before, after in replacements.items():
                require(before in script, 'Executable guard fixture replacement disappeared: ' + before)
                script = script.replace(before, after)
            fake = textwrap.dedent('''
                class _FixtureResult:
                    returncode = 0
                    stdout = 'status: done\\n'
                class _FixtureSubprocess:
                    @staticmethod
                    def run(args, **kwargs):
                        if args[:2] != ['cloud-init', 'status']:
                            raise AssertionError('unexpected fixture command: ' + repr(args))
                        return _FixtureResult()
                    @staticmethod
                    def check_output(args, **kwargs):
                        if args == ['dpkg', '--print-architecture']:
                            return 'amd64\\n'
                        if args == ['dpkg', '--print-foreign-architectures']:
                            return ''
                        if args == ['apt-config', 'dump']:
                            return ('Dir::Etc::sourcelist "sources.list";\\n'
                                    'Dir::Etc::sourceparts "sources.list.d";\\n' + APT_EXTRA)
                        raise AssertionError('unexpected fixture command: ' + repr(args))
                subprocess = _FixtureSubprocess()
            ''').replace('APT_EXTRA', repr(apt_extra))
            needle = '    import hashlib, json, os, re, secrets, stat, subprocess, sys\n'
            require(script.count(needle) == 1, 'Executable guard import boundary changed')
            script = script.replace(needle, needle + textwrap.indent(fake, '    '), 1)
            script += '\nprepare_ubuntu()\n'
            runner = root / 'guard.py'
            runner.write_text(script, encoding='utf-8')
            try:
                subprocess.run(['sudo', '-n', 'chown', '-R', '0:0', str(root)], check=True)
                subprocess.run(['sudo', '-n', 'chmod', '755', str(root), str(apt), str(sources), str(apt_conf),
                                str(root / 'var'), str(root / 'var' / 'lib'), str(apt_state), str(qa)], check=True)
                subprocess.run(['sudo', '-n', 'chmod', format(keyring_directory_mode, 'o'), str(keyrings)],
                               check=True)
                for path in (mirror, ubuntu, release, runner):
                    subprocess.run(['sudo', '-n', 'chmod', '644', str(path)], check=True)
                subprocess.run(['sudo', '-n', 'chmod', format(keyring_mode, 'o'), str(keyring)], check=True)
                result = subprocess.run(['sudo', '-n', 'python3', str(runner)], cwd=root,
                                        capture_output=True, text=True)
                if expected_profile is None:
                    require(result.returncode != 0, name + ' unexpectedly passed')
                    receipt = json.loads((qa / 'ubuntu-apt-source-preflight.json').read_text())
                    require(receipt['passed'] is False and receipt.get('failure'),
                            name + ' did not retain a bounded failure receipt')
                else:
                    require(result.returncode == 0, name + ' failed: ' + result.stderr)
                    receipt = json.loads((qa / 'ubuntu-apt-source.json').read_text())
                    require(receipt['passed'] is True and receipt['profile'] == expected_profile,
                            name + ' retained the wrong profile')
                    require(receipt['keyring_input_metadata']['directory_mode']
                            == format(keyring_directory_mode, '04o')
                            and receipt['keyring_input_metadata']['file_mode']
                            == format(keyring_mode, '04o'),
                            name + ' retained the wrong keyring input metadata')
                    require(mirror.read_text(encoding='ascii') == selected_mirror,
                            name + ' did not retain the selected mirror list')
                    require(ubuntu.read_text(encoding='ascii') == canonical_source,
                            name + ' did not retain the canonical source')
                    require((apt / 'cwp-sources.list.d' / 'ubuntu.sources').read_text(encoding='ascii') == canonical_source,
                            name + ' did not retain the isolated Ubuntu source')
                    require((isolated_keyrings / 'ubuntu-archive-keyring.gpg').read_bytes() == fixture_key,
                            name + ' did not retain the verified isolated Ubuntu keyring')
                    require((apt / 'cwp-source-isolation.conf.pending').is_file(),
                            name + ' did not retain the pending isolation config')
            finally:
                subprocess.run(['sudo', '-n', 'chown', '-R', str(os.getuid()) + ':' + str(os.getgid()), str(root)],
                               check=False, capture_output=True)
        checks.append({'name': name, 'passed': True})

    run_case('Executable guard accepts exact .370 mirror profile', source('mirror+file:/etc/apt/apt-mirrors.txt'),
             expected_mirror, 'github-mirror-list')
    run_case('Executable guard accepts protected conventional keyring metadata',
             source('mirror+file:/etc/apt/apt-mirrors.txt'), expected_mirror, 'github-mirror-list',
             keyring_directory_mode=0o755, keyring_mode=0o644)
    for region in ('us-east-1', 'us-west-2'):
        run_case('Executable guard accepts bounded AWS direct profile ' + region,
                 source('http://' + region + '.ec2.archive.ubuntu.com/ubuntu/'),
                 'http://' + region + '.ec2.archive.ubuntu.com/ubuntu/\tpriority:1\n'
                 'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
                 'https://security.ubuntu.com/ubuntu/\tpriority:3\n', 'aws-direct')
    run_case('Executable guard accepts exact .127 AWS profile without terminal source slashes',
             source('http://us-east-2.ec2.archive.ubuntu.com/ubuntu',
                    'http://security.ubuntu.com/ubuntu'),
             'http://us-east-2.ec2.archive.ubuntu.com/ubuntu/\tpriority:1\n'
             'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
             'https://security.ubuntu.com/ubuntu/\tpriority:3\n', 'aws-direct')
    run_case('Executable guard accepts exact .127 AWS mirror-list profile',
             source('mirror+file:/etc/apt/apt-mirrors.txt',
                    'http://security.ubuntu.com/ubuntu'),
             'http://us-east-2.ec2.archive.ubuntu.com/ubuntu/\tpriority:1\n'
             'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
             'https://security.ubuntu.com/ubuntu/\tpriority:3\n', 'aws-mirror-list')
    run_case('Executable guard accepts exact fully converted AWS mirror-list profile',
             source('mirror+file:/etc/apt/apt-mirrors.txt',
                    'mirror+file:/etc/apt/apt-mirrors.txt'),
             'http://us-east-2.ec2.archive.ubuntu.com/ubuntu/\tpriority:1\n'
             'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
             'https://security.ubuntu.com/ubuntu/\tpriority:3\n', 'aws-mirror-list')
    run_case('Executable guard rejects an unknown AWS mirror-list archive host',
             source('mirror+file:/etc/apt/apt-mirrors.txt',
                    'http://security.ubuntu.com/ubuntu'),
             'http://eu-west-1.ec2.archive.ubuntu.com/ubuntu/\tpriority:1\n'
             'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
             'https://security.ubuntu.com/ubuntu/\tpriority:3\n')
    run_case('Executable guard rejects an unknown fully converted AWS mirror-list archive host',
             source('mirror+file:/etc/apt/apt-mirrors.txt',
                    'mirror+file:/etc/apt/apt-mirrors.txt'),
             'http://eu-west-1.ec2.archive.ubuntu.com/ubuntu/\tpriority:1\n'
             'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
             'https://security.ubuntu.com/ubuntu/\tpriority:3\n')
    run_case('Executable guard rejects an unknown AWS archive host',
             source('http://eu-west-1.ec2.archive.ubuntu.com/ubuntu/'),
             'http://eu-west-1.ec2.archive.ubuntu.com/ubuntu/\n')
    run_case('Executable guard rejects another active mirror-list reference',
             source('http://us-east-1.ec2.archive.ubuntu.com/ubuntu/'),
             'http://us-east-1.ec2.archive.ubuntu.com/ubuntu/\n',
             extra_source='Types: deb\nURIs: mirror+file:/etc/apt/apt-mirrors.txt\nSuites: noble\n')
    run_case('Executable guard rejects a hard-linked Ubuntu source',
             source('mirror+file:/etc/apt/apt-mirrors.txt'), expected_mirror, hardlink=True)
    run_case('Executable guard rejects an insecure effective APT option',
             source('mirror+file:/etc/apt/apt-mirrors.txt'), expected_mirror,
             apt_extra='APT::Get::AllowUnauthenticated "yes";\n')
    run_case('Executable guard rejects unreviewed keyring directory metadata',
             source('mirror+file:/etc/apt/apt-mirrors.txt'), expected_mirror,
             keyring_directory_mode=0o775)
    run_case('Executable guard rejects unreviewed keyring file metadata',
             source('mirror+file:/etc/apt/apt-mirrors.txt'), expected_mirror,
             keyring_mode=0o666)
    return checks


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


def validate(workflow, legacy, protected, apt_script):
    checks = []

    def check(name, passed):
        require(passed, name)
        checks.append({'name': name, 'passed': True})

    check('Legacy source builder and focused workflow remain byte-identical', digest(legacy) == LEGACY_SHA256)
    script_directory = ROOT / '.github' / 'scripts'
    script_entries = sorted(path.relative_to(ROOT).as_posix() for path in script_directory.iterdir()
                            if path.is_file() or path.is_symlink())
    check('APT preparation directory has exactly one ordinary top-level source',
          script_entries == [APT_PREPARATION_SCRIPT]
          and (ROOT / APT_PREPARATION_SCRIPT).is_file()
          and not (ROOT / APT_PREPARATION_SCRIPT).is_symlink())
    indexed = subprocess.check_output(
        ['git', 'ls-files', '-s', '--', APT_PREPARATION_SCRIPT], cwd=ROOT, text=True).strip().split()
    check('APT preparation script retains ordinary Git mode', len(indexed) >= 4 and indexed[0] == '100644')
    check('Reviewed APT preparation script remains byte-identical',
          digest(apt_script) == APT_PREPARATION_SHA256)
    protected_predecessor = protected
    check('Protected application build has one bounded timeout successor',
          protected_predecessor.count(PROTECTED_BUILD_TIMEOUT_SUCCESSOR) == 1
          and PROTECTED_BUILD_TIMEOUT_PREDECESSOR not in protected_predecessor)
    protected_predecessor = protected_predecessor.replace(
        PROTECTED_BUILD_TIMEOUT_SUCCESSOR, PROTECTED_BUILD_TIMEOUT_PREDECESSOR, 1)
    for addition in reversed(PROTECTED_PROJECT_NOTE_ADDITIONS):
        check('Reviewed private project note application command is present exactly once',
              protected_predecessor.count(addition) == 1)
        protected_predecessor = protected_predecessor.replace(addition, '', 1)
    for after, additions in PROTECTED_PROJECT_NOTE_BLOCKS:
        check('Reviewed private project note application commands are additive and ordered',
              protected.count(after + ''.join(additions)) == 1)
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
    check('Protected application workflow triggers on the reviewed APT script',
          protected_predecessor.count(PROTECTED_APT_TRIGGER_ADDITION)
          == PROTECTED_APT_TRIGGER_ADDITION_COUNT)
    protected_predecessor = protected_predecessor.replace(PROTECTED_APT_TRIGGER_ADDITION, '')
    check('Protected application workflow has every reviewed canonical mirror selection',
          protected_predecessor.count(PROTECTED_APT_MIRRORS) == PROTECTED_APT_MIRRORS_COUNT)
    protected_predecessor = protected_predecessor.replace(PROTECTED_APT_MIRRORS, '')
    check('Protected application workflow has every reviewed bounded reconnect wrapper',
          protected_predecessor.count(PROTECTED_APT_WRAPPER) == PROTECTED_APT_WRAPPER_COUNT)
    protected_predecessor = protected_predecessor.replace(PROTECTED_APT_WRAPPER, '')
    check('Protected application workflow isolates every reviewed PPA source',
          protected_predecessor.count(PROTECTED_PPA_SUCCESSOR) == PROTECTED_APT_ISOLATION_COUNT
          and protected_predecessor.count(PROTECTED_APT_ISOLATION_ACTIVATION) == PROTECTED_APT_ISOLATION_COUNT
          and protected_predecessor.count(PROTECTED_APT_RELEASES) == PROTECTED_APT_ISOLATION_COUNT)
    protected_predecessor = protected_predecessor.replace(PROTECTED_PPA_SUCCESSOR, PROTECTED_PPA_PREDECESSOR)
    protected_predecessor = protected_predecessor.replace(PROTECTED_APT_ISOLATION_ACTIVATION, '')
    protected_predecessor = protected_predecessor.replace(PROTECTED_APT_RELEASES, '')
    check('Protected application workflow cleans every isolated signed index set',
          protected_predecessor.count(PROTECTED_APT_LIST_CLEANUP) == PROTECTED_APT_LIST_CLEANUP_COUNT)
    protected_predecessor = protected_predecessor.replace(PROTECTED_APT_LIST_CLEANUP, '')
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
    apt_script = (root / APT_PREPARATION_SCRIPT).read_text()
    checks = validate(workflow, legacy, protected, apt_script)
    checks.extend(executable_ubuntu_source_guard_fixtures())
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
            validate(changed, legacy, protected, apt_script)
        except AssertionError:
            checks.append({'name': 'Regression rejected: ' + name, 'passed': True})
        else:
            raise AssertionError('Regression accepted: ' + name)
    for name, old, full in (('legacy source-builder drift', legacy + '\n', protected),
                            ('protected acceptance drift', legacy, protected + '\n')):
        try:
            validate(workflow, old, full, apt_script)
        except AssertionError:
            checks.append({'name': 'Regression rejected: ' + name, 'passed': True})
        else:
            raise AssertionError('Regression accepted: ' + name)
    try:
        validate(workflow, legacy, protected, apt_script + '\n')
    except AssertionError:
        checks.append({'name': 'Regression rejected: APT preparation script drift', 'passed': True})
    else:
        raise AssertionError('Regression accepted: APT preparation script drift')
    for name, changed in (
        ('lost private project note command', protected.replace(PROTECTED_PROJECT_NOTE_ADDITIONS[0], '', 1)),
        ('changed private project note native command', protected.replace(
            'tests/project_private_notes_native.php', 'tests/project_private_notes_policy.php', 1)),
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
        ('lost bounded application build timeout', protected.replace(
            PROTECTED_BUILD_TIMEOUT_SUCCESSOR, PROTECTED_BUILD_TIMEOUT_PREDECESSOR, 1)),
        ('lost canonical application mirror selection', protected.replace(PROTECTED_APT_MIRRORS, '', 1)),
        ('lost APT preparation trigger', protected.replace(PROTECTED_APT_TRIGGER_ADDITION, '', 1)),
        ('lost bounded application reconnect wrapper', protected.replace(PROTECTED_APT_WRAPPER, '', 1)),
        ('lost isolated PPA source command', protected.replace(PROTECTED_PPA_SUCCESSOR, '', 1)),
        ('lost package-source isolation activation', protected.replace(PROTECTED_APT_ISOLATION_ACTIVATION, '', 1)),
        ('lost signed release identity gate', protected.replace(PROTECTED_APT_RELEASES, '', 1)),
        ('lost isolated package-index cleanup', protected.replace(PROTECTED_APT_LIST_CLEANUP, '', 1)),
        ('lost bounded application mirror retry', protected.replace(
            PROTECTED_APT_CALL_SUCCESSOR, PROTECTED_APT_CALL_PREDECESSOR, 1)),
        ('extra bounded application mirror retry', protected + PROTECTED_APT_CALL_SUCCESSOR + '\n'),
        ('extra protected workflow command', protected.replace(
            PROTECTED_ADDITIONS[1], PROTECTED_ADDITIONS[1] + '          true # unreviewed command\n', 1)),
    ):
        try:
            validate(workflow, legacy, changed, apt_script)
        except AssertionError:
            checks.append({'name': 'Regression rejected: ' + name, 'passed': True})
        else:
            raise AssertionError('Regression accepted: ' + name)
    return {'passed': True, 'scope': __doc__.splitlines()[0] + ' Not hosted or installed acceptance.',
            'workflow_sha256': digest(workflow), 'legacy_sha256': digest(legacy), 'checks': checks}


if __name__ == '__main__':
    print(json.dumps(self_test(), indent=2))
