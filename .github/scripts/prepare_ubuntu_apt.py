#!/usr/bin/env python3
"""Fail-closed Ubuntu runner package-source preparation for hosted acceptance.

The workflow invokes one bounded phase at a time so GitHub workflow scalar limits
do not duplicate or truncate the reviewed source. No phase performs live product
operations.
"""
import json
import sys

APT_RELEASE_IDENTITY_FIELDS = ('origin', 'label', 'suite', 'codename', 'components')
PHP_PPA_RELEASE_LABELS = ('PPA for PHP', 'PPA for PHP (deprecated)')
AZURE_IMDS_TRANSIENT_WARNING = (
    "Polling IMDS failed attempt 1 with exception: UrlError('404 Client Error: Not Found for url: "
    "http://169.254.169.254/metadata/reprovisiondata?api-version=2019-06-01')")


def release_identity_allowed(records, php_version):
    """Admit only the exact signed Ubuntu/PPA release identities we reviewed."""
    if php_version not in ('8.2', '8.3', '8.4') or not isinstance(records, list):
        return False
    if any(not isinstance(row, dict) or set(row) != set(APT_RELEASE_IDENTITY_FIELDS)
           or any(not isinstance(row[name], str) for name in APT_RELEASE_IDENTITY_FIELDS)
           for row in records):
        return False
    ubuntu_components = 'main restricted universe multiverse'
    ubuntu = [
        {'origin': 'Ubuntu', 'label': 'Ubuntu', 'suite': suite,
         'codename': 'noble', 'components': ubuntu_components}
        for suite in ('noble', 'noble-updates', 'noble-backports', 'noble-security')
    ]
    choices = [ubuntu]
    if php_version != '8.3':
        choices = [ubuntu + [{
            'origin': 'LP-PPA-ondrej-php', 'label': label, 'suite': 'noble',
            'codename': 'noble', 'components': 'main',
        }] for label in PHP_PPA_RELEASE_LABELS]
    order = lambda row: tuple(row[name] for name in APT_RELEASE_IDENTITY_FIELDS)
    actual = sorted(records, key=order)
    return any(actual == sorted(expected, key=order) for expected in choices)


def strict_cloud_init_status(value):
    """Parse cloud-init JSON without duplicate names or non-finite constants."""
    if not isinstance(value, str):
        raise TypeError('cloud-init status must be text')

    def unique_object(pairs):
        result = {}
        for name, item in pairs:
            if name in result:
                raise ValueError('duplicate cloud-init status name')
            result[name] = item
        return result

    def refuse_constant(value):
        raise ValueError('non-finite cloud-init status constant: ' + value)

    return json.loads(value, object_pairs_hook=unique_object,
                      parse_constant=refuse_constant)


def cloud_init_state_allowed(returncode, state):
    """Return the bounded severity summary for an admitted terminal state."""
    required = {'status', 'extended_status', 'errors', 'recoverable_errors'}
    if not isinstance(state, dict) or not required <= state.keys():
        return None
    status = state['status']
    extended = state['extended_status']
    errors = state['errors']
    recoverable = state['recoverable_errors']
    if (not isinstance(status, str) or not isinstance(extended, str)
            or state.get('stage') is not None or not isinstance(errors, list)
            or any(not isinstance(item, str) or not item or len(item) > 2048
                   for item in errors)
            or not isinstance(recoverable, dict)):
        return None
    severity_counts = {}
    message_bytes = 0
    for severity, messages in recoverable.items():
        if (severity != 'WARNING' or not isinstance(messages, list)
                or not messages or len(messages) > 64
                or any(not isinstance(message, str) or not message or len(message) > 2048
                       for message in messages)):
            return None
        severity_counts[severity] = len(messages)
        message_bytes += sum(len(message.encode('utf-8', errors='replace'))
                             for message in messages)
    if message_bytes > 32768:
        return None
    ordinary = (returncode == 0 and not errors and not recoverable
                and (((status, extended) == ('done', 'done') and 'stage' in state)
                     or (status, extended) == ('disabled', 'disabled')))
    degraded = (returncode == 2 and not errors
                and recoverable == {'WARNING': [AZURE_IMDS_TRANSIENT_WARNING]}
                and (status, extended) == ('done', 'degraded done')
                and state.get('boot_status_code') == 'enabled-by-generator'
                and state.get('datasource') == 'azure'
                and state.get('detail') == 'DataSourceAzure [seed=/dev/sr0]')
    return severity_counts if ordinary or degraded else None

