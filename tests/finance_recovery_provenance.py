"""Exact84 FIN recovery correction; predecessors are source comparisons only.

The complete current application and native fixtures remain the inputs to every
runtime command. This standalone proof admits no notification or software patch.
"""
from pathlib import Path
import copy
import hashlib
import json
import re
import subprocess
import tempfile

PARENT = '84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd'
PARENT_TREE = 'a206e95562ab751cd9461db3a8ed32c4305ecbec'
RECEIPT = 'docs/FINANCE_RECOVERY_SOURCE.json'
VERIFIER = 'tests/finance_recovery_provenance.py'
INVENTORY = 'site/install/inventory.generated.php'
AREAS = ['site', 'foundation', 'tools/structure', 'tools/build.py', 'VERSION', 'START-HERE.txt']
# BEGIN EXACT FIN IDENTITIES
BEFORE = {'site/admin/views/studio/licenses.php': 'ecb1ed274ab43b72a442f288d107e4def377b7f3cdf87db8010d112bf17e055a',
 'site/app/billing/finance_authorize_net.php': '75cd7b5d120084da911ab9810e095e10e1737bdaa99aece87dbba695fe11d00f',
 'site/app/billing/finance_charge.php': '538b57715bd909d3a06910e22ed4d94eb16bb9b45926bb3e67edc71079b3c003',
 'site/app/billing/finance_subscription.php': 'eae98b777fb8da6325c9ba92a5ca54c7bbe0eac01a575f01094eb5c3c05c19a9',
 'site/app/billing/ledger.php': 'd2e144b11747ad2ea51e3a6c425b75dd3133f54f58e14a210110dc1ff0f1714a',
 'site/app/licenses/finance_installments.php': '75c34c7c0806d3fd0435c711fa310151667d4510216702c3aad3e69cb98178ae',
 'site/app/licenses/financing.php': 'c384d0303f59fe6c8351af0bc4f72d69f4b5b3065e1e3cc85103bb33e3ce95bb',
 'site/app/licenses/service.php': 'bba380cb430d7bb8b8a3ece2fbba47be7ce67d7d9ef48cef10dae8dbeb97da43',
 'site/database/migrations/license_finance_gateway_migrate.php': 'c3661c16c4c6e25fa567c236820764409a610c08be4ac4b671e87646d85520d2',
 'site/install/inventory.generated.php': 'd950fdb0cd0e5583d0792c02ff4c4f23b6dae6852bd42e1bcf1114d79ff5b35a',
 'tests/finance_initial_receipts_boundaries.php': '587c3590d6cfcb4af4d5313cb2b26a5f6bd1215d0710e0b906a3e9aeb23f7d06',
 'tests/finance_initial_receipts_native.php': '1daedeb016fe8ac777819e680f2d75901d70a7d81b838d83284dc7cee9c204ef',
 'tests/finance_payoff_retirement_boundaries.php': '6f67a369ba62c6adca3baedec6dca2da7ec9392e6f4c127246d16bf272483452',
 'tests/finance_payoff_retirement_native.php': '64bb7e8997a7b26995ed52e72930ccc252f9c36431303f37c154395412272de4',
 'tests/finance_reconcile_snapshot.php': '33440173ad595bd029408e9ea8b56131dc31e0b0e25e314c2515d57a5534c4ed',
 'tests/finance_replacement_outcomes.php': 'a01812e1c886868b49a0ade2439d874de5df9b6fa924e50206844c4f7b7c1b19',
 'tests/finance_replacement_payoff_boundaries.php': '5cc87de087a46a43d6acbd8525ed667b4b4ae68d643be7e45b7c70f573c30514',
 'tests/finance_replacement_payoff_native.php': 'eaae47960f586f6b9aef01d744e56e05bfe4b5ac78350d620a361e597d928464',
 'tests/finance_replacement_pending_native.php': 'a50d417723e0bcb2bbc008bd5ad7a20f00295865d63dc4745c108cb478e5297e',
 'tests/finance_replacement_receipts_native.php': '2aac58b0831628f4c420afbd52a16f262d3b6445d8f6cfc6c7cc1bb75bbe18da',
 'tests/license_finance_reconcile_native.php': '51448f66376845283988503cc9dde8f5f8aad79d3da82131ca1f1ec10b286df7',
 'tests/tickets_integration_contract.py': '318992e307c192108e6de7ceb975ed2df719d6e9358d4bdda574c6a4305552df'}
