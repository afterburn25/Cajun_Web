"""Exact additive acceptance for client folder requests; no installed runtime claims."""
import ast
import copy
import hashlib
import json
from pathlib import Path
import shutil
from unittest.mock import patch


def digest(data):
    return hashlib.sha256(data).hexdigest()


def inverse(data, delta):
    if digest(data) != delta['after_sha256']:
        raise ValueError('Client folder requests current source changed')
    lines = data.decode('utf-8').splitlines(keepends=True)
    for change in reversed(delta['changes']):
        start, end = change['after_start'], change['after_end']
        if ''.join(lines[start:end]) != change['after']:
            raise ValueError('Client folder requests inverse range changed')
        lines[start:end] = change['before'].splitlines(keepends=True)
    result = ''.join(lines).encode('utf-8')
    if digest(result) != delta['before_sha256']:
        raise ValueError('Client folder requests predecessor changed')
    return result


def run(env, tmp, previous_run):
    root, a, check, deny = (env[key] for key in ('ROOT', 'a', 'check', 'deny'))
    read = lambda path: (root / path).read_bytes().replace(b'\r\n', b'\n')
    contract = json.loads(read('tools/acceptance-contract.json'))
    section = contract['client_document_root_requests']
    predecessor = copy.deepcopy(contract); del predecessor['client_document_root_requests']
    previous_contract = (json.dumps(predecessor, indent=2) + '\n').encode()
    if digest(previous_contract) != section['predecessor_contract_sha256']:
        raise ValueError('Client folder requests predecessor contract changed')
    historical = {'tools/acceptance-contract.json': previous_contract}
    for path, delta in section['source_deltas'].items():
        historical[path] = inverse(read(path), delta)
    for path, expected in section['source_sha256'].items():
        if digest(read(path)) != expected:
            raise ValueError('Client folder requests reviewed source changed: ' + path)
    originals = {str((root / path).resolve()): data for path, data in historical.items()}
    read_bytes, read_text = Path.read_bytes, Path.read_text

    def previous_bytes(path):
        key = str(path.resolve()); return originals[key] if key in originals else read_bytes(path)

    def previous_text(path, *args, **kwargs):
        key = str(path.resolve())
        return originals[key].decode(kwargs.get('encoding') or (args[0] if args else 'utf-8')) if key in originals else read_text(path, *args, **kwargs)

    # Execute every original helper against its exact whole predecessor source.
    # Current source and all newly required outputs are verified separately below.
    with patch.object(Path, 'read_bytes', previous_bytes), patch.object(Path, 'read_text', previous_text):
        previous_run(tmp)
    workflow = read('.github/workflows/application-tests.yml').decode()
    registry = json.loads(read('tools/acceptance-reports.json'))
    old_registry = json.loads(historical['tools/acceptance-reports.json'])
    old_workflow = historical['.github/workflows/application-tests.yml'].decode()
    native_block = '      - name: Native database, upgrade and authorization integration\n        id: native\n        run: |\n'
    commands = lambda text: [line.strip() for line in text.split(native_block, 1)[1].split('      - name: Real HTTP', 1)[0].splitlines()]
    current_commands, old_commands = commands(workflow), commands(old_workflow)
    additions = section['native_additions']
    position = current_commands.index(section['native_after']) + 1
    check('Client folder requests retain all154 predecessor native recipes in order',
          len(old_commands) == 154 and current_commands[position:position + len(additions)] == additions
          and all(current_commands.count(command) == 1 for command in additions)
          and [command for command in current_commands if command not in additions] == old_commands)
    inserted = ''.join('          ' + command + '\n' for command in additions)
    modeled = section['browser_step']
    apache = section['apache_additions']
    check('Client folder requests workflow changes only exact additional test commands',
          workflow.count(inserted) == 1 and workflow.count(modeled) == 1 and workflow.count(apache) == 1
          and section['apache_after'] + apache in workflow
          and workflow.replace(inserted, '', 1).replace(modeled, '', 1).replace(apache, '', 1) == old_workflow)
    check('Client folder requests native fixture keeps explicit isolated runtime opt in',
          section['native_additions'][-1] == 'CWP_TEST_DOCUMENT_ROOT_REQUESTS=1 "$PHP_BINARY" tests/provider_document_root_requests_native.php'
          and env['valid_port_context'](workflow))
    check('Client folder requests modeled browser uses selected PHP after Python setup',
          '--php "$PHP_BINARY"' in modeled and workflow.index('.ci-venv/bin/pip install') < workflow.index(modeled))
    for lane in ('browser', 'final'):
        actual = registry[lane]
        required = section['reports'] + (section['apache_reports'] if lane == 'final' else [])
        check('Client folder requests retain every original ' + lane + ' report and require new producers',
              len(actual) == len(set(actual)) and all(actual.count(name) == 1 for name in required)
              and [name for name in actual if name not in required] == old_registry[lane])
    for path, expected in section['source_sha256'].items():
        check('Client folder requests source binding ' + path, digest(read(path)) == expected)
    for path, delta in section['source_deltas'].items():
        check('Client folder requests preserve complete predecessor ' + path, digest(historical[path]) == delta['before_sha256'])
        deny('Client folder requests reject changed current ' + path, lambda path=path, delta=delta: inverse(read(path) + b'\n', delta))

    browser = read('tests/browser_acceptance.py').decode()
    check('Installed client folder callback follows original owner controls and requires captures',
          browser.count(section['browser_hook']) == 1 and section['browser_after'] + section['browser_hook'] in browser)
    tree = ast.parse(read('tests/provider_document_root_requests_cases.py').decode())
    nodes = [node for node in tree.body if
             (isinstance(node, ast.Assign) and any(isinstance(target, ast.Name) and target.id == 'CAPTURES' for target in node.targets))
             or (isinstance(node, ast.FunctionDef) and node.name in ('retained_rows', 'capture_complete'))]
    namespace = {}; exec(compile(ast.Module(body=nodes, type_ignores=[]), '<installed-folder-protocol>', 'exec'), namespace)
    check('Installed client folder captures have eight exact distinct names',
          tuple(section['screenshots']) == namespace['CAPTURES'] and len(set(section['screenshots'])) == 8)
    captures = tmp / 'client-folder-protocol'; captures.mkdir()
    result = {'passed': True, 'checks': [{'name': 'Synthetic capture contract', 'passed': True}], 'captures': list(namespace['CAPTURES'])}
    for name in result['captures']: (captures / name).write_bytes(b'synthetic-only')
    check('Installed folder completion accepts its finite synthetic output contract', namespace['capture_complete'](result, captures))
    for label, mutate in (
        ('false overall', lambda value: value.update(passed=False)),
        ('numeric overall', lambda value: value.update(passed=1)),
        ('empty checks', lambda value: value.update(checks=[])),
        ('false check', lambda value: value['checks'][0].update(passed=False)),
        ('numeric check', lambda value: value['checks'][0].update(passed=1)),
        ('duplicate check', lambda value: value['checks'].append(copy.deepcopy(value['checks'][0]))),
        ('missing capture', lambda value: value['captures'].pop()),
        ('duplicate capture', lambda value: value['captures'].__setitem__(0, value['captures'][1])),
    ):
        changed = copy.deepcopy(result); mutate(changed)
        check('Installed folder completion refuses ' + label, not namespace['capture_complete'](changed, captures))
    first = captures / result['captures'][0]; first.write_bytes(b'')
    check('Installed folder completion refuses empty file', not namespace['capture_complete'](result, captures)); first.unlink()
    check('Installed folder completion refuses missing file', not namespace['capture_complete'](result, captures))
    check('Installed folder retention accepts additions and rejects mutated history',
          namespace['retained_rows']([{'id': 1, 'value': 'original'}], [{'id': 2}, {'id': 1, 'value': 'original'}])
          and not namespace['retained_rows']([{'id': 1, 'value': 'original'}], [{'id': 1, 'value': 'changed'}]))

    evidence_root = tmp / 'client-folder-evidence'; source = evidence_root / 'source'; ctx = env['context'](); env['fixtures'](source, ctx)
    incoming, bindings, jobs = evidence_root / 'partitions', {}, {}; input_sha = '7' * 64
    for index, lane in enumerate(a.LANES):
        job = evidence_root / ('job-' + lane); job.mkdir(parents=True); jobs[lane] = job
        if lane == 'build':
            shutil.copytree(source / 'dist', job / 'dist'); shutil.copytree(source / 'qa', job / 'qa')
        elif lane == 'native-browser':
            for name in registry['browser']:
                if name not in a.BUILD_QA: env['named'](job / 'qa' / name)
        else:
            for name in registry['final']:
                if name not in registry['browser'] and a.evidence_allowed('qa/' + name, lane): env['named'](job / 'qa' / name)
        value = a.seal(job, ctx, incoming / lane, lane, env['outcomes'](lane), input_sha)
        bindings[lane] = {'result': 'success', 'artifact_id': str(index + 81), 'sha256': value,
                          **({'input_sha256': input_sha} if lane == 'build' else {}),
                          **({'checkpoint_id': '89', 'checkpoint_digest': '8' * 64} if lane == 'native-browser' else {})}
    sequence = 0

    def combine():
        nonlocal sequence
        sequence += 1; return a.aggregate(evidence_root / ('combined-' + str(sequence)), ctx, incoming, bindings, registry)

    def reseal(lane='native-browser'):
        job = jobs[lane]
        for path in (job / '.acceptance/checkpoint', incoming / lane):
            if path.exists():
                assert path.resolve().is_relative_to(evidence_root.resolve()); shutil.rmtree(path)
        bindings[lane]['sha256'] = a.seal(job, ctx, incoming / lane, lane, env['outcomes'](lane), input_sha)

    result = combine()
    check('Client folder aggregate requires all current reports', result['complete'] is True and set(result['reports']) == set(registry['final']))
    for name in section['reports'] + section['apache_reports']:
        lane = 'apache' if name in section['apache_reports'] else 'native-browser'
        path = jobs[lane] / 'qa' / name; path.unlink(); reseal(lane); result = combine()
        check('Client folder missing report refuses ' + name, result['complete'] is False and 'Missing reports: ' + name in result['errors'])
        env['named'](path, False); reseal(lane); result = combine()
        check('Client folder failed report refuses ' + name, result['complete'] is False and result['reports'][name]['passed'] is False)
        env['named'](path)
        reseal(lane)
    env['named'](jobs['native-browser'] / 'qa/client-folder-unexpected-results.json'); reseal(); result = combine()
    check('Client folder unexpected report refuses', result['complete'] is False and 'Unexpected reports: client-folder-unexpected-results.json' in result['errors'])
    check('Client folder private fixture state stays excluded',
          not a.evidence_allowed('test-runtime/client-folder-private/session.json', 'native-browser')
          and not a.evidence_allowed('qa/client-folder-private/session.json', 'native-browser'))