def prepare_ubuntu():
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

    def checked_runner_keyring(path, limit, label, expected_size, expected_sha256):
        """Read the exact hosted-image key, then isolate it from /usr/share.

        The GitHub Ubuntu image build deliberately applies mode 0777 recursively
        below /usr/share.  Trusting that path later would be unsafe, but refusing
        it prevents every hosted job from reaching package verification.  Open
        the root-owned entry relative to the observed directory, require one of
        the two exact supported image modes, verify stable metadata and exact
        bytes, and return those bytes for a protected /etc/apt copy.
        """
        parent = path.parent.lstat()
        parent_mode = stat.S_IMODE(parent.st_mode)
        if (not stat.S_ISDIR(parent.st_mode) or parent.st_uid != 0 or parent.st_gid != 0
                or parent_mode not in (0o755, 0o777)):
            fail('unsupported ' + label + ' directory metadata')
        directory_fd = os.open(path.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
        try:
            if directory_identity(os.fstat(directory_fd)) != directory_identity(parent):
                fail(label + ' directory changed while opening')
            before = os.stat(path.name, dir_fd=directory_fd, follow_symlinks=False)
            if (not stat.S_ISREG(before.st_mode) or before.st_nlink != 1
                    or before.st_uid != 0 or before.st_gid != 0
                    or stat.S_IMODE(before.st_mode) not in (0o644, 0o777)
                    or before.st_size != expected_size or before.st_size > limit):
                fail('unsupported ' + label + ' metadata')
            descriptor = os.open(path.name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=directory_fd)
            with os.fdopen(descriptor, 'rb') as stream:
                opened = os.fstat(stream.fileno())
                if fingerprint(opened) != fingerprint(before):
                    fail(label + ' changed while opening')
                data = stream.read(limit + 1)
                final = os.fstat(stream.fileno())
            if (len(data) != before.st_size or fingerprint(final) != fingerprint(before)
                    or directory_identity(os.fstat(directory_fd)) != directory_identity(parent)):
                fail(label + ' changed while reading')
        finally:
            os.close(directory_fd)
        if hashlib.sha256(data).hexdigest() != expected_sha256:
            fail('unexpected ' + label)
        return data, {
            'directory_mode': format(parent_mode, '04o'),
            'file_mode': format(stat.S_IMODE(before.st_mode), '04o'),
            'uid': before.st_uid,
            'gid': before.st_gid,
        }

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

    def checked_cloud_init(wait, phase):
        """Accept only structured terminal cloud-init states.

        cloud-init 23.4 and newer returns 2 after completing with recoverable
        errors.  GitHub images can expose that documented state even though the
        APT source, keyring and signed release checks below remain independent.
        Admit only the reviewed Azure IMDS first-attempt 404 after verified
        terminal recovery; every other warning, critical, unrecoverable,
        malformed, oversized or non-terminal state fails closed.
        """
        command = ['cloud-init', 'status']
        if wait:
            command.append('--wait')
        command.append('--format=json')
        completed = subprocess.run(
            command, text=True, capture_output=True, timeout=120 if wait else 15)
        stdout = completed.stdout.encode('utf-8', errors='replace')
        stderr = completed.stderr.encode('utf-8', errors='replace')
        summary = {
            'phase': phase,
            'returncode': completed.returncode,
            'stdout_bytes': len(stdout),
            'stdout_sha256': hashlib.sha256(stdout).hexdigest(),
            'stderr_bytes': len(stderr),
            'stderr_sha256': hashlib.sha256(stderr).hexdigest(),
        }
        diagnostic.setdefault('cloud_init_checks', []).append(summary)
        if len(stdout) > 65536 or len(stderr) > 16384:
            fail('cloud-init returned oversized diagnostics')
        try:
            state = strict_cloud_init_status(completed.stdout)
        except (json.JSONDecodeError, TypeError, ValueError):
            fail('cloud-init returned malformed structured status')
        severity_counts = cloud_init_state_allowed(completed.returncode, state)
        if severity_counts is None:
            fail('cloud-init did not reach an admitted terminal state')
        status = state['status']
        extended = state['extended_status']
        summary.update({
            'status': status,
            'extended_status': extended,
            'recoverable_severity_counts': severity_counts,
        })
        return (completed.returncode, status, extended, severity_counts)

    cloud_init_state = checked_cloud_init(True, 'before-source-normalization')

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
    key_data, key_input_metadata = checked_runner_keyring(
        keyring, 4096, 'Ubuntu Noble archive keyring', 3607,
        '80a36b0a6de2f69f49d2df75ef473ccde121e9e190b9ea01d20a4f63778d5c31')
    diagnostic.update({
        'source_bytes': len(source_data),
        'source_sha256': hashlib.sha256(source_data).hexdigest(),
        'mirror_bytes': len(mirror_data),
        'mirror_sha256': hashlib.sha256(mirror_data).hexdigest(),
        'keyring_bytes': len(key_data),
        'keyring_sha256': hashlib.sha256(key_data).hexdigest(),
        'keyring_input_metadata': key_input_metadata,
    })
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
        'http://us-east-2.ec2.archive.ubuntu.com/ubuntu/',
        'http://us-west-2.ec2.archive.ubuntu.com/ubuntu/',
    }
    seen_suites = set()
    records = []
    raw_records = []
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
        raw_uri = fields['uris']
        if raw_uri == 'mirror+file:/etc/apt/apt-mirrors.txt':
            uri = raw_uri
        elif re.fullmatch(r'https?://[A-Za-z0-9.-]+/ubuntu/?', raw_uri):
            uri = raw_uri.rstrip('/') + '/'
        else:
            fail('unsafe Ubuntu source URI syntax')
        records.append((tuple(suites), uri))
        raw_records.append((tuple(suites), raw_uri))
    diagnostic['source_records'] = [
        {'suites': list(suites), 'uri': raw_uri, 'normalized_uri': uri}
        for (suites, uri), (_, raw_uri) in zip(records, raw_records)
    ]
    archive_record = ('noble', 'noble-updates', 'noble-backports')
    security_record = ('noble-security',)
    mirror_records = {
        (archive_record, 'mirror+file:/etc/apt/apt-mirrors.txt'),
        (security_record, 'mirror+file:/etc/apt/apt-mirrors.txt'),
    }
    aws_mirror_records = {
        (archive_record, 'mirror+file:/etc/apt/apt-mirrors.txt'),
        (security_record, 'http://security.ubuntu.com/ubuntu/'),
    }
    aws_profiles = [{
        (archive_record, uri),
        (security_record, 'http://security.ubuntu.com/ubuntu/'),
    } for uri in aws_archives]
    fallback_mirrors = (
        b'https://archive.ubuntu.com/ubuntu/\tpriority:2\n'
        b'https://security.ubuntu.com/ubuntu/\tpriority:3\n'
    )
    expected = b'http://azure.archive.ubuntu.com/ubuntu/\tpriority:1\n' + fallback_mirrors
    expected_aws_mirrors = {
        uri.encode('ascii') + b'\tpriority:1\n' + fallback_mirrors
        for uri in aws_archives
    }
    record_set = set(records)
    if len(records) != 2 or len(record_set) != 2 or seen_suites != archive_suites | security_suites:
        fail('Ubuntu source must contain the exact archive and security stanzas')
    if record_set == mirror_records:
        if references != [ubuntu]:
            fail('unexpected active APT mirror-list reference')
        if mirror_data == expected:
            profile = 'github-mirror-list'
        elif mirror_data in expected_aws_mirrors:
            profile = 'aws-mirror-list'
        else:
            fail('unknown or untrusted active Ubuntu mirror list')
    elif record_set == aws_mirror_records:
        profile = 'aws-mirror-list'
        if references != [ubuntu]:
            fail('unexpected active AWS APT mirror-list reference')
    elif any(record_set == candidate for candidate in aws_profiles):
        profile = 'aws-direct'
        if references:
            fail('direct AWS source profile still references the mirror list')
    else:
        fail('unknown or untrusted Ubuntu source URI or suite grouping')

    if profile == 'aws-mirror-list':
        if mirror_data not in expected_aws_mirrors:
            fail('unknown or untrusted AWS Ubuntu mirror list')
    if profile == 'aws-direct':
        archive_uri = next(uri for suites, uri in records if suites == archive_record)
        expected_aws = archive_uri.encode('ascii') + b'\tpriority:1\n' + fallback_mirrors
        if mirror_data != expected_aws:
            fail('unknown or untrusted AWS Ubuntu mirror list')
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
        b'Signed-By: /etc/apt/cwp-keyrings/ubuntu-archive-keyring.gpg\n\n'
        b'Types: deb\nURIs: mirror+file:/etc/apt/apt-mirrors.txt\n'
        b'Suites: noble-security\nComponents: main universe restricted multiverse\n'
        b'Signed-By: /etc/apt/cwp-keyrings/ubuntu-archive-keyring.gpg\n'
    )
    isolated = Path('/etc/apt/cwp-sources.list.d')
    isolated_keyrings = Path('/etc/apt/cwp-keyrings')
    isolated_keyring = isolated_keyrings / 'ubuntu-archive-keyring.gpg'
    lists = Path('/var/lib/apt/cwp-lists')
    pending_config = Path('/etc/apt/cwp-source-isolation.conf.pending')
    active_config = Path('/etc/apt/apt.conf.d/zz-cwp-source-isolation')
    if any(os.path.lexists(path) for path in (
            isolated, isolated_keyrings, lists, pending_config, active_config)):
        fail('APT source isolation path already exists')
    config_parent = active_config.parent.lstat()
    if (not stat.S_ISDIR(config_parent.st_mode) or config_parent.st_uid != 0 or config_parent.st_gid != 0
            or config_parent.st_mode & 0o022):
        fail('unsafe APT configuration directory')
    apt_parent = isolated.parent.lstat()
    if (not stat.S_ISDIR(apt_parent.st_mode) or apt_parent.st_uid != 0
            or apt_parent.st_gid != 0 or apt_parent.st_mode & 0o022):
        fail('unsafe APT directory')
    apt_fd = os.open(isolated.parent, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        if directory_identity(os.fstat(apt_fd)) != directory_identity(apt_parent):
            fail('APT directory changed before isolation setup')
        for directory in (isolated, isolated_keyrings):
            os.mkdir(directory.name, 0o755, dir_fd=apt_fd)
            directory_fd = os.open(directory.name, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,
                                   dir_fd=apt_fd)
            try:
                os.fchown(directory_fd, 0, 0)
                os.fchmod(directory_fd, 0o755)
                os.fsync(directory_fd)
            finally:
                os.close(directory_fd)
        os.fsync(apt_fd)
    finally:
        os.close(apt_fd)
    isolated_metadata = isolated.lstat()
    if (not stat.S_ISDIR(isolated_metadata.st_mode) or isolated_metadata.st_nlink < 2
            or (isolated_metadata.st_uid, isolated_metadata.st_gid, stat.S_IMODE(isolated_metadata.st_mode))
            != (0, 0, 0o755)):
        fail('APT source isolation directory was not retained')
    isolated_keyring_metadata = isolated_keyrings.lstat()
    if (not stat.S_ISDIR(isolated_keyring_metadata.st_mode) or isolated_keyring_metadata.st_nlink < 2
            or (isolated_keyring_metadata.st_uid, isolated_keyring_metadata.st_gid,
                stat.S_IMODE(isolated_keyring_metadata.st_mode)) != (0, 0, 0o755)):
        fail('APT keyring isolation directory was not retained')
    write_new(isolated_keyring, key_data, 'isolated Ubuntu archive keyring')
    isolated_key_data, _, _ = checked_read(
        isolated_keyring, 4096, 'isolated Ubuntu archive keyring')
    if isolated_key_data != key_data:
        fail('isolated Ubuntu archive keyring differs from verified input')
    replace_checked(mirror, mirror_data, mirror_metadata, mirror_parent, selected, 'Ubuntu mirror list')
    replace_checked(ubuntu, source_data, source_metadata, source_parent, canonical, 'Ubuntu source')
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
    cloud_init_after = checked_cloud_init(False, 'after-source-normalization')
    if cloud_init_after != cloud_init_state:
        fail('cloud-init state changed after APT source replacement')
    receipt = {
        **diagnostic,
        'passed': True,
        'profile': profile,
        'source_sha256': hashlib.sha256(source_data).hexdigest(),
        'mirror_sha256': hashlib.sha256(mirror_data).hexdigest(),
        'mirror_lines': mirror_lines,
        'keyring_sha256': hashlib.sha256(key_data).hexdigest(),
        'keyring_input_metadata': key_input_metadata,
        'isolated_keyring': '/etc/apt/cwp-keyrings/ubuntu-archive-keyring.gpg',
        'isolated_keyring_sha256': hashlib.sha256(isolated_key_data).hexdigest(),
        'canonical_source_sha256': hashlib.sha256(canonical).hexdigest(),
        'selected_mirror_sha256': hashlib.sha256(selected).hexdigest(),
        'isolated_source_sha256': hashlib.sha256(canonical).hexdigest(),
        'isolated_lists': '/var/lib/apt/cwp-lists',
        'pending_isolation_config_sha256': hashlib.sha256(isolation_config).hexdigest(),
        'cloud_init': diagnostic['cloud_init_checks'],
        'scope': 'Bounded source normalization only; package assertions and acceptance remain separate.',
    }
    evidence = Path.cwd() / 'qa' / 'ubuntu-apt-source.json'
    evidence.write_text(json.dumps(receipt, indent=2) + '\n', encoding='utf-8')
    diagnostic['passed'] = True
    diagnostic.pop('failure', None)
    preflight = Path.cwd() / 'qa' / 'ubuntu-apt-source-preflight.json'
    preflight.write_text(json.dumps(diagnostic, indent=2) + '\n', encoding='utf-8')
    print('Validated signed Ubuntu Noble sources and selected canonical archives before Azure fallback')

def prepare_php_ppa():
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

def verify_effective():
    import re, subprocess
    configuration = subprocess.check_output(['apt-config', 'dump'], text=True)
    for name, expected in (
        ('Dir::Etc::sourcelist', '/dev/null'),
        ('Dir::Etc::sourceparts', '/etc/apt/cwp-sources.list.d'),
        ('Dir::State::lists', '/var/lib/apt/cwp-lists'),
    ):
        if re.findall(r'^' + re.escape(name) + r' "([^"]*)";$', configuration, re.M) != [expected]:
            raise SystemExit('Effective APT source isolation was overridden: ' + name)

def verify_releases():
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
    evidence = {
        'schema': 2,
        'passed': False,
        'records': records,
        'php': os.environ['CWP_PHP_VERSION'],
        'allowed_ppa_labels': list(PHP_PPA_RELEASE_LABELS),
        'scope': 'APT-verified InRelease identity before package installation.',
    }
    path = Path('qa/ubuntu-apt-release-metadata.json')
    path.write_text(json.dumps(evidence, indent=2) + '\n')
    if not release_identity_allowed(records, os.environ['CWP_PHP_VERSION']):
        raise SystemExit('Signed APT InRelease identity set mismatch')
    evidence['passed'] = True
    path.write_text(json.dumps(evidence, indent=2) + '\n')

COMMANDS = {
    'prepare-ubuntu': prepare_ubuntu,
    'prepare-php-ppa': prepare_php_ppa,
    'verify-effective': verify_effective,
    'verify-releases': verify_releases,
}

if __name__ == '__main__':
    if len(sys.argv) != 2 or sys.argv[1] not in COMMANDS:
        raise SystemExit('usage: prepare_ubuntu_apt.py ' + '|'.join(COMMANDS))
    COMMANDS[sys.argv[1]]()
