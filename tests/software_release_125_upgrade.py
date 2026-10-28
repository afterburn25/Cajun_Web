"""Published v1.25.0 -> next release update/recovery acceptance.

This gate reuses organized_update_cases against the immutable published 1.25.0
COMPLETE package materialized by CI. It intentionally does not run while the
candidate still reports 1.25.0.
"""
from pathlib import Path
import getpass
import grp
import json
import os
import socket
import subprocess
import time

import requests

from organized_update_cases import run as organized_update
from release_version import VERSION

ROOT = Path(__file__).resolve().parents[1]
QA = ROOT / 'qa'
RUNTIME = ROOT / 'test-runtime/software-release125-gate'


def version_tuple(value):
    parts = value.strip().split('.')
    if len(parts) != 3 or any(not part.isdigit() for part in parts):
        raise AssertionError('Candidate version must be numeric MAJOR.MINOR.PATCH')
    return tuple(map(int, parts))


def launch(work, web, rewrite):
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 0))
        port = probe.getsockname()[1]
    modules = ['mpm_prefork', 'authz_core', 'authz_host', 'dir', 'mime',
               'alias', 'env', 'headers', 'autoindex']
    if rewrite:
        modules.append('rewrite')
    php_version = '8.3'
    config = (
        f'ServerRoot "{work}"\nServerName localhost\nListen 127.0.0.1:{port}\n'
        f'PidFile "{work / "httpd.pid"}"\nErrorLog "{QA / ("software-release125-" + work.name + ".log")}"\n'
        'LogLevel warn\n'
        f'User {getpass.getuser()}\nGroup {grp.getgrgid(os.getgid()).gr_name}\n'
        f'DocumentRoot "{web}"\nTypesConfig /etc/mime.types\n'
    )
    config += ''.join(
        'LoadModule ' + module + '_module /usr/lib/apache2/modules/mod_' + module + '.so\n'
        for module in modules
    )
    config += (
        f'LoadModule php_module /usr/lib/apache2/modules/libphp{php_version}.so\n'
        f'PHPIniDir /etc/php/{php_version}/apache2\n'
        '<FilesMatch "\\.php$">\nSetHandler application/x-httpd-php\n</FilesMatch>\n'
        f'<Directory "{web}">\nAllowOverride All\nRequire all granted\n</Directory>\n'
    )
    config_path = work / 'httpd.conf'
    config_path.write_text(config, encoding='utf-8')
    ini = work / 'php-conf'
    ini.mkdir()
    (ini / '99-release.ini').write_text(
        'opcache.enable=1\nopcache.validate_timestamps=0\nopcache.file_update_protection=0\n'
        'opcache.memory_consumption=512\nopcache.max_accelerated_files=100000\n', encoding='utf-8')
    env = {**os.environ, 'PHP_INI_SCAN_DIR': f'/etc/php/{php_version}/apache2/conf.d:' + str(ini)}
    with (QA / ('software-release125-' + work.name + '-launcher.log')).open('wb') as launcher:
        proc = subprocess.Popen(
            ['/usr/sbin/apache2', '-f', str(config_path), '-DFOREGROUND'],
            stdout=launcher, stderr=subprocess.STDOUT, env=env, start_new_session=True,
        )
    return proc, f'http://127.0.0.1:{port}'


def main():
    if version_tuple(VERSION) <= (1, 25, 0):
        raise AssertionError('Published-v1.25 upgrade gate requires candidate version greater than 1.25.0')
    if os.environ.get('CWP_TEST_DATABASE') != 'cwp_test':
        raise AssertionError('Use disposable cwp_test database only')
    baseline = ROOT / 'test-runtime/released-1.25.0'
    if not baseline.is_dir() or not (baseline / 'install.php').is_file():
        raise AssertionError('Immutable published v1.25.0 baseline was not materialized')
    updates = list((ROOT / 'dist').glob(f'CajunWebPro-{VERSION}-UPDATE.zip'))
    if len(updates) != 1:
        raise AssertionError('Exactly one candidate UPDATE package is required')
    if RUNTIME.exists():
        raise AssertionError('Use a new test-runtime directory; preserve populated upgrade evidence')
    RUNTIME.mkdir(parents=True)
    QA.mkdir(exist_ok=True)
    checks = []

    def check(name, value):
        checks.append({'name': name, 'passed': bool(value)})
        (QA / 'software-release125-progress.json').write_text(
            json.dumps({'complete': False, 'checks': checks}, indent=2) + '\n', encoding='utf-8')
        if not value:
            raise AssertionError(name)

    php = os.environ.get('PHP_BINARY', '/usr/bin/php8.3')
    scopes = []
    complete = False
    try:
        for rewrite in (True, False):
            for at_root in (True, False):
                label = ('root' if at_root else 'subfolder') + ('-rewrite' if rewrite else '-no-rewrite')
                start = len(checks)
                work = RUNTIME / label
                web = work / 'web'
                web.mkdir(parents=True)
                probe = web / 'upgrade-gate-ready.txt'
                probe.write_text('upgrade-gate-ready', encoding='utf-8')
                proc, base = launch(work, web, rewrite)
                try:
                    ready = False
                    for _ in range(80):
                        if proc.poll() is not None:
                            raise AssertionError('Apache exited before v1.25 gate could run: ' + label)
                        try:
                            response = requests.get(base + '/' + probe.name, timeout=2)
                            if response.status_code == 200 and response.text == 'upgrade-gate-ready':
                                ready = True
                                break
                        except requests.RequestException:
                            time.sleep(.1)
                    check(label + ' Apache started', ready)
                    probe.unlink()
                    prefix = 'sw125_' + ('r' if at_root else 's') + ('on_' if rewrite else 'off_')
                    organized_update(web, base, check, QA, php, 'software-1.25 ' + label,
                                     prefix, '1.25.0', at_root=at_root)
                    scopes.append({'label': label, 'checks': len(checks) - start})
                finally:
                    proc.terminate()
                    try:
                        proc.wait(timeout=10)
                    except subprocess.TimeoutExpired:
                        proc.kill()
                        proc.wait()
        complete = True
    finally:
        report = {
            'passed': complete and len(scopes) == 4 and all(item['passed'] for item in checks),
            'complete': complete,
            'baseline': 'published v1.25.0 COMPLETE-UPLOAD',
            'candidate_version': VERSION,
            'scope': 'Published baseline update/recovery with synthetic local data; no live provider, mail or payment transport',
            'scopes': scopes,
            'checks': checks,
        }
        (QA / 'software-release125-upgrade.json').write_text(
            json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'published v1.25 software release-gate checks')


if __name__ == '__main__':
    main()
