"""Remove only the exact reviewed backup hook before historical byte contracts."""
import hashlib

def predecessor_browser(root, contract):
    driver = (root / 'tests/browser_acceptance.py').read_text()
    item = contract.get('backups_browser_extension')
    if item is None:
        return driver
    hook = item['hook']
    expected = '        from backups_cases import run_browser as backups_browser\n        backups_browser(globals(),browser)\n'
    if hook != expected or driver.count(hook) != 1:
        raise AssertionError('Backup browser hook must occur exactly once')
    if hashlib.sha256(driver.encode()).hexdigest() != item['source_sha256']:
        raise AssertionError('Backup browser driver identity mismatch')
    predecessor = driver.replace(hook, '', 1)
    if hashlib.sha256(predecessor.encode()).hexdigest() != item['predecessor_sha256']:
        raise AssertionError('Backup hook changed predecessor browser behavior')
    return predecessor
