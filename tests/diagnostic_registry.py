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
# Edges below were verified against controllers, *_ready() gates and module
# fixtures in run 39934414489: admin readiness gates chain through
# staff_cases (staff_migrate → staff_ready), payments_cases creates the
# shared request-staff account used by 34 modules, provider feature pages
# render on ?view=provider-settings behind provider_settings_ready and the
# provider_inventory_prepare that provider_settings_cases applies, and the
# mail feature chain applies its own migrations in order. Prerequisite
# modules run as 'preparation' units inside each group job so every group
# is self-sufficient on a fresh ci_ schema.
MODULES = [
    ('release-browser', 'release_cases', 'run_browser', 'installer', []),
    ('staff', 'staff_cases', 'run_browser', 'portal', []),
    ('support', 'support_cases', 'run_browser', 'security', ['staff']),
    ('catalog', 'catalog_cases', 'run_browser', 'billing', ['staff']),
    ('catalog-import', 'provider_catalog_import_cases', 'run_browser', 'twentyi-inventory', []),
    ('payments', 'payments_cases', 'run_browser', 'billing', ['catalog']),
    ('attempts', 'attempts_cases', 'run_browser', 'billing', ['payments']),
    ('notifications', 'notifications_cases', 'run_browser', 'mail', ['attempts']),
    ('ledger', 'ledger_cases', 'run_browser', 'billing', ['payments']),
    ('delivery', 'delivery_cases', 'run_browser', 'billing', ['support', 'payments']),
    ('activity', 'activity_cases', 'run_browser', 'security', ['support', 'payments', 'delivery']),
    ('portfolio', 'portfolio_cases', 'run_browser', 'website', ['staff']),
    ('mail-templates', 'mail_templates_cases', 'run_browser', 'mail', ['payments']),
    ('mail-accounts', 'mail_accounts_cases', 'run_browser', 'mail', ['payments']),
    ('reviews', 'reviews_cases', 'run_browser', 'website', ['payments']),
    ('appointments', 'appointments_cases', 'run_browser', 'portal', ['payments']),
    ('mail-connections', 'mail_connections_cases', 'run_browser', 'mail', ['mail-accounts']),
    ('mail-inbox', 'mail_inbox_cases', 'run_browser', 'mail', ['mail-accounts']),
    ('mail-message', 'mail_message_cases', 'run_browser', 'mail', ['mail-inbox']),
    ('mail-notices', 'mail_notices_cases', 'run_browser', 'mail', ['mail-inbox', 'mail-message', 'activity']),
    ('provider-settings', 'provider_settings_cases', 'run_browser', 'twentyi-inventory', ['payments', 'activity']),
    ('provider-operations', 'provider_operations_cases', 'run_browser', 'twentyi-inventory', ['provider-settings']),
    ('domain-search', 'provider_domain_search_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('provider-mailbox', 'provider_mailbox_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('provider-forwarding', 'provider_forwarding_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('registrar', 'provider_registrar_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('domain-renewals', 'domain_renewals_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('cloud-sites', 'provider_cloud_sites_cases', 'run_browser', 'twentyi-packages', ['provider-operations']),
    ('service-observations', 'service_observations_cases', 'run_browser', 'twentyi-inventory', ['provider-settings']),
    ('mail-drafts', 'mail_drafts_cases', 'run_browser', 'mail', ['mail-accounts']),
    ('mail-submissions', 'mail_submissions_cases', 'run_browser', 'mail', ['mail-accounts', 'mail-drafts']),
    ('client-authority', 'client_authority_cases', 'run_browser', 'security', ['payments']),
    ('service-plan', 'service_plan_cases', 'run_browser', 'twentyi-inventory', ['payments']),
    ('recurring-billing', 'recurring_billing_cases', 'run_browser', 'billing', ['payments', 'service-plan', 'ledger']),
    ('domain-contracts', 'domain_contracts_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('cloud-providers', 'provider_cloud_providers_cases', 'run_browser', 'twentyi-inventory', ['provider-settings']),
    ('registration-periods', 'provider_registration_periods_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('package-php', 'provider_package_php_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('domain-expiry', 'domain_expiry_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('document-roots', 'provider_document_roots_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('package-force-ssl', 'provider_package_force_ssl_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('directory-listing', 'provider_directory_listing_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('force-ssl-controls', 'provider_force_ssl_controls_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('renewal-agreements', 'domain_renewal_agreements_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('wordpress-installed', 'provider_package_wordpress_installed_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('renewal-dispatch', 'domain_renewal_dispatch_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('directory-controls', 'provider_directory_listing_controls_cases', 'run_browser', 'twentyi-packages', ['provider-settings']),
    ('stackcp-prerequisites', 'stackcp_prerequisites_cases', 'run_browser', 'stackcp', ['provider-settings']),
    ('registration-drafts', 'domain_registration_drafts_cases', 'run_browser', 'twentyi-domains', ['provider-settings']),
    ('stackcp-access', 'stackcp_access_cases', 'run_browser', 'stackcp', ['stackcp-prerequisites']),
    ('reset-template', 'provider_password_reset_template_cases', 'run_browser', 'twentyi-inventory', ['provider-settings']),
    ('cloud-assignment', 'provider_cloud_assignment_cases', 'run_browser', 'twentyi-inventory', ['provider-settings']),
    ('license-release', 'license_release_cases', 'run_browser', 'billing', ['staff', 'provider-settings']),
    ('provider-schedule', 'provider_schedule_cases', 'run_browser', 'twentyi-inventory', ['provider-settings']),
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
