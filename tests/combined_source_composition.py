"""Exact component composition for historical source checks, never runtime code.

The live checkout must equal the finite union of the reviewed component trees,
plus the canonical inventory, four reversible comparison adapters, exact
integration repairs, four contact files bound to the retained PR331 source and
the ticket screenshot helper bound to its retained two-file source commit.
Unmodified standalone validators run in disposable exact-component checkouts;
PHP, browser, migration and packaging commands continue to use the live checkout.
"""
from pathlib import Path, PurePosixPath
import base64
import copy
import hashlib
import json
import os
import re
import stat
import subprocess
import sys
import tempfile
from urllib.parse import unquote, urlparse

BASE = '84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd'
BASE_TREE = 'a206e95562ab751cd9461db3a8ed32c4305ecbec'
COMPONENTS = {
    'account': {'commit': 'fb8b5627d8b18bf662a2dff5bce138365ac3285f', 'tree': '743eca5f6aacb30f2a74bd65ee0ebf7ff849f7a2'},
    'software': {'commit': '8e93c2a782b34a2fd52537fb825911c672267c08', 'tree': 'c44d522cdb2a83a27bc694e1e79748de19e83be2'},
    'performance': {'commit': '4668c7d9e92127a97e92a5ea1c6f9bcf287107c9', 'tree': '7632498a7227e10fa2974c1fb840b4130cba49c4'},
    'finance': {'commit': '2dfe76973de68e60b04da5343b75f3fbdedd4d59', 'tree': '5afec38a662307d073e2c54730af36971e177adb'},
}
RECEIPT = 'docs/COMBINED_SOURCE_COMPOSITION_20261102.json'
VERIFIER = 'tests/combined_source_composition.py'
REGRESSION = 'tests/combined_source_composition_test.py'
INVENTORY = 'site/install/inventory.generated.php'
ONBOARDING_SOURCE = 'docs/SOFTWARE_ONBOARDING_SOURCE.json'
ONBOARDING_BASE = '3ddaa97cd9bf46f9a447b33828c2b1bf3114a77c'
ONBOARDING_BASE_TREE = '9f77b938f343d1f7c6e56e121ed60fdd9a9446b4'
ONBOARDING_MODIFIED = frozenset((
    '.github/workflows/software-focused-acceptance.yml',
    'site/app/licenses/software_assets.php',
    'site/app/licenses/software_products.php',
    'site/app/jobs/run.php',
    'site/assets/js/admin.js',
    'tests/software_commerce_cases.py',
))
ONBOARDING_ADDITIONS = frozenset((
    '.github/workflows/software-onboarding-native.yml',
    'docs/SOFTWARE_ONBOARDING.md',
    'site/admin/views/software_onboarding.php',
    'site/app/licenses/software_onboarding.php',
    'site/database/migrations/software_onboarding_migrate.php',
    'site/database/schema/software_onboarding_schema.php',
    'tests/software_onboarding_native.php',
))
REPAIR_BASE = '6247ab257ff8562afb317908cf5de9a602b34d9c'
REPAIR_BASE_TREE = '9620a10bdd3be1876b7a994a14d739a76e5e3745'
# These four paths did not exist in the original component union. Their exact
# retained source and any reviewed corrections are verified separately; they
# cannot replace an existing component, adapter, repair or proof path.
ADDITION_SOURCE = 'ccbe765516e261f92d55acd65978262f3811e9bc'
ADDITION_SOURCE_TREE = '8de0cd2602844f9d913bac80c2a82702f0745466'
ADDITIONS = frozenset((
    'site/app/clients/contacts.php',
    'site/database/migrations/contacts_migrate.php',
    'site/database/schema/contacts_schema.php',
    'tests/contacts_cases.py',
))
# BEGIN GENERATED CONTACT ADDITION IDENTITIES
ADDITION_SHA256 = {'site/app/clients/contacts.php': '73732cea46fc24532bfe95dedea72873210ffdf84f5cb226f4ab4424abcc335a', 'site/database/migrations/contacts_migrate.php': 'c4f447909a14ddfc870065b517c29f4be7786e96488d7aa1fca616d399c7aeea', 'site/database/schema/contacts_schema.php': 'a2fde85f75d286241a3642bf4f4fab0a7b4aa98c26705e9316552a9223974f0d', 'tests/contacts_cases.py': 'fc5a777bf9f464744e2ff9315114b9b5c0e771a5ba991b04fa5c134dcd05eed2'}
# BEGIN GENERATED DATABASE WORKFLOW CONTACT IDENTITIES
ADDITION_SHA256['tests/contacts_cases.py'] = 'c4fc6c75febba8d28db7a5874368cb6fbade205e0fc94d57162f19be12e3ec39'
# END GENERATED DATABASE WORKFLOW CONTACT IDENTITIES
# END GENERATED CONTACT ADDITION IDENTITIES
# The ticket helper is new source, not a repair of a component file. Its exact
# source commit is retained as an ancestor independently from the older PR331
# contact source. Keeping the classes separate prevents one source from
# broadening or substituting the other.
TICKET_ADDITION_SOURCE = '2421ff27d21a75f888a23ae0f2d17d3066a9893c'
TICKET_ADDITION_SOURCE_TREE = '375e82042ded868582448a8d14b2d81ad72d7daf'
TICKET_ADDITIONS = frozenset(('tests/ticket_screenshot.py',))
TICKET_SOURCE_PATHS = TICKET_ADDITIONS | {'tests/ticket_cases.py'}
TICKET_ADDITION_SHA256 = {
    'tests/ticket_screenshot.py': '2ff95ddef50e9acd3ddf9f5a865ad54f029c58aebddc2ab9c5ba46749014419d',
}
# The diagnostic runner is a second finite addition source.  It changes no
# shipping, schema, authorization or billing path.  Its exact six-file source
# commit remains an ancestor, while reviewed current bytes and their complete
# source-to-current Git deltas are independently pinned below.
DIAGNOSTIC_ADDITION_SOURCE = 'dfda440c70dbda178a23b0dcca7b17941e1965ea'
DIAGNOSTIC_ADDITION_SOURCE_TREE = 'b8f4b46a63871e852e92e90e0422d34d4785da51'
DIAGNOSTIC_ADDITIONS = frozenset((
    '.github/workflows/diagnostic-tests.yml',
    'docs/DIAGNOSTIC_ACCEPTANCE.md',
    'tests/diagnostic_acceptance.py',
    'tests/diagnostic_acceptance_test.py',
    'tests/diagnostic_registry.py',
    'tests/diagnostic_report.py',
))
DIAGNOSTIC_ADDITION_SHA256 = {
    '.github/workflows/diagnostic-tests.yml': {'source': '4a2aa7e221a93a55f8b8ecd9fe5af4545f23736722485e9f0e0085b7c37d5b9c', 'current': '6685b5ad314bb41e47fbc3d133b6b2c83a07c0e1d3de6b1db02a0c0ad47051be', 'delta': '8c1b9fc1761c713c536db7ca22072b14680ff2a5866d3c73db37adb5dd26b8c8'},
    'docs/DIAGNOSTIC_ACCEPTANCE.md': {'source': '2a456813d05799febff04c3adcaf8c4d1b8ab38a3ff6647d91aaebcf8d7c3593', 'current': 'ec2dead5ccebd3b2b5f619c7a17391e8d53df75211e3d223aeac008c35c53ca0', 'delta': '29a61e5977b9911adf244faedbf8b4e74379a55c460b3336177bc9881a02ec8a'},
    'tests/diagnostic_acceptance.py': {'source': 'aa17ed6946f2660a39d2e652ab869cb600c93d2dcd699c98c7a1963ae890bcad', 'current': '4b9505faafe06a4e822999dd2d04e15bd482e3daa673135a5bdc25a1c1e6aed4', 'delta': '1f433b177ab5e6fa78f90aa2212acabb1246fb8ed112f607a110095a4866a4c2'},
    'tests/diagnostic_acceptance_test.py': {'source': 'ce14b1ac0e172b163cb20ba192bb3f302ec063f4c134151360eebf420f738655', 'current': 'd0dae0bc115daf3d8cdfcf8d39cd8199d4f97bcc11bcbbc2fa4ef7801929203b', 'delta': 'f47a2578e25ea89fdd61d07542cd6478c9b1072edba9f6aa22325205b11c0e83'},
    'tests/diagnostic_registry.py': {'source': '1188ef031448d3a56e100ed18f3d357d8f75380b6b6087848c97032e2e93be34', 'current': '823d13a81b24d1c39f0e53a58a26ba727520dcd7b677baa5825a00ded4fce8d6', 'delta': 'f39cb50543cd25907091c8bae4a80db8c174472be372e30e21594275a1907a34'},
    'tests/diagnostic_report.py': {'source': 'cf43434d0a81b35b2103f341a1c6d7e4a59e175d3d7642ae2cce860cd783bc33', 'current': 'e1b06b07dfd74b862e6ccdba78f208982377e5805d9ac43b6b0e736fa86b5181', 'delta': '3e2dd2ca6211956be8b454c73dd9f4c7ac1870a6be15120f6b1214d6b5278863'},
}
# BEGIN GENERATED DATABASE WORKFLOW DIAGNOSTIC IDENTITIES
DIAGNOSTIC_ADDITION_SHA256['tests/diagnostic_acceptance_test.py']['current'] = '6fa76fc60584158ae29729995e091f43efb5cd1373cf0853a75234150db8ef61'
DIAGNOSTIC_ADDITION_SHA256['tests/diagnostic_acceptance_test.py']['delta'] = 'acead1f82b00128a776321dc79495d72b51e4f45042618bc935cdf78fed44b19'
DIAGNOSTIC_ADDITION_SHA256['tests/diagnostic_registry.py']['current'] = '368048a13179629c5ce37f8b355b425a928724f72821e7eb9e29ba830405ee4f'
DIAGNOSTIC_ADDITION_SHA256['tests/diagnostic_registry.py']['delta'] = 'cccc34657c225ee8dcd1af78ebafe1fe7beb7220af868c792e30ec5530c16e4b'
# END GENERATED DATABASE WORKFLOW DIAGNOSTIC IDENTITIES
DIAGNOSTIC_ADDITION_SHA256['.github/workflows/diagnostic-tests.yml']['current'] = '26243783910e73df36b0efe90137b56d37d8832f2df7191796ee9fb21c21248c'
DIAGNOSTIC_ADDITION_SHA256['.github/workflows/diagnostic-tests.yml']['delta'] = '334c53286ccf214c8c9ddd210bd53e5105e2508e4b1311bfffe6890ffd3d2fbd'
DATABASE_WORKFLOW_BASE = '8d31de5e125ece6ad999dab4bb9e483dd8b70822'
DATABASE_WORKFLOW_BASE_TREE = 'a73a70b6bbe05fdc1e773cf078e1a642fb9a6816'
DATABASE_WORKFLOW_SOURCE = 'aeeea87e4d0d31dcfc187daed1a29cb9946d907c'
DATABASE_WORKFLOW_SOURCE_TREE = '6dc4871e6edf370844534a837f6f6de6ef7c58c4'
DATABASE_WORKFLOW_ADDITIONS = frozenset((
    'site/app/settings/database_schemas.php',
    'tests/database_schema_action_policy.php',
    'tests/database_schema_native.php',
    'tests/database_schema_registry_policy.php',
    'tests/database_schema_view_policy.php',
    'tests/database_update_cases.py',
    'tests/database_update_forms.py',
))
DATABASE_WORKFLOW_REPAIRS = frozenset((
    '.github/workflows/application-tests.yml',
    'site/admin/controllers/studio.php',
    'site/admin/views/studio/upgrade.php',
    'site/app/staff/accounts.php',
    'tests/acceptance_artifacts_test.py',
    'tests/account_notification_workflow_contract.py',
    'tests/admin_20i_populated_upgrade.py',
    'tests/browser_acceptance.py',
    'tests/owner_update_session_policy.php',
    'tests/release_cases.py',
    'tests/service_plan_cases.py',
    'tools/acceptance-contract.json',
    'tools/acceptance-reports.json',
    'tools/update/owner-update.php',
))
DATABASE_WORKFLOW_CONTACTS = frozenset(('tests/contacts_cases.py',))
DATABASE_WORKFLOW_DIAGNOSTICS = frozenset((
    'tests/diagnostic_acceptance_test.py',
    'tests/diagnostic_registry.py',
))
DATABASE_WORKFLOW_SOURCE_PATHS = (DATABASE_WORKFLOW_ADDITIONS | DATABASE_WORKFLOW_REPAIRS
                                  | DATABASE_WORKFLOW_CONTACTS | DATABASE_WORKFLOW_DIAGNOSTICS
                                  | {INVENTORY})
