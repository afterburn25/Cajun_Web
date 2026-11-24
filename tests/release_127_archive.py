"""Immutable, validated ZIP snapshots for the 1.26.1 -> 1.27.0 gates."""
from pathlib import Path, PurePosixPath
import hashlib
import io
import json
import re
import shutil
import stat
import zipfile

PRIVATE_GUARD = b'<?php http_response_code(404); exit; ?>\n'


def _strict_object(pairs):
    result = {}
    for key, value in pairs:
        if not isinstance(key, str) or key in result:
            raise RuntimeError('Archive manifest contains a duplicate or invalid key')
        result[key] = value
    return result


def _safe_name(name):
    if not isinstance(name, str) or not name or len(name) >= 1024:
        raise RuntimeError('Invalid archive member name')
    path = PurePosixPath(name)
    reserved = {'CON', 'PRN', 'AUX', 'NUL', *(f'COM{i}' for i in range(1, 10)), *(f'LPT{i}' for i in range(1, 10))}
    if (path.is_absolute() or str(path) != name or '\\' in name or ':' in name
            or any(ord(char) < 32 for char in name)
            or any(part in ('', '.', '..') or part != part.rstrip(' .')
                   or part.split('.')[0].upper() in reserved for part in path.parts)):
        raise RuntimeError('Unsafe archive member: ' + name)
    return path


def inspect_snapshot(data, expected_files=None):
    """Validate one byte snapshot and return its immutable member tuple."""
    try:
        with zipfile.ZipFile(io.BytesIO(data)) as archive:
            entries = archive.infolist()
            names = [entry.filename for entry in entries]
            if (not names or len(names) > 20_000 or len(names) != len(set(names))
                    or len(names) != len({name.casefold() for name in names})):
                raise RuntimeError('Archive has an invalid member set')
            if sum(entry.file_size for entry in entries) > 512 * 1024 * 1024:
                raise RuntimeError('Archive exceeds the expanded-size bound')
            if expected_files is not None and set(names) != set(expected_files):
                raise RuntimeError('Archive member set differs from its manifest')
            for entry in entries:
                _safe_name(entry.filename)
                mode = stat.S_IFMT(entry.external_attr >> 16)
                if entry.orig_filename != entry.filename or entry.is_dir() or entry.flag_bits & 1 or mode not in (0, stat.S_IFREG):
                    raise RuntimeError('Nonregular or encoded archive member: ' + entry.filename)
                if expected_files is not None:
                    expected = expected_files[entry.filename]
                    if not isinstance(expected, str) or re.fullmatch(r'[0-9a-f]{64}', expected) is None:
                        raise RuntimeError('Invalid manifest member digest')
                    digest = hashlib.sha256()
                    with archive.open(entry) as source:
                        for block in iter(lambda: source.read(1024 * 1024), b''):
                            digest.update(block)
                    if digest.hexdigest() != expected:
                        raise RuntimeError('Archive member differs from its manifest: ' + entry.filename)
            if archive.testzip() is not None:
                raise RuntimeError('Archive CRC verification failed')
            return tuple(names)
    except zipfile.BadZipFile as error:
        raise RuntimeError('Invalid ZIP archive') from error


def published_snapshot(path, expected_size, expected_sha256):
    """Read exactly once, verify, and return the same bytes used by extraction."""
    path = Path(path)
    if path.is_symlink() or not path.is_file():
        raise RuntimeError('Published baseline must be a regular file')
    with path.open('rb') as source:
        data = source.read(expected_size + 1)
        if source.read(1):
            raise RuntimeError('Published baseline exceeds its exact size')
    if len(data) != expected_size or hashlib.sha256(data).hexdigest() != expected_sha256:
        raise RuntimeError('Published baseline identity mismatch')
    inspect_snapshot(data)
    return data


