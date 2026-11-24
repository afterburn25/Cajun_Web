"""Fetch and verify the immutable published v1.26.1 baseline and manifest."""
from pathlib import Path, PurePosixPath
from urllib.parse import urlsplit
import hashlib
import io
import json
import re
import sys
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE_ASSET_ID = 711656635
MANIFEST_ASSET_ID = 711656643
ASSET_API = 'https://api.github.com/repos/afterburn25/Cajun_Web/releases/assets/'
ARCHIVE_URL = ASSET_API + str(ARCHIVE_ASSET_ID)
MANIFEST_URL = ASSET_API + str(MANIFEST_ASSET_ID)
ARCHIVE_NAME = 'CajunWebPro-1.26.1-COMPLETE-UPLOAD.zip'
MANIFEST_NAME = 'CajunWebPro-1.26.1-COMPLETE-UPLOAD.manifest.json'
ARCHIVE_SHA256 = '7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3'
ARCHIVE_BYTES = 17_429_306
MANIFEST_SHA256 = 'a903b2850ab4ff25ed94ceddd1794370341865183ee515a72bd816cd5324d0ee'
MANIFEST_BYTES = 482_435
SOURCE = 'f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391'
MEMBERS = 3072
ALLOWED_HOSTS = {
    'api.github.com', 'github.com', 'release-assets.githubusercontent.com',
    'objects.githubusercontent.com',
}


class TrustedRedirectHandler(urllib.request.HTTPRedirectHandler):
    """Refuse an untrusted redirect before making the redirected request."""

    def redirect_request(self, request, file_pointer, code, message, headers, new_url):
        parsed = urlsplit(new_url)
        if parsed.scheme != 'https' or parsed.hostname not in ALLOWED_HOSTS:
            raise RuntimeError('Published asset attempted an untrusted redirect')
        return super().redirect_request(request, file_pointer, code, message, headers, new_url)


def asset_opener():
    """Use direct trusted HTTPS only; ambient proxy configuration is not acceptance input."""
    return urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        TrustedRedirectHandler(),
    )


def strict_object(pairs):
    result = {}
    for key, value in pairs:
        if not isinstance(key, str) or key in result:
            raise RuntimeError('Published manifest contains a duplicate or invalid object key')
        result[key] = value
    return result


def load_unique_json(data: bytes):
    try:
        text = data.decode('utf-8')
    except UnicodeDecodeError as error:
        raise RuntimeError('Published manifest is not UTF-8') from error
    try:
        return json.loads(text, object_pairs_hook=strict_object)
    except (json.JSONDecodeError, ValueError) as error:
        raise RuntimeError('Published manifest is not strict JSON') from error


def safe_file_path(path: str) -> bool:
    if not isinstance(path, str):
        return False
    parsed = PurePosixPath(path)
    return (bool(path) and not parsed.is_absolute() and parsed.as_posix() == path
            and all(part not in ('', '.', '..') for part in path.split('/'))
            and '\\' not in path and ':' not in path and '\x00' not in path)


def expected_directories(files) -> set[str]:
    result = set()
    for path in files:
        parts = path.split('/')
        result.update('/'.join(parts[:position]) + '/' for position in range(1, len(parts)))
    return result


def validate_manifest_document(manifest) -> dict[str, str]:
    if not isinstance(manifest, dict) or set(manifest) != {
            'version', 'source_commit', 'artifact', 'sha256', 'directories', 'files'}:
        raise RuntimeError('Published manifest has an unknown or missing field')
    if (manifest['version'] != '1.26.1' or manifest['source_commit'] != SOURCE
            or manifest['artifact'] != ARCHIVE_NAME or manifest['sha256'] != ARCHIVE_SHA256):
        raise RuntimeError('Published manifest release identity mismatch')
    files = manifest['files']
    directories = manifest['directories']
    if (not isinstance(files, dict) or len(files) != MEMBERS
            or len(files) != len({path.casefold() for path in files})
            or any(not safe_file_path(path) for path in files)
            or any(not isinstance(value, str) or re.fullmatch(r'[0-9a-f]{64}', value) is None
                   for value in files.values())):
        raise RuntimeError('Published manifest file inventory is unsafe or malformed')
    if (not isinstance(directories, list) or not all(isinstance(path, str) for path in directories)
            or len(directories) != len(set(directories))
            or len(directories) != len({path.casefold() for path in directories})
            or any(not path.endswith('/') or not safe_file_path(path[:-1]) for path in directories)
            or set(directories) != expected_directories(files)):
        raise RuntimeError('Published manifest directory inventory is unsafe or incomplete')
    return files


