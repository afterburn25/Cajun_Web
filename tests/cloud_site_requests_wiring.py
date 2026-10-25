"""Exact additive cloud-request acceptance wiring; no application or provider execution."""
import ast
import hashlib
import json


def predecessor_bytes(root, path, contract):
    value = (root / path).read_bytes().replace(b'\r\n', b'\n')
    if path == 'tests/prepare_legacy.py':
        baseline = contract['cloud_site_requests']['published_baseline']
        appendix = baseline['preparation_appendix'].encode()
        assert value.endswith(appendix) and value.count(appendix) == 1
        assert hashlib.sha256(value).hexdigest() == baseline['source_sha256'][path]
        value = value[:-len(appendix)]
    return value


def verify(root, contract, workflow, commands, check):
    item = contract['cloud_site_requests']
    additions = item['additions']
    check('Client cloud requests retain the exact expanded native recipe', commands == contract['native_commands'])
    position = commands.index(item['additions_after']) + 1
    check('Five cloud request commands occur once in declared order', len(additions) == len(set(additions)) == 5 and commands[position:position + 5] == additions and all(commands.count(c) == 1 for c in additions))
    previous = [c for c in commands if c not in additions]
    check('Cloud requests preserve all160 prior native commands', len(previous) == item['predecessor_native_count'] == 160 and hashlib.sha256(json.dumps(previous, separators=(',', ':')).encode()).hexdigest() == item['predecessor_native_sha256'])
    for path, digest in item['source_sha256'].items():
        check('Cloud request acceptance source bound ' + path, hashlib.sha256((root / path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest)
    for path, digest in item['published_baseline']['source_sha256'].items():
        check('Published 1.25 acceptance input source bound ' + path, hashlib.sha256((root / path).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest)
    guard = item['published_baseline']['input_guard_correction']
    original_guard = (root / guard['path']).read_text()
    check('Published archive negative test changes only unapproved future version', original_guard.count(guard['after']) == 1 and hashlib.sha256(original_guard.replace(guard['after'], guard['before'], 1).encode()).hexdigest() == guard['predecessor_sha256'])
    reports = json.loads((root / 'tools/acceptance-reports.json').read_text())
    for key, facts in item['predecessor_reports'].items():
        current = reports[key]
        additions = item['reports'] + (item['upgrade']['reports'] if key == 'final' else [])
        check('Cloud request reports are unique in ' + key, len(current) == len(set(current)) and all(current.count(n) == 1 for n in additions))
        retained = [n for n in current if n not in additions]
        check('Cloud requests preserve exact prior ' + key + ' reports', len(retained) == facts['count'] and hashlib.sha256(json.dumps(retained, separators=(',', ':')).encode()).hexdigest() == facts['sha256'])
    upgrade = item['upgrade']
    apache = workflow.split('      - name: Apache protection with and without rewriting\n        id: test\n        run: |\n', 1)[1].split('      - name: Verify shipping source', 1)[0]
    apache_commands = [line.strip() for line in apache.splitlines()]
    check('Installed upgrade guard and real Apache fixture run exactly once in order', apache_commands[-2:] == upgrade['commands'] and all(apache_commands.count(command) == 1 for command in upgrade['commands']))
    check('Installed client request upgrade retains all original Apache commands', hashlib.sha256(json.dumps(apache_commands[:-2], separators=(',', ':')).encode()).hexdigest() == upgrade['predecessor_apache_sha256'])
    driver = (root / 'tests/provider_cloud_sites_cases.py').read_text()
    original = driver.replace(item['browser_hook'], '', 1)
    check('Browser continuation keeps original cloud creation and confirmation flow byte-for-byte', driver.count(item['browser_hook']) == 1 and hashlib.sha256(original.encode()).hexdigest() == item['browser_predecessor_sha256'])
    browser = (root / 'tests/provider_cloud_site_requests_cases.py').read_text()
    tree = ast.parse(browser)
    captures = [node for node in tree.body if isinstance(node, ast.Assign) and any(isinstance(target, ast.Name) and target.id == 'CAPTURES' for target in node.targets)]
    expected = ast.parse("CAPTURES = tuple('Cloud-Client-Website-' + state + '-' + size + '.png' for state in ('Request', 'Review', 'Accepted', 'Confirmed') for size in ('Desktop', 'Mobile'))").body[0]
    called = [node.args[3].value for node in ast.walk(tree) if isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id == 'capture' and len(node.args) >= 4 and isinstance(node.args[3], ast.Constant)]
    check('Browser captures name all eight requested desktop/mobile states', len(captures) == 1 and ast.dump(captures[0]) == ast.dump(expected) and called == ['Request', 'Review', 'Accepted', 'Confirmed'] and item['screenshots'] == ['Cloud-Client-Website-' + state + '-' + size + '.png' for state in called for size in ('Desktop', 'Mobile')])
    focus = (root / '.github/workflows/provider-cloud-sites.yml').read_text()
    check('Focused native job opts in to isolated client request fixture', 'CWP_TEST_CLOUD_SITE_REQUESTS=1 php tests/provider_cloud_site_requests_native.php' in focus)
    check('Full acceptance opts in only on the new native command', workflow.count('CWP_TEST_CLOUD_SITE_REQUESTS=1') == 1)
    check('Focused job preserves old native creation and confirmation', 'php tests/provider_cloud_sites_native.php' in focus and 'php tests/provider_cloud_confirmation_native.php' in focus)
    return previous
