"""Installed published1.25 -> exact current nameserver changes, with actual owner UPDATE/recovery.

Imports bounded immutable helpers from the original readonly producer; never runs
or edits its main. New feature history is created only after the actual target update.
"""
from pathlib import Path
import json
import os
import re
import subprocess
import time
from registered_nameservers_upgrade import (BASELINE_SOURCE, BASELINE_SHA, BASELINE_NAME,
    BASELINE_MEMBERS, baseline_inputs, target_inputs, digest, fresh_path, code_matches)

ROOT = Path(__file__).resolve().parents[1]
OWNER = 'Synthetic nameserver changes upgrade owner passphrase'
PREFIX = 'rnscu_'
REPORT = 'apache-registered-nameserver-changes-upgrade-results.json'
FEATURE_PATHS = ('app/providers/registered_nameserver_changes.php',
                 'app/providers/registered_nameserver_changes_storage.php',
                 'database/schema/registered_nameserver_changes_schema.php',
                 'app/providers/registered_nameserver_changes_views.php')


def changes_target_inputs(repo):
    archive, version, source, files = target_inputs(repo)
    for name in FEATURE_PATHS:
        actual = (repo / 'site' / name).read_bytes()
        original = subprocess.check_output(['git', 'show', source + ':site/' + name], cwd=repo)
        assert actual == original or actual.replace(b'\r\n', b'\n') == original.replace(b'\r\n', b'\n')
        assert files['cwp-update-' + version + '/site/' + name] == digest(repo / 'site' / name)
    return archive, version, source, files


