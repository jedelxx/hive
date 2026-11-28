# Changelog

All notable features, fixes, and architectural updates for **Hive** are documented in this file.

## [Unreleased]
* In-app messaging (`chat.php`) between Seekers and Keepers.
* Roommate finder and compatibility matching module.

---

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

### Fixed
* Synchronized rejected document detection between `admin_dashboard.php` (`is_id_verified = 0`, `id_document_url = NULL`) and `settings.php` so rejected users immediately see the re-upload form instead of a stuck pending status.
* Configured Windows XAMPP Apache environment path so PHP `cURL` loads reliably for Cloudflare Turnstile verification.