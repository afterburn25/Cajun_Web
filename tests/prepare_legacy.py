"""Verify and extract the retained historical fixture, never a deployment source."""
from pathlib import Path, PurePosixPath
import os,hashlib, zipfile, subprocess, shutil, re, json, base64
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
php=os.environ.get('PHP_BINARY') or shutil.which('php8.3') or shutil.which('php')
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

ledger_baseline=root/'test-runtime/CajunWebPro-1.7.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.7.0/CajunWebPro-1.7.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    ledger_baseline.write_bytes(response.read())
assert hashlib.sha256(ledger_baseline.read_bytes()).hexdigest()=='cc6ef45967dc98ca9cdbd0cef1620bfda0a6d983428e782080ca1298a269bf9a'
with zipfile.ZipFile(ledger_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.7.0')
print('PASS: immutable released 1.7.0 package checksum, archive paths and extraction')

delivery_baseline=root/'test-runtime/CajunWebPro-1.8.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.8.0/CajunWebPro-1.8.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    delivery_baseline.write_bytes(response.read())
assert hashlib.sha256(delivery_baseline.read_bytes()).hexdigest()=='a3aa9d335e5cf39904c757aa94631d9bae80f47896be695819523849d8f2f190'
with zipfile.ZipFile(delivery_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.8.0')
print('PASS: immutable released 1.8.0 package checksum, archive paths and extraction')

activity_baseline=root/'test-runtime/CajunWebPro-1.9.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.9.0/CajunWebPro-1.9.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    activity_baseline.write_bytes(response.read())
assert hashlib.sha256(activity_baseline.read_bytes()).hexdigest()=='9a7b11f36cb3ca502637b59356c8163d274ad5e0a53fc42d88d1c0b0b9d11c23'
with zipfile.ZipFile(activity_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.9.0')
print('PASS: immutable released 1.9.0 package checksum, archive paths and extraction')

portfolio_baseline=root/'test-runtime/CajunWebPro-1.10.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.10.0/CajunWebPro-1.10.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    portfolio_baseline.write_bytes(response.read())
assert hashlib.sha256(portfolio_baseline.read_bytes()).hexdigest()=='a9003b041552c6759ade64a471fbfb940e192dcb5ad6d8bcb4d610902f154885'
with zipfile.ZipFile(portfolio_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.10.0')
print('PASS: immutable released 1.10.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.11.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.11.0/CajunWebPro-1.11.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='5bf60867bc47d14ac112bebcb30d02899a2ff13167d39bd01ca9e8dfb5c79f2c'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.11.0')
print('PASS: immutable released 1.11.0 package checksum, archive paths and extraction')
organized_update=root/'test-runtime/CajunWebPro-1.11.0-UPDATE.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.11.0/CajunWebPro-1.11.0-UPDATE.zip',timeout=60) as response:
    organized_update.write_bytes(response.read())
assert hashlib.sha256(organized_update.read_bytes()).hexdigest()=='927bb9f121076a0d627807a7809e099c551372a7d57acc71954980720462568d'
with zipfile.ZipFile(organized_update) as z:
    text=z.read('cwp-update-1.11.0/manifest.php').decode()
    manifest=json.loads(base64.b64decode(re.search(r"base64_decode\('([^']+)'\)",text).group(1)))
    (root/'test-runtime/organized-shims.json').write_text(json.dumps({n:base64.b64encode(b.encode()).decode() for n,b in manifest['shims'].items()}))
print('PASS: original 1.11.0 UPDATE checksum and compatibility bytes')

organized_baseline=root/'test-runtime/CajunWebPro-1.11.1-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.11.1/CajunWebPro-1.11.1-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='259c6960b84a64d1b51b0e400bd90943f84f84001844a9735133358f8d51625e'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.11.1')
print('PASS: immutable released 1.11.1 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.11.2-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.11.2/CajunWebPro-1.11.2-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='4b76f50483b9c7b1000b11d933c15a4e69f6f4c61b42dfdc96275c43c7e9c9b8'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.11.2')
print('PASS: immutable released 1.11.2 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.12.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.12.0/CajunWebPro-1.12.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='fd9dcb1bab34588588384c5eb99af5b06f67ec7a1ad1af7da86aa0532e6c453e'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.12.0')
print('PASS: immutable released 1.12.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.13.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.13.0/CajunWebPro-1.13.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='e2a24d324901ed1f1c43ecb3e3a72a8b6ffd2ff566b8418a2493c18700f1c6ee'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.13.0')
print('PASS: immutable released 1.13.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.14.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.14.0/CajunWebPro-1.14.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='051d33fe6c2e9bb524b2b065c0fa72ada2d00979a1526e0d2d1bcb8e6b559f62'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.14.0')
print('PASS: immutable released 1.14.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.15.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.15.0/CajunWebPro-1.15.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='c85379eee996a7d9dcbc012a0595dd7b6195889e6bde17ff90a855b15b6ccde5'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.15.0')
print('PASS: immutable released 1.15.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.16.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.16.0/CajunWebPro-1.16.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='dc9567005cb94bc65015d0980410d27cca1971db912662dfcd117b037267539f'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.16.0')
print('PASS: immutable released 1.16.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.17.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.17.0/CajunWebPro-1.17.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='eb4de16101800e13c837b6787507a3d7b9538811674cff987c4b74995a447ef3'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.17.0')
print('PASS: immutable released 1.17.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.18.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.18.0/CajunWebPro-1.18.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='17a4b84c5e2064b7e0f72c0d0fbec829bbf8046c82e9bff23619dd40f628f9e6'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.18.0')
print('PASS: immutable released 1.18.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.19.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.19.0/CajunWebPro-1.19.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='bbd0d67f850fcce7445080b305b61a92d61171d613e9e25057b419e19ce0b13f'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.19.0')
print('PASS: immutable released 1.19.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.20.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.20.0/CajunWebPro-1.20.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='9bf4504afbab260d619f3e6fc9e7dc65bb1ab84583e9e8afe0d35d58bbaaac48'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.20.0')
print('PASS: immutable released 1.20.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.21.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.21.0/CajunWebPro-1.21.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='307ed8f26db291efaac2a72d91683bb14e188080e5927507764b339c50a0b6b1'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.21.0')
print('PASS: immutable released 1.21.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.22.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.22.0/CajunWebPro-1.22.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='ba90fc84b4eb3b06e1e89ae46124cab1d759f1df10a0b9a9b1a53394796cf5fb'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.22.0')
print('PASS: immutable released 1.22.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.23.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.23.0/CajunWebPro-1.23.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='057a58c42e847fe68454c1a7dac47afad10c8c05f46de4301842654b356f7228'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.23.0')
print('PASS: immutable released 1.23.0 package checksum, archive paths and extraction')

organized_baseline=root/'test-runtime/CajunWebPro-1.24.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.24.0/CajunWebPro-1.24.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_baseline.write_bytes(response.read())
assert hashlib.sha256(organized_baseline.read_bytes()).hexdigest()=='aa7a84b4c0d1201a4eddf2fe68340b90d4860117ceff4d959d165e6feafa731e'
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
    z.extractall(root/'test-runtime/released-1.24.0')
print('PASS: immutable released 1.24.0 package checksum, archive paths and extraction')

# Retain the actual published 1.25 source for 1.26 upgrade/recovery acceptance.
organized_baseline=root/'test-runtime/CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
with urlopen('https://github.com/afterburn25/Cajun_Web/releases/download/v1.25.0/CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip',timeout=60) as response:
    organized_bytes=response.read(15843209)
assert len(organized_bytes)==15843208
assert hashlib.sha256(organized_bytes).hexdigest()=='bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39'
organized_baseline.write_bytes(organized_bytes)
organized_history=json.loads((root/'tools/legacy-release-files.json').read_text(encoding='utf-8'))['1.25.0']
assert organized_history['archive_sha256']==hashlib.sha256(organized_bytes).hexdigest()
with zipfile.ZipFile(organized_baseline) as z:
    assert z.testzip() is None
    names=z.namelist()
    assert len(names)==len(set(names))==len({name.casefold() for name in names})==2895
    assert set(names)==set(organized_history['files'])
    for item in z.infolist():
        path=PurePosixPath(item.filename)
        assert not item.is_dir() and not path.is_absolute() and path.as_posix()==item.filename
        assert not any(part in ('','.','..') for part in item.filename.split('/'))
        assert '\\' not in item.filename and ':' not in item.filename
        assert (item.external_attr>>16)&0o170000 != 0o120000
        assert hashlib.sha256(z.read(item)).hexdigest()==organized_history['files'][item.filename]
    z.extractall(root/'test-runtime/released-1.25.0')
print('PASS: immutable released 1.25.0 package checksum, exact member hashes, archive paths and extraction')
