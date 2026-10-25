"""Verify additive template acceptance while retaining every predecessor recipe check."""
import hashlib
import json


def predecessor_contract(root, contract, native_commands, check):
    addition = contract['password_reset_template']
    expected = [
        '"$PHP_BINARY" tests/twentyi-provider-templates/run.php > qa/password-reset-template-contract-results.json',
        '"$PHP_BINARY" -n tests/twentyi-provider-templates/run.php > qa/password-reset-template-contract-minimal-results.json',
        'CWP_TEST_PASSWORD_RESET_TEMPLATE=1 "$PHP_BINARY" tests/provider_password_reset_template_native.php',
    ]
    check('Template native additions retain the complete current recipe', native_commands == contract['native_commands'])
    position = native_commands.index(addition['after']) + 1
    check('Three template commands run exactly once after the existing operation fixture', addition['commands'] == expected and native_commands[position:position+3] == expected and all(native_commands.count(command) == 1 for command in expected))
    predecessor = [command for command in native_commands if command not in expected]
    fingerprint = hashlib.sha256(json.dumps(predecessor, separators=(',', ':')).encode()).hexdigest()
    check('Template additions retain the exact 157 predecessor commands', len(predecessor) == addition['predecessor_count'] == 157 and fingerprint == addition['predecessor_sha256'])
    driver = (root / 'tests/browser_acceptance.py').read_text()
    call = '        from provider_password_reset_template_cases import run_browser as reset_template_browser\n        reset_template_browser(globals(),browser)\n'
    check('Installed browser driver invokes template acceptance exactly once', driver.count(call) == 1)
    original = driver.replace(call, '')
    check('Template browser addition retains the complete predecessor driver', hashlib.sha256(original.encode()).hexdigest() == addition['predecessor_browser_sha256'])
    reports = json.loads((root / 'tools/acceptance-reports.json').read_text())
    names = ['password-reset-template-contract-results.json', 'password-reset-template-contract-minimal-results.json', 'password-reset-template-native-results.json']
    for lane in ('browser', 'final'):
        check('Template reports are required exactly once in '+lane, all(reports[lane].count(name) == 1 for name in names))
        old = [name for name in reports[lane] if name not in names]
        check('Template reports preserve every predecessor '+lane+' report', hashlib.sha256(json.dumps(old, separators=(',', ':')).encode()).hexdigest() == addition['predecessor_reports_sha256'][lane])
    return {**contract, 'native_commands': predecessor}, predecessor
