#!/usr/bin/env python3
"""AST-bound registry for the strict installed HTTP/browser acceptance runner.

The diagnostic runner is secondary evidence. The non-focused branch of
``tests/browser_acceptance.py`` remains authoritative for which browser
modules exist and their execution order. This module discovers that branch
with Python's AST, then attaches finite group and prerequisite metadata.
"""
import argparse
import ast
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
STRICT_RUNNER = ROOT / 'tests/browser_acceptance.py'

GROUPS = {
    'website': 'Website, navigation, and public pages',
    'installer': 'Installer and upgrade procedures',
    'portal': 'Client portal and account management',
    'billing': 'Payments, invoices, and billing',
    'mail': 'Email, inbox, and notifications',
    'twentyi-inventory': '20i provider inventory, settings and service plans',
    'twentyi-domains': '20i domain search, registrar, renewals and contracts',
    'twentyi-packages': '20i package controls, cloud sites and website folders',
    'stackcp': 'StackCP access and provider handoff',
    'security': 'Security, permissions, support and recovery',
}

STAGES = [
    ('public', 'Public routes, assets, guards and estimator', 'website', []),
    ('admin-flow', 'Admin/client HTTP flows, uploads and invoices', 'portal', []),
    ('installer', 'Installer guards and native fresh installation', 'installer', []),
    ('release', 'Release fixture: upgrade, invites, media, billing state', 'installer', ['admin-flow']),
    ('browser', 'Chromium session, captures, overflow, motion and portal', 'website', ['admin-flow']),
]


def _is_name(node, name):
    return isinstance(node, ast.Name) and node.id == name


def _nonfocused_body(tree):
    matches = []
    for node in ast.walk(tree):
        if isinstance(node, ast.If) and _is_name(node.test, 'software_only'):
            if (len(node.orelse) == 1 and isinstance(node.orelse[0], ast.If)
                    and _is_name(node.orelse[0].test, 'financing_only')):
                matches.append(node.orelse[0].orelse)
    if len(matches) != 1 or not matches[0]:
        raise RuntimeError('Expected one focused branch with a non-focused else body')
    return matches[0]


def _ordered_run_browser_imports(statements):
    found = []
    for node in statements:
        if isinstance(node, ast.ImportFrom):
            if any(alias.name == 'run_browser' for alias in node.names):
                if not node.module or not node.module.endswith('_cases'):
                    raise RuntimeError('Unexpected run_browser import in strict non-focused branch')
                found.append(node.module)
        elif isinstance(node, ast.If):
            found.extend(_ordered_run_browser_imports(node.body))
            found.extend(_ordered_run_browser_imports(node.orelse))
        elif isinstance(node, (ast.With, ast.AsyncWith, ast.For, ast.AsyncFor, ast.While)):
            found.extend(_ordered_run_browser_imports(node.body))
            found.extend(_ordered_run_browser_imports(getattr(node, 'orelse', [])))
        elif isinstance(node, ast.Try):
            found.extend(_ordered_run_browser_imports(node.body))
            for handler in node.handlers:
                found.extend(_ordered_run_browser_imports(handler.body))
            found.extend(_ordered_run_browser_imports(node.orelse))
            found.extend(_ordered_run_browser_imports(node.finalbody))
    return found


def discover_strict_modules(path=STRICT_RUNNER):
    tree = ast.parse(Path(path).read_bytes(), filename=str(path))
    modules = ['release_cases'] + _ordered_run_browser_imports(_nonfocused_body(tree))
    if len(modules) != len(set(modules)):
        duplicates = sorted({name for name in modules if modules.count(name) > 1})
        raise RuntimeError('Duplicate strict non-focused modules: ' + ', '.join(duplicates))
    return modules