def validate_manifest_bytes(data: bytes) -> dict[str, str]:
    if len(data) != MANIFEST_BYTES or hashlib.sha256(data).hexdigest() != MANIFEST_SHA256:
        raise RuntimeError('Published v1.26.1 manifest identity mismatch')
    return validate_manifest_document(load_unique_json(data))


def validate_archive_bytes(data: bytes, files) -> None:
    if len(data) != ARCHIVE_BYTES or hashlib.sha256(data).hexdigest() != ARCHIVE_SHA256:
        raise RuntimeError('Published v1.26.1 baseline identity mismatch')
    try:
        with zipfile.ZipFile(io.BytesIO(data)) as archive:
            names = archive.namelist()
            if (len(names) != len(set(names)) or len(names) != len({name.casefold() for name in names})
                    or len(names) != MEMBERS or archive.testzip() is not None or set(names) != set(files)):
                raise RuntimeError('Published baseline archive inventory mismatch')
            for item in archive.infolist():
                mode = (item.external_attr >> 16) & 0o177777
                if (item.is_dir() or not safe_file_path(item.filename) or mode != 0o100644
                        or hashlib.sha256(archive.read(item)).hexdigest() != files[item.filename]):
                    raise RuntimeError('Published baseline member identity mismatch: ' + item.filename)
    except zipfile.BadZipFile as error:
        raise RuntimeError('Published baseline is not a valid ZIP') from error


def fetch(url: str, limit: int) -> bytes:
    parsed = urlsplit(url)
    if parsed.scheme != 'https' or parsed.hostname not in ALLOWED_HOSTS:
        raise RuntimeError('Published asset URL is not trusted')
    request = urllib.request.Request(url, headers={
        'Accept': 'application/octet-stream',
        'User-Agent': 'CajunWebPro-release-127-acceptance',
        'X-GitHub-Api-Version': '2022-11-28',
    })
    with asset_opener().open(request, timeout=90) as response:
        parsed = urlsplit(response.url)
        if parsed.scheme != 'https' or parsed.hostname not in ALLOWED_HOSTS:
            raise RuntimeError('Published asset redirected to an untrusted host')
        data = response.read(limit + 1)
    if len(data) > limit:
        raise RuntimeError('Published asset exceeds its exact bounded size')
    return data


def main(destination: Path) -> None:
    manifest_destination = destination.with_suffix('.manifest.json')
    if (destination.exists() or destination.is_symlink()
            or manifest_destination.exists() or manifest_destination.is_symlink()):
        raise RuntimeError('Refuse to replace an existing baseline or manifest')
    manifest_data = fetch(MANIFEST_URL, MANIFEST_BYTES)
    files = validate_manifest_bytes(manifest_data)
    archive_data = fetch(ARCHIVE_URL, ARCHIVE_BYTES)
    validate_archive_bytes(archive_data, files)
    destination.parent.mkdir(parents=True, exist_ok=True)
    created = []
    try:
        with manifest_destination.open('xb') as output:
            created.append(manifest_destination)
            output.write(manifest_data)
        with destination.open('xb') as output:
            created.append(destination)
            output.write(archive_data)
    except BaseException:
        for path in created:
            path.unlink(missing_ok=True)
        raise
    print(json.dumps({
        'verified': True, 'source': SOURCE,
        'archive_asset_id': ARCHIVE_ASSET_ID, 'archive_sha256': ARCHIVE_SHA256,
        'archive_bytes': ARCHIVE_BYTES, 'manifest_asset_id': MANIFEST_ASSET_ID,
        'manifest_sha256': MANIFEST_SHA256, 'manifest_bytes': MANIFEST_BYTES,
        'members': MEMBERS, 'manifest_path': str(manifest_destination),
    }))


if __name__ == '__main__':
    if len(sys.argv) != 2:
        raise SystemExit('usage: fetch_release_127_baseline.py DESTINATION')
    main(Path(sys.argv[1]))
