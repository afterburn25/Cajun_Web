"""Portable report/input/output guards only; no SQL, browser, transport, or application execution."""
import copy
import json
from pathlib import Path
import re
import tempfile

import hosting_checkout_http_browser as runner


def main():
    checks = []
    def check(name, condition):
        checks.append({'name': name, 'passed': bool(condition)})
        if not condition: raise AssertionError(name)
    def deny(name, fn):
        try: fn()
        except (RuntimeError, ValueError, KeyError, TypeError): check(name, True); return
        check(name, False)
    # Installed source is provided by the normal tests path, not copied report strings.
    producer = (runner.ROOT/'tests/hosting_checkout_native.php').read_text(encoding='utf-8')
    labels = runner.native_labels(producer)
    workflow = (runner.ROOT/'.github/workflows/consolidation-after-backups.yml').read_text(encoding='utf-8')
    apt_mirrors = r'''          sudo python3 - <<'CWP_UBUNTU_MIRRORS'
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
    apt_wrapper = '''          cwp_apt_get() {
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
    check('Checkout apt calls preserve mirror failover and hard-fail bounded reconnects',
          workflow.count(apt_mirrors) == 1
          and workflow.count(apt_wrapper) == 1
          and workflow.count('cwp_apt_get -o Acquire::Retries=1') == 4
          and 'sudo apt-get -o Acquire::Retries' not in workflow
          and 'Acquire::Retries=5' not in workflow)
    report = {'passed': True, 'checks': [{'name': n, 'passed': True} for n in labels], 'runtime': {'php': '8.3.30', 'database': 'synthetic guard value'}}
    def encoded(value): return json.dumps(value).encode()
    check('Exact 85 native producer labels are retained', len(labels) == 85 and len(set(labels)) == 85)
    check('Complete synthetic report shape is accepted only as a guard example', runner.validate_native(encoded(report), producer, '8.3') == report)
    for name, mutate in [
        ('False outer pass refuses', lambda x: x.update(passed=False)),
        ('Truthy integer outer pass refuses', lambda x: x.update(passed=1)),
        ('False named outcome refuses', lambda x: x['checks'][0].update(passed=False)),
        ('Truthy named integer refuses', lambda x: x['checks'][0].update(passed=1)),
        ('Missing original assertion refuses', lambda x: x['checks'].pop()),
        ('Duplicate original assertion refuses', lambda x: x['checks'].__setitem__(1, x['checks'][0])),
        ('Reordered original assertion refuses', lambda x: x['checks'].reverse()),
        ('Error-bearing report refuses', lambda x: x.update(error={'class': 'RuntimeException'})),
        ('Different actual PHP family refuses', lambda x: x['runtime'].update(php='8.4.1')),
        ('Missing actual database runtime refuses', lambda x: x['runtime'].pop('database')),
    ]:
        changed = copy.deepcopy(report); mutate(changed); deny(name, lambda: runner.validate_native(encoded(changed), producer, '8.3'))
    deny('Duplicate JSON keys refuse', lambda: runner.validate_native(b'{"passed":false,' + encoded(report)[1:], producer, '8.3'))
    deny('Oversized original report refuses', lambda: runner.validate_native(b' '*(256*1024+1), producer, '8.3'))
    deny('Altered producer label count refuses', lambda: runner.native_labels(producer + "\nhcc_ok('unexpected',true);"))
    check('Twelve exact unique capture names retained', len(runner.CAPTURES) == 12 and len(set(runner.CAPTURES)) == 12)
    source = {path: (runner.ROOT/path).read_text(encoding='utf-8') for path in (
        'site/admin/controllers/actions.php', 'site/admin/controllers/hosting_purchases.php',
        'site/admin/controllers/router.php',
        'site/app/hosting/checkout_purchases_views.php', 'site/app/billing/ledger.php',
        'site/database/migrations/hosting_payments_migrate.php',
        'site/database/schema/gateway_receipts_schema.php', 'site/database/schema/hosting_payments_schema.php',
        'tests/hosting_checkout_http_browser.py', 'tests/hosting_payment_cases.py',
        'tests/hosting_checkout_http_fixture.php')}
    check('Owner payment reconciliation is a shipping CSRF-routed confirmed action',
          "str_starts_with($action,'hosting_payment_')" in source['site/admin/controllers/actions.php']
          and "function hosting_purchases_admin_action" in source['site/admin/controllers/hosting_purchases.php']
          and "posted('confirm',1)!=='1'" in source['site/admin/controllers/hosting_purchases.php']
          and "hosting_payment_reconcile(" in source['site/admin/controllers/hosting_purchases.php']
          and "data-hosting-payment-reconcile" in source['site/app/hosting/checkout_purchases_views.php'])
    check('Failed owner reconciliation returns escaped visible feedback',
          "hosting_purchases_admin_render($error)" in source['site/admin/controllers/router.php']
          and "function hosting_purchases_admin_render(string $error='')" in source['site/admin/controllers/hosting_purchases.php']
          and "role=\"alert\">'.esc($error)" in source['site/admin/controllers/hosting_purchases.php'])
    check('Paid reversal review never presents initial-capture reconciliation',
          "$row['invoice']['status']??null)==='issued'" in source['site/app/hosting/checkout_purchases_views.php'])
    check('Every ledger writer serializes against unresolved hosting captures',
          'function ledger_hosting_attempt_assert_clear' in source['site/app/billing/ledger.php']
          and 'ledger_hosting_attempt_assert_clear((int)$i[\'id\'],$hostingAttempt);' in source['site/app/billing/ledger.php'])
    migration = source['site/database/migrations/hosting_payments_migrate.php']
    check('Hosting payment setup validates checkout readiness under its owning lock and transaction',
          'staff_locked(function()use($password):void {' in migration
          and '$checkoutReady=staff_transaction(static fn():bool=>hosting_checkout_ready());' in migration
          and migration.index('staff_locked(function()use($password):void {')
              < migration.index('$checkoutReady=staff_transaction(static fn():bool=>hosting_checkout_ready());')
              < migration.index("if(!$checkoutReady||setting('gateway_receipts_schema_version')!=='1')"))
    payment_fixture = source['tests/hosting_checkout_http_fixture.php']
    payment_runner = source['tests/hosting_checkout_http_browser.py']
    payment_cases = source['tests/hosting_payment_cases.py']
    payment_diagnostics = re.findall(r"check\(label \+ '([^']+)'", payment_cases)
    check('Payment form failure records precise bounded page and tokenization diagnostics',
          len(payment_diagnostics) == len(set(payment_diagnostics)) == 12
          and payment_diagnostics[-1] == 'hosting payment form is the actual tokenized-card form'
          and "label = outcome['outcome'] + ' journey: '" in payment_cases
          and "target = fill_card(page, outcome)" in payment_cases)
    expanded_diagnostics = [outcome + ' journey: ' + label
                            for outcome in ('declined', 'uncertain', 'approved')
                            for label in payment_diagnostics]
    check('All three hosting payment journeys emit unique form diagnostics',
          len(expanded_diagnostics) == len(set(expanded_diagnostics)) == 36
          and "check('Hosting payment form is the actual tokenized-card form'" not in payment_cases)
    check('Payment continuation binds the exact current billing publication under the checkout lock',
          "'payment_publication': result['billing_options']['monthly']" in payment_runner
          and "fixture('payment-state', {" in payment_cases
          and "'publication_id': publication['publication_id']" in payment_cases
          and "'publication_sha256': publication['publication_sha256']" in payment_cases
          and "'amount_cents': payment_amount" in payment_cases
          and "$publicationId=$input['publication_id']??null" in payment_fixture
          and "$publicationHash=$input['publication_sha256']??null" in payment_fixture
          and "$amountCents=$input['amount_cents']??null" in payment_fixture
          and payment_fixture.count('hosting_catalog_publication($publicationId)') == 1
          and '$publication=hosting_checkout_locked(static fn()=>hosting_catalog_publication($publicationId));'
              in payment_fixture
          and "hash_equals($publicationHash,$publication->fingerprint())" in payment_fixture
          and "'publication_id'=>$publicationId" in payment_fixture
          and "'publication_sha256'=>$publicationHash" in payment_fixture
          and "'amount_cents'=>$amountCents" in payment_fixture
          and 'WHERE product_id=? ORDER BY seq DESC LIMIT 1' not in payment_fixture)
    for name in ('gateway_receipts_schema.php', 'hosting_payments_schema.php'):
        schema = source['site/database/schema/' + name]
        check(name + ' requires same-database foreign-key targets',
              'k.REFERENCED_TABLE_SCHEMA' in schema
              and "$relation['REFERENCED_TABLE_SCHEMA']!==config()['db']['name']" in schema)
    old_root, old_web, old_site, old_sessions = runner.ROOT, runner.WEB, runner.SITE, runner.SESSIONS
    try:
        with tempfile.TemporaryDirectory(prefix='cwp-checkout-output-') as temp:
            root = Path(temp).resolve(); runner.ROOT = root
            def paths(tag):
                runner.WEB = root/'test-runtime'/('web-'+tag); runner.SITE = runner.WEB/'hosting-checkout'; runner.SESSIONS = root/'test-runtime'/('sessions-'+tag)
            qa, outputs = runner.output_paths(True)
            check('Full outputs are the exact flat report names', qa == root/'qa' and {p.name for p in outputs.values()} == {'hosting-checkout-http-results.json','hosting-checkout-http-progress.txt','hosting-checkout-http-commands.json','hosting-checkout-http-server.log'})
            focused, focused_outputs = runner.output_paths(False)
            check('Focused outputs retain the separate nested directory', focused == root/'qa/hosting-checkout-http' and {p.name for p in focused_outputs.values()} == {'results.json','progress.txt','commands.json','server.log'})
            qa.mkdir(); (qa/'unrelated-results.json').write_bytes(b'preserve')
            paths('ok'); runner.prepare_outputs(True)
            check('Flat setup preserves unrelated evidence', (qa/'unrelated-results.json').read_bytes() == b'preserve' and runner.SITE.is_dir() and runner.SESSIONS.is_dir())
            for index, target in enumerate([*outputs.values(), *(qa/n for n in runner.CAPTURES)]):
                paths(str(index)); target.write_bytes(b'original')
                deny('Existing owned output refuses '+target.name, lambda: runner.prepare_outputs(True))
                check('Collision preserves original '+target.name, target.read_bytes() == b'original' and not runner.WEB.exists())
                target.unlink()
            paths('existing-runtime'); runner.WEB.mkdir(parents=True)
            deny('Existing web runtime refuses reset', lambda: runner.prepare_outputs(True))
    finally: runner.ROOT, runner.WEB, runner.SITE, runner.SESSIONS = old_root, old_web, old_site, old_sessions
    print(json.dumps({'passed': True, 'scope': 'Portable guards, synthetic report mutations and temporary output paths only', 'checks': checks, 'count': len(checks), 'native_or_http_executed': False}, indent=2))


if __name__ == '__main__': main()
