"""Diagnostic-mode module registry for tests/diagnostic_acceptance.py.

Declarative data only — no environment, network or application code runs here.
The registry mirrors the module call order of tests/browser_acceptance.py
(strict acceptance is unchanged and remains the authoritative release gate);
each entry records where the module's run_browser lives, which diagnostic
group it belongs to and which earlier units must have passed first.

Status vocabulary: passed, failed, blocked (a declared prerequisite did not
pass — the unit never ran against possibly contaminated state), skipped
(intentionally deselected) and absent (the module file is not present in
this source tree — lanes add and remove suites).
"""

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

# Bootstrap stages run before modules. requires lists earlier stage keys; a
# failed or blocked prerequisite blocks dependents so dependent checks never
# run against invalid or contaminated state.
STAGES = [
    # key, label, group, requires
    ('public', 'Public routes, assets, guards and estimator', 'website', []),
    ('admin-flow', 'Admin/client HTTP flows, uploads and invoices', 'portal', []),
    ('installer', 'Installer guards and native fresh installation', 'installer', []),
    ('release', 'Release fixture: upgrade, invites, media, billing state', 'installer', ['admin-flow']),
    ('browser', 'Chromium session, captures, overflow, motion and portal', 'website', ['admin-flow']),
]

# (key, module file stem, attribute, group, extra requires beyond stages)
# Every module additionally requires the release and browser stages — they
# seed the shared sessions, release fixture content and the browser object.
# Modules self-seed their own dedicated fixtures; add an edge only where one
# module provably consumes another module's durable state.
MODULES = [
    ('release-browser', 'release_cases', 'run_browser', 'installer', []),
    ('staff', 'staff_cases', 'run_browser', 'portal', []),
    ('support', 'support_cases', 'run_browser', 'security', []),
    ('catalog', 'catalog_cases', 'run_browser', 'billing', []),
    ('catalog-import', 'provider_catalog_import_cases', 'run_browser', 'twentyi-inventory', []),
    ('payments', 'payments_cases', 'run_browser', 'billing', []),
    ('attempts', 'attempts_cases', 'run_browser', 'billing', []),
    ('notifications', 'notifications_cases', 'run_browser', 'mail', []),
    ('ledger', 'ledger_cases', 'run_browser', 'billing', []),
    ('delivery', 'delivery_cases', 'run_browser', 'billing', []),
    ('activity', 'activity_cases', 'run_browser', 'security', []),
    ('portfolio', 'portfolio_cases', 'run_browser', 'website', []),
    ('mail-templates', 'mail_templates_cases', 'run_browser', 'mail', []),
    ('mail-accounts', 'mail_accounts_cases', 'run_browser', 'mail', []),
    ('reviews', 'reviews_cases', 'run_browser', 'website', []),
    ('appointments', 'appointments_cases', 'run_browser', 'portal', []),
    ('mail-connections', 'mail_connections_cases', 'run_browser', 'mail', []),
    ('mail-inbox', 'mail_inbox_cases', 'run_browser', 'mail', []),
    ('mail-message', 'mail_message_cases', 'run_browser', 'mail', []),
    ('mail-notices', 'mail_notices_cases', 'run_browser', 'mail', []),
    ('provider-settings', 'provider_settings_cases', 'run_browser', 'twentyi-inventory', []),
    ('provider-operations', 'provider_operations_cases', 'run_browser', 'twentyi-inventory', []),
    ('domain-search', 'provider_domain_search_cases', 'run_browser', 'twentyi-domains', []),
    ('provider-mailbox', 'provider_mailbox_cases', 'run_browser', 'twentyi-packages', []),
    ('provider-forwarding', 'provider_forwarding_cases', 'run_browser', 'twentyi-packages', []),
    ('registrar', 'provider_registrar_cases', 'run_browser', 'twentyi-domains', []),
    ('domain-renewals', 'domain_renewals_cases', 'run_browser', 'twentyi-domains', []),
    ('cloud-sites', 'provider_cloud_sites_cases', 'run_browser', 'twentyi-packages', []),
    ('service-observations', 'service_observations_cases', 'run_browser', 'twentyi-inventory', []),
    ('mail-drafts', 'mail_drafts_cases', 'run_browser', 'mail', []),
    ('mail-submissions', 'mail_submissions_cases', 'run_browser', 'mail', []),
    ('client-authority', 'client_authority_cases', 'run_browser', 'security', []),
    ('service-plan', 'service_plan_cases', 'run_browser', 'twentyi-inventory', []),
    ('recurring-billing', 'recurring_billing_cases', 'run_browser', 'billing', []),
    ('domain-contracts', 'domain_contracts_cases', 'run_browser', 'twentyi-domains', []),
    ('cloud-providers', 'provider_cloud_providers_cases', 'run_browser', 'twentyi-inventory', []),
    ('registration-periods', 'provider_registration_periods_cases', 'run_browser', 'twentyi-domains', []),
    ('package-php', 'provider_package_php_cases', 'run_browser', 'twentyi-packages', []),
    ('domain-expiry', 'domain_expiry_cases', 'run_browser', 'twentyi-domains', []),
    ('document-roots', 'provider_document_roots_cases', 'run_browser', 'twentyi-packages', []),
    ('package-force-ssl', 'provider_package_force_ssl_cases', 'run_browser', 'twentyi-packages', []),
    ('directory-listing', 'provider_directory_listing_cases', 'run_browser', 'twentyi-packages', []),
    ('force-ssl-controls', 'provider_force_ssl_controls_cases', 'run_browser', 'twentyi-packages', []),
    ('renewal-agreements', 'domain_renewal_agreements_cases', 'run_browser', 'twentyi-domains', []),
    ('wordpress-installed', 'provider_package_wordpress_installed_cases', 'run_browser', 'twentyi-packages', []),
    ('renewal-dispatch', 'domain_renewal_dispatch_cases', 'run_browser', 'twentyi-domains', []),
    ('directory-controls', 'provider_directory_listing_controls_cases', 'run_browser', 'twentyi-packages', []),
    ('stackcp-prerequisites', 'stackcp_prerequisites_cases', 'run_browser', 'stackcp', []),
    ('registration-drafts', 'domain_registration_drafts_cases', 'run_browser', 'twentyi-domains', []),
    ('stackcp-access', 'stackcp_access_cases', 'run_browser', 'stackcp', ['stackcp-prerequisites']),
    ('reset-template', 'provider_password_reset_template_cases', 'run_browser', 'twentyi-inventory', []),
    ('cloud-assignment', 'provider_cloud_assignment_cases', 'run_browser', 'twentyi-inventory', []),
    ('license-release', 'license_release_cases', 'run_browser', 'billing', []),
    ('provider-schedule', 'provider_schedule_cases', 'run_browser', 'twentyi-inventory', []),
]

# Units every module needs before it may run: the release fixture content and
# the live Chromium session (modules receive the shared browser object).
MODULE_STAGE_REQUIRES = ('release', 'browser')

# Suggested repair lane per group, used by the combined report when assigning
# failures to independent workers.
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
