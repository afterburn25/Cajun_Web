"""Offline temporary-file regressions for actual CI artifact helpers. No network/DB."""
import argparse
import copy
import hashlib
import importlib.util
import io
import json
import re
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
from unittest.mock import patch
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('acceptance_artifacts', ROOT / 'tools/acceptance_artifacts.py')
a = importlib.util.module_from_spec(spec); spec.loader.exec_module(a)
checks = []
from imported_plan_setup_wiring import historical_bytes, run as imported_plan_setup_checks
from hosting_purchase_inbox_wiring import run as purchase_inbox_checks


def check(name, condition):
    checks.append({'name': name, 'passed': bool(condition)})
    if not condition: raise AssertionError(name)


def deny(name, callback):
    try: callback()
    except (ValueError, OSError, KeyError, TypeError, zipfile.BadZipFile): check(name, True)
    else: check(name, False)


def blob(path, data=b'public synthetic bytes'):
    path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(data)


def context():
    return {'schema': 1, 'environment': {'php':'8.3','database':'mariadb-10.11'}, 'repository': 'synthetic/repository', 'run_id': '123', 'attempt': '1',
            'ci_sha': 'a' * 40, 'source_sha': 'b' * 40, 'tree': 'c' * 40,
            'workflow_sha256': 'd' * 64, 'helper_sha256': 'e' * 64, 'reports_sha256': 'f' * 64, 'contract_sha256': '1' * 64}


def packages(root, ctx):
    dist = root / 'dist'; dist.mkdir(parents=True)
    for kind in ['FULL-PROJECT', 'COMPLETE-UPLOAD', 'UPDATE']:
        p = dist / ('CajunWebPro-synthetic-' + kind + '.zip')
        member = 'index.php' if kind == 'COMPLETE-UPLOAD' else 'public/index.php'
        with zipfile.ZipFile(p, 'w') as z: z.writestr(member, b'<?php /* synthetic public */')
        a.write(p.with_suffix('.manifest.json'), {'source_commit': ctx['ci_sha'], 'artifact': p.name,
                'sha256': a.digest(p), 'files': {member: hashlib.sha256(b'<?php /* synthetic public */').hexdigest()}})
        blob(p.with_suffix('.directories.txt'), b'public\n')
    a.write(dist / 'manifest.json', {'source_commit': ctx['ci_sha']})
    blob(dist / 'START-HERE.txt', b'Synthetic package')
    entries = sorted(dist.iterdir())
    blob(dist / 'SHA256SUMS.txt', ''.join(a.digest(p) + '  ' + p.name + '\n' for p in entries).encode())


def named(path, passed=True):
    a.write(path, {'passed': passed, 'checks': [{'name': 'Synthetic exact check', 'passed': passed}]})


def synthetic_baseline():
    # Unit protocol bytes only. Actual upgrade acceptance uses the pinned published asset.
    stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        for name, body in {'.htaccess': b'Deny public listing', 'includes/public.php': b'public synthetic bytes'}.items():
            archive.writestr(zipfile.ZipInfo(name, date_time=(2026, 10, 12, 0, 0, 0)), body)
    return stream.getvalue()


def synthetic_baseline_126():
    # Deliberately different bytes from the retained 1.24 synthetic baseline.
    stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        for name, body in {'.htaccess': b'Deny public listing', 'includes/public.php': b'public synthetic 1.25 bytes'}.items():
            archive.writestr(zipfile.ZipInfo(name, date_time=(2026, 10, 12, 0, 0, 0)), body)
    return stream.getvalue()



def additional_baseline_checks(tmp, ctx, source, transfer, input_sha):
    obj = a.verify_bundle(transfer, 'inputs', ctx, input_sha)
    a.validate_published_baseline(source, obj['files'])
    a.validate_published_baseline_126(source, obj['files'])
    check('Both independently pinned published baselines validate in one sealed input',
          a.PUBLISHED_BASELINE != a.PUBLISHED_BASELINE_126 and synthetic_baseline() != synthetic_baseline_126())
    check('Additional baseline extends the public fixture set to exactly28 trees',
          len(a.FIXTURES) == 28 and a.VERSIONS[-2:] == ('1.24.0', '1.25.0'))
    check('Only the exact added published archive is admitted',
          a.input_allowed('test-runtime/' + a.PUBLISHED_BASELINE_126))
    for name in ['test-runtime/CajunWebPro-1.26.0-COMPLETE-UPLOAD.zip',
                 'qa/' + a.PUBLISHED_BASELINE_126,
                 'test-runtime/' + a.PUBLISHED_BASELINE_126 + '/extra.php',
                 'test-runtime/private/' + a.PUBLISHED_BASELINE_126]:
        check('Additional baseline cannot broaden the archive path allowlist ' + name, not a.input_allowed(name))
    prefix = 'test-runtime/released-1.25.0/'
    member = prefix + 'includes/public.php'
    for title, mutation in [
        ('missing', lambda f: f.pop(member)),
        ('extra', lambda f: f.update({prefix + 'extra.php': f[member]})),
        ('changed digest', lambda f: f[member].update(sha256='0' * 64)),
        ('changed size', lambda f: f[member].update(size=f[member]['size'] + 1)),
        ('traversal', lambda f: f.update({prefix + '../outside': f.pop(member)})),
    ]:
        facts = copy.deepcopy(obj['files']); mutation(facts)
        deny('Added baseline refuses ' + title + ' member evidence', lambda f=facts: a.validate_published_baseline_126(source, f))
    archive = source / 'test-runtime' / a.PUBLISHED_BASELINE_126
    raw = archive.read_bytes()
    try:
        archive.write_bytes(synthetic_baseline())
        deny('Original1.24 raw archive cannot impersonate the added1.25 baseline', lambda: a.validate_published_baseline_126(source, obj['files']))
        archive.write_bytes(raw[:-1] + bytes([raw[-1] ^ 1]))
        deny('Added baseline refuses mutated original ZIP bytes', lambda: a.validate_published_baseline_126(source, obj['files']))
        archive.unlink()
        deny('Added baseline cannot fall back when its raw original ZIP is absent', lambda: a.validate_published_baseline_126(source, obj['files']))
    finally:
        archive.write_bytes(raw)
    # Correctly re-sealed envelopes still cannot omit either original baseline or new fixture tree.
    for index, omitted in enumerate(['test-runtime/' + a.PUBLISHED_BASELINE,
                                     'test-runtime/' + a.PUBLISHED_BASELINE_126, prefix]):
        facts = {n: f for n, f in obj['files'].items() if n != omitted and not n.startswith(omitted if omitted.endswith('/') else omitted + '/')}
        folder = tmp / ('baseline-omission-' + str(index)); folder.mkdir()
        envelope = dict(obj, files=facts, archive=a.pack(source, facts, folder / 'inputs.zip'))
        a.write(folder / 'inputs.json', envelope)
        deny('Re-sealed input still requires original baseline family ' + omitted,
             lambda d=folder, i=index: a.receive_inputs(d, tmp / ('baseline-receive-' + str(i)), ctx, a.digest(d / 'inputs.json'), 'apache'))
    # Wrong new fixture bytes with coherent outer hashes must fail the immutable inner baseline.
    changed = tmp / 'baseline-member-changed'; shutil.copytree(source, changed)
    blob(changed / member, b'wrong but coherently sealed fixture member')
    facts = copy.deepcopy(obj['files']); facts[member] = a.file_facts(changed / member)
    folder = tmp / 'baseline-coherent-envelope'; folder.mkdir()
    envelope = dict(obj, files=facts, archive=a.pack(changed, facts, folder / 'inputs.zip'))
    a.write(folder / 'inputs.json', envelope)
    deny('Coherent outer input cannot replace an original1.25 member',
         lambda: a.receive_inputs(folder, tmp / 'baseline-coherent-child', ctx, a.digest(folder / 'inputs.json'), 'apache'))



PHP_CONTROLS_NATIVE_REPORTS = (
    'php-controls-delegation-document-results.json',
    'php-controls-delegation-document-no-ini-results.json',
    'provider-php-controls-delegation-native-results.json',
    'php-options-document-results.json', 'php-options-document-no-ini-results.json',
    'php-controls-lifecycle-document-results.json', 'php-controls-lifecycle-document-no-ini-results.json',
    'php-controls-lifecycle-storage-results.json', 'php-controls-lifecycle-storage-no-ini-results.json',
    'php-controls-reads-results.json', 'php-controls-reads-no-ini-results.json',
    'php-controls-changes-results.json', 'php-controls-changes-no-ini-results.json',
    'php-controls-screen-results.json', 'php-controls-screen-no-ini-results.json',
    'php-controls-views-results.json', 'php-controls-views-no-ini-results.json',
    'php-controls-http-results.json', 'php-controls-http-no-ini-results.json',
    'provider-php-controls-lifecycle-native-results.json',
)
PHP_CONTROLS_BROWSER_REPORTS = PHP_CONTROLS_NATIVE_REPORTS + (
    'php-controls-packaged-ui-bridge-results.json', 'php-controls-packaged-ui-results.json',
)
PHP_CONTROLS_FINAL_REPORTS = PHP_CONTROLS_BROWSER_REPORTS + (
    'apache-php-controls-upgrade-guards-results.json', 'apache-php-controls-upgrade-results.json',
)


def php_controls_source_predecessor(raw, delta):
    a.require(set(delta) == {'predecessor_sha256', 'current_sha256', 'replacements'}, 'Closed PHP source delta required')
    a.require(hashlib.sha256(raw).hexdigest() == delta['current_sha256'], 'Current PHP producer changed')
    for edit in reversed(delta['replacements']):
        a.require(set(edit) == {'before', 'after'} and edit['before'] and edit['after'], 'Exact source replacement required')
        before, after = edit['before'].encode(), edit['after'].encode()
        a.require(raw.count(after) == 1, 'Unique PHP source replacement required')
        raw = raw.replace(after, before, 1)
    a.require(hashlib.sha256(raw).hexdigest() == delta['predecessor_sha256'], 'Original PHP predecessor changed')
    return raw


def php_controls_report_predecessor(reports, extension):
    expected = {'browser': list(PHP_CONTROLS_BROWSER_REPORTS), 'final': list(PHP_CONTROLS_FINAL_REPORTS)}
    a.require(extension['reports'] == expected, 'Exact PHP report additions required')
    prior = copy.deepcopy(reports)
    for lane, names in expected.items():
        a.require(len(reports[lane]) == len(set(reports[lane])) and all(reports[lane].count(n) == 1 for n in names),
                  'Missing or duplicate PHP report')
        prior[lane] = [n for n in reports[lane] if n not in names]
    a.require(hashlib.sha256(a.canonical(prior)).hexdigest() == extension['predecessor_reports_sha256'],
              'Original report inventory changed')
    return prior


def php_controls_partition_checks(tmp, ctx, lane_roots, input_sha, expected):
    # Synthetic named files exercise the unchanged real seal/aggregate boundary;
    # these are not application, SQL, HTTP or visual results.
    root = tmp / 'php-controls-partitions'
    incoming, sources, bindings = root / 'complete', {}, {}
    target_expected = copy.deepcopy(expected)
    target_expected['browser'].extend(PHP_CONTROLS_BROWSER_REPORTS)
    target_expected['final'].extend(PHP_CONTROLS_FINAL_REPORTS)
    for index, lane in enumerate(a.LANES):
        source = root / ('source-' + lane)
        sources[lane] = source
        shutil.copytree(lane_roots[lane] / 'qa', source / 'qa')
        if lane == 'build': shutil.copytree(lane_roots[lane] / 'dist', source / 'dist')
        additions = PHP_CONTROLS_BROWSER_REPORTS if lane == 'native-browser' else PHP_CONTROLS_FINAL_REPORTS[-2:] if lane == 'apache' else ()
        for name in additions: named(source / 'qa' / name)
        digest = a.seal(source, ctx, incoming / lane, lane, outcomes(lane), input_sha)
        bindings[lane] = {'result': 'success', 'artifact_id': str(index + 101), 'sha256': digest,
                          **({'input_sha256': input_sha} if lane == 'build' else {}),
                          **({'checkpoint_id': '109', 'checkpoint_digest': '6' * 64} if lane == 'native-browser' else {})}
    aggregate = root / 'aggregate-complete'
    aggregate.mkdir()
    result = a.aggregate(aggregate, ctx, incoming, bindings, target_expected, False)
    check('PHP complete synthetic union passes actual partition aggregation',
          result['complete'] is True and set(result['reports']) == set(target_expected['final']))
    cases = [('native-browser', 'php-controls-packaged-ui-results.json'),
             ('apache', 'apache-php-controls-upgrade-results.json')]
    for lane, report in cases:
        for mode in ('missing', 'failed', 'extra'):
            folder = root / (lane + '-' + mode)
            source, parts = folder / 'source', folder / 'parts'
            shutil.copytree(sources[lane] / 'qa', source / 'qa')
            if mode == 'missing': (source / 'qa' / report).unlink()
            elif mode == 'failed': named(source / 'qa' / report, False)
            else:
                extra = ('apache-' if lane == 'apache' else '') + 'php-controls-extra-results.json'
                named(source / 'qa' / extra)
            for other in a.LANES:
                if other != lane: shutil.copytree(incoming / other, parts / other)
            bound = copy.deepcopy(bindings)
            bound[lane]['sha256'] = a.seal(source, ctx, parts / lane, lane, outcomes(lane), input_sha)
            aggregate = folder / 'aggregate'
            aggregate.mkdir()
            result = a.aggregate(aggregate, ctx, parts, bound, target_expected, False)
            check('PHP actual aggregation refuses ' + mode + ' ' + lane + ' evidence', not result['complete'])
            if mode == 'failed':
                check('PHP actual aggregation preserves failed ' + report,
                      result['reports'][report]['failed_names'] == ['Synthetic exact check']
                      and a.load(aggregate / 'qa' / report)['passed'] is False)