AFTER = {'site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'site/app/billing/finance_subscription.php': '3b7c779fdcf4a1e3a9f6d67756cbaa10b20c7b924f4191ebd0edadf12a3a483b',
 'site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'site/install/inventory.generated.php': 'fd1cf9632156326746db7ba05fb25756f0ae53d79909aef66804d56ad7427579',
 'tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'tests/finance_initial_receipts_native.php': '6d05dc5f3a09b2e8dcd7afcfbd47f805533d4b576cc52543c1cf7fb2581140d4',
 'tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'tests/finance_reconcile_snapshot.php': '6205354b5252cee7387eff560a14b049163ee8838efa17a3c051d9094aba8daa',
 'tests/finance_replacement_outcomes.php': '5b2146f3b6c3da735176af82e97bd1a5a599c734cd605ff7e014f1c1d3d4c913',
 'tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'tests/tickets_integration_contract.py': 'f92edde9d74b80ec806427802a991718372043ca2305c8c0115b0d21889361e2'}
ADDED = {'docs/FINANCE_RECOVERY_CORRECTION.md': '5773f6fb9fcbfdb14a5f713ade6f27d5b9d4b5a1c7f4734812f42206e05a8f06',
 'docs/FINANCE_RECOVERY_VERIFICATION.json': '27ccb4efa4858a11eef8b3ec37a79299c9b273a8c18e8a2fc7d059f4540c0de7',
 'docs/finance-recovery-evidence/adjacent-compatibility02/01-finance_payoff_retirement_native/execution.json': '92f08799522a00fb3ab8740d2bc9aa66fbf20ccb4c9fbf180c82b55ada71ef61',
 'docs/finance-recovery-evidence/adjacent-compatibility02/01-finance_payoff_retirement_native/native.json': '3dcfd11de3980376556724dc7ba322483fcd7a87d6d61fba90f057782f4549b1',
 'docs/finance-recovery-evidence/adjacent-compatibility02/01-finance_payoff_retirement_native/stderr.log': '08709cb125f67c804508b963809ec2d770d042e3c6b7c2f097d2f349741fe09c',
 'docs/finance-recovery-evidence/adjacent-compatibility02/01-finance_payoff_retirement_native/stdout.log': '3dcfd11de3980376556724dc7ba322483fcd7a87d6d61fba90f057782f4549b1',
 'docs/finance-recovery-evidence/adjacent-compatibility02/02-finance_replacement_receipts_native/execution.json': '274e128961cb8bcd3fb61815b787ba7a25ada5e66cd84352a0396b9d69fec962',
 'docs/finance-recovery-evidence/adjacent-compatibility02/02-finance_replacement_receipts_native/stderr.log': 'ea1fdebb9338e1bcd31d9257d1cb8d04798a0b02a706799e8cc8c54ba84c4004',
 'docs/finance-recovery-evidence/adjacent-compatibility02/02-finance_replacement_receipts_native/stdout.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/adjacent-compatibility02/03-finance_replacement_pending_native/execution.json': '8e610cd8ef2e33864e3ddabedd58ca5c52f8cba19edcee4a59c60a1afd03e8fc',
 'docs/finance-recovery-evidence/adjacent-compatibility02/03-finance_replacement_pending_native/stderr.log': 'e68c0f0e8dea6dc70588ac5101c36e809efe1f02d3b3b154c8c9d734626f2859',
 'docs/finance-recovery-evidence/adjacent-compatibility02/03-finance_replacement_pending_native/stdout.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/adjacent-compatibility02/execution.json': 'ece9121bbd87472e1334a9c85cbcb455883d393faf264866f6ef23f1e05b46f8',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/billing/finance_subscription.php': '3baa531431b52fc42b63d17c6b6190a38eb881f7de709099a6f92881302654f8',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/tests/finance_enrollment_payoff_boundaries.php': '3d574b75e84586c69542681a365ed18df800c6ece61d0ffb600ee7e1d6788655',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/adjacent-compatibility02/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/adjacent-fences01/01-finance_capture_snapshot_native/execution.json': '18c4a6ad7ca28c7e208b4f273a5480cbe73579785cc3b6aece6ebf338775e7e3',
 'docs/finance-recovery-evidence/adjacent-fences01/01-finance_capture_snapshot_native/native.json': '5d9c12b3e43887f786b47bb5e7d812d5db5bb5db5a3134eea7cc621ac8ab9477',
 'docs/finance-recovery-evidence/adjacent-fences01/01-finance_capture_snapshot_native/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/adjacent-fences01/01-finance_capture_snapshot_native/stdout.log': '5d9c12b3e43887f786b47bb5e7d812d5db5bb5db5a3134eea7cc621ac8ab9477',
 'docs/finance-recovery-evidence/adjacent-fences01/02-finance_charge_response_native/execution.json': '8f9358445ac5ead39602f7a0453822cab106bd74ed36180a29ebf3ca7d512fcd',
 'docs/finance-recovery-evidence/adjacent-fences01/02-finance_charge_response_native/native.json': '2d72c3dd1361107b2e97ed51ba3f7755ebfc512d6e7487c63e859e5a75d3367b',
 'docs/finance-recovery-evidence/adjacent-fences01/02-finance_charge_response_native/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/adjacent-fences01/02-finance_charge_response_native/stdout.log': '2d72c3dd1361107b2e97ed51ba3f7755ebfc512d6e7487c63e859e5a75d3367b',
 'docs/finance-recovery-evidence/adjacent-fences01/03-license_finance_reconcile_native/execution.json': 'bef18a85a25f4f418567e26743793bf4ec6636be445bd5845fe459777ca29a4b',
 'docs/finance-recovery-evidence/adjacent-fences01/03-license_finance_reconcile_native/native.json': '3dde36d95701a547797ca8df84881c6628df78a6419a1aa447532d850197f57d',
 'docs/finance-recovery-evidence/adjacent-fences01/03-license_finance_reconcile_native/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/adjacent-fences01/03-license_finance_reconcile_native/stdout.log': '3dde36d95701a547797ca8df84881c6628df78a6419a1aa447532d850197f57d',
 'docs/finance-recovery-evidence/adjacent-fences01/execution.json': '255497bd261051097160fae5989116255ae15b24c8de9ac13c1d6478d0dad984',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/billing/finance_subscription.php': '9f8e4965304ef90b0d9d22e3db02e14d870cd3a9d83fcbf46bdefbd633f0b6df',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/adjacent-fences01/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/adjacent-fences01/source/tests/finance_capture_snapshot_native.php': '299334636d5ffcc9402a6d54f2c166e3cd197aacd1b069bb8592411e0ee60c28',
 'docs/finance-recovery-evidence/adjacent-fences01/source/tests/finance_charge_response_native.php': 'fc033f1970ada2277fb2cf7e36b12dd6982ee137ace2d9816a269df59f779374',
 'docs/finance-recovery-evidence/adjacent-fences01/source/tests/finance_enrollment_payoff_boundaries.php': '3d574b75e84586c69542681a365ed18df800c6ece61d0ffb600ee7e1d6788655',
 'docs/finance-recovery-evidence/adjacent-fences01/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/adjacent-fences01/source/tests/finance_payoff_retirement_boundaries.php': 'c38497be0de914b060356e113cdc99da0632798dcda9199c64dd8e23365cf237',
 'docs/finance-recovery-evidence/adjacent-fences01/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/adjacent-fences01/source/tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'docs/finance-recovery-evidence/adjacent-full01/01-finance_replacement_receipts_native/execution.json': 'cae8a9d53ec80bc69e737085b05cec0dc56d6892173bb754ce56a1a93f02fb0a',
 'docs/finance-recovery-evidence/adjacent-full01/01-finance_replacement_receipts_native/native.json': '8b3a494c975302a29fd1744ea38d68443d07bc79d9f25c9253ff6cbfa9217d5c',
 'docs/finance-recovery-evidence/adjacent-full01/01-finance_replacement_receipts_native/stderr.log': 'd54ede3cb839403bc0aa30dfede0b50afddbd4747be12af4fa7d75daa18f9a8a',
 'docs/finance-recovery-evidence/adjacent-full01/01-finance_replacement_receipts_native/stdout.log': '8b3a494c975302a29fd1744ea38d68443d07bc79d9f25c9253ff6cbfa9217d5c',
 'docs/finance-recovery-evidence/adjacent-full01/02-finance_replacement_pending_native/execution.json': '3680f49a7696603f1f6fb5cad8427dcf71822ddb39d1b7ca8eb26374809aa014',
 'docs/finance-recovery-evidence/adjacent-full01/02-finance_replacement_pending_native/native.json': '4032d846321e4dac99ac6938bd328357f3a00e839cccfb398d0dc075693dc9cd',
 'docs/finance-recovery-evidence/adjacent-full01/02-finance_replacement_pending_native/stderr.log': '703ac512831863c1e9420da9f1550be0c699bb9039aaa166265ff0ccf51e0dbb',
 'docs/finance-recovery-evidence/adjacent-full01/02-finance_replacement_pending_native/stdout.log': '4032d846321e4dac99ac6938bd328357f3a00e839cccfb398d0dc075693dc9cd',
 'docs/finance-recovery-evidence/adjacent-full01/03-finance_initial_receipts_native/execution.json': '3b66485af91c3446697d3ace561b126106fe44c6db06f88b3dd6dd71fe76b6c2',
 'docs/finance-recovery-evidence/adjacent-full01/03-finance_initial_receipts_native/native.json': 'cb16bfc041ebd813896a7be737c3998e23393f20a3e92aefc24ee66d84ab1855',
 'docs/finance-recovery-evidence/adjacent-full01/03-finance_initial_receipts_native/stderr.log': 'bab6101b55a256ba8f8f9cdb14cf53687fa23cb34e66ddb4e0ed866495c125cb',
 'docs/finance-recovery-evidence/adjacent-full01/03-finance_initial_receipts_native/stdout.log': 'cb16bfc041ebd813896a7be737c3998e23393f20a3e92aefc24ee66d84ab1855',
 'docs/finance-recovery-evidence/adjacent-full01/04-finance_payoff_retirement_native/execution.json': '6837f9ad6b0d6c071efbe404c27c87489ee100a1b8cde266bb60cf22d6464121',
 'docs/finance-recovery-evidence/adjacent-full01/04-finance_payoff_retirement_native/native.json': 'c6e783657b48234adf9d75fda69957f99ce03da89d3c16d8e8bd06afd36ec3bd',
 'docs/finance-recovery-evidence/adjacent-full01/04-finance_payoff_retirement_native/stderr.log': '24e8ad1fd42c34d6032267a7fa505db93bd4cbd6bc2295284ba1e5b84946eca9',
 'docs/finance-recovery-evidence/adjacent-full01/04-finance_payoff_retirement_native/stdout.log': 'c6e783657b48234adf9d75fda69957f99ce03da89d3c16d8e8bd06afd36ec3bd',
 'docs/finance-recovery-evidence/adjacent-full01/05-finance_payoff_retirement_native/execution.json': '118e2c49efa18dd9bb6eef520db96e2d96148d7d0b56a5f03ad1ca1e368c2549',
 'docs/finance-recovery-evidence/adjacent-full01/05-finance_payoff_retirement_native/native.json': '51becfb890f529b55e7e867ff55340fb95d0d768a26702f7c4a779ceb9c92d11',
 'docs/finance-recovery-evidence/adjacent-full01/05-finance_payoff_retirement_native/stderr.log': '097bb8afefdccfca12cd93fb42fb35ab53118f83c32e66d73360138d0bd695aa',
 'docs/finance-recovery-evidence/adjacent-full01/05-finance_payoff_retirement_native/stdout.log': '51becfb890f529b55e7e867ff55340fb95d0d768a26702f7c4a779ceb9c92d11',
 'docs/finance-recovery-evidence/adjacent-full01/06-finance_payoff_retirement_settlement/execution.json': '632d5829b67c99ac5073145d2019bca4eaa087855971f48e1152b99819ebc35a',
 'docs/finance-recovery-evidence/adjacent-full01/06-finance_payoff_retirement_settlement/native.json': '7fdcbb0c363c525faa07249758974825fd1e0b342c1ea90d4bc8cc3a040e64a4',
 'docs/finance-recovery-evidence/adjacent-full01/06-finance_payoff_retirement_settlement/stderr.log': '8a52b2af23bd47dc36e667a02daa5bcb4af452cdcdb81fd5ea0d6e76b9045b6a',
 'docs/finance-recovery-evidence/adjacent-full01/06-finance_payoff_retirement_settlement/stdout.log': '7fdcbb0c363c525faa07249758974825fd1e0b342c1ea90d4bc8cc3a040e64a4',
 'docs/finance-recovery-evidence/adjacent-full01/07-finance_initial_enrollment_native/execution.json': '432ab867f9fcf7bae20cd56029eef3f047fb49bad5ce0333c4664cc568f0304a',
 'docs/finance-recovery-evidence/adjacent-full01/07-finance_initial_enrollment_native/native.json': 'f36648ff854734def2d7bb91b84aa9a758b9fa5c96a3e8fba3416e124b918f07',
 'docs/finance-recovery-evidence/adjacent-full01/07-finance_initial_enrollment_native/stderr.log': '687d25ee6d2fc7ceb9d6693421284c966a917a0583fee6f2bb9fc92171d1d02b',
 'docs/finance-recovery-evidence/adjacent-full01/07-finance_initial_enrollment_native/stdout.log': 'f36648ff854734def2d7bb91b84aa9a758b9fa5c96a3e8fba3416e124b918f07',
 'docs/finance-recovery-evidence/adjacent-full01/08-finance_enrollment_payoff_native/execution.json': '218dcd2c6989709d57c6293b8b0bde6890f79f79de7f8f8581998662730cf7fb',
 'docs/finance-recovery-evidence/adjacent-full01/08-finance_enrollment_payoff_native/native.json': 'bac1beaabc1c229113bff2c4ecbeaec2e4f84069078f4f96cdda125f5f10427b',
 'docs/finance-recovery-evidence/adjacent-full01/08-finance_enrollment_payoff_native/stderr.log': 'b57f64ed8fbbf22a34644fa54b33bfb115c10219e083d2ca214b59983d9b22f4',
 'docs/finance-recovery-evidence/adjacent-full01/08-finance_enrollment_payoff_native/stdout.log': 'bac1beaabc1c229113bff2c4ecbeaec2e4f84069078f4f96cdda125f5f10427b',
 'docs/finance-recovery-evidence/adjacent-full01/execution.json': 'e16740241aeb1b201ba977e86b5fe5004bcb0f3690cb447e8ce61aa0641049d2',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/billing/finance_subscription.php': '37815cbae63d7eecf19bedf95133d582b51fd75169962fbc47a939baa2fd15a1',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/adjacent-full01/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_enrollment_payoff_boundaries.php': '3d574b75e84586c69542681a365ed18df800c6ece61d0ffb600ee7e1d6788655',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_enrollment_payoff_native.php': '7071014608d6c5ff9fb9d20d47ef85e6df9e486d64f16b0de2c0fe4935bc0d03',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_initial_enrollment_native.php': '0632b77c78a78992c81505d9ec30e142373d4c42c2a1608dbb38f8e312d05de3',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_initial_receipts_native.php': '1daedeb016fe8ac777819e680f2d75901d70a7d81b838d83284dc7cee9c204ef',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_payoff_retirement_boundaries.php': 'c38497be0de914b060356e113cdc99da0632798dcda9199c64dd8e23365cf237',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_payoff_retirement_native.php': '64bb7e8997a7b26995ed52e72930ccc252f9c36431303f37c154395412272de4',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_payoff_retirement_settlement.php': '1aceebbaa10726d56384f81ea0201a5039cd58cf508bf48e1f957779cc2aa8eb',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/adjacent-full01/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/adjacent-hy000-context01.json': '5a9315cb0a91b4ba35f426f1b64a254e5b200f0c615ca3375ab661647233523b',
 'docs/finance-recovery-evidence/adjacent-initial01/01-finance_initial_receipts_native/execution.json': '45c126dcabda3ddb7dba1274eff87bd6fb3a93b00febd9e9abf49accd455a71a',
 'docs/finance-recovery-evidence/adjacent-initial01/01-finance_initial_receipts_native/native.json': '49c7b8d9f1b8071d836f542cffe7e87b506eb35c74eab24feb0178c1b11eeeb0',
 'docs/finance-recovery-evidence/adjacent-initial01/01-finance_initial_receipts_native/stderr.log': '5db795540abed7ed166863fc041f271d5adc664bf99c3749eb5129aea02a90c0',
 'docs/finance-recovery-evidence/adjacent-initial01/01-finance_initial_receipts_native/stdout.log': '49c7b8d9f1b8071d836f542cffe7e87b506eb35c74eab24feb0178c1b11eeeb0',
 'docs/finance-recovery-evidence/adjacent-initial01/execution.json': '81d6d0be9b18e4d72243bfd2c69ff9e90b7a14ba9f9bfd6824c7bf016a16fb4e',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/billing/finance_subscription.php': '9f8e4965304ef90b0d9d22e3db02e14d870cd3a9d83fcbf46bdefbd633f0b6df',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/adjacent-initial01/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/adjacent-initial01/source/tests/finance_enrollment_payoff_boundaries.php': '3d574b75e84586c69542681a365ed18df800c6ece61d0ffb600ee7e1d6788655',
 'docs/finance-recovery-evidence/adjacent-initial01/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/adjacent-initial01/source/tests/finance_initial_receipts_native.php': '1daedeb016fe8ac777819e680f2d75901d70a7d81b838d83284dc7cee9c204ef',
 'docs/finance-recovery-evidence/adjacent-initial01/source/tests/finance_payoff_retirement_boundaries.php': 'c38497be0de914b060356e113cdc99da0632798dcda9199c64dd8e23365cf237',
 'docs/finance-recovery-evidence/adjacent-initial01/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/adjacent-receipts02/01-finance_replacement_receipts_native/execution.json': '31875724a20310eae96ca6ec207cc698de1b34f8b519f3f3c8ce8114d3a8c824',
 'docs/finance-recovery-evidence/adjacent-receipts02/01-finance_replacement_receipts_native/native.json': '6b6f0f059fb593ba535ea6d060cb7b819a6bf29889afc067740481f20a3a6544',
 'docs/finance-recovery-evidence/adjacent-receipts02/01-finance_replacement_receipts_native/stderr.log': '7c9e485a84aa24bda4823a72d76b03d0c0a0eb00203dfd432a8b728984fa097d',
 'docs/finance-recovery-evidence/adjacent-receipts02/01-finance_replacement_receipts_native/stdout.log': '6b6f0f059fb593ba535ea6d060cb7b819a6bf29889afc067740481f20a3a6544',
 'docs/finance-recovery-evidence/adjacent-receipts02/execution.json': '4b27961b564393abc7c7040427d17357a7ed050446248ac64a537fcf16a3cafd',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/billing/finance_subscription.php': '3baa531431b52fc42b63d17c6b6190a38eb881f7de709099a6f92881302654f8',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/tests/finance_enrollment_payoff_boundaries.php': '3d574b75e84586c69542681a365ed18df800c6ece61d0ffb600ee7e1d6788655',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/tests/finance_payoff_retirement_boundaries.php': 'c38497be0de914b060356e113cdc99da0632798dcda9199c64dd8e23365cf237',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/adjacent-receipts02/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/adjacent-resumed03/01-finance_replacement_receipts_native/execution.json': '8596c8f091641c646ba320c6444a353f270827fe9ab445288f5bb37dbcb67882',
 'docs/finance-recovery-evidence/adjacent-resumed03/01-finance_replacement_receipts_native/native.json': '6b6f0f059fb593ba535ea6d060cb7b819a6bf29889afc067740481f20a3a6544',
 'docs/finance-recovery-evidence/adjacent-resumed03/01-finance_replacement_receipts_native/stderr.log': '4ad285eb5434c2263f6a4132d318766aecd93c617da728ce036ca04ccb9ee85e',
 'docs/finance-recovery-evidence/adjacent-resumed03/01-finance_replacement_receipts_native/stdout.log': '6b6f0f059fb593ba535ea6d060cb7b819a6bf29889afc067740481f20a3a6544',
 'docs/finance-recovery-evidence/adjacent-resumed03/02-finance_replacement_pending_native/execution.json': '5ebfbdcc47b9f10004b40e472c42e811e9fd4a41669e438ba9f970656ba5f2a1',
 'docs/finance-recovery-evidence/adjacent-resumed03/02-finance_replacement_pending_native/native.json': 'a64a00c64ceb84c83536650386ca229ade57b2dc25ee04679daaf880a0a86066',
 'docs/finance-recovery-evidence/adjacent-resumed03/02-finance_replacement_pending_native/stderr.log': 'fbb8f8d2f06d04e41e11eee739a83398f59d8f0f0bb76cc9ec8f92f8d050696c',
 'docs/finance-recovery-evidence/adjacent-resumed03/02-finance_replacement_pending_native/stdout.log': 'a64a00c64ceb84c83536650386ca229ade57b2dc25ee04679daaf880a0a86066',
 'docs/finance-recovery-evidence/adjacent-resumed03/diagnostic-classification.json': '3e8112b159bd0c4b68dea1be42b3a29bcc1958660e99c0cf1b2478ee8af2473c',
 'docs/finance-recovery-evidence/adjacent-resumed03/execution.json': '7e2a34baf8d480570c93854f0fd160d4365a24ae26d1ecf2253ba1f2c72c4fd0',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/billing/finance_subscription.php': '3baa531431b52fc42b63d17c6b6190a38eb881f7de709099a6f92881302654f8',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/tests/finance_enrollment_payoff_boundaries.php': '3d574b75e84586c69542681a365ed18df800c6ece61d0ffb600ee7e1d6788655',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/adjacent-resumed03/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/combined-first/execution.json': '195a52fd7bfc4fbc7b91c4b9bc669dcfc3a85e2f58b779d04dfa0ad8e1fe09ec',
 'docs/finance-recovery-evidence/combined-first/native.json': '936b1f17bcf15657930fe5a0f1a68b94f2e2f69d4d88559c842346516026b35b',
 'docs/finance-recovery-evidence/combined-first/native.stderr.log': '78b2fefce3c2f0a5a68e829f62d1161a700783a94b25b691c2a767537f2d40f0',
 'docs/finance-recovery-evidence/combined-first/native.stdout.log': '936b1f17bcf15657930fe5a0f1a68b94f2e2f69d4d88559c842346516026b35b',
 'docs/finance-recovery-evidence/combined-first/source/site/app/billing/finance_authorize_net.php': '27091dfeb30fbe7ef2f4c4292b388370747afeaa282583b1057ae90f37a33d17',
 'docs/finance-recovery-evidence/combined-first/source/site/app/billing/finance_recovery.php': 'f92725cca9da667411e61a09b26799056163874da4b6b77ffac9b28787782f6d',
 'docs/finance-recovery-evidence/combined-first/source/site/app/billing/finance_subscription.php': 'e0db8cb1a0aad5d9c49d5839d3ceb6f83d7bde7458244585fa5bf9d7c8353ab6',
 'docs/finance-recovery-evidence/combined-first/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/combined-first/source/site/app/licenses/finance_installments.php': '75c34c7c0806d3fd0435c711fa310151667d4510216702c3aad3e69cb98178ae',
 'docs/finance-recovery-evidence/combined-first/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/combined-first/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/combined-first/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/combined-first/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/combined-first/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/combined-first/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/combined-first/source/tests/finance_replacement_payoff_native.php': '93767eabba32c992028032aada0412bd732c2503735e6be57ea8fd994e547852',
 'docs/finance-recovery-evidence/combined-first/source/tests/finance_replacement_payoff_recovery_boundaries.php': 'a94763b1474455a43c86bd457eb924efe8d224995a924bdcf825ea58142dc6d0',
 'docs/finance-recovery-evidence/combined-first/source/tests/finance_replacement_payoff_recovery_cases.php': '523bbc50001a4a73afc3a8eb95442655a622c5218f305607c4d89115bfb59691',
 'docs/finance-recovery-evidence/compatibility-server-exit01/launch.json': 'cc41c9113a8827a7c3c113bc878b784eecae966e60f67f5f55c0b8f37c8a7511',
 'docs/finance-recovery-evidence/compatibility-server-exit01/resource-observation.json': '69b3ac477ea22e0776bceecfd9e1f8d640991a7ec09a90a5e6b4bcc63c80fb9a',
 'docs/finance-recovery-evidence/compatibility-server-exit01/server-launch.log': 'd459cd5eaeb4a6020ee21ca23b825ffb9aa180cec2c8765d4bb53df80671eaab',
 'docs/finance-recovery-evidence/compatibility-server-exit01/server.log': '68193ea9efd9d7c059302e74d2d653c3b2e96ace6b34688434bf89c9dab86afc',
 'docs/finance-recovery-evidence/fin01-first/execution.json': 'c8dc6b4251511ef450d9730ca64e7f002610d82168cbdf86ee34199fd5d1507a',
 'docs/finance-recovery-evidence/fin01-first/native.json': '75016adf9a1a65e90f87a77bea19ab9ac493f7bbb18c2cacbb8a8dcbffb8adea',
 'docs/finance-recovery-evidence/fin01-first/native.stderr.log': 'aa9ba2d422985a37737649bdd0d99fe92765b64b15ee51815ef6d098c8354aea',
 'docs/finance-recovery-evidence/fin01-first/native.stdout.log': '75016adf9a1a65e90f87a77bea19ab9ac493f7bbb18c2cacbb8a8dcbffb8adea',
 'docs/finance-recovery-evidence/fin01-first/source/site/app/billing/finance_authorize_net.php': '27091dfeb30fbe7ef2f4c4292b388370747afeaa282583b1057ae90f37a33d17',
 'docs/finance-recovery-evidence/fin01-first/source/site/app/billing/finance_recovery.php': '7d47b485a1955365285a3244c132031123eb6a9e4255301480beeb65da5c7c71',
 'docs/finance-recovery-evidence/fin01-first/source/site/app/billing/finance_subscription.php': 'e0db8cb1a0aad5d9c49d5839d3ceb6f83d7bde7458244585fa5bf9d7c8353ab6',
 'docs/finance-recovery-evidence/fin01-first/source/site/app/billing/ledger.php': 'd2e144b11747ad2ea51e3a6c425b75dd3133f54f58e14a210110dc1ff0f1714a',
 'docs/finance-recovery-evidence/fin01-first/source/site/app/licenses/finance_installments.php': '75c34c7c0806d3fd0435c711fa310151667d4510216702c3aad3e69cb98178ae',
 'docs/finance-recovery-evidence/fin01-first/source/site/app/licenses/financing.php': 'c384d0303f59fe6c8351af0bc4f72d69f4b5b3065e1e3cc85103bb33e3ce95bb',
 'docs/finance-recovery-evidence/fin01-first/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/fin01-first/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/fin01-first/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/fin01-first/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/fin01-first/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/fin01-first/source/tests/finance_replacement_payoff_native.php': '93767eabba32c992028032aada0412bd732c2503735e6be57ea8fd994e547852',
 'docs/finance-recovery-evidence/fin01-first/source/tests/finance_replacement_payoff_recovery_boundaries.php': 'a94763b1474455a43c86bd457eb924efe8d224995a924bdcf825ea58142dc6d0',
 'docs/finance-recovery-evidence/fin01-first/source/tests/finance_replacement_payoff_recovery_cases.php': '523bbc50001a4a73afc3a8eb95442655a622c5218f305607c4d89115bfb59691',
 'docs/finance-recovery-evidence/final-native01/execution.json': '99393c6541442051c4756dcba67a98667a65d5d41f08b70859b3b187da0ef3e7',
 'docs/finance-recovery-evidence/final-native01/native.stderr.log': 'f09ffc4306de9d442d887dc48baf6a8350752c44865c81949b3b81dfd2118f67',
 'docs/finance-recovery-evidence/final-native01/native.stdout.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/final-native01/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/final-native01/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/final-native01/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/final-native01/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/final-native01/source/site/app/billing/finance_subscription.php': '9f8e4965304ef90b0d9d22e3db02e14d870cd3a9d83fcbf46bdefbd633f0b6df',
 'docs/finance-recovery-evidence/final-native01/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/final-native01/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/final-native01/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/final-native01/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/final-native01/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/final-native01/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/final-native01/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/final-native01/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/final-native01/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/final-native01/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/final-native01/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/final-native01/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/final-native01/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/final-native01/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/final-native01/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/final-native01/source/tests/finance_replacement_payoff_recovery_safety.php': '758e9978b02e85210dd3cdaa6ae10637b56d826b7a2b7d26d847851fad05fb4a',
 'docs/finance-recovery-evidence/final-native02/execution.json': '508be80a762ab8f6cb38d354b65cc0ac37c55ad1b693b087d00874cfc8b7eb53',
 'docs/finance-recovery-evidence/final-native02/native.json': '7082ae799c9e2a351370a947ca8498c48a571378bba9034ac097a7794e493c31',
 'docs/finance-recovery-evidence/final-native02/native.stderr.log': '0caf8fe4f0b0d1709c19371c78d7bb4aab37c798284c2ecda5b391ae478ab0b8',
 'docs/finance-recovery-evidence/final-native02/native.stdout.log': '7082ae799c9e2a351370a947ca8498c48a571378bba9034ac097a7794e493c31',
 'docs/finance-recovery-evidence/final-native02/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/final-native02/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/final-native02/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/final-native02/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/final-native02/source/site/app/billing/finance_subscription.php': '9f8e4965304ef90b0d9d22e3db02e14d870cd3a9d83fcbf46bdefbd633f0b6df',
 'docs/finance-recovery-evidence/final-native02/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/final-native02/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/final-native02/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/final-native02/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/final-native02/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/final-native02/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/final-native02/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/final-native02/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/final-native02/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/final-native02/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/final-native02/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/final-native02/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/final-native02/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/final-native02/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/final-native02/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/final-native02/source/tests/finance_replacement_payoff_recovery_safety.php': '2c174b592d5410919cc8f6e8cbcc692e27659906707aaa2edaa50e24f0731fc7',
 'docs/finance-recovery-evidence/final-native03/execution.json': '777b30ba5e6b34d1120ba11cd2f80448484bcbeaec2910ca09d523825835ade5',
 'docs/finance-recovery-evidence/final-native03/native.json': 'ca018e1b60cab164a0ac9e66a96ea9483e7e1ed5187fc6e74e14fc85c4d5806c',
 'docs/finance-recovery-evidence/final-native03/native.stderr.log': '15beffa60517fc71815b157fb1f9fa362fb4d372932adfc0d61e6f0a9878e66d',
 'docs/finance-recovery-evidence/final-native03/native.stdout.log': 'ca018e1b60cab164a0ac9e66a96ea9483e7e1ed5187fc6e74e14fc85c4d5806c',
 'docs/finance-recovery-evidence/final-native03/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/final-native03/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/final-native03/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/final-native03/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/final-native03/source/site/app/billing/finance_subscription.php': '37815cbae63d7eecf19bedf95133d582b51fd75169962fbc47a939baa2fd15a1',
 'docs/finance-recovery-evidence/final-native03/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/final-native03/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/final-native03/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/final-native03/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/final-native03/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/final-native03/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/final-native03/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/final-native03/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/final-native03/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/final-native03/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/final-native03/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/final-native03/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/final-native03/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/final-native03/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/final-native03/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/final-native03/source/tests/finance_replacement_payoff_recovery_safety.php': '2c174b592d5410919cc8f6e8cbcc692e27659906707aaa2edaa50e24f0731fc7',
 'docs/finance-recovery-evidence/final-native04/diagnostic-classification.json': 'a0d44886b92256f30880d44839e0e70533f5eaa7893f91ab2943b55b042790d5',
 'docs/finance-recovery-evidence/final-native04/execution.json': '90edbc18ddc524642e253096982a647e67bf34c6be21051eb2ca4d1ebe2c2f71',
 'docs/finance-recovery-evidence/final-native04/native.json': 'ca149ee5fc44aa5e732ec500ff7bd0db6943d11cd51332a2cd90cbb0d994bd53',
 'docs/finance-recovery-evidence/final-native04/native.stderr.log': 'ae803d2e738b4cf8ae6fdaa44486460450581cb825b89818e9f963fead2f7bbe',
 'docs/finance-recovery-evidence/final-native04/native.stdout.log': 'ca149ee5fc44aa5e732ec500ff7bd0db6943d11cd51332a2cd90cbb0d994bd53',
 'docs/finance-recovery-evidence/final-native04/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/final-native04/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/final-native04/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/final-native04/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/final-native04/source/site/app/billing/finance_subscription.php': '3baa531431b52fc42b63d17c6b6190a38eb881f7de709099a6f92881302654f8',
 'docs/finance-recovery-evidence/final-native04/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/final-native04/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/final-native04/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/final-native04/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/final-native04/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/final-native04/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/final-native04/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/final-native04/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/final-native04/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/final-native04/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/final-native04/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/final-native04/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/final-native04/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/final-native04/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/final-native04/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/final-native04/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/final-native05/diagnostic-classification.json': 'da9840742c58f2518ef37fce068fe81a720d7fc3a7c524649030d82702784332',
 'docs/finance-recovery-evidence/final-native05/execution.json': '353aa7930bd9f34c15bd2642267ddb4765efd0246058f9dfc8eaf1e8d9792d02',
 'docs/finance-recovery-evidence/final-native05/native.json': '32e3c9d4569a08d9b8e5004ada4f564912fb7f95c23aecc168f08d873a62c6dc',
 'docs/finance-recovery-evidence/final-native05/native.stderr.log': '59a42d4a234e2aee552f8cb2228149edf3c6f20926c0aef6a5438fece4774814',
 'docs/finance-recovery-evidence/final-native05/native.stdout.log': '32e3c9d4569a08d9b8e5004ada4f564912fb7f95c23aecc168f08d873a62c6dc',
 'docs/finance-recovery-evidence/final-native05/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/final-native05/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/final-native05/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/final-native05/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/final-native05/source/site/app/billing/finance_subscription.php': '3b7c779fdcf4a1e3a9f6d67756cbaa10b20c7b924f4191ebd0edadf12a3a483b',
 'docs/finance-recovery-evidence/final-native05/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/final-native05/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/final-native05/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/final-native05/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/final-native05/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/final-native05/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/final-native05/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/final-native05/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/final-native05/source/site/install/inventory.generated.php': 'fd1cf9632156326746db7ba05fb25756f0ae53d79909aef66804d56ad7427579',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_initial_receipts_native.php': '6d05dc5f3a09b2e8dcd7afcfbd47f805533d4b576cc52543c1cf7fb2581140d4',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_reconcile_snapshot.php': '6205354b5252cee7387eff560a14b049163ee8838efa17a3c051d9094aba8daa',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_outcomes.php': '5b2146f3b6c3da735176af82e97bd1a5a599c734cd605ff7e014f1c1d3d4c913',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_initial_admission.php': '087a779de88e3b6a763bf8b4a2f6fcad14ccc1905f667958cf64f040012e9a07',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_recovery_cases.php': '5f8b4b1299b0088fdc98b7a2e2b52d79533115cbb8bd85d730676b4de9e0e740',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/final-native05/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/final-native05/source/tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/execution.json': '14a42e193a7294deba51a27abaafda9f00724d0e211cc8e29e84743a0b70ad84',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/native.json': '41c41d6f89ffdfd76d3d704006a5a207c99566b3620182e816d851c2acdb6d35',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/native.stderr.log': 'acc5620ce44e8d10cd00fe30a56b834278649c8fbe9b83349aef330e551e3133',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/native.stdout.log': '41c41d6f89ffdfd76d3d704006a5a207c99566b3620182e816d851c2acdb6d35',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/billing/finance_subscription.php': '3baa531431b52fc42b63d17c6b6190a38eb881f7de709099a6f92881302654f8',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/billing/payments.php': 'c41ff35d04b5f87baccdc0ecada491a510540ffc2a91342cd405d3ca8bab2ecb',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/clients/accounts.php': '03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/clients/identity.php': '42bb888223848260b76a5d49730e8d50d5aaaac29eae7220771b6294cd6034e9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/plans/records.php': '2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/site/install/inventory.generated.php': '2b4a263c9e1fcbf790bd81fe46aa265c0e135e4b495757adad6ac3f5fc5deee3',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_initial_receipts_native.php': '1daedeb016fe8ac777819e680f2d75901d70a7d81b838d83284dc7cee9c204ef',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_initial_unresolved_admission_probe.php': '26ea14595a13080c8c95d9ef1dee763d39b5e9de1ce072339866700061c41373',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_reconcile_snapshot.php': '6205354b5252cee7387eff560a14b049163ee8838efa17a3c051d9094aba8daa',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_outcomes.php': '5b2146f3b6c3da735176af82e97bd1a5a599c734cd605ff7e014f1c1d3d4c913',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native01/source/tests/recurring_billing_native.php': 'f2b811ba24274cf9421521c7e7f580b95aa9e8fa55033dfc48d2c2ff42f6810a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/execution.json': 'c9353437a35a2d4078fa4b7952091df3596493491f5a1df8058632bf8ac61901',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/native.json': '3dfd2e678c20f37f745ec1b10a959eaf871f60f54a73b046207124fd414cd897',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/native.stderr.log': 'e9fd1fed0505a581bff0cd761df56118bebe69ec22316831c2ca1cc19a72918a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/native.stdout.log': '3dfd2e678c20f37f745ec1b10a959eaf871f60f54a73b046207124fd414cd897',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/billing/finance_subscription.php': '3baa531431b52fc42b63d17c6b6190a38eb881f7de709099a6f92881302654f8',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/billing/payments.php': 'c41ff35d04b5f87baccdc0ecada491a510540ffc2a91342cd405d3ca8bab2ecb',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/clients/accounts.php': '03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/clients/identity.php': '42bb888223848260b76a5d49730e8d50d5aaaac29eae7220771b6294cd6034e9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/plans/records.php': '2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/site/install/inventory.generated.php': '2b4a263c9e1fcbf790bd81fe46aa265c0e135e4b495757adad6ac3f5fc5deee3',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_initial_receipts_native.php': '1daedeb016fe8ac777819e680f2d75901d70a7d81b838d83284dc7cee9c204ef',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_initial_unresolved_admission_probe.php': '2da7e9f668528c7f3f9a9a1c84bd1647362e92d4a6ebde81c88a1e956571762b',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_reconcile_snapshot.php': '6205354b5252cee7387eff560a14b049163ee8838efa17a3c051d9094aba8daa',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_outcomes.php': '5b2146f3b6c3da735176af82e97bd1a5a599c734cd605ff7e014f1c1d3d4c913',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native02/source/tests/recurring_billing_native.php': 'f2b811ba24274cf9421521c7e7f580b95aa9e8fa55033dfc48d2c2ff42f6810a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/execution.json': 'd3dcab4caf32bbbc44cb2f14518116f5b15884588719750f428f6a7561bcc121',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/native.json': 'df167544588a999bc7aa73cbfa10f939573bdfe123abd20e64c3124075f1f4ec',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/native.stderr.log': '36788d1ffd33fd6086b0aee26919c4c40284c642f04db93bf534d3bf5a1add6c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/native.stdout.log': 'df167544588a999bc7aa73cbfa10f939573bdfe123abd20e64c3124075f1f4ec',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/owned-db-launch.json': 'b63f8dbc9e3472213e9ad519210192951a5c7d0b3d309d51c8983bdbeaf90015',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/owned-db-server.log': '5c1c299e4267497a948d888d031976445826b95a02440b71d045324c280258d5',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/owned-db-shutdown.json': '8ea7d8e88f4c331b413ab99eb02ccfc026de35fbe6850869c7b4c14474d73b76',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/billing/finance_subscription.php': '3baa531431b52fc42b63d17c6b6190a38eb881f7de709099a6f92881302654f8',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/billing/payments.php': 'c41ff35d04b5f87baccdc0ecada491a510540ffc2a91342cd405d3ca8bab2ecb',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/clients/accounts.php': '03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/clients/identity.php': '42bb888223848260b76a5d49730e8d50d5aaaac29eae7220771b6294cd6034e9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/plans/records.php': '2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/site/install/inventory.generated.php': '2b4a263c9e1fcbf790bd81fe46aa265c0e135e4b495757adad6ac3f5fc5deee3',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_initial_receipts_native.php': '1daedeb016fe8ac777819e680f2d75901d70a7d81b838d83284dc7cee9c204ef',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_initial_unresolved_admission_probe.php': 'db674557ce52b7afa28dddef0297820c8dc02ad499c7f32fbd0899446800b06f',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_reconcile_snapshot.php': '6205354b5252cee7387eff560a14b049163ee8838efa17a3c051d9094aba8daa',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_outcomes.php': '5b2146f3b6c3da735176af82e97bd1a5a599c734cd605ff7e014f1c1d3d4c913',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native03/source/tests/recurring_billing_native.php': 'f2b811ba24274cf9421521c7e7f580b95aa9e8fa55033dfc48d2c2ff42f6810a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/execution.json': '5dc3d0bc425a0e4015d941a650b17825e1cdad7d06d60108e769cd975520b2e9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/native.json': '4170cab576062554497f235b02044d5861e921fdcc5993afce87e7019005a37f',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/native.stderr.log': '1dd9451c8ead591834a06a28d377e6e5b7bfcc737467ee5b4924f1f3c174cde2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/native.stdout.log': '4170cab576062554497f235b02044d5861e921fdcc5993afce87e7019005a37f',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/owned-db-lifecycle.json': '13253678facaff543dc9d7d09dbde4d6cdd180157dc33d423dbc802977eb11a1',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/owned-db-server.log': '813c0182b7970c29d8a9c4360fcc8f87bc82593b4bec7021ca3ad31cefcda7e6',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/billing/finance_subscription.php': '3b7c779fdcf4a1e3a9f6d67756cbaa10b20c7b924f4191ebd0edadf12a3a483b',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/billing/payments.php': 'c41ff35d04b5f87baccdc0ecada491a510540ffc2a91342cd405d3ca8bab2ecb',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/clients/accounts.php': '03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/clients/identity.php': '42bb888223848260b76a5d49730e8d50d5aaaac29eae7220771b6294cd6034e9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/plans/records.php': '2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/app/staff/accounts.php': 'fdb9d77f4870891676bc69826af22353d37ec9faf736fe80a2e33fadbc6dfedd',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/site/install/inventory.generated.php': '2b4a263c9e1fcbf790bd81fe46aa265c0e135e4b495757adad6ac3f5fc5deee3',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_initial_receipts_native.php': '1daedeb016fe8ac777819e680f2d75901d70a7d81b838d83284dc7cee9c204ef',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_initial_unresolved_admission_probe.php': 'db674557ce52b7afa28dddef0297820c8dc02ad499c7f32fbd0899446800b06f',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_reconcile_snapshot.php': '6205354b5252cee7387eff560a14b049163ee8838efa17a3c051d9094aba8daa',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_outcomes.php': '5b2146f3b6c3da735176af82e97bd1a5a599c734cd605ff7e014f1c1d3d4c913',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'docs/finance-recovery-evidence/finance-initial-unresolved-native04/source/tests/recurring_billing_native.php': 'f2b811ba24274cf9421521c7e7f580b95aa9e8fa55033dfc48d2c2ff42f6810a',
 'docs/finance-recovery-evidence/fixed-auth01/execution.json': '3df3a70a9373880a3d6dd9dc5d23043516e56968e90642380c2f9f8625950f64',
 'docs/finance-recovery-evidence/fixed-auth01/native.json': '5f7ebae6496461bc36d699b3e521502818f4ac6364ee51003f50eb2803e7d2b9',
 'docs/finance-recovery-evidence/fixed-auth01/native.stderr.log': 'a953383688889e9e3875ad064d2b20982d02e9b3266b174a748579139bccfdb5',
 'docs/finance-recovery-evidence/fixed-auth01/native.stdout.log': '5f7ebae6496461bc36d699b3e521502818f4ac6364ee51003f50eb2803e7d2b9',
 'docs/finance-recovery-evidence/fixed-auth01/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/fixed-auth01/source/site/app/billing/finance_subscription.php': '2f406c5284c277843127d6c24755f2337a11d1ebd91a4f90db09bf2004ec4337',
 'docs/finance-recovery-evidence/fixed-auth01/source/site/app/clients/accounts.php': '03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e',
 'docs/finance-recovery-evidence/fixed-auth01/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/fixed-auth01/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/fixed-auth01/source/site/app/plans/records.php': '2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c',
 'docs/finance-recovery-evidence/fixed-auth01/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/fixed-auth01/source/tests/finance_replacement_payoff_authority_cases.php': '2844174dadd4b7488acca4e8eedc337291e0f09af10f103a0f1a84eadb33f9d7',
 'docs/finance-recovery-evidence/fixed-auth01/source/tests/finance_replacement_payoff_authority_native.php': '044359f856226e5a31df3891d3f0029d73a4c7e21e1ef3af817112ec47cdcacb',
 'docs/finance-recovery-evidence/fixed-auth01/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/fixed-auth01/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/fixed-auth02/execution.json': '7098a4289a8096acefd676dfc58cb69b202adb02e8a6aa91bcf980c2f7b1f607',
 'docs/finance-recovery-evidence/fixed-auth02/native.json': '12721d7d390835af206414e644b076bee22a5db4a26ca11be831feacc60fb060',
 'docs/finance-recovery-evidence/fixed-auth02/native.stderr.log': '3ace0fd94803d20be692389fe9d3541e0cf80485947dffe13dfa9e35749c9361',
 'docs/finance-recovery-evidence/fixed-auth02/native.stdout.log': '12721d7d390835af206414e644b076bee22a5db4a26ca11be831feacc60fb060',
 'docs/finance-recovery-evidence/fixed-auth02/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/fixed-auth02/source/site/app/billing/finance_subscription.php': '9f8e4965304ef90b0d9d22e3db02e14d870cd3a9d83fcbf46bdefbd633f0b6df',
 'docs/finance-recovery-evidence/fixed-auth02/source/site/app/clients/accounts.php': '03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e',
 'docs/finance-recovery-evidence/fixed-auth02/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/fixed-auth02/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/fixed-auth02/source/site/app/plans/records.php': '2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c',
 'docs/finance-recovery-evidence/fixed-auth02/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/fixed-auth02/source/tests/finance_replacement_payoff_authority_cases.php': '2844174dadd4b7488acca4e8eedc337291e0f09af10f103a0f1a84eadb33f9d7',
 'docs/finance-recovery-evidence/fixed-auth02/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/fixed-auth02/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/fixed-auth02/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33313-after-server-launch.log': '9e78d9f6c5e7bccc9826add68d7ba3255dd9691ad91fde0f563dba2a8affeea9',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33313-after-server.log': '77a0764235284d965ab0a5bc482cdd069c21aad2bd2bbd4ecee23d45b7a4dc0b',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33313-before-launch.json': 'cc41c9113a8827a7c3c113bc878b784eecae966e60f67f5f55c0b8f37c8a7511',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33313-before-server-launch.log': '9e78d9f6c5e7bccc9826add68d7ba3255dd9691ad91fde0f563dba2a8affeea9',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33313-before-server.log': '9a170483aace46333d0fffe08b7a788480622b0b3ba113b46546c01d7eeddf61',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33315-after-server-launch.log': '619be422f377dafa3de27243a27c41e5f3c5501119671a4fbc9ceefdc42bf79a',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33315-after-server.log': 'f5b8bd6e7b634f64e138b61ce937c3e26761c1154463bc529ac3fcf05226c3cd',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33315-before-launch.json': 'fd8b7a31de698397f86dd4d027142929608917a7152e7cb27cd4b29fb14697a6',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33315-before-server-launch.log': '619be422f377dafa3de27243a27c41e5f3c5501119671a4fbc9ceefdc42bf79a',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/33315-before-server.log': '4f56601bebc33cc64ff388a4de75e75f7574cc498330bcc8589ba04e60cca6fc',
 'docs/finance-recovery-evidence/idle-runtime-shutdown02/execution.json': 'b0bb529b833fae8eaa26be7d53a137753fc1f485ba0c625af8653eadca0ec477',
 'docs/finance-recovery-evidence/implementation-frozen05/canonical.stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/implementation-frozen05/canonical.stdout.log': '401de6817343b9321e9475c025fed1f1969f7e723f6ee20deb6ab526c25282b9',
 'docs/finance-recovery-evidence/implementation-frozen05/manifest.json': '2c50b9c02296d0ea220b049fcc79ea705ab7ca3c3e34d6a5c1f9c2f7e4fa76ea',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/app/billing/finance_subscription.php': '3b7c779fdcf4a1e3a9f6d67756cbaa10b20c7b924f4191ebd0edadf12a3a483b',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/implementation-frozen05/source/site/install/inventory.generated.php': 'fd1cf9632156326746db7ba05fb25756f0ae53d79909aef66804d56ad7427579',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_initial_receipts_native.php': '6d05dc5f3a09b2e8dcd7afcfbd47f805533d4b576cc52543c1cf7fb2581140d4',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_reconcile_snapshot.php': '6205354b5252cee7387eff560a14b049163ee8838efa17a3c051d9094aba8daa',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_outcomes.php': '5b2146f3b6c3da735176af82e97bd1a5a599c734cd605ff7e014f1c1d3d4c913',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_initial_admission.php': '087a779de88e3b6a763bf8b4a2f6fcad14ccc1905f667958cf64f040012e9a07',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_recovery_cases.php': '5f8b4b1299b0088fdc98b7a2e2b52d79533115cbb8bd85d730676b4de9e0e740',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/implementation-frozen05/source/tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'docs/finance-recovery-evidence/initial-admission-adaptation01/assertion-count-clarification.json': 'b7901a01aef4cc3271ee010e399ab44f219ab35fbaebd55a994a81a65e9eed19',
 'docs/finance-recovery-evidence/initial-admission-adaptation01/delta.patch': 'd72fb26934ac8d60a6cfebfbdbdee53b4470b5b939a672a79f474706b90d7d71',
 'docs/finance-recovery-evidence/initial-admission-adaptation01/manifest.json': 'c787288a6250fc064323048254100f5dfe80d23745a4011f9ab46c3faf92fc1e',
 'docs/finance-recovery-evidence/initial-admission-adaptation01/rationale.md': '8920d5b3dcc83fa1af99601d6c0806b6c1c6a5a109cb1d72622e0e7118accf74',
 'docs/finance-recovery-evidence/initial-admission-adaptation01/source/site/app/billing/finance_subscription.php': '3b7c779fdcf4a1e3a9f6d67756cbaa10b20c7b924f4191ebd0edadf12a3a483b',
 'docs/finance-recovery-evidence/initial-admission-adaptation01/source/tests/finance_initial_receipts_native.php': '6d05dc5f3a09b2e8dcd7afcfbd47f805533d4b576cc52543c1cf7fb2581140d4',
 'docs/finance-recovery-evidence/initial-admission-adaptation01/source/tests/finance_replacement_payoff_initial_admission.php': '087a779de88e3b6a763bf8b4a2f6fcad14ccc1905f667958cf64f040012e9a07',
 'docs/finance-recovery-evidence/initial-admission-adaptation01/source/tests/finance_replacement_payoff_recovery_cases.php': '5f8b4b1299b0088fdc98b7a2e2b52d79533115cbb8bd85d730676b4de9e0e740',
 'docs/finance-recovery-evidence/initial-compatibility03/diagnostic-classification.json': '3859e74cac5dd799893123bbbfd86f0d09405b1aa00cc314cf3328502265b91c',
 'docs/finance-recovery-evidence/initial-compatibility03/execution.json': '68a14e86a3255cd4efcf2fe27e54c2714ff874e2847506afd473f91c27187654',
 'docs/finance-recovery-evidence/initial-compatibility03/native.json': '27ade65b2e99c5c904a80f368c7845b8908ca70443036903292a81e07c7090cd',
 'docs/finance-recovery-evidence/initial-compatibility03/native.stderr.log': '3ab0df3bec543296e55b73bb9cec913690fdadb1b753b349ea6725f01ce7afde',
 'docs/finance-recovery-evidence/initial-compatibility03/native.stdout.log': '27ade65b2e99c5c904a80f368c7845b8908ca70443036903292a81e07c7090cd',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/app/billing/finance_subscription.php': '3b7c779fdcf4a1e3a9f6d67756cbaa10b20c7b924f4191ebd0edadf12a3a483b',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/app/licenses/service.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/initial-compatibility03/source/site/install/inventory.generated.php': 'fd1cf9632156326746db7ba05fb25756f0ae53d79909aef66804d56ad7427579',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_initial_receipts_boundaries.php': '3985468cc782231e10a120dc27908b45e2519c3a57bb83529f95d8415dd7354a',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_initial_receipts_native.php': '6d05dc5f3a09b2e8dcd7afcfbd47f805533d4b576cc52543c1cf7fb2581140d4',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_reconcile_snapshot.php': '6205354b5252cee7387eff560a14b049163ee8838efa17a3c051d9094aba8daa',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_outcomes.php': '5b2146f3b6c3da735176af82e97bd1a5a599c734cd605ff7e014f1c1d3d4c913',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_boundaries.php': '3e33ec5d79aeb3af7446c97a1b33075e9432cba6068e58824275d88f847e691d',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_initial_admission.php': '087a779de88e3b6a763bf8b4a2f6fcad14ccc1905f667958cf64f040012e9a07',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_recovery_cases.php': '5f8b4b1299b0088fdc98b7a2e2b52d79533115cbb8bd85d730676b4de9e0e740',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_pending_native.php': '4c6596b46ed19d2ad0612979ab3fea859f97cb6eb5e6c053203156bba9e59240',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/finance_replacement_receipts_native.php': 'fcd673714810f5978cce053c47a423dd365522226dc3cfe7e0e06ab13921016a',
 'docs/finance-recovery-evidence/initial-compatibility03/source/tests/license_finance_reconcile_native.php': 'afd2870bf217c7994e749c1361c11a6fa6aaff2b5d07b64eac70ec1eb2ed9b11',
 'docs/finance-recovery-evidence/license-comment01/current.php': '04739cc0327ba6e474808f4df2aa8d0974a27c94455d2891e790114a228d6443',
 'docs/finance-recovery-evidence/license-comment01/execution.json': '9eb0986ebbc6835b4cbb328fa96bbab60ad3a969729c0b2f7fc09056030b1d1f',
 'docs/finance-recovery-evidence/license-comment01/lint.stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/license-comment01/lint.stdout.log': '1b50b981981ac64f9301481b69a64f05ad3785b27b68792b38a4355e39129b8f',
 'docs/finance-recovery-evidence/license-comment01/original.php': 'bba380cb430d7bb8b8a3ece2fbba47be7ce67d7d9ef48cef10dae8dbeb97da43',
 'docs/finance-recovery-evidence/license-comment01/source-contract.stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/license-comment01/source-contract.stdout.log': '43e774120c53630bd52ccceb5f38be38d86a16ceabb5188d26d9c45e3c49a16a',
 'docs/finance-recovery-evidence/original-auth01/execution.json': 'dee478552a6b3f680e5d514ce99ed8105c0b5d5fa5a9557537b692f34e0c3511',
 'docs/finance-recovery-evidence/original-auth01/native.json': '579828778d0b5836d334d254c3528a6a860f729405b936863057f9ba7a55a5d4',
 'docs/finance-recovery-evidence/original-auth01/native.stderr.log': 'a7a80ae887c0f7f9da1624764bd4b08a0654c5e3a70e9aefa7921f852781567d',
 'docs/finance-recovery-evidence/original-auth01/native.stdout.log': '579828778d0b5836d334d254c3528a6a860f729405b936863057f9ba7a55a5d4',
 'docs/finance-recovery-evidence/original-auth01/source/site/app/billing/finance_authorize_net.php': '75cd7b5d120084da911ab9810e095e10e1737bdaa99aece87dbba695fe11d00f',
 'docs/finance-recovery-evidence/original-auth01/source/site/app/billing/finance_subscription.php': 'eae98b777fb8da6325c9ba92a5ca54c7bbe0eac01a575f01094eb5c3c05c19a9',
 'docs/finance-recovery-evidence/original-auth01/source/site/app/clients/accounts.php': '03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e',
 'docs/finance-recovery-evidence/original-auth01/source/site/app/licenses/finance_installments.php': '75c34c7c0806d3fd0435c711fa310151667d4510216702c3aad3e69cb98178ae',
 'docs/finance-recovery-evidence/original-auth01/source/site/app/licenses/financing.php': 'c384d0303f59fe6c8351af0bc4f72d69f4b5b3065e1e3cc85103bb33e3ce95bb',
 'docs/finance-recovery-evidence/original-auth01/source/site/app/plans/records.php': '2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c',
 'docs/finance-recovery-evidence/original-auth01/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/original-auth01/source/tests/finance_replacement_payoff_authority_cases.php': '2844174dadd4b7488acca4e8eedc337291e0f09af10f103a0f1a84eadb33f9d7',
 'docs/finance-recovery-evidence/original-auth01/source/tests/finance_replacement_payoff_authority_native.php': '044359f856226e5a31df3891d3f0029d73a4c7e21e1ef3af817112ec47cdcacb',
 'docs/finance-recovery-evidence/original-auth01/source/tests/finance_replacement_payoff_boundaries.php': '5cc87de087a46a43d6acbd8525ed667b4b4ae68d643be7e45b7c70f573c30514',
 'docs/finance-recovery-evidence/original-auth01/source/tests/finance_replacement_payoff_native.php': 'eaae47960f586f6b9aef01d744e56e05bfe4b5ac78350d620a361e597d928464',
 'docs/finance-recovery-evidence/original-auth02/execution.json': 'cc90bafe48c4fd7b46534817a3c33d364e53f53551e1f4c0c9d3c4ad4a758a40',
 'docs/finance-recovery-evidence/original-auth02/native.json': 'a21842cfb0774d3bcfc0f7fc7fe0f6c407047804991b4f8274c1329d52ab3c89',
 'docs/finance-recovery-evidence/original-auth02/native.stderr.log': 'b2a3c6557a5f25935154f01494f4ab581c369311e6e8d64bc74f79544487a223',
 'docs/finance-recovery-evidence/original-auth02/native.stdout.log': 'a21842cfb0774d3bcfc0f7fc7fe0f6c407047804991b4f8274c1329d52ab3c89',
 'docs/finance-recovery-evidence/original-auth02/source/site/app/billing/finance_authorize_net.php': '75cd7b5d120084da911ab9810e095e10e1737bdaa99aece87dbba695fe11d00f',
 'docs/finance-recovery-evidence/original-auth02/source/site/app/billing/finance_subscription.php': 'eae98b777fb8da6325c9ba92a5ca54c7bbe0eac01a575f01094eb5c3c05c19a9',
 'docs/finance-recovery-evidence/original-auth02/source/site/app/clients/accounts.php': '03ca51e11d3ce125f2eb203685a82c109dbe3638f37c84cad953da171c665c1e',
 'docs/finance-recovery-evidence/original-auth02/source/site/app/licenses/finance_installments.php': '75c34c7c0806d3fd0435c711fa310151667d4510216702c3aad3e69cb98178ae',
 'docs/finance-recovery-evidence/original-auth02/source/site/app/licenses/financing.php': 'c384d0303f59fe6c8351af0bc4f72d69f4b5b3065e1e3cc85103bb33e3ce95bb',
 'docs/finance-recovery-evidence/original-auth02/source/site/app/plans/records.php': '2d9df2440c7ef3d21cea9b6d640006fa5a6545783ef90659c24555f74d04531c',
 'docs/finance-recovery-evidence/original-auth02/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/original-auth02/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/original-auth02/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/original-auth02/source/tests/finance_replacement_payoff_boundaries.php': '5cc87de087a46a43d6acbd8525ed667b4b4ae68d643be7e45b7c70f573c30514',
 'docs/finance-recovery-evidence/original-auth02/source/tests/finance_replacement_payoff_native.php': 'eaae47960f586f6b9aef01d744e56e05bfe4b5ac78350d620a361e597d928464',
 'docs/finance-recovery-evidence/original-source-contract02/execution.json': '33fba7d0bd28bb1db7bd7a49ddde068c25701699717f6a6cc0894ed990089e45',
 'docs/finance-recovery-evidence/original-source-contract02/stderr.log': '953bb6c68bb192dd6318a17df8e2c57ce13b10abe37fd2e1483bcf115c4ea84a',
 'docs/finance-recovery-evidence/original-source-contract02/stdout.log': '1d381bc446b9e88248041f2da90ffbbc140fc0c011af027393af59e79a50fdfe',
 'docs/finance-recovery-evidence/original01/execution.json': '36d129f00bfe9d9f5457fafad00f54ccd1f239a6bd78fab100fc886ee543500c',
 'docs/finance-recovery-evidence/original01/native.json': 'b4735b104f915e1427e572e2dc1950e8e547731610588e55167f9f9737cb2d17',
 'docs/finance-recovery-evidence/original01/native.stderr.log': '5b0ef1927a1f5a6e63f464140135648816ebdabd79da4dc51ea3847babd5160f',
 'docs/finance-recovery-evidence/original01/native.stdout.log': 'b4735b104f915e1427e572e2dc1950e8e547731610588e55167f9f9737cb2d17',
 'docs/finance-recovery-evidence/original01/source/site/app/billing/finance_authorize_net.php': '75cd7b5d120084da911ab9810e095e10e1737bdaa99aece87dbba695fe11d00f',
 'docs/finance-recovery-evidence/original01/source/site/app/billing/finance_subscription.php': 'eae98b777fb8da6325c9ba92a5ca54c7bbe0eac01a575f01094eb5c3c05c19a9',
 'docs/finance-recovery-evidence/original01/source/site/app/billing/ledger.php': 'd2e144b11747ad2ea51e3a6c425b75dd3133f54f58e14a210110dc1ff0f1714a',
 'docs/finance-recovery-evidence/original01/source/site/app/licenses/finance_installments.php': '75c34c7c0806d3fd0435c711fa310151667d4510216702c3aad3e69cb98178ae',
 'docs/finance-recovery-evidence/original01/source/site/app/licenses/financing.php': 'c384d0303f59fe6c8351af0bc4f72d69f4b5b3065e1e3cc85103bb33e3ce95bb',
 'docs/finance-recovery-evidence/original01/source/site/database/migrations/license_finance_gateway_migrate.php': 'c3661c16c4c6e25fa567c236820764409a610c08be4ac4b671e87646d85520d2',
 'docs/finance-recovery-evidence/original01/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/original01/source/tests/finance_replacement_payoff_boundaries.php': '5cc87de087a46a43d6acbd8525ed667b4b4ae68d643be7e45b7c70f573c30514',
 'docs/finance-recovery-evidence/original01/source/tests/finance_replacement_payoff_native.php': '4cd3da88809fdcf8605ac232d236ceff6c329cc51f14eac101f520f6373a3c36',
 'docs/finance-recovery-evidence/original01/source/tests/finance_replacement_payoff_recovery_boundaries.php': 'cf23be5397b62b3f9a6a21e4c96a6cc1109297d407004e520c9875440839ee3b',
 'docs/finance-recovery-evidence/original01/source/tests/finance_replacement_payoff_recovery_cases.php': '523bbc50001a4a73afc3a8eb95442655a622c5218f305607c4d89115bfb59691',
 'docs/finance-recovery-evidence/owned-server-recovery01/33311-after-server-launch.log': '2cfa5ddb0747dfab9f4d1961fbd57a66455cd929e521d1e6d26d7f14f84e1933',
 'docs/finance-recovery-evidence/owned-server-recovery01/33311-after-server.log': 'a1c5521abdc64db56ff22dbf7409ffbf5b7b0d7a5907738b6094fae63f9a4106',
 'docs/finance-recovery-evidence/owned-server-recovery01/33311-before-launch.json': '6b79aa167a2d481fbe1ffd58367c6c4f58188a68c47ec72cbb609235c5c67687',
 'docs/finance-recovery-evidence/owned-server-recovery01/33311-before-server-launch.log': '2cfa5ddb0747dfab9f4d1961fbd57a66455cd929e521d1e6d26d7f14f84e1933',
 'docs/finance-recovery-evidence/owned-server-recovery01/33311-before-server.log': 'b31b7d8785146fd502d3cde30cf08bf51a38f224dd8b1240823ed8e8f62eb7b2',
 'docs/finance-recovery-evidence/owned-server-recovery01/33313-restart-server-launch.log': '9e78d9f6c5e7bccc9826add68d7ba3255dd9691ad91fde0f563dba2a8affeea9',
 'docs/finance-recovery-evidence/owned-server-recovery01/33313-restart-server.log': '9a170483aace46333d0fffe08b7a788480622b0b3ba113b46546c01d7eeddf61',
 'docs/finance-recovery-evidence/owned-server-recovery01/execution.json': '2e390986fb62347d935c571c1255861189f6fc04ff8ab4e7264547d2f814857e',
 'docs/finance-recovery-evidence/payoff-compatibility-adaptation01/delta.patch': 'b57658bc96c5464f4b9cdb4e2873dac9d2d757f52058592518eb6452848b193a',
 'docs/finance-recovery-evidence/payoff-compatibility-adaptation01/diagnostic-classification.json': '835a2ff2fa74ccc304f558a084d0b40885e179e35ca913b5191c53c3bc78f779',
 'docs/finance-recovery-evidence/payoff-compatibility-adaptation01/manifest.json': 'f97dd91f3fcaa910acb1fdc1468a0e986928c06d62018d9a2488e0786723d410',
 'docs/finance-recovery-evidence/payoff-compatibility-adaptation01/rationale.md': '28390f2b22721eb6b506ff59f17e0a78d0b84afa9f39bb79c8c065fd8d822ca9',
 'docs/finance-recovery-evidence/payoff-compatibility-adaptation01/source/site/app/licenses/financing.php': '2d2857ef961963044c641123899041bf728247ac555bb22a2599ea4c04664bc6',
 'docs/finance-recovery-evidence/payoff-compatibility-adaptation01/source/tests/finance_payoff_retirement_boundaries.php': '567eaaed4b7723ddeaf8c52443395a58377729a3605e24d134ea937713a22b8b',
 'docs/finance-recovery-evidence/payoff-compatibility-adaptation01/source/tests/finance_payoff_retirement_native.php': '622a08903c74e2ee471bbef15ccfb896d960ddf2115f41e7f770439d58824cec',
 'docs/finance-recovery-evidence/payoff-compatibility-adaptation01/source/tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2',
 'docs/finance-recovery-evidence/pure-final01/execution.json': 'f13cc6dd1ad99fd615023b459e69d5fc3b7e988708649ce147a5ead111a14f8f',
 'docs/finance-recovery-evidence/pure-final01/finance_charge_attempt_bindings/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final01/finance_charge_attempt_bindings/stdout.log': '3ec36965226a12176d8aeef35787aab9657c53f069ae4caf0428cf89dfea8c69',
 'docs/finance-recovery-evidence/pure-final01/finance_enrollment_sandbox_boundary/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final01/finance_enrollment_sandbox_boundary/stdout.log': 'fdafa9edfabf1b8b82b4cae886e7736a93460183684d88d868ef5ea2550ec99e',
 'docs/finance-recovery-evidence/pure-final01/finance_initial_enrollment_handler/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final01/finance_initial_enrollment_handler/stdout.log': 'e18936055c3f089dd43ce5f7ce56f34302457a9620c850dff5b215101df8e54c',
 'docs/finance-recovery-evidence/pure-final01/finance_reconcile_snapshot/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final01/finance_reconcile_snapshot/stdout.log': 'f96ff6e2fa006d968e49e0dc3183e544102aacf26147c759f9265080317555a4',
 'docs/finance-recovery-evidence/pure-final01/finance_replacement_outcomes/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final01/finance_replacement_outcomes/stdout.log': 'b7c3f84e5b039ebdc6ce8f1355976c0ba26fcd0e807b747f0d67aa8d1993ca58',
 'docs/finance-recovery-evidence/pure-final01/finance_subscription_lifecycle/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final01/finance_subscription_lifecycle/stdout.log': '8001b33cf8c4d112517cc61a06e077557520a9520e6c3dd478bc6ff3c817d1c2',
 'docs/finance-recovery-evidence/pure-final01/license_finance_replacement_contract/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final01/license_finance_replacement_contract/stdout.log': 'f0318ab5c476cd6982d7e1a00184ce731187549075d09ee89cf2bede6e87cc59',
 'docs/finance-recovery-evidence/pure-final01/license_finance_source_contract/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final01/license_finance_source_contract/stdout.log': '43e774120c53630bd52ccceb5f38be38d86a16ceabb5188d26d9c45e3c49a16a',
 'docs/finance-recovery-evidence/pure-final01/lint.stdout.log': '23047f0aac9cf8023d6f712c2041940b6968ec0b429a2d386aaa0371b2c1fc05',
 'docs/finance-recovery-evidence/pure-final02/execution.json': '3aee3dc97b0c1a8cca05247b5b8f3b32ec853c80f15fab8e5fb866ca753fbf06',
 'docs/finance-recovery-evidence/pure-final02/finance_charge_attempt_bindings/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final02/finance_charge_attempt_bindings/stdout.log': '3ec36965226a12176d8aeef35787aab9657c53f069ae4caf0428cf89dfea8c69',
 'docs/finance-recovery-evidence/pure-final02/finance_enrollment_sandbox_boundary/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final02/finance_enrollment_sandbox_boundary/stdout.log': 'd917e75523272e078c0432b2bd778597acfcf0c33b9061a4574399739db2852e',
 'docs/finance-recovery-evidence/pure-final02/finance_initial_enrollment_handler/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final02/finance_initial_enrollment_handler/stdout.log': '9d206fd118f93b319253aeae12409c8d324fa957419de8b6fdb30ea3983a7f5e',
 'docs/finance-recovery-evidence/pure-final02/finance_reconcile_snapshot/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final02/finance_reconcile_snapshot/stdout.log': 'de80aedc5ad51eb1f17476db88d4afcf25d0285cd9d158e93ec4b5d7d8259e81',
 'docs/finance-recovery-evidence/pure-final02/finance_replacement_outcomes/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final02/finance_replacement_outcomes/stdout.log': '8bb4b6dc2cc65c96ca4d545164896efb7ee5be89dd57271db66f19c6854b395e',
 'docs/finance-recovery-evidence/pure-final02/finance_subscription_lifecycle/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final02/finance_subscription_lifecycle/stdout.log': '8001b33cf8c4d112517cc61a06e077557520a9520e6c3dd478bc6ff3c817d1c2',
 'docs/finance-recovery-evidence/pure-final02/license_finance_replacement_contract/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final02/license_finance_replacement_contract/stdout.log': 'f0318ab5c476cd6982d7e1a00184ce731187549075d09ee89cf2bede6e87cc59',
 'docs/finance-recovery-evidence/pure-final02/license_finance_source_contract/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final02/license_finance_source_contract/stdout.log': '43e774120c53630bd52ccceb5f38be38d86a16ceabb5188d26d9c45e3c49a16a',
 'docs/finance-recovery-evidence/pure-final03/canonical.stderr.log': 'a5d504c365710ec95cb1d39c20a32dbffb777a720030cb3c7338b983e0bf9e09',
 'docs/finance-recovery-evidence/pure-final03/canonical.stdout.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final03/execution.json': '2eb9a48957c32fdb4011e648498e6be4249391ce60893cce57169e1f89d77e31',
 'docs/finance-recovery-evidence/pure-final04/canonical.stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/canonical.stdout.log': '401de6817343b9321e9475c025fed1f1969f7e723f6ee20deb6ab526c25282b9',
 'docs/finance-recovery-evidence/pure-final04/execution.json': 'b40e8290de2356987bf1bc0d722b4621ec9dbfa3b9b284904db57991441ba7b3',
 'docs/finance-recovery-evidence/pure-final04/finance_charge_attempt_bindings/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/finance_charge_attempt_bindings/stdout.log': '3ec36965226a12176d8aeef35787aab9657c53f069ae4caf0428cf89dfea8c69',
 'docs/finance-recovery-evidence/pure-final04/finance_enrollment_sandbox_boundary/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/finance_enrollment_sandbox_boundary/stdout.log': 'a2c25caf240797b60116506edfd992946f744c4d5cee3d41fea61387851ebbde',
 'docs/finance-recovery-evidence/pure-final04/finance_initial_enrollment_handler/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/finance_initial_enrollment_handler/stdout.log': 'aa8459938501e8a3526e45fce57328f9582ae9f436eb1c2eff7761b77cb0ee8c',
 'docs/finance-recovery-evidence/pure-final04/finance_reconcile_snapshot/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/finance_reconcile_snapshot/stdout.log': 'a77baa0723e17874ef9549878aaff8c8196d494c27cf9ddd581f39d233ffe637',
 'docs/finance-recovery-evidence/pure-final04/finance_replacement_outcomes/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/finance_replacement_outcomes/stdout.log': '08d095ce31a4d5949c41576793a1883a20c6946b45ef36e524a6c4bc7fb39258',
 'docs/finance-recovery-evidence/pure-final04/finance_subscription_lifecycle/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/finance_subscription_lifecycle/stdout.log': '8001b33cf8c4d112517cc61a06e077557520a9520e6c3dd478bc6ff3c817d1c2',
 'docs/finance-recovery-evidence/pure-final04/license_finance_replacement_contract/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/license_finance_replacement_contract/stdout.log': 'f0318ab5c476cd6982d7e1a00184ce731187549075d09ee89cf2bede6e87cc59',
 'docs/finance-recovery-evidence/pure-final04/license_finance_source_contract/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final04/license_finance_source_contract/stdout.log': '43e774120c53630bd52ccceb5f38be38d86a16ceabb5188d26d9c45e3c49a16a',
 'docs/finance-recovery-evidence/pure-final05/execution.json': '4619a49bf06a80d87d20f775990c51980cec532c02b5f4befa1cf41b8ceca4ea',
 'docs/finance-recovery-evidence/pure-final05/finance_charge_attempt_bindings/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final05/finance_charge_attempt_bindings/stdout.log': '3ec36965226a12176d8aeef35787aab9657c53f069ae4caf0428cf89dfea8c69',
 'docs/finance-recovery-evidence/pure-final05/finance_enrollment_sandbox_boundary/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final05/finance_enrollment_sandbox_boundary/stdout.log': 'a4f635dd1694174a90ee191ccca42c1ddcc4b9fcd108758e416571f3dca2fcdb',
 'docs/finance-recovery-evidence/pure-final05/finance_initial_enrollment_handler/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final05/finance_initial_enrollment_handler/stdout.log': 'e0c95e6317ea963847e604157f3c74450ff7f1441b0bbf15fb8b75b9ad3e132a',
 'docs/finance-recovery-evidence/pure-final05/finance_reconcile_snapshot/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final05/finance_reconcile_snapshot/stdout.log': 'fccacdfdd4b33c5ffd54f9492c97069d743f68da3c23d9c867f76d28ff9d5e55',
 'docs/finance-recovery-evidence/pure-final05/finance_replacement_outcomes/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final05/finance_replacement_outcomes/stdout.log': 'da6b4e40d4f1b89d951658f34f89d93405d837a2207f42cdcaf192e63c7586ef',
 'docs/finance-recovery-evidence/pure-final05/finance_subscription_lifecycle/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final05/finance_subscription_lifecycle/stdout.log': '8001b33cf8c4d112517cc61a06e077557520a9520e6c3dd478bc6ff3c817d1c2',
 'docs/finance-recovery-evidence/pure-final05/license_finance_replacement_contract/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final05/license_finance_replacement_contract/stdout.log': 'f0318ab5c476cd6982d7e1a00184ce731187549075d09ee89cf2bede6e87cc59',
 'docs/finance-recovery-evidence/pure-final05/license_finance_source_contract/stderr.log': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure-final05/license_finance_source_contract/stdout.log': '43e774120c53630bd52ccceb5f38be38d86a16ceabb5188d26d9c45e3c49a16a',
 'docs/finance-recovery-evidence/pure01/execution.json': 'd10704d09d6e42eac5d890821be16809dc165af2f39e792cc8caed2a89acf37a',
 'docs/finance-recovery-evidence/pure01/finance_charge_attempt_bindings.php.stderr': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/finance_charge_attempt_bindings.php.stdout': '3ec36965226a12176d8aeef35787aab9657c53f069ae4caf0428cf89dfea8c69',
 'docs/finance-recovery-evidence/pure01/finance_enrollment_sandbox_boundary.php.stderr': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/finance_enrollment_sandbox_boundary.php.stdout': 'fdafa9edfabf1b8b82b4cae886e7736a93460183684d88d868ef5ea2550ec99e',
 'docs/finance-recovery-evidence/pure01/finance_initial_enrollment_handler.php.stderr': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/finance_initial_enrollment_handler.php.stdout': 'e18936055c3f089dd43ce5f7ce56f34302457a9620c850dff5b215101df8e54c',
 'docs/finance-recovery-evidence/pure01/finance_payoff_retirement_guards.php.stderr': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/finance_payoff_retirement_guards.php.stdout': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/finance_reconcile_snapshot.corrected.json': 'f04b3ac157081dcebc2cd70a58631f3c301db4bc3ee911c4b5f37498eb10e996',
 'docs/finance-recovery-evidence/pure01/finance_reconcile_snapshot.php.stderr': 'ee2ffea5fa3f0f87051f5c89baa16dbc0413cac3e17a99fbef5b1bbe89ab280e',
 'docs/finance-recovery-evidence/pure01/finance_reconcile_snapshot.php.stdout': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/finance_replacement_outcomes.corrected.json': 'b7c3f84e5b039ebdc6ce8f1355976c0ba26fcd0e807b747f0d67aa8d1993ca58',
 'docs/finance-recovery-evidence/pure01/finance_replacement_outcomes.php.stderr': 'a697912740dabe8613680faf058c7a23a2438c6784ab3c6b92696dc188ba675f',
 'docs/finance-recovery-evidence/pure01/finance_replacement_outcomes.php.stdout': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/finance_subscription_lifecycle.php.stderr': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/finance_subscription_lifecycle.php.stdout': '8001b33cf8c4d112517cc61a06e077557520a9520e6c3dd478bc6ff3c817d1c2',
 'docs/finance-recovery-evidence/pure01/license_finance_replacement_contract.php.stderr': 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
 'docs/finance-recovery-evidence/pure01/license_finance_replacement_contract.php.stdout': 'f0318ab5c476cd6982d7e1a00184ce731187549075d09ee89cf2bede6e87cc59',
 'docs/finance-recovery-evidence/pure01/license_finance_source_contract.php.stderr': 'a0e605d1197800f1c20408cd691f9dd38523117ef9e4526011e729d832a080f8',
 'docs/finance-recovery-evidence/pure01/license_finance_source_contract.php.stdout': '1d381bc446b9e88248041f2da90ffbbc140fc0c011af027393af59e79a50fdfe',
 'docs/finance-recovery-evidence/review-checkpoints/finance-authority-baseline-review.json': '68ac90634dcd59d98a5e60a9150ffde8540e7e6177bc8883d02655415cbd04a1',
 'docs/finance-recovery-evidence/review-checkpoints/finance-authority-baseline-review.md': '930aec4a05a5eef593ed0f996e51348c7e31b42c29bec15f62f4a84e740a3796',
 'docs/finance-recovery-evidence/review-checkpoints/finance-initial-admission-adaptation-independent-review.json': '9ecc0454b50c108ea003918068d0004ca3d3abc4628b2b9bee516ab174ff1dd9',
 'docs/finance-recovery-evidence/review-checkpoints/finance-initial-admission-adaptation-independent-review.md': 'ff2f31388cc8fb1ed28244096677bdae2c1bb6aea6dba874383a16eaa8a4bdc2',
 'docs/finance-recovery-evidence/review-checkpoints/finance-initial-unresolved-independent-root-review.json': 'f1bbeabdc4d69ec56ae86832747e7ad273078bc4958d2afad2e92a248e0d3b6c',
 'docs/finance-recovery-evidence/review-checkpoints/finance-payoff-compatibility-independent-review.json': '4136b9e78500b82e6fd7c9fd206d82e53a9a30f99cfe1bc17342f963e88dbd4a',
 'docs/finance-recovery-evidence/review-checkpoints/finance-recovery-early-correctness-review.json': '91ecedd9a8ecaeb417aa0d483c744975bd16b345aeaa44f1392de6379118053c',
 'docs/finance-recovery-evidence/review-checkpoints/finance-recovery-early-correctness-review.md': '8b579588fe2bbbad06922f9d6a65a96c7c15fc9b1d9ee4e04553ee01db5f7a0b',
 'docs/finance-recovery-evidence/review-checkpoints/finance-recovery-final-independent-review.json': '6603ee0ba9557191eb0320d4a79b4623b3069d9e3b4c37dd793ccb976400e1e4',
 'docs/finance-recovery-evidence/review-checkpoints/finance-recovery-final-independent-review.md': 'c20b15263d66a2f6bf6af4aea3679020f4b1f6f9aef9774cbbc31837436c6f3f',
 'docs/finance-recovery-evidence/review-checkpoints/finance-recovery-guard-chain-review.json': '894fcf0c99d0457379d364ad5c42e7b176bdea77fb5611fa57dff826250a281f',
 'docs/finance-recovery-evidence/review-checkpoints/finance-recovery-guard-chain-review.md': 'ae2a522d88b0c0f1e03d89d9d35a719ff641fa4eef82fd6adf8ff2fc5880ba63',
 'docs/finance-recovery-evidence/review-checkpoints/primary-finance-review.json': '8c6eeeb25b0960d9a1570d48aec891324df9eaa5cd8c8e9412333ba3c0b199c7',
 'docs/finance-recovery-evidence/review-checkpoints/primary-finance-review.md': 'f79433e6e29941d8203378f47f3cc347bbef88b354946077173b70228ac1c2a7',
 'docs/finance-recovery-evidence/safety-first/execution.json': '36b57763d3e0c41abae1718bad6f6f6691c6611e0636fcc557f2fa05ebb3ff07',
 'docs/finance-recovery-evidence/safety-first/native.json': '163d512f7cf634e9db0153e1366d00b575fb294e970d86c5c99273debf2fabfc',
 'docs/finance-recovery-evidence/safety-first/native.stderr.log': '827792cc6051981d88669ed8b70e5a7a70348e59f49c5f055dc13f26f1ed4a42',
 'docs/finance-recovery-evidence/safety-first/native.stdout.log': '163d512f7cf634e9db0153e1366d00b575fb294e970d86c5c99273debf2fabfc',
 'docs/finance-recovery-evidence/safety-first/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/safety-first/source/site/app/billing/finance_authorize_net.php': '27091dfeb30fbe7ef2f4c4292b388370747afeaa282583b1057ae90f37a33d17',
 'docs/finance-recovery-evidence/safety-first/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/safety-first/source/site/app/billing/finance_recovery.php': '07fc039980eda8cd190f61467f98f03b184096344700945d283b8c55eaf496fb',
 'docs/finance-recovery-evidence/safety-first/source/site/app/billing/finance_subscription.php': 'd0a130a57b7edef2f7c5782cf19389b727bbc2912d506eba9ae77b0966b279f6',
 'docs/finance-recovery-evidence/safety-first/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/safety-first/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/safety-first/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/safety-first/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/safety-first/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/safety-first/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/safety-first/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/safety-first/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/safety-first/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/safety-first/source/tests/finance_replacement_payoff_recovery_boundaries.php': '6a55ba0f2bbf8ee236c05dd7ece9f5aa6b5b781474a5df4c9b7c23ec5190bf36',
 'docs/finance-recovery-evidence/safety-first/source/tests/finance_replacement_payoff_recovery_cases.php': 'f3ba02e1433ddd277e81fe873cdc6899baf88953c8cea4ba168fb3b02e7da84d',
 'docs/finance-recovery-evidence/safety-first/source/tests/finance_replacement_payoff_recovery_safety.php': 'ea5144371c0fd15da50437910e55a88a304cdf085a7ec42fe02a22576485c9f0',
 'docs/finance-recovery-evidence/safety-second/execution.json': '8ba6bdfbf67b547044db663daf5ee6ed70d2bd87d37e0f87f4e229e8e9925bb1',
 'docs/finance-recovery-evidence/safety-second/native.json': '6aad644ff31611e762f9d892228de0f311643877c23d7db239306f684ea16929',
 'docs/finance-recovery-evidence/safety-second/native.stderr.log': 'd92582a04328b63bc06e15531a20d7f96a28a18172a3ee443c708d3d91713a81',
 'docs/finance-recovery-evidence/safety-second/native.stdout.log': '6aad644ff31611e762f9d892228de0f311643877c23d7db239306f684ea16929',
 'docs/finance-recovery-evidence/safety-second/source/site/admin/views/studio/licenses.php': '897c428f411be19b73dfb89004bcd5f16c5536d11e1a2ccba585853a8bf2d3fc',
 'docs/finance-recovery-evidence/safety-second/source/site/app/billing/finance_authorize_net.php': '484bd1208f743548a9e92b90a9a44f223d4f8399fec80da157479ba8d8a572ba',
 'docs/finance-recovery-evidence/safety-second/source/site/app/billing/finance_charge.php': 'b6fd12026dc4fc51550ece8d4cc1d4001eed1e5e1ecc64e05d3e61d36c1c11f8',
 'docs/finance-recovery-evidence/safety-second/source/site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'docs/finance-recovery-evidence/safety-second/source/site/app/billing/finance_subscription.php': '9f8e4965304ef90b0d9d22e3db02e14d870cd3a9d83fcbf46bdefbd633f0b6df',
 'docs/finance-recovery-evidence/safety-second/source/site/app/billing/ledger.php': 'fd42830175fc973694d13791d81e8c507be85482210b974d9f9398ceb54fc989',
 'docs/finance-recovery-evidence/safety-second/source/site/app/licenses/finance_installments.php': 'fcbc11b6b742e906bd7a0d14544b3bd9499f4722af2d9d869e446d1a4bb75f4c',
 'docs/finance-recovery-evidence/safety-second/source/site/app/licenses/financing.php': '964f62a8146552555fbd194ed45a71ab988fdbc71ae00f5044460cf05dce2f99',
 'docs/finance-recovery-evidence/safety-second/source/site/database/migrations/license_finance_gateway_migrate.php': 'df61523c70d26912ec17b22c2bf3bba4f681ba736f7c88fb302a86b4c15413d9',
 'docs/finance-recovery-evidence/safety-second/source/site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'docs/finance-recovery-evidence/safety-second/source/site/database/schema/license_finance_gateway_schema.php': '9e02f98f9b38eefa1361c441f09c0e1841fd9c0e5a498708b3a39ed470f6dd25',
 'docs/finance-recovery-evidence/safety-second/source/site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'docs/finance-recovery-evidence/safety-second/source/tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'docs/finance-recovery-evidence/safety-second/source/tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'docs/finance-recovery-evidence/safety-second/source/tests/finance_replacement_payoff_boundaries.php': '0f220d09f928d36f0bda265e9b14a40ff3d5d7015aea533fc165b04c9f1e96cc',
 'docs/finance-recovery-evidence/safety-second/source/tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'docs/finance-recovery-evidence/safety-second/source/tests/finance_replacement_payoff_native.php': 'dfef81e60bb68b25e2f9584ecbbd7d4ad53eb2bb699159a40203ff88f0a9c7c9',
 'docs/finance-recovery-evidence/safety-second/source/tests/finance_replacement_payoff_recovery_boundaries.php': '2004c6cd07f36b1a38f5bc4d18abb0315c47facdb0ae8f74684e5457328b4b5a',
 'docs/finance-recovery-evidence/safety-second/source/tests/finance_replacement_payoff_recovery_cases.php': '188248b9324394b235cc83bb051957a2921a7261300a01c7cbd955396c0d79ae',
 'docs/finance-recovery-evidence/safety-second/source/tests/finance_replacement_payoff_recovery_safety.php': '450c43e88de41e748b49b1e0c816f2295e99616b36a40db9219df8fbcfdb17ad',
 'docs/finance-recovery-evidence/schema-timing01.json': '2655c74786f2ca85039c77482fd6accd199c0e480d49f326b44077833140188d',
 'site/app/billing/finance_recovery.php': '4edb7a10b9684e5fc2959484dc2902ccc8ff19cf99fa8ef5f4ecd247c6270ec2',
 'site/database/migrations/license_finance_recovery_migrate.php': '4745502b88e9037a2ce9da298c1f98845295220aee69b8780e2c352720beac0e',
 'site/database/schema/license_finance_recovery_schema.php': 'ae8c112da7961d3bbcd6944b6c2c98220ecdfe6dbc78a22c6d6e3b3e5592e21f',
 'tests/finance_replacement_payoff_authority_cases.php': '49863331b3b8c12a37ed0eb55b2775dd77679f211516699291d5470e89c8c1e2',
 'tests/finance_replacement_payoff_authority_native.php': '600f8a4fbcbc2afab788dadba386f0b18b29c9b7bac33c45b0a5b2556f267617',
 'tests/finance_replacement_payoff_gateway_cases.php': '427217bc891eee36eba5d3d54c5b05069bd2fe4e1a5ba7dfd0b852d98f038ec1',
 'tests/finance_replacement_payoff_initial_admission.php': '087a779de88e3b6a763bf8b4a2f6fcad14ccc1905f667958cf64f040012e9a07',
 'tests/finance_replacement_payoff_recovery_boundaries.php': '50f1233955582fcf8e21d1dcb2b94890f67e23605c031f3e0a2294bc3541220d',
 'tests/finance_replacement_payoff_recovery_cases.php': '5f8b4b1299b0088fdc98b7a2e2b52d79533115cbb8bd85d730676b4de9e0e740',
 'tests/finance_replacement_payoff_recovery_safety.php': 'bd284178430c6c49b40d0402da67aef49f6270b531a3eecaedf12ad60d1aa6c2'}
# END EXACT FIN IDENTITIES


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def git(root, *args):
    return subprocess.check_output(['git', *args], cwd=root)


def ordinary_bytes(root, path):
    relative = Path(path)
    require(not relative.is_absolute() and '..' not in relative.parts
            and relative.as_posix() == path, 'Unsafe finance proof path')
    target = root / relative
    require(target.is_file() and not target.is_symlink(), 'Nonregular finance source: ' + path)
    for parent in target.parents:
        if parent == root.parent:
            break
        require(not parent.is_symlink(), 'Symlink ancestor of finance source: ' + path)
    return target.read_bytes()


def shipping_paths():
    return {path for path in set(BEFORE) | set(ADDED) if path.startswith('site/')}


def validate_data(data, verifier):
    require(isinstance(data, dict) and set(data) == {
        'parent', 'parent_tree', 'shipping', 'verifier_sha256', 'files', 'additions'},
        'Unexpected finance recovery receipt fields')
    require(data['parent'] == PARENT and data['parent_tree'] == PARENT_TREE,
            'Wrong finance recovery predecessor identity')
    require(isinstance(data['shipping'], list) and set(data['shipping']) == shipping_paths()
            and len(data['shipping']) == len(shipping_paths()), 'Unreviewed finance shipping scope')
    require(data['verifier_sha256'] == digest(verifier), 'Finance recovery verifier changed')
    require(isinstance(data['files'], dict) and set(data['files']) == set(BEFORE),
            'Unreviewed finance inverse paths')
    require(isinstance(data['additions'], dict) and data['additions'] == ADDED,
            'Unreviewed finance additions or evidence')
    for path, row in data['files'].items():
        require(isinstance(row, dict) and set(row) == {'before_sha256', 'after_sha256', 'edits'},
                'Unexpected finance inverse fields: ' + path)
        require(row['before_sha256'] == BEFORE[path] and row['after_sha256'] == AFTER[path],
                'Changed finance correction identities: ' + path)
        require(isinstance(row['edits'], list) and bool(row['edits']),
                'Missing bounded finance inverse: ' + path)
        for edit in row['edits']:
            require(isinstance(edit, list) and len(edit) == 2
                    and all(isinstance(value, str) and bool(value) for value in edit)
                    and edit[0] != edit[1], 'Invalid finance inverse edit: ' + path)
    return data


def receipt(root):
    return validate_data(json.loads(ordinary_bytes(root, RECEIPT)), ordinary_bytes(root, VERIFIER))


def reconstruct(data, path, value):
    require(path in BEFORE, 'Unknown finance predecessor path')
    row = data['files'][path]
    require(row['before_sha256'] == BEFORE[path] and row['after_sha256'] == AFTER[path],
            'Finance inverse identity changed: ' + path)
    require(digest(value) == AFTER[path], 'Current finance source changed: ' + path)
    text = value.decode('utf-8')
    for old, new in reversed(row['edits']):
        require(text.count(new) == 1, 'Nonunique finance inverse: ' + path)
        text = text.replace(new, old, 1)
    original = text.encode('utf-8')
    require(digest(original) == BEFORE[path], 'Finance inverse did not retain exact predecessor: ' + path)
    for old, new in row['edits']:
        require(text.count(old) == 1, 'Nonunique finance forward edit: ' + path)
        text = text.replace(old, new, 1)
    require(text.encode('utf-8') == value, 'Finance correction is not exactly reversible: ' + path)
    return original


def validate_parent(tree, ancestor):
    require(tree == PARENT_TREE and ancestor, 'Finance source does not descend from exact84')


def validate_scope(changed, ignored_shipping):
    expected = set(BEFORE) | set(ADDED) | {VERIFIER, RECEIPT}
    require(set(changed) == expected, 'Unreviewed finance correction scope: '
            + repr(set(changed).symmetric_difference(expected)))
    require(not ignored_shipping, 'Ignored finance shipping source: ' + repr(ignored_shipping))


def validate_addition(path, value, parent_paths):
    require(path in ADDED and path not in parent_paths, 'Finance addition exists at parent or is unknown: ' + path)
    require(value is not None and digest(value) == ADDED[path], 'Finance addition changed or is missing: ' + path)


def nonexecutable_file(root, path):
    ordinary_bytes(root, path)
    require(not ((root / path).stat().st_mode & 0o111), 'Unexpected executable finance source: ' + path)


def proof_modes(root):
    for path in (VERIFIER, RECEIPT):
        nonexecutable_file(root, path)


def frozen04_compatibility_bytes(root, path):
    value = ordinary_bytes(root, path)
    if path == 'site/app/billing/finance_subscription.php':
        guard = (b'            // A zero ledger balance does not resolve an already admitted charge.\n'
                 b"            license_finance_recovery_invoice_clear((int)$map['invoice_id']);\n")
        require(value.count(guard) == 1, 'Reviewed initial admission guard changed')
        value = value.replace(guard, b'', 1)
        require(digest(value) == '3baa531431b52fc42b63d17c6b6190a38eb881f7de709099a6f92881302654f8',
                'Initial guard inverse did not retain exact frozen04 subscription')
    return value


def canonical_inventory(root):
    site = root / 'site'
    rows = []
    for target in sorted(site.rglob('*'), key=lambda path: path.relative_to(site).as_posix()):
        require(not target.is_symlink(), 'Symlink in finance shipping tree')
        if target.is_file():
            name = target.relative_to(site).as_posix()
            if name != 'install/inventory.generated.php':
                rows.append("    '" + name + "'=>'" + digest(target.read_bytes()) + "',\n")
    guard = "<?php\ndeclare(strict_types=1);\nif (!defined('CWP_SETUP') || !defined('CWP_BOOT')) { http_response_code(404); exit; }\n"
    return (guard + 'function setup_expected_files(): array { return [\n'
            + ''.join(rows) + ']; }\n').encode('utf-8')


def validate_inventory(current, expected):
    require(current == expected, 'Finance installer inventory is not canonical current source')


def parent_entries(root):
    entries = {}
    for entry in git(root, 'ls-tree', '-r', '-z', PARENT).split(b'\0'):
        if entry:
            metadata, name = entry.split(b'\t', 1)
            entries[name.decode()] = tuple(metadata.decode().split(' '))
    return entries


def retained_path(path):
    return (path.startswith(('tests/', 'tools/', '.github/workflows/', 'foundation/'))
            or path.startswith('docs/') and path.endswith('.json')) and path not in BEFORE


def original_assertion_bodies(root):
    for path in ('tests/finance_replacement_payoff_native.php',
                 'tests/finance_replacement_receipts_native.php',
                 'tests/finance_replacement_pending_native.php'):
        old = git(root, 'cat-file', 'blob', PARENT + ':' + path)
        current = ordinary_bytes(root, path)
        marker = b'try{\n    foreach(schema_statements($prefix)'
        require(old.count(marker) == current.count(marker) == 1, 'Finance original runner boundary changed')
        old_body = old[old.index(marker):]
        new_body = current[current.index(marker):]
        if path.endswith('payoff_native.php'):
            hook = b'    frg_recovery_cases();\n'
            require(new_body.count(hook) == 1, 'Finance recovery must run once through retained native entry')
            new_body = new_body.replace(hook, b'', 1)
        require(old_body == new_body, 'Original finance assertions or runner changed: ' + path)
    path = 'tests/finance_payoff_retirement_native.php'
    old = git(root, 'cat-file', 'blob', PARENT + ':' + path)
    current = ordinary_bytes(root, path)
    fixture_edits = [
        (b'use($invoices,&$paymentEvidence):void{', b'use($invoices,&$paymentEvidence,$caller):void{'),
        (b'', b'                        // Isolate the original final-payoff fault: the first two\n'
              b'                        // manual receipts commit while the synthetic gateway is\n'
              b'                        // paused. FIN-02 separately covers immediate retirement\n'
              b'                        // and repeated receipts with an enabled gateway.\n'
              b"                        $GLOBALS['fepReceiptGatewayPaused']=$caller==='manual'&&$invoice!==$invoices[array_key_last($invoices)];\n"),
        (b'}finally{$_SESSION=$session;}',
         b"}finally{unset($GLOBALS['fepReceiptGatewayPaused']);$_SESSION=$session;}"),
    ]
    for before, after in fixture_edits:
        require(current.count(after) == 1, 'Final-payoff fixture timing edit changed')
        current = current.replace(after, before, 1)
    require(current == old, 'Original final-payoff assertions or non-timing fixture bytes changed')
    original = ordinary_bytes(root, 'docs/finance-recovery-evidence/original01/source/tests/finance_replacement_payoff_recovery_cases.php')
    current = ordinary_bytes(root, 'tests/finance_replacement_payoff_recovery_cases.php')
    marker = b'function frg_recovery_cases(): void {\n'
    retained = original[original.index(marker):]
    require(retained.endswith(b'\n}\n') and current[current.index(marker):].startswith(retained[:-2]),
            'Original 79 finance failure/control assertions changed')
    baseline = json.loads(ordinary_bytes(root, 'docs/finance-recovery-evidence/original01/native.json'))
    final = json.loads(ordinary_bytes(root, 'docs/finance-recovery-evidence/final-native05/native.json'))
    require(len(baseline['checks']) == 217 and sum(not row['passed'] for row in baseline['checks']) == 20,
            'Original exact84 finance failure evidence changed')
    require(all(row['passed'] for row in baseline['checks'][:138]), 'Original 138 finance assertions were not green')
    require([row['name'] for row in baseline['checks']] == [row['name'] for row in final['checks'][:217]]
            and final['passed'] and all(row['passed'] for row in final['checks']),
            'Current native evidence did not retain all original assertions and resolve the failures')
    authority = json.loads(ordinary_bytes(root, 'docs/finance-recovery-evidence/original-auth02/native.json'))
    require(len(authority['checks']) == 25 and sum(not row['passed'] for row in authority['checks']) == 11,
            'Original exact84 authority failure evidence changed')
    require([row['name'] for row in authority['checks']]
            == [row['name'] for row in final['checks'] if row['name'].startswith('FIN-AUTH')],
            'Current native evidence omitted an original authority control or failure')
    previous = json.loads(ordinary_bytes(root, 'docs/finance-recovery-evidence/final-native04/native.json'))
    require(previous['passed'] and len(previous['checks']) == 387
            and [row['name'] for row in previous['checks']] == [row['name'] for row in final['checks'][:387]]
            and len([row for row in final['checks'] if row['name'].startswith('FIN-ADMISSION')]) == 26,
            'Final initial admission cases replaced or omitted an earlier native assertion')
    execution = json.loads(ordinary_bytes(root, 'docs/finance-recovery-evidence/final-native05/execution.json'))
    require(execution['exit_code'] == 0 and execution['report_passed'] is True
            and execution['check_count'] == len(final['checks']) == 413,
            'Final native execution is incomplete or failed')
    for path, expected in execution['source_sha256'].items():
        require(digest(ordinary_bytes(root, path)) == expected
                and digest(ordinary_bytes(root, 'docs/finance-recovery-evidence/final-native05/source/' + path)) == expected,
                'Final native evidence is not the current source: ' + path)
    compatibility = [
        ('adjacent-compatibility02', '01-finance_payoff_retirement_native', 0, 292),
        ('adjacent-resumed03', '01-finance_replacement_receipts_native', 0, 232),
        ('adjacent-resumed03', '02-finance_replacement_pending_native', 1, 25),
    ]
    for phase, directory, index, count in compatibility:
        base = 'docs/finance-recovery-evidence/' + phase + '/'
        execution = json.loads(ordinary_bytes(root, base + 'execution.json'))
        result = execution['results'][index]
        report = json.loads(ordinary_bytes(root, base + directory + '/native.json'))
        require(result['exit_code'] == 0 and result['report_passed'] is True
                and result['check_count'] == len(report['checks']) == count
                and report['passed'] and all(row['passed'] for row in report['checks']),
                'Retained frozen04 finance compatibility result is incomplete or failed: ' + directory)
        for path, expected_hash in execution['source_sha256'].items():
            require(digest(frozen04_compatibility_bytes(root, path)) == expected_hash
                    and digest(ordinary_bytes(root, base + 'source/' + path)) == expected_hash,
                    'Reviewed frozen04 finance compatibility source changed: ' + path)
    old_payoff = json.loads(ordinary_bytes(root,
        'docs/finance-recovery-evidence/adjacent-full01/04-finance_payoff_retirement_native/native.json'))
    new_payoff = json.loads(ordinary_bytes(root,
        'docs/finance-recovery-evidence/adjacent-compatibility02/01-finance_payoff_retirement_native/native.json'))
    require([row['name'] for row in old_payoff['checks']] == [row['name'] for row in new_payoff['checks']]
            and sum(not row['passed'] for row in old_payoff['checks']) == 8,
            'Original final-payoff failure evidence or 292 assertion names changed')
    path = 'tests/finance_initial_receipts_native.php'
    old = git(root, 'cat-file', 'blob', PARENT + ':' + path)
    current = ordinary_bytes(root, path)
    calls = lambda value: re.findall(rb'(?<!function )\bfep_ok\(.*?\);', value, re.S)
    require(len(calls(old)) == 13 and calls(old) == calls(current),
            'Retained initial receipt assertion call sites changed')
    base = 'docs/finance-recovery-evidence/initial-compatibility03/'
    execution = json.loads(ordinary_bytes(root, base + 'execution.json'))
    initial = json.loads(ordinary_bytes(root, base + 'native.json'))
    original = json.loads(ordinary_bytes(root,
        'docs/finance-recovery-evidence/adjacent-full01/03-finance_initial_receipts_native/native.json'))
    require(execution['exit_code'] == 0 and execution['report_passed'] is True
            and initial['passed'] and all(row['passed'] for row in initial['checks'])
            and execution['check_count'] == len(initial['checks']) == 358
            and [row['name'] for row in initial['checks']] == [row['name'] for row in original['checks']],
            'Final current initial receipt execution changed or omitted original assertions')
    historical = [row for row in initial['writers'] if row['entry_scope'] == 'historical admitted-result recovery']
    require(len(historical) == 4 and all(not any(call['method'] == 'ARBCreateSubscriptionRequest'
                                              for call in row['calls']) for row in historical),
            'The four historical initial-result cases are not identified honestly')
    for path, expected_hash in execution['source_sha256'].items():
        require(digest(ordinary_bytes(root, path)) == expected_hash
                and digest(ordinary_bytes(root, base + 'source/' + path)) == expected_hash,
                'Final initial receipt evidence is not current source: ' + path)
    witness_before = json.loads(ordinary_bytes(root,
        'docs/finance-recovery-evidence/finance-initial-unresolved-native03/native.json'))
    witness_after = json.loads(ordinary_bytes(root,
        'docs/finance-recovery-evidence/finance-initial-unresolved-native04/native.json'))
    require(len(witness_before['checks']) == len(witness_after['checks']) == 11
            and sum(not row['passed'] for row in witness_before['checks']) == 2
            and witness_after['passed'] and all(row['passed'] for row in witness_after['checks'])
            and [row['name'] for row in witness_before['checks']] == [row['name'] for row in witness_after['checks']]
            and witness_after['subscription_sha256'] == digest(ordinary_bytes(root, 'site/app/billing/finance_subscription.php')),
            'Independent initial-admission failure/fix witness changed or is not current subscription source')
    before_execution = json.loads(ordinary_bytes(root,
        'docs/finance-recovery-evidence/finance-initial-unresolved-native03/execution.json'))
    after_execution = json.loads(ordinary_bytes(root,
        'docs/finance-recovery-evidence/finance-initial-unresolved-native04/execution.json'))
    require(before_execution['exit_code'] != 0 and after_execution['exit_code'] == 0
            and set(before_execution['source_sha256']) == set(after_execution['source_sha256'])
            and [path for path in before_execution['source_sha256']
                 if before_execution['source_sha256'][path] != after_execution['source_sha256'][path]]
                == ['site/app/billing/finance_subscription.php'],
            'Independent initial-admission witness was changed with the application overlay')
    for path, expected_hash in after_execution['source_sha256'].items():
        if path.startswith('site/') and path != INVENTORY:
            require(digest(ordinary_bytes(root, path)) == expected_hash,
                    'Independent initial-admission witness application is not current: ' + path)


def verify_current(root):
    proof_modes(root)
    data = receipt(root)
    ancestor = subprocess.run(['git', 'merge-base', '--is-ancestor', PARENT, 'HEAD'],
                              cwd=root, capture_output=True).returncode == 0
    validate_parent(git(root, 'rev-parse', PARENT + '^{tree}').decode().strip(), ancestor)
    changed = set(git(root, 'diff', '--name-only', PARENT, '--').decode().splitlines())
    changed.update(git(root, 'ls-files', '--others', '--exclude-standard').decode().splitlines())
    ignored = git(root, 'ls-files', '--others', '--ignored', '--exclude-standard', '--', *AREAS).decode().splitlines()
    validate_scope(changed, ignored)
    entries = parent_entries(root)
    for path in BEFORE:
        require(reconstruct(data, path, ordinary_bytes(root, path))
                == git(root, 'cat-file', 'blob', PARENT + ':' + path),
                'Finance predecessor is not the actual published blob: ' + path)
        require(bool((root / path).stat().st_mode & 0o111) == (entries[path][0] == '100755'),
                'Finance source mode changed: ' + path)
    for path in ADDED:
        validate_addition(path, ordinary_bytes(root, path), entries)
        nonexecutable_file(root, path)
    require(VERIFIER not in entries and RECEIPT not in entries, 'Finance proof must be absent at exact84')
    retained = 0
    for path, (mode, kind, oid) in entries.items():
        if retained_path(path):
            require(mode in ('100644', '100755') and kind == 'blob', 'Nonregular historical finance proof input')
            value = ordinary_bytes(root, path)
            require(hashlib.sha1(b'blob ' + str(len(value)).encode() + b'\0' + value).hexdigest() == oid,
                    'Retained test, builder, workflow or receipt changed: ' + path)
            retained += 1
    validate_inventory(ordinary_bytes(root, INVENTORY), canonical_inventory(root))
    original_assertion_bodies(root)
    return data, retained


def source_revision(root):
    """Historical Git comparison only, after complete current correction proof."""
    verify_current(root)
    return PARENT


def predecessor_bytes(root, path, value):
    """Never use these bytes to execute a native fixture or construct a package."""
    if path not in BEFORE:
        return value
    data, _ = verify_current(root)
    return reconstruct(data, path, value)


def validate(root):
    data, retained = verify_current(root)
    verifier = ordinary_bytes(root, VERIFIER)
    checks = []

    def passed(name):
        checks.append({'name': name, 'passed': True})

    def refuses(name, work):
        try:
            work()
        except AssertionError:
            passed(name)
        else:
            raise AssertionError(name)

    passed('Exact84 finance parent tree and ancestry')
    passed('Exact bounded application, fixture, proof and evidence scope')
    passed('Canonical current installer inventory')
    passed('Preserves ' + str(retained) + ' historical fixture, builder, workflow, foundation and JSON receipt inputs')
    passed('Original native assertion bytes retained; final-payoff fixture has only the exact timing edit')
    passed('Original 138 plus 79 fresh reproduction checks retained in current passing report')
    passed('All 25 original authority failure/control checks retained in the current driver')
    passed('Complete 413-check native execution binds every captured file to exact current source')
    passed('All 292 payoff, 232 replacement-receipt and 25 pending checks retained at exact frozen04 compatibility source')
    passed('Frozen04 compatibility is source comparison only with the exact reviewed two-line initial guard inverse')
    passed('All 358 initial receipt checks execute exact current source; four historical-result entries are explicit')
    passed('Independent unchanged 11-check initial-admission witness fails twice before and passes on current subscription')
    passed('Current runtime entry invokes recovery and authority cases without workflow changes')
    for path in sorted(BEFORE):
        current = ordinary_bytes(root, path)
        passed('Exact reversible finance predecessor ' + path)
        refuses('Finance proof rejects altered current source ' + path,
                lambda: reconstruct(data, path, current + b'\n# drift\n'))
        changed = copy.deepcopy(data)
        changed['files'][path]['before_sha256'] = '0' * 64
        refuses('Finance proof rejects changed predecessor identity ' + path,
                lambda: validate_data(changed, verifier))
        changed = copy.deepcopy(data)
        changed['files'][path]['edits'].pop()
        refuses('Finance proof rejects omitted inverse ' + path,
                lambda: reconstruct(changed, path, current))
        changed = copy.deepcopy(data)
        changed['files'][path]['edits'][0][0] += '# altered original\n'
        refuses('Finance proof rejects forged original edit ' + path,
                lambda: reconstruct(changed, path, current))
    for path in sorted(ADDED):
        current = ordinary_bytes(root, path)
        passed('Exact added finance source or evidence ' + path)
        refuses('Finance proof rejects changed addition ' + path,
                lambda: validate_addition(path, current + b'\n# drift\n', set()))
    for case in ('parent', 'parent_tree', 'shipping', 'files', 'additions', 'verifier'):
        changed = copy.deepcopy(data)
        if case in ('parent', 'parent_tree'):
            changed[case] = '0' * 40
        elif case == 'shipping':
            changed[case].append('site/app/unreviewed.php')
        elif case == 'files':
            changed[case].pop(next(iter(BEFORE)))
        elif case == 'additions':
            changed[case]['tests/unreviewed.php'] = '0' * 64
        else:
            changed['verifier_sha256'] = '0' * 64
        refuses('Finance proof rejects changed receipt ' + case, lambda: validate_data(changed, verifier))
    refuses('Finance proof rejects altered verifier', lambda: validate_data(data, verifier + b'\n# drift\n'))
    with tempfile.TemporaryDirectory(prefix='cwp-finance-proof-mode-') as directory:
        temporary = Path(directory)
        for path in (VERIFIER, RECEIPT):
            target = temporary / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(b'isolated source-mode negative fixture\n')
            target.chmod(0o644)
        proof_modes(temporary)
        passed('Finance proof and receipt accept regular nonexecutable source modes')
        for path in (VERIFIER, RECEIPT):
            target = temporary / path
            target.chmod(0o755)
            refuses('Finance proof rejects actual executable file mode ' + path,
                    lambda: proof_modes(temporary))
            target.chmod(0o644)
    refuses('Finance proof rejects nonancestor source', lambda: validate_parent(PARENT_TREE, False))
    refuses('Finance proof rejects a wrong parent tree', lambda: validate_parent('0' * 40, True))
    complete = set(BEFORE) | set(ADDED) | {VERIFIER, RECEIPT}
    refuses('Finance proof rejects unreviewed additional files', lambda: validate_scope(complete | {'site/unreviewed.php'}, []))
    refuses('Finance proof rejects missing files', lambda: validate_scope(complete - {INVENTORY}, []))
    refuses('Finance proof rejects hidden shipping additions', lambda: validate_scope(complete, ['site/hidden.php']))
    refuses('Finance proof rejects noncanonical inventory', lambda: validate_inventory(b'changed', ordinary_bytes(root, INVENTORY)))
    return checks


if __name__ == '__main__':
    checks = validate(Path(__file__).resolve().parents[1])
    print(json.dumps({'passed': True, 'count': len(checks), 'checks': checks,
                      'scope': 'Exact current source and evidence identity, reversible historical comparisons, original assertion retention and negative proof cases. Runtime commands execute current source; this source proof performs no SQL, provider or browser work.'}, indent=2))
