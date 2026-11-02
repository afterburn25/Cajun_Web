"""Exact component composition for historical source checks, never runtime code.

The live checkout must equal the finite union of the reviewed component trees,
plus the canonical inventory, four reversible comparison adapters and the exact
integration-test repairs bound to the retained failed acceptance checkpoint.
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
REPAIRS = frozenset((
    'tests/installed_owner_authority.php',
    'tests/installed_owner_authority.py',
    'tests/installed_owner_authority_test.py',
    'tests/owner_browser_forms.py',
    'tests/owner_browser_forms_test.py',
    'tests/provider_force_ssl_controls_cases.py',
    'tests/provider_directory_listing_controls_cases.py',
    '.github/workflows/application-tests.yml',
    '.github/workflows/mail-compose.yml',
    '.github/workflows/mail-receiving.yml',
    '.github/workflows/mail-submission.yml',
    'docs/ADMIN_MAIL_ROADMAP.md',
    'foundation/mail-compose/DraftContent.php',
    'foundation/mail-receiving/HeaderSession.php',
    'foundation/mail-receiving/InboxReader.php',
    'foundation/mail-receiving/MessageReader.php',
    'foundation/mail-receiving/ReadProfile.php',
    'foundation/mail-receiving/ReadSession.php',
    'foundation/mail-receiving/ReceivingFailure.php',
    'foundation/mail-receiving/ReceivingStream.php',
    'foundation/mail-receiving/bootstrap.php',
    'foundation/mail-submission/OfflineMailer.php',
    'foundation/mail-submission/PreparedMime.php',
    'foundation/mail-submission/SubmissionSnapshot.php',
    'site/admin/controllers/mail_accounts.php',
    'site/admin/controllers/mail_drafts.php',
    'site/admin/controllers/mail_inbox.php',
    'site/admin/controllers/mail_message.php',
    'site/admin/controllers/mail_submissions.php',
    'site/admin/views/mail_accounts.php',
    'site/admin/views/mail_drafts.php',
    'site/admin/views/mail_inbox.php',
    'site/admin/views/mail_message.php',
    'site/admin/views/mail_submissions.php',
    'site/app/mail/drafts.php',
    'site/app/mail/inbox.php',
    'site/app/mail/messages.php',
    'site/app/mail/notices.php',
    'site/app/mail/submission_views.php',
    'site/app/mail/submissions.php',
    'site/app/staff/accounts.php',
    'site/database/migrations/mail_drafts_migrate.php',
    'site/database/migrations/mail_inbox_migrate.php',
    'site/database/migrations/mail_messages_migrate.php',
    'site/database/migrations/mail_notices_migrate.php',
    'site/database/schema/catalog_validate_schema.php',
    'site/database/schema/mail_drafts_schema.php',
    'site/database/schema/mail_inbox_schema.php',
    'site/database/schema/mail_messages_schema.php',
    'site/database/schema/mail_notices_schema.php',
    'site/includes/foundation/mail-compose/DraftContent.php',
    'site/includes/foundation/mail-receiving/HeaderSession.php',
    'site/includes/foundation/mail-receiving/InboxReader.php',
    'site/includes/foundation/mail-receiving/MessageReader.php',
    'site/includes/foundation/mail-receiving/ReadProfile.php',
    'site/includes/foundation/mail-receiving/ReadSession.php',
    'site/includes/foundation/mail-receiving/ReceivingFailure.php',
    'site/includes/foundation/mail-receiving/ReceivingStream.php',
    'site/includes/foundation/mail-receiving/bootstrap.php',
    'site/includes/foundation/mail-submission/OfflineMailer.php',
    'site/includes/foundation/mail-submission/PreparedMime.php',
    'site/includes/foundation/mail-submission/SubmissionSnapshot.php',
    'tests/apache_acceptance.py',
    'tests/browser_acceptance.py',
    'tests/mail-receiving/session_test.php',
    'tests/mail-submission/mime.php',
    'tests/mail_drafts_cases.py',
    'tests/mail_drafts_form.php',
    'tests/mail_drafts_native.php',
    'tests/mail_inbox_cases.py',
    'tests/mail_inbox_http_server.py',
    'tests/mail_inbox_native.php',
    'tests/mail_message_cases.py',
    'tests/mail_message_native.php',
    'tests/mail_notices_cases.py',
    'tests/mail_notices_native.php',
    'tests/mail_submission_forms.php',
    'tests/mail_submission_server.py',
    'tests/mail_submission_upgrade_cases.py',
    'tests/mail_submissions_cases.py',
    'tests/mail_submissions_native.php',
    'tools/acceptance-contract.json',
    'tools/acceptance-reports.json',
    'tests/acceptance_artifacts_test.py',
    'tests/cloud_site_requests_wiring.py',
    'tests/reset_template_acceptance_contract.py',
    'tests/license_financing_cases.py',
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
REGRESSION_SHA256 = '05aef331b516865759c956d84236d950167c3862ca56162bf8bb797468054849'
# END GENERATED COMPOSITION IDENTITIES

# BEGIN GENERATED INTEGRATION REPAIR IDENTITIES
REPAIR_SHA256 = {'tools/acceptance-contract.json': '98e4ed5c6c8c0a07583969c77417a1e35e3fa507500bb7ff04c1a05ab5f01a01', 'tests/mail_submissions_native.php': '69c7ad1cdd35dcdaae50deade263773e0403cbab46ee7078715f27a8e84a38e6', 'tests/mail_submissions_cases.py': 'e9d7a512f657ed5129766382f7f4c8c4aaaade9594e8602c90eeccb93f07eaad', 'tests/mail_submission_upgrade_cases.py': '347bdc44e2ecb30c76b9df3f31b23b34857bee3e98dce7185d19961f1eb296ec', 'tests/mail_submission_server.py': 'fb7b6d970aaf1c3bd40f3a071d795ca54b3ef76a2468902dbe6abc99e117cdab', 'tests/mail_submission_forms.php': '7e6fb7a911694bf7f6478e3ca6e62742289d309cac287fc64f8fa271ce62da75', 'tests/mail_notices_native.php': 'cec162a3a535b742f2f7bbbf29b8eafdfe0ed343305be23ccbba5be4dfbb9bdd', 'tests/mail_notices_cases.py': '03ae19d5d49cde16654e9941dd73a4565c48b20e8f5a7fe85aa473a4347480f7', 'tests/mail_message_native.php': 'acc5fdafb73663e31cc7f42b4179f9463a425b91e32c92c2239b8d82f9d259af', 'tests/mail_message_cases.py': 'ead9d1653184f362e23857f6fb6d207dea7ff6352efa23d831e0eccd5b94d522', 'tests/mail_inbox_native.php': '7940bbf662215555db0ec1c0cdfbd2e333bd426022a0858674b61f9bd5eb0e24', 'tests/mail_inbox_http_server.py': '7cdd9984506e0511dbaaecc8dda205060ff7541cea09644b12758f76b375184c', 'tests/mail_inbox_cases.py': '5cf176d8c9bfb3dd502910883db2d3426ebdc6618536e3801bbb7f926ba404f4', 'tests/mail_drafts_native.php': '109d831cd882dfb4d20454dd9e634ce82cd55ed64ed6d6bbfdd5bedff3d5d0fb', 'tests/mail_drafts_form.php': 'f7c7d2464ba2b047a42c00e61d76011cb88015210136f6da3825a5fe0e882a94', 'tests/mail_drafts_cases.py': 'e4bb6d5277d66f232edaac3753f7c764e4a01fa1d80d8495a9e1a4f4e37144af', 'tests/mail-submission/mime.php': 'c1bb30ab5da81aa0a3b81891228702a273ec3af947477edbcf91ada7da74c836', 'tests/mail-receiving/session_test.php': '0c2a0d4aaeb2ea453939da260080ba85ce266853787abb4a74e8e9badf489346', 'tests/browser_acceptance.py': '05a4046b4547ab1ad1caac2f1d603f4771c709fe23b87e48f14b2e90bad4c4c9', 'tests/apache_acceptance.py': 'ea60935aef870404c49e7a666f0bd179f5bd9de79b7b0bbd20ce2bd1ccf0326d', 'site/includes/foundation/mail-submission/SubmissionSnapshot.php': 'be250e3472a8467236151cd3d4959d04d2029b2861bd9dc40a90c45c89d06348', 'site/includes/foundation/mail-submission/PreparedMime.php': '6fd0f9c77e2922be8b886a8ad28cefdc3fa7ec2d3bc0f33ce100306461f605f2', 'site/includes/foundation/mail-submission/OfflineMailer.php': '88213d92d3e0e74d580a011e9dc0ac943caaa9b77aac64ed3999e9ad62da227e', 'site/includes/foundation/mail-receiving/bootstrap.php': 'b2d42fea9e5dfb2111576f42af057625e44627207e37ce544c2629dea7f08f78', 'site/includes/foundation/mail-receiving/ReceivingStream.php': 'e3ff6bedbe20842f28013e1b73b4b2e410183f76180e90d543228474bc1afa23', 'site/includes/foundation/mail-receiving/ReceivingFailure.php': 'd22f14d999013732e86699a671257967ff4153ec0e70caead4aad84f7c7b807e', 'site/includes/foundation/mail-receiving/ReadSession.php': '237dd882cca2a327a46ed04b29d499f7c9995f6cd0e8296184a4fbee88857048', 'site/includes/foundation/mail-receiving/ReadProfile.php': '63602313b26fe3f1853c978e28fbd4825f7f462944fecead14f1a92e4e489dc6', 'site/includes/foundation/mail-receiving/MessageReader.php': 'b588f58b9be4fcf14b8cd5ba517e887665f073a3898887c88928188aa7326d4c', 'site/includes/foundation/mail-receiving/InboxReader.php': 'd71905669831c7dfd10b99870234e26e638a39aca8fde1fd2e0c9465c1749a64', 'site/includes/foundation/mail-receiving/HeaderSession.php': '86a0e59d3451e78b579e2911791860b19dc170b1f5ebfd224057c2518da14696', 'site/includes/foundation/mail-compose/DraftContent.php': '28cbea1fe0d47105202e7ee01420fb3bf8bc4fe33d17dbb98f8f8939bdea08a7', 'site/database/schema/mail_notices_schema.php': '708298d8d225eaac595b4c6e0a776a61e6a15ecedf7f2ed3be790c462c46c244', 'site/database/schema/mail_messages_schema.php': '0ce6cbe9a6acfef63107ca63894fdffbf8cd274b58d58a3c80f91eafad35dd58', 'site/database/schema/mail_inbox_schema.php': '088197fa5ac468fc6c66df5514c7e8673ea3298a546bb2aa34c3ea4a132779bd', 'site/database/schema/mail_drafts_schema.php': 'cc98349b7e3344d96f2606e70df6ac95dceea0b354f42598f2f5f8a188aa24d3', 'site/database/schema/catalog_validate_schema.php': '0adbd4e031ab0bdfe657d093cade476fa89c8e02218d7c20e6d502d55cc1b3e1', 'site/database/migrations/mail_notices_migrate.php': '56fbe057402e45d9b2a2090de661e4698a93ac5758a3c8cb14fe14b6486d6ec4', 'site/database/migrations/mail_messages_migrate.php': 'e414e3b5457ae9f3edf49d4265e347e34bda4c0a223fd0695e20ed0fae301188', 'site/database/migrations/mail_inbox_migrate.php': '5d688802e002cb9af7d425cbd8a5c1d375b7f00981bac89d5ab95a2594f18413', 'site/database/migrations/mail_drafts_migrate.php': '4726910fe238662ff9fc4473d847bf0b118c87f02ed19b6d5a31ede77098e917', 'site/app/staff/accounts.php': 'bedc0d33a29f3543f05d428f347c05d3b22987395cfc0c8c7af364fa69186c35', 'site/app/mail/submissions.php': '91eb96d4fec03600a0bf79a3913c9e412ce17e0df64fb3734e996601e3c6f711', 'site/app/mail/submission_views.php': 'aed3ad8d74edecf8c168aba9a519a6fb11d5ab2a465ee6c24708c84fa77162e2', 'site/app/mail/notices.php': '3c87295279b5b84b305a33b51e8d6a723eabc8230255292a0cf47a7a0cbf593c', 'site/app/mail/messages.php': '3aeecf91fae03a5cbb0b3d9c3aae81d92b720b4f2a66b91a5435e5a3fc26c6b5', 'site/app/mail/inbox.php': '1441123aa651d1c89b23a8a55d781d82a2b9b0638405ca4f7120e418d91dc294', 'site/app/mail/drafts.php': 'b9db7a92538a09e8534dfd786933cb629dd7971021a7f3409a3bd6f51a62c35a', 'site/admin/views/mail_submissions.php': 'b6bad155493ef5774a74845228220003fdc8bfe638e99f6832edc7b5bdfaca18', 'site/admin/views/mail_message.php': '44777aa2ada8648a8a0af3e86c6b761b5b8b47ad3c6b7bbbaeb6cd4c6198698f', 'site/admin/views/mail_inbox.php': 'f41131ccffbbe4c34d1c9553f4e34167b1d23e8564811938bdb428f6fc15884f', 'site/admin/views/mail_drafts.php': 'c133cb77b934120f0b0cfac58d87ca3d82c0881a6ffb4bc05669a9637b0fcb1d', 'site/admin/views/mail_accounts.php': '1e348af956c217604ce7aadbcac8ef65e30524fe33cf955edbdc0e1e36c278a3', 'site/admin/controllers/mail_submissions.php': '3c182a4e798849384fc5eb9c3b5cb9af5e53dc07c976f4ffe4ff8f9cebc0fee3', 'site/admin/controllers/mail_message.php': '8758b1e3c7def3431be30d3770b5d387411392f28906aad74cd74746dc7b2842', 'site/admin/controllers/mail_inbox.php': '44e485a8bd45af51e0c12be668cfce42b90aebd176faba688bf930cfaeb8c444', 'site/admin/controllers/mail_drafts.php': '0cc8769d79fd922ff19c231903c5b74d4b6f1e1f8620d8b5e332b35fa2c4a53e', 'site/admin/controllers/mail_accounts.php': 'b0be0f65928c0457fbbbec2ed6dad449569200a05b87447589dc7539258d09a3', 'foundation/mail-submission/SubmissionSnapshot.php': 'be250e3472a8467236151cd3d4959d04d2029b2861bd9dc40a90c45c89d06348', 'foundation/mail-submission/PreparedMime.php': '6fd0f9c77e2922be8b886a8ad28cefdc3fa7ec2d3bc0f33ce100306461f605f2', 'foundation/mail-submission/OfflineMailer.php': '88213d92d3e0e74d580a011e9dc0ac943caaa9b77aac64ed3999e9ad62da227e', 'foundation/mail-receiving/bootstrap.php': 'b2d42fea9e5dfb2111576f42af057625e44627207e37ce544c2629dea7f08f78', 'foundation/mail-receiving/ReceivingStream.php': 'e3ff6bedbe20842f28013e1b73b4b2e410183f76180e90d543228474bc1afa23', 'foundation/mail-receiving/ReceivingFailure.php': 'd22f14d999013732e86699a671257967ff4153ec0e70caead4aad84f7c7b807e', 'foundation/mail-receiving/ReadSession.php': '237dd882cca2a327a46ed04b29d499f7c9995f6cd0e8296184a4fbee88857048', 'foundation/mail-receiving/ReadProfile.php': '63602313b26fe3f1853c978e28fbd4825f7f462944fecead14f1a92e4e489dc6', 'foundation/mail-receiving/MessageReader.php': 'b588f58b9be4fcf14b8cd5ba517e887665f073a3898887c88928188aa7326d4c', 'foundation/mail-receiving/InboxReader.php': 'd71905669831c7dfd10b99870234e26e638a39aca8fde1fd2e0c9465c1749a64', 'foundation/mail-receiving/HeaderSession.php': '86a0e59d3451e78b579e2911791860b19dc170b1f5ebfd224057c2518da14696', 'foundation/mail-compose/DraftContent.php': '28cbea1fe0d47105202e7ee01420fb3bf8bc4fe33d17dbb98f8f8939bdea08a7', 'docs/ADMIN_MAIL_ROADMAP.md': '2f73fc4dfef0948034099dd01097f2a6be30ba4a3cfceaa453460734acf1bc15', 'CHANGELOG.md': '86a298b94614798a31ae4f49bfa77db55655ef38e4651568b0f0edcf1c28a987', '.github/workflows/mail-submission.yml': '01a175ac676b96f7df64556583ddbb47cfa11608b5b0ab20e1da4e2c783b7e6e', '.github/workflows/mail-receiving.yml': '71bc63ac0aad91dcc5ac94780f71275237544108fd3eb86f65c9c3783505601c', '.github/workflows/mail-compose.yml': '618ab1c02e2f319376c8a4c774f35e6cd797de840462e9ed3d5c9293086d1808', '.github/workflows/application-tests.yml': 'f62c7cfeed79d3a38e1866734f62e542bbd75af8795a781f4430387e30b69658', 'tests/installed_owner_authority.php': 'bbc48036af794564a1b39b54eb218d3390f94a65861d355d5e4ccef4c7c69863', 'tests/installed_owner_authority.py': 'a9511d52459ac1a9d50d62b646198ab29258718491f0c53f339bf32d7f0e3b71', 'tests/installed_owner_authority_test.py': '48e1930aa6d3e0d8ea7d02a5001718e68c15463fb9b9c62e6cfb2513b69c8101', 'tests/owner_browser_forms.py': 'abb07881c43514fd6ac9241700fb2fe97a881095b122d3d092a4ef82924616a3', 'tests/owner_browser_forms_test.py': 'ae4cac11289a97e2d20cf4755ed924f7f438e01d8c5550994ff7b86a144a4b54', 'tests/provider_force_ssl_controls_cases.py': 'a764c99c984514afb9b9d6ac129fd272ead523a274ab9c8ebd29e6c6436e6d68', 'tests/provider_directory_listing_controls_cases.py': 'de983d82a43bfe75c1f01fa443be944451bd1fdba16ef9c77e4c0c161096f25f', 'tools/acceptance-reports.json': 'c50ee66cba8731b60877687fdc18d76146e67b8e3ffe7e532ad09d89bd52fa91', 'tests/acceptance_artifacts_test.py': '9c1da1c8bafbc2febe87d66d5c1d6368e8e0c159e848fd54a3a64ae2108763dc', 'tests/cloud_site_requests_wiring.py': 'ee50481194217b3c6f669347f6fd64275ef87b8b07b800939f2325af51eca27e', 'tests/reset_template_acceptance_contract.py': '7d9d58a4fafacddd8b3d9d62ffe5222a4099e297c8cf05aaf00e54e2f52bf3a9', 'tests/license_financing_cases.py': '7c23b44721f97a718d99b06e449dbd379bfab856ec7452a9d193ef7b031ec3c1'}
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
        'repair_base', 'repair_base_tree', 'integration_repairs'},
        'Unexpected composition receipt fields')
    require(data['format'] == 2 and data['base'] == BASE and data['base_tree'] == BASE_TREE
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
    allowed = set(expected) | ADAPTERS | {INVENTORY, RECEIPT, VERIFIER, REGRESSION}
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
