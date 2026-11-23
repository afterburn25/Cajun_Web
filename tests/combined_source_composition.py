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
from collections import OrderedDict
from contextlib import contextmanager
import functools
import hashlib
import inspect
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
UX08_STOREFRONT_BASE = '7dd3bbde203603fbda579e0cf1a1a2e24a5e6aab'
UX08_STOREFRONT_BASE_TREE = '3f69190a028d2201e1cb7900434417db1b34ed7d'
UX08_STOREFRONT_SOURCE = 'fa4185d42022b8f6296f358e05976cf2769b4b3c'
UX08_STOREFRONT_SOURCE_TREE = 'b10612dae5e1d8f85d3fee3529b4f3cf9c0d30b3'
UX08_STOREFRONT_MODIFIED = frozenset((
    'site/admin/controllers/hosting_catalog.php',
    'site/admin/views/hosting_catalog.php',
    'site/app/clients/public_identity.php',
    'site/app/hosting/catalog_login.php',
    'site/app/hosting/catalog_offer_views.php',
    'site/app/hosting/catalog_offers.php',
    'site/app/hosting/catalog_offers_context.php',
    'site/app/hosting/catalog_offers_document.php',
    'site/app/hosting/catalog_offers_storage.php',
    'site/app/providers/catalog_import.php',
    'site/app/providers/catalog_variants.php',
    'site/app/settings/database_schemas.php',
    'site/assets/css/admin.css',
    'site/assets/css/premium.css',
    'site/includes/layout/hosting_catalog.php',
    'site/includes/layout/public.php',
    'site/pages/controller.php',
    'site/pages/services/hosting.php',
    'site/portal/controllers/hosting_catalog.php',
    'site/portal/controllers/hosting_checkout.php',
    'site/portal/views/hosting_catalog.php',
    'site/portal/views/hosting_checkout.php',
    'tests/catalog_storefront_checkout_cases.py',
    'tests/database_schema_registry_policy.php',
    'tests/database_schema_view_policy.php',
    'tests/hosting_billing_options_cases.py',
    'tests/hosting_catalog_billing.php',
    'tests/hosting_catalog_billing_browser.py',
    'tests/hosting_catalog_billing_http_fixture.php',
    'tests/hosting_catalog_billing_native.php',
    'tests/hosting_catalog_comparison.php',
    'tests/hosting_catalog_comparison_browser.py',
    'tests/hosting_catalog_document.php',
    'tests/hosting_catalog_login.php',
    'tests/hosting_catalog_native.php',
    'tests/hosting_catalog_plan_setup_native.php',
    'tests/hosting_catalog_plan_setup_views.php',
    'tests/hosting_catalog_purchase_native.php',
    'tests/hosting_catalog_variants_browser.py',
    'tests/hosting_catalog_variants_contract.php',
    'tests/hosting_catalog_variants_http_fixture.php',
    'tests/hosting_catalog_variants_native.php',
    'tests/hosting_checkout_document.php',
    'tests/hosting_checkout_native.php',
    'tests/hosting_checkout_purchases_native.php',
    'tests/hosting_checkout_views.php',
    'tests/recurring-billing/checkout.php',
    'tests/recurring_billing_checkout_native.php',
    'tests/service-plans/checkout_documents.php',
    'tests/service_plans_checkout_native.php',
    'tools/integration/portable_checks.py',
))
UX08_STOREFRONT_ADDITIONS = frozenset((
    'site/app/hosting/storefront_categories.php',
    'site/app/hosting/storefront_categories_document.php',
    'site/database/migrations/hosting_storefront_migrate.php',
    'site/database/schema/hosting_storefront_schema.php',
    'site/pages/services/hosting-overview.php',
    'site/pages/services/vps-hosting.php',
    'tests/hosting_storefront_routes.php',
    'tests/hosting_storefront_schema.php',
))
UX08_INVENTORY_BASE = 'fa4185d42022b8f6296f358e05976cf2769b4b3c'
UX08_INVENTORY_BASE_TREE = 'b10612dae5e1d8f85d3fee3529b4f3cf9c0d30b3'
UX08_INVENTORY_SOURCE = '19ddbb7038ad6148295c9f2d08cb666b2950713c'
UX08_INVENTORY_SOURCE_TREE = '346585a348db5def3d3acba8bdc5519a507b80bd'
UX08_INVENTORY_SHA256 = '54f88be487638ba66e96a7c518c8a64a8d11458ca4e5a7c78661306367942482'
UX08_REVIEW_BASE = '6201a0ad224bafbed988fd29c72b1e1599dba4d8'
UX08_REVIEW_BASE_TREE = '59facd3a2e4b9e009b07dd566de1720bf45124b0'
UX08_REVIEW_SOURCE = 'de79afa5a44f7d0167f1962f77717d364d557c46'
UX08_REVIEW_SOURCE_TREE = '80432630e55382670d1444d333c1adce53066fb8'
UX08_REVIEW_MODIFIED = frozenset((
    'site/app/hosting/catalog_offers_context.php',
    'site/app/hosting/storefront_categories.php',
    'site/install/inventory.generated.php',
    'tests/acceptance_artifacts_test.py',
    'tests/hosting_catalog_billing.php',
    'tests/hosting_catalog_billing_browser.py',
    'tests/hosting_catalog_billing_http_fixture.php',
    'tests/hosting_storefront_routes.php',
))
UX08_ARTIFACT_PROOF_BASE = '50efb5e5d47db0e3fb2b770475691c9578a2f11a'
UX08_ARTIFACT_PROOF_BASE_TREE = 'da9a8f106d824ff7f7bb9a08f0f2ef105b21eb75'
UX08_ARTIFACT_PROOF_SOURCE = '01804464017ef444d9e2c24c913a14930d258f3c'
UX08_ARTIFACT_PROOF_SOURCE_TREE = '116501633cc00b6e47f58819056f0e58f4d40d68'
UX08_ARTIFACT_PROOF_PATH = 'tests/acceptance_artifacts_test.py'
UX08_FIXTURE_DEPENDENCY_BASE = 'ae53cb070b1753b2b0c639ec1187ba2bcb3ebd05'
UX08_FIXTURE_DEPENDENCY_BASE_TREE = '9c652b42feafd236816058a0f1f2576868fb1d40'
UX08_FIXTURE_DEPENDENCY_SOURCE = '7e2f608f0c1c0fea9f28d15209ceb6f7b15eab3f'
UX08_FIXTURE_DEPENDENCY_SOURCE_TREE = 'bf81d5a83003569bf220abb22031b24a1603e426'
UX08_FIXTURE_DEPENDENCY_MODIFIED = frozenset((
    'tests/hosting_catalog_billing_native.php',
    'tests/hosting_catalog_native.php',
    'tests/hosting_catalog_plan_setup_native.php',
    'tests/hosting_catalog_purchase_native.php',
    'tests/hosting_catalog_variants_native.php',
    'tests/hosting_checkout_native.php',
    'tests/hosting_checkout_purchases_native.php',
))
UX08_BROWSER_NAVIGATION_BASE = 'a52a3b4aab527aa95ca1afb6f54360caa3063534'
UX08_BROWSER_NAVIGATION_BASE_TREE = 'eac7d1e7c5cc5bdf489846e0dd07191b93870eae'
UX08_BROWSER_NAVIGATION_SOURCE = 'bdba2c03622d9de4e8fdc99ecd11820c497a7c12'
UX08_BROWSER_NAVIGATION_SOURCE_TREE = 'def24e13ff1bda9e7c2f42507a8a9344a4c0c717'
UX08_BROWSER_NAVIGATION_PATH = 'tests/release_cases.py'
UX08_BROWSER_STABILITY_BASE = '8c6e1f2b44fd2ab0717fd25b175de6bad5d8b412'
UX08_BROWSER_STABILITY_BASE_TREE = '7db87e72ba8b93b8b5153f87e7029eb3304d71fb'
UX08_BROWSER_STABILITY_SOURCE = '85173f76a7383fe1b1de42c9ba1697dafc9c41a4'
UX08_BROWSER_STABILITY_SOURCE_TREE = '17c62dff821a67da5d704a7c63c3c454facf7e59'
UX08_BROWSER_STABILITY_PATH = 'tests/release_cases.py'
UX08_BROWSER_STABILITY_SHA256 = '04308784931c7cff22a120b81453304ee105c2b5c2878e653570a5cb06d7af9f'
UX08_PAYMENT_BASE = 'ebcb7a1e52982c742996e10e2042c69764890fed'
UX08_PAYMENT_BASE_TREE = '7ccd5cd2384b5adecbd66fd98b7719a92a38887e'
UX08_PAYMENT_SOURCE = '1dd1784ef3146576b62d8f80e9676907d0b4b45a'
UX08_PAYMENT_SOURCE_TREE = 'cf06d1ddf0599a91695e2ccf880619d679a72901'
UX08_PAYMENT_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    '.github/workflows/consolidation-after-backups.yml',
    'site/admin/controllers/actions.php',
    'site/admin/controllers/hosting_catalog.php',
    'site/admin/controllers/hosting_purchases.php',
    'site/admin/controllers/router.php',
    'site/admin/views/hosting_catalog.php',
    'site/app/billing/authorize_net_client.php',
    'site/app/billing/ledger.php',
    'site/app/billing/ledger_fulfillment.php',
    'site/app/billing/software_subscription_settle.php',
    'site/app/hosting/catalog_offer_views.php',
    'site/app/hosting/checkout_purchases.php',
    'site/app/hosting/checkout_purchases_views.php',
    'site/app/hosting/orders.php',
    'site/app/settings/database_schemas.php',
    'site/install/inventory.generated.php',
    'site/portal/controllers/hosting_checkout.php',
    'site/portal/controllers/router.php',
    'site/portal/views/hosting_checkout.php',
    'tests/acceptance_artifacts_test.py',
    'tests/account_notification_workflow_contract.py',
    'tests/cloud_site_intake_wiring.py',
    'tests/combined_source_composition_test.py',
    'tests/database_schema_registry_policy.php',
    'tests/database_schema_view_policy.php',
    'tests/hosting_billing_options_cases.py',
    'tests/hosting_billing_options_wiring.py',
    'tests/hosting_catalog_plan_setup_views.php',
    'tests/hosting_checkout_http_browser.py',
    'tests/hosting_checkout_http_fixture.php',
    'tests/hosting_checkout_http_guards.py',
    'tests/hosting_checkout_native.php',
    'tests/hosting_checkout_purchases_guards.php',
    'tests/hosting_checkout_views.php',
    'tests/hosting_purchase_inbox_cases.py',
    'tools/acceptance-contract.json',
    'tools/acceptance-reports.json',
))
UX08_PAYMENT_ADDITIONS = frozenset((
    'site/app/billing/gateway_receipts.php',
    'site/app/hosting/payments.php',
    'site/database/migrations/gateway_receipts_migrate.php',
    'site/database/migrations/hosting_payments_migrate.php',
    'site/database/schema/gateway_receipts_schema.php',
    'site/database/schema/hosting_payments_schema.php',
    'tests/hosting_payment_cases.py',
    'tests/hosting_payment_native.php',
))
UX08_PAYMENT_NATIVE_FIX_BASE = '71899fa2a9380bd614c0163b8974e8e39eba22e7'
UX08_PAYMENT_NATIVE_FIX_BASE_TREE = '8288fcbbdc7d9cf7bcd15a067522502765075ab5'
UX08_PAYMENT_NATIVE_FIX_SOURCE = '0e3e44b7da454dfa9278f5524774d770d92ac64b'
UX08_PAYMENT_NATIVE_FIX_SOURCE_TREE = 'fd29018dbd98e80a26a0ee097d95099d001d92f3'
UX08_PAYMENT_NATIVE_FIX_MODIFIED = frozenset((
    'tests/hosting_payment_native.php',
    'tools/acceptance-contract.json',
))
UX08_PAYMENT_DIAGNOSTIC_FIX_BASE = 'f07cbc628482a483baf2dc9403c681d976dd5f2a'
UX08_PAYMENT_DIAGNOSTIC_FIX_BASE_TREE = 'decaa75860746339de5bbb857270a8721a5b1988'
UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE = '4d7d8590936d3ced460d55f1e2972ff66199d2fa'
UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE_TREE = 'cc6e96405471cf6f02921fd9a29e30448a53f1b2'
UX08_PAYMENT_DIAGNOSTIC_FIX_MODIFIED = frozenset((
    'tests/hosting_payment_native.php',
    'tools/acceptance-contract.json',
))
UX08_INBOX_STATE_FIX_BASE = '05359da4c279766144528465db3757bb05817f40'
UX08_INBOX_STATE_FIX_BASE_TREE = '950138189b42761fd27ef5af75449d79ed8194af'
UX08_INBOX_STATE_FIX_SOURCE = '1200cd1af38829fe0127888e6f11dc04bc7d7985'
UX08_INBOX_STATE_FIX_SOURCE_TREE = '6765efc94c65a16002add7b3dd1645e7215b82a6'
UX08_INBOX_STATE_FIX_PATH = 'tests/hosting_purchase_inbox_cases.py'
UX08_INBOX_STATE_CORRECTION_BASE = '76799a2ecc97bf4b7e481a9455b2eae08d5fb413'
UX08_INBOX_STATE_CORRECTION_BASE_TREE = 'd864771b285782828498b90b436110d4cd86f22e'
UX08_INBOX_STATE_CORRECTION_SOURCE = '0b3ef632ee3eeb728ef5c2175b06a4258f45aa53'
UX08_INBOX_STATE_CORRECTION_SOURCE_TREE = '8d5eb1087764b5ceefe6aa814020c8d0c0d7352f'
UX08_INBOX_OPERATIONS_FIX_BASE = '148555f24959948b0aa232585319ad4c4c116367'
UX08_INBOX_OPERATIONS_FIX_BASE_TREE = '9e60ab462798ab8cf16b03dd4717ddb6f2692c23'
UX08_INBOX_OPERATIONS_FIX_SOURCE = '3315e3358edc62ef1e33881176ca221f21cf83c2'
UX08_INBOX_OPERATIONS_FIX_SOURCE_TREE = '7e6243c6d039bd06dc3046318a32cfc47f3689d1'
UX08_INBOX_OPERATIONS_FIX_MODIFIED = frozenset((
    '.github/workflows/consolidation-after-backups.yml',
    'site/app/hosting/payments.php',
    'site/install/inventory.generated.php',
    'tests/hosting_checkout_http_browser.py',
    'tests/hosting_checkout_http_fixture.php',
    'tests/hosting_checkout_http_guards.py',
    'tests/hosting_checkout_native.php',
))
UX08_INBOX_RECORD_FIX_BASE = '94c340d954684302cc976440e8f3c793b9c67c9d'
UX08_INBOX_RECORD_FIX_BASE_TREE = '8097ab6983a642af5d05fa813cc84b3b43f07376'
UX08_INBOX_RECORD_FIX_SOURCE = 'd6a64ee8c2a393b2663337ef1c7b3ac272127583'
UX08_INBOX_RECORD_FIX_SOURCE_TREE = 'd876b7d52e90b9ace273852aacfc82d6036d5330'
UX08_INBOX_RECORD_FIX_MODIFIED = UX08_INBOX_OPERATIONS_FIX_MODIFIED
UX08_AGREEMENT_LINK_FIX_BASE = 'fcddf1d0b7039677dd0d55f676ea4ff8e0c15d1a'
UX08_AGREEMENT_LINK_FIX_BASE_TREE = 'b29eea35661896fe804644270a8df5d4e7e67185'
UX08_AGREEMENT_LINK_FIX_SOURCE = 'ae3db0a92735085b9161b22730b9bd42102b67a9'
UX08_AGREEMENT_LINK_FIX_SOURCE_TREE = '88ce5c8f39b210cbc77dcd412bd43f35f335f9fa'
UX08_AGREEMENT_LINK_FIX_MODIFIED = frozenset((
    'site/app/hosting/checkout_purchases_views.php',
    'site/install/inventory.generated.php',
    'tests/hosting_purchase_inbox_cases.py',
    'tests/hosting_purchases_views.php',
))
UX08_AGREEMENT_DUPLICATE_FIX_BASE = '5d9e39e6629163edddb93d7de00ea70cb2775360'
UX08_AGREEMENT_DUPLICATE_FIX_BASE_TREE = 'cd92dae60dd267500645da126a9fc6fa849ddb16'
UX08_AGREEMENT_DUPLICATE_FIX_SOURCE = '4dc278286c309239a9350ba4fa18bc1726008652'
UX08_AGREEMENT_DUPLICATE_FIX_SOURCE_TREE = '0e5d28540c62061ebb19f011278fae0cbad730e9'
UX08_AGREEMENT_DUPLICATE_FIX_MODIFIED = frozenset((
    'site/app/hosting/checkout_purchases_views.php',
    'site/install/inventory.generated.php',
    'tests/hosting_purchases_views.php',
))
UX08_INBOX_NETWORK_FIX_BASE = 'b6bbadc61a515aed37abb636aceef035aa74c165'
UX08_INBOX_NETWORK_FIX_BASE_TREE = 'a4c45794c8d5559727fecc4298aa7e2da00a5add'
UX08_INBOX_NETWORK_FIX_SOURCE = '8639e019f87b632e22b2523648d64e76632cf42e'
UX08_INBOX_NETWORK_FIX_SOURCE_TREE = 'cc31dd823e65ba7ad0390dff9e3ce6bf1543485b'
UX08_INBOX_NETWORK_FIX_PATH = 'tests/hosting_purchase_inbox_cases.py'
UX08_BILLING_NETWORK_FIX_BASE = 'f7a7c6e1b8a6853becdfc4698d22481d6b495f96'
UX08_BILLING_NETWORK_FIX_BASE_TREE = '990eb62baf9cb109f03f645dde30c468091ed451'
UX08_BILLING_NETWORK_FIX_SOURCE = '4e3512772553709d5c515dd7b85227dc23417905'
UX08_BILLING_NETWORK_FIX_SOURCE_TREE = '27010185b4799dc1813b8ad72fd2720f447fe39b'
UX08_BILLING_NETWORK_FIX_PATH = 'tests/hosting_billing_options_cases.py'
UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE = 'e1fb72035bec339435d1e6ab5c7b2654d0a5026f'
UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE_TREE = 'c24b845a33ed3f04de1720d18f63c53bcb89f701'
UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE = '1eb4497ba15ca737f0c032c09a5c3576f53d3f35'
UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE_TREE = '76ab2b7d79f7f861429161f0981e4176f57f5274'
UX08_PAYMENT_FIXTURE_LOCK_FIX_MODIFIED = frozenset((
    'tests/hosting_checkout_http_fixture.php',
    'tests/hosting_checkout_http_guards.py',
))
UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE = '1ded2d4eacc33d55aa722b70447417bc997d4316'
UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE_TREE = 'e4e7ca2ab0c11a564c372fc7eb120b8ef1da5baa'
UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE = 'fa38cb8d335046fdfb0a386e48961c0edfec512b'
UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE_TREE = '88f0cb1e7a5873c659aadda2b86b985b7cf1db87'
UX08_PAYMENT_PUBLICATION_PIN_FIX_MODIFIED = frozenset((
    'tests/hosting_checkout_http_fixture.php',
    'tests/hosting_checkout_http_guards.py',
))
UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE = '066f8f5b791acaddadc10a5e2274971c5a11a42f'
UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE_TREE = '2a49c5e3d0c1f11ce685c5d5b67bb582e03b4ee5'
UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE = '7a9278ecaeb52f8bdedc3abb002ae3db4d02d77f'
UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE_TREE = '8d8c1464167acf31267e413ddba1a43a86eab442'
UX08_PAYMENT_CURRENT_PUBLICATION_FIX_MODIFIED = frozenset((
    'tests/hosting_checkout_http_browser.py',
    'tests/hosting_checkout_http_fixture.php',
    'tests/hosting_checkout_http_guards.py',
    'tests/hosting_payment_cases.py',
))
UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE = '5a6df46d0167dbcbf8d2c1adb59da1f8cd08501c'
UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE_TREE = '54f80ab712add0a8d764f6c8275423ed90016ebb'
UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE = '9a726b4431b077096bd3a4d1e2755d208b793d16'
UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE_TREE = '55c0424d14009e39fa93c7afb1be59b731abc3ae'
UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_PATH = 'tests/acceptance_artifacts_test.py'
UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE = '664ae9caae50d8b3ad90380fb9f19d5359e45aba'
UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE_TREE = '6aee69485150129560d5b20f2ad5a51c66409c27'
UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE = '62a3f1d97bb46b8ba3d92542ed3d03ff705a2012'
UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE_TREE = '222c744e4518f22c1c42485926218a6cda8a1db6'
UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_MODIFIED = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/hosting_checkout_http_guards.py',
    'tests/hosting_payment_cases.py',
))
UX08_PAYMENT_FORM_LABEL_FIX_BASE = 'a561bd7537fc02d8d32968a16b81028b6a7f2571'
UX08_PAYMENT_FORM_LABEL_FIX_BASE_TREE = '293611768e9b73902456099efb50c9fe6e266687'
UX08_PAYMENT_FORM_LABEL_FIX_SOURCE = '229fc1e1f67e84ee00a32cc3272754003aaddd5f'
UX08_PAYMENT_FORM_LABEL_FIX_SOURCE_TREE = '65bcacc98c0be0f5ef8a715c6c9dd16db82bd406'
UX08_PAYMENT_FORM_LABEL_FIX_MODIFIED = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/hosting_checkout_http_guards.py',
    'tests/hosting_payment_cases.py',
))
UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE = '7cf5904d8b8c866921e45853716e2a3164627cf1'
UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE_TREE = '7bd3325868715bee98c111e92f58c11bcfb14c66'
UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE = '0af7957544b3dcbd12ba7be2e4b536123b12e91a'
UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE_TREE = '29aa86a37a404396367e24f99eac41965b0fc136'
UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_MODIFIED = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/hosting_checkout_http_fixture.php',
))
UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE = 'deeb28699df09bd3d52c21e3f4c867735b431f54'
UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE_TREE = '21b2bbf55456c7248360668f68bccdcfca6deb60'
UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE = '90ebe4f4fc6950bc0adcbc0155fb07e1c77c3d42'
UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE_TREE = 'f3119926a910e22167f9e93a5130114b82c407ab'
UX08_PAYMENT_FULFILLMENT_LOCK_FIX_MODIFIED = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/hosting_checkout_http_fixture.php',
))
UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE = '3fe8b8375b82640b9f6d2e05260032cc6cbb9b72'
UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE_TREE = '0ce09dc7e16832180eeac4d427d2be6aee9a62c3'
UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE = '25caea4546ef7aa3c78bf52b35d7a4ab028923c1'
UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE_TREE = '953f8352516e03645664250c65b99b54d670ff5b'
UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_MODIFIED = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/hosting_checkout_http_fixture.php',
))
UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE = '23ecdb4cbbcccdc14507727829fc52a0f782e7a4'
UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE_TREE = 'df00a9ebf4d44e56febae02ec2a66753eaf4ad05'
UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE = '3dc35fc9503ee3048da214bebfd9a1ad37ef6d17'
UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE_TREE = '83188bd9846a1f4cf4e788bee7615110957228d5'
UX08_PAYMENT_OUTCOME_PROOF_FIX_MODIFIED = frozenset((
    '.github/workflows/consolidation-after-backups.yml',
))
UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE = 'a2c4e8067fe279a31aa51c8793cce8693c857aca'
UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE_TREE = 'afd3e701dfa2f4334931ad33194d0c9dac865d29'
UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE = '73fb770254a5dc4a1da46f0e0489251d3b30c3ab'
UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE_TREE = 'e7f0c2bbe7c9ceb9e7e119b34f8fe197c2210572'
UX08_HOSTED_MIRROR_CONTRACT_FIX_MODIFIED = frozenset((
    'tests/acceptance_artifacts_test.py',
))
PR388_ACCEPTED_HEAD = 'b7109264486127f8fbbdd4a3f37f695d03caa058'
PR388_ACCEPTED_TREE = '0dc0eb0a8c0595423b5ceaf50cb8f1a10a3296bf'
PR388_ACCEPTED_BASE = 'ebcb7a1e52982c742996e10e2042c69764890fed'
PR388_RESULTING_MAIN = 'cbffec34eaea73942514c799f0622959f0f9a4c0'
PR388_RESULTING_MAIN_TREE = '0dc0eb0a8c0595423b5ceaf50cb8f1a10a3296bf'
PR391_RECOVERY_SOURCE = '511a5d91627374878780a8dbb800d51b24d8a087'
PR391_RECOVERY_SOURCE_TREE = '31be156cbd38bac2e49ba7aedcb0c0d61b78cce1'
PR391_RECOVERY_HEAD = '331572f0de664a50c0a3b1df7cd671bb38e9df73'
PR391_RECOVERY_HEAD_TREE = '713c63e742c524be1ebd9c8ea3a0ee71bf3392ff'
RUNNER_AWS_FULL_MIRROR_BASE = '9c44df1a3a0da13f1a9ecd1e51a58ffed42314ab'
RUNNER_AWS_FULL_MIRROR_BASE_TREE = '3c524240336cb6bb37374c9013d20bd7a6750c2f'
RUNNER_AWS_FULL_MIRROR_SOURCE = '000d423c141483b33bde0f5e849c52764fce873b'
RUNNER_AWS_FULL_MIRROR_SOURCE_TREE = 'cb3f6d35644436ed9c29477fe5c0862f27f097dc'
RUNNER_AWS_FULL_MIRROR_MODIFIED = frozenset((
    '.github/scripts/prepare_ubuntu_apt.py',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_MIRROR_COMPAT_BASE = '161cc51dd5610f8166c002fe1121c178f996ec84'
RUNNER_MIRROR_COMPAT_BASE_TREE = '35e38293bc31a8e3307884db72b931565630b0b7'
RUNNER_MIRROR_COMPAT_SOURCE = '0f9bceed32a9f4836671bd226f3582c844a6b32f'
RUNNER_MIRROR_COMPAT_SOURCE_TREE = '4e93055be01df7c3f3ef7435646551496927956c'
RUNNER_MIRROR_COMPAT_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    '.github/workflows/consolidation-after-backups.yml',
    'tests/acceptance_artifacts_test.py',
    'tests/account_notification_workflow_contract.py',
    'tests/hosting_checkout_http_guards.py',
))
RUNNER_MIRROR_PHASE_BASE = '677a980897fdbed500bc96ecfaf8268456aba75f'
RUNNER_MIRROR_PHASE_BASE_TREE = '98b8127f63e69a3f841a942211776c88c9de0409'
RUNNER_MIRROR_PHASE_SOURCE = '40fe97f113c0996171a20b6107ca5f8922f15967'
RUNNER_MIRROR_PHASE_SOURCE_TREE = '0c8acab822200ac9e7eb01b3c0a5c0235f5aa970'
RUNNER_MIRROR_PHASE_MODIFIED = frozenset((
    '.github/workflows/account-notifications-acceptance.yml',
    '.github/workflows/application-tests.yml',
    '.github/workflows/consolidation-after-backups.yml',
    'tests/acceptance_artifacts_test.py',
    'tests/account_notification_workflow_contract.py',
    'tests/hosting_checkout_http_guards.py',
))
RUNNER_MIRROR_PHASE_ADDITIONS = frozenset((
    '.github/scripts/prepare_ubuntu_apt.py',
))
RUNNER_KEYRING_ISOLATION_BASE = '9bc274f6fb71d1ef3bf20d110e2be986c2b5d833'
RUNNER_KEYRING_ISOLATION_BASE_TREE = '47c9e065857f494c02de1a0f688e3c65af88c925'
RUNNER_KEYRING_ISOLATION_SOURCE = '32bd7e9f6cb3da54921a291318a72a1ba99b8542'
RUNNER_KEYRING_ISOLATION_SOURCE_TREE = '2bc03ae11a775a64519c3fee42576010219e66a0'
RUNNER_KEYRING_ISOLATION_MODIFIED = frozenset((
    '.github/scripts/prepare_ubuntu_apt.py',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_KEYRING_FIXTURE_BASE = '3044c7636e20553f027ebfc8d5e8a0d9f8e07125'
RUNNER_KEYRING_FIXTURE_BASE_TREE = 'c1cf56dd9704e50d8003fff1686a6664390f7808'
RUNNER_KEYRING_FIXTURE_SOURCE = '52c609694cc90f37de3cc69c7002513eeb56bb0e'
RUNNER_KEYRING_FIXTURE_SOURCE_TREE = 'cfc9d688b87b627249b550457877b4d5db8ac7c4'
RUNNER_KEYRING_FIXTURE_MODIFIED = frozenset((
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_COMPLETION_BASE = '1b731513bdf7f78d7d636b280e615705089a2eed'
RUNNER_APPLICATION_COMPLETION_BASE_TREE = '30d6d989f8bbd94a92b1bf0530ecb5c1e81f9682'
RUNNER_APPLICATION_COMPLETION_SOURCE = '280c8f1517a9a57e9618cf350f99575450cdcef9'
RUNNER_APPLICATION_COMPLETION_SOURCE_TREE = '8e806ed965e598f2ca0b4ca6535db4ff0b7a03ca'
RUNNER_APPLICATION_COMPLETION_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'tests/acceptance_artifacts_test.py',
    'tests/account_notification_workflow_contract.py',
    'tools/acceptance_artifacts.py',
))
RUNNER_EVIDENCE_PROVENANCE_BASE = '826a0e28b874e97d74c74395b3a86187d57564d1'
RUNNER_EVIDENCE_PROVENANCE_BASE_TREE = 'dda977bdbaf83a1f7169e346f382b97ac8fd5cc5'
RUNNER_EVIDENCE_PROVENANCE_SOURCE = 'd166f2bc135fd818ac9683ab604a100a3e1f0786'
RUNNER_EVIDENCE_PROVENANCE_SOURCE_TREE = '5ff7375a9bea1cff8ccf97f0c6a0a1c995660d6c'
RUNNER_EVIDENCE_PROVENANCE_MODIFIED = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/delivery_evidence_cases.py',
    'tools/acceptance_artifacts.py',
))
RUNNER_AWS_PROFILE_BASE = 'b5ee60bc321e5f604465e546d7a5c780db201e4b'
RUNNER_AWS_PROFILE_BASE_TREE = 'b9c3bc0ad6c6f2f437a5cd23a913fc7de3653a11'
RUNNER_AWS_PROFILE_SOURCE = 'ceab9f20960b915d5b1082d3af3d11f3e177763e'
RUNNER_AWS_PROFILE_SOURCE_TREE = '6014aaa19e49f43f12cddeb4628f61bcc45d02bb'
RUNNER_AWS_PROFILE_MODIFIED = frozenset((
    '.github/scripts/prepare_ubuntu_apt.py',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_SOURCE_BINDING_BASE = 'd40c02039877585bb889c679479d92ec08fc197b'
RUNNER_APPLICATION_SOURCE_BINDING_BASE_TREE = '838fc3920b2c772aa111aac2240563d59fdbb0f6'
RUNNER_APPLICATION_SOURCE_BINDING_SOURCE = '6d32b53dc9719bd0f6937411ee8fc395be2dfe2e'
RUNNER_APPLICATION_SOURCE_BINDING_SOURCE_TREE = 'cfdd44101260e6d47a11281f7e0a43499d32df95'
RUNNER_APPLICATION_SOURCE_BINDING_MODIFIED = frozenset((
    'tests/cloud_site_requests_wiring.py',
    'tools/acceptance-contract.json',
))
RUNNER_APPLICATION_HISTORICAL_BINDING_BASE = 'd3cb6b8d1bfee92eccba3f539189274b93a44031'
RUNNER_APPLICATION_HISTORICAL_BINDING_BASE_TREE = '5b94b2fba94a1ef60e19bb2eb616e7d91056c335'
RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE = '088c2c1f47bd190d4c93d1a2f55695cade8f6205'
RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE_TREE = 'be713473b8e56773d1d650c9b45c06e005ac7fc5'
RUNNER_APPLICATION_HISTORICAL_BINDING_MODIFIED = frozenset((
    'tests/cloud_site_requests_wiring.py',
))
RUNNER_AWS_MIRROR_PROFILE_BASE = 'cc10c6c515ede8145a6ceb43449f855f6300bcc6'
RUNNER_AWS_MIRROR_PROFILE_BASE_TREE = '91061082b27f44d1906e5ee7603b0fc72110c45b'
RUNNER_AWS_MIRROR_PROFILE_SOURCE = '33c8e484ee0592ebd39c49aefd21230b52c28ca3'
RUNNER_AWS_MIRROR_PROFILE_SOURCE_TREE = '8d4dd430dc181a5227fdc183671924cdcb2a55af'
RUNNER_AWS_MIRROR_PROFILE_MODIFIED = frozenset((
    '.github/scripts/prepare_ubuntu_apt.py',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_INTAKE_BINDING_BASE = '33c8e484ee0592ebd39c49aefd21230b52c28ca3'
RUNNER_APPLICATION_INTAKE_BINDING_BASE_TREE = '8d4dd430dc181a5227fdc183671924cdcb2a55af'
RUNNER_APPLICATION_INTAKE_BINDING_SOURCE = '89d0b44b72b67efd05d9a80a2e4c739de6637c25'
RUNNER_APPLICATION_INTAKE_BINDING_SOURCE_TREE = '0389530dcf6d01fdc519490542833ebc0ce35981'
RUNNER_APPLICATION_INTAKE_BINDING_MODIFIED = frozenset((
    'tests/cloud_site_intake_wiring.py',
))
RUNNER_AWS_MIRROR_FIXTURE_BASE = 'efbe26f8c60428267483ef811882bf6bf5ed4036'
RUNNER_AWS_MIRROR_FIXTURE_BASE_TREE = '0c66e4ae972a187674f4d91d883674fe883de066'
RUNNER_AWS_MIRROR_FIXTURE_SOURCE = '53cce48017b91bcf4a33c76488ae3e258f27a04f'
RUNNER_AWS_MIRROR_FIXTURE_SOURCE_TREE = '7e64105de0fc15be15e58910f3f0c4eb1806a4a7'
RUNNER_AWS_MIRROR_FIXTURE_MODIFIED = frozenset((
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_INTAKE_PROJECTION_BASE = '53cce48017b91bcf4a33c76488ae3e258f27a04f'
RUNNER_APPLICATION_INTAKE_PROJECTION_BASE_TREE = '7e64105de0fc15be15e58910f3f0c4eb1806a4a7'
RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE = 'fe50a214966847aefa846ce026aa6eaa22f80a87'
RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE_TREE = '05c877c125388e072cc73e05846a65717d561848'
RUNNER_APPLICATION_INTAKE_PROJECTION_MODIFIED = frozenset((
    'tests/cloud_site_intake_wiring.py',
))
RUNNER_INTAKE_CURRENT_BINDING_BASE = '8c6807a04b7ed96dc86ed9045a1b73c4f53083e3'
RUNNER_INTAKE_CURRENT_BINDING_BASE_TREE = 'befaf8822ab12b4ecf2aba2513bdfc8d4b4a3076'
RUNNER_INTAKE_CURRENT_BINDING_SOURCE = '980ed0693f74e1ea9b13b1286f7fe03cedfae80f'
RUNNER_INTAKE_CURRENT_BINDING_SOURCE_TREE = 'cdb2a13b1c322d39f268ce0756f372723f245ca3'
RUNNER_INTAKE_CURRENT_BINDING_MODIFIED = frozenset((
    'tools/acceptance-contract.json',
))
RUNNER_FINITE_TOOL_REFUSAL_BASE = 'e136fd53e0e742acdb99c39ab343f398b63cb20d'
RUNNER_FINITE_TOOL_REFUSAL_BASE_TREE = '65561b984c34add8d3407ad87880f9696f37e796'
RUNNER_FINITE_TOOL_REFUSAL_SOURCE = '815db336cb013e3c745af8d6741e6bbbef616175'
RUNNER_FINITE_TOOL_REFUSAL_SOURCE_TREE = '869dbcc8964168fb426b97d896962ae0c1e590ea'
RUNNER_FINITE_TOOL_REFUSAL_MODIFIED = frozenset((
    'tests/combined_source_composition_test.py',
))
RUNNER_ACCOUNT_SOURCE_BUDGET_BASE = '6a24302bbc9d108964d2c051affb84ff79a297e9'
RUNNER_ACCOUNT_SOURCE_BUDGET_BASE_TREE = 'c1fee0fde234546dfbba5f6a04805888d9094eb3'
RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE = '1599cc73a238462028dd962fad8818ec07095e68'
RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE_TREE = '5e3cff7e64d297920bac4414e0794a48d8beb0c9'
RUNNER_ACCOUNT_SOURCE_BUDGET_MODIFIED = frozenset((
    '.github/workflows/account-notifications-acceptance.yml',
))
RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE = 'c45be42591ebd3295c929970b52662585fe4155f'
RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE_TREE = '46cb7b37a0acda3712128b44f6577e74b055bbc1'
RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE = 'c7cbc62d977d7612fa016b044058cfad60fcf353'
RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE_TREE = '7dd903aabef4ee94bcfc8ad693697ce0b66f2629'
RUNNER_ACCOUNT_SOURCE_COMPLETION_MODIFIED = frozenset((
    '.github/workflows/account-notifications-acceptance.yml',
))
RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE = '89d7602f9e338a21922b18345c1c1cd548a110f8'
RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE_TREE = '76b9657ed92bd2b4c9809051ca2939a968bfbddf'
RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE = '6d57621e67f43543906aba54d05bf8a42093bb82'
RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE_TREE = 'e214aa19b186a453484b7023f064081084dad5bd'
RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_MODIFIED = frozenset((
    '.github/workflows/account-notifications-acceptance.yml',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE = '410edaa0c97ad2c322ade7b35191a0fa8a4f0a6a'
RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE_TREE = '53331d93e10c1d44dd79b99faa2046bd4377b1f0'
RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE = '7087026561ce4c90121c2ac24f17d186b55bbff2'
RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE_TREE = '71e60fedc5de54794d1cce9f5109130dde9e4c72'
RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_MODIFIED = frozenset((
    '.github/workflows/account-notifications-acceptance.yml',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE = 'bfed1d429ed31714c6e38673208f5bf27edfba4c'
RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE_TREE = '18fb08497b8eb1505558a54c89d8f071544d410c'
RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE = 'a6dfcbbee6ece30e65f4d40f7be761eefaf4ccae'
RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE_TREE = '554f545ff8382dbae1624338c8279802164e27d2'
RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_MODIFIED = frozenset((
    '.github/workflows/account-notifications-acceptance.yml',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_BUILD_BUDGET_BASE = '0382da0e1b8d55ae1edc921b985cb569ea53823d'
RUNNER_APPLICATION_BUILD_BUDGET_BASE_TREE = 'acb09bb392c96cd83c40561c966f46f4dedc81df'
RUNNER_APPLICATION_BUILD_BUDGET_SOURCE = '7e108b483e0ebeb39a42f670c42f20d9f54a04f8'
RUNNER_APPLICATION_BUILD_BUDGET_SOURCE_TREE = '118600d3e9c9ee071c14428f6d17f7a8ed01341f'
RUNNER_APPLICATION_BUILD_BUDGET_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_BUILD_COMPLETION_BASE = 'c45be42591ebd3295c929970b52662585fe4155f'
RUNNER_APPLICATION_BUILD_COMPLETION_BASE_TREE = '46cb7b37a0acda3712128b44f6577e74b055bbc1'
RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE = '2d2c214805f6d26459794538badc6a16df5d20eb'
RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE_TREE = '705294538c2c097cb77adb913adba84e8934d6aa'
RUNNER_APPLICATION_BUILD_COMPLETION_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_SEAL_BUDGET_BASE = 'f95bd524b2793a7faa253a0a5c8fc87796df1f45'
RUNNER_APPLICATION_SEAL_BUDGET_BASE_TREE = '42a88f5bbae9fef6b01b1fe1b59427c97c86e84e'
RUNNER_APPLICATION_SEAL_BUDGET_SOURCE = '7baab8574ecd671bfae3fb1924e812a095a63f1b'
RUNNER_APPLICATION_SEAL_BUDGET_SOURCE_TREE = '5941b9734caa1f1a09a92fc4143e456693558dc5'
RUNNER_APPLICATION_SEAL_BUDGET_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE = '6efedfc1cc81db2f95146c69dab1b809b317c2f9'
RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE_TREE = 'ab246aed3a741057243c0b787de22a8660a841b1'
RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE = 'cd40a6c6d004ff0fdb70767029afbc5dd227bf8d'
RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE_TREE = '4b3f15481149e07043ab5cddc94ba23575f91ad7'
RUNNER_APPLICATION_COMPOSITION_COMPLETION_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'tests/account_notification_workflow_contract.py',
))
RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE = '592e79d55935c0fd01d5e05c1663ea6077faac6e'
RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE_TREE = '6e616c7ac9c9faafe638cf415c2daacedf159ee1'
RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE = '990320c6a17fa504554df7e3033df5a5fbfcfc81'
RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE_TREE = 'e7b4a6b1a5e671f585c4adcbcc44db1d7b62d217'
RUNNER_APPLICATION_COMPOSITION_PARTITION_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'tests/acceptance_artifacts_test.py',
    'tests/account_notification_workflow_contract.py',
    'tests/delivery_evidence_cases.py',
    'tools/acceptance_artifacts.py',
))
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE = 'dc216cbcab71d3e2b74c7086c9c21647bdd54e12'
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE_TREE = '439c323d42978eefad8f854e22fd462456984e5e'
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE = '4c41661b839e02a884abc40cf83c8acc04b373d9'
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE_TREE = 'f1044b109d67845384150f566cacf9a87fac2692'
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_PATH = 'tools/acceptance-contract.json'
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE_SHA256 = 'c19d6fe13fc3b48d04459e60fc2d43f6ad87570a8fd2b9e54a73d8f397a8abc8'
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE_SHA256 = 'be671f70ce28a413e39b85beaf1297803e8d919e1c78149ca6cc1dcb33c7c165'
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_OLD_SHA256 = 'c4885f25bf27faa9be827dadba04de38ec6ae68d2529213a780d2cad87a428d0'
RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_NEW_SHA256 = '5e43a8242244918200e2aee75175bd68f895d3a054239a17c9f85ae0b03b56c9'
APPLICATION_SMTP_FIXTURE_BOUND_BASE = 'efa50cd92d82bf62dcaea68fe45604eb4fe032a8'
APPLICATION_SMTP_FIXTURE_BOUND_BASE_TREE = '51a8d7d4a1eae6afead1dd9ede95ff8d3ce39aa9'
APPLICATION_SMTP_FIXTURE_BOUND_SOURCE = '21af41f2a258beafa4d11f8f91f7cabd198fc554'
APPLICATION_SMTP_FIXTURE_BOUND_SOURCE_TREE = 'd3112ffeba4c9b0cfe7bf604c78092795def79ec'
APPLICATION_SMTP_FIXTURE_BOUND_PATH = 'tests/mail_submission_server.py'
APPLICATION_SMTP_FIXTURE_BOUND_BASE_SHA256 = 'fb7b6d970aaf1c3bd40f3a071d795ca54b3ef76a2468902dbe6abc99e117cdab'
APPLICATION_SMTP_FIXTURE_BOUND_SOURCE_SHA256 = 'fd2d691efd71b79e5e60628d9c6d913ff211682fbf58c7445ff002495bbb1e61'
APPLICATION_SENT_COPY_FIXTURE_MODE_BASE = 'a84595a2be9550b03a86132fc05e5607553468b9'
APPLICATION_SENT_COPY_FIXTURE_MODE_BASE_TREE = '2055621726acce690e4c4b0d6581130cd80abac1'
APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE = 'b18c493d98a1197363546c3cafecb07504766c4c'
APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE_TREE = '2e9a35206ee6a88560745f1c7ba4a1347512cfcc'
APPLICATION_SENT_COPY_FIXTURE_MODE_PATH = 'tests/mail_submissions_cases.py'
APPLICATION_SENT_COPY_FIXTURE_MODE_BASE_SHA256 = '6306bdaf60ba85ba239952e2839a84c2338c1de9d6978c8ed9c325e518c88261'
APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE_SHA256 = 'ad53caa0a838312f18844d72192a04cbd75e6f35276ded20bb1f34a8fa73d21e'
APPLICATION_REPLY_FOLDER_ROUTE_BASE = 'e83180bf22b3ee3f1586f524f77ed89e443c216e'
APPLICATION_REPLY_FOLDER_ROUTE_BASE_TREE = 'f6b86fac4b5c9be5b900d829d50eea2dd1c27350'
APPLICATION_REPLY_FOLDER_ROUTE_SOURCE = '2758b70e84ed3bd75e7fdc9a42eddb9e220ac3ad'
APPLICATION_REPLY_FOLDER_ROUTE_SOURCE_TREE = '121b75b7abf168692433032b5cddeeebddca0d95'
APPLICATION_REPLY_FOLDER_ROUTE_PATH = 'tests/mail_reply_cases.py'
APPLICATION_REPLY_FOLDER_ROUTE_BASE_SHA256 = '8acac68723100447290889511af4e0c072bb2730e17f28fba0fb2d53e558846c'
APPLICATION_REPLY_FOLDER_ROUTE_SOURCE_SHA256 = '0f4a388121e410769993b76afc14214aab709552e3291e41810af8b23bc2ad72'
ACCOUNT_RATE_BOUNDARY_BASE = 'f8e85fd886b1a011c092a22948812fe1c6b8a563'
ACCOUNT_RATE_BOUNDARY_BASE_TREE = '51a63ca1932ec5029584c53b18e89f65f47fe760'
ACCOUNT_RATE_BOUNDARY_SOURCE = '1225f25c70668b43edf5fbc43e3455196eee7091'
ACCOUNT_RATE_BOUNDARY_SOURCE_TREE = '348c6764efb3b626edc6369d3391ee204e683d95'
ACCOUNT_RATE_BOUNDARY_MODIFIED = frozenset((
    'tests/account_notification_browser.py',
    'tests/account_security_browser.py',
))
APPLICATION_MAIL_COPIES_SETUP_BASE = '63564b955f1ec82bcd6b13c23630234f940fac7a'
APPLICATION_MAIL_COPIES_SETUP_BASE_TREE = '6e73ec0f20fb20f9b955750ea38147ca6d5355e0'
APPLICATION_MAIL_COPIES_SETUP_SOURCE = '13c9d7d1255ca72bc62e53c87e4087bac7515b78'
APPLICATION_MAIL_COPIES_SETUP_SOURCE_TREE = 'abd329530e1ed394b925863cef7bf3dde6ebae9c'
APPLICATION_MAIL_COPIES_SETUP_PATH = 'tests/mail_submissions_cases.py'
APPLICATION_MAIL_COPIES_ROUTING_BASE = '03fea7c3c4897bfda480d4c7d062c436a5250a21'
APPLICATION_MAIL_COPIES_ROUTING_BASE_TREE = 'c6853b1e1bde35b7e26f51a6bd93b9478a3545cf'
APPLICATION_MAIL_COPIES_ROUTING_SOURCE = 'f146383883d4ff218d54dd31e3dffe3a4ee677f7'
APPLICATION_MAIL_COPIES_ROUTING_SOURCE_TREE = 'ec8aa5261a0a18d9d53c8a5053adf0ab4a3bd4af'
APPLICATION_MAIL_COPIES_ROUTING_MODIFIED = frozenset((
    'site/admin/controllers/actions.php',
    'site/admin/controllers/mail_accounts.php',
    'site/app/staff/accounts.php',
    'site/install/inventory.generated.php',
    'tests/mail_submission_forms.php',
    'tests/mail_submissions_cases.py',
))
APPLICATION_HTTP_EXPIRY_FIX_BASE = 'e09d9473787a6d9b6f6462aed4d46cd45b292686'
APPLICATION_HTTP_EXPIRY_FIX_BASE_TREE = 'd7e7c544c5c367a918bcc209e15ce86ccccd9ddb'
APPLICATION_HTTP_EXPIRY_FIX_SOURCE = 'ae5ae3339e7e6baf21208194989589da70988823'
APPLICATION_HTTP_EXPIRY_FIX_SOURCE_TREE = 'ca8db018400d962a4fa591e8739019adc4b9ca8a'
APPLICATION_HTTP_EXPIRY_FIX_PATH = 'tests/mail_submissions_cases.py'
SIGNED_PPA_LABEL_BASE = '975a19001ffd4fa2ef2c0e5976f3c2e5986e9338'
SIGNED_PPA_LABEL_BASE_TREE = '154720dcd38df56a8114c30ff2a10ed42df1f926'
SIGNED_PPA_LABEL_SOURCE = '4ebe7755a9f27a2ba9b83cf4f50d9f474fd97844'
SIGNED_PPA_LABEL_SOURCE_TREE = '2cc31e448b1907a0df5cb10b92551fd3abd6205a'
SIGNED_PPA_LABEL_MODIFIED = frozenset((
    '.github/scripts/prepare_ubuntu_apt.py',
    'tests/account_notification_workflow_contract.py',
))
SIGNED_PPA_LABEL_SCRIPT_SHA256 = '1de78d6a6104380d39b9977a4285e18490defea7fc4a60ff1b5227a30945b562'
MAIL_FOLDER_DYNAMIC_PORT_BASE = 'f779ba5ec7e983367041f3323472ee9f3c08f095'
MAIL_FOLDER_DYNAMIC_PORT_BASE_TREE = 'e95239d6fa15603ec5b955f131969af25c9b1203'
MAIL_FOLDER_DYNAMIC_PORT_SOURCE = 'c87c0499a95cf3d58504d203517299b77b9ab317'
MAIL_FOLDER_DYNAMIC_PORT_SOURCE_TREE = '9e26aee0094d0a2ebdb217241efab56f4fb8e021'
MAIL_FOLDER_DYNAMIC_PORT_MODIFIED = frozenset((
    '.github/workflows/mail-folder-native.yml',
    'tests/mail_folder_native_workflow_contract.py',
))
RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE = '56c658594ba6f757a0f21ddcd83aff1518d78cb2'
RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE_TREE = 'b996cfcaa9b293d0ba669eadfcaeb944afc77a8b'
RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE = '6e067dfd7419913d5a2c0f89aa84bf4ecb3a9825'
RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE_TREE = 'c914d21d844f1b5d9c5238f3016f9654fec0a5bd'
RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_PATH = 'tests/hosting_purchase_inbox_cases.py'
RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE = '440d269f3dd2a81df71877859582b0977bc3feee'
RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE_TREE = 'ff8543cabdaee2cb1cc2b0e65ce991e415831df2'
RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE = 'a13eff58a9d0e934ecfc403c8bc26d3c8622a111'
RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE_TREE = '7f71774d072a8340e9d1c9d958753e9970f99f85'
RESULTING_MAIN_INBOX_CLICK_BUDGET_MODIFIED = frozenset((
    'tests/hosting_purchase_inbox_cases.py',
))
RESULTING_MAIN_INBOX_CLICK_BUDGET_ADDITIONS = frozenset((
    'docs/agent-handoffs/2026-11-15-codex-pr394-inbox-navigation.md',
))
NOTIFICATION_HEALTH_BASE = 'ab35aea5f9e746405dd99075482060b15c3debf9'
NOTIFICATION_HEALTH_BASE_TREE = '46692f1243c586d795f0713e7b5d0a69c861f398'
NOTIFICATION_HEALTH_COMPONENT = '5a9dff61acf4abe01fa8fedd2647226e98f5dbc1'
NOTIFICATION_HEALTH_COMPONENT_TREE = 'd5d2016406a7782f44c43b16ae138fc23c2f5418'
NOTIFICATION_HEALTH_ROUTER = 'bef7951338b084322764897174648fa3517d18b8'
NOTIFICATION_HEALTH_ROUTER_TREE = '81e3c9c648e91b391e17817c335f52a666f130ee'
NOTIFICATION_HEALTH_SOURCE = 'b7c79aa96dc7d9ed81c7982c989eeba5cb76e388'
NOTIFICATION_HEALTH_SOURCE_TREE = 'b942f63474b73e51f9800798d3a5c54e73190ef1'
NOTIFICATION_HEALTH_MODIFIED = frozenset((
    'site/admin/controllers/router.php',
    'site/admin/views/health.php',
    'site/install/inventory.generated.php',
    'tests/notification_center_browser.py',
    'tests/notification_center_native.php',
))
NOTIFICATION_HEALTH_ADDITIONS = frozenset((
    'docs/agent-handoffs/2026-11-16-codex-notification-health-component.md',
    'tests/notification_health_view_contract.php',
))
MAIL_REPLY_FORWARD_BASE = '0a713da7b280cb3f2ec873eb419fed025d2c6dec'
MAIL_REPLY_FORWARD_BASE_TREE = '21ba22ff7a96b70167a51ac217f74476b7a00217'
MAIL_REPLY_FORWARD_PREPARED = 'f592ce999b1e9d99a6a94e0865e18f12393ffec9'
MAIL_REPLY_FORWARD_PREPARED_TREE = 'ff548ad35bdf31da07ae6b17197c2b0d04997a0e'
MAIL_REPLY_FORWARD_SOURCE = '0b5838aff1664a52cfcd86d7272649fe067c6070'
MAIL_REPLY_FORWARD_SOURCE_TREE = '896ecf9f53c3f6076a6844d81cde9bd595f55b7c'
MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE = '167073ead347ae606a827104308d8b2817e7731f'
MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE_TREE = '4a44211f5141336b8ed4d301a23aba5d10fb1474'
MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE = 'd395eb5294647b80f723ad29617d92b21b6afc2e'
MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE_TREE = '61cac762b98f3dca1a19938c0799fbb84762bd47'
MAIL_REPLY_FORWARD_WORKFLOW_FIX_PATHS = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    'tests/mail_copied_draft_native_workflow_contract.py',
))
MAIL_BACKUP_PROJECTION_FIX_BASE = 'f025b9637c1fa011c7e85c1eb910d4a74aa16219'
MAIL_BACKUP_PROJECTION_FIX_BASE_TREE = '58163a6ebd87eca7b078c8bb636c5dcd46f98fef'
MAIL_BACKUP_PROJECTION_FIX_SOURCE = '61733d7e9ac3166d3f614a8e29fd67d9b87d746e'
MAIL_BACKUP_PROJECTION_FIX_SOURCE_TREE = 'dc578eb8f781f9d46c2b2ab19758e2d71e0050fd'
MAIL_BACKUP_PROJECTION_FIX_PATH = 'tests/backups_acceptance_wiring.py'
MAIL_ARTIFACT_PROJECTION_FIX_BASE = 'fab39f449f7aa37014345a82fcb5bae2b69c9ce7'
MAIL_ARTIFACT_PROJECTION_FIX_BASE_TREE = 'b31e0ea58db081c16b7af55332ccc521cd6341f9'
MAIL_ARTIFACT_PROJECTION_FIX_SOURCE = '91f9b969698ddadca5e85d0fc5856a6ad6118493'
MAIL_ARTIFACT_PROJECTION_FIX_SOURCE_TREE = '3e0415069618670c0123a4b91a1cf3cafdf47263'
MAIL_ARTIFACT_PROJECTION_FIX_PATH = 'tests/acceptance_artifacts_test.py'
MAIL_ARTIFACT_OVERLAP_FIX_BASE = '46b4e58db17a9b33d95ec72fdd70f9cb9b99e736'
MAIL_ARTIFACT_OVERLAP_FIX_BASE_TREE = '7ed574a318f2011a2cd75f284c58c0b567330c62'
MAIL_ARTIFACT_OVERLAP_FIX_SOURCE = '77f2045f990e014a8f5280b7966fb0abf84dbe14'
MAIL_ARTIFACT_OVERLAP_FIX_SOURCE_TREE = '2c88257a650f70d2e3884a0bfea62e9fe3103a6b'
MAIL_ARTIFACT_OVERLAP_FIX_PATH = 'tests/acceptance_artifacts_test.py'
MAIL_THREAD_HEADER_FIX_BASE = '0bb81978a6573b600bfd38c2f66639ea4f63844b'
MAIL_THREAD_HEADER_FIX_BASE_TREE = 'fc16ae880ec086a661e77c1fed3ea043bd9138b6'
MAIL_THREAD_HEADER_FIX_SOURCE = '64480db8d9be5f88f1c1c4ae53304a7a363b13a5'
MAIL_THREAD_HEADER_FIX_SOURCE_TREE = '42e8dfcec333a2869e7c6e6159d8edb74ca24e9e'
MAIL_THREAD_HEADER_FIX_PATHS = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    'foundation/mail-submission/OfflineMailer.php',
    'foundation/mail-submission/PreparedMime.php',
    'site/includes/foundation/mail-submission/OfflineMailer.php',
    'site/includes/foundation/mail-submission/PreparedMime.php',
    'site/install/inventory.generated.php',
    'tests/mail-submission/mime.php',
    'tests/mail-submission/verify_mime.py',
    'tests/mail_copied_draft_native_workflow_contract.py',
))
MAIL_NATIVE_FIXTURE_POLICY_BASE = '64480db8d9be5f88f1c1c4ae53304a7a363b13a5'
MAIL_NATIVE_FIXTURE_POLICY_BASE_TREE = '42e8dfcec333a2869e7c6e6159d8edb74ca24e9e'
MAIL_NATIVE_FIXTURE_POLICY_SOURCE = '158159ea5b9f1de6a2ab32fcb5fa77bfedda9fc2'
MAIL_NATIVE_FIXTURE_POLICY_SOURCE_TREE = '5dae136f62772a4ec06bce0aaec8c42e2ec569cd'
MAIL_NATIVE_FIXTURE_POLICY_PATH = 'tests/mail_copied_draft_create_native.php'
MAIL_REPLY_SESSION_FIX_BASE = '681bcd2de5f211f31a709d647804c6717516b440'
MAIL_REPLY_SESSION_FIX_BASE_TREE = 'c4bd5f3e552b51a46e13d1fa1975f835707896c1'
MAIL_REPLY_SESSION_FIX_SOURCE = '48d13fa964f57e95a2ee53e596c38a535b703d0d'
MAIL_REPLY_SESSION_FIX_SOURCE_TREE = 'f876dd293e2d8d2f4e350769e809bb5fb878a81b'
MAIL_REPLY_SESSION_FIX_PATH = 'tests/mail_reply_cases.py'
MAIL_MYSQL_TRIGGER_FIX_BASE = 'e9d252d12e6d900f891e760e2505bd843c958e32'
MAIL_MYSQL_TRIGGER_FIX_BASE_TREE = 'd9c0e64d3b5346f96bb1feabddd9bd6bb5d41995'
MAIL_MYSQL_TRIGGER_FIX_SOURCE = '40f06f6cd71ccb48558e495585f774f5e4d88164'
MAIL_MYSQL_TRIGGER_FIX_SOURCE_TREE = '83e97e489914bb53144ef8266b3caf771cf80256'
MAIL_MYSQL_TRIGGER_FIX_PATHS = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    'tests/mail_copied_draft_native_workflow_contract.py',
))
MAIL_REPLY_FORM_HELPER_FIX_BASE = '6357fa812eb4ffbc401a92f73e16c12d06d8508d'
MAIL_REPLY_FORM_HELPER_FIX_BASE_TREE = 'e6cc51600697309966b969f5124943718435be19'
MAIL_REPLY_FORM_HELPER_FIX_SOURCE = '21572347519cf78cf17f47c041682645278e1b35'
MAIL_REPLY_FORM_HELPER_FIX_SOURCE_TREE = 'aa3794bda8c43811d3685a1ccd4b96a8b6a91066'
MAIL_REPLY_FORM_HELPER_FIX_PATH = 'tests/mail_reply_cases.py'
MAIL_COPY_CONTROLS_FIX_BASE = '740609d47cad1fd4f630f95b84a0c597f9d553eb'
MAIL_COPY_CONTROLS_FIX_BASE_TREE = '4d031bde857d42f098a2a8a9ef4a6329805afbed'
MAIL_COPY_CONTROLS_FIX_SOURCE = '5bfdbe7027973d66efec939478feeba17f498a46'
MAIL_COPY_CONTROLS_FIX_SOURCE_TREE = '60a71d7e0f694ca19e7303c6488dcb174a56b5e3'
MAIL_COPY_CONTROLS_FIX_PATHS = frozenset((
    'site/admin/controllers/mail_message.php',
    'site/install/inventory.generated.php',
    'tests/mail_copied_draft_create_adapter.php',
))
MAIL_CONSOLIDATED_BASE = '1e79ef57d576cda6291cc540ee3c76ac109f071e'
MAIL_CONSOLIDATED_BASE_TREE = 'd866df1cd0f7790af06c82ba58944d22f5fcaec0'
MAIL_CONSOLIDATED_SOURCE = 'cc4e0cbbb9beab53c9f2a7f8dfb6ef74740aa385'
MAIL_CONSOLIDATED_SOURCE_TREE = '6d9648b44b76e54ae249e1b9a1c6b4c558d544ac'
MAIL_CONSOLIDATED_DIFF_SHA256 = '888cfb1ba74e68be66911f023f9fd410c8ee82dbd7a0ec90086a5b860422fe0d'
MAIL_CONSOLIDATED_CHANGE_COUNT = 131
_MAIL_CONSOLIDATED_CHANGES = {}
MAIL_CONSOLIDATED_REVIEW_FIX_BASE = 'e369fa5582e312119ae6c44f572cd1dd427cbf7b'
MAIL_CONSOLIDATED_REVIEW_FIX_BASE_TREE = '9c27ecbd904c85d40084c471a3b607d17f1e67f1'
MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE = 'fc2553d70f13fd13472ab7e337fb567ff562b35b'
MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE_TREE = 'c2cb99ac5fe343d02ee6298f2219b8793eb900a1'
MAIL_CONSOLIDATED_REVIEW_FIX_PATHS = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    '.github/workflows/mail-folder-native.yml',
    'foundation/mail-compose/DraftOrigin.php',
    'site/admin/controllers/mail_drafts.php',
    'site/app/mail/drafts.php',
    'site/database/migrations/mail_drafts_migrate.php',
    'site/includes/foundation/mail-compose/DraftOrigin.php',
    'site/install/inventory.generated.php',
    'tests/mail-compose/origin.php',
    'tests/mail_copied_draft_native_workflow_contract.py',
    'tests/mail_drafts_cases.py',
    'tests/mail_drafts_form.php',
    'tests/mail_drafts_native.php',
    'tests/mail_folder_native_workflow_contract.py',
))
MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE = '2b05e0271b85009ae7685ffda3fe888c696aa322'
MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE_TREE = 'bf816533aa9e1e3a4297e710f45192aa329758a5'
MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE = '2abf8e990b3360d94db5ed2ac8258cd1b082c7b7'
MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE_TREE = '7b1235f90017946e8a7449fa22aba5bd4a64e960'
MAIL_CONSOLIDATED_REVIEW_RECOVERY_PATHS = frozenset((
    'site/database/migrations/mail_drafts_migrate.php',
    'site/install/inventory.generated.php',
    'tests/mail_drafts_native.php',
))
MAIL_SENT_COPY_BASE = 'b9a021a643cf3ea015900ccd84cbb5cc9f0bd1e1'
MAIL_SENT_COPY_BASE_TREE = 'f79abfb7da91ad17f4902aaae3db1fdfb8e069a2'
MAIL_SENT_COPY_SOURCE = '78020344d8d20b67950f66993f466b267c6262f2'
MAIL_SENT_COPY_SOURCE_TREE = '6c3ce08b8ad6ef1b9790ab1a495d506628d9ddf4'
MAIL_SENT_COPY_MODIFIED = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    '.github/workflows/mail-flags-native.yml',
    '.github/workflows/mail-folder-native.yml',
    'foundation/mail-receiving/bootstrap.php',
    'site/admin/controllers/mail_accounts.php',
    'site/admin/controllers/mail_submissions.php',
    'site/admin/views/mail_accounts.php',
    'site/admin/views/mail_submissions.php',
    'site/app/settings/database_schemas.php',
    'site/database/schema/base.php',
    'site/includes/foundation/mail-receiving/bootstrap.php',
    'site/install/actions/deploy.php',
    'site/install/inventory.generated.php',
    'tests/database_schema_registry_policy.php',
    'tests/mail-receiving/append_test.php',
    'tests/mail_accounts_native.php',
    'tests/mail_copied_draft_native_workflow_contract.py',
    'tests/mail_drafts_native.php',
    'tests/mail_flags_native_workflow_contract.py',
    'tests/mail_folder_native_workflow_contract.py',
    'tests/mail_inbox_http_server.py',
    'tests/mail_submissions_cases.py',
    'tests/mail_submissions_native.php',
))
MAIL_SENT_COPY_ADDITIONS = frozenset((
    'foundation/mail-receiving/SentCopy.php',
    'site/app/mail/copies.php',
    'site/database/migrations/mail_copies_migrate.php',
    'site/database/schema/mail_copies_schema.php',
    'site/includes/foundation/mail-receiving/SentCopy.php',
))
MAIL_SENT_COPY_ORIGIN_FIX_BASE = '9698024e0ac0a730ea87f943d84fe32423959f79'
MAIL_SENT_COPY_ORIGIN_FIX_BASE_TREE = '03aef7fc7ee88784b79e2b38bca347b5727f37e7'
MAIL_SENT_COPY_ORIGIN_FIX_SOURCE = 'faa6a67968469b2caf09d5ad3a758f32223d7d10'
MAIL_SENT_COPY_ORIGIN_FIX_SOURCE_TREE = 'b73a4efa405b7d442e0cb6f810958363e6af07ea'
MAIL_SENT_COPY_ORIGIN_FIX_PATHS = frozenset((
    'site/app/mail/copies.php',
    'site/install/inventory.generated.php',
    'tests/mail_replies_native.php',
    'tests/mail_reply_cases.py',
))
MAIL_ATTACHMENT_PORTABLE_BASE = '0233faa7f5e6207ea2bf595dcf454ce4e7856f7b'
MAIL_ATTACHMENT_PORTABLE_BASE_TREE = '68e13b9ea7eacbbe7b8e3c9bce1f83de4915375a'
MAIL_ATTACHMENT_PORTABLE_SOURCE = 'bf41fbc332c735667568666a1647328a5a2a460b'
MAIL_ATTACHMENT_PORTABLE_SOURCE_TREE = '27676e2194f5ea27a58828b3fb47bf3e72fcd30c'
MAIL_ATTACHMENT_PORTABLE_ADDITIONS = frozenset((
    'tests/mail-submission/attachments.php',
    'tests/mail-submission/verify_attachments.py',
))
MAIL_ATTACHMENT_SOURCE_BASE = MAIL_ATTACHMENT_PORTABLE_SOURCE
MAIL_ATTACHMENT_SOURCE_BASE_TREE = MAIL_ATTACHMENT_PORTABLE_SOURCE_TREE
MAIL_ATTACHMENT_SOURCE = '97ce65809f3fe143088d7d0c04043b9d6ccaa132'
MAIL_ATTACHMENT_SOURCE_TREE = '78619844fa4081fa890f2e396df2f34a745dc84c'
MAIL_ATTACHMENT_MODIFIED = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    '.github/workflows/mail-flags-native.yml',
    '.github/workflows/mail-folder-native.yml',
    '.github/workflows/mail-submission-storage.yml',
    '.github/workflows/mail-submission.yml',
    'site/admin/controllers/mail_drafts.php',
    'site/admin/views/mail_drafts.php',
    'site/admin/views/mail_submissions.php',
    'site/app/mail/submission_views.php',
    'site/app/mail/submissions.php',
    'site/app/settings/database_schemas.php',
    'site/database/schema/base.php',
    'site/install/actions/deploy.php',
    'site/install/inventory.generated.php',
    'tests/apache_acceptance.py',
    'tests/database_schema_registry_policy.php',
    'tests/database_schema_view_policy.php',
    'tests/mail_copied_draft_native_workflow_contract.py',
    'tests/mail_flags_native_workflow_contract.py',
    'tests/mail_folder_native_workflow_contract.py',
    'tests/mail_submissions_cases.py',
    'tests/mail_submissions_native.php',
))
MAIL_ATTACHMENT_ADDITIONS = frozenset((
    'site/app/mail/files.php',
    'site/database/migrations/mail_files_migrate.php',
    'site/database/schema/mail_files_schema.php',
))
MAIL_ATTACHMENT_AUTH_FIX_BASE = MAIL_ATTACHMENT_SOURCE
MAIL_ATTACHMENT_AUTH_FIX_BASE_TREE = MAIL_ATTACHMENT_SOURCE_TREE
MAIL_ATTACHMENT_AUTH_FIX_SOURCE = '53efa17222858dda47f852d959494ba86f19bb8f'
MAIL_ATTACHMENT_AUTH_FIX_SOURCE_TREE = '6a311a9c4f39083e3f6ffbef9d3d57626933e280'
MAIL_ATTACHMENT_AUTH_FIX_PATHS = frozenset((
    'site/admin/controllers/mail_drafts.php',
    'site/app/mail/files.php',
    'site/database/migrations/mail_files_migrate.php',
    'site/install/inventory.generated.php',
    'tests/mail_replies_native.php',
    'tests/mail_reply_cases.py',
    'tests/mail_submissions_cases.py',
    'tests/mail_submissions_native.php',
))
MAIL_ATTACHMENT_READINESS_FIX_BASE = MAIL_ATTACHMENT_AUTH_FIX_SOURCE
MAIL_ATTACHMENT_READINESS_FIX_BASE_TREE = MAIL_ATTACHMENT_AUTH_FIX_SOURCE_TREE
MAIL_ATTACHMENT_READINESS_FIX_SOURCE = '87bc71ae87ba6b13d4f2c561895897101fb0e740'
MAIL_ATTACHMENT_READINESS_FIX_SOURCE_TREE = 'e3700bdf07082ff92ee712da2bdd0d69abd964e3'
MAIL_ATTACHMENT_READINESS_FIX_PATHS = frozenset((
    'site/app/mail/files.php',
    'site/install/inventory.generated.php',
    'tests/mail_replies_native.php',
    'tests/mail_reply_cases.py',
    'tests/mail_submissions_cases.py',
))
MAIL_ATTACHMENT_SUBMISSION_FIX_BASE = MAIL_ATTACHMENT_READINESS_FIX_SOURCE
MAIL_ATTACHMENT_SUBMISSION_FIX_BASE_TREE = MAIL_ATTACHMENT_READINESS_FIX_SOURCE_TREE
MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE = 'b199c5b7ae227ae5c60f636718ffa9a90ddd4ead'
MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE_TREE = 'd934a3da2339a459831e97e6914e012a51109688'
MAIL_ATTACHMENT_SUBMISSION_FIX_PATHS = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    'site/app/mail/replies.php',
    'site/install/inventory.generated.php',
    'tests/mail_copied_draft_native_workflow_contract.py',
    'tests/mail_replies_native.php',
    'tests/mail_submissions_cases.py',
))
MAIL_ATTACHMENT_CLAIM_FIX_BASE = MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE
MAIL_ATTACHMENT_CLAIM_FIX_BASE_TREE = MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE_TREE
MAIL_ATTACHMENT_CLAIM_FIX_SOURCE = '33ca06fb9fe29d286442a44d43c81f329d7dac68'
MAIL_ATTACHMENT_CLAIM_FIX_SOURCE_TREE = 'e363c21ef9296889d9bb3c68d804fa863fc6e7fd'
MAIL_ATTACHMENT_CLAIM_FIX_PATH = 'tests/mail_replies_native.php'
MAIL_SUBMISSION_EXPIRY_FIX_BASE = '3576e6b5640a56175efbc475b42b2d85323ab472'
MAIL_SUBMISSION_EXPIRY_FIX_BASE_TREE = '185e1cee0ee80226ae0da4eb8ec0fce94380de3c'
MAIL_SUBMISSION_EXPIRY_FIX_SOURCE = 'a5131a0c8d5f7eaa3a7acc9b3fcccb1a0135cd8e'
MAIL_SUBMISSION_EXPIRY_FIX_SOURCE_TREE = 'cbeda4f6bd647756a7aab2b8e7a03d2459b33714'
MAIL_SUBMISSION_EXPIRY_FIX_PATH = 'tests/mail_submissions_native.php'
MAIL_ACCEPTANCE_REPAIR_BASE = '007785194f3646fd831189536a25d825b271c77c'
MAIL_ACCEPTANCE_REPAIR_BASE_TREE = 'd019dac150ea98ed93c3ae6dc7783cf7469c8d6f'
MAIL_ACCEPTANCE_REPAIR_SOURCE = 'f2e7066f5d909de1aac823cda161050ce3ad93f9'
MAIL_ACCEPTANCE_REPAIR_SOURCE_TREE = '2e87cafec0efb27f4d1eb2f0b31337f8146f86ac'
MAIL_ACCEPTANCE_REPAIR_PATHS = frozenset((
    '.github/workflows/mail-folder-native.yml',
    'tests/mail_copied_draft_create_native.php',
    'tests/mail_flags_native.php',
    'tests/mail_folder_native_workflow_contract.py',
    'tests/mail_message_native.php',
))
MAIL_ACCEPTANCE_HARDENING_BASE = MAIL_ACCEPTANCE_REPAIR_SOURCE
MAIL_ACCEPTANCE_HARDENING_BASE_TREE = MAIL_ACCEPTANCE_REPAIR_SOURCE_TREE
MAIL_ACCEPTANCE_HARDENING_SOURCE = '7dbff127e3d49028d6176b9449265fdaad5e9285'
MAIL_ACCEPTANCE_HARDENING_SOURCE_TREE = '60713893cb1783b8738901ee4ca3b066a5645bb9'
MAIL_ACCEPTANCE_HARDENING_PATHS = frozenset((
    '.github/workflows/mail-folder-native.yml',
    'tests/mail_folder_native_workflow_contract.py',
    'tests/mail_message_native.php',
))
FORCE_SSL_RUNNER_PORT_BASE = 'c45be42591ebd3295c929970b52662585fe4155f'
FORCE_SSL_RUNNER_PORT_BASE_TREE = '46cb7b37a0acda3712128b44f6577e74b055bbc1'
FORCE_SSL_RUNNER_PORT_SOURCE = '3f3bd69c68cbf858cf3bf5342682779a7f385b64'
FORCE_SSL_RUNNER_PORT_SOURCE_TREE = '20d83937cc4abe95bf5ab64de273ba29eeeeee86'
FORCE_SSL_RUNNER_PORT_PATH = '.github/workflows/provider-force-ssl-controls.yml'
MAIL_REPLY_ALL_BOUNDARY_BASE = FORCE_SSL_RUNNER_PORT_BASE
MAIL_REPLY_ALL_BOUNDARY_BASE_TREE = FORCE_SSL_RUNNER_PORT_BASE_TREE
MAIL_REPLY_ALL_BOUNDARY_SOURCE = '3a658a02336ed48e65e5b04bdff6c9d025a7c636'
MAIL_REPLY_ALL_BOUNDARY_SOURCE_TREE = '005a3fab7ca66704e1954c7b8d1e7f9135f58744'
MAIL_REPLY_ALL_BOUNDARY_PATH = 'tests/mail_copied_draft_create_native.php'
MAIL_FLAGS_IDENTITY_BASE = FORCE_SSL_RUNNER_PORT_BASE
MAIL_FLAGS_IDENTITY_BASE_TREE = FORCE_SSL_RUNNER_PORT_BASE_TREE
MAIL_FLAGS_IDENTITY_SOURCE = 'fb07a60ae8a3a2b292e3f4891604559efc022668'
MAIL_FLAGS_IDENTITY_SOURCE_TREE = 'b16d7d76c3e54ae80a6cb090bebbfefdd9f87a1e'
MAIL_FLAGS_IDENTITY_PATH = 'tests/mail_flags_native.php'
MAIL_FLAGS_MARKER_BASE = MAIL_FLAGS_IDENTITY_SOURCE
MAIL_FLAGS_MARKER_BASE_TREE = MAIL_FLAGS_IDENTITY_SOURCE_TREE
MAIL_FLAGS_MARKER_SOURCE = 'edebd5ec41a7ad3189568d1c2987e6d63b14a612'
MAIL_FLAGS_MARKER_SOURCE_TREE = '36707a42eb5591624e51aeaa016556e95088d5af'
MAIL_FLAGS_MARKER_PATH = MAIL_FLAGS_IDENTITY_PATH
MAIL_LIST_IDENTITY_BASE = '7e375c4ad4123cea02756cedd64e763be93222b9'
MAIL_LIST_IDENTITY_BASE_TREE = '9162f616ffb2d444939d7a0e14ed58a7ca6592be'
MAIL_LIST_IDENTITY_SOURCE = 'ee29b6f97e7dd18d2435612c4a96fb489137202e'
MAIL_LIST_IDENTITY_SOURCE_TREE = 'f3b62a964a5f230aa2e152c976d72a42cac15c2c'
MAIL_LIST_IDENTITY_PATHS = frozenset((
    '.github/workflows/mail-folder-native.yml',
    'tests/mail_folder_native_workflow_contract.py',
    'tests/mail_replies_native.php',
))
MAIL_FLAGS_REPORT_COUNT_BASE = 'ee29b6f97e7dd18d2435612c4a96fb489137202e'
MAIL_FLAGS_REPORT_COUNT_BASE_TREE = 'f3b62a964a5f230aa2e152c976d72a42cac15c2c'
MAIL_FLAGS_REPORT_COUNT_SOURCE = '89d7602f9e338a21922b18345c1c1cd548a110f8'
MAIL_FLAGS_REPORT_COUNT_SOURCE_TREE = '76b9657ed92bd2b4c9809051ca2939a968bfbddf'
MAIL_FLAGS_REPORT_COUNT_PATHS = frozenset((
    '.github/workflows/mail-flags-native.yml',
    'tests/mail_flags_native_workflow_contract.py',
))
APPLICATION_ACCOUNT_FIXTURE_BASE = '6d57621e67f43543906aba54d05bf8a42093bb82'
APPLICATION_ACCOUNT_FIXTURE_BASE_TREE = 'e214aa19b186a453484b7023f064081084dad5bd'
APPLICATION_ACCOUNT_FLAGS_SOURCE = '967bfcc97c0bfda78a6c73e39578e5d6528b5df6'
APPLICATION_ACCOUNT_FLAGS_TREE = 'f4d2d20c10c69628eebf09625bc1339c2d293e2c'
APPLICATION_ACCOUNT_FILES_SOURCE = '57384ea3a8d75df629b53b4e11d143052f8ef916'
APPLICATION_ACCOUNT_FILES_TREE = '26aa65ce536afd3cdc4821b7547f1e944f70961f'
APPLICATION_INBOX_FIXTURE_SOURCE = '410edaa0c97ad2c322ade7b35191a0fa8a4f0a6a'
APPLICATION_INBOX_FIXTURE_TREE = '53331d93e10c1d44dd79b99faa2046bd4377b1f0'
APPLICATION_MESSAGE_FIXTURE_BASE = 'f4b073c351bd2255646c4a3b3f24119cc9e70116'
APPLICATION_MESSAGE_FIXTURE_BASE_TREE = '49fd3e0e3b892ac65393087ca412ccabd60b5661'
APPLICATION_MESSAGE_FIXTURE_SOURCE = '134465633c16551b38d5e63879edbd241abc289b'
APPLICATION_MESSAGE_FIXTURE_SOURCE_TREE = '13a51f7f1445c0f68cb7af808ba9227f30138223'
APPLICATION_NOTICES_FIXTURE_BASE = '1744bd1208ecc51137961ed7cada8e709ed2753b'
APPLICATION_NOTICES_FIXTURE_BASE_TREE = '3d0467bf32f667f511dd224f0280ffecbd779a25'
APPLICATION_NOTICES_FIXTURE_SOURCE = 'c3050a35f7bfd30c33321b71c18fbaf28328b85c'
APPLICATION_NOTICES_FIXTURE_SOURCE_TREE = 'd4a38f96f39a67fbfbc52bef91c82008545ad1e7'
APPLICATION_FRESH_DRAFT_READINESS_BASE = '9ebc6433a8bd17e7186dbfab3860feb1cdd214d3'
APPLICATION_FRESH_DRAFT_READINESS_BASE_TREE = 'e00e69f2a6df181f5bb7bede96eacb70f2cc5764'
APPLICATION_FRESH_DRAFT_READINESS_SOURCE = 'ac3766454ecf0bd8e3886c9a548ae33061bccb16'
APPLICATION_FRESH_DRAFT_READINESS_SOURCE_TREE = '57cbbbb0b2e609d6a74e26d0ee0c64d9a600a674'
APPLICATION_FRESH_DRAFT_READINESS_PATHS = frozenset((
    'site/install/actions/deploy.php',
    'site/install/inventory.generated.php',
))
APPLICATION_SENT_COPY_ABSENCE_BASE = '1a65bd018c7d6cbd0a6bfcb127ed795e079abfcd'
APPLICATION_SENT_COPY_ABSENCE_BASE_TREE = '9ce92a1e493fbafd8c90e859719467e64448728e'
APPLICATION_SENT_COPY_ABSENCE_SOURCE = '8950af94e94185af70867f9ca9405c93d70b1577'
APPLICATION_SENT_COPY_ABSENCE_SOURCE_TREE = '97cf22d547ec42d0fbf4bd84a2829a21a56554e4'
APPLICATION_SENT_COPY_ABSENCE_PATH = 'tests/mail_submissions_cases.py'
APPLICATION_BROWSER_FIXTURE_BASE = 'f51840f68114ec70f3c5821529054d121f803d4b'
APPLICATION_BROWSER_FIXTURE_BASE_TREE = 'd368a6c60d0f956cfa4a1b4442c1eb1f34b3742e'
APPLICATION_BROWSER_FIXTURE_SOURCE = 'b8d347fd5ab3e529ee09c9671403b1bcddb5521a'
APPLICATION_BROWSER_FIXTURE_SOURCE_TREE = 'e4a4e2878bee0dd973591199750494ff3fa268fb'
APPLICATION_ACCOUNT_FIXTURE_PATH = 'tests/mail_accounts_native.php'
APPLICATION_INBOX_FIXTURE_PATH = 'tests/organized_update_cases.py'
APPLICATION_BROWSER_FIXTURE_PATH = 'tests/mail_accounts_cases.py'
MAIL_REPLY_ALL_REPLAY_BASE = '12a05205b47809b679584c763090cdedb66589f8'
MAIL_REPLY_ALL_REPLAY_BASE_TREE = '223a2f02f6f634b9c66991915b32f90680f6fb3b'
MAIL_REPLY_ALL_REPLAY_SOURCE = 'fa537502037517d6019bca46ee4cc44d3005fe6a'
MAIL_REPLY_ALL_REPLAY_SOURCE_TREE = '1bac0f7b237fdfbb157337b976fd379432785421'
MAIL_REPLY_ALL_REPLAY_PATHS = frozenset((
    'tests/mail_copied_draft_create_native.php',
    'tests/mail_reply_cases.py',
))
MAIL_ACCEPTANCE_PORTABILITY_BASE = '6207e36d0f1ca68887a93ded09b33478296594b4'
MAIL_ACCEPTANCE_PORTABILITY_BASE_TREE = '093fdbd211d9fb48856bc8b3f0217d015d6a3fe1'
MAIL_ACCEPTANCE_PORTABILITY_SOURCE = '273640171bc70b4ad2bc4f4e0036d80a52fdc823'
MAIL_ACCEPTANCE_PORTABILITY_SOURCE_TREE = 'eecf48ab0eae41849337b52dd306ea40a26a1db8'
MAIL_ACCEPTANCE_PORTABILITY_PATHS = frozenset((
    '.github/workflows/mail-flags-native.yml',
    '.github/workflows/mail-folder-native.yml',
    '.github/workflows/mail-receiving.yml',
    'foundation/mail-compose/ComposeHtml.php',
    'foundation/mail-receiving/BodyHtml.php',
    'site/database/schema/catalog_validate_schema.php',
    'site/includes/foundation/mail-compose/ComposeHtml.php',
    'site/includes/foundation/mail-receiving/BodyHtml.php',
    'site/install/inventory.generated.php',
    'tests/mail-compose/html.php',
    'tests/mail-submission/verify_mime.py',
    'tests/mail_accounts_native.php',
    'tests/mail_drafts_native.php',
    'tests/mail_flags_native_workflow_contract.py',
    'tests/mail_folder_native_workflow_contract.py',
    'tests/mail_inbox_native.php',
    'tests/mail_message_native.php',
))
MAIL_ADMISSION_EVIDENCE_BASE = '235561bf035fc9d67e2b509d3f42916f738da98c'
MAIL_ADMISSION_EVIDENCE_BASE_TREE = 'de067cef42b9f2820c028ea4073d832b2e1ccb34'
MAIL_ADMISSION_EVIDENCE_SOURCE = 'c0e713e4a382f42fdaa08d63d04328af70950df5'
MAIL_ADMISSION_EVIDENCE_SOURCE_TREE = 'da2a2dd8ffcd5e9284552c6af12e6bfe593b0eed'
MAIL_ADMISSION_EVIDENCE_PATHS = frozenset((
    '.github/workflows/mail-flags-native.yml',
    '.github/workflows/mail-folder-native.yml',
    'tests/mail_flags_native_workflow_contract.py',
    'tests/mail_folder_native_workflow_contract.py',
))
MAIL_THREAD_ALIAS_FIX_BASE = '3963afb9ade411960a55b555bccc2c4b81d5db3e'
MAIL_THREAD_ALIAS_FIX_BASE_TREE = '0d525c706bcf059053b02948008fdcc4a16b9996'
MAIL_THREAD_ALIAS_FIX_SOURCE = 'ec6ba09b4465c747398044835aaf5f0575a4635f'
MAIL_THREAD_ALIAS_FIX_SOURCE_TREE = 'a361151f82a48d79eae73e251fd41826bbddd5aa'
MAIL_THREAD_ALIAS_FIX_PATHS = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    'foundation/mail-submission/OfflineMailer.php',
    'site/includes/foundation/mail-submission/OfflineMailer.php',
    'site/install/inventory.generated.php',
    'tests/mail-submission/mime.php',
    'tests/mail_copied_draft_native_workflow_contract.py',
))
MAIL_REPLY_FORWARD_MODIFIED = frozenset((
    'foundation/mail-receiving/HeaderDecoder.php',
    'foundation/mail-submission/PreparedMime.php',
    'foundation/mail-submission/SubmissionSnapshot.php',
    'site/admin/controllers/mail_drafts.php',
    'site/admin/controllers/mail_message.php',
    'site/admin/views/mail_drafts.php',
    'site/admin/views/mail_message.php',
    'site/app/mail/drafts.php',
    'site/app/mail/messages.php',
    'site/app/mail/submission_views.php',
    'site/app/mail/submissions.php',
    'site/app/settings/database_schemas.php',
    'site/app/staff/accounts.php',
    'site/includes/foundation/mail-receiving/HeaderDecoder.php',
    'site/includes/foundation/mail-submission/PreparedMime.php',
    'site/includes/foundation/mail-submission/SubmissionSnapshot.php',
    'site/install/inventory.generated.php',
    'tests/apache_acceptance.py',
    'tests/browser_acceptance.py',
    'tests/database_schema_registry_policy.php',
    'tests/database_schema_view_policy.php',
    'tests/mail-submission/mime.php',
    'tests/mail-submission/run.php',
    'tests/mail_inbox_http_server.py',
    'tests/mail_message_native.php',
    'tests/mail_submission_server.py',
))
MAIL_REPLY_FORWARD_ADDITIONS = frozenset((
    '.github/workflows/mail-copied-draft-native.yml',
    'docs/MAIL_REPLY_FORWARD.md',
    'foundation/mail-compose/CopiedDraftSource.php',
    'foundation/mail-compose/DraftOrigin.php',
    'foundation/mail-compose/MessageThread.php',
    'foundation/mail-compose/ReplyDraft.php',
    'foundation/mail-compose/ReplyMetadata.php',
    'site/app/mail/copied_drafts.php',
    'site/app/mail/replies.php',
    'site/database/migrations/mail_replies_migrate.php',
    'site/database/schema/mail_replies_schema.php',
    'site/includes/foundation/mail-compose/CopiedDraftSource.php',
    'site/includes/foundation/mail-compose/DraftOrigin.php',
    'site/includes/foundation/mail-compose/MessageThread.php',
    'site/includes/foundation/mail-compose/ReplyDraft.php',
    'site/includes/foundation/mail-compose/ReplyMetadata.php',
    'tests/mail-compose/copied_source.php',
    'tests/mail-compose/origin.php',
    'tests/mail-compose/reply.php',
    'tests/mail-compose/thread.php',
    'tests/mail_copied_draft_admin_policy.php',
    'tests/mail_copied_draft_create_adapter.php',
    'tests/mail_copied_draft_create_native.php',
    'tests/mail_copied_draft_native_workflow_contract.py',
    'tests/mail_copied_draft_storage.php',
    'tests/mail_replies_native.php',
    'tests/mail_reply_cases.py',
))
MAIL_REPLY_FORWARD_PATHS = MAIL_REPLY_FORWARD_MODIFIED | MAIL_REPLY_FORWARD_ADDITIONS
PROJECT_PRIVATE_NOTES_BASE = '803705fc76bfce3e0e2dba1125e73267165847a9'
PROJECT_PRIVATE_NOTES_BASE_TREE = '0cf9421972425bd3f54329abba6eece9ecdca917'
PROJECT_PRIVATE_NOTES_MODEL = 'ef02fe058feb2cb655cbd602f06bd5f1a1d6f740'
PROJECT_PRIVATE_NOTES_MODEL_TREE = '259dae8b24928d8f9fa19893342798002acc08e5'
PROJECT_PRIVATE_NOTES_IMPLEMENTATION = '6c6ad4bc0367a048989469b7ae705a4f5fe2e8e6'
PROJECT_PRIVATE_NOTES_IMPLEMENTATION_TREE = '7d064c6d6410a3c6de8eb92aa42fc369333c12b3'
PROJECT_PRIVATE_NOTES_HARDENING = '9a2deace926680a95817f1e687a8eeecdd157aaf'
PROJECT_PRIVATE_NOTES_HARDENING_TREE = 'd49e2e05357c2591a1d0323d6c82935bf007423b'
PROJECT_PRIVATE_NOTES_SOURCE = 'ed35d1c0f515a43e3b2be23f2b1158b985a8a92e'
PROJECT_PRIVATE_NOTES_SOURCE_TREE = 'f2930651134669872dfa6c18425c680f16927f63'
PROJECT_PRIVATE_NOTES_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'site/admin/controllers/router.php',
    'site/admin/controllers/studio.php',
    'site/admin/views/studio/job.php',
    'site/app/notifications/events.php',
    'site/app/settings/database_schemas.php',
    'site/app/staff/accounts.php',
    'site/database/schema/base.php',
    'site/install/inventory.generated.php',
    'tests/client_authority_native.php',
    'tests/database_schema_registry_policy.php',
    'tests/database_schema_view_policy.php',
    'tools/build.py',
))
PROJECT_PRIVATE_NOTES_ADDITIONS = frozenset((
    'foundation/projects/ProjectPrivateNote.php',
    'site/admin/views/studio/private_notes.php',
    'site/app/projects/private_notes.php',
    'site/database/migrations/project_private_notes_migrate.php',
    'site/database/schema/project_private_notes_schema.php',
    'site/database/schema/project_private_notes_validate_schema.php',
    'site/includes/foundation/projects/ProjectPrivateNote.php',
    'tests/project-notes/records.php',
    'tests/project_private_notes_native.php',
    'tests/project_private_notes_policy.php',
    'tests/project_private_notes_view.php',
))
PROJECT_PRIVATE_NOTES_EVIDENCE_BASE = '18d4778cd4d942deda6c804928e199bba288b2eb'
PROJECT_PRIVATE_NOTES_EVIDENCE_BASE_TREE = '4e961cdff8db8a8b371c424d8eb7abd42a48f712'
PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE = '28e257e1efb3fc2486e63a3087266a764a59de5b'
PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE_TREE = 'a1b2593525fb08ea4663f5f3aa1c48e8136c7078'
PROJECT_PRIVATE_NOTES_EVIDENCE_MODIFIED = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tools/acceptance-contract.json',
    'tools/acceptance-reports.json',
))
PROJECT_PRIVATE_NOTES_AUTHORITY_BASE = '5667858710a21a1f66dfb5669b744f2e13ae181b'
PROJECT_PRIVATE_NOTES_AUTHORITY_BASE_TREE = 'a487b02fb7a08d934c8b90b0e5eb07ad7099be65'
PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE = '95526a159a2da46db6fa74f6c99cc740fce0650a'
PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE_TREE = '2125d4e4b6c02741724ca4be77588778a0fe72ff'
PROJECT_PRIVATE_NOTES_AUTHORITY_PATH = 'tests/acceptance_artifacts_test.py'
PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE = '1e8dc88d5a93c4364b6bb82c7f9c4ece19ec2bd9'
PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE_TREE = 'b29c644416800838fd2cdfacc321b3384d933e57'
PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE = 'bf5e01aba2bef7cbda555469644082e143d0ccc7'
PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE_TREE = '96d23521112534e7d7b2904c252ec5dd847420c8'
PROJECT_PRIVATE_NOTES_PROOF_COMPAT_PATHS = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/account_notification_workflow_contract.py',
))
PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE = 'a2fa4a7d98ac7d28852ffc7246c4c32850ff3975'
PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE_TREE = 'bacc5f363d3310332c7af5c3857634928295c65b'
PROJECT_PRIVATE_NOTES_STATIC_INTAKE_IMPLEMENTATION = '171d3174a1a8f4b1369a0829cfcdc8a9c3a508ca'
PROJECT_PRIVATE_NOTES_STATIC_INTAKE_IMPLEMENTATION_TREE = '0b8f7b33b7579e5841625dab69579e9e5dec7c07'
PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE = 'b638d071584adc4967013b0425511ac5dafca745'
PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE_TREE = 'bcaeaf3f024ae14c4aa938abf493fed8ef58ef78'
PROJECT_PRIVATE_NOTES_STATIC_INTAKE_PATHS = frozenset((
    'tests/acceptance_artifacts_test.py',
    'tests/cloud_site_intake_wiring.py',
))
PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE = 'c6dfa1cc65398c941106761913b72d416a0928c6'
PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE_TREE = '0db717097450b662eb7e0c363eeb93df60e5579c'
PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_IMPLEMENTATION = 'aba46a6954f8a288110baee21228835f8fbacea6'
PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_IMPLEMENTATION_TREE = 'f199452cdd4bb178b91fd2fe940e78c18e66920b'
PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE = 'a37dd2f863b044e89873f5459c23951cbec52e1e'
PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE_TREE = 'bc2b610f8ed53c94a7783e2a5f474f180bedc14d'
PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_PATHS = frozenset((
    'tests/project_private_notes_native.php',
    'tools/acceptance-contract.json',
))
PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE = '556b38fa575c20424ebd80be11dd1f1f7469d592'
PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE_TREE = 'f2d7f74aa15da84305cdbfbe8b06f1764ab1f668'
PROJECT_PRIVATE_NOTES_FK_FIXTURE_IMPLEMENTATION = 'acfce3e24f7815fe44ecbde0a605e3e5e568067d'
PROJECT_PRIVATE_NOTES_FK_FIXTURE_IMPLEMENTATION_TREE = '856c90144032eebef582d1ff5260e51f709d7345'
PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE = '6cde30d315308d85a4d068365c5644ef439a0ccc'
PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE_TREE = 'db9d3da93b4f27725757f6a5381ce156ba78c17d'
PROJECT_PRIVATE_NOTES_FK_FIXTURE_PATHS = frozenset((
    'tests/project_private_notes_native.php',
    'tools/acceptance-contract.json',
))
PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE = '48bac3d8c7cbb24444e593af1c963f9064a66c60'
PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE_TREE = '9c3abef3835616cc652f3090e60b82c2982851ba'
PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_IMPLEMENTATION = 'bd15774fe3a222c52a8000f437049a03168a3c6c'
PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_IMPLEMENTATION_TREE = '8985e686b0bd6026738ce6ee0b24db969e44e1c5'
PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE = 'bd4d4d2909ecd562a253664f6da365c0bf6acd9a'
PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE_TREE = '36eb97f59087fca54dafdd0c38c12d30cdee239c'
PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_PATHS = frozenset((
    'tests/project_private_notes_native.php',
    'tools/acceptance-contract.json',
))
PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE = '00035e10090b13c3956ab4628c0f59d8709b2403'
PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE_TREE = 'c3fac5b85789844d00f5d72250b29cbe0c1398c1'
PROJECT_PRIVATE_NOTES_STALE_FIXTURE_IMPLEMENTATION = 'a0994d1d041367a2cee17b0a4636b981b05be41b'
PROJECT_PRIVATE_NOTES_STALE_FIXTURE_IMPLEMENTATION_TREE = 'd525e42f2d1498c929aab064ef6b7dca17bd9955'
PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE = 'ee908dd1ff5723b109fd08d49fec96d2ea744dd7'
PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE_TREE = 'd240079feed5477a56d5bf4a48b1c5f61d60e290'
PROJECT_PRIVATE_NOTES_STALE_FIXTURE_PATHS = frozenset((
    'tests/project_private_notes_native.php',
    'tools/acceptance-contract.json',
))
DOMAIN_EXPIRY_PORT_BASE = '5165718b248a1dcbfe0794fa7de304d502c8178b'
DOMAIN_EXPIRY_PORT_BASE_TREE = 'c6cc250cecc7b64040f9ea7f64ef9a983d6baee5'
DOMAIN_EXPIRY_PORT_SOURCE = 'f0f729f4b841294721bd0832a0fcaf5e7a2e9051'
DOMAIN_EXPIRY_PORT_SOURCE_TREE = '93939967b33abc038afd40f24c742620091acabe'
DOMAIN_EXPIRY_PORT_PATH = '.github/workflows/domain-expiry.yml'
PROOF_RUNTIME_BASE = '0e02bace532d78e0134bf3a2f48ccd9b41c0e4bd'
PROOF_RUNTIME_BASE_TREE = 'a6eba1f09ed5ae0c7cfda0a0fc2b7bfe5307b8ab'
PROOF_RUNTIME_SOURCE = '6742496041db3cd51091cdcf3cad5864219d38f7'
PROOF_RUNTIME_SOURCE_TREE = '6ba87b125afc22c65402a71e929ac6d21331005e'
PROOF_RUNTIME_MODIFIED = frozenset((
    '.github/workflows/application-tests.yml',
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json',
    'tests/account_notification_workflow_contract.py',
    'tests/combined_source_composition.py',
))
PROOF_RUNTIME_PROJECTED = frozenset((
    '.github/workflows/application-tests.yml',
    'tests/account_notification_workflow_contract.py',
))
PROOF_CACHE_HARDENING_BASE = '50c5c814c2f0fbd33f9aeb04d40dc8ceef5b1a16'
PROOF_CACHE_HARDENING_BASE_TREE = 'e2543ebacbf0d009f74624f813eb2fa7141f22f7'
PROOF_CACHE_HARDENING_SOURCE = 'eebaa708b09914e3812f33a3e21b6620b6482683'
PROOF_CACHE_HARDENING_SOURCE_TREE = '345c6065436fb7a39bf167ce8f3103c8c06a0645'
PROOF_CACHE_HARDENING_MODIFIED = frozenset((
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json',
    'tests/combined_source_composition.py',
    'tests/combined_source_composition_test.py',
))
PROOF_CACHE_HARDENING_PROJECTED = frozenset((
    'tests/combined_source_composition_test.py',
))
DIAGNOSTIC_ARTIFACT_BATCH_BASE = '4d3db9c923581063eba99e47e59a037efc9027b5'
DIAGNOSTIC_ARTIFACT_BATCH_BASE_TREE = '8f1b89ea6519ae0fedad8f6408646323555273c3'
DIAGNOSTIC_ARTIFACT_BATCH_SOURCE = '7f590a53154e86a225de02c6519640cac2cd9bae'
DIAGNOSTIC_ARTIFACT_BATCH_SOURCE_TREE = '2c6ee0b29fb4c03a68cbdfac92ba436457a4e92c'
DIAGNOSTIC_ARTIFACT_BATCH_MODIFIED = frozenset((
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json',
    'tests/acceptance_artifacts_test.py',
    'tests/combined_source_composition.py',
))
DIAGNOSTIC_ARTIFACT_BATCH_PATH = 'tests/acceptance_artifacts_test.py'
DIAGNOSTIC_ARTIFACT_BATCH_SHA256 = {
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json': 'b1213d6135a624d808e7869102203af7fa0e22a0bf7b7b438e7fd60c89c04fca',
    'tests/acceptance_artifacts_test.py': 'cc8047f464cea7450df788bf94f7d1a8843d9422f87e344c3b062727768eca4d',
    'tests/combined_source_composition.py': 'fb4a3ba190013802a72b36b18cfb10d669631c0ed78a3de95299d5d44c0e8bf1',
}
DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_BASE = 'a55e4a5fee0874b23ec862db034791117cf73c9c'
DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_BASE_TREE = '3ee5b14c95b841c99477d9d4be910a0553d84329'
DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE = '8a6056b4d13158a629a3f24aa6da21c6a081e106'
DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE_TREE = '02f0ce7c3b15bf4a5e35f03ea1137dec6a16fa3e'
DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_MODIFIED = frozenset((
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json',
    'tests/combined_source_composition.py',
    'tests/combined_source_composition_test.py',
))
DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SHA256 = {
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json': 'b8e82b37ab901f0398fe36d23f54c2eb40f1648598dfa176a448c6dfff6252a4',
    'tests/combined_source_composition.py': '0fe00c64bc9574b3d131fc8f261e5119deba30cae9d5ecd7ddad56df997a41e8',
    'tests/combined_source_composition_test.py': '6847c56cca6ad1f611637d055c7fa12038d011df6f4ffa009fdcc6815e7bb207',
}
DIAGNOSTIC_BLOB_BATCH_BASE = '0f193123770cb6fd12c65b247ea65595f1cd012b'
DIAGNOSTIC_BLOB_BATCH_BASE_TREE = '8d80b68da404743fbdd8e31a9fbfbd071d69d252'
DIAGNOSTIC_BLOB_BATCH_SOURCE = 'e1db46670b098a65ddf8dbceef07cc4f4c912ba4'
DIAGNOSTIC_BLOB_BATCH_SOURCE_TREE = 'a9e4786827d5d2cfc3fc789d9961dd846c8d505f'
DIAGNOSTIC_BLOB_BATCH_MODIFIED = frozenset((
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json',
    'tests/combined_source_composition.py',
    'tests/combined_source_composition_test.py',
))
DIAGNOSTIC_BLOB_BATCH_SHA256 = {
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json': '4136fb2c026de36fdd5ffc753f5b262feaa85c7b6ef4291497270a3b0b86015f',
    'tests/combined_source_composition.py': '0b5c8ad8c27f082d9039011fd3cedc9e060a52e2a5e208c290588d0499d7b11e',
    'tests/combined_source_composition_test.py': 'b09cfde6e024e5cdbb1768bf1384a61c870680333dd41d0e858eae4230514533',
}
DIAGNOSTIC_BLOB_LIVE_BINDING_BASE = '2b7155cf22ad4fef40ecb8bceb1c46e431419175'
DIAGNOSTIC_BLOB_LIVE_BINDING_BASE_TREE = '122696487628c8b0a210fe84c190f9fabe521058'
DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE = 'ecbb38f958a6a6f393160e766ff72ce250ca8bb8'
DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE_TREE = '0e5e447369268f52668c04d0599fbecc460c9949'
DIAGNOSTIC_BLOB_LIVE_BINDING_MODIFIED = DIAGNOSTIC_BLOB_BATCH_MODIFIED
DIAGNOSTIC_BLOB_LIVE_BINDING_SHA256 = {
    'docs/COMBINED_SOURCE_COMPOSITION_20261102.json': '7837778c4c6b516c97cce9710f2488cd5aee5c73794bedcc282a158abf4dea6e',
    'tests/combined_source_composition.py': '0c1722cb738e59bdef453e79843ee16ba447f0fcfda98542e1cba3a8a43594ff',
    'tests/combined_source_composition_test.py': 'd46294728b7a67c629ef40b57845bc53d1ac94cbe718fec44805aa8bb52924a3',
}
DIAGNOSTIC_PROJECTION_CACHE_BASE = '5fd3db7865582a399450bb46d4de79d944bda1a8'
DIAGNOSTIC_PROJECTION_CACHE_BASE_TREE = '77b3b8d9cef787dbe9ea0cf3ed1f5149b8b0f2a7'
DIAGNOSTIC_PROJECTION_CACHE_SOURCE = 'adb88d042d084069f3f90cc13bc5ddf5911a006d'
DIAGNOSTIC_PROJECTION_CACHE_SOURCE_TREE = 'a0f907a2f3d3dcd81a861e85c80789de387c847a'
DIAGNOSTIC_PROJECTION_CACHE_MODIFIED = frozenset((
    RECEIPT,
    VERIFIER,
    REGRESSION,
))
DIAGNOSTIC_PROJECTION_CACHE_SHA256 = {
    RECEIPT: '456cf434f8b444d6f034752c472e53f6715e8e8fde0f45fad7214b9c63da5c36',
    VERIFIER: 'cdc5e18ea8b98c05b09f881c3bfd7970561e3b2f909c740e760c59fb5c659957',
    REGRESSION: '31123ebaad7bcc8e52e8f0ddbd8d6ace0265bbb6f2c6dd44d6225c180854fb01',
}
DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE = '6d3a661d3753fc043dee7287226200d65721e85e'
DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE_TREE = 'db83b1ce9e4c180824600a9f9d9b4136176c4c10'
DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE = '713e37f7ba1ab33d9eb00917f9244a41323b1afa'
DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE_TREE = '31385df47e96c816e66a7a81028cb34c54f2b7dd'
DIAGNOSTIC_PROJECTION_CACHE_RACE_MODIFIED = DIAGNOSTIC_PROJECTION_CACHE_MODIFIED
DIAGNOSTIC_PROJECTION_CACHE_RACE_SHA256 = {
    RECEIPT: '0fa3894a9a7f0353566d89948d230a3f3ad66d7550e3d0d4a1a7a96bfab4b9e5',
    VERIFIER: '57a4cc4834038aa7fa2bc3e91d379e4baabb017ff457a706969e34604f821e15',
    REGRESSION: 'f81ff547d94569e0cc9ca12d69d662765db6399d749500322546b0d75f046a90',
}
DIAGNOSTIC_TRACKED_PREFLIGHT_BASE = 'd8c564ac3dfa9d85e8a58711c70b276369a4e117'
DIAGNOSTIC_TRACKED_PREFLIGHT_BASE_TREE = '770117d64f609c7a0602e3a65debfc70c839199d'
DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE = 'ed8b0159a98613a0e67058fdb536da37b331d22f'
DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE_TREE = 'e166422bf58e5a06da174ac9d52ec4e0e03430da'
DIAGNOSTIC_TRACKED_PREFLIGHT_MODIFIED = DIAGNOSTIC_PROJECTION_CACHE_MODIFIED
DIAGNOSTIC_TRACKED_PREFLIGHT_SHA256 = {
    RECEIPT: 'e02c68e3cb7f1472dcb04dc554f0b14d4d4c976d25fbd5f2d5d68ea8d6d3ec5a',
    VERIFIER: 'cabd41eae6f7bd46e35ee5c89381327167579290a9bea626dc917446757383e8',
    REGRESSION: '19a83d9cb75937f5fcb42cf182c9f320c05d7df9ab00f5d466d57fe4c4ef7cf3',
}
DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_BASE = '81d9876a414c1f3444db167e0bf2b4f9200cba80'
DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_BASE_TREE = 'e47f6364b6f449ec33bbc259ea65cc09ebe0c1df'
DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE = '250720f96c5ad3cf6528f99ae34a4a20fae4c816'
DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE_TREE = 'b5d48256799df01bed6e3aa99a8d2c55f0ca7548'
DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_MODIFIED = DIAGNOSTIC_PROJECTION_CACHE_MODIFIED
DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SHA256 = {
    RECEIPT: 'dea2191541a41f17b3b68009e654d57947f769c95c84bc93a6ff9bdbfdc2f2c1',
    VERIFIER: 'ad8837e253875fdc300ef37b5abea43d99a2c6c3715df3a8ea4a2bfb360da5c7',
    REGRESSION: 'ba167d78d0fc2ca2464954b7cec00c02d6b9f7c09481f493e9e6d9d99fae2232',
}
DIAGNOSTIC_UNTRACKED_PREFLIGHT_BASE = '9f6ad78985f1b42ad2e083bc0fe8efb6d69d3459'
DIAGNOSTIC_UNTRACKED_PREFLIGHT_BASE_TREE = '97670b591fae5b3121b0653ccc9a66625dd45d67'
DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE = '722adc580e70204e33161c721f6fc396b59819b0'
DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE_TREE = '7d7381ebfa283f005cdebceb40dd37cdf39642d5'
DIAGNOSTIC_UNTRACKED_PREFLIGHT_MODIFIED = DIAGNOSTIC_PROJECTION_CACHE_MODIFIED
DIAGNOSTIC_UNTRACKED_PREFLIGHT_SHA256 = {
    RECEIPT: '3e3ba61b94473fea21e8b90fdf84640fe7e237758973c62b6e47f479532cd784',
    VERIFIER: '69d06aada7a567151a9adbcc81b6844d9336a75c55e89663186c0c1f47cfe71a',
    REGRESSION: 'a1a7e3dda485a60ccec2edb8547f284255ec48b0aac027d5e63adedda20b7191',
}
DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_BASE = '95a7c416f97d26155153f0e052ec5579919d6859'
DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_BASE_TREE = '3184ca79076c70988d4c61206bd971c894a9f3ca'
DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE = '4362d0ebe552e8d9955f42d65be0032da143b739'
DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE_TREE = '301656c040d74dc7951648fc0421811f53e4fedd'
DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_MODIFIED = DIAGNOSTIC_PROJECTION_CACHE_MODIFIED
DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SHA256 = {
    RECEIPT: '6da40eeca1ae03a165c675f21b6c6a1a4793f522f0f02b3fde02616d9cca3844',
    VERIFIER: 'edd5afb67012b3826e1840998865bbe097a6da49021ef4dfd9f47081797585d2',
    REGRESSION: 'e5eed6956ae1c0d49ec7abcd4830b600f9284a7c985d52c4e4404eb26332e921',
}
DIAGNOSTIC_RECEIPT_CONTENT_BASE = 'd0dd991fbcaa122a4ea87764230c2b09ffe3d1f6'
DIAGNOSTIC_RECEIPT_CONTENT_BASE_TREE = '96b147eb1b8fa45929db9a04a6cb462ab88b0f5c'
DIAGNOSTIC_RECEIPT_CONTENT_SOURCE = '8871796cf325ec66a24c5bb53378c68c2373dab1'
DIAGNOSTIC_RECEIPT_CONTENT_SOURCE_TREE = '39730fac6e9a4f3fafd3f52167c8b1bbc6ec5236'
DIAGNOSTIC_RECEIPT_CONTENT_MODIFIED = DIAGNOSTIC_PROJECTION_CACHE_MODIFIED
DIAGNOSTIC_RECEIPT_CONTENT_SHA256 = {
    RECEIPT: '6be920b92e8d88a13b91f6c3b839003c39dd808559bf97a71dfc0481df16288f',
    VERIFIER: 'b9ab3df850afadccf32b86086ace5375d72b2c43ed558d58002e49ac48742792',
    REGRESSION: '30fa8987f8535ed837e54631156b2794e6bf2bd565cbaf2c8095d4dfcbe98d78',
}
DIAGNOSTIC_STRICT_RECEIPT_BASE = '761164ac54ad731d9aecb926e81ec57ff880f4f4'
DIAGNOSTIC_STRICT_RECEIPT_BASE_TREE = 'abb8714b0fa93a9fbae7eed063b15662aa73665d'
DIAGNOSTIC_STRICT_RECEIPT_SOURCE = '8c5ae13066a21b6b39b5c42ea03086266e7a2f2e'
DIAGNOSTIC_STRICT_RECEIPT_SOURCE_TREE = 'a56ea559f5e91413b330606eb129fab3b5d2e192'
DIAGNOSTIC_STRICT_RECEIPT_MODIFIED = DIAGNOSTIC_PROJECTION_CACHE_MODIFIED
DIAGNOSTIC_STRICT_RECEIPT_SHA256 = {
    RECEIPT: 'd04e4d42224193c898bff083bc847ca54263edbf5d0842903f2f55aea16f3da6',
    VERIFIER: '75f368aab373e7d9fa95ac04b17cf2e8ce469d95dcf058658d4e59ee6a7f4710',
    REGRESSION: 'bbd2b8694dbe3a97ca0ec3aa8e55a9b8297d8e289cb86d29d547b72618d9f3a9',
}
DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_BASE = 'df850823434b00b3bcf8626ea3759ec2cadf3fb4'
DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_BASE_TREE = 'a0226dc8357c15e78d10f83a8f23a2eb90bb56a9'
DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE = '3ea4f590dae069ca732a1227dd2ae779989a4da9'
DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE_TREE = '2fd869632c856c53a84c12d896184c766201cc67'
DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_MODIFIED = DIAGNOSTIC_PROJECTION_CACHE_MODIFIED
DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SHA256 = {
    RECEIPT: '7271ce3b20462ba1159e3c4281b57bf6e7f3c0e805a6b25c9c93495af26cf313',
    VERIFIER: 'dce754d1130b59451f82b012286143ca7549e83c16777fb79ead81fe42c7e2cf',
    REGRESSION: '8826ecf4db319aaf395c9fbecfb0a5ae950233d93399d3517f4b9bfb9415393d',
}
HOSTING_INBOX_RELOAD_BUDGET_BASE = '20f1cfd76245102f3163038b9401a3db036cc23f'
HOSTING_INBOX_RELOAD_BUDGET_BASE_TREE = 'a93bd0f280d1b8e0de0dd03c94e0fd369728900f'
HOSTING_INBOX_RELOAD_BUDGET_SOURCE = 'c90fa845dc7e87d7b26733e451aa0c477387aab0'
HOSTING_INBOX_RELOAD_BUDGET_SOURCE_TREE = '2736764ce8d0bd56598651c39218aabee78852f0'
HOSTING_INBOX_RELOAD_BUDGET_PATH = 'tests/hosting_purchase_inbox_cases.py'
HOSTING_INBOX_RELOAD_BUDGET_SHA256 = 'f6a3a5e91813df7a3ceb49cefc3192ce727e6b468939206c6d05caf880a1b11a'
HOSTING_INBOX_RELOAD_COMPLETION_BASE = 'cfe07732654a340ea9f00b111e473f5588afe9a0'
HOSTING_INBOX_RELOAD_COMPLETION_BASE_TREE = '4006618189d5f2236e12e85a4f314290265f49ef'
HOSTING_INBOX_RELOAD_COMPLETION_SOURCE = '3f9520ca24fa6a5af100952629b7ca8e09099fd8'
HOSTING_INBOX_RELOAD_COMPLETION_SOURCE_TREE = '2c8092fb4af7f377b8b2218d7d3223cc81c97c19'
HOSTING_INBOX_RELOAD_COMPLETION_PATH = HOSTING_INBOX_RELOAD_BUDGET_PATH
HOSTING_INBOX_RELOAD_COMPLETION_SHA256 = 'c97d2ceee1a2106ad8a51585e63503193dd1a4b0183e71c415883dbf7335f6e0'
NOTIFICATION_METRIC_LABEL_BASE = '535c533d6c5b111d7ea25bf45a435433e0a1b012'
NOTIFICATION_METRIC_LABEL_BASE_TREE = '50abf10bd92c5e4683a8004cfc62bdbf1d0fb5f8'
NOTIFICATION_METRIC_LABEL_SOURCE = '053a3ad1939030c2a4a0bf1607390cbbd1616044'
NOTIFICATION_METRIC_LABEL_SOURCE_TREE = '4304f5f975a17617f10c8611dff33ae96e0a68e1'
NOTIFICATION_METRIC_LABEL_MODIFIED = frozenset((
    'site/app/notifications/metrics.php',
    'site/install/inventory.generated.php',
    'tests/notification_health_view_contract.php',
))
RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE = '8cc167ca4d7dbd8dc9c69e975493d58f7b8f506e'
RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE_TREE = '2d42ad37eadf7ad82c241e271537681e487691f5'
RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE = 'e4cf335f0b52b779afa29bb448ba767ef67f1036'
RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE_TREE = 'd138bee289528fca65e00a718c2f20755835ffb3'
RESULTING_MAIN_CLIENT_INBOX_BUDGET_PATH = 'tests/hosting_purchase_inbox_cases.py'
UX08_INBOX_PAYMENT_READINESS_FIX_BASE = '6aefdbb2d1dc5ce372814886e850aa7b9b8519c0'
UX08_INBOX_PAYMENT_READINESS_FIX_BASE_TREE = '10633b60ac909015a3ca0df3d2d7bde77af62337'
UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE = 'f6e8f9fce4369c51b80410be9bc69c6b0b82df8f'
UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE_TREE = '0a03a416e2166b710e96b486258632df65151457'
UX08_INBOX_PAYMENT_READINESS_FIX_MODIFIED = frozenset((
    'site/app/hosting/checkout_purchases.php',
    'site/app/hosting/payments.php',
    'site/install/inventory.generated.php',
    'tests/hosting_checkout_purchases_guards.php',
))
UX08_CHECKOUT_PIN_FIX_BASE = 'a69b4ae04e9087707ddad59a60cc6afda26b57fd'
UX08_CHECKOUT_PIN_FIX_BASE_TREE = '732d83a18dd6a666369ecfa1b7598583cd4b02dd'
UX08_CHECKOUT_PIN_FIX_SOURCE = 'e27d57a74962dffa1f595504e4aa9e570050a496'
UX08_CHECKOUT_PIN_FIX_SOURCE_TREE = '0885b9271dbf419cbae627c35c8cc80b6a66b201'
UX08_CHECKOUT_PIN_FIX_PATH = 'tools/acceptance-contract.json'
UX08_STOREFRONT_NETWORK_FIX_BASE = '3019274622c502e82b13e650b82ca05fe32afc4c'
UX08_STOREFRONT_NETWORK_FIX_BASE_TREE = 'baab97e10b0d04cb4654fcb59fac93c536203111'
UX08_STOREFRONT_NETWORK_FIX_SOURCE = '14186fc141838334ec20a6b215968b8422798153'
UX08_STOREFRONT_NETWORK_FIX_SOURCE_TREE = 'cb7fa493a9b0b335d5a5d918b596cd01f3587d59'
UX08_STOREFRONT_NETWORK_FIX_PATH = 'tests/catalog_storefront_checkout_cases.py'
UX08_BROWSER_NETWORK_FIX_BASE = 'eafdc88104caba6909ed4614310e50c07eba1990'
UX08_BROWSER_NETWORK_FIX_BASE_TREE = 'dd55856960a45467fde96dba5ddf8e7b300fa1f3'
UX08_BROWSER_NETWORK_FIX_SOURCE = '4bfb6d69aa3339a1ba6957e7d798019430a48869'
UX08_BROWSER_NETWORK_FIX_SOURCE_TREE = '5e1a610be8052b2b909a275eedebdd6d4fa38492'
UX08_BROWSER_NETWORK_FIX_PATH = 'tests/hosting_checkout_http_cases.py'
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
    'docs/DIAGNOSTIC_ACCEPTANCE.md': {'source': '2a456813d05799febff04c3adcaf8c4d1b8ab38a3ff6647d91aaebcf8d7c3593', 'current': '3b8780eb819816e55d729642bdcd4ea11b06e78d9879307151115af631134a04', 'delta': 'f8d85adc615ec23963083f2250aa234d7910d1a32dbee92baffa302fb647069a'},
    'tests/diagnostic_acceptance.py': {'source': 'aa17ed6946f2660a39d2e652ab869cb600c93d2dcd699c98c7a1963ae890bcad', 'current': '4b9505faafe06a4e822999dd2d04e15bd482e3daa673135a5bdc25a1c1e6aed4', 'delta': '1f433b177ab5e6fa78f90aa2212acabb1246fb8ed112f607a110095a4866a4c2'},
    'tests/diagnostic_acceptance_test.py': {'source': 'ce14b1ac0e172b163cb20ba192bb3f302ec063f4c134151360eebf420f738655', 'current': 'd0dae0bc115daf3d8cdfcf8d39cd8199d4f97bcc11bcbbc2fa4ef7801929203b', 'delta': 'f47a2578e25ea89fdd61d07542cd6478c9b1072edba9f6aa22325205b11c0e83'},
    'tests/diagnostic_registry.py': {'source': '1188ef031448d3a56e100ed18f3d357d8f75380b6b6087848c97032e2e93be34', 'current': '823d13a81b24d1c39f0e53a58a26ba727520dcd7b677baa5825a00ded4fce8d6', 'delta': 'f39cb50543cd25907091c8bae4a80db8c174472be372e30e21594275a1907a34'},
    'tests/diagnostic_report.py': {'source': 'cf43434d0a81b35b2103f341a1c6d7e4a59e175d3d7642ae2cce860cd783bc33', 'current': 'e1b06b07dfd74b862e6ccdba78f208982377e5805d9ac43b6b0e736fa86b5181', 'delta': '3e2dd2ca6211956be8b454c73dd9f4c7ac1870a6be15120f6b1214d6b5278863'},
}
# BEGIN GENERATED DATABASE WORKFLOW DIAGNOSTIC IDENTITIES
DIAGNOSTIC_ADDITION_SHA256['tests/diagnostic_acceptance_test.py']['current'] = '2c95149c78aff9dbdf9a7fc15bc1505cdbefda9364e19592190bb253c12f50c2'
DIAGNOSTIC_ADDITION_SHA256['tests/diagnostic_acceptance_test.py']['delta'] = '3fb2893048ae7a4be8dfb2b83ad7b6143df8c795ea98546d4e4d84582d5ac0f3'
DIAGNOSTIC_ADDITION_SHA256['tests/diagnostic_registry.py']['current'] = '8a1626fb36db0d051bab5d5de98329a306cdf2c01edd4d339efa4a4bf4ee5592'
DIAGNOSTIC_ADDITION_SHA256['tests/diagnostic_registry.py']['delta'] = '0a38133b55b7b3040042aba333787530867aa6c855aa1844425f6b752152e955'
# END GENERATED DATABASE WORKFLOW DIAGNOSTIC IDENTITIES
DIAGNOSTIC_ADDITION_SHA256['.github/workflows/diagnostic-tests.yml']['current'] = '26243783910e73df36b0efe90137b56d37d8832f2df7191796ee9fb21c21248c'
DIAGNOSTIC_ADDITION_SHA256['.github/workflows/diagnostic-tests.yml']['delta'] = '334c53286ccf214c8c9ddd210bd53e5105e2508e4b1311bfffe6890ffd3d2fbd'
RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE = '59da2c9a0d4e6fd1be6ba854163e16db279c628b'
RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE_TREE = '3d47557c772b3c4b3f7f5b00d323d172528a16bb'
RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE = 'd2d6ca2be90974e0afc2c9efcba4c5ed2a8e069f'
RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE_TREE = 'e24ee600212384653392f69f95c63715db7b49e6'
RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_MODIFIED = frozenset((
    'docs/DIAGNOSTIC_ACCEPTANCE.md',
    'tests/combined_source_composition_test.py',
    'tests/diagnostic_acceptance_test.py',
    'tests/diagnostic_registry.py',
))
RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SHA256 = {
    'docs/DIAGNOSTIC_ACCEPTANCE.md': '3b8780eb819816e55d729642bdcd4ea11b06e78d9879307151115af631134a04',
    'tests/combined_source_composition_test.py': '84c5139bcd6c00754ad279e211ba95a51eb840d9c0f6712558de69a6593dc98e',
    'tests/diagnostic_acceptance_test.py': '2c95149c78aff9dbdf9a7fc15bc1505cdbefda9364e19592190bb253c12f50c2',
    'tests/diagnostic_registry.py': '8a1626fb36db0d051bab5d5de98329a306cdf2c01edd4d339efa4a4bf4ee5592',
}
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
UX09_CI_BASE = 'a9931eb5231927a7428f0f9116bd0ea389c6429f'
UX09_CI_BASE_TREE = '0a6d040edd825c48c45f9fd03358a16997a83983'
UX09_CI_SOURCE = 'ba6dc64ba55527bf9c79e33da45f89dba61716f7'
UX09_CI_SOURCE_TREE = 'a648dd1a0486481aed7dbeb24a8ae0d1bafc17ce'
UX09_CI_MODIFIED = frozenset((
    'tests/domain_registration_drafts_native.php',
))
UX09_CI_SHA256 = {
    'tests/domain_registration_drafts_native.php': '3cb508d3eeadf0b4e43f28874486220a75326825752489c3eafe5a2c36064d5f',
}
UX09_SECURITY_BASE = 'fe164de009bc815906cf984d22f4d80a7ebd6d0e'
UX09_SECURITY_BASE_TREE = '23217bd9fd2926a3952257e119081a4baf1faeb3'
UX09_SECURITY_SOURCE = 'a2f628953a9296e9f289695df7e2d729e0ae3b37'
UX09_SECURITY_SOURCE_TREE = '8b2476748229795aef979946a2e70f12a6714a3e'
UX09_SECURITY_MODIFIED = frozenset((
    'site/app/clients/public_identity.php',
    'site/app/mail/transport.php',
    'site/database/migrations/client_identity_migrate.php',
    'site/database/schema/client_identity_schema.php',
    'site/install/inventory.generated.php',
    'site/portal/views/auth.php',
    'tests/client_identity_cases.py',
    'tests/client_identity_native.php',
    'tests/client_identity_policy.php',
    'tools/acceptance-contract.json',
))
UX09_SECURITY_SHA256 = {
    'site/app/clients/public_identity.php': 'd8fa45ac54aca93ad9efd44514f714bfdc3a563926758a564048a8951521e2dd',
    'site/app/mail/transport.php': '3ebb02c59eac4471aacb898eea556e3d51993790600b759119d2691d1a043e13',
    'site/database/migrations/client_identity_migrate.php': '715321ea5b558791f02fadb9e8c4cb67e0d6e4f89dcd1734e46be3bf9ff63105',
    'site/database/schema/client_identity_schema.php': 'c33ecb467e98b7606b77fafb2f814c6a1afb5b01a0c2badd7f67220d6402d1d7',
    'site/install/inventory.generated.php': '939686630f755a93c73971bf98c48a9cdd783c5f0fb28b13589975e9e6be70f1',
    'site/portal/views/auth.php': 'a1b875bad315ac56c65e87761d60371baffebf7f3d18a09e5fc6de6e037a05e3',
    'tests/client_identity_cases.py': '7f49245e4947acf80f3245d7c4dc46d456edc9f1323e40756c340ad12fa5def7',
    'tests/client_identity_native.php': '5519d65a40f2362316029b5d55b7979bf3280fddf6940ebff5c7e53b01ef038a',
    'tests/client_identity_policy.php': '6439fd45d0ff0162dd5507b1a7f9b467cb26e0f9dadb5b47f4cbaeca339e4908',
    'tools/acceptance-contract.json': '1129016da1b74943d9fef1798c43f88bd1cab245c2104dc187971fc9ab322ec4',
}
UX09_CLOUD_PROOF_BASE = '83220d9e2e7d53610cb2fa54c8bafd0d409880ce'
UX09_CLOUD_PROOF_BASE_TREE = '2da36d9b84bf407d7f87972026a3e97966c11ad2'
UX09_CLOUD_PROOF_SOURCE = 'f544fa9ea804a8e655216af42ec44f8bc63dc114'
UX09_CLOUD_PROOF_SOURCE_TREE = 'd4295816302f137801ef8d5194e6af7f6f0c06b8'
UX09_CLOUD_PROOF_MODIFIED = frozenset((
    'tests/cloud_site_intake_wiring.py',
    'tools/acceptance-contract.json',
))
UX09_CLOUD_PROOF_SHA256 = {
    'tests/cloud_site_intake_wiring.py': '4d49cad5f9b51fda266860ec25abab366377a1912b4caedf5b055109a1005d86',
    'tools/acceptance-contract.json': '5b84c8d5cbbe028d90cb19aefc5d6db6e4cc0d1d628f61a0fb78af8e7d4cba55',
}
UX09_MAIL_REVIEW_BASE = 'd1e0b4c2427c61b808d7a4ed24089ef07dfab296'
UX09_MAIL_REVIEW_BASE_TREE = '8029360355ad3b26bae6eaebd010a0c703ecd5ca'
UX09_MAIL_REVIEW_SOURCE = '8b55480dd64c7134a979088ee34c1b80f2218f21'
UX09_MAIL_REVIEW_SOURCE_TREE = 'f6b8f8a8590c5f90310b628cf46f3de413982521'
UX09_MAIL_REVIEW_MODIFIED = frozenset((
    'site/app/clients/public_identity.php',
    'site/app/mail/transport.php',
    'site/install/inventory.generated.php',
    'tests/client_identity_native.php',
    'tests/client_identity_policy.php',
    'tools/acceptance-contract.json',
))
UX09_MAIL_REVIEW_SHA256 = {
    'site/app/clients/public_identity.php': '18a583d7008ac75b4f99fb5803adcded757883f4ab2f89a16396fe9ee19019d3',
    'site/app/mail/transport.php': '2f43b31ce1d0e36c1880e12c82ee847328305c07f745a11073d334473da7d8ca',
    'site/install/inventory.generated.php': 'f03ac9ec4062c30ada95be5542e071791ff9714b37e4f1e9d585b2a39274539e',
    'tests/client_identity_native.php': 'fd3b534b8023a6d6fc34cd1549c53c049ab8a210d51f993234f48cc27968a3f8',
    'tests/client_identity_policy.php': '8e83106f2ea03275bb806b4413cd2da8dc07a4ebd2b4a42176b4ed98a86f8530',
    'tools/acceptance-contract.json': '647284bd38be6094e699dbe317d9cdffacb9a868560d97c0aee98db741d9a1a8',
}
UX09_SMTP_REJECTION_BASE = 'bb64fe65c1b99c3e90da70ba21345cfdb615457d'
UX09_SMTP_REJECTION_BASE_TREE = '088bab54a414c2661a1cba1bb3c3278267fe5afe'
UX09_SMTP_REJECTION_SOURCE = '26930e830544d6fe68ef426999e8d81de688a4fe'
UX09_SMTP_REJECTION_SOURCE_TREE = 'dc05d778d289bb955568dfca546d3bede5d81b7e'
UX09_SMTP_REJECTION_MODIFIED = frozenset((
    'site/app/mail/transport.php',
    'site/install/inventory.generated.php',
    'tests/client_identity_policy.php',
    'tools/acceptance-contract.json',
))
UX09_SMTP_REJECTION_SHA256 = {
    'site/app/mail/transport.php': '3b4e684a05ccb7de31b81ae4fed76304ff8faee5befb716a5be58aee0971a8ce',
    'site/install/inventory.generated.php': '8f34c3841018f675fc3d7fad32b145b3521a8fea12846efce3d9bb928ace46c0',
    'tests/client_identity_policy.php': 'f613746da519f5ea009d1cad8c7c7736bc6a3e93ff5cabae2e86ace85baca436',
    'tools/acceptance-contract.json': '6cba59bbdc8116bd14147febb1463a43a62a31aef298987b20559d85dac098c8',
}
UX09_IDENTITY_SNAPSHOT_BASE = '3e4beab4f0e8b28e30241b48eae1cfaff1792a32'
UX09_IDENTITY_SNAPSHOT_BASE_TREE = '25fbce58d06c4820c02609be40cfec4e93d47645'
UX09_IDENTITY_SNAPSHOT_SOURCE = 'c6f6222e4208a9b6a4e5bbf384ea921a94f82ee4'
UX09_IDENTITY_SNAPSHOT_SOURCE_TREE = '214dda6e82b88bd493605103cd6db39a2c445e59'
UX09_IDENTITY_SNAPSHOT_MODIFIED = frozenset((
    'tests/client_identity_native.php',
))
UX09_IDENTITY_SNAPSHOT_SHA256 = {
    'tests/client_identity_native.php': '74b25adc1dcd6028a7ef332de1aa0dbc00fba285139ff40d5eeab77ada36555b',
}
UX09_FIXTURE_RECEIPT_BASE = 'ca81be662b0c01dd85d223540a191beb04f7ba2e'
UX09_FIXTURE_RECEIPT_BASE_TREE = '7e57dbda6e403c9fd5af6c2ca56703fa929a42b3'
UX09_FIXTURE_RECEIPT_SOURCE = '1e40f8aa245c8318013aaa86b9a3fcc3b8d13feb'
UX09_FIXTURE_RECEIPT_SOURCE_TREE = '03b6ee8b65d0aad4c5feb202e084636480959dc3'
UX09_FIXTURE_RECEIPT_MODIFIED = frozenset((
    'tools/acceptance-contract.json',
))
UX09_FIXTURE_RECEIPT_SHA256 = {
    'tools/acceptance-contract.json': 'fa43b8a278a72d9975b29858e45cb41bc9637643ed457ad0dc4d51af4014df69',
}
UX09_COMMERCIAL_SNAPSHOT_BASE = 'defe92eafef2c928ae24bfa4793d3708ccfe47ea'
UX09_COMMERCIAL_SNAPSHOT_BASE_TREE = '3ec9fa3c7839d6bf7b274eae139c5cde1cc83ec7'
UX09_COMMERCIAL_SNAPSHOT_SOURCE = '1dfc7b5ad5e403b7221092cf598f75dc8c18cb13'
UX09_COMMERCIAL_SNAPSHOT_SOURCE_TREE = '7b1c3f6dfa921df268a5c762c3b428ab9fda674a'
UX09_COMMERCIAL_SNAPSHOT_MODIFIED = frozenset((
    'tests/client_identity_native.php',
    'tools/acceptance-contract.json',
))
UX09_COMMERCIAL_SNAPSHOT_SHA256 = {
    'tests/client_identity_native.php': 'cf1204220ff950783a3774985ee25784cd0462599d1424c8f6f719dc462ad68b',
    'tools/acceptance-contract.json': '294647ae675ef0f9168ba5431aebb539838c6d7a293552e344486e4f6b04ab74',
}
UX09_AUDIT_ROLLBACK_BASE = '6c1f9788c83573e09226ec8e6d259424f4409002'
UX09_AUDIT_ROLLBACK_BASE_TREE = 'fb36caaaf5adfb0e2e29538a690b271cd4853f7d'
UX09_AUDIT_ROLLBACK_SOURCE = '7f075128cdb426e72da699e3187e92108eeb9007'
UX09_AUDIT_ROLLBACK_SOURCE_TREE = '8e674aef10b5cd5ec33b2dab83463b06d99dc10b'
UX09_AUDIT_ROLLBACK_MODIFIED = frozenset((
    'tests/client_identity_native.php',
    'tools/acceptance-contract.json',
))
UX09_AUDIT_ROLLBACK_SHA256 = {
    'tests/client_identity_native.php': '07d7facdcebab76f8e099908f759c1431772385d88679267db5e74da0eb480f8',
    'tools/acceptance-contract.json': '54050615e7c338335ce73ac84ab8097707f750ee63d703bc487918a326c22695',
}
UX09_BOUNDED_TOKEN_REFUSAL_BASE = 'bd2ebf58eb260a1eed4f82e616009601edb4b221'
UX09_BOUNDED_TOKEN_REFUSAL_BASE_TREE = '444a4c2db86e0d3287579c7ba3e368a5efc1d953'
UX09_BOUNDED_TOKEN_REFUSAL_SOURCE = '64fa0f27354ae6e42bf5bd2762997fb4baffc4c6'
UX09_BOUNDED_TOKEN_REFUSAL_SOURCE_TREE = 'bbda9f336d8d3fe8e995ae2e86a9d49f4f49d828'
UX09_BOUNDED_TOKEN_REFUSAL_MODIFIED = frozenset((
    'tests/client_identity_native.php',
    'tools/acceptance-contract.json',
))
UX09_BOUNDED_TOKEN_REFUSAL_SHA256 = {
    'tests/client_identity_native.php': '2032d7f58be86267cb1cd98443acb81caa56f8995db1ccc23a784cb00fbde7b6',
    'tools/acceptance-contract.json': '052a73eac648aac088eea50f4f80c3114a76b4e8f2ab73b1866f02d4568ce7b3',
}
UX09_MIGRATION_PRESERVATION_BASE = '8e5deea056a1bc31b43b27fd5fcd8db0f9318e1e'
UX09_MIGRATION_PRESERVATION_BASE_TREE = '2dd0e788949af43e037228a64165af9fefac078b'
UX09_MIGRATION_PRESERVATION_SOURCE = 'ec6cfdb1ee89a0fc84b194fb571887fb6975d5ad'
UX09_MIGRATION_PRESERVATION_SOURCE_TREE = '9368e65bd354033f5326f3a86bdfa281028527da'
UX09_MIGRATION_PRESERVATION_MODIFIED = frozenset((
    'tests/database_update_cases.py',
    'tools/acceptance-contract.json',
))
UX09_MIGRATION_PRESERVATION_SHA256 = {
    'tests/database_update_cases.py': '239c55f65e0d41ab5612013673239d1adbe03338d1f03bd18d75ca820f1a2278',
    'tools/acceptance-contract.json': 'f68d2d5b5623423bce060c0e3fdec4f95340ed448c8f640e7ea2c7d1fc89de04',
}
UX09_DIAGNOSTIC_REGISTRY_BASE = 'e524f8db249f33431db9a0b6424ab09fe469cd6e'
UX09_DIAGNOSTIC_REGISTRY_BASE_TREE = 'dedc3dc7ffbaf28a0e4cd374021dc373c2d9f47a'
UX09_DIAGNOSTIC_REGISTRY_SOURCE = '9aa577fb2ea3850f5b6c7b4af8c1b49222e06ee3'
UX09_DIAGNOSTIC_REGISTRY_SOURCE_TREE = '4dda631be94941d503e8f3b05a07b6fa8cce3d97'
UX09_DIAGNOSTIC_REGISTRY_MODIFIED = frozenset((
    'tests/diagnostic_acceptance_test.py',
    'tests/diagnostic_registry.py',
))
UX09_DIAGNOSTIC_REGISTRY_SHA256 = {
    'tests/diagnostic_acceptance_test.py': '1558fbe6e463794743ecb3722b9bb49dcdaeae96f54f4132cb7b37c219647bc2',
    'tests/diagnostic_registry.py': '19c911bd6fcd4aa4150910bc6b2ce8a57bbbb174582f5f133bce6cd33c7d6f2d',
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
    'docs/agent-handoffs/2026-11-15-codex-pr394-inbox-navigation.md',
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
REGRESSION_SHA256 = '6197a6cb8865a5bcc41188456240381b7872bddfe65ca7288d87b21198115dba'
RECEIPT_CONTENT_SHA256 = 'c98b6138afa9a3f9753a5c62b14eea0d83380a4cd60e198fd979e09ae896df75'
# END GENERATED COMPOSITION IDENTITIES

# BEGIN GENERATED INTEGRATION REPAIR IDENTITIES
REPAIR_SHA256 = {'.github/workflows/application-tests.yml': '5db94d83c68397a964baae19329fa7522fe4d8e2204ca3de0270c40a65ab0ef6', '.github/workflows/consolidation-after-backups.yml': 'd3e4cf7343cb1454917c691af8acb7b7456e704e735bd7fa5695a646c19279b5', '.github/workflows/rc126-test-package.yml': '7902b61c4bda1946e9d768b9d793afab1b832c2de8fa1d667c792f1caf739ba3', '.github/workflows/software-webhook-contract.yml': '4926777bcb6c1b2bde6260a5904a191af89ddebab505ea6d29e5d04b41450142', 'docs/CLIENT_PORTAL_ROADMAP.md': '8e31a75b47ea79d8059bbaf9a8c530426c47a5299bd8fb6b65806d4ba42c75bd', 'docs/LICENSING_WORKSPACE_INTEGRATION.md': 'de5df463294abf413b918f70b57da80040cb6b23b108a36f9e53b47b2edefea0', 'docs/RELEASE_WORKFLOW.md': 'ae72fcdf222802c81f1769eb42c3c7e17a61afdcd26ae650c01d788ed780bae6', 'site/admin/controllers/catalog.php': 'ed9aba12ab012011a34b59c6cd0ea2b1c356df0d51b01ffa67ef24484672761d', 'site/admin/controllers/studio.php': 'b19f05cc44f88489523c53079f229b46c06419b27c68b1b6c367be01f92b4b26', 'site/admin/views/catalog_import.php': '12fd6799e7f2c1805c245b4ba969fe291a70c5944a93707dc499ca027e9a53eb', 'site/admin/views/studio/clients.php': '5d42172fb9767e187564c4af59d75c519f5f2c942593f5c59d037b98358370b3', 'site/admin/views/studio/job.php': '87f17d26b213db3ae9b03d343f7a5afc4e215691b34d7507f206e372d9f92f5e', 'site/admin/views/studio/upgrade.php': 'ab60c55217f08d6fc021cea3a9e996feed1c1e289cac6b1f5717b9706d8c9239', 'site/api/bundle-download.php': 'abd65140460543c13b99d3c20e5454306664f084d505ea57b90e5752b17aa4bf', 'site/api/software-download.php': '702c86f6f7ba0bedcb9fc464efb6418701b94fb044bd5718491095d205cc57dd', 'site/app/activity/events.php': '011a2fc5ce9dc93cd414e4ed637157d1c772e56f321d3fd8e9c0ba18a72508e4', 'site/app/billing/authorize_net_webhooks.php': '4d79e37255166518fa64f7e86b21d48b2d8ea933a07da08f8d65c4b45b21d8de', 'site/app/billing/catalog_browse.php': '82b296b1fa8b48d9e29e30e580a290e020803353c9848284eb753287a8f61c38', 'site/app/billing/software_subscription_settle.php': 'cb9f7486e57079d82ca786a2c04e71a98edd95b42d1d8d5ab5e23784ffcd17a2', 'site/app/clients/accounts.php': '9535abe5b5688a82e630c5fc84d58b661c882082eb8358de5b1b9f928ea0e5cd', 'site/app/clients/files.php': 'e8bbd51f4c4b6baf518ff4ae7dab866f6b46bb59a3f434ef78f51df19f360db0', 'site/app/clients/identity.php': '61df7e8e02ca3362a27dfcdad7841df065d201d0d3dcc25a900f71ccdd5cecc7', 'site/app/licenses/financing_offers.php': '2b87d8cfc93cd31594eba6143390413d326e7f637393b22e4b243de869ddd447', 'site/app/marketing/proposals.php': '41f5e7afcc6d680ed801bcc685b3e5c5ac8450263bd945c30b7d84f8cf534bcb', 'site/app/notifications/events.php': '8a55bb62ebe6b8d2a8ad03e60a93610d3b48af2bbab8a64eee8895ed0decbce4', 'site/app/notifications/feed.php': '1013c10b3452d903a49e772dd326e6ffdf0d0b9306ec5302bfd4813f27b5725c', 'site/app/plans/records.php': '985e2472db522bb70ccaa0205f95f21e4e5030bf5596b091864617f91c1e4b04', 'site/app/staff/accounts.php': 'b7d58ff6866f6ceae240f1325555d230b4a24ab291dd1dc01469031bdf8465ec', 'site/app/support/ticket_choices.php': '4e91ccd9764e814f3a4d9e6e8902632a148e6fd6a4e72e6869c1c4b216081167', 'site/app/support/ticket_notifications.php': '058f41c8c146ebe82cf72bad8fa7e9bc754e1e58b791d52e9169f0795fd48867', 'site/app/support/ticket_reads.php': '5e824bdfbc9a37fe77110464d670ae58b0ea7ccb7bcd9c635f7a449082a06868', 'site/app/support/ticket_views.php': '313d6bdfe400ee63be9534a0ccf1e014a14db71922fe7e8af68de5adf381100b', 'site/app/support/tickets.php': '1e9183db114a24a648ccdc7b66514ac5ab94066925237be22bf8abab0b8b5dd1', 'site/assets/css/admin.css': '861d805127a37b750eed5dab06320d98583568d762b688af56415884410ecab5', 'site/assets/css/premium.css': '790097578fe9219d52a8581e3aae761c4646b5145590ae4b6e15d74a690d1743', 'site/assets/js/site.js': '3da42a595a67d5b8fea689785267353f0424a90ac03bb9d80c4d8e6a160cef16', 'site/database/schema/base.php': 'b0de5962e671267dba3dde791a9f4bf90b2cfe3967d803038d663562a51c4cea', 'site/includes/layout/admin.php': '650149a9b80e1158b17deed975b2ae582d81910ea6258627a395812a277ba4b6', 'site/includes/layout/notifications.php': '222d1869e75dc47415617814c508f5132cca130291d7a2642609b4c05e705bb0', 'site/includes/layout/proposals.php': 'e4ed91ec5a436d05f0febbe4ab340f317a6205de9a6a5f5c47eb830daa223e17', 'site/install/actions/deploy.php': '1c5cd699653e278cc2e56bf336039b3765fc00e5c220040068e33487b426917a', 'site/pages/home/body.php': '77c19a7ae72842d9bb1a8cfe73d25de8c6c46f0f845adc1dd1266e210e11962c', 'site/portal/controllers/actions.php': 'e0bfb2c185492d09b43747f9cda83ad28f67c41211b126c60b6081bbe1de4226', 'site/portal/controllers/router.php': '3d00beebce20b6c700232e091b60ecee9766c34751a0496ec5dc1806a0720a63', 'site/portal/views/auth.php': '4d0a9b73b32ac6230f2ace6c0b822107ab0fd0d2a015c46023df9acf5aec4b95', 'site/portal/views/financing.php': '37f385593cb7fe34beb5e24020602b9c27b178062b6e704d86f72945356d045c', 'site/portal/views/software.php': 'a36d3e71f9abfb327e3ed6707c16e0a7dcbe811e735358fd7c8a3301de7524fc', 'site/portal/views/tickets.php': 'e584022a5f53b56c05623176a854ba8f7e160a1834cac056602072ccd8a80767', 'site/portal/views/workspace.php': '3559010a49bcbbacfe0aac0c70d440a6f0de18df262372a8ffb53b4e10c28e61', 'tests/acceptance_artifacts_test.py': '9c1da1c8bafbc2febe87d66d5c1d6368e8e0c159e848fd54a3a64ae2108763dc', 'tests/account_notification_workflow_contract.py': '9f32e3e4e6e64d0b36a8afc9da2d7ae09f1c3a3c995e9246671cb484447ac8c1', 'tests/backups_acceptance_wiring.py': '525486323025784b00ffa7d347f6663a6a5aa6e86574830c77ba5b18f1116741', 'tests/browser_acceptance.py': '3b8f81333b9712eb8aa3a5c6bb4e5f28b4ab64687933e7f333016d6a2e053f80', 'tests/build_guards.py': '7df4f04b58418ef27d33ee53c175cf4eb61e2348988f0c4b79909a063ad049f2', 'tests/catalog_storefront_checkout_cases.py': '843048d926ca4187c9a2f60a3a360b43e46311f336b5c32573f4f2af76778213', 'tests/client_authority_native.php': 'af236ef91f9bcb33b219e8f8d34774ed56c652ef8dd00f69a2f97115d3a7fab9', 'tests/cloud_site_requests_wiring.py': 'ee50481194217b3c6f669347f6fd64275ef87b8b07b800939f2325af51eca27e', 'tests/finance_initial_enrollment_native.php': 'f61066d00ae989965130c0f8965bb0300c45ab45bbe220bc1b95ff6029020f38', 'tests/hosting_billing_options_cases.py': '4ac8630913e7812e11f536bc8663a2dd06891f49e5c2c4c1ed119ae713334cc5', 'tests/hosting_billing_options_wiring.py': 'b59b8e4bc1a017abdb77fc9fb309e98ff13029bf652d4e82ae0e5ac2aee274a0', 'tests/hosting_catalog_plan_setup_cases.py': '51bd299de65d61e40f2ec34bf7deccad452e14281761e0a0b2a38b54ca281508', 'tests/hosting_checkout_http_browser.py': '507c9ff9a450a9a09f4d8750711d0bd363232474fa13e4b14899692b18f1b5aa', 'tests/hosting_checkout_http_cases.py': '14401d06c4295f5bca794db60d875689c0de7de7c231fcac390fe0e72346e509', 'tests/hosting_checkout_http_fixture.php': '77d69fab3506b1e6a903318efdacfe168c1afb047b63bc7b7fe461099dce610d', 'tests/hosting_checkout_native.php': '7690790bc095fca8f7690518390ab36e3f8ec83d53b40f10e4161f823f0aef97', 'tests/hosting_purchase_inbox_cases.py': 'c09947207bb3b996f325e630f0a57b419369626d2be5808747a30ceca75dfe89', 'tests/installed_owner_authority.php': 'bbc48036af794564a1b39b54eb218d3390f94a65861d355d5e4ccef4c7c69863', 'tests/installed_owner_authority.py': 'a9511d52459ac1a9d50d62b646198ab29258718491f0c53f339bf32d7f0e3b71', 'tests/installed_owner_authority_test.py': '48e1930aa6d3e0d8ea7d02a5001718e68c15463fb9b9c62e6cfb2513b69c8101', 'tests/license_financing_cases.py': '7c23b44721f97a718d99b06e449dbd379bfab856ec7452a9d193ef7b031ec3c1', 'tests/license_financing_native_cases.php': '230a6a2ef6d39fcecff1a028b3941e7ef3e87e9154ceb17ab82f75d69cc5ff0d', 'tests/licensing_workspace_browser.py': '3607b7c09f72c508e3bdc8ff72cd51d2cd7f1f764a0cc4a377d29ef5fdb46660', 'tests/licensing_workspace_contract.php': 'b77832e716a06d29d050d9e731bda48e60dfed15c92f48383acb482118e5ba04', 'tests/organized_update_cases.py': '9ffe696d822add3a7013e72cb6a99f5bbe44994d5fc83ebe9d2d8c5d15322674', 'tests/owner_browser_forms.py': 'abb07881c43514fd6ac9241700fb2fe97a881095b122d3d092a4ef82924616a3', 'tests/owner_browser_forms_test.py': 'ae4cac11289a97e2d20cf4755ed924f7f438e01d8c5550994ff7b86a144a4b54', 'tests/payments_cases.py': 'b5b4f3e9e3fd23474f298832a22a14672c0f61e50d55b0c57b21d4c0e06755e6', 'tests/payments_native.php': 'e732ca439d3bb460cc530714dcd03b70f332282c2681f75ed2324a114e3c6316', 'tests/proposal_version_binding.php': 'e2b01d643092b15eebc8cc8b2b22aa5b4eb22a4bb30bce4b970249c09c8bdbbd', 'tests/proposals_native.php': 'a3ab274887a5f4b6b65fd1bdef447adee9fbd820d467402f81bdc4ccddf8611d', 'tests/provider_catalog_import_batch_views.php': '1205def0109cc6aa2ef8bec65186bdb50c185040b4596a2808eca4f9ad59b1f3', 'tests/provider_catalog_import_native.php': '09daadc349d961a472eb15e14d07f08364498add25200b580587a9ffcfa9c37e', 'tests/provider_directory_listing_controls_cases.py': 'de983d82a43bfe75c1f01fa443be944451bd1fdba16ef9c77e4c0c161096f25f', 'tests/provider_force_ssl_controls_cases.py': 'a764c99c984514afb9b9d6ac129fd272ead523a274ab9c8ebd29e6c6436e6d68', 'tests/provider_schedule_cases.py': '2a0bbd5fd2a67195a17242339810e2dee2196f2d212b16835e314024578862ca', 'tests/provider_workspace_browser.py': '07ef304967ed4962e0025563765fd37810d795b0f7180357100a7544dac86ba2', 'tests/provider_workspace_fixture.php': 'b339fe8be82869fe321e20b3061b0ceb95e668a3e324e8fc497b8c9c4aa0ecf1', 'tests/provider_workspace_policy.php': 'e7c727c040693d62d9ef42ac869cd0433c417e8c6e6db3587aeed49d89c9021b', 'tests/provider_workspace_render.py': '8f70ecd0aca67c31923a916af8126f79d20466444878b2145813f20aecd97673', 'tests/release_cases.py': '11669b9afbfa66a95de06e0c9617d01a4d80841550ff391873758b02b17aa3da', 'tests/reset_template_acceptance_contract.py': '7d9d58a4fafacddd8b3d9d62ffe5222a4099e297c8cf05aaf00e54e2f52bf3a9', 'tests/service_plan_cases.py': '0d4a34bff3eacbe3a8051315e04953cdbfe7be1438106eddfa8707877425e363', 'tests/service_plans_native.php': '45a2c1d888bee7b694b06f6f873fe0dc6874a4c3b0234d937e7d70abbc927058', 'tests/software_webhook_contract.php': '570e44c6d6f2369d1793a885033e95151e28ed1dcdbc21b996a020e980665c2c', 'tests/support_tickets_native.php': '2128bba21f9d53410e5077dfe5566a988bbe14a27bdd270298d52baf5fc58e19', 'tests/ticket_cases.py': 'dc3bf485e8473588661b8bfaeb88ef8caab87281d5111e23ed987957af4938bf', 'tools/acceptance-contract.json': '068d0aac2cd983215bd90decfa396fbbf1637813836bfe7c44b580adb99c3f2c', 'tools/acceptance-reports.json': 'c50ee66cba8731b60877687fdc18d76146e67b8e3ffe7e532ad09d89bd52fa91', 'tools/build.py': 'bf76464dc1e736b76a892a5662b65134eeb8a558f1c2f39e0359f39463725ad4'}
REPAIR_SHA256['tests/admin_session_no_reentry.php'] = '8869b683a3996819428fa13d87dc3f94841cb53121838317d8de32a4ee190887'
REPAIR_SHA256['tests/backups_acceptance_wiring.py'] = '366f985b84a61379fadfc48a170e60483599c17fc118c52a8892c6865cf343d9'
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
_TREE_ENTRIES = OrderedDict()
TREE_ENTRY_CACHE_LIMIT = 16
_IMMUTABLE_GIT_RESULTS = {}
_PINNED_HEADS = {}
_BLOB_BATCHES = {}
_ACTIVE_ROOT_KEYS = {}
_PROJECTION_CACHE_LIMIT = 32768
_LAST_PROJECTION_CACHE_MAX = 0
UPSTREAM = 'https://github.com/afterburn25/Cajun_Web.git'


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def strict_json_object(pairs):
    """Reject duplicate JSON names at every object depth."""
    result = {}
    for key, value in pairs:
        require(isinstance(key, str) and key not in result,
                'Duplicate or malformed composition receipt name: ' + repr(key))
        result[key] = value
    return result


def reject_json_constant(value):
    raise AssertionError('Non-finite composition receipt value: ' + value)


def strict_json_loads(value):
    try:
        return json.loads(
            value, object_pairs_hook=strict_json_object,
            parse_constant=reject_json_constant)
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise AssertionError('Malformed composition receipt JSON') from error


def receipt_content_digest(data):
    """Bind receipt content without its two deliberately self-binding hashes."""
    require(isinstance(data, dict), 'Malformed composition receipt content')
    canonical = {
        **data,
        'verifier_sha256': '',
        'regression_sha256': '',
    }
    return digest(json.dumps(
        canonical, sort_keys=True, separators=(',', ':'),
        ensure_ascii=True).encode('utf-8'))


def present(root):
    path = root / RECEIPT
    return path.exists() or path.is_symlink()


def root_key(root):
    """Reuse one verified root identity only during a complete proof.

    Resolving the same directory hundreds of thousands of times adds no source
    coverage.  Outside the bounded verification context every caller still
    resolves the supplied path afresh.
    """
    literal = os.fspath(root)
    if literal in _ACTIVE_ROOT_KEYS:
        return _ACTIVE_ROOT_KEYS[literal]
    return str(Path(root).resolve())


def immutable_git_query(args):
    """Cache only queries whose answers are fixed by complete object IDs.

    The composition regression deliberately changes real worktree files between
    hundreds of negative checks.  Those files must always be read again.  Git
    commit, tree and blob objects named by complete IDs are content-addressed,
    however, so starting a new Git process for the same immutable answer cannot
    add coverage and makes hosted acceptance depend on runner process speed.
    """
    oid = r'[0-9a-f]{40}'
    if len(args) == 3 and args[:2] == ('cat-file', 'blob'):
        return re.fullmatch(oid + r':[^\x00\r\n]+', args[2]) is not None
    if len(args) == 2 and args[0] == 'rev-parse':
        return re.fullmatch(oid + r'(?:\^|\^\{tree\})?', args[1]) is not None
    if (len(args) == 4 and args[:3] == ('show', '-s', '--format=%P')
            and re.fullmatch(oid, args[3])):
        return True
    if (len(args) == 4 and args[:2] == ('merge-base', '--is-ancestor')
            and all(re.fullmatch(oid, value) for value in args[2:])):
        return True
    if args and args[0] in {'diff', 'diff-tree'}:
        boundary = args.index('--') if '--' in args else len(args)
        revisions = [value for value in args[1:boundary] if not value.startswith('-')]
        return len(revisions) == 2 and all(re.fullmatch(oid, value) for value in revisions)
    return False


def _read_immutable_blob(process, expression):
    """Read one exact Git blob from the framed binary batch protocol."""
    require(re.fullmatch(r'[0-9a-f]{40}:[^\x00\r\n]+', expression) is not None,
            'Unsafe immutable blob expression')
    process.stdin.write(expression.encode('utf-8') + b'\n')
    process.stdin.flush()
    header = process.stdout.readline(160)
    match = re.fullmatch(
        rb'([0-9a-f]{40}|[0-9a-f]{64}) blob (0|[1-9][0-9]*)\n', header)
    require(match is not None, 'Invalid composition batch blob header')
    size = int(match[2])
    data = process.stdout.read(size)
    require(len(data) == size and process.stdout.read(1) == b'\n',
            'Truncated composition batch blob')
    object_digest = (hashlib.sha1 if len(match[1]) == 40 else hashlib.sha256)()
    object_digest.update(b'blob ' + str(size).encode('ascii') + b'\0')
    object_digest.update(data)
    require(object_digest.hexdigest().encode('ascii') == match[1],
            'Composition batch blob identity differs')
    return data


@contextmanager
def immutable_blob_batch(root):
    """Use one verified child for immutable blob reads in one full proof."""
    resolved_root = root_key(root)
    require(resolved_root not in _BLOB_BATCHES,
            'Nested composition blob batch is forbidden')
    with tempfile.TemporaryFile() as errors:
        process = subprocess.Popen(
            ['git', '--no-replace-objects', 'cat-file', '--batch'],
            cwd=root, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
            stderr=errors)
        _BLOB_BATCHES[resolved_root] = process
        try:
            yield
            process.stdin.close()
            require(not process.stdout.read(1),
                    'Unexpected trailing composition batch output')
            code = process.wait()
            if code:
                errors.seek(0)
                raise AssertionError('Composition batch blob reader failed: '
                                     + errors.read(8192).decode('utf-8', 'replace'))
        finally:
            _BLOB_BATCHES.pop(resolved_root, None)
            if process.poll() is None:
                process.kill()
            process.wait()
            try:
                process.stdin.close()
            finally:
                process.stdout.close()


def git(root, *args):
    resolved_root = root_key(root)
    pinned = _PINNED_HEADS.get(resolved_root)
    pin_revisions = bool(args and args[0] in {'merge-base', 'diff', 'diff-tree'})
    cache_args = tuple(
        pinned + value[4:] if pin_revisions and pinned and value in {'HEAD', 'HEAD^{tree}'} else value
        for value in args
    )
    cache_key = (resolved_root, cache_args)
    immutable = immutable_git_query(cache_args)
    if immutable and cache_key in _IMMUTABLE_GIT_RESULTS:
        return _IMMUTABLE_GIT_RESULTS[cache_key]
    # Execute the same full-object-ID command used as the cache key. A mutable
    # HEAD spelling must never be evaluated and stored under its earlier pin.
    active_batch = _BLOB_BATCHES.get(resolved_root)
    if (immutable and active_batch is not None and len(cache_args) == 3
            and cache_args[:2] == ('cat-file', 'blob')):
        output = _read_immutable_blob(active_batch, cache_args[2])
    else:
        result = subprocess.run(
            ['git', '--no-replace-objects', *(cache_args if immutable else args)],
            cwd=root, capture_output=True)
        require(result.returncode == 0, 'Composition needs the existing full-history checkout and component objects: '
                + result.stderr.decode(errors='replace')[-1500:])
        output = result.stdout
    if immutable:
        _IMMUTABLE_GIT_RESULTS[cache_key] = output
    return output


def pin_head(root):
    """Bind HEAD once per verification root and refuse any later movement."""
    resolved_root = root_key(root)
    replacements = subprocess.run(
        ['git', '--no-replace-objects', 'for-each-ref', '--format=%(refname)', 'refs/replace'],
        cwd=root, capture_output=True)
    require(replacements.returncode == 0 and not replacements.stdout.strip(),
            'Git replacement refs are forbidden during composition verification')
    result = subprocess.run(
        ['git', '--no-replace-objects', 'rev-parse', 'HEAD'], cwd=root, capture_output=True)
    require(result.returncode == 0, 'Composition cannot resolve current HEAD')
    head = result.stdout.decode().strip()
    require(re.fullmatch(r'[0-9a-f]{40}', head) is not None, 'Invalid current HEAD identity')
    require(resolved_root not in _PINNED_HEADS or _PINNED_HEADS[resolved_root] == head,
            'Composition HEAD changed during verification')
    _PINNED_HEADS[resolved_root] = head


def parse_tracked_source_drift(raw):
    """Decode one NUL-delimited Git path list without path normalization."""
    require(isinstance(raw, bytes), 'Malformed tracked-source preflight output')
    require(not raw or raw.endswith(b'\0'),
            'Tracked-source preflight output is not NUL terminated')
    encoded = raw[:-1].split(b'\0') if raw else []
    if encoded:
        require(all(encoded) and len(encoded) == len(set(encoded)),
                'Tracked-source preflight output has an empty or duplicate path')
    paths = []
    for value in encoded:
        try:
            path = value.decode('utf-8')
        except UnicodeDecodeError as error:
            raise AssertionError('Tracked-source preflight path is not UTF-8') from error
        relative = PurePosixPath(path)
        require(path and not relative.is_absolute() and '..' not in relative.parts
                and relative.as_posix() == path,
                'Unsafe tracked-source preflight path: ' + repr(path))
        paths.append(path)
    return tuple(paths)


def tracked_source_preflight(root, proof_files):
    """Reject non-proof tracked drift before expensive historical projection.

    The receipt, verifier and regression suite are deliberately allowed to be
    uncommitted while a new proof commit binds its direct implementation
    parent. Their exact bytes and ordinary modes are validated before this
    function runs. No other tracked path may differ from the pinned commit.
    """
    resolved_root = root_key(root)
    head = _PINNED_HEADS.get(resolved_root)
    require(head is not None and re.fullmatch(r'[0-9a-f]{40}', head) is not None,
            'Tracked-source preflight requires a pinned HEAD')
    require(isinstance(proof_files, dict)
            and set(proof_files) == {RECEIPT, VERIFIER, REGRESSION}
            and all(isinstance(value, bytes) for value in proof_files.values()),
            'Tracked-source preflight requires exact proof bytes')
    paths = []
    staged_paths = ()
    for label, arguments in (
        ('staged', ('diff', '--cached', '--no-ext-diff', '--no-renames',
                    '--name-only', '-z', head, '--')),
        ('unstaged', ('diff', '--no-ext-diff', '--no-renames',
                      '--name-only', '-z', '--')),
    ):
        result = subprocess.run(
            ['git', '--no-replace-objects', *arguments],
            cwd=root, capture_output=True)
        require(result.returncode == 0 and not result.stderr,
                'Tracked-source ' + label + ' preflight failed: '
                + result.stderr.decode(errors='replace')[-1500:])
        parsed = parse_tracked_source_drift(result.stdout)
        paths.extend(parsed)
        if label == 'staged':
            staged_paths = parsed
    paths = tuple(sorted(set(paths)))
    allowed = {RECEIPT, VERIFIER, REGRESSION}
    unexpected = set(paths) - allowed
    require(not unexpected,
            'Tracked source changed before composition proof: '
            + repr(sorted(unexpected)))
    result = subprocess.run([
        'git', '--no-replace-objects', 'ls-files', '--others',
        '--exclude-standard', '-z', '--'], cwd=root, capture_output=True)
    require(result.returncode == 0 and not result.stderr,
            'Untracked-source preflight failed: '
            + result.stderr.decode(errors='replace')[-1500:])
    untracked = set(parse_tracked_source_drift(result.stdout))
    require(not untracked - NARRATIVES,
            'Untracked source present before composition proof: '
            + repr(sorted(untracked - NARRATIVES)))
    tracked = set(parse_tracked_source_drift(git(root, 'ls-files', '-z')))
    unexpected_filesystem = actual_paths(root, tracked) - tracked
    require(not unexpected_filesystem,
            'Ignored or untracked source present before composition proof: '
            + repr(sorted(unexpected_filesystem)))
    for path in set(staged_paths) & allowed:
        result = subprocess.run(
            ['git', '--no-replace-objects', 'ls-files', '--stage', '-z', '--', path],
            cwd=root, capture_output=True)
        require(result.returncode == 0 and not result.stderr
                and result.stdout.endswith(b'\0')
                and result.stdout.count(b'\0') == 1,
                'Tracked-source preflight found a malformed staged proof entry: ' + path)
        try:
            metadata, encoded_path = result.stdout[:-1].split(b'\t', 1)
            permission, oid, stage = metadata.decode('ascii').split(' ')
        except (UnicodeDecodeError, ValueError) as error:
            raise AssertionError(
                'Tracked-source preflight found a malformed staged proof entry: '
                + path) from error
        require(encoded_path == path.encode('utf-8') and permission == '100644'
                and stage == '0' and re.fullmatch(r'[0-9a-f]{40}', oid) is not None
                and git(root, 'cat-file', 'blob', oid) == proof_files[path],
                'Staged proof entry differs from validated bytes or mode: ' + path)
    # Refuse a ref move that raced the pinned-object diff.
    pin_head(root)
    return paths


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
    key = (root_key(root), ref)
    if key in _TREE_ENTRIES:
        result = _TREE_ENTRIES.pop(key)
        _TREE_ENTRIES[key] = result
        return result
    result = {}
    for row in git(root, 'ls-tree', '-r', '-z', ref).split(b'\0'):
        if row:
            metadata, path = row.split(b'\t', 1)
            permission, kind, oid = metadata.decode().split(' ')
            require(kind == 'blob' and permission in {'100644', '100755'},
                    'Nonregular component tree entry: ' + path.decode())
            result[path.decode()] = (permission, oid)
    _TREE_ENTRIES[key] = result
    while len(_TREE_ENTRIES) > TREE_ENTRY_CACHE_LIMIT:
        _TREE_ENTRIES.popitem(last=False)
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
        (UX09_CI_BASE, UX09_CI_BASE_TREE, 'UX-09 CI-successor base'),
        (UX09_CI_SOURCE, UX09_CI_SOURCE_TREE, 'UX-09 CI-successor source'),
        (UX09_SECURITY_BASE, UX09_SECURITY_BASE_TREE, 'UX-09 security-successor base'),
        (UX09_SECURITY_SOURCE, UX09_SECURITY_SOURCE_TREE, 'UX-09 security-successor source'),
        (UX09_CLOUD_PROOF_BASE, UX09_CLOUD_PROOF_BASE_TREE, 'UX-09 cloud-proof base'),
        (UX09_CLOUD_PROOF_SOURCE, UX09_CLOUD_PROOF_SOURCE_TREE, 'UX-09 cloud-proof source'),
        (UX09_MAIL_REVIEW_BASE, UX09_MAIL_REVIEW_BASE_TREE, 'UX-09 mail-review base'),
        (UX09_MAIL_REVIEW_SOURCE, UX09_MAIL_REVIEW_SOURCE_TREE, 'UX-09 mail-review source'),
        (UX09_SMTP_REJECTION_BASE, UX09_SMTP_REJECTION_BASE_TREE, 'UX-09 SMTP-rejection base'),
        (UX09_SMTP_REJECTION_SOURCE, UX09_SMTP_REJECTION_SOURCE_TREE, 'UX-09 SMTP-rejection source'),
        (UX09_IDENTITY_SNAPSHOT_BASE, UX09_IDENTITY_SNAPSHOT_BASE_TREE, 'UX-09 identity-snapshot base'),
        (UX09_IDENTITY_SNAPSHOT_SOURCE, UX09_IDENTITY_SNAPSHOT_SOURCE_TREE,
         'UX-09 identity-snapshot source'),
        (UX09_FIXTURE_RECEIPT_BASE, UX09_FIXTURE_RECEIPT_BASE_TREE, 'UX-09 fixture-receipt base'),
        (UX09_FIXTURE_RECEIPT_SOURCE, UX09_FIXTURE_RECEIPT_SOURCE_TREE, 'UX-09 fixture-receipt source'),
        (UX09_COMMERCIAL_SNAPSHOT_BASE, UX09_COMMERCIAL_SNAPSHOT_BASE_TREE,
         'UX-09 commercial-snapshot base'),
        (UX09_COMMERCIAL_SNAPSHOT_SOURCE, UX09_COMMERCIAL_SNAPSHOT_SOURCE_TREE,
         'UX-09 commercial-snapshot source'),
        (UX09_AUDIT_ROLLBACK_BASE, UX09_AUDIT_ROLLBACK_BASE_TREE,
         'UX-09 audit-rollback base'),
        (UX09_AUDIT_ROLLBACK_SOURCE, UX09_AUDIT_ROLLBACK_SOURCE_TREE,
         'UX-09 audit-rollback source'),
        (UX09_BOUNDED_TOKEN_REFUSAL_BASE, UX09_BOUNDED_TOKEN_REFUSAL_BASE_TREE,
         'UX-09 bounded-token-refusal base'),
        (UX09_BOUNDED_TOKEN_REFUSAL_SOURCE, UX09_BOUNDED_TOKEN_REFUSAL_SOURCE_TREE,
         'UX-09 bounded-token-refusal source'),
        (UX09_MIGRATION_PRESERVATION_BASE, UX09_MIGRATION_PRESERVATION_BASE_TREE,
         'UX-09 migration-preservation base'),
        (UX09_MIGRATION_PRESERVATION_SOURCE, UX09_MIGRATION_PRESERVATION_SOURCE_TREE,
         'UX-09 migration-preservation source'),
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
        actual = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        actual = project_ux09_identity_snapshot(root, path, actual)
        actual = project_ux09_smtp_rejection(root, path, actual)
        actual = project_ux09_mail_review(root, path, actual)
        actual = project_ux09_cloud_proof(root, path, actual)
        actual = project_ux09_security(root, path, actual)
        require(isinstance(row, dict) and set(row) == {'base_sha256', 'source_sha256', 'mode'}
                and row['base_sha256'] == digest(before)
                and row['source_sha256'] == digest(current)
                and row['mode'] == base_entries[path][0] == source_entries[path][0] == '100644'
                and actual == current and mode(root, path) == row['mode'],
                'UX-09 modified source identity changed: ' + path)
    for path in sorted(UX09_ADDITIONS):
        row = data['added'][path];current = git(root, 'cat-file', 'blob', UX09_SOURCE + ':' + path)
        actual = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        actual = project_ux09_identity_snapshot(root, path, actual)
        actual = project_ux09_smtp_rejection(root, path, actual)
        actual = project_ux09_mail_review(root, path, actual)
        actual = project_ux09_cloud_proof(root, path, actual)
        actual = project_ux09_security(root, path, actual)
        require(isinstance(row, dict) and set(row) == {'source_sha256', 'mode'}
                and row['source_sha256'] == digest(current)
                and row['mode'] == source_entries[path][0] == '100644'
                and actual == current and mode(root, path) == row['mode'],
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


def validate_ux09_ci_source(root):
    """Bind the exact private-draft fixture repair discovered by initial UX-09 CI."""
    require(git(root, 'rev-parse', UX09_CI_BASE + '^{tree}').decode().strip() == UX09_CI_BASE_TREE
            and git(root, 'rev-parse', UX09_CI_SOURCE + '^{tree}').decode().strip()
            == UX09_CI_SOURCE_TREE, 'UX-09 CI-successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_CI_BASE, UX09_CI_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_CI_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_CI_BASE, UX09_CI_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_CI_MODIFIED)],
            'UX-09 CI successor changed a different path or status set')
    base_entries = entries(root, UX09_CI_BASE);source_entries = entries(root, UX09_CI_SOURCE)
    for path in sorted(UX09_CI_MODIFIED):
        current = project_application_mail_copies_routing_source(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob', UX09_CI_SOURCE + ':' + path)
                and digest(current) == UX09_CI_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 CI-successor source identity changed: ' + path)


def project_ux09_ci(root, path, current):
    """Restore the exact pre-CI-fix bytes only for historical component proofs."""
    if path not in UX09_CI_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_CI_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_CI_SHA256[path],
            'Current UX-09 CI-successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_CI_BASE + ':' + path)


def validate_ux09_security_source(root):
    """Bind the exact review-blocker successor before projecting older proofs."""
    require(git(root, 'rev-parse', UX09_SECURITY_BASE + '^{tree}').decode().strip()
            == UX09_SECURITY_BASE_TREE
            and git(root, 'rev-parse', UX09_SECURITY_SOURCE + '^{tree}').decode().strip()
            == UX09_SECURITY_SOURCE_TREE, 'UX-09 security successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_SECURITY_BASE, UX09_SECURITY_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_SECURITY_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_SECURITY_BASE, UX09_SECURITY_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_SECURITY_MODIFIED)],
            'UX-09 security successor changed a different path or status set')
    base_entries = entries(root, UX09_SECURITY_BASE);source_entries = entries(root, UX09_SECURITY_SOURCE)
    for path in sorted(UX09_SECURITY_MODIFIED):
        current = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        current = project_ux09_identity_snapshot(root, path, current)
        current = project_ux09_smtp_rejection(root, path, current)
        current = project_ux09_mail_review(root, path, current)
        current = project_ux09_cloud_proof(root, path, current)
        require(current == git(root, 'cat-file', 'blob', UX09_SECURITY_SOURCE + ':' + path)
                and digest(current) == UX09_SECURITY_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 security successor source identity changed: ' + path)


def project_ux09_security(root, path, current):
    """Restore pre-security-successor bytes only for historical source proofs."""
    if path not in UX09_SECURITY_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_SECURITY_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_SECURITY_SHA256[path],
            'Current UX-09 security successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_SECURITY_BASE + ':' + path)


def validate_ux09_cloud_proof_source(root):
    """Bind the exact cloud-intake predecessor projection exposed by hosted UX-09 proof."""
    require(git(root, 'rev-parse', UX09_CLOUD_PROOF_BASE + '^{tree}').decode().strip()
            == UX09_CLOUD_PROOF_BASE_TREE
            and git(root, 'rev-parse', UX09_CLOUD_PROOF_SOURCE + '^{tree}').decode().strip()
            == UX09_CLOUD_PROOF_SOURCE_TREE, 'UX-09 cloud-proof successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_CLOUD_PROOF_BASE, UX09_CLOUD_PROOF_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_CLOUD_PROOF_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_CLOUD_PROOF_BASE, UX09_CLOUD_PROOF_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_CLOUD_PROOF_MODIFIED)],
            'UX-09 cloud-proof successor changed a different path or status set')
    base_entries = entries(root, UX09_CLOUD_PROOF_BASE);source_entries = entries(root, UX09_CLOUD_PROOF_SOURCE)
    for path in sorted(UX09_CLOUD_PROOF_MODIFIED):
        current = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        current = project_ux09_identity_snapshot(root, path, current)
        current = project_ux09_smtp_rejection(root, path, current)
        current = project_ux09_mail_review(root, path, current)
        require(current == git(root, 'cat-file', 'blob', UX09_CLOUD_PROOF_SOURCE + ':' + path)
                and digest(current) == UX09_CLOUD_PROOF_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 cloud-proof successor source identity changed: ' + path)


def project_ux09_cloud_proof(root, path, current):
    """Restore pre-cloud-proof bytes only for older source receipts."""
    if path not in UX09_CLOUD_PROOF_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_CLOUD_PROOF_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_CLOUD_PROOF_SHA256[path],
            'Current UX-09 cloud-proof successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_CLOUD_PROOF_BASE + ':' + path)


def validate_ux09_mail_review_source(root):
    """Bind the exact mail certainty and socket-cleanup review successor."""
    require(git(root, 'rev-parse', UX09_MAIL_REVIEW_BASE + '^{tree}').decode().strip()
            == UX09_MAIL_REVIEW_BASE_TREE
            and git(root, 'rev-parse', UX09_MAIL_REVIEW_SOURCE + '^{tree}').decode().strip()
            == UX09_MAIL_REVIEW_SOURCE_TREE, 'UX-09 mail-review successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_MAIL_REVIEW_BASE, UX09_MAIL_REVIEW_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_MAIL_REVIEW_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_MAIL_REVIEW_BASE, UX09_MAIL_REVIEW_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_MAIL_REVIEW_MODIFIED)],
            'UX-09 mail-review successor changed a different path or status set')
    base_entries = entries(root, UX09_MAIL_REVIEW_BASE)
    source_entries = entries(root, UX09_MAIL_REVIEW_SOURCE)
    for path in sorted(UX09_MAIL_REVIEW_MODIFIED):
        current = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        current = project_ux09_identity_snapshot(root, path, current)
        current = project_ux09_smtp_rejection(root, path, current)
        require(current == git(root, 'cat-file', 'blob', UX09_MAIL_REVIEW_SOURCE + ':' + path)
                and digest(current) == UX09_MAIL_REVIEW_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 mail-review successor source identity changed: ' + path)


def project_ux09_mail_review(root, path, current):
    """Restore pre-mail-review bytes only for older source receipts."""
    if path not in UX09_MAIL_REVIEW_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_MAIL_REVIEW_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_MAIL_REVIEW_SHA256[path],
            'Current UX-09 mail-review successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_MAIL_REVIEW_BASE + ':' + path)


def validate_ux09_diagnostic_registry_source(root):
    """Bind the exact resulting-main diagnostic registry successor."""
    require(git(root, 'rev-parse', UX09_DIAGNOSTIC_REGISTRY_BASE + '^{tree}').decode().strip()
            == UX09_DIAGNOSTIC_REGISTRY_BASE_TREE
            and git(root, 'rev-parse', UX09_DIAGNOSTIC_REGISTRY_SOURCE + '^{tree}').decode().strip()
            == UX09_DIAGNOSTIC_REGISTRY_SOURCE_TREE,
            'UX-09 diagnostic-registry successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_DIAGNOSTIC_REGISTRY_BASE,
        UX09_DIAGNOSTIC_REGISTRY_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_DIAGNOSTIC_REGISTRY_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_DIAGNOSTIC_REGISTRY_BASE,
                  UX09_DIAGNOSTIC_REGISTRY_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_DIAGNOSTIC_REGISTRY_MODIFIED)],
            'UX-09 diagnostic-registry successor changed a different path or status set')
    base_entries = entries(root, UX09_DIAGNOSTIC_REGISTRY_BASE)
    source_entries = entries(root, UX09_DIAGNOSTIC_REGISTRY_SOURCE)
    for path in sorted(UX09_DIAGNOSTIC_REGISTRY_MODIFIED):
        current = project_resulting_main_diagnostic_mail_reply_source(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob',
                               UX09_DIAGNOSTIC_REGISTRY_SOURCE + ':' + path)
                and digest(current) == UX09_DIAGNOSTIC_REGISTRY_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 diagnostic-registry successor source identity changed: ' + path)


def project_ux09_diagnostic_registry(root, path, current):
    """Restore resulting-main bytes before replaying older source receipts."""
    if path not in UX09_DIAGNOSTIC_REGISTRY_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_DIAGNOSTIC_REGISTRY_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_DIAGNOSTIC_REGISTRY_SHA256[path],
            'Current UX-09 diagnostic-registry successor changed before predecessor projection: '
            + path)
    return git(root, 'cat-file', 'blob', UX09_DIAGNOSTIC_REGISTRY_BASE + ':' + path)


def validate_resulting_main_diagnostic_mail_reply_source(root):
    """Bind the exact fail-closed registry repair exposed by PR #398 resulting main."""
    require(git(root, 'rev-parse', RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE + '^{tree}').decode().strip()
            == RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE_TREE
            and git(root, 'rev-parse', RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE + '^{tree}').decode().strip()
            == RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE_TREE,
            'Resulting-main diagnostic mail-reply source tree changed')
    git(root, 'merge-base', '--is-ancestor', RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE,
        RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE)
    git(root, 'merge-base', '--is-ancestor', RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE,
                  RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_MODIFIED)],
            'Resulting-main diagnostic mail-reply source changed a different path or status set')
    base_entries = entries(root, RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE)
    source_entries = entries(root, RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE)
    for path in sorted(RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_MODIFIED):
        current = project_diagnostic_artifact_batch_source(
            root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob',
                               RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE + ':' + path)
                and digest(current) == RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'Resulting-main diagnostic mail-reply source identity changed: ' + path)


def project_resulting_main_diagnostic_mail_reply_source(root, path, current):
    """Restore PR #398 resulting-main bytes before replaying older source receipts."""
    if path not in RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SOURCE + ':' + path)
    require(current == source and digest(current) == RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_SHA256[path],
            'Current resulting-main diagnostic mail-reply source changed before predecessor projection: '
            + path)
    return git(root, 'cat-file', 'blob', RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_BASE + ':' + path)


def validate_ux09_migration_preservation_source(root):
    """Bind the populated client/contact migration-preservation fixture."""
    require(git(root, 'rev-parse', UX09_MIGRATION_PRESERVATION_BASE + '^{tree}').decode().strip()
            == UX09_MIGRATION_PRESERVATION_BASE_TREE
            and git(root, 'rev-parse', UX09_MIGRATION_PRESERVATION_SOURCE + '^{tree}').decode().strip()
            == UX09_MIGRATION_PRESERVATION_SOURCE_TREE,
            'UX-09 migration-preservation successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_MIGRATION_PRESERVATION_BASE,
        UX09_MIGRATION_PRESERVATION_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_MIGRATION_PRESERVATION_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_MIGRATION_PRESERVATION_BASE,
                  UX09_MIGRATION_PRESERVATION_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_MIGRATION_PRESERVATION_MODIFIED)],
            'UX-09 migration-preservation successor changed a different path or status set')
    base_entries = entries(root, UX09_MIGRATION_PRESERVATION_BASE)
    source_entries = entries(root, UX09_MIGRATION_PRESERVATION_SOURCE)
    for path in sorted(UX09_MIGRATION_PRESERVATION_MODIFIED):
        current = project_ux08_payment(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob',
                               UX09_MIGRATION_PRESERVATION_SOURCE + ':' + path)
                and digest(current) == UX09_MIGRATION_PRESERVATION_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 migration-preservation successor source identity changed: ' + path)


def project_ux09_migration_preservation(root, path, current):
    """Restore pre-migration-preservation bytes for older source proofs."""
    current = project_ux08_payment(root, path, current)
    if path not in UX09_MIGRATION_PRESERVATION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_MIGRATION_PRESERVATION_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_MIGRATION_PRESERVATION_SHA256[path],
            'Current UX-09 migration-preservation successor changed before predecessor projection: '
            + path)
    return git(root, 'cat-file', 'blob', UX09_MIGRATION_PRESERVATION_BASE + ':' + path)


def validate_ux09_bounded_token_refusal_source(root):
    """Bind the fixture's intentional bounded token-issuance refusal."""
    require(git(root, 'rev-parse', UX09_BOUNDED_TOKEN_REFUSAL_BASE + '^{tree}').decode().strip()
            == UX09_BOUNDED_TOKEN_REFUSAL_BASE_TREE
            and git(root, 'rev-parse', UX09_BOUNDED_TOKEN_REFUSAL_SOURCE + '^{tree}').decode().strip()
            == UX09_BOUNDED_TOKEN_REFUSAL_SOURCE_TREE,
            'UX-09 bounded-token-refusal successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_BOUNDED_TOKEN_REFUSAL_BASE,
        UX09_BOUNDED_TOKEN_REFUSAL_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_BOUNDED_TOKEN_REFUSAL_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_BOUNDED_TOKEN_REFUSAL_BASE,
                  UX09_BOUNDED_TOKEN_REFUSAL_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_BOUNDED_TOKEN_REFUSAL_MODIFIED)],
            'UX-09 bounded-token-refusal successor changed a different path or status set')
    base_entries = entries(root, UX09_BOUNDED_TOKEN_REFUSAL_BASE)
    source_entries = entries(root, UX09_BOUNDED_TOKEN_REFUSAL_SOURCE)
    for path in sorted(UX09_BOUNDED_TOKEN_REFUSAL_MODIFIED):
        current = project_ux09_migration_preservation(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob',
                               UX09_BOUNDED_TOKEN_REFUSAL_SOURCE + ':' + path)
                and digest(current) == UX09_BOUNDED_TOKEN_REFUSAL_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 bounded-token-refusal successor source identity changed: ' + path)


def project_ux09_bounded_token_refusal(root, path, current):
    """Restore pre-token-refusal bytes only for older source proofs."""
    current = project_ux09_migration_preservation(root, path, current)
    if path not in UX09_BOUNDED_TOKEN_REFUSAL_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_BOUNDED_TOKEN_REFUSAL_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_BOUNDED_TOKEN_REFUSAL_SHA256[path],
            'Current UX-09 bounded-token-refusal successor changed before predecessor projection: '
            + path)
    return git(root, 'cat-file', 'blob', UX09_BOUNDED_TOKEN_REFUSAL_BASE + ':' + path)


def validate_ux09_audit_rollback_source(root):
    """Bind the synthetic delivery-ready registration rollback fixture."""
    require(git(root, 'rev-parse', UX09_AUDIT_ROLLBACK_BASE + '^{tree}').decode().strip()
            == UX09_AUDIT_ROLLBACK_BASE_TREE
            and git(root, 'rev-parse', UX09_AUDIT_ROLLBACK_SOURCE + '^{tree}').decode().strip()
            == UX09_AUDIT_ROLLBACK_SOURCE_TREE,
            'UX-09 audit-rollback successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_AUDIT_ROLLBACK_BASE, UX09_AUDIT_ROLLBACK_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_AUDIT_ROLLBACK_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_AUDIT_ROLLBACK_BASE, UX09_AUDIT_ROLLBACK_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_AUDIT_ROLLBACK_MODIFIED)],
            'UX-09 audit-rollback successor changed a different path or status set')
    base_entries = entries(root, UX09_AUDIT_ROLLBACK_BASE)
    source_entries = entries(root, UX09_AUDIT_ROLLBACK_SOURCE)
    for path in sorted(UX09_AUDIT_ROLLBACK_MODIFIED):
        current = project_ux09_bounded_token_refusal(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob', UX09_AUDIT_ROLLBACK_SOURCE + ':' + path)
                and digest(current) == UX09_AUDIT_ROLLBACK_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 audit-rollback successor source identity changed: ' + path)


def project_ux09_audit_rollback(root, path, current):
    """Restore pre-audit-rollback bytes only for older source proofs."""
    current = project_ux09_bounded_token_refusal(root, path, current)
    if path not in UX09_AUDIT_ROLLBACK_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_AUDIT_ROLLBACK_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_AUDIT_ROLLBACK_SHA256[path],
            'Current UX-09 audit-rollback successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_AUDIT_ROLLBACK_BASE + ':' + path)


def validate_ux09_commercial_snapshot_source(root):
    """Bind the exact physical-table commercial-side-effect snapshot repair."""
    require(git(root, 'rev-parse', UX09_COMMERCIAL_SNAPSHOT_BASE + '^{tree}').decode().strip()
            == UX09_COMMERCIAL_SNAPSHOT_BASE_TREE
            and git(root, 'rev-parse', UX09_COMMERCIAL_SNAPSHOT_SOURCE + '^{tree}').decode().strip()
            == UX09_COMMERCIAL_SNAPSHOT_SOURCE_TREE,
            'UX-09 commercial-snapshot successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_COMMERCIAL_SNAPSHOT_BASE,
        UX09_COMMERCIAL_SNAPSHOT_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_COMMERCIAL_SNAPSHOT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_COMMERCIAL_SNAPSHOT_BASE, UX09_COMMERCIAL_SNAPSHOT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_COMMERCIAL_SNAPSHOT_MODIFIED)],
            'UX-09 commercial-snapshot successor changed a different path or status set')
    base_entries = entries(root, UX09_COMMERCIAL_SNAPSHOT_BASE)
    source_entries = entries(root, UX09_COMMERCIAL_SNAPSHOT_SOURCE)
    for path in sorted(UX09_COMMERCIAL_SNAPSHOT_MODIFIED):
        current = project_ux09_audit_rollback(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob', UX09_COMMERCIAL_SNAPSHOT_SOURCE + ':' + path)
                and digest(current) == UX09_COMMERCIAL_SNAPSHOT_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 commercial-snapshot successor source identity changed: ' + path)


def project_ux09_commercial_snapshot(root, path, current):
    """Restore pre-commercial-snapshot bytes only for older source proofs."""
    current = project_ux09_audit_rollback(root, path, current)
    if path not in UX09_COMMERCIAL_SNAPSHOT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_COMMERCIAL_SNAPSHOT_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_COMMERCIAL_SNAPSHOT_SHA256[path],
            'Current UX-09 commercial-snapshot successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_COMMERCIAL_SNAPSHOT_BASE + ':' + path)


def validate_ux09_fixture_receipt_source(root):
    """Bind the exact-source receipt for the repaired identity fixture."""
    require(git(root, 'rev-parse', UX09_FIXTURE_RECEIPT_BASE + '^{tree}').decode().strip()
            == UX09_FIXTURE_RECEIPT_BASE_TREE
            and git(root, 'rev-parse', UX09_FIXTURE_RECEIPT_SOURCE + '^{tree}').decode().strip()
            == UX09_FIXTURE_RECEIPT_SOURCE_TREE, 'UX-09 fixture-receipt successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_FIXTURE_RECEIPT_BASE, UX09_FIXTURE_RECEIPT_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_FIXTURE_RECEIPT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_FIXTURE_RECEIPT_BASE, UX09_FIXTURE_RECEIPT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_FIXTURE_RECEIPT_MODIFIED)],
            'UX-09 fixture-receipt successor changed a different path or status set')
    base_entries = entries(root, UX09_FIXTURE_RECEIPT_BASE)
    source_entries = entries(root, UX09_FIXTURE_RECEIPT_SOURCE)
    for path in sorted(UX09_FIXTURE_RECEIPT_MODIFIED):
        current = project_ux09_commercial_snapshot(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob', UX09_FIXTURE_RECEIPT_SOURCE + ':' + path)
                and digest(current) == UX09_FIXTURE_RECEIPT_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 fixture-receipt successor source identity changed: ' + path)


def project_ux09_fixture_receipt(root, path, current):
    """Restore the predecessor receipt only for older source proofs."""
    current = project_mail_reply_forward_source(root, path, current)
    current = project_runner_application_intake_binding(root, path, current)
    current = project_runner_application_source_binding(root, path, current)
    current = project_ux08_browser_navigation(root, path, current)
    current = project_ux09_commercial_snapshot(root, path, current)
    if path not in UX09_FIXTURE_RECEIPT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_FIXTURE_RECEIPT_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_FIXTURE_RECEIPT_SHA256[path],
            'Current UX-09 fixture-receipt successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_FIXTURE_RECEIPT_BASE + ':' + path)


def validate_ux09_identity_snapshot_source(root):
    """Bind the optional-commercial-table identity fixture successor."""
    require(git(root, 'rev-parse', UX09_IDENTITY_SNAPSHOT_BASE + '^{tree}').decode().strip()
            == UX09_IDENTITY_SNAPSHOT_BASE_TREE
            and git(root, 'rev-parse', UX09_IDENTITY_SNAPSHOT_SOURCE + '^{tree}').decode().strip()
            == UX09_IDENTITY_SNAPSHOT_SOURCE_TREE, 'UX-09 identity-snapshot successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_IDENTITY_SNAPSHOT_BASE, UX09_IDENTITY_SNAPSHOT_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_IDENTITY_SNAPSHOT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_IDENTITY_SNAPSHOT_BASE, UX09_IDENTITY_SNAPSHOT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_IDENTITY_SNAPSHOT_MODIFIED)],
            'UX-09 identity-snapshot successor changed a different path or status set')
    base_entries = entries(root, UX09_IDENTITY_SNAPSHOT_BASE)
    source_entries = entries(root, UX09_IDENTITY_SNAPSHOT_SOURCE)
    for path in sorted(UX09_IDENTITY_SNAPSHOT_MODIFIED):
        current = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob', UX09_IDENTITY_SNAPSHOT_SOURCE + ':' + path)
                and digest(current) == UX09_IDENTITY_SNAPSHOT_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 identity-snapshot successor source identity changed: ' + path)


def project_ux09_identity_snapshot(root, path, current):
    """Restore pre-snapshot-fixture bytes only for older source receipts."""
    if path not in UX09_IDENTITY_SNAPSHOT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_IDENTITY_SNAPSHOT_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_IDENTITY_SNAPSHOT_SHA256[path],
            'Current UX-09 identity-snapshot successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_IDENTITY_SNAPSHOT_BASE + ':' + path)


def validate_ux08_inbox_state_correction_source(root):
    """Bind accepted manual-payment display state to its exact correction."""
    require(git(root, 'rev-parse', UX08_INBOX_STATE_CORRECTION_BASE + '^{tree}').decode().strip()
            == UX08_INBOX_STATE_CORRECTION_BASE_TREE
            and git(root, 'rev-parse', UX08_INBOX_STATE_CORRECTION_SOURCE + '^{tree}').decode().strip()
            == UX08_INBOX_STATE_CORRECTION_SOURCE_TREE,
            'UX-08 inbox state correction source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_STATE_CORRECTION_BASE,
        UX08_INBOX_STATE_CORRECTION_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_STATE_CORRECTION_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_INBOX_STATE_CORRECTION_BASE,
                  UX08_INBOX_STATE_CORRECTION_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_INBOX_STATE_FIX_PATH],
            'UX-08 inbox state correction changed a different path or status set')
    base_entries = entries(root, UX08_INBOX_STATE_CORRECTION_BASE)
    source_entries = entries(root, UX08_INBOX_STATE_CORRECTION_SOURCE)
    current = project_ux08_agreement_link_fix(
        root, UX08_INBOX_STATE_FIX_PATH, ordinary(root, UX08_INBOX_STATE_FIX_PATH))
    require(current == git(root, 'cat-file', 'blob', UX08_INBOX_STATE_CORRECTION_SOURCE + ':'
                           + UX08_INBOX_STATE_FIX_PATH)
            and mode(root, UX08_INBOX_STATE_FIX_PATH)
            == base_entries[UX08_INBOX_STATE_FIX_PATH][0]
            == source_entries[UX08_INBOX_STATE_FIX_PATH][0] == '100644',
            'UX-08 inbox state correction source identity changed')


def project_ux08_inbox_state_correction(root, path, current):
    """Restore the first inbox-state proof head for predecessor projection."""
    current = project_ux08_agreement_link_fix(root, path, current)
    if path != UX08_INBOX_STATE_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_INBOX_STATE_CORRECTION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_INBOX_STATE_CORRECTION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 inbox state correction changed before predecessor projection')
    return base


def validate_ux08_inbox_state_fix_source(root):
    """Bind the retained two-paid-invoice inbox fixture to its exact source."""
    require(git(root, 'rev-parse', UX08_INBOX_STATE_FIX_BASE + '^{tree}').decode().strip()
            == UX08_INBOX_STATE_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_INBOX_STATE_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_INBOX_STATE_FIX_SOURCE_TREE,
            'UX-08 inbox state fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_STATE_FIX_BASE,
        UX08_INBOX_STATE_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_STATE_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_INBOX_STATE_FIX_BASE, UX08_INBOX_STATE_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_INBOX_STATE_FIX_PATH],
            'UX-08 inbox state fix changed a different path or status set')
    base_entries = entries(root, UX08_INBOX_STATE_FIX_BASE)
    source_entries = entries(root, UX08_INBOX_STATE_FIX_SOURCE)
    current = project_ux08_inbox_state_correction(
        root, UX08_INBOX_STATE_FIX_PATH, ordinary(root, UX08_INBOX_STATE_FIX_PATH))
    require(current == git(root, 'cat-file', 'blob', UX08_INBOX_STATE_FIX_SOURCE + ':'
                           + UX08_INBOX_STATE_FIX_PATH)
            and mode(root, UX08_INBOX_STATE_FIX_PATH)
            == base_entries[UX08_INBOX_STATE_FIX_PATH][0]
            == source_entries[UX08_INBOX_STATE_FIX_PATH][0] == '100644',
            'UX-08 inbox state fix source identity changed')
    projected = project_ux08_inbox_state_fix(
        root, UX08_INBOX_STATE_FIX_PATH, ordinary(root, UX08_INBOX_STATE_FIX_PATH))
    require(projected == git(root, 'cat-file', 'blob', UX08_INBOX_STATE_FIX_BASE + ':'
                             + UX08_INBOX_STATE_FIX_PATH)
            and project_ux08_inbox_state_fix(
                root, UX08_INBOX_STATE_FIX_PATH, projected) == projected,
            'UX-08 inbox state projection is not exact and idempotent')


def project_ux08_inbox_state_fix(root, path, current):
    """Restore the storefront-network proof head for older source receipts."""
    current = project_ux08_inbox_state_correction(root, path, current)
    if path != UX08_INBOX_STATE_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_INBOX_STATE_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_INBOX_STATE_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 inbox state fix changed before predecessor projection')
    return base


def validate_ux08_inbox_operations_fix_source(root):
    """Bind the owner-inbox optional-operations readiness correction."""
    require(git(root, 'rev-parse', UX08_INBOX_OPERATIONS_FIX_BASE + '^{tree}').decode().strip()
            == UX08_INBOX_OPERATIONS_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_INBOX_OPERATIONS_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_INBOX_OPERATIONS_FIX_SOURCE_TREE,
            'UX-08 inbox operations fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_OPERATIONS_FIX_BASE,
        UX08_INBOX_OPERATIONS_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_OPERATIONS_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_INBOX_OPERATIONS_FIX_BASE,
                  UX08_INBOX_OPERATIONS_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_INBOX_OPERATIONS_FIX_MODIFIED)],
            'UX-08 inbox operations fix changed a different path or status set')
    base_entries = entries(root, UX08_INBOX_OPERATIONS_FIX_BASE)
    source_entries = entries(root, UX08_INBOX_OPERATIONS_FIX_SOURCE)
    for path in sorted(UX08_INBOX_OPERATIONS_FIX_MODIFIED):
        current = project_ux08_inbox_record_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_INBOX_OPERATIONS_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_INBOX_OPERATIONS_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_inbox_operations_fix(root, path, current) == base
                and project_ux08_inbox_operations_fix(root, path, base) == base,
                'UX-08 inbox operations fix identity or projection changed: ' + path)


def project_ux08_inbox_operations_fix(root, path, current):
    """Restore the preceding proof head only for older source receipts."""
    if path not in UX08_INBOX_OPERATIONS_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_INBOX_OPERATIONS_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_INBOX_OPERATIONS_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 inbox operations fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_inbox_record_fix_source(root):
    """Bind retained-record validation before optional operations readiness."""
    require(git(root, 'rev-parse', UX08_INBOX_RECORD_FIX_BASE + '^{tree}').decode().strip()
            == UX08_INBOX_RECORD_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_INBOX_RECORD_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_INBOX_RECORD_FIX_SOURCE_TREE,
            'UX-08 inbox record fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_RECORD_FIX_BASE,
        UX08_INBOX_RECORD_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_RECORD_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_INBOX_RECORD_FIX_BASE,
                  UX08_INBOX_RECORD_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_INBOX_RECORD_FIX_MODIFIED)],
            'UX-08 inbox record fix changed a different path or status set')
    base_entries = entries(root, UX08_INBOX_RECORD_FIX_BASE)
    source_entries = entries(root, UX08_INBOX_RECORD_FIX_SOURCE)
    for path in sorted(UX08_INBOX_RECORD_FIX_MODIFIED):
        current = project_ux08_payment_outcome_proof_fix(root, path, ordinary(root, path))
        current = project_ux08_agreement_link_fix(root, path, current)
        source = git(root, 'cat-file', 'blob', UX08_INBOX_RECORD_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_INBOX_RECORD_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_inbox_record_fix(root, path, current) == base
                and project_ux08_inbox_record_fix(root, path, base) == base,
                'UX-08 inbox record fix identity or projection changed: ' + path)


def project_ux08_inbox_record_fix(root, path, current):
    """Restore the first inbox-read correction before older source projections."""
    current = project_ux08_payment_outcome_proof_fix(root, path, current)
    current = project_ux08_agreement_link_fix(root, path, current)
    if path not in UX08_INBOX_RECORD_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_INBOX_RECORD_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_INBOX_RECORD_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 inbox record fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_agreement_link_fix_source(root):
    """Bind explicit agreement access without replacing pending-payment actions."""
    require(git(root, 'rev-parse', UX08_AGREEMENT_LINK_FIX_BASE + '^{tree}').decode().strip()
            == UX08_AGREEMENT_LINK_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_AGREEMENT_LINK_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_AGREEMENT_LINK_FIX_SOURCE_TREE,
            'UX-08 agreement-link fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_AGREEMENT_LINK_FIX_BASE,
        UX08_AGREEMENT_LINK_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_AGREEMENT_LINK_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_AGREEMENT_LINK_FIX_BASE,
                  UX08_AGREEMENT_LINK_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_AGREEMENT_LINK_FIX_MODIFIED)],
            'UX-08 agreement-link fix changed a different path or status set')
    base_entries = entries(root, UX08_AGREEMENT_LINK_FIX_BASE)
    source_entries = entries(root, UX08_AGREEMENT_LINK_FIX_SOURCE)
    for path in sorted(UX08_AGREEMENT_LINK_FIX_MODIFIED):
        current = project_ux08_inbox_network_fix(root, path, ordinary(root, path))
        current = project_ux08_agreement_duplicate_fix(root, path, current)
        source = git(root, 'cat-file', 'blob', UX08_AGREEMENT_LINK_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_AGREEMENT_LINK_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_agreement_link_fix(root, path, current) == base
                and project_ux08_agreement_link_fix(root, path, base) == base,
                'UX-08 agreement-link fix identity or projection changed: ' + path)


def project_ux08_agreement_link_fix(root, path, current):
    """Restore the accepted fcdd proof head for every older source receipt."""
    current = project_ux08_inbox_payment_readiness_fix(root, path, current)
    current = project_ux08_payment_fixture_lock_fix(root, path, current)
    current = project_ux08_billing_network_fix(root, path, current)
    current = project_ux08_inbox_network_fix(root, path, current)
    current = project_ux08_agreement_duplicate_fix(root, path, current)
    if path not in UX08_AGREEMENT_LINK_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_AGREEMENT_LINK_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_AGREEMENT_LINK_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 agreement-link fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_agreement_duplicate_fix_source(root):
    """Bind the single agreement action while retaining complete link-set checks."""
    require(git(root, 'rev-parse', UX08_AGREEMENT_DUPLICATE_FIX_BASE + '^{tree}').decode().strip()
            == UX08_AGREEMENT_DUPLICATE_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_AGREEMENT_DUPLICATE_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_AGREEMENT_DUPLICATE_FIX_SOURCE_TREE,
            'UX-08 agreement duplicate fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_AGREEMENT_DUPLICATE_FIX_BASE,
        UX08_AGREEMENT_DUPLICATE_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_AGREEMENT_DUPLICATE_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_AGREEMENT_DUPLICATE_FIX_BASE,
                  UX08_AGREEMENT_DUPLICATE_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_AGREEMENT_DUPLICATE_FIX_MODIFIED)],
            'UX-08 agreement duplicate fix changed a different path or status set')
    base_entries = entries(root, UX08_AGREEMENT_DUPLICATE_FIX_BASE)
    source_entries = entries(root, UX08_AGREEMENT_DUPLICATE_FIX_SOURCE)
    for path in sorted(UX08_AGREEMENT_DUPLICATE_FIX_MODIFIED):
        current = project_ux08_inbox_payment_readiness_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_AGREEMENT_DUPLICATE_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_AGREEMENT_DUPLICATE_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_agreement_duplicate_fix(root, path, current) == base
                and project_ux08_agreement_duplicate_fix(root, path, base) == base,
                'UX-08 agreement duplicate fix identity or projection changed: ' + path)


def project_ux08_agreement_duplicate_fix(root, path, current):
    """Restore the first agreement-link proof head for older receipts."""
    current = project_ux08_inbox_payment_readiness_fix(root, path, current)
    if path not in UX08_AGREEMENT_DUPLICATE_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_AGREEMENT_DUPLICATE_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_AGREEMENT_DUPLICATE_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 agreement duplicate fix changed before predecessor projection: ' + path)
    return base


def validate_resulting_main_client_inbox_budget_source(root):
    """Bind the one-attempt hosted client-inbox navigation budget correction."""
    require(git(root, 'rev-parse', RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE + '^{tree}').decode().strip()
            == RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE_TREE
            and git(root, 'rev-parse',
                    RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE_TREE,
            'Resulting-main client inbox budget source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE).decode().strip().split()
            == [RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE],
            'Resulting-main client inbox budget source parent changed')
    git(root, 'merge-base', '--is-ancestor', RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE,
                  RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE).decode().splitlines()
    require(changes == ['M\t' + RESULTING_MAIN_CLIENT_INBOX_BUDGET_PATH],
            'Resulting-main client inbox budget changed a different path or status set')
    base_entries = entries(root, RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE)
    source_entries = entries(root, RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE)
    path = RESULTING_MAIN_CLIENT_INBOX_BUDGET_PATH
    current = project_runner_application_composition_partition(root, path, ordinary(root, path))
    current = project_hosting_inbox_reload_budget_source(root, path, current)
    source = git(root, 'cat-file', 'blob', RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_resulting_main_client_inbox_budget(root, path, current) == base
            and project_resulting_main_client_inbox_budget(root, path, base) == base,
            'Resulting-main client inbox budget identity or projection changed')
    require(source.count(b'click(ap, entry, 60000) == 200') == 1
            and source.count(b'click(op, entry, 60000) == 200') == 1
            and b'click(ap, entry) == 200' not in source
            and b"destination(entry.get_attribute('href'), portal, {'hosting_purchases': 1})" in source
            and b"cards(ap, 'client')" in source,
            'Resulting-main client inbox correction weakened destination, response or content assertions')


def project_resulting_main_client_inbox_budget(root, path, current):
    """Restore the exact reviewed notification successor for older proofs."""
    current = project_hosting_inbox_reload_budget_source(root, path, current)
    if path != RESULTING_MAIN_CLIENT_INBOX_BUDGET_PATH:
        return current
    source = git(root, 'cat-file', 'blob', RESULTING_MAIN_CLIENT_INBOX_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RESULTING_MAIN_CLIENT_INBOX_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current resulting-main client inbox budget changed before predecessor projection')
    return base


def validate_resulting_main_inbox_click_budget_source(root):
    """Bind the complete one-attempt navigation and continuity correction."""
    require(git(root, 'rev-parse', RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE + '^{tree}').decode().strip()
            == RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE_TREE
            and git(root, 'rev-parse', RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE_TREE,
            'Resulting-main inbox click-budget source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE).decode().strip().split()
            == [RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE],
            'Resulting-main inbox click-budget source parent changed')
    git(root, 'merge-base', '--is-ancestor', RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE,
                  RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE).decode().splitlines()
    require(changes == [
        'A\t' + next(iter(RESULTING_MAIN_INBOX_CLICK_BUDGET_ADDITIONS)),
        'M\t' + next(iter(RESULTING_MAIN_INBOX_CLICK_BUDGET_MODIFIED)),
    ], 'Resulting-main inbox click-budget source changed a different path or status set')
    base_entries = entries(root, RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE)
    source_entries = entries(root, RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE)
    modified = next(iter(RESULTING_MAIN_INBOX_CLICK_BUDGET_MODIFIED))
    addition = next(iter(RESULTING_MAIN_INBOX_CLICK_BUDGET_ADDITIONS))
    current = project_resulting_main_client_inbox_budget(root, modified, ordinary(root, modified))
    source = git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE + ':' + modified)
    base = git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE + ':' + modified)
    require(current == source
            and mode(root, modified) == base_entries[modified][0] == source_entries[modified][0] == '100644'
            and project_resulting_main_inbox_click_budget(root, modified, current) == base
            and project_resulting_main_inbox_click_budget(root, modified, base) == base,
            'Resulting-main inbox click-budget identity or projection changed')
    require(addition not in base_entries and addition in source_entries
            and ordinary(root, addition)
            == git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE + ':' + addition)
            and mode(root, addition) == source_entries[addition][0] == '100644',
            'Resulting-main inbox task handoff identity changed')


def project_resulting_main_inbox_click_budget(root, path, current):
    """Restore the reviewed outer-navigation proof head for older receipts."""
    current = project_resulting_main_client_inbox_budget(root, path, current)
    if path not in RESULTING_MAIN_INBOX_CLICK_BUDGET_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_CLICK_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_CLICK_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current resulting-main inbox click budget changed before predecessor projection')
    return base


def validate_resulting_main_inbox_navigation_budget_source(root):
    """Bind the one-attempt hosted owner-inbox navigation budget correction."""
    require(git(root, 'rev-parse', RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE + '^{tree}').decode().strip()
            == RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE_TREE
            and git(root, 'rev-parse', RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE_TREE,
            'Resulting-main inbox navigation budget source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE).decode().strip().split()
            == [RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE],
            'Resulting-main inbox navigation budget source parent changed')
    git(root, 'merge-base', '--is-ancestor', RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE,
                  RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE).decode().splitlines()
    require(changes == ['M\t' + RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_PATH],
            'Resulting-main inbox navigation budget changed a different path or status set')
    base_entries = entries(root, RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE)
    source_entries = entries(root, RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE)
    path = RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_PATH
    current = project_resulting_main_inbox_click_budget(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_resulting_main_inbox_navigation_budget(root, path, current) == base
            and project_resulting_main_inbox_navigation_budget(root, path, base) == base,
            'Resulting-main inbox navigation budget identity or projection changed')


def project_resulting_main_inbox_navigation_budget(root, path, current):
    """Restore exact merged-main inbox fixture bytes for older source proofs."""
    current = project_resulting_main_inbox_click_budget(root, path, current)
    if path != RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_PATH:
        return current
    source = git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current resulting-main inbox navigation budget changed before predecessor projection')
    return base


def validate_ux08_inbox_network_fix_source(root):
    """Bind exact closed interception of the inbox payment dependency."""
    require(git(root, 'rev-parse', UX08_INBOX_NETWORK_FIX_BASE + '^{tree}').decode().strip()
            == UX08_INBOX_NETWORK_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_INBOX_NETWORK_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_INBOX_NETWORK_FIX_SOURCE_TREE,
            'UX-08 inbox network fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_NETWORK_FIX_BASE,
        UX08_INBOX_NETWORK_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_NETWORK_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_INBOX_NETWORK_FIX_BASE, UX08_INBOX_NETWORK_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_INBOX_NETWORK_FIX_PATH],
            'UX-08 inbox network fix changed a different path or status set')
    base_entries = entries(root, UX08_INBOX_NETWORK_FIX_BASE)
    source_entries = entries(root, UX08_INBOX_NETWORK_FIX_SOURCE)
    current = project_resulting_main_inbox_navigation_budget(
        root, UX08_INBOX_NETWORK_FIX_PATH, ordinary(root, UX08_INBOX_NETWORK_FIX_PATH))
    source = git(root, 'cat-file', 'blob', UX08_INBOX_NETWORK_FIX_SOURCE + ':'
                 + UX08_INBOX_NETWORK_FIX_PATH)
    base = git(root, 'cat-file', 'blob', UX08_INBOX_NETWORK_FIX_BASE + ':'
               + UX08_INBOX_NETWORK_FIX_PATH)
    require(current == source
            and mode(root, UX08_INBOX_NETWORK_FIX_PATH)
            == base_entries[UX08_INBOX_NETWORK_FIX_PATH][0]
            == source_entries[UX08_INBOX_NETWORK_FIX_PATH][0] == '100644'
            and project_ux08_inbox_network_fix(root, UX08_INBOX_NETWORK_FIX_PATH, current) == base
            and project_ux08_inbox_network_fix(root, UX08_INBOX_NETWORK_FIX_PATH, base) == base,
            'UX-08 inbox network fix identity or projection changed')


def project_ux08_inbox_network_fix(root, path, current):
    """Restore the single-agreement proof head for older receipts."""
    current = project_resulting_main_inbox_navigation_budget(root, path, current)
    if path != UX08_INBOX_NETWORK_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_INBOX_NETWORK_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_INBOX_NETWORK_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 inbox network fix changed before predecessor projection')
    return base


def validate_ux08_billing_network_fix_source(root):
    """Bind exact closed interception of billing-option payment dependencies."""
    require(git(root, 'rev-parse', UX08_BILLING_NETWORK_FIX_BASE + '^{tree}').decode().strip()
            == UX08_BILLING_NETWORK_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_BILLING_NETWORK_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_BILLING_NETWORK_FIX_SOURCE_TREE,
            'UX-08 billing network fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_BILLING_NETWORK_FIX_BASE,
        UX08_BILLING_NETWORK_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_BILLING_NETWORK_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_BILLING_NETWORK_FIX_BASE, UX08_BILLING_NETWORK_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_BILLING_NETWORK_FIX_PATH],
            'UX-08 billing network fix changed a different path or status set')
    base_entries = entries(root, UX08_BILLING_NETWORK_FIX_BASE)
    source_entries = entries(root, UX08_BILLING_NETWORK_FIX_SOURCE)
    current = ordinary(root, UX08_BILLING_NETWORK_FIX_PATH)
    source = git(root, 'cat-file', 'blob', UX08_BILLING_NETWORK_FIX_SOURCE + ':'
                 + UX08_BILLING_NETWORK_FIX_PATH)
    base = git(root, 'cat-file', 'blob', UX08_BILLING_NETWORK_FIX_BASE + ':'
               + UX08_BILLING_NETWORK_FIX_PATH)
    require(current == source
            and mode(root, UX08_BILLING_NETWORK_FIX_PATH)
            == base_entries[UX08_BILLING_NETWORK_FIX_PATH][0]
            == source_entries[UX08_BILLING_NETWORK_FIX_PATH][0] == '100644'
            and project_ux08_billing_network_fix(root, UX08_BILLING_NETWORK_FIX_PATH, current) == base
            and project_ux08_billing_network_fix(root, UX08_BILLING_NETWORK_FIX_PATH, base) == base,
            'UX-08 billing network fix identity or projection changed')


def project_ux08_billing_network_fix(root, path, current):
    """Restore the accepted inbox-network proof head for older receipts."""
    if path != UX08_BILLING_NETWORK_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_BILLING_NETWORK_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_BILLING_NETWORK_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 billing network fix changed before predecessor projection')
    return base


def validate_runner_intake_current_binding_source(root):
    """Bind the current cloud-intake verifier source hash."""
    require(git(root, 'rev-parse', RUNNER_INTAKE_CURRENT_BINDING_BASE + '^{tree}').decode().strip()
            == RUNNER_INTAKE_CURRENT_BINDING_BASE_TREE
            and git(root, 'rev-parse', RUNNER_INTAKE_CURRENT_BINDING_SOURCE + '^{tree}').decode().strip()
            == RUNNER_INTAKE_CURRENT_BINDING_SOURCE_TREE,
            'Runner intake current-binding tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_INTAKE_CURRENT_BINDING_SOURCE).decode().strip().split()
            == [RUNNER_INTAKE_CURRENT_BINDING_BASE],
            'Runner intake current-binding parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_INTAKE_CURRENT_BINDING_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_INTAKE_CURRENT_BINDING_BASE,
                  RUNNER_INTAKE_CURRENT_BINDING_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_INTAKE_CURRENT_BINDING_MODIFIED)],
            'Runner intake current binding changed a different path or status set')
    base_entries = entries(root, RUNNER_INTAKE_CURRENT_BINDING_BASE)
    source_entries = entries(root, RUNNER_INTAKE_CURRENT_BINDING_SOURCE)
    for path in sorted(RUNNER_INTAKE_CURRENT_BINDING_MODIFIED):
        current = project_project_private_notes_evidence_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_INTAKE_CURRENT_BINDING_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_INTAKE_CURRENT_BINDING_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_intake_current_binding(root, path, current) == base
                and project_runner_intake_current_binding(root, path, base) == base,
                'Runner intake current-binding identity or projection changed: ' + path)


def project_runner_intake_current_binding(root, path, current):
    """Restore the accepted AWS mirror successor proof head."""
    current = project_project_private_notes_evidence_source(root, path, current)
    if path not in RUNNER_INTAKE_CURRENT_BINDING_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_INTAKE_CURRENT_BINDING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_INTAKE_CURRENT_BINDING_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner intake binding changed before predecessor projection: ' + path)
    return base


def validate_runner_finite_tool_refusal_source(root):
    """Bind the corrected negative fixture to an unrelated tool."""
    require(git(root, 'rev-parse', RUNNER_FINITE_TOOL_REFUSAL_BASE + '^{tree}').decode().strip()
            == RUNNER_FINITE_TOOL_REFUSAL_BASE_TREE
            and git(root, 'rev-parse', RUNNER_FINITE_TOOL_REFUSAL_SOURCE + '^{tree}').decode().strip()
            == RUNNER_FINITE_TOOL_REFUSAL_SOURCE_TREE,
            'Runner finite-tool refusal tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_FINITE_TOOL_REFUSAL_SOURCE).decode().strip().split()
            == [RUNNER_FINITE_TOOL_REFUSAL_BASE],
            'Runner finite-tool refusal parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_FINITE_TOOL_REFUSAL_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_FINITE_TOOL_REFUSAL_BASE,
                  RUNNER_FINITE_TOOL_REFUSAL_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_FINITE_TOOL_REFUSAL_MODIFIED)],
            'Runner finite-tool refusal changed a different path or status set')
    base_entries = entries(root, RUNNER_FINITE_TOOL_REFUSAL_BASE)
    source_entries = entries(root, RUNNER_FINITE_TOOL_REFUSAL_SOURCE)
    for path in sorted(RUNNER_FINITE_TOOL_REFUSAL_MODIFIED):
        current = project_proof_cache_hardening_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_FINITE_TOOL_REFUSAL_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_FINITE_TOOL_REFUSAL_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_finite_tool_refusal(root, path, current) == base
                and project_runner_finite_tool_refusal(root, path, base) == base,
                'Runner finite-tool refusal identity or projection changed: ' + path)


def project_runner_finite_tool_refusal(root, path, current):
    """Restore the accepted current-source proof head for older receipts."""
    current = project_proof_cache_hardening_source(root, path, current)
    if path not in RUNNER_FINITE_TOOL_REFUSAL_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_FINITE_TOOL_REFUSAL_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_FINITE_TOOL_REFUSAL_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner finite-tool refusal changed before predecessor projection: ' + path)
    return base


def validate_runner_aws_full_mirror_source(root):
    """Bind the exact fully converted GitHub AWS mirror-list profile."""
    require(git(root, 'rev-parse', RUNNER_AWS_FULL_MIRROR_BASE + '^{tree}').decode().strip()
            == RUNNER_AWS_FULL_MIRROR_BASE_TREE
            and git(root, 'rev-parse', RUNNER_AWS_FULL_MIRROR_SOURCE + '^{tree}').decode().strip()
            == RUNNER_AWS_FULL_MIRROR_SOURCE_TREE,
            'Runner fully converted AWS mirror source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_AWS_FULL_MIRROR_SOURCE).decode().strip().split()
            == [RUNNER_AWS_FULL_MIRROR_BASE],
            'Runner fully converted AWS mirror source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_AWS_FULL_MIRROR_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_AWS_FULL_MIRROR_BASE, RUNNER_AWS_FULL_MIRROR_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_AWS_FULL_MIRROR_MODIFIED)],
            'Runner fully converted AWS mirror source changed another path or status')
    base_entries = entries(root, RUNNER_AWS_FULL_MIRROR_BASE)
    source_entries = entries(root, RUNNER_AWS_FULL_MIRROR_SOURCE)
    for path in sorted(RUNNER_AWS_FULL_MIRROR_MODIFIED):
        current = project_runner_application_build_completion(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_AWS_FULL_MIRROR_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_AWS_FULL_MIRROR_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_aws_full_mirror(root, path, current) == base
                and project_runner_aws_full_mirror(root, path, base) == base,
                'Runner fully converted AWS mirror identity or projection changed: ' + path)
    script = ordinary(root, '.github/scripts/prepare_ubuntu_apt.py')
    contract = ordinary(root, 'tests/account_notification_workflow_contract.py')
    require(script.count(b'expected_aws_mirrors = {') == 1
            and script.count(b'elif mirror_data in expected_aws_mirrors:') == 1
            and script.count(b"profile = 'aws-mirror-list'") == 2
            and script.count(b'if mirror_data not in expected_aws_mirrors:') == 1
            and script.count(b"'http://us-east-2.ec2.archive.ubuntu.com/ubuntu/'") == 1
            and script.count(b"'http://eu-west-1.ec2.archive.ubuntu.com/ubuntu/'") == 0
            and script.count(b"'APT::Get::AllowUnauthenticated'") == 1
            and script.count(b"'80a36b0a6de2f69f49d2df75ef473ccde121e9e190b9ea01d20a4f63778d5c31'") == 1
            and contract.count(b'Executable guard accepts exact fully converted AWS mirror-list profile') == 1
            and contract.count(b'Executable guard rejects an unknown fully converted AWS mirror-list archive host') == 1
            and contract.count(b"'http://eu-west-1.ec2.archive.ubuntu.com/ubuntu/\\tpriority:1\\n'") == 2,
            'Exact AWS mirror allowlist, trust guard or positive/negative fixture changed')


def project_runner_aws_full_mirror(root, path, current):
    """Restore the reviewed mail proof before the AWS full-mirror correction."""
    current = project_runner_application_build_completion(root, path, current)
    if path not in RUNNER_AWS_FULL_MIRROR_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_AWS_FULL_MIRROR_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_AWS_FULL_MIRROR_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner fully converted AWS mirror source changed before predecessor projection: ' + path)
    return base


def validate_runner_account_source_third_budget(root):
    """Bind the bounded account source successor after preserved run 44502124267."""
    require(git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE_TREE
            and git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE_TREE,
            'Runner third account source budget identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE).decode().strip().split()
            == [RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE],
            'Runner third account source budget parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE,
                  RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_MODIFIED)],
            'Runner third account source budget changed another path or status')
    base_entries = entries(root, RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE)
    source_entries = entries(root, RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE)
    for path in sorted(RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_MODIFIED):
        current = project_runner_application_seal_budget(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_account_source_third_budget(root, path, current) == base
                and project_runner_account_source_third_budget(root, path, base) == base,
                'Runner third account source budget identity or projection changed: ' + path)
    workflow = ordinary(root, '.github/workflows/account-notifications-acceptance.yml')
    contract = ordinary(root, 'tests/account_notification_workflow_contract.py')
    require(workflow.count(b'run 44502124267') == 1
            and workflow.count(b'    timeout-minutes: 360\n') == 1
            and workflow.count(b'    timeout-minutes: 180\n') == 0
            and workflow.count(b'    timeout-minutes: 35\n') == 1
            and contract.count(b'SOURCE_TIMEOUT_SUCCESSOR =') == 1
            and contract.count(b'source.count(SOURCE_TIMEOUT_SUCCESSOR) == 1') == 1
            and contract.count(b"SOURCE_TIMEOUT_SUCCESSOR.replace('timeout-minutes: 360', 'timeout-minutes: 180')") == 1,
            'Runner third account source timeout or mutation guard changed')


def project_runner_account_source_third_budget(root, path, current):
    """Restore the reviewed browser proof head before the 360-minute account budget."""
    current = project_runner_application_seal_budget(root, path, current)
    if path not in RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_THIRD_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner third account source budget changed before predecessor projection: ' + path)
    return base


def validate_runner_account_source_second_budget(root):
    """Bind the bounded account source successor after preserved run 44457889547."""
    require(git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE_TREE
            and git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE_TREE,
            'Runner second account source budget identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE).decode().strip().split()
            == [RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE],
            'Runner second account source budget parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE,
                  RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_MODIFIED)],
            'Runner second account source budget changed another path or status')
    base_entries = entries(root, RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE)
    source_entries = entries(root, RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE)
    for path in sorted(RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_MODIFIED):
        current = project_runner_account_source_third_budget(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_account_source_second_budget(root, path, current) == base
                and project_runner_account_source_second_budget(root, path, base) == base,
                'Runner second account source budget identity or projection changed: ' + path)
    workflow = project_runner_account_source_third_budget(
        root, '.github/workflows/account-notifications-acceptance.yml',
        ordinary(root, '.github/workflows/account-notifications-acceptance.yml'))
    contract = project_runner_account_source_third_budget(
        root, 'tests/account_notification_workflow_contract.py',
        ordinary(root, 'tests/account_notification_workflow_contract.py'))
    require(workflow.count(b'run 44457889547') == 1
            and workflow.count(b'    timeout-minutes: 180\n') == 1
            and workflow.count(b'    timeout-minutes: 90\n') == 0
            and workflow.count(b'    timeout-minutes: 35\n') == 1
            and contract.count(b'SOURCE_TIMEOUT_SUCCESSOR =') == 1
            and contract.count(b'source.count(SOURCE_TIMEOUT_SUCCESSOR) == 1') == 1
            and contract.count(b"SOURCE_TIMEOUT_SUCCESSOR.replace('timeout-minutes: 180', 'timeout-minutes: 90')") == 1,
            'Runner second account source timeout or mutation guard changed')


def project_runner_account_source_second_budget(root, path, current):
    """Restore the reviewed Application fixture source before the 180-minute account budget."""
    current = project_runner_account_source_third_budget(root, path, current)
    if path not in RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_SECOND_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner second account source budget changed before predecessor projection: ' + path)
    return base


def validate_runner_account_source_final_budget(root):
    """Bind the bounded account source budget after preserved run 44423175458."""
    require(git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE_TREE
            and git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE_TREE,
            'Runner final account source budget identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE).decode().strip().split()
            == [RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE],
            'Runner final account source budget parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE,
                  RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_MODIFIED)],
            'Runner final account source budget changed another path or status')
    base_entries = entries(root, RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE)
    source_entries = entries(root, RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE)
    for path in sorted(RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_MODIFIED):
        current = project_runner_account_source_second_budget(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_account_source_final_budget(root, path, current) == base
                and project_runner_account_source_final_budget(root, path, base) == base,
                'Runner final account source budget identity or projection changed: ' + path)
    workflow = project_runner_account_source_second_budget(
        root, '.github/workflows/account-notifications-acceptance.yml',
        ordinary(root, '.github/workflows/account-notifications-acceptance.yml'))
    contract = project_runner_account_source_second_budget(
        root, 'tests/account_notification_workflow_contract.py',
        ordinary(root, 'tests/account_notification_workflow_contract.py'))
    require(workflow.count(b'run 44423175458') == 1
            and workflow.count(b'    timeout-minutes: 90\n') == 1
            and workflow.count(b'    timeout-minutes: 50\n') == 0
            and workflow.count(b'    timeout-minutes: 35\n') == 1
            and contract.count(b'SOURCE_TIMEOUT_SUCCESSOR =') == 1
            and contract.count(b'source.count(SOURCE_TIMEOUT_SUCCESSOR) == 1') == 1
            and contract.count(b"'lost bounded current-source timeout', SOURCE_TIMEOUT_SUCCESSOR") == 1,
            'Runner final account source timeout or mutation guard changed')


def project_runner_account_source_final_budget(root, path, current):
    """Restore the reviewed flag-count head before the 90-minute account budget."""
    current = project_runner_account_source_second_budget(root, path, current)
    if path not in RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_FINAL_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner final account source budget changed before predecessor projection: ' + path)
    return base


def validate_account_rate_boundary_source(root):
    """Bind the exact fixture-only correction after preserved run 44676679254."""
    require(git(root, 'rev-parse', ACCOUNT_RATE_BOUNDARY_BASE + '^{tree}').decode().strip()
            == ACCOUNT_RATE_BOUNDARY_BASE_TREE
            and git(root, 'rev-parse', ACCOUNT_RATE_BOUNDARY_SOURCE + '^{tree}').decode().strip()
            == ACCOUNT_RATE_BOUNDARY_SOURCE_TREE,
            'Account rate-boundary source identity changed')
    require(git(root, 'show', '-s', '--format=%P', ACCOUNT_RATE_BOUNDARY_SOURCE)
            .decode().strip().split() == [ACCOUNT_RATE_BOUNDARY_BASE],
            'Account rate-boundary source parent changed')
    git(root, 'merge-base', '--is-ancestor', ACCOUNT_RATE_BOUNDARY_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  ACCOUNT_RATE_BOUNDARY_BASE, ACCOUNT_RATE_BOUNDARY_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(ACCOUNT_RATE_BOUNDARY_MODIFIED)],
            'Account rate-boundary source changed another path or status set')
    base_entries = entries(root, ACCOUNT_RATE_BOUNDARY_BASE)
    source_entries = entries(root, ACCOUNT_RATE_BOUNDARY_SOURCE)
    for path in sorted(ACCOUNT_RATE_BOUNDARY_MODIFIED):
        current = ordinary(root, path)
        source = git(root, 'cat-file', 'blob', ACCOUNT_RATE_BOUNDARY_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', ACCOUNT_RATE_BOUNDARY_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_account_rate_boundary_source(root, path, current) == base
                and project_account_rate_boundary_source(root, path, base) == base,
                'Account rate-boundary identity or projection changed: ' + path)
        require(source.count(b'rate_window=900;rate_remaining=rate_window-(int(time.time())%rate_window)') == 1
                and source.count(b'if rate_remaining<=30:time.sleep(rate_remaining+1)') == 1
                and source.count(b'rate_bucket=int(time.time())//rate_window') == 1
                and source.count(b'int(time.time())//rate_window==rate_bucket and ') == 1
                and source.count(b'for _ in range(6):') == 1
                and source.count(b"'Too many password-change attempts' in r.text") == 1
                and source.count(b"int(time.time())//rate_window==rate_bucket and 'Too many password-change attempts' in r.text and state()['studio_clients']==before['studio_clients']") == 1
                and b'rate_window=900' not in base,
                'Account rate-boundary guard or retained security assertion changed: ' + path)


def project_account_rate_boundary_source(root, path, current):
    """Restore the f8 exact head before older account fixture projections."""
    if path not in ACCOUNT_RATE_BOUNDARY_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', ACCOUNT_RATE_BOUNDARY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', ACCOUNT_RATE_BOUNDARY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current account rate-boundary source changed before predecessor projection: ' + path)
    return base


def validate_runner_application_seal_budget(root):
    """Bind the evidence-seal budget discovered by preserved run 44639431573."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_SEAL_BUDGET_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_SEAL_BUDGET_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_SEAL_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_SEAL_BUDGET_SOURCE_TREE,
            'Runner Application seal budget identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                RUNNER_APPLICATION_SEAL_BUDGET_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_SEAL_BUDGET_BASE],
            'Runner Application seal budget parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_APPLICATION_SEAL_BUDGET_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_SEAL_BUDGET_BASE,
                  RUNNER_APPLICATION_SEAL_BUDGET_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_APPLICATION_SEAL_BUDGET_MODIFIED)],
            'Runner Application seal budget changed another path or status set')
    base_entries = entries(root, RUNNER_APPLICATION_SEAL_BUDGET_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_SEAL_BUDGET_SOURCE)
    for path in sorted(RUNNER_APPLICATION_SEAL_BUDGET_MODIFIED):
        current = project_signed_ppa_label_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SEAL_BUDGET_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SEAL_BUDGET_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_seal_budget(root, path, current) == base
                and project_runner_application_seal_budget(root, path, base) == base,
                'Runner Application seal budget identity or projection changed: ' + path)
    workflow = project_runner_application_composition_completion(
        root, '.github/workflows/application-tests.yml',
        ordinary(root, '.github/workflows/application-tests.yml'))
    contract = project_runner_application_composition_completion(
        root, 'tests/account_notification_workflow_contract.py',
        ordinary(root, 'tests/account_notification_workflow_contract.py'))
    workflow_base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SEAL_BUDGET_BASE
                        + ':.github/workflows/application-tests.yml')
    contract_base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SEAL_BUDGET_BASE
                        + ':tests/account_notification_workflow_contract.py')
    require(workflow.count(b'Run 44639431573 completed provenance, source, inventory, package, guards') == 1
            and workflow.count(b'cancelled the evidence seal. Keep every assertion and allow bounded seal.') == 1
            and workflow.count(b'    timeout-minutes: 300\n') == 1
            and workflow.count(b'    timeout-minutes: 240\n') == 1
            and contract.count(b'Run 44639431573 completed provenance, source, inventory, package, guards') == 1
            and contract.count(b"'    timeout-minutes: 300\\n')") == 1
            and workflow_base.count(b'    timeout-minutes: 300\n') == 0
            and workflow_base.count(b'    timeout-minutes: 240\n') == 2
            and contract_base.count(b"'    timeout-minutes: 300\\n')") == 0
            and contract_base.count(b"'    timeout-minutes: 240\\n')") == 1,
            'Runner Application seal budget or mutation guard changed')


def project_runner_application_seal_budget(root, path, current):
    """Restore the preserved 240-minute exact head before older projections."""
    current = project_signed_ppa_label_source(root, path, current)
    if path not in RUNNER_APPLICATION_SEAL_BUDGET_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SEAL_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SEAL_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application seal budget changed before predecessor projection: ' + path)
    return base


def validate_runner_application_build_completion(root):
    """Bind the bounded completion budget discovered by preserved run 44385954180."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_BUILD_COMPLETION_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_BUILD_COMPLETION_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE_TREE,
            'Runner Application build completion identity changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_BUILD_COMPLETION_BASE],
            'Runner Application build completion parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_BUILD_COMPLETION_BASE,
                  RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_APPLICATION_BUILD_COMPLETION_MODIFIED)],
            'Runner Application build completion changed a different path or status set')
    base_entries = entries(root, RUNNER_APPLICATION_BUILD_COMPLETION_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE)
    for path in sorted(RUNNER_APPLICATION_BUILD_COMPLETION_MODIFIED):
        current = project_runner_application_seal_budget(root, path, ordinary(root, path))
        current = project_runner_account_source_final_budget(root, path, current)
        source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_BUILD_COMPLETION_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_build_completion(root, path, current) == base
                and project_runner_application_build_completion(root, path, base) == base,
                'Runner Application build completion identity or projection changed: ' + path)


def project_runner_application_build_completion(root, path, current):
    """Restore c45 only for the exact 240-minute Application completion paths."""
    current = project_runner_application_seal_budget(root, path, current)
    current = project_runner_account_source_final_budget(root, path, current)
    if path not in RUNNER_APPLICATION_BUILD_COMPLETION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_BUILD_COMPLETION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_BUILD_COMPLETION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application build completion changed before predecessor projection: ' + path)
    return base


def validate_runner_application_build_budget(root):
    """Bind the exact Application build-proof timeout correction to its failed head."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_BUILD_BUDGET_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_BUILD_BUDGET_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_BUILD_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_BUILD_BUDGET_SOURCE_TREE,
            'Runner Application build budget identity changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_APPLICATION_BUILD_BUDGET_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_BUILD_BUDGET_BASE],
            'Runner Application build budget parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_APPLICATION_BUILD_BUDGET_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_BUILD_BUDGET_BASE,
                  RUNNER_APPLICATION_BUILD_BUDGET_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_APPLICATION_BUILD_BUDGET_MODIFIED)],
            'Runner Application build budget changed a different path or status set')
    base_entries = entries(root, RUNNER_APPLICATION_BUILD_BUDGET_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_BUILD_BUDGET_SOURCE)
    for path in sorted(RUNNER_APPLICATION_BUILD_BUDGET_MODIFIED):
        current = project_runner_application_build_completion(root, path, ordinary(root, path))
        current = project_runner_aws_full_mirror(root, path, current)
        current = project_proof_runtime_source(root, path, current)
        source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_BUILD_BUDGET_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_BUILD_BUDGET_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_build_budget(root, path, current) == base
                and project_runner_application_build_budget(root, path, base) == base,
                'Runner Application build budget identity or projection changed: ' + path)


def project_runner_application_build_budget(root, path, current):
    """Restore the failed hosted head only for the exact Application budget paths."""
    current = project_runner_application_build_completion(root, path, current)
    current = project_runner_aws_full_mirror(root, path, current)
    current = project_proof_runtime_source(root, path, current)
    if path not in RUNNER_APPLICATION_BUILD_BUDGET_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_BUILD_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_BUILD_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application build budget changed before predecessor projection: ' + path)
    return base


def validate_runner_account_source_completion(root):
    """Bind the bounded completion budget discovered by the preserved c45 run."""
    require(git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE_TREE
            and git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE_TREE,
            'Runner account source completion identity changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE)
            .decode().strip().split() == [RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE],
            'Runner account source completion parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE,
                  RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_ACCOUNT_SOURCE_COMPLETION_MODIFIED)],
            'Runner account source completion changed a different path or status set')
    base_entries = entries(root, RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE)
    source_entries = entries(root, RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE)
    for path in sorted(RUNNER_ACCOUNT_SOURCE_COMPLETION_MODIFIED):
        current = project_runner_account_source_final_budget(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and source.count(b'    timeout-minutes: 50\n') == 1
                and source.count(b'    timeout-minutes: 35\n') == 1
                and base.count(b'    timeout-minutes: 35\n') == 2
                and project_runner_account_source_completion(root, path, current) == base
                and project_runner_account_source_completion(root, path, base) == base,
                'Runner account source completion identity or projection changed: ' + path)


def project_runner_account_source_completion(root, path, current):
    """Restore c45 only for the exact 50-to-35-minute source budget."""
    current = project_runner_account_source_final_budget(root, path, current)
    if path not in RUNNER_ACCOUNT_SOURCE_COMPLETION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_COMPLETION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_COMPLETION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner account source completion changed before predecessor projection: ' + path)
    return base


def validate_runner_account_source_budget(root):
    """Bind the exact account source-proof timeout correction to its failed head."""
    require(git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_BUDGET_BASE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_BUDGET_BASE_TREE
            and git(root, 'rev-parse', RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE + '^{tree}').decode().strip()
            == RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE_TREE,
            'Runner account source budget identity changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE).decode().strip().split()
            == [RUNNER_ACCOUNT_SOURCE_BUDGET_BASE],
            'Runner account source budget parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_ACCOUNT_SOURCE_BUDGET_BASE,
                  RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_ACCOUNT_SOURCE_BUDGET_MODIFIED)],
            'Runner account source budget changed a different path or status set')
    base_entries = entries(root, RUNNER_ACCOUNT_SOURCE_BUDGET_BASE)
    source_entries = entries(root, RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE)
    for path in sorted(RUNNER_ACCOUNT_SOURCE_BUDGET_MODIFIED):
        current = project_runner_account_source_completion(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_BUDGET_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_account_source_budget(root, path, current) == base
                and project_runner_account_source_budget(root, path, base) == base,
                'Runner account source budget identity or projection changed: ' + path)


def project_runner_account_source_budget(root, path, current):
    """Restore the failed hosted head only for the exact account timeout path."""
    current = project_runner_account_source_completion(root, path, current)
    current = project_runner_application_build_budget(root, path, current)
    if path not in RUNNER_ACCOUNT_SOURCE_BUDGET_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_ACCOUNT_SOURCE_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner account source budget changed before predecessor projection: ' + path)
    return base


def validate_runner_application_intake_projection_source(root):
    """Bind the immediate cloud-request predecessor projection."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_INTAKE_PROJECTION_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_INTAKE_PROJECTION_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE_TREE,
            'Runner Application intake-projection tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_INTAKE_PROJECTION_BASE],
            'Runner Application intake-projection parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_INTAKE_PROJECTION_BASE,
                  RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_APPLICATION_INTAKE_PROJECTION_MODIFIED)],
            'Runner Application intake projection changed a different path or status set')
    base_entries = entries(root, RUNNER_APPLICATION_INTAKE_PROJECTION_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE)
    for path in sorted(RUNNER_APPLICATION_INTAKE_PROJECTION_MODIFIED):
        current = project_project_private_notes_static_intake_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_INTAKE_PROJECTION_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_intake_projection(root, path, current) == base
                and project_runner_application_intake_projection(root, path, base) == base,
                'Runner Application intake-projection identity or projection changed: ' + path)


def project_runner_application_intake_projection(root, path, current):
    """Restore the reviewed hybrid-fixture correction head."""
    current = project_project_private_notes_static_intake_source(root, path, current)
    if path not in RUNNER_APPLICATION_INTAKE_PROJECTION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_INTAKE_PROJECTION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_INTAKE_PROJECTION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application intake projection changed before predecessor projection: ' + path)
    return base


def validate_runner_aws_mirror_fixture_source(root):
    """Bind the explicit hybrid-security executable fixture correction."""
    require(git(root, 'rev-parse', RUNNER_AWS_MIRROR_FIXTURE_BASE + '^{tree}').decode().strip()
            == RUNNER_AWS_MIRROR_FIXTURE_BASE_TREE
            and git(root, 'rev-parse', RUNNER_AWS_MIRROR_FIXTURE_SOURCE + '^{tree}').decode().strip()
            == RUNNER_AWS_MIRROR_FIXTURE_SOURCE_TREE,
            'Runner AWS mirror fixture source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_AWS_MIRROR_FIXTURE_SOURCE).decode().strip().split()
            == [RUNNER_AWS_MIRROR_FIXTURE_BASE],
            'Runner AWS mirror fixture source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_AWS_MIRROR_FIXTURE_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_AWS_MIRROR_FIXTURE_BASE, RUNNER_AWS_MIRROR_FIXTURE_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_AWS_MIRROR_FIXTURE_MODIFIED)],
            'Runner AWS mirror fixture changed a different path or status set')
    base_entries = entries(root, RUNNER_AWS_MIRROR_FIXTURE_BASE)
    source_entries = entries(root, RUNNER_AWS_MIRROR_FIXTURE_SOURCE)
    for path in sorted(RUNNER_AWS_MIRROR_FIXTURE_MODIFIED):
        current = project_runner_application_build_budget(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_AWS_MIRROR_FIXTURE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_AWS_MIRROR_FIXTURE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_aws_mirror_fixture(root, path, current) == base
                and project_runner_aws_mirror_fixture(root, path, base) == base,
                'Runner AWS mirror fixture identity or projection changed: ' + path)


def project_runner_aws_mirror_fixture(root, path, current):
    """Restore the first AWS mirror-profile proof head."""
    current = project_runner_application_build_budget(root, path, current)
    if path not in RUNNER_AWS_MIRROR_FIXTURE_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_AWS_MIRROR_FIXTURE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_AWS_MIRROR_FIXTURE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner AWS mirror fixture changed before predecessor projection: ' + path)
    return base


def validate_runner_application_intake_binding(root):
    """Bind the exact historical cloud-request projection exposed by Application."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_INTAKE_BINDING_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_INTAKE_BINDING_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_INTAKE_BINDING_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_INTAKE_BINDING_SOURCE_TREE,
            'Runner Application intake-binding tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_APPLICATION_INTAKE_BINDING_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_INTAKE_BINDING_BASE],
            'Runner Application intake-binding parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_APPLICATION_INTAKE_BINDING_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_INTAKE_BINDING_BASE,
                  RUNNER_APPLICATION_INTAKE_BINDING_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_APPLICATION_INTAKE_BINDING_MODIFIED)],
            'Runner Application intake binding changed a different path or status set')
    base_entries = entries(root, RUNNER_APPLICATION_INTAKE_BINDING_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_INTAKE_BINDING_SOURCE)
    for path in sorted(RUNNER_APPLICATION_INTAKE_BINDING_MODIFIED):
        current = project_runner_application_intake_projection(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_INTAKE_BINDING_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_INTAKE_BINDING_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_intake_binding(root, path, current) == base
                and project_runner_application_intake_binding(root, path, base) == base,
                'Runner Application intake-binding identity or projection changed: ' + path)


def project_runner_application_intake_binding(root, path, current):
    """Restore the accepted AWS mirror-profile implementation head."""
    current = project_runner_application_intake_projection(root, path, current)
    if path not in RUNNER_APPLICATION_INTAKE_BINDING_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_INTAKE_BINDING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_INTAKE_BINDING_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application intake binding changed before predecessor projection: ' + path)
    return base


def validate_runner_aws_mirror_profile_source(root):
    """Bind only the exact observed .127 AWS mirror-list source profile."""
    require(git(root, 'rev-parse', RUNNER_AWS_MIRROR_PROFILE_BASE + '^{tree}').decode().strip()
            == RUNNER_AWS_MIRROR_PROFILE_BASE_TREE
            and git(root, 'rev-parse', RUNNER_AWS_MIRROR_PROFILE_SOURCE + '^{tree}').decode().strip()
            == RUNNER_AWS_MIRROR_PROFILE_SOURCE_TREE,
            'Runner AWS mirror profile source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_AWS_MIRROR_PROFILE_SOURCE).decode().strip().split()
            == [RUNNER_AWS_MIRROR_PROFILE_BASE],
            'Runner AWS mirror profile source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_AWS_MIRROR_PROFILE_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_AWS_MIRROR_PROFILE_BASE, RUNNER_AWS_MIRROR_PROFILE_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_AWS_MIRROR_PROFILE_MODIFIED)],
            'Runner AWS mirror profile changed a different path or status set')
    base_entries = entries(root, RUNNER_AWS_MIRROR_PROFILE_BASE)
    source_entries = entries(root, RUNNER_AWS_MIRROR_PROFILE_SOURCE)
    for path in sorted(RUNNER_AWS_MIRROR_PROFILE_MODIFIED):
        current = project_runner_aws_mirror_fixture(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_AWS_MIRROR_PROFILE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_AWS_MIRROR_PROFILE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_aws_mirror_profile(root, path, current) == base
                and project_runner_aws_mirror_profile(root, path, base) == base,
                'Runner AWS mirror profile identity or projection changed: ' + path)


def project_runner_aws_mirror_profile(root, path, current):
    """Restore the accepted Application historical-binding proof head."""
    current = project_runner_aws_mirror_fixture(root, path, current)
    if path not in RUNNER_AWS_MIRROR_PROFILE_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_AWS_MIRROR_PROFILE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_AWS_MIRROR_PROFILE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner AWS mirror profile changed before predecessor projection: ' + path)
    return base


def validate_runner_application_historical_binding(root):
    """Bind independent historical and current Application source hashes."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_HISTORICAL_BINDING_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_HISTORICAL_BINDING_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE_TREE,
            'Runner Application historical-binding tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_HISTORICAL_BINDING_BASE],
            'Runner Application historical-binding parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_HISTORICAL_BINDING_BASE,
                  RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_APPLICATION_HISTORICAL_BINDING_MODIFIED)],
            'Runner Application historical binding changed a different path or status set')
    base_entries = entries(root, RUNNER_APPLICATION_HISTORICAL_BINDING_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE)
    for path in sorted(RUNNER_APPLICATION_HISTORICAL_BINDING_MODIFIED):
        current = ordinary(root, path)
        source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_HISTORICAL_BINDING_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_historical_binding(root, path, current) == base
                and project_runner_application_historical_binding(root, path, base) == base,
                'Runner Application historical-binding identity or projection changed: ' + path)


def project_runner_application_historical_binding(root, path, current):
    """Restore the first current-source binding proof for older receipts."""
    if path not in RUNNER_APPLICATION_HISTORICAL_BINDING_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_HISTORICAL_BINDING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_HISTORICAL_BINDING_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application historical binding changed before predecessor projection: ' + path)
    return base


def validate_runner_application_source_binding(root):
    """Bind the exact current-source override exposed by hosted Application."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_SOURCE_BINDING_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_SOURCE_BINDING_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_SOURCE_BINDING_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_SOURCE_BINDING_SOURCE_TREE,
            'Runner Application source-binding tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_APPLICATION_SOURCE_BINDING_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_SOURCE_BINDING_BASE],
            'Runner Application source-binding parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_APPLICATION_SOURCE_BINDING_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_SOURCE_BINDING_BASE,
                  RUNNER_APPLICATION_SOURCE_BINDING_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_APPLICATION_SOURCE_BINDING_MODIFIED)],
            'Runner Application source binding changed a different path or status set')
    base_entries = entries(root, RUNNER_APPLICATION_SOURCE_BINDING_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_SOURCE_BINDING_SOURCE)
    for path in sorted(RUNNER_APPLICATION_SOURCE_BINDING_MODIFIED):
        current = project_runner_intake_current_binding(root, path, ordinary(root, path))
        current = project_runner_application_historical_binding(root, path, current)
        source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SOURCE_BINDING_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SOURCE_BINDING_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_source_binding(root, path, current) == base
                and project_runner_application_source_binding(root, path, base) == base,
                'Runner Application source-binding identity or projection changed: ' + path)


def project_runner_application_source_binding(root, path, current):
    """Restore the reviewed AWS proof head for older source receipts."""
    current = project_runner_intake_current_binding(root, path, current)
    current = project_runner_application_historical_binding(root, path, current)
    if path not in RUNNER_APPLICATION_SOURCE_BINDING_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SOURCE_BINDING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_SOURCE_BINDING_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application source binding changed before predecessor projection: ' + path)
    return base


def validate_runner_aws_profile_source(root):
    """Bind only the exact observed .127 AWS Noble source profile."""
    require(git(root, 'rev-parse', RUNNER_AWS_PROFILE_BASE + '^{tree}').decode().strip()
            == RUNNER_AWS_PROFILE_BASE_TREE
            and git(root, 'rev-parse', RUNNER_AWS_PROFILE_SOURCE + '^{tree}').decode().strip()
            == RUNNER_AWS_PROFILE_SOURCE_TREE,
            'Runner AWS profile source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_AWS_PROFILE_SOURCE).decode().strip().split()
            == [RUNNER_AWS_PROFILE_BASE],
            'Runner AWS profile source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_AWS_PROFILE_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_AWS_PROFILE_BASE, RUNNER_AWS_PROFILE_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_AWS_PROFILE_MODIFIED)],
            'Runner AWS profile changed a different path or status set')
    base_entries = entries(root, RUNNER_AWS_PROFILE_BASE)
    source_entries = entries(root, RUNNER_AWS_PROFILE_SOURCE)
    for path in sorted(RUNNER_AWS_PROFILE_MODIFIED):
        current = project_runner_aws_mirror_profile(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_AWS_PROFILE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_AWS_PROFILE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_aws_profile(root, path, current) == base
                and project_runner_aws_profile(root, path, base) == base,
                'Runner AWS profile identity or projection changed: ' + path)


def project_runner_aws_profile(root, path, current):
    """Restore the reviewed evidence-provenance head for older receipts."""
    current = project_runner_aws_mirror_profile(root, path, current)
    if path not in RUNNER_AWS_PROFILE_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_AWS_PROFILE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_AWS_PROFILE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner AWS profile changed before predecessor projection: ' + path)
    return base


def validate_runner_evidence_provenance_source(root):
    """Bind the independent-review repair for mandatory APT receipts."""
    require(git(root, 'rev-parse', RUNNER_EVIDENCE_PROVENANCE_BASE + '^{tree}').decode().strip()
            == RUNNER_EVIDENCE_PROVENANCE_BASE_TREE
            and git(root, 'rev-parse', RUNNER_EVIDENCE_PROVENANCE_SOURCE + '^{tree}').decode().strip()
            == RUNNER_EVIDENCE_PROVENANCE_SOURCE_TREE,
            'Runner evidence provenance source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_EVIDENCE_PROVENANCE_SOURCE).decode().strip().split()
            == [RUNNER_EVIDENCE_PROVENANCE_BASE],
            'Runner evidence provenance source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_EVIDENCE_PROVENANCE_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_EVIDENCE_PROVENANCE_BASE,
                  RUNNER_EVIDENCE_PROVENANCE_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_EVIDENCE_PROVENANCE_MODIFIED)],
            'Runner evidence provenance changed a different path or status set')
    base_entries = entries(root, RUNNER_EVIDENCE_PROVENANCE_BASE)
    source_entries = entries(root, RUNNER_EVIDENCE_PROVENANCE_SOURCE)
    for path in sorted(RUNNER_EVIDENCE_PROVENANCE_MODIFIED):
        current = project_runner_aws_profile(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_EVIDENCE_PROVENANCE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_EVIDENCE_PROVENANCE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_evidence_provenance(root, path, current) == base
                and project_runner_evidence_provenance(root, path, base) == base,
                'Runner evidence provenance identity or projection changed: ' + path)


def project_runner_evidence_provenance(root, path, current):
    """Restore the first Application-completion proof for older receipts."""
    current = project_runner_aws_profile(root, path, current)
    if path not in RUNNER_EVIDENCE_PROVENANCE_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_EVIDENCE_PROVENANCE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_EVIDENCE_PROVENANCE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner evidence provenance changed before predecessor projection: ' + path)
    return base


def validate_runner_application_completion_source(root):
    """Bind the bounded timeout and collision-free APT evidence successor."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_COMPLETION_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_COMPLETION_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_COMPLETION_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_COMPLETION_SOURCE_TREE,
            'Runner Application completion source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_APPLICATION_COMPLETION_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_COMPLETION_BASE],
            'Runner Application completion source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_APPLICATION_COMPLETION_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_COMPLETION_BASE,
                  RUNNER_APPLICATION_COMPLETION_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_APPLICATION_COMPLETION_MODIFIED)],
            'Runner Application completion changed a different path or status set')
    base_entries = entries(root, RUNNER_APPLICATION_COMPLETION_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_COMPLETION_SOURCE)
    for path in sorted(RUNNER_APPLICATION_COMPLETION_MODIFIED):
        current = project_runner_evidence_provenance(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_COMPLETION_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_COMPLETION_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_completion(root, path, current) == base
                and project_runner_application_completion(root, path, base) == base,
                'Runner Application completion identity or projection changed: ' + path)


def project_runner_application_completion(root, path, current):
    """Restore the first hosted compatibility proof head for older receipts."""
    current = project_runner_evidence_provenance(root, path, current)
    if path not in RUNNER_APPLICATION_COMPLETION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_COMPLETION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_APPLICATION_COMPLETION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application completion changed before predecessor projection: ' + path)
    return base


def validate_runner_keyring_fixture_source(root):
    """Bind the exact Linux fixture correction found by independent review."""
    require(git(root, 'rev-parse', RUNNER_KEYRING_FIXTURE_BASE + '^{tree}').decode().strip()
            == RUNNER_KEYRING_FIXTURE_BASE_TREE
            and git(root, 'rev-parse', RUNNER_KEYRING_FIXTURE_SOURCE + '^{tree}').decode().strip()
            == RUNNER_KEYRING_FIXTURE_SOURCE_TREE,
            'Runner keyring fixture source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_KEYRING_FIXTURE_SOURCE).decode().strip().split()
            == [RUNNER_KEYRING_FIXTURE_BASE],
            'Runner keyring fixture source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_KEYRING_FIXTURE_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_KEYRING_FIXTURE_BASE, RUNNER_KEYRING_FIXTURE_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_KEYRING_FIXTURE_MODIFIED)],
            'Runner keyring fixture changed a different path or status set')
    base_entries = entries(root, RUNNER_KEYRING_FIXTURE_BASE)
    source_entries = entries(root, RUNNER_KEYRING_FIXTURE_SOURCE)
    for path in sorted(RUNNER_KEYRING_FIXTURE_MODIFIED):
        current = project_runner_application_completion(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_KEYRING_FIXTURE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_KEYRING_FIXTURE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_keyring_fixture(root, path, current) == base
                and project_runner_keyring_fixture(root, path, base) == base,
                'Runner keyring fixture identity or projection changed: ' + path)


def project_runner_keyring_fixture(root, path, current):
    """Restore the reviewed keyring source before the fixture-only successor."""
    current = project_runner_application_completion(root, path, current)
    if path not in RUNNER_KEYRING_FIXTURE_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_KEYRING_FIXTURE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_KEYRING_FIXTURE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner keyring fixture changed before predecessor projection: ' + path)
    return base


def validate_runner_keyring_isolation_source(root):
    """Bind the exact protected-keyring correction to its failed hosted source."""
    require(git(root, 'rev-parse', RUNNER_KEYRING_ISOLATION_BASE + '^{tree}').decode().strip()
            == RUNNER_KEYRING_ISOLATION_BASE_TREE
            and git(root, 'rev-parse', RUNNER_KEYRING_ISOLATION_SOURCE + '^{tree}').decode().strip()
            == RUNNER_KEYRING_ISOLATION_SOURCE_TREE,
            'Runner keyring isolation source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_KEYRING_ISOLATION_SOURCE).decode().strip().split()
            == [RUNNER_KEYRING_ISOLATION_BASE],
            'Runner keyring isolation source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_KEYRING_ISOLATION_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_KEYRING_ISOLATION_BASE, RUNNER_KEYRING_ISOLATION_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(RUNNER_KEYRING_ISOLATION_MODIFIED)],
            'Runner keyring isolation changed a different path or status set')
    base_entries = entries(root, RUNNER_KEYRING_ISOLATION_BASE)
    source_entries = entries(root, RUNNER_KEYRING_ISOLATION_SOURCE)
    for path in sorted(RUNNER_KEYRING_ISOLATION_MODIFIED):
        current = project_runner_keyring_fixture(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_KEYRING_ISOLATION_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_KEYRING_ISOLATION_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_keyring_isolation(root, path, current) == base
                and project_runner_keyring_isolation(root, path, base) == base,
                'Runner keyring isolation identity or projection changed: ' + path)


def project_runner_keyring_isolation(root, path, current):
    """Restore the failed hosted head only for the exact keyring successor paths."""
    current = project_runner_keyring_fixture(root, path, current)
    if path not in RUNNER_KEYRING_ISOLATION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_KEYRING_ISOLATION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_KEYRING_ISOLATION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner keyring isolation changed before predecessor projection: ' + path)
    return base


def validate_runner_mirror_phase_source(root):
    """Bind the exact extraction successor for GitHub scalar and fixture failures."""
    require(git(root, 'rev-parse', RUNNER_MIRROR_PHASE_BASE + '^{tree}').decode().strip()
            == RUNNER_MIRROR_PHASE_BASE_TREE
            and git(root, 'rev-parse', RUNNER_MIRROR_PHASE_SOURCE + '^{tree}').decode().strip()
            == RUNNER_MIRROR_PHASE_SOURCE_TREE,
            'Runner mirror phase source tree changed')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_MIRROR_PHASE_SOURCE).decode().strip().split()
            == [RUNNER_MIRROR_PHASE_BASE],
            'Runner mirror phase source parent changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_MIRROR_PHASE_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_MIRROR_PHASE_BASE, RUNNER_MIRROR_PHASE_SOURCE).decode().splitlines()
    expected = [('A' if path in RUNNER_MIRROR_PHASE_ADDITIONS else 'M') + '\t' + path
                for path in sorted(RUNNER_MIRROR_PHASE_MODIFIED | RUNNER_MIRROR_PHASE_ADDITIONS)]
    require(changed == expected, 'Runner mirror phase successor changed a different path or status set')
    base_entries = entries(root, RUNNER_MIRROR_PHASE_BASE)
    source_entries = entries(root, RUNNER_MIRROR_PHASE_SOURCE)
    require(not RUNNER_MIRROR_PHASE_ADDITIONS & set(base_entries)
            and RUNNER_MIRROR_PHASE_ADDITIONS <= set(source_entries),
            'Runner mirror phase addition did not originate in its exact source')
    for path in sorted(RUNNER_MIRROR_PHASE_MODIFIED):
        current = project_runner_account_source_budget(root, path, ordinary(root, path))
        current = project_runner_keyring_isolation(root, path, current)
        source = git(root, 'cat-file', 'blob', RUNNER_MIRROR_PHASE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_MIRROR_PHASE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_mirror_phase(root, path, current) == base
                and project_runner_mirror_phase(root, path, base) == base,
                'Runner mirror phase identity or projection changed: ' + path)
    for path in sorted(RUNNER_MIRROR_PHASE_ADDITIONS):
        require(project_runner_keyring_isolation(root, path, ordinary(root, path))
                == git(root, 'cat-file', 'blob', RUNNER_MIRROR_PHASE_SOURCE + ':' + path)
                and mode(root, path) == source_entries[path][0] == '100644',
                'Runner mirror phase addition identity changed: ' + path)


def project_runner_mirror_phase(root, path, current):
    """Project only modified extraction-successor paths to the failed proof head."""
    current = project_runner_account_source_budget(root, path, current)
    current = project_runner_keyring_isolation(root, path, current)
    if path not in RUNNER_MIRROR_PHASE_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_MIRROR_PHASE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_MIRROR_PHASE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner mirror phase source changed before predecessor projection: ' + path)
    return base


def validate_runner_mirror_compat_source(root):
    """Bind the reviewed dual-profile runner correction to exact main source."""
    require(git(root, 'rev-parse', RUNNER_MIRROR_COMPAT_BASE + '^{tree}').decode().strip()
            == RUNNER_MIRROR_COMPAT_BASE_TREE
            and git(root, 'rev-parse', RUNNER_MIRROR_COMPAT_SOURCE + '^{tree}').decode().strip()
            == RUNNER_MIRROR_COMPAT_SOURCE_TREE,
            'Runner mirror compatibility source tree changed')
    git(root, 'merge-base', '--is-ancestor', RUNNER_MIRROR_COMPAT_BASE,
        RUNNER_MIRROR_COMPAT_SOURCE)
    git(root, 'merge-base', '--is-ancestor', RUNNER_MIRROR_COMPAT_SOURCE, 'HEAD')
    require(git(root, 'show', '-s', '--format=%P', RUNNER_MIRROR_COMPAT_SOURCE).decode().strip().split()
            == [RUNNER_MIRROR_COMPAT_BASE],
            'Runner mirror compatibility source parent changed')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_MIRROR_COMPAT_BASE, RUNNER_MIRROR_COMPAT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(RUNNER_MIRROR_COMPAT_MODIFIED)],
            'Runner mirror compatibility changed a different path or status set')
    base_entries = entries(root, RUNNER_MIRROR_COMPAT_BASE)
    source_entries = entries(root, RUNNER_MIRROR_COMPAT_SOURCE)
    for path in sorted(RUNNER_MIRROR_COMPAT_MODIFIED):
        current = project_runner_mirror_phase(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', RUNNER_MIRROR_COMPAT_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', RUNNER_MIRROR_COMPAT_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_mirror_compat(root, path, current) == base
                and project_runner_mirror_compat(root, path, base) == base,
                'Runner mirror compatibility identity or projection changed: ' + path)


def project_runner_mirror_compat(root, path, current):
    """Project the reviewed runner correction to exact resulting-main bytes."""
    current = project_mail_reply_forward_source(root, path, current)
    current = project_domain_expiry_port_source(root, path, current)
    current = project_notification_health_source(root, path, current)
    current = project_runner_application_source_binding(root, path, current)
    current = project_runner_mirror_phase(root, path, current)
    if path not in RUNNER_MIRROR_COMPAT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', RUNNER_MIRROR_COMPAT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', RUNNER_MIRROR_COMPAT_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner mirror compatibility changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_outcome_proof_fix_source(root):
    """Bind the complete ordered payment-outcome proof to the executable fixture body."""
    require(git(root, 'rev-parse', UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE_TREE,
            'UX-08 payment outcome proof source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE,
        UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE,
                  UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_OUTCOME_PROOF_FIX_MODIFIED)],
            'UX-08 payment outcome proof changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_OUTCOME_PROOF_FIX_MODIFIED):
        current = project_runner_mirror_compat(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_outcome_proof_fix(root, path, current) == base
                and project_ux08_payment_outcome_proof_fix(root, path, base) == base,
                'UX-08 payment outcome proof identity or projection changed: ' + path)


def project_ux08_payment_outcome_proof_fix(root, path, current):
    """Restore the accepted fulfillment-lock proof head for older receipts."""
    current = project_runner_mirror_compat(root, path, current)
    if path not in UX08_PAYMENT_OUTCOME_PROOF_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_OUTCOME_PROOF_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_OUTCOME_PROOF_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment outcome proof changed before predecessor projection: ' + path)
    return base


def validate_ux08_hosted_mirror_contract_fix_source(root):
    """Bind the mandatory Application and checkout gates to one hosted mirror contract."""
    require(git(root, 'rev-parse', UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE + '^{tree}').decode().strip()
            == UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE_TREE,
            'UX-08 hosted mirror contract source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE,
        UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE,
                  UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_HOSTED_MIRROR_CONTRACT_FIX_MODIFIED)],
            'UX-08 hosted mirror contract changed a different path or status set')
    base_entries = entries(root, UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE)
    source_entries = entries(root, UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE)
    for path in sorted(UX08_HOSTED_MIRROR_CONTRACT_FIX_MODIFIED):
        current = project_runner_mirror_compat(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_hosted_mirror_contract_fix(root, path, current) == base
                and project_ux08_hosted_mirror_contract_fix(root, path, base) == base,
                'UX-08 hosted mirror contract identity or projection changed: ' + path)


def project_ux08_hosted_mirror_contract_fix(root, path, current):
    """Project the hosted-mirror contract implementation to its accepted base."""
    current = project_runner_mirror_compat(root, path, current)
    if path not in UX08_HOSTED_MIRROR_CONTRACT_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_HOSTED_MIRROR_CONTRACT_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_HOSTED_MIRROR_CONTRACT_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 hosted mirror contract changed before predecessor projection: ' + path)
    return base


def validate_mail_thread_header_fix(root):
    """Bind the long structured-thread correction to its reviewed source."""
    require(git(root, 'rev-parse', MAIL_THREAD_HEADER_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_THREAD_HEADER_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_THREAD_HEADER_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_THREAD_HEADER_FIX_SOURCE_TREE,
            'Mail structured-thread source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_THREAD_HEADER_FIX_SOURCE).decode().split()
            == [MAIL_THREAD_HEADER_FIX_SOURCE, MAIL_THREAD_HEADER_FIX_BASE],
            'Mail structured-thread fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_THREAD_HEADER_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_THREAD_HEADER_FIX_BASE,
                MAIL_THREAD_HEADER_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_THREAD_HEADER_FIX_PATHS)],
            'Mail structured-thread fix changed another path or status')
    base_entries = entries(root, MAIL_THREAD_HEADER_FIX_BASE)
    source_entries = entries(root, MAIL_THREAD_HEADER_FIX_SOURCE)
    for path in sorted(MAIL_THREAD_HEADER_FIX_PATHS):
        current = project_mail_thread_alias_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_thread_header_fix(root, path, current) == base
                and project_mail_thread_header_fix(root, path, base) == base,
                'Mail structured-thread source identity or projection changed: ' + path)
    canonical_mailer = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE
                           + ':foundation/mail-submission/OfflineMailer.php')
    shipping_mailer = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE
                          + ':site/includes/foundation/mail-submission/OfflineMailer.php')
    canonical_prepared = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE
                             + ':foundation/mail-submission/PreparedMime.php')
    shipping_prepared = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE
                            + ':site/includes/foundation/mail-submission/PreparedMime.php')
    mime_test = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE
                    + ':tests/mail-submission/mime.php')
    parser_test = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE
                      + ':tests/mail-submission/verify_mime.py')
    workflow = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE
                   + ':.github/workflows/mail-copied-draft-native.yml')
    contract = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE
                   + ':tests/mail_copied_draft_native_workflow_contract.py')
    require(canonical_mailer == shipping_mailer
            and canonical_prepared == shipping_prepared
            and canonical_mailer.count(b'public function addIdentifierHeader(') == 1
            and canonical_mailer.count(b'private function identifierHeaderLine(') == 1
            and b"MessageThread::fromStored(['in_reply_to'" in canonical_mailer
            and b"MessageThread::fromStored(['in_reply_to'=>null,'references'=>$identifiers])" in canonical_mailer
            and b"strlen($candidate)>77" in canonical_mailer
            and b"strlen($line)>998" in canonical_mailer
            and canonical_prepared.count(b"addIdentifierHeader('In-Reply-To'") == 1
            and canonical_prepared.count(b"addIdentifierHeader('References'") == 1,
            'Mail structured-thread rendering path is not exact or mirrored')
    require(b"strlen(implode(' ',$thread->references))>982" in mime_test
            and b"'name'=>'long-thread'" in mime_test
            and b"str(message['References']).split()==s['thread']['references']" in parser_test
            and b"structured identifiers are not RFC 2047 encoded" in parser_test
            and workflow.count(b"'submission-snapshot': 54, 'submission-mime': 32") == 1
            and contract.count(b"('tests/mail-submission/mime.php', 'submission-mime', '32')") == 1,
            'Mail structured-thread regression or exact matrix count changed')
    require(ordinary(root, INVENTORY) == canonical_inventory(root),
            'Mail structured-thread installer inventory is not canonical')


def project_mail_thread_header_fix(root, path, current):
    """Restore the exact pre-review-thread PR head for older mail proofs."""
    current = project_mail_thread_alias_fix(root, path, current)
    if path not in MAIL_THREAD_HEADER_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_THREAD_HEADER_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail structured-thread fix changed before predecessor projection: ' + path)
    return base


def validate_mail_thread_alias_fix(root):
    """Bind the security-review correction to detached, revalidated values."""
    require(git(root, 'rev-parse', MAIL_THREAD_ALIAS_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_THREAD_ALIAS_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_THREAD_ALIAS_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_THREAD_ALIAS_FIX_SOURCE_TREE,
            'Mail thread-alias security source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_THREAD_ALIAS_FIX_SOURCE).decode().split()
            == [MAIL_THREAD_ALIAS_FIX_SOURCE, MAIL_THREAD_ALIAS_FIX_BASE],
            'Mail thread-alias security fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_THREAD_ALIAS_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_THREAD_ALIAS_FIX_BASE,
                MAIL_THREAD_ALIAS_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_THREAD_ALIAS_FIX_PATHS)],
            'Mail thread-alias security fix changed another path or status')
    base_entries = entries(root, MAIL_THREAD_ALIAS_FIX_BASE)
    source_entries = entries(root, MAIL_THREAD_ALIAS_FIX_SOURCE)
    for path in sorted(MAIL_THREAD_ALIAS_FIX_PATHS):
        current = project_mail_mysql_trigger_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_thread_alias_fix(root, path, current) == base
                and project_mail_thread_alias_fix(root, path, base) == base,
                'Mail thread-alias security source identity or projection changed: ' + path)
    canonical = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_SOURCE
                    + ':foundation/mail-submission/OfflineMailer.php')
    shipping = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_SOURCE
                   + ':site/includes/foundation/mail-submission/OfflineMailer.php')
    regression = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_SOURCE
                     + ':tests/mail-submission/mime.php')
    workflow = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_SOURCE
                   + ':.github/workflows/mail-copied-draft-native.yml')
    contract = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_SOURCE
                   + ':tests/mail_copied_draft_native_workflow_contract.py')
    require(canonical == shipping
            and canonical.count(b'private function validatedIdentifierHeader(') == 1
            and canonical.count(b'$canonical=$this->validatedIdentifierHeader($name,$identifiers);') == 1
            and canonical.count(b'$identifiers=$this->validatedIdentifierHeader($name,$identifiers);') == 1
            and canonical.count(b'$this->identifierHeaders[$name]=$canonical;') == 1
            and b'array_values($identifiers)' not in canonical
            and canonical.count(b"return $name==='In-Reply-To'?[$thread->inReplyTo]:$thread->references;") == 1,
            'Mail thread-alias fix no longer detaches and revalidates exact structured values')
    require(regression.count(b"foreach(['In-Reply-To','References'] as $header)") == 1
            and b'$aliases=[&$identifier]' in regression
            and b'Bcc: injected@example.invalid' in regression
            and b'detaches caller aliases before render' in regression
            and workflow.count(b"'submission-snapshot': 54, 'submission-mime': 34") == 1
            and contract.count(b"('tests/mail-submission/mime.php', 'submission-mime', '34')") == 1,
            'Mail thread-alias adversarial regression or exact matrix count changed')
    require(ordinary(root, INVENTORY) == canonical_inventory(root),
            'Mail thread-alias installer inventory is not canonical')


def project_mail_thread_alias_fix(root, path, current):
    """Restore the security-review-blocked head for older mail proofs."""
    current = project_mail_mysql_trigger_fix(root, path, current)
    if path not in MAIL_THREAD_ALIAS_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_THREAD_ALIAS_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail thread-alias security fix changed before predecessor projection: ' + path)
    return base


def validate_mail_native_fixture_policy_fix(root):
    """Bind the exact fixture correction discovered by all four hosted cells."""
    require(git(root, 'rev-parse', MAIL_NATIVE_FIXTURE_POLICY_BASE + '^{tree}').decode().strip()
            == MAIL_NATIVE_FIXTURE_POLICY_BASE_TREE
            and git(root, 'rev-parse', MAIL_NATIVE_FIXTURE_POLICY_SOURCE + '^{tree}').decode().strip()
            == MAIL_NATIVE_FIXTURE_POLICY_SOURCE_TREE,
            'Mail native fixture-policy source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_NATIVE_FIXTURE_POLICY_SOURCE).decode().split()
            == [MAIL_NATIVE_FIXTURE_POLICY_SOURCE, MAIL_NATIVE_FIXTURE_POLICY_BASE],
            'Mail native fixture-policy fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_NATIVE_FIXTURE_POLICY_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_NATIVE_FIXTURE_POLICY_BASE,
                MAIL_NATIVE_FIXTURE_POLICY_SOURCE).decode().splitlines()
            == ['M\t' + MAIL_NATIVE_FIXTURE_POLICY_PATH],
            'Mail native fixture-policy fix changed another path or status')
    path = MAIL_NATIVE_FIXTURE_POLICY_PATH
    current = project_mail_consolidated_source(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', MAIL_NATIVE_FIXTURE_POLICY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_NATIVE_FIXTURE_POLICY_BASE + ':' + path)
    base_entries = entries(root, MAIL_NATIVE_FIXTURE_POLICY_BASE)
    source_entries = entries(root, MAIL_NATIVE_FIXTURE_POLICY_SOURCE)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_mail_native_fixture_policy_fix(root, path, current) == base
            and project_mail_native_fixture_policy_fix(root, path, base) == base,
            'Mail native fixture-policy source identity or projection changed')
    require(source.count(b"'imap_host'=>'imap.example.com'") == 1
            and source.count(b"'smtp_host'=>'smtp.example.com'") == 1
            and source.count(b"$host!=='imap.example.com'") == 1
            and source.count(b'function cdc_resolver(string $host):array') == 1
            and source.count(b'function cdc_transport(array $row,') == 1
            and b"'cdc_resolver','cdc_transport'" in source,
            'Mail native fixture no longer uses its exact injected public-host boundary')


def project_mail_native_fixture_policy_fix(root, path, current):
    """Restore the pre-hosted-assertion fixture for older mail proofs."""
    current = project_mail_consolidated_source(root, path, current)
    if path != MAIL_NATIVE_FIXTURE_POLICY_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_NATIVE_FIXTURE_POLICY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_NATIVE_FIXTURE_POLICY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail native fixture changed before predecessor projection')
    return base


def validate_mail_reply_session_fix(root):
    """Bind the hosted browser correction to the accepted no-reentry policy."""
    require(git(root, 'rev-parse', MAIL_REPLY_SESSION_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_REPLY_SESSION_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_REPLY_SESSION_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_REPLY_SESSION_FIX_SOURCE_TREE,
            'Mail reply session-policy source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_REPLY_SESSION_FIX_SOURCE).decode().split()
            == [MAIL_REPLY_SESSION_FIX_SOURCE, MAIL_REPLY_SESSION_FIX_BASE],
            'Mail reply session-policy fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_REPLY_SESSION_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_REPLY_SESSION_FIX_BASE,
                MAIL_REPLY_SESSION_FIX_SOURCE).decode().splitlines()
            == ['M\t' + MAIL_REPLY_SESSION_FIX_PATH],
            'Mail reply session-policy fix changed another path or status')
    path = MAIL_REPLY_SESSION_FIX_PATH
    current = project_mail_reply_form_helper_fix(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', MAIL_REPLY_SESSION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_REPLY_SESSION_FIX_BASE + ':' + path)
    base_entries = entries(root, MAIL_REPLY_SESSION_FIX_BASE)
    source_entries = entries(root, MAIL_REPLY_SESSION_FIX_SOURCE)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_mail_reply_session_fix(root, path, current) == base
            and project_mail_reply_session_fix(root, path, base) == base,
            'Mail reply session-policy source identity or projection changed')
    require(source.count(b"check('Reply storage setup rejects invalid CSRF'") == 1
            and source.count(b"check('Reply storage setup refuses a revoked current owner session'") == 1
            and source.count(b"db('UPDATE ci_users SET auth_version=? WHERE id=?', [uuid.uuid4().hex, oid])") == 1
            and source.count(b"submit(owner, root + '?view=login', 'login'") == 2
            and source.count(b"submit(owner, drafts_url, 'mail_drafts_replies_setup')") == 1
            and b"'current_password': 'wrong'" not in source,
            'Mail reply browser fixture no longer exercises CSRF and revoked-session authority')


def project_mail_reply_session_fix(root, path, current):
    """Restore the pre-browser-failure fixture for older mail source proofs."""
    current = project_mail_reply_form_helper_fix(root, path, current)
    if path != MAIL_REPLY_SESSION_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_REPLY_SESSION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_REPLY_SESSION_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail reply session fixture changed before predecessor projection')
    return base


def validate_mail_mysql_trigger_fix(root):
    """Bind the isolated MySQL fixture permission discovered by hosted acceptance."""
    require(git(root, 'rev-parse', MAIL_MYSQL_TRIGGER_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_MYSQL_TRIGGER_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_MYSQL_TRIGGER_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_MYSQL_TRIGGER_FIX_SOURCE_TREE,
            'Mail MySQL trigger-fixture source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_MYSQL_TRIGGER_FIX_SOURCE).decode().split()
            == [MAIL_MYSQL_TRIGGER_FIX_SOURCE, MAIL_MYSQL_TRIGGER_FIX_BASE],
            'Mail MySQL trigger-fixture fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_MYSQL_TRIGGER_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_MYSQL_TRIGGER_FIX_BASE,
                MAIL_MYSQL_TRIGGER_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_MYSQL_TRIGGER_FIX_PATHS)],
            'Mail MySQL trigger-fixture fix changed another path or status')
    base_entries = entries(root, MAIL_MYSQL_TRIGGER_FIX_BASE)
    source_entries = entries(root, MAIL_MYSQL_TRIGGER_FIX_SOURCE)
    for path in sorted(MAIL_MYSQL_TRIGGER_FIX_PATHS):
        current = project_mail_copy_controls_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_MYSQL_TRIGGER_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_MYSQL_TRIGGER_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_mysql_trigger_fix(root, path, current) == base
                and project_mail_mysql_trigger_fix(root, path, base) == base,
                'Mail MySQL trigger-fixture source identity or projection changed: ' + path)
    workflow = git(root, 'cat-file', 'blob', MAIL_MYSQL_TRIGGER_FIX_SOURCE
                   + ':.github/workflows/mail-copied-draft-native.yml')
    contract = git(root, 'cat-file', 'blob', MAIL_MYSQL_TRIGGER_FIX_SOURCE
                   + ':tests/mail_copied_draft_native_workflow_contract.py')
    step = b'- name: Allow isolated MySQL audit-failure fixture triggers'
    condition = b"if: matrix.database == 'mysql'"
    command = (b'docker exec -e MYSQL_PWD=synthetic-root-only '
               b'${{ job.services.database.id }} mysql -uroot -e '
               b"'SET GLOBAL log_bin_trust_function_creators=1'")
    require(workflow.count(step) == workflow.count(condition) == workflow.count(command) == 1
            and workflow.index(b'- name: Prove the synthetic database is ready')
            < workflow.index(step) < workflow.index(b'- name: Run isolated copied-draft SQL acceptance')
            and contract.count(b'Only isolated MySQL cells grant their synthetic fixture triggers') == 1
            and contract.count(b"('lost isolated MySQL trigger admission',") == 1,
            'Mail MySQL trigger-fixture permission is missing, broadened or unguarded')


def project_mail_mysql_trigger_fix(root, path, current):
    """Restore the accepted predecessor before the isolated MySQL fixture permission."""
    current = project_mail_copy_controls_fix(root, path, current)
    if path not in MAIL_MYSQL_TRIGGER_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_MYSQL_TRIGGER_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_MYSQL_TRIGGER_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail MySQL trigger-fixture fix changed before predecessor projection: ' + path)
    return base


def validate_mail_reply_form_helper_fix(root):
    """Bind the missing packaged-browser form helper discovered by hosted acceptance."""
    require(git(root, 'rev-parse', MAIL_REPLY_FORM_HELPER_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_REPLY_FORM_HELPER_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_REPLY_FORM_HELPER_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_REPLY_FORM_HELPER_FIX_SOURCE_TREE,
            'Mail reply form-helper source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_REPLY_FORM_HELPER_FIX_SOURCE).decode().split()
            == [MAIL_REPLY_FORM_HELPER_FIX_SOURCE, MAIL_REPLY_FORM_HELPER_FIX_BASE],
            'Mail reply form-helper fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_REPLY_FORM_HELPER_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_REPLY_FORM_HELPER_FIX_BASE,
                MAIL_REPLY_FORM_HELPER_FIX_SOURCE).decode().splitlines()
            == ['M\t' + MAIL_REPLY_FORM_HELPER_FIX_PATH],
            'Mail reply form-helper fix changed another path or status')
    path = MAIL_REPLY_FORM_HELPER_FIX_PATH
    current = project_mail_consolidated_source(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', MAIL_REPLY_FORM_HELPER_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_REPLY_FORM_HELPER_FIX_BASE + ':' + path)
    base_entries = entries(root, MAIL_REPLY_FORM_HELPER_FIX_BASE)
    source_entries = entries(root, MAIL_REPLY_FORM_HELPER_FIX_SOURCE)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_mail_reply_form_helper_fix(root, path, current) == base
            and project_mail_reply_form_helper_fix(root, path, base) == base,
            'Mail reply form-helper source identity or projection changed')
    require(source.count(b"'form_data', 'form_for', 'submit'") == 1
            and base.count(b"'form_data', 'form_for', 'submit'") == 0
            and source.count(b"form = form_for(setup, 'mail_drafts_replies_setup')") == 1,
            'Mail reply packaged fixture does not import the exact helper it invokes')


def project_mail_reply_form_helper_fix(root, path, current):
    """Restore the accepted predecessor before the packaged-browser helper binding."""
    current = project_mail_consolidated_source(root, path, current)
    if path != MAIL_REPLY_FORM_HELPER_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_REPLY_FORM_HELPER_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_REPLY_FORM_HELPER_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail reply form-helper fix changed before predecessor projection')
    return base


def mail_consolidated_changes(root):
    """Return the exact status/path map for the pinned consolidated mail source."""
    key=root_key(root)
    if key in _MAIL_CONSOLIDATED_CHANGES:
        return _MAIL_CONSOLIDATED_CHANGES[key]
    raw=git(root, 'diff', '--name-status', '--no-renames',
            MAIL_CONSOLIDATED_BASE, MAIL_CONSOLIDATED_SOURCE)
    require(hashlib.sha256(raw).hexdigest() == MAIL_CONSOLIDATED_DIFF_SHA256,
            'Consolidated mail source path/status digest changed')
    rows=raw.decode().splitlines()
    require(len(rows) == MAIL_CONSOLIDATED_CHANGE_COUNT,
            'Consolidated mail source path count changed')
    changes={}
    for row in rows:
        status,path=row.split('\t',1)
        require(status in {'A','M'} and path not in changes,
                'Consolidated mail source has an unsupported or duplicate path')
        changes[path]=status
    _MAIL_CONSOLIDATED_CHANGES[key]=changes
    return changes


def validate_mail_consolidated_review_recovery(root):
    """Bind the stale-marker invalidation required by independent review."""
    require(git(root, 'rev-parse', MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE + '^{tree}').decode().strip()
            == MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE_TREE
            and git(root, 'rev-parse', MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE + '^{tree}').decode().strip()
            == MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE_TREE,
            'Consolidated mail review-recovery source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE).decode().split()
            == [MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE,
                MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE],
            'Consolidated mail review recovery is not the direct proof successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE,
                MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_CONSOLIDATED_REVIEW_RECOVERY_PATHS)],
            'Consolidated mail review recovery changed another path or status')
    base_entries = entries(root, MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE)
    source_entries = entries(root, MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE)
    for path in sorted(MAIL_CONSOLIDATED_REVIEW_RECOVERY_PATHS):
        current = project_mail_sent_copy_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_consolidated_review_recovery(root, path, current) == base
                and project_mail_consolidated_review_recovery(root, path, base) == base,
                'Consolidated mail review-recovery identity or projection changed: ' + path)
    migration = ordinary(root, 'site/database/migrations/mail_drafts_migrate.php')
    native = ordinary(root, 'tests/mail_drafts_native.php')
    require(migration.count(b"query('DELETE FROM '.table('settings').' WHERE name=?',['mail_drafts_html_ready'])") == 1
            and migration.index(b"'html_repair_pending'")
            < migration.index(b'ADD COLUMN body_html MEDIUMTEXT NULL')
            and native.count(b"NEW.action='mail_drafts.setup'") == 1
            and native.count(b'Post-ALTER setup audit failure cannot revive a stale readiness marker') == 1,
            'Consolidated mail stale-marker recovery boundary changed')


def project_mail_consolidated_review_recovery(root, path, current):
    """Restore the first review proof before its stale-marker correction."""
    current = project_mail_sent_copy_source(root, path, current)
    if path not in MAIL_CONSOLIDATED_REVIEW_RECOVERY_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_REVIEW_RECOVERY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_REVIEW_RECOVERY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current consolidated mail review recovery changed before predecessor projection: ' + path)
    return base


def validate_mail_sent_copy_source(root):
    """Bind the explicit, uncertainty-safe server Sent-copy implementation."""
    require(git(root, 'rev-parse', MAIL_SENT_COPY_BASE + '^{tree}').decode().strip()
            == MAIL_SENT_COPY_BASE_TREE
            and git(root, 'rev-parse', MAIL_SENT_COPY_SOURCE + '^{tree}').decode().strip()
            == MAIL_SENT_COPY_SOURCE_TREE,
            'Sent-copy source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_SENT_COPY_SOURCE).decode().split()
            == [MAIL_SENT_COPY_SOURCE, MAIL_SENT_COPY_BASE],
            'Sent-copy source is not the direct consolidated-mail successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_SENT_COPY_SOURCE, 'HEAD')
    expected = [('A' if path in MAIL_SENT_COPY_ADDITIONS else 'M') + '\t' + path
                for path in sorted(MAIL_SENT_COPY_MODIFIED | MAIL_SENT_COPY_ADDITIONS)]
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_SENT_COPY_BASE, MAIL_SENT_COPY_SOURCE).decode().splitlines() == expected,
            'Sent-copy source changed another path or status')
    base_entries = entries(root, MAIL_SENT_COPY_BASE)
    source_entries = entries(root, MAIL_SENT_COPY_SOURCE)
    for path in sorted(MAIL_SENT_COPY_MODIFIED):
        current = project_mail_sent_copy_origin_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_sent_copy_source(root, path, current) == base
                and project_mail_sent_copy_source(root, path, base) == base,
                'Sent-copy modified source identity or projection changed: ' + path)
    for path in sorted(MAIL_SENT_COPY_ADDITIONS):
        current = project_mail_sent_copy_origin_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_SOURCE + ':' + path)
        require(path not in base_entries and current == source
                and mode(root, path) == source_entries[path][0] == '100644',
                'Sent-copy addition identity or mode changed: ' + path)
    canonical = ordinary(root, 'foundation/mail-receiving/SentCopy.php')
    shipped = ordinary(root, 'site/includes/foundation/mail-receiving/SentCopy.php')
    adapter = ordinary(root, 'site/app/mail/copies.php')
    migration = ordinary(root, 'site/database/migrations/mail_copies_migrate.php')
    native = ordinary(root, 'tests/mail_submissions_native.php')
    browser = ordinary(root, 'tests/mail_submissions_cases.py')
    require(canonical == shipped
            and canonical.count(b'ReadProfile::Append') == 1
            and adapter.count(b"'uncertain'=>'unknown'") == 1
            and adapter.count(b"r.id IS NULL OR r.result_code IN (?,?)") == 1
            and adapter.count(b"if(!$receipt instanceof AppendReceipt||!$receipt->accepted)") == 1
            and migration.count(b"Unrecognized Sent-copy records exist. No data was adopted.") == 1
            and migration.count(b"mail_accounts_owner('')") == 1
            and native.count(b'Uncertain Sent copy can never be replayed') == 1
            and native.count(b'Result audit failure leaves an unresolved durable attempt') == 1
            and browser.count(b'Acceptance never appends a Sent copy silently') == 1
            and browser.count(b'HTTP copy appends the exact accepted MIME once') == 1
            and ordinary(root, 'site/database/schema/base.php').count(b'mail_copies_schema($prefix)') == 1
            and ordinary(root, 'site/install/actions/deploy.php').count(b"save_setting('mail_copies_schema_version','1')") == 1
            and ordinary(root, 'site/admin/controllers/mail_submissions.php').count(b"($_POST['confirm_copy']??null)!=='1'") == 1
            and ordinary(root, INVENTORY) == canonical_inventory(root),
            'Sent-copy authority, uncertainty, fresh-install, browser or inventory boundary changed')


def project_mail_sent_copy_source(root, path, current):
    """Restore the reviewed formatted-mail proof head before Sent-copy source."""
    current = project_mail_sent_copy_origin_fix(root, path, current)
    if path not in MAIL_SENT_COPY_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Sent-copy source changed before predecessor projection: ' + path)
    return base


def validate_mail_attachment_portable_source(root):
    """Bind the independent formats 1..8 attachment MIME source."""
    require(git(root, 'rev-parse', MAIL_ATTACHMENT_PORTABLE_BASE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_PORTABLE_BASE_TREE
            and git(root, 'rev-parse', MAIL_ATTACHMENT_PORTABLE_SOURCE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_PORTABLE_SOURCE_TREE,
            'Portable attachment source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ATTACHMENT_PORTABLE_SOURCE).decode().split()
            == [MAIL_ATTACHMENT_PORTABLE_SOURCE, MAIL_ATTACHMENT_PORTABLE_BASE],
            'Portable attachment source is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ATTACHMENT_PORTABLE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ATTACHMENT_PORTABLE_BASE,
                MAIL_ATTACHMENT_PORTABLE_SOURCE).decode().splitlines()
            == ['A\t' + path for path in sorted(MAIL_ATTACHMENT_PORTABLE_ADDITIONS)],
            'Portable attachment source changed another path or status')
    base_entries = entries(root, MAIL_ATTACHMENT_PORTABLE_BASE)
    source_entries = entries(root, MAIL_ATTACHMENT_PORTABLE_SOURCE)
    for path in sorted(MAIL_ATTACHMENT_PORTABLE_ADDITIONS):
        source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_PORTABLE_SOURCE + ':' + path)
        require(path not in base_entries and ordinary(root, path) == source
                and mode(root, path) == source_entries[path][0] == '100644',
                'Portable attachment addition identity or mode changed: ' + path)
    fixture = ordinary(root, 'tests/mail-submission/attachments.php')
    parser = ordinary(root, 'tests/mail-submission/verify_attachments.py')
    require(fixture.count(b'for($format=1;$format<=8;$format++)') == 1
            and fixture.count(b'DraftAttachment::MAX_FILES') >= 1
            and parser.count(b'email.message_from_bytes') == 1
            and parser.count(b'get_content_disposition() == "attachment"') == 1,
            'Portable attachment format, bound or independent-parser coverage changed')


def validate_mail_reply_all_replay_source(root):
    """Bind exact existing Reply All replay through native SQL and HTTP."""
    require(git(root, 'rev-parse', MAIL_REPLY_ALL_REPLAY_BASE + '^{tree}').decode().strip()
            == MAIL_REPLY_ALL_REPLAY_BASE_TREE
            and git(root, 'rev-parse', MAIL_REPLY_ALL_REPLAY_SOURCE + '^{tree}').decode().strip()
            == MAIL_REPLY_ALL_REPLAY_SOURCE_TREE,
            'Reply All replay source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1', MAIL_REPLY_ALL_REPLAY_SOURCE).decode().split()
            == [MAIL_REPLY_ALL_REPLAY_SOURCE, MAIL_REPLY_ALL_REPLAY_BASE],
            'Reply All replay source is not the direct reviewed proof-head successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_REPLY_ALL_REPLAY_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_REPLY_ALL_REPLAY_BASE,
                MAIL_REPLY_ALL_REPLAY_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_REPLY_ALL_REPLAY_PATHS)],
            'Reply All replay source changed another path or status')
    base_entries = entries(root, MAIL_REPLY_ALL_REPLAY_BASE)
    source_entries = entries(root, MAIL_REPLY_ALL_REPLAY_SOURCE)
    for path in sorted(MAIL_REPLY_ALL_REPLAY_PATHS):
        current = project_application_reply_folder_route(root, path, ordinary(root, path))
        current = project_latest_acceptance_successors(root, path, current)
        current = project_mail_acceptance_repair_source(root, path, current)
        source = git(root, 'cat-file', 'blob', MAIL_REPLY_ALL_REPLAY_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_REPLY_ALL_REPLAY_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_reply_all_replay(root, path, current) == base
                and project_mail_reply_all_replay(root, path, base) == base,
                'Reply All replay identity or projection changed: ' + path)
    native = ordinary(root, 'tests/mail_copied_draft_create_native.php')
    browser = ordinary(root, 'tests/mail_reply_cases.py')
    require(native.count(b'Exact reply all replay returns the existing draft before network or mutation') == 1
            and native.count(b"$replyAllReplay===['state'=>'existing','draft_id'=>$replyAll,'result'=>null]") == 1
            and native.count(b'[$resolveCalls,$transportCalls]===$replyAllCalls') == 1
            and native.count(b'cdc_tables()===$replyAllBefore') == 1
            and browser.count(b'Exact reply all replay reopens the existing draft without another fetch or write') == 1
            and browser.count(b"'action': 'mail_message_reply_all'") == 2
            and browser.count(b"'existing copied-message draft' in r.text.lower()") == 1
            and browser.count(b'inbox_server.connections == before + 2') >= 2
            and browser.count(b"db('SELECT * FROM ci_mail_message_reads ORDER BY id')") == 2
            and browser.count(b"db('SELECT * FROM ci_mail_message_results ORDER BY id')") == 2
            and browser.count(b"db('SELECT * FROM ci_mail_drafts ORDER BY id')") == 2
            and browser.count(b"db('SELECT * FROM ci_mail_reply_drafts ORDER BY id')") == 2
            and browser.count(b"db('SELECT * FROM ci_mail_draft_revisions ORDER BY draft_id,revision')") == 2
            and browser.count(b"db('SELECT * FROM ci_audit ORDER BY id')") == 2,
            'Reply All replay native, controller, no-network or no-write assertion changed')


def project_mail_reply_all_replay(root, path, current):
    """Restore the first consolidated-mail proof head before replay coverage."""
    current = project_application_reply_folder_route(root, path, current)
    current = project_mail_acceptance_portability(root, path, current)
    if path not in MAIL_REPLY_ALL_REPLAY_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_REPLY_ALL_REPLAY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_REPLY_ALL_REPLAY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Reply All replay source changed before predecessor projection: ' + path)
    return base


def validate_mail_acceptance_portability_source(root):
    """Bind the exact product and proof correction discovered by first-attempt CI."""
    require(git(root, 'rev-parse', MAIL_ACCEPTANCE_PORTABILITY_BASE + '^{tree}').decode().strip()
            == MAIL_ACCEPTANCE_PORTABILITY_BASE_TREE
            and git(root, 'rev-parse', MAIL_ACCEPTANCE_PORTABILITY_SOURCE + '^{tree}').decode().strip()
            == MAIL_ACCEPTANCE_PORTABILITY_SOURCE_TREE,
            'Mail acceptance portability source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ACCEPTANCE_PORTABILITY_SOURCE).decode().split()
            == [MAIL_ACCEPTANCE_PORTABILITY_SOURCE, MAIL_ACCEPTANCE_PORTABILITY_BASE],
            'Mail acceptance portability source is not the direct proof-head successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ACCEPTANCE_PORTABILITY_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ACCEPTANCE_PORTABILITY_BASE,
                MAIL_ACCEPTANCE_PORTABILITY_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_ACCEPTANCE_PORTABILITY_PATHS)],
            'Mail acceptance portability source changed another path or status')
    base_entries = entries(root, MAIL_ACCEPTANCE_PORTABILITY_BASE)
    source_entries = entries(root, MAIL_ACCEPTANCE_PORTABILITY_SOURCE)
    for path in sorted(MAIL_ACCEPTANCE_PORTABILITY_PATHS):
        current = project_application_fresh_draft_readiness_source(root, path, ordinary(root, path))
        current = project_application_fixture_successors(root, path, current)
        current = project_mail_admission_evidence(root, path, current)
        source = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_PORTABILITY_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_PORTABILITY_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_acceptance_portability(root, path, current) == base
                and project_mail_acceptance_portability(root, path, base) == base,
                'Mail acceptance portability identity or projection changed: ' + path)
    flags = project_mail_admission_evidence(
        root, '.github/workflows/mail-flags-native.yml',
        ordinary(root, '.github/workflows/mail-flags-native.yml'))
    folder = project_mail_admission_evidence(
        root, '.github/workflows/mail-folder-native.yml',
        ordinary(root, '.github/workflows/mail-folder-native.yml'))
    receiving = ordinary(root, '.github/workflows/mail-receiving.yml')
    flags_contract = project_mail_admission_evidence(
        root, 'tests/mail_flags_native_workflow_contract.py',
        ordinary(root, 'tests/mail_flags_native_workflow_contract.py'))
    folder_contract = project_mail_admission_evidence(
        root, 'tests/mail_folder_native_workflow_contract.py',
        ordinary(root, 'tests/mail_folder_native_workflow_contract.py'))
    require(flags.count(b'sudo python3 -I .github/scripts/prepare_ubuntu_apt.py prepare-ubuntu') == 2
            and folder.count(b'sudo python3 -I .github/scripts/prepare_ubuntu_apt.py prepare-ubuntu') == 2
            and flags.count(b'ubuntu-apt-source.json qa/ubuntu-apt-source-preflight.json') == 2
            and folder.count(b'ubuntu-apt-source.json qa/ubuntu-apt-source-preflight.json') == 2
            and flags.count(b'flags="-n -d extension=ctype"') == 1
            and folder.count(b'php_flags="-n -d extension=mbstring -d extension=ctype"') == 1
            and receiving.count(b'php -n -d extension=ctype tests/mail-receiving/append_test.php') == 1
            and flags_contract.count(b"'lost explicit no-INI ctype'") == 1
            and folder_contract.count(b"'lost no-INI ctype'") == 1
            and folder.count(b"'copied-source': 19") == 1
            and folder_contract.count(b"'changed copied-source report count'") == 1,
            'Signed runner admission, exact count or no-INI mail coverage changed')
    parser = ordinary(root, 'foundation/mail-receiving/BodyHtml.php')
    compose = ordinary(root, 'foundation/mail-compose/ComposeHtml.php')
    require(parser.count(b'public function __construct(bool $portable=false)') == 1
            and parser.count(b'$portable || PHP_VERSION_ID<80400 ? new MastermindsParser() : new NativeParser()') == 1
            and compose.count(b'new BodyHtmlParser(true)') == 1
            and parser == ordinary(root, 'site/includes/foundation/mail-receiving/BodyHtml.php')
            and compose == ordinary(root, 'site/includes/foundation/mail-compose/ComposeHtml.php')
            and ordinary(root, 'tests/mail-compose/html.php').count(
                b'Accepted canonical output is an exact portable fixed point') == 1,
            'Portable outbound HTML fixed point or deployed mirror changed')
    validator = ordinary(root, 'site/database/schema/catalog_validate_schema.php')
    mime_parser = ordinary(root, 'tests/mail-submission/verify_mime.py')
    require(validator.count(b'MEDIUMTEXT|LONGTEXT|TEXT|MEDIUMBLOB') == 1
            and mime_parser.count(b"re.findall(rb'(?m)^Message-ID:") == 1
            and mime_parser.count(b"re.sub(rb'\\r\\n[ \\t]+'") == 1
            and mime_parser.count(b"message['Message-ID'] is not None") == 1,
            'Catalog type or independent exact wire Message-ID proof changed')
    require(ordinary(root, 'tests/mail_drafts_native.php').count(
                b"['mail_drafts','mail_draft_revisions','mail_draft_files']") == 1
            and ordinary(root, 'tests/mail_accounts_native.php').count(
                b'mail_accounts_grant($id,2,2,false,false,false,0,$password)') == 1
            and ordinary(root, 'tests/mail_inbox_native.php').count(b'$read||$send,0,$password') == 1
            and ordinary(root, 'tests/mail_inbox_native.php').count(
                b"mail_accounts_grant($interleave,1,(int)$g['revision'],false,false,false") == 1
            and ordinary(root, 'tests/mail_message_native.php').count(b'$read||$send,0,$password') == 1
            and ordinary(root, 'tests/mail_message_native.php').count(
                b"mail_accounts_grant($interleave,1,(int)$g['revision'],false,false,false") == 1,
            'Schema fixture or disabled mailbox-grant coverage changed')


def project_mail_acceptance_portability(root, path, current):
    """Restore the reviewed proof head before first-attempt mail corrections."""
    current = project_application_fresh_draft_readiness_source(root, path, current)
    current = project_application_fixture_successors(root, path, current)
    current = project_mail_admission_evidence(root, path, current)
    if path not in MAIL_ACCEPTANCE_PORTABILITY_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_PORTABILITY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_PORTABILITY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail acceptance portability source changed before predecessor projection: ' + path)
    return base


def validate_mail_admission_evidence_source(root):
    """Bind structured runner-admission diagnostics on both success and failure."""
    require(git(root, 'rev-parse', MAIL_ADMISSION_EVIDENCE_BASE + '^{tree}').decode().strip()
            == MAIL_ADMISSION_EVIDENCE_BASE_TREE
            and git(root, 'rev-parse', MAIL_ADMISSION_EVIDENCE_SOURCE + '^{tree}').decode().strip()
            == MAIL_ADMISSION_EVIDENCE_SOURCE_TREE,
            'Mail admission evidence source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ADMISSION_EVIDENCE_SOURCE).decode().split()
            == [MAIL_ADMISSION_EVIDENCE_SOURCE, MAIL_ADMISSION_EVIDENCE_BASE],
            'Mail admission evidence source is not the direct reviewed proof successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ADMISSION_EVIDENCE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ADMISSION_EVIDENCE_BASE,
                MAIL_ADMISSION_EVIDENCE_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_ADMISSION_EVIDENCE_PATHS)],
            'Mail admission evidence source changed another path or status')
    base_entries = entries(root, MAIL_ADMISSION_EVIDENCE_BASE)
    source_entries = entries(root, MAIL_ADMISSION_EVIDENCE_SOURCE)
    for path in sorted(MAIL_ADMISSION_EVIDENCE_PATHS):
        current = project_mail_acceptance_repair_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_ADMISSION_EVIDENCE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_ADMISSION_EVIDENCE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_admission_evidence(root, path, current) == base
                and project_mail_admission_evidence(root, path, base) == base,
                'Mail admission evidence identity or projection changed: ' + path)
    flags = ordinary(root, '.github/workflows/mail-flags-native.yml')
    folder = ordinary(root, '.github/workflows/mail-folder-native.yml')
    flags_contract = ordinary(root, 'tests/mail_flags_native_workflow_contract.py')
    folder_contract = ordinary(root, 'tests/mail_folder_native_workflow_contract.py')
    for name, workflow, contract in (
            ('flags', flags, flags_contract), ('folder', folder, folder_contract)):
        require(workflow.count(b'prepare-ubuntu >') == 2
                and workflow.count(b'|| status=$?') >= 2
                and workflow.count(b'sudo test -f qa/ubuntu-apt-source-preflight.json') == 2
                and workflow.count(b'sudo mv qa/ubuntu-apt-source-preflight.json') == 2
                and workflow.count(b'if [ -f qa/ubuntu-apt-source.json ]; then sudo mv qa/ubuntu-apt-source.json') == 2
                and contract.count(b"'lost runner failure receipt'") == 1,
                'Mail ' + name + ' admission failure evidence retention changed')


def project_mail_admission_evidence(root, path, current):
    """Restore the first reviewed portability proof before evidence retention."""
    current = project_mail_acceptance_repair_source(root, path, current)
    if path not in MAIL_ADMISSION_EVIDENCE_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ADMISSION_EVIDENCE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ADMISSION_EVIDENCE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail admission evidence source changed before predecessor projection: ' + path)
    return base


def validate_mail_attachment_claim_fix(root):
    """Bind the current-intent causal claim regression for protected readiness."""
    require(git(root, 'rev-parse', MAIL_ATTACHMENT_CLAIM_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_CLAIM_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_ATTACHMENT_CLAIM_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_CLAIM_FIX_SOURCE_TREE,
            'Attachment causal-claim fix tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1', MAIL_ATTACHMENT_CLAIM_FIX_SOURCE).decode().split()
            == [MAIL_ATTACHMENT_CLAIM_FIX_SOURCE, MAIL_ATTACHMENT_CLAIM_FIX_BASE],
            'Attachment causal-claim fix is not the direct submission-fix successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ATTACHMENT_CLAIM_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ATTACHMENT_CLAIM_FIX_BASE,
                MAIL_ATTACHMENT_CLAIM_FIX_SOURCE).decode().splitlines()
            == ['M\t' + MAIL_ATTACHMENT_CLAIM_FIX_PATH],
            'Attachment causal-claim fix changed another path or status')
    path = MAIL_ATTACHMENT_CLAIM_FIX_PATH
    current = project_mail_list_identity(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_CLAIM_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_CLAIM_FIX_BASE + ':' + path)
    require(current == source and mode(root, path) == '100644'
            and project_mail_attachment_claim_fix(root, path, current) == base
            and project_mail_attachment_claim_fix(root, path, base) == base
            and current.count(b'$fileClaimIntent=$fileIntent;') == 1
            and current.count(b'$fileClaimIntent=$fileRichIntent;') == 1
            and current.count(b'mail_submissions_claim($fileClaimIntent)') == 1,
            'Attachment causal-claim identity, projection or current-intent boundary changed')


def project_mail_attachment_claim_fix(root, path, current):
    """Restore the submission-readiness source before its causal test correction."""
    current = project_mail_list_identity(root, path, current)
    current = project_mail_submission_expiry_fix(root, path, current)
    current = project_mail_reply_all_replay(root, path, current)
    if path != MAIL_ATTACHMENT_CLAIM_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_CLAIM_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_CLAIM_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current attachment causal-claim fix changed before predecessor projection: ' + path)
    return base


def validate_mail_submission_expiry_fix(root):
    """Bind the native expired-grant fixture to the canonical epoch schema."""
    require(git(root, 'rev-parse', MAIL_SUBMISSION_EXPIRY_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_SUBMISSION_EXPIRY_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_SUBMISSION_EXPIRY_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_SUBMISSION_EXPIRY_FIX_SOURCE_TREE,
            'Mail submission expiry-fixture source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_SUBMISSION_EXPIRY_FIX_SOURCE).decode().split()
            == [MAIL_SUBMISSION_EXPIRY_FIX_SOURCE, MAIL_SUBMISSION_EXPIRY_FIX_BASE],
            'Mail submission expiry fixture is not the direct failed-run successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_SUBMISSION_EXPIRY_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_SUBMISSION_EXPIRY_FIX_BASE,
                MAIL_SUBMISSION_EXPIRY_FIX_SOURCE).decode().splitlines()
            == ['M\t' + MAIL_SUBMISSION_EXPIRY_FIX_PATH],
            'Mail submission expiry fixture changed another path or status')
    base_entries = entries(root, MAIL_SUBMISSION_EXPIRY_FIX_BASE)
    source_entries = entries(root, MAIL_SUBMISSION_EXPIRY_FIX_SOURCE)
    current = ordinary(root, MAIL_SUBMISSION_EXPIRY_FIX_PATH)
    source = git(root, 'cat-file', 'blob',
                 MAIL_SUBMISSION_EXPIRY_FIX_SOURCE + ':' + MAIL_SUBMISSION_EXPIRY_FIX_PATH)
    base = git(root, 'cat-file', 'blob',
               MAIL_SUBMISSION_EXPIRY_FIX_BASE + ':' + MAIL_SUBMISSION_EXPIRY_FIX_PATH)
    require(current == source
            and mode(root, MAIL_SUBMISSION_EXPIRY_FIX_PATH)
            == base_entries[MAIL_SUBMISSION_EXPIRY_FIX_PATH][0]
            == source_entries[MAIL_SUBMISSION_EXPIRY_FIX_PATH][0] == '100644'
            and project_mail_submission_expiry_fix(
                root, MAIL_SUBMISSION_EXPIRY_FIX_PATH, current) == base
            and project_mail_submission_expiry_fix(
                root, MAIL_SUBMISSION_EXPIRY_FIX_PATH, base) == base,
            'Mail submission expiry-fixture identity, mode or projection changed')
    replacement = (b"elseif($mode==='expiry')query('UPDATE '.table('mail_account_grants')"
                   b".' SET expires_at=? WHERE account_id=? AND user_id=2',[1,$a]);")
    legacy = (b"elseif($mode==='expiry')query(\"UPDATE \".table('mail_account_grants')"
              b".\" SET expires_at='2000-01-01 00:00:00' WHERE account_id=? AND user_id=2\",[$a]);")
    schema = ordinary(root, 'site/database/schema/mail_accounts_schema.php')
    require(current.count(replacement) == 1
            and legacy not in current
            and current.count(b"foreach(['send','grant','expiry','account'] as $mode)") == 1
            and current.count(b"su_deny('Current '.$mode.' authority refuses attachment metadata'") == 1
            and current.count(b"su_deny('Current '.$mode.' authority refuses attachment download'") == 1
            and schema.count(b'mail_account_grants') >= 1
            and schema.count(b'expires_at BIGINT UNSIGNED NULL') >= 1,
            'Mail submission expiry fixture or canonical epoch-schema boundary changed')


def project_mail_submission_expiry_fix(root, path, current):
    """Restore the accepted hosted source before the strict-DB fixture correction."""
    if path != MAIL_SUBMISSION_EXPIRY_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_SUBMISSION_EXPIRY_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_SUBMISSION_EXPIRY_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail submission expiry fixture changed before predecessor projection')
    return base


def validate_mail_list_identity(root):
    """Bind exact-list identity and retained folder-report count after preserved runs."""
    require(git(root, 'rev-parse', MAIL_LIST_IDENTITY_BASE + '^{tree}').decode().strip()
            == MAIL_LIST_IDENTITY_BASE_TREE
            and git(root, 'rev-parse', MAIL_LIST_IDENTITY_SOURCE + '^{tree}').decode().strip()
            == MAIL_LIST_IDENTITY_SOURCE_TREE,
            'Mail list-identity source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1', MAIL_LIST_IDENTITY_SOURCE).decode().split()
            == [MAIL_LIST_IDENTITY_SOURCE, MAIL_LIST_IDENTITY_BASE],
            'Mail list-identity source is not the direct failed-head successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_LIST_IDENTITY_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_LIST_IDENTITY_BASE, MAIL_LIST_IDENTITY_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_LIST_IDENTITY_PATHS)],
            'Mail list-identity source changed another path or status')
    base_entries = entries(root, MAIL_LIST_IDENTITY_BASE)
    source_entries = entries(root, MAIL_LIST_IDENTITY_SOURCE)
    for path in sorted(MAIL_LIST_IDENTITY_PATHS):
        current = project_mail_folder_dynamic_port_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_LIST_IDENTITY_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_LIST_IDENTITY_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_list_identity(root, path, current) == base
                and project_mail_list_identity(root, path, base) == base,
                'Mail list-identity source or projection changed: ' + path)
    workflow = ordinary(root, '.github/workflows/mail-folder-native.yml')
    contract = ordinary(root, 'tests/mail_folder_native_workflow_contract.py')
    native = ordinary(root, 'tests/mail_replies_native.php')
    require(workflow.count(b"'copied': (cell / 'copied-results.json', 23)") == 1
            and workflow.count(b"assert len(report['checks']) == count") == 2
            and workflow.count(b"assert all(check['passed'] is True for check in report['checks'])") == 2
            and contract.count(b"'copied': (cell / 'copied-results.json', 23)") == 1
            and contract.count(b'assert len(report[\'checks\']) >=') == 1
            and native.count(b"$listed=array_column(mail_reply_drafts_list($sender),null,'id');") == 1
            and native.count(b"isset($listed[$id])&&$listed[$id]['subject']===$draft['subject']&&!$listed[$id]['unavailable']") == 1,
            'Mail exact list identity, strict report count or all-check gate changed')


def project_mail_list_identity(root, path, current):
    """Restore the preserved 7e375 head before exact-list/count fixture correction."""
    current = project_mail_folder_dynamic_port_source(root, path, current)
    if path not in MAIL_LIST_IDENTITY_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_LIST_IDENTITY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_LIST_IDENTITY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail list-identity source changed before predecessor projection: ' + path)
    return base


def validate_mail_flags_report_count(root):
    """Bind the exact 45-check flag report after the preserved four-lane failure."""
    require(git(root, 'rev-parse', MAIL_FLAGS_REPORT_COUNT_BASE + '^{tree}').decode().strip()
            == MAIL_FLAGS_REPORT_COUNT_BASE_TREE
            and git(root, 'rev-parse', MAIL_FLAGS_REPORT_COUNT_SOURCE + '^{tree}').decode().strip()
            == MAIL_FLAGS_REPORT_COUNT_SOURCE_TREE,
            'Mail flags report-count source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_FLAGS_REPORT_COUNT_SOURCE).decode().split()
            == [MAIL_FLAGS_REPORT_COUNT_SOURCE, MAIL_FLAGS_REPORT_COUNT_BASE],
            'Mail flags report-count source is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_FLAGS_REPORT_COUNT_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_FLAGS_REPORT_COUNT_BASE,
                MAIL_FLAGS_REPORT_COUNT_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_FLAGS_REPORT_COUNT_PATHS)],
            'Mail flags report-count source changed another path or status')
    base_entries = entries(root, MAIL_FLAGS_REPORT_COUNT_BASE)
    source_entries = entries(root, MAIL_FLAGS_REPORT_COUNT_SOURCE)
    for path in sorted(MAIL_FLAGS_REPORT_COUNT_PATHS):
        current = ordinary(root, path)
        source = git(root, 'cat-file', 'blob', MAIL_FLAGS_REPORT_COUNT_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_FLAGS_REPORT_COUNT_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_flags_report_count(root, path, current) == base
                and project_mail_flags_report_count(root, path, base) == base,
                'Mail flags report-count source or projection changed: ' + path)
    workflow = ordinary(root, '.github/workflows/mail-flags-native.yml')
    contract = ordinary(root, 'tests/mail_flags_native_workflow_contract.py')
    require(workflow.count(b"len(report['checks'])==45") == 1
            and workflow.count(b"assert all(row['passed'] is True for row in report['checks'])") == 2
            and workflow.count(b"assert report['passed'] is True") == 2
            and contract.count(b"len(report['checks'])==45") == 2
            and contract.count(b"len(report['checks'])>=40") == 1,
            'Mail flags exact report count or strict all-check gate changed')


def project_mail_flags_report_count(root, path, current):
    """Restore the copied-draft successor before the flag report-count correction."""
    if path not in MAIL_FLAGS_REPORT_COUNT_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_FLAGS_REPORT_COUNT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_FLAGS_REPORT_COUNT_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail flags report-count source changed before predecessor projection: ' + path)
    return base


def validate_application_browser_fixture_source(root):
    """Bind the exact browser assertion correction from preserved Application run 44457889533."""
    require(git(root, 'rev-parse', APPLICATION_BROWSER_FIXTURE_BASE + '^{tree}').decode().strip()
            == APPLICATION_BROWSER_FIXTURE_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_BROWSER_FIXTURE_SOURCE + '^{tree}').decode().strip()
            == APPLICATION_BROWSER_FIXTURE_SOURCE_TREE,
            'Application browser fixture successor tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1', APPLICATION_BROWSER_FIXTURE_SOURCE)
            .decode().split() == [APPLICATION_BROWSER_FIXTURE_SOURCE,
                                  APPLICATION_BROWSER_FIXTURE_BASE],
            'Application browser fixture source is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_BROWSER_FIXTURE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_BROWSER_FIXTURE_BASE, APPLICATION_BROWSER_FIXTURE_SOURCE)
            .decode().splitlines() == ['M\t' + APPLICATION_BROWSER_FIXTURE_PATH],
            'Application browser fixture successor changed another path or status')
    base_entries = entries(root, APPLICATION_BROWSER_FIXTURE_BASE)
    source_entries = entries(root, APPLICATION_BROWSER_FIXTURE_SOURCE)
    current = ordinary(root, APPLICATION_BROWSER_FIXTURE_PATH)
    source = git(root, 'cat-file', 'blob', APPLICATION_BROWSER_FIXTURE_SOURCE
                 + ':' + APPLICATION_BROWSER_FIXTURE_PATH)
    base = git(root, 'cat-file', 'blob', APPLICATION_BROWSER_FIXTURE_BASE
               + ':' + APPLICATION_BROWSER_FIXTURE_PATH)
    view = ordinary(root, 'site/admin/views/mail_accounts.php')
    capability = b'Bounded message attachments download only through an explicit authorized request'
    require(current == source
            and mode(root, APPLICATION_BROWSER_FIXTURE_PATH)
            == base_entries[APPLICATION_BROWSER_FIXTURE_PATH][0]
            == source_entries[APPLICATION_BROWSER_FIXTURE_PATH][0] == '100644'
            and project_application_browser_fixture_source(
                root, APPLICATION_BROWSER_FIXTURE_PATH, current) == base
            and project_application_browser_fixture_source(
                root, APPLICATION_BROWSER_FIXTURE_PATH, base) == base
            and source.count(capability) == 1
            and source.count(b'Attachments remain unavailable') == 0
            and view.count(capability) == 1,
            'Application browser fixture identity, projection or capability assertion changed')


def project_application_browser_fixture_source(root, path, current):
    """Restore the accepted pre-browser-correction proof head for older source receipts."""
    if path != APPLICATION_BROWSER_FIXTURE_PATH:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_BROWSER_FIXTURE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_BROWSER_FIXTURE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application browser fixture changed before predecessor projection')
    return base


def validate_application_notices_fixture_source(root):
    """Bind the retained-v1.18 migration order exposed by preserved run 44502124163."""
    require(git(root, 'rev-parse', APPLICATION_NOTICES_FIXTURE_BASE + '^{tree}').decode().strip()
            == APPLICATION_NOTICES_FIXTURE_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_NOTICES_FIXTURE_SOURCE + '^{tree}').decode().strip()
            == APPLICATION_NOTICES_FIXTURE_SOURCE_TREE,
            'Application notices fixture source identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                APPLICATION_NOTICES_FIXTURE_SOURCE).decode().strip().split()
            == [APPLICATION_NOTICES_FIXTURE_BASE],
            'Application notices fixture source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_NOTICES_FIXTURE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_NOTICES_FIXTURE_BASE,
                APPLICATION_NOTICES_FIXTURE_SOURCE).decode().splitlines()
            == ['M\t' + APPLICATION_INBOX_FIXTURE_PATH],
            'Application notices fixture changed another path or status')
    base_entries = entries(root, APPLICATION_NOTICES_FIXTURE_BASE)
    source_entries = entries(root, APPLICATION_NOTICES_FIXTURE_SOURCE)
    current = ordinary(root, APPLICATION_INBOX_FIXTURE_PATH)
    source = git(root, 'cat-file', 'blob', APPLICATION_NOTICES_FIXTURE_SOURCE
                 + ':' + APPLICATION_INBOX_FIXTURE_PATH)
    base = git(root, 'cat-file', 'blob', APPLICATION_NOTICES_FIXTURE_BASE
               + ':' + APPLICATION_INBOX_FIXTURE_PATH)
    require(current == source
            and mode(root, APPLICATION_INBOX_FIXTURE_PATH)
            == base_entries[APPLICATION_INBOX_FIXTURE_PATH][0]
            == source_entries[APPLICATION_INBOX_FIXTURE_PATH][0] == '100644'
            and project_application_notices_fixture_source(
                root, APPLICATION_INBOX_FIXTURE_PATH, current) == base
            and project_application_notices_fixture_source(
                root, APPLICATION_INBOX_FIXTURE_PATH, base) == base
            and source.count(b"'action':'mail_inbox_setup','current_password':password") == 2
            and source.count(b"'action':'mail_message_setup','current_password':password") == 2
            and source.count(b"owner migrates retained inbox history to scoped reads") == 1
            and source.count(b"all(q['folder']=='INBOX' for q in migrated_inbox['requests'])") == 1
            and source.count(b"owner migrates retained message history to scoped reads") == 1,
            'Application notices fixture identity, projection or retained-data assertions changed')


def project_application_notices_fixture_source(root, path, current):
    """Restore the reviewed proof head before the retained-v1.18 fixture correction."""
    if path != APPLICATION_INBOX_FIXTURE_PATH:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_NOTICES_FIXTURE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_NOTICES_FIXTURE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application notices fixture changed before predecessor projection')
    return base


def validate_application_fresh_draft_readiness_source(root):
    """Bind the fresh-install readiness correction exposed by preserved run 44502124163."""
    require(git(root, 'rev-parse', APPLICATION_FRESH_DRAFT_READINESS_BASE + '^{tree}').decode().strip()
            == APPLICATION_FRESH_DRAFT_READINESS_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_FRESH_DRAFT_READINESS_SOURCE + '^{tree}').decode().strip()
            == APPLICATION_FRESH_DRAFT_READINESS_SOURCE_TREE,
            'Application fresh-draft readiness source identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                APPLICATION_FRESH_DRAFT_READINESS_SOURCE).decode().strip().split()
            == [APPLICATION_FRESH_DRAFT_READINESS_BASE],
            'Application fresh-draft readiness source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_FRESH_DRAFT_READINESS_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_FRESH_DRAFT_READINESS_BASE,
                APPLICATION_FRESH_DRAFT_READINESS_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(APPLICATION_FRESH_DRAFT_READINESS_PATHS)],
            'Application fresh-draft readiness source changed another path or status')
    base_entries = entries(root, APPLICATION_FRESH_DRAFT_READINESS_BASE)
    source_entries = entries(root, APPLICATION_FRESH_DRAFT_READINESS_SOURCE)
    for path in sorted(APPLICATION_FRESH_DRAFT_READINESS_PATHS):
        current = project_application_mail_copies_routing_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', APPLICATION_FRESH_DRAFT_READINESS_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', APPLICATION_FRESH_DRAFT_READINESS_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_application_fresh_draft_readiness_source(root, path, current) == base
                and project_application_fresh_draft_readiness_source(root, path, base) == base,
                'Application fresh-draft readiness identity or projection changed: ' + path)
    deploy = ordinary(root, 'site/install/actions/deploy.php')
    predecessor = git(root, 'cat-file', 'blob', APPLICATION_FRESH_DRAFT_READINESS_BASE
                      + ':site/install/actions/deploy.php')
    browser = ordinary(root, 'tests/mail_drafts_cases.py')
    require(deploy.count(b"save_setting('mail_drafts_schema_version','1')") == 1
            and deploy.count(b"save_setting('mail_drafts_html_ready','1')") == 1
            and deploy.index(b"save_setting('mail_drafts_schema_version','1')")
            < deploy.index(b"save_setting('mail_drafts_html_ready','1')")
            and predecessor.count(b"save_setting('mail_drafts_html_ready','1')") == 0
            and browser.count(b'Fresh installation prepares empty private draft storage') == 1
            and ordinary(root, INVENTORY) == canonical_inventory(root),
            'Application fresh-draft readiness marker, assertion or inventory boundary changed')


def project_application_fresh_draft_readiness_source(root, path, current):
    """Restore the reviewed proof head before the fresh-install readiness correction."""
    current = project_application_mail_copies_routing_source(root, path, current)
    if path not in APPLICATION_FRESH_DRAFT_READINESS_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_FRESH_DRAFT_READINESS_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_FRESH_DRAFT_READINESS_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application fresh-draft readiness source changed before predecessor projection: ' + path)
    return base


def validate_application_http_expiry_fix_source(root):
    """Bind the strict-database HTTP expiry fixture exposed by run 44729363443."""
    require(git(root, 'rev-parse', APPLICATION_HTTP_EXPIRY_FIX_BASE + '^{tree}').decode().strip()
            == APPLICATION_HTTP_EXPIRY_FIX_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_HTTP_EXPIRY_FIX_SOURCE + '^{tree}').decode().strip()
            == APPLICATION_HTTP_EXPIRY_FIX_SOURCE_TREE,
            'Application HTTP expiry-fixture source identity changed')
    require(git(root, 'show', '-s', '--format=%P', APPLICATION_HTTP_EXPIRY_FIX_SOURCE)
            .decode().strip().split() == [APPLICATION_HTTP_EXPIRY_FIX_BASE],
            'Application HTTP expiry-fixture source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_HTTP_EXPIRY_FIX_SOURCE, 'HEAD')
    path = APPLICATION_HTTP_EXPIRY_FIX_PATH
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_HTTP_EXPIRY_FIX_BASE,
                APPLICATION_HTTP_EXPIRY_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path],
            'Application HTTP expiry fixture changed another path or status')
    base_entries = entries(root, APPLICATION_HTTP_EXPIRY_FIX_BASE)
    source_entries = entries(root, APPLICATION_HTTP_EXPIRY_FIX_SOURCE)
    current = project_application_sent_copy_fixture_mode(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', APPLICATION_HTTP_EXPIRY_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_HTTP_EXPIRY_FIX_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_application_http_expiry_fix_source(root, path, current) == base
            and project_application_http_expiry_fix_source(root, path, base) == base,
            'Application HTTP expiry-fixture identity, mode or projection changed')
    replacement = b"'UPDATE ci_mail_account_grants SET expires_at=1 WHERE account_id=? AND user_id=?'"
    legacy = b'"UPDATE ci_mail_account_grants SET expires_at=\'2000-01-01 00:00:00\' WHERE account_id=? AND user_id=?"'
    schema = ordinary(root, 'site/database/schema/mail_accounts_schema.php')
    require(current.count(replacement) == 1
            and legacy not in current
            and schema.count(b'expires_at BIGINT UNSIGNED NULL') >= 1,
            'Application HTTP expiry fixture or canonical epoch-schema boundary changed')


def project_application_http_expiry_fix_source(root, path, current):
    """Restore the accepted PR head before the strict-database fixture correction."""
    current = project_application_sent_copy_fixture_mode(root, path, current)
    if path != APPLICATION_HTTP_EXPIRY_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_HTTP_EXPIRY_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_HTTP_EXPIRY_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application HTTP expiry fixture changed before predecessor projection')
    return base


def validate_application_mail_copies_routing_source(root):
    """Bind the owner-routed successor exposed by review of run 44676679112."""
    require(git(root, 'rev-parse', APPLICATION_MAIL_COPIES_ROUTING_BASE + '^{tree}').decode().strip()
            == APPLICATION_MAIL_COPIES_ROUTING_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_MAIL_COPIES_ROUTING_SOURCE + '^{tree}').decode().strip()
            == APPLICATION_MAIL_COPIES_ROUTING_SOURCE_TREE,
            'Application Sent-copy routing source identity changed')
    require(git(root, 'show', '-s', '--format=%P', APPLICATION_MAIL_COPIES_ROUTING_SOURCE)
            .decode().strip().split() == [APPLICATION_MAIL_COPIES_ROUTING_BASE],
            'Application Sent-copy routing source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_MAIL_COPIES_ROUTING_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  APPLICATION_MAIL_COPIES_ROUTING_BASE,
                  APPLICATION_MAIL_COPIES_ROUTING_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(APPLICATION_MAIL_COPIES_ROUTING_MODIFIED)],
            'Application Sent-copy routing source changed another path or status set')
    base_entries = entries(root, APPLICATION_MAIL_COPIES_ROUTING_BASE)
    source_entries = entries(root, APPLICATION_MAIL_COPIES_ROUTING_SOURCE)
    for path in sorted(APPLICATION_MAIL_COPIES_ROUTING_MODIFIED):
        current = project_application_http_expiry_fix_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', APPLICATION_MAIL_COPIES_ROUTING_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', APPLICATION_MAIL_COPIES_ROUTING_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_application_mail_copies_routing_source(root, path, current) == base
                and project_application_mail_copies_routing_source(root, path, base) == base,
                'Application Sent-copy routing identity or projection changed: ' + path)
    actions = ordinary(root, 'site/admin/controllers/actions.php')
    controller = ordinary(root, 'site/admin/controllers/mail_accounts.php')
    staff = ordinary(root, 'site/app/staff/accounts.php')
    forms = ordinary(root, 'tests/mail_submission_forms.php')
    browser = ordinary(root, 'tests/mail_submissions_cases.py')
    require(actions.count(b"in_array($action,['mail_copies_setup','mail_sent_folder_save'],true)") == 1
            and controller.count(b"if ($action==='mail_copies_setup') {\n        mail_accounts_manager();mail_copies_migrate();") == 1
            and controller.count(b"if ($action==='mail_sent_folder_save')") == 1
            and controller.count(b"mail_accounts_manager();mail_accounts_load($id);") >= 1
            and staff.count(b"if(in_array($action,['mail_copies_setup','mail_sent_folder_save'],true))return 'staff.manage';") == 1
            and forms.count(b'Sent-copy setup and folder actions have explicit owner routing') == 1
            and browser.count(b'Sent-copy setup rejects forged CSRF without storage or server contact') == 1
            and browser.count(b'Non-owner cannot prepare Sent-copy storage or contact the server') == 1
            and browser.count(b'Owner prepares additive Sent-copy storage without a server connection') == 1
            and browser.count(b"r = submit(owner, root + '?view=mail-accounts', 'mail_copies_setup', {})") == 1
            and ordinary(root, INVENTORY) == canonical_inventory(root),
            'Application Sent-copy routing, owner boundary, refusal evidence or inventory changed')


def project_application_mail_copies_routing_source(root, path, current):
    """Restore the reviewed test-route proof head before older projections."""
    current = project_application_http_expiry_fix_source(root, path, current)
    if path not in APPLICATION_MAIL_COPIES_ROUTING_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_MAIL_COPIES_ROUTING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_MAIL_COPIES_ROUTING_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application Sent-copy routing source changed before predecessor projection: ' + path)
    return base


def validate_application_mail_copies_setup_source(root):
    """Bind the listing-route correction exposed by preserved run 44676679112."""
    require(git(root, 'rev-parse', APPLICATION_MAIL_COPIES_SETUP_BASE + '^{tree}').decode().strip()
            == APPLICATION_MAIL_COPIES_SETUP_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_MAIL_COPIES_SETUP_SOURCE + '^{tree}').decode().strip()
            == APPLICATION_MAIL_COPIES_SETUP_SOURCE_TREE,
            'Application Sent-copy setup source identity changed')
    require(git(root, 'show', '-s', '--format=%P', APPLICATION_MAIL_COPIES_SETUP_SOURCE)
            .decode().strip().split() == [APPLICATION_MAIL_COPIES_SETUP_BASE],
            'Application Sent-copy setup source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_MAIL_COPIES_SETUP_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_MAIL_COPIES_SETUP_BASE,
                APPLICATION_MAIL_COPIES_SETUP_SOURCE).decode().splitlines()
            == ['M\t' + APPLICATION_MAIL_COPIES_SETUP_PATH],
            'Application Sent-copy setup source changed another path or status')
    path = APPLICATION_MAIL_COPIES_SETUP_PATH
    base_entries = entries(root, APPLICATION_MAIL_COPIES_SETUP_BASE)
    source_entries = entries(root, APPLICATION_MAIL_COPIES_SETUP_SOURCE)
    current = project_application_mail_copies_routing_source(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', APPLICATION_MAIL_COPIES_SETUP_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_MAIL_COPIES_SETUP_BASE + ':' + path)
    old_call = b"r = submit(owner, accounts, 'mail_copies_setup', {})"
    new_call = b"r = submit(owner, root + '?view=mail-accounts', 'mail_copies_setup', {})"
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_application_mail_copies_setup_source(root, path, current) == base
            and project_application_mail_copies_setup_source(root, path, base) == base,
            'Application Sent-copy setup source identity or projection changed')
    require(source.count(new_call) == 1
            and source.count(old_call) == 0
            and base.count(old_call) == 1
            and base.count(new_call) == 0
            and source.count(b"accounts = root + '?view=mail-account'") == 1
            and source.count(b"check('Copy refuses before an owner prepares storage and a folder'") == 1
            and source.count(b"check('Owner prepares additive Sent-copy storage without a server connection'") == 1,
            'Application Sent-copy setup route or retained safety assertions changed')


def project_application_mail_copies_setup_source(root, path, current):
    """Restore the reviewed rate-boundary proof head before older mail projections."""
    current = project_application_mail_copies_routing_source(root, path, current)
    if path != APPLICATION_MAIL_COPIES_SETUP_PATH:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_MAIL_COPIES_SETUP_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_MAIL_COPIES_SETUP_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application Sent-copy setup source changed before predecessor projection')
    return base


def validate_application_sent_copy_absence_source(root):
    """Bind the exact evidence correction exposed by preserved run 44567362237."""
    require(git(root, 'rev-parse', APPLICATION_SENT_COPY_ABSENCE_BASE + '^{tree}').decode().strip()
            == APPLICATION_SENT_COPY_ABSENCE_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_SENT_COPY_ABSENCE_SOURCE + '^{tree}').decode().strip()
            == APPLICATION_SENT_COPY_ABSENCE_SOURCE_TREE,
            'Application Sent-copy absence source identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                APPLICATION_SENT_COPY_ABSENCE_SOURCE).decode().strip().split()
            == [APPLICATION_SENT_COPY_ABSENCE_BASE],
            'Application Sent-copy absence source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_SENT_COPY_ABSENCE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_SENT_COPY_ABSENCE_BASE,
                APPLICATION_SENT_COPY_ABSENCE_SOURCE).decode().splitlines()
            == ['M\t' + APPLICATION_SENT_COPY_ABSENCE_PATH],
            'Application Sent-copy absence source changed another path or status')
    base_entries = entries(root, APPLICATION_SENT_COPY_ABSENCE_BASE)
    source_entries = entries(root, APPLICATION_SENT_COPY_ABSENCE_SOURCE)
    path = APPLICATION_SENT_COPY_ABSENCE_PATH
    current = project_application_mail_copies_setup_source(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', APPLICATION_SENT_COPY_ABSENCE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_SENT_COPY_ABSENCE_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_application_sent_copy_absence_source(root, path, current) == base
            and project_application_sent_copy_absence_source(root, path, base) == base,
            'Application Sent-copy absence source identity or projection changed')
    query = (b"SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() "
             b"AND table_name IN ('ci_mail_sent_folders','ci_mail_copy_attempts','ci_mail_copy_results')")
    require(source.count(query) == 1
            and source.count(b"not db(\"SELECT value FROM ci_settings WHERE name='mail_copies_schema_version'\") and not copy_tables") == 1
            and source.count(b"'sent-copy storage' in r.text.lower() and inbox_server.connections == inbox_connections") == 1
            and base.count(b"not db(\"SELECT value FROM ci_settings WHERE name='mail_copies_schema_version'\") and not db('SELECT * FROM ci_mail_copy_attempts')") == 1
            and source.count(b"not db(\"SELECT value FROM ci_settings WHERE name='mail_copies_schema_version'\") and not db('SELECT * FROM ci_mail_copy_attempts')") == 0
            and query not in base,
            'Application Sent-copy absence assertion was weakened or changed')


def project_application_sent_copy_absence_source(root, path, current):
    """Restore the preserved failed exact head before the evidence correction."""
    current = project_application_mail_copies_setup_source(root, path, current)
    if path != APPLICATION_SENT_COPY_ABSENCE_PATH:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_SENT_COPY_ABSENCE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_SENT_COPY_ABSENCE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application Sent-copy absence source changed before predecessor projection')
    return base


def validate_application_fixture_successors(root):
    """Bind exact fixture corrections discovered by preserved Application run 44423175477."""
    identities = (
        (APPLICATION_ACCOUNT_FIXTURE_BASE, APPLICATION_ACCOUNT_FIXTURE_BASE_TREE),
        (APPLICATION_ACCOUNT_FLAGS_SOURCE, APPLICATION_ACCOUNT_FLAGS_TREE),
        (APPLICATION_ACCOUNT_FILES_SOURCE, APPLICATION_ACCOUNT_FILES_TREE),
        (APPLICATION_INBOX_FIXTURE_SOURCE, APPLICATION_INBOX_FIXTURE_TREE),
        (APPLICATION_MESSAGE_FIXTURE_BASE, APPLICATION_MESSAGE_FIXTURE_BASE_TREE),
        (APPLICATION_MESSAGE_FIXTURE_SOURCE, APPLICATION_MESSAGE_FIXTURE_SOURCE_TREE),
    )
    for commit, tree in identities:
        require(git(root, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Application fixture successor tree changed: ' + commit)
    chain = (
        (APPLICATION_ACCOUNT_FIXTURE_BASE, APPLICATION_ACCOUNT_FLAGS_SOURCE,
         APPLICATION_ACCOUNT_FIXTURE_PATH),
        (APPLICATION_ACCOUNT_FLAGS_SOURCE, APPLICATION_ACCOUNT_FILES_SOURCE,
         APPLICATION_ACCOUNT_FIXTURE_PATH),
        (APPLICATION_ACCOUNT_FILES_SOURCE, APPLICATION_INBOX_FIXTURE_SOURCE,
         APPLICATION_INBOX_FIXTURE_PATH),
        (APPLICATION_MESSAGE_FIXTURE_BASE, APPLICATION_MESSAGE_FIXTURE_SOURCE,
         APPLICATION_INBOX_FIXTURE_PATH),
    )
    for base, source, path in chain:
        require(git(root, 'rev-list', '--parents', '-n', '1', source).decode().split()
                == [source, base],
                'Application fixture source is not the direct reviewed successor: ' + source)
        require(git(root, 'diff', '--name-status', '--no-renames', base, source)
                .decode().splitlines() == ['M\t' + path],
                'Application fixture successor changed another path or status: ' + source)
        base_entries = entries(root, base)
        source_entries = entries(root, source)
        require(base_entries[path][0] == source_entries[path][0] == '100644',
                'Application fixture successor mode changed: ' + source)
    git(root, 'merge-base', '--is-ancestor', APPLICATION_MESSAGE_FIXTURE_SOURCE, 'HEAD')
    account = ordinary(root, APPLICATION_ACCOUNT_FIXTURE_PATH)
    inbox = ordinary(root, APPLICATION_INBOX_FIXTURE_PATH)
    predecessor_inbox = project_application_notices_fixture_source(
        root, APPLICATION_INBOX_FIXTURE_PATH, inbox)
    require(account == git(root, 'cat-file', 'blob', APPLICATION_ACCOUNT_FILES_SOURCE
                           + ':' + APPLICATION_ACCOUNT_FIXTURE_PATH)
            and predecessor_inbox == git(root, 'cat-file', 'blob', APPLICATION_MESSAGE_FIXTURE_SOURCE
                                         + ':' + APPLICATION_INBOX_FIXTURE_PATH)
            and project_application_fixture_successors(
                root, APPLICATION_ACCOUNT_FIXTURE_PATH, account)
            == git(root, 'cat-file', 'blob', APPLICATION_ACCOUNT_FIXTURE_BASE
                   + ':' + APPLICATION_ACCOUNT_FIXTURE_PATH)
            and project_application_fixture_successors(
                root, APPLICATION_INBOX_FIXTURE_PATH, inbox)
            == git(root, 'cat-file', 'blob', APPLICATION_ACCOUNT_FILES_SOURCE
                   + ':' + APPLICATION_INBOX_FIXTURE_PATH),
            'Current Application fixture bytes or reversible projection changed')
    controller = ordinary(root, 'site/admin/controllers/mail_inbox.php')
    view = ordinary(root, 'site/admin/views/mail_inbox.php')
    require(account.count(b"mail_flags_schema('maci_')") == 1
            and account.count(b"mail_files_schema('maci_')") == 1
            and account.count(b"schema_statements('maci_')") == 1
            and inbox.count(b"'action':'mail_inbox_read','account_id':account['id'],'revision':account['revision'],'folder':'INBOX'") == 1
            and inbox.count(b"'public server hostname' in r.text and records('inbox')=={'requests':[],'results':[]}") == 1
            and inbox.count(b"route={'account_id':account['id'],'revision':str(account['revision']),'folder':'INBOX','uidvalidity':'1234','uid':'82'}") == 1
            and inbox.count(b"'public incoming server' in r.text and records('messages')=={'requests':[],'results':[]}") == 1
            and controller.count(b"if(posted('folder',64)!==$folder)") == 1
            and view.count(b'name="folder" value="<?=esc($folder)?>"') == 1,
            'Application fixture dependency exclusion, route identity or no-network assertion changed')


def project_application_fixture_successors(root, path, current):
    """Restore the accepted 6d576 source before exact Application fixture corrections."""
    current = project_application_notices_fixture_source(root, path, current)
    current = project_application_browser_fixture_source(root, path, current)
    if path == APPLICATION_ACCOUNT_FIXTURE_PATH:
        final = git(root, 'cat-file', 'blob', APPLICATION_ACCOUNT_FILES_SOURCE + ':' + path)
        middle = git(root, 'cat-file', 'blob', APPLICATION_ACCOUNT_FLAGS_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', APPLICATION_ACCOUNT_FIXTURE_BASE + ':' + path)
        if current == final:
            current = middle
        if current == middle:
            return base
        if current == base or current != ordinary(root, path):
            return current
        require(current == final,
                'Current Application account fixture changed before predecessor projection')
    if path == APPLICATION_INBOX_FIXTURE_PATH:
        final = git(root, 'cat-file', 'blob', APPLICATION_MESSAGE_FIXTURE_SOURCE + ':' + path)
        middle = git(root, 'cat-file', 'blob', APPLICATION_MESSAGE_FIXTURE_BASE + ':' + path)
        if current == final:
            current = middle
        source = git(root, 'cat-file', 'blob', APPLICATION_INBOX_FIXTURE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', APPLICATION_ACCOUNT_FILES_SOURCE + ':' + path)
        if current == source:
            return base
        if current == base or current != ordinary(root, path):
            return current
        require(current == source,
                'Current Application inbox fixture changed before predecessor projection')
    return current


def validate_latest_acceptance_successors(root):
    """Bind the reviewed corrections from the preserved c45 cohort."""
    sources = (
        (FORCE_SSL_RUNNER_PORT_BASE, FORCE_SSL_RUNNER_PORT_BASE_TREE,
         FORCE_SSL_RUNNER_PORT_SOURCE, FORCE_SSL_RUNNER_PORT_SOURCE_TREE,
         FORCE_SSL_RUNNER_PORT_PATH, 'Force SSL runner port'),
        (MAIL_REPLY_ALL_BOUNDARY_BASE, MAIL_REPLY_ALL_BOUNDARY_BASE_TREE,
         MAIL_REPLY_ALL_BOUNDARY_SOURCE, MAIL_REPLY_ALL_BOUNDARY_SOURCE_TREE,
         MAIL_REPLY_ALL_BOUNDARY_PATH, 'Reply All recipient boundary'),
        (MAIL_FLAGS_IDENTITY_BASE, MAIL_FLAGS_IDENTITY_BASE_TREE,
         MAIL_FLAGS_IDENTITY_SOURCE, MAIL_FLAGS_IDENTITY_SOURCE_TREE,
         MAIL_FLAGS_IDENTITY_PATH, 'mail flags notice identity'),
        (MAIL_FLAGS_MARKER_BASE, MAIL_FLAGS_MARKER_BASE_TREE,
         MAIL_FLAGS_MARKER_SOURCE, MAIL_FLAGS_MARKER_SOURCE_TREE,
         MAIL_FLAGS_MARKER_PATH, 'mail flags current schema marker'),
    )
    for base, base_tree, source, source_tree, path, label in sources:
        require(git(root, 'rev-parse', base + '^{tree}').decode().strip() == base_tree
                and git(root, 'rev-parse', source + '^{tree}').decode().strip() == source_tree,
                label + ' source tree changed')
        require(git(root, 'rev-list', '--parents', '-n', '1', source).decode().split()
                == [source, base], label + ' is not the direct reviewed successor')
        git(root, 'merge-base', '--is-ancestor', source, 'HEAD')
        require(git(root, 'diff', '--name-status', '--no-renames', base, source)
                .decode().splitlines() == ['M\t' + path],
                label + ' changed another path or status')
        base_entries = entries(root, base)
        source_entries = entries(root, source)
        require(base_entries[path][0] == source_entries[path][0] == mode(root, path) == '100644',
                label + ' mode changed')

    force = ordinary(root, FORCE_SSL_RUNNER_PORT_PATH)
    reply = ordinary(root, MAIL_REPLY_ALL_BOUNDARY_PATH)
    flags = ordinary(root, MAIL_FLAGS_MARKER_PATH)
    notices = ordinary(root, 'site/app/mail/notices.php')
    require(force == git(root, 'cat-file', 'blob', FORCE_SSL_RUNNER_PORT_SOURCE
                         + ':' + FORCE_SSL_RUNNER_PORT_PATH)
            and force.count(b"ports: ['3306/tcp']") == 1
            and b'33306' not in force
            and force.count(b'CWP_TEST_PORT=${{ job.services.database.ports[3306] }}') == 1,
            'Force SSL dynamic service-port boundary changed')
    require(reply == git(root, 'cat-file', 'blob', MAIL_REPLY_ALL_BOUNDARY_SOURCE
                         + ':' + MAIL_REPLY_ALL_BOUNDARY_PATH)
            and reply.count(b"$replyAllRow['sender']===['id'=>$senderAccount,'revision'=>1,'label'=>'Sender account','address'=>'sender@example.invalid']") == 1
            and reply.count(b"$replyAllRecipients=json_encode([$replyAllRow['to'],$replyAllRow['cc'],$replyAllRow['bcc']],JSON_THROW_ON_ERROR)") == 1
            and reply.count(b"!str_contains($replyAllRecipients,'sender@example.invalid')") == 1
            and reply.count(b"!str_contains(json_encode($replyAllRow,JSON_THROW_ON_ERROR),'RAW_HIDDEN_MARKER')") == 1,
            'Reply All sender metadata or recipient privacy boundary changed')
    identity = git(root, 'cat-file', 'blob', MAIL_FLAGS_IDENTITY_SOURCE
                   + ':' + MAIL_FLAGS_IDENTITY_PATH)
    require(flags == git(root, 'cat-file', 'blob', MAIL_FLAGS_MARKER_SOURCE
                         + ':' + MAIL_FLAGS_MARKER_PATH)
            and identity.count(b"save_setting('mail_notices_schema_version','2')") == 1
            and flags.count(b"save_setting('mail_notices_schema_version','1')") == 1
            and flags.count(b"(account_id,account_revision,folder,uidvalidity,request_id,checked_at)") == 1
            and flags.count(b"[$id,str_repeat('b',32),gmdate('Y-m-d H:i:s')]") == 1
            and notices.count(b"['mail_notices_schema_version'])['value']??'')==='1'") == 1,
            'Mail flag notice identity or current-schema readiness boundary changed')


def project_force_ssl_runner_port(root, path, current):
    """Restore c45 before the Force SSL ephemeral-port correction."""
    if path != FORCE_SSL_RUNNER_PORT_PATH:
        return current
    source = git(root, 'cat-file', 'blob', FORCE_SSL_RUNNER_PORT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', FORCE_SSL_RUNNER_PORT_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source, 'Current Force SSL runner port source changed before projection')
    return base


def project_mail_reply_all_boundary(root, path, current):
    """Restore c45 before the Reply All recipient-boundary fixture correction."""
    if path != MAIL_REPLY_ALL_BOUNDARY_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_REPLY_ALL_BOUNDARY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_REPLY_ALL_BOUNDARY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source, 'Current Reply All boundary source changed before projection')
    return base


def project_mail_flags_marker(root, path, current):
    """Restore the reviewed notice-identity source before its marker correction."""
    if path != MAIL_FLAGS_MARKER_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_FLAGS_MARKER_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_FLAGS_MARKER_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source, 'Current mail flags marker source changed before projection')
    return base


def project_mail_flags_identity(root, path, current):
    """Restore c45 before the exact notice-identity fixture correction."""
    if path != MAIL_FLAGS_IDENTITY_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_FLAGS_IDENTITY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_FLAGS_IDENTITY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source, 'Current mail flags identity source changed before projection')
    return base


def project_latest_acceptance_successors(root, path, current):
    """Restore c45 across the independently reviewed correction sources."""
    current = project_mail_flags_report_count(root, path, current)
    current = project_mail_list_identity(root, path, current)
    current = project_mail_flags_marker(root, path, current)
    current = project_mail_flags_identity(root, path, current)
    current = project_mail_reply_all_boundary(root, path, current)
    return project_force_ssl_runner_port(root, path, current)


def validate_mail_acceptance_hardening_source(root):
    """Bind the complete retained-matrix correction after artifact inspection."""
    require(git(root, 'rev-parse', MAIL_ACCEPTANCE_HARDENING_BASE + '^{tree}').decode().strip()
            == MAIL_ACCEPTANCE_HARDENING_BASE_TREE
            and git(root, 'rev-parse', MAIL_ACCEPTANCE_HARDENING_SOURCE + '^{tree}').decode().strip()
            == MAIL_ACCEPTANCE_HARDENING_SOURCE_TREE,
            'Mail acceptance hardening source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ACCEPTANCE_HARDENING_SOURCE).decode().split()
            == [MAIL_ACCEPTANCE_HARDENING_SOURCE, MAIL_ACCEPTANCE_HARDENING_BASE],
            'Mail acceptance hardening is not the direct inspected-artifact successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ACCEPTANCE_HARDENING_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ACCEPTANCE_HARDENING_BASE,
                MAIL_ACCEPTANCE_HARDENING_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_ACCEPTANCE_HARDENING_PATHS)],
            'Mail acceptance hardening changed another path or status')
    base_entries = entries(root, MAIL_ACCEPTANCE_HARDENING_BASE)
    source_entries = entries(root, MAIL_ACCEPTANCE_HARDENING_SOURCE)
    for path in sorted(MAIL_ACCEPTANCE_HARDENING_PATHS):
        current = project_mail_list_identity(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_HARDENING_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_HARDENING_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_acceptance_hardening_source(root, path, current) == base
                and project_mail_acceptance_hardening_source(root, path, base) == base,
                'Mail acceptance hardening identity, mode or projection changed: ' + path)
    workflow = ordinary(root, '.github/workflows/mail-folder-native.yml')
    contract = ordinary(root, 'tests/mail_folder_native_workflow_contract.py')
    native = ordinary(root, 'tests/mail_message_native.php')
    require(workflow.count(b'php_flags="-n"') == 1
            and workflow.count(b'php -n -r') == 1
            and workflow.count(b'function_exists("mb_convert_encoding")') == 1
            and workflow.count(b'function_exists("ctype_digit")') == 1
            and workflow.count(b'class_exists("DOMDocument")') == 1
            and workflow.count(b"'adapter': 19, 'schema-registry': 401") == 1
            and workflow.count(b"assert 'Revoked read grant records a bounded failure, not bytes'") == 1
            and contract.count(b"'lost no-INI mbstring capability'") == 1
            and contract.count(b"'lost no-INI ctype capability'") == 1
            and contract.count(b"'lost no-INI DOM capability'") == 1
            and contract.count(b"'lost retained adapter report count'") == 1
            and native.count(b"in_array($mode,['after','attachment_after'],true)") == 1
            and native.count(b"in_array($mode,['attachment','attachment_after'],true)") == 1
            and native.count(b"$mode='attachment_after'") == 1
            and native.count(b'$transportCalls===$beforeAttach+1') == 1
            and native.count(b"$lateResult['result_code']==='access_changed'") == 1
            and native.count(b"!str_contains(json_encode($r),'SYNTHETIC_ATTACHMENT_BYTES')") == 1,
            'Mail matrix capability, exact-count or late-authority proof changed')


def project_mail_acceptance_hardening_source(root, path, current):
    """Restore the first retained-mail repair before its evidence hardening."""
    current = project_mail_list_identity(root, path, current)
    if path not in MAIL_ACCEPTANCE_HARDENING_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_HARDENING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_HARDENING_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail acceptance hardening changed before predecessor projection: ' + path)
    return base


def validate_mail_acceptance_repair_source(root):
    """Bind the minimal fixes for every retained first-attempt mail failure."""
    require(git(root, 'rev-parse', MAIL_ACCEPTANCE_REPAIR_BASE + '^{tree}').decode().strip()
            == MAIL_ACCEPTANCE_REPAIR_BASE_TREE
            and git(root, 'rev-parse', MAIL_ACCEPTANCE_REPAIR_SOURCE + '^{tree}').decode().strip()
            == MAIL_ACCEPTANCE_REPAIR_SOURCE_TREE,
            'Mail acceptance repair source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ACCEPTANCE_REPAIR_SOURCE).decode().split()
            == [MAIL_ACCEPTANCE_REPAIR_SOURCE, MAIL_ACCEPTANCE_REPAIR_BASE],
            'Mail acceptance repair is not the direct proof-head successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ACCEPTANCE_REPAIR_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ACCEPTANCE_REPAIR_BASE,
                MAIL_ACCEPTANCE_REPAIR_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_ACCEPTANCE_REPAIR_PATHS)],
            'Mail acceptance repair changed another path or status')
    base_entries = entries(root, MAIL_ACCEPTANCE_REPAIR_BASE)
    source_entries = entries(root, MAIL_ACCEPTANCE_REPAIR_SOURCE)
    for path in sorted(MAIL_ACCEPTANCE_REPAIR_PATHS):
        current = project_latest_acceptance_successors(root, path, ordinary(root, path))
        current = project_mail_acceptance_hardening_source(root, path, current)
        source = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_REPAIR_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_REPAIR_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_acceptance_repair_source(root, path, current) == base
                and project_mail_acceptance_repair_source(root, path, base) == base,
                'Mail acceptance repair identity, mode or projection changed: ' + path)
    copied = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_REPAIR_SOURCE
                 + ':tests/mail_copied_draft_create_native.php')
    flags = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_REPAIR_SOURCE
                + ':tests/mail_flags_native.php')
    workflow = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_REPAIR_SOURCE
                   + ':.github/workflows/mail-folder-native.yml')
    contract = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_REPAIR_SOURCE
                   + ':tests/mail_folder_native_workflow_contract.py')
    require(copied.count(b"['name'=>'RAW_ATTACHMENT_MARKER.bin','type'=>'application/octet-stream','size'=>20]") == 1
            and copied.count(b"!str_contains($createdJson,'RAW_HIDDEN_MARKER')") == 1
            and copied.count(b"!str_contains($createdJson,'RAW_ATTACHMENT_BYTES')") == 1
            and copied.count(b"!str_contains($createdJson,'UkFXX0FUVEFDSE1FTlRfQllURVM=')") == 1
            and flags.count(b"require_once __DIR__.'/../site/app/mail/flags.php'") == 1
            and workflow.count(b"'copied-source': 19, 'storage': 21, 'admin-policy': 23") == 1
            and contract.count(b"'lost retained storage report count'") == 1,
            'Mail sanitized-metadata, include or exact-count repair changed')


def project_mail_acceptance_repair_source(root, path, current):
    """Restore the accepted proof head before retained mail acceptance repairs."""
    current = project_latest_acceptance_successors(root, path, current)
    current = project_mail_acceptance_hardening_source(root, path, current)
    if path not in MAIL_ACCEPTANCE_REPAIR_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_REPAIR_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ACCEPTANCE_REPAIR_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail acceptance repair changed before predecessor projection: ' + path)
    return base


def validate_mail_attachment_submission_fix(root):
    """Bind central copied-submission readiness and mandatory format-8 coverage."""
    require(git(root, 'rev-parse', MAIL_ATTACHMENT_SUBMISSION_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_SUBMISSION_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE_TREE,
            'Attachment submission-fix tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE).decode().split()
            == [MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE, MAIL_ATTACHMENT_SUBMISSION_FIX_BASE],
            'Attachment submission fix is not the direct readiness-review successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ATTACHMENT_SUBMISSION_FIX_BASE,
                MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_ATTACHMENT_SUBMISSION_FIX_PATHS)],
            'Attachment submission fix changed another path or status')
    base_entries = entries(root, MAIL_ATTACHMENT_SUBMISSION_FIX_BASE)
    source_entries = entries(root, MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE)
    for path in sorted(MAIL_ATTACHMENT_SUBMISSION_FIX_PATHS):
        current = project_application_sent_copy_absence_source(root, path, ordinary(root, path))
        current = project_mail_attachment_claim_fix(root, path, current)
        source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SUBMISSION_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_attachment_submission_fix(root, path, current) == base
                and project_mail_attachment_submission_fix(root, path, base) == base,
                'Attachment submission-fix identity or projection changed: ' + path)
    replies = ordinary(root, 'site/app/mail/replies.php')
    native = ordinary(root, 'tests/mail_replies_native.php')
    workflow = ordinary(root, '.github/workflows/mail-copied-draft-native.yml')
    contract = ordinary(root, 'tests/mail_copied_draft_native_workflow_contract.py')
    browser = ordinary(root, 'tests/mail_submissions_cases.py')
    require(replies.count(b"if(!mail_replies_ready())throw new RuntimeException('Protected draft storage is unavailable. Existing data was preserved.');") == 1
            and native.count(b'Copied submission preparation refuses ') == 1
            and native.count(b'Copied submission claim refuses ') == 1
            and native.count(b'Dedicated attachment fixture leaves the active pagination set') == 1
            and workflow.count(b'docker-php-ext-install pdo_mysql mbstring dom') == 1
            and workflow.count(b'extension_loaded("dom")') == 1
            and contract.count(b'required DOM coverage') == 1
            and browser.count(b'Another author cannot download a private draft attachment') == 1
            and browser.count(b'attach_bytes not in r.content') == 1
            and ordinary(root, INVENTORY) == canonical_inventory(root),
            'Attachment submission readiness, DOM coverage, pagination or denial boundary changed')


def project_mail_attachment_submission_fix(root, path, current):
    """Restore the file-readiness correction before submission review fixes."""
    current = project_application_sent_copy_absence_source(root, path, current)
    current = project_mail_attachment_claim_fix(root, path, current)
    if path not in MAIL_ATTACHMENT_SUBMISSION_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SUBMISSION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SUBMISSION_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current attachment submission fix changed before predecessor projection: ' + path)
    return base


def validate_mail_attachment_readiness_fix(root):
    """Bind fail-closed protected-storage readiness and corrected format coverage."""
    require(git(root, 'rev-parse', MAIL_ATTACHMENT_READINESS_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_READINESS_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_ATTACHMENT_READINESS_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_READINESS_FIX_SOURCE_TREE,
            'Attachment readiness-fix tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ATTACHMENT_READINESS_FIX_SOURCE).decode().split()
            == [MAIL_ATTACHMENT_READINESS_FIX_SOURCE, MAIL_ATTACHMENT_READINESS_FIX_BASE],
            'Attachment readiness fix is not the direct failed-review successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ATTACHMENT_READINESS_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ATTACHMENT_READINESS_FIX_BASE,
                MAIL_ATTACHMENT_READINESS_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_ATTACHMENT_READINESS_FIX_PATHS)],
            'Attachment readiness fix changed another path or status')
    base_entries = entries(root, MAIL_ATTACHMENT_READINESS_FIX_BASE)
    source_entries = entries(root, MAIL_ATTACHMENT_READINESS_FIX_SOURCE)
    for path in sorted(MAIL_ATTACHMENT_READINESS_FIX_PATHS):
        current = project_mail_attachment_submission_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_READINESS_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_READINESS_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_attachment_readiness_fix(root, path, current) == base
                and project_mail_attachment_readiness_fix(root, path, base) == base,
                'Attachment readiness-fix identity or projection changed: ' + path)
    files = ordinary(root, 'site/app/mail/files.php')
    native = ordinary(root, 'tests/mail_replies_native.php')
    ordinary_http = ordinary(root, 'tests/mail_submissions_cases.py')
    copied_http = ordinary(root, 'tests/mail_reply_cases.py')
    require(files.count(b'if(!mail_replies_ready())') == 2
            and native.count(b"foreach(['missing','future'] as $marker)") == 2
            and native.count(b'Copied attachment submission uses format 4') == 1
            and native.count(b'Formatted copied attachment submission uses format 8') == 1
            and native.count(b'mail_drafts_attach($fileDraft,1') == 1
            and ordinary_http.count(b'attach_bytes not in denied.content') == 1
            and copied_http.count(b'copied_file_bytes not in r.content') == 1
            and ordinary(root, INVENTORY) == canonical_inventory(root),
            'Attachment readiness, format-coverage, denial or inventory boundary changed')


def project_mail_attachment_readiness_fix(root, path, current):
    """Restore the first authority correction before readiness review fixes."""
    current = project_mail_attachment_submission_fix(root, path, current)
    if path not in MAIL_ATTACHMENT_READINESS_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_READINESS_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_READINESS_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current attachment readiness fix changed before predecessor projection: ' + path)
    return base


def validate_mail_attachment_auth_fix(root):
    """Bind current-mailbox/origin authority and compatibility review corrections."""
    require(git(root, 'rev-parse', MAIL_ATTACHMENT_AUTH_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_AUTH_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_ATTACHMENT_AUTH_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_AUTH_FIX_SOURCE_TREE,
            'Attachment authority-fix tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ATTACHMENT_AUTH_FIX_SOURCE).decode().split()
            == [MAIL_ATTACHMENT_AUTH_FIX_SOURCE, MAIL_ATTACHMENT_AUTH_FIX_BASE],
            'Attachment authority fix is not the direct blocked-source successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ATTACHMENT_AUTH_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ATTACHMENT_AUTH_FIX_BASE,
                MAIL_ATTACHMENT_AUTH_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_ATTACHMENT_AUTH_FIX_PATHS)],
            'Attachment authority fix changed another path or status')
    base_entries = entries(root, MAIL_ATTACHMENT_AUTH_FIX_BASE)
    source_entries = entries(root, MAIL_ATTACHMENT_AUTH_FIX_SOURCE)
    for path in sorted(MAIL_ATTACHMENT_AUTH_FIX_PATHS):
        current = project_mail_attachment_readiness_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_AUTH_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_AUTH_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_attachment_auth_fix(root, path, current) == base
                and project_mail_attachment_auth_fix(root, path, base) == base,
                'Attachment authority-fix identity or projection changed: ' + path)
    files = ordinary(root, 'site/app/mail/files.php')
    migration = ordinary(root, 'site/database/migrations/mail_files_migrate.php')
    controller = ordinary(root, 'site/admin/controllers/mail_drafts.php')
    browser = ordinary(root, 'tests/mail_submissions_cases.py')
    require(files.count(b'mail_drafts_file_authority($draftId,$actor)') == 2
            and files.count(b'mail_drafts_account($row[\'account_id\'])') >= 1
            and migration.count(b"['1','2'],true") == 1
            and controller.count(b'$editorAction=in_array(') == 1
            and browser.count(b"b'%PDF-1.4") == 1,
            'Attachment current authority, marker compatibility, rerender or MIME fixture changed')


def project_mail_attachment_auth_fix(root, path, current):
    """Restore the blocked attachment implementation before authority corrections."""
    current = project_mail_attachment_readiness_fix(root, path, current)
    if path not in MAIL_ATTACHMENT_AUTH_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_AUTH_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_AUTH_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current attachment authority fix changed before predecessor projection: ' + path)
    return base


def validate_mail_attachment_source(root):
    """Bind the reviewed private outgoing-attachment implementation source."""
    require(git(root, 'rev-parse', MAIL_ATTACHMENT_SOURCE_BASE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_SOURCE_BASE_TREE
            and git(root, 'rev-parse', MAIL_ATTACHMENT_SOURCE + '^{tree}').decode().strip()
            == MAIL_ATTACHMENT_SOURCE_TREE,
            'Attachment implementation source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1', MAIL_ATTACHMENT_SOURCE).decode().split()
            == [MAIL_ATTACHMENT_SOURCE, MAIL_ATTACHMENT_SOURCE_BASE],
            'Attachment implementation is not the direct portable-source successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ATTACHMENT_SOURCE, 'HEAD')
    expected = [('A' if path in MAIL_ATTACHMENT_ADDITIONS else 'M') + '\t' + path
                for path in sorted(MAIL_ATTACHMENT_MODIFIED | MAIL_ATTACHMENT_ADDITIONS)]
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ATTACHMENT_SOURCE_BASE, MAIL_ATTACHMENT_SOURCE).decode().splitlines() == expected,
            'Attachment implementation changed another path or status')
    base_entries = entries(root, MAIL_ATTACHMENT_SOURCE_BASE)
    source_entries = entries(root, MAIL_ATTACHMENT_SOURCE)
    for path in sorted(MAIL_ATTACHMENT_MODIFIED):
        current = project_mail_attachment_auth_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SOURCE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_attachment_source(root, path, current) == base
                and project_mail_attachment_source(root, path, base) == base,
                'Attachment modified source identity or projection changed: ' + path)
    for path in sorted(MAIL_ATTACHMENT_ADDITIONS):
        current = project_mail_attachment_auth_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SOURCE + ':' + path)
        require(path not in base_entries and current == source
                and mode(root, path) == source_entries[path][0] == '100644',
                'Attachment addition identity or mode changed: ' + path)
    require(ordinary(root, 'foundation/mail-compose/DraftAttachment.php')
            == ordinary(root, 'site/includes/foundation/mail-compose/DraftAttachment.php')
            and ordinary(root, 'site/app/mail/submissions.php').count(b'mail_submission_files(') >= 1
            and ordinary(root, 'site/database/schema/base.php').count(b'mail_files_schema($prefix)') == 1
            and ordinary(root, 'site/install/actions/deploy.php').count(b"save_setting('mail_files_schema_version','1')") == 1
            and ordinary(root, INVENTORY) == canonical_inventory(root),
            'Attachment canonical runtime, schema, fresh-install or inventory boundary changed')


def project_mail_attachment_source(root, path, current):
    """Restore the Sent-copy proof head before the attachment implementation."""
    current = project_mail_attachment_auth_fix(root, path, current)
    if path not in MAIL_ATTACHMENT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SOURCE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current attachment implementation changed before predecessor projection: ' + path)
    return base


def validate_mail_sent_copy_origin_fix(root):
    """Bind the copied-origin Read-authority correction for direct Sent copies."""
    require(git(root, 'rev-parse', MAIL_SENT_COPY_ORIGIN_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_SENT_COPY_ORIGIN_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_SENT_COPY_ORIGIN_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_SENT_COPY_ORIGIN_FIX_SOURCE_TREE,
            'Sent-copy copied-origin fix tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_SENT_COPY_ORIGIN_FIX_SOURCE).decode().split()
            == [MAIL_SENT_COPY_ORIGIN_FIX_SOURCE, MAIL_SENT_COPY_ORIGIN_FIX_BASE],
            'Sent-copy copied-origin fix is not the direct proof successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_SENT_COPY_ORIGIN_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_SENT_COPY_ORIGIN_FIX_BASE,
                MAIL_SENT_COPY_ORIGIN_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_SENT_COPY_ORIGIN_FIX_PATHS)],
            'Sent-copy copied-origin fix changed another path or status')
    base_entries = entries(root, MAIL_SENT_COPY_ORIGIN_FIX_BASE)
    source_entries = entries(root, MAIL_SENT_COPY_ORIGIN_FIX_SOURCE)
    for path in sorted(MAIL_SENT_COPY_ORIGIN_FIX_PATHS):
        current = project_mail_attachment_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_ORIGIN_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_ORIGIN_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_sent_copy_origin_fix(root, path, current) == base
                and project_mail_sent_copy_origin_fix(root, path, base) == base,
                'Sent-copy copied-origin fix identity or projection changed: ' + path)
    adapter = ordinary(root, 'site/app/mail/copies.php')
    native = ordinary(root, 'tests/mail_replies_native.php')
    browser = ordinary(root, 'tests/mail_reply_cases.py')
    require(adapter.count(b'mail_submissions_origin($row,$prepared,$actor);') == 1
            and adapter.count(b'mail_submissions_origin($saved,$prepared,$actor);') == 1
            and native.count(b'Revoked source Read refuses known-id direct Sent copy') == 1
            and native.count(b'Revoked copied-origin refusal creates no attempt or transport call') == 1
            and browser.count(b'Revoked source Read blocks known-id direct Sent copy before attempt or IMAP') == 1
            and ordinary(root, INVENTORY) == canonical_inventory(root),
            'Sent-copy copied-origin authority, regression or inventory boundary changed')


def project_mail_sent_copy_origin_fix(root, path, current):
    """Restore the first Sent-copy proof head before copied-origin correction."""
    current = project_mail_attachment_source(root, path, current)
    if path not in MAIL_SENT_COPY_ORIGIN_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_ORIGIN_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_SENT_COPY_ORIGIN_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Sent-copy copied-origin fix changed before predecessor projection: ' + path)
    return base


def validate_mail_consolidated_review_fix(root):
    """Bind the independently reviewed formatted-mail safety corrections."""
    require(git(root, 'rev-parse', MAIL_CONSOLIDATED_REVIEW_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_CONSOLIDATED_REVIEW_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE_TREE,
            'Consolidated mail review-fix source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE).decode().split()
            == [MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE, MAIL_CONSOLIDATED_REVIEW_FIX_BASE],
            'Consolidated mail review fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_CONSOLIDATED_REVIEW_FIX_BASE,
                MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_CONSOLIDATED_REVIEW_FIX_PATHS)],
            'Consolidated mail review fix changed another path or status')
    base_entries = entries(root, MAIL_CONSOLIDATED_REVIEW_FIX_BASE)
    source_entries = entries(root, MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE)
    for path in sorted(MAIL_CONSOLIDATED_REVIEW_FIX_PATHS):
        current = project_mail_consolidated_review_recovery(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_REVIEW_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_consolidated_review_fix(root, path, current) == base
                and project_mail_consolidated_review_fix(root, path, base) == base,
                'Consolidated mail review-fix identity or projection changed: ' + path)
    origin = ordinary(root, 'foundation/mail-compose/DraftOrigin.php')
    shipped_origin = ordinary(root, 'site/includes/foundation/mail-compose/DraftOrigin.php')
    drafts = ordinary(root, 'site/app/mail/drafts.php')
    migration = ordinary(root, 'site/database/migrations/mail_drafts_migrate.php')
    controller = ordinary(root, 'site/admin/controllers/mail_drafts.php')
    require(origin == shipped_origin
            and origin.count(b'MAX_CONTENT_JSON_BYTES=65535') == 1
            and drafts.count(b"['mail_drafts_html_ready'])['value']??'')==='1'") == 1
            and drafts.count(b"return $html===''?null:$html") == 1
            and migration.index(b"save_setting('mail_drafts_html_ready','1')")
            < migration.index(b"mail_drafts_audit((int)$actor['id'],'setup'")
            and controller.count(b'$content->html!==null') == 1,
            'Consolidated mail review safety boundary changed')


def project_mail_consolidated_review_fix(root, path, current):
    """Restore the consolidated proof predecessor before review corrections."""
    current = project_application_fixture_successors(root, path, current)
    current = project_mail_consolidated_review_recovery(root, path, current)
    if path not in MAIL_CONSOLIDATED_REVIEW_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_REVIEW_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_REVIEW_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current consolidated mail review fix changed before predecessor projection: ' + path)
    return base


def validate_mail_consolidated_source(root):
    """Bind the locally reviewed folder, flag, attachment, HTML and MIME source range."""
    require(git(root, 'rev-parse', MAIL_CONSOLIDATED_BASE + '^{tree}').decode().strip()
            == MAIL_CONSOLIDATED_BASE_TREE
            and git(root, 'rev-parse', MAIL_CONSOLIDATED_SOURCE + '^{tree}').decode().strip()
            == MAIL_CONSOLIDATED_SOURCE_TREE,
            'Consolidated mail source tree changed')
    git(root, 'merge-base', '--is-ancestor', MAIL_CONSOLIDATED_BASE,
        MAIL_CONSOLIDATED_SOURCE)
    git(root, 'merge-base', '--is-ancestor', MAIL_CONSOLIDATED_SOURCE, 'HEAD')
    changes=mail_consolidated_changes(root)
    base_entries=entries(root, MAIL_CONSOLIDATED_BASE)
    source_entries=entries(root, MAIL_CONSOLIDATED_SOURCE)
    for path,status in changes.items():
        source=git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_SOURCE + ':' + path)
        current=project_mail_consolidated_review_fix(root, path, ordinary(root, path))
        require(current == source and mode(root, path) == source_entries[path][0] == '100644',
                'Consolidated mail source bytes or mode changed: ' + path)
        if status == 'A':
            require(path not in base_entries,
                    'Consolidated mail addition existed in the accepted base: ' + path)
        else:
            base=git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_BASE + ':' + path)
            require(path in base_entries and base_entries[path][0] == '100644'
                    and project_mail_consolidated_source(root, path, current) == base
                    and project_mail_consolidated_source(root, path, base) == base,
                    'Consolidated mail predecessor projection changed: ' + path)
    require(ordinary(root, INVENTORY) == canonical_inventory(root),
            'Consolidated mail installer inventory is not canonical')
    document=ordinary(root, 'site/app/mail/document.php')
    drafts=ordinary(root, 'site/app/mail/drafts.php')
    migration=ordinary(root, 'site/database/migrations/mail_drafts_migrate.php')
    require(document.count(b"default-src 'none'; script-src 'none'") == 1
            and document.count(b"img-src 'none'") == 1
            and document.count(b"form-action 'none'") == 1
            and document.count(b'#[\\SensitiveParameter] string $sanitized') == 1
            and drafts.count(b'function mail_drafts_html(') == 1
            and drafts.count(b'ComposeHtml::sanitize($raw)') == 1
            and migration.index(b"if($version==='')")
            < migration.index(b"ADD COLUMN body_html MEDIUMTEXT NULL"),
            'Consolidated formatted-mail privacy or data-preservation boundary changed')


def project_mail_consolidated_source(root, path, current):
    """Restore resulting main before the exact consolidated mail source range."""
    current=project_mail_consolidated_review_fix(root, path, current)
    if mail_consolidated_changes(root).get(path) != 'M':
        return current
    source=git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_SOURCE + ':' + path)
    base=git(root, 'cat-file', 'blob', MAIL_CONSOLIDATED_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current consolidated mail source changed before predecessor projection: ' + path)
    return base


def validate_mail_copy_controls_fix(root):
    """Bind the hosted browser product correction without weakening mailbox authority."""
    require(git(root, 'rev-parse', MAIL_COPY_CONTROLS_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_COPY_CONTROLS_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_COPY_CONTROLS_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_COPY_CONTROLS_FIX_SOURCE_TREE,
            'Mail copy-controls fix source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_COPY_CONTROLS_FIX_SOURCE).decode().split()
            == [MAIL_COPY_CONTROLS_FIX_SOURCE, MAIL_COPY_CONTROLS_FIX_BASE],
            'Mail copy-controls fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_COPY_CONTROLS_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_COPY_CONTROLS_FIX_BASE,
                MAIL_COPY_CONTROLS_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_COPY_CONTROLS_FIX_PATHS)],
            'Mail copy-controls fix changed another path or status')
    base_entries = entries(root, MAIL_COPY_CONTROLS_FIX_BASE)
    source_entries = entries(root, MAIL_COPY_CONTROLS_FIX_SOURCE)
    for path in sorted(MAIL_COPY_CONTROLS_FIX_PATHS):
        current = project_mail_consolidated_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_COPY_CONTROLS_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_COPY_CONTROLS_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_copy_controls_fix(root, path, current) == base
                and project_mail_copy_controls_fix(root, path, base) == base,
                'Mail copy-controls fix source identity or projection changed: ' + path)
    controller = git(root, 'cat-file', 'blob', MAIL_COPY_CONTROLS_FIX_SOURCE
                     + ':site/admin/controllers/mail_message.php')
    predecessor = git(root, 'cat-file', 'blob', MAIL_COPY_CONTROLS_FIX_BASE
                      + ':site/admin/controllers/mail_message.php')
    contract = git(root, 'cat-file', 'blob', MAIL_COPY_CONTROLS_FIX_SOURCE
                   + ':tests/mail_copied_draft_create_adapter.php')
    inventory = git(root, 'cat-file', 'blob', MAIL_COPY_CONTROLS_FIX_SOURCE
                    + ':site/install/inventory.generated.php')
    require(controller.count(b'$mailCopyAllowed=false') == 1
            and controller.count(b'$mailCopyReady&&mail_drafts_admin_access($actor)') == 1
            and controller.count(b'$draftActor=mail_drafts_actor()') == 1
            and controller.count(b"(int)$draftActor['id']===(int)$actor['id']") == 1
            and controller.count(b"hash_equals($draftActor['auth_version'],$actor['auth_version'])") == 1
            and b"staff_can('mail.send',$actor)" not in controller
            and predecessor.count(b"staff_can('mail.send',$actor)") == 1
            and contract.count(b'canonical current Send actor') == 1
            and contract.count(b"!str_contains($controller,\"staff_can('mail.send',\\$actor)\")") == 1
            and inventory.count(b"'admin/controllers/mail_message.php'=>'83a8ebce4a603c313103aaef71b08d7d535d52e8e0be652857f766fd96576fe2'") == 1,
            'Mail copy-controls fix broadened or lost its current-actor and exact-mailbox boundary')


def project_mail_copy_controls_fix(root, path, current):
    """Restore the preserved failed exact head before the product correction."""
    current = project_mail_consolidated_source(root, path, current)
    if path not in MAIL_COPY_CONTROLS_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_COPY_CONTROLS_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_COPY_CONTROLS_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail copy-controls fix changed before predecessor projection: ' + path)
    return base


def validate_mail_reply_forward_source(root):
    """Bind the complete bounded Reply/Forward successor to resulting main."""
    require(git(root, 'rev-parse', MAIL_REPLY_FORWARD_BASE + '^{tree}').decode().strip()
            == MAIL_REPLY_FORWARD_BASE_TREE
            and git(root, 'rev-parse', MAIL_REPLY_FORWARD_PREPARED + '^{tree}').decode().strip()
            == MAIL_REPLY_FORWARD_PREPARED_TREE
            and git(root, 'rev-parse', MAIL_REPLY_FORWARD_SOURCE + '^{tree}').decode().strip()
            == MAIL_REPLY_FORWARD_SOURCE_TREE,
            'Mail Reply/Forward source tree changed')
    git(root, 'merge-base', '--is-ancestor', MAIL_REPLY_FORWARD_BASE,
        MAIL_REPLY_FORWARD_PREPARED)
    require(git(root, 'rev-list', '--parents', '-n', '1', MAIL_REPLY_FORWARD_SOURCE).decode().split()
            == [MAIL_REPLY_FORWARD_SOURCE, MAIL_REPLY_FORWARD_PREPARED],
            'Mail Reply/Forward source is not the direct child of the prepared head')
    git(root, 'merge-base', '--is-ancestor', MAIL_REPLY_FORWARD_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  MAIL_REPLY_FORWARD_BASE, MAIL_REPLY_FORWARD_SOURCE).decode().splitlines()
    modified = {line.split('\t', 1)[1] for line in changes if line.startswith('M\t')}
    additions = {line.split('\t', 1)[1] for line in changes if line.startswith('A\t')}
    require(len(changes) == len(MAIL_REPLY_FORWARD_PATHS)
            and modified == MAIL_REPLY_FORWARD_MODIFIED
            and additions == MAIL_REPLY_FORWARD_ADDITIONS,
            'Mail Reply/Forward source changed a different path or status set')
    base_entries = entries(root, MAIL_REPLY_FORWARD_BASE)
    source_entries = entries(root, MAIL_REPLY_FORWARD_SOURCE)
    require(not MAIL_REPLY_FORWARD_ADDITIONS & set(base_entries)
            and MAIL_REPLY_FORWARD_ADDITIONS <= set(source_entries),
            'Mail Reply/Forward additions did not originate in the source range')
    for path in sorted(MAIL_REPLY_FORWARD_MODIFIED):
        current = project_application_smtp_fixture_bound(root, path, ordinary(root, path))
        current = project_mail_copy_controls_fix(root, path, current)
        current = project_mail_thread_header_fix(root, path, current)
        source = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_reply_forward_source(root, path, current) == base
                and project_mail_reply_forward_source(root, path, base) == base,
                'Mail Reply/Forward modified source identity or projection changed: ' + path)
    for path in sorted(MAIL_REPLY_FORWARD_ADDITIONS):
        current = project_mail_copy_controls_fix(root, path, ordinary(root, path))
        current = project_mail_reply_session_fix(root, path, current)
        current = project_mail_native_fixture_policy_fix(root, path, current)
        current = project_mail_thread_header_fix(root, path, current)
        current = project_mail_reply_forward_workflow_fix(root, path, current)
        require(current
                == git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_SOURCE + ':' + path)
                and mode(root, path) == source_entries[path][0] == '100644',
                'Mail Reply/Forward addition identity changed: ' + path)
    workflow = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_SOURCE
                   + ':.github/workflows/mail-copied-draft-native.yml')
    snapshot = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_SOURCE
                   + ':foundation/mail-submission/SubmissionSnapshot.php')
    mime_test = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_SOURCE
                    + ':tests/mail-submission/mime.php')
    require(b'test "$CWP_RUN_ATTEMPT" = "1"' in workflow
            and b'workflow_dispatch:' not in workflow
            and b'contents: read' in workflow
            and b"php: ['8.2', '8.4']" in workflow
            and b'database: [mariadb, mysql]' in workflow
            and b'sudo apt' not in workflow and b'--allow-unauthenticated' not in workflow,
            'Mail Reply/Forward first-attempt matrix or signed-package boundary changed')
    require(b"$payload['format']=2;" in snapshot
            and b"'format'=>1" in snapshot
            and b'eded77d9b87f652450215918e2fb55e890fd20c1eb8298c3d9b1f73ed96281c7'
            in mime_test,
            'Mail Reply/Forward format-1 compatibility or format-2 binding changed')
    require(ordinary(root, INVENTORY) == canonical_inventory(root),
            'Mail Reply/Forward installer inventory is not canonical')


def project_mail_reply_forward_source(root, path, current):
    """Restore exact resulting-main bytes for predecessor source proofs."""
    current = project_application_smtp_fixture_bound(root, path, current)
    current = project_mail_copy_controls_fix(root, path, current)
    current = project_mail_reply_session_fix(root, path, current)
    current = project_mail_thread_header_fix(root, path, current)
    current = project_mail_reply_forward_workflow_fix(root, path, current)
    if path not in MAIL_REPLY_FORWARD_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Mail Reply/Forward source changed before predecessor projection: ' + path)
    return base


def validate_mail_reply_forward_workflow_fix(root):
    """Bind the dependency-free source admission after its preserved hosted failure."""
    require(git(root, 'rev-parse', MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE_TREE,
            'Mail Reply/Forward workflow-fix tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE).decode().split()
            == [MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE, MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE],
            'Mail Reply/Forward workflow fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE,
                MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(MAIL_REPLY_FORWARD_WORKFLOW_FIX_PATHS)],
            'Mail Reply/Forward workflow fix changed another path or status')
    base_entries = entries(root, MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE)
    source_entries = entries(root, MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE)
    for path in sorted(MAIL_REPLY_FORWARD_WORKFLOW_FIX_PATHS):
        current = project_mail_thread_header_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_reply_forward_workflow_fix(root, path, current) == base
                and project_mail_reply_forward_workflow_fix(root, path, base) == base,
                'Mail Reply/Forward workflow-fix source identity or projection changed: ' + path)
    workflow = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE
                   + ':.github/workflows/mail-copied-draft-native.yml')
    contract = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE
                   + ':tests/mail_copied_draft_native_workflow_contract.py')
    require(b'python3 tests/mail_message_cases.py' not in workflow
            and workflow.count(b"for path in ('tests/mail_message_cases.py', 'tests/mail_reply_cases.py'):") == 1
            and workflow.count(b"compile(Path(path).read_bytes(), path, 'exec')") == 1
            and b'Source admission compiles the reviewed browser modules without importing browser dependencies' in contract
            and b"('lost source message compilation', \"compile(Path(path).read_bytes(), path, 'exec')\"" in contract,
            'Mail Reply/Forward workflow fix does not preserve dependency-free syntax admission')


def project_mail_reply_forward_workflow_fix(root, path, current):
    """Restore the preserved failed exact-head workflow bytes for older proofs."""
    current = project_mail_thread_header_fix(root, path, current)
    if path not in MAIL_REPLY_FORWARD_WORKFLOW_FIX_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_WORKFLOW_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_WORKFLOW_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Mail Reply/Forward workflow fix changed before predecessor projection: ' + path)
    return base


def validate_mail_backup_projection_fix(root):
    """Bind the backup predecessor projection after its preserved Application failure."""
    require(git(root, 'rev-parse', MAIL_BACKUP_PROJECTION_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_BACKUP_PROJECTION_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_BACKUP_PROJECTION_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_BACKUP_PROJECTION_FIX_SOURCE_TREE,
            'Mail backup-projection source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_BACKUP_PROJECTION_FIX_SOURCE).decode().split()
            == [MAIL_BACKUP_PROJECTION_FIX_SOURCE, MAIL_BACKUP_PROJECTION_FIX_BASE],
            'Mail backup-projection fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_BACKUP_PROJECTION_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_BACKUP_PROJECTION_FIX_BASE,
                MAIL_BACKUP_PROJECTION_FIX_SOURCE).decode().splitlines()
            == ['M\t' + MAIL_BACKUP_PROJECTION_FIX_PATH],
            'Mail backup-projection fix changed another path or status')
    base_entries = entries(root, MAIL_BACKUP_PROJECTION_FIX_BASE)
    source_entries = entries(root, MAIL_BACKUP_PROJECTION_FIX_SOURCE)
    path = MAIL_BACKUP_PROJECTION_FIX_PATH
    current = ordinary(root, path)
    source = git(root, 'cat-file', 'blob', MAIL_BACKUP_PROJECTION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_BACKUP_PROJECTION_FIX_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_mail_backup_projection_fix(root, path, current) == base
            and project_mail_backup_projection_fix(root, path, base) == base,
            'Mail backup-projection source identity or projection changed')
    require(source.count(b'composition.project_mail_reply_forward_source(') == 1
            and source.count(b'composition.project_ux09_security(') == 1
            and source.index(b'composition.project_mail_reply_forward_source(')
            < source.index(b'composition.project_ux09_security(')
            and source.count(b'composition.verify_current(root)') == 1,
            'Mail backup predecessor does not project current source before UX-09')


def project_mail_backup_projection_fix(root, path, current):
    """Restore the exact failed Application-head helper for older receipts."""
    if path != MAIL_BACKUP_PROJECTION_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_BACKUP_PROJECTION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_BACKUP_PROJECTION_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail backup-projection fix changed before predecessor projection')
    return base


def validate_mail_artifact_projection_fix(root):
    """Bind the public-identity artifact projection after its preserved Application failure."""
    require(git(root, 'rev-parse', MAIL_ARTIFACT_PROJECTION_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_ARTIFACT_PROJECTION_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_ARTIFACT_PROJECTION_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_ARTIFACT_PROJECTION_FIX_SOURCE_TREE,
            'Mail artifact-projection source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ARTIFACT_PROJECTION_FIX_SOURCE).decode().split()
            == [MAIL_ARTIFACT_PROJECTION_FIX_SOURCE, MAIL_ARTIFACT_PROJECTION_FIX_BASE],
            'Mail artifact-projection fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ARTIFACT_PROJECTION_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ARTIFACT_PROJECTION_FIX_BASE,
                MAIL_ARTIFACT_PROJECTION_FIX_SOURCE).decode().splitlines()
            == ['M\t' + MAIL_ARTIFACT_PROJECTION_FIX_PATH],
            'Mail artifact-projection fix changed another path or status')
    base_entries = entries(root, MAIL_ARTIFACT_PROJECTION_FIX_BASE)
    source_entries = entries(root, MAIL_ARTIFACT_PROJECTION_FIX_SOURCE)
    path = MAIL_ARTIFACT_PROJECTION_FIX_PATH
    current = project_mail_artifact_overlap_fix(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', MAIL_ARTIFACT_PROJECTION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ARTIFACT_PROJECTION_FIX_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_mail_artifact_projection_fix(root, path, current) == base
            and project_mail_artifact_projection_fix(root, path, base) == base,
            'Mail artifact-projection source identity or projection changed')
    require(source.count(b'def project_mail_reply_forward_artifact_predecessor(path, current):') == 1
            and source.count(b'composition.project_mail_reply_forward_source(ROOT, path, current)') == 1
            and source.count(b'current=project_mail_reply_forward_artifact_predecessor(path,current)') == 1
            and source.index(b'current=project_mail_reply_forward_artifact_predecessor(path,current)')
            < source.index(b'current=project_private_notes_artifact_predecessor(path,current)'),
            'Mail public-identity artifact projection is not exact and ordered')


def project_mail_artifact_projection_fix(root, path, current):
    """Restore the exact failed Application-head artifact proof for older receipts."""
    current = project_mail_artifact_overlap_fix(root, path, current)
    if path != MAIL_ARTIFACT_PROJECTION_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ARTIFACT_PROJECTION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ARTIFACT_PROJECTION_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail artifact-projection fix changed before predecessor projection')
    return base


def validate_mail_artifact_overlap_fix(root):
    """Bind complete mail-overlay projection after its preserved Application failure."""
    require(git(root, 'rev-parse', MAIL_ARTIFACT_OVERLAP_FIX_BASE + '^{tree}').decode().strip()
            == MAIL_ARTIFACT_OVERLAP_FIX_BASE_TREE
            and git(root, 'rev-parse', MAIL_ARTIFACT_OVERLAP_FIX_SOURCE + '^{tree}').decode().strip()
            == MAIL_ARTIFACT_OVERLAP_FIX_SOURCE_TREE,
            'Mail artifact-overlap source tree changed')
    require(git(root, 'rev-list', '--parents', '-n', '1',
                MAIL_ARTIFACT_OVERLAP_FIX_SOURCE).decode().split()
            == [MAIL_ARTIFACT_OVERLAP_FIX_SOURCE, MAIL_ARTIFACT_OVERLAP_FIX_BASE],
            'Mail artifact-overlap fix is not the direct reviewed successor')
    git(root, 'merge-base', '--is-ancestor', MAIL_ARTIFACT_OVERLAP_FIX_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                MAIL_ARTIFACT_OVERLAP_FIX_BASE,
                MAIL_ARTIFACT_OVERLAP_FIX_SOURCE).decode().splitlines()
            == ['M\t' + MAIL_ARTIFACT_OVERLAP_FIX_PATH],
            'Mail artifact-overlap fix changed another path or status')
    base_entries = entries(root, MAIL_ARTIFACT_OVERLAP_FIX_BASE)
    source_entries = entries(root, MAIL_ARTIFACT_OVERLAP_FIX_SOURCE)
    path = MAIL_ARTIFACT_OVERLAP_FIX_PATH
    current = project_runner_application_composition_partition(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', MAIL_ARTIFACT_OVERLAP_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ARTIFACT_OVERLAP_FIX_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_mail_artifact_overlap_fix(root, path, current) == base
            and project_mail_artifact_overlap_fix(root, path, base) == base,
            'Mail artifact-overlap source identity or projection changed')
    contract = json.loads(git(root, 'cat-file', 'blob',
                              MAIL_ARTIFACT_OVERLAP_FIX_SOURCE + ':tools/acceptance-contract.json'))
    overlap = set()
    for section in ('client_identity_native_command_extension',
                    'database_schema_native_command_extension'):
        overlap.update(set(contract[section]['source_sha256']) & MAIL_REPLY_FORWARD_MODIFIED)
    require(overlap == {
        'tests/browser_acceptance.py',
        'site/app/settings/database_schemas.php',
        'tests/database_schema_registry_policy.php',
        'tests/database_schema_view_policy.php',
    } and source.count(b'current=project_mail_reply_forward_artifact_predecessor(path,current)') == 1
      and source.count(b'retained=project_mail_reply_forward_artifact_predecessor(path,retained)') == 1
      and source.count(b'return composition.project_mail_reply_forward_source(ROOT, path, current)') == 1,
            'Mail artifact proof does not project the complete bounded receipt overlap')


def project_mail_artifact_overlap_fix(root, path, current):
    """Restore the exact failed Application-head artifact proof for predecessor checks."""
    current = project_runner_application_composition_partition(root, path, current)
    if path != MAIL_ARTIFACT_OVERLAP_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_ARTIFACT_OVERLAP_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_ARTIFACT_OVERLAP_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail artifact-overlap fix changed before predecessor projection')
    return base


def validate_notification_health_source(root):
    """Bind the read-only notification health component and lead-owned route."""
    identities = (
        (NOTIFICATION_HEALTH_BASE, NOTIFICATION_HEALTH_BASE_TREE),
        (NOTIFICATION_HEALTH_COMPONENT, NOTIFICATION_HEALTH_COMPONENT_TREE),
        (NOTIFICATION_HEALTH_ROUTER, NOTIFICATION_HEALTH_ROUTER_TREE),
        (NOTIFICATION_HEALTH_SOURCE, NOTIFICATION_HEALTH_SOURCE_TREE),
    )
    for commit, tree in identities:
        require(git(root, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Notification health source tree changed: ' + commit)
    require(git(root, 'show', '-s', '--format=%P',
                NOTIFICATION_HEALTH_COMPONENT).decode().strip().split()
            == [NOTIFICATION_HEALTH_BASE]
            and git(root, 'show', '-s', '--format=%P',
                    NOTIFICATION_HEALTH_ROUTER).decode().strip().split()
            == [NOTIFICATION_HEALTH_COMPONENT]
            and git(root, 'show', '-s', '--format=%P',
                    NOTIFICATION_HEALTH_SOURCE).decode().strip().split()
            == [NOTIFICATION_HEALTH_ROUTER],
            'Notification health source ancestry changed')
    git(root, 'merge-base', '--is-ancestor', NOTIFICATION_HEALTH_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  NOTIFICATION_HEALTH_BASE, NOTIFICATION_HEALTH_SOURCE).decode().splitlines()
    require(changes == [
        'A\t' + 'docs/agent-handoffs/2026-11-16-codex-notification-health-component.md',
        'M\t' + 'site/admin/controllers/router.php',
        'M\t' + 'site/admin/views/health.php',
        'M\t' + 'site/install/inventory.generated.php',
        'M\t' + 'tests/notification_center_browser.py',
        'M\t' + 'tests/notification_center_native.php',
        'A\t' + 'tests/notification_health_view_contract.php',
    ], 'Notification health source changed a different path or status set')
    base_entries = entries(root, NOTIFICATION_HEALTH_BASE)
    source_entries = entries(root, NOTIFICATION_HEALTH_SOURCE)
    for path in sorted(NOTIFICATION_HEALTH_MODIFIED):
        current = project_project_private_notes_source(root, path, ordinary(root, path))
        current = project_notification_metric_label_source(root, path, current)
        source = git(root, 'cat-file', 'blob', NOTIFICATION_HEALTH_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', NOTIFICATION_HEALTH_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_notification_health_source(root, path, current) == base
                and project_notification_health_source(root, path, base) == base,
                'Notification health source identity or projection changed: ' + path)
    require(not NOTIFICATION_HEALTH_ADDITIONS & set(base_entries)
            and NOTIFICATION_HEALTH_ADDITIONS <= set(source_entries),
            'Notification health additions did not originate in the source range')
    for path in sorted(NOTIFICATION_HEALTH_ADDITIONS):
        require(project_notification_metric_label_source(root, path, ordinary(root, path))
                == git(root, 'cat-file', 'blob', NOTIFICATION_HEALTH_SOURCE + ':' + path)
                and mode(root, path) == source_entries[path][0] == '100644',
                'Notification health addition identity changed: ' + path)
    require(ordinary(root, INVENTORY) == canonical_inventory(root),
            'Notification health installer inventory is not canonical')


def project_notification_health_source(root, path, current):
    """Restore verified resulting-main bytes for predecessor source proofs."""
    current = project_project_private_notes_source(root, path, current)
    current = project_notification_metric_label_source(root, path, current)
    if path not in NOTIFICATION_HEALTH_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', NOTIFICATION_HEALTH_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', NOTIFICATION_HEALTH_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current notification health source changed before predecessor projection: ' + path)
    return base


def validate_project_private_notes_source(root):
    """Bind isolated immutable project notes to exact resulting-main source."""
    identities = (
        (PROJECT_PRIVATE_NOTES_BASE, PROJECT_PRIVATE_NOTES_BASE_TREE),
        (PROJECT_PRIVATE_NOTES_MODEL, PROJECT_PRIVATE_NOTES_MODEL_TREE),
        (PROJECT_PRIVATE_NOTES_IMPLEMENTATION, PROJECT_PRIVATE_NOTES_IMPLEMENTATION_TREE),
        (PROJECT_PRIVATE_NOTES_HARDENING, PROJECT_PRIVATE_NOTES_HARDENING_TREE),
        (PROJECT_PRIVATE_NOTES_SOURCE, PROJECT_PRIVATE_NOTES_SOURCE_TREE),
    )
    for commit, tree in identities:
        require(git(root, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Project private notes source tree changed: ' + commit)
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_MODEL).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_BASE]
            and git(root, 'show', '-s', '--format=%P',
                    PROJECT_PRIVATE_NOTES_IMPLEMENTATION).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_MODEL]
            and git(root, 'show', '-s', '--format=%P',
                    PROJECT_PRIVATE_NOTES_HARDENING).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_IMPLEMENTATION]
            and git(root, 'show', '-s', '--format=%P',
                    PROJECT_PRIVATE_NOTES_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_HARDENING],
            'Project private notes source ancestry changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  PROJECT_PRIVATE_NOTES_BASE, PROJECT_PRIVATE_NOTES_SOURCE).decode().splitlines()
    require(changes == [
        'M\t.github/workflows/application-tests.yml',
        'A\tfoundation/projects/ProjectPrivateNote.php',
        'M\tsite/admin/controllers/router.php',
        'M\tsite/admin/controllers/studio.php',
        'M\tsite/admin/views/studio/job.php',
        'A\tsite/admin/views/studio/private_notes.php',
        'M\tsite/app/notifications/events.php',
        'A\tsite/app/projects/private_notes.php',
        'M\tsite/app/settings/database_schemas.php',
        'M\tsite/app/staff/accounts.php',
        'A\tsite/database/migrations/project_private_notes_migrate.php',
        'M\tsite/database/schema/base.php',
        'A\tsite/database/schema/project_private_notes_schema.php',
        'A\tsite/database/schema/project_private_notes_validate_schema.php',
        'A\tsite/includes/foundation/projects/ProjectPrivateNote.php',
        'M\tsite/install/inventory.generated.php',
        'M\ttests/client_authority_native.php',
        'M\ttests/database_schema_registry_policy.php',
        'M\ttests/database_schema_view_policy.php',
        'A\ttests/project-notes/records.php',
        'A\ttests/project_private_notes_native.php',
        'A\ttests/project_private_notes_policy.php',
        'A\ttests/project_private_notes_view.php',
        'M\ttools/build.py',
    ], 'Project private notes source changed a different path or status set')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_SOURCE)
    for path in sorted(PROJECT_PRIVATE_NOTES_MODIFIED):
        current = project_runner_application_build_completion(root, path, ordinary(root, path))
        current = project_mail_reply_forward_source(root, path, current)
        source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_project_private_notes_source(root, path, current) == base
                and project_project_private_notes_source(root, path, base) == base,
                'Project private notes source identity or projection changed: ' + path)
    require(not PROJECT_PRIVATE_NOTES_ADDITIONS & set(base_entries)
            and PROJECT_PRIVATE_NOTES_ADDITIONS <= set(source_entries),
            'Project private notes additions did not originate in the source range')
    for path in sorted(PROJECT_PRIVATE_NOTES_ADDITIONS):
        current = project_project_private_notes_native_fixture_source(root, path, ordinary(root, path))
        require(current
                == git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_SOURCE + ':' + path)
                and mode(root, path) == source_entries[path][0] == '100644',
                'Project private notes addition identity changed: ' + path)
    require(ordinary(root, INVENTORY) == canonical_inventory(root),
            'Project private notes installer inventory is not canonical')


def project_project_private_notes_source(root, path, current):
    """Restore exact resulting-main bytes for predecessor source proofs."""
    current = project_runner_application_build_completion(root, path, current)
    current = project_mail_reply_forward_source(root, path, current)
    current = project_project_private_notes_evidence_source(root, path, current)
    if path not in PROJECT_PRIVATE_NOTES_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes source changed before predecessor projection: ' + path)
    return base


def validate_project_private_notes_evidence_source(root):
    """Bind the first-attempt acceptance inventory correction to its failed head."""
    require(git(root, 'rev-parse', PROJECT_PRIVATE_NOTES_EVIDENCE_BASE + '^{tree}').decode().strip()
            == PROJECT_PRIVATE_NOTES_EVIDENCE_BASE_TREE
            and git(root, 'rev-parse',
                    PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE + '^{tree}').decode().strip()
            == PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE_TREE,
            'Project private notes evidence source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_EVIDENCE_BASE],
            'Project private notes evidence source parent changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  PROJECT_PRIVATE_NOTES_EVIDENCE_BASE,
                  PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(PROJECT_PRIVATE_NOTES_EVIDENCE_MODIFIED)],
            'Project private notes evidence source changed a different path or status set')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_EVIDENCE_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE)
    for path in sorted(PROJECT_PRIVATE_NOTES_EVIDENCE_MODIFIED):
        current = project_project_private_notes_authority_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_EVIDENCE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_project_private_notes_evidence_source(root, path, current) == base
                and project_project_private_notes_evidence_source(root, path, base) == base,
                'Project private notes evidence identity or projection changed: ' + path)
    source_contract = json.loads(git(root, 'cat-file', 'blob',
                                     PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE
                                     + ':tools/acceptance-contract.json'))
    extension = source_contract.get('project_private_notes_native_command_extension', {})
    require(extension.get('predecessor_native_count') == 175
            and len(extension.get('additions', [])) == 4
            and len(extension.get('reports', [])) == 4
            and extension.get('predecessor_reports', {}).get('browser', {}).get('count') == 185
            and extension.get('predecessor_reports', {}).get('final', {}).get('count') == 231,
            'Project private notes acceptance predecessor or evidence inventory changed')
    source_test = git(root, 'cat-file', 'blob',
                      PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE + ':tests/acceptance_artifacts_test.py')
    require(b'Private project note commands are one exact additive extension' in source_test
            and b'Private project note reports retain the exact predecessor inventory' in source_test
            and b"current=[report for report in registry[mode] if report not in project_notes_reports]" in source_test,
            'Project private notes additive acceptance assertions changed')


def project_project_private_notes_evidence_source(root, path, current):
    """Restore the failed first-attempt proof head for predecessor receipts."""
    current = project_project_private_notes_authority_source(root, path, current)
    if path not in PROJECT_PRIVATE_NOTES_EVIDENCE_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_EVIDENCE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_EVIDENCE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes evidence changed before predecessor projection: ' + path)
    return base


def validate_project_private_notes_authority_source(root):
    """Bind the frozen software-authority inventory correction."""
    require(git(root, 'rev-parse', PROJECT_PRIVATE_NOTES_AUTHORITY_BASE + '^{tree}').decode().strip()
            == PROJECT_PRIVATE_NOTES_AUTHORITY_BASE_TREE
            and git(root, 'rev-parse',
                    PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE + '^{tree}').decode().strip()
            == PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE_TREE,
            'Project private notes authority source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_AUTHORITY_BASE],
            'Project private notes authority source parent changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                PROJECT_PRIVATE_NOTES_AUTHORITY_BASE,
                PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE).decode().splitlines()
            == ['M\t' + PROJECT_PRIVATE_NOTES_AUTHORITY_PATH],
            'Project private notes authority correction changed another path or status')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_AUTHORITY_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE)
    path = PROJECT_PRIVATE_NOTES_AUTHORITY_PATH
    current = project_project_private_notes_proof_compat_source(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_AUTHORITY_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_project_private_notes_authority_source(root, path, current) == base
            and project_project_private_notes_authority_source(root, path, base) == base,
            'Project private notes authority identity or projection changed')
    require(source.count(b"project_notes_reports=set(acceptance_contract.get('project_private_notes_native_command_extension',{}).get('reports',[]))") == 1
            and b'item not in project_notes_reports' in source
            and b"('browser', 172, 'fe575824e759ec6cb45237f34dbca9069da2a8dbd137b336b4c9c0e09dbbe57e')" in source
            and b"('final', 218, 'de07b604dc35878b3e337314a5559e902f9bdcf41cb43241d143451388c828f7')" in source,
            'Project private notes authority correction weakened frozen inventory identities')


def project_project_private_notes_authority_source(root, path, current):
    """Restore the reviewed evidence proof head for predecessor receipts."""
    current = project_project_private_notes_proof_compat_source(root, path, current)
    if path != PROJECT_PRIVATE_NOTES_AUTHORITY_PATH:
        return current
    source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_AUTHORITY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_AUTHORITY_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes authority correction changed before predecessor projection')
    return base


def validate_project_private_notes_proof_compat_source(root):
    """Bind the exact proof-only correction after preserved hosted failures."""
    require(git(root, 'rev-parse', PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE + '^{tree}').decode().strip()
            == PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE_TREE
            and git(root, 'rev-parse',
                    PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE + '^{tree}').decode().strip()
            == PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE_TREE,
            'Project private notes proof compatibility source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE],
            'Project private notes proof compatibility source parent changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE,
                PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(PROJECT_PRIVATE_NOTES_PROOF_COMPAT_PATHS)],
            'Project private notes proof compatibility correction changed another path or status')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE)
    for path in sorted(PROJECT_PRIVATE_NOTES_PROOF_COMPAT_PATHS):
        current = project_runner_aws_full_mirror(root, path, ordinary(root, path))
        current = project_project_private_notes_static_intake_source(root, path, current)
        source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_project_private_notes_proof_compat_source(root, path, current) == base
                and project_project_private_notes_proof_compat_source(root, path, base) == base,
                'Project private notes proof compatibility identity or projection changed: ' + path)
    artifact_source = git(root, 'cat-file', 'blob',
                          PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE + ':tests/acceptance_artifacts_test.py')
    workflow_source = git(root, 'cat-file', 'blob',
                          PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE + ':tests/account_notification_workflow_contract.py')
    require(artifact_source.count(b'def project_private_notes_artifact_predecessor(path, current):') == 1
            and artifact_source.count(b'current=project_private_notes_artifact_predecessor(path,current)') == 1
            and artifact_source.count(b'retained=project_private_notes_artifact_predecessor(path,retained)') == 1
            and b'PROJECT_PRIVATE_NOTES_SOURCE_TREE = \'7d064c6d6410a3c6de8eb92aa42fc369333c12b3\'' in artifact_source,
            'Project private notes artifact proof correction is not exact and fail-closed')
    require(workflow_source.count(b'PROTECTED_PROJECT_NOTE_ADDITIONS = (') == 1
            and workflow_source.count(b"protected_predecessor.count(addition) == 1") >= 3
            and workflow_source.count(b"protected.count(after + ''.join(additions)) == 1") == 1,
            'Project private notes Application predecessor proof correction is not exact and ordered')


def project_project_private_notes_proof_compat_source(root, path, current):
    """Restore the failed hosted head for older proof receipts."""
    current = project_runner_aws_full_mirror(root, path, current)
    current = project_project_private_notes_static_intake_source(root, path, current)
    if path not in PROJECT_PRIVATE_NOTES_PROOF_COMPAT_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_PROOF_COMPAT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_PROOF_COMPAT_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes proof compatibility source changed before predecessor projection: ' + path)
    return base


def validate_project_private_notes_static_intake_source(root):
    """Bind the exact static-intake proof correction after its preserved hosted failure."""
    identities = (
        (PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE, PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE_TREE),
        (PROJECT_PRIVATE_NOTES_STATIC_INTAKE_IMPLEMENTATION,
         PROJECT_PRIVATE_NOTES_STATIC_INTAKE_IMPLEMENTATION_TREE),
        (PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE, PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE_TREE),
    )
    for commit, tree in identities:
        require(git(root, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Project private notes static-intake source tree changed: ' + commit)
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_STATIC_INTAKE_IMPLEMENTATION).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE]
            and git(root, 'show', '-s', '--format=%P',
                    PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_STATIC_INTAKE_IMPLEMENTATION],
            'Project private notes static-intake source ancestry changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE,
                PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(PROJECT_PRIVATE_NOTES_STATIC_INTAKE_PATHS)],
            'Project private notes static-intake correction changed another path or status')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE)
    for path in sorted(PROJECT_PRIVATE_NOTES_STATIC_INTAKE_PATHS):
        current = project_mail_artifact_projection_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_project_private_notes_static_intake_source(root, path, current) == base
                and project_project_private_notes_static_intake_source(root, path, base) == base,
                'Project private notes static-intake identity or projection changed: ' + path)
    wiring = git(root, 'cat-file', 'blob',
                 PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE + ':tests/cloud_site_intake_wiring.py')
    artifact = git(root, 'cat-file', 'blob',
                   PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE + ':tests/acceptance_artifacts_test.py')
    require(wiring.count(b"len(project_note_additions) == len(set(project_note_additions)) == 4") == 1
            and wiring.count(b"len(project_note_reports) == len(set(project_note_reports)) == 4") == 1
            and wiring.count(b"all(full_native.count(command) == 1 for command in project_note_additions)") == 1
            and wiring.count(b"all(current.count(name) == 1 for name in project_note_reports)") == 1
            and wiring.count(b"callable(source_projector)") == 1,
            'Project private notes static-intake command/report projection is not exact and fail-closed')
    require(artifact.count(b'def project_private_notes_static_intake_predecessor(path, current):') == 1
            and artifact.count(b'project_private_notes_static_intake_predecessor)') == 1
            and b"PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE_TREE = '0b8f7b33b7579e5841625dab69579e9e5dec7c07'" in artifact,
            'Project private notes static-intake source projection is not exact and required')


def project_project_private_notes_static_intake_source(root, path, current):
    """Restore the preserved failed head for older source receipts."""
    current = project_mail_artifact_projection_fix(root, path, current)
    current = project_project_private_notes_native_fixture_source(root, path, current)
    if path not in PROJECT_PRIVATE_NOTES_STATIC_INTAKE_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_STATIC_INTAKE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_STATIC_INTAKE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes static-intake source changed before predecessor projection: ' + path)
    return base


def validate_project_private_notes_native_fixture_source(root):
    """Bind the exact duplicate-include correction after its preserved hosted failure."""
    identities = (
        (PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE, PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE_TREE),
        (PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_IMPLEMENTATION,
         PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_IMPLEMENTATION_TREE),
        (PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE, PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE_TREE),
    )
    for commit, tree in identities:
        require(git(root, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Project private notes native fixture source tree changed: ' + commit)
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_IMPLEMENTATION).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE]
            and git(root, 'show', '-s', '--format=%P',
                    PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_IMPLEMENTATION],
            'Project private notes native fixture ancestry changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE,
                PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_PATHS)],
            'Project private notes native fixture correction changed another path or status')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE)
    for path in sorted(PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_PATHS):
        current = project_project_private_notes_fk_fixture_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_project_private_notes_native_fixture_source(root, path, current) == base
                and project_project_private_notes_native_fixture_source(root, path, base) == base,
                'Project private notes native fixture identity or projection changed: ' + path)
    fixture = git(root, 'cat-file', 'blob',
                  PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE + ':tests/project_private_notes_native.php')
    contract = json.loads(git(root, 'cat-file', 'blob',
                              PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE + ':tools/acceptance-contract.json'))
    require(fixture.count(b"require_once __DIR__.'/../site/app/clients/workspace.php';") == 1
            and b"require __DIR__.'/../site/app/clients/workspace.php';" not in fixture,
            'Project private notes native fixture does not prevent duplicate workspace loading')
    require(contract['project_private_notes_native_command_extension']['source_sha256']
            ['tests/project_private_notes_native.php'] == digest(fixture),
            'Project private notes native fixture acceptance identity changed')


def project_project_private_notes_native_fixture_source(root, path, current):
    """Restore the preserved static-intake proof head for older source receipts."""
    current = project_project_private_notes_fk_fixture_source(root, path, current)
    if path not in PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes native fixture changed before predecessor projection: ' + path)
    return base


def validate_project_private_notes_fk_fixture_source(root):
    """Bind the exact foreign-key-safe missing-index fixture correction."""
    identities = (
        (PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE, PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE_TREE),
        (PROJECT_PRIVATE_NOTES_FK_FIXTURE_IMPLEMENTATION,
         PROJECT_PRIVATE_NOTES_FK_FIXTURE_IMPLEMENTATION_TREE),
        (PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE, PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE_TREE),
    )
    for commit, tree in identities:
        require(git(root, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Project private notes FK fixture source tree changed: ' + commit)
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_FK_FIXTURE_IMPLEMENTATION).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE]
            and git(root, 'show', '-s', '--format=%P',
                    PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_FK_FIXTURE_IMPLEMENTATION],
            'Project private notes FK fixture ancestry changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE,
                PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(PROJECT_PRIVATE_NOTES_FK_FIXTURE_PATHS)],
            'Project private notes FK fixture correction changed another path or status')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE)
    for path in sorted(PROJECT_PRIVATE_NOTES_FK_FIXTURE_PATHS):
        current = project_project_private_notes_notification_fixture_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_project_private_notes_fk_fixture_source(root, path, current) == base
                and project_project_private_notes_fk_fixture_source(root, path, base) == base,
                'Project private notes FK fixture identity or projection changed: ' + path)
    fixture = git(root, 'cat-file', 'blob',
                  PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE + ':tests/project_private_notes_native.php')
    contract = json.loads(git(root, 'cat-file', 'blob',
                              PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE + ':tools/acceptance-contract.json'))
    require(fixture.count(b'ADD INDEX fixture_project_fk(project_id), DROP INDEX project_notes') == 1
            and fixture.count(b'ADD INDEX project_notes(project_id,id), DROP INDEX fixture_project_fk') == 1,
            'Project private notes FK fixture does not preserve the relation while testing exact indexes')
    require(contract['project_private_notes_native_command_extension']['source_sha256']
            ['tests/project_private_notes_native.php'] == digest(fixture),
            'Project private notes FK fixture acceptance identity changed')


def project_project_private_notes_fk_fixture_source(root, path, current):
    """Restore the accepted duplicate-include proof head for older source receipts."""
    current = project_project_private_notes_notification_fixture_source(root, path, current)
    if path not in PROJECT_PRIVATE_NOTES_FK_FIXTURE_PATHS:
        return current
    source = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_FK_FIXTURE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROJECT_PRIVATE_NOTES_FK_FIXTURE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes FK fixture changed before predecessor projection: ' + path)
    return base


def validate_project_private_notes_notification_fixture_source(root):
    """Bind the exact enabled-notification-table fixture correction."""
    identities = (
        (PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE,
         PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE_TREE),
        (PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_IMPLEMENTATION,
         PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_IMPLEMENTATION_TREE),
        (PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE,
         PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE_TREE),
    )
    for commit, tree in identities:
        require(git(root, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Project private notes notification fixture source tree changed: ' + commit)
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_IMPLEMENTATION).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE]
            and git(root, 'show', '-s', '--format=%P',
                    PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_IMPLEMENTATION],
            'Project private notes notification fixture ancestry changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE,
                PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_PATHS)],
            'Project private notes notification fixture changed another path or status')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE)
    for path in sorted(PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_PATHS):
        current = project_project_private_notes_stale_fixture_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob',
                     PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob',
                   PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_project_private_notes_notification_fixture_source(root, path, current) == base
                and project_project_private_notes_notification_fixture_source(root, path, base) == base,
                'Project private notes notification fixture identity or projection changed: ' + path)
    fixture = git(root, 'cat-file', 'blob',
                  PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE
                  + ':tests/project_private_notes_native.php')
    contract = json.loads(git(root, 'cat-file', 'blob',
                              PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE
                              + ':tools/acceptance-contract.json'))
    require(fixture.count(
                b"require_once __DIR__.'/../site/database/schema/notification_feed_schema.php';") == 1
            and fixture.count(
                b"foreach(notification_feed_schema(config()['db']['prefix']) as $sql)db()->exec($sql);") == 1
            and fixture.count(b"save_setting('notification_feed_version','1');") == 1,
            'Project private notes notification fixture is not exact and enabled')
    require(contract['project_private_notes_native_command_extension']['source_sha256']
            ['tests/project_private_notes_native.php'] == digest(fixture),
            'Project private notes notification fixture acceptance identity changed')


def project_project_private_notes_notification_fixture_source(root, path, current):
    """Restore the accepted FK-safe fixture proof head for older source receipts."""
    current = project_project_private_notes_stale_fixture_source(root, path, current)
    if path not in PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_PATHS:
        return current
    source = git(root, 'cat-file', 'blob',
                 PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               PROJECT_PRIVATE_NOTES_NOTIFICATION_FIXTURE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes notification fixture changed before predecessor projection: '
            + path)
    return base


def validate_project_private_notes_stale_fixture_source(root):
    """Bind the exact subprocess stale-session evidence correction."""
    identities = (
        (PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE,
         PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE_TREE),
        (PROJECT_PRIVATE_NOTES_STALE_FIXTURE_IMPLEMENTATION,
         PROJECT_PRIVATE_NOTES_STALE_FIXTURE_IMPLEMENTATION_TREE),
        (PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE,
         PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE_TREE),
    )
    for commit, tree in identities:
        require(git(root, 'rev-parse', commit + '^{tree}').decode().strip() == tree,
                'Project private notes stale fixture source tree changed: ' + commit)
    require(git(root, 'show', '-s', '--format=%P',
                PROJECT_PRIVATE_NOTES_STALE_FIXTURE_IMPLEMENTATION).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE]
            and git(root, 'show', '-s', '--format=%P',
                    PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE).decode().strip().split()
            == [PROJECT_PRIVATE_NOTES_STALE_FIXTURE_IMPLEMENTATION],
            'Project private notes stale fixture ancestry changed')
    git(root, 'merge-base', '--is-ancestor', PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE,
                PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE).decode().splitlines()
            == ['M\t' + path for path in sorted(PROJECT_PRIVATE_NOTES_STALE_FIXTURE_PATHS)],
            'Project private notes stale fixture changed another path or status')
    base_entries = entries(root, PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE)
    source_entries = entries(root, PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE)
    for path in sorted(PROJECT_PRIVATE_NOTES_STALE_FIXTURE_PATHS):
        current = project_runner_application_current_artifact_binding(
            root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob',
                     PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob',
                   PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_project_private_notes_stale_fixture_source(root, path, current) == base
                and project_project_private_notes_stale_fixture_source(root, path, base) == base,
                'Project private notes stale fixture identity or projection changed: ' + path)
    fixture = git(root, 'cat-file', 'blob',
                  PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE
                  + ':tests/project_private_notes_native.php')
    contract = json.loads(git(root, 'cat-file', 'blob',
                              PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE
                              + ':tools/acceptance-contract.json'))
    require(fixture.count(
                b"if(getenv('CWP_PROJECT_PRIVATE_NOTES_STALE_WORKER')==='1'){") == 1
            and fixture.count(b'$process=proc_open([PHP_BINARY,__FILE__]') == 1
            and fixture.count(
                b"query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$replacementVersion]);") == 1
            and fixture.count(
                b"query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$staleVersion]);") == 1
            and fixture.count(b"LOCK_EX)!==strlen($report)") == 1
            and b"$_SESSION['auth_version']=str_repeat('0',32)" not in fixture,
            'Project private notes stale fixture is not exact and fail closed')
    require(contract['project_private_notes_native_command_extension']['source_sha256']
            ['tests/project_private_notes_native.php'] == digest(fixture),
            'Project private notes stale fixture acceptance identity changed')


def project_project_private_notes_stale_fixture_source(root, path, current):
    """Restore the accepted notification fixture proof head for older source receipts."""
    current = project_runner_application_current_artifact_binding(root, path, current)
    if path not in PROJECT_PRIVATE_NOTES_STALE_FIXTURE_PATHS:
        return current
    source = git(root, 'cat-file', 'blob',
                 PROJECT_PRIVATE_NOTES_STALE_FIXTURE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               PROJECT_PRIVATE_NOTES_STALE_FIXTURE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current project private notes stale fixture changed before predecessor projection: '
            + path)
    return base


def validate_domain_expiry_port_source(root):
    """Bind the finite dynamic service-port correction after its preserved failure."""
    require(git(root, 'rev-parse', DOMAIN_EXPIRY_PORT_BASE + '^{tree}').decode().strip()
            == DOMAIN_EXPIRY_PORT_BASE_TREE
            and git(root, 'rev-parse', DOMAIN_EXPIRY_PORT_SOURCE + '^{tree}').decode().strip()
            == DOMAIN_EXPIRY_PORT_SOURCE_TREE,
            'Domain expiry service-port source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DOMAIN_EXPIRY_PORT_SOURCE).decode().strip().split()
            == [DOMAIN_EXPIRY_PORT_BASE],
            'Domain expiry service-port source parent changed')
    git(root, 'merge-base', '--is-ancestor', DOMAIN_EXPIRY_PORT_SOURCE, 'HEAD')
    require(git(root, 'diff', '--name-status', '--no-renames',
                DOMAIN_EXPIRY_PORT_BASE, DOMAIN_EXPIRY_PORT_SOURCE).decode().splitlines()
            == ['M\t' + DOMAIN_EXPIRY_PORT_PATH],
            'Domain expiry service-port source changed a different path or status')
    base_entries = entries(root, DOMAIN_EXPIRY_PORT_BASE)
    source_entries = entries(root, DOMAIN_EXPIRY_PORT_SOURCE)
    current = ordinary(root, DOMAIN_EXPIRY_PORT_PATH)
    source = git(root, 'cat-file', 'blob',
                 DOMAIN_EXPIRY_PORT_SOURCE + ':' + DOMAIN_EXPIRY_PORT_PATH)
    base = git(root, 'cat-file', 'blob',
               DOMAIN_EXPIRY_PORT_BASE + ':' + DOMAIN_EXPIRY_PORT_PATH)
    require(current == source
            and mode(root, DOMAIN_EXPIRY_PORT_PATH)
            == base_entries[DOMAIN_EXPIRY_PORT_PATH][0]
            == source_entries[DOMAIN_EXPIRY_PORT_PATH][0] == '100644'
            and project_domain_expiry_port_source(root, DOMAIN_EXPIRY_PORT_PATH, current) == base
            and project_domain_expiry_port_source(root, DOMAIN_EXPIRY_PORT_PATH, base) == base,
            'Domain expiry service-port source identity or projection changed')


def project_domain_expiry_port_source(root, path, current):
    """Restore the reviewed exact-head notification proof for older receipts."""
    if path != DOMAIN_EXPIRY_PORT_PATH:
        return current
    source = git(root, 'cat-file', 'blob', DOMAIN_EXPIRY_PORT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', DOMAIN_EXPIRY_PORT_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current domain expiry service-port source changed before predecessor projection')
    return base


def validate_proof_runtime_source(root):
    """Bind the finite proof-runtime correction to its preserved timeout."""
    require(git(root, 'rev-parse', PROOF_RUNTIME_BASE + '^{tree}').decode().strip()
            == PROOF_RUNTIME_BASE_TREE
            and git(root, 'rev-parse', PROOF_RUNTIME_SOURCE + '^{tree}').decode().strip()
            == PROOF_RUNTIME_SOURCE_TREE,
            'Proof-runtime source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                PROOF_RUNTIME_SOURCE).decode().strip().split() == [PROOF_RUNTIME_BASE],
            'Proof-runtime source parent changed')
    git(root, 'merge-base', '--is-ancestor', PROOF_RUNTIME_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  PROOF_RUNTIME_BASE, PROOF_RUNTIME_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(PROOF_RUNTIME_MODIFIED)],
            'Proof-runtime source changed a different path or status set')
    base_entries = entries(root, PROOF_RUNTIME_BASE)
    source_entries = entries(root, PROOF_RUNTIME_SOURCE)
    for path in sorted(PROOF_RUNTIME_PROJECTED):
        current = project_project_private_notes_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', PROOF_RUNTIME_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', PROOF_RUNTIME_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_proof_runtime_source(root, path, current) == base
                and project_proof_runtime_source(root, path, base) == base,
                'Proof-runtime source identity or projection changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob', PROOF_RUNTIME_SOURCE + ':' + VERIFIER)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    PROOF_RUNTIME_SOURCE + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and b'_IMMUTABLE_GIT_RESULTS = {}' in source_verifier
            and b'_PINNED_HEADS = {}' in source_verifier
            and b'def immutable_git_query(args):' in source_verifier
            and b'def pin_head(root):' in source_verifier,
            'Proof-runtime implementation or source receipt changed')
    source_workflow = git(root, 'cat-file', 'blob',
                          PROOF_RUNTIME_SOURCE + ':.github/workflows/application-tests.yml')
    require(source_workflow.count(b'    timeout-minutes: 180\n') == 1
            and source_workflow.count(b'    timeout-minutes: 105\n') == 1,
            'Proof-runtime source changed unrelated Application timeout budgets')


def project_proof_runtime_source(root, path, current):
    """Restore the exact pre-timeout notification successor for older proofs."""
    current = project_project_private_notes_proof_compat_source(root, path, current)
    current = project_project_private_notes_source(root, path, current)
    if path not in PROOF_RUNTIME_PROJECTED:
        return current
    source = git(root, 'cat-file', 'blob', PROOF_RUNTIME_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROOF_RUNTIME_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current proof-runtime source changed before predecessor projection: ' + path)
    return base


def validate_proof_cache_hardening_source(root):
    """Bind the reviewed moving-ref and replacement-ref cache hardening."""
    require(git(root, 'rev-parse', PROOF_CACHE_HARDENING_BASE + '^{tree}').decode().strip()
            == PROOF_CACHE_HARDENING_BASE_TREE
            and git(root, 'rev-parse',
                    PROOF_CACHE_HARDENING_SOURCE + '^{tree}').decode().strip()
            == PROOF_CACHE_HARDENING_SOURCE_TREE,
            'Proof-cache hardening source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                PROOF_CACHE_HARDENING_SOURCE).decode().strip().split()
            == [PROOF_CACHE_HARDENING_BASE],
            'Proof-cache hardening source parent changed')
    git(root, 'merge-base', '--is-ancestor', PROOF_CACHE_HARDENING_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  PROOF_CACHE_HARDENING_BASE,
                  PROOF_CACHE_HARDENING_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(PROOF_CACHE_HARDENING_MODIFIED)],
            'Proof-cache hardening changed a different path or status set')
    source_entries = entries(root, PROOF_CACHE_HARDENING_SOURCE)
    require(all(source_entries[path][0] == '100644'
                for path in PROOF_CACHE_HARDENING_MODIFIED),
            'Proof-cache hardening changed a protected source mode')
    source_verifier = git(root, 'cat-file', 'blob',
                          PROOF_CACHE_HARDENING_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            PROOF_CACHE_HARDENING_SOURCE + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    PROOF_CACHE_HARDENING_SOURCE + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and b"['git', '--no-replace-objects'" in source_verifier
            and b'cache_args if immutable else args' in source_verifier
            and source_verifier.count(b'    pin_head(root)\n') >= 2
            and b'Pinned HEAD query executes the pinned object ID' in source_regression
            and b'Refuses Git replacement refs' in source_regression,
            'Proof-cache hardening implementation or receipt changed')


def project_proof_cache_hardening_source(root, path, current):
    """Restore the pre-hardening regression bytes for its older proof source."""
    if path not in PROOF_CACHE_HARDENING_PROJECTED:
        return current
    source = git(root, 'cat-file', 'blob', PROOF_CACHE_HARDENING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', PROOF_CACHE_HARDENING_BASE + ':' + path)
    if (path == REGRESSION
            and ((current == ordinary(root, path)
                  and digest(current) == REGRESSION_SHA256)
                 or current == git(root, 'cat-file', 'blob',
                                   DIAGNOSTIC_ARTIFACT_BATCH_BASE + ':' + path))):
        current = source
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current proof-cache regression changed before predecessor projection')
    return base


def validate_diagnostic_artifact_batch_source(root):
    """Bind the finite one-proof artifact-validation batch to its exact source."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_ARTIFACT_BATCH_BASE + '^{tree}').decode().strip()
            == DIAGNOSTIC_ARTIFACT_BATCH_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_ARTIFACT_BATCH_SOURCE + '^{tree}').decode().strip()
            == DIAGNOSTIC_ARTIFACT_BATCH_SOURCE_TREE,
            'Diagnostic artifact batch source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_ARTIFACT_BATCH_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_ARTIFACT_BATCH_BASE],
            'Diagnostic artifact batch source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_ARTIFACT_BATCH_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_ARTIFACT_BATCH_BASE,
                  DIAGNOSTIC_ARTIFACT_BATCH_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path
                        for path in sorted(DIAGNOSTIC_ARTIFACT_BATCH_MODIFIED)],
            'Diagnostic artifact batch source changed another path or status')
    source_entries = entries(root, DIAGNOSTIC_ARTIFACT_BATCH_SOURCE)
    require(all(source_entries[path][0] == '100644'
                for path in DIAGNOSTIC_ARTIFACT_BATCH_MODIFIED),
            'Diagnostic artifact batch changed a protected source mode')
    for path in sorted(DIAGNOSTIC_ARTIFACT_BATCH_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_ARTIFACT_BATCH_SOURCE + ':' + path)
        require(digest(source) == DIAGNOSTIC_ARTIFACT_BATCH_SHA256[path],
                'Diagnostic artifact batch source bytes changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_ARTIFACT_BATCH_SOURCE + ':' + VERIFIER)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_ARTIFACT_BATCH_SOURCE + ':' + RECEIPT))
    source_artifacts = git(root, 'cat-file', 'blob',
                           DIAGNOSTIC_ARTIFACT_BATCH_SOURCE + ':'
                           + DIAGNOSTIC_ARTIFACT_BATCH_PATH)
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and ordinary(root, DIAGNOSTIC_ARTIFACT_BATCH_PATH) == source_artifacts
            and source_verifier.count(b'def provider_browser_artifact_validations(') == 1
            and source_verifier.count(b'    checks = validate(root)\n') == 1
            and source_verifier.count(b"'projected_validation', callback=module") == 1
            and source_artifacts.count(
                b'composition.provider_browser_artifact_validations(') == 1
            and b'validate_provider_fixtures(ROOT)' not in source_artifacts
            and b'projected_validation(ROOT' not in source_artifacts,
            'Diagnostic artifact batch implementation or source receipt changed')


def project_diagnostic_artifact_batch_source(root, path, current):
    """Restore the accepted PR #401 proof head before older source projections."""
    current = project_diagnostic_artifact_failure_evidence_source(
        root, path, current)
    if (path == REGRESSION
            and ((current == ordinary(root, path)
                  and digest(current) == REGRESSION_SHA256)
                 or current == git(root, 'cat-file', 'blob',
                                   DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_BASE
                                   + ':' + path))):
        return git(root, 'cat-file', 'blob',
                   DIAGNOSTIC_ARTIFACT_BATCH_BASE + ':' + path)
    if path != DIAGNOSTIC_ARTIFACT_BATCH_PATH:
        return current
    source = git(root, 'cat-file', 'blob',
                 DIAGNOSTIC_ARTIFACT_BATCH_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               DIAGNOSTIC_ARTIFACT_BATCH_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current diagnostic artifact batch changed before predecessor projection')
    return base


def validate_diagnostic_artifact_failure_evidence_source(root):
    """Bind exact named-failure preservation after the independent review blocker."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_BASE
                + '^{tree}').decode().strip()
            == DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE_TREE,
            'Diagnostic artifact failure-evidence source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_BASE],
            'Diagnostic artifact failure-evidence source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_BASE,
                  DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_MODIFIED)],
            'Diagnostic artifact failure-evidence source changed another path')
    source_entries = entries(root, DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE)
    for path in sorted(DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source)
                == DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SHA256[path],
                'Diagnostic artifact failure-evidence source changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE
                          + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE
                            + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE
                                    + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and source_verifier.count(
                b'def _extend_provider_artifact_rows(') == 1
            and b"type(row['passed']) is bool" in source_verifier
            and b"row.get('passed') is True" not in source_verifier
            and b'Provider artifact batching retains exact named historical failures'
                in source_regression,
            'Diagnostic artifact named-failure preservation changed')


def project_diagnostic_artifact_failure_evidence_source(root, path, current):
    """Restore the reviewed batch proof before its failure-evidence correction."""
    current = project_diagnostic_blob_batch_source(root, path, current)
    if path != REGRESSION:
        return current
    source = git(root, 'cat-file', 'blob',
                 DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               DIAGNOSTIC_ARTIFACT_FAILURE_EVIDENCE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current diagnostic artifact failure-evidence source changed')
    return base


def project_diagnostic_blob_batch_source(root, path, current):
    """Restore the accepted proof before the immutable blob-batch successor."""
    current = project_diagnostic_projection_cache_race_source(root, path, current)
    if path != REGRESSION:
        return current
    source = git(root, 'cat-file', 'blob',
                 DIAGNOSTIC_BLOB_BATCH_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               DIAGNOSTIC_BLOB_BATCH_BASE + ':' + path)
    proof_base = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_BLOB_LIVE_BINDING_BASE + ':' + path)
    if (current == ordinary(root, path)
            and digest(current) == REGRESSION_SHA256):
        current = source
    if current == proof_base:
        current = source
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current diagnostic blob-batch regression changed')
    return base


def validate_diagnostic_blob_batch_source(root):
    """Bind the bounded immutable reader and terminal projection source."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_BLOB_BATCH_BASE + '^{tree}').decode().strip()
            == DIAGNOSTIC_BLOB_BATCH_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_BLOB_BATCH_SOURCE + '^{tree}').decode().strip()
            == DIAGNOSTIC_BLOB_BATCH_SOURCE_TREE,
            'Diagnostic blob-batch source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_BLOB_BATCH_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_BLOB_BATCH_BASE],
            'Diagnostic blob-batch source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_BLOB_BATCH_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_BLOB_BATCH_BASE,
                  DIAGNOSTIC_BLOB_BATCH_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path
                        for path in sorted(DIAGNOSTIC_BLOB_BATCH_MODIFIED)],
            'Diagnostic blob-batch source changed another path or status')
    source_entries = entries(root, DIAGNOSTIC_BLOB_BATCH_SOURCE)
    for path in sorted(DIAGNOSTIC_BLOB_BATCH_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_BLOB_BATCH_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source) == DIAGNOSTIC_BLOB_BATCH_SHA256[path],
                'Diagnostic blob-batch source changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_BLOB_BATCH_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_BLOB_BATCH_SOURCE + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_BLOB_BATCH_SOURCE + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and source_verifier.count(
                b"['git', '--no-replace-objects', 'cat-file', '--batch']") == 1
            and b'TREE_ENTRY_CACHE_LIMIT = 16' in source_verifier
            and source_verifier.count(
                b'def project_component_union_source(') == 1
            and b'Immutable blob batch verifies exact binary bytes and request framing'
                in source_regression
            and b'Immutable parsed-tree cache remains bounded after the complete proof'
                in source_regression
            and b'Component union applies each terminal projection chain once in exact order'
                in source_regression,
            'Diagnostic blob-batch implementation or source receipt changed')


def project_diagnostic_blob_live_binding_source(root, path, current):
    """Restore the performance proof before the live-helper binding correction."""
    if path != REGRESSION:
        return current
    source = git(root, 'cat-file', 'blob',
                 DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               DIAGNOSTIC_BLOB_LIVE_BINDING_BASE + ':' + path)
    if (current == ordinary(root, path)
            and digest(current) == REGRESSION_SHA256):
        current = source
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current diagnostic live-binding regression changed')
    return base


def validate_diagnostic_blob_live_binding_source(root):
    """Bind the review correction that refuses a live helper rollback."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_BLOB_LIVE_BINDING_BASE + '^{tree}').decode().strip()
            == DIAGNOSTIC_BLOB_LIVE_BINDING_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE + '^{tree}').decode().strip()
            == DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE_TREE,
            'Diagnostic blob live-binding source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_BLOB_LIVE_BINDING_BASE],
            'Diagnostic blob live-binding source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_BLOB_LIVE_BINDING_BASE,
                  DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_BLOB_LIVE_BINDING_MODIFIED)],
            'Diagnostic blob live-binding source changed another path')
    source_entries = entries(root, DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE)
    for path in sorted(DIAGNOSTIC_BLOB_LIVE_BINDING_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source) == DIAGNOSTIC_BLOB_LIVE_BINDING_SHA256[path],
                'Diagnostic blob live-binding source changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and b'ordinary(root, DIAGNOSTIC_ARTIFACT_BATCH_PATH) == source_artifacts'
                in source_verifier
            and b'Refuses exact predecessor diagnostic artifact helper rollback'
                in source_regression,
            'Diagnostic blob live-binding implementation or receipt changed')


def project_diagnostic_projection_cache_source(root, path, current):
    """Restore the accepted PR head before the bounded projection-cache source."""
    if path != REGRESSION:
        return project_diagnostic_blob_live_binding_source(root, path, current)
    source = git(root, 'cat-file', 'blob',
                 DIAGNOSTIC_PROJECTION_CACHE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               DIAGNOSTIC_PROJECTION_CACHE_BASE + ':' + path)
    live_binding_source = git(root, 'cat-file', 'blob',
                              DIAGNOSTIC_BLOB_LIVE_BINDING_SOURCE + ':' + path)
    if (current == ordinary(root, path)
            and digest(current) == REGRESSION_SHA256):
        current = source
    if current == source:
        current = base
    if current == base:
        current = live_binding_source
    return project_diagnostic_blob_live_binding_source(root, path, current)


def validate_diagnostic_projection_cache_source(root):
    """Bind the exact bounded cache implementation to its accepted PR parent."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_PROJECTION_CACHE_BASE + '^{tree}').decode().strip()
            == DIAGNOSTIC_PROJECTION_CACHE_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_PROJECTION_CACHE_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_PROJECTION_CACHE_SOURCE_TREE,
            'Diagnostic projection-cache source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_PROJECTION_CACHE_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_PROJECTION_CACHE_BASE],
            'Diagnostic projection-cache source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_PROJECTION_CACHE_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_PROJECTION_CACHE_BASE,
                  DIAGNOSTIC_PROJECTION_CACHE_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_PROJECTION_CACHE_MODIFIED)],
            'Diagnostic projection-cache source changed another path')
    source_entries = entries(root, DIAGNOSTIC_PROJECTION_CACHE_SOURCE)
    for path in sorted(DIAGNOSTIC_PROJECTION_CACHE_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_PROJECTION_CACHE_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source) == DIAGNOSTIC_PROJECTION_CACHE_SHA256[path],
                'Diagnostic projection-cache source changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_PROJECTION_CACHE_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_PROJECTION_CACHE_SOURCE + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_PROJECTION_CACHE_SOURCE
                                    + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and source_verifier.count(
                b'def verification_projection_cache(') == 1
            and b'_PROJECTION_CACHE_LIMIT = 32768' in source_verifier
            and b'live = ordinary(Path(root_value), path)' in source_verifier
            and b'key = (call_root, name, path, len(current), digest(current),'
                in source_verifier
            and b'_ACTIVE_ORDINARY' not in source_verifier
            and b'Bound projection cache preserves fresh mutable-worktree reads'
                in source_regression
            and b'Exact projection memoization is bounded and cleared'
                in source_regression,
            'Diagnostic projection-cache implementation or receipt changed')


def project_diagnostic_projection_cache_race_source(root, path, current):
    """Restore the first cache proof before its mutable-source race correction."""
    if path != REGRESSION:
        return project_diagnostic_projection_cache_source(root, path, current)
    source = git(root, 'cat-file', 'blob',
                 DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE + ':' + path)
    prior_source = git(root, 'cat-file', 'blob',
                       DIAGNOSTIC_PROJECTION_CACHE_SOURCE + ':' + path)
    if (current == ordinary(root, path)
            and digest(current) == REGRESSION_SHA256):
        current = source
    if current == source:
        current = base
    if current == base:
        current = prior_source
    return project_diagnostic_projection_cache_source(root, path, current)


def validate_diagnostic_projection_cache_race_source(root):
    """Bind the exact correction that brackets cached projections with reads."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE
                + '^{tree}').decode().strip()
            == DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE_TREE,
            'Diagnostic projection-cache race source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE],
            'Diagnostic projection-cache race source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_PROJECTION_CACHE_RACE_BASE,
                  DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_PROJECTION_CACHE_RACE_MODIFIED)],
            'Diagnostic projection-cache race source changed another path')
    source_entries = entries(root, DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE)
    for path in sorted(DIAGNOSTIC_PROJECTION_CACHE_RACE_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source)
                == DIAGNOSTIC_PROJECTION_CACHE_RACE_SHA256[path],
                'Diagnostic projection-cache race source changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE
                            + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_PROJECTION_CACHE_RACE_SOURCE
                                    + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and source_verifier.count(
                b"require(ordinary(Path(root_value), path) == live,") == 2
            and b'cached.move_to_end(key)' in source_verifier
            and b'Composition source changed during cached projection'
                in source_verifier
            and b'Composition source changed during projection'
                in source_verifier
            and b'Bound projection cache refuses a mutable-source race'
                in source_regression
            and b'assert len(calls) == 3' in source_regression
            and source_verifier.count(b'_ACTIVE_ORDINARY') == 1,
            'Diagnostic projection-cache race correction or receipt changed')


def validate_diagnostic_tracked_preflight_source(root):
    """Bind the fail-closed tracked-drift preflight to its timed-out parent."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_TRACKED_PREFLIGHT_BASE + '^{tree}').decode().strip()
            == DIAGNOSTIC_TRACKED_PREFLIGHT_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE_TREE,
            'Diagnostic tracked-source preflight tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_TRACKED_PREFLIGHT_BASE],
            'Diagnostic tracked-source preflight parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_TRACKED_PREFLIGHT_BASE,
                  DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_TRACKED_PREFLIGHT_MODIFIED)],
            'Diagnostic tracked-source preflight changed another path')
    source_entries = entries(root, DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE)
    for path in sorted(DIAGNOSTIC_TRACKED_PREFLIGHT_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source)
                == DIAGNOSTIC_TRACKED_PREFLIGHT_SHA256[path],
                'Diagnostic tracked-source preflight changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE
                            + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_TRACKED_PREFLIGHT_SOURCE
                                    + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and source_verifier.count(b'def parse_tracked_source_drift(') == 1
            and source_verifier.count(b'def tracked_source_preflight(') == 1
            and b"'git', '--no-replace-objects', 'diff', '--no-ext-diff', '--no-renames',"
                in source_verifier
            and b"'--name-only', '-z', head, '--'" in source_verifier
            and b'allowed = {RECEIPT, VERIFIER, REGRESSION}' in source_verifier
            and b"require(result.returncode == 0 and not result.stderr,"
                in source_verifier
            and b'    ensure_history(root)\n    pin_head(root)\n    tracked_source_preflight(root)\n'
                in source_verifier
            and source_verifier.count(
                b"'Composition proof file changed during") == 2
            and b'Tracked-source preflight parses only canonical NUL-delimited paths'
                in source_regression
            and b'Refuses unstaged tracked drift before historical validation'
                in source_regression
            and b'Refuses staged tracked drift before historical projection'
                in source_regression
            and b'assert not reached_historical_validation' in source_regression,
            'Diagnostic tracked-source preflight implementation or receipt changed')


def validate_diagnostic_tracked_preflight_cancellation_source(root):
    """Bind independent index/worktree checks and the exact proof index."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_BASE
                + '^{tree}').decode().strip()
            == DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE_TREE,
            'Diagnostic tracked-source cancellation correction tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE
                ).decode().strip().split()
            == [DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_BASE],
            'Diagnostic tracked-source cancellation correction parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_BASE,
                  DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE
                  ).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_MODIFIED)],
            'Diagnostic tracked-source cancellation correction changed another path')
    source_entries = entries(
        root, DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE)
    for path in sorted(DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_MODIFIED):
        source = git(
            root, 'cat-file', 'blob',
            DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source)
                == DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SHA256[path],
                'Diagnostic tracked-source cancellation correction changed: '
                + path)
    source_verifier = git(
        root, 'cat-file', 'blob',
        DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE + ':' + VERIFIER)
    source_regression = git(
        root, 'cat-file', 'blob',
        DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE + ':' + REGRESSION)
    source_receipt = json.loads(git(
        root, 'cat-file', 'blob',
        DIAGNOSTIC_TRACKED_PREFLIGHT_CANCELLATION_SOURCE + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and b"('staged', ('diff', '--cached', '--no-ext-diff', '--no-renames',"
                in source_verifier
            and b"('unstaged', ('diff', '--no-ext-diff', '--no-renames',"
                in source_verifier
            and b"['git', '--no-replace-objects', *arguments]" in source_verifier
            and b"['git', '--no-replace-objects', 'ls-files', '--stage', '-z', '--', path]"
                in source_verifier
            and b"git(root, 'cat-file', 'blob', oid) == proof_files[path]"
                in source_verifier
            and source_verifier.count(
                b"mode(root, path) == '100644' and ordinary(root, path) == value") == 2
            and b'tracked_source_preflight(root, proof_files)' in source_verifier
            and b'Refuses offsetting staged and unstaged tracked drift'
                in source_regression
            and b'Refuses staged proof bytes that differ from validated worktree bytes'
                in source_regression
            and b'Refuses a staged executable mode for a proof file'
                in source_regression,
            'Diagnostic tracked-source cancellation correction or receipt changed')


def validate_diagnostic_untracked_preflight_source(root):
    """Bind the early non-ignored untracked-source refusal."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_UNTRACKED_PREFLIGHT_BASE
                + '^{tree}').decode().strip()
            == DIAGNOSTIC_UNTRACKED_PREFLIGHT_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE_TREE,
            'Diagnostic untracked-source preflight tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_UNTRACKED_PREFLIGHT_BASE],
            'Diagnostic untracked-source preflight parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_UNTRACKED_PREFLIGHT_BASE,
                  DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_UNTRACKED_PREFLIGHT_MODIFIED)],
            'Diagnostic untracked-source preflight changed another path')
    source_entries = entries(root, DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE)
    for path in sorted(DIAGNOSTIC_UNTRACKED_PREFLIGHT_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source) == DIAGNOSTIC_UNTRACKED_PREFLIGHT_SHA256[path],
                'Diagnostic untracked-source preflight changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_UNTRACKED_PREFLIGHT_SOURCE
                                    + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and b"'ls-files', '--others',\n        '--exclude-standard', '-z', '--'"
                in source_verifier
            and b'untracked = set(parse_tracked_source_drift(result.stdout))'
                in source_verifier
            and b'require(not untracked - NARRATIVES,' in source_verifier
            and b'Untracked-source preflight precedes expensive historical validation'
                in source_regression
            and b'Ordinary generated test cache does not become a Git/source/package input'
                in source_regression,
            'Diagnostic untracked-source preflight implementation or receipt changed')


def validate_diagnostic_protected_tree_preflight_source(root):
    """Bind ignored-file inventory across every tracked top-level tree."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_BASE
                + '^{tree}').decode().strip()
            == DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE_TREE,
            'Diagnostic protected-tree preflight tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_BASE],
            'Diagnostic protected-tree preflight parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_BASE,
                  DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_MODIFIED)],
            'Diagnostic protected-tree preflight changed another path')
    source_entries = entries(root, DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE)
    for path in sorted(DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source)
                == DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SHA256[path],
                'Diagnostic protected-tree preflight changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE
                            + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_PROTECTED_TREE_PREFLIGHT_SOURCE
                                    + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and b'unexpected_filesystem = actual_paths(root, tracked) - tracked'
                in source_verifier
            and b"areas = {PurePosixPath(path).parts[0] for path in tracked if '/' in path}"
                in source_verifier
            and b'for area in sorted(areas):' in source_verifier
            and b'Refuses ignored source under a tracked operations tree'
                in source_regression
            and b'Ignored operations source is inventoried before historical validation'
                in source_regression
            and b'Ordinary generated test cache does not become a Git/source/package input'
                in source_regression,
            'Diagnostic protected-tree preflight implementation or receipt changed')


def validate_diagnostic_receipt_content_source(root):
    """Bind canonical receipt content before historical reconstruction."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_RECEIPT_CONTENT_BASE + '^{tree}').decode().strip()
            == DIAGNOSTIC_RECEIPT_CONTENT_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_RECEIPT_CONTENT_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_RECEIPT_CONTENT_SOURCE_TREE,
            'Diagnostic receipt-content source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_RECEIPT_CONTENT_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_RECEIPT_CONTENT_BASE],
            'Diagnostic receipt-content source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_RECEIPT_CONTENT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_RECEIPT_CONTENT_BASE,
                  DIAGNOSTIC_RECEIPT_CONTENT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_RECEIPT_CONTENT_MODIFIED)],
            'Diagnostic receipt-content source changed another path')
    source_entries = entries(root, DIAGNOSTIC_RECEIPT_CONTENT_SOURCE)
    for path in sorted(DIAGNOSTIC_RECEIPT_CONTENT_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_RECEIPT_CONTENT_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source) == DIAGNOSTIC_RECEIPT_CONTENT_SHA256[path],
                'Diagnostic receipt-content source changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_RECEIPT_CONTENT_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_RECEIPT_CONTENT_SOURCE + ':' + REGRESSION)
    source_receipt = json.loads(git(root, 'cat-file', 'blob',
                                    DIAGNOSTIC_RECEIPT_CONTENT_SOURCE
                                    + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and b'def receipt_content_digest(data):' in source_verifier
            and b"'verifier_sha256': '',\n        'regression_sha256': '',"
                in source_verifier
            and b'receipt_content_digest(receipt_data) == RECEIPT_CONTENT_SHA256'
                in source_verifier
            and b'Refuses changed canonical receipt content before historical validation'
                in source_regression
            and b'Canonical receipt content is bound before historical validation'
                in source_regression,
            'Diagnostic receipt-content implementation or receipt changed')


def validate_diagnostic_strict_receipt_source(root):
    """Bind duplicate-name and non-finite receipt refusal."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_STRICT_RECEIPT_BASE + '^{tree}').decode().strip()
            == DIAGNOSTIC_STRICT_RECEIPT_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_STRICT_RECEIPT_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_STRICT_RECEIPT_SOURCE_TREE,
            'Diagnostic strict-receipt source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_STRICT_RECEIPT_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_STRICT_RECEIPT_BASE],
            'Diagnostic strict-receipt source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_STRICT_RECEIPT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_STRICT_RECEIPT_BASE,
                  DIAGNOSTIC_STRICT_RECEIPT_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_STRICT_RECEIPT_MODIFIED)],
            'Diagnostic strict-receipt source changed another path')
    source_entries = entries(root, DIAGNOSTIC_STRICT_RECEIPT_SOURCE)
    for path in sorted(DIAGNOSTIC_STRICT_RECEIPT_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_STRICT_RECEIPT_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source) == DIAGNOSTIC_STRICT_RECEIPT_SHA256[path],
                'Diagnostic strict-receipt source changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_STRICT_RECEIPT_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_STRICT_RECEIPT_SOURCE + ':' + REGRESSION)
    source_receipt = strict_json_loads(git(root, 'cat-file', 'blob',
                                           DIAGNOSTIC_STRICT_RECEIPT_SOURCE
                                           + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and b'def strict_json_object(pairs):' in source_verifier
            and b'object_pairs_hook=strict_json_object' in source_verifier
            and b'parse_constant=reject_json_constant' in source_verifier
            and b'receipt_data = strict_json_loads(receipt)' in source_verifier
            and b"refuses('Refuses composition receipt ' + label," in source_regression
            and b"('top-level duplicate name'," in source_regression
            and b"('nested duplicate name'," in source_regression
            and b"('non-finite constant'," in source_regression
            and b'Strict receipt parsing precedes historical validation'
                in source_regression,
            'Diagnostic strict-receipt implementation or receipt changed')


def validate_diagnostic_current_receipt_consumer_source(root):
    """Bind every current receipt consumer to one strict byte bracket."""
    require(git(root, 'rev-parse',
                DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_BASE
                + '^{tree}').decode().strip()
            == DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_BASE_TREE
            and git(root, 'rev-parse',
                    DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE
                    + '^{tree}').decode().strip()
            == DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE_TREE,
            'Diagnostic current-receipt consumer source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE).decode().strip().split()
            == [DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_BASE],
            'Diagnostic current-receipt consumer source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_BASE,
                  DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(
                DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_MODIFIED)],
            'Diagnostic current-receipt consumer source changed another path')
    source_entries = entries(root, DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE)
    for path in sorted(DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_MODIFIED):
        source = git(root, 'cat-file', 'blob',
                     DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE + ':' + path)
        require(source_entries[path][0] == '100644'
                and digest(source)
                == DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SHA256[path],
                'Diagnostic current-receipt consumer source changed: ' + path)
    source_verifier = git(root, 'cat-file', 'blob',
                          DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE + ':' + VERIFIER)
    source_regression = git(root, 'cat-file', 'blob',
                            DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE + ':' + REGRESSION)
    source_receipt = strict_json_loads(git(
        root, 'cat-file', 'blob',
        DIAGNOSTIC_CURRENT_RECEIPT_CONSUMER_SOURCE + ':' + RECEIPT))
    require(source_receipt.get('verifier_sha256') == digest(source_verifier)
            and source_receipt.get('regression_sha256') == digest(source_regression)
            and b'def current_proof_receipt(root):' in source_verifier
            and source_verifier.count(
                b'data, proof_files = current_proof_receipt(root)') == 2
            and b'json.loads(ordinary(root, RECEIPT))' not in source_verifier
            and b'Composition proof file changed during account projection'
                in source_verifier
            and b'Strict receipt loader refuses proof-file change during validation'
                in source_regression
            and b'Every current receipt consumer brackets exact proof bytes'
                in source_regression,
            'Diagnostic current-receipt consumer implementation or receipt changed')


def project_hosting_inbox_reload_completion_source(root, path, current):
    """Restore the first bounded-reload proof before its reviewed completion."""
    if path != HOSTING_INBOX_RELOAD_COMPLETION_PATH:
        return current
    source = git(root, 'cat-file', 'blob',
                 HOSTING_INBOX_RELOAD_COMPLETION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               HOSTING_INBOX_RELOAD_COMPLETION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current hosted inbox reload completion changed before predecessor projection')
    return base


def validate_hosting_inbox_reload_completion_source(root):
    """Bind the two equivalent five-card owner loads found during review."""
    require(git(root, 'rev-parse',
                HOSTING_INBOX_RELOAD_COMPLETION_BASE
                + '^{tree}').decode().strip()
            == HOSTING_INBOX_RELOAD_COMPLETION_BASE_TREE
            and git(root, 'rev-parse',
                    HOSTING_INBOX_RELOAD_COMPLETION_SOURCE
                    + '^{tree}').decode().strip()
            == HOSTING_INBOX_RELOAD_COMPLETION_SOURCE_TREE,
            'Hosted inbox reload completion source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                HOSTING_INBOX_RELOAD_COMPLETION_SOURCE).decode().strip().split()
            == [HOSTING_INBOX_RELOAD_COMPLETION_BASE],
            'Hosted inbox reload completion source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        HOSTING_INBOX_RELOAD_COMPLETION_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  HOSTING_INBOX_RELOAD_COMPLETION_BASE,
                  HOSTING_INBOX_RELOAD_COMPLETION_SOURCE).decode().splitlines()
    require(changes == ['M\t' + HOSTING_INBOX_RELOAD_COMPLETION_PATH],
            'Hosted inbox reload completion changed another path')
    base_entries = entries(root, HOSTING_INBOX_RELOAD_COMPLETION_BASE)
    source_entries = entries(root, HOSTING_INBOX_RELOAD_COMPLETION_SOURCE)
    path = HOSTING_INBOX_RELOAD_COMPLETION_PATH
    current = ordinary(root, path)
    source = git(root, 'cat-file', 'blob',
                 HOSTING_INBOX_RELOAD_COMPLETION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               HOSTING_INBOX_RELOAD_COMPLETION_BASE + ':' + path)
    require(current == source
            and digest(source) == HOSTING_INBOX_RELOAD_COMPLETION_SHA256
            and mode(root, path)
            == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_hosting_inbox_reload_completion_source(
                root, path, current) == base
            and project_hosting_inbox_reload_completion_source(
                root, path, base) == base,
            'Hosted inbox reload completion identity or projection changed')
    require(source.count(b'def goto(page, url, timeout=30000):') == 1
            and source.count(b'def click(page, link, timeout=30000):') == 1
            and source.count(
                b'goto(page, list_url(is_owner), 60000) == 200') == 1
            and source.count(b'click(op, previous, 60000) == 200') == 1
            and source.count(
                b'goto(op, list_url(True), 60000) == 200') == 1
            and b'click(op, previous) == 200' not in source
            and b'goto(op, list_url(True)) == 200' not in source,
            'Hosted inbox reload completion broadened defaults or omitted a five-card load')


def project_hosting_inbox_reload_budget_source(root, path, current):
    """Restore the reviewed cache proof before the bounded inbox reload fix."""
    current = project_hosting_inbox_reload_completion_source(root, path, current)
    if path != HOSTING_INBOX_RELOAD_BUDGET_PATH:
        return current
    source = git(root, 'cat-file', 'blob',
                 HOSTING_INBOX_RELOAD_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               HOSTING_INBOX_RELOAD_BUDGET_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current hosted inbox reload budget changed before predecessor projection')
    return base


def validate_hosting_inbox_reload_budget_source(root):
    """Bind one finite hosted budget to retained five-card inbox reloads."""
    require(git(root, 'rev-parse',
                HOSTING_INBOX_RELOAD_BUDGET_BASE
                + '^{tree}').decode().strip()
            == HOSTING_INBOX_RELOAD_BUDGET_BASE_TREE
            and git(root, 'rev-parse',
                    HOSTING_INBOX_RELOAD_BUDGET_SOURCE
                    + '^{tree}').decode().strip()
            == HOSTING_INBOX_RELOAD_BUDGET_SOURCE_TREE,
            'Hosted inbox reload budget source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                HOSTING_INBOX_RELOAD_BUDGET_SOURCE).decode().strip().split()
            == [HOSTING_INBOX_RELOAD_BUDGET_BASE],
            'Hosted inbox reload budget source parent changed')
    git(root, 'merge-base', '--is-ancestor',
        HOSTING_INBOX_RELOAD_BUDGET_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  HOSTING_INBOX_RELOAD_BUDGET_BASE,
                  HOSTING_INBOX_RELOAD_BUDGET_SOURCE).decode().splitlines()
    require(changes == ['M\t' + HOSTING_INBOX_RELOAD_BUDGET_PATH],
            'Hosted inbox reload budget source changed another path')
    base_entries = entries(root, HOSTING_INBOX_RELOAD_BUDGET_BASE)
    source_entries = entries(root, HOSTING_INBOX_RELOAD_BUDGET_SOURCE)
    path = HOSTING_INBOX_RELOAD_BUDGET_PATH
    current = project_hosting_inbox_reload_completion_source(
        root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob',
                 HOSTING_INBOX_RELOAD_BUDGET_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               HOSTING_INBOX_RELOAD_BUDGET_BASE + ':' + path)
    require(current == source
            and digest(source) == HOSTING_INBOX_RELOAD_BUDGET_SHA256
            and mode(root, path)
            == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_hosting_inbox_reload_budget_source(
                root, path, current) == base
            and project_hosting_inbox_reload_budget_source(
                root, path, base) == base,
            'Hosted inbox reload budget identity or projection changed')
    require(source.count(b'def goto(page, url, timeout=30000):') == 1
            and source.count(
                b"page.goto(url, wait_until='load', timeout=timeout)") == 1
            and source.count(
                b'goto(page, list_url(is_owner), 60000) == 200') == 1
            and b'goto(page, list_url(is_owner)) == 200' not in source
            and source.count(b"check('Inbox reloads ' + actor") == 1
            and source.count(b"click(page, link) == 200") == 1,
            'Hosted inbox reload correction weakened one-attempt destination assertions')


def validate_notification_metric_label_source(root):
    """Bind the review correction that labels retained delivery rows truthfully."""
    require(git(root, 'rev-parse', NOTIFICATION_METRIC_LABEL_BASE + '^{tree}').decode().strip()
            == NOTIFICATION_METRIC_LABEL_BASE_TREE
            and git(root, 'rev-parse',
                    NOTIFICATION_METRIC_LABEL_SOURCE + '^{tree}').decode().strip()
            == NOTIFICATION_METRIC_LABEL_SOURCE_TREE,
            'Notification metric label source tree changed')
    require(git(root, 'show', '-s', '--format=%P',
                NOTIFICATION_METRIC_LABEL_SOURCE).decode().strip().split()
            == [NOTIFICATION_METRIC_LABEL_BASE],
            'Notification metric label source parent changed')
    git(root, 'merge-base', '--is-ancestor', NOTIFICATION_METRIC_LABEL_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  NOTIFICATION_METRIC_LABEL_BASE,
                  NOTIFICATION_METRIC_LABEL_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(NOTIFICATION_METRIC_LABEL_MODIFIED)],
            'Notification metric label source changed a different path or status set')
    base_entries = entries(root, NOTIFICATION_METRIC_LABEL_BASE)
    source_entries = entries(root, NOTIFICATION_METRIC_LABEL_SOURCE)
    for path in sorted(NOTIFICATION_METRIC_LABEL_MODIFIED):
        current = project_project_private_notes_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', NOTIFICATION_METRIC_LABEL_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', NOTIFICATION_METRIC_LABEL_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_notification_metric_label_source(root, path, current) == base
                and project_notification_metric_label_source(root, path, base) == base,
                'Notification metric label source identity or projection changed: ' + path)
    source_metrics = git(root, 'cat-file', 'blob',
                         NOTIFICATION_METRIC_LABEL_SOURCE + ':site/app/notifications/metrics.php')
    source_contract = git(root, 'cat-file', 'blob',
                          NOTIFICATION_METRIC_LABEL_SOURCE + ':tests/notification_health_view_contract.php')
    require(b"$delivery['n'].' delivery records'" in source_metrics
            and b'recorded attempts' not in source_metrics
            and b'truthfully labeled as records, never dispatch attempts' in source_contract,
            'Notification metric label correction or regression assertion changed')
    require(ordinary(root, INVENTORY) == canonical_inventory(root),
            'Notification metric label installer inventory is not canonical')


def project_notification_metric_label_source(root, path, current):
    """Restore the exact reviewed proof-cache head for older source receipts."""
    current = project_project_private_notes_source(root, path, current)
    if path not in NOTIFICATION_METRIC_LABEL_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', NOTIFICATION_METRIC_LABEL_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', NOTIFICATION_METRIC_LABEL_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current notification metric label changed before predecessor projection: ' + path)
    return base


def validate_pr388_resulting_main_source(root):
    """Bind the accepted PR head to its normal, tree-identical main merge."""
    accepted_tree = git(root, 'rev-parse', PR388_ACCEPTED_HEAD + '^{tree}').decode().strip()
    resulting_tree = git(root, 'rev-parse', PR388_RESULTING_MAIN + '^{tree}').decode().strip()
    parents = git(root, 'show', '-s', '--format=%P', PR388_RESULTING_MAIN).decode().strip().split()
    require(accepted_tree == PR388_ACCEPTED_TREE
            and resulting_tree == PR388_RESULTING_MAIN_TREE
            and resulting_tree == accepted_tree,
            'PR #388 accepted or resulting-main tree changed')
    require(parents == [PR388_ACCEPTED_BASE, PR388_ACCEPTED_HEAD],
            'PR #388 resulting-main merge parent order changed')
    require(git(root, 'diff', '--name-status', '--no-renames',
                PR388_ACCEPTED_HEAD, PR388_RESULTING_MAIN).decode() == '',
            'PR #388 resulting-main merge changed accepted source bytes')
    git(root, 'merge-base', '--is-ancestor', PR388_RESULTING_MAIN, 'HEAD')


def validate_pr391_recovery_successor(root):
    """Retain the failed first-attempt proof head and admit only a new proof successor."""
    require(git(root, 'rev-parse', PR391_RECOVERY_SOURCE + '^{tree}').decode().strip()
            == PR391_RECOVERY_SOURCE_TREE
            and git(root, 'rev-parse', PR391_RECOVERY_HEAD + '^{tree}').decode().strip()
            == PR391_RECOVERY_HEAD_TREE,
            'PR #391 recovery source identity changed')
    require(git(root, 'show', '-s', '--format=%P', PR391_RECOVERY_SOURCE).decode().strip().split()
            == [PR388_RESULTING_MAIN]
            and git(root, 'show', '-s', '--format=%P', PR391_RECOVERY_HEAD).decode().strip().split()
            == [PR391_RECOVERY_SOURCE],
            'PR #391 recovery proof ancestry changed')
    require(git(root, 'diff', '--name-only', '--no-renames',
                PR388_RESULTING_MAIN, PR391_RECOVERY_SOURCE).decode().splitlines()
            == [VERIFIER]
            and git(root, 'diff', '--name-only', '--no-renames',
                    PR391_RECOVERY_SOURCE, PR391_RECOVERY_HEAD).decode().splitlines()
            == [RECEIPT],
            'PR #391 recovery proof changed application source')
    git(root, 'merge-base', '--is-ancestor', PR391_RECOVERY_HEAD, 'HEAD')
    require(set(git(root, 'diff', '--name-only', '--no-renames',
                    PR391_RECOVERY_HEAD, 'HEAD').decode().splitlines())
            == ({VERIFIER, RECEIPT, REGRESSION} | RUNNER_MIRROR_COMPAT_MODIFIED
                | RUNNER_MIRROR_PHASE_MODIFIED | RUNNER_MIRROR_PHASE_ADDITIONS
                | RUNNER_APPLICATION_COMPLETION_MODIFIED
                | RUNNER_EVIDENCE_PROVENANCE_MODIFIED | RUNNER_AWS_PROFILE_MODIFIED
                | RUNNER_APPLICATION_SOURCE_BINDING_MODIFIED
                | RUNNER_APPLICATION_HISTORICAL_BINDING_MODIFIED
                | RUNNER_AWS_MIRROR_PROFILE_MODIFIED
                | RUNNER_APPLICATION_INTAKE_BINDING_MODIFIED
                | RUNNER_AWS_MIRROR_FIXTURE_MODIFIED
                | RUNNER_APPLICATION_INTAKE_PROJECTION_MODIFIED
                | RUNNER_INTAKE_CURRENT_BINDING_MODIFIED
                | RUNNER_FINITE_TOOL_REFUSAL_MODIFIED
                | RUNNER_ACCOUNT_SOURCE_BUDGET_MODIFIED
                | RUNNER_APPLICATION_BUILD_BUDGET_MODIFIED
                | {RESULTING_MAIN_INBOX_NAVIGATION_BUDGET_PATH}
                | RESULTING_MAIN_INBOX_CLICK_BUDGET_MODIFIED
                | RESULTING_MAIN_INBOX_CLICK_BUDGET_ADDITIONS
                | NOTIFICATION_HEALTH_MODIFIED
                | NOTIFICATION_HEALTH_ADDITIONS
                | PROJECT_PRIVATE_NOTES_MODIFIED
                | PROJECT_PRIVATE_NOTES_ADDITIONS
                | PROJECT_PRIVATE_NOTES_EVIDENCE_MODIFIED
                | {PROJECT_PRIVATE_NOTES_AUTHORITY_PATH}
                | PROJECT_PRIVATE_NOTES_PROOF_COMPAT_PATHS
                | PROJECT_PRIVATE_NOTES_STATIC_INTAKE_PATHS
                | PROJECT_PRIVATE_NOTES_NATIVE_FIXTURE_PATHS
                | {DOMAIN_EXPIRY_PORT_PATH}
                | PROOF_RUNTIME_MODIFIED
                | PROOF_CACHE_HARDENING_MODIFIED
                | NOTIFICATION_METRIC_LABEL_MODIFIED
                | {MAIL_BACKUP_PROJECTION_FIX_PATH}
                | MAIL_REPLY_FORWARD_PATHS
                | MAIL_THREAD_HEADER_FIX_PATHS
                | MAIL_THREAD_ALIAS_FIX_PATHS
                | {MAIL_NATIVE_FIXTURE_POLICY_PATH, MAIL_REPLY_SESSION_FIX_PATH}
                | MAIL_SENT_COPY_MODIFIED
                | MAIL_SENT_COPY_ADDITIONS
                | MAIL_ATTACHMENT_PORTABLE_ADDITIONS
                | MAIL_ATTACHMENT_MODIFIED
                | MAIL_ATTACHMENT_ADDITIONS
                | MAIL_ATTACHMENT_AUTH_FIX_PATHS
                | MAIL_ATTACHMENT_READINESS_FIX_PATHS
                | MAIL_ATTACHMENT_SUBMISSION_FIX_PATHS
                | {MAIL_ATTACHMENT_CLAIM_FIX_PATH}
                | MAIL_ACCEPTANCE_PORTABILITY_PATHS
                | {APPLICATION_ACCOUNT_FIXTURE_PATH, APPLICATION_INBOX_FIXTURE_PATH,
                   APPLICATION_BROWSER_FIXTURE_PATH}
                | APPLICATION_FRESH_DRAFT_READINESS_PATHS
                | {FORCE_SSL_RUNNER_PORT_PATH}
                | ACCOUNT_RATE_BOUNDARY_MODIFIED
                | APPLICATION_MAIL_COPIES_ROUTING_MODIFIED
                 | RESULTING_MAIN_DIAGNOSTIC_MAIL_REPLY_MODIFIED
                | set(mail_consolidated_changes(root))),
            'Current recovery successor contains an unbound path')


def validate_ux08_payment_fulfillment_validation_lock_fix_source(root):
    """Bind the fixture-only transaction for retained order validation."""
    require(git(root, 'rev-parse', UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE_TREE,
            'UX-08 synthetic fulfillment validation lock source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE,
        UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE,
                  UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_MODIFIED)],
            'UX-08 synthetic fulfillment validation lock changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_MODIFIED):
        current = project_ux08_hosted_mirror_contract_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_fulfillment_validation_lock_fix(root, path, current) == base
                and project_ux08_payment_fulfillment_validation_lock_fix(root, path, base) == base,
                'UX-08 synthetic fulfillment validation lock identity or projection changed: ' + path)


def project_ux08_payment_fulfillment_validation_lock_fix(root, path, current):
    """Restore the accepted fulfillment-lock proof head for older receipts."""
    current = project_ux08_hosted_mirror_contract_fix(root, path, current)
    if path not in UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FULFILLMENT_VALIDATION_LOCK_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 synthetic fulfillment validation lock changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_fulfillment_lock_fix_source(root):
    """Bind the fixture-only staff-lock scope for closed synthetic fulfillment."""
    require(git(root, 'rev-parse', UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE_TREE,
            'UX-08 synthetic fulfillment lock source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE,
        UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE,
                  UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_FULFILLMENT_LOCK_FIX_MODIFIED)],
            'UX-08 synthetic fulfillment lock changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_FULFILLMENT_LOCK_FIX_MODIFIED):
        current = project_ux08_payment_fulfillment_validation_lock_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_fulfillment_lock_fix(root, path, current) == base
                and project_ux08_payment_fulfillment_lock_fix(root, path, base) == base,
                'UX-08 synthetic fulfillment lock identity or projection changed: ' + path)


def project_ux08_payment_fulfillment_lock_fix(root, path, current):
    """Restore the accepted expected-diagnostic proof head for older receipts."""
    current = project_ux08_payment_fulfillment_validation_lock_fix(root, path, current)
    if path not in UX08_PAYMENT_FULFILLMENT_LOCK_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FULFILLMENT_LOCK_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FULFILLMENT_LOCK_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 synthetic fulfillment lock changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_expected_diagnostic_fix_source(root):
    """Bind exact capture of the deliberate uncertain gateway diagnostic."""
    require(git(root, 'rev-parse', UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE_TREE,
            'UX-08 expected payment diagnostic source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE,
        UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE,
                  UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_MODIFIED)],
            'UX-08 expected payment diagnostic changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_MODIFIED):
        current = project_ux08_payment_fulfillment_lock_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_expected_diagnostic_fix(root, path, current) == base
                and project_ux08_payment_expected_diagnostic_fix(root, path, base) == base,
                'UX-08 expected payment diagnostic identity or projection changed: ' + path)


def project_ux08_payment_expected_diagnostic_fix(root, path, current):
    """Restore the accepted unique-label successor before older source receipts."""
    current = project_ux08_payment_fulfillment_lock_fix(root, path, current)
    if path not in UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_EXPECTED_DIAGNOSTIC_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 expected payment diagnostic changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_form_label_fix_source(root):
    """Bind unique per-journey payment-form checks before the hosted successor."""
    require(git(root, 'rev-parse', UX08_PAYMENT_FORM_LABEL_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FORM_LABEL_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_FORM_LABEL_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FORM_LABEL_FIX_SOURCE_TREE,
            'UX-08 payment form label fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FORM_LABEL_FIX_BASE,
        UX08_PAYMENT_FORM_LABEL_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FORM_LABEL_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_FORM_LABEL_FIX_BASE,
                  UX08_PAYMENT_FORM_LABEL_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_FORM_LABEL_FIX_MODIFIED)],
            'UX-08 payment form label fix changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_FORM_LABEL_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_FORM_LABEL_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_FORM_LABEL_FIX_MODIFIED):
        current = project_ux08_payment_expected_diagnostic_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FORM_LABEL_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FORM_LABEL_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_form_label_fix(root, path, current) == base
                and project_ux08_payment_form_label_fix(root, path, base) == base,
                'UX-08 payment form label fix identity or projection changed: ' + path)


def project_ux08_payment_form_label_fix(root, path, current):
    """Restore the first diagnostic successor before its own source receipt."""
    current = project_ux08_payment_expected_diagnostic_fix(root, path, current)
    if path not in UX08_PAYMENT_FORM_LABEL_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FORM_LABEL_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FORM_LABEL_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment form label fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_form_diagnostic_fix_source(root):
    """Bind the precise safe payment-form diagnostic successor and its proof."""
    require(git(root, 'rev-parse', UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE_TREE,
            'UX-08 payment form diagnostic source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE,
        UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE,
                  UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_MODIFIED)],
            'UX-08 payment form diagnostic changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_MODIFIED):
        current = project_ux08_payment_form_label_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_form_diagnostic_fix(root, path, current) == base
                and project_ux08_payment_form_diagnostic_fix(root, path, base) == base,
                'UX-08 payment form diagnostic identity or projection changed: ' + path)


def project_ux08_payment_form_diagnostic_fix(root, path, current):
    """Restore the exact accepted #388 predecessor before older source receipts."""
    current = project_ux08_payment_form_label_fix(root, path, current)
    if path not in UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FORM_DIAGNOSTIC_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment form diagnostic changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_artifact_projection_fix_source(root):
    """Bind the exact artifact-source predecessor projection for the payment overlay."""
    require(git(root, 'rev-parse', UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE_TREE,
            'UX-08 payment artifact projection fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE,
        UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE,
                  UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_PATH],
            'UX-08 payment artifact projection fix changed a different path or status')
    base_entries = entries(root, UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE)
    path = UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_PATH
    current = project_ux08_payment_form_diagnostic_fix(root, path, ordinary(root, path))
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE + ':' + path)
    require(current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_ux08_payment_artifact_projection_fix(root, path, current) == base
            and project_ux08_payment_artifact_projection_fix(root, path, base) == base,
            'UX-08 payment artifact projection fix identity or projection changed')


def project_ux08_payment_artifact_projection_fix(root, path, current):
    """Restore the reviewed current-publication proof head for older receipts."""
    current = project_ux08_payment_form_diagnostic_fix(root, path, current)
    if path != UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_ARTIFACT_PROJECTION_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment artifact projection fix changed before predecessor projection')
    return base


def validate_ux08_inbox_payment_readiness_fix_source(root):
    """Bind the exact bounded payment-readiness correction for purchase inbox reads."""
    require(git(root, 'rev-parse', UX08_INBOX_PAYMENT_READINESS_FIX_BASE + '^{tree}').decode().strip()
            == UX08_INBOX_PAYMENT_READINESS_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE_TREE,
            'UX-08 inbox payment readiness fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_PAYMENT_READINESS_FIX_BASE,
        UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_INBOX_PAYMENT_READINESS_FIX_BASE,
                  UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_INBOX_PAYMENT_READINESS_FIX_MODIFIED)],
            'UX-08 inbox payment readiness fix changed a different path or status set')
    base_entries = entries(root, UX08_INBOX_PAYMENT_READINESS_FIX_BASE)
    source_entries = entries(root, UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE)
    for path in sorted(UX08_INBOX_PAYMENT_READINESS_FIX_MODIFIED):
        current = project_notification_health_source(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_INBOX_PAYMENT_READINESS_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_inbox_payment_readiness_fix(root, path, current) == base
                and project_ux08_inbox_payment_readiness_fix(root, path, base) == base,
                'UX-08 inbox payment readiness fix identity or projection changed: ' + path)


def project_ux08_inbox_payment_readiness_fix(root, path, current):
    """Restore the exact pre-readiness-fix head for older source receipts."""
    current = project_hosting_inbox_reload_budget_source(root, path, current)
    current = project_notification_health_source(root, path, current)
    if path not in UX08_INBOX_PAYMENT_READINESS_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_INBOX_PAYMENT_READINESS_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_INBOX_PAYMENT_READINESS_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 inbox payment readiness fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_current_publication_fix_source(root):
    """Bind the exact current billing publication used by payment journeys."""
    require(git(root, 'rev-parse', UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE_TREE,
            'UX-08 payment current publication fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE,
        UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE,
                  UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_CURRENT_PUBLICATION_FIX_MODIFIED)],
            'UX-08 payment current publication fix changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_CURRENT_PUBLICATION_FIX_MODIFIED):
        current = project_ux08_payment_form_diagnostic_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_current_publication_fix(root, path, current) == base
                and project_ux08_payment_current_publication_fix(root, path, base) == base,
                'UX-08 payment current publication fix identity or projection changed: ' + path)


def project_ux08_payment_current_publication_fix(root, path, current):
    """Restore the accepted publication-pin proof head for older receipts."""
    current = project_ux08_payment_form_diagnostic_fix(root, path, current)
    current = project_ux08_payment_artifact_projection_fix(root, path, current)
    if path not in UX08_PAYMENT_CURRENT_PUBLICATION_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_CURRENT_PUBLICATION_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_CURRENT_PUBLICATION_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment current publication fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_publication_pin_fix_source(root):
    """Bind the exact prepared publication identity used by payment journeys."""
    require(git(root, 'rev-parse', UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE_TREE,
            'UX-08 payment publication pin fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE,
        UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE,
                  UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_PUBLICATION_PIN_FIX_MODIFIED)],
            'UX-08 payment publication pin fix changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_PUBLICATION_PIN_FIX_MODIFIED):
        current = project_ux08_payment_current_publication_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_publication_pin_fix(root, path, current) == base
                and project_ux08_payment_publication_pin_fix(root, path, base) == base,
                'UX-08 payment publication pin fix identity or projection changed: ' + path)


def project_ux08_payment_publication_pin_fix(root, path, current):
    """Restore the accepted payment-fixture-lock proof head for older receipts."""
    current = project_ux08_payment_current_publication_fix(root, path, current)
    if path not in UX08_PAYMENT_PUBLICATION_PIN_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_PUBLICATION_PIN_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_PUBLICATION_PIN_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment publication pin fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_fixture_lock_fix_source(root):
    """Bind the exact locked publication read used to seed payment journeys."""
    require(git(root, 'rev-parse', UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE_TREE,
            'UX-08 payment fixture lock fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE,
        UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE,
                  UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_FIXTURE_LOCK_FIX_MODIFIED)],
            'UX-08 payment fixture lock fix changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_FIXTURE_LOCK_FIX_MODIFIED):
        current = project_ux08_payment_publication_pin_fix(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_ux08_payment_fixture_lock_fix(root, path, current) == base
                and project_ux08_payment_fixture_lock_fix(root, path, base) == base,
                'UX-08 payment fixture lock fix identity or projection changed: ' + path)


def project_ux08_payment_fixture_lock_fix(root, path, current):
    """Restore the billing-network proof head for older source receipts."""
    current = project_ux08_payment_publication_pin_fix(root, path, current)
    if path not in UX08_PAYMENT_FIXTURE_LOCK_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_FIXTURE_LOCK_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_FIXTURE_LOCK_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment fixture lock fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_storefront_network_fix_source(root):
    """Bind exact closed interception in the separate storefront continuation."""
    require(git(root, 'rev-parse', UX08_STOREFRONT_NETWORK_FIX_BASE + '^{tree}').decode().strip()
            == UX08_STOREFRONT_NETWORK_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_STOREFRONT_NETWORK_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_STOREFRONT_NETWORK_FIX_SOURCE_TREE,
            'UX-08 storefront network fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_STOREFRONT_NETWORK_FIX_BASE,
        UX08_STOREFRONT_NETWORK_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_STOREFRONT_NETWORK_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_STOREFRONT_NETWORK_FIX_BASE,
                  UX08_STOREFRONT_NETWORK_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_STOREFRONT_NETWORK_FIX_PATH],
            'UX-08 storefront network fix changed a different path or status set')
    base_entries = entries(root, UX08_STOREFRONT_NETWORK_FIX_BASE)
    source_entries = entries(root, UX08_STOREFRONT_NETWORK_FIX_SOURCE)
    current = ordinary(root, UX08_STOREFRONT_NETWORK_FIX_PATH)
    require(current == git(root, 'cat-file', 'blob', UX08_STOREFRONT_NETWORK_FIX_SOURCE + ':'
                           + UX08_STOREFRONT_NETWORK_FIX_PATH)
            and mode(root, UX08_STOREFRONT_NETWORK_FIX_PATH)
            == base_entries[UX08_STOREFRONT_NETWORK_FIX_PATH][0]
            == source_entries[UX08_STOREFRONT_NETWORK_FIX_PATH][0] == '100644',
            'UX-08 storefront network fix source identity changed')


def project_ux08_storefront_network_fix(root, path, current):
    """Restore the preceding browser-network proof head for older receipts."""
    current = project_ux08_inbox_state_fix(root, path, current)
    if path != UX08_STOREFRONT_NETWORK_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_STOREFRONT_NETWORK_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_STOREFRONT_NETWORK_FIX_BASE + ':' + path)
    if current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 storefront network fix changed before predecessor projection')
    return base


def validate_ux08_browser_network_fix_source(root):
    """Bind exact closed interception of the production tokenization dependency."""
    require(git(root, 'rev-parse', UX08_BROWSER_NETWORK_FIX_BASE + '^{tree}').decode().strip()
            == UX08_BROWSER_NETWORK_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_BROWSER_NETWORK_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_BROWSER_NETWORK_FIX_SOURCE_TREE,
            'UX-08 browser network fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_BROWSER_NETWORK_FIX_BASE,
        UX08_BROWSER_NETWORK_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_BROWSER_NETWORK_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_BROWSER_NETWORK_FIX_BASE, UX08_BROWSER_NETWORK_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_BROWSER_NETWORK_FIX_PATH],
            'UX-08 browser network fix changed a different path or status set')
    base_entries = entries(root, UX08_BROWSER_NETWORK_FIX_BASE)
    source_entries = entries(root, UX08_BROWSER_NETWORK_FIX_SOURCE)
    current = ordinary(root, UX08_BROWSER_NETWORK_FIX_PATH)
    require(current == git(root, 'cat-file', 'blob', UX08_BROWSER_NETWORK_FIX_SOURCE + ':'
                           + UX08_BROWSER_NETWORK_FIX_PATH)
            and mode(root, UX08_BROWSER_NETWORK_FIX_PATH)
            == base_entries[UX08_BROWSER_NETWORK_FIX_PATH][0]
            == source_entries[UX08_BROWSER_NETWORK_FIX_PATH][0] == '100644',
            'UX-08 browser network fix source identity changed')


def project_ux08_browser_network_fix(root, path, current):
    """Restore the preceding diagnostic-proof head for older source receipts."""
    current = project_ux08_storefront_network_fix(root, path, current)
    if path != UX08_BROWSER_NETWORK_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_BROWSER_NETWORK_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_BROWSER_NETWORK_FIX_BASE + ':' + path)
    if current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 browser network fix changed before predecessor projection')
    return base


def validate_ux08_checkout_pin_fix_source(root):
    """Bind the corrected native checkout source identity in acceptance."""
    require(git(root, 'rev-parse', UX08_CHECKOUT_PIN_FIX_BASE + '^{tree}').decode().strip()
            == UX08_CHECKOUT_PIN_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_CHECKOUT_PIN_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_CHECKOUT_PIN_FIX_SOURCE_TREE,
            'UX-08 checkout source-pin fix tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_CHECKOUT_PIN_FIX_BASE,
        UX08_CHECKOUT_PIN_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_CHECKOUT_PIN_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_CHECKOUT_PIN_FIX_BASE, UX08_CHECKOUT_PIN_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_CHECKOUT_PIN_FIX_PATH],
            'UX-08 checkout source-pin fix changed a different path or status')
    base_entries = entries(root, UX08_CHECKOUT_PIN_FIX_BASE)
    source_entries = entries(root, UX08_CHECKOUT_PIN_FIX_SOURCE)
    current = project_runner_application_source_binding(
        root, UX08_CHECKOUT_PIN_FIX_PATH, ordinary(root, UX08_CHECKOUT_PIN_FIX_PATH))
    require(current == git(root, 'cat-file', 'blob', UX08_CHECKOUT_PIN_FIX_SOURCE + ':'
                           + UX08_CHECKOUT_PIN_FIX_PATH)
            and mode(root, UX08_CHECKOUT_PIN_FIX_PATH)
            == base_entries[UX08_CHECKOUT_PIN_FIX_PATH][0]
            == source_entries[UX08_CHECKOUT_PIN_FIX_PATH][0] == '100644'
            and project_ux08_checkout_pin_fix(root, UX08_CHECKOUT_PIN_FIX_PATH, current)
            == git(root, 'cat-file', 'blob', UX08_CHECKOUT_PIN_FIX_BASE + ':'
                   + UX08_CHECKOUT_PIN_FIX_PATH),
            'UX-08 checkout source-pin fix identity or projection changed')


def project_ux08_checkout_pin_fix(root, path, current):
    """Restore the accepted inbox successor before older source projections."""
    current = project_runner_application_source_binding(root, path, current)
    if path != UX08_CHECKOUT_PIN_FIX_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_CHECKOUT_PIN_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_CHECKOUT_PIN_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 checkout source-pin fix changed before predecessor projection')
    return base


def validate_ux08_payment_diagnostic_fix_source(root):
    """Bind exact isolation of the four deliberate payment review diagnostics."""
    require(git(root, 'rev-parse', UX08_PAYMENT_DIAGNOSTIC_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_DIAGNOSTIC_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE_TREE,
            'UX-08 payment diagnostic fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_DIAGNOSTIC_FIX_BASE,
        UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_DIAGNOSTIC_FIX_BASE, UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_DIAGNOSTIC_FIX_MODIFIED)],
            'UX-08 payment diagnostic fix changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_DIAGNOSTIC_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_DIAGNOSTIC_FIX_MODIFIED):
        current = project_ux08_checkout_pin_fix(root, path, ordinary(root, path))
        require(current
                == git(root, 'cat-file', 'blob', UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE + ':' + path)
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-08 payment diagnostic fix source identity changed: ' + path)


def project_ux08_payment_diagnostic_fix(root, path, current):
    """Restore the preceding native-helper successor for older source receipts."""
    current = project_ux08_checkout_pin_fix(root, path, current)
    current = project_ux08_browser_network_fix(root, path, current)
    if path not in UX08_PAYMENT_DIAGNOSTIC_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_DIAGNOSTIC_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_DIAGNOSTIC_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment diagnostic fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_native_fix_source(root):
    """Bind the isolated native cooldown-helper correction and its contract hash."""
    require(git(root, 'rev-parse', UX08_PAYMENT_NATIVE_FIX_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_NATIVE_FIX_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_NATIVE_FIX_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_NATIVE_FIX_SOURCE_TREE,
            'UX-08 native payment fix source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_NATIVE_FIX_BASE,
        UX08_PAYMENT_NATIVE_FIX_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_NATIVE_FIX_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_NATIVE_FIX_BASE, UX08_PAYMENT_NATIVE_FIX_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_PAYMENT_NATIVE_FIX_MODIFIED)],
            'UX-08 native payment fix changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_NATIVE_FIX_BASE)
    source_entries = entries(root, UX08_PAYMENT_NATIVE_FIX_SOURCE)
    for path in sorted(UX08_PAYMENT_NATIVE_FIX_MODIFIED):
        current = project_ux08_payment_diagnostic_fix(root, path, ordinary(root, path))
        require(current
                == git(root, 'cat-file', 'blob', UX08_PAYMENT_NATIVE_FIX_SOURCE + ':' + path)
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-08 native payment fix source identity changed: ' + path)


def project_ux08_payment_native_fix(root, path, current):
    """Restore the first recovery-attempt bytes for older source receipts."""
    current = project_runner_application_intake_binding(root, path, current)
    current = project_ux08_inbox_payment_readiness_fix(root, path, current)
    current = project_ux08_agreement_link_fix(root, path, current)
    current = project_ux08_inbox_record_fix(root, path, current)
    current = project_ux08_inbox_operations_fix(root, path, current)
    current = project_ux08_payment_diagnostic_fix(root, path, current)
    if path not in UX08_PAYMENT_NATIVE_FIX_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_NATIVE_FIX_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_NATIVE_FIX_BASE + ':' + path)
    if current == source:
        return base
    if current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 native payment fix changed before predecessor projection: ' + path)
    return base


def validate_ux08_payment_source(root):
    """Bind the complete Shared payment, receipt, fulfillment and acceptance successor."""
    require(git(root, 'rev-parse', UX08_PAYMENT_BASE + '^{tree}').decode().strip()
            == UX08_PAYMENT_BASE_TREE
            and git(root, 'rev-parse', UX08_PAYMENT_SOURCE + '^{tree}').decode().strip()
            == UX08_PAYMENT_SOURCE_TREE, 'UX-08 payment successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_BASE, UX08_PAYMENT_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_PAYMENT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_PAYMENT_BASE, UX08_PAYMENT_SOURCE).decode().splitlines()
    observed_modified = {line.split('\t', 1)[1] for line in changes if line.startswith('M\t')}
    observed_added = {line.split('\t', 1)[1] for line in changes if line.startswith('A\t')}
    require(observed_modified == UX08_PAYMENT_MODIFIED
            and observed_added == UX08_PAYMENT_ADDITIONS
            and len(changes) == len(UX08_PAYMENT_MODIFIED | UX08_PAYMENT_ADDITIONS),
            'UX-08 payment successor changed a different path or status set')
    base_entries = entries(root, UX08_PAYMENT_BASE)
    source_entries = entries(root, UX08_PAYMENT_SOURCE)
    require(not UX08_PAYMENT_ADDITIONS & set(base_entries)
            and UX08_PAYMENT_ADDITIONS <= set(source_entries),
            'UX-08 payment additions did not originate in the exact source range')
    for path in sorted(UX08_PAYMENT_MODIFIED):
        current = project_notification_health_source(root, path, ordinary(root, path))
        current = project_runner_finite_tool_refusal(root, path, current)
        current = project_ux08_payment_fixture_lock_fix(root, path, current)
        current = project_ux08_billing_network_fix(root, path, current)
        current = project_ux08_payment_native_fix(root, path, current)
        require(current
                == git(root, 'cat-file', 'blob', UX08_PAYMENT_SOURCE + ':' + path)
                and mode(root, path) == base_entries[path][0] == source_entries[path][0],
                'UX-08 payment modified source identity changed: ' + path)
    for path in sorted(UX08_PAYMENT_ADDITIONS):
        current = project_ux08_payment_native_fix(root, path, ordinary(root, path))
        require(current
                == git(root, 'cat-file', 'blob', UX08_PAYMENT_SOURCE + ':' + path)
                and mode(root, path) == source_entries[path][0] == '100644',
                'UX-08 payment addition source identity changed: ' + path)
    require(ordinary(root, INVENTORY) == canonical_inventory(root),
            'UX-08 payment source inventory is not canonical')


def project_ux08_payment(root, path, current):
    """Restore resulting-main bytes only while evaluating older source receipts."""
    current = project_notification_health_source(root, path, current)
    current = project_runner_finite_tool_refusal(root, path, current)
    current = project_ux08_payment_fixture_lock_fix(root, path, current)
    current = project_ux08_billing_network_fix(root, path, current)
    current = project_ux08_payment_native_fix(root, path, current)
    if path not in UX08_PAYMENT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_PAYMENT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_PAYMENT_BASE + ':' + path)
    if current == source:
        return base
    if current != ordinary(root, path):
        return current
    require(current == source,
            'Current UX-08 payment successor changed before predecessor projection: ' + path)
    return base


def validate_ux08_browser_stability_source(root):
    """Bind the final owner-network gate to a completed page lifecycle."""
    require(git(root, 'rev-parse', UX08_BROWSER_STABILITY_BASE + '^{tree}').decode().strip()
            == UX08_BROWSER_STABILITY_BASE_TREE
            and git(root, 'rev-parse', UX08_BROWSER_STABILITY_SOURCE + '^{tree}').decode().strip()
            == UX08_BROWSER_STABILITY_SOURCE_TREE,
            'UX-08 browser-stability source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_BROWSER_STABILITY_BASE,
        UX08_BROWSER_STABILITY_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_BROWSER_STABILITY_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_BROWSER_STABILITY_BASE, UX08_BROWSER_STABILITY_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_BROWSER_STABILITY_PATH],
            'UX-08 browser-stability source changed a different path or status set')
    base_entries = entries(root, UX08_BROWSER_STABILITY_BASE)
    source_entries = entries(root, UX08_BROWSER_STABILITY_SOURCE)
    current = ordinary(root, UX08_BROWSER_STABILITY_PATH)
    require(current == git(root, 'cat-file', 'blob', UX08_BROWSER_STABILITY_SOURCE + ':'
                           + UX08_BROWSER_STABILITY_PATH)
            and digest(current) == UX08_BROWSER_STABILITY_SHA256
            and mode(root, UX08_BROWSER_STABILITY_PATH)
            == base_entries[UX08_BROWSER_STABILITY_PATH][0]
            == source_entries[UX08_BROWSER_STABILITY_PATH][0] == '100644',
            'UX-08 browser-stability source identity changed')


def project_ux08_browser_stability(root, path, current):
    """Restore the pre-stability browser driver for older source proofs."""
    if path != UX08_BROWSER_STABILITY_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_BROWSER_STABILITY_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_BROWSER_STABILITY_BASE + ':' + path)
    if current == base:
        return current
    require(current == source and digest(current) == UX08_BROWSER_STABILITY_SHA256,
            'Current UX-08 browser-stability source changed')
    return base


def validate_ux08_browser_navigation_source(root):
    """Bind the installed-browser contract to the native Hosting disclosure."""
    require(git(root, 'rev-parse', UX08_BROWSER_NAVIGATION_BASE + '^{tree}').decode().strip()
            == UX08_BROWSER_NAVIGATION_BASE_TREE
            and git(root, 'rev-parse', UX08_BROWSER_NAVIGATION_SOURCE + '^{tree}').decode().strip()
            == UX08_BROWSER_NAVIGATION_SOURCE_TREE, 'UX-08 browser-navigation source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_BROWSER_NAVIGATION_BASE,
        UX08_BROWSER_NAVIGATION_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_BROWSER_NAVIGATION_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_BROWSER_NAVIGATION_BASE, UX08_BROWSER_NAVIGATION_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_BROWSER_NAVIGATION_PATH],
            'UX-08 browser-navigation source changed a different path or status set')
    base_entries = entries(root, UX08_BROWSER_NAVIGATION_BASE)
    source_entries = entries(root, UX08_BROWSER_NAVIGATION_SOURCE)
    current = project_ux08_browser_stability(
        root, UX08_BROWSER_NAVIGATION_PATH, ordinary(root, UX08_BROWSER_NAVIGATION_PATH))
    require(current
            == git(root, 'cat-file', 'blob', UX08_BROWSER_NAVIGATION_SOURCE + ':'
                   + UX08_BROWSER_NAVIGATION_PATH)
            and mode(root, UX08_BROWSER_NAVIGATION_PATH)
            == base_entries[UX08_BROWSER_NAVIGATION_PATH][0]
            == source_entries[UX08_BROWSER_NAVIGATION_PATH][0] == '100644',
            'UX-08 browser-navigation source identity changed')


def project_ux08_browser_navigation(root, path, current):
    """Restore the pre-disclosure browser assertion for older source proofs."""
    current = project_ux08_payment(root, path, current)
    current = project_ux08_browser_stability(root, path, current)
    if path != UX08_BROWSER_NAVIGATION_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_BROWSER_NAVIGATION_SOURCE + ':' + path)
    require(current == source, 'Current UX-08 browser-navigation source changed')
    return git(root, 'cat-file', 'blob', UX08_BROWSER_NAVIGATION_BASE + ':' + path)


def validate_ux08_fixture_dependency_source(root):
    """Bind the exact seven native fixtures that load storefront setup."""
    require(git(root, 'rev-parse', UX08_FIXTURE_DEPENDENCY_BASE + '^{tree}').decode().strip()
            == UX08_FIXTURE_DEPENDENCY_BASE_TREE
            and git(root, 'rev-parse', UX08_FIXTURE_DEPENDENCY_SOURCE + '^{tree}').decode().strip()
            == UX08_FIXTURE_DEPENDENCY_SOURCE_TREE, 'UX-08 fixture-dependency source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_FIXTURE_DEPENDENCY_BASE,
        UX08_FIXTURE_DEPENDENCY_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_FIXTURE_DEPENDENCY_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_FIXTURE_DEPENDENCY_BASE, UX08_FIXTURE_DEPENDENCY_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_FIXTURE_DEPENDENCY_MODIFIED)]
            and UX08_FIXTURE_DEPENDENCY_MODIFIED <= UX08_STOREFRONT_MODIFIED,
            'UX-08 fixture-dependency source changed a different path or status set')
    base_entries = entries(root, UX08_FIXTURE_DEPENDENCY_BASE)
    source_entries = entries(root, UX08_FIXTURE_DEPENDENCY_SOURCE)
    for path in sorted(UX08_FIXTURE_DEPENDENCY_MODIFIED):
        current = project_ux08_payment(root, path, ordinary(root, path))
        require(current
                == git(root, 'cat-file', 'blob', UX08_FIXTURE_DEPENDENCY_SOURCE + ':' + path)
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-08 fixture-dependency source identity changed: ' + path)


def project_ux08_fixture_dependency(root, path, current):
    """Restore the pre-fixture-dependency bytes for older UX-08 receipts."""
    current = project_ux08_payment(root, path, current)
    if path not in UX08_FIXTURE_DEPENDENCY_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_FIXTURE_DEPENDENCY_SOURCE + ':' + path)
    require(current == source, 'Current UX-08 fixture-dependency source changed: ' + path)
    return git(root, 'cat-file', 'blob', UX08_FIXTURE_DEPENDENCY_BASE + ':' + path)


def validate_ux08_artifact_proof_source(root):
    """Bind the five exact artifact predecessor projections exposed by Linux CI."""
    require(git(root, 'rev-parse', UX08_ARTIFACT_PROOF_BASE + '^{tree}').decode().strip()
            == UX08_ARTIFACT_PROOF_BASE_TREE
            and git(root, 'rev-parse', UX08_ARTIFACT_PROOF_SOURCE + '^{tree}').decode().strip()
            == UX08_ARTIFACT_PROOF_SOURCE_TREE, 'UX-08 artifact-proof successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_ARTIFACT_PROOF_BASE, UX08_ARTIFACT_PROOF_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_ARTIFACT_PROOF_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_ARTIFACT_PROOF_BASE, UX08_ARTIFACT_PROOF_SOURCE).decode().splitlines()
    require(changes == ['M\t' + UX08_ARTIFACT_PROOF_PATH],
            'UX-08 artifact-proof successor changed a different path or status set')
    base_entries = entries(root, UX08_ARTIFACT_PROOF_BASE)
    source_entries = entries(root, UX08_ARTIFACT_PROOF_SOURCE)
    current = project_ux08_payment(root, UX08_ARTIFACT_PROOF_PATH,
                                   ordinary(root, UX08_ARTIFACT_PROOF_PATH))
    require(current
            == git(root, 'cat-file', 'blob', UX08_ARTIFACT_PROOF_SOURCE + ':' + UX08_ARTIFACT_PROOF_PATH)
            and mode(root, UX08_ARTIFACT_PROOF_PATH) == base_entries[UX08_ARTIFACT_PROOF_PATH][0]
            == source_entries[UX08_ARTIFACT_PROOF_PATH][0] == '100644',
            'UX-08 artifact-proof successor source identity changed')


def project_ux08_artifact_proof(root, path, current):
    """Restore the exact pre-artifact-proof helper for older source proofs."""
    current = project_ux08_fixture_dependency(root, path, current)
    if path != UX08_ARTIFACT_PROOF_PATH:
        return current
    source = git(root, 'cat-file', 'blob', UX08_ARTIFACT_PROOF_SOURCE + ':' + path)
    require(current == source,
            'Current UX-08 artifact-proof successor changed before predecessor projection')
    return git(root, 'cat-file', 'blob', UX08_ARTIFACT_PROOF_BASE + ':' + path)


def validate_ux08_review_source(root):
    """Bind the bounded read-scope review successor and refreshed inventory."""
    require(git(root, 'rev-parse', UX08_REVIEW_BASE + '^{tree}').decode().strip()
            == UX08_REVIEW_BASE_TREE
            and git(root, 'rev-parse', UX08_REVIEW_SOURCE + '^{tree}').decode().strip()
            == UX08_REVIEW_SOURCE_TREE, 'UX-08 read-scope successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_REVIEW_BASE, UX08_REVIEW_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_REVIEW_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_REVIEW_BASE, UX08_REVIEW_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX08_REVIEW_MODIFIED)],
            'UX-08 read-scope successor changed a different path or status set')
    base_entries = entries(root, UX08_REVIEW_BASE)
    source_entries = entries(root, UX08_REVIEW_SOURCE)
    for path in sorted(UX08_REVIEW_MODIFIED):
        current = project_ux08_artifact_proof(root, path, ordinary(root, path))
        require(current
                == git(root, 'cat-file', 'blob', UX08_REVIEW_SOURCE + ':' + path)
                and (path != INVENTORY or ordinary(root, INVENTORY) == canonical_inventory(root))
                and mode(root, path) == base_entries[path][0] == source_entries[path][0],
                'UX-08 read-scope successor source identity changed: ' + path)


def project_ux08_review(root, path, current):
    """Restore pre-review bytes only for the exact eight-path successor."""
    current = project_ux08_artifact_proof(root, path, current)
    if path not in UX08_REVIEW_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_REVIEW_SOURCE + ':' + path)
    require(current == source,
            'Current UX-08 read-scope successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX08_REVIEW_BASE + ':' + path)


def validate_ux08_storefront_source(root):
    """Bind the complete classified Shared/VPS storefront source range."""
    require(git(root, 'rev-parse', UX08_STOREFRONT_BASE + '^{tree}').decode().strip()
            == UX08_STOREFRONT_BASE_TREE
            and git(root, 'rev-parse', UX08_STOREFRONT_SOURCE + '^{tree}').decode().strip()
            == UX08_STOREFRONT_SOURCE_TREE, 'UX-08 storefront source tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_STOREFRONT_BASE, UX08_STOREFRONT_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_STOREFRONT_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_STOREFRONT_BASE, UX08_STOREFRONT_SOURCE).decode().splitlines()
    observed_modified = {line.split('\t', 1)[1] for line in changes if line.startswith('M\t')}
    observed_added = {line.split('\t', 1)[1] for line in changes if line.startswith('A\t')}
    require(len(changes) == len(UX08_STOREFRONT_MODIFIED | UX08_STOREFRONT_ADDITIONS)
            and observed_modified == UX08_STOREFRONT_MODIFIED
            and observed_added == UX08_STOREFRONT_ADDITIONS,
            'UX-08 storefront source changed a different path or status set')
    base_entries = entries(root, UX08_STOREFRONT_BASE)
    source_entries = entries(root, UX08_STOREFRONT_SOURCE)
    require(not UX08_STOREFRONT_ADDITIONS & set(base_entries)
            and UX08_STOREFRONT_ADDITIONS <= set(source_entries),
            'UX-08 storefront additions did not originate in the exact source range')
    for path in sorted(UX08_STOREFRONT_MODIFIED):
        current = project_ux08_payment(root, path, ordinary(root, path))
        current = project_ux08_review(root, path, current)
        require(current
                == git(root, 'cat-file', 'blob', UX08_STOREFRONT_SOURCE + ':' + path)
                and mode(root, path) == base_entries[path][0] == source_entries[path][0],
                'UX-08 storefront modified source identity changed: ' + path)
    for path in sorted(UX08_STOREFRONT_ADDITIONS):
        current = project_ux08_review(root, path, ordinary(root, path))
        require(current
                == git(root, 'cat-file', 'blob', UX08_STOREFRONT_SOURCE + ':' + path)
                and mode(root, path) == source_entries[path][0],
                'UX-08 storefront addition source identity changed: ' + path)


def project_ux08_storefront(root, path, current):
    """Restore pre-storefront bytes only for older exact-source proofs."""
    if path not in UX08_STOREFRONT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_STOREFRONT_SOURCE + ':' + path)
    require(current == source,
            'Current UX-08 storefront source changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX08_STOREFRONT_BASE + ':' + path)


def validate_ux08_inventory_source(root):
    """Bind the canonical inventory refresh for the UX-08 storefront source."""
    require(git(root, 'rev-parse', UX08_INVENTORY_BASE + '^{tree}').decode().strip()
            == UX08_INVENTORY_BASE_TREE
            and git(root, 'rev-parse', UX08_INVENTORY_SOURCE + '^{tree}').decode().strip()
            == UX08_INVENTORY_SOURCE_TREE, 'UX-08 inventory successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX08_INVENTORY_BASE, UX08_INVENTORY_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX08_INVENTORY_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX08_INVENTORY_BASE, UX08_INVENTORY_SOURCE).decode().splitlines()
    require(changes == ['M\t' + INVENTORY],
            'UX-08 inventory successor changed a different path or status set')
    base_entries = entries(root, UX08_INVENTORY_BASE)
    source_entries = entries(root, UX08_INVENTORY_SOURCE)
    current = project_ux08_review(root, INVENTORY, ordinary(root, INVENTORY))
    require(current == git(root, 'cat-file', 'blob', UX08_INVENTORY_SOURCE + ':' + INVENTORY)
            and digest(current) == UX08_INVENTORY_SHA256
            and mode(root, INVENTORY) == base_entries[INVENTORY][0]
            == source_entries[INVENTORY][0] == '100644',
            'UX-08 inventory successor source identity changed')


def project_ux08_inventory(root, path, current):
    """Restore the pre-UX-08 inventory only for older exact-source proofs."""
    if path != INVENTORY:
        return current
    source = git(root, 'cat-file', 'blob', UX08_INVENTORY_SOURCE + ':' + path)
    require(current == source and digest(current) == UX08_INVENTORY_SHA256,
            'Current UX-08 inventory successor changed before predecessor projection')
    return git(root, 'cat-file', 'blob', UX08_INVENTORY_BASE + ':' + path)


def validate_ux09_smtp_rejection_source(root):
    """Bind the explicit SMTP-rejection certainty successor."""
    require(git(root, 'rev-parse', UX09_SMTP_REJECTION_BASE + '^{tree}').decode().strip()
            == UX09_SMTP_REJECTION_BASE_TREE
            and git(root, 'rev-parse', UX09_SMTP_REJECTION_SOURCE + '^{tree}').decode().strip()
            == UX09_SMTP_REJECTION_SOURCE_TREE, 'UX-09 SMTP-rejection successor tree changed')
    git(root, 'merge-base', '--is-ancestor', UX09_SMTP_REJECTION_BASE, UX09_SMTP_REJECTION_SOURCE)
    git(root, 'merge-base', '--is-ancestor', UX09_SMTP_REJECTION_SOURCE, 'HEAD')
    changes = git(root, 'diff', '--name-status', '--no-renames',
                  UX09_SMTP_REJECTION_BASE, UX09_SMTP_REJECTION_SOURCE).decode().splitlines()
    require(changes == ['M\t' + path for path in sorted(UX09_SMTP_REJECTION_MODIFIED)],
            'UX-09 SMTP-rejection successor changed a different path or status set')
    base_entries = entries(root, UX09_SMTP_REJECTION_BASE)
    source_entries = entries(root, UX09_SMTP_REJECTION_SOURCE)
    for path in sorted(UX09_SMTP_REJECTION_MODIFIED):
        current = project_ux08_review(root, path, ordinary(root, path))
        current = project_ux08_inventory(root, path, current)
        current = project_ux08_storefront(root, path, current)
        current = project_ux09_fixture_receipt(root, path, current)
        current = project_ux09_identity_snapshot(root, path, current)
        require(current == git(root, 'cat-file', 'blob', UX09_SMTP_REJECTION_SOURCE + ':' + path)
                and digest(current) == UX09_SMTP_REJECTION_SHA256[path]
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644',
                'UX-09 SMTP-rejection successor source identity changed: ' + path)


def project_ux09_smtp_rejection(root, path, current):
    """Restore pre-SMTP-rejection bytes only for older source receipts."""
    current = project_ux08_review(root, path, current)
    current = project_ux08_inventory(root, path, current)
    current = project_ux08_storefront(root, path, current)
    if path not in UX09_SMTP_REJECTION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX09_SMTP_REJECTION_SOURCE + ':' + path)
    require(current == source and digest(current) == UX09_SMTP_REJECTION_SHA256[path],
            'Current UX-09 SMTP-rejection successor changed before predecessor projection: ' + path)
    return git(root, 'cat-file', 'blob', UX09_SMTP_REJECTION_BASE + ':' + path)


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


def project_ux08_storefront_repair_input(root, path, current):
    """Accept exact storefront or base bytes before an older repair inverse."""
    if path not in UX08_STOREFRONT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', UX08_STOREFRONT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', UX08_STOREFRONT_BASE + ':' + path)
    if path in UX08_FIXTURE_DEPENDENCY_MODIFIED:
        fixture_source = git(root, 'cat-file', 'blob', UX08_FIXTURE_DEPENDENCY_SOURCE + ':' + path)
        fixture_base = git(root, 'cat-file', 'blob', UX08_FIXTURE_DEPENDENCY_BASE + ':' + path)
        require(fixture_base == source,
                'UX-08 fixture repair base differs from storefront source: ' + path)
        if current == fixture_source:
            current = fixture_base
    if current == source:
        return base
    require(current == base, 'Unexpected UX-08 storefront repair input: ' + path)
    return current


def reconstruct_repair(data, path, current):
    """Admit exact current tests; restore old bytes only for source comparison."""
    require(path in REPAIRS, 'Unknown integration repair')
    current = project_hosting_inbox_reload_budget_source(
        Path(__file__).resolve().parents[1], path, current)
    current = project_application_fixture_successors(
        Path(__file__).resolve().parents[1], path, current)
    current = project_mail_artifact_projection_fix(
        Path(__file__).resolve().parents[1], path, current)
    current = project_mail_backup_projection_fix(
        Path(__file__).resolve().parents[1], path, current)
    current = project_ux08_payment(Path(__file__).resolve().parents[1], path, current)
    current = project_ux08_storefront_repair_input(Path(__file__).resolve().parents[1], path, current)
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
    # Include ignored files beneath every tracked top-level source/evidence
    # directory. This prevents a global ignore such as __pycache__/ from
    # hiding executable inputs under ops/, brand/ or future tracked trees.
    areas = {PurePosixPath(path).parts[0] for path in tracked if '/' in path}
    for area in sorted(areas):
        directory = root / area
        require(not directory.is_symlink(), 'Symlink protected source directory: ' + area)
        if directory.exists():
            for target in directory.rglob('*'):
                if target.is_symlink() or target.is_file():
                    paths.add(target.relative_to(root).as_posix())
    return {path for path in paths - NARRATIVES if path in tracked or not generated_bytecode(root, path, source_paths)}


def validate_application_reply_folder_route(root):
    """Bind the retained folder-scoped Reply fixture after preserved run 44943541659."""
    require(git(root, 'rev-parse', APPLICATION_REPLY_FOLDER_ROUTE_BASE + '^{tree}')
            .decode().strip() == APPLICATION_REPLY_FOLDER_ROUTE_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_REPLY_FOLDER_ROUTE_SOURCE + '^{tree}')
            .decode().strip() == APPLICATION_REPLY_FOLDER_ROUTE_SOURCE_TREE,
            'Application Reply folder-route source identity changed')
    require(git(root, 'show', '-s', '--format=%P', APPLICATION_REPLY_FOLDER_ROUTE_SOURCE)
            .decode().strip().split() == [APPLICATION_REPLY_FOLDER_ROUTE_BASE],
            'Application Reply folder-route source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_REPLY_FOLDER_ROUTE_SOURCE, 'HEAD')
    path = APPLICATION_REPLY_FOLDER_ROUTE_PATH
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_REPLY_FOLDER_ROUTE_BASE,
                APPLICATION_REPLY_FOLDER_ROUTE_SOURCE).decode().splitlines()
            == ['M\t' + path],
            'Application Reply folder-route source changed another path or status')
    base_entries = entries(root, APPLICATION_REPLY_FOLDER_ROUTE_BASE)
    source_entries = entries(root, APPLICATION_REPLY_FOLDER_ROUTE_SOURCE)
    base = git(root, 'cat-file', 'blob', APPLICATION_REPLY_FOLDER_ROUTE_BASE + ':' + path)
    source = git(root, 'cat-file', 'blob', APPLICATION_REPLY_FOLDER_ROUTE_SOURCE + ':' + path)
    current = ordinary(root, path)
    old_route = b"    route = {'account_id': aid, 'revision': '1', 'uidvalidity': '1234', 'uid': '82'}"
    new_route = b"    route = {'account_id': aid, 'revision': '1', 'uidvalidity': '1234', 'uid': '82', 'folder': 'INBOX'}"
    require(digest(base) == APPLICATION_REPLY_FOLDER_ROUTE_BASE_SHA256
            and digest(source) == APPLICATION_REPLY_FOLDER_ROUTE_SOURCE_SHA256
            and current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and base.count(old_route) == 1 and source.count(new_route) == 1
            and old_route not in source
            and project_application_reply_folder_route(root, path, current) == base
            and project_application_reply_folder_route(root, path, base) == base,
            'Application Reply folder-route identity or projection changed')


def project_application_reply_folder_route(root, path, current):
    """Restore the accepted e831 source before the folder-route fixture correction."""
    if path != APPLICATION_REPLY_FOLDER_ROUTE_PATH:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_REPLY_FOLDER_ROUTE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_REPLY_FOLDER_ROUTE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application Reply folder-route fixture changed before predecessor projection')
    return base


def validate_application_sent_copy_fixture_mode(root):
    """Bind the finite IMAP fixture correction after preserved run 44913252537."""
    require(git(root, 'rev-parse', APPLICATION_SENT_COPY_FIXTURE_MODE_BASE + '^{tree}')
            .decode().strip() == APPLICATION_SENT_COPY_FIXTURE_MODE_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE + '^{tree}')
            .decode().strip() == APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE_TREE,
            'Application Sent-copy fixture source identity changed')
    require(git(root, 'show', '-s', '--format=%P', APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE)
            .decode().strip().split() == [APPLICATION_SENT_COPY_FIXTURE_MODE_BASE],
            'Application Sent-copy fixture source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE, 'HEAD')
    path = APPLICATION_SENT_COPY_FIXTURE_MODE_PATH
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_SENT_COPY_FIXTURE_MODE_BASE,
                APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE).decode().splitlines()
            == ['M\t' + path],
            'Application Sent-copy fixture source changed another path or status')
    base_entries = entries(root, APPLICATION_SENT_COPY_FIXTURE_MODE_BASE)
    source_entries = entries(root, APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE)
    base = git(root, 'cat-file', 'blob', APPLICATION_SENT_COPY_FIXTURE_MODE_BASE + ':' + path)
    source = git(root, 'cat-file', 'blob', APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE + ':' + path)
    current = ordinary(root, path)
    require(digest(base) == APPLICATION_SENT_COPY_FIXTURE_MODE_BASE_SHA256
            and digest(source) == APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE_SHA256
            and current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_application_sent_copy_fixture_mode(root, path, current) == base
            and project_application_sent_copy_fixture_mode(root, path, base) == base,
            'Application Sent-copy fixture identity or projection changed')
    post = b"r = member.post(attach_copy_page, data={'csrf': token(member.get(attach_copy_page)), 'action': 'mail_submissions_copy_sent', 'submission_id': attach_sid, 'confirm_copy': '1'}); copy_connections += 1"
    lines = source.splitlines()
    index = lines.index(b'    ' + post)
    require(lines[index - 1] == b"    inbox_server.mode = 'append'"
            and lines[index + 1] == b"    inbox_server.mode = 'ok'"
            and lines.count(b"    inbox_server.mode = 'append'") == 2
            and lines.count(b"    inbox_server.mode = 'ok'") == 2,
            'Application Sent-copy fixture lost the bounded append-mode transition')


def project_application_sent_copy_fixture_mode(root, path, current):
    """Restore the accepted a845 source before the IMAP fixture correction."""
    if path != APPLICATION_SENT_COPY_FIXTURE_MODE_PATH:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_SENT_COPY_FIXTURE_MODE_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_SENT_COPY_FIXTURE_MODE_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application Sent-copy fixture changed before predecessor projection')
    return base


def validate_application_smtp_fixture_bound(root):
    """Bind the finite SMTP fixture correction after preserved run 44890368753."""
    require(git(root, 'rev-parse', APPLICATION_SMTP_FIXTURE_BOUND_BASE + '^{tree}')
            .decode().strip() == APPLICATION_SMTP_FIXTURE_BOUND_BASE_TREE
            and git(root, 'rev-parse', APPLICATION_SMTP_FIXTURE_BOUND_SOURCE + '^{tree}')
            .decode().strip() == APPLICATION_SMTP_FIXTURE_BOUND_SOURCE_TREE,
            'Application SMTP fixture source identity changed')
    require(git(root, 'show', '-s', '--format=%P', APPLICATION_SMTP_FIXTURE_BOUND_SOURCE)
            .decode().strip().split() == [APPLICATION_SMTP_FIXTURE_BOUND_BASE],
            'Application SMTP fixture source parent changed')
    git(root, 'merge-base', '--is-ancestor', APPLICATION_SMTP_FIXTURE_BOUND_SOURCE, 'HEAD')
    path = APPLICATION_SMTP_FIXTURE_BOUND_PATH
    require(git(root, 'diff', '--name-status', '--no-renames',
                APPLICATION_SMTP_FIXTURE_BOUND_BASE,
                APPLICATION_SMTP_FIXTURE_BOUND_SOURCE).decode().splitlines()
            == ['M\t' + path],
            'Application SMTP fixture source changed another path or status')
    base_entries = entries(root, APPLICATION_SMTP_FIXTURE_BOUND_BASE)
    source_entries = entries(root, APPLICATION_SMTP_FIXTURE_BOUND_SOURCE)
    base = git(root, 'cat-file', 'blob', APPLICATION_SMTP_FIXTURE_BOUND_BASE + ':' + path)
    source = git(root, 'cat-file', 'blob', APPLICATION_SMTP_FIXTURE_BOUND_SOURCE + ':' + path)
    current = ordinary(root, path)
    require(digest(base) == APPLICATION_SMTP_FIXTURE_BOUND_BASE_SHA256
            and digest(source) == APPLICATION_SMTP_FIXTURE_BOUND_SOURCE_SHA256
            and current == source
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and project_application_smtp_fixture_bound(root, path, current) == base
            and project_application_smtp_fixture_bound(root, path, base) == base,
            'Application SMTP fixture identity or projection changed')
    require(source.count(b"pending=[b'']") == 1
            and source.count(b's.recv(65536)') == 1
            and source.count(b"assert len(line)<=2048,'Synthetic SMTP line overflow'") == 1
            and source.count(b"assert (len(pending[0]) if first_nl<0 else first_nl)<=2048,'Synthetic SMTP line overflow'") == 1
            and source.count(b"assert len(attempt['data'])<=1048576,'Synthetic SMTP DATA overflow'") == 1
            and b's.recv(1)' not in source
            and b"assert len(attempt['data'])<=65536" not in source,
            'Application SMTP fixture lost its buffered read, line bound or one-MiB DATA bound')


def project_application_smtp_fixture_bound(root, path, current):
    """Restore the accepted efa50 source before the bounded fixture correction."""
    if path != APPLICATION_SMTP_FIXTURE_BOUND_PATH:
        return current
    source = git(root, 'cat-file', 'blob', APPLICATION_SMTP_FIXTURE_BOUND_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', APPLICATION_SMTP_FIXTURE_BOUND_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current Application SMTP fixture changed before predecessor projection')
    return base


def validate_runner_application_current_artifact_binding(root):
    """Bind the exact current artifact helper after the preserved hosted assertion failure."""
    require(git(root, 'rev-parse',
                RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE_TREE
            and git(root, 'rev-parse',
                    RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE_TREE,
            'Runner Application current-artifact binding identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE],
            'Runner Application current-artifact binding parent changed')
    git(root, 'merge-base', '--is-ancestor',
        RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE,
                  RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE).decode().splitlines()
    path = RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_PATH
    require(changed == ['M\t' + path],
            'Runner Application current-artifact binding changed another path or status')
    base_entries = entries(root, RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE)
    current = ordinary(root, path)
    source = git(root, 'cat-file', 'blob',
                 RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE + ':' + path)
    old = RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_OLD_SHA256.encode()
    new = RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_NEW_SHA256.encode()
    require(current == source
            and digest(base) == RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE_SHA256
            and digest(source) == RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE_SHA256
            and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
            and source.count(new) == 1 and old not in source
            and base.count(old) == 1 and new not in base
            and source.replace(new, old, 1) == base
            and project_runner_application_current_artifact_binding(root, path, current) == base
            and project_runner_application_current_artifact_binding(root, path, base) == base,
            'Runner Application current-artifact binding identity or projection changed')
    contract = json.loads(current)
    expected = digest(ordinary(root, 'tools/acceptance_artifacts.py').replace(b'\r\n', b'\n'))
    require(expected == RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_NEW_SHA256
            and contract['cloud_site_requests']['published_baseline']['current_source_sha256']
            ['tools/acceptance_artifacts.py'] == expected,
            'Runner Application current artifact helper is not bound to its exact source')


def project_runner_application_current_artifact_binding(root, path, current):
    """Restore the exact failed Application candidate before its source-binding correction."""
    if path != RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_PATH:
        return current
    source = git(root, 'cat-file', 'blob',
                 RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               RUNNER_APPLICATION_CURRENT_ARTIFACT_BINDING_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application artifact binding changed before predecessor projection')
    return base


def validate_runner_application_composition_partition(root):
    """Bind the unchanged full proof to a required same-attempt partition."""
    require(git(root, 'rev-parse', RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE_TREE
            and git(root, 'rev-parse', RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE_TREE,
            'Runner Application composition-partition identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE],
            'Runner Application composition-partition parent changed')
    git(root, 'merge-base', '--is-ancestor',
        RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE,
                  RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path
                        for path in sorted(RUNNER_APPLICATION_COMPOSITION_PARTITION_MODIFIED)],
            'Runner Application composition-partition changed another path or status')
    base_entries = entries(root, RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE)
    for path in sorted(RUNNER_APPLICATION_COMPOSITION_PARTITION_MODIFIED):
        current = project_diagnostic_artifact_batch_source(
            root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob',
                     RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob',
                   RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_composition_partition(root, path, current) == base
                and project_runner_application_composition_partition(root, path, base) == base,
                'Runner Application composition-partition identity or projection changed: ' + path)
    workflow = ordinary(root, '.github/workflows/application-tests.yml')
    require(workflow.count(b'python3 -B tests/combined_source_composition_test.py | tee qa/combined-source-composition-results.json') == 1
            and b'--shallow' not in workflow
            and workflow.count(b'needs: [composition, build]') == 3
            and workflow.count(b'needs: [composition, build, native-and-browser, recovery, apache]') == 1
            and workflow.count(b'"composition": {"result": "${{ needs.composition.result }}"') == 1,
            'Runner Application composition partition command or fail-closed dependency changed')


def project_runner_application_composition_partition(root, path, current):
    """Restore the reviewed 592e head before the isolated full-proof successor."""
    current = project_diagnostic_artifact_batch_source(root, path, current)
    if path not in RUNNER_APPLICATION_COMPOSITION_PARTITION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob',
                 RUNNER_APPLICATION_COMPOSITION_PARTITION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               RUNNER_APPLICATION_COMPOSITION_PARTITION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application composition partition changed before predecessor projection: '
            + path)
    return base


def validate_runner_application_composition_completion(root):
    """Bind the hosted maximum after two preserved 300-minute proof ceilings."""
    require(git(root, 'rev-parse',
                RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE_TREE
            and git(root, 'rev-parse',
                    RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE + '^{tree}').decode().strip()
            == RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE_TREE,
            'Runner Application composition-completion identity changed')
    require(git(root, 'show', '-s', '--format=%P',
                RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE).decode().strip().split()
            == [RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE],
            'Runner Application composition-completion parent changed')
    git(root, 'merge-base', '--is-ancestor',
        RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE,
                  RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path
                        for path in sorted(RUNNER_APPLICATION_COMPOSITION_COMPLETION_MODIFIED)],
            'Runner Application composition-completion changed another path or status')
    base_entries = entries(root, RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE)
    source_entries = entries(root, RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE)
    for path in sorted(RUNNER_APPLICATION_COMPOSITION_COMPLETION_MODIFIED):
        current = project_runner_application_composition_partition(root, path, ordinary(root, path))
        source = git(root, 'cat-file', 'blob',
                     RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob',
                   RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_runner_application_composition_completion(root, path, current) == base
                and project_runner_application_composition_completion(root, path, base) == base,
                'Runner Application composition-completion identity or projection changed: ' + path)
    workflow_path = '.github/workflows/application-tests.yml'
    contract_path = 'tests/account_notification_workflow_contract.py'
    workflow = project_runner_application_composition_partition(
        root, workflow_path, ordinary(root, workflow_path))
    contract = project_runner_application_composition_partition(
        root, contract_path, ordinary(root, contract_path))
    workflow_base = git(root, 'cat-file', 'blob',
                        RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE + ':' + workflow_path)
    contract_base = git(root, 'cat-file', 'blob',
                        RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE + ':' + contract_path)
    workflow_predecessor = (
        b'    # cancelled the evidence seal. Keep every assertion and allow bounded seal.\n'
        b'    timeout-minutes: 300\n')
    workflow_successor = (
        b'    # cancelled the evidence seal. Runs 44792670039 and 44803351271 then passed\n'
        b'    # setup, provenance, package and all 94 build guards before the unchanged\n'
        b'    # composition suite reached the exact 300m job ceiling on slower allocations.\n'
        b'    # Keep every assertion and use the hosted-runner maximum as the finite bound.\n'
        b'    timeout-minutes: 360\n')
    contract_predecessor = (
        b"                                     '    # cancelled the evidence seal. Keep every assertion and allow bounded seal.\\n'\n"
        b"                                     '    timeout-minutes: 300\\n')\n")
    contract_successor = (
        b"                                     '    # cancelled the evidence seal. Runs 44792670039 and 44803351271 then passed\\n'\n"
        b"                                     '    # setup, provenance, package and all 94 build guards before the unchanged\\n'\n"
        b"                                     '    # composition suite reached the exact 300m job ceiling on slower allocations.\\n'\n"
        b"                                     '    # Keep every assertion and use the hosted-runner maximum as the finite bound.\\n'\n"
        b"                                     '    timeout-minutes: 360\\n')\n")
    require(workflow.count(workflow_successor) == 1
            and workflow.replace(workflow_successor, workflow_predecessor, 1) == workflow_base
            and contract.count(contract_successor) == 1
            and contract.replace(contract_successor, contract_predecessor, 1) == contract_base
            and workflow.count(b'python3 -B tests/combined_source_composition_test.py') == 1
            and workflow.count(b'    timeout-minutes: 360\n') == 1
            and workflow.count(b'    timeout-minutes: 300\n') == 0
            and contract.count(b"'    timeout-minutes: 360\\n')") == 2,
            'Runner Application composition-completion bound or retained proof changed')


def project_runner_application_composition_completion(root, path, current):
    """Restore the reviewed dynamic-port proof before the 360-minute correction."""
    current = project_runner_application_composition_partition(root, path, current)
    if path not in RUNNER_APPLICATION_COMPOSITION_COMPLETION_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob',
                 RUNNER_APPLICATION_COMPOSITION_COMPLETION_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob',
               RUNNER_APPLICATION_COMPOSITION_COMPLETION_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current runner Application composition-completion changed before predecessor projection: '
            + path)
    return base


def validate_mail_folder_dynamic_port_source(root):
    """Bind the assigned-port correction after preserved runner failure 136522311121."""
    require(git(root, 'rev-parse', MAIL_FOLDER_DYNAMIC_PORT_BASE + '^{tree}').decode().strip()
            == MAIL_FOLDER_DYNAMIC_PORT_BASE_TREE
            and git(root, 'rev-parse', MAIL_FOLDER_DYNAMIC_PORT_SOURCE + '^{tree}').decode().strip()
            == MAIL_FOLDER_DYNAMIC_PORT_SOURCE_TREE,
            'Mail-folder dynamic-port source identity changed')
    require(git(root, 'show', '-s', '--format=%P', MAIL_FOLDER_DYNAMIC_PORT_SOURCE)
            .decode().strip().split() == [MAIL_FOLDER_DYNAMIC_PORT_BASE],
            'Mail-folder dynamic-port source parent changed')
    git(root, 'merge-base', '--is-ancestor', MAIL_FOLDER_DYNAMIC_PORT_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  MAIL_FOLDER_DYNAMIC_PORT_BASE, MAIL_FOLDER_DYNAMIC_PORT_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(MAIL_FOLDER_DYNAMIC_PORT_MODIFIED)],
            'Mail-folder dynamic-port correction changed another path or status')
    base_entries = entries(root, MAIL_FOLDER_DYNAMIC_PORT_BASE)
    source_entries = entries(root, MAIL_FOLDER_DYNAMIC_PORT_SOURCE)
    for path in sorted(MAIL_FOLDER_DYNAMIC_PORT_MODIFIED):
        current = ordinary(root, path)
        source = git(root, 'cat-file', 'blob', MAIL_FOLDER_DYNAMIC_PORT_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', MAIL_FOLDER_DYNAMIC_PORT_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_mail_folder_dynamic_port_source(root, path, current) == base
                and project_mail_folder_dynamic_port_source(root, path, base) == base,
                'Mail-folder dynamic-port source identity or projection changed: ' + path)
    workflow = ordinary(root, '.github/workflows/mail-folder-native.yml')
    contract = ordinary(root, 'tests/mail_folder_native_workflow_contract.py')
    require(workflow.count(b"ports: ['3306']") == 1
            and b"ports: ['33306:3306']" not in workflow
            and b"CWP_TEST_PORT: '33306'" not in workflow
            and workflow.count(b'CWP_TEST_PORT: ${{ job.services.database.ports[3306] }}') == 2
            and workflow.count(b"php: ['8.2', '8.4']") == 1
            and workflow.count(b'database: [mariadb, mysql]') == 1
            and contract.count(b'Every database cell uses its assigned collision-free service port') == 1
            and contract.count(b"'fixed database host port'") == 1,
            'Mail-folder assigned-port scope, matrix or executable refusal changed')


def project_mail_folder_dynamic_port_source(root, path, current):
    """Restore the reviewed f779 proof head before the collision-free port correction."""
    if path not in MAIL_FOLDER_DYNAMIC_PORT_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', MAIL_FOLDER_DYNAMIC_PORT_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', MAIL_FOLDER_DYNAMIC_PORT_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current mail-folder dynamic-port source changed before predecessor projection: ' + path)
    return base


def validate_signed_ppa_label_source(root):
    """Bind the exact signed-label correction after preserved run 44792670112."""
    require(git(root, 'rev-parse', SIGNED_PPA_LABEL_BASE + '^{tree}').decode().strip()
            == SIGNED_PPA_LABEL_BASE_TREE
            and git(root, 'rev-parse', SIGNED_PPA_LABEL_SOURCE + '^{tree}').decode().strip()
            == SIGNED_PPA_LABEL_SOURCE_TREE,
            'Signed PHP PPA label source identity changed')
    require(git(root, 'show', '-s', '--format=%P', SIGNED_PPA_LABEL_SOURCE)
            .decode().strip().split() == [SIGNED_PPA_LABEL_BASE],
            'Signed PHP PPA label source parent changed')
    git(root, 'merge-base', '--is-ancestor', SIGNED_PPA_LABEL_SOURCE, 'HEAD')
    changed = git(root, 'diff', '--name-status', '--no-renames',
                  SIGNED_PPA_LABEL_BASE, SIGNED_PPA_LABEL_SOURCE).decode().splitlines()
    require(changed == ['M\t' + path for path in sorted(SIGNED_PPA_LABEL_MODIFIED)],
            'Signed PHP PPA label correction changed another path or status')
    base_entries = entries(root, SIGNED_PPA_LABEL_BASE)
    source_entries = entries(root, SIGNED_PPA_LABEL_SOURCE)
    for path in sorted(SIGNED_PPA_LABEL_MODIFIED):
        current = project_runner_application_composition_completion(root, path, ordinary(root, path))
        current = project_mail_folder_dynamic_port_source(root, path, current)
        source = git(root, 'cat-file', 'blob', SIGNED_PPA_LABEL_SOURCE + ':' + path)
        base = git(root, 'cat-file', 'blob', SIGNED_PPA_LABEL_BASE + ':' + path)
        require(current == source
                and mode(root, path) == base_entries[path][0] == source_entries[path][0] == '100644'
                and project_signed_ppa_label_source(root, path, current) == base
                and project_signed_ppa_label_source(root, path, base) == base,
                'Signed PHP PPA label source identity or projection changed: ' + path)
    for path in ('.github/workflows/application-tests.yml',
                 '.github/workflows/account-notifications-acceptance.yml'):
        require(git(root, 'cat-file', 'blob', SIGNED_PPA_LABEL_BASE + ':' + path)
                == git(root, 'cat-file', 'blob', SIGNED_PPA_LABEL_SOURCE + ':' + path),
                'Signed PHP PPA label correction changed hosted workflow bytes: ' + path)
    script = ordinary(root, '.github/scripts/prepare_ubuntu_apt.py')
    contract = ordinary(root, 'tests/account_notification_workflow_contract.py')
    require(digest(script) == SIGNED_PPA_LABEL_SCRIPT_SHA256
            and script.count(b"PHP_PPA_RELEASE_LABELS = ('PPA for PHP', 'PPA for PHP (deprecated)')") == 1
            and script.count(b"fingerprint = 'B8DC7E53946656EFBCE4C1DD71DAEAAB4AD4CAB6'") == 1
            and script.count(b"'origin': 'LP-PPA-ondrej-php'") == 1
            and script.count(b"if not release_identity_allowed(records, os.environ['CWP_PHP_VERSION']):") == 1
            and contract.count(SIGNED_PPA_LABEL_SCRIPT_SHA256.encode()) == 1
            and contract.count(b'Signed release allowlist accepts the exact deprecated PHP PPA label') == 1
            and contract.count(b'Signed release allowlist refuses an unknown PPA label') == 1
            and contract.count(b'Signed release allowlist refuses a changed publisher origin') == 1
            and contract.count(b'Signed release allowlist refuses additional identity fields') == 1,
            'Signed label allowlist, publisher identity or executable negative cases changed')


def project_signed_ppa_label_source(root, path, current):
    """Restore the preserved 975a proof head before the exact signed-label correction."""
    current = project_runner_application_composition_completion(root, path, current)
    current = project_mail_folder_dynamic_port_source(root, path, current)
    if path not in SIGNED_PPA_LABEL_MODIFIED:
        return current
    source = git(root, 'cat-file', 'blob', SIGNED_PPA_LABEL_SOURCE + ':' + path)
    base = git(root, 'cat-file', 'blob', SIGNED_PPA_LABEL_BASE + ':' + path)
    if current == source:
        return base
    if current == base or current != ordinary(root, path):
        return current
    require(current == source,
            'Current signed PHP PPA label source changed before predecessor projection: ' + path)
    return base


def project_component_union_source(root, ux09, path, current):
    """Apply each terminal successor chain once for the component-union check."""
    current = project_account_rate_boundary_source(root, path, current)
    current = project_ux09_fixture_receipt(root, path, current)
    current = project_ux09_identity_snapshot(root, path, current)
    current = project_ux09_smtp_rejection(root, path, current)
    current = project_ux09_mail_review(root, path, current)
    current = project_ux09_cloud_proof(root, path, current)
    current = project_ux09_security(root, path, current)
    current = project_ux09_ci(root, path, current)
    return project_ux09(root, ux09, path, current)


def current_proof_receipt(root):
    """Strictly load and bracket the three mutable proof files."""
    root = Path(root)
    proof_files = {
        VERIFIER: ordinary(root, VERIFIER),
        RECEIPT: ordinary(root, RECEIPT),
        REGRESSION: ordinary(root, REGRESSION),
    }
    receipt_data = strict_json_loads(proof_files[RECEIPT])
    data = validate_receipt(receipt_data, proof_files[VERIFIER])
    require(receipt_content_digest(receipt_data) == RECEIPT_CONTENT_SHA256,
            'Composition receipt content changed')
    require(digest(proof_files[REGRESSION]) == REGRESSION_SHA256,
            'Composition regression changed')
    require(all(mode(root, path) == '100644'
                and ordinary(root, path) == value
                for path, value in proof_files.items()),
            'Composition proof file changed during receipt validation')
    return data, proof_files


def _verify_current(root):
    root = Path(root)
    data, proof_files = current_proof_receipt(root)
    ensure_history(root)
    pin_head(root)
    tracked_source_preflight(root, proof_files)
    require(all(mode(root, path) == '100644' and ordinary(root, path) == value
                for path, value in proof_files.items()),
            'Composition proof file changed during tracked-source preflight')
    validate_application_reply_folder_route(root)
    validate_application_sent_copy_fixture_mode(root)
    validate_application_smtp_fixture_bound(root)
    validate_runner_application_current_artifact_binding(root)
    validate_runner_application_composition_partition(root)
    validate_runner_application_composition_completion(root)
    validate_mail_folder_dynamic_port_source(root)
    validate_signed_ppa_label_source(root)
    validate_application_http_expiry_fix_source(root)
    validate_application_mail_copies_routing_source(root)
    validate_application_mail_copies_setup_source(root)
    validate_account_rate_boundary_source(root)
    validate_application_sent_copy_absence_source(root)
    validate_application_fresh_draft_readiness_source(root)
    validate_application_browser_fixture_source(root)
    validate_application_notices_fixture_source(root)
    validate_mail_list_identity(root)
    validate_mail_flags_report_count(root)
    validate_application_fixture_successors(root)
    validate_latest_acceptance_successors(root)
    validate_mail_acceptance_hardening_source(root)
    validate_mail_acceptance_repair_source(root)
    validate_mail_admission_evidence_source(root)
    validate_mail_acceptance_portability_source(root)
    validate_mail_reply_all_replay_source(root)
    validate_mail_submission_expiry_fix(root)
    validate_mail_attachment_claim_fix(root)
    validate_mail_attachment_submission_fix(root)
    validate_mail_attachment_readiness_fix(root)
    validate_mail_attachment_auth_fix(root)
    validate_mail_attachment_source(root)
    validate_mail_attachment_portable_source(root)
    validate_mail_sent_copy_origin_fix(root)
    validate_mail_sent_copy_source(root)
    validate_mail_consolidated_review_recovery(root)
    validate_mail_consolidated_review_fix(root)
    validate_mail_consolidated_source(root)
    validate_mail_copy_controls_fix(root)
    validate_mail_reply_form_helper_fix(root)
    validate_mail_mysql_trigger_fix(root)
    validate_mail_thread_alias_fix(root)
    validate_mail_native_fixture_policy_fix(root)
    validate_mail_reply_session_fix(root)
    validate_mail_thread_header_fix(root)
    validate_mail_artifact_overlap_fix(root)
    validate_mail_artifact_projection_fix(root)
    validate_mail_backup_projection_fix(root)
    validate_mail_reply_forward_workflow_fix(root)
    validate_mail_reply_forward_source(root)
    validate_runner_account_source_third_budget(root)
    validate_runner_account_source_second_budget(root)
    validate_runner_account_source_final_budget(root)
    validate_runner_application_seal_budget(root)
    validate_runner_aws_full_mirror_source(root)
    validate_runner_application_build_completion(root)
    validate_runner_application_build_budget(root)
    validate_runner_account_source_completion(root)
    validate_runner_account_source_budget(root)
    validate_runner_finite_tool_refusal_source(root)
    validate_runner_intake_current_binding_source(root)
    validate_runner_application_intake_projection_source(root)
    validate_runner_aws_mirror_fixture_source(root)
    validate_runner_application_intake_binding(root)
    validate_runner_aws_mirror_profile_source(root)
    validate_runner_application_historical_binding(root)
    validate_runner_application_source_binding(root)
    validate_runner_aws_profile_source(root)
    validate_runner_evidence_provenance_source(root)
    validate_runner_application_completion_source(root)
    validate_runner_keyring_fixture_source(root)
    validate_runner_keyring_isolation_source(root)
    validate_runner_mirror_phase_source(root)
    validate_runner_mirror_compat_source(root)
    validate_pr388_resulting_main_source(root)
    validate_project_private_notes_stale_fixture_source(root)
    validate_project_private_notes_notification_fixture_source(root)
    validate_project_private_notes_fk_fixture_source(root)
    validate_project_private_notes_native_fixture_source(root)
    validate_project_private_notes_static_intake_source(root)
    validate_project_private_notes_proof_compat_source(root)
    validate_project_private_notes_authority_source(root)
    validate_project_private_notes_evidence_source(root)
    validate_project_private_notes_source(root)
    validate_notification_health_source(root)
    validate_domain_expiry_port_source(root)
    validate_proof_runtime_source(root)
    validate_proof_cache_hardening_source(root)
    validate_diagnostic_artifact_batch_source(root)
    validate_diagnostic_artifact_failure_evidence_source(root)
    validate_diagnostic_blob_batch_source(root)
    validate_diagnostic_blob_live_binding_source(root)
    validate_diagnostic_projection_cache_source(root)
    validate_diagnostic_projection_cache_race_source(root)
    validate_diagnostic_tracked_preflight_source(root)
    validate_diagnostic_tracked_preflight_cancellation_source(root)
    validate_diagnostic_untracked_preflight_source(root)
    validate_diagnostic_protected_tree_preflight_source(root)
    validate_diagnostic_receipt_content_source(root)
    validate_diagnostic_strict_receipt_source(root)
    validate_diagnostic_current_receipt_consumer_source(root)
    validate_hosting_inbox_reload_completion_source(root)
    validate_hosting_inbox_reload_budget_source(root)
    validate_notification_metric_label_source(root)
    validate_pr391_recovery_successor(root)
    validate_ux08_checkout_pin_fix_source(root)
    validate_ux08_hosted_mirror_contract_fix_source(root)
    validate_ux08_payment_outcome_proof_fix_source(root)
    validate_ux08_payment_fulfillment_validation_lock_fix_source(root)
    validate_ux08_payment_fulfillment_lock_fix_source(root)
    validate_ux08_payment_expected_diagnostic_fix_source(root)
    validate_ux08_payment_form_label_fix_source(root)
    validate_ux08_payment_form_diagnostic_fix_source(root)
    validate_ux08_inbox_payment_readiness_fix_source(root)
    validate_ux08_payment_artifact_projection_fix_source(root)
    validate_ux08_payment_current_publication_fix_source(root)
    validate_ux08_payment_publication_pin_fix_source(root)
    validate_ux08_payment_fixture_lock_fix_source(root)
    validate_ux08_billing_network_fix_source(root)
    validate_resulting_main_client_inbox_budget_source(root)
    validate_resulting_main_inbox_click_budget_source(root)
    validate_resulting_main_inbox_navigation_budget_source(root)
    validate_ux08_inbox_network_fix_source(root)
    validate_ux08_agreement_duplicate_fix_source(root)
    validate_ux08_agreement_link_fix_source(root)
    validate_ux08_inbox_record_fix_source(root)
    validate_ux08_inbox_operations_fix_source(root)
    validate_ux08_inbox_state_correction_source(root)
    validate_ux08_inbox_state_fix_source(root)
    validate_ux08_storefront_network_fix_source(root)
    validate_ux08_browser_network_fix_source(root)
    validate_ux08_payment_diagnostic_fix_source(root)
    validate_ux08_payment_native_fix_source(root)
    validate_ux08_payment_source(root)
    validate_ux08_browser_stability_source(root)
    validate_ux08_browser_navigation_source(root)
    validate_ux08_fixture_dependency_source(root)
    validate_ux08_artifact_proof_source(root)
    validate_ux08_review_source(root)
    validate_ux08_storefront_source(root)
    validate_ux08_inventory_source(root)
    validate_resulting_main_diagnostic_mail_reply_source(root)
    validate_ux09_diagnostic_registry_source(root)
    validate_ux09_migration_preservation_source(root)
    validate_ux09_bounded_token_refusal_source(root)
    validate_ux09_audit_rollback_source(root)
    validate_ux09_commercial_snapshot_source(root)
    validate_ux09_fixture_receipt_source(root)
    validate_ux09_identity_snapshot_source(root)
    validate_ux09_smtp_rejection_source(root)
    validate_ux09_mail_review_source(root)
    validate_ux09_cloud_proof_source(root)
    validate_ux09_security_source(root)
    ux09 = validate_ux09_source(root)
    validate_ux09_ci_source(root)
    onboarding = json.loads(ordinary(root, ONBOARDING_SOURCE))
    require(onboarding.get('schema') == 1 and onboarding.get('base_commit') == ONBOARDING_BASE
            and onboarding.get('base_tree') == ONBOARDING_BASE_TREE,
            'Software onboarding source receipt identity changed')
    require(set(onboarding.get('modified', {})) == ONBOARDING_MODIFIED
            and set(onboarding.get('added', {})) == ONBOARDING_ADDITIONS,
            'Software onboarding source receipt path set changed')
    require(git(root, 'rev-parse', ONBOARDING_BASE + '^{tree}').decode().strip() == ONBOARDING_BASE_TREE,
            'Software onboarding base tree changed')
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
    require(not UX08_PAYMENT_ADDITIONS & (set(expected) | ADAPTERS | REPAIRS | ADDITIONS
                                          | TICKET_ADDITIONS | DIAGNOSTIC_ADDITIONS
                                          | ONBOARDING_ADDITIONS | DATABASE_WORKFLOW_ADDITIONS
                                          | CLOUD_INTAKE_ADDITIONS | UX09_ADDITIONS
                                          | UX08_STOREFRONT_ADDITIONS | NARRATIVES
                                          | {INVENTORY, RECEIPT, VERIFIER, REGRESSION,
                                             UX09_SOURCE_RECEIPT}),
            'UX-08 payment additions cannot replace existing admitted source or proof files')
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
        value = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        value = project_ux09_identity_snapshot(root, path, value)
        value = project_ux09_smtp_rejection(root, path, value)
        value = project_ux09_mail_review(root, path, value)
        value = project_ux09_cloud_proof(root, path, value)
        value = project_ux09_security(root, path, value)
        value = project_ux09(root, ux09, path, value)
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
        value = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        value = project_ux09_identity_snapshot(root, path, value)
        value = project_ux09_smtp_rejection(root, path, value)
        value = project_ux09_mail_review(root, path, value)
        value = project_ux09_cloud_proof(root, path, value)
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
        value = project_resulting_main_diagnostic_mail_reply_source(root, path, ordinary(root, path))
        value = project_ux09_diagnostic_registry(root, path, value)
        value = project_ux09_fixture_receipt(root, path, value)
        value = project_ux09_identity_snapshot(root, path, value)
        value = project_ux09_smtp_rejection(root, path, value)
        value = project_ux09_mail_review(root, path, value)
        value = project_ux09_cloud_proof(root, path, value)
        value = project_ux09_security(root, path, value)
        value = project_ux09(root, ux09, path, value)
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
        value = project_ux09_fixture_receipt(root, path, ordinary(root, path))
        value = project_ux09_identity_snapshot(root, path, value)
        value = project_ux09_smtp_rejection(root, path, value)
        value = project_ux09_mail_review(root, path, value)
        value = project_ux09_cloud_proof(root, path, value)
        value = project_ux09_security(root, path, value)
        value = project_ux09(root, ux09, path, value)
        row = data['database_workflow_additions'][path]
        require(digest(value) == row['sha256'] == DATABASE_WORKFLOW_ADDITION_SHA256[path]
                and row['mode'] == database_source_entries[path][0] == '100644',
                'Database workflow addition bytes or mode changed: ' + path)
    allowed = (set(expected) | ADAPTERS | ADDITIONS | TICKET_ADDITIONS | DIAGNOSTIC_ADDITIONS
               | ONBOARDING_ADDITIONS | DATABASE_WORKFLOW_ADDITIONS | CLOUD_INTAKE_ADDITIONS
               | UX09_ADDITIONS | UX08_STOREFRONT_ADDITIONS | UX08_PAYMENT_ADDITIONS
               | RUNNER_MIRROR_PHASE_ADDITIONS | NOTIFICATION_HEALTH_ADDITIONS
               | MAIL_REPLY_FORWARD_ADDITIONS
               | MAIL_SENT_COPY_ADDITIONS
               | MAIL_SENT_COPY_ORIGIN_FIX_PATHS
               | MAIL_ATTACHMENT_PORTABLE_ADDITIONS
               | MAIL_ATTACHMENT_ADDITIONS
               | PROJECT_PRIVATE_NOTES_ADDITIONS
               | {path for path,status in mail_consolidated_changes(root).items() if status == 'A'}
               | {INVENTORY, RECEIPT, VERIFIER, REGRESSION, ONBOARDING_SOURCE,
                  UX09_SOURCE_RECEIPT})
    actual = actual_paths(root, allowed)
    require(actual == allowed, 'Unknown or missing composition paths: '
            + repr(sorted(actual.symmetric_difference(allowed))))
    for path, (permission, oid) in expected.items():
        value = project_component_union_source(root, ux09, path, ordinary(root, path))
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
    for path in sorted(UX08_STOREFRONT_ADDITIONS):
        value = project_ux08_payment(root, path, ordinary(root, path))
        value = project_ux08_review(root, path, value)
        require(value == git(root, 'cat-file', 'blob', UX08_STOREFRONT_SOURCE + ':' + path)
                and mode(root, path) == '100644',
                'UX-08 storefront addition no longer matches its exact source: ' + path)
    for path in sorted(UX08_PAYMENT_ADDITIONS):
        value = project_ux08_payment_native_fix(root, path, ordinary(root, path))
        require(value
                == git(root, 'cat-file', 'blob', UX08_PAYMENT_SOURCE + ':' + path)
                and mode(root, path) == '100644',
                'UX-08 payment addition no longer matches its exact source: ' + path)
    for path in sorted(MAIL_REPLY_FORWARD_ADDITIONS):
        current = project_application_reply_folder_route(root, path, ordinary(root, path))
        current = project_mail_copy_controls_fix(root, path, current)
        current = project_mail_reply_session_fix(root, path, current)
        current = project_mail_native_fixture_policy_fix(root, path, current)
        current = project_mail_reply_forward_workflow_fix(root, path, current)
        require(current
                == git(root, 'cat-file', 'blob', MAIL_REPLY_FORWARD_SOURCE + ':' + path)
                and mode(root, path) == '100644',
                'Mail Reply/Forward addition no longer matches its exact source: ' + path)
    for path in sorted(MAIL_ATTACHMENT_PORTABLE_ADDITIONS):
        require(ordinary(root, path)
                == git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_PORTABLE_SOURCE + ':' + path)
                and mode(root, path) == '100644',
                'Portable attachment addition no longer matches its exact source: ' + path)
    for path in sorted(MAIL_ATTACHMENT_ADDITIONS):
        current = project_mail_attachment_auth_fix(root, path, ordinary(root, path))
        require(current == git(root, 'cat-file', 'blob', MAIL_ATTACHMENT_SOURCE + ':' + path)
                and mode(root, path) == '100644',
                'Attachment implementation addition no longer matches its exact source: ' + path)
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
    pin_head(root)
    require(all(mode(root, path) == '100644' and ordinary(root, path) == value
                for path, value in proof_files.items()),
            'Composition proof file changed during verification')
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
        {'name': 'Exact resulting-main-to-Reply/Forward range binds ' + str(len(MAIL_REPLY_FORWARD_MODIFIED)) + ' modified paths and ' + str(len(MAIL_REPLY_FORWARD_ADDITIONS)) + ' disjoint additions', 'passed': True},
        {'name': 'Exact Reply/Forward workflow successor binds dependency-free browser-module compilation after the preserved first-attempt failure', 'passed': True},
        {'name': 'Exact structured-thread successor preserves long message identifiers without RFC 2047 transformation', 'passed': True},
        {'name': 'Exact security successor detaches caller aliases and revalidates structured identifiers at render', 'passed': True},
        {'name': 'Exact native-fixture successor admits a policy-valid host while retaining injected resolver and transport boundaries', 'passed': True},
        {'name': 'Exact mail backup-projection successor restores Reply/Forward browser bytes before historical UX-09 and backup proofs', 'passed': True},
        {'name': 'Exact mail artifact-projection successor restores Reply/Forward browser bytes before the public-identity receipt', 'passed': True},
        {'name': 'Exact mail artifact-overlap successor projects all four bounded mail paths before identity and schema receipts', 'passed': True},
        {'name': 'Exact resulting-main-to-UX-09 range binds ' + str(len(UX09_MODIFIED)) + ' modified paths and six disjoint additions', 'passed': True},
        {'name': 'Exact qualified-main-to-UX-08-payment range binds ' + str(len(UX08_PAYMENT_MODIFIED)) + ' modified paths and eight disjoint additions', 'passed': True},
        {'name': 'Exact PR #391 recovery predecessor is retained and the current range is limited to its proof files and explicitly bound successors', 'passed': True},
        {'name': 'Exact billing-network-proof-to-payment-fixture-lock-fix range binds two reversible test paths', 'passed': True},
        {'name': 'Exact storefront-proof-head-to-inbox-state-fix range binds one reversible read-only browser fixture path', 'passed': True},
        {'name': 'Exact browser-proof-head-to-storefront-network-fix range binds one reversible test path and performs no live tokenization fetch', 'passed': True},
        {'name': 'Exact diagnostic-proof-head-to-browser-network-fix range binds one reversible test path and performs no live tokenization fetch', 'passed': True},
        {'name': 'Exact native-helper-head-to-payment-diagnostic-fix range binds two reversible test and contract paths', 'passed': True},
        {'name': 'Exact recovery-head-to-native-payment-fix range binds two reversible test and contract paths', 'passed': True},
        {'name': 'Exact UX-08 installed-browser successor binds the native Hosting disclosure contract and reversible predecessor bytes', 'passed': True},
        {'name': 'Exact initial-CI successor binds the private-draft fixture to current identity schema', 'passed': True},
        {'name': 'Exact review-blocker successor binds bounded mail readiness, overlapping links and neutral timing', 'passed': True},
        {'name': 'Exact Sent-copy successor binds explicit confirmation, immutable MIME, uncertainty and additive storage', 'passed': True},
        {'name': 'Exact Sent-copy review successor restores copied-origin Read authority before attempt and transport', 'passed': True},
        {'name': 'Exact outgoing-attachment successors bind portable formats 1..8, private storage, current authority, supported migration and fail-closed protected submission', 'passed': True},
        {'name': 'Exact first-attempt mail successor binds signed runner admission, portable HTML, exact schemas, no-INI dependencies and corrected fixtures', 'passed': True},
        {'name': 'Exact Application browser successor binds the current bounded attachment capability copy after the preserved first-attempt failure', 'passed': True},
        {'name': 'Exact Application notices successor restores the retained-v1.18 owner migration order without changing application behavior', 'passed': True},
        {'name': 'Exact Application fresh-install successor binds formatted-draft readiness and the canonical installer inventory after the preserved browser failure', 'passed': True},
        {'name': 'Exact Application Sent-copy evidence successor proves absent pre-migration storage without querying an absent table', 'passed': True},
        {'name': 'Exact Application Sent-copy setup successor uses the owner listing route while retaining the singular account editor and every copy-safety assertion', 'passed': True},
        {'name': 'Exact Application Sent-copy routing successor dispatches setup and folder actions through explicit staff-manage plus owner controls with CSRF and non-owner refusal evidence', 'passed': True},
        {'name': 'Exact Application HTTP expiry-fixture successor uses the canonical numeric epoch under the strict BIGINT grant schema after preserved run 44729363443', 'passed': True},
        {'name': 'Exact signed-label successor admits only the two reviewed PPA labels under the retained publisher fingerprint after preserved run 44792670112', 'passed': True},
        {'name': 'Exact mail-folder runner successor uses only assigned service ports after preserved fixed-port failure 136522311121', 'passed': True},
        {'name': 'Exact Application Reply successor posts the retained INBOX route identity after preserved run 44943541659', 'passed': True},
        {'name': 'Exact Application Sent-copy fixture successor restores the APPEND protocol around the larger MIME assertion after preserved run 44913252537', 'passed': True},
        {'name': 'Exact Application SMTP-fixture successor retains byte-exact attachment acceptance after preserved run 44890368753', 'passed': True},
        {'name': 'Exact Application current-artifact successor binds the helper source after preserved first-attempt run 44881476341', 'passed': True},
        {'name': 'Exact Diagnostic artifact batch retains one full current-source proof before the finite isolated historical comparisons', 'passed': True},
        {'name': 'Exact Diagnostic artifact review successor preserves named failures; the immutable blob-batch successor retains complete source reconstruction with bounded tree state', 'passed': True},
        {'name': 'Exact Diagnostic tracked-source preflight refuses staged and unstaged non-proof drift before historical projection', 'passed': True},
        {'name': 'Exact tracked-source correction refuses offsetting index/worktree drift and binds staged proof bytes and modes', 'passed': True},
        {'name': 'Exact untracked-source preflight refuses non-ignored additions before projection while retaining generated-cache compatibility', 'passed': True},
        {'name': 'Exact protected-tree preflight inventories ignored inputs under every tracked top-level directory', 'passed': True},
        {'name': 'Exact canonical receipt digest rejects all non-self-binding content drift before historical projection', 'passed': True},
        {'name': 'Exact strict receipt parser refuses duplicate names and non-finite constants before validation', 'passed': True},
        {'name': 'Exact current receipt consumers share one strict loader and bracket proof bytes before and after use', 'passed': True},
        {'name': 'Exact Application composition-partition successor requires the unchanged full proof after preserved 360-minute run 44847291464', 'passed': True},
        {'name': 'Exact Application composition-completion successor retains the full proof after two preserved 300-minute hosted ceilings', 'passed': True},
        {'name': 'Exact Application evidence-seal successor retains every build assertion after preserved run 44639431573 reached the 240-minute ceiling', 'passed': True},
        {'name': 'Exact account rate-boundary successor keeps both six-request security bursts inside one fixed window after preserved run 44676679254', 'passed': True},
        {'name': 'Exact account source-budget successor retains every proof command after the preserved 180-minute ceiling', 'passed': True},
        {'name': 'Exact admission-evidence successor retains structured signed-source diagnostics on both success and failure', 'passed': True},
        {'name': 'Exact runner compatibility successor admits only the source-qualified fully converted GitHub AWS mirror list', 'passed': True},
        {'name': 'Exact Reply All replay successor binds native SQL and HTTP controller no-network no-write acceptance', 'passed': True},
        {'name': 'Exact notification-health review successor labels retained delivery rows as records and preserves its regression assertion', 'passed': True},
        {'name': 'Exact hosted-proof successor projects only later UX-09 commands and reports from cloud-intake history', 'passed': True},
        {'name': 'CI-discovered workflow, predecessor-projection and browser-fixture successors are exact and reversible', 'passed': True},
        {'name': 'Current canonical installer inventory binds the complete combined shipping tree', 'passed': True},
        {'name': 'Unknown, missing, symlink and ignored source additions are refused', 'passed': True},
        {'name': 'Original standalone proofs, receipts, historical failures and workflow bodies are unchanged', 'passed': True},
    ]


@contextmanager
def verification_projection_cache(root):
    """Bound repeated pure projections to one immutable verification pass.

    Projection functions receive exact bytes and return exact predecessor bytes.
    The full proof invokes the same functions hundreds of thousands of times for
    identical path/byte pairs. Cache only those exact calls for this one proof
    and retain a strict LRU bound. Each outer projection chain is bracketed by
    fresh worktree reads; nested pure projections for that same root/path reuse
    the already-bracketed exact bytes in their cache keys. A different root or
    path starts its own bracket, so mutable-source changes cannot reuse results.
    """
    global _LAST_PROJECTION_CACHE_MAX
    root = Path(root)
    literal = os.fspath(root)
    resolved = str(root.resolve())
    require(literal not in _ACTIVE_ROOT_KEYS,
            'Nested composition projection cache is forbidden')
    _ACTIVE_ROOT_KEYS[literal] = resolved
    cached = OrderedDict()
    originals = {}
    active = []
    maximum = 0

    def bind(name, function):
        @functools.wraps(function)
        def retained(root_value, path, current):
            nonlocal maximum
            call_root = root_key(root_value)
            require(isinstance(path, str) and isinstance(current, bytes),
                    'Malformed cached composition projection')
            parent = active[-1] if active else None
            nested = (parent is not None
                      and parent[0] == call_root and parent[1] == path)
            # The outer call reads mutable checkout bytes afresh and brackets
            # the complete nested pure-projection chain. Nested calls reuse
            # only those exact already-bracketed bytes; a different root/path
            # establishes an independent fresh-read bracket.
            live = parent[2] if nested else ordinary(Path(root_value), path)
            key = (call_root, name, path, len(current), digest(current),
                   len(live), digest(live))
            if key in cached:
                result = cached[key]
                if not nested:
                    require(ordinary(Path(root_value), path) == live,
                            'Composition source changed during cached projection: '
                            + path)
                cached.move_to_end(key)
                return result
            active.append((call_root, path, live))
            try:
                result = function(root_value, path, current)
            finally:
                require(active.pop() == (call_root, path, live),
                        'Composition projection bracket changed')
            require(isinstance(result, bytes),
                    'Composition projection did not return exact bytes: ' + name)
            if not nested:
                require(ordinary(Path(root_value), path) == live,
                        'Composition source changed during projection: ' + path)
            cached[key] = result
            while len(cached) > _PROJECTION_CACHE_LIMIT:
                cached.popitem(last=False)
            maximum = max(maximum, len(cached))
            return result
        return retained

    for name, function in list(globals().items()):
        if (name.startswith('project_') and inspect.isfunction(function)
                and tuple(inspect.signature(function).parameters) ==
                    ('root', 'path', 'current')):
            originals[name] = function
            globals()[name] = bind(name, function)
    try:
        yield
    finally:
        for name, function in originals.items():
            globals()[name] = function
        try:
            require(str(root.resolve()) == resolved,
                    'Composition root changed during verification')
        finally:
            _ACTIVE_ROOT_KEYS.pop(literal, None)
            _LAST_PROJECTION_CACHE_MAX = maximum


def verify_current(root):
    with verification_projection_cache(root):
        with immutable_blob_batch(root):
            return _verify_current(root)


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
                and method == 'source'
                and path in {'tools/acceptance-reports.json', 'tools/acceptance_artifacts.py'}),
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
            data, proof_files = current_proof_receipt(root)
            value = reconstruct_repair(data, path, value)
            require(value == git(root, 'cat-file', 'blob', COMPONENTS['account']['commit'] + ':' + path),
                    'Core comparison repair does not retain the exact account component: ' + path)
            require(all(mode(root, proof_path) == '100644'
                        and ordinary(root, proof_path) == proof_value
                        for proof_path, proof_value in proof_files.items()),
                    'Composition proof file changed during account projection')
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


def _extend_provider_artifact_rows(checks, batches):
    """Retain structured historical outcomes, including their exact failures."""
    require(isinstance(checks, list) and isinstance(batches, list),
            'Malformed provider artifact outcome collection')
    for rows in batches:
        require(isinstance(rows, list) and rows,
                'Incomplete provider artifact historical validation')
        for row in rows:
            require(isinstance(row, dict) and set(row) == {'name', 'passed'}
                    and isinstance(row['name'], str) and bool(row['name'])
                    and type(row['passed']) is bool,
                    'Malformed provider artifact historical outcome')
        checks.extend(rows)


def provider_browser_artifact_validations(root, validations):
    """Verify current source once, then run the exact historical artifact comparisons.

    The historical calls below execute only in the disposable, detached account
    component checkout created by ``original_call``.  They cannot read current
    worktree bytes, so repeating the complete current-source proof before every
    call adds no coverage.  Keep the request finite and ordered so this batching
    boundary cannot become a general verification bypass.
    """
    expected = (
        ('standalone_repair_provenance',
         ('docs/STANDALONE_ACCEPTANCE_REPAIRS.json',)),
        ('combined_repair_provenance',
         ('docs/COMBINED_ACCEPTANCE_REPAIR_SOURCE.json',
          'docs/COMBINED_ACCEPTANCE_REMAINING_SOURCE.json')),
    )
    require(isinstance(validations, tuple) and len(validations) == len(expected),
            'Incomplete provider artifact validation batch')
    requests = []
    for request in validations:
        require(isinstance(request, tuple) and len(request) == 2,
                'Malformed provider artifact validation request')
        validator, receipt_paths = request
        require(callable(validator) and validator.__name__ == 'validate'
                and isinstance(receipt_paths, tuple),
                'Unreviewed provider artifact validator')
        requests.append((validator.__module__, receipt_paths))
    require(tuple(requests) == expected,
            'Unreviewed provider artifact validation batch')

    checks = validate(root)
    batches = [original_call(root, 'account',
                             'provider_browser_fixture_provenance', 'validate')]
    batches.extend(
        original_call(root, 'account', 'provider_browser_fixture_provenance',
                      'projected_validation', callback=module, paths=list(paths))
        for module, paths in requests
    )
    _extend_provider_artifact_rows(checks, batches)
    return checks


if __name__ == '__main__':
    checks = validate(Path(__file__).resolve().parents[1])
    print(json.dumps({'passed': True, 'count': len(checks), 'components': COMPONENTS, 'checks': checks,
                      'scope': 'Exact current composition and original source-only proofs. No SQL, browser, provider, merchant, main or release certification.'}, indent=2))
