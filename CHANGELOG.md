# Changelog

All notable features, fixes, and architectural updates for **Hive** are documented in this file.

## [Unreleased]
* Migration of property listing photo uploads and tenant review proof uploads to Cloudflare R2.
* In-app messaging (`chat.php`) between Seekers and Keepers.
* Roommate finder and compatibility matching module.

---
## [v0.1.1.1] - 2026-11-29
### Added
* **Cloudflare R2 Storage Helper (`r2_storage.php`):** Built a centralized S3-compatible storage layer (`getR2Client`, `uploadToR2`, `getR2SignedUrl`, `deleteFromR2`) supporting private bucket uploads, 15-minute temporary presigned view URLs, automated object deletion, and backward compatibility with legacy local `uploads/` paths.
* **Binary MIME Type Verification:** Added `finfo_file()` signature inspection in `register.php` and `settings.php` to verify uploaded JPG, PNG, and PDF documents prior to cloud upload.

### Changed
* **Registration & Account Settings Uploads (`register.php`, `settings.php`):** Migrated Seeker PUP COR and Keeper valid ID uploads/re-uploads from local XAMPP disk storage (`uploads/ids/`) to the private Cloudflare R2 `hive-uploads` bucket, and wrapped user document preview links in `getR2SignedUrl()`.* **Admin Moderation & Cleanup (`admin_dashboard.php`):** Wrapped pending verification document links (`id_document_url`) and review proof links (`proof_document_url`) in temporary 15-minute presigned R2 URLs, and added automated `deleteFromR2()` cleanup when rejecting submitted documents or rolling back failed database writes.

## [v0.1.1] - 2026-11-29
### Added 
* **Dependency Management (Composer):** Initialized `composer.json` and `composer.lock` and installed `aws/aws-sdk-php` in preparation for Cloudflare R2 object storage integration.* **Cloudflare R2 Secret Template:** Added placeholder R2 configuration constants (`R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET_NAME`, `R2_ENDPOINT`) to `config_secrets.example.php`.

## [v0.1.0] - 2026-11-28 (Baseline Snapshot)
### Added
* **Authentication & Bot Protection:** Built `login.php`, `register.php`, and `turnstile_verify.php` integrating Cloudflare Turnstile siteverify via native PHP `cURL`.
* **Secrets Management:** Added `config_secrets.example.php` and updated `.gitignore` to keep local API keys (`config_secrets.php`) out of version control.
* **Admin Moderation Dashboard (`admin_dashboard.php`):**
  * Dashboard overview metrics (Active Users, Pending Verifications, Available Listings, Pending Reports).
  * Verification queue for approving or rejecting Seeker PUP CORs and Keeper Valid IDs.
  * Tenant review proof verification queue (`Verified` vs. `Unverified` badges).
  * Listing fraud report resolution (`Confirmed Fraud` auto-takedown and Keeper ban vs. `Dismissed`).
  * User account status controls (`Active`, `Suspended`, `Banned`).
* **Account Settings & Document Re-Upload (`settings.php`):**
  * Added profile editing (`first_name`, `last_name`, `contact_no`, `sex`).
  * Added role and document verification status badges (`Verified`, `Pending Review`, `Rejected`).
  * Added file re-upload form (`JPG`, `PNG`, `PDF` up to 5MB) for rejected Seekers and Keepers that cleans up old files and re-queues accounts for Admin review.

### Changed
* **Git Ignore Rules (`.gitignore`):** Excluded `vendor/`, `node_modules/`, and `config_secrets.php` from version control to keep third-party packages and local API credentials out of the repository.

### Fixed
* Enabled `extension=zip` in XAMPP `php.ini` so Composer can extract archive distributions locally.
* Synchronized rejected document detection between `admin_dashboard.php` (`is_id_verified = 0`, `id_document_url = NULL`) and `settings.php` so rejected users immediately see the re-upload form instead of a stuck pending status.
* Configured Windows XAMPP Apache environment path so PHP `cURL` loads reliably for Cloudflare Turnstile verification.