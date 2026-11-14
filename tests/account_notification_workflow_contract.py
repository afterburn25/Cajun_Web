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
          import hashlib, json, os, re, secrets, stat, subprocess, sys

          diagnostic = {'schema': 1, 'passed': False}
          def retain_diagnostic(reason):
              diagnostic['failure'] = reason
              evidence = Path.cwd() / 'qa' / 'ubuntu-apt-source-preflight.json'
              evidence.parent.mkdir(parents=True, exist_ok=True)
              evidence.write_text(json.dumps(diagnostic, indent=2) + '\n', encoding='utf-8')

          def fail(reason):
              try:
                  retain_diagnostic(reason)
              except Exception:
                  pass
              raise SystemExit('Ubuntu APT source preparation: ' + reason)

          def retain_unhandled(kind, value, traceback):
              try:
                  retain_diagnostic('unhandled setup exception: ' + kind.__name__)
              finally:
                  sys.__excepthook__(kind, value, traceback)

          sys.excepthook = retain_unhandled

          def fingerprint(value):
              return (value.st_dev, value.st_ino, value.st_mode, value.st_uid, value.st_gid,
                      value.st_size, value.st_mtime_ns, value.st_ctime_ns)

          def directory_identity(value):
              return (value.st_dev, value.st_ino, stat.S_IFMT(value.st_mode), stat.S_IMODE(value.st_mode),
                      value.st_uid, value.st_gid)

          def checked_read(path, limit, label):
              parent = path.parent.lstat()
              if not stat.S_ISDIR(parent.st_mode) or parent.st_uid != 0 or parent.st_gid != 0 or parent.st_mode & 0o022:
                  fail('unsafe ' + label + ' directory')
              before = path.lstat()
              if (not stat.S_ISREG(before.st_mode) or before.st_nlink != 1
                      or before.st_uid != 0 or before.st_gid != 0
                      or before.st_mode & 0o022 or not 0 < before.st_size <= limit):
                  fail('unsafe ' + label + ' metadata')
              with os.fdopen(os.open(path, os.O_RDONLY | os.O_NOFOLLOW), 'rb') as stream:
                  if fingerprint(os.fstat(stream.fileno())) != fingerprint(before):
                      fail(label + ' changed while opening')
                  data = stream.read(limit + 1)
                  if len(data) != before.st_size or fingerprint(os.fstat(stream.fileno())) != fingerprint(before):
                      fail(label + ' changed while reading')
              return data, before, parent

          def replace_checked(path, original, before, parent, changed, label):
              temporary = '.' + path.name + '-' + secrets.token_hex(16)
              try:
                  directory_fd = os.open(path.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
              except OSError:
                  fail(label + ' directory could not be opened safely')
              try:
                  if directory_identity(os.fstat(directory_fd)) != directory_identity(parent):
                      fail(label + ' directory changed while opening')
                  descriptor = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW,
                                       0o600, dir_fd=directory_fd)
                  with os.fdopen(descriptor, 'wb') as stream:
                      os.fchown(stream.fileno(), before.st_uid, before.st_gid)
                      os.fchmod(stream.fileno(), stat.S_IMODE(before.st_mode))
                      stream.write(changed)
                      stream.flush()
                      os.fsync(stream.fileno())
                  current_fd = os.open(path.name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=directory_fd)
                  with os.fdopen(current_fd, 'rb') as current_stream:
                      metadata = os.fstat(current_stream.fileno())
                      current = current_stream.read(max(len(original), len(changed), 4096) + 1)
                      final_metadata = os.fstat(current_stream.fileno())
                  if (current != original or fingerprint(metadata) != fingerprint(before)
                          or fingerprint(final_metadata) != fingerprint(before)
                          or directory_identity(os.fstat(directory_fd)) != directory_identity(parent)):
                      fail(label + ' changed before replacement')
                  os.replace(temporary, path.name, src_dir_fd=directory_fd, dst_dir_fd=directory_fd)
                  temporary = None
                  secured_parent = os.fstat(directory_fd)
                  if (not stat.S_ISDIR(secured_parent.st_mode) or secured_parent.st_uid != 0
                          or secured_parent.st_gid != 0 or secured_parent.st_mode & 0o022):
                      fail(label + ' directory changed during replacement')
                  os.fsync(directory_fd)
                  final_fd = os.open(path.name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=directory_fd)
                  with os.fdopen(final_fd, 'rb') as final_stream:
                      final = os.fstat(final_stream.fileno())
                      actual = final_stream.read(max(len(changed), 4096) + 1)
                  if (actual != changed or final.st_uid != before.st_uid or final.st_gid != before.st_gid
                          or stat.S_IMODE(final.st_mode) != stat.S_IMODE(before.st_mode)):
                      fail(label + ' replacement was not retained')
              finally:
                  if temporary is not None:
                      try:
                          os.unlink(temporary, dir_fd=directory_fd)
                      except FileNotFoundError:
                          pass
                  os.close(directory_fd)

          def write_new(path, data, label):
              parent = path.parent.lstat()
              if (not stat.S_ISDIR(parent.st_mode) or parent.st_uid != 0 or parent.st_gid != 0
                      or parent.st_mode & 0o022 or os.path.lexists(path)):
                  fail('unsafe new ' + label + ' path')
              directory_fd = os.open(path.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
              try:
                  if directory_identity(os.fstat(directory_fd)) != directory_identity(parent):
                      fail(label + ' directory changed while opening')
                  descriptor = os.open(path.name, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW,
                                       0o644, dir_fd=directory_fd)
                  with os.fdopen(descriptor, 'wb') as stream:
                      os.fchown(stream.fileno(), 0, 0)
                      os.fchmod(stream.fileno(), 0o644)
                      stream.write(data)
                      stream.flush()
                      os.fsync(stream.fileno())
                  os.fsync(directory_fd)
                  final_fd = os.open(path.name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=directory_fd)
                  with os.fdopen(final_fd, 'rb') as final_stream:
                      metadata = os.fstat(final_stream.fileno())
                      actual = final_stream.read(max(len(data), 4096) + 1)
                  if (actual != data or metadata.st_nlink != 1
                          or (metadata.st_uid, metadata.st_gid, stat.S_IMODE(metadata.st_mode)) != (0, 0, 0o644)):
                      fail(label + ' creation was not retained')
              finally:
                  os.close(directory_fd)

          cloud_init = subprocess.run(
              ['cloud-init', 'status', '--wait', '--long'], text=True, capture_output=True, timeout=120)
          if cloud_init.returncode != 0 or not re.search(r'^status: (?:done|disabled)$', cloud_init.stdout, re.M):
              fail('cloud-init did not reach a successful terminal state')

          platform = dict(line.split('=', 1) for line in Path('/usr/lib/os-release').read_text().splitlines()
                          if line and not line.startswith('#') and '=' in line)
          if any(platform.get(key, '').strip('"') != value for key, value in
                 {'ID': 'ubuntu', 'VERSION_ID': '24.04', 'VERSION_CODENAME': 'noble'}.items()):
              fail('expected Ubuntu 24.04 noble')
          if (subprocess.check_output(['dpkg', '--print-architecture'], text=True).strip() != 'amd64'
                  or subprocess.check_output(['dpkg', '--print-foreign-architectures'], text=True).strip()):
              fail('expected native amd64 without foreign architectures')

          mirror = Path('/etc/apt/apt-mirrors.txt')
          ubuntu = Path('/etc/apt/sources.list.d/ubuntu.sources')
          keyring = Path('/usr/share/keyrings/ubuntu-archive-keyring.gpg')
          mirror_data, mirror_metadata, mirror_parent = checked_read(mirror, 4096, 'Ubuntu mirror list')
          source_data, source_metadata, source_parent = checked_read(ubuntu, 16384, 'Ubuntu source')
          key_data, _, _ = checked_read(keyring, 4096, 'Ubuntu archive keyring')
          diagnostic.update({
              'source_bytes': len(source_data),
              'source_sha256': hashlib.sha256(source_data).hexdigest(),
              'mirror_bytes': len(mirror_data),
              'mirror_sha256': hashlib.sha256(mirror_data).hexdigest(),
              'keyring_bytes': len(key_data),
              'keyring_sha256': hashlib.sha256(key_data).hexdigest(),
          })
          if (len(key_data) != 3607
                  or hashlib.sha256(key_data).hexdigest() != '80a36b0a6de2f69f49d2df75ef473ccde121e9e190b9ea01d20a4f63778d5c31'):
              fail('unexpected Ubuntu Noble archive keyring')
          if any(byte > 126 or byte < 32 and byte not in (9, 10) for byte in source_data):
              fail('Ubuntu source is not flat ASCII')
          try:
              mirror_text = mirror_data.decode('ascii')
          except UnicodeDecodeError:
              fail('Ubuntu mirror list is not ASCII')
          mirror_lines = mirror_text.splitlines()
          if (not mirror_lines or any(not re.fullmatch(
                  r'https?://[A-Za-z0-9.-]+/ubuntu/?(?:\tpriority:[1-9][0-9]*)?', line)
                  for line in mirror_lines)):
              fail('Ubuntu mirror list contains an unsafe or malformed entry')
          diagnostic['mirror_lines'] = mirror_lines

          apt_config = subprocess.check_output(['apt-config', 'dump'], text=True)
          def apt_setting(name):
              matches = re.findall(r'^' + re.escape(name) + r' "([^"]*)";$', apt_config, re.M)
              if len(matches) != 1:
                  fail('unexpected effective APT setting: ' + name)
              return matches[0]
          if apt_setting('Dir::Etc::sourcelist') != 'sources.list' or apt_setting('Dir::Etc::sourceparts') != 'sources.list.d':
              fail('nonstandard effective APT source paths')
          unsafe_settings = {
              'APT::Get::AllowUnauthenticated': {'true', 'yes', '1'},
              'Acquire::AllowInsecureRepositories': {'true', 'yes', '1'},
              'Acquire::AllowWeakRepositories': {'true', 'yes', '1'},
              'Acquire::AllowDowngradeToInsecureRepositories': {'true', 'yes', '1'},
              'Acquire::Check-Valid-Until': {'false', 'no', '0'},
              'Acquire::https::Verify-Peer': {'false', 'no', '0'},
              'Acquire::https::Verify-Host': {'false', 'no', '0'},
              'Acquire::By-Hash': {'false', 'no', '0'},
          }
          for name, unsafe_values in unsafe_settings.items():
              values = re.findall(r'^' + re.escape(name) + r' "([^"]*)";$', apt_config, re.M | re.I)
              if any(value.lower() in unsafe_values for value in values):
                  fail('unsafe effective APT setting: ' + name)
          if re.search(r'^Acquire::https::[^:\s"]+::Verify-(?:Peer|Host) "(?:false|no|0)";$', apt_config, re.M | re.I):
              fail('unsafe host-scoped HTTPS verification setting')

          source_paths = []
          legacy = Path('/etc/apt/sources.list')
          if legacy.exists() or legacy.is_symlink():
              source_paths.append(legacy)
          source_paths.extend(sorted(Path('/etc/apt/sources.list.d').glob('*.list')))
          source_paths.extend(sorted(Path('/etc/apt/sources.list.d').glob('*.sources')))
          active_sources = {}
          for path in source_paths:
              data, _, _ = checked_read(path, 1048576, 'APT source ' + str(path))
              if (re.search(
                      rb'(?:^|[\s\[])(?:Trusted|Allow-Insecure|Allow-Weak|Allow-Downgrade-To-Insecure)\s*(?::|=)\s*(?:yes|true|1)',
                      data, re.M | re.I)
                      or re.search(rb'(?:^|[\s\[])Check-Valid-Until\s*(?::|=)\s*(?:no|false|0)',
                                   data, re.M | re.I)):
                  fail('unsafe trust or expiry override in active APT source: ' + str(path))
              active_sources[path] = data
          references = [path for path, data in active_sources.items() if b'apt-mirrors.txt' in data]

          allowed = {'types', 'uris', 'suites', 'components', 'signed-by', 'architectures', 'enabled'}
          required = {'types', 'uris', 'suites', 'components', 'signed-by'}
          archive_suites = {'noble', 'noble-updates', 'noble-backports'}
          security_suites = {'noble-security'}
          aws_archives = {
              'http://us-east-1.ec2.archive.ubuntu.com/ubuntu/',
              'http://us-west-2.ec2.archive.ubuntu.com/ubuntu/',
          }
          seen_suites = set()
          records = []
          for section in re.split(r'\n[ \t]*\n', source_data.decode('ascii').strip('\n')):
              fields = {}
              for line in section.splitlines():
                  if line.startswith('#'):
                      continue
                  name, separator, value = line.partition(':')
                  name = name.lower()
                  if not separator or name not in allowed or name in fields or not value.strip():
                      fail('unsupported, empty or duplicate Ubuntu source field')
                  fields[name] = value.strip()
              if not fields:
                  continue
              if not required <= fields.keys() or fields['types'] != 'deb':
                  fail('incomplete or unsupported Ubuntu source stanza')
              if (fields['components'] != 'main universe restricted multiverse'
                      or fields['signed-by'] != '/usr/share/keyrings/ubuntu-archive-keyring.gpg'
                      or fields.get('architectures', 'amd64') != 'amd64'
                      or fields.get('enabled', 'yes') != 'yes'):
                  fail('unsupported Ubuntu source trust, component or architecture')
              suites = fields['suites'].split()
              if len(suites) != len(set(suites)) or not set(suites) <= archive_suites | security_suites:
                  fail('unsupported or repeated Ubuntu suite')
              if seen_suites & set(suites):
                  fail('Ubuntu suite repeated across source stanzas')
              seen_suites.update(suites)
              uri = fields['uris']
              if not (uri == 'mirror+file:/etc/apt/apt-mirrors.txt'
                      or re.fullmatch(r'https?://[A-Za-z0-9.-]+/ubuntu/', uri)):
                  fail('unsafe Ubuntu source URI syntax')
              records.append((tuple(suites), uri))
          diagnostic['source_records'] = [
              {'suites': list(suites), 'uri': uri} for suites, uri in records
          ]
          archive_record = ('noble', 'noble-updates', 'noble-backports')
          security_record = ('noble-security',)
          mirror_records = {
              (archive_record, 'mirror+file:/etc/apt/apt-mirrors.txt'),
              (security_record, 'mirror+file:/etc/apt/apt-mirrors.txt'),
          }
          aws_profiles = [{
              (archive_record, uri),
              (security_record, 'http://security.ubuntu.com/ubuntu/'),
          } for uri in aws_archives]
          record_set = set(records)
          if len(records) != 2 or len(record_set) != 2 or seen_suites != archive_suites | security_suites:
              fail('Ubuntu source must contain the exact archive and security stanzas')
          if record_set == mirror_records:
              profile = 'github-mirror-list'
              if references != [ubuntu]:
                  fail('unexpected active APT mirror-list reference')
          elif any(record_set == candidate for candidate in aws_profiles):
              profile = 'aws-direct'
              if references:
                  fail('direct AWS source profile still references the mirror list')
          else:
              fail('unknown or untrusted Ubuntu source URI or suite grouping')

          expected = (
              b'http://azure.archive.ubuntu.com/ubuntu/\tpriority:1\n'
              b'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
              b'https://security.ubuntu.com/ubuntu/\tpriority:3\n'
          )
          if profile == 'github-mirror-list' and mirror_data != expected:
              fail('unknown or untrusted active Ubuntu mirror list')
          diagnostic['profile'] = profile
          selected = (
              b'https://archive.ubuntu.com/ubuntu/\tpriority:1\n'
              b'https://security.ubuntu.com/ubuntu/\tpriority:2\n'
              b'http://azure.archive.ubuntu.com/ubuntu/\tpriority:3\n'
          )
          canonical = (
              b'Types: deb\nURIs: mirror+file:/etc/apt/apt-mirrors.txt\n'
              b'Suites: noble noble-updates noble-backports\n'
              b'Components: main universe restricted multiverse\n'
              b'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n\n'
              b'Types: deb\nURIs: mirror+file:/etc/apt/apt-mirrors.txt\n'
              b'Suites: noble-security\nComponents: main universe restricted multiverse\n'
              b'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n'
          )
          replace_checked(mirror, mirror_data, mirror_metadata, mirror_parent, selected, 'Ubuntu mirror list')
          replace_checked(ubuntu, source_data, source_metadata, source_parent, canonical, 'Ubuntu source')
          isolated = Path('/etc/apt/cwp-sources.list.d')
          lists = Path('/var/lib/apt/cwp-lists')
          pending_config = Path('/etc/apt/cwp-source-isolation.conf.pending')
          active_config = Path('/etc/apt/apt.conf.d/zz-cwp-source-isolation')
          if any(os.path.lexists(path) for path in (isolated, lists, pending_config, active_config)):
              fail('APT source isolation path already exists')
          config_parent = active_config.parent.lstat()
          if (not stat.S_ISDIR(config_parent.st_mode) or config_parent.st_uid != 0 or config_parent.st_gid != 0
                  or config_parent.st_mode & 0o022):
              fail('unsafe APT configuration directory')
          apt_parent = isolated.parent.lstat()
          apt_fd = os.open(isolated.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
          try:
              if directory_identity(os.fstat(apt_fd)) != directory_identity(apt_parent):
                  fail('APT directory changed before isolation setup')
              os.mkdir(isolated, 0o755)
              os.chown(isolated, 0, 0)
              os.chmod(isolated, 0o755)
              os.fsync(apt_fd)
          finally:
              os.close(apt_fd)
          isolated_metadata = isolated.lstat()
          if (not stat.S_ISDIR(isolated_metadata.st_mode) or isolated_metadata.st_nlink < 2
                  or (isolated_metadata.st_uid, isolated_metadata.st_gid, stat.S_IMODE(isolated_metadata.st_mode))
                  != (0, 0, 0o755)):
              fail('APT source isolation directory was not retained')
          write_new(isolated / 'ubuntu.sources', canonical, 'isolated Ubuntu source')
          lists_parent = lists.parent.lstat()
          lists_parent_fd = os.open(lists.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
          try:
              if (not stat.S_ISDIR(lists_parent.st_mode) or lists_parent.st_uid != 0
                      or lists_parent.st_gid != 0 or lists_parent.st_mode & 0o022
                      or directory_identity(os.fstat(lists_parent_fd)) != directory_identity(lists_parent)):
                  fail('unsafe APT lists parent directory')
              os.mkdir(lists.name, 0o755, dir_fd=lists_parent_fd)
              lists_fd = os.open(lists.name, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,
                                 dir_fd=lists_parent_fd)
              try:
                  os.fchown(lists_fd, 0, 0)
                  os.fchmod(lists_fd, 0o755)
                  os.fsync(lists_fd)
                  retained_lists = os.fstat(lists_fd)
                  if (directory_identity(retained_lists)[0:2] == (0, 0)
                          or (retained_lists.st_uid, retained_lists.st_gid,
                              stat.S_IMODE(retained_lists.st_mode)) != (0, 0, 0o755)
                          or os.listdir(lists_fd)):
                      fail('APT lists isolation directory was not retained empty')
              finally:
                  os.close(lists_fd)
              os.fsync(lists_parent_fd)
          finally:
              os.close(lists_parent_fd)
          isolation_config = (
              b'Dir::Etc::sourcelist "/dev/null";\n'
              b'Dir::Etc::sourceparts "/etc/apt/cwp-sources.list.d";\n'
              b'Dir::State::lists "/var/lib/apt/cwp-lists";\n'
          )
          write_new(pending_config, isolation_config, 'pending APT isolation config')
          cloud_init_after = subprocess.run(
              ['cloud-init', 'status', '--long'], text=True, capture_output=True, timeout=15)
          if cloud_init_after.returncode != 0 or not re.search(r'^status: (?:done|disabled)$', cloud_init_after.stdout, re.M):
              fail('cloud-init state changed after APT source replacement')
          receipt = {
              **diagnostic,
              'passed': True,
              'profile': profile,
              'source_sha256': hashlib.sha256(source_data).hexdigest(),
              'mirror_sha256': hashlib.sha256(mirror_data).hexdigest(),
              'mirror_lines': mirror_lines,
              'keyring_sha256': hashlib.sha256(key_data).hexdigest(),
              'canonical_source_sha256': hashlib.sha256(canonical).hexdigest(),
              'selected_mirror_sha256': hashlib.sha256(selected).hexdigest(),
              'isolated_source_sha256': hashlib.sha256(canonical).hexdigest(),
              'isolated_lists': '/var/lib/apt/cwp-lists',
              'pending_isolation_config_sha256': hashlib.sha256(isolation_config).hexdigest(),
              'cloud_init': 'done-or-disabled',
              'scope': 'Bounded source normalization only; package assertions and acceptance remain separate.',
          }
          evidence = Path.cwd() / 'qa' / 'ubuntu-apt-source.json'
          evidence.write_text(json.dumps(receipt, indent=2) + '\n', encoding='utf-8')
          diagnostic['passed'] = True
          diagnostic.pop('failure', None)
          preflight = Path.cwd() / 'qa' / 'ubuntu-apt-source-preflight.json'
          preflight.write_text(json.dumps(diagnostic, indent=2) + '\n', encoding='utf-8')
          print('Validated signed Ubuntu Noble sources and selected canonical archives before Azure fallback')
          CWP_UBUNTU_MIRRORS
'''
PROTECTED_APT_MIRRORS_COUNT = 4
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
          python3 - <<'CWP_APT_EFFECTIVE'
          import re, subprocess
          configuration = subprocess.check_output(['apt-config', 'dump'], text=True)
          for name, expected in (
              ('Dir::Etc::sourcelist', '/dev/null'),
              ('Dir::Etc::sourceparts', '/etc/apt/cwp-sources.list.d'),
              ('Dir::State::lists', '/var/lib/apt/cwp-lists'),
          ):
              if re.findall(r'^' + re.escape(name) + r' "([^"]*)";$', configuration, re.M) != [expected]:
                  raise SystemExit('Effective APT source isolation was overridden: ' + name)
          CWP_APT_EFFECTIVE
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
PROTECTED_PPA_SUCCESSOR = r'''            sudo python3 - <<'CWP_PHP_MIRROR'
          from pathlib import Path
          import os, re, stat, subprocess, tempfile
          source = Path('/etc/apt/sources.list.d/ondrej-ubuntu-php-noble.sources')
          primary = 'https://ppa.launchpadcontent.net/ondrej/php/ubuntu/'
          mirror = 'https://ppa.setup-php.com/ondrej/php/ubuntu/'
          fingerprint = 'B8DC7E53946656EFBCE4C1DD71DAEAAB4AD4CAB6'
          source_parent = os.open(source.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
          source_descriptor = os.open(source.name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=source_parent)
          source_metadata = os.fstat(source_descriptor)
          if (not stat.S_ISREG(source_metadata.st_mode) or source_metadata.st_nlink != 1
                  or source_metadata.st_uid != 0 or source_metadata.st_gid != 0
                  or source_metadata.st_mode & 0o022 or not 0 < source_metadata.st_size <= 65536):
              raise SystemExit('Expected regular Noble PHP PPA source is unavailable')
          with os.fdopen(source_descriptor, 'rb') as source_stream:
              original = source_stream.read(65537)
              source_final = os.fstat(source_stream.fileno())
          os.close(source_parent)
          if (source_final.st_dev, source_final.st_ino, source_final.st_size) != (
                  source_metadata.st_dev, source_metadata.st_ino, source_metadata.st_size):
              raise SystemExit('Noble PHP PPA source changed while reading')
          text = original.decode('utf-8')
          headers = re.findall(r'^([A-Za-z][A-Za-z0-9-]*):', text, re.M)
          if sorted(headers) != sorted(['Types', 'URIs', 'Suites', 'Components', 'Signed-By']):
              raise SystemExit('Unexpected or duplicate Noble PHP PPA source field')
          continuation_owner = None
          for line in text.splitlines():
              if not line:
                  raise SystemExit('Multiple Noble PHP PPA source stanzas are refused')
              if line.startswith((' ', '\t')):
                  if continuation_owner != 'Signed-By':
                      raise SystemExit('Unexpected Noble PHP PPA field continuation')
                  continue
              match = re.fullmatch(r'([A-Za-z][A-Za-z0-9-]*):.*', line)
              if match is None:
                  raise SystemExit('Malformed Noble PHP PPA source line')
              continuation_owner = match.group(1)
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
              if key_path.parent not in (Path('/usr/share/keyrings'), Path('/etc/apt/keyrings')):
                  raise SystemExit('Unexpected publisher key file')
              key_parent = os.open(key_path.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
              key_descriptor = os.open(key_path.name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=key_parent)
              key_metadata = os.fstat(key_descriptor)
              if (not stat.S_ISREG(key_metadata.st_mode) or key_metadata.st_nlink != 1
                      or key_metadata.st_uid != 0 or key_metadata.st_gid != 0
                      or key_metadata.st_mode & 0o022 or not 0 < key_metadata.st_size <= 65536):
                  raise SystemExit('Unsafe publisher key file')
              with os.fdopen(key_descriptor, 'rb') as key_stream:
                  key = key_stream.read(65537)
                  opened = os.fstat(key_stream.fileno())
              os.close(key_parent)
              if len(key) != key_metadata.st_size or (opened.st_dev, opened.st_ino) != (key_metadata.st_dev, key_metadata.st_ino):
                  raise SystemExit('Publisher key changed while reading')
          with tempfile.TemporaryDirectory(prefix='cwp-public-key-inspection-') as key_home:
              shown = subprocess.run(['gpg', '--batch', '--no-options', '--homedir', key_home, '--no-autostart', '--with-colons', '--show-keys'], input=key, capture_output=True, check=True).stdout.decode('utf-8')
          if len(re.findall(r'^pub:', shown, re.M)) != 1 or re.findall(r'^fpr:::::::::([A-F0-9]{40}):', shown, re.M)[:1] != [fingerprint]:
              raise SystemExit('Unexpected PHP PPA signing identity')
          changed = re.sub(r'^URIs: ' + re.escape(primary) + r'$', 'URIs: ' + mirror, text, count=1, flags=re.M).encode('utf-8')
          if changed.replace(mirror.encode(), primary.encode(), 1) != original or changed == original:
              raise SystemExit('Only the exact repository URI may change')
          isolated = Path('/etc/apt/cwp-sources.list.d')
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
          print('Noble PHP PPA mirror selected; original publisher Signed-By retained: ' + fingerprint)
          CWP_PHP_MIRROR
'''
PROTECTED_APT_RELEASES = r'''          python3 - <<'CWP_APT_RELEASES'
          from pathlib import Path
          import json, os, re, stat
          records = []
          for path in sorted(Path('/var/lib/apt/cwp-lists').glob('*_InRelease')):
              metadata = path.lstat()
              if (not stat.S_ISREG(metadata.st_mode) or metadata.st_nlink != 1
                      or metadata.st_uid != 0 or metadata.st_gid != 0
                      or metadata.st_mode & 0o022 or not 0 < metadata.st_size <= 8388608):
                  raise SystemExit('Unsafe APT InRelease metadata')
              with os.fdopen(os.open(path, os.O_RDONLY | os.O_NOFOLLOW), 'rb') as stream:
                  data = stream.read(8388609)
                  opened = os.fstat(stream.fileno())
              if len(data) != metadata.st_size or (opened.st_dev, opened.st_ino) != (metadata.st_dev, metadata.st_ino):
                  raise SystemExit('APT InRelease changed while reading')
              text = data.decode('utf-8')
              def field(name):
                  values = re.findall(r'^' + re.escape(name) + r': (.*)$', text, re.M)
                  return values[0] if len(values) == 1 else None
              record = {name.lower(): field(name) for name in ('Origin', 'Label', 'Suite', 'Codename', 'Components')}
              if any(value is None for value in record.values()):
                  raise SystemExit('Incomplete APT InRelease identity')
              records.append(record)
          ubuntu_components = 'main restricted universe multiverse'
          expected = [
              {'origin': 'Ubuntu', 'label': 'Ubuntu', 'suite': suite,
               'codename': 'noble', 'components': ubuntu_components}
              for suite in ('noble', 'noble-updates', 'noble-backports', 'noble-security')
          ]
          ppa = {'origin': 'LP-PPA-ondrej-php', 'label': 'PPA for PHP', 'suite': 'noble',
                 'codename': 'noble', 'components': 'main'}
          if os.environ['CWP_PHP_VERSION'] != '8.3':
              expected.append(ppa)
          order = lambda row: tuple(row[name] for name in ('origin', 'label', 'suite', 'codename', 'components'))
          if sorted(records, key=order) != sorted(expected, key=order):
              raise SystemExit('Signed APT InRelease identity set mismatch')
          Path('qa/ubuntu-apt-release-metadata.json').write_text(json.dumps(
              {'schema': 1, 'records': records, 'php': os.environ['CWP_PHP_VERSION'],
               'scope': 'APT-verified InRelease identity before package installation.'}, indent=2) + '\n')
          CWP_APT_RELEASES
'''
PROTECTED_APT_CALL_SUCCESSOR = 'cwp_apt_get -o Acquire::Retries=1'
PROTECTED_APT_CALL_PREDECESSOR = 'sudo apt-get -o Acquire::Retries=5'
PROTECTED_APT_CALL_COUNT = 16
PROTECTED_APT_LIST_CLEANUP = ' -o APT::Get::List-Cleanup=true'
PROTECTED_APT_LIST_CLEANUP_COUNT = 4
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


def executable_ubuntu_source_guard_fixtures():
    """Execute the actual embedded guard in root-owned disposable POSIX fixtures."""
    if os.name != 'posix' or shutil.which('sudo') is None:
        return []
    sudo = subprocess.run(['sudo', '-n', 'true'], capture_output=True)
    if sudo.returncode != 0:
        return []
    marker = "          sudo python3 - <<'CWP_UBUNTU_MIRRORS'\n"
    body = PROTECTED_APT_MIRRORS.split(marker, 1)[1].rsplit('          CWP_UBUNTU_MIRRORS', 1)[0]
    body = textwrap.dedent(body)
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
        'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n\n'
        'Types: deb\nURIs: mirror+file:/etc/apt/apt-mirrors.txt\n'
        'Suites: noble-security\nComponents: main universe restricted multiverse\n'
        'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n'
    )

    def source(archive):
        return (
            'Types: deb\nURIs: ' + archive + '\n'
            'Suites: noble noble-updates noble-backports\n'
            'Components: main universe restricted multiverse\n'
            'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n\n'
            'Types: deb\nURIs: ' + ('mirror+file:/etc/apt/apt-mirrors.txt' if archive.startswith('mirror+')
                                     else 'http://security.ubuntu.com/ubuntu/') + '\n'
            'Suites: noble-security\nComponents: main universe restricted multiverse\n'
            'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg\n'
        )

    checks = []
    fixture_key = b'fixture Ubuntu archive keyring'
    fixture_key_hash = hashlib.sha256(fixture_key).hexdigest()

    def run_case(name, ubuntu_source, mirror_text, expected_profile=None, extra_source=None,
                 hardlink=False, apt_extra=''):
        with tempfile.TemporaryDirectory(prefix='cwp-ubuntu-apt-guard-') as folder:
            root = Path(folder)
            apt = root / 'etc' / 'apt'
            sources = apt / 'sources.list.d'
            apt_conf = apt / 'apt.conf.d'
            keyrings = root / 'usr' / 'share' / 'keyrings'
            qa = root / 'qa'
            sources.mkdir(parents=True)
            apt_conf.mkdir()
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
                "Path('/etc/apt/cwp-source-isolation.conf.pending')": 'Path(' + repr(str(apt / 'cwp-source-isolation.conf.pending')) + ')',
                "Path('/etc/apt/apt.conf.d/zz-cwp-source-isolation')": 'Path(' + repr(str(apt_conf / 'zz-cwp-source-isolation')) + ')',
                '80a36b0a6de2f69f49d2df75ef473ccde121e9e190b9ea01d20a4f63778d5c31': fixture_key_hash,
                'len(key_data) != 3607': 'len(key_data) != ' + str(len(fixture_key)),
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
            needle = 'import hashlib, json, os, re, secrets, stat, subprocess, sys\n'
            require(script.count(needle) == 1, 'Executable guard import boundary changed')
            script = script.replace(needle, needle + fake, 1)
            runner = root / 'guard.py'
            runner.write_text(script, encoding='utf-8')
            try:
                subprocess.run(['sudo', '-n', 'chown', '-R', '0:0', str(root)], check=True)
                subprocess.run(['sudo', '-n', 'chmod', '755', str(root), str(apt), str(sources), str(apt_conf), str(keyrings), str(qa)], check=True)
                for path in (mirror, ubuntu, keyring, release, runner):
                    subprocess.run(['sudo', '-n', 'chmod', '644', str(path)], check=True)
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
                    require(mirror.read_text(encoding='ascii') == selected_mirror,
                            name + ' did not retain the selected mirror list')
                    require(ubuntu.read_text(encoding='ascii') == canonical_source,
                            name + ' did not retain the canonical source')
                    require((apt / 'cwp-sources.list.d' / 'ubuntu.sources').read_text(encoding='ascii') == canonical_source,
                            name + ' did not retain the isolated Ubuntu source')
                    require((apt / 'cwp-source-isolation.conf.pending').is_file(),
                            name + ' did not retain the pending isolation config')
            finally:
                subprocess.run(['sudo', '-n', 'chown', '-R', str(os.getuid()) + ':' + str(os.getgid()), str(root)],
                               check=False, capture_output=True)
        checks.append({'name': name, 'passed': True})

    run_case('Executable guard accepts exact .370 mirror profile', source('mirror+file:/etc/apt/apt-mirrors.txt'),
             expected_mirror, 'github-mirror-list')
    for region in ('us-east-1', 'us-west-2'):
        run_case('Executable guard accepts bounded AWS direct profile ' + region,
                 source('http://' + region + '.ec2.archive.ubuntu.com/ubuntu/'),
                 'http://' + region + '.ec2.archive.ubuntu.com/ubuntu/\n', 'aws-direct')
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
    checks = validate(workflow, legacy, protected)
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
        ('lost isolated PPA source', protected.replace(PROTECTED_PPA_ISOLATION, '', 1)),
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
            validate(workflow, legacy, changed)
        except AssertionError:
            checks.append({'name': 'Regression rejected: ' + name, 'passed': True})
        else:
            raise AssertionError('Regression accepted: ' + name)
    return {'passed': True, 'scope': __doc__.splitlines()[0] + ' Not hosted or installed acceptance.',
            'workflow_sha256': digest(workflow), 'legacy_sha256': digest(legacy), 'checks': checks}


if __name__ == '__main__':
    print(json.dumps(self_test(), indent=2))