def php_controls_wiring_checks(tmp, contract):
    extension = contract['php_controls_acceptance']
    original = copy.deepcopy(contract)
    del original['php_controls_acceptance']
    check('PHP controls preserve the entire7af predecessor contract',
          extension['source_predecessor'] == '7af25ed5a1b02f1e21fbac666ba4d0ca31852a79'
          and hashlib.sha256(a.canonical(original)).hexdigest() == extension['predecessor_contract_sha256'])
    delta_paths = {'.github/workflows/application-tests.yml', '.github/workflows/provider-package-php.yml', '.github/workflows/twentyi-operations.yml', 'CHANGELOG.md', 'PROJECT_STATUS.md', 'SESSION_HANDOFF.md', 'docs/ROADMAP.md', 'docs/SESSION_LOG.md', 'foundation/twentyi-operations/TwentyIOperationRegister.php', 'foundation/twentyi-operations/TwentyIRequestBuilder.php', 'foundation/twentyi/FixedHttpTransport.php', 'site/admin/controllers/actions.php', 'site/admin/controllers/router.php', 'site/app/providers/checks.php', 'site/app/providers/inventory.php', 'site/app/providers/package_php.php', 'site/app/providers/package_php_views.php', 'site/app/staff/accounts.php', 'site/assets/css/site.css', 'site/includes/foundation/twentyi-operations/TwentyIOperationRegister.php', 'site/includes/foundation/twentyi-operations/TwentyIRequestBuilder.php', 'site/includes/foundation/twentyi/FixedHttpTransport.php', 'site/includes/layout/provider_services.php', 'site/install/inventory.generated.php', 'site/portal/controllers/actions.php', 'site/portal/controllers/router.php', 'tests/acceptance_artifacts_test.py', 'tests/prepare_legacy.py', 'tests/release_125_upgrade_input_guards.py', 'tests/twentyi-operations/run.php', 'tests/twentyi-package-php/run.php', 'tools/acceptance_artifacts.py'}
    check('PHP controls have only 32 declared predecessor source entries', set(extension['source_delta']) == delta_paths)
    check('PHP controls pin every declared current test and dependency', set(extension['source_sha256']) == set(['.github/workflows/application-tests.yml','.github/workflows/provider-package-php.yml','.github/workflows/twentyi-operations.yml','CHANGELOG.md','PROJECT_STATUS.md','SESSION_HANDOFF.md','docs/ROADMAP.md','docs/SESSION_LOG.md','foundation/twentyi-operations/TwentyIOperationRegister.php','foundation/twentyi-operations/TwentyIRequestBuilder.php','foundation/twentyi/FixedHttpTransport.php','site/admin/controllers/actions.php','site/admin/controllers/router.php','site/app/providers/checks.php','site/app/providers/inventory.php','site/app/providers/package_php.php','site/app/providers/package_php_views.php','site/app/staff/accounts.php','site/assets/css/site.css','site/includes/foundation/twentyi-operations/TwentyIOperationRegister.php','site/includes/foundation/twentyi-operations/TwentyIRequestBuilder.php','site/includes/foundation/twentyi/FixedHttpTransport.php','site/includes/layout/provider_services.php','site/install/inventory.generated.php','site/portal/controllers/actions.php','site/portal/controllers/router.php','tests/acceptance_artifacts_test.py','tests/acceptance_environment.py','tests/acceptance_environment_test.py','tests/prepare_legacy.py','tests/provider_php_controls_changes.php','tests/provider_php_controls_delegation.php','tests/provider_php_controls_delegation_native.php','tests/provider_php_controls_http.php','tests/provider_php_controls_lifecycle.php','tests/provider_php_controls_lifecycle_native.php','tests/provider_php_controls_lifecycle_storage.php','tests/provider_php_controls_reads.php','tests/provider_php_controls_screen.php','tests/provider_php_controls_ui_acceptance.py','tests/provider_php_controls_ui_acceptance_test.py','tests/provider_php_controls_ui_browser.py','tests/provider_php_controls_ui_cases.py','tests/provider_php_controls_ui_connector.php','tests/provider_php_controls_ui_fixture.php','tests/provider_php_controls_ui_guards.py','tests/provider_php_controls_upgrade.py','tests/provider_php_controls_upgrade_fixture.php','tests/provider_php_controls_upgrade_test.py','tests/provider_php_controls_views.php','tests/release_125_upgrade_input_guards.py','tests/twentyi-operations/run.php','tests/twentyi-package-php/options.php','tests/twentyi-package-php/run.php','tools/acceptance_artifacts.py','tools/legacy-release-files.json']))
    for path, expected in extension['source_sha256'].items():
        check('PHP controls current source pin ' + path,
              hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == expected)
    restored = {}
    for path, delta in extension['source_delta'].items():
        raw = ROOT.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
        restored[path] = php_controls_source_predecessor(raw, delta)
        check('PHP controls preserve complete predecessor source ' + path, True)
        deny('PHP controls refuse changed source ' + path,
             lambda data=raw, proof=delta: php_controls_source_predecessor(data + b'\n# changed', proof))
    changed = copy.deepcopy(extension['source_delta']['.github/workflows/provider-package-php.yml'])
    changed['replacements'][0]['after'] += '# changed suffix'
    deny('PHP controls refuse changed preparation inverse',
         lambda: php_controls_source_predecessor(ROOT.joinpath('.github/workflows/provider-package-php.yml').read_bytes().replace(b'\r\n', b'\n'), changed))
    deeper = {'tests/browser_acceptance.py'}
    for section in original.values():
        if isinstance(section, dict):
            for key in ('source_delta', 'predecessor_source_delta', 'merged_stage_delta', 'source_deltas'):
                if isinstance(section.get(key), dict): deeper |= set(section[key])
    if isinstance(original.get('release126_baseline_driver_delta'), dict):
        deeper |= set(original['release126_baseline_driver_delta'])
    check('PHP controls retain all original browser recovery and Apache producer pins',
          all(hashlib.sha256(restored.get(p, ROOT.joinpath(p).read_bytes().replace(b'\r\n', b'\n'))).hexdigest() == h
              for p, h in original['unchanged_test_sha256'].items() if p not in deeper))
    history = a.load(ROOT / 'tools/legacy-release-files.json')
    preceding_history = copy.deepcopy(history)
    published = preceding_history.pop('1.25.0')
    check('PHP upgrade input retains every original published history entry',
          hashlib.sha256(a.canonical(preceding_history)).hexdigest() == extension['predecessor_history_sha256'])
    check('PHP upgrade input adds only exact published1.25 inventory',
          published['archive_sha256'] == 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39'
          and len(published['files']) == 2895)
    guard_path = 'tests/release_125_upgrade_input_guards.py'
    check('PHP upgrade leaves original guard unchanged already carrying unapproved1.26 negative',
          ROOT.joinpath(guard_path).read_bytes().replace(b'\r\n', b'\n') == restored[guard_path]
          and restored[guard_path].count(b'test-runtime/CajunWebPro-1.26.0-COMPLETE-UPLOAD.zip') == 1
          and b'test-runtime/CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip' not in restored[guard_path])
    workflow = ROOT.joinpath('.github/workflows/application-tests.yml').read_text(encoding='utf-8')
    marker = '      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n'
    native = [line.strip() for line in workflow.split(marker, 1)[1].split('      - name: Real HTTP', 1)[0].splitlines()]
    commands = extension['native_commands']
    anchor = '"$PHP_BINARY" tests/provider_package_php_native.php'
    position = original['native_commands'].index(anchor) + 1
    check('PHP controls add exactly20 commands after existing PHP observation native fixture',
          len(commands) == len(set(commands)) == 20 and len(native) == 260
          and native == original['native_commands'][:position] + commands + original['native_commands'][position:]
          and all(native.count(command) == 1 for command in commands))
    paths = {command.split(' tests/', 1)[1].split()[0] for command in commands}
    focused = ROOT.joinpath('.github/workflows/provider-package-php.yml').read_text(encoding='utf-8')
    selected = [line.strip() for line in focused.splitlines()
                if re.match(r'^\s+php(?: -n)?(?: -d extension=[a-z_]+)* tests/', line)
                and line.strip().split(' tests/', 1)[1].split()[0] in paths]
    check('PHP controls commands preserve exact focused arguments and default minimal order',
          commands == ['"$PHP_BINARY"' + command[3:] for command in selected])
    expected_focus = restored['.github/workflows/provider-package-php.yml']
    for edit in reversed(extension['source_delta']['.github/workflows/provider-package-php.yml']['replacements']):
        expected_focus = expected_focus.replace(edit['before'].encode(), edit['after'].encode(), 1)
    check('PHP focused workflow applies only the declared era changes and preserves every PR job',
          focused == expected_focus.decode('utf-8'))
    browser = ['.ci-venv/bin/python tests/browser_acceptance.py',
               '.ci-venv/bin/python tests/provider_php_controls_ui_acceptance_test.py --report qa/php-controls-packaged-ui-bridge-results.json',
               '.ci-venv/bin/python tests/provider_php_controls_ui_acceptance.py']
    apache = ['.ci-venv/bin/python tests/release_125_upgrade_acceptance.py',
              '.ci-venv/bin/python tests/provider_php_controls_upgrade_test.py --report qa/apache-php-controls-upgrade-guards-results.json',
              '.ci-venv/bin/python tests/provider_php_controls_upgrade.py']
    check('PHP packaged guard and actual runner follow original browser in the dependency venv',
          all(workflow.count(command + '\n') == 1 for command in browser)
          and '\n'.join('          ' + command for command in browser) in workflow)
    check('PHP installed upgrade guard and actual runner follow original release125 upgrade',
          all(workflow.count(command + '\n') == 1 for command in apache)
          and '\n'.join('          ' + command for command in apache) in workflow)
    reports = a.load(ROOT / 'tools/acceptance-reports.json')
    prior_reports = php_controls_report_predecessor(reports, extension)
    check('PHP controls extend264 browser and323 final reports to286 and347',
          len(prior_reports['browser']) == 264 and len(prior_reports['final']) == 323
          and len(reports['browser']) == 286 and len(reports['final']) == 347)
    for label in ('missing', 'duplicate', 'extra', 'changed original'):
        wrong = copy.deepcopy(reports)
        if label == 'missing': wrong['final'].remove(PHP_CONTROLS_FINAL_REPORTS[-1])
        elif label == 'duplicate': wrong['browser'].append(PHP_CONTROLS_BROWSER_REPORTS[0])
        elif label == 'extra': wrong['final'].append('php-controls-unexpected-results.json')
        else: wrong['final'][0] += '.changed'
        deny('PHP report union refuses ' + label, lambda value=wrong: php_controls_report_predecessor(value, extension))
    sample = tmp / 'php-controls-new-reports'
    for name in PHP_CONTROLS_FINAL_REPORTS:
        lane = 'apache' if name.startswith('apache-') else 'native-browser'
        check('PHP report has exactly one owner ' + name,
              a.evidence_allowed('qa/' + name, lane)
              and all(not a.evidence_allowed('qa/' + name, other) for other in a.LANES if other != lane))
        file = sample / 'qa' / name
        named(file, False)
        check('PHP failed outcome cannot pass ' + name, not a.reports(sample)[name]['passed'])
        file.unlink()
        check('PHP missing outcome remains missing ' + name, name not in a.reports(sample))
    titles = ('Owner', 'Client', 'Owner-Review', 'Client-Review', 'Owner-History',
              'Client-History', 'Owner-Recovery', 'Client-Unavailable')
    captures = ['PHP-Controls-' + title + '-' + size + '.png' for title in titles for size in ('Desktop', 'Mobile')]
    check('PHP controls retain exactly16 flat native browser capture names',
          extension['screenshots'] == captures and all(a.evidence_allowed('qa/' + n, 'native-browser')
          and not a.evidence_allowed('qa/' + n, 'apache') for n in captures))
    for name in ('qa/php-controls-ui/results.json', 'qa/php-controls-ui/PHP-Controls-Owner-Mobile.png',
                 'test-runtime/php-controls-ui-acceptance-original/results.json'):
        check('PHP private or nested runtime stays excluded ' + name, not a.evidence_allowed(name, 'native-browser'))
    deny('PHP private config remains refused', lambda: a.evidence_allowed('test-runtime/php-controls-ui/config.php', 'native-browser'))
    a.write(sample / 'qa/apache-php-controls-upgrade-retention.json', {'phases': {}})
    check('PHP retention diagnostic does not masquerade as a passing report', not a.reports(sample))
    return restored




def fixtures(root, ctx):
    packages(root, ctx)
    for n in a.FIXTURES:
        blob(root / 'test-runtime' / n / '.htaccess', b'Deny public listing')
        blob(root / 'test-runtime' / n / 'includes/public.php')
    for n in a.FIXTURE_JSON: a.write(root / 'test-runtime' / n, {'public': True})
    blob(root / 'test-runtime' / a.PUBLISHED_BASELINE, synthetic_baseline())
    blob(root / 'test-runtime' / a.PUBLISHED_BASELINE_126, synthetic_baseline_126())
    blob(root / 'test-runtime/released-1.25.0/includes/public.php', b'public synthetic 1.25 bytes')
    for n in a.BUILD_QA:
        if n.endswith('.json'): named(root / 'qa' / n)
        else: blob(root / 'qa' / n)


def outcomes(lane): return {k: 'success' for k in a.STEP_KEYS[lane]}


def valid_port_context(workflow):
    for job in ('native-and-browser', 'apache'):
        match=re.search(r'^  '+job+r':\n(.*?)(?=^  [a-z][a-z-]*:|\Z)',workflow,re.M|re.S)
        if not match:return False
        body=match.group(1)
        env=body.split('    env:\n',1)[1].split('    steps:\n',1)[0]
        if 'job.services' in env or 'CWP_TEST_PORT:' in env:return False
        setup=body.split('        id: setup\n',1)[1].split('      - name:',1)[0]
        if 'CWP_ASSIGNED_PORT: ${{ job.services.mariadb.ports[3306] }}' not in setup:return False
        if 'printf \'CWP_TEST_PORT=%s\\n\' "$CWP_ASSIGNED_PORT" >> "$GITHUB_ENV"' not in setup:return False
    return True


def baseline_driver_predecessor(path, data, contract):
    delta = contract['release126_baseline_driver_delta'][path]
    a.require(set(delta) == {'predecessor_sha256', 'current_sha256', 'append', 'replacements'}, 'Unexpected baseline driver delta fields')
    a.require(hashlib.sha256(data).hexdigest() == delta['current_sha256'], 'Changed current baseline driver bytes')
    if delta['append']:
        suffix = delta['append'].encode()
        a.require(data.endswith(suffix) and data.count(suffix) == 1, 'Missing exact baseline preparation suffix')
        data = data[:-len(suffix)]
    for edit in reversed(delta['replacements']):
        a.require(set(edit) == {'before', 'after', 'count'} and type(edit['count']) is int and edit['count'] == 1, 'Invalid finite baseline driver edit')
        before, after = edit['before'].encode(), edit['after'].encode()
        a.require(before and after and data.count(after) == edit['count'], 'Missing exact baseline driver extension')
        data = data.replace(after, before)
    a.require(hashlib.sha256(data).hexdigest() == delta['predecessor_sha256'] == contract['unchanged_test_sha256'][path], 'Baseline driver predecessor changed')
    return data


def hosting_checkout_recipe_predecessor(commands, contract):
    checkout = contract['hosting_checkout']; additions = checkout['additions']
    a.require(len(commands) == 179 and commands == contract['native_commands'], 'Checkout requires exactly179 ordered native commands')
    expected = [
        '"$PHP_BINARY" tests/hosting_checkout_document.php qa/hosting-checkout-document-default.json',
        '"$PHP_BINARY" -n tests/hosting_checkout_document.php qa/hosting-checkout-document-minimal.json',
        '"$PHP_BINARY" tests/service-plans/checkout_documents.php > qa/hosting-checkout-subscription-default.json',
        '"$PHP_BINARY" -n tests/service-plans/checkout_documents.php > qa/hosting-checkout-subscription-minimal.json',
        '"$PHP_BINARY" tests/recurring-billing/checkout.php > qa/hosting-checkout-billing-default.json',
        '"$PHP_BINARY" -n tests/recurring-billing/checkout.php > qa/hosting-checkout-billing-minimal.json',
        '"$PHP_BINARY" tests/hosting_checkout_views.php > qa/hosting-checkout-views-default.json',
        '"$PHP_BINARY" -n -d extension=tokenizer tests/hosting_checkout_views.php > qa/hosting-checkout-views-minimal.json',
        'CWP_TEST_HOSTING_CHECKOUT=1 "$PHP_BINARY" tests/hosting_checkout_native.php',
    ]
    a.require(additions == expected and checkout['additions_after'] == 'CWP_TEST_HOSTING_RECOVERY=1 "$PHP_BINARY" tests/hosting_catalog_native.php', 'Exact checkout command block required')
    position = commands.index(checkout['additions_after']) + 1
    a.require(commands[position:position + 9] == additions and all(commands.count(c) == 1 for c in additions), 'Checkout commands occur once at their exact position')
    original = [c for c in commands if c not in additions]
    a.require(len(original) == checkout['predecessor_native_count'] == 170 and hashlib.sha256(json.dumps(original, separators=(',', ':')).encode()).hexdigest() == checkout['predecessor_native_sha256'], 'Original165 recipe changed')
    return original


def hosting_checkout_workflow_predecessor(workflow, checkout):
    a.require(len(checkout['workflow_delta']) == 2, 'Two exact checkout workflow insertions required')
    for edit in reversed(checkout['workflow_delta']):
        a.require(set(edit) == {'before', 'after'} and edit['before'] and edit['after'] and workflow.count(edit['after']) == 1, 'Exact checkout workflow insertion missing')
        workflow = workflow.replace(edit['after'], edit['before'], 1)
    a.require(hashlib.sha256(workflow.encode()).hexdigest() == checkout['predecessor_workflow_sha256'], 'Full workflow changed outside checkout additions')
    return workflow


def hosting_checkout_report_predecessor(reports, checkout):
    expected = ['hosting-checkout-' + name + '.json' for name in (
        'document-default', 'document-minimal', 'subscription-default', 'subscription-minimal',
        'billing-default', 'billing-minimal', 'views-default', 'views-minimal', 'native-results', 'http-guards', 'http-results')]
    a.require(checkout['reports'] == expected, 'Eleven exact checkout report names required')
    original = copy.deepcopy(reports)
    for lane, count in [('browser', 196), ('final', 247)]:
        current = reports[lane]; prior = [n for n in current if n not in expected]
        a.require(len(current) == len(set(current)) == count and all(current.count(n) == 1 for n in expected), 'Missing or duplicate checkout reports')
        a.require(hashlib.sha256(json.dumps(prior, separators=(',', ':')).encode()).hexdigest() == checkout['predecessor_reports_sha256'][lane], 'Original report sequence changed')
        original[lane] = prior
    return original


