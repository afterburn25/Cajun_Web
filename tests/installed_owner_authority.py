"""Same-owner HTTP permission denial with exact private/database retention."""
from contextlib import contextmanager
from pathlib import Path
import json
import os
import subprocess

ROOT = Path(__file__).resolve().parents[1]

@contextmanager
def revoked_owner(installed_root, php, *, owner_id=1):
    if type(owner_id) is not int or not 1 <= owner_id <= 2147483647:
        raise AssertionError('An exact positive fixture owner ID is required')
    if os.environ.get('CWP_TEST_DATABASE') != 'cwp_test' or os.environ.get('CWP_TEST_HOST') != '127.0.0.1':
        raise AssertionError('Disposable loopback installation only')
    root = Path(installed_root).resolve(strict=True)
    root.relative_to((ROOT / 'test-runtime').resolve(strict=True))
    if not (root / '.installed.php').is_file():
        raise AssertionError('An actual installed fixture is required')
    def probe(mode, original=None):
        result = subprocess.run([str(php), str(ROOT / 'tests/installed_owner_authority.php'), str(root), mode],
                                input=json.dumps({'original': original, 'owner_id': owner_id}), capture_output=True, text=True, timeout=90)
        if result.returncode or result.stderr.strip():
            raise AssertionError('Installed authority probe failed: ' + result.stderr[-2000:])
        value = json.loads(result.stdout)
        if value.get('profile', {}).get('user_id') != owner_id:
            raise AssertionError('Authority probe returned a different fixture owner')
        if not value.get('snapshot', {}).get('tables'):
            raise AssertionError('Empty state snapshot is not acceptance')
        return value
    before = probe('snapshot')
    changed = False
    try:
        changed = True
        revoked = probe('revoke', before['profile'])
        yield
        after = probe('snapshot')
        if after != revoked:
            raise AssertionError('Refused HTTP operation changed records, schema, private files or permissions')
    finally:
        if changed:
            restored = probe('restore', before['profile'])
            if restored != before:
                raise AssertionError('Authority denial did not retain exact original data and owner profile')
