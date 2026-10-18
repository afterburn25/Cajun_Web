"""Actual selected release runtime evidence; disposable CI databases only.

No application/provider credentials, stored customer values, or production target
is read. A report is evidence only for the lane/run that sealed it.
"""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess

VERSIONS = ('8.2', '8.3', '8.4')
DATABASES = ('mariadb-10.11', 'mysql-8.4')
REPORTS = {lane: lane + '-environment-results.json'
           for lane in ('build', 'native', 'recovery', 'apache')}
EXTENSIONS = {'pdo_mysql', 'gd', 'zip', 'curl', 'dom', 'mbstring', 'openssl', 'tokenizer'}
ROOT = Path(__file__).resolve().parents[1]


def selected(environ=os.environ):
    php = environ.get('CWP_PHP_VERSION', '8.3')
    database = environ.get('CWP_DATABASE', 'mariadb-10.11')
    if php not in VERSIONS or database not in DATABASES:
        raise ValueError('Unsupported acceptance environment')
    return {'php': php, 'database': database}


def php_matches(actual, expected):
    return isinstance(actual, str) and bool(re.fullmatch(re.escape(expected) + r'\.\d+(?:[-+][A-Za-z0-9.+~-]+)?', actual))


def database_matches(actual, expected):
    if not isinstance(actual, str):
        return False
    if expected == 'mariadb-10.11':
        return bool(re.match(r'^10\.11\.\d+', actual)) and 'MariaDB' in actual
    return bool(re.match(r'^8\.4\.\d+(?:[-+.]|$)', actual)) and 'MariaDB' not in actual


def validate(cli, minimal, database, lane, choice):
    if lane not in REPORTS:
        raise ValueError('Unsupported acceptance lane')
    checks = []
    def check(name, value):
        checks.append({'name': name, 'passed': bool(value)})
    check('Actual CLI PHP matches selected major and minor', php_matches(cli.get('version'), choice['php']))
    check('Actual CLI SAPI is cli', cli.get('sapi') == 'cli')
    check('Actual CLI has every application extension', isinstance(cli.get('extensions'), list) and EXTENSIONS <= set(cli['extensions']))
    check('Explicit runtime is an absolute executable path', isinstance(cli.get('binary'), str) and Path(cli['binary']).is_absolute())
    check('Minimal PHP matches selected major and minor', php_matches(minimal.get('version'), choice['php']))
    check('Minimal runtime loads PDO and tokenizer explicitly', minimal.get('pdo') is True and minimal.get('tokenizer') is True)
    check('Minimal runtime has no PDO drivers', minimal.get('drivers') == [])
    if lane in ('native', 'apache'):
        check('Actual database server matches selected family and version', database_matches(database, choice['database']))
    else:
        check('Database-free lane does not claim a database connection', database is None)
    return checks


def run_php(binary, code, flags=()):
    process = subprocess.run([binary, *flags, '-r', code], capture_output=True, text=True, check=False)
    if process.returncode or process.stderr.strip():
        raise RuntimeError('PHP runtime probe failed')
    return json.loads(process.stdout)


def write(path, report):
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    Path(path).write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')


def probe(lane):
    choice = selected()
    path = ROOT / 'qa' / REPORTS[lane]
    report = {'passed': False, 'selected': choice, 'lane': lane,
              'scope': 'Actual isolated CI runtime, not live provider or deployment evidence.', 'checks': []}
    try:
        binary = os.environ['PHP_BINARY']
        if not Path(binary).is_absolute() or not Path(binary).is_file():
            raise ValueError('Explicit PHP executable is unavailable')
        cli = run_php(binary, "echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'binary'=>PHP_BINARY,'extensions'=>get_loaded_extensions()]);")
        minimal = run_php(binary, "echo json_encode(['version'=>PHP_VERSION,'pdo'=>class_exists('PDO'),'tokenizer'=>function_exists('token_get_all'),'drivers'=>PDO::getAvailableDrivers()]);", ('-n', '-d', 'extension=tokenizer', '-d', 'extension=pdo'))
        database = None
        if lane in ('native', 'apache'):
            database = run_php(binary, "$p=new PDO('mysql:host='.getenv('CWP_TEST_HOST').';port='.getenv('CWP_TEST_PORT').';dbname='.getenv('CWP_TEST_DATABASE'),getenv('CWP_TEST_USER'),getenv('CWP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);echo json_encode($p->query('SELECT VERSION()')->fetchColumn());")
        report.update(cli=cli, minimal=minimal, database_version=database)
        report['checks'] = validate(cli, minimal, database, lane, choice)
        if lane == 'apache':
            # This is filled only by actual mod_php HTTP responses, for both modes.
            report['apache_http'] = {}
            report['checks'].append({'name': 'Actual rewrite and no-rewrite mod_php match selected version', 'passed': False})
        report['passed'] = all(check['passed'] for check in report['checks'])
        write(path, report)
        if not all(check['passed'] for check in report['checks'] if check['name'] != 'Actual rewrite and no-rewrite mod_php match selected version'):
            raise RuntimeError('Acceptance runtime mismatch')
    except Exception:
        report['passed'] = False
        report['error'] = 'Acceptance environment probe failed'
        if not report['checks']:
            report['checks'] = [{'name': 'Actual runtime probe completed', 'passed': False}]
        write(path, report)
        raise


def record_apache_runtime(label, body, root=ROOT):
    if label not in ('rewrite', 'no-rewrite'):
        raise ValueError('Unsupported Apache mode')
    path = root / 'qa' / REPORTS['apache']
    report = json.loads(path.read_text(encoding='utf-8'))
    if report['selected'] != selected() or report['lane'] != 'apache' or label in report['apache_http']:
        raise ValueError('Apache runtime evidence context mismatch')
    if not isinstance(body, dict) or set(body) != {'version', 'sapi'} or not php_matches(body['version'], report['selected']['php']) or body['sapi'] != 'apache2handler':
        raise ValueError('Actual Apache runtime mismatch')
    report['apache_http'][label] = body
    report['checks'][-1]['passed'] = set(report['apache_http']) == {'rewrite', 'no-rewrite'}
    report['passed'] = all(check['passed'] for check in report['checks'])
    write(path, report)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--lane', choices=REPORTS, required=True)
    probe(parser.parse_args().lane)
