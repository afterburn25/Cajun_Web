"""Verify and extract the retained historical fixture, never a deployment source."""
from pathlib import Path, PurePosixPath
import hashlib, zipfile, subprocess, shutil
from urllib.request import urlopen
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
# Download the immutable released package for actual 1.1.0 -> current update acceptance.
# Never adopt bytes solely on a release name: verify the retained, published SHA-256 first.
current_baseline=root/'test-runtime/CajunWebPro-1.1.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.1.0/CajunWebPro-1.1.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    current_baseline.write_bytes(response.read())
assert hashlib.sha256(current_baseline.read_bytes()).hexdigest()=='52da8a009ace9643cf65d678ca2de6d9612b01a24be4acd2634668d5de8423cd'
with zipfile.ZipFile(current_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.1.0')
print('PASS: immutable released 1.1.0 package checksum, archive paths and extraction')
support_baseline=root/'test-runtime/CajunWebPro-1.2.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.2.0/CajunWebPro-1.2.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    support_baseline.write_bytes(response.read())
assert hashlib.sha256(support_baseline.read_bytes()).hexdigest()=='f3da85146c316da6f94cef5b2f0e7aba8b2526f0a683218da85d5215c6d1d32e'
with zipfile.ZipFile(support_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.2.0')
print('PASS: immutable released 1.2.0 package checksum, archive paths and extraction')
catalog_baseline=root/'test-runtime/CajunWebPro-1.3.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.3.0/CajunWebPro-1.3.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    catalog_baseline.write_bytes(response.read())
assert hashlib.sha256(catalog_baseline.read_bytes()).hexdigest()=='bb9bdf041e8bb158d79edb5564276e061f9a0d5e11f79df839796b19f7e48dcb'
with zipfile.ZipFile(catalog_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.3.0')
print('PASS: immutable released 1.3.0 package checksum, archive paths and extraction')

payment_baseline=root/'test-runtime/CajunWebPro-1.4.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.4.0/CajunWebPro-1.4.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    payment_baseline.write_bytes(response.read())
assert hashlib.sha256(payment_baseline.read_bytes()).hexdigest()=='5d67d3349cf2e48be5a693f9662a2b6f4bbb80c829f03d5ccb5de84a2e678bef'
with zipfile.ZipFile(payment_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.4.0')
print('PASS: immutable released 1.4.0 package checksum, archive paths and extraction')

trial_baseline=root/'test-runtime/CajunWebPro-1.5.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.5.0/CajunWebPro-1.5.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    trial_baseline.write_bytes(response.read())
assert hashlib.sha256(trial_baseline.read_bytes()).hexdigest()=='99c4c76a369154d60c142d3af07fb35d57393ee9205fb259dee5a05851854e98'
with zipfile.ZipFile(trial_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.5.0')
print('PASS: immutable released 1.5.0 package checksum, archive paths and extraction')

notification_baseline=root/'test-runtime/CajunWebPro-1.6.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.6.0/CajunWebPro-1.6.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    notification_baseline.write_bytes(response.read())
assert hashlib.sha256(notification_baseline.read_bytes()).hexdigest()=='b8c25c79f60616a30685490fd1185d2964759091efbb31dc83e57819b232b7ec'
with zipfile.ZipFile(notification_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.6.0')
print('PASS: immutable released 1.6.0 package checksum, archive paths and extraction')
