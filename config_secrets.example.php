<?php
// Copy this file to config_secrets.php and replace with your real Cloudflare Turnstile keys
define('CF_TURNSTILE_SITE_KEY', '1x00000000000000000000AA');
define('CF_TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA');

// Cloudflare R2 Storage Credentials
define('R2_ACCOUNT_ID', 'your_cloudflare_account_id');
define('R2_ACCESS_KEY_ID', 'your_r2_access_key_id');
define('R2_SECRET_ACCESS_KEY', 'your_r2_secret_access_key');
define('R2_BUCKET_NAME', 'hive-uploads');
define('R2_ENDPOINT', 'https://your_cloudflare_account_id.r2.cloudflarestorage.com');