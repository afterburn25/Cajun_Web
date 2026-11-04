"""Exercise the composition boundary by mutating real disposable source files."""
from pathlib import Path
import argparse
import importlib.util
import json
import py_compile
import subprocess
import sys
import tempfile

import combined_source_composition as proof

ROOT = Path(__file__).resolve().parents[1]


def run(root=ROOT):
    checks = []

    def passed(name):
        checks.append({'name': name, 'passed': True})

    def refuses(name, operation):
        try:
            operation()
        except AssertionError:
            passed(name)
        else:
            raise AssertionError('Accepted unreviewed composition: ' + name)

    proof.verify_current(root)
    passed('Actual current combined checkout passes its complete byte/mode/path gate')
    with tempfile.TemporaryDirectory(prefix='cwp-composition-negative-') as folder:
        fixture = Path(folder) / 'source'
        subprocess.run(['git', 'clone', '--quiet', '--shared', '--no-checkout', '--local', str(root), str(fixture)], check=True)
        subprocess.run(['git', 'checkout', '--quiet', '--detach', proof.git(root, 'rev-parse', 'HEAD').decode().strip()], cwd=fixture, check=True)
        # The test also runs before the new comparison adapters are committed.
        # Copy only their finite reviewed bytes into the disposable checkout.
        for path in proof.ADAPTERS | proof.REPAIRS | proof.ADDITIONS | {proof.VERIFIER, proof.RECEIPT, proof.REGRESSION, proof.INVENTORY, 'tools/acceptance-contract.json'}:
            target = fixture / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(proof.ordinary(root, path))
            target.chmod(0o644)
        proof.verify_current(fixture)
        passed('Disposable actual-filesystem fixture begins with the same validated composition')

        def modified(path, operation):
            target = fixture / path
            original = target.read_bytes()
            permission = target.stat().st_mode & 0o777
            try:
                operation(target)
                proof.verify_current(fixture)
            finally:
                if target.is_symlink():
                    target.unlink()
                target.write_bytes(original)
                target.chmod(permission)

        for name, path in (
            ('current software authority application', 'site/app/billing/software_client.php'),
            ('current finance application', 'site/app/billing/finance_subscription.php'),
            ('current notification application', 'site/app/notifications/outbox.php'),
            ('retained native software assertions', 'tests/software_commerce_native.php'),
            ('standalone software source proof', 'tests/software_authority_provenance.py'),
            ('standalone finance source proof', 'tests/finance_recovery_provenance.py'),
            ('original notification receipt', 'docs/NOTIFICATION_SETTINGS_FRESHNESS_CORRECTION.json'),
            ('protected complete application workflow', '.github/workflows/application-tests.yml'),
            ('current canonical installer inventory', proof.INVENTORY),
            ('finite comparison adapter', 'tests/provider_browser_fixture_provenance.py'),
            ('composition verifier', proof.VERIFIER),
            ('actual-filesystem regression suite', proof.REGRESSION),
        ):
            refuses('Refuses altered ' + name, lambda path=path: modified(path, lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed source\n')))

        path = 'site/app/billing/software_client.php'
        refuses('Refuses removal of an actual current application file',
                lambda: modified(path, lambda target: target.unlink()))
        refuses('Refuses an unexpected executable mode on actual application source',
                lambda: modified(path, lambda target: target.chmod(0o755)))

        def symlink(target):
            target.unlink()
            target.symlink_to('software_orders.php')

        refuses('Refuses a real symlink replacing current application source', lambda: modified(path, symlink))

        def addition(path):
            target = fixture / path
            target.write_bytes(b'<?php /* unreviewed addition */\n')
            try:
                proof.verify_current(fixture)
            finally:
                target.unlink()

        refuses('Refuses an untracked additional source file', lambda: addition('tests/unreviewed_composition.py'))
        refuses('Refuses a hidden additional shipping file', lambda: addition('site/.unreviewed-composition.php'))

        def altered_receipt(change):
            def rewrite(target):
                data = json.loads(target.read_bytes())
                change(data)
                target.write_text(json.dumps(data))
            modified(proof.RECEIPT, rewrite)

        refuses('Refuses a forged component commit in the actual receipt',
                lambda: altered_receipt(lambda data: data['components']['software'].__setitem__('commit', '0' * 40)))
        refuses('Refuses a missing component in the actual receipt',
                lambda: altered_receipt(lambda data: data['components'].pop('finance')))
        refuses('Refuses an expanded comparison adapter scope',
                lambda: altered_receipt(lambda data: data['adapters'].__setitem__('tests/unreviewed.py', {})))
        refuses('Refuses an omitted reversible adapter edit',
                lambda: altered_receipt(lambda data: data['adapters']['tests/tickets_integration_contract.py']['edits'].pop()))

        def forged_adapter_and_receipt():
            adapter = fixture / 'tests/tickets_integration_contract.py'
            receipt = fixture / proof.RECEIPT
            old_adapter, old_receipt = adapter.read_bytes(), receipt.read_bytes()
            try:
                adapter.write_bytes(old_adapter + b'\n# different comparison logic\n')
                data = json.loads(old_receipt)
                data['adapters']['tests/tickets_integration_contract.py']['current_sha256'] = proof.digest(adapter.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                adapter.write_bytes(old_adapter)
                receipt.write_bytes(old_receipt)

        refuses('Refuses simultaneous comparison code and receipt-hash substitution', forged_adapter_and_receipt)

        for path in sorted(proof.REPAIRS):
            refuses('Refuses altered exact integration repair ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed repair\n')))
        refuses('Refuses a changed executable mode on an integration repair',
                lambda: modified('tests/installed_owner_authority.php', lambda target: target.chmod(0o755)))
        for path in ('tests/provider_force_ssl_controls_cases.py', 'tests/provider_directory_listing_controls_cases.py'):
            refuses('Refuses replacing the original recovery actor with the setup owner in ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(
                        target.read_bytes().replace(b'actor_session=owner, actor_id=', b'actor_session=setup, actor_id=', 1))))
        for group in ('browser', 'final'):
            def omit_required(target, group=group):
                inventory = json.loads(target.read_bytes())
                inventory[group].remove('software-commerce-native-authority.json')
                target.write_text(json.dumps(inventory))
            refuses('Refuses dropping required software authority evidence from ' + group,
                    lambda: modified('tools/acceptance-reports.json', omit_required))
        refuses('Refuses substituting the failed acceptance checkpoint',
                lambda: altered_receipt(lambda data: data.__setitem__('repair_base', '0' * 40)))
        refuses('Refuses omitted exact integration repair admission',
                lambda: altered_receipt(lambda data: data['integration_repairs'].pop('tests/installed_owner_authority.php')))
        refuses('Refuses omitted integration repair inverse',
                lambda: altered_receipt(lambda data: data['integration_repairs']['tests/provider_force_ssl_controls_cases.py']['edits'].pop()))

        def forged_repair_and_receipt():
            repair = fixture / 'tests/installed_owner_authority.py'
            receipt = fixture / proof.RECEIPT
            old_repair, old_receipt = repair.read_bytes(), receipt.read_bytes()
            try:
                repair.write_bytes(old_repair + b'\n# changed selected actor\n')
                data = json.loads(old_receipt)
                data['integration_repairs']['tests/installed_owner_authority.py']['current_sha256'] = proof.digest(repair.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                repair.write_bytes(old_repair)
                receipt.write_bytes(old_receipt)
        refuses('Refuses simultaneous runtime fixture code and repair-receipt hash substitution', forged_repair_and_receipt)

        # Invoke the backup boundary itself after changing real current inputs;
        # do not let modified()'s own verification manufacture a refusal.
        from backups_acceptance_wiring import predecessor_browser as backup_predecessor
        browser_path = 'tests/browser_acceptance.py'
        contract_path = 'tools/acceptance-contract.json'
        before_browser = proof.ordinary(fixture, browser_path)
        before_contract = proof.ordinary(fixture, contract_path)
        current_contract = json.loads(before_contract)
        retained_browser = backup_predecessor(fixture, current_contract).encode()
        backup_hook = current_contract['backups_browser_extension']['hook'].encode()
        contact_hook = b'            from contacts_cases import run_browser as contacts_browser\n            contacts_browser(globals(),browser)\n'
        expected_browser = proof.git(fixture, 'cat-file', 'blob', proof.REPAIR_BASE + ':' + browser_path).replace(backup_hook, b'', 1)
        proof.require(retained_browser == expected_browser
                      and proof.digest(retained_browser) == current_contract['unchanged_test_sha256'][browser_path]
                      and before_browser.count(contact_hook) == 1
                      and proof.ordinary(fixture, browser_path) == before_browser
                      and proof.ordinary(fixture, contract_path) == before_contract,
                      'Backup comparison did not retain the exact predecessor and current runtime inputs')
        passed('Historical backup comparison preserves its exact guards and current contact browser continuation')

        def changed_backup_input(path, transform):
            target = fixture / path
            original = target.read_bytes()
            try:
                target.write_bytes(transform(original))
                backup_predecessor(fixture, json.loads(proof.ordinary(fixture, contract_path)))
            finally:
                target.write_bytes(original)

        refuses('Backup comparison refuses altered actual current browser source',
                lambda: changed_backup_input(browser_path, lambda raw: raw + b'\n# unreviewed browser continuation\n'))
        def wrong_predecessor(raw):
            value = json.loads(raw)
            value['unchanged_test_sha256'][browser_path] = '0' * 64
            return (json.dumps(value, indent=2) + '\n').encode()
        refuses('Backup comparison refuses altered actual current predecessor contract',
                lambda: changed_backup_input(contract_path, wrong_predecessor))
        def missing_backup_guard(raw):
            value = json.loads(raw)
            del value['backups_browser_extension']
            return (json.dumps(value, indent=2) + '\n').encode()
        refuses('Backup comparison refuses removal of its actual current contract guards',
                lambda: changed_backup_input(contract_path, missing_backup_guard))
        forged_contract = json.loads(before_contract)
        forged_contract.pop('backups_browser_extension')
        refuses('Backup comparison refuses a caller contract that omits the retained guards',
                lambda: backup_predecessor(fixture, forged_contract))

        for path in sorted(proof.ADDITIONS):
            refuses('Refuses altered exact contact addition ' + path,
                    lambda path=path: modified(path, lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed contact source\n')))
            refuses('Refuses missing exact contact addition ' + path,
                    lambda path=path: modified(path, lambda target: target.unlink()))
        refuses('Refuses an executable mode on a contact addition',
                lambda: modified('site/app/clients/contacts.php', lambda target: target.chmod(0o755)))
        refuses('Refuses a symlink replacing a contact addition',
                lambda: modified('site/app/clients/contacts.php', symlink))
        refuses('Refuses substituting the retained contact source commit',
                lambda: altered_receipt(lambda data: data.__setitem__('addition_source', '0' * 40)))
        refuses('Refuses substituting the retained contact source tree',
                lambda: altered_receipt(lambda data: data.__setitem__('addition_source_tree', '0' * 40)))
        refuses('Refuses an omitted contact addition admission',
                lambda: altered_receipt(lambda data: data['additions'].pop('site/app/clients/contacts.php')))
        refuses('Refuses an expanded contact addition scope',
                lambda: altered_receipt(lambda data: data['additions'].__setitem__('site/unreviewed-contact.php', {})))
        refuses('Refuses an omitted correction inverse for changed contact source',
                lambda: altered_receipt(lambda data: data['additions']['site/app/clients/contacts.php'].__setitem__('edits', [])))
        refuses('Refuses an empty-context contact correction inverse',
                lambda: altered_receipt(lambda data: data['additions']['site/app/clients/contacts.php']['edits'][0].__setitem__(0, '')))

        def forged_addition_and_receipt():
            addition = fixture / 'site/app/clients/contacts.php'
            receipt = fixture / proof.RECEIPT
            old_addition, old_receipt = addition.read_bytes(), receipt.read_bytes()
            try:
                addition.write_bytes(old_addition + b'\n# changed contact authority\n')
                data = json.loads(old_receipt)
                data['additions']['site/app/clients/contacts.php']['current_sha256'] = proof.digest(addition.read_bytes())
                receipt.write_text(json.dumps(data))
                proof.verify_current(fixture)
            finally:
                addition.write_bytes(old_addition)
                receipt.write_bytes(old_receipt)

        refuses('Refuses simultaneous contact source and addition-receipt hash substitution', forged_addition_and_receipt)

        source = fixture / 'tests/software_authority_provenance.py'
        cached = Path(importlib.util.cache_from_source(str(source)))
        py_compile.compile(str(source), cfile=str(cached), doraise=True)
        proof.verify_current(fixture)
        passed('Ordinary generated test cache does not become a Git/source/package input')
        subprocess.run(['git', 'add', '--force', str(cached.relative_to(fixture))], cwd=fixture, check=True)
        try:
            refuses('Tracked bytecode cannot use the generated-cache exclusion', lambda: proof.verify_current(fixture))
        finally:
            subprocess.run(['git', 'rm', '--quiet', '--cached', str(cached.relative_to(fixture))], cwd=fixture, check=True)
        refuses('Retained source drift is refused even when an ordinary generated cache exists',
                lambda: modified('tests/software_authority_provenance.py',
                                 lambda target: target.write_bytes(target.read_bytes() + b'\n# unreviewed source\n')))

        refuses('Historical projection refuses unrelated comparison input', lambda: proof.account_call(
            fixture, 'provider_browser_fixture_provenance', 'predecessor',
            path='tests/release_cases.py', value=b'unreviewed predecessor'))
        refuses('Historical projection cannot dispatch a runtime module', lambda: proof.account_call(
            fixture, 'software_commerce_native', 'run'))
        refuses('Historical projection never returns shipping application bytes', lambda: proof.account_call(
            fixture, 'provider_browser_fixture_provenance', 'source', path='site/app/billing/software_client.php'))
        refuses('The finite report-inventory comparison does not admit other tools', lambda: proof.account_call(
            fixture, 'provider_browser_fixture_provenance', 'source', path='tools/acceptance_artifacts.py'))
        refuses('The finite report-inventory comparison cannot supply runtime input', lambda: proof.account_call(
            fixture, 'provider_browser_fixture_provenance', 'predecessor', path='tools/acceptance-reports.json',
            value=proof.ordinary(fixture, 'tools/acceptance-reports.json')))
        historical_reports = proof.account_call(fixture, 'provider_browser_fixture_provenance', 'source',
                                               path='tools/acceptance-reports.json')
        assert historical_reports == proof.git(root, 'cat-file', 'blob', proof.REPAIR_BASE + ':tools/acceptance-reports.json')
        assert historical_reports != proof.ordinary(fixture, 'tools/acceptance-reports.json')
        passed('Historical report comparison is exact while runtime retains the new required evidence')
        refuses('Historical projection cannot dispatch an arbitrary callback', lambda: proof.projected_validation(
            fixture, lambda root: [], ('docs/STANDALONE_ACCEPTANCE_REPAIRS.json',)))
        proof.verify_current(fixture)
        passed('Restored actual filesystem passes after every rejection case')

    return {'passed': True, 'count': len(checks), 'checks': checks,
            'scope': 'Actual source/receipt/mode/symlink mutation boundaries and ordinary generated cache compatibility. No native SQL or browser execution.'}


def shallow_acceptance(root=ROOT):
    """Use the unchanged protected-workflow helper in a genuine depth-one clone."""
    checks = []

    def check(name, condition):
        proof.require(condition, name)
        checks.append({'name': name, 'passed': True})

    output = root / 'qa/combined-composition/shallow-corrected'
    output.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='cwp-composition-shallow-') as directory:
        fixture = Path(directory) / 'source'
        subprocess.run(['git', 'clone', '--quiet', '--depth', '1', '--no-tags', root.as_uri(), str(fixture)], check=True)
        for path in proof.ADAPTERS | proof.REPAIRS | proof.ADDITIONS | {proof.VERIFIER, proof.RECEIPT, proof.REGRESSION, proof.INVENTORY, 'tools/acceptance-contract.json'}:
            target = fixture / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(proof.ordinary(root, path))
            target.chmod(0o644)
        before = proof.history_state(fixture)
        check('Fixture is an actual depth-one Git clone before the unchanged helper runs',
              proof.git(fixture, 'rev-parse', '--is-shallow-repository').strip() == b'true')
        missing = subprocess.run(['git', 'cat-file', '-e', proof.BASE + '^{tree}'], cwd=fixture, capture_output=True)
        check('Required historical component ancestry is genuinely absent before hydration', missing.returncode != 0)
        check('Protected complete application workflow remains its original exact bytes',
              proof.ordinary(fixture, '.github/workflows/application-tests.yml')
              == proof.git(root, 'cat-file', 'blob', proof.BASE + ':.github/workflows/application-tests.yml'))
        command = [sys.executable, '-B', 'tests/acceptance_artifacts_test.py', '--report', str(output / 'helpers.json')]
        with (output / 'stdout.log').open('wb') as stdout, (output / 'stderr.log').open('wb') as stderr:
            execution = subprocess.run(command, cwd=fixture, stdout=stdout, stderr=stderr)
        after = proof.history_state(fixture)
        record = {'command': command, 'exit_code': execution.returncode, 'before': before, 'after': after,
                  'protected_workflow_sha256': proof.digest(proof.ordinary(fixture, '.github/workflows/application-tests.yml'))}
        (output / 'execution.json').write_text(json.dumps(record, indent=2) + '\n')
        check('Unchanged maintained acceptance helper succeeds from genuine shallow source', execution.returncode == 0)
        check('The unchanged helper has no source-proof stderr', not (output / 'stderr.log').read_bytes())
        report = json.loads((output / 'helpers.json').read_bytes())
        check('Every retained helper assertion remains passing',
              report['passed'] is True and bool(report['checks']) and all(row['passed'] is True for row in report['checks']))
        check('Only shallow/object metadata changed; HEAD, refs, index and actual source stayed exact', before == after)
        check('Exact current HEAD fetch made the required ancestry available',
              proof.git(fixture, 'rev-parse', '--is-shallow-repository').strip() == b'false')
        for identity in proof.COMPONENTS.values():
            proof.git(fixture, 'merge-base', '--is-ancestor', identity['commit'], 'HEAD')
        check('All four exact component ancestry requirements still execute', True)
        proof.git(fixture, 'merge-base', '--is-ancestor', proof.ADDITION_SOURCE, 'HEAD')
        check('The exact retained contact source is also hydrated as required current ancestry', True)
        check('A later source check performs no duplicate history fetch', proof.ensure_history(fixture) == {'performed': False})

        # A second genuine shallow clone proves the local test transport is not
        # an arbitrary upstream or environment bypass.
        rejected = Path(directory) / 'unreviewed-origin'
        subprocess.run(['git', 'clone', '--quiet', '--depth', '1', '--no-tags', root.as_uri(), str(rejected)], check=True)
        subprocess.run(['git', 'remote', 'set-url', 'origin', 'https://github.com/unreviewed/other.git'], cwd=rejected, check=True)
        refused = False
        try:
            proof.ensure_history(rejected)
        except AssertionError:
            refused = True
        check('An unrelated upstream is refused before any history fetch', refused)
        check('Refusing an unrelated upstream leaves the clone shallow',
              proof.git(rejected, 'rev-parse', '--is-shallow-repository').strip() == b'true')

    return {'passed': True, 'count': len(checks), 'checks': checks,
            'maintained_helper_checks': len(report['checks']),
            'scope': 'Genuine shallow Git and unchanged historical source helper only. No SQL/browser/merchant execution.'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--shallow', action='store_true', help='Run the unchanged helper from a genuine depth-one clone.')
    arguments = parser.parse_args()
    print(json.dumps(shallow_acceptance() if arguments.shallow else run(), indent=2))