# BEGIN GENERATED DATABASE WORKFLOW ADDITION IDENTITIES
DATABASE_WORKFLOW_ADDITION_SHA256 = {'site/app/settings/database_schemas.php': 'fe6126ddd5e1690d817eda4d80c6f298b1abc7be71bc56e677d11b1af81d5a5d', 'tests/database_schema_action_policy.php': 'af06296416c48856b3272ae12bf136812cfced28143a9665de8afa3cb67556bd', 'tests/database_schema_native.php': '5b8a7dbb4b43af3bc392bd03f0062257d85d85ab31e4deac585dbfec04fd490e', 'tests/database_schema_registry_policy.php': '9d2262fae374e1efccb41c3cf1cfa6ecbf5af39955d572f75be0088ba2b05acd', 'tests/database_schema_view_policy.php': '4b3913711d35665e3c382f9d168dd8ebe5f7606b5d33bd9770a869bb6b8b7935', 'tests/database_update_cases.py': '11298023398d6f2ef9c374ef2a9248909f3517d04344f0ae9080de3a5345c592', 'tests/database_update_forms.py': '6f066f71322ea679078e492e549e20b4434d397542125743985edf9950aac9e2'}
# END GENERATED DATABASE WORKFLOW ADDITION IDENTITIES
CLOUD_INTAKE_BASE = '47425290d1f69ba312b59ae888c9390772391966'
CLOUD_INTAKE_BASE_TREE = 'cc1deebbf6ce4e75006b48c4fca906e5006bd023'
CLOUD_INTAKE_SOURCE = '30df630fc802838396d8d308264ff52bed5e072a'
CLOUD_INTAKE_SOURCE_TREE = 'aec45c42645be94b79f1919f0b7534e7aaa67973'
CLOUD_INTAKE_ADDITIONS = frozenset((
    'tests/cloud_site_intake_contract.php',
    'tests/cloud_site_intake_wiring.py',
))
CLOUD_INTAKE_REPAIRS = frozenset((
    '.github/workflows/application-tests.yml',
    '.github/workflows/provider-cloud-sites.yml',
    'docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md',
    'site/app/providers/cloud_site_requests.php',
    'site/app/providers/cloud_site_requests_views.php',
    'tests/acceptance_artifacts_test.py',
    'tests/cloud_site_requests_ui_contract.php',
    'tests/provider_cloud_site_requests_cases.py',
    'tests/provider_cloud_site_requests_native.php',
    'tools/acceptance-contract.json',
    'tools/acceptance-reports.json',
))
CLOUD_INTAKE_SOURCE_NARRATIVES = frozenset((
    'docs/agent-handoffs/2026-11-09-codex-issue343-cloud-intake-feature.md',
))
CLOUD_INTAKE_SOURCE_PATHS = CLOUD_INTAKE_ADDITIONS | CLOUD_INTAKE_REPAIRS | {INVENTORY}
CLOUD_INTAKE_ADDITION_SHA256 = {
    'tests/cloud_site_intake_contract.php': '0afa7765917c2f26937884c9baea5559c62aa90e17fea152f735b9ea4eb6e03b',
    'tests/cloud_site_intake_wiring.py': '5b38379d10d44ceb903bb77fdfe7849ad8feecd1cd5585ebe741e21ad959be8f',
}
CLOUD_INTAKE_PREDECESSOR_SHA256 = {
    '.github/workflows/application-tests.yml': '5ddd172b5ede5221799575b5a604e32f43e6415e7cf824fd7cf4c4222f706d09',
    '.github/workflows/provider-cloud-sites.yml': 'bacfcd5d2c98434444c44c769a1f5b737c157db59df1c0b05023541c9141920f',
    'docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md': '090c4fb63ffe1f83936a39e40d0db87966222f84d12fa0ba971b890cff0dbfaa',
    'site/app/providers/cloud_site_requests.php': 'ac5c3c666c916b26081b6f9a5dbef8d588ca942dae9b10164213509014d54af0',
    'site/app/providers/cloud_site_requests_views.php': '59c3d89f76615b182bba3d49d65937b85f88d8033e5ab8ee189409a33070fb64',
    'tests/acceptance_artifacts_test.py': 'aed38862b2b66cdcc01262b2daaf639994d53599733ed9c8b00b259692cccf1d',
    'tests/cloud_site_requests_ui_contract.php': 'e8f3c2d7902c6f9c1b265e8d346fae09557518d6cbcefaa4e9bb75e4e32dbfdc',
    'tests/provider_cloud_site_requests_cases.py': '84e42344a2a03d42cb9bbb5d063b69e601224a0a1c9dce2bc20d19704df73bfb',
    'tests/provider_cloud_site_requests_native.php': '23837d2606bb2a38ad45f513a7fb3aabefcf07f052182ba19fab731f8c17e9a8',
    'tools/acceptance-contract.json': '7ec735ad0c767b6d450df7ffe66355f65d0244e900048343ad61885bfa08abda',
    'tools/acceptance-reports.json': 'e7d9d2b1f66f72109c434c96303611062c7236bfe24cdcf296e5a0080c88a2b8',
}
CLOUD_INTAKE_SUCCESSOR_EDITS = {
    '.github/workflows/application-tests.yml': 1,
    '.github/workflows/provider-cloud-sites.yml': 4,
    'docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md': 1,
    'site/app/providers/cloud_site_requests.php': 3,
    'site/app/providers/cloud_site_requests_views.php': 1,
    'tests/acceptance_artifacts_test.py': 2,
    'tests/cloud_site_requests_ui_contract.php': 1,
    'tests/provider_cloud_site_requests_cases.py': 5,
    'tests/provider_cloud_site_requests_native.php': 4,
    'tools/acceptance-contract.json': 2,
    'tools/acceptance-reports.json': 1,
}
CLOUD_INTAKE_SUCCESSOR_EDIT_COUNTS = {
    '.github/workflows/application-tests.yml': 7,
    '.github/workflows/provider-cloud-sites.yml': 4,
    'docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md': 1,
    'site/app/providers/cloud_site_requests.php': 3,
    'site/app/providers/cloud_site_requests_views.php': 1,
    'tests/acceptance_artifacts_test.py': 8,
    'tests/cloud_site_requests_ui_contract.php': 1,
    'tests/provider_cloud_site_requests_cases.py': 10,
    'tests/provider_cloud_site_requests_native.php': 4,
    'tools/acceptance-contract.json': 22,
    'tools/acceptance-reports.json': 7,
}
CLOUD_INTAKE_CI_SUCCESSOR_EDITS = {
    'tests/acceptance_artifacts_test.py': 1,
    'tests/account_notification_workflow_contract.py': 1,
    'tests/provider_cloud_site_requests_cases.py': 5,
    'tools/acceptance-contract.json': 3,
}
CLOUD_INTAKE_CI_PREDECESSOR_SHA256 = {
    'tests/acceptance_artifacts_test.py': '93a31d163c487716e74f60ed005a2f19e9d7b9dcca66be452f0f26194867599e',
    'tests/account_notification_workflow_contract.py': 'bc4317c73f9e9c1e7b1b99671c7627d9701aa4cc9c0ce58f35cd7fb228bc4fcf',
    'tests/provider_cloud_site_requests_cases.py': '66e9c9c8febe28604b93b3eaaee12ce4a5367fbf2c555597f1db54553c1eb0ed',
    'tools/acceptance-contract.json': 'e5ebee48faa0facf006106c0900f34d9d29b90309e8237dcdf54125f7fc124f5',
}
CLOUD_INTAKE_CI_EDIT_COUNTS = {
    'tests/acceptance_artifacts_test.py': 8,
    'tests/account_notification_workflow_contract.py': 5,
    'tests/provider_cloud_site_requests_cases.py': 10,
    'tools/acceptance-contract.json': 22,
}
UX09_PROOF_SUCCESSOR_EDITS = {
    'tests/account_notification_workflow_contract.py': 4,
}
UX09_PROOF_PREDECESSOR_SHA256 = {
    'tests/account_notification_workflow_contract.py': '8e119d1f951a9b12f128f769ae2a2762e5dc0cd703a0516c6467cfea2881cf4a',
}
UX09_PROOF_EDIT_COUNTS = {
    'tests/account_notification_workflow_contract.py': 9,
}
UX09_SOURCE_RECEIPT = 'docs/UX09_PUBLIC_IDENTITY_SOURCE.json'
UX09_BASE = '4d10251e4f9c7ad85f42a5b49c3c23344057d66e'
UX09_BASE_TREE = '54d1fc56bbe27ab7f5665f47323eb13d88d4a2e2'
UX09_SOURCE = '6000fb349136956dadc2e66ab47b779ad920accc'
UX09_SOURCE_TREE = 'd570271ba597e7c6286b8cb1afbb538598f13899'
UX09_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'site/app/clients/accounts.php',
    'site/app/clients/identity.php',
    'site/app/settings/database_schemas.php',
    'site/database/schema/base.php',
    'site/install/actions/deploy.php',
    'site/install/inventory.generated.php',
    'site/portal/controllers/actions.php',
    'site/portal/controllers/router.php',
    'site/portal/views/auth.php',
    'tests/acceptance_artifacts_test.py',
    'tests/browser_acceptance.py',
    'tests/database_schema_registry_policy.php',
    'tests/database_schema_view_policy.php',
    'tests/database_update_cases.py',
    'tools/acceptance-contract.json',
    'tools/acceptance-reports.json',
))
UX09_ADDITIONS = frozenset((
    'site/app/clients/public_identity.php',
    'site/database/migrations/client_identity_migrate.php',
    'site/database/schema/client_identity_schema.php',
    'tests/client_identity_cases.py',
    'tests/client_identity_native.php',
    'tests/client_identity_policy.php',
))
UX09_SOURCE_PATHS = UX09_MODIFIED | UX09_ADDITIONS
REPAIRS = frozenset((
    '.github/workflows/consolidation-after-backups.yml',
    '.github/workflows/rc126-test-package.yml',
    'docs/CLIENT_PORTAL_ROADMAP.md',
    'docs/LICENSING_WORKSPACE_INTEGRATION.md',
    'docs/RELEASE_WORKFLOW.md',
    'site/admin/controllers/catalog.php',
    'site/admin/controllers/studio.php',
    'site/admin/views/catalog_import.php',
    'site/admin/views/studio/clients.php',
    'site/admin/views/studio/job.php',
    'site/admin/views/studio/license_overview.php',
    'site/admin/views/studio/licenses.php',
    'site/admin/views/studio/upgrade.php',
    'site/api/bundle-download.php',
    'site/api/software-download.php',
    'site/app/activity/events.php',
    'site/app/billing/catalog_browse.php',
    'site/app/clients/accounts.php',
    'site/app/clients/files.php',
    'site/app/clients/identity.php',
    'site/app/licenses/financing_offers.php',
    'site/app/licenses/software_grants.php',
    'site/app/marketing/proposals.php',
    'site/app/notifications/events.php',
    'site/app/notifications/feed.php',
    'site/app/plans/records.php',
    'site/app/staff/accounts.php',
    'site/app/support/ticket_choices.php',
    'site/app/support/ticket_notifications.php',
    'site/app/support/ticket_reads.php',
    'site/app/support/ticket_views.php',
    'site/app/support/tickets.php',
    'site/assets/css/admin.css',
    'site/assets/css/premium.css',
    'site/assets/js/site.js',
    'site/database/schema/base.php',
    'site/includes/layout/admin.php',
    'site/includes/layout/notifications.php',
    'site/includes/layout/proposals.php',
    'site/install/actions/deploy.php',
    'site/pages/home/body.php',
    'site/portal/controllers/actions.php',
    'site/portal/controllers/router.php',
    'site/portal/views/auth.php',
    'site/portal/views/financing.php',
    'site/portal/views/software.php',
    'site/portal/views/tickets.php',
    'site/portal/views/workspace.php',
    'tests/acceptance_artifacts_test.py',
    'tests/backups_acceptance_wiring.py',
    'tests/browser_acceptance.py',
    'tests/catalog_storefront_checkout_cases.py',
    'tests/client_authority_native.php',
    'tests/cloud_site_requests_wiring.py',
    'tests/finance_initial_enrollment_native.php',
    'tests/hosting_billing_options_cases.py',
    'tests/hosting_billing_options_wiring.py',
    'tests/hosting_catalog_plan_setup_cases.py',
    'tests/hosting_checkout_http_browser.py',
    'tests/hosting_checkout_http_cases.py',
    'tests/hosting_checkout_http_fixture.php',
    'tests/hosting_checkout_native.php',
    'tests/hosting_purchase_inbox_cases.py',
    'tests/installed_owner_authority.php',
    'tests/installed_owner_authority.py',
    'tests/installed_owner_authority_test.py',
    'tests/license_financing_cases.py',
    'tests/license_financing_native_cases.php',
    'tests/licensing_action_boundary.php',
    'tests/licensing_workspace_browser.py',
    'tests/licensing_workspace_contract.php',
    'tests/organized_update_cases.py',
    'tests/owner_browser_forms.py',
    'tests/owner_browser_forms_test.py',
    'tests/proposal_version_binding.php',
    'tests/proposals_native.php',
    'tests/provider_catalog_import_batch_views.php',
    'tests/provider_catalog_import_native.php',
    'tests/provider_directory_listing_controls_cases.py',
    'tests/provider_force_ssl_controls_cases.py',
    'tests/provider_schedule_cases.py',
    'tests/provider_workspace_browser.py',
    'tests/provider_workspace_fixture.php',
    'tests/provider_workspace_policy.php',
    'tests/provider_workspace_render.py',
    'tests/release_cases.py',
    'tests/reset_template_acceptance_contract.py',
    'tests/service_plan_cases.py',
    'tests/service_plans_native.php',
    'tests/software_admin_routing_contract.php',
    'tests/support_tickets_native.php',
    'tests/build_guards.py',
    'tests/ticket_cases.py',
    '.github/workflows/application-tests.yml',
    '.github/workflows/software-webhook-contract.yml',
    'site/app/billing/authorize_net_webhooks.php',
    'site/app/billing/software_subscription_settle.php',
    'tests/account_notification_workflow_contract.py',
    'tests/admin_session_no_reentry.php',
    'tests/payments_cases.py',
    'tests/payments_native.php',
    'tests/software_webhook_contract.php',
    'tools/acceptance-contract.json',
    'tools/acceptance-reports.json',
    'tools/build.py',
))
# UX-06 extends the reviewed repair set with the exact master-portal source
# and qualification paths changed from the immutable repair checkpoint.
REPAIRS = REPAIRS | frozenset((
    'docs/MASTER_CLIENT_PORTAL_1.26.1.md',
    'site/admin/controllers/support.php',
    'site/admin/views/auth.php',
    'site/app/clients/master_portal.php',
    'site/includes/layout/admin.php',
    'site/portal/controllers/router.php',
    'site/portal/views/auth.php',
    'site/portal/views/financing.php',
    'site/portal/views/software.php',
    'site/portal/views/workspace.php',
    'tests/master_portal_installed_browser.py',
    'tests/master_portal_native.php',
    'tests/master_portal_policy.php',
    'tests/owner_release_apache.py',
))
# UX-07 extends the repair set only with existing files from the immutable
# repair checkpoint. Its seven genuinely new source files remain disjoint.
REPAIRS = REPAIRS | DATABASE_WORKFLOW_REPAIRS
# Release-asset delivery repairs only the four additional existing fixture,
# workflow and test paths; Application and its guard are already retained repairs.
REPAIRS = REPAIRS | frozenset((
    '.github/workflows/license-financing-browser.yml',
    '.github/workflows/software-release-125-upgrade.yml',
    'tests/prepare_legacy.py',
    'tests/software_release_125_harness_tests.py',
))
# The staff-invite observability successor changes only this retained browser
# fixture. Its exact predecessor remains reconstructed for component checks.
REPAIRS = REPAIRS | frozenset(('tests/staff_cases.py',))
REPAIRS = REPAIRS | CLOUD_INTAKE_REPAIRS
# Exact-head Application exposed fixture-only mutations around previously
# confirmed hosting refreshes and the shared browser actor budget. These are
# ordinary reviewed integration repairs, not part of the immutable cloud-intake
# feature source range.
REPAIRS = REPAIRS | frozenset((
    'tests/cloud_assignment_fixture.py',
    'tests/cloud_assignment_fixture_test.py',
    'tests/provider_cloud_providers_cases.py',
))
DATABASE_WORKFLOW_SUCCESSOR_EDITS = {
    '.github/workflows/application-tests.yml': 6,
    'tests/acceptance_artifacts_test.py': 5,
    'tests/account_notification_workflow_contract.py': 4,
    'tools/acceptance-contract.json': 17,
}
# Session narratives may be prefixed by the integration owner. They are not
# executable/source receipts; every other historical document remains bound.
NARRATIVES = frozenset((
    'AGENTS.md', '.clinerules/01-cajunwebpro-continuity.md',
    'CHANGELOG.md', 'PROJECT_STATUS.md', 'SESSION_HANDOFF.md',
    'docs/AI_AGENT_CONTINUITY.md',
    'docs/agent-handoffs/2026-11-08-codex-release-stack-integration.md',
    'docs/agent-handoffs/2026-11-09-codex-issue343-cloud-intake-feature.md',
    'docs/agent-handoffs/2026-11-09-codex-issue343-cloud-intake-integration.md',
    'docs/AGENT_COORDINATION.md', 'docs/DECISIONS.md', 'docs/DEVIN_INTEGRATION_QUEUE.md',
    'docs/RELEASES.md', 'docs/ROADMAP.md', 'docs/SESSION_LOG.md',
))
ADAPTERS = frozenset((
    'tests/tickets_integration_contract.py',
    'tests/provider_browser_fixture_provenance.py',
    'tests/account_notification_contract.py',
    'tests/account_notification_fixture_provenance.py',
))
# BEGIN GENERATED COMPOSITION IDENTITIES
ADAPTER_SHA256 = {'tests/tickets_integration_contract.py': '2e17ee99dc98038e68fc00b7fcb532f5c42ad4933c0a4e018a88a81afab93fcd', 'tests/account_notification_contract.py': 'f4153275f34e3370f269c21e257501e537c4bd2f8f62fb72b9037ac645a2fef2', 'tests/account_notification_fixture_provenance.py': '1ce3822b9963ff83de10bafbd33759211a08a14b65a7d0587a4d6518523e9828', 'tests/provider_browser_fixture_provenance.py': 'ed680a7fd31ff677ebc173b119c13f43a736f9fefaae0089699572a659da9386'}
REGRESSION_SHA256 = '2cfd5791ce838722753f6601db2db78e4ae36442a99e73fb66fa3d0dbfe399b0'
# END GENERATED COMPOSITION IDENTITIES

