"""Exact component composition for historical source checks, never runtime code.

The live checkout must equal the finite union of the reviewed component trees,
plus the canonical inventory, four reversible comparison adapters, exact
integration repairs and four contact files bound to the retained PR331 source.
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
# END GENERATED CONTACT ADDITION IDENTITIES
REPAIRS = frozenset((
    '.github/workflows/consolidation-after-backups.yml',
    'docs/CLIENT_PORTAL_ROADMAP.md',
    'site/admin/controllers/studio.php',
    'site/admin/views/studio/clients.php',
    'site/admin/views/studio/job.php',
    'site/admin/views/studio/upgrade.php',
    'site/api/bundle-download.php',
    'site/api/software-download.php',
    'site/app/activity/events.php',
    'site/app/clients/accounts.php',
    'site/app/clients/files.php',
    'site/app/clients/identity.php',
    'site/app/licenses/financing_offers.php',
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
    'tests/organized_update_cases.py',
    'tests/owner_browser_forms.py',
    'tests/owner_browser_forms_test.py',
    'tests/proposal_version_binding.php',
    'tests/proposals_native.php',
    'tests/provider_directory_listing_controls_cases.py',
    'tests/provider_force_ssl_controls_cases.py',
    'tests/provider_schedule_cases.py',
    'tests/release_cases.py',
    'tests/reset_template_acceptance_contract.py',
    'tests/service_plan_cases.py',
    'tests/service_plans_native.php',
    'tests/support_tickets_native.php',
    'site/app/billing/authorize_net_webhooks.php',
    'site/app/billing/software_subscription_settle.php',
    'tests/payments_cases.py',
    'tests/payments_native.php',
    'tests/software_webhook_contract.php',
    'tools/acceptance-contract.json',
    'tools/acceptance-reports.json',
))
# Session narratives may be prefixed by the integration owner. They are not
# executable/source receipts; every other historical document remains bound.
NARRATIVES = frozenset((
    'CHANGELOG.md', 'PROJECT_STATUS.md', 'SESSION_HANDOFF.md',
    'docs/DECISIONS.md', 'docs/DEVIN_INTEGRATION_QUEUE.md',
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
REGRESSION_SHA256 = '13bf56104e133adb2ca57b8ef422471885313659ed82527437bccf2ceb5a3e6e'
# END GENERATED COMPOSITION IDENTITIES

# BEGIN GENERATED INTEGRATION REPAIR IDENTITIES
REPAIR_SHA256 = {'.github/workflows/consolidation-after-backups.yml': 'd3e4cf7343cb1454917c691af8acb7b7456e704e735bd7fa5695a646c19279b5', 'docs/CLIENT_PORTAL_ROADMAP.md': '8e31a75b47ea79d8059bbaf9a8c530426c47a5299bd8fb6b65806d4ba42c75bd', 'site/admin/controllers/studio.php': 'b19f05cc44f88489523c53079f229b46c06419b27c68b1b6c367be01f92b4b26', 'site/admin/views/studio/clients.php': '5d42172fb9767e187564c4af59d75c519f5f2c942593f5c59d037b98358370b3', 'site/admin/views/studio/job.php': '87f17d26b213db3ae9b03d343f7a5afc4e215691b34d7507f206e372d9f92f5e', 'site/admin/views/studio/upgrade.php': 'ab60c55217f08d6fc021cea3a9e996feed1c1e289cac6b1f5717b9706d8c9239', 'site/api/bundle-download.php': 'abd65140460543c13b99d3c20e5454306664f084d505ea57b90e5752b17aa4bf', 'site/api/software-download.php': '702c86f6f7ba0bedcb9fc464efb6418701b94fb044bd5718491095d205cc57dd', 'site/app/activity/events.php': '011a2fc5ce9dc93cd414e4ed637157d1c772e56f321d3fd8e9c0ba18a72508e4', 'site/app/billing/authorize_net_webhooks.php': '4aa434e8b9bbb2f75b0ae5590e0b0d219f2d7ef92497c5c2290dd888ed1386db', 'site/app/billing/software_subscription_settle.php': 'cb9f7486e57079d82ca786a2c04e71a98edd95b42d1d8d5ab5e23784ffcd17a2', 'site/app/clients/accounts.php': '9535abe5b5688a82e630c5fc84d58b661c882082eb8358de5b1b9f928ea0e5cd', 'site/app/clients/files.php': 'e8bbd51f4c4b6baf518ff4ae7dab866f6b46bb59a3f434ef78f51df19f360db0', 'site/app/clients/identity.php': '61df7e8e02ca3362a27dfcdad7841df065d201d0d3dcc25a900f71ccdd5cecc7', 'site/app/licenses/financing_offers.php': '2b87d8cfc93cd31594eba6143390413d326e7f637393b22e4b243de869ddd447', 'site/app/marketing/proposals.php': '41f5e7afcc6d680ed801bcc685b3e5c5ac8450263bd945c30b7d84f8cf534bcb', 'site/app/notifications/events.php': '8a55bb62ebe6b8d2a8ad03e60a93610d3b48af2bbab8a64eee8895ed0decbce4', 'site/app/notifications/feed.php': '1013c10b3452d903a49e772dd326e6ffdf0d0b9306ec5302bfd4813f27b5725c', 'site/app/plans/records.php': '985e2472db522bb70ccaa0205f95f21e4e5030bf5596b091864617f91c1e4b04', 'site/app/staff/accounts.php': 'b7d58ff6866f6ceae240f1325555d230b4a24ab291dd1dc01469031bdf8465ec', 'site/app/support/ticket_choices.php': '4e91ccd9764e814f3a4d9e6e8902632a148e6fd6a4e72e6869c1c4b216081167', 'site/app/support/ticket_notifications.php': '058f41c8c146ebe82cf72bad8fa7e9bc754e1e58b791d52e9169f0795fd48867', 'site/app/support/ticket_reads.php': '5e824bdfbc9a37fe77110464d670ae58b0ea7ccb7bcd9c635f7a449082a06868', 'site/app/support/ticket_views.php': '313d6bdfe400ee63be9534a0ccf1e014a14db71922fe7e8af68de5adf381100b', 'site/app/support/tickets.php': '1e9183db114a24a648ccdc7b66514ac5ab94066925237be22bf8abab0b8b5dd1', 'site/assets/css/premium.css': '790097578fe9219d52a8581e3aae761c4646b5145590ae4b6e15d74a690d1743', 'site/assets/js/site.js': '3da42a595a67d5b8fea689785267353f0424a90ac03bb9d80c4d8e6a160cef16', 'site/database/schema/base.php': 'b0de5962e671267dba3dde791a9f4bf90b2cfe3967d803038d663562a51c4cea', 'site/includes/layout/admin.php': '243d2ae7c1aed11fb82f2cdd504ae5424c75a636b713d0d92d2bbcaba49f0044', 'site/includes/layout/notifications.php': '222d1869e75dc47415617814c508f5132cca130291d7a2642609b4c05e705bb0', 'site/includes/layout/proposals.php': 'e4ed91ec5a436d05f0febbe4ab340f317a6205de9a6a5f5c47eb830daa223e17', 'site/install/actions/deploy.php': '1c5cd699653e278cc2e56bf336039b3765fc00e5c220040068e33487b426917a', 'site/pages/home/body.php': '77c19a7ae72842d9bb1a8cfe73d25de8c6c46f0f845adc1dd1266e210e11962c', 'site/portal/controllers/actions.php': 'e0bfb2c185492d09b43747f9cda83ad28f67c41211b126c60b6081bbe1de4226', 'site/portal/controllers/router.php': '3d00beebce20b6c700232e091b60ecee9766c34751a0496ec5dc1806a0720a63', 'site/portal/views/auth.php': '4d0a9b73b32ac6230f2ace6c0b822107ab0fd0d2a015c46023df9acf5aec4b95', 'site/portal/views/financing.php': '37f385593cb7fe34beb5e24020602b9c27b178062b6e704d86f72945356d045c', 'site/portal/views/software.php': 'a36d3e71f9abfb327e3ed6707c16e0a7dcbe811e735358fd7c8a3301de7524fc', 'site/portal/views/tickets.php': 'e584022a5f53b56c05623176a854ba8f7e160a1834cac056602072ccd8a80767', 'site/portal/views/workspace.php': '3559010a49bcbbacfe0aac0c70d440a6f0de18df262372a8ffb53b4e10c28e61', 'tests/acceptance_artifacts_test.py': '9c1da1c8bafbc2febe87d66d5c1d6368e8e0c159e848fd54a3a64ae2108763dc', 'tests/backups_acceptance_wiring.py': '525486323025784b00ffa7d347f6663a6a5aa6e86574830c77ba5b18f1116741', 'tests/browser_acceptance.py': '3b8f81333b9712eb8aa3a5c6bb4e5f28b4ab64687933e7f333016d6a2e053f80', 'tests/catalog_storefront_checkout_cases.py': '843048d926ca4187c9a2f60a3a360b43e46311f336b5c32573f4f2af76778213', 'tests/client_authority_native.php': 'af236ef91f9bcb33b219e8f8d34774ed56c652ef8dd00f69a2f97115d3a7fab9', 'tests/cloud_site_requests_wiring.py': 'ee50481194217b3c6f669347f6fd64275ef87b8b07b800939f2325af51eca27e', 'tests/hosting_billing_options_cases.py': '4ac8630913e7812e11f536bc8663a2dd06891f49e5c2c4c1ed119ae713334cc5', 'tests/hosting_billing_options_wiring.py': 'b59b8e4bc1a017abdb77fc9fb309e98ff13029bf652d4e82ae0e5ac2aee274a0', 'tests/hosting_catalog_plan_setup_cases.py': '51bd299de65d61e40f2ec34bf7deccad452e14281761e0a0b2a38b54ca281508', 'tests/hosting_checkout_http_browser.py': '507c9ff9a450a9a09f4d8750711d0bd363232474fa13e4b14899692b18f1b5aa', 'tests/hosting_checkout_http_cases.py': '14401d06c4295f5bca794db60d875689c0de7de7c231fcac390fe0e72346e509', 'tests/hosting_checkout_http_fixture.php': '77d69fab3506b1e6a903318efdacfe168c1afb047b63bc7b7fe461099dce610d', 'tests/hosting_checkout_native.php': '7690790bc095fca8f7690518390ab36e3f8ec83d53b40f10e4161f823f0aef97', 'tests/hosting_purchase_inbox_cases.py': 'c09947207bb3b996f325e630f0a57b419369626d2be5808747a30ceca75dfe89', 'tests/installed_owner_authority.php': 'bbc48036af794564a1b39b54eb218d3390f94a65861d355d5e4ccef4c7c69863', 'tests/installed_owner_authority.py': 'a9511d52459ac1a9d50d62b646198ab29258718491f0c53f339bf32d7f0e3b71', 'tests/installed_owner_authority_test.py': '48e1930aa6d3e0d8ea7d02a5001718e68c15463fb9b9c62e6cfb2513b69c8101', 'tests/license_financing_cases.py': '7c23b44721f97a718d99b06e449dbd379bfab856ec7452a9d193ef7b031ec3c1', 'tests/license_financing_native_cases.php': '230a6a2ef6d39fcecff1a028b3941e7ef3e87e9154ceb17ab82f75d69cc5ff0d', 'tests/organized_update_cases.py': '9ffe696d822add3a7013e72cb6a99f5bbe44994d5fc83ebe9d2d8c5d15322674', 'tests/owner_browser_forms.py': 'abb07881c43514fd6ac9241700fb2fe97a881095b122d3d092a4ef82924616a3', 'tests/owner_browser_forms_test.py': 'ae4cac11289a97e2d20cf4755ed924f7f438e01d8c5550994ff7b86a144a4b54', 'tests/payments_cases.py': 'e445a3a65c19bdf02ee3f1027e17b4d8f3bed39ecb0a995609af57e82800c3ad', 'tests/payments_native.php': 'e732ca439d3bb460cc530714dcd03b70f332282c2681f75ed2324a114e3c6316', 'tests/proposal_version_binding.php': 'e2b01d643092b15eebc8cc8b2b22aa5b4eb22a4bb30bce4b970249c09c8bdbbd', 'tests/proposals_native.php': 'a3ab274887a5f4b6b65fd1bdef447adee9fbd820d467402f81bdc4ccddf8611d', 'tests/provider_directory_listing_controls_cases.py': 'de983d82a43bfe75c1f01fa443be944451bd1fdba16ef9c77e4c0c161096f25f', 'tests/provider_force_ssl_controls_cases.py': 'a764c99c984514afb9b9d6ac129fd272ead523a274ab9c8ebd29e6c6436e6d68', 'tests/provider_schedule_cases.py': '2a0bbd5fd2a67195a17242339810e2dee2196f2d212b16835e314024578862ca', 'tests/release_cases.py': '235ba3512076d5231808bc0f32912534b4ac249852f1f5cb9d0209d0fa07f0f6', 'tests/reset_template_acceptance_contract.py': '7d9d58a4fafacddd8b3d9d62ffe5222a4099e297c8cf05aaf00e54e2f52bf3a9', 'tests/service_plan_cases.py': '0d4a34bff3eacbe3a8051315e04953cdbfe7be1438106eddfa8707877425e363', 'tests/service_plans_native.php': '45a2c1d888bee7b694b06f6f873fe0dc6874a4c3b0234d937e7d70abbc927058', 'tests/software_webhook_contract.php': 'a361af569d195dfc22ce2649ad60961de52e47d3fad9dc3d4b8be2fbfcf263e6', 'tests/support_tickets_native.php': '2128bba21f9d53410e5077dfe5566a988bbe14a27bdd270298d52baf5fc58e19', 'tools/acceptance-contract.json': 'bce78109de45f93f2bf0ea675ee613e7fe2262199d0c56e02d274e9061858733', 'tools/acceptance-reports.json': 'c50ee66cba8731b60877687fdc18d76146e67b8e3ffe7e532ad09d89bd52fa91'}
# END GENERATED INTEGRATION REPAIR IDENTITIES

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
        'addition_source', 'addition_source_tree', 'additions'},
        'Unexpected composition receipt fields')
    require(data['format'] == 3 and data['base'] == BASE and data['base_tree'] == BASE_TREE
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
    return data


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
    require(mode(root, VERIFIER) == mode(root, RECEIPT) == mode(root, REGRESSION) == '100644',
            'Composition proof file mode changed')
    require(digest(ordinary(root, REGRESSION)) == REGRESSION_SHA256, 'Composition regression changed')
    ensure_history(root)
    require(git(root, 'rev-parse', REPAIR_BASE + '^{tree}').decode().strip() == REPAIR_BASE_TREE,
            'Failed acceptance checkpoint tree changed')
    git(root, 'merge-base', '--is-ancestor', REPAIR_BASE, 'HEAD')
    expected, changed = expected_union(root)
    require(REPAIRS <= set(expected) and not REPAIRS & ADAPTERS,
            'Integration repairs must retain existing component files')
    require(not ADDITIONS & (set(expected) | ADAPTERS | REPAIRS
                            | NARRATIVES | {INVENTORY, RECEIPT, VERIFIER, REGRESSION}),
            'Contact additions cannot replace existing component or proof files')
    require(git(root, 'rev-parse', ADDITION_SOURCE + '^{tree}').decode().strip() == ADDITION_SOURCE_TREE,
            'Retained contact source tree changed')
    git(root, 'merge-base', '--is-ancestor', ADDITION_SOURCE, 'HEAD')
    addition_entries = entries(root, ADDITION_SOURCE)
    require(ADDITIONS <= set(addition_entries), 'Missing contact addition in retained source')
    allowed = set(expected) | ADAPTERS | ADDITIONS | {INVENTORY, RECEIPT, VERIFIER, REGRESSION}
    actual = actual_paths(root, allowed)
    require(actual == allowed, 'Unknown or missing composition paths: '
            + repr(sorted(actual.symmetric_difference(allowed))))
    for path, (permission, oid) in expected.items():
        value = ordinary(root, path)
        if path in REPAIRS:
            value = reconstruct_repair(data, path, value)
            require(value == git(root, 'cat-file', 'blob', REPAIR_BASE + ':' + path),
                    'Integration repair no longer retains its exact failed-checkpoint source: ' + path)
        blob = hashlib.sha1(b'blob ' + str(len(value)).encode() + b'\0' + value).hexdigest()
        require(blob == oid and mode(root, path) == permission, 'Current component source or mode differs: ' + path)
    for path in sorted(ADDITIONS):
        original = reconstruct_addition(data, path, ordinary(root, path))
        require(mode(root, path) == addition_entries[path][0] == '100644'
                and original == git(root, 'cat-file', 'blob', ADDITION_SOURCE + ':' + path),
                'Contact addition no longer retains its exact original source or mode: ' + path)
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
