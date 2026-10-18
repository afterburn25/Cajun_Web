"""Portable boundary regressions; no PHP, database, network or CI execution."""
import copy
import json
from pathlib import Path
from unittest.mock import patch
import acceptance_environment as runtime


def run(artifacts, check, deny, tmp):
    for php in ('8.2', '8.3', '8.4'):
        for database, observed in [('mariadb-10.11', '10.11.15-MariaDB-ubu2404'), ('mysql-8.4', '8.4.8')]:
            choice = runtime.selected({'CWP_PHP_VERSION': php, 'CWP_DATABASE': database})
            cli = {'version': php + '.30', 'sapi': 'cli', 'binary': str((tmp/'php').resolve()), 'extensions': sorted(runtime.EXTENSIONS)}
            minimal = {'version': php + '.30', 'pdo': True, 'tokenizer': True, 'drivers': []}
            check('Runtime validator accepts exact supported pair ' + php + '/' + database,
                  all(c['passed'] for c in runtime.validate(cli, minimal, observed, 'native', choice)))
    deny('Unsupported PHP selector is refused', lambda: runtime.selected({'CWP_PHP_VERSION': '8.4/../../'}))
    deny('Unsupported database selector is refused', lambda: runtime.selected({'CWP_DATABASE': 'mysql:latest'}))
    choice = {'php': '8.4', 'database': 'mysql-8.4'}
    cli = {'version': '8.4.8', 'sapi': 'cli', 'binary': str((tmp/'php').resolve()), 'extensions': sorted(runtime.EXTENSIONS)}
    minimal = {'version': '8.4.8', 'pdo': True, 'tokenizer': True, 'drivers': []}
    for label, delta in [('wrong version', {'version': '8.3.99'}), ('prefix version', {'version': '8.40.1'}), ('web SAPI', {'sapi': 'apache2handler'}), ('missing extension', {'extensions': ['pdo_mysql']}), ('relative binary', {'binary': 'php'})]:
        wrong = dict(cli, **delta)
        check('CLI evidence refuses ' + label, not all(c['passed'] for c in runtime.validate(wrong, minimal, '8.4.8', 'native', choice)))
    for label, delta in [('wrong version', {'version': '8.2.30'}), ('missing PDO', {'pdo': False}), ('missing tokenizer', {'tokenizer': False}), ('unexpected PDO driver', {'drivers': ['mysql']})]:
        check('Minimal evidence refuses ' + label, not all(c['passed'] for c in runtime.validate(cli, dict(minimal, **delta), '8.4.8', 'native', choice)))
    for wrong in ('10.11.15-MariaDB', '8.3.1', '8.40.1', None):
        check('Database evidence refuses mismatched server ' + str(wrong), not all(c['passed'] for c in runtime.validate(cli, minimal, wrong, 'native', choice)))
    check('Database-free lane refuses invented database connection', not all(c['passed'] for c in runtime.validate(cli, minimal, '8.4.8', 'build', choice)))
    environment_files = {'native-environment-results.json': 'native-browser', 'recovery-environment-results.json': 'recovery', 'apache-environment-results.json': 'apache'}
    for filename, owner in environment_files.items():
        check('Runtime report has exact partition owner ' + owner,
              all(artifacts.evidence_allowed('qa/' + filename, lane) == (lane == owner) for lane in artifacts.LANES))
    # Actual report-update path must require both HTTP modes; it may not silently
    # reuse CLI evidence, a wrong module, a duplicate mode or another environment.
    root = tmp/'runtime-evidence'; path = root/'qa'/runtime.REPORTS['apache']
    base = {'passed': False, 'selected': choice, 'lane': 'apache', 'apache_http': {},
            'checks': [{'name': 'CLI validation', 'passed': True}, {'name': 'Actual rewrite and no-rewrite mod_php match selected version', 'passed': False}]}
    runtime.write(path, base)
    with patch.dict(runtime.os.environ, {'CWP_PHP_VERSION': '8.4', 'CWP_DATABASE': 'mysql-8.4'}):
        for value in ({'version': '8.3.99', 'sapi': 'apache2handler'}, {'version': '8.4.8', 'sapi': 'cli'}, {'version': '8.4.8', 'sapi': 'apache2handler', 'extra': True}):
            deny('Actual HTTP evidence refuses wrong module or shape ' + json.dumps(value, sort_keys=True), lambda value=value: runtime.record_apache_runtime('rewrite', value, root))
        runtime.record_apache_runtime('rewrite', {'version': '8.4.8', 'sapi': 'apache2handler'}, root)
        check('One Apache mode remains incomplete', json.loads(path.read_text())['passed'] is False)
        deny('Duplicate Apache mode refuses evidence reuse', lambda: runtime.record_apache_runtime('rewrite', {'version': '8.4.8', 'sapi': 'apache2handler'}, root))
        runtime.record_apache_runtime('no-rewrite', {'version': '8.4.8', 'sapi': 'apache2handler'}, root)
        check('Both exact Apache HTTP modes complete runtime report', json.loads(path.read_text())['passed'] is True)
    runtime.write(path, base)
    with patch.dict(runtime.os.environ, {'CWP_PHP_VERSION': '8.2', 'CWP_DATABASE': 'mysql-8.4'}):
        deny('Changed selected runtime cannot reuse Apache report', lambda: runtime.record_apache_runtime('rewrite', {'version': '8.2.30', 'sapi': 'apache2handler'}, root))