# BEGIN GENERATED INTEGRATION REPAIR IDENTITIES
REPAIR_SHA256 = {'.github/workflows/application-tests.yml': '5db94d83c68397a964baae19329fa7522fe4d8e2204ca3de0270c40a65ab0ef6', '.github/workflows/consolidation-after-backups.yml': 'd3e4cf7343cb1454917c691af8acb7b7456e704e735bd7fa5695a646c19279b5', '.github/workflows/rc126-test-package.yml': '7902b61c4bda1946e9d768b9d793afab1b832c2de8fa1d667c792f1caf739ba3', '.github/workflows/software-webhook-contract.yml': '4926777bcb6c1b2bde6260a5904a191af89ddebab505ea6d29e5d04b41450142', 'docs/CLIENT_PORTAL_ROADMAP.md': '8e31a75b47ea79d8059bbaf9a8c530426c47a5299bd8fb6b65806d4ba42c75bd', 'docs/LICENSING_WORKSPACE_INTEGRATION.md': 'de5df463294abf413b918f70b57da80040cb6b23b108a36f9e53b47b2edefea0', 'docs/RELEASE_WORKFLOW.md': 'ae72fcdf222802c81f1769eb42c3c7e17a61afdcd26ae650c01d788ed780bae6', 'site/admin/controllers/catalog.php': 'ed9aba12ab012011a34b59c6cd0ea2b1c356df0d51b01ffa67ef24484672761d', 'site/admin/controllers/studio.php': 'b19f05cc44f88489523c53079f229b46c06419b27c68b1b6c367be01f92b4b26', 'site/admin/views/catalog_import.php': '12fd6799e7f2c1805c245b4ba969fe291a70c5944a93707dc499ca027e9a53eb', 'site/admin/views/studio/clients.php': '5d42172fb9767e187564c4af59d75c519f5f2c942593f5c59d037b98358370b3', 'site/admin/views/studio/job.php': '87f17d26b213db3ae9b03d343f7a5afc4e215691b34d7507f206e372d9f92f5e', 'site/admin/views/studio/upgrade.php': 'ab60c55217f08d6fc021cea3a9e996feed1c1e289cac6b1f5717b9706d8c9239', 'site/api/bundle-download.php': 'abd65140460543c13b99d3c20e5454306664f084d505ea57b90e5752b17aa4bf', 'site/api/software-download.php': '702c86f6f7ba0bedcb9fc464efb6418701b94fb044bd5718491095d205cc57dd', 'site/app/activity/events.php': '011a2fc5ce9dc93cd414e4ed637157d1c772e56f321d3fd8e9c0ba18a72508e4', 'site/app/billing/authorize_net_webhooks.php': '4d79e37255166518fa64f7e86b21d48b2d8ea933a07da08f8d65c4b45b21d8de', 'site/app/billing/catalog_browse.php': '82b296b1fa8b48d9e29e30e580a290e020803353c9848284eb753287a8f61c38', 'site/app/billing/software_subscription_settle.php': 'cb9f7486e57079d82ca786a2c04e71a98edd95b42d1d8d5ab5e23784ffcd17a2', 'site/app/clients/accounts.php': '9535abe5b5688a82e630c5fc84d58b661c882082eb8358de5b1b9f928ea0e5cd', 'site/app/clients/files.php': 'e8bbd51f4c4b6baf518ff4ae7dab866f6b46bb59a3f434ef78f51df19f360db0', 'site/app/clients/identity.php': '61df7e8e02ca3362a27dfcdad7841df065d201d0d3dcc25a900f71ccdd5cecc7', 'site/app/licenses/financing_offers.php': '2b87d8cfc93cd31594eba6143390413d326e7f637393b22e4b243de869ddd447', 'site/app/marketing/proposals.php': '41f5e7afcc6d680ed801bcc685b3e5c5ac8450263bd945c30b7d84f8cf534bcb', 'site/app/notifications/events.php': '8a55bb62ebe6b8d2a8ad03e60a93610d3b48af2bbab8a64eee8895ed0decbce4', 'site/app/notifications/feed.php': '1013c10b3452d903a49e772dd326e6ffdf0d0b9306ec5302bfd4813f27b5725c', 'site/app/plans/records.php': '985e2472db522bb70ccaa0205f95f21e4e5030bf5596b091864617f91c1e4b04', 'site/app/staff/accounts.php': 'b7d58ff6866f6ceae240f1325555d230b4a24ab291dd1dc01469031bdf8465ec', 'site/app/support/ticket_choices.php': '4e91ccd9764e814f3a4d9e6e8902632a148e6fd6a4e72e6869c1c4b216081167', 'site/app/support/ticket_notifications.php': '058f41c8c146ebe82cf72bad8fa7e9bc754e1e58b791d52e9169f0795fd48867', 'site/app/support/ticket_reads.php': '5e824bdfbc9a37fe77110464d670ae58b0ea7ccb7bcd9c635f7a449082a06868', 'site/app/support/ticket_views.php': '313d6bdfe400ee63be9534a0ccf1e014a14db71922fe7e8af68de5adf381100b', 'site/app/support/tickets.php': '1e9183db114a24a648ccdc7b66514ac5ab94066925237be22bf8abab0b8b5dd1', 'site/assets/css/admin.css': '861d805127a37b750eed5dab06320d98583568d762b688af56415884410ecab5', 'site/assets/css/premium.css': '790097578fe9219d52a8581e3aae761c4646b5145590ae4b6e15d74a690d1743', 'site/assets/js/site.js': '3da42a595a67d5b8fea689785267353f0424a90ac03bb9d80c4d8e6a160cef16', 'site/database/schema/base.php': 'b0de5962e671267dba3dde791a9f4bf90b2cfe3967d803038d663562a51c4cea', 'site/includes/layout/admin.php': '650149a9b80e1158b17deed975b2ae582d81910ea6258627a395812a277ba4b6', 'site/includes/layout/notifications.php': '222d1869e75dc47415617814c508f5132cca130291d7a2642609b4c05e705bb0', 'site/includes/layout/proposals.php': 'e4ed91ec5a436d05f0febbe4ab340f317a6205de9a6a5f5c47eb830daa223e17', 'site/install/actions/deploy.php': '1c5cd699653e278cc2e56bf336039b3765fc00e5c220040068e33487b426917a', 'site/pages/home/body.php': '77c19a7ae72842d9bb1a8cfe73d25de8c6c46f0f845adc1dd1266e210e11962c', 'site/portal/controllers/actions.php': 'e0bfb2c185492d09b43747f9cda83ad28f67c41211b126c60b6081bbe1de4226', 'site/portal/controllers/router.php': '3d00beebce20b6c700232e091b60ecee9766c34751a0496ec5dc1806a0720a63', 'site/portal/views/auth.php': '4d0a9b73b32ac6230f2ace6c0b822107ab0fd0d2a015c46023df9acf5aec4b95', 'site/portal/views/financing.php': '37f385593cb7fe34beb5e24020602b9c27b178062b6e704d86f72945356d045c', 'site/portal/views/software.php': 'a36d3e71f9abfb327e3ed6707c16e0a7dcbe811e735358fd7c8a3301de7524fc', 'site/portal/views/tickets.php': 'e584022a5f53b56c05623176a854ba8f7e160a1834cac056602072ccd8a80767', 'site/portal/views/workspace.php': '3559010a49bcbbacfe0aac0c70d440a6f0de18df262372a8ffb53b4e10c28e61', 'tests/acceptance_artifacts_test.py': '9c1da1c8bafbc2febe87d66d5c1d6368e8e0c159e848fd54a3a64ae2108763dc', 'tests/account_notification_workflow_contract.py': '9f32e3e4e6e64d0b36a8afc9da2d7ae09f1c3a3c995e9246671cb484447ac8c1', 'tests/backups_acceptance_wiring.py': '525486323025784b00ffa7d347f6663a6a5aa6e86574830c77ba5b18f1116741', 'tests/browser_acceptance.py': '3b8f81333b9712eb8aa3a5c6bb4e5f28b4ab64687933e7f333016d6a2e053f80', 'tests/build_guards.py': '7df4f04b58418ef27d33ee53c175cf4eb61e2348988f0c4b79909a063ad049f2', 'tests/catalog_storefront_checkout_cases.py': '843048d926ca4187c9a2f60a3a360b43e46311f336b5c32573f4f2af76778213', 'tests/client_authority_native.php': 'af236ef91f9bcb33b219e8f8d34774ed56c652ef8dd00f69a2f97115d3a7fab9', 'tests/cloud_site_requests_wiring.py': 'ee50481194217b3c6f669347f6fd64275ef87b8b07b800939f2325af51eca27e', 'tests/finance_initial_enrollment_native.php': 'f61066d00ae989965130c0f8965bb0300c45ab45bbe220bc1b95ff6029020f38', 'tests/hosting_billing_options_cases.py': '4ac8630913e7812e11f536bc8663a2dd06891f49e5c2c4c1ed119ae713334cc5', 'tests/hosting_billing_options_wiring.py': 'b59b8e4bc1a017abdb77fc9fb309e98ff13029bf652d4e82ae0e5ac2aee274a0', 'tests/hosting_catalog_plan_setup_cases.py': '51bd299de65d61e40f2ec34bf7deccad452e14281761e0a0b2a38b54ca281508', 'tests/hosting_checkout_http_browser.py': '507c9ff9a450a9a09f4d8750711d0bd363232474fa13e4b14899692b18f1b5aa', 'tests/hosting_checkout_http_cases.py': '14401d06c4295f5bca794db60d875689c0de7de7c231fcac390fe0e72346e509', 'tests/hosting_checkout_http_fixture.php': '77d69fab3506b1e6a903318efdacfe168c1afb047b63bc7b7fe461099dce610d', 'tests/hosting_checkout_native.php': '7690790bc095fca8f7690518390ab36e3f8ec83d53b40f10e4161f823f0aef97', 'tests/hosting_purchase_inbox_cases.py': 'c09947207bb3b996f325e630f0a57b419369626d2be5808747a30ceca75dfe89', 'tests/installed_owner_authority.php': 'bbc48036af794564a1b39b54eb218d3390f94a65861d355d5e4ccef4c7c69863', 'tests/installed_owner_authority.py': 'a9511d52459ac1a9d50d62b646198ab29258718491f0c53f339bf32d7f0e3b71', 'tests/installed_owner_authority_test.py': '48e1930aa6d3e0d8ea7d02a5001718e68c15463fb9b9c62e6cfb2513b69c8101', 'tests/license_financing_cases.py': '7c23b44721f97a718d99b06e449dbd379bfab856ec7452a9d193ef7b031ec3c1', 'tests/license_financing_native_cases.php': '230a6a2ef6d39fcecff1a028b3941e7ef3e87e9154ceb17ab82f75d69cc5ff0d', 'tests/licensing_workspace_browser.py': '3607b7c09f72c508e3bdc8ff72cd51d2cd7f1f764a0cc4a377d29ef5fdb46660', 'tests/licensing_workspace_contract.php': 'b77832e716a06d29d050d9e731bda48e60dfed15c92f48383acb482118e5ba04', 'tests/organized_update_cases.py': '9ffe696d822add3a7013e72cb6a99f5bbe44994d5fc83ebe9d2d8c5d15322674', 'tests/owner_browser_forms.py': 'abb07881c43514fd6ac9241700fb2fe97a881095b122d3d092a4ef82924616a3', 'tests/owner_browser_forms_test.py': 'ae4cac11289a97e2d20cf4755ed924f7f438e01d8c5550994ff7b86a144a4b54', 'tests/payments_cases.py': 'b5b4f3e9e3fd23474f298832a22a14672c0f61e50d55b0c57b21d4c0e06755e6', 'tests/payments_native.php': 'e732ca439d3bb460cc530714dcd03b70f332282c2681f75ed2324a114e3c6316', 'tests/proposal_version_binding.php': 'e2b01d643092b15eebc8cc8b2b22aa5b4eb22a4bb30bce4b970249c09c8bdbbd', 'tests/proposals_native.php': 'a3ab274887a5f4b6b65fd1bdef447adee9fbd820d467402f81bdc4ccddf8611d', 'tests/provider_catalog_import_batch_views.php': '1205def0109cc6aa2ef8bec65186bdb50c185040b4596a2808eca4f9ad59b1f3', 'tests/provider_catalog_import_native.php': '09daadc349d961a472eb15e14d07f08364498add25200b580587a9ffcfa9c37e', 'tests/provider_directory_listing_controls_cases.py': 'de983d82a43bfe75c1f01fa443be944451bd1fdba16ef9c77e4c0c161096f25f', 'tests/provider_force_ssl_controls_cases.py': 'a764c99c984514afb9b9d6ac129fd272ead523a274ab9c8ebd29e6c6436e6d68', 'tests/provider_schedule_cases.py': '2a0bbd5fd2a67195a17242339810e2dee2196f2d212b16835e314024578862ca', 'tests/provider_workspace_browser.py': '07ef304967ed4962e0025563765fd37810d795b0f7180357100a7544dac86ba2', 'tests/provider_workspace_fixture.php': 'b339fe8be82869fe321e20b3061b0ceb95e668a3e324e8fc497b8c9c4aa0ecf1', 'tests/provider_workspace_policy.php': 'e7c727c040693d62d9ef42ac869cd0433c417e8c6e6db3587aeed49d89c9021b', 'tests/provider_workspace_render.py': '8f70ecd0aca67c31923a916af8126f79d20466444878b2145813f20aecd97673', 'tests/release_cases.py': '11669b9afbfa66a95de06e0c9617d01a4d80841550ff391873758b02b17aa3da', 'tests/reset_template_acceptance_contract.py': '7d9d58a4fafacddd8b3d9d62ffe5222a4099e297c8cf05aaf00e54e2f52bf3a9', 'tests/service_plan_cases.py': '0d4a34bff3eacbe3a8051315e04953cdbfe7be1438106eddfa8707877425e363', 'tests/service_plans_native.php': '45a2c1d888bee7b694b06f6f873fe0dc6874a4c3b0234d937e7d70abbc927058', 'tests/software_webhook_contract.php': '570e44c6d6f2369d1793a885033e95151e28ed1dcdbc21b996a020e980665c2c', 'tests/support_tickets_native.php': '2128bba21f9d53410e5077dfe5566a988bbe14a27bdd270298d52baf5fc58e19', 'tests/ticket_cases.py': 'dc3bf485e8473588661b8bfaeb88ef8caab87281d5111e23ed987957af4938bf', 'tools/acceptance-contract.json': '068d0aac2cd983215bd90decfa396fbbf1637813836bfe7c44b580adb99c3f2c', 'tools/acceptance-reports.json': 'c50ee66cba8731b60877687fdc18d76146e67b8e3ffe7e532ad09d89bd52fa91', 'tools/build.py': 'bf76464dc1e736b76a892a5662b65134eeb8a558f1c2f39e0359f39463725ad4'}
REPAIR_SHA256['tests/admin_session_no_reentry.php'] = '8869b683a3996819428fa13d87dc3f94841cb53121838317d8de32a4ee190887'
REPAIR_SHA256['tests/license_financing_cases.py'] = '40dcbea1d3347c03e15cc1c5fa450c0fb8757ed1a6a2b17c8a6fa09b152c0d2f'
REPAIR_SHA256['tools/acceptance-contract.json'] = '557968d4a2889f0955fcc16e357f488285281bb6a6a1d193fd9bb7077052c030'
REPAIR_SHA256['site/admin/controllers/catalog.php'] = '586e03f6637fb952be7f095e28366e080ff83dec950681d888a1d2c82abd2ee2'
REPAIR_SHA256['tests/catalog_storefront_checkout_cases.py'] = '70d146a07a0fe22e5628873954a87dbd69ab9741d29a291dc0d51f3e3bc11112'
REPAIR_SHA256['tests/hosting_catalog_plan_setup_cases.py'] = '91f98263a8e5716129ab2d11012b2d3f9672cb96e861b144d1d46272929b85d0'
REPAIR_SHA256['tests/hosting_billing_options_cases.py'] = 'd4959ae1033a045364cc933a75e841680ee4978a4af1dc08c9855146d8ed6175'
REPAIR_SHA256['site/app/billing/catalog_browse.php'] = 'da7ba2410622a0b74f8c377e9ac7a14dd468ce708803c711cdb334989cc8c6da'
REPAIR_SHA256['tests/provider_workspace_fixture.php'] = '68e2b0f3c10b5a96c31073a3fc0c37d0ac9b97c935d47a72fc495807da45137f'
REPAIR_SHA256['tests/provider_workspace_policy.php'] = '65ba05aeefaeb62c142deac9526c09bc92149c30f15574230aacab2f89145297'
REPAIR_SHA256['tests/provider_workspace_render.py'] = '356a5121d43dccb74912e17573cfdca2e5d71db8cd0adfede891a6404f0bdf38'
REPAIR_SHA256['tests/provider_workspace_browser.py'] = '024e57e59dec8c7118a43c547076d6d020befe5a6bd266f15f9458a4ef0b5ffe'
REPAIR_SHA256['site/admin/controllers/studio.php'] = 'df1d93ddd9ca77e2fa9887c55bb534bc24859f4717d15adcf2b68322bc812c15'
REPAIR_SHA256['site/admin/views/studio/license_overview.php'] = '8bf6a301d845d59acb4c796ba25847fd2577799f5d441fff817ce42c5fc68271'
REPAIR_SHA256['site/admin/views/studio/licenses.php'] = 'b3ee5e1537ef48e36cd099cbcaa23378934123481494b00221929aed0aa7b1a2'
REPAIR_SHA256['site/app/licenses/software_grants.php'] = 'bd3e5dbf510e5e2985f9656694d66883a06bd8c4d11cebc26e454bd47b79407a'
REPAIR_SHA256['tests/licensing_action_boundary.php'] = 'c80a461c467a5e2c4d2ca503890cadc39706c4c3baff28cd2b9bd8074a6b611b'
REPAIR_SHA256['tests/licensing_workspace_browser.py'] = 'ecc18bac36886b8697ff5c38e05f0631738ee35fbed35ebaced8b5b761ab2fdc'
REPAIR_SHA256['tests/software_admin_routing_contract.php'] = '1609dda83ce374bbeba2b250763ba4eb22788baa23c20b405647fcb91757c90c'
REPAIR_SHA256['docs/MASTER_CLIENT_PORTAL_1.26.1.md'] = 'ac669c0f6f4e627481f5a65a6fc308759ea01251c023678e616d154aef4bafb9'
REPAIR_SHA256['site/admin/controllers/support.php'] = '035e6ab10f041f5920b13604ca979e0202262980046277a41fb62eed3ce6ef1e'
REPAIR_SHA256['site/admin/views/auth.php'] = '810d3818d3e2faf1e01d601db135089d6041199ed80ec23d0c166eab5dd7fb76'
REPAIR_SHA256['site/app/clients/master_portal.php'] = '1e801456fc903bf77c497681d593ccddb24f775a56fcde751f9d000727d0b90e'
REPAIR_SHA256['site/includes/layout/admin.php'] = '084b9ab7e75d0b57c546dbd225321dbe3762dd9ec44a8fe9e0fafc1138daf9e7'
REPAIR_SHA256['site/portal/controllers/router.php'] = 'd7bd12d1c2c53f8531900ae6b75d7068a8f0b50720029346d56416f0bd34ea99'
REPAIR_SHA256['site/portal/views/auth.php'] = '5195c1e288d3642f52f541e3e361ea5c3dc746a0ea25db9cade9d2fb45199942'
REPAIR_SHA256['site/portal/views/financing.php'] = '47532bc87a409a47b335d8f504b8d76bd19fbd5e205ec896bf46a1a8b51995b9'
REPAIR_SHA256['site/portal/views/software.php'] = '9b937b8d2adfdb2da7e718dd7053eb63a67b2e9078276bd55d7d5e138b5e91b0'
REPAIR_SHA256['site/portal/views/workspace.php'] = 'cab382dda952f2b71f91d81dbbcea919c71a71a637165577de28baa9965afd73'
REPAIR_SHA256['tests/master_portal_installed_browser.py'] = '2256945bd7ddb4a083ef7ffd4ad676145866730f331ec7f9a513e3ee57fb9003'
REPAIR_SHA256['tests/master_portal_native.php'] = '896b9cbfebb19de382892c189ab8d838c4cfb5b43745b0c1a186a737a5c3a213'
REPAIR_SHA256['tests/master_portal_policy.php'] = '95de8026903492bf4b9f2fb69d96ac999436dcdd96f91792dbe839109f13a5eb'
REPAIR_SHA256['tests/owner_release_apache.py'] = '0d074273e99e89af22b6f865355fc9dfa09f90f122eead05792390e8ca173a87'
# BEGIN GENERATED DATABASE WORKFLOW REPAIR IDENTITIES
REPAIR_SHA256['.github/workflows/application-tests.yml'] = '4a5152120f702a374ab1706fd9cd0aec8833fea13b0aa12a9b5c7ac58f57c81f'
REPAIR_SHA256['site/admin/controllers/studio.php'] = '77501d58a9c577587fd8052025331c882d3b1bc4bee660bbe507a20cf507ff99'
REPAIR_SHA256['site/admin/views/studio/upgrade.php'] = '37fe5df5ca0bf48e0d2843f7dbaf07412c8b8a345daa53879573da370c352e49'
REPAIR_SHA256['site/app/staff/accounts.php'] = '149eb237a32b304b19edc5fbd3c556978834c6de22f00247ccd9609755df4ea6'
REPAIR_SHA256['tests/acceptance_artifacts_test.py'] = '10c42783e6be677e2a4d630adbf4583f53bf1065b256706c6fb3517d3a135fa5'
REPAIR_SHA256['tests/account_notification_workflow_contract.py'] = 'bc4317c73f9e9c1e7b1b99671c7627d9701aa4cc9c0ce58f35cd7fb228bc4fcf'
REPAIR_SHA256['tests/admin_20i_populated_upgrade.py'] = '23980a8fb6da772c101a4a33e1db5e3d9e77768a8f75aeac377d6b509dd1f8a2'
REPAIR_SHA256['tests/browser_acceptance.py'] = '76cc1bebf0cff397a46bb920db598d25669d6a205a22cc8467d23abfc01a86a1'
REPAIR_SHA256['tests/owner_update_session_policy.php'] = 'b12346f03f23d130bd32f9c03fe862abb0fe75c8cdff5a7d26ded960290df6b5'
REPAIR_SHA256['tests/release_cases.py'] = 'bef2874006b64ba39fa063c4180de164ca25727c3081381c069a22f9368ec282'
REPAIR_SHA256['tests/service_plan_cases.py'] = 'aed974cf60079d8268d3090e9e9f9876c2d69d737b09e575e1533bd823b67ec9'
REPAIR_SHA256['tools/acceptance-contract.json'] = 'a4f6d568865513547c86f20269176091ce92c91b787defa1be99a93796163a3d'
REPAIR_SHA256['tools/acceptance-reports.json'] = 'e7d9d2b1f66f72109c434c96303611062c7236bfe24cdcf296e5a0080c88a2b8'
REPAIR_SHA256['tools/update/owner-update.php'] = 'f151c1f042841853bfd36cc60ec0629cd8cc1b8d4c0da5af57562d4239e9cec5'
# END GENERATED DATABASE WORKFLOW REPAIR IDENTITIES
REPAIR_SHA256['tools/build.py'] = '85d3a6762852afa633c047ec7c138b8afe7cb0df08724034ba6a0d39d6065c77'
REPAIR_SHA256['tests/build_guards.py'] = 'b85c6c2de743b6df813362c3714eab02dbe70a63f0e5a9d71e74ebd9d873a1c8'
REPAIR_SHA256['.github/workflows/application-tests.yml'] = 'a4ece2460ea61f9fa9ed8f77c80bba9956c0eeea18b1232ef2a0a28972a3d599'
REPAIR_SHA256['.github/workflows/software-release-125-upgrade.yml'] = 'e66c3724bbc0f5ce0d65f7b711037c2b9f53108a43097b061c465b4b561ba4eb'
REPAIR_SHA256['tests/acceptance_artifacts_test.py'] = '25723015f3b3578cb1e0334c125e93f7b40da827d46458154b6c97607e454684'
REPAIR_SHA256['tests/software_release_125_harness_tests.py'] = '34efecf23833da592141292e0c1a12c6bbff3dd165a07749057afe60cf0b458e'
REPAIR_SHA256['.github/workflows/application-tests.yml'] = '5ddd172b5ede5221799575b5a604e32f43e6415e7cf824fd7cf4c4222f706d09'
REPAIR_SHA256['.github/workflows/software-release-125-upgrade.yml'] = '95a66a528da11ad358a9e9fba711afb4461d4a1d9fed25fed78908e0647fe51d'
REPAIR_SHA256['tests/prepare_legacy.py'] = '66e1620ce25ea6f7bfc5c6a0c8a66690d26df2cda15bae9f430da8bcd716bb60'
REPAIR_SHA256['tests/software_release_125_harness_tests.py'] = 'aed628120c02169b833042992b3bd2bee520870029ed0ddfdfc91623c9b18a0d'
REPAIR_SHA256['.github/workflows/license-financing-browser.yml'] = '46e929ef2652ff8f6ecb2cf3ccdfc21289f4fb32d449ede6045303c88a040abc'
REPAIR_SHA256['tests/software_release_125_harness_tests.py'] = 'd5f802cc6774179c35fafab703951d89633fed2f22377ef291d5ab31f79546d3'
REPAIR_SHA256['tests/cloud_site_requests_wiring.py'] = 'a6cde5e837c572a9c2bf3fa5ea6572fc4f506146ac35be291b8189c6ee23a36e'
REPAIR_SHA256['tools/acceptance-contract.json'] = '7ec735ad0c767b6d450df7ffe66355f65d0244e900048343ad61885bfa08abda'
REPAIR_SHA256['tests/acceptance_artifacts_test.py'] = 'aed38862b2b66cdcc01262b2daaf639994d53599733ed9c8b00b259692cccf1d'
REPAIR_SHA256['tests/staff_cases.py'] = '9446930e4f511848dfa1de1be20b20569b351d5e77990336fc733fac05fcc0ee'
# BEGIN GENERATED CLOUD INTAKE REPAIR IDENTITIES
REPAIR_SHA256['.github/workflows/application-tests.yml'] = 'c3a2cf408833a8dcf5ec661a8e5e89b8201bb49019885cb97ddfeb56dc0da185'
REPAIR_SHA256['.github/workflows/provider-cloud-sites.yml'] = '36a950242aaef55ad7f69a69e264593487b4535ec3fd738cd8a6f4e355fd9b81'
REPAIR_SHA256['docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md'] = '2272a5666ca8a218074c6334e03bb078a987c028d9513b9fc058c768dd50e320'
REPAIR_SHA256['site/app/providers/cloud_site_requests.php'] = 'aaafdf5210e00e92a946b19186e5e72f2c0025f0277da38c368d844b5f49e84d'
REPAIR_SHA256['site/app/providers/cloud_site_requests_views.php'] = '0a5f61bee9af8c9ca805bdfc0bf820c34ea70eec15aed6559428b6fb93bb5539'
REPAIR_SHA256['tests/acceptance_artifacts_test.py'] = '93a31d163c487716e74f60ed005a2f19e9d7b9dcca66be452f0f26194867599e'
REPAIR_SHA256['tests/cloud_site_requests_ui_contract.php'] = '01016f139e72c31aedb53bfa10a5e5e477e1247cd29efa16e0095049c7210234'
REPAIR_SHA256['tests/provider_cloud_site_requests_cases.py'] = '32e436f0a9718d4a5ea85cea8c16fc99ea17b19dbf434bc2188ce264cd24e99c'
REPAIR_SHA256['tests/provider_cloud_site_requests_native.php'] = '365d9bb50af2dbf8f61d931f096cc52e870c23205f51febedb5407f18986c059'
REPAIR_SHA256['tools/acceptance-contract.json'] = 'f48640f62f64bc5dcd7608ea1b6f46aecf74c69426639e34f66bbd9978792e22'
REPAIR_SHA256['tools/acceptance-reports.json'] = '0bb0377061c504fcc4b7c1740558217d726b358a8edd99d5591b0afec989d8cb'
# END GENERATED CLOUD INTAKE REPAIR IDENTITIES
REPAIR_SHA256['tests/account_notification_workflow_contract.py'] = '8e119d1f951a9b12f128f769ae2a2762e5dc0cd703a0516c6467cfea2881cf4a'
REPAIR_SHA256['tests/acceptance_artifacts_test.py'] = 'bc0b96a1818073406581d434d1e26d7b1549e54f57c1ad465b7e2008f33d1279'
REPAIR_SHA256['tests/cloud_assignment_fixture.py'] = '253440f3e7385c52711dacf86b561cadb915b34535598e19c2d333906485b3cb'
REPAIR_SHA256['tests/cloud_assignment_fixture_test.py'] = 'd35ec960bce9c3c247a46b9184384142937ec02db373f3536475d2edba3ffe95'
REPAIR_SHA256['tests/provider_cloud_providers_cases.py'] = '8a26ab9a64abf07edda6627391d53c2296f49559c58514f9bca4a47582c7a95f'
# END GENERATED INTEGRATION REPAIR IDENTITIES
REPAIR_SHA256['tests/account_notification_workflow_contract.py'] = '2c348e84a881a2902739d83309a1f3c2995ef7049015e7e31a41481fa69e2b9e'

