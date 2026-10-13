# Historical upgrade fixture

`CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip` is the previously verified original distribution retained strictly for upgrade acceptance. SHA-256: `13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf`. It contains no installed configuration, credentials or customer data. It is excluded from all deployment packages.

`tests/prepare_legacy.py` checks the exact hash and safe archive inventory, extracts under ignored `test-runtime/legacy/`, and reads the original PHP schema. Native tests populate that real schema with synthetic data. HTTP tests overlay the current UPDATE ZIP on the original application fixture and use the authenticated Version & database action, asserting preservation and repeatability. The historical archive is not offered as the current release.

The 1.2.0 suite also downloads the immutable published v1.1.0 complete release into ignored `test-runtime/`, verifies SHA-256 `52da8a009ace9643cf65d678ca2de6d9612b01a24be4acd2634668d5de8423cd`, validates archive paths, and uses its original schema/files for populated account-upgrade acceptance. It is not another ordinary-Git release copy. Synthetic configuration and private fixtures stay outside deployment assets.