# stem: (stable key, group, strict prerequisites). All modules also require
# the release and browser stages. Cross-group prerequisites are replayed as
# preparation units on a fresh database so each group lane is self-contained.
MODULE_SPECS = {
    'release_cases': ('release-browser', 'installer', []),
    'staff_cases': ('staff', 'portal', []),
    'support_cases': ('support', 'security', ['staff']),
    'database_update_cases': ('database-updates', 'installer', ['support']),
    'catalog_cases': ('catalog', 'billing', ['staff']),
    'provider_catalog_import_cases': ('catalog-import', 'twentyi-inventory', []),
    'payments_cases': ('payments', 'billing', ['catalog']),
    'attempts_cases': ('attempts', 'billing', ['payments']),
    'notifications_cases': ('notifications', 'mail', ['attempts']),
    'ledger_cases': ('ledger', 'billing', ['payments']),
    'delivery_cases': ('delivery', 'billing', ['support', 'payments']),
    'backups_cases': ('backups', 'security', ['staff']),
    'activity_cases': ('activity', 'security', ['support', 'payments', 'delivery', 'ledger']),
    'portfolio_cases': ('portfolio', 'website', ['staff']),
    'mail_templates_cases': ('mail-templates', 'mail', ['payments']),
    'mail_accounts_cases': ('mail-accounts', 'mail', ['payments']),
    'reviews_cases': ('reviews', 'website', ['payments']),
    'appointments_cases': ('appointments', 'portal', ['payments']),
    'mail_connections_cases': ('mail-connections', 'mail', ['mail-accounts']),
    'mail_inbox_cases': ('mail-inbox', 'mail', ['mail-accounts']),
    'mail_message_cases': ('mail-message', 'mail', ['mail-inbox']),
    'mail_notices_cases': ('mail-notices', 'mail', ['mail-inbox', 'mail-message', 'activity']),
    'provider_settings_cases': ('provider-settings', 'twentyi-inventory', ['payments', 'activity']),
    'provider_operations_cases': ('provider-operations', 'twentyi-inventory', ['provider-settings']),
    'provider_domain_search_cases': ('domain-search', 'twentyi-domains', ['provider-settings']),
    'provider_mailbox_cases': ('provider-mailbox', 'twentyi-packages', ['provider-settings', 'domain-search']),
    'provider_forwarding_cases': ('provider-forwarding', 'twentyi-packages', ['provider-settings', 'domain-search']),
    'provider_registrar_cases': ('registrar', 'twentyi-domains', ['provider-settings']),
    'domain_renewals_cases': ('domain-renewals', 'twentyi-domains', ['registrar']),
    'provider_cloud_sites_cases': ('cloud-sites', 'twentyi-packages', ['provider-operations']),
    'service_observations_cases': ('service-observations', 'twentyi-inventory', ['provider-settings', 'registrar']),
    'mail_drafts_cases': ('mail-drafts', 'mail', ['mail-accounts']),
    'mail_submissions_cases': ('mail-submissions', 'mail', ['mail-accounts', 'mail-drafts']),
    'client_authority_cases': ('client-authority', 'security', ['payments']),
    'service_plan_cases': ('service-plan', 'twentyi-inventory', ['payments', 'support', 'database-updates']),
    'recurring_billing_cases': ('recurring-billing', 'billing', ['payments', 'service-plan', 'ledger', 'provider-operations']),
    'domain_contracts_cases': ('domain-contracts', 'twentyi-domains', ['registrar']),
    'provider_cloud_providers_cases': ('cloud-providers', 'twentyi-inventory', ['provider-settings']),
    'provider_registration_periods_cases': ('registration-periods', 'twentyi-domains', ['provider-settings', 'domain-search']),
    'provider_package_php_cases': ('package-php', 'twentyi-packages', ['provider-settings']),
    'domain_expiry_cases': ('domain-expiry', 'twentyi-domains', ['registrar']),
    'provider_document_roots_cases': ('document-roots', 'twentyi-packages', ['provider-settings']),
    'provider_package_force_ssl_cases': ('package-force-ssl', 'twentyi-packages', ['provider-settings']),
    'provider_directory_listing_cases': ('directory-listing', 'twentyi-packages', ['provider-settings']),
    'provider_force_ssl_controls_cases': ('force-ssl-controls', 'twentyi-packages', ['provider-settings']),
    'domain_renewal_agreements_cases': ('renewal-agreements', 'twentyi-domains', ['registrar', 'domain-renewals', 'service-plan', 'recurring-billing']),
    'provider_package_wordpress_installed_cases': ('wordpress-installed', 'twentyi-packages', ['provider-settings']),
    'domain_renewal_dispatch_cases': ('renewal-dispatch', 'twentyi-domains', ['renewal-agreements']),
    'provider_directory_listing_controls_cases': ('directory-controls', 'twentyi-packages', ['provider-settings']),
    'stackcp_prerequisites_cases': ('stackcp-prerequisites', 'stackcp', ['provider-settings']),
    'domain_registration_drafts_cases': ('registration-drafts', 'twentyi-domains', ['provider-operations', 'domain-renewals', 'service-plan']),
    'stackcp_access_cases': ('stackcp-access', 'stackcp', ['stackcp-prerequisites']),
    'provider_password_reset_template_cases': ('reset-template', 'twentyi-inventory', ['provider-settings']),
    'provider_cloud_assignment_cases': ('cloud-assignment', 'twentyi-inventory', ['provider-settings']),
    'license_release_cases': ('license-release', 'billing', ['staff', 'provider-settings']),
    'provider_schedule_cases': ('provider-schedule', 'twentyi-inventory', ['provider-settings']),
    'software_commerce_cases': ('software-commerce', 'billing', []),
    'license_financing_cases': ('license-financing', 'billing', ['software-commerce']),
    'contacts_cases': ('contacts', 'portal', ['recurring-billing', 'registration-drafts', 'software-commerce', 'license-financing', 'database-updates']),
    'client_identity_cases': ('client-identity', 'portal', ['contacts']),
}

STRICT_MODULES = discover_strict_modules()
if set(STRICT_MODULES) != set(MODULE_SPECS):
    missing = sorted(set(STRICT_MODULES) - set(MODULE_SPECS))
    stale = sorted(set(MODULE_SPECS) - set(STRICT_MODULES))
    raise RuntimeError('Diagnostic metadata differs from strict modules; missing=%r stale=%r' % (missing, stale))

MODULES = [
    (MODULE_SPECS[stem][0], stem, 'run_browser', MODULE_SPECS[stem][1], list(MODULE_SPECS[stem][2]))
    for stem in STRICT_MODULES
]
MODULE_STAGE_REQUIRES = ('release', 'browser')
MODULE_STAGE_REQUIRES_BY_KEY = {
    # release_cases.run_browser reads the fresh-install configuration written
    # by the installer stage; a failed installer must block this module rather
    # than letting the missing file appear as an independent browser failure.
    'release-browser': ('installer', 'release', 'browser'),
}


def module_stage_requires(key):
    return MODULE_STAGE_REQUIRES_BY_KEY.get(key, MODULE_STAGE_REQUIRES)

ASSIGNMENTS = {
    'website': 'site-www lane',
    'installer': 'release/installer lane',
    'portal': 'studio/portal lane',
    'billing': 'billing lane',
    'mail': 'mail-foundation lane',
    'twentyi-inventory': '20i inventory lane',
    'twentyi-domains': '20i domains lane',
    'twentyi-packages': '20i package-controls lane',
    'stackcp': 'stackcp lane',
    'security': 'security/recovery lane',
}


def resolve_groups(raw):
    if not isinstance(raw, str) or not raw or len(raw) > 512:
        raise ValueError('Diagnostic groups must be a non-empty bounded string')
    if not re.fullmatch(r'[a-z0-9,-]+', raw):
        raise ValueError('Diagnostic groups contain a disallowed character')
    parts = raw.split(',')
    if any(not part for part in parts) or len(parts) != len(set(parts)):
        raise ValueError('Diagnostic groups must be unique non-empty comma-separated names')
    if 'all' in parts and parts != ['all']:
        raise ValueError('"all" cannot be combined with named groups')
    unknown = sorted(set(parts) - set(GROUPS) - {'all'})
    if unknown:
        raise ValueError('Unknown diagnostic group(s): ' + ', '.join(unknown))
    return sorted(GROUPS) if parts == ['all'] else sorted(parts)


def lanes_for(groups):
    lanes = [
        {'group': group, 'php_version': '8.3', 'database': 'mariadb-10.11',
         'tag': group, 'artifact': 'diag-group-' + group, 'role': 'combined'}
        for group in groups
    ]
    if 'billing' in groups:
        lanes.append({'group': 'billing', 'php_version': '8.4', 'database': 'mysql-8.4',
                      'tag': 'target-billing-php84-mysql',
                      'artifact': 'diag-target-billing-php84-mysql', 'role': 'target'})
    return lanes


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--resolve-groups', metavar='LIST')
    parser.add_argument('--github-output')
    parser.add_argument('--list', action='store_true')
    args = parser.parse_args()
    if args.list:
        print(json.dumps({'groups': GROUPS, 'modules': MODULES}, indent=2))
        return 0
    if args.resolve_groups is None:
        parser.error('--resolve-groups or --list is required')
    try:
        groups = resolve_groups(args.resolve_groups)
    except ValueError as exc:
        raise SystemExit(str(exc))
    payload = {'groups': groups, 'lanes': lanes_for(groups)}
    if args.github_output:
        with Path(args.github_output).open('a', encoding='utf-8') as output:
            output.write('groups=' + json.dumps(groups, separators=(',', ':')) + '\n')
            output.write('lanes=' + json.dumps(payload['lanes'], separators=(',', ':')) + '\n')
    print(json.dumps(payload, separators=(',', ':')))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