def hosting_checkout_wiring_checks(tmp, workflow, contract, reports=None):
    checkout = contract['hosting_checkout']
    native = workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n', 1)[1].split('      - name: Real HTTP', 1)[0]
    commands = [line.strip() for line in native.splitlines()]
    original = hosting_checkout_recipe_predecessor(commands, contract)
    prior_workflow = hosting_checkout_workflow_predecessor(workflow, checkout)
    check('Checkout preserves exact170 predecessor commands and the whole7703 workflow', len(original) == 170 and bool(prior_workflow))
    for label, changed in [('missing', commands[:-1]), ('duplicate', commands + [checkout['additions'][0]]), ('reordered', commands[1:] + commands[:1])]:
        deny('Checkout recipe refuses ' + label, lambda c=changed: hosting_checkout_recipe_predecessor(c, contract))
    deny('Checkout cannot omit native opt-in', lambda: hosting_checkout_recipe_predecessor([c.replace('CWP_TEST_HOSTING_CHECKOUT=1 ', '') for c in commands], contract))
    deny('Checkout cannot replace a PHP filename suffix', lambda: hosting_checkout_recipe_predecessor([c.replace('hosting_checkout_document.php', 'hosting_checkout_document."$PHP_BINARY"') for c in commands], contract))
    deny('Checkout workflow cannot alter old timeout', lambda: hosting_checkout_workflow_predecessor(workflow.replace('timeout-minutes: 360', 'timeout-minutes: 361', 1), checkout))
    deny('Checkout workflow cannot omit full-suite output mode', lambda: hosting_checkout_workflow_predecessor(workflow.replace('hosting_checkout_http_browser.py --full-suite', 'hosting_checkout_http_browser.py'), checkout))
    check('Checkout guards then browser run once after unchanged packaged continuation', checkout['browser_additions'] == ['.ci-venv/bin/python -B tests/hosting_checkout_http_guards.py > qa/hosting-checkout-http-guards.json', '.ci-venv/bin/python -B tests/hosting_checkout_http_browser.py --full-suite']
          and all(workflow.count(c) == 1 for c in checkout['browser_additions'])
          and workflow.index('hosting_purchase_handoff_browser.py --full-suite') < workflow.index(checkout['browser_additions'][0]) < workflow.index(checkout['browser_additions'][1]))
    hosting_source_bytes(ROOT, checkout['source_sha256'])
    check('Checkout binds actual native pure browser and fixture source', True)
    check('Checkout source inverses cover only view test two renderers and focused wiring', set(checkout['source_delta']) == {'tests/hosting_checkout_views.php', 'site/includes/layout/plans.php', 'site/includes/layout/recurring_billing.php', '.github/workflows/hosting-catalog-offers.yml'})
    for path, delta in checkout['source_delta'].items():
        raw = ROOT.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
        check('Checkout retains complete source preimage ' + path, bool(hosting_upgrade_source_predecessor(raw, delta)))
        deny('Checkout altered source cannot bypass exact inverse ' + path, lambda b=raw, d=delta: hosting_upgrade_source_predecessor(b + b'\n# changed', d))
        if path in contract['hosting_catalog_offers']['source_sha256']:
            check('Checkout existing source pin describes actual reviewed bytes ' + path, contract['hosting_catalog_offers']['source_sha256'][path] == delta['current_sha256'])
    check('Original browser driver and transfer helper remain byte-identical',
          all(hashlib.sha256(ROOT.joinpath(p).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == h for p, h in checkout['unchanged_source_sha256'].items())
          and set(checkout['unchanged_source_sha256']) == {'tests/browser_acceptance.py', 'tools/acceptance_artifacts.py'})
    reports = a.load(ROOT / 'tools/acceptance-reports.json') if reports is None else reports
    prior_reports = hosting_checkout_report_predecessor(reports, checkout)
    check('Checkout adds eleven required reports while retaining185 browser and236 final reports', len(prior_reports['browser']) == 185 and len(prior_reports['final']) == 236)
    for label in ('missing', 'duplicate', 'changed predecessor'):
        wrong = copy.deepcopy(reports)
        if label == 'missing': wrong['final'].remove(checkout['reports'][0])
        elif label == 'duplicate': wrong['final'].append(checkout['reports'][0])
        else: wrong['final'][0] += '.changed'
        deny('Checkout report union refuses ' + label, lambda r=wrong: hosting_checkout_report_predecessor(r, checkout))
    sample = tmp / 'checkout-reports'
    for name in checkout['reports']:
        named(sample / 'qa' / name)
        check('Checkout named report has only native-browser ownership ' + name,
              a.evidence_allowed('qa/' + name, 'native-browser') and all(not a.evidence_allowed('qa/' + name, lane) for lane in ('build', 'recovery', 'apache')))
        named(sample / 'qa' / name, False)
        check('Checkout cannot hide failed report ' + name, not a.reports(sample)[name]['passed'])
        (sample / 'qa' / name).unlink()
        check('Checkout cannot hide missing report ' + name, name not in a.reports(sample))
        named(sample / 'qa' / name)
    a.write(sample / 'qa/hosting-checkout-http-progress.txt', {'checks': [{'name': 'Incomplete diagnostic', 'passed': False}]})
    a.write(sample / 'qa/hosting-checkout-http-commands.json', [{'returncode': 0}])
    blob(sample / 'qa/hosting-checkout-http-server.log', b'synthetic loopback server diagnostic')
    check('Checkout progress commands and server log never count as passing reports', set(a.reports(sample)) == set(checkout['reports']))
    captures = ['Hosting-Checkout-' + stem + '-' + size + '.png' for stem in ('Owner-Publication', 'Project', 'Review', 'Result', 'Invoice', 'New-Project') for size in ('Desktop', 'Mobile')]
    check('Checkout requires exact twelve new capture names', checkout['screenshots'] == captures and len(set(captures)) == 12)
    for name in captures + checkout['diagnostics']:
        check('Checkout flat capture or diagnostic retains native-browser ownership ' + name,
              a.evidence_allowed('qa/' + name, 'native-browser') and all(not a.evidence_allowed('qa/' + name, lane) for lane in ('build', 'recovery', 'apache')))
    for name in ['test-runtime/hosting-checkout-private/state.json', 'test-runtime/hosting-checkout-web/index.php', 'test-runtime/hosting-checkout-browser-sessions/session', 'qa/hosting-checkout-http/results.json']:
        check('Checkout private runtime and nested evidence remain excluded ' + name, not a.evidence_allowed(name, 'native-browser'))
    deny('Checkout private key remains refused', lambda: a.evidence_allowed('test-runtime/hosting-checkout-private/provider-master.key', 'native-browser'))
    contract['native_commands'] = original
    return prior_workflow, prior_reports


def hosting_upgrade_workflow_predecessor(workflow, extension):
    edit = extension['workflow_delta']
    a.require(set(edit) == {'before', 'after'} and workflow.count(edit['after']) == 1, 'Exact hosting upgrade guard command missing')
    original = workflow.replace(edit['after'], edit['before'], 1)
    a.require(hashlib.sha256(original.encode()).hexdigest() == extension['predecessor_workflow_sha256'], 'Full workflow changed outside hosting upgrade guard')
    return original


def hosting_upgrade_source_predecessor(data, delta):
    a.require(hashlib.sha256(data).hexdigest() == delta['current_sha256'], 'Changed hosting upgrade source')
    for edit in reversed(delta['replacements']):
        before, after = edit['before'].encode(), edit['after'].encode()
        a.require(set(edit) == {'before', 'after'} and before and after and data.count(after) == 1, 'Missing exact hosting upgrade source addition')
        data = data.replace(after, before, 1)
    a.require(hashlib.sha256(data).hexdigest() == delta['predecessor_sha256'], 'Original upgrade source changed')
    return data


def hosting_upgrade_wiring_checks(tmp, workflow, contract, reports=None):
    extension = contract['published125_hosting_extension']
    prior_workflow = hosting_upgrade_workflow_predecessor(workflow, extension)
    check('Hosting upgrade adds its guard while retaining the exact prior full workflow', bool(prior_workflow))
    deny('Hosting upgrade workflow cannot omit its guard', lambda: hosting_upgrade_workflow_predecessor(prior_workflow, extension))
    deny('Hosting upgrade workflow cannot change an old timeout', lambda: hosting_upgrade_workflow_predecessor(workflow.replace('timeout-minutes: 360', 'timeout-minutes: 361', 1), extension))
    hosting_source_bytes(ROOT, extension['source_sha256'])
    check('Hosting upgrade binds all six actual source files', set(extension['source_sha256']) == {
        'tests/release_126_hosting_records.py', 'tests/release_126_hosting_fixture.php', 'tests/release_126_hosting_guards.py',
        'tests/release_126_upgrade_cases.py', 'tests/release_126_upgrade_acceptance.py', 'tests/release_126_provider_fixture.php'})
    deltas = extension['source_delta']
    check('Hosting upgrade modifies only three existing upgrade producers', set(deltas) == {
        'tests/release_126_upgrade_cases.py', 'tests/release_126_upgrade_acceptance.py', 'tests/release_126_provider_fixture.php'})
    previous = copy.deepcopy(contract['published_release_upgrade_126'])
    for path, delta in deltas.items():
        current = ROOT.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
        check('Hosting upgrade preserves exact original source ' + path, bool(hosting_upgrade_source_predecessor(current, delta)))
        deny('Hosting upgrade source mutation is refused ' + path, lambda d=current, x=delta: hosting_upgrade_source_predecessor(d + b'\n# mutation', x))
        check('Existing upgrade contract binds current producer ' + path, previous['source_sha256'][path] == delta['current_sha256'])
        previous['source_sha256'][path] = delta['predecessor_sha256']
    check('Original published baseline contract changes only three current source pins',
          hashlib.sha256(json.dumps(previous, sort_keys=True, separators=(',', ':')).encode()).hexdigest() == extension['predecessor_upgrade_contract_sha256'])
    check('Hosting supplement uses the immutable published1.25 baseline', previous['baseline_source'] == 'b79c67110829126916e3a65e616191542448bccc'
          and previous['baseline_sha256'] == 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39'
          and previous['baseline_members'] == 2895)
    expected = ['apache-release126-hosting-guards-results.json', 'apache-release126-hosting-results.json']
    check('Hosting upgrade adds exactly two required reports', extension['reports'] == expected)
    reports = a.load(ROOT / 'tools/acceptance-reports.json') if reports is None else reports
    for lane, count in [('browser', 185), ('final', 236)]:
        current = reports[lane]
        additions = expected if lane == 'final' else []
        prior = [name for name in current if name not in additions]
        check('Hosting upgrade preserves all preceding reports ' + lane, len(current) == len(set(current)) == count
              and all(current.count(name) == 1 for name in additions)
              and hashlib.sha256(json.dumps(prior, separators=(',', ':')).encode()).hexdigest() == extension['predecessor_reports_sha256'][lane])
        reports[lane] = prior
    sample = tmp / 'hosting-upgrade-reports'
    for name in expected:
        named(sample / 'qa' / name)
        check('Hosting upgrade report belongs only to Apache ' + name, a.evidence_allowed('qa/' + name, 'apache')
              and all(not a.evidence_allowed('qa/' + name, lane) for lane in ('build', 'native-browser', 'recovery')))
        named(sample / 'qa' / name, False)
        check('Hosting upgrade cannot hide failed report ' + name, not a.reports(sample)[name]['passed'])
        (sample / 'qa' / name).unlink()
        check('Hosting upgrade cannot hide missing report ' + name, name not in a.reports(sample))
        named(sample / 'qa' / name)
    prefixes = ('r126_ron_', 'r126_son_', 'r126_roff_', 'r126_soff_')
    snapshots = ['apache-release126-hosting-' + p + '-retention.json' for p in prefixes]
    images = ['apache-release126-hosting-' + p + '-Upgraded-Hosting-Offers-' + size + '.png' for p in prefixes for size in ('Desktop', 'Mobile')]
    check('Hosting upgrade inventories four snapshots and eight captures separately', extension['snapshots'] == snapshots and extension['screenshots'] == images)
    for name in snapshots:
        a.write(sample / 'qa' / name, {'sha256': '0' * 64})
    a.write(sample / 'qa/apache-release126-hosting-progress.txt', {'checks': [{'name': 'Incomplete synthetic progress', 'passed': False}]})
    check('Hosting upgrade snapshots and progress cannot count as acceptance reports', set(a.reports(sample)) == set(expected))
    for name in snapshots + images:
        check('Hosting upgrade output has unchanged Apache ownership ' + name, a.evidence_allowed('qa/' + name, 'apache')
              and all(not a.evidence_allowed('qa/' + name, lane) for lane in ('build', 'native-browser', 'recovery')))
    for name in ['test-runtime/apache-release126-private/state.json', 'test-runtime/apache-release126-private/sessions/session', 'qa/hosting-upgrade/nested.json']:
        check('Hosting upgrade private or nested path stays excluded ' + name, not a.evidence_allowed(name, 'apache'))
    deny('Hosting upgrade private provider key stays refused', lambda: a.evidence_allowed('test-runtime/apache-release126-private/provider-master.key', 'apache'))
    return prior_workflow, reports


def hosting_purchase_recipe_predecessor(commands, contract):
    joined=contract['hosting_joined_purchase']; additions=joined['additions']
    a.require(len(commands)==167 and commands==contract['native_commands'], 'Joined recipe requires exactly167 commands')
    a.require(additions==['.ci-venv/bin/python -B tests/hosting_catalog_purchase_guards.py','.ci-venv/bin/python -B tests/hosting_catalog_purchase.py --full-suite'], 'Exact joined runner and guards required')
    pos=commands.index(joined['additions_after'])+1
    a.require(commands[pos:pos+2]==additions and all(commands.count(c)==1 for c in additions), 'Joined commands occur once at exact position')
    original=[c for c in commands if c not in additions]
    a.require(len(original)==joined['predecessor_native_count']==165 and hashlib.sha256(json.dumps(original,separators=(',',':')).encode()).hexdigest()==joined['predecessor_native_sha256'], 'Original160 recipe changed')
    return original


def hosting_purchase_workflow_predecessor(workflow, joined):
    for edit in reversed(joined['workflow_delta']):
        a.require(set(edit)=={'before','after'} and edit['before'] and edit['after'] and workflow.count(edit['after'])==1, 'Exact joined workflow change missing')
        workflow=workflow.replace(edit['after'],edit['before'],1)
    a.require(hashlib.sha256(workflow.encode()).hexdigest()==joined['predecessor_workflow_sha256'], 'Prior full workflow changed outside joined additions')
    return workflow


def hosting_purchase_wiring_checks(tmp, workflow, commands, contract, reports=None):
    joined=contract['hosting_joined_purchase']
    original=hosting_purchase_recipe_predecessor(commands,contract)
    prior_workflow=hosting_purchase_workflow_predecessor(workflow,joined)
    check('Joined purchase retains the exact165 predecessor recipe and full workflow',len(original)==165 and bool(prior_workflow))
    for label,changed in [('missing',commands[:-1]),('duplicate',commands+[joined['additions'][0]]),('reordered',commands[1:]+commands[:1])]:
        deny('Joined recipe refuses '+label,lambda c=changed:hosting_purchase_recipe_predecessor(c,contract))
    deny('Joined recipe cannot omit full-suite output mode',lambda:hosting_purchase_recipe_predecessor([c.replace('hosting_catalog_purchase.py --full-suite','hosting_catalog_purchase.py') for c in commands],contract))
    deny('Joined workflow cannot alter existing job timeout',lambda:hosting_purchase_workflow_predecessor(workflow.replace('timeout-minutes: 360','timeout-minutes: 361',1),joined))
    deny('Joined workflow cannot omit isolated clock dependency',lambda:hosting_purchase_workflow_predecessor(workflow.replace('install -y libfaketime ','install -y ',1),joined))
    hosting_source_bytes(ROOT,joined['source_sha256'])
    check('Joined purchase exact current source producers and runtime dependencies are pinned',True)
    expected=['hosting-purchase-'+p+'-results.json' for p in ('prepare','due','portal-own','portal-other','joined')]+['hosting-purchase-handoff-guards.json','hosting-purchase-http-results.json']
    check('Joined purchase requires exact five native phase runner reports and two handoff reports',joined['reports']==expected and len(set(expected))==7)
    if reports is None: reports=a.load(ROOT/'tools/acceptance-reports.json')
    for lane,count in [('browser',185),('final',234)]:
        current=reports[lane];prior=[n for n in current if n not in expected]
        check('Joined purchase retains every original report '+lane,len(current)==len(set(current))==count and all(current.count(n)==1 for n in expected) and hashlib.sha256(json.dumps(prior,separators=(',',':')).encode()).hexdigest()==joined['predecessor_reports_sha256'][lane])
        reports[lane]=prior
    sample=tmp/'joined-purchase-reports'
    for name in expected:
        named(sample/'qa'/name)
        check('Joined report belongs only to unchanged flat browser partition '+name,a.evidence_allowed('qa/'+name,'native-browser') and all(not a.evidence_allowed('qa/'+name,lane) for lane in ('build','apache','recovery')))
    a.write(sample/'qa/hosting-purchase-http-progress.txt',{'checks':[{'name':'Synthetic incomplete','passed':False}]})
    a.write(sample/'qa/hosting-purchase-http-commands.json',[{'returncode':0}])
    check('Joined progress and commands cannot masquerade as additional passing reports',set(a.reports(sample))==set(expected))
    for name in expected:
        named(sample/'qa'/name,False)
        check('Joined failed report cannot be masked by passing neighbors '+name,not a.reports(sample)[name]['passed'])
        (sample/'qa'/name).unlink()
        check('Joined missing report remains missing '+name,name not in a.reports(sample))
        named(sample/'qa'/name)
    captures=['Hosting-Purchase-'+stem+'-'+size+'.png' for stem in ('Owner-Linked','Owner-Unlinked','Owner-Package','Client-Hosting','Other-Client') for size in ('Desktop','Mobile')]
    check('Joined handoff has exactly ten independently named captures',joined['screenshots']==captures and len(set(captures))==10)
    deny('Private joined provider key is explicitly refused',lambda:a.evidence_allowed('test-runtime/hosting-purchase/private/provider-master.key','native-browser'))
    for name in ['test-runtime/hosting-purchase/state.json','test-runtime/hosting-purchase/browser-sessions/session','qa/hosting-purchase/due-results.json','qa/hosting-purchase-http/results.json']:
        check('Private or nested joined file remains excluded '+name,not a.evidence_allowed(name,'native-browser'))
    return prior_workflow,original,reports


def hosting_recipe_predecessor(commands, contract):
    hosting = contract['hosting_catalog_offers']; additions = hosting['additions']
    a.require(len(commands) == 165 and commands == contract['native_commands'], 'Hosting recipe must contain exactly165 ordered commands')
    a.require(len(additions) == len(set(additions)) == 5, 'Exactly five hosting additions are required')
    position = commands.index(hosting['additions_after']) + 1
    a.require(commands[position:position + 5] == additions and all(commands.count(c) == 1 for c in additions), 'Hosting additions must occur exactly once at the reviewed position')
    original = [c for c in commands if c not in additions]
    a.require(len(original) == hosting['predecessor_native_count'] == 160 and hashlib.sha256(json.dumps(original, separators=(',', ':')).encode()).hexdigest() == hosting['predecessor_native_sha256'], 'Original155 hosting predecessor recipe changed')
    return original


def hosting_workflow_predecessor(workflow, hosting):
    for edit in reversed(hosting['workflow_delta']):
        a.require(set(edit) == {'before', 'after'} and edit['before'] and edit['after'] and workflow.count(edit['after']) == 1, 'Missing exact hosting workflow insertion')
        workflow = workflow.replace(edit['after'], edit['before'], 1)
    a.require(hashlib.sha256(workflow.encode()).hexdigest() == hosting['predecessor_workflow_sha256'], 'Original full workflow changed outside finite hosting additions')
    return workflow


def hosting_source_bytes(root, pins):
    a.require(pins and all(isinstance(v, str) and re.fullmatch('[0-9a-f]{64}', v) for v in pins.values()), 'Hosting source pins must all be bound')
    for path, expected in pins.items():
        a.safe_name(path)
        a.require(hashlib.sha256(historical_bytes(root, path)).hexdigest() == expected, 'Changed hosting source: ' + path)


def hosting_wiring_checks(tmp, workflow, commands, contract, reports=None):
    hosting = contract['hosting_catalog_offers']
    original = hosting_recipe_predecessor(commands, contract)
    check('Hosting adds five ordered commands while preserving all160 predecessor commands', len(original) == 160)
    check('Hosting workflow reverses exactly without changing old jobs or outcomes', bool(hosting_workflow_predecessor(workflow, hosting)))
    for label, changed in [('missing', commands[:-1]), ('duplicate', commands + [hosting['additions'][0]]), ('reordered', commands[1:] + commands[:1])]:
        deny('Hosting recipe refuses ' + label + ' commands', lambda c=changed: hosting_recipe_predecessor(c, contract))
    wrong = copy.deepcopy(contract); wrong['hosting_catalog_offers']['additions'][2] = wrong['hosting_catalog_offers']['additions'][2].replace('CWP_TEST_HOSTING_RECOVERY=1 ', '')
    deny('Hosting recovery opt-in cannot silently disappear', lambda: hosting_recipe_predecessor(commands, wrong))
    deny('Hosting workflow refuses an unrelated timeout change', lambda: hosting_workflow_predecessor(workflow.replace('timeout-minutes: 360', 'timeout-minutes: 361', 1), hosting))
    deny('Hosting workflow refuses removal of raw recovery retention', lambda: hosting_workflow_predecessor(workflow.replace(hosting['workflow_delta'][-1]['after'], hosting['workflow_delta'][-1]['before'], 1), hosting))
    hosting_source_bytes(ROOT, hosting['source_sha256'])
    check('Hosting source and recovery producers match every exact current pin', True)
    pin_root = tmp / 'hosting-source'; blob(pin_root / 'tests/probe.php', b'<?php /* synthetic */')
    pins = {'tests/probe.php': a.digest(pin_root / 'tests/probe.php')}
    hosting_source_bytes(pin_root, pins)
    (pin_root / 'tests/probe.php').unlink()
    deny('Missing hosting producer source cannot pass', lambda: hosting_source_bytes(pin_root, pins))
    blob(pin_root / 'tests/probe.php', b'<?php /* changed */')
    deny('Changed hosting producer source cannot pass', lambda: hosting_source_bytes(pin_root, pins))
    deny('Pending hosting producer pin cannot pass', lambda: hosting_source_bytes(pin_root, {'tests/probe.php': None}))
    reports = a.load(ROOT / 'tools/acceptance-reports.json') if reports is None else reports
    names = hosting['reports']
    expected_names = ['hosting-catalog-document-default.json', 'hosting-catalog-document-minimal.json',
                      'hosting-catalog-native-results.json', 'hosting-catalog-http-results.json',
                      'hosting-catalog-recovery-guard-default.json', 'hosting-catalog-recovery-guard-minimal.json',
                      'hosting-catalog-recovery-native-results.json']
    check('Hosting requires exactly seven distinct named reports', names == expected_names and len(set(names)) == 7)
    for lane, count in [('browser', 178), ('final', 227)]:
        current = reports[lane]; prior = [n for n in current if n not in names]
        check('Hosting preserves exact original report union ' + lane, len(current) == len(set(current)) == count and all(current.count(n) == 1 for n in names) and hashlib.sha256(json.dumps(prior, separators=(',', ':')).encode()).hexdigest() == hosting['predecessor_reports_sha256'][lane])
    report_root = tmp / 'hosting-reports'
    for name in names:
        named(report_root / 'qa' / name)
        check('Hosting report remains in the existing flat native browser partition ' + name, a.evidence_allowed('qa/' + name, 'native-browser') and all(not a.evidence_allowed('qa/' + name, lane) for lane in ('build', 'recovery', 'apache')))
    a.write(report_root / 'qa/hosting-catalog-http-commands.json', [{'command': ['synthetic'], 'returncode': 0}])
    a.write(report_root / 'qa/hosting-catalog-http-progress.txt', {'passed': False, 'checks': [{'name': 'Incomplete synthetic progress', 'passed': False}]})
    check('Hosting diagnostic progress and commands cannot become extra passing reports', set(a.reports(report_root)) == set(names))
    for name in names:
        named(report_root / 'qa' / name, False)
        check('Successful neighboring reports cannot mask hosting failure ' + name, not a.reports(report_root)[name]['passed'])
        named(report_root / 'qa' / name)
        (report_root / 'qa' / name).unlink()
        check('Absent hosting report remains absent ' + name, name not in a.reports(report_root))
        named(report_root / 'qa' / name)
    duplicate = report_root / 'qa' / names[0]
    duplicate.write_bytes(b'{"passed":true,"passed":false,"checks":[{"name":"Synthetic","passed":true}]}')
    deny('Duplicate keys cannot disguise a failed hosting report', lambda: a.reports(report_root))
    captures = hosting['screenshots']
    expected_captures = ['Hosting-Offers-' + stem + '-' + size + '.png' for stem in ('Owner-Preview', 'Public', 'Owner-Request', 'Client-History', 'Client-Agreement') for size in ('Desktop', 'Mobile')]
    check('Hosting adds only the ten exact desktop and mobile captures', captures == expected_captures and len(set(captures)) == 10 and all(a.evidence_allowed('qa/' + n, 'native-browser') for n in captures))
    for name in ['test-runtime/hosting-recovery-absent-0000000000000000/site/index.php', 'qa/hosting-recovery/site/index.php']:
        check('Raw recovery files require their separate artifact ' + name, not a.evidence_allowed(name, 'native-browser'))
    return original


def catalog_storefront_http_predecessor(contract):
    extension = contract['catalog_storefront_http']
    prior = copy.deepcopy(contract)
    del prior['catalog_storefront_http']
    for path, location in extension['updated_pins'].items():
        delta = extension['source_delta'][path]
        a.require(prior[location]['source_sha256'][path] == delta['current_sha256'], 'Storefront HTTP current source pin changed')
        prior[location]['source_sha256'][path] = delta['predecessor_sha256']
    a.require(hashlib.sha256(json.dumps(prior, sort_keys=True, separators=(',', ':')).encode()).hexdigest() == extension['predecessor_contract_sha256'], 'Original storefront acceptance contract changed')
    return prior


def catalog_storefront_http_checks(contract):
    extension = contract['catalog_storefront_http']
    pins = {'tests/provider_package_types_cases.py': 'provider_package_types',
            'tests/hosting_checkout_http_browser.py': 'hosting_checkout'}
    check('Storefront HTTP changes only the two exact callback producer pins', extension['updated_pins'] == pins and set(extension['source_delta']) == set(pins))
    hosting_source_bytes(ROOT, extension['source_sha256'])
    check('Storefront HTTP binds both installed case modules and their actual callers', set(extension['source_sha256']) == set(pins) | {'tests/catalog_storefront_http_cases.py', 'tests/catalog_storefront_checkout_cases.py'})
    for path, delta in extension['source_delta'].items():
        raw = historical_bytes(ROOT, path)
        check('Storefront HTTP retains the complete prior producer ' + path, bool(hosting_upgrade_source_predecessor(raw, delta)))
        deny('Storefront HTTP refuses an altered producer ' + path, lambda b=raw, d=delta: hosting_upgrade_source_predecessor(b + b'\n# changed', d))
    check('Storefront HTTP preserves the entire 9b450 acceptance contract', bool(catalog_storefront_http_predecessor(contract)))
    wrong = copy.deepcopy(contract); wrong['native_commands'].pop()
    deny('Storefront HTTP cannot hide removal of prior native coverage', lambda: catalog_storefront_http_predecessor(wrong))
    wrong = copy.deepcopy(contract); wrong['hosting_checkout']['source_sha256']['tests/hosting_checkout_http_browser.py'] = '0' * 64
    deny('Storefront HTTP cannot hide a mismatched original producer pin', lambda: catalog_storefront_http_predecessor(wrong))
    unchanged = {'.github/workflows/application-tests.yml', '.github/workflows/catalog-storefront.yml',
                 '.github/workflows/hosting-catalog-offers.yml', 'tests/browser_acceptance.py',
                 'tests/hosting_checkout_http_cases.py', 'tools/acceptance_artifacts.py', 'tools/acceptance-reports.json'}
    check('Storefront HTTP retains original workflows browser cases report inventory and transfer helper', set(extension['unchanged_source_sha256']) == unchanged)
    hosting_source_bytes(ROOT, extension['unchanged_source_sha256'])
    captures = ['Catalog-Storefront-' + stem + '.png' for stem in (
        'Connection-Desktop', 'Connection-Mobile', 'Batch-Desktop', 'Batch-Mobile',
        'Comparison-Desktop', 'Comparison-Mobile', 'Selected-Review', 'Unpaid-Invoice')]
    check('Storefront HTTP inventories exactly eight new installed captures', extension['screenshots'] == captures and len(set(captures)) == 8)
    for name in captures:
        check('Storefront installed capture retains native-browser ownership ' + name,
              a.evidence_allowed('qa/' + name, 'native-browser') and all(not a.evidence_allowed('qa/' + name, lane) for lane in ('build', 'recovery', 'apache')))


def catalog_storefront_source_checks(contract):
    extension = contract['catalog_storefront']
    hosting_source_bytes(ROOT, extension['source_sha256'])
    check('Catalog storefront binds all declared current source and focused test bytes', True)
    path = 'site/includes/layout/hosting_catalog.php'
    check('Catalog storefront changes only the retained public renderer source pin', set(extension['source_delta']) == {path})
    delta = extension['source_delta'][path]
    raw = ROOT.joinpath(path).read_bytes().replace(b'\r\n', b'\n')
    check('Catalog comparison preserves complete predecessor card renderers', bool(hosting_upgrade_source_predecessor(raw, delta)))
    deny('Catalog comparison refuses changed current source', lambda: hosting_upgrade_source_predecessor(raw + b'\n# mutation', delta))
    prior = catalog_storefront_http_predecessor(contract)
    del prior['catalog_storefront']
    check('Hosting renderer source pin binds the exact appended comparison', prior['hosting_catalog_offers']['source_sha256'][path] == delta['current_sha256'])
    prior['hosting_catalog_offers']['source_sha256'][path] = delta['predecessor_sha256']
    check('Every prior acceptance contract entry is retained exactly', hashlib.sha256(json.dumps(prior, sort_keys=True, separators=(',', ':')).encode()).hexdigest() == extension['predecessor_contract_sha256'])
    check('Original application and hosting workflows remain unchanged', all(hashlib.sha256(historical_bytes(ROOT, p)).hexdigest() == h for p, h in extension['unchanged_workflows'].items()))


def run(tmp):
    def remove_partition(path):
        resolved=path.resolve();require_parent=tmp.resolve()
        assert resolved.is_relative_to(require_parent) and resolved.name=='native-browser'
        shutil.rmtree(resolved)
    ctx = context(); source = tmp / 'source'; fixtures(source, ctx)
    # Exercise the actual entry points and GitHub output file, including the
    # numeric suffix used by both immutable artifact-manifest outputs.
    cli_root=tmp/'cli-source';fixtures(cli_root,ctx);job_output=tmp/'github-output'
    with patch.object(a,'ROOT',cli_root),patch.object(a,'identity',return_value=ctx),patch.dict(a.os.environ,{'GITHUB_OUTPUT':str(job_output),'CWP_STEP_OUTCOMES':json.dumps(outcomes('build')),'CWP_INPUT_SHA256':''}):
        with patch.object(sys,'argv',['acceptance_artifacts.py','pack-inputs']):a.main()
        expected='sha256='+a.digest(cli_root/'.acceptance/inputs/inputs.json')+'\n'
        check('Actual pack-inputs entry point writes its sha256 manifest digest to GitHub output',job_output.read_text()==expected)
        with patch.object(sys,'argv',['acceptance_artifacts.py','seal','--lane','build']):a.main()
        expected+='sha256='+a.digest(cli_root/'.acceptance/partition/partition.json')+'\n'
        check('Actual seal entry point appends its sha256 manifest digest to GitHub output',job_output.read_text()==expected)
        for label,name in [('empty',''),('digit prefix','9sha'),('line break','sha256\ninjected'),('assignment','sha256=x')]:
            deny('Job output refuses '+label+' name',lambda n=name:a.output(n,'a'*64))
        for label,value in [('line feed','a\ninjected=yes'),('carriage return','a\rinjected=yes')]:
            deny('Job output refuses '+label+' injection',lambda v=value:a.output('sha256',v))
        check('Rejected output fields never append to the GitHub output file',job_output.read_text()==expected)
    transfer = tmp / 'input'; input_sha = a.create_inputs(source, ctx, transfer)
    obj = a.verify_bundle(transfer, 'inputs', ctx, input_sha)
    check('Input binds all27 public fixture trees plus exact package/checksum files', len(a.FIXTURES - {'released-1.25.0'}) == 27 and 'dist/SHA256SUMS.txt' in obj['files'])
    additional_baseline_checks(tmp, ctx, source, transfer, input_sha)
    for lane in a.LANES[1:]:
        target = tmp / ('receive-' + lane); target.mkdir()
        (target / 'qa').mkdir()
        a.receive_inputs(transfer, target, ctx, input_sha, lane)
        check('Exact input materializes safely for ' + lane, (target / 'test-runtime/legacy/.htaccess').read_bytes() == b'Deny public listing')
        check('Both original published archives and distinct1.25 members reach ' + lane,
              (target / 'test-runtime' / a.PUBLISHED_BASELINE).read_bytes() == synthetic_baseline()
              and (target / 'test-runtime' / a.PUBLISHED_BASELINE_126).read_bytes() == synthetic_baseline_126()
              and (target / 'test-runtime/released-1.25.0/includes/public.php').read_bytes() == b'public synthetic 1.25 bytes')
        check('Only native/browser inherits build QA ' + lane, (target / 'qa/build-results.txt').exists() == (lane == 'native-browser'))
        check('Each child saves same-attempt immutable input receipt ' + lane, a.load(target / '.acceptance/input-receipt.json') == {'identity': ctx, 'input_sha256': input_sha, 'lane': lane})
        deny('Input materialization never overwrites an earlier child ' + lane, lambda t=target,l=lane: a.receive_inputs(transfer,t,ctx,input_sha,l))
    for field, value in [('attempt', '2'), ('run_id', '124'), ('ci_sha', '1' * 40), ('source_sha', '2' * 40), ('tree', '3' * 40), ('repository', 'foreign/repo'), ('workflow_sha256', '4' * 64), ('reports_sha256', '5' * 64)]:
        changed = {**ctx, field: value}
        deny('Input refuses mismatched ' + field, lambda c=changed: a.verify_bundle(transfer, 'inputs', c, input_sha))
    for environment in ({'php':'8.2','database':'mariadb-10.11'},{'php':'8.3','database':'mysql-8.4'}):
        changed={**ctx,'environment':environment}
        deny('Input refuses mismatched runtime '+json.dumps(environment,sort_keys=True),lambda c=changed:a.verify_bundle(transfer,'inputs',c,input_sha))
    deny('Input refuses missing expected digest', lambda: a.verify_bundle(transfer, 'inputs', ctx, ''))
    deny('Input refuses changed manifest bytes', lambda: a.verify_bundle(transfer, 'inputs', ctx, '0' * 64))
    broken = tmp / 'broken'; shutil.copytree(transfer, broken)
    archive = broken / 'inputs.zip'; data = archive.read_bytes(); archive.write_bytes(data[:-1] + bytes([data[-1] ^ 1]))
    deny('Input refuses corrupt outer bytes before extraction', lambda: a.verify_bundle(broken, 'inputs', ctx, input_sha))
    duplicate=tmp/'duplicate.json';blob(duplicate,b'{"identity":1,"identity":2}')
    deny('Duplicate JSON keys cannot hide competing identities',lambda:a.load(duplicate))
    bad_source=tmp/'private-source';shutil.copytree(source,bad_source);blob(bad_source/'test-runtime/legacy/config.php',b'private synthetic')
    deny('Actual input packing rejects private configuration inside public tree',lambda:a.create_inputs(bad_source,ctx,tmp/'private-input'))
    changed=tmp/'changed-member';shutil.copytree(transfer,changed)
    p=changed/'inputs.zip'
    with zipfile.ZipFile(p) as z:members={n:z.read(n) for n in z.namelist()}
    members['test-runtime/legacy/.htaccess']=b'altered public member'
    with zipfile.ZipFile(p,'w') as z:
        for n,data in members.items():z.writestr(n,data)
    manifest=a.load(changed/'inputs.json');manifest['archive']=a.file_facts(p);a.write(changed/'inputs.json',manifest)
    deny('Rehashed outer ZIP still refuses member content mismatch',lambda:a.verify_bundle(changed,'inputs',ctx,a.digest(changed/'inputs.json')))
    del members['test-runtime/legacy/.htaccess']
    with zipfile.ZipFile(p,'w') as z:
        for n,data in members.items():z.writestr(n,data)
    manifest['archive']=a.file_facts(p);a.write(changed/'inputs.json',manifest)
    deny('Rehashed ZIP still refuses missing member',lambda:a.verify_bundle(changed,'inputs',ctx,a.digest(changed/'inputs.json')))
    for name in ['../escaped', '/absolute', 'a/../b', 'a\\b', 'C:/outside', 'a//b', './a', 'a/./b', 'a\x00b']:
        deny('Noncanonical member refused ' + repr(name), lambda n=name: a.safe_name(n))
    for name in ['test-runtime/private/key.txt', 'test-runtime/config-fixture.php', 'test-runtime/legacy/config.php', 'test-runtime/legacy/.installed.php', 'test-runtime/legacy/server.key', 'qa/session.php', 'test-runtime/build-guards-private/fake.php']:
        try: allowed = a.input_allowed(name)
        except ValueError: allowed = False
        check('Private/unapproved input refused ' + name, not allowed)
    # Test malformed ZIPs with internally matching outer metadata: member rules must still reject.
    for title, names, mode in [('duplicate', ['same','same'], None), ('case collision', ['Same','same'], None), ('traversal', ['../outside'], None), ('symlink', ['link'], 0o120777)]:
        p = tmp / (title.replace(' ', '-') + '.zip'); files = {}
        import warnings
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', UserWarning)
            with zipfile.ZipFile(p, 'w') as z:
                for n in names:
                    info = zipfile.ZipInfo(n)
                    if mode: info.external_attr = mode << 16
                    z.writestr(info, b'x'); files[n] = {'size': 1, 'sha256': hashlib.sha256(b'x').hexdigest()}
        deny('ZIP refuses ' + title + ' independently of envelope hash', lambda p=p,f=files: a.inspect_archive(p, f))
    # A complete four-partition synthetic run exercises the real seal and aggregate paths.
    incoming = tmp / 'partitions'; bindings = {}; lane_roots = {}
    for i,lane in enumerate(a.LANES):
        root = tmp / ('job-' + lane); root.mkdir(); lane_roots[lane] = root
        if lane == 'build': shutil.copytree(source / 'dist', root / 'dist'); shutil.copytree(source / 'qa', root / 'qa')
        elif lane == 'native-browser': shutil.copytree(source / 'qa', root / 'qa'); named(root / 'qa/http-results.json'); blob(root / 'qa/actual-view.png', b'exact image bytes')
        elif lane == 'recovery': named(root / 'qa/structure-results.json')
        else: named(root / 'qa/apache-results.json')
        d = a.seal(root,ctx,incoming/lane,lane,outcomes(lane),input_sha)
        bindings[lane] = {'result':'success','artifact_id':str(i+1),'sha256':d,**({'input_sha256':input_sha} if lane=='build' else {}),**({'checkpoint_id':'9','checkpoint_digest':'6'*64} if lane=='native-browser' else {})}
    expected = {'browser':['build-guards-results.json','parallel-helper-tests.json','http-results.json','build-environment-results.json'],
                'final':['build-guards-results.json','parallel-helper-tests.json','http-results.json','structure-results.json','apache-results.json','build-environment-results.json']}
    count = 0
    def combined(b=bindings, inc=incoming, cp=False, exp=expected):
        nonlocal count
        count += 1; root = tmp / ('aggregate-' + str(count)); root.mkdir()
        result = a.aggregate(root,ctx,inc,b,exp,cp)
        return result, root
    result, target = combined()
    check('Complete exact four-partition run assembles all named reports', result['complete'] and result['full_partition_checks_passed'] and set(result['reports']) == set(expected['final']))
    check('Final carries exact browser screenshot bytes', (target/'qa/actual-view.png').read_bytes()==b'exact image bytes')
    check('Checkpoint is the same immutable QA snapshot used by final', all(p.read_bytes()==(target/'qa'/p.name).read_bytes() for p in (lane_roots['native-browser']/'.acceptance/checkpoint/qa').iterdir()))
    check('Final packages remain exact original build', all(a.digest(p)==a.digest(target/'dist'/p.name) for p in (source/'dist').iterdir()))
    check('Final records every immutable partition ID and digest', all(result['partitions'][n]['artifact_id']==bindings[n]['artifact_id'] and result['partitions'][n]['manifest_sha256']==bindings[n]['sha256'] for n in a.LANES))
    b=copy.deepcopy(bindings)
    for lane in ['recovery','apache']:b[lane]={'result':'skipped','artifact_id':'','sha256':''}
    result,_=combined(b,cp=True)
    check('Deliberate checkpoint has complete limited evidence, never full acceptance', result['complete'] and not result['full_partition_checks_passed'] and result['mode']=='checkpoint')
    b['apache']['result']='cancelled';result,_=combined(b,cp=True)
    check('Checkpoint does not disguise cancellation as intentional skip', not result['complete'])
    for lane in a.LANES:
        for state in ['failure','cancelled','skipped']:
            b=copy.deepcopy(bindings);b[lane]['result']=state;result,root=combined(b)
            check('Non-successful '+lane+' '+state+' remains incomplete with partial evidence', not result['complete'] and (root/'qa/http-results.json').exists())
        b=copy.deepcopy(bindings);b[lane]['artifact_id']='';result,root=combined(b)
        check('Missing '+lane+' artifact cannot become success', not result['complete'] and lane not in result['partitions'])
    b=copy.deepcopy(bindings);b['native-browser']['sha256']='0'*64;result,root=combined(b)
    check('Corrupt partition digest is refused while other evidence survives', not result['complete'] and 'native-browser' not in result['partitions'] and (root/'qa/structure-results.json').exists())
    b=copy.deepcopy(bindings);b['build']['input_sha256']='0'*64;result,_=combined(b)
    check('Different input generation cannot mix into final', not result['complete'] and set(result['partitions'])=={'build'})
    b=copy.deepcopy(bindings);b['apache']['artifact_id']=b['recovery']['artifact_id'];result,_=combined(b)
    check('Duplicate artifact IDs never masquerade as independent jobs', not result['complete'])
    b=copy.deepcopy(bindings);b['native-browser']['checkpoint_id']='';result,_=combined(b)
    check('Missing checkpoint upload cannot pass final acceptance', not result['complete'] and 'native-browser' in result['partitions'])
    for title, change in [('cross-attempt',lambda o:o['identity'].update(attempt='2')),('missing steps',lambda o:o.update(steps={})),('wrong lane',lambda o:o.update(lane='build'))]:
        inc=tmp/('changed-'+title);shutil.copytree(incoming,inc);path=inc/'native-browser/partition.json';o=a.load(path);change(o);a.write(path,o)
        b=copy.deepcopy(bindings);b['native-browser']['sha256']=a.digest(path);result,_=combined(b,inc)
        check('Internally hashed '+title+' partition is refused', not result['complete'] and 'native-browser' not in result['partitions'])
    # Re-seal an explicitly failed report; truthful output is preserved rather than discarded.
    failed_root=tmp/'failed-native';shutil.copytree(lane_roots['native-browser'],failed_root);named(failed_root/'qa/http-results.json',False)
    inc=tmp/'failed-partitions';shutil.copytree(incoming,inc);remove_partition(inc/'native-browser')
    # The copied immutable checkpoint is not an input to the new synthetic seal.
    # Use a different snapshot root by omitting the prior metadata tree from copies.
    old=failed_root/'.acceptance/checkpoint'
    if old.exists():
        assert old.resolve().is_relative_to(tmp.resolve());shutil.rmtree(old)
    b=copy.deepcopy(bindings);b['native-browser']['sha256']=a.seal(failed_root,ctx,inc/'native-browser','native-browser',outcomes('native-browser'),input_sha)
    result,root=combined(b,inc)
    check('Named failure beats successful job labels and remains available', not result['complete'] and result['reports']['http-results.json']['failed_names']==['Synthetic exact check'] and a.load(root/'qa/http-results.json')['passed'] is False)
    exp=copy.deepcopy(expected);exp['final'].append('missing-native-results.json');result,_=combined(exp=exp)
    check('Missing expected named report cannot pass', not result['complete'])
    exp=copy.deepcopy(expected);exp['final'].remove('apache-results.json');result,_=combined(exp=exp)
    check('Unexpected named report forces expectation review', not result['complete'])
    # Shared build facts may repeat only byte-identically in native/browser.
    root=tmp/'collision-native';shutil.copytree(lane_roots['native-browser'],root);blob(root/'qa/build-results.txt',b'changed build')
    inc=tmp/'collision-parts';shutil.copytree(incoming,inc);remove_partition(inc/'native-browser')
    old=root/'.acceptance/checkpoint'
    if old.exists():
        assert old.resolve().is_relative_to(tmp.resolve());shutil.rmtree(old)
    b=copy.deepcopy(bindings);b['native-browser']['sha256']=a.seal(root,ctx,inc/'native-browser','native-browser',outcomes('native-browser'),input_sha)
    result,_=combined(b,inc)
    check('Changed duplicate build evidence cannot silently overwrite', not result['complete'] and 'native-browser' not in result['partitions'])
    inc=tmp/'case-alias-parts';shutil.copytree(incoming,inc);b=copy.deepcopy(bindings)
    for lane,name in [('native-browser','Apache-error.log'),('apache','apache-error.log')]:
        root=tmp/('case-alias-'+lane);shutil.copytree(lane_roots[lane]/'qa',root/'qa');blob(root/'qa'/name,b'case collision')
        target=inc/lane;assert target.resolve().is_relative_to(tmp.resolve());shutil.rmtree(target)
        b[lane]['sha256']=a.seal(root,ctx,target,lane,outcomes(lane),input_sha)
    result,_=combined(b,inc)
    check('Cross-partition case alias is refused by explicit global ownership before extraction',
          not result['complete'] and 'apache' not in result['partitions'] and 'apache: Case-colliding evidence path' in result['errors'])
    root=tmp/'integer-report';path=root/'qa/counts.json'
    rows=[{'name':'First count','passed':True},{'name':'Second count','passed':True}]
    a.write(path,{'passed':2,'checks':rows,'failed':0})
    check('Existing integer aggregate report accepts exact passing execution count',a.reports(root)['counts.json']['passed'])
    a.write(path,{'passed':1,'checks':rows,'failed':0})
    check('Incorrect integer aggregate count cannot mask named outcomes',not a.reports(root)['counts.json']['passed'])
    a.write(path,{'passed':2,'checks':rows,'error':'synthetic failure'})
    check('Integer aggregate with failure detail remains failed',not a.reports(root)['counts.json']['passed'])
    for lane,name in [('apache','qa/http-results.json'),('recovery','qa/apache-results.json'),('native-browser','qa/structure-results.json'),('build','test-runtime/private/key.txt')]:
        check('Evidence ownership refuses '+lane+' '+name, not a.evidence_allowed(name,lane))
    snapshot_root=tmp/'access-snapshot-report';snapshot_name='stackcp-access-read-snapshot-native-results.json';seed_name='stackcp-access-read-snapshot-seed.json'
    named(snapshot_root/'qa'/snapshot_name);a.write(snapshot_root/'qa'/seed_name,{'passed':True,'scope':'Synthetic metadata, not acceptance','prefix':'sapr_','linked_packages':21,'local_assignments':20})
    check('Finite access snapshot seed stays diagnostic metadata beside its required report',set(a.reports(snapshot_root))=={snapshot_name} and a.evidence_allowed('qa/'+seed_name,'native-browser') and not a.input_allowed('qa/'+seed_name))
    named(snapshot_root/'qa'/snapshot_name,False)
    check('Successful seed cannot mask a failed access snapshot native report',not a.reports(snapshot_root)[snapshot_name]['passed'])
    from delivery_evidence_cases import run as delivery_evidence_cases
    delivery_evidence_cases(a, ROOT, tmp/'delivery-evidence', check, deny, context, fixtures, outcomes)
    from acceptance_environment_test import run as environment_checks
    environment_checks(a,check,deny,tmp)
    # Retain reviewed driver bytes and the complete predecessor command sequence.
    php_controls_partition_checks(tmp, ctx, lane_roots, input_sha, expected)
    contract=a.load(ROOT/'tools/acceptance-contract.json')
    nameserver_request_extension=contract['registered_nameserver_requests']
    from registered_nameserver_requests_acceptance import verify as nameserver_requests_acceptance
    requests_predecessor = nameserver_requests_acceptance(a, ROOT, tmp, check, deny, contract)
    contract = requests_predecessor['contract']
    nameserver_installed_extension=contract['registered_nameserver_changes_installed']
    from registered_nameserver_changes_acceptance import verify as nameserver_changes_acceptance
    changes_predecessor = nameserver_changes_acceptance(a, ROOT, tmp, check, deny, contract, requests_predecessor['source'], requests_predecessor['reports'])
    # New write controls extend the recipe without changing predecessor assertions.
    write_contract=contract['registered_nameserver_changes'];write_commands=write_contract['additions']
    nameserver_contract=contract['registered_nameservers'];nameserver_commands=nameserver_contract['additions']
    nameserver_commands_list=list(contract['native_commands'])
    check('Nameserver change extension retains complete recipe',len(nameserver_commands_list)==write_contract['predecessor_native_count']+len(write_commands) and nameserver_commands_list==contract['native_commands'])
    write_position=nameserver_commands_list.index(write_contract['additions_after'])+1
    check('Seven nameserver change commands occur once after original reader native fixture',len(write_commands)==len(set(write_commands))==7 and nameserver_commands_list[write_position:write_position+7]==write_commands and all(nameserver_commands_list.count(command)==1 for command in write_commands))
    nameserver_commands_list=[command for command in nameserver_commands_list if command not in write_commands]
    check('Nameserver changes retain exact predecessor commands',len(nameserver_commands_list)==write_contract['predecessor_native_count'] and hashlib.sha256(json.dumps(nameserver_commands_list,separators=(',',':')).encode()).hexdigest()==write_contract['predecessor_native_sha256'])
    contract['native_commands']=[command for command in contract['native_commands'] if command not in write_commands]
    for path,expected_hash in write_contract['source_sha256'].items():
        check('Nameserver change producer source retained '+path,hashlib.sha256(requests_predecessor['source'].get(path,ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n'))).hexdigest()==expected_hash)
    write_original_sources={}
    check('Nameserver change predecessor test delta is limited to the existing renderer',set(write_contract['predecessor_source_delta'])=={'tests/registered_nameservers_views.php'})
    for path,delta in write_contract['predecessor_source_delta'].items():
        data=ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n').decode('utf-8')
        check('Nameserver change pending status test addition is finite '+path,data.count(delta['after'])==1)
        original=data.replace(delta['after'],delta['before'],1).encode('utf-8')
        check('Nameserver change test addition preserves all predecessor assertions '+path,hashlib.sha256(original).hexdigest()==delta['predecessor_sha256']==contract['registered_nameservers']['source_sha256'][path])
        write_original_sources[path]=original
    write_reports=a.load(ROOT/'tools/acceptance-reports.json')
    check('Nameserver change reports are required once in both aggregates',len(write_contract['reports'])==len(set(write_contract['reports']))==7 and all(write_reports['browser'].count(name)==write_reports['final'].count(name)==1 for name in write_contract['reports']))
    check('Nameserver extension retains complete recipe',len(nameserver_commands_list)==nameserver_contract['predecessor_native_count']+len(nameserver_commands) and nameserver_commands_list==contract['native_commands'])
    nameserver_position=nameserver_commands_list.index(nameserver_contract['additions_after'])+1
    check('Five nameserver commands occur once in the intended position',len(nameserver_commands)==len(set(nameserver_commands))==5 and nameserver_commands_list[nameserver_position:nameserver_position+5]==nameserver_commands and all(nameserver_commands_list.count(command)==1 for command in nameserver_commands))
    nameserver_commands_list=[command for command in nameserver_commands_list if command not in nameserver_commands]
    check('Nameserver extension preserves exact predecessor commands',len(nameserver_commands_list)==nameserver_contract['predecessor_native_count'] and hashlib.sha256(json.dumps(nameserver_commands_list,separators=(',',':')).encode()).hexdigest()==nameserver_contract['predecessor_native_sha256'])
    for path,expected_hash in nameserver_contract['source_sha256'].items():
        check('Nameserver fixture source retained '+path,hashlib.sha256(write_original_sources.get(path,changes_predecessor['source'].get(path,ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')))).hexdigest()==expected_hash)
    nameserver_server=changes_predecessor['source']['tests/provider_http_server.py'].decode('utf-8')
    for index,change in enumerate(reversed(nameserver_contract['server_changes'])):
        check('Nameserver synthetic GET addition is finite '+str(index),nameserver_server.count(change['after'])==change['count'])
        nameserver_server=nameserver_server.replace(change['after'],change['before'])
    check('Nameserver synthetic GET preserves all prior provider fixture bytes',hashlib.sha256(nameserver_server.encode('utf-8')).hexdigest()==nameserver_contract['server_predecessor_sha256'])
    nameserver_upgrade=nameserver_contract['upgrade']
    nameserver_apache_header='      - name: Apache protection with and without rewriting\n        id: test\n        run: |\n'
    nameserver_apache=[line.strip() for line in changes_predecessor['workflow'].split(nameserver_apache_header,1)[1].split('      - name:',1)[0].splitlines()]
    nameserver_upgrade_position=nameserver_apache.index(nameserver_upgrade['commands'][0])
    check('Nameserver upgrade inserts exactly two Apache commands after the original release-125 sequence',len(nameserver_upgrade['commands'])==2 and nameserver_apache[nameserver_upgrade_position:nameserver_upgrade_position+2]==nameserver_upgrade['commands'] and nameserver_apache[:nameserver_upgrade_position]==nameserver_upgrade['predecessor_commands'] and len(nameserver_apache)==len(set(nameserver_apache)))
    check('Nameserver upgrade preserves all prior Apache commands',hashlib.sha256(json.dumps(nameserver_apache[:nameserver_upgrade_position],separators=(',',':')).encode()).hexdigest()==nameserver_upgrade['predecessor_sha256'])
    for path,expected_hash in nameserver_upgrade['source_sha256'].items():
        check('Nameserver upgrade fixture source retained '+path,hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
    nameserver_reports=a.load(ROOT/'tools/acceptance-reports.json')
    check('Nameserver native reports required in both aggregates',all(name in nameserver_reports['browser'] and name in nameserver_reports['final'] for name in nameserver_contract['reports']))
    check('Nameserver Apache reports required only in final aggregate',all(name in nameserver_reports['final'] and name not in nameserver_reports['browser'] for name in nameserver_upgrade['reports']))
    contract['native_commands']=[command for command in contract['native_commands'] if command not in nameserver_commands]
    # The nameserver family is newer than every catalog predecessor: serve the
    # nameserver-free contract, report, workflow and source bytes reconstructed
    # through the verified stage delta to the deeper chain.
    del contract['registered_nameserver_changes_installed']
    del contract['registered_nameserver_changes']
    del contract['registered_nameservers']
    for pin in nameserver_contract.get('repins', []):
        cursor = contract
        for key in pin['keys'][:-1]:
            cursor = cursor[key]
        check('Nameserver strip retains pinned value ' + pin['keys'][-1], cursor[pin['keys'][-1]] == pin['after'])
        cursor[pin['keys'][-1]] = pin['before']
    nameserver_reports_object=a.load(ROOT/'tools/acceptance-reports.json')
    nameserver_reports_removed={'browser':set(nameserver_request_extension['reports']['browser'])|set(nameserver_installed_extension['reports']['browser'])|set(write_contract['reports'])|set(nameserver_contract['reports']),'final':set(nameserver_request_extension['reports']['final'])|set(nameserver_installed_extension['reports']['final'])|set(write_contract['reports'])|set(nameserver_contract['reports'])|set(nameserver_upgrade['reports'])}
    nameserver_reports_object={'browser':[name for name in nameserver_reports_object['browser'] if name not in nameserver_reports_removed['browser']],'final':[name for name in nameserver_reports_object['final'] if name not in nameserver_reports_removed['final']],'provenance':{key:value for key,value in nameserver_reports_object['provenance'].items() if 'nameserver' not in key}}
    nameserver_reports_object['provenance']['additions']=[entry for entry in nameserver_reports_object['provenance']['additions'] if 'nameserver' not in entry]
    nameserver_stage_sources={}
    nameserver_served_contract=json.dumps(contract,indent=2).encode()+b'\n'
    check('Nameserver-free contract serves the verified stage object',hashlib.sha256(nameserver_served_contract).hexdigest()==nameserver_contract['merged_stage_contract_sha256'])
    nameserver_stage_sources[str((ROOT/'tools/acceptance-contract.json').resolve())]=nameserver_served_contract
    nameserver_served_reports=json.dumps(nameserver_reports_object,indent=2).encode()+b'\n'
    check('Nameserver-free reports serve the verified stage object',hashlib.sha256(nameserver_served_reports).hexdigest()==nameserver_contract['merged_stage_reports_sha256'])
    nameserver_stage_sources[str((ROOT/'tools/acceptance-reports.json').resolve())]=nameserver_served_reports
    for path, delta in nameserver_contract['merged_stage_delta'].items():
        if path == 'tools/acceptance-reports.json':
            continue
        raw = Path.read_bytes(ROOT.joinpath(path)).replace(b'\r\n', b'\n')
        if hashlib.sha256(raw).hexdigest() == delta['after_sha256']:
            raw = installed_billing_options_inverse(raw, delta)
        check('Nameserver stage source verified ' + path, hashlib.sha256(raw).hexdigest() == delta['before_sha256'])
        nameserver_stage_sources[str((ROOT/path).resolve())] = raw
    nameserver_stage_read_bytes=Path.read_bytes
    nameserver_stage_read_text=Path.read_text
    def nameserver_stage_read_file_bytes(path):
        nameserver_served=nameserver_stage_sources.get(str(Path(path).resolve()))
        return nameserver_served if nameserver_served is not None else nameserver_stage_read_bytes(path)
    def nameserver_stage_read_file_text(path,*args,**kwargs):
        nameserver_served=nameserver_stage_sources.get(str(Path(path).resolve()))
        if nameserver_served is not None:
            return nameserver_served.decode(kwargs.get('encoding') or (args[0] if args else 'utf-8'))
        return nameserver_stage_read_text(path,*args,**kwargs)
    Path.read_bytes=nameserver_stage_read_file_bytes
    Path.read_text=nameserver_stage_read_file_text
    contract,inbox_reports=purchase_inbox_checks(a,ROOT,tmp,check,deny,named,contract)
    contract,plan_workflow,plan_reports=imported_plan_setup_checks(a,ROOT,tmp,check,deny,named,contract,inbox_reports)
    catalog_storefront_http_checks(contract)
    catalog_storefront_source_checks(contract)
    driver_paths = {'tests/prepare_legacy.py', 'tests/apache_acceptance.py', 'tests/structure_acceptance.py'}
    check('Published1.25 driver inverse is limited to three explicit original source paths', set(contract['release126_baseline_driver_delta']) == driver_paths)
    for driver in sorted(driver_paths):
        data = ROOT.joinpath(driver).read_bytes().replace(b'\r\n', b'\n')
        check('Published1.25 driver extension reverses exactly ' + driver,
              hashlib.sha256(baseline_driver_predecessor(driver, data, contract)).hexdigest() == contract['unchanged_test_sha256'][driver])
        deny('Changed driver bytes cannot bypass baseline inverse ' + driver,
             lambda p=driver,d=data: baseline_driver_predecessor(p, d + b'\n# changed', contract))
        wrong = copy.deepcopy(contract); delta = wrong['release126_baseline_driver_delta'][driver]
        if delta['append']: delta['append'] += '# missing exact suffix'
        else: delta['replacements'][0]['after'] += '# missing exact replacement'
        deny('Changed baseline inverse description is refused ' + driver,
             lambda p=driver,d=data,c=wrong: baseline_driver_predecessor(p, d, c))
    for path,expected_hash in contract['unchanged_test_sha256'].items():
        original_bytes=ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')
        if path in driver_paths: original_bytes=baseline_driver_predecessor(path, original_bytes, contract)
        if path=='tests/browser_acceptance.py':
            added=contract['provider_package_types']['browser_addition'].encode()
            check('One hosting catalog browser callback is additive',original_bytes.count(added)==1)
            original_bytes=original_bytes.replace(added,b'',1)
            nameserver_addition=nameserver_contract['browser_addition'].encode()
            check('Nameserver browser callback occurs once',original_bytes.count(nameserver_addition)==1)
            original_bytes=original_bytes.replace(nameserver_addition,b'',1)
        check('Original test source retained '+path, hashlib.sha256(original_bytes).hexdigest()==expected_hash)
    workflow=plan_workflow
    workflow,checkout_reports=hosting_checkout_wiring_checks(tmp,workflow,contract,plan_reports)
    workflow,upgrade_reports=hosting_upgrade_wiring_checks(tmp,workflow,contract,checkout_reports)
    native=workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    native_commands=[line.strip() for line in native.splitlines()]
    renewal_commands=contract['domain_renewal_invoicing']['additions']
    native_commands=[command for command in native_commands if command not in renewal_commands]
    contract['native_commands']=[command for command in contract['native_commands'] if command not in renewal_commands]
    workflow,native_commands,hosting_reports=hosting_purchase_wiring_checks(tmp,workflow,native_commands,contract,upgrade_reports)
    contract['native_commands']=list(native_commands)
    native_commands=hosting_wiring_checks(tmp, workflow, native_commands, contract, hosting_reports)
    contract['native_commands']=list(native_commands)
    ext=contract.get('non20i_native_command_extension')
    ext_commands=ext['additions'] if ext else []
    package_contract=contract['provider_package_types'];package_commands=package_contract['additions']
    check('Direct hosting catalog retains the complete merged recipe',len(native_commands)==155+len(ext_commands) and native_commands==contract['native_commands'])
    package_position=native_commands.index(package_contract['additions_after'])+1
    check('Three hosting catalog commands occur once after legacy import coverage',len(package_commands)==len(set(package_commands))==3 and native_commands[package_position:package_position+3]==package_commands and all(native_commands.count(command)==1 for command in package_commands))
    native_commands=[command for command in native_commands if command not in package_commands]
    if ext:
        check('Each non-20i addition occurs once directly after its predecessor',all(native_commands.count(command)==1 and native_commands.index(ext['after'][command])+1==native_commands.index(command) for command in ext_commands))
        native_commands=[command for command in native_commands if command not in ext_commands]
        for path,expected_hash in ext.get('source_sha256',{}).items():
            check('Non-20i fixture source retained '+path,hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
        contract['native_commands']=[command for command in contract['native_commands'] if command not in ext_commands]
    check('Direct catalog additions preserve exact152 predecessor commands',len(native_commands)==package_contract['predecessor_native_count']==152 and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==package_contract['predecessor_native_sha256'])
    for path,expected_hash in package_contract['source_sha256'].items():
        check('Hosting catalog fixture source retained '+path,hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
    contract['native_commands']=[command for command in contract['native_commands'] if command not in package_commands]
    import_contract=contract['provider_catalog_import'];import_commands=import_contract['additions']
    check('Import additions retain the complete selected-runtime recipe',len(native_commands)==152 and native_commands==[command for command in contract['native_commands'] if command not in renewal_commands])
    import_position=native_commands.index(import_contract['additions_after'])+1
    check('Three import commands occur once after the existing catalog native fixture',len(import_commands)==len(set(import_commands))==3 and native_commands[import_position:import_position+3]==import_commands and all(native_commands.count(command)==1 for command in import_commands))
    native_commands=[command for command in native_commands if command not in import_commands]
    check('Import additions preserve the exact149 predecessor commands',len(native_commands)==import_contract['predecessor_native_count']==149 and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==import_contract['predecessor_native_sha256'])
    for path,expected_hash in import_contract['source_sha256'].items():
        check('Import fixture source retained '+path,hashlib.sha256(ROOT.joinpath(path).read_bytes().replace(b'\r\n',b'\n')).hexdigest()==expected_hash)
    access_snapshot=contract['stackcp_access_snapshot_native_command_baseline'];access_snapshot_command='"$PHP_BINARY" tests/stackcp_access_read_snapshot_native.php'
    check('All149 native commands equal the exact current selected-runtime recipe',len(native_commands)==149 and native_commands==[command for command in contract['native_commands'] if command not in import_commands and command not in renewal_commands])
    access_snapshot_position=native_commands.index(access_snapshot['additions_after'])+1
    check('Access snapshot comparison occurs exactly once after prerequisite snapshot',access_snapshot['additions']==[access_snapshot_command] and native_commands[access_snapshot_position:access_snapshot_position+1]==[access_snapshot_command] and native_commands.count(access_snapshot_command)==1)
    native_commands=[command for command in native_commands if command!=access_snapshot_command]
    check('Access snapshot addition retains exact148 source-bound commands',len(native_commands)==access_snapshot['count']==148 and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==access_snapshot['sha256'])
    check('All148 native commands retain exact selected-runtime order and arguments', native_commands==[command for command in contract['native_commands'] if command!=access_snapshot_command and command not in import_commands and command not in renewal_commands])
    predecessor=contract['release_environment_predecessor']
    native_commands=['php8.3 '+command[len('"$PHP_BINARY" '):] if command.startswith('"$PHP_BINARY" ') else command for command in native_commands]
    check('Only explicit executable selection changes all148 predecessor commands',len(native_commands)==predecessor['count']==148 and native_commands==predecessor['commands'] and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==predecessor['sha256'])
    snapshot=contract['stackcp_snapshot_native_command_baseline'];snapshot_additions=snapshot['additions']
    snapshot_position=native_commands.index(snapshot['additions_after'])+1
    check('Snapshot native comparison is inserted exactly once after StackCP access',
          snapshot_additions==['php8.3 tests/stackcp_read_snapshot_native.php']
          and native_commands[snapshot_position:snapshot_position+1]==snapshot_additions
          and native_commands.count(snapshot_additions[0])==1)
    native_commands=[command for command in native_commands if command not in snapshot_additions]
    check('Snapshot addition retains all147 predecessor native commands exactly',
          len(native_commands)==snapshot['count']==147
          and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==snapshot['sha256'])
    cloud=contract['cloud_assignment_native_command_baseline'];cloud_additions=cloud['additions']
    cloud_preserved=[command for command in native_commands if command not in cloud_additions]
    check('All139 pre-cloud-assignment native commands retain exact order and arguments',
          len(cloud_preserved)==cloud['count']==139 and hashlib.sha256(json.dumps(cloud_preserved,separators=(',',':')).encode()).hexdigest()==cloud['sha256'])
    cloud_position=native_commands.index(cloud['additions_after'])+1
    check('Eight cloud assignment commands occur once immediately after cloud confirmation',
          len(cloud_additions)==len(set(cloud_additions))==8 and native_commands[cloud_position:cloud_position+8]==cloud_additions
          and all(native_commands.count(command)==1 for command in cloud_additions))
    access=contract['stackcp_access_command_baseline'];access_additions=access['additions']
    access_preserved=[command for command in cloud_preserved if command not in access_additions]
    check('All131 pre-StackCP-access commands retain exact order and arguments',
          len(access_preserved)==access['count']==131 and hashlib.sha256(json.dumps(access_preserved,separators=(',',':')).encode()).hexdigest()==access['sha256'])
    access_position=native_commands.index(access['additions_after'])+1
    check('Eight StackCP access commands occur once after prerequisite native checks',
          len(access_additions)==len(set(access_additions))==8 and native_commands[access_position:access_position+8]==access_additions
          and all(native_commands.count(command)==1 for command in access_additions))
    registration=contract['registration_draft_command_baseline'];registration_additions=registration['additions']
    registration_preserved=[command for command in access_preserved if command not in registration_additions]
    check('All126 pre-registration-draft commands retain exact order and arguments',
          len(registration_preserved)==registration['count']==126 and hashlib.sha256(json.dumps(registration_preserved,separators=(',',':')).encode()).hexdigest()==registration['sha256'])
    registration_position=native_commands.index(registration['additions_after'])+1
    check('Five registration contract draft pure and native commands occur once after renewal dispatch',
          len(registration_additions)==len(set(registration_additions))==5 and native_commands[registration_position:registration_position+5]==registration_additions
          and all(native_commands.count(command)==1 for command in registration_additions))
    stackcp=contract['stackcp_native_command_baseline'];stackcp_additions=stackcp['additions']
    stackcp_preserved=[command for command in registration_preserved if command not in stackcp_additions]
    check('All119 pre-StackCP native commands retain exact order and arguments',
          len(stackcp_preserved)==stackcp['count']==119 and hashlib.sha256(json.dumps(stackcp_preserved,separators=(',',':')).encode()).hexdigest()==stackcp['sha256'])
    stackcp_position=native_commands.index(stackcp['additions_after'])+1
    check('Seven StackCP reports cover pure handoff prerequisites views and native once',
          len(stackcp_additions)==len(set(stackcp_additions))==7 and native_commands[stackcp_position:stackcp_position+7]==stackcp_additions
          and all(native_commands.count(command)==1 for command in stackcp_additions))
    baseline=contract['native_command_baseline'];additions=baseline['additions']
    directory_baseline=contract['directory_control_command_baseline'];directory_additions=directory_baseline['additions']
    preserved=[command for command in stackcp_preserved if command not in additions and command not in directory_additions]
    check('All105 predecessor native commands retain exact order and arguments',
          len(preserved)==baseline['count']==105 and hashlib.sha256(json.dumps(preserved,separators=(',',':')).encode()).hexdigest()==baseline['sha256'])
    position=native_commands.index(baseline['additions_after'])+1
    check('Six renewal native commands occur once immediately after agreement checks',
          len(additions)==len(set(additions))==6 and native_commands[position:position+6]==additions
          and all(native_commands.count(command)==1 for command in additions))
    directory_position=native_commands.index(directory_baseline['additions_after'])+1
    check('Eight directory control commands occur once immediately after Force SSL controls',
          len(directory_additions)==len(set(directory_additions))==8 and native_commands[directory_position:directory_position+8]==directory_additions
          and all(native_commands.count(command)==1 for command in directory_additions))
    check('Public fixture preparation runs only once in the build job', workflow.count('python3 tests/prepare_legacy.py')==1)
    check('Only one exact source package build remains', workflow.count('python3 tools/build.py --commit')==1)
    check('Assigned ports use permitted step context and GITHUB_ENV in both DB jobs', valid_port_context(workflow))
    for job in ('native-and-browser','apache'):
        start=workflow.index('  '+job+':\n');pos=workflow.index('      CWP_TEST_HOST: 127.0.0.1\n',start)+len('      CWP_TEST_HOST: 127.0.0.1\n')
        original_bug=workflow[:pos]+'      CWP_TEST_PORT: ${{ job.services.mariadb.ports[3306] }}\n'+workflow[pos:]
        check('Original invalid job-level service context is rejected for '+job, not valid_port_context(original_bug))


_run_before_retail_variants = run


def retail_variants_inverse(data, delta):
    if hashlib.sha256(data).hexdigest() != delta['after_sha256']:
        raise ValueError('Retail variants current source changed')
    lines = data.decode().splitlines(keepends=True)
    for change in reversed(delta['changes']):
        start, end = change['after_start'], change['after_end']
        if ''.join(lines[start:end]) != change['after']:
            raise ValueError('Retail variants inverse range changed')
        lines[start:end] = change['before'].splitlines(keepends=True)
    result = ''.join(lines).encode()
    if hashlib.sha256(result).hexdigest() != delta['before_sha256']:
        raise ValueError('Retail variants predecessor changed')
    return result


def retail_variants_current(current=None, registry=None):
    if current is None:
        current = a.load(ROOT/'tools/acceptance-contract.json')
    if registry is None:
        registry = a.load(ROOT/'tools/acceptance-reports.json')
    section = current['catalog_retail_variants']
    for path, digest in section['source_sha256'].items():
        if hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() != digest:
            raise ValueError('Retail variants current source changed: '+path)
    prior = copy.deepcopy(current)
    del prior['catalog_retail_variants']
    additions = section['native_additions']
    position = prior['native_commands'].index(section['native_after'])+1
    if prior['native_commands'][position:position+len(additions)] != additions or any(prior['native_commands'].count(c) != 1 for c in additions):
        raise ValueError('Retail variants native recipe changed')
    del prior['native_commands'][position:position+len(additions)]
    for pin in section['repins']:
        cursor = prior
        for key in pin['keys'][:-1]:
            cursor = cursor[key]
        key = pin['keys'][-1]
        if cursor[key] != pin['after']:
            raise ValueError('Retail variants current serialized pin changed')
        cursor[key] = pin['before']
    dump = lambda value: json.dumps(value, indent=2).encode()+b'\n'
    if hashlib.sha256(dump(prior)).hexdigest() != section['predecessor_contract_sha256']:
        raise ValueError('Retail variants predecessor contract changed')
    old_reports = copy.deepcopy(registry)
    for phase in ('browser', 'final'):
        if any(old_reports[phase].count(name) != 1 for name in section['reports']):
            raise ValueError('Retail variants report missing or duplicated')
        old_reports[phase] = [name for name in old_reports[phase] if name not in section['reports']]
    if hashlib.sha256(dump(old_reports)).hexdigest() != section['predecessor_reports_sha256']:
        raise ValueError('Retail variants predecessor reports changed')
    historical = {'tools/acceptance-contract.json': dump(prior), 'tools/acceptance-reports.json': dump(old_reports)}
    for path, delta in section['source_deltas'].items():
        historical[path] = retail_variants_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n'), delta)
    return current, registry, section, historical


def retail_variants_checks(tmp, current, registry, section, historical):
    prior = json.loads(historical['tools/acceptance-contract.json'])
    check('Retail variants retain all210 native commands and add five exact producers',
          len(current['native_commands']) == 215 and len(section['native_additions']) == 5
          and [c for c in current['native_commands'] if c not in section['native_additions']] == prior['native_commands'])
    check('Retail variants require six unique report producers and no new captures',
          len(section['reports']) == len(set(section['reports'])) == 6 and section['screenshots'] == [])
    workflow = (ROOT/'.github/workflows/application-tests.yml').read_text()
    previous = historical['.github/workflows/application-tests.yml'].decode()
    inserted = ''.join('          '+command+'\n' for command in section['native_additions'])
    browser = '          '+section['browser_command']+'\n'
    check('Retail variants workflow changes only the five native recipes and local browser command',
          workflow.count(inserted) == 1 and workflow.count(browser) == 1
          and workflow.replace(inserted, '', 1).replace(browser, '', 1) == previous)
    check('Retail variant browser uses selected PHP after existing Python browser setup',
          section['browser_command'] == '.ci-venv/bin/python -B tests/hosting_catalog_variants_browser.py --php "$PHP_BINARY" --report qa/hosting-catalog-variants-browser-results.json'
          and workflow.index('.ci-venv/bin/pip install') < workflow.index(section['browser_command']))
    check('Retail variant native preserves explicit Linux isolated opt-in and mapped port selection',
          section['native_additions'][-1] == 'CWP_TEST_HOSTING_VARIANTS=1 "$PHP_BINARY" tests/hosting_catalog_variants_native.php' and valid_port_context(workflow))
    for path, digest in section['source_sha256'].items():
        check('Retail variants bind current source '+path,
              hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest)
    for path, delta in section['source_deltas'].items():
        check('Retail variants preserve complete parent source '+path,
              hashlib.sha256(historical[path]).hexdigest() == delta['before_sha256'])
        deny('Retail variants refuse modified current source '+path,
             lambda path=path, delta=delta: retail_variants_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n')+b'\n', delta))
    wrong = copy.deepcopy(current)
    wrong['native_commands'].pop(0)
    deny('Retail variants refuse a removed original command', lambda: retail_variants_current(wrong, registry))
    wrong = copy.deepcopy(current)
    wrong['native_commands'][0] += ' changed'
    deny('Retail variants refuse an altered original command', lambda: retail_variants_current(wrong, registry))
    wrong = copy.deepcopy(current)
    first = next(iter(section['source_deltas']))
    wrong['catalog_retail_variants']['source_deltas'][first]['before_sha256'] = '0'*64
    deny('Retail variants refuse a substituted predecessor source', lambda: retail_variants_current(wrong, registry))
    wrong = copy.deepcopy(registry)
    wrong['final'].remove(next(n for n in wrong['final'] if n not in section['reports']))
    deny('Retail variants refuse removing an original report', lambda: retail_variants_current(current, wrong))
    for pin in section['repins']:
        wrong = copy.deepcopy(current)
        cursor = wrong
        for key in pin['keys'][:-1]:
            cursor = cursor[key]
        cursor[pin['keys'][-1]] = pin['before']
        try:
            retail_variants_current(wrong, registry)
        except ValueError:
            pass
        else:
            raise AssertionError('Stale retail variant source pin passed')
    check('Retail variants refuse every stale serialized current pin', True)
    for phase, expected in [('browser', 234), ('final', 291)]:
        check('Retail variants preserve exact original '+phase+' reports',
              len(registry[phase]) == len(set(registry[phase])) == expected
              and [n for n in registry[phase] if n not in section['reports']] == json.loads(historical['tools/acceptance-reports.json'])[phase])
    root = tmp/'retail-variants-artifacts'
    source = root/'source'
    ctx = context()
    fixtures(source, ctx)
    incoming = root/'partitions'
    bindings = {}
    jobs = {}
    input_sha = '7'*64
    for index, lane in enumerate(a.LANES):
        job = root/('job-'+lane)
        job.mkdir(parents=True)
        jobs[lane] = job
        if lane == 'build':
            shutil.copytree(source/'dist', job/'dist')
            shutil.copytree(source/'qa', job/'qa')
        elif lane == 'native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA:
                    named(job/'qa'/name)
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/'+name, lane):
                    named(job/'qa'/name)
        digest = a.seal(job, ctx, incoming/lane, lane, outcomes(lane), input_sha)
        bindings[lane] = {'result':'success','artifact_id':str(index+81),'sha256':digest,**({'input_sha256':input_sha} if lane == 'build' else {}),**({'checkpoint_id':'89','checkpoint_digest':'8'*64} if lane == 'native-browser' else {})}
    seq = 0
    def combine():
        nonlocal seq
        seq += 1
        return a.aggregate(root/('combined-'+str(seq)), ctx, incoming, bindings, registry)
    def reseal():
        job = jobs['native-browser']
        for path in (job/'.acceptance/checkpoint', incoming/'native-browser'):
            if path.exists():
                assert path.resolve().is_relative_to(root.resolve())
                shutil.rmtree(path)
        bindings['native-browser']['sha256'] = a.seal(job, ctx, incoming/'native-browser', 'native-browser', outcomes('native-browser'), input_sha)
    result = combine()
    check('Retail variants aggregate requires all290 current reports', result['complete'] is True and set(result['reports']) == set(registry['final']))
    for name in section['reports']:
        path = jobs['native-browser']/'qa'/name
        path.unlink()
        reseal()
        result = combine()
        check('Retail variants missing report refuses '+name, result['complete'] is False and 'Missing reports: '+name in result['errors'])
        named(path, False)
        reseal()
        result = combine()
        check('Retail variants failed report refuses '+name, result['complete'] is False and result['reports'][name]['passed'] is False)
        named(path)
        check('Retail variants report stays in native-browser '+name,
              a.evidence_allowed('qa/'+name, 'native-browser') and not any(a.evidence_allowed('qa/'+name, lane) for lane in ('build', 'apache', 'recovery')))
    named(jobs['native-browser']/'qa/retail-variants-unexpected-results.json')
    reseal()
    result = combine()
    check('Retail variants unexpected report refuses', result['complete'] is False and 'Unexpected reports: retail-variants-unexpected-results.json' in result['errors'])
    for path in ('test-runtime/hosting-catalog-variants-private/provider-master.key', 'qa/hosting-catalog-variants-private/provider-master.key'):
        deny('Retail variant private fixture file refuses '+path,
             lambda path=path: a.evidence_allowed(path, 'native-browser'))


def run(tmp):
    current, registry, section, historical = retail_variants_current()
    originals = {str((ROOT/path).resolve()):data for path, data in historical.items()}
    read_bytes, read_text = Path.read_bytes, Path.read_text
    def previous_bytes(path):
        key = str(path.resolve())
        return originals[key] if key in originals else read_bytes(path)
    def previous_text(path, *args, **kwargs):
        key = str(path.resolve())
        return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path, *args, **kwargs)
    with patch.object(Path, 'read_bytes', previous_bytes), patch.object(Path, 'read_text', previous_text):
        _run_before_retail_variants(tmp)
    retail_variants_checks(tmp, current, registry, section, historical)


_run_before_billing_selector = run


def billing_selector_inverse(data, delta):
    if hashlib.sha256(data).hexdigest() != delta['after_sha256']:
        raise ValueError('Billing selector current source changed')
    lines = data.decode().splitlines(keepends=True)
    for change in reversed(delta['changes']):
        start, end = change['after_start'], change['after_end']
        if ''.join(lines[start:end]) != change['after']:
            raise ValueError('Billing selector inverse range changed')
        lines[start:end] = change['before'].splitlines(keepends=True)
    result = ''.join(lines).encode()
    if hashlib.sha256(result).hexdigest() != delta['before_sha256']:
        raise ValueError('Billing selector predecessor changed')
    return result


def billing_selector_current(current=None, registry=None):
    if current is None:
        current = a.load(ROOT/'tools/acceptance-contract.json')
    if registry is None:
        registry = a.load(ROOT/'tools/acceptance-reports.json')
    section = current['hosting_billing_selector']
    for path, digest in section['source_sha256'].items():
        if hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() != digest:
            raise ValueError('Billing selector current source changed: '+path)
    prior = copy.deepcopy(current)
    del prior['hosting_billing_selector']
    additions = section['native_additions']
    position = prior['native_commands'].index(section['native_after'])+1
    if prior['native_commands'][position:position+len(additions)] != additions or any(prior['native_commands'].count(c) != 1 for c in additions):
        raise ValueError('Billing selector native recipe changed')
    del prior['native_commands'][position:position+len(additions)]
    for pin in section['repins']:
        cursor = prior
        for key in pin['keys'][:-1]:
            cursor = cursor[key]
        key = pin['keys'][-1]
        if cursor[key] != pin['after']:
            raise ValueError('Billing selector current serialized pin changed')
        cursor[key] = pin['before']
    dump = lambda value: json.dumps(value, indent=2).encode()+b'\n'
    if hashlib.sha256(dump(prior)).hexdigest() != section['predecessor_contract_sha256']:
        raise ValueError('Billing selector predecessor contract changed')
    old_reports = copy.deepcopy(registry)
    for phase in ('browser', 'final'):
        if any(old_reports[phase].count(name) != 1 for name in section['reports']):
            raise ValueError('Billing selector report missing or duplicated')
        old_reports[phase] = [name for name in old_reports[phase] if name not in section['reports']]
    if hashlib.sha256(dump(old_reports)).hexdigest() != section['predecessor_reports_sha256']:
        raise ValueError('Billing selector predecessor reports changed')
    historical = {'tools/acceptance-contract.json': dump(prior), 'tools/acceptance-reports.json': dump(old_reports)}
    for path, delta in section['source_deltas'].items():
        historical[path] = billing_selector_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n'), delta)
    return current, registry, section, historical


def billing_selector_checks(tmp, current, registry, section, historical):
    prior = json.loads(historical['tools/acceptance-contract.json'])
    check('Billing selector retains all215 native commands and adds three exact producers',
          len(current['native_commands']) == 218 and len(section['native_additions']) == 3
          and [c for c in current['native_commands'] if c not in section['native_additions']] == prior['native_commands'])
    check('Billing selector requires four unique report producers and no new captures',
          len(section['reports']) == len(set(section['reports'])) == 4 and section['screenshots'] == [])
    workflow = (ROOT/'.github/workflows/application-tests.yml').read_text()
    previous = historical['.github/workflows/application-tests.yml'].decode()
    inserted = ''.join('          '+command+'\n' for command in section['native_additions'])
    browser = '          '+section['browser_command']+'\n'
    check('Billing selector workflow changes only the three native recipes and local browser command',
          workflow.count(inserted) == 1 and workflow.count(browser) == 1
          and workflow.replace(inserted, '', 1).replace(browser, '', 1) == previous)
    check('Billing selector browser uses selected PHP after existing Python browser setup',
          section['browser_command'] == '.ci-venv/bin/python -B tests/hosting_catalog_billing_browser.py --php "$PHP_BINARY" --report qa/hosting-catalog-billing-browser-results.json'
          and workflow.index('.ci-venv/bin/pip install') < workflow.index(section['browser_command']))
    check('Billing selector native preserves explicit Linux isolated opt-in and mapped port selection',
          section['native_additions'][-1] == 'CWP_TEST_HOSTING_BILLING=1 "$PHP_BINARY" tests/hosting_catalog_billing_native.php' and valid_port_context(workflow))
    for path, digest in section['source_sha256'].items():
        check('Billing selector binds current source '+path,
              hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest)
    for path, delta in section['source_deltas'].items():
        check('Billing selector preserves complete parent source '+path,
              hashlib.sha256(historical[path]).hexdigest() == delta['before_sha256'])
        deny('Billing selector refuse modified current source '+path,
             lambda path=path, delta=delta: billing_selector_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n')+b'\n', delta))
    wrong = copy.deepcopy(current)
    wrong['native_commands'].pop(0)
    deny('Billing selector refuse a removed original command', lambda: billing_selector_current(wrong, registry))
    wrong = copy.deepcopy(current)
    wrong['native_commands'][0] += ' changed'
    deny('Billing selector refuse an altered original command', lambda: billing_selector_current(wrong, registry))
    wrong = copy.deepcopy(current)
    first = next(iter(section['source_deltas']))
    wrong['hosting_billing_selector']['source_deltas'][first]['before_sha256'] = '0'*64
    deny('Billing selector refuse a substituted predecessor source', lambda: billing_selector_current(wrong, registry))
    wrong = copy.deepcopy(registry)
    wrong['final'].remove(next(n for n in wrong['final'] if n not in section['reports']))
    deny('Billing selector refuse removing an original report', lambda: billing_selector_current(current, wrong))
    for pin in section['repins']:
        wrong = copy.deepcopy(current)
        cursor = wrong
        for key in pin['keys'][:-1]:
            cursor = cursor[key]
        cursor[pin['keys'][-1]] = pin['before']
        try:
            billing_selector_current(wrong, registry)
        except ValueError:
            pass
        else:
            raise AssertionError('Stale billing selector source pin passed')
    check('Billing selector refuse every stale serialized current pin', True)
    for phase, expected in [('browser', 238), ('final', 295)]:
        check('Billing selector preserves exact original '+phase+' reports',
              len(registry[phase]) == len(set(registry[phase])) == expected
              and [n for n in registry[phase] if n not in section['reports']] == json.loads(historical['tools/acceptance-reports.json'])[phase])
    root = tmp/'billing-selector-artifacts'
    source = root/'source'
    ctx = context()
    fixtures(source, ctx)
    incoming = root/'partitions'
    bindings = {}
    jobs = {}
    input_sha = '7'*64
    for index, lane in enumerate(a.LANES):
        job = root/('job-'+lane)
        job.mkdir(parents=True)
        jobs[lane] = job
        if lane == 'build':
            shutil.copytree(source/'dist', job/'dist')
            shutil.copytree(source/'qa', job/'qa')
        elif lane == 'native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA:
                    named(job/'qa'/name)
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/'+name, lane):
                    named(job/'qa'/name)
        digest = a.seal(job, ctx, incoming/lane, lane, outcomes(lane), input_sha)
        bindings[lane] = {'result':'success','artifact_id':str(index+81),'sha256':digest,**({'input_sha256':input_sha} if lane == 'build' else {}),**({'checkpoint_id':'89','checkpoint_digest':'8'*64} if lane == 'native-browser' else {})}
    seq = 0
    def combine():
        nonlocal seq
        seq += 1
        return a.aggregate(root/('combined-'+str(seq)), ctx, incoming, bindings, registry)
    def reseal():
        job = jobs['native-browser']
        for path in (job/'.acceptance/checkpoint', incoming/'native-browser'):
            if path.exists():
                assert path.resolve().is_relative_to(root.resolve())
                shutil.rmtree(path)
        bindings['native-browser']['sha256'] = a.seal(job, ctx, incoming/'native-browser', 'native-browser', outcomes('native-browser'), input_sha)
    result = combine()
    check('Billing selector aggregate requires all294 current reports', result['complete'] is True and set(result['reports']) == set(registry['final']))
    for name in section['reports']:
        path = jobs['native-browser']/'qa'/name
        path.unlink()
        reseal()
        result = combine()
        check('Billing selector missing report refuses '+name, result['complete'] is False and 'Missing reports: '+name in result['errors'])
        named(path, False)
        reseal()
        result = combine()
        check('Billing selector failed report refuses '+name, result['complete'] is False and result['reports'][name]['passed'] is False)
        named(path)
        check('Billing selector report stays in native-browser '+name,
              a.evidence_allowed('qa/'+name, 'native-browser') and not any(a.evidence_allowed('qa/'+name, lane) for lane in ('build', 'apache', 'recovery')))
    named(jobs['native-browser']/'qa/billing-selector-unexpected-results.json')
    reseal()
    result = combine()
    check('Billing selector unexpected report refuses', result['complete'] is False and 'Unexpected reports: billing-selector-unexpected-results.json' in result['errors'])
    for path in ('test-runtime/hosting-catalog-billing-private/provider-master.key', 'qa/hosting-catalog-billing-private/provider-master.key'):
        deny('Billing selector private fixture file refuses '+path,
             lambda path=path: a.evidence_allowed(path, 'native-browser'))


def run(tmp):
    current, registry, section, historical = billing_selector_current()
    originals = {str((ROOT/path).resolve()):data for path, data in historical.items()}
    read_bytes, read_text = Path.read_bytes, Path.read_text
    def previous_bytes(path):
        key = str(path.resolve())
        return originals[key] if key in originals else read_bytes(path)
    def previous_text(path, *args, **kwargs):
        key = str(path.resolve())
        return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path, *args, **kwargs)
    with patch.object(Path, 'read_bytes', previous_bytes), patch.object(Path, 'read_text', previous_text):
        _run_before_billing_selector(tmp)
    billing_selector_checks(tmp, current, registry, section, historical)


_run_before_installed_billing_options = run


def installed_billing_options_inverse(data, delta):
    if hashlib.sha256(data).hexdigest() != delta['after_sha256']:
        raise ValueError('Installed billing options current source changed')
    lines = data.decode().splitlines(keepends=True)
    for change in reversed(delta['changes']):
        start, end = change['after_start'], change['after_end']
        if ''.join(lines[start:end]) != change['after']:
            raise ValueError('Installed billing options inverse range changed')
        lines[start:end] = change['before'].splitlines(keepends=True)
    result = ''.join(lines).encode()
    if hashlib.sha256(result).hexdigest() != delta['before_sha256']:
        raise ValueError('Installed billing options predecessor changed')
    return result


def installed_billing_options_current(current=None, registry=None):
    if current is None:
        current = a.load(ROOT/'tools/acceptance-contract.json')
    if registry is None:
        registry = a.load(ROOT/'tools/acceptance-reports.json')
    section = current['hosting_billing_options']
    for path, digest in section['source_sha256'].items():
        if hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() != digest:
            raise ValueError('Installed billing options current source changed: '+path)
    prior = copy.deepcopy(current)
    del prior['hosting_billing_options']
    additions = section['native_additions']
    position = prior['native_commands'].index(section['native_after'])+1
    if prior['native_commands'][position:position+len(additions)] != additions or any(prior['native_commands'].count(c) != 1 for c in additions):
        raise ValueError('Installed billing options native recipe changed')
    del prior['native_commands'][position:position+len(additions)]
    for pin in section['repins']:
        cursor = prior
        for key in pin['keys'][:-1]:
            cursor = cursor[key]
        key = pin['keys'][-1]
        if cursor[key] != pin['after']:
            raise ValueError('Installed billing options current serialized pin changed')
        cursor[key] = pin['before']
    dump = lambda value: json.dumps(value, indent=2).encode()+b'\n'
    if hashlib.sha256(dump(prior)).hexdigest() != section['predecessor_contract_sha256']:
        raise ValueError('Installed billing options predecessor contract changed')
    old_reports = copy.deepcopy(registry)
    for phase in ('browser', 'final'):
        if any(old_reports[phase].count(name) != 1 for name in section['reports']):
            raise ValueError('Installed billing options report missing or duplicated')
        old_reports[phase] = [name for name in old_reports[phase] if name not in section['reports']]
    if hashlib.sha256(dump(old_reports)).hexdigest() != section['predecessor_reports_sha256']:
        raise ValueError('Installed billing options predecessor reports changed')
    historical = {'tools/acceptance-contract.json': dump(prior), 'tools/acceptance-reports.json': dump(old_reports)}
    for path, delta in section['source_deltas'].items():
        historical[path] = installed_billing_options_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n'), delta)
    return current, registry, section, historical


def installed_billing_options_checks(tmp, current, registry, section, historical):
    prior = json.loads(historical['tools/acceptance-contract.json'])
    check('Installed billing options preserve all218 commands and add one portable producer',
          len(current['native_commands']) == 219 and len(section['native_additions']) == 1
          and [c for c in current['native_commands'] if c not in section['native_additions']] == prior['native_commands'])
    expected_captures = ['Hosting-Billing-Options-'+scene+'-'+size+'.png'
                         for scene in ('Owner-Annual','Public-Monthly','Public-Annual','Client-Annual')
                         for size in ('Desktop','Mobile')]
    check('Installed billing options require three new reports and eight exact unique captures',
          section['reports'] == ['hosting-billing-options-wiring-results.json','mail-flags-native-results.json','mail-replies-native-results.json']
          and section['screenshots'] == expected_captures and len(set(expected_captures)) == 8)
    workflow = (ROOT/'.github/workflows/application-tests.yml').read_text()
    previous = historical['.github/workflows/application-tests.yml'].decode()
    inserted = ''.join('          '+command+'\n' for command in section['native_additions'])
    check('Installed billing options preserve all workflow jobs triggers and previous command order',
          workflow.count(inserted) == 1 and workflow.replace(inserted, '', 1) == previous)
    check('Installed billing options use one stdlib guard and retain the installed checkout producer',
          section['native_additions'] == ['python3 -B tests/hosting_billing_options_wiring.py --report qa/hosting-billing-options-wiring-results.json']
          and workflow.count('.ci-venv/bin/python -B tests/hosting_checkout_http_browser.py --full-suite') == 1
          and valid_port_context(workflow))
    runner = (ROOT/'tests/hosting_checkout_http_browser.py').read_text()
    check('Installed billing options are mandatory in the original packaged result and capture inventory',
          "result['billing_options'] = billing_options_browser(" in runner
          and "billing_options_complete(result['billing_options'], qa)" in runner
          and 'INBOX_CAPTURES + BILLING_OPTIONS_CAPTURES' in runner
          and "'tests/hosting_billing_options_cases.py'" in runner
          and "'tests/hosting_billing_options_wiring.py'" in runner)
    for path, digest in section['source_sha256'].items():
        check('Installed billing options binds current source '+path,
              hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest)
    for path, delta in section['source_deltas'].items():
        check('Installed billing options preserves complete parent source '+path,
              hashlib.sha256(historical[path]).hexdigest() == delta['before_sha256'])
        deny('Installed billing options refuse modified current source '+path,
             lambda path=path, delta=delta: installed_billing_options_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n')+b'\n', delta))
    wrong = copy.deepcopy(current)
    wrong['native_commands'].pop(0)
    deny('Installed billing options refuse a removed original command', lambda: installed_billing_options_current(wrong, registry))
    wrong = copy.deepcopy(current)
    wrong['native_commands'][0] += ' changed'
    deny('Installed billing options refuse an altered original command', lambda: installed_billing_options_current(wrong, registry))
    wrong = copy.deepcopy(current)
    first = next(iter(section['source_deltas']))
    wrong['hosting_billing_options']['source_deltas'][first]['before_sha256'] = '0'*64
    deny('Installed billing options refuse a substituted predecessor source', lambda: installed_billing_options_current(wrong, registry))
    wrong = copy.deepcopy(registry)
    wrong['final'].remove(next(n for n in wrong['final'] if n not in section['reports']))
    deny('Installed billing options refuse removing an original report', lambda: installed_billing_options_current(current, wrong))
    for pin in section['repins']:
        wrong = copy.deepcopy(current)
        cursor = wrong
        for key in pin['keys'][:-1]:
            cursor = cursor[key]
        cursor[pin['keys'][-1]] = pin['before']
        try:
            installed_billing_options_current(wrong, registry)
        except ValueError:
            pass
        else:
            raise AssertionError('Stale installed billing options source pin passed')
    check('Installed billing options refuse every stale serialized current pin', True)
    for phase, expected in [('browser', 241), ('final', 298)]:
        check('Installed billing options preserves exact original '+phase+' reports',
              len(registry[phase]) == len(set(registry[phase])) == expected
              and [n for n in registry[phase] if n not in section['reports']] == json.loads(historical['tools/acceptance-reports.json'])[phase])
    root = tmp/'installed-billing-options-artifacts'
    source = root/'source'
    ctx = context()
    fixtures(source, ctx)
    incoming = root/'partitions'
    bindings = {}
    jobs = {}
    input_sha = '7'*64
    for index, lane in enumerate(a.LANES):
        job = root/('job-'+lane)
        job.mkdir(parents=True)
        jobs[lane] = job
        if lane == 'build':
            shutil.copytree(source/'dist', job/'dist')
            shutil.copytree(source/'qa', job/'qa')
        elif lane == 'native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA:
                    named(job/'qa'/name)
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/'+name, lane):
                    named(job/'qa'/name)
        digest = a.seal(job, ctx, incoming/lane, lane, outcomes(lane), input_sha)
        bindings[lane] = {'result':'success','artifact_id':str(index+81),'sha256':digest,**({'input_sha256':input_sha} if lane == 'build' else {}),**({'checkpoint_id':'89','checkpoint_digest':'8'*64} if lane == 'native-browser' else {})}
    seq = 0
    def combine():
        nonlocal seq
        seq += 1
        return a.aggregate(root/('combined-'+str(seq)), ctx, incoming, bindings, registry)
    def reseal():
        job = jobs['native-browser']
        for path in (job/'.acceptance/checkpoint', incoming/'native-browser'):
            if path.exists():
                assert path.resolve().is_relative_to(root.resolve())
                shutil.rmtree(path)
        bindings['native-browser']['sha256'] = a.seal(job, ctx, incoming/'native-browser', 'native-browser', outcomes('native-browser'), input_sha)
    result = combine()
    check('Installed billing options aggregate requires all297 current reports', result['complete'] is True and set(result['reports']) == set(registry['final']))
    for name in section['reports']:
        path = jobs['native-browser']/'qa'/name
        path.unlink()
        reseal()
        result = combine()
        check('Installed billing options missing report refuses '+name, result['complete'] is False and 'Missing reports: '+name in result['errors'])
        named(path, False)
        reseal()
        result = combine()
        check('Installed billing options failed report refuses '+name, result['complete'] is False and result['reports'][name]['passed'] is False)
        named(path)
        check('Installed billing options report stays in native-browser '+name,
              a.evidence_allowed('qa/'+name, 'native-browser') and not any(a.evidence_allowed('qa/'+name, lane) for lane in ('build', 'apache', 'recovery')))
    named(jobs['native-browser']/'qa/installed-billing-options-unexpected-results.json')
    reseal()
    result = combine()
    check('Installed billing options unexpected report refuses', result['complete'] is False and 'Unexpected reports: installed-billing-options-unexpected-results.json' in result['errors'])
    for path in ('test-runtime/hosting-checkout-private/provider-master.key', 'qa/hosting-checkout-private/provider-master.key'):
        deny('Installed billing options private fixture file refuses '+path,
             lambda path=path: a.evidence_allowed(path, 'native-browser'))


def run(tmp):
    contract=a.load(ROOT/'tools/acceptance-contract.json')
    renewal_contract=contract['domain_renewal_invoicing']
    renewal_commands=renewal_contract['additions']
    workflow=(ROOT/'.github/workflows/application-tests.yml').read_text()
    native=workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    native_commands=[line.strip() for line in native.splitlines()]
    check('Renewal invoice creation adds three mandatory native-lane checks',len(native_commands)==219 and native_commands==contract['native_commands'] and len(renewal_commands)==3 and len(set(renewal_commands))==3 and all(native_commands.count(command)==1 for command in renewal_commands) and native_commands[-3:]==renewal_commands)
    native_commands=[command for command in native_commands if command not in renewal_commands]
    check('Renewal invoice creation preserves every original216 command in order',len(native_commands)==renewal_contract['predecessor_native_count']==216 and hashlib.sha256(json.dumps(native_commands,separators=(',',':')).encode()).hexdigest()==renewal_contract['predecessor_native_sha256'])
    renewal_reports=a.load(ROOT/'tools/acceptance-reports.json')
    check('Renewal invoice creation requires all three new reports in both acceptance lists',all(report in renewal_reports['browser'] and report in renewal_reports['final'] for report in renewal_contract['reports']))
    current, registry, section, historical = installed_billing_options_current()
    originals = {str((ROOT/path).resolve()):data for path, data in historical.items()}
    read_bytes, read_text = Path.read_bytes, Path.read_text
    def previous_bytes(path):
        key = str(path.resolve())
        return originals[key] if key in originals else read_bytes(path)
    def previous_text(path, *args, **kwargs):
        key = str(path.resolve())
        return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path, *args, **kwargs)
    with patch.object(Path, 'read_bytes', previous_bytes), patch.object(Path, 'read_text', previous_text):
        _run_before_installed_billing_options(tmp)
    installed_billing_options_checks(tmp, current, registry, section, historical)


_run_before_client_https_requests = run


def client_https_requests_inverse(data, delta):
    if hashlib.sha256(data).hexdigest() != delta['after_sha256']:
        raise ValueError('Client HTTPS requests current source changed')
    lines = data.decode().splitlines(keepends=True)
    for change in reversed(delta['changes']):
        start, end = change['after_start'], change['after_end']
        if ''.join(lines[start:end]) != change['after']:
            raise ValueError('Client HTTPS requests inverse range changed')
        lines[start:end] = change['before'].splitlines(keepends=True)
    result = ''.join(lines).encode()
    if hashlib.sha256(result).hexdigest() != delta['before_sha256']:
        raise ValueError('Client HTTPS requests predecessor changed')
    return result


def client_https_requests_current():
    current = a.load(ROOT/'tools/acceptance-contract.json'); section = current['client_https_requests']
    for path, digest in section['source_sha256'].items():
        if hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() != digest:
            raise ValueError('Client HTTPS requests source changed: '+path)
    registry = a.load(ROOT/'tools/acceptance-reports.json')
    prior = copy.deepcopy(current); del prior['client_https_requests']
    additions = section['native_additions']; position = prior['native_commands'].index(section['native_after'])+1
    if prior['native_commands'][position:position+len(additions)] != additions or any(prior['native_commands'].count(c) != 1 for c in additions):
        raise ValueError('Client HTTPS requests native recipe changed')
    del prior['native_commands'][position:position+len(additions)]
    for pin in section['repins']:
        cursor = prior
        for key in pin['keys'][:-1]: cursor = cursor[key]
        key = pin['keys'][-1]
        if cursor[key] != pin['after']: raise ValueError('Client HTTPS requests current pin changed')
        cursor[key] = pin['before']
    dump = lambda value: json.dumps(value, indent=2).encode()+b'\n'
    if hashlib.sha256(dump(prior)).hexdigest() != section['predecessor_contract_sha256']:
        raise ValueError('Client HTTPS requests predecessor contract changed')
    old_reports = copy.deepcopy(registry)
    for phase in ('browser', 'final'):
        if any(old_reports[phase].count(name) != 1 for name in section['reports']): raise ValueError('Client HTTPS requests report missing or duplicated')
        old_reports[phase] = [name for name in old_reports[phase] if name not in section['reports']]
    if hashlib.sha256(dump(old_reports)).hexdigest() != section['predecessor_reports_sha256']:
        raise ValueError('Client HTTPS requests predecessor reports changed')
    historical = {'tools/acceptance-contract.json': dump(prior), 'tools/acceptance-reports.json': dump(old_reports)}
    for path, delta in section['source_deltas'].items():
        historical[path] = client_https_requests_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n'), delta)
    return current, registry, section, historical


def client_https_requests_checks(tmp, current, registry, section, historical):
    prior = json.loads(historical['tools/acceptance-contract.json'])
    check('Client HTTPS requests preserves all219 native recipes and adds seven', len(current['native_commands']) == 226 and [c for c in current['native_commands'] if c not in section['native_additions']] == prior['native_commands'])
    check('Client HTTPS requests reports are eight unique producers', len(section['reports']) == len(set(section['reports'])) == 8)
    workflow = (ROOT/'.github/workflows/application-tests.yml').read_text()
    previous = historical['.github/workflows/application-tests.yml'].decode()
    inserted = ''.join('          '+command+'\n' for command in section['native_additions'])
    browser = '          '+section['browser_command']+'\n'
    check('Client HTTPS requests workflow adds only exact native and browser commands', workflow.count(inserted) == 1 and workflow.count(browser) == 1 and workflow.replace(inserted, '', 1).replace(browser, '', 1) == previous)
    check('Client HTTPS requests browser uses selected PHP after Python environment setup', section['browser_command'].startswith('.ci-venv/bin/python ') and '--php "$PHP_BINARY"' in section['browser_command'] and workflow.index('.ci-venv/bin/pip install') < workflow.index(section['browser_command']))
    check('Client HTTPS requests native requires explicit isolated test opt in', section['native_additions'][-1] == 'CWP_TEST_FORCE_SSL_REQUESTS=1 "$PHP_BINARY" tests/provider_force_ssl_requests_native.php' and valid_port_context(workflow))
    check('Client HTTPS requests adds no unreviewed capture expectations', section['screenshots'] == [])
    for path, digest in section['source_sha256'].items():
        check('Client HTTPS requests binds exact source '+path, hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest)
    for path, delta in section['source_deltas'].items():
        check('Client HTTPS requests retains exact predecessor '+path, hashlib.sha256(historical[path]).hexdigest() == delta['before_sha256'])
        deny('Client HTTPS requests modified current source refuses '+path, lambda path=path, delta=delta: client_https_requests_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n')+b'\n', delta))
    for phase, expected in [('browser',249),('final',306)]:
        check('Client HTTPS requests preserves all original '+phase+' reports', len(registry[phase]) == len(set(registry[phase])) == expected and [n for n in registry[phase] if n not in section['reports']] == json.loads(historical['tools/acceptance-reports.json'])[phase])
    root = tmp/'client-https-requests-artifacts'; source = root/'source'; ctx = context(); fixtures(source, ctx)
    incoming = root/'partitions'; bindings = {}; jobs = {}; input_sha = '7'*64
    for index, lane in enumerate(a.LANES):
        job = root/('job-'+lane); job.mkdir(parents=True); jobs[lane] = job
        if lane == 'build': shutil.copytree(source/'dist', job/'dist'); shutil.copytree(source/'qa', job/'qa')
        elif lane == 'native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA: named(job/'qa'/name)
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/'+name,lane): named(job/'qa'/name)
        digest = a.seal(job, ctx, incoming/lane, lane, outcomes(lane), input_sha)
        bindings[lane] = {'result':'success','artifact_id':str(index+81),'sha256':digest,**({'input_sha256':input_sha} if lane == 'build' else {}),**({'checkpoint_id':'89','checkpoint_digest':'8'*64} if lane == 'native-browser' else {})}
    seq = 0
    def combine():
        nonlocal seq
        seq += 1; return a.aggregate(root/('combined-'+str(seq)), ctx, incoming, bindings, registry)
    def reseal():
        job = jobs['native-browser']
        for path in (job/'.acceptance/checkpoint', incoming/'native-browser'):
            if path.exists():
                assert path.resolve().is_relative_to(root.resolve()); shutil.rmtree(path)
        bindings['native-browser']['sha256'] = a.seal(job, ctx, incoming/'native-browser', 'native-browser', outcomes('native-browser'), input_sha)
    result = combine()
    check('Client HTTPS requests aggregate requires all306 reports', result['complete'] is True and set(result['reports']) == set(registry['final']))
    for name in section['reports']:
        path = jobs['native-browser']/'qa'/name; path.unlink(); reseal(); result = combine()
        check('Client HTTPS requests missing report refuses '+name, result['complete'] is False and 'Missing reports: '+name in result['errors'])
        named(path,False); reseal(); result = combine()
        check('Client HTTPS requests failed report refuses '+name, result['complete'] is False and result['reports'][name]['passed'] is False)
        named(path)
        check('Client HTTPS requests report only belongs to native-browser '+name, a.evidence_allowed('qa/'+name,'native-browser') and not any(a.evidence_allowed('qa/'+name,lane) for lane in ('build','apache','recovery')))
    named(jobs['native-browser']/'qa/client-https-requests-unexpected-results.json'); reseal(); result = combine()
    check('Client HTTPS requests unexpected report refuses', result['complete'] is False and 'Unexpected reports: client-https-requests-unexpected-results.json' in result['errors'])
    check('Client HTTPS requests private fixture files remain excluded', not a.evidence_allowed('test-runtime/client-https-requests-private/session.json','native-browser') and not a.evidence_allowed('qa/client-https-requests-private/session.json','native-browser'))


def run(tmp):
    current, registry, section, historical = client_https_requests_current()
    originals = {str((ROOT/path).resolve()):data for path,data in historical.items()}
    read_bytes, read_text = Path.read_bytes, Path.read_text
    def previous_bytes(path):
        key = str(path.resolve()); return originals[key] if key in originals else read_bytes(path)
    def previous_text(path,*args,**kwargs):
        key = str(path.resolve()); return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path,*args,**kwargs)
    with patch.object(Path,'read_bytes',previous_bytes), patch.object(Path,'read_text',previous_text):
        _run_before_client_https_requests(tmp)
    client_https_requests_checks(tmp,current,registry,section,historical)


_run_before_client_directory_requests = run


def client_directory_requests_inverse(data, delta):
    if hashlib.sha256(data).hexdigest() != delta['after_sha256']:
        raise ValueError('Client directory listing requests current source changed')
    lines = data.decode().splitlines(keepends=True)
    for change in reversed(delta['changes']):
        start, end = change['after_start'], change['after_end']
        if ''.join(lines[start:end]) != change['after']:
            raise ValueError('Client directory listing requests inverse range changed')
        lines[start:end] = change['before'].splitlines(keepends=True)
    result = ''.join(lines).encode()
    if hashlib.sha256(result).hexdigest() != delta['before_sha256']:
        raise ValueError('Client directory listing requests predecessor changed')
    return result


def client_directory_requests_current():
    current = a.load(ROOT/'tools/acceptance-contract.json'); section = current['client_directory_requests']
    for path, digest in section['source_sha256'].items():
        if hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() != digest:
            raise ValueError('Client directory listing requests source changed: '+path)
    registry = a.load(ROOT/'tools/acceptance-reports.json')
    prior = copy.deepcopy(current); del prior['client_directory_requests']
    additions = section['native_additions']; position = prior['native_commands'].index(section['native_after'])+1
    if prior['native_commands'][position:position+len(additions)] != additions or any(prior['native_commands'].count(c) != 1 for c in additions):
        raise ValueError('Client directory listing requests native recipe changed')
    del prior['native_commands'][position:position+len(additions)]
    # The cloud-request family is the newest era: the served pre-merge contract
    # drops both cloud sections and their seven anchored native commands.
    cloud_intake_additions = prior['cloud_site_intake']['additions']
    cloud_intake_position = prior['native_commands'].index(prior['cloud_site_intake']['additions_after'])+1
    if prior['native_commands'][cloud_intake_position:cloud_intake_position+len(cloud_intake_additions)] != cloud_intake_additions:
        raise ValueError('Client directory listing requests cloud intake recipe changed')
    del prior['native_commands'][cloud_intake_position:cloud_intake_position+len(cloud_intake_additions)]
    cloud_request_additions = prior['cloud_site_requests']['additions']
    cloud_position = prior['native_commands'].index(prior['cloud_site_requests']['additions_after'])+1
    if prior['native_commands'][cloud_position:cloud_position+len(cloud_request_additions)] != cloud_request_additions:
        raise ValueError('Client directory listing requests cloud request recipe changed')
    del prior['native_commands'][cloud_position:cloud_position+len(cloud_request_additions)]
    del prior['cloud_site_intake']; del prior['cloud_site_requests']
    for pin in section['repins']:
        cursor = prior
        for key in pin['keys'][:-1]: cursor = cursor[key]
        key = pin['keys'][-1]
        if cursor[key] != pin['after']: raise ValueError('Client directory listing requests current pin changed')
        cursor[key] = pin['before']
    dump = lambda value: json.dumps(value, indent=2).encode()+b'\n'
    if hashlib.sha256(dump(prior)).hexdigest() != section['predecessor_contract_sha256']:
        raise ValueError('Client directory listing requests predecessor contract changed')
    cloud_report_names = set(current['cloud_site_requests']['reports'])|set(current['cloud_site_requests']['upgrade']['reports'])|set(current['cloud_site_intake']['reports'])
    old_reports = copy.deepcopy(registry)
    for phase in ('browser', 'final'):
        if any(old_reports[phase].count(name) != 1 for name in section['reports']): raise ValueError('Client directory listing requests report missing or duplicated')
        old_reports[phase] = [name for name in old_reports[phase] if name not in section['reports'] and name not in cloud_report_names]
    if hashlib.sha256(dump(old_reports)).hexdigest() != section['predecessor_reports_sha256']:
        raise ValueError('Client directory listing requests predecessor reports changed')
    historical = {'tools/acceptance-contract.json': dump(prior), 'tools/acceptance-reports.json': dump(old_reports)}
    for path, delta in section['source_deltas'].items():
        historical[path] = client_directory_requests_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n'), delta)
    return current, registry, section, historical


def client_directory_requests_checks(tmp, current, registry, section, historical):
    prior = json.loads(historical['tools/acceptance-contract.json'])
    cloud_additions = current['cloud_site_requests']['additions'] + current['cloud_site_intake']['additions']
    check('Client directory listing requests preserves all226 native recipes and adds seven', len(current['native_commands']) == 240 and [c for c in current['native_commands'] if c not in section['native_additions'] and c not in cloud_additions] == prior['native_commands'])
    check('Client directory listing requests reports are eight unique producers', len(section['reports']) == len(set(section['reports'])) == 8)
    workflow = (ROOT/'.github/workflows/application-tests.yml').read_text()
    previous = historical['.github/workflows/application-tests.yml'].decode()
    inserted = ''.join('          '+command+'\n' for command in section['native_additions'])
    browser = '          '+section['browser_command']+'\n'
    cloud_native = ''.join('          '+command+'\n' for command in cloud_additions)
    cloud_apache = ''.join('          '+command+'\n' for command in current['cloud_site_requests']['upgrade']['commands'])
    reclaim_step = '      - name: Reclaim unused hosted toolchain disk\n        run: |\n          # Hosted runners preinstall ~14GB of unrelated toolchains; the suite\'s\n          # MariaDB plus release fixtures filled the disk (errno=28) on past runs.\n          sudo rm -rf "$AGENT_TOOLSDIRECTORY" /usr/share/dotnet /opt/ghc /usr/local/share/boost /opt/android || true\n          sudo docker image prune --all --force || true\n          df -h /\n'
    check('Client directory listing requests workflow adds only exact native and browser commands', workflow.count(inserted) == 1 and workflow.count(browser) == 1 and workflow.count(reclaim_step) == 4 and workflow.replace(inserted, '', 1).replace(browser, '', 1).replace(cloud_native, '', 1).replace(cloud_apache, '', 1).replace(reclaim_step, '') == previous)
    check('Client directory listing requests browser uses selected PHP after Python environment setup', section['browser_command'].startswith('.ci-venv/bin/python ') and '--php "$PHP_BINARY"' in section['browser_command'] and workflow.index('.ci-venv/bin/pip install') < workflow.index(section['browser_command']))
    check('Client directory listing requests native requires explicit isolated test opt in', section['native_additions'][-1] == 'CWP_TEST_DIRECTORY_REQUESTS=1 "$PHP_BINARY" tests/provider_directory_listing_requests_native.php' and valid_port_context(workflow))
    check('Client directory listing requests adds no unreviewed capture expectations', section['screenshots'] == [])
    for path, digest in section['source_sha256'].items():
        check('Client directory listing requests binds exact source '+path, hashlib.sha256((ROOT/path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest)
    for path, delta in section['source_deltas'].items():
        check('Client directory listing requests retains exact predecessor '+path, hashlib.sha256(historical[path]).hexdigest() == delta['before_sha256'])
        deny('Client directory listing requests modified current source refuses '+path, lambda path=path, delta=delta: client_directory_requests_inverse((ROOT/path).read_bytes().replace(b'\r\n', b'\n')+b'\n', delta))
    cloud_report_names = set(current['cloud_site_requests']['reports'])|set(current['cloud_site_requests']['upgrade']['reports'])|set(current['cloud_site_intake']['reports'])
    for phase, expected in [('browser',264),('final',323)]:
        check('Client directory listing requests preserves all original '+phase+' reports', len(registry[phase]) == len(set(registry[phase])) == expected and [n for n in registry[phase] if n not in section['reports'] and n not in cloud_report_names] == json.loads(historical['tools/acceptance-reports.json'])[phase])
    root = tmp/'client-directory-requests-artifacts'; source = root/'source'; ctx = context(); fixtures(source, ctx)
    incoming = root/'partitions'; bindings = {}; jobs = {}; input_sha = '7'*64
    for index, lane in enumerate(a.LANES):
        job = root/('job-'+lane); job.mkdir(parents=True); jobs[lane] = job
        if lane == 'build': shutil.copytree(source/'dist', job/'dist'); shutil.copytree(source/'qa', job/'qa')
        elif lane == 'native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA: named(job/'qa'/name)
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/'+name,lane): named(job/'qa'/name)
        digest = a.seal(job, ctx, incoming/lane, lane, outcomes(lane), input_sha)
        bindings[lane] = {'result':'success','artifact_id':str(index+81),'sha256':digest,**({'input_sha256':input_sha} if lane == 'build' else {}),**({'checkpoint_id':'89','checkpoint_digest':'8'*64} if lane == 'native-browser' else {})}
    seq = 0
    def combine():
        nonlocal seq
        seq += 1; return a.aggregate(root/('combined-'+str(seq)), ctx, incoming, bindings, registry)
    def reseal():
        job = jobs['native-browser']
        for path in (job/'.acceptance/checkpoint', incoming/'native-browser'):
            if path.exists():
                assert path.resolve().is_relative_to(root.resolve()); shutil.rmtree(path)
        bindings['native-browser']['sha256'] = a.seal(job, ctx, incoming/'native-browser', 'native-browser', outcomes('native-browser'), input_sha)
    result = combine()
    check('Client directory listing requests aggregate requires all314 reports', result['complete'] is True and set(result['reports']) == set(registry['final']))
    for name in section['reports']:
        path = jobs['native-browser']/'qa'/name; path.unlink(); reseal(); result = combine()
        check('Client directory listing requests missing report refuses '+name, result['complete'] is False and 'Missing reports: '+name in result['errors'])
        named(path,False); reseal(); result = combine()
        check('Client directory listing requests failed report refuses '+name, result['complete'] is False and result['reports'][name]['passed'] is False)
        named(path)
        check('Client directory listing requests report only belongs to native-browser '+name, a.evidence_allowed('qa/'+name,'native-browser') and not any(a.evidence_allowed('qa/'+name,lane) for lane in ('build','apache','recovery')))
    named(jobs['native-browser']/'qa/client-directory-requests-unexpected-results.json'); reseal(); result = combine()
    check('Client directory listing requests unexpected report refuses', result['complete'] is False and 'Unexpected reports: client-directory-requests-unexpected-results.json' in result['errors'])
    check('Client directory listing requests private fixture files remain excluded', not a.evidence_allowed('test-runtime/directory-listing-requests-private/session.json','native-browser') and not a.evidence_allowed('qa/directory-listing-requests-private/session.json','native-browser'))


def run(tmp):
    cloud_workflow=(ROOT/'.github/workflows/application-tests.yml').read_text()
    cloud_native=cloud_workflow.split('      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n',1)[1].split('      - name: Real HTTP',1)[0]
    cloud_commands=[line.strip() for line in cloud_native.splitlines()]
    cloud_contract=a.load(ROOT/'tools/acceptance-contract.json')
    from cloud_site_intake_wiring import verify as cloud_intake_wiring
    cloud_commands,cloud_request_reports=cloud_intake_wiring(ROOT,cloud_contract,cloud_workflow,cloud_commands,check)
    cloud_contract={**cloud_contract,'native_commands':cloud_commands}
    from cloud_site_requests_wiring import verify as cloud_request_wiring
    cloud_commands=cloud_request_wiring(ROOT,cloud_contract,cloud_workflow,cloud_commands,check,cloud_request_reports)
    current, registry, section, historical = client_directory_requests_current()
    originals = {str((ROOT/path).resolve()):data for path,data in historical.items()}
    read_bytes, read_text = Path.read_bytes, Path.read_text
    def previous_bytes(path):
        key = str(path.resolve()); return originals[key] if key in originals else read_bytes(path)
    def previous_text(path,*args,**kwargs):
        key = str(path.resolve()); return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path,*args,**kwargs)
    with patch.object(Path,'read_bytes',previous_bytes), patch.object(Path,'read_text',previous_text):
        _run_before_client_directory_requests(tmp)
    client_directory_requests_checks(tmp,current,registry,section,historical)


_run_before_php_controls = run


def run(tmp):
    # The PHP-controls era is newest: its wiring checks run against the actual
    # merged bytes, then the entire deeper chain observes php-peeled contract,
    # report and source files reconstructed through the verified delta.
    contract = a.load(ROOT/'tools/acceptance-contract.json')
    php_predecessor = php_controls_wiring_checks(tmp, contract)
    php_extension = contract['php_controls_acceptance']
    del contract['php_controls_acceptance']
    originals = {str((ROOT/path).resolve()): data for path, data in php_predecessor.items()}
    originals[str((ROOT/'tools/acceptance-contract.json').resolve())] = json.dumps(contract, indent=2).encode() + b'\n'
    originals[str((ROOT/'tools/acceptance-reports.json').resolve())] = json.dumps(
        php_controls_report_predecessor(a.load(ROOT/'tools/acceptance-reports.json'), php_extension), indent=2).encode() + b'\n'
    read_bytes, read_text = Path.read_bytes, Path.read_text
    def previous_bytes(path):
        key = str(path.resolve()); return originals[key] if key in originals else read_bytes(path)
    def previous_text(path, *args, **kwargs):
        key = str(path.resolve()); return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path, *args, **kwargs)
    with patch.object(Path, 'read_bytes', previous_bytes), patch.object(Path, 'read_text', previous_text):
        _run_before_php_controls(tmp)


def main():
    parser=argparse.ArgumentParser();parser.add_argument('--report',type=Path,required=True);args=parser.parse_args()
    error=None
    try:
        # Scoped constants describe this suite's two-file synthetic protocol fixture only.
        # The separate published-baseline guards execute with the unchanged production constants.
        check('Production1.24 baseline constants retain exact immutable values',
              a.PUBLISHED_BASELINE == 'CajunWebPro-1.24.0-COMPLETE-UPLOAD.zip'
              and a.PUBLISHED_BASELINE_SHA256 == 'aa7a84b4c0d1201a4eddf2fe68340b90d4860117ceff4d959d165e6feafa731e' and a.PUBLISHED_BASELINE_MEMBERS == 2591)
        check('Production1.25 baseline constants bind the actual published COMPLETE archive',
              a.PUBLISHED_BASELINE_126 == 'CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
              and a.PUBLISHED_BASELINE_126_SHA256 == 'bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39' and a.PUBLISHED_BASELINE_126_MEMBERS == 2895)
        with patch.object(a, 'PUBLISHED_BASELINE_SHA256', hashlib.sha256(synthetic_baseline()).hexdigest()), patch.object(a, 'PUBLISHED_BASELINE_MEMBERS', 2), patch.object(a, 'PUBLISHED_BASELINE_126_SHA256', hashlib.sha256(synthetic_baseline_126()).hexdigest()), patch.object(a, 'PUBLISHED_BASELINE_126_MEMBERS', 2):
            with tempfile.TemporaryDirectory(prefix='cwp-artifact-tests-') as temp:run(Path(temp))
    except Exception as exc:error=exc
    report={'passed':error is None,'scope':'Offline synthetic temporary files and exact transfer helpers; no application, provider, database or CI execution.', 'checks':checks}
    if error:report['error']=type(error).__name__+': '+str(error)
    a.write(args.report,report)
    print(json.dumps({'passed':report['passed'],'checks':len(checks),'error':report.get('error')}))
    if error:raise error


if __name__=='__main__':main()