def candidate_snapshot(path, expected_source, expected_version):
    """Bind candidate archive bytes to the builder's adjacent strict manifest."""
    path = Path(path)
    manifest_path = path.with_suffix('.manifest.json')
    if (re.fullmatch(r'[0-9a-f]{40}', expected_source or '') is None
            or re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+', expected_version or '') is None):
        raise RuntimeError('Expected candidate identity is invalid')
    if path.is_symlink() or manifest_path.is_symlink() or not path.is_file() or not manifest_path.is_file():
        raise RuntimeError('Candidate archive and manifest must be regular files')
    manifest_data = manifest_path.read_bytes()
    manifest = json.loads(manifest_data, object_pairs_hook=_strict_object)
    if not isinstance(manifest, dict) or set(manifest) != {'version', 'source_commit', 'artifact', 'sha256', 'directories', 'files'}:
        raise RuntimeError('Candidate manifest shape mismatch')
    if manifest['source_commit'] != expected_source or manifest['version'] != expected_version:
        raise RuntimeError('Candidate manifest source or version mismatch')
    if (re.fullmatch(r'[0-9a-f]{64}', manifest.get('sha256', '')) is None
            or not isinstance(manifest.get('directories'), list)
            or not isinstance(manifest.get('files'), dict)):
        raise RuntimeError('Candidate manifest identity is invalid')
    expected_directories = sorted({
        '/'.join(name.split('/')[:position]) + '/'
        for name in manifest['files'] for position in range(1, len(name.split('/')))
    })
    directories = manifest['directories']
    if (directories != expected_directories
            or len(directories) != len(set(directories))
            or len(directories) != len({name.casefold() for name in directories})
            or any(not isinstance(name, str) or not name.endswith('/')
                   for name in directories)):
        raise RuntimeError('Candidate manifest directory inventory is invalid')
    data = path.read_bytes()
    if manifest['artifact'] != path.name or hashlib.sha256(data).hexdigest() != manifest['sha256']:
        raise RuntimeError('Candidate archive identity mismatch')
    inspect_snapshot(data, manifest['files'])
    return data, {
        'artifact': path.name,
        'bytes': len(data),
        'sha256': manifest['sha256'],
        'manifest': manifest_path.name,
        'manifest_sha256': hashlib.sha256(manifest_data).hexdigest(),
        'source_commit': manifest['source_commit'],
        'version': manifest['version'],
    }


def extract_snapshot(data, destination):
    """Extract only the already-inspected bytes, refusing replacement and escapes."""
    destination = Path(destination)
    if destination.is_symlink() or not destination.is_dir():
        raise RuntimeError('Archive destination must be an existing ordinary directory')
    root = destination.resolve()
    names = inspect_snapshot(data)
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        for name in names:
            safe = _safe_name(name)
            parent = root
            for part in safe.parts[:-1]:
                child = parent / part
                if child.exists() or child.is_symlink():
                    if child.is_symlink() or not child.is_dir() or not child.resolve().is_relative_to(root):
                        raise RuntimeError('Archive extraction parent is unsafe: ' + name)
                else:
                    child.mkdir()
                    if child.is_symlink() or not child.is_dir() or not child.resolve().is_relative_to(root):
                        raise RuntimeError('Archive extraction parent is unsafe: ' + name)
                parent = child.resolve()
            target = parent / safe.name
            if target.exists() or target.is_symlink() or not parent.is_relative_to(root):
                raise RuntimeError('Archive extraction would replace or escape: ' + name)
            with archive.open(name) as source, target.open('xb') as output:
                shutil.copyfileobj(source, output)


def fixture_storage_path(raw, private_parent, public_root, *, virtual_root=None, host_root=None):
    """Resolve an installer-reported synthetic store without trusting traversal or links."""
    if (not isinstance(raw, str) or not raw.startswith('/') or '\\' in raw or '\x00' in raw
            or any(part in ('', '.', '..') for part in raw.split('/')[1:])):
        raise RuntimeError('Installer returned a noncanonical private-storage path')
    reported = Path(raw)
    if (virtual_root is None) != (host_root is None):
        raise RuntimeError('Container private-storage mapping is incomplete')
    if virtual_root is not None:
        virtual_root, host_root = Path(virtual_root), Path(host_root)
        if not reported.is_relative_to(virtual_root):
            raise RuntimeError('Container private storage is outside the exact source mount')
        candidate = host_root / reported.relative_to(virtual_root)
    else:
        candidate = reported
    candidate = candidate.absolute()
    parent = Path(private_parent).absolute()
    public = Path(public_root).absolute()
    if (not candidate.is_relative_to(parent) or candidate.is_relative_to(public)
            or not parent.is_dir() or parent.is_symlink()
            or parent.resolve(strict=True) != parent):
        raise RuntimeError('Private storage is outside the bounded synthetic parent')
    current = parent
    for part in candidate.relative_to(parent).parts:
        current /= part
        info = current.lstat()
        if stat.S_ISLNK(info.st_mode) or not stat.S_ISDIR(info.st_mode):
            raise RuntimeError('Private storage has a linked or non-directory component')
    if candidate.resolve(strict=True) != candidate:
        raise RuntimeError('Private storage has a noncanonical ancestor')
    return candidate


def ordinary_file_bytes(path, root):
    """Read a regular contained file only when its entire absolute path is canonical."""
    path, root = Path(path).absolute(), Path(root).absolute()
    if (not path.is_relative_to(root) or root.resolve(strict=True) != root
            or path.resolve(strict=True) != path):
        raise RuntimeError('Evidence file is outside its canonical root')
    info = path.lstat()
    if stat.S_ISLNK(info.st_mode) or not stat.S_ISREG(info.st_mode):
        raise RuntimeError('Evidence path is not an ordinary file')
    return path.read_bytes()


def interrupted_journal(storage, installed, version, source_commit, normal_policy, *,
                        starting_version, expected_root=None, virtual_root=None, host_root=None):
    """Parse and bind the exact durable applying-state journal after synthetic interruption."""
    storage, installed = Path(storage).absolute(), Path(installed).absolute()
    structure = storage / ('structure-' + version)
    journal = structure / 'journal.php'
    structure_info = structure.lstat()
    if (stat.S_ISLNK(structure_info.st_mode) or not stat.S_ISDIR(structure_info.st_mode)
            or stat.S_IMODE(structure_info.st_mode) != 0o700
            or structure.resolve(strict=True) != structure):
        raise RuntimeError('Interrupted journal directory is not canonical mode 0700')
    journal_info = journal.lstat()
    if (stat.S_ISLNK(journal_info.st_mode) or not stat.S_ISREG(journal_info.st_mode)
            or stat.S_IMODE(journal_info.st_mode) != 0o600):
        raise RuntimeError('Interrupted journal is not an ordinary mode-0600 file')
    raw = ordinary_file_bytes(journal, structure)
    if not raw.startswith(PRIVATE_GUARD):
        raise RuntimeError('Interrupted journal has an invalid private guard')
    try:
        data = json.loads(raw[len(PRIVATE_GUARD):], object_pairs_hook=_strict_object)
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise RuntimeError('Interrupted journal JSON is invalid') from error
    if not isinstance(data, dict):
        raise RuntimeError('Interrupted journal JSON is not an object')
    if expected_root is None:
        if virtual_root is not None or host_root is not None:
            raise RuntimeError('Virtual journal mapping requires an explicit recorded root')
        expected_root = str(installed.resolve(strict=True))
    elif (not isinstance(expected_root, str) or not expected_root.startswith('/')
          or '\\' in expected_root or '\x00' in expected_root
          or any(part in ('', '.', '..') for part in expected_root.split('/')[1:])):
        raise RuntimeError('Expected journal root is not canonical')
    if virtual_root is not None or host_root is not None:
        if virtual_root is None or host_root is None:
            raise RuntimeError('Virtual journal mapping is incomplete')
        recorded, virtual, host = Path(expected_root), Path(virtual_root), Path(host_root)
        if (not recorded.is_relative_to(virtual)
                or (host / recorded.relative_to(virtual)).resolve(strict=True)
                != installed.resolve(strict=True)):
            raise RuntimeError('Expected journal root does not map to the exact host installation')
    elif expected_root != str(installed.resolve(strict=True)):
        raise RuntimeError('Expected journal root differs from the canonical host installation')
    operations = data.get('operations')
    policy = operations.get('.htaccess') if isinstance(operations, dict) else None
    last = data.get('last_completed')
    if (data.get('root') != expected_root or data.get('version') != starting_version
            or data.get('target') != version or data.get('commit') != source_commit
            or data.get('state') != 'applying' or not isinstance(last, str)
            or not last or last == '.htaccess' or not isinstance(operations, dict)
            or last not in operations
            or not isinstance(policy, dict) or policy.get('source') != '.htaccess'
            or policy.get('new') != hashlib.sha256(normal_policy).hexdigest()):
        raise RuntimeError('Interrupted journal does not bind this exact applying update')
    return data
