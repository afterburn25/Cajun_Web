"""Exact browser-fixture inverses; source evidence only, never installed acceptance."""
from pathlib import Path, PurePosixPath
from tempfile import TemporaryDirectory
import copy
import hashlib
import json
import re

BASE = '366d53b1ed6b367b32664f2b91ee073fc6813544'
RECEIPT = 'docs/PROVIDER_BROWSER_FIXTURE_REPAIRS_20261101.json'
VERIFIER = 'tests/provider_browser_fixture_provenance.py'
PATHS = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/activity_cases.py',
    'tests/cloud_site_requests_wiring.py',
    'tests/combined_repair_provenance.py',
    'tests/core_browser_fixture_contract.py',
    'tests/delivery_cases.py',
    'tests/domain_contracts_cases.py',
    'tests/domain_expiry_cases.py',
    'tests/domain_registration_drafts_cases.py',
    'tests/domain_renewal_agreements_cases.py',
    'tests/domain_renewal_dispatch_cases.py',
    'tests/domain_renewals_cases.py',
    'tests/hosting_confirmation_cases.py',
    'tests/hosting_fulfillment_cases.py',
    'tests/ledger_cases.py',
    'tests/license_release_cases.py',
    'tests/payment_upgrade_helpers.py',
    'tests/provider_cloud_assignment_cases.py',
    'tests/provider_cloud_confirmation_cases.py',
    'tests/provider_cloud_providers_cases.py',
    'tests/provider_cloud_site_requests_cases.py',
    'tests/provider_cloud_sites_cases.py',
    'tests/provider_directory_listing_cases.py',
    'tests/provider_directory_listing_controls_cases.py',
    'tests/provider_document_roots_cases.py',
    'tests/provider_domain_client_cases.py',
    'tests/provider_domain_search_cases.py',
    'tests/provider_force_ssl_controls_cases.py',
    'tests/provider_forwarding_cases.py',
    'tests/provider_inventory_upgrade_native.php',
    'tests/provider_mailbox_cases.py',
    'tests/provider_operations_cases.py',
    'tests/provider_package_force_ssl_cases.py',
    'tests/provider_package_php_cases.py',
    'tests/provider_package_wordpress_installed_cases.py',
    'tests/provider_password_reset_template_cases.py',
    'tests/provider_plan_catalog_cases.py',
    'tests/provider_registrar_cases.py',
    'tests/provider_registration_periods_cases.py',
    'tests/provider_schedule_cases.py',
    'tests/provider_settings_cases.py',
    'tests/recurring_billing_cases.py',
    'tests/release_cases.py',
    'tests/service_observations_cases.py',
    'tests/service_plan_cases.py',
    'tests/software_commerce_cases.py',
    'tests/stackcp_access_cases.py',
    'tests/stackcp_prerequisites_cases.py',
    'tests/standalone_repair_provenance.py',
    'tests/support_cases.py',
))
LEGACY_RECEIPTS = frozenset((
    'docs/STANDALONE_ACCEPTANCE_REPAIRS.json',
    'docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json',
    'docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json',
))


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def validate_receipt(data, verifier):
    require(isinstance(data, dict) and set(data) == {'base', 'scope', 'verifier_sha256', 'files'},
            'Unexpected provider browser receipt fields')
    require(data['base'] == BASE and isinstance(data['scope'], str) and bool(data['scope']),
            'Unreviewed provider browser correction source')
    require(data['verifier_sha256'] == digest(verifier), 'Provider browser provenance verifier changed')
    require(isinstance(data['files'], dict) and set(data['files']) == PATHS,
            'Unreviewed provider browser correction scope')
    for path, row in data['files'].items():
        require(isinstance(row, dict) and set(row) == {'before_sha256', 'after_sha256', 'edits'},
                'Unexpected provider browser correction fields: ' + path)
        require(all(isinstance(row[key], str) and re.fullmatch('[0-9a-f]{64}', row[key])
                    for key in ('before_sha256', 'after_sha256'))
                and row['before_sha256'] != row['after_sha256'],
                'Invalid provider browser source identities: ' + path)
        require(isinstance(row['edits'], list) and bool(row['edits']),
                'Empty provider browser correction: ' + path)
        for edit in row['edits']:
            require(isinstance(edit, list) and len(edit) == 2
                    and all(isinstance(value, str) and bool(value) for value in edit)
                    and edit[0] != edit[1], 'Invalid provider browser correction edit: ' + path)
    return data


def receipt(root):
    from software_authority_provenance import predecessor_bytes
    verifier = predecessor_bytes(root, VERIFIER, (root / VERIFIER).read_bytes())
    return validate_receipt(json.loads((root / RECEIPT).read_text()), verifier)