_PROJECTIONS = {}
_HISTORICAL_RESULTS = {}
_TREE_ENTRIES = {}
UPSTREAM = 'https://github.com/afterburn25/Cajun_Web.git'


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def present(root):
    path = root / RECEIPT
    return path.exists() or path.is_symlink()


def git(root, *args):
    result = subprocess.run(['git', *args], cwd=root, capture_output=True)
    require(result.returncode == 0, 'Composition needs the existing full-history checkout and component objects: '
            + result.stderr.decode(errors='replace')[-1500:])
    return result.stdout


def ordinary(root, path):
    relative = PurePosixPath(path)
    require(not relative.is_absolute() and '..' not in relative.parts
            and relative.as_posix() == path, 'Unsafe composition path: ' + path)
    target = root / path
    require(root.is_dir() and not root.is_symlink(), 'Nonordinary composition root')
    require(all(not parent.is_symlink() for parent in target.parents if parent != root.parent),
            'Symlink composition ancestor: ' + path)
    require(target.is_file() and not target.is_symlink()
            and stat.S_ISREG(target.stat().st_mode), 'Nonregular composition file: ' + path)
    return target.read_bytes()


def mode(root, path):
    return '100755' if (root / path).stat().st_mode & 0o111 else '100644'


def entries(root, ref):
    # Git objects named by the pinned commit IDs are immutable. Only this
    # metadata is cached; every actual working file is re-read for every gate.
    key = (str(Path(root).resolve()), ref)
    if key in _TREE_ENTRIES:
        return _TREE_ENTRIES[key]
    result = {}
    for row in git(root, 'ls-tree', '-r', '-z', ref).split(b'\0'):
        if row:
            metadata, path = row.split(b'\t', 1)
            permission, kind, oid = metadata.decode().split(' ')
            require(kind == 'blob' and permission in {'100644', '100755'},
                    'Nonregular component tree entry: ' + path.decode())
            result[path.decode()] = (permission, oid)
    _TREE_ENTRIES[key] = result
    return result


