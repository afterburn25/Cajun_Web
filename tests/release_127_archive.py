"""Immutable, validated ZIP snapshots for the 1.26.1 -> 1.27.0 gates."""
from pathlib import Path, PurePosixPath
import hashlib
import io
import json
import re
import shutil
import stat
import zipfile


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


def candidate_snapshot(path):
    """Bind candidate archive bytes to the builder's adjacent strict manifest."""
    path = Path(path)
    manifest_path = path.with_suffix('.manifest.json')
    if path.is_symlink() or manifest_path.is_symlink() or not path.is_file() or not manifest_path.is_file():
        raise RuntimeError('Candidate archive and manifest must be regular files')
    manifest = json.loads(manifest_path.read_bytes(), object_pairs_hook=_strict_object)
    if not isinstance(manifest, dict) or set(manifest) != {'version', 'source_commit', 'artifact', 'sha256', 'directories', 'files'}:
        raise RuntimeError('Candidate manifest shape mismatch')
    data = path.read_bytes()
    if manifest['artifact'] != path.name or hashlib.sha256(data).hexdigest() != manifest['sha256']:
        raise RuntimeError('Candidate archive identity mismatch')
    inspect_snapshot(data, manifest['files'])
    return data


def extract_snapshot(data, destination):
    """Extract only the already-inspected bytes, refusing replacement and escapes."""
    destination = Path(destination)
    destination.mkdir(parents=True, exist_ok=True)
    root = destination.resolve()
    names = inspect_snapshot(data)
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        for name in names:
            target = destination / _safe_name(name)
            target.parent.mkdir(parents=True, exist_ok=True)
            if target.exists() or target.is_symlink() or not target.resolve().is_relative_to(root):
                raise RuntimeError('Archive extraction would replace or escape: ' + name)
            with archive.open(name) as source, target.open('xb') as output:
                shutil.copyfileobj(source, output)