def restore(data, path, value):
    require(path in PATHS, 'Unreviewed provider browser predecessor: ' + path)
    row = data['files'][path]
    require(digest(value) == row['after_sha256'], 'Corrected provider browser source differs: ' + path)
    text = value.decode('utf-8')
    for old, new in reversed(row['edits']):
        require(text.count(new) == 1, 'Nonunique provider browser correction inverse: ' + path)
        text = text.replace(new, old, 1)
    original = text.encode('utf-8')
    require(digest(original) == row['before_sha256'], 'Original provider browser bytes not retained: ' + path)
    for old, new in row['edits']:
        require(text.count(old) == 1, 'Nonunique provider browser forward correction: ' + path)
        text = text.replace(old, new, 1)
    require(text.encode('utf-8') == value, 'Provider browser correction is not reversible: ' + path)
    return original


def predecessor(root, path, value):
    return restore(receipt(root), path, value)


def source(root, path):
    """Use only at an existing historical-source comparison, never to run fixtures."""
    value = (root / path).read_bytes()
    return predecessor(root, path, value) if path in PATHS else value


def projected_validation(root, validator, receipt_paths):
    """Give an old receipt verifier its exact 366d inputs; retain the old receipts."""
    require(bool(receipt_paths) and set(receipt_paths) <= LEGACY_RECEIPTS,
            'Unreviewed historical verifier boundary')
    data = receipt(root)
    paths = set(receipt_paths)
    for name in receipt_paths:
        paths.update(json.loads((root / name).read_text())['files'])
    with TemporaryDirectory(prefix='cwp-provider-predecessors-') as folder:
        historical = Path(folder)
        for path in sorted(paths):
            relative = PurePosixPath(path)
            require(not relative.is_absolute() and '..' not in relative.parts
                    and relative.as_posix() == path, 'Unsafe historical receipt path')
            value = (root / path).read_bytes()
            if path == 'tests/software_commerce_native.php':
                from software_authority_provenance import predecessor_bytes
                value = predecessor_bytes(root, path, value)
            if path in PATHS:
                value = restore(data, path, value)
            target = historical / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(value)
        return validator(historical)


def validate(root):
    """Check every current source, inverse, and focused negative receipt cases."""
    from software_authority_provenance import validate as validate_software_authority, predecessor_bytes
    software_checks = validate_software_authority(root)
    data = receipt(root)
    verifier = predecessor_bytes(root, VERIFIER, (root / VERIFIER).read_bytes())
    checks = []

    def passed(name):
        checks.append({'name': name, 'passed': True})

    def refuses(name, callback):
        try:
            callback()
        except AssertionError:
            passed(name)
        else:
            raise AssertionError(name)

    for path in sorted(PATHS):
        current = (root / path).read_bytes()
        restore(data, path, current)
        passed('Exact provider browser correction retains predecessor ' + path)
        refuses('Provider browser receipt refuses altered current bytes ' + path,
                lambda: restore(data, path, current + b'\n# unreviewed synthetic change\n'))
        changed = copy.deepcopy(data)
        changed['files'][path]['before_sha256'] = '0' * 64
        refuses('Provider browser receipt refuses altered predecessor hash ' + path,
                lambda: restore(changed, path, current))
        changed = copy.deepcopy(data)
        changed['files'][path]['edits'].pop()
        refuses('Provider browser receipt refuses omitted edit ' + path,
                lambda: restore(changed, path, current))
    for case in ('base', 'missing-file', 'additional-file', 'verifier', 'unchanged-edit'):
        changed = copy.deepcopy(data)
        path = sorted(PATHS)[0]
        if case == 'base':
            changed['base'] = '0' * 40
        elif case == 'missing-file':
            changed['files'].pop(path)
        elif case == 'additional-file':
            changed['files']['tests/unreviewed.py'] = changed['files'][path]
        elif case == 'verifier':
            changed['verifier_sha256'] = '0' * 64
        else:
            old = changed['files'][path]['edits'][0][0]
            changed['files'][path]['edits'][0] = [old, old]
        refuses('Provider browser receipt refuses changed ' + case,
                lambda: validate_receipt(changed, verifier))
    return checks + software_checks


if __name__ == '__main__':
    root = Path(__file__).resolve().parents[1]
    checks = validate(root)
    print(json.dumps({'passed': True, 'scope': 'Exact fixture source identities, reversible changes and negative receipt checks only; no SQL, HTTP or browser execution.',
                      'count': len(checks), 'checks': checks}, indent=2))