def diagnostic_delta(root, path):
    return git(root, 'diff', '--binary', '--no-ext-diff', '--no-renames',
               DIAGNOSTIC_ADDITION_SOURCE, 'HEAD', '--', path)


def github_origin(value):
    if value == 'git@github.com:afterburn25/Cajun_Web.git':
        return True
    parsed = urlparse(value)
    return (parsed.scheme == 'https' and parsed.hostname == 'github.com'
            and parsed.path.rstrip('/') in {'/afterburn25/Cajun_Web', '/afterburn25/Cajun_Web.git'}
            and not parsed.query and not parsed.fragment)


def hydration_origin(root, head):
    """Accept only this repository or a verified local clone of this repository."""
    origin = git(root, 'remote', 'get-url', 'origin').decode().strip()
    if github_origin(origin):
        return UPSTREAM
    parsed = urlparse(origin)
    require((parsed.scheme == 'file' and parsed.netloc in {'', 'localhost'}
             and not parsed.query and not parsed.fragment)
            or (not parsed.scheme and Path(origin).is_absolute()), 'Unreviewed history hydration origin')
    source = Path(unquote(parsed.path) if parsed.scheme == 'file' else origin).resolve()
    require(source.is_dir() and source != Path(root).resolve(), 'Invalid local history source')
    require(Path(git(source, 'rev-parse', '--show-toplevel').decode().strip()).resolve() == source
            and github_origin(git(source, 'remote', 'get-url', 'origin').decode().strip()),
            'Local history source is not a clone of the authorized Cajun_Web repository')
    require(git(source, 'rev-parse', '--is-shallow-repository').strip() == b'false',
            'Local history source must already contain complete ancestry')
    require(git(source, 'rev-parse', head + '^{tree}') == git(root, 'rev-parse', head + '^{tree}'),
            'Local history source has a different current tree')
    for identity in COMPONENTS.values():
        require(git(source, 'rev-parse', identity['commit'] + '^{tree}').decode().strip() == identity['tree'],
                'Local history source has a different component tree')
        git(source, 'merge-base', '--is-ancestor', identity['commit'], head)
    for commit, tree, label in (
        (ADDITION_SOURCE, ADDITION_SOURCE_TREE, 'contact addition'),
        (TICKET_ADDITION_SOURCE, TICKET_ADDITION_SOURCE_TREE, 'ticket addition'),
        (DIAGNOSTIC_ADDITION_SOURCE, DIAGNOSTIC_ADDITION_SOURCE_TREE, 'diagnostic addition'),
        (DATABASE_WORKFLOW_BASE, DATABASE_WORKFLOW_BASE_TREE, 'database workflow base'),
        (DATABASE_WORKFLOW_SOURCE, DATABASE_WORKFLOW_SOURCE_TREE, 'database workflow source'),
        (CLOUD_INTAKE_BASE, CLOUD_INTAKE_BASE_TREE, 'cloud intake base'),
        (CLOUD_INTAKE_SOURCE, CLOUD_INTAKE_SOURCE_TREE, 'cloud intake source'),
        (UX09_BASE, UX09_BASE_TREE, 'UX-09 base'),
        (UX09_SOURCE, UX09_SOURCE_TREE, 'UX-09 source'),
    ):
        require(git(source, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Local history source has a different ' + label + ' tree')
        git(source, 'merge-base', '--is-ancestor', commit, head)
    return source.as_uri()


def history_state(root):
    """State that a read-only history fetch must not change."""
    root = Path(root)
    paths = set(git(root, 'ls-files', '-z').decode().split('\0')) - {''}
    paths.update(set(git(root, 'ls-files', '--others', '--exclude-standard', '-z').decode().split('\0')) - {''})
    rows = []
    for path in sorted(paths):
        rows.append(path.encode() + b'\0' + mode(root, path).encode() + b'\0'
                    + hashlib.sha256(ordinary(root, path)).digest())
    index = Path(git(root, 'rev-parse', '--git-path', 'index').decode().strip())
    if not index.is_absolute():
        index = root / index
    require(index.is_file() and not index.is_symlink(), 'Missing or nonordinary Git index')
    return {
        'head': git(root, 'rev-parse', 'HEAD').decode().strip(),
        'head_tree': git(root, 'rev-parse', 'HEAD^{tree}').decode().strip(),
        'refs_sha256': digest(git(root, 'for-each-ref', '--format=%(refname) %(objectname)')),
        'index_sha256': digest(index.read_bytes()),
        'tracked_and_untracked_source_sha256': digest(b'\n'.join(rows)),
    }


def ensure_history(root):
    """Hydrate only the immutable current HEAD in a real shallow CI checkout.

    The protected workflow deliberately remains unchanged. No branch ref, index,
    checkout, tracked file or untracked source may change. There is no fetch-all,
    tag fetch, moving branch request, environment override or ancestry exception.
    """
    if git(root, 'rev-parse', '--is-shallow-repository').strip() == b'false':
        return {'performed': False}
    before = history_state(root)
    head = before['head']
    require(re.fullmatch('[0-9a-f]{40}', head) is not None, 'Invalid immutable history target')
    origin = hydration_origin(root, head)
    command = ['git', 'fetch', '--no-tags', '--no-write-fetch-head', '--unshallow', origin, head]
    result = subprocess.run(command, cwd=root, capture_output=True,
                            env={**os.environ, 'GIT_TERMINAL_PROMPT': '0'})
    require(history_state(root) == before, 'History hydration changed HEAD, refs, index or actual source')
    require(result.returncode == 0, 'Exact-HEAD history hydration failed: '
            + result.stderr.decode(errors='replace')[-2000:])
    require(git(root, 'rev-parse', '--is-shallow-repository').strip() == b'false',
            'Exact-HEAD hydration did not provide complete ancestry')
    return {'performed': True, 'head': head, 'source_unchanged': True,
            'scope': 'Only exact current HEAD ancestry; no tags, refs, index or working-source changes'}


def expected_union(root):
    require(git(root, 'rev-parse', BASE + '^{tree}').decode().strip() == BASE_TREE,
            'Composition base tree changed')
    baseline = entries(root, BASE)
    changes = {}
    for name, identity in COMPONENTS.items():
        revision = identity['commit']
        require(git(root, 'rev-parse', revision + '^{tree}').decode().strip() == identity['tree'],
                'Component tree identity changed: ' + name)
        git(root, 'merge-base', '--is-ancestor', BASE, revision)
        git(root, 'merge-base', '--is-ancestor', revision, 'HEAD')
        current = entries(root, revision)
        for path in set(baseline) | set(current):
            if baseline.get(path) != current.get(path):
                changes.setdefault(path, {})[name] = current.get(path)
    expected = dict(baseline)
    for path, sources in changes.items():
        if path in NARRATIVES or path in ADAPTERS or path == INVENTORY:
            continue
        values = set(sources.values())
        require(len(values) == 1, 'Unreviewed overlapping component changes: ' + path)
        value, = values
        if value is None:
            expected.pop(path, None)
        else:
            expected[path] = value
    for path in NARRATIVES | ADAPTERS | {INVENTORY}:
        expected.pop(path, None)
    return expected, changes


def validate_receipt(data, verifier):
    require(isinstance(data, dict) and set(data) == {
        'format', 'base', 'base_tree', 'components', 'verifier_sha256', 'regression_sha256', 'adapters',
        'repair_base', 'repair_base_tree', 'integration_repairs',
        'addition_source', 'addition_source_tree', 'additions',
        'ticket_addition_source', 'ticket_addition_source_tree', 'ticket_additions',
        'diagnostic_addition_source', 'diagnostic_addition_source_tree', 'diagnostic_additions',
        'database_workflow_base', 'database_workflow_base_tree',
        'database_workflow_source', 'database_workflow_source_tree',
        'database_workflow_source_paths', 'database_workflow_additions',
        'cloud_intake_base', 'cloud_intake_base_tree',
        'cloud_intake_source', 'cloud_intake_source_tree',
        'cloud_intake_source_paths', 'cloud_intake_source_narratives',
        'cloud_intake_additions'},
        'Unexpected composition receipt fields')
    require(data['format'] == 6 and data['base'] == BASE and data['base_tree'] == BASE_TREE
            and data['components'] == COMPONENTS, 'Unreviewed component source identity')
    require(data['verifier_sha256'] == digest(verifier), 'Composition verifier changed')
    require(data['regression_sha256'] == REGRESSION_SHA256, 'Composition regression identity changed')
    require(set(data['adapters']) == ADAPTERS and set(ADAPTER_SHA256) == ADAPTERS,
            'Unreviewed composition adapter scope')
    for path, row in data['adapters'].items():
        require(isinstance(row, dict) and set(row) == {
            'component', 'original_sha256', 'current_sha256', 'edits'}, 'Malformed composition adapter')
        require(row['component'] == 'account' and row['current_sha256'] == ADAPTER_SHA256[path]
                and isinstance(row['original_sha256'], str) and len(row['original_sha256']) == 64,
                'Unreviewed comparison adapter identity: ' + path)
        require(isinstance(row['edits'], list) and bool(row['edits']), 'Missing adapter inverse: ' + path)
        for edit in row['edits']:
            require(isinstance(edit, list) and len(edit) == 2
                    and all(isinstance(part, str) and part for part in edit)
                    and edit[0] != edit[1], 'Malformed adapter inverse: ' + path)
    require(data['repair_base'] == REPAIR_BASE and data['repair_base_tree'] == REPAIR_BASE_TREE,
            'Unreviewed integration repair checkpoint')
    require(set(data['integration_repairs']) == REPAIRS and set(REPAIR_SHA256) == REPAIRS,
            'Unreviewed integration repair scope')
    for path, row in data['integration_repairs'].items():
        require(isinstance(row, dict) and set(row) == {'original_sha256', 'current_sha256', 'edits'},
                'Malformed integration repair: ' + path)
        require(row['current_sha256'] == REPAIR_SHA256[path]
                and isinstance(row['original_sha256'], str) and len(row['original_sha256']) == 64,
                'Unreviewed integration repair identity: ' + path)
        require(isinstance(row['edits'], list) and bool(row['edits']), 'Missing integration repair inverse: ' + path)
        for edit in row['edits']:
            require(isinstance(edit, list) and len(edit) == 2
                    and all(isinstance(part, str) and part for part in edit)
                    and edit[0] != edit[1], 'Malformed integration repair inverse: ' + path)
    require(data['addition_source'] == ADDITION_SOURCE
            and data['addition_source_tree'] == ADDITION_SOURCE_TREE,
            'Unreviewed contact addition source')
    require(isinstance(data['additions'], dict)
            and set(data['additions']) == ADDITIONS and set(ADDITION_SHA256) == ADDITIONS,
            'Unreviewed contact addition scope')
    for path, row in data['additions'].items():
        require(isinstance(row, dict) and set(row) == {'original_sha256', 'current_sha256', 'edits'},
                'Malformed contact addition: ' + path)
        require(row['current_sha256'] == ADDITION_SHA256[path]
                and isinstance(row['original_sha256'], str)
                and re.fullmatch('[0-9a-f]{64}', row['original_sha256']) is not None,
                'Unreviewed contact addition identity: ' + path)
        require(isinstance(row['edits'], list)
                and (bool(row['edits']) or row['original_sha256'] == row['current_sha256']),
                'Missing corrected contact addition inverse: ' + path)
        for edit in row['edits']:
            require(isinstance(edit, list) and len(edit) == 2
                    and all(isinstance(part, str) and part for part in edit)
                    and edit[0] != edit[1], 'Malformed contact addition inverse: ' + path)
    require(data['ticket_addition_source'] == TICKET_ADDITION_SOURCE
            and data['ticket_addition_source_tree'] == TICKET_ADDITION_SOURCE_TREE,
            'Unreviewed ticket addition source')
    require(isinstance(data['ticket_additions'], dict)
            and set(data['ticket_additions']) == TICKET_ADDITIONS
            and set(TICKET_ADDITION_SHA256) == TICKET_ADDITIONS,
            'Unreviewed ticket addition scope')
    for path, row in data['ticket_additions'].items():
        require(isinstance(row, dict) and set(row) == {'sha256', 'mode'}
                and row['sha256'] == TICKET_ADDITION_SHA256[path]
                and re.fullmatch('[0-9a-f]{64}', row['sha256']) is not None
                and row['mode'] == '100644', 'Unreviewed ticket addition identity: ' + path)
    require(data['diagnostic_addition_source'] == DIAGNOSTIC_ADDITION_SOURCE
            and data['diagnostic_addition_source_tree'] == DIAGNOSTIC_ADDITION_SOURCE_TREE,
            'Unreviewed diagnostic addition source')
    require(isinstance(data['diagnostic_additions'], dict)
            and set(data['diagnostic_additions']) == DIAGNOSTIC_ADDITIONS
            and set(DIAGNOSTIC_ADDITION_SHA256) == DIAGNOSTIC_ADDITIONS,
            'Unreviewed diagnostic addition scope')
    for path, row in data['diagnostic_additions'].items():
        pins = DIAGNOSTIC_ADDITION_SHA256[path]
        require(isinstance(row, dict)
                and set(row) == {'source_sha256', 'current_sha256', 'delta_sha256', 'mode'}
                and row['source_sha256'] == pins['source']
                and row['current_sha256'] == pins['current']
                and row['delta_sha256'] == pins['delta']
                and all(re.fullmatch('[0-9a-f]{64}', row[name]) is not None
                        for name in ('source_sha256', 'current_sha256', 'delta_sha256'))
                and row['mode'] == '100644', 'Unreviewed diagnostic addition identity: ' + path)
    require(data['database_workflow_base'] == DATABASE_WORKFLOW_BASE
            and data['database_workflow_base_tree'] == DATABASE_WORKFLOW_BASE_TREE
            and data['database_workflow_source'] == DATABASE_WORKFLOW_SOURCE
            and data['database_workflow_source_tree'] == DATABASE_WORKFLOW_SOURCE_TREE,
            'Unreviewed database workflow source identity')
    require(set(data['database_workflow_source_paths']) == DATABASE_WORKFLOW_SOURCE_PATHS
            and len(data['database_workflow_source_paths']) == len(DATABASE_WORKFLOW_SOURCE_PATHS),
            'Unreviewed database workflow source path set')
    require(isinstance(data['database_workflow_additions'], dict)
            and set(data['database_workflow_additions']) == DATABASE_WORKFLOW_ADDITIONS
            and set(DATABASE_WORKFLOW_ADDITION_SHA256) == DATABASE_WORKFLOW_ADDITIONS,
            'Unreviewed database workflow addition scope')
    for path, row in data['database_workflow_additions'].items():
        require(isinstance(row, dict) and set(row) == {'sha256', 'mode'}
                and row['sha256'] == DATABASE_WORKFLOW_ADDITION_SHA256[path]
                and re.fullmatch('[0-9a-f]{64}', row['sha256']) is not None
                and row['mode'] == '100644',
                'Unreviewed database workflow addition identity: ' + path)
    require(data['cloud_intake_base'] == CLOUD_INTAKE_BASE
            and data['cloud_intake_base_tree'] == CLOUD_INTAKE_BASE_TREE
            and data['cloud_intake_source'] == CLOUD_INTAKE_SOURCE
            and data['cloud_intake_source_tree'] == CLOUD_INTAKE_SOURCE_TREE,
            'Unreviewed cloud intake source identity')
    require(set(data['cloud_intake_source_paths']) == CLOUD_INTAKE_SOURCE_PATHS
            and len(data['cloud_intake_source_paths']) == len(CLOUD_INTAKE_SOURCE_PATHS),
            'Unreviewed cloud intake source path set')
    require(set(data['cloud_intake_source_narratives']) == CLOUD_INTAKE_SOURCE_NARRATIVES
            and len(data['cloud_intake_source_narratives']) == len(CLOUD_INTAKE_SOURCE_NARRATIVES),
            'Unreviewed cloud intake source narrative set')
    require(isinstance(data['cloud_intake_additions'], dict)
            and set(data['cloud_intake_additions']) == CLOUD_INTAKE_ADDITIONS
            and set(CLOUD_INTAKE_ADDITION_SHA256) == CLOUD_INTAKE_ADDITIONS,
            'Unreviewed cloud intake addition scope')
    for path, row in data['cloud_intake_additions'].items():
        require(isinstance(row, dict) and set(row) == {'sha256', 'mode'}
                and row['sha256'] == CLOUD_INTAKE_ADDITION_SHA256[path]
                and re.fullmatch('[0-9a-f]{64}', row['sha256']) is not None
                and row['mode'] == '100644',
                'Unreviewed cloud intake addition identity: ' + path)
    return data


def validate_ux09_source(root):
    """Bind the complete UX-09 base-to-source range and its current bytes."""
    data = json.loads(ordinary(root, UX09_SOURCE_RECEIPT))
    require(isinstance(data, dict) and set(data) == {
        'schema', 'base_commit', 'base_tree', 'source_commit', 'source_tree',
        'modified', 'added', 'scope'}, 'Unexpected UX-09 source receipt fields')
    require(data['schema'] == 1 and data['base_commit'] == UX09_BASE
            and data['base_tree'] == UX09_BASE_TREE and data['source_commit'] == UX09_SOURCE
            and data['source_tree'] == UX09_SOURCE_TREE and isinstance(data['scope'], str)
            and bool(data['scope']), 'Unreviewed UX-09 source identity')
    require(set(data['modified']) == UX09_MODIFIED and set(data['added']) == UX09_ADDITIONS,
            'Unreviewed UX-09 source path set')
    require(git(root, 'rev-parse', UX09_BASE + '^{tree}').decode().strip() == UX09_BASE_TREE
            and git(root, 'rev-parse', UX09_SOURCE + '^{tree}').decode().strip() == UX09_SOURCE_TREE,
            'UX-09 source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_BASE, UX09_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames', UX09_BASE, UX09_SOURCE).decode().splitlines()
    require(len(changes) == len(UX09_SOURCE_PATHS), 'UX-09 source change count differs')
    observed_modified = {line.split('\t', 1)[1] for line in changes if line.startswith('M\t')}
    observed_added = {line.split('\t', 1)[1] for line in changes if line.startswith('A\t')}
    require(observed_modified == UX09_MODIFIED and observed_added == UX09_ADDITIONS,
            'UX-09 source changed a different path or status set')
    base_entries = entries(root, UX09_BASE);source_entries = entries(root, UX09_SOURCE)
    require(not UX09_ADDITIONS & set(base_entries) and UX09_ADDITIONS <= set(source_entries),
            'UX-09 additions did not originate in the exact source range')
    for path in sorted(UX09_MODIFIED):
        row = data['modified'][path]
        before = git(root, 'cat-file', 'blob', UX09_BASE + ':' + path)
        current = git(root, 'cat-file', 'blob', UX09_SOURCE + ':' + path)
        require(isinstance(row, dict) and set(row) == {'base_sha256', 'source_sha256', 'mode'}
                and row['base_sha256'] == digest(before)
                and row['source_sha256'] == digest(current)
                and row['mode'] == base_entries[path][0] == source_entries[path][0] == '100644'
                and ordinary(root, path) == current and mode(root, path) == row['mode'],
                'UX-09 modified source identity changed: ' + path)
    for path in sorted(UX09_ADDITIONS):
        row = data['added'][path];current = git(root, 'cat-file', 'blob', UX09_SOURCE + ':' + path)
        require(isinstance(row, dict) and set(row) == {'source_sha256', 'mode'}
                and row['source_sha256'] == digest(current)
                and row['mode'] == source_entries[path][0] == '100644'
                and ordinary(root, path) == current and mode(root, path) == row['mode'],
                'UX-09 addition source identity changed: ' + path)
    return data


def project_ux09(root, data, path, current):
    """Restore the resulting-main predecessor only for older source proofs."""
    if path not in UX09_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_SOURCE + ':' + path)
    require(current == source and digest(current) == data['modified'][path]['source_sha256'],
            'Current UX-09 source changed before predecessor projection: ' + path)
    before = git(root, 'cat-file', 'blob', UX09_BASE + ':' + path)
    require(digest(before) == data['modified'][path]['base_sha256'],
            'UX-09 predecessor identity changed: ' + path)
    return before


def reconstruct_adapter(data, path, current):
    require(path in ADAPTERS, 'Unknown comparison adapter')
    row = data['adapters'][path]
    require(digest(current) == row['current_sha256'] == ADAPTER_SHA256[path],
            'Current comparison adapter changed: ' + path)
    text = current.decode('utf-8')
    for before, after in reversed(row['edits']):
        require(text.count(after) == 1, 'Nonunique comparison adapter inverse: ' + path)
        text = text.replace(after, before, 1)
    original = text.encode('utf-8')
    require(digest(original) == row['original_sha256'], 'Historical comparison adapter differs: ' + path)
    for before, after in row['edits']:
        require(text.count(before) == 1, 'Nonunique comparison adapter forward change: ' + path)
        text = text.replace(before, after, 1)
    require(text.encode('utf-8') == current, 'Comparison adapter is not reversible: ' + path)
    return original


def reconstruct_repair(data, path, current):
    """Admit exact current tests; restore old bytes only for source comparison."""
    require(path in REPAIRS, 'Unknown integration repair')
    row = data['integration_repairs'][path]
    require(digest(current) == row['current_sha256'] == REPAIR_SHA256[path],
            'Current integration repair changed: ' + path)
    text = current.decode('utf-8')
    for before, after in reversed(row['edits']):
        require(text.count(after) == 1, 'Nonunique integration repair inverse: ' + path)
        text = text.replace(after, before, 1)
    original = text.encode('utf-8')
    require(digest(original) == row['original_sha256'], 'Failed-checkpoint source differs: ' + path)
    for before, after in row['edits']:
        require(text.count(before) == 1, 'Nonunique integration repair forward change: ' + path)
        text = text.replace(before, after, 1)
    require(text.encode('utf-8') == current, 'Integration repair is not reversible: ' + path)
    return original


def reconstruct_cloud_intake_successor(data, path, current):
    """Reverse only the reviewed current-main-to-cloud-intake source range."""
    require(path in CLOUD_INTAKE_REPAIRS, 'Unknown cloud intake successor')
    row = data['integration_repairs'][path]
    count = CLOUD_INTAKE_SUCCESSOR_EDITS[path]
    ci_count = CLOUD_INTAKE_CI_SUCCESSOR_EDITS.get(path, 0)
    expected = CLOUD_INTAKE_CI_PREDECESSOR_SHA256.get(path, REPAIR_SHA256[path])
    require(digest(current) == expected
            and row['current_sha256'] == REPAIR_SHA256[path]
            and len(row['edits']) == CLOUD_INTAKE_SUCCESSOR_EDIT_COUNTS[path],
            'Current cloud intake successor changed: ' + path)
    text = current.decode('utf-8')
    edits = row['edits'][-(count + ci_count):-ci_count if ci_count else None]
    for before, after in reversed(edits):
        require(text.count(after) == 1, 'Nonunique cloud intake successor inverse: ' + path)
        text = text.replace(after, before, 1)
    predecessor = text.encode('utf-8')
    require(digest(predecessor) == CLOUD_INTAKE_PREDECESSOR_SHA256[path],
            'Cloud intake predecessor changed: ' + path)
    for before, after in edits:
        require(text.count(before) == 1, 'Nonunique cloud intake successor forward change: ' + path)
        text = text.replace(before, after, 1)
    require(text.encode('utf-8') == current, 'Cloud intake successor is not reversible: ' + path)
    return predecessor


def reconstruct_database_workflow_successor(data, path, current):
    """Reverse the fixed post-UX07 edit after any later source-range projection."""
    require(path in DATABASE_WORKFLOW_SUCCESSOR_EDITS, 'Unknown database workflow successor')
    row = data['integration_repairs'][path]
    cloud_count = CLOUD_INTAKE_SUCCESSOR_EDITS.get(path, 0)
    ci_count = CLOUD_INTAKE_CI_SUCCESSOR_EDITS.get(path, 0)
    ux09_proof_count = UX09_PROOF_SUCCESSOR_EDITS.get(path, 0)
    expected = CLOUD_INTAKE_PREDECESSOR_SHA256.get(
        path, CLOUD_INTAKE_CI_PREDECESSOR_SHA256.get(path, REPAIR_SHA256[path]))
    require(digest(current) == expected
            and len(row['edits']) == (DATABASE_WORKFLOW_SUCCESSOR_EDITS[path] + cloud_count
                                      + ci_count + ux09_proof_count),
            'Current database workflow successor changed: ' + path)
    before, after = row['edits'][DATABASE_WORKFLOW_SUCCESSOR_EDITS[path] - 1]
    text = current.decode('utf-8')
    require(text.count(after) == 1, 'Nonunique database workflow successor inverse: ' + path)
    predecessor = text.replace(after, before, 1)
    require(predecessor.count(before) == 1,
            'Nonunique database workflow successor forward change: ' + path)
    require(predecessor.replace(before, after, 1).encode('utf-8') == current,
            'Database workflow successor is not reversible: ' + path)
    return predecessor.encode('utf-8')


def reconstruct_cloud_intake_ci_successor(data, path, current):
    """Reverse an exact CI-discovered successor before source-range reconstruction."""
    require(path in CLOUD_INTAKE_CI_SUCCESSOR_EDITS, 'Unknown cloud intake CI successor')
    row = data['integration_repairs'][path]
    count = CLOUD_INTAKE_CI_SUCCESSOR_EDITS[path]
    ux09_proof_count = UX09_PROOF_SUCCESSOR_EDITS.get(path, 0)
    expected = UX09_PROOF_PREDECESSOR_SHA256.get(path, REPAIR_SHA256[path])
    require(digest(current) == expected and row['current_sha256'] == REPAIR_SHA256[path]
            and len(row['edits']) == CLOUD_INTAKE_CI_EDIT_COUNTS[path] + ux09_proof_count,
            'Current cloud intake CI successor changed: ' + path)
    text = current.decode('utf-8')
    edits = row['edits'][-(count + ux09_proof_count):-ux09_proof_count if ux09_proof_count else None]
    for before, after in reversed(edits):
        require(text.count(after) == 1, 'Nonunique cloud intake CI inverse: ' + path)
        text = text.replace(after, before, 1)
    predecessor = text.encode('utf-8')
    require(digest(predecessor) == CLOUD_INTAKE_CI_PREDECESSOR_SHA256[path],
            'Cloud intake CI predecessor changed: ' + path)
    for before, after in edits:
        require(text.count(before) == 1, 'Nonunique cloud intake CI forward change: ' + path)
        text = text.replace(before, after, 1)
    require(text.encode('utf-8') == current, 'Cloud intake CI successor is not reversible: ' + path)
    return predecessor


def reconstruct_ux09_proof_successor(data, path, current):
    """Reverse only the exact proof repair discovered by initial UX-09 CI."""
    require(path in UX09_PROOF_SUCCESSOR_EDITS, 'Unknown UX-09 proof successor')
    row = data['integration_repairs'][path]
    count = UX09_PROOF_SUCCESSOR_EDITS[path]
    require(digest(current) == row['current_sha256'] == REPAIR_SHA256[path]
            and len(row['edits']) == UX09_PROOF_EDIT_COUNTS[path],
            'Current UX-09 proof successor changed: ' + path)
    text = current.decode('utf-8')
    edits = row['edits'][-count:]
    for before, after in reversed(edits):
        require(text.count(after) == 1, 'Nonunique UX-09 proof successor inverse: ' + path)
        text = text.replace(after, before, 1)
    predecessor = text.encode('utf-8')
    require(digest(predecessor) == UX09_PROOF_PREDECESSOR_SHA256[path],
            'UX-09 proof predecessor changed: ' + path)
    for before, after in edits:
        require(text.count(before) == 1, 'Nonunique UX-09 proof successor forward change: ' + path)
        text = text.replace(before, after, 1)
    require(text.encode('utf-8') == current, 'UX-09 proof successor is not reversible: ' + path)
    return predecessor


def reconstruct_addition(data, path, current):
    """Retain each exact new PR331 file while running its current reviewed bytes."""
    require(path in ADDITIONS, 'Unknown contact addition')
    row = data['additions'][path]
    require(digest(current) == row['current_sha256'] == ADDITION_SHA256[path],
            'Current contact addition changed: ' + path)
    text = current.decode('utf-8')
    for before, after in reversed(row['edits']):
        require(text.count(after) == 1, 'Nonunique contact addition inverse: ' + path)
        text = text.replace(after, before, 1)
    original = text.encode('utf-8')
    require(digest(original) == row['original_sha256'], 'Retained contact source differs: ' + path)
    for before, after in row['edits']:
        require(text.count(before) == 1, 'Nonunique contact addition forward change: ' + path)
        text = text.replace(before, after, 1)
    require(text.encode('utf-8') == current, 'Contact addition is not reversible: ' + path)
    return original


def canonical_inventory(root):
    rows = []
    for target in sorted((root / 'site').rglob('*'), key=lambda item: item.relative_to(root / 'site').as_posix()):
        require(not target.is_symlink(), 'Symlink in current shipping tree')
        if target.is_file():
            path = target.relative_to(root / 'site').as_posix()
            if path != 'install/inventory.generated.php':
                rows.append("    '" + path + "'=>'" + digest(target.read_bytes()) + "',\n")
    guard = "<?php\ndeclare(strict_types=1);\nif (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }\n"
    return (guard + 'function setup_expected_files(): array { return [\n' + ''.join(rows) + ']; }\n').encode()


def generated_bytecode(root, path, source_paths):
    """Ignore only ordinary generated test/tool caches, never tracked/source files.

    These caches are not Git inputs or package contents. Their corresponding .py
    files are independently bound to component/adapter identities below. Source
    projections start from clean Git checkouts and use -B. No shipping, foundation,
    documentation or workflow path receives this cache exception.
    """
    parts = PurePosixPath(path).parts
    if len(parts) < 3 or parts[0] not in {'tests', 'tools'} or parts[-2] != '__pycache__':
        return False
    match = re.fullmatch(r'(.+)\.' + re.escape(sys.implementation.cache_tag) + r'(?:\.opt-([12]))?\.pyc', parts[-1])
    if not match:
        return False
    source_path = PurePosixPath(*parts[:-2], match[1] + '.py').as_posix()
    require(source_path in source_paths, 'Bytecode has no reviewed source: ' + path)
    ordinary(root, path)
    require(mode(root, path) == '100644', 'Executable generated cache: ' + path)
    return True


def actual_paths(root, source_paths):
    tracked = set(git(root, 'ls-files', '-z').decode().split('\0')) - {''}
    paths = set(tracked)
    paths.update(set(git(root, 'ls-files', '--others', '--exclude-standard', '-z').decode().split('\0')) - {''})
    # Include ignored files too within protected source/evidence directories.
    for area in ('site', 'foundation', 'tests', 'tools', '.github', 'docs'):
        directory = root / area
        require(not directory.is_symlink(), 'Symlink protected source directory: ' + area)
        if directory.exists():
            for target in directory.rglob('*'):
                if target.is_symlink() or target.is_file():
                    paths.add(target.relative_to(root).as_posix())
    return {path for path in paths - NARRATIVES if path in tracked or not generated_bytecode(root, path, source_paths)}


def verify_current(root):
    root = Path(root)
    verifier = ordinary(root, VERIFIER)
    data = validate_receipt(json.loads(ordinary(root, RECEIPT)), verifier)
    ensure_history(root)
    ux09 = validate_ux09_source(root)
    onboarding = json.loads(ordinary(root, ONBOARDING_SOURCE))
    require(onboarding.get('schema') == 1 and onboarding.get('base_commit') == ONBOARDING_BASE
            and onboarding.get('base_tree') == ONBOARDING_BASE_TREE,
            'Software onboarding source receipt identity changed')
    require(set(onboarding.get('modified', {})) == ONBOARDING_MODIFIED
            and set(onboarding.get('added', {})) == ONBOARDING_ADDITIONS,
            'Software onboarding source receipt path set changed')
    require(git(root, 'rev-parse', ONBOARDING_BASE + '^{tree}').decode().strip() == ONBOARDING_BASE_TREE,
            'Software onboarding base tree changed')
    require(mode(root, VERIFIER) == mode(root, RECEIPT) == mode(root, REGRESSION) == '100644',
            'Composition proof file mode changed')
    require(digest(ordinary(root, REGRESSION)) == REGRESSION_SHA256, 'Composition regression changed')
    require(git(root, 'rev-parse', REPAIR_BASE + '^{tree}').decode().strip() == REPAIR_BASE_TREE,
            'Failed acceptance checkpoint tree changed')
    git(root, 'merge-base', '--is-ancestor', REPAIR_BASE, 'HEAD')
    expected, changed = expected_union(root)
    require(REPAIRS <= set(expected) and not REPAIRS & ADAPTERS,
            'Integration repairs must retain existing component files')
    require(not ADDITIONS & (set(expected) | ADAPTERS | REPAIRS
                            | NARRATIVES | {INVENTORY, RECEIPT, VERIFIER, REGRESSION}),
            'Contact additions cannot replace existing component or proof files')
    require(not TICKET_ADDITIONS & (set(expected) | ADAPTERS | REPAIRS | ADDITIONS
                                   | NARRATIVES | {INVENTORY, RECEIPT, VERIFIER, REGRESSION}),
            'Ticket additions cannot replace existing component, contact or proof files')
    require(not DIAGNOSTIC_ADDITIONS & (set(expected) | ADAPTERS | REPAIRS | ADDITIONS
                                       | TICKET_ADDITIONS | NARRATIVES
                                       | {INVENTORY, RECEIPT, VERIFIER, REGRESSION}),
            'Diagnostic additions cannot replace existing component, repair, addition or proof files')
    require(not DATABASE_WORKFLOW_ADDITIONS & (set(expected) | ADAPTERS | REPAIRS | ADDITIONS
                                               | TICKET_ADDITIONS | DIAGNOSTIC_ADDITIONS
                                               | ONBOARDING_ADDITIONS | NARRATIVES
                                               | {INVENTORY, RECEIPT, VERIFIER, REGRESSION}),
            'Database workflow additions cannot replace existing admitted source or proof files')
    require(not CLOUD_INTAKE_ADDITIONS & (set(expected) | ADAPTERS | REPAIRS | ADDITIONS
                                          | TICKET_ADDITIONS | DIAGNOSTIC_ADDITIONS
                                          | ONBOARDING_ADDITIONS | DATABASE_WORKFLOW_ADDITIONS
                                          | NARRATIVES | {INVENTORY, RECEIPT, VERIFIER, REGRESSION}),
            'Cloud intake additions cannot replace existing admitted source or proof files')
    require(not UX09_ADDITIONS & (set(expected) | ADAPTERS | REPAIRS | ADDITIONS
                                  | TICKET_ADDITIONS | DIAGNOSTIC_ADDITIONS
                                  | ONBOARDING_ADDITIONS | DATABASE_WORKFLOW_ADDITIONS
                                  | CLOUD_INTAKE_ADDITIONS | NARRATIVES
                                  | {INVENTORY, RECEIPT, VERIFIER, REGRESSION, UX09_SOURCE_RECEIPT}),
            'UX-09 additions cannot replace existing admitted source or proof files')
    require(CLOUD_INTAKE_SOURCE_NARRATIVES <= NARRATIVES,
            'Cloud intake source narratives must remain non-executable exceptions')
    require(git(root, 'rev-parse', ADDITION_SOURCE + '^{tree}').decode().strip() == ADDITION_SOURCE_TREE,
            'Retained contact source tree changed')
    git(root, 'merge-base', '--is-ancestor', ADDITION_SOURCE, 'HEAD')
    addition_entries = entries(root, ADDITION_SOURCE)
    require(ADDITIONS <= set(addition_entries), 'Missing contact addition in retained source')
    require(git(root, 'rev-parse', TICKET_ADDITION_SOURCE + '^{tree}').decode().strip()
            == TICKET_ADDITION_SOURCE_TREE, 'Retained ticket source tree changed')
    git(root, 'merge-base', '--is-ancestor', TICKET_ADDITION_SOURCE, 'HEAD')
    ticket_addition_entries = entries(root, TICKET_ADDITION_SOURCE)
    require(TICKET_ADDITIONS <= set(ticket_addition_entries),
            'Missing ticket addition in retained source')
    ticket_source_parent = git(root, 'rev-parse', TICKET_ADDITION_SOURCE + '^').decode().strip()
    ticket_source_paths = set(git(root, 'diff-tree', '--no-commit-id', '--name-only', '-r',
                                  ticket_source_parent, TICKET_ADDITION_SOURCE).decode().splitlines())
    require(ticket_source_paths == TICKET_SOURCE_PATHS,
            'Retained ticket source changed a different path set')
    require(ordinary(root, 'tests/ticket_cases.py')
            == git(root, 'cat-file', 'blob', TICKET_ADDITION_SOURCE + ':tests/ticket_cases.py'),
            'Current ticket cases do not match the exact retained functional source')
    require(git(root, 'rev-parse', DIAGNOSTIC_ADDITION_SOURCE + '^{tree}').decode().strip()
            == DIAGNOSTIC_ADDITION_SOURCE_TREE, 'Retained diagnostic source tree changed')
    git(root, 'merge-base', '--is-ancestor', DIAGNOSTIC_ADDITION_SOURCE, 'HEAD')
    diagnostic_entries = entries(root, DIAGNOSTIC_ADDITION_SOURCE)
    require(DIAGNOSTIC_ADDITIONS <= set(diagnostic_entries),
            'Missing diagnostic addition in retained source')
    diagnostic_source_parent = git(root, 'rev-parse', DIAGNOSTIC_ADDITION_SOURCE + '^').decode().strip()
    diagnostic_source_paths = set(git(root, 'diff-tree', '--no-commit-id', '--name-only', '-r',
                                      diagnostic_source_parent, DIAGNOSTIC_ADDITION_SOURCE).decode().splitlines())
    require(diagnostic_source_paths == DIAGNOSTIC_ADDITIONS,
            'Retained diagnostic source changed a different path set')
    require(not DIAGNOSTIC_ADDITIONS & set(entries(root, diagnostic_source_parent)),
            'Retained diagnostic source did not add every admitted path')
    require(not DIAGNOSTIC_ADDITIONS & set(ticket_addition_entries),
            'Diagnostic additions existed in the earlier ticket source')
    for identity in (BASE, REPAIR_BASE, ADDITION_SOURCE,
                     *(source['commit'] for source in COMPONENTS.values())):
        require(not TICKET_ADDITIONS & set(entries(root, identity)),
                'Ticket helper existed in an earlier admitted source: ' + identity)
        require(not DIAGNOSTIC_ADDITIONS & set(entries(root, identity)),
                'Diagnostic source existed in an earlier admitted source: ' + identity)
    require(git(root, 'rev-parse', CLOUD_INTAKE_BASE + '^{tree}').decode().strip()
            == CLOUD_INTAKE_BASE_TREE, 'Cloud intake base tree changed')
    require(git(root, 'rev-parse', CLOUD_INTAKE_SOURCE + '^{tree}').decode().strip()
            == CLOUD_INTAKE_SOURCE_TREE, 'Cloud intake source tree changed')
    git(root, 'merge-base', '--is-ancestor', CLOUD_INTAKE_BASE, CLOUD_INTAKE_SOURCE)
    git(root, 'merge-base', '--is-ancestor', CLOUD_INTAKE_SOURCE, 'HEAD')
    cloud_source_paths = set(git(root, 'diff', '--name-only', '--no-renames',
                                 CLOUD_INTAKE_BASE, CLOUD_INTAKE_SOURCE).decode().splitlines())
    require(cloud_source_paths == CLOUD_INTAKE_SOURCE_PATHS | CLOUD_INTAKE_SOURCE_NARRATIVES,
            'Cloud intake source changed a different path set')
    cloud_base_entries = entries(root, CLOUD_INTAKE_BASE)
    cloud_source_entries = entries(root, CLOUD_INTAKE_SOURCE)
    require(not CLOUD_INTAKE_ADDITIONS & set(cloud_base_entries)
            and CLOUD_INTAKE_ADDITIONS <= set(cloud_source_entries),
            'Cloud intake addition did not originate in its exact source range')
    for path in sorted(CLOUD_INTAKE_SOURCE_PATHS):
        value = project_ux09(root, ux09, path, ordinary(root, path))
        if path in UX09_PROOF_SUCCESSOR_EDITS:
            value = reconstruct_ux09_proof_successor(data, path, value)
        if path in CLOUD_INTAKE_CI_SUCCESSOR_EDITS:
            value = reconstruct_cloud_intake_ci_successor(data, path, value)
        if path in CLOUD_INTAKE_REPAIRS:
            require(reconstruct_cloud_intake_successor(data, path, value)
                    == git(root, 'cat-file', 'blob', CLOUD_INTAKE_BASE + ':' + path),
                    'Cloud intake repair does not retain exact base bytes: ' + path)
        require(value == git(root, 'cat-file', 'blob', CLOUD_INTAKE_SOURCE + ':' + path)
                and mode(root, path) == cloud_source_entries[path][0],
                'Current cloud intake bytes or mode differ from exact source: ' + path)
    for path in sorted(CLOUD_INTAKE_ADDITIONS):
        value = ordinary(root, path)
        row = data['cloud_intake_additions'][path]
        require(digest(value) == row['sha256'] == CLOUD_INTAKE_ADDITION_SHA256[path]
                and row['mode'] == cloud_source_entries[path][0] == '100644',
                'Cloud intake addition bytes or mode changed: ' + path)
    require(git(root, 'rev-parse', DATABASE_WORKFLOW_BASE + '^{tree}').decode().strip()
            == DATABASE_WORKFLOW_BASE_TREE, 'Database workflow base tree changed')
    require(git(root, 'rev-parse', DATABASE_WORKFLOW_SOURCE + '^{tree}').decode().strip()
            == DATABASE_WORKFLOW_SOURCE_TREE, 'Database workflow source tree changed')
    git(root, 'merge-base', '--is-ancestor', DATABASE_WORKFLOW_BASE, DATABASE_WORKFLOW_SOURCE)
    git(root, 'merge-base', '--is-ancestor', DATABASE_WORKFLOW_SOURCE, 'HEAD')
    database_source_paths = set(git(root, 'diff', '--name-only', '--no-renames',
                                    DATABASE_WORKFLOW_BASE, DATABASE_WORKFLOW_SOURCE).decode().splitlines())
    require(database_source_paths == DATABASE_WORKFLOW_SOURCE_PATHS,
            'Database workflow source changed a different path set')
    database_base_entries = entries(root, DATABASE_WORKFLOW_BASE)
    database_source_entries = entries(root, DATABASE_WORKFLOW_SOURCE)
    require(not DATABASE_WORKFLOW_ADDITIONS & set(database_base_entries)
            and DATABASE_WORKFLOW_ADDITIONS <= set(database_source_entries),
            'Database workflow addition did not originate in its exact source range')
    for path in sorted(DATABASE_WORKFLOW_SOURCE_PATHS):
        value = project_ux09(root, ux09, path, ordinary(root, path))
        if path in UX09_PROOF_SUCCESSOR_EDITS:
            value = reconstruct_ux09_proof_successor(data, path, value)
        if path in CLOUD_INTAKE_CI_SUCCESSOR_EDITS:
            value = reconstruct_cloud_intake_ci_successor(data, path, value)
        if path == INVENTORY:
            require(value == git(root, 'cat-file', 'blob', CLOUD_INTAKE_SOURCE + ':' + path),
                    'Current cloud intake inventory differs from its exact source')
            value = git(root, 'cat-file', 'blob', CLOUD_INTAKE_BASE + ':' + path)
        elif path in CLOUD_INTAKE_REPAIRS:
            value = reconstruct_cloud_intake_successor(data, path, value)
        if path in DATABASE_WORKFLOW_SUCCESSOR_EDITS:
            value = reconstruct_database_workflow_successor(data, path, value)
        require(value == git(root, 'cat-file', 'blob', DATABASE_WORKFLOW_SOURCE + ':' + path)
                and mode(root, path) == database_source_entries[path][0],
                'Current database workflow bytes or mode differ from exact source: ' + path)
    for path in sorted(DATABASE_WORKFLOW_ADDITIONS):
        value = project_ux09(root, ux09, path, ordinary(root, path))
        row = data['database_workflow_additions'][path]
        require(digest(value) == row['sha256'] == DATABASE_WORKFLOW_ADDITION_SHA256[path]
                and row['mode'] == database_source_entries[path][0] == '100644',
                'Database workflow addition bytes or mode changed: ' + path)
    allowed = (set(expected) | ADAPTERS | ADDITIONS | TICKET_ADDITIONS | DIAGNOSTIC_ADDITIONS
               | ONBOARDING_ADDITIONS | DATABASE_WORKFLOW_ADDITIONS | CLOUD_INTAKE_ADDITIONS
               | UX09_ADDITIONS
               | {INVENTORY, RECEIPT, VERIFIER, REGRESSION, ONBOARDING_SOURCE,
                  UX09_SOURCE_RECEIPT})
    actual = actual_paths(root, allowed)
    require(actual == allowed, 'Unknown or missing composition paths: '
            + repr(sorted(actual.symmetric_difference(allowed))))
    for path, (permission, oid) in expected.items():
        value = project_ux09(root, ux09, path, ordinary(root, path))
        if path in ONBOARDING_MODIFIED:
            row = onboarding['modified'][path]
            before = git(root, 'cat-file', 'blob', ONBOARDING_BASE + ':' + path)
            require(row == {'before_sha256': digest(before), 'current_sha256': digest(value)}
                    and mode(root, path) == permission,
                    'Software onboarding modified source identity changed: ' + path)
            value = before
        if path in REPAIRS:
            value = reconstruct_repair(data, path, value)
            require(value == git(root, 'cat-file', 'blob', REPAIR_BASE + ':' + path),
                    'Integration repair no longer retains its exact failed-checkpoint source: ' + path)
        blob = hashlib.sha1(b'blob ' + str(len(value)).encode() + b'\0' + value).hexdigest()
        require(blob == oid and mode(root, path) == permission, 'Current component source or mode differs: ' + path)
    for path in sorted(ONBOARDING_ADDITIONS):
        value = ordinary(root, path)
        require(onboarding['added'][path] == digest(value) and mode(root, path) == '100644'
                and path not in entries(root, ONBOARDING_BASE),
                'Software onboarding addition identity changed: ' + path)
    for path in sorted(ADDITIONS):
        original = reconstruct_addition(data, path, ordinary(root, path))
        require(mode(root, path) == addition_entries[path][0] == '100644'
                and original == git(root, 'cat-file', 'blob', ADDITION_SOURCE + ':' + path),
                'Contact addition no longer retains its exact original source or mode: ' + path)
    for path in sorted(TICKET_ADDITIONS):
        value = ordinary(root, path)
        row = data['ticket_additions'][path]
        require(digest(value) == row['sha256'] == TICKET_ADDITION_SHA256[path]
                and mode(root, path) == row['mode'] == ticket_addition_entries[path][0] == '100644'
                and value == git(root, 'cat-file', 'blob', TICKET_ADDITION_SOURCE + ':' + path),
                'Ticket addition no longer matches its exact source bytes or mode: ' + path)
    for path in sorted(DIAGNOSTIC_ADDITIONS):
        value = ordinary(root, path)
        source = git(root, 'cat-file', 'blob', DIAGNOSTIC_ADDITION_SOURCE + ':' + path)
        row = data['diagnostic_additions'][path]
        pins = DIAGNOSTIC_ADDITION_SHA256[path]
        require(digest(source) == row['source_sha256'] == pins['source']
                and digest(value) == row['current_sha256'] == pins['current']
                and digest(diagnostic_delta(root, path)) == row['delta_sha256'] == pins['delta']
                and mode(root, path) == row['mode'] == diagnostic_entries[path][0] == '100644',
                'Diagnostic addition source/current/delta bytes or mode changed: ' + path)
    for path in sorted(ADAPTERS):
        original = reconstruct_adapter(data, path, ordinary(root, path))
        require(mode(root, path) == '100644'
                and original == git(root, 'cat-file', 'blob', COMPONENTS['account']['commit'] + ':' + path),
                'Original adapter assertions are not the exact component bytes: ' + path)
    require(mode(root, INVENTORY) == '100644'
            and ordinary(root, INVENTORY) == canonical_inventory(root), 'Current combined inventory is not canonical')
    return [
        {'name': 'Exact common ancestor and all four component commit/tree/ancestry identities', 'passed': True},
        {'name': 'Current or exactly reconstructed bytes and executable modes match the component union for ' + str(len(expected)) + ' retained files', 'passed': True},
        {'name': 'Four finite comparison adapters retain exact original assertions through reversible edits', 'passed': True},
        {'name': str(len(REPAIRS)) + ' exact integration-test repairs retain the failed checkpoint through reversible edits; runtime uses current bytes', 'passed': True},
        {'name': 'Four exact contact additions retain their original PR331 source, ancestry and modes; runtime uses current reviewed bytes', 'passed': True},
        {'name': 'The exact two-path ticket source retains its commit, tree, ancestry and path equality; the helper bytes and mode are a disjoint addition', 'passed': True},
        {'name': 'Six diagnostic additions retain their exact source commit/tree/path set, current bytes, complete deltas and modes as a disjoint second addition source', 'passed': True},
        {'name': 'Exact UX-07 base-to-source range binds ' + str(len(DATABASE_WORKFLOW_SOURCE_PATHS)) + ' database workflow paths and seven disjoint additions', 'passed': True},
        {'name': 'Exact current-main-to-cloud-intake range binds ' + str(len(CLOUD_INTAKE_SOURCE_PATHS)) + ' source paths, two disjoint additions and the bounded narrative exception', 'passed': True},
        {'name': 'Exact resulting-main-to-UX-09 range binds ' + str(len(UX09_MODIFIED)) + ' modified paths and six disjoint additions', 'passed': True},
        {'name': 'CI-discovered workflow, predecessor-projection and browser-fixture successors are exact and reversible', 'passed': True},
        {'name': 'Current canonical installer inventory binds the complete combined shipping tree', 'passed': True},
        {'name': 'Unknown, missing, symlink and ignored source additions are refused', 'passed': True},
        {'name': 'Original standalone proofs, receipts, historical failures and workflow bodies are unchanged', 'passed': True},
    ]


def projection(root, component):
    """An isolated checkout used exclusively by historical source validators."""
    require(component in COMPONENTS, 'Unknown component projection')
    identity = COMPONENTS[component]['commit']
    key = (str(Path(root).resolve()), identity)
    if key not in _PROJECTIONS:
        directory = tempfile.TemporaryDirectory(prefix='cwp-component-source-')
        target = Path(directory.name) / 'component'
        result = subprocess.run(['git', 'clone', '--quiet', '--shared', '--no-checkout', '--local', str(root), str(target)],
                                capture_output=True)
        require(result.returncode == 0, 'Could not create local source-only component projection')
        git(target, 'checkout', '--quiet', '--detach', identity)
        require(not git(target, 'status', '--porcelain'), 'Dirty historical component projection')
        _PROJECTIONS[key] = (directory, target)
    return _PROJECTIONS[key][1]


SOURCE_RUNNER = r'''
from pathlib import Path
import base64,importlib,json,sys
root=Path(sys.argv[1]);sys.path.insert(0,str(root/'tests'))
request=json.load(sys.stdin)
module=importlib.import_module(request['module'])
method=getattr(module,request['method'])
args=[root]
if request['method']=='projected_validation':
    callback=importlib.import_module(request['callback'])
    args.extend([callback.validate,tuple(request['paths'])])
else:
    if 'path' in request:args.append(request['path'])
    if 'value' in request:args.append(base64.b64decode(request['value']))
    if 'side' in request:args.append(request['side'])
result=method(*args)
if isinstance(result,bytes):result={'bytes':base64.b64encode(result).decode()}
json.dump(result,sys.stdout)
'''


def original_call(root, component, module, method, **arguments):
    allowed = {
        'account': {
            'tickets_integration_contract': {'validate'},
            'account_notification_contract': {'validate'},
            'account_notification_fixture_provenance': {'validate', 'project', 'core_input'},
            'provider_browser_fixture_provenance': {'validate', 'predecessor', 'source', 'projected_validation'},
            'notification_settings_freshness_provenance': {'validate'},
        },
        'software': {'software_authority_provenance': {'validate'}},
        'finance': {'finance_recovery_provenance': {'validate'}},
    }
    require(component in allowed and module in allowed[component]
            and method in allowed[component][module], 'Component projections are restricted to named source validators')
    target = projection(root, component)
    request = {'module': module, 'method': method, **arguments}
    result = subprocess.run([sys.executable, '-B', '-c', SOURCE_RUNNER, str(target)],
                            input=json.dumps(request), text=True, capture_output=True,
                            cwd=target, env={**os.environ, 'PYTHONDONTWRITEBYTECODE': '1', 'PYTHONPATH': str(target / 'tests')})
    require(result.returncode == 0, 'Original ' + component + ' source proof failed: '
            + module + '.' + method + '\n' + result.stderr[-4000:])
    require(not result.stderr, 'Unexpected original source-proof stderr: ' + result.stderr[-1000:])
    output = json.loads(result.stdout)
    return base64.b64decode(output['bytes']) if isinstance(output, dict) and set(output) == {'bytes'} else output


def account_call(root, module, method, *, path=None, value=None, side=None):
    """Only explicitly listed historical-comparison APIs may use a projection."""
    allowed = {
        'tickets_integration_contract': {'validate'},
        'account_notification_contract': {'validate'},
        'account_notification_fixture_provenance': {'validate', 'project', 'core_input'},
        'provider_browser_fixture_provenance': {'validate', 'predecessor', 'source'},
    }
    require(module in allowed and method in allowed[module], 'Unreviewed source-comparison dispatch')
    verify_current(root)
    arguments = {}
    if path is not None:
        require(path.startswith('tests/') or (module == 'provider_browser_fixture_provenance'
                and method == 'source' and path == 'tools/acceptance-reports.json'),
                'Historical projections never supply shipping/runtime file bytes')
        ordinary(root, path)
        arguments['path'] = path
    if value is not None:
        require(path is not None and isinstance(value, bytes), 'Malformed comparison bytes')
        if (module == 'account_notification_fixture_provenance' and method == 'core_input'
                and path == 'tests/payments_cases.py'):
            # The current payment fixture has one reviewed integration repair,
            # while the unchanged account component's core-browser validator
            # still expects its exact pre-repair bytes. Project only through
            # that already-validated finite receipt; runtime keeps current bytes.
            require(value == ordinary(root, path), 'Unreviewed current core comparison input: ' + path)
            data = validate_receipt(json.loads(ordinary(root, RECEIPT)), ordinary(root, VERIFIER))
            value = reconstruct_repair(data, path, value)
            require(value == git(root, 'cat-file', 'blob', COMPONENTS['account']['commit'] + ':' + path),
                    'Core comparison repair does not retain the exact account component: ' + path)
        if method in {'project', 'predecessor'}:
            # Callers must supply actual current bytes, never arbitrary restored
            # code. Equality to the reviewed union was established just above.
            require(value == ordinary(root, path), 'Unreviewed current comparison input: ' + path)
            value = git(root, 'cat-file', 'blob', COMPONENTS['account']['commit'] + ':' + path)
        arguments['value'] = base64.b64encode(value).decode()
    if side is not None:
        require(side in {'root', 'feature'}, 'Unknown historical fixture side')
        arguments['side'] = side
    return original_call(root, 'account', module, method, **arguments)


def projected_validation(root, validator, receipt_paths):
    expected = {
        'standalone_repair_provenance': ('docs/STANDALONE_ACCEPTANCE_REPAIRS.json',),
        'combined_repair_provenance': ('docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json', 'docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json'),
    }
    require(validator.__name__ == 'validate' and validator.__module__ in expected
            and tuple(receipt_paths) == expected[validator.__module__], 'Unreviewed historical validator dispatch')
    verify_current(root)
    return original_call(root, 'account', 'provider_browser_fixture_provenance', 'projected_validation',
                         callback=validator.__module__, paths=list(receipt_paths))


def validate(root):
    checks = verify_current(root)
    # These are unchanged source proofs, including their negative cases. They
    # are deliberately not reruns of historical SQL/browser/runtime assertions.
    for component, module in (
        ('account', 'notification_settings_freshness_provenance'),
        ('software', 'software_authority_provenance'),
        ('finance', 'finance_recovery_provenance'),
    ):
        key = (str(Path(root).resolve()), COMPONENTS[component]['commit'], module)
        if key not in _HISTORICAL_RESULTS:
            _HISTORICAL_RESULTS[key] = original_call(root, component, module, 'validate')
        original = copy.deepcopy(_HISTORICAL_RESULTS[key])
        require(isinstance(original, list) and original and all(row['passed'] is True for row in original),
                'Incomplete historical source proof: ' + module)
        checks.extend({'name': component + ' source: ' + row['name'], 'passed': True} for row in original)
    return checks


if __name__ == '__main__':
    checks = validate(Path(__file__).resolve().parents[1])
    print(json.dumps({'passed': True, 'count': len(checks), 'components': COMPONENTS, 'checks': checks,
                      'scope': 'Exact current composition and original source-only proofs. No SQL, browser, provider, merchant, main or release certification.'}, indent=2))
