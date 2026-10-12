# Supplied application baseline — 1.0.8

Inspection date: 2026-10-12. This record describes an actual conversation attachment inspected locally during the repository documentation session. **It does not mean the source has been imported into Git or installed on the host.**

## Identity and current-session observations

Artifact: `CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip`.

Archive SHA-256:

```text
13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf
```

Archive size: 1,185,879 bytes. Python ZIP integrity check returned no bad member. The listing has 33 files directly at the archive root/subdirectories, with no enclosing version/upload folder. Source headers identify `CWP_VERSION = '1.0.8'`, `CWP_INSTALLER_BUILD = '1.0.8'`, and `CWP_INSTALLER_LAYOUT = 'one-page'`.

The original standalone website wordmark matches packaged `assets/logo.png` byte-for-byte. The requested attached favicon source has a separate recorded fingerprint. See `BRAND_ASSETS.md`.

## File inventory

Sizes are uncompressed bytes from the inspected ZIP.

| Path | Bytes |
|---|---:|
| `.htaccess` | 856 |
| `admin.php` | 53016 |
| `api.php` | 4024 |
| `app/.htaccess` | 19 |
| `app/admin-view.php` | 6498 |
| `app/core.php` | 19346 |
| `app/home.php` | 7301 |
| `app/index.php` | 37 |
| `app/mail.php` | 5547 |
| `app/schema.php` | 9966 |
| `app/view.php` | 12043 |
| `assets/admin.css` | 22697 |
| `assets/admin.js` | 552 |
| `assets/bayou-detail.webp` | 36926 |
| `assets/bayou.webp` | 33216 |
| `assets/emblem.png` | 100493 |
| `assets/favicon.png` | 6276 |
| `assets/hero-detail.webp` | 158166 |
| `assets/hero.webp` | 141910 |
| `assets/logo.png` | 353632 |
| `assets/micrograin.png` | 11794 |
| `assets/premium.css` | 35225 |
| `assets/project-construction.webp` | 20536 |
| `assets/project-placeholder.svg` | 532 |
| `assets/project-restaurant.webp` | 21968 |
| `assets/project-wellness.webp` | 19952 |
| `assets/site.css` | 33233 |
| `assets/site.js` | 8376 |
| `index.php` | 11385 |
| `install.php` | 321639 |
| `media.php` | 1202 |
| `robots.php` | 438 |
| `sitemap.php` | 867 |

`config.php`, private media/storage, installed markers and credentials are not distribution payload. Do not fabricate those files to satisfy a missing-file complaint. The installer's current inventory counts its 32 dependencies separately from the installer itself; regenerate it as source changes.

## Historical report — do not relabel as new evidence

The accompanying `CajunWebPro-1.0.8-Verification.md` reports prior local PHP HTTP/filesystem, offline browser, helper cleanup, package, and syntax checks. It explicitly describes mocked/recorded browser responses and missing native MySQL/GD/service acceptance. That report was read in this session; those test suites were not re-run here.

The current-session independent checks were archive/hash/listing inspection, selected source headers/schema declarations, and image identity/metadata. They do not establish complete application correctness, native database behavior, email delivery, real HTTPS browser installation, security certification, or production deployment.

## Recovery/import instructions

Recover this exact named archive from conversation attachments or the file Library and verify the hash before treating it as the baseline. Do not rely on an old sandbox path or the version in a filename alone. Import all application files together after checking for secrets, retain original asset bytes, and document any deliberate modifications separately.

The last host report of a 1.0.5 installer and root-owned storage remains a separate unresolved observation. Package correctness is not evidence of which entry point the host is executing.
