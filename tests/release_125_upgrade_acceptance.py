"""Additive published1.24 -> frozen1.25 real Apache/Chromium upgrade matrix.

One invocation runs root/subfolder x rewrite on/off on the selected PHP/database.
No existing acceptance fixture or package is changed. Requires an unprivileged CI account.
"""
from pathlib import Path
import getpass
import grp
import hashlib
import json
import os
import re
import socket
import subprocess
import time
import traceback

import requests
from playwright.sync_api import sync_playwright
from acceptance_environment import selected, php_matches
from release_125_upgrade_cases import BASELINE_SHA, BASELINE_SOURCE, run, unpack

ROOT = Path(__file__).resolve().parents[1]
QA = ROOT / 'qa'
RUNTIME = ROOT / 'test-runtime/release125-upgrade'


def launch(work, web, rewrite):
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 0)); port = probe.getsockname()[1]
    version = selected()['php']
    modules = ['mpm_prefork', 'authz_core', 'authz_host', 'dir', 'mime', 'alias', 'env', 'headers', 'autoindex']
    if rewrite:
        modules.append('rewrite')
    log = QA / ('apache-release125-' + work.name + '.log')
    config = (f'ServerRoot "{work}"\nServerName localhost\nListen 127.0.0.1:{port}\n'
              f'PidFile "{work / "httpd.pid"}"\nErrorLog "{log}"\nLogLevel warn\n'
              f'User {getpass.getuser()}\nGroup {grp.getgrgid(os.getgid()).gr_name}\n'
              f'DocumentRoot "{web}"\nTypesConfig /etc/mime.types\n')
    config += ''.join('LoadModule ' + m + '_module /usr/lib/apache2/modules/mod_' + m + '.so\n' for m in modules)
    config += (f'LoadModule php_module /usr/lib/apache2/modules/libphp{version}.so\nPHPIniDir /etc/php/{version}/apache2\n'
               '<FilesMatch "\\.php$">\nSetHandler application/x-httpd-php\n</FilesMatch>\n'
               f'<Directory "{web}">\nAllowOverride All\nRequire all granted\n</Directory>\n')
    config_path = work / 'httpd.conf'; config_path.write_text(config, encoding='utf-8')
    ini = work / 'php-conf'; ini.mkdir()
    (ini / '99-release.ini').write_text('opcache.enable=1\nopcache.validate_timestamps=0\nopcache.file_update_protection=0\n'
                                       'opcache.memory_consumption=512\nopcache.max_accelerated_files=100000\n', encoding='utf-8')
    env = {**os.environ, 'PHP_INI_SCAN_DIR': '/etc/php/' + version + '/apache2/conf.d:' + str(ini)}
    launcher = open(QA / ('apache-release125-' + work.name + '-launcher.log'), 'wb')
    proc = subprocess.Popen(['/usr/sbin/apache2', '-f', str(config_path), '-DFOREGROUND'],
                            stdout=launcher, stderr=subprocess.STDOUT, env=env, start_new_session=True)
    return proc, launcher, 'http://127.0.0.1:' + str(port)


