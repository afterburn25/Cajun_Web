"""Additive folder-control acceptance wiring; retain every predecessor check."""
import ast
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return ROOT.joinpath(path).read_text(encoding='utf-8')


def digest(text):
    return hashlib.sha256(text.encode()).hexdigest()


def predecessor(path, contract):
    """Reverse only exact reviewed additions, then verify the original whole file."""
    text = read(path)
    extension = contract['document_root_controls']['source_extensions'].get(path)
    if extension is None:
        return text
    if digest(text) != extension['current_sha256']:
        raise AssertionError('Changed folder-control acceptance source: ' + path)
    for edit in reversed(extension['edits']):
        if text.count(edit['after']) != 1:
            raise AssertionError('Ambiguous folder-control acceptance extension: ' + path)
        text = text.replace(edit['after'], edit['before'], 1)
    if digest(text) != extension['predecessor_sha256']:
        raise AssertionError('Folder-control predecessor changed: ' + path)
    return text


def run(check, contract, workflow, commands):
    c = contract['document_root_controls']
    additions = c['native_additions']
    position = commands.index(c['native_after']) + 1
    check('Folder-control commands are each inserted once after saved-root observations',
          len(additions) == len(set(additions)) == 2
          and commands[position:position + 2] == additions
          and all(commands.count(x) == 1 for x in additions))
    remaining = [x for x in commands if x not in additions]
    check('Folder controls preserve the complete predecessor native recipe in order',
          len(commands) == len(contract['native_commands']) + len(additions)
          and remaining == contract['native_commands'])
    for path, source in c['source_extensions'].items():
        check('Exact folder-control source extension preserves predecessor ' + path,
              digest(predecessor(path, contract)) == source['predecessor_sha256'])
    for path, expected in c['source_sha256'].items():
        check('Reviewed folder-control acceptance source retained ' + path, digest(read(path)) == expected)
    reports = json.loads(read('tools/acceptance-reports.json'))
    upgrade = contract['document_root_controls_upgrade']
    for path, expected in upgrade['source_sha256'].items():
        check('Reviewed installed folder-control source retained ' + path, digest(read(path)) == expected)
    for lane in ('browser', 'final'):
        upgrade_report_additions = upgrade['reports'] if lane == 'final' else []
        actual = reports[lane]
        prior = [x for x in actual if x not in upgrade_report_additions]
        check('Installed folder-control ' + lane + ' reports preserve predecessor requirements',
              len(actual) == len(set(actual)) and all(actual.count(x) == 1 for x in upgrade_report_additions)
              and len(prior) == upgrade['predecessor_reports'][lane]['count']
              and digest(json.dumps(prior, separators=(',', ':'))) == upgrade['predecessor_reports'][lane]['sha256'])
        reports[lane] = prior
    extension = upgrade['apache_additions']
    check('Installed folder-control commands extend the exact Apache predecessor once',
          workflow.count(extension) == 1 and upgrade['apache_after'] + extension in workflow
          and digest(workflow.replace(extension, '', 1)) == upgrade['predecessor_workflow_sha256'])
    old_guard = upgrade['old_baseline_guard']
    guard = read('tests/release_125_upgrade_input_guards.py')
    for edit in reversed(old_guard['edits']):
        if guard.count(edit['after']) != 1:
            raise AssertionError('Ambiguous original baseline guard extension')
        guard = guard.replace(edit['after'], edit['before'], 1)
    check('Original actual1.24 baseline guards preserve exact predecessor source',
          digest(guard) == old_guard['before_sha256'])
    for lane in ('browser', 'final'):
        actual = reports[lane]
        check('Folder-control ' + lane + ' reports are additive and required',
              len(actual) == len(set(actual))
              and all(actual.count(x) == 1 for x in c['reports'])
              and len(actual) == c['predecessor_reports'][lane]['count'] + len(c['reports'])
              and digest(json.dumps([x for x in actual if x not in c['reports']], separators=(',', ':')))
                  == c['predecessor_reports'][lane]['sha256'])
    hook = c['browser_hook']
    browser = read('tests/browser_acceptance.py')
    check('Folder-control browser callback follows the complete saved-map callback',
          browser.count(hook) == 1 and c['browser_after'] + hook in browser)
    tree = ast.parse(read('tests/provider_document_root_controls_cases.py'))
    capture = next(n for n in tree.body if isinstance(n, ast.Assign)
                   and any(isinstance(t, ast.Name) and t.id == 'CAPTURES' for t in n.targets))
    namespace = {}
    exec(compile(ast.Module(body=[capture], type_ignores=[]), '<folder-captures>', 'exec'), namespace)
    check('Folder-control browser requires eight distinct desktop and mobile captures',
          tuple(c['screenshots']) == namespace['CAPTURES'] and len(set(c['screenshots'])) == 8)
    focused = read('.github/workflows/provider-document-root-controls.yml')
    check('Folder-control native acceptance requires explicit isolated-database opt-in',
          'CWP_TEST_DOCUMENT_ROOT_CONTROLS=1 "$PHP_BINARY" tests/provider_document_root_controls_native.php' in additions
          and 'CWP_TEST_DOCUMENT_ROOT_CONTROLS: \'1\'' in focused
          and 'CWP_TEST_DATABASE: cwp_test' in focused)
    check('Folder-control focused acceptance retains all four supported combinations',
          "php: ['8.2', '8.4']" in focused and "image: 'mariadb:10.11'" in focused
          and "image: 'mysql:8.4'" in focused and 'fail-fast: false' in focused
          and 'cancel-in-progress: false' in focused)
    return remaining
