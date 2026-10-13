"""Verify and extract the retained historical fixture, never a deployment source."""
from pathlib import Path, PurePosixPath
import hashlib, zipfile, subprocess, shutil
root=Path(__file__).resolve().parents[1]
archive=root/'tests/fixtures/CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip'
assert hashlib.sha256(archive.read_bytes()).hexdigest()=='13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf'
destination=root/'test-runtime/legacy'
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
        assert path.name not in {'config.php','.installed.php','proxy-config.php'}
    z.extractall(destination)
print('PASS: retained 1.0.8 fixture checksum, archive paths and extraction')
php=shutil.which('php8.3') or shutil.which('php')
code="define('CWP_BOOT',true);require $argv[1];echo json_encode(['schema'=>schema_statements('ci_'),'settings'=>default_settings()],JSON_THROW_ON_ERROR);"
result=subprocess.run([php,'-r',code,str(destination/'app/schema.php')],check=True,capture_output=True)
(root/'test-runtime/legacy-schema.json').write_bytes(result.stdout)