def main():
    assert os.geteuid() != 0, 'Run the real installer and Apache worker as an unprivileged user.'
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test'
    php = os.environ['PHP_BINARY']
    assert Path(php).is_absolute() and Path(php).is_file()
    baseline = ROOT / 'test-runtime/CajunWebPro-1.24.0-COMPLETE-UPLOAD.zip'
    assert hashlib.sha256(baseline.read_bytes()).hexdigest() == BASELINE_SHA
    assert not RUNTIME.exists(), 'Use a new test-runtime directory; never reset a populated upgrade prefix.'
    RUNTIME.mkdir(parents=True); QA.mkdir(parents=True, exist_ok=True)
    checks = []; scopes = []; failure = None; apache_http = {}; evidence = {}

    def check(name, value):
        checks.append({'name': name, 'passed': bool(value)})
        (QA / 'apache-release125-progress.json').write_text(json.dumps({'complete': False, 'observations': checks}, indent=2) + '\n', encoding='utf-8')
        if not value:
            raise AssertionError(name)

    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch()
            try:
                for rewrite in [True, False]:
                    for subfolder in [False, True]:
                        label = ('subfolder' if subfolder else 'root') + ('-rewrite' if rewrite else '-no-rewrite')
                        prefix = 'r125_' + ('s' if subfolder else 'r') + ('on' if rewrite else 'off') + '_'
                        work = RUNTIME / label; web = work / 'web'; installed = web / 'subfolder' if subfolder else web
                        installed.mkdir(parents=True)
                        # Extract once before Apache starts so its startup readiness probes never see an empty fixture.
                        unpack(baseline, installed, BASELINE_SHA)
                        proc, launcher, origin = launch(work, web, rewrite)
                        base = origin + ('/subfolder' if subfolder else '')
                        try:
                            ready = None
                            for _ in range(60):
                                if proc.poll() is not None:
                                    raise AssertionError('Apache exited before serving the original package: ' + label)
                                try:
                                    ready = requests.get(base + '/install.php', timeout=2)
                                    break
                                except requests.RequestException:
                                    time.sleep(.1)
                            check(label + ' original installer is served by actual Apache', ready is not None
                                  and ready.status_code == 200 and 'Apache/' in ready.headers.get('Server', ''))
                            probe = installed / 'release-runtime-probe.php'
                            probe.write_text("<?php header('Content-Type: application/json');echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI]);", encoding='utf-8')
                            try:
                                response = requests.get(base + '/release-runtime-probe.php', timeout=10)
                                body = response.json()
                                check(label + ' actual mod_php matches selected release runtime', response.status_code == 200
                                      and set(body) == {'version', 'sapi'} and php_matches(body['version'], selected()['php'])
                                      and body['sapi'] == 'apache2handler')
                                apache_http[label] = body
                            finally:
                                probe.unlink()
                            result = run(ROOT, installed, base, prefix, php, check, QA, browser)
                            scopes.append({'scope': label, 'prefix': prefix, 'passed': True, 'checks': len(result)})
                        finally:
                            proc.terminate()
                            try: proc.wait(timeout=15)
                            except subprocess.TimeoutExpired: proc.kill(); proc.wait()
                            launcher.close()
                        log = QA / ('apache-release125-' + label + '.log')
                        check(label + ' Apache reports no PHP runtime warning or error',
                              re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', log.read_text(encoding='utf-8', errors='replace')) is None)
                check('All four real published upgrade URL/rewrite combinations finished', len(scopes) == 4 and all(s['passed'] for s in scopes))
                from release_125_retention import validate
                prefixes = ['r125_ron_', 'r125_son_', 'r125_roff_', 'r125_soff_']
                for prefix in prefixes:
                    for stage in ['original', 'upgrade']:
                        name = 'apache-release125-' + prefix + '-' + stage + '-retention.json'
                        path = QA / name; obj = json.loads(path.read_text(encoding='utf-8'))
                        check(prefix + ' ' + stage + ' snapshot retains immutable original identity',
                              obj['baseline_source'] == BASELINE_SOURCE and obj['baseline_sha256'] == BASELINE_SHA)
                        if stage == 'original':
                            validate(obj['snapshot']); check(prefix + ' initial snapshot is from installed1.24', obj['snapshot']['version'] == '1.24.0')
                        else:
                            validate(obj['initial']); validate(obj['final'])
                            check(prefix + ' final snapshot is from updated1.25', obj['initial']['version'] == '1.24.0' and obj['final']['version'] == '1.25.0')
                        evidence[name] = {'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
                    for size in ['Desktop', 'Mobile']:
                        name = 'apache-release125-' + prefix + '-Upgraded-Client-' + size + '.png'; path = QA / name
                        check(prefix + ' actual ' + size + ' capture is a PNG', path.read_bytes().startswith(b'\x89PNG\r\n\x1a\n'))
                        evidence[name] = {'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
                check('Eight retained snapshots and eight actual upgraded-client captures are present', len(evidence) == 16)
            finally:
                browser.close()
    except BaseException as error:
        failure = {'type': type(error).__name__, 'message': str(error)}
        (QA / 'apache-release125-failure.log').write_text(traceback.format_exc(), encoding='utf-8')
        raise
    finally:
        (QA / 'apache-release125-results.json').write_text(json.dumps({'passed': failure is None and len(scopes) == 4,
            'baseline_source': BASELINE_SOURCE, 'baseline_sha256': BASELINE_SHA,
            'candidate_version': '1.25.0', 'selected': selected(), 'apache_http': apache_http,
            'scope': 'Actual published baseline; synthetic clients/data; no live provider, mail or payment',
            'cases': scopes, 'checks': checks, 'evidence': evidence, 'failure': failure}, indent=2) + '\n', encoding='utf-8')


if __name__ == '__main__':
    main()