def terminal_counter_cell(before):
    # One explicit real terminal INSERT increments its independent counter.
    # Identify the original setting by hashed exact name, never loosen all settings.
    import hashlib
    h = lambda value: hashlib.sha256(json.dumps(value, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
    rows = before['tables']['settings']['rows']
    keys = [key for key, row in rows.items() if row.get('name') == h('registered_nameserver_changes_terminal_count')]
    assert len(keys) == 1 and rows[keys[0]]['value'] == h('1')
    return [('settings', keys[0], 'value')]



def terminal_recovery_retained(before, after):
    from release_125_retention import retained
    from copy import deepcopy
    import hashlib
    cells = terminal_counter_cell(before)
    retained(before, after, changed_cells=cells)
    assert set(before['tables']) == set(after['tables']) and before['private_files'] == after['private_files']
    key = cells[0][1]
    assert after['tables']['settings']['rows'][key]['value'] == hashlib.sha256(b'"2"').hexdigest()
    old, new = deepcopy(before), deepcopy(after)
    old['tables']['settings']['rows'][key]['value'] = new['tables']['settings']['rows'][key]['value']
    for table in ('provider_nameserver_change_terminals', 'audit'):
        assert new['tables'][table]['count'] == old['tables'][table]['count'] + 1
        added = set(new['tables'][table]['rows']) - set(old['tables'][table]['rows'])
        assert len(added) == 1
        del new['tables'][table]['rows'][added.pop()]
        new['tables'][table]['count'] -= 1
    assert old == new, 'Local recovery changed unrelated history or keys'
    return True


def main():
    assert os.name == 'posix' and os.geteuid() != 0
    assert os.environ.get('CWP_TEST_DATABASE') == 'cwp_test' and os.environ.get('CWP_TEST_HOST') == '127.0.0.1'
    import requests
    from acceptance_environment import selected, php_matches
    from release_125_upgrade_acceptance import launch
    from release_125_upgrade_cases import fields, unpack
    from release_125_retention import retained, exact_business
    php = os.environ['PHP_BINARY']
    assert Path(php).is_absolute() and Path(php).is_file()
    baseline, baseline_files = baseline_inputs(ROOT)
    update, version, source, update_files = changes_target_inputs(ROOT)
    runtime = ROOT / 'test-runtime/registered-nameserver-changes-upgrade'
    qa = ROOT / 'qa'
    fresh_path(runtime, ROOT)
    outputs = [REPORT, 'apache-registered-nameserver-changes-upgrade-retention.json',
               'apache-release125-rnsc-upgrade.log', 'apache-release125-rnsc-upgrade-launcher.log']
    for name in outputs:
        fresh_path(qa / name, ROOT)
    qa.mkdir(exist_ok=True)
    work = runtime / 'rnsc-upgrade'
    web = work / 'web'
    installed = web / 'subfolder'
    installed.mkdir(parents=True)
    unpack(baseline, installed, BASELINE_SHA)
    checks, phases, runtime_fact, helper_failures = [], {}, {}, []
    failure = None
    proc = launcher = None

    def check(name, value):
        assert isinstance(value, bool)
        assert not any(item['name'] == name for item in checks), 'Duplicate named result'
        checks.append({'name': name, 'passed': value})
        if not value:
            raise AssertionError(name)

    def command(mode, phase=None):
        result = subprocess.run([php, str(ROOT / 'tests/registered_nameserver_changes_upgrade_fixture.php'),
                                 str(installed), mode, str(web)], capture_output=True, text=True, timeout=240)
        assert len(result.stdout) <= 16 * 1024 * 1024 and len(result.stderr) <= 65536
        body = json.loads(result.stdout)
        if body.get('passed') is not True:
            detail = body.get('failure', {})
            safe = {'mode': mode, 'exit_code': result.returncode}
            for key, pattern in [('class', r'[A-Za-z_\\][A-Za-z0-9_\\]{0,120}'),
                                 ('file', r'[A-Za-z0-9_.-]{1,120}'), ('sqlstate', r'[A-Z0-9]{5}')]:
                if isinstance(detail.get(key), str) and re.fullmatch(pattern, detail[key]):
                    safe[key] = detail[key]
            if type(detail.get('line')) is int and 0 < detail['line'] <= 100000:
                safe['line'] = detail['line']
            # The helper reports named literal checks; surface only the failed names.
            failed_names = [item['name'] for item in body.get('checks', [])
                            if type(item) is dict and item.get('passed') is False and type(item.get('name')) is str]
            if 0 < len(failed_names) <= 64 and all(len(name) <= 200 and name.isascii() for name in failed_names):
                safe['failed_checks'] = failed_names
            helper_failures.append(safe)
        assert result.returncode == 0 and not result.stderr, ('Installed helper failed', mode, result.returncode)
        assert body['passed'] is True
        for item in body['checks']:
            check((phase or mode) + ': ' + item['name'], item['passed'])
        return body

    try:
        command('preflight')
        proc, launcher, origin = launch(work, web, True)
        base = origin + '/subfolder'
        ready = None
        for _ in range(60):
            assert proc.poll() is None, 'Apache stopped before installation'
            try:
                ready = requests.get(base + '/install.php', timeout=2)
                break
            except requests.RequestException:
                time.sleep(.1)
        check('Published installer served by actual Apache', ready is not None and ready.status_code == 200
              and 'Apache/' in ready.headers.get('Server', ''))
        probe = installed / 'rnsc-runtime-probe.php'
        probe.write_text("<?php header('Content-Type: application/json');echo json_encode(['version'=>PHP_VERSION,'sapi'=>PHP_SAPI]);", encoding='utf-8')
        try:
            response = requests.get(base + '/rnsc-runtime-probe.php', timeout=10)
            runtime_fact = response.json()
            check('Actual mod_php matches selected runtime', response.status_code == 200
                  and set(runtime_fact) == {'version', 'sapi'} and runtime_fact['sapi'] == 'apache2handler'
                  and php_matches(runtime_fact['version'], selected()['php']))
        finally:
            probe.unlink()
        session = requests.Session()
        target, form = fields(session.get(base + '/install.php', timeout=30), 'install')
        form.update({'db_host': '127.0.0.1', 'db_port': os.environ['CWP_TEST_PORT'], 'db_name': 'cwp_test',
                     'db_user': os.environ['CWP_TEST_USER'], 'db_password': os.environ['CWP_TEST_PASSWORD'],
                     'db_prefix': PREFIX, 'owner_name': 'Nameserver changes upgrade owner', 'owner_email': 'rnsc-upgrade@example.invalid',
                     'owner_password': OWNER, 'owner_confirm': OWNER, 'site_url': base,
                     'confirm_install': '1', 'seed': '1', 'preview': '1'})
        response = session.post(target, data=form, timeout=120)
        body = response.json()
        check('Actual immutable published 1.25 installation completes', response.status_code == 200 and body.get('ok') is True)
        check('Private store is outside the served tree', not Path(body['storage']).resolve().is_relative_to(web.resolve()))
        check('Published code bytes match before updating', code_matches(installed, baseline_files))
        phases['baseline'] = command('snapshot')['snapshot']
        check('Published baseline has no new nameserver observation or change history', not any(n.startswith(('registered_nameserver_', 'provider_nameserver_change_')) for n in phases['baseline']['tables']))
        config = digest(installed / 'config.php')
        guard = digest(installed / '.installed.php')
        unpack(update, installed)
        updater = base + '/cwp-update-' + version + '/owner-update.php'

        def action(which):
            target, form = fields(session.get(updater, timeout=120), which)
            form.update({'email': 'rnsc-upgrade@example.invalid', 'owner_password': OWNER, 'backup_confirm': '1'})
            response = session.post(target, data=form, timeout=120)
            expected = '<h2>Complete</h2>' if which == 'apply' else '<h2>Rolled-back</h2>'
            check('Owner updater ' + which + ' action ' + str(sum(c['name'].startswith('Owner updater') for c in checks) + 1),
                  response.status_code == 200 and expected in response.text and 'class="error"' not in response.text)
            check('Configuration and installed guard retained after action ' + str(len(checks)),
                  digest(installed / 'config.php') == config and digest(installed / '.installed.php') == guard)

        action('apply')
        check('First update serves exact current build bytes', code_matches(installed, update_files, True, version))
        phases['updated_unprepared'] = command('snapshot')['snapshot']
        check('Update enables neither observation nor change schema', not any(n.startswith(('registered_nameserver_', 'provider_nameserver_change_')) for n in phases['updated_unprepared']['tables']))
        check('First update retains original business and private data', retained(phases['baseline'], phases['updated_unprepared']))
        command('seed')
        phases['healthy'] = command('snapshot')['snapshot']
        action('apply')
        check('Exact UPDATE retry retains original approvals ledgers checks and unresolved fence', exact_business(phases['healthy'], command('snapshot')['snapshot']))
        action('recover')
        check('Recovery restores actual published code and removes new change service', code_matches(installed, baseline_files)
              and not (installed / 'app/providers/registered_nameserver_changes.php').exists())
        phases['recovered'] = command('snapshot')['snapshot']
        check('Old files retain every original grant request ledger terminal check key and audit', exact_business(phases['healthy'], phases['recovered']))
        action('apply')
        check('Reapplication restores exact current change service bytes', code_matches(installed, update_files, True, version))
        command('verify')
        phases['before_terminal_recovery'] = command('snapshot')['snapshot']
        check('Reapplication preserves original change timestamps proofs and pending global history', exact_business(phases['healthy'], phases['before_terminal_recovery']))
        command('recover-terminal')
        phases['terminal_recovered'] = command('snapshot')['snapshot']
        check('Explicit local terminal recovery preserves every preexisting witness and key', terminal_recovery_retained(phases['before_terminal_recovery'], phases['terminal_recovered']))
        command('damage')
        phases['damaged'] = command('snapshot')['snapshot']
        action('apply')
        command('refuse', 'refuse-after-retry')
        action('recover')
        check('Damaged history recovery still restores only old files', code_matches(installed, baseline_files)
              and exact_business(phases['damaged'], command('snapshot')['snapshot']))
        action('apply')
        command('refuse', 'refuse-after-reapply')
        phases['final_damaged'] = command('snapshot')['snapshot']
        check('File retry recovery reapply never recreate lost database history', exact_business(phases['damaged'], phases['final_damaged']))
        check('Final installed files still match the target build', code_matches(installed, update_files, True, version))
    except BaseException as error:
        failure = {'type': type(error).__name__}  # No raw exception/config/provider values.
    finally:
        if proc is not None:
            proc.terminate()
            try:
                proc.wait(timeout=15)
            except subprocess.TimeoutExpired:
                proc.kill(); proc.wait()
        if launcher is not None:
            launcher.close()
        log = qa / 'apache-release125-rnsc-upgrade.log'
        if log.exists():
            clean = re.search(r'PHP (?:Warning|Notice|Fatal error|Parse error|Deprecated):', log.read_text(encoding='utf-8', errors='replace')) is None
            checks.append({'name': 'Terminated Apache log has no PHP diagnostic', 'passed': clean})
        retention = qa / 'apache-registered-nameserver-changes-upgrade-retention.json'
        with retention.open('x', encoding='utf-8') as handle:
            json.dump({'source': source, 'baseline_source': BASELINE_SOURCE, 'phases': phases}, handle, indent=2)
        passed = failure is None and 'final_damaged' in phases and all(c['passed'] is True for c in checks)
        with (qa / REPORT).open('x', encoding='utf-8') as handle:
            json.dump({'passed': passed, 'source': source, 'target_version': version, 'baseline_source': BASELINE_SOURCE,
                       'baseline_sha256': BASELINE_SHA, 'update_sha256': digest(update), 'selected': selected(),
                       'runtime': runtime_fact, 'checks': checks, 'failure': failure, 'helper_failures': helper_failures,
                       'retention_sha256': digest(retention), 'scope': 'One real published1.25 Apache subfolder/rewrite install and seven owner UPDATE/recovery actions. Two original synthetic gateway sends; no provider network. Resolved and unresolved change history retained; intentional final lost-check database. Complete-journal file rollback, not interrupted file application; no browser/live acceptance'}, handle, indent=2)
    return 0 if passed else 1


if __name__ == '__main__':
    raise SystemExit(main())
