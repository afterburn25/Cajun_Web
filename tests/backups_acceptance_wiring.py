"""Remove only the exact reviewed backup hook before historical byte contracts."""
import hashlib
import json
import combined_source_composition as composition

def predecessor_browser(root, contract):
    driver = (root / 'tests/browser_acceptance.py').read_text()
    if composition.present(root):
        # This is a historical byte comparison only. Prove every current file,
        # then invert the finite reviewed driver repair before the old guards.
        composition.verify_current(root)
        actual_contract = json.loads(composition.ordinary(root, 'tools/acceptance-contract.json'))
        if contract.get('backups_browser_extension') != actual_contract.get('backups_browser_extension'):
            raise AssertionError('Unreviewed backup comparison contract')
        receipt = json.loads(composition.ordinary(root, composition.RECEIPT))
        driver = composition.reconstruct_repair(
            receipt, 'tests/browser_acceptance.py',
            composition.ordinary(root, 'tests/browser_acceptance.py')).decode('utf-8')
    item = contract.get('backups_browser_extension')
    if item is None:
        return driver
    hook = item['hook']
    expected = '            from backups_cases import run_browser as backups_browser\n            backups_browser(globals(),browser)\n'
    if hook != expected or driver.count(hook) != 1:
        raise AssertionError('Backup browser hook must occur exactly once')
    if hashlib.sha256(driver.encode()).hexdigest() != item['source_sha256']:
        raise AssertionError('Backup browser driver identity mismatch')
    predecessor = driver.replace(hook, '', 1)
    if hashlib.sha256(predecessor.encode()).hexdigest() != item['predecessor_sha256']:
        raise AssertionError('Backup hook changed predecessor browser behavior')
    return predecessor
