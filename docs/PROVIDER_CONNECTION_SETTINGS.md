# Owner 20i connection settings

Released in [1.20.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.20.0) after exact-main acceptance and verified public downloads. This establishes the bounded workflow below; target-host and real-account adapter acceptance remain separate.

## Configure

Sign in with a current owner account and open **20i connection**. Existing installations first prepare the additional storage after a database backup and current-password confirmation. Fresh installs prepare empty storage. Save a connection name and the raw General API key from20i; do not pre-encode it. Leave the replacement field blank to retain a saved key. Enable explicit checks when ready. Saving does not contact20i.

Only current owners can configure, inspect or check this account. Administrator permissions, client identity and client-support context do not grant access. Passwords and keys are never redisplayed, including after errors; reenter changed values before resubmitting. Stale forms retain their old revision and require reloading current settings.

## Check and interpret history

**Check saved20i connection** requires your current owner password and makes one fixed HTTPS GET/package request. There is no automatic polling or retry. Wait30 seconds between profile checks; each owner may start20 per hour. Public DNS, original-host certificate verification, no proxies/redirects, bounded response parsing and transfer deadlines protect the request. The hosting resolver owns its separate DNS timeout.

A verified read means this saved credential and revision received an accepted successful package-list response at that time. It does not verify item fields, account entitlements or any other operation. The connection check itself does not persist or display inventory. Inventory refresh, domain search, mailbox creation, hosting preparation and cloud website controls have their own permissions and reviews; a successful connection check does not approve them. The current capability summary distinguishes these bounded controls from automatic plan discovery, broader hosting/domain/VPS lifecycle, restoration and SSH work that remains unfinished.

History retains time, actor, saved revision and finite outcome. Older revisions are marked historical. Timeout/invalid response/access changes are uncertain; a process that fails before its terminal result is recorded remains incomplete. Neither state is displayed as a verified connection. Recheck current settings explicitly after resolving the cause.

## Credentials and recovery

The profile key is encrypted using a separate private32-byte provider-master.key outside the served root. Back up the database and that original key together with the other application private files. Losing or changing it does not silently recreate working credentials. Disable preserves the saved key; clear removes the encrypted General key and disables checks while retaining profile/history. Restoring the original database and key together is the recovery path for key loss. Package building refuses provider-master.key in shipping source.

## Verification scope

PHP 8.2/8.4 matrices, packaged HTTP/Chromium, Apache and actual 1.19.0 update/recovery/reapplication pass at `6f0b0ce5a9cba68b6859334818383fcc09e2ba3e`. Both original private keys and nonempty provider history are retained. Tests use synthetic credentials and loopback HTTPS; they do not establish real-account or target-host compatibility. [Named report](TEST_REPORT_1.20.0.md). No new live call or deployment occurred.
