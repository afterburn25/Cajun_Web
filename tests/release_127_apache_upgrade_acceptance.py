"""Published 1.26.1 -> 1.27.0 Apache root/subfolder and rewrite topology gate.

This complements the populated four-lane upgrade gate. It uses only loopback HTTP,
the disposable cwp_test database, synthetic records, and disabled PHP transports.
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

from acceptance_environment import php_matches, selected
from release_126_upgrade_cases import OWNER, fields
from release_127_archive import candidate_snapshot, extract_snapshot, published_snapshot
from release_127_loopback_session import LocalSession


ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT / 'test-runtime/release127-apache'
OUT = ROOT / 'qa/release127-apache'
BASELINE = ROOT / 'test-runtime/CajunWebPro-1.26.1-COMPLETE-UPLOAD.zip'
BASELINE_BYTES = 17_429_306
BASELINE_SHA = '7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3'
BASELINE_SOURCE = 'f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391'
CANDIDATE_VERSION = '1.27.0'
DISABLED_FUNCTIONS = ('curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,'
                      'stream_socket_server,stream_socket_sendto,socket_create,socket_create_pair,'
                      'socket_import_stream,socket_connect,socket_send,socket_sendto,socket_sendmsg,'
                      'socket_write,ftp_connect,ftp_ssl_connect,ftp_nb_connect,imap_open,ssh2_connect,'
                      'ldap_connect,pg_connect,dns_get_record,gethostbyaddr,gethostbyname,gethostbynamel,'
                      'checkdnsrr,snmpget,snmpgetnext,snmpwalk,mail,exec,shell_exec,system,passthru,'
                      'popen,proc_open,pcntl_exec')
PHP_OPTIONS = ['-d', 'allow_url_fopen=0', '-d', 'disable_functions=' + DISABLED_FUNCTIONS]


PROBE = r'''<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||getenv('CWP_TEST_HOST')!=='127.0.0.1'||count($argv)!==3)exit(2);
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOL))exit(2);
foreach(explode(',',getenv('CWP_TEST_DISABLED_FUNCTIONS')?:'') as $function)if($function!==''&&function_exists($function))exit(2);
$allowed=realpath(__DIR__);$root=realpath($argv[1]);$mode=$argv[2];
if(!$allowed||!$root||!str_starts_with($root,$allowed.DIRECTORY_SEPARATOR)||is_link($argv[1])||!in_array($mode,['seed','snapshot'],true))exit(2);
define('CWP_BOOT',true);define('CWP_ROOT',$root);require $root.'/includes/bootstrap.php';
$GLOBALS['cwp_config']=require $root.'/config.php';$config=config();
if($config['db']['name']!=='cwp_test'||$config['db']['host']!=='127.0.0.1'||!preg_match('/^ar127_(?:ron|son|roff|soff)_$/D',$config['db']['prefix']))exit(2);
$private=realpath($config['storage']);if(!$private||is_link($private)||str_starts_with($private,$root.DIRECTORY_SEPARATOR))exit(2);
$file=$private.'/release-127-apache-retained.txt';
if($mode==='seed'){
    if(setting('release_127_apache_retained')!==''||is_file($file)||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['fixture.release127.apache'])->fetchColumn()!==0)exit(2);
    write_private_file($file,CWP_GUARD.'SYNTHETIC APACHE UPGRADE RETENTION');
    try{db()->beginTransaction();save_setting('release_127_apache_retained','1');query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (1,?,?,?)',['fixture.release127.apache','Synthetic Apache retention witness; no transport',gmdate('Y-m-d H:i:s')]);db()->commit();}
    catch(Throwable $error){if(db()->inTransaction())db()->rollBack();@unlink($file);throw $error;}
}
$present=is_file($file)&&!is_link($file);
echo json_encode(['version'=>CWP_VERSION,'setting'=>setting('release_127_apache_retained')==='1','audit_rows'=>(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['fixture.release127.apache'])->fetchColumn(),'private_present'=>$present,'private_sha256'=>$present?hash_file('sha256',$file):null,'private_bytes'=>$present?filesize($file):null,'config_sha256'=>hash_file('sha256',$root.'/config.php'),'lock_sha256'=>hash_file('sha256',$root.'/.installed.php')],JSON_THROW_ON_ERROR);
'''


def launch(work, web, rewrite):
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 0))
        port = probe.getsockname()[1]
    version = selected()['php']
    modules = ['mpm_prefork', 'authz_core', 'authz_host', 'dir', 'mime', 'alias',
               'env', 'headers', 'autoindex'] + (['rewrite'] if rewrite else [])
    log = OUT / (work.name + '-apache.log')
    config = (f'ServerRoot "{work}"\nServerName localhost\nListen 127.0.0.1:{port}\n'
              f'PidFile "{work / "httpd.pid"}"\nErrorLog "{log}"\nLogLevel warn\n'
              f'User {getpass.getuser()}\nGroup {grp.getgrgid(os.getgid()).gr_name}\n'
              f'DocumentRoot "{web}"\nTypesConfig /etc/mime.types\n')
    config += ''.join('LoadModule ' + module + '_module /usr/lib/apache2/modules/mod_'
                      + module + '.so\n' for module in modules)
    config += (f'LoadModule php_module /usr/lib/apache2/modules/libphp{version}.so\n'
               f'PHPIniDir /etc/php/{version}/apache2\n'
               '<FilesMatch "\\.php$">\nSetHandler application/x-httpd-php\n</FilesMatch>\n'
               f'<Directory "{web}">\nAllowOverride All\nRequire all granted\n</Directory>\n')
    config_path = work / 'httpd.conf'
    config_path.write_text(config, encoding='utf-8')
    ini = work / 'php-conf'
    ini.mkdir()
    (ini / '99-release127.ini').write_text(
        'allow_url_fopen=0\ndisable_functions=' + DISABLED_FUNCTIONS + '\n'
        'opcache.enable=1\nopcache.validate_timestamps=1\n'
        'opcache.file_update_protection=0\nopcache.memory_consumption=256\n', encoding='utf-8')
    environment = {**os.environ,
                   'PHP_INI_SCAN_DIR': '/etc/php/' + version + '/apache2/conf.d:' + str(ini)}
    launcher = (OUT / (work.name + '-launcher.log')).open('wb')
    process = subprocess.Popen(['/usr/sbin/apache2', '-f', str(config_path), '-DFOREGROUND'],
                               stdout=launcher, stderr=subprocess.STDOUT, env=environment,
                               start_new_session=True)
    return process, launcher, 'http://127.0.0.1:' + str(port)


def submit(session, target, action, values=None):
    destination, data = fields(session.get(target, timeout=120), action)
    data.update(values or {})
    return session.post(destination, data=data, timeout=120)


def retained(before, after):
    return (before['setting'] is True and before['audit_rows'] == 1
            and before['private_present'] is True and before['private_bytes'] > 32
            and after['setting'] is True and after['audit_rows'] == 1
            and after['private_present'] is True
            and after['private_sha256'] == before['private_sha256']
            and after['private_bytes'] == before['private_bytes']
            and after['config_sha256'] == before['config_sha256']
            and after['lock_sha256'] == before['lock_sha256'])


def main():
    if os.name != 'posix' or os.geteuid() == 0:
        raise SystemExit('Actual Apache gate requires an unprivileged POSIX runner')
    if os.environ.get('CWP_TEST_DATABASE') != 'cwp_test' or os.environ.get('CWP_TEST_HOST') != '127.0.0.1':
        raise SystemExit('Disposable loopback database only')
    if os.environ.get('CWP_TEST_DISABLED_FUNCTIONS') != DISABLED_FUNCTIONS:
        raise SystemExit('Exact PHP outbound transport guard is required')
    php = Path(os.environ['PHP_BINARY'])
    if not php.is_absolute() or not php.is_file():
        raise SystemExit('Exact hosted PHP binary is required')
    source_sha = os.environ.get('CWP_SOURCE_SHA', '')
    if re.fullmatch(r'[0-9a-f]{40}', source_sha) is None:
        raise SystemExit('Exact candidate source identity is required')
    if (ROOT / 'VERSION').read_text(encoding='utf-8').strip() != CANDIDATE_VERSION:
        raise SystemExit('Expected 1.27.0 candidate')
    baseline_bytes = published_snapshot(BASELINE, BASELINE_BYTES, BASELINE_SHA)
    updates = list((ROOT / 'dist').glob('CajunWebPro-1.27.0-UPDATE.zip'))
    if len(updates) != 1 or RUNTIME.exists() or OUT.exists():
        raise SystemExit('Exact clean update and fresh Apache evidence directories are required')
    update_bytes = candidate_snapshot(updates[0])
    RUNTIME.mkdir(parents=True)
    OUT.mkdir(parents=True)
    probe_path = RUNTIME / 'probe.php'
    probe_path.write_text(PROBE, encoding='utf-8')
    checks, scopes = [], []
    failure = None

    def check(name, result):
        checks.append({'name': name, 'passed': bool(result)})
        (OUT / 'progress.json').write_text(json.dumps({'complete': False, 'checks': checks}, indent=2) + '\n', encoding='utf-8')
        if not result:
            raise AssertionError(name)

    def probe(installed, mode):
        result = subprocess.run([str(php), *PHP_OPTIONS, str(probe_path), str(installed), mode],
                                cwd=ROOT, capture_output=True, text=True, timeout=120)
        check(installed.parent.parent.name + ' CLI probe ' + mode + ' succeeds without transport',
              result.returncode == 0 and not result.stderr)
        return json.loads(result.stdout)

    try:
        for rewrite in (True, False):
            for subfolder in (False, True):
                label = ('subfolder' if subfolder else 'root') + ('-rewrite' if rewrite else '-no-rewrite')
                prefix = 'ar127_' + ('s' if subfolder else 'r') + ('on' if rewrite else 'off') + '_'
                work = RUNTIME / label
                web = work / 'web'
                installed = web / 'studio' if subfolder else web
                installed.mkdir(parents=True)
                extract_snapshot(baseline_bytes, installed)
                private_parent = work / 'private-parent'
                private_parent.mkdir(mode=0o700)
                process, launcher, origin = launch(work, web, rewrite)
                base = origin + ('/studio' if subfolder else '')
                session = LocalSession.from_origin(origin)
                scope_start = len(checks)
                try:
                    ready = None
                    for _ in range(80):
                        if process.poll() is not None:
                            raise AssertionError(label + ' Apache exited before readiness')
                        try:
                            ready = session.get(base + '/install.php', timeout=2)
                            break
                        except requests.RequestException:
                            time.sleep(.1)
                    check(label + ' published installer served by actual Apache',
                          ready is not None and ready.status_code == 200
                          and 'Apache/' in ready.headers.get('Server', ''))
                    runtime = installed / 'release127-runtime.php'
                    runtime.write_text("<?php header('Content-Type: application/json');$b=explode(',',getenv('CWP_TEST_DISABLED_FUNCTIONS')?:'');echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'url_fopen'=>(bool)ini_get('allow_url_fopen'),'disabled'=>ini_get('disable_functions'),'blocked'=>array_values(array_filter($b,'function_exists'))]);", encoding='utf-8')
                    try:
                        response = session.get(base + '/release127-runtime.php', timeout=10)
                        runtime_state = response.json()
                        check(label + ' mod_php runtime and transport boundary are exact',
                              response.status_code == 200 and runtime_state['sapi'] == 'apache2handler'
                              and php_matches(runtime_state['version'], selected()['php'])
                              and runtime_state['url_fopen'] is False
                              and runtime_state['disabled'] == DISABLED_FUNCTIONS
                              and runtime_state['blocked'] == [])
                    finally:
                        runtime.unlink()
                    response = submit(session, base + '/install.php', 'install', {
                        'db_host': '127.0.0.1', 'db_port': os.environ['CWP_TEST_PORT'],
                        'db_name': 'cwp_test', 'db_user': os.environ['CWP_TEST_USER'],
                        'db_password': os.environ['CWP_TEST_PASSWORD'], 'db_prefix': prefix,
                        'owner_name': 'Synthetic Apache upgrade owner',
                        'owner_email': 'organized@example.invalid', 'owner_password': OWNER,
                        'owner_confirm': OWNER, 'site_url': base, 'confirm_install': '1',
                        'seed': '1', 'preview': '1', 'private_parent': str(private_parent)})
                    result = response.json()
                    check(label + ' actual published 1.26.1 installer succeeds',
                          response.status_code == 200 and result.get('ok') is True)
                    check(label + ' installer selects external private storage',
                          Path(result['storage']).is_relative_to(private_parent)
                          and not Path(result['storage']).is_relative_to(web))
                    seeded = probe(installed, 'seed')
                    check(label + ' retained SQL and private witness originates on 1.26.1',
                          seeded['version'] == '1.26.1' and seeded['setting'] is True
                          and seeded['audit_rows'] == 1 and seeded['private_present'] is True)
                    before = probe(installed, 'snapshot')
                    login = submit(session, base + '/admin/index.php?view=login', 'login',
                                   {'email': 'organized@example.invalid', 'password': OWNER})
                    check(label + ' original owner signs in through Apache',
                          login.status_code == 200 and 'view=login' not in login.url)
                    extract_snapshot(update_bytes, installed)
                    updater = base + '/cwp-update-1.27.0/owner-update.php'
                    review = submit(session, updater, 'inspect', {'backup_confirm': '1'})
                    check(label + ' updater reviews exact published starting release',
                          review.status_code == 200 and 'Starting release: 1.26.1' in review.text)
                    applied = submit(session, review.url, 'apply', {'backup_confirm': '1'})
                    check(label + ' owner applies exact candidate UPDATE through Apache',
                          applied.status_code == 200 and '<h2>Complete</h2>' in applied.text)
                    after = probe(installed, 'snapshot')
                    check(label + ' update reports exact 1.27.0 candidate', after['version'] == CANDIDATE_VERSION)
                    check(label + ' SQL private config and installed guard are retained', retained(before, after))
                    admin = session.get(base + '/admin/index.php?view=dashboard', timeout=120)
                    portal = session.get(base + '/portal/index.php', timeout=120)
                    check(label + ' upgraded admin and portal routes remain served at installed base',
                          admin.status_code == 200 and portal.status_code == 200
                          and 'We could not complete this request' not in admin.text + portal.text)
                    route = session.get(base + ('/robots.txt' if rewrite else '/robots.php'), timeout=120)
                    check(label + (' rewrite route' if rewrite else ' nonrewrite route') + ' remains available',
                          route.status_code == 200 and 'User-agent:' in route.text)
                    evidence = {'scope': label, 'prefix': prefix, 'baseline_source': BASELINE_SOURCE,
                                'baseline_bytes': BASELINE_BYTES, 'baseline_sha256': BASELINE_SHA, 'source_sha': source_sha,
                                'before': before, 'after': after,
                                'checks': len(checks) - scope_start, 'passed': True}
                    evidence_path = OUT / (label + '.json')
                    evidence_path.write_text(json.dumps(evidence, indent=2) + '\n', encoding='utf-8')
                    scopes.append({'scope': label, 'checks': evidence['checks'], 'passed': True,
                                   'evidence_sha256': hashlib.sha256(evidence_path.read_bytes()).hexdigest()})
                finally:
                    session.close()
                    process.terminate()
                    try:
                        process.wait(timeout=15)
                    except subprocess.TimeoutExpired:
                        process.kill()
                        process.wait()
                    launcher.close()
                log = (OUT / (label + '-apache.log')).read_text(encoding='utf-8', errors='replace')
                check(label + ' Apache log has no PHP warning or error',
                      re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', log) is None)
        check('All four 1.26.1 Apache topology scopes pass',
              len(scopes) == 4 and {scope['scope'] for scope in scopes}
              == {'root-rewrite', 'subfolder-rewrite', 'root-no-rewrite', 'subfolder-no-rewrite'})
    except BaseException as error:
        failure = {'type': type(error).__name__, 'message': str(error)}
        (OUT / 'failure.txt').write_text(traceback.format_exc(), encoding='utf-8')
        raise
    finally:
        report = {'passed': failure is None and len(scopes) == 4,
                  'source_sha': source_sha, 'candidate_version': CANDIDATE_VERSION,
                  'baseline_source': BASELINE_SOURCE, 'baseline_bytes': BASELINE_BYTES,
                  'baseline_sha256': BASELINE_SHA,
                  'selected': selected(), 'scopes': scopes, 'checks': checks, 'failure': failure,
                  'scope': 'Actual Apache root/subfolder rewrite/no-rewrite 1.26.1 upgrade with synthetic retained data and disabled outbound PHP transports.'}
        (OUT / 'results.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
        print(json.dumps({'passed': report['passed'], 'scopes': len(scopes), 'checks': len(checks)}))


if __name__ == '__main__':
    main()
