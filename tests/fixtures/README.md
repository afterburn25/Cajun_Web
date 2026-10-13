# Historical upgrade fixture

`CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip` is the previously verified original distribution retained strictly for upgrade acceptance. SHA-256: `13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf`. It contains no installed configuration, credentials or customer data. It is excluded from all deployment packages.

`tests/prepare_legacy.py` checks the exact hash and safe archive inventory, extracts under ignored `test-runtime/legacy/`, and reads the original PHP schema. Native tests populate that real schema with synthetic data. HTTP tests overlay the current UPDATE ZIP on the original application fixture and use the authenticated Version & database action, asserting preservation and repeatability. The historical archive is not offered as the current release.
