<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config_secrets.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Returns a singleton S3Client configured for Cloudflare R2.
 */
function getR2Client(): S3Client
{
    static $client = null;

    if ($client === null) {
        $client = new S3Client([
            'region'                  => 'auto',
            'version'                 => 'latest',
            'endpoint'                => R2_ENDPOINT,
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => R2_ACCESS_KEY_ID,
                'secret' => R2_SECRET_ACCESS_KEY,
            ],
        ]);
    }

    return $client;
}

/**
 * Uploads a local temporary file ($_FILES['...']['tmp_name']) to Cloudflare R2.
 *
 * @param string $tmpFilePath Path to the uploaded temporary file.
 * @param string $folder      Subfolder prefix inside the bucket (e.g., 'ids', 'listings', 'proofs').
 * @param string $extension   Safe lowercase file extension without dot (e.g., 'jpg', 'png', 'pdf').
 * @param string $mimeType    Detected MIME type (e.g., 'image/jpeg', 'application/pdf').
 * @return string|false       Returns the stored R2 object key (e.g., 'ids/cor_6749a1b2.pdf') on success, or false on failure.
 */
function uploadToR2(string $tmpFilePath, string $folder, string $extension, string $mimeType)
{
    if (!is_file($tmpFilePath)) {
        return false;
    }

    $folder = trim($folder, '/');
    $uniqueName = bin2hex(random_bytes(12)) . '.' . strtolower($extension);
    $objectKey = ($folder !== '' ? $folder . '/' : '') . $uniqueName;

    try {
        $client = getR2Client();
        $client->putObject([
            'Bucket'      => R2_BUCKET_NAME,
            'Key'         => $objectKey,
            'SourceFile'  => $tmpFilePath,
            'ContentType' => $mimeType,
        ]);

        return $objectKey;
    } catch (AwsException $e) {
        error_log('R2 Upload Error: ' . $e->getAwsErrorMessage());
        return false;
    }
}

/**
 * Generates a temporary, signed URL so private files (like Seeker CORs and Keeper IDs)
 * can be viewed securely in admin_dashboard.php and settings.php without making the bucket public.
 *
 * @param string|null $storedPath The R2 object key or legacy local path stored in the database.
 * @param string      $expiresIn  Expiration window (default '+15 minutes').
 * @return string|null            Signed URL, legacy local path, or null if empty.
 */
function getR2SignedUrl(?string $storedPath, string $expiresIn = '+15 minutes'): ?string
{
    if (empty($storedPath)) {
        return null;
    }

    // Backward compatibility: if the record still points to an old local XAMPP file, return it as-is
    if (strpos($storedPath, 'uploads/') === 0 || file_exists(__DIR__ . '/' . ltrim($storedPath, '/'))) {
        return $storedPath;
    }

    try {
        $client = getR2Client();
        $cmd = $client->getCommand('GetObject', [
            'Bucket' => R2_BUCKET_NAME,
            'Key'    => $storedPath,
        ]);

        $request = $client->createPresignedRequest($cmd, $expiresIn);
        return (string) $request->getUri();
    } catch (AwsException $e) {
        error_log('R2 Signed URL Error: ' . $e->getAwsErrorMessage());
        return null;
    }
}

/**
 * Deletes an object from Cloudflare R2 (or deletes a legacy local file if it starts with 'uploads/').
 *
 * @param string|null $storedPath The R2 object key or legacy local path to delete.
 * @return bool
 */
function deleteFromR2(?string $storedPath): bool
{
    if (empty($storedPath)) {
        return false;
    }

    // Clean up legacy local XAMPP files smoothly during transition
    if (strpos($storedPath, 'uploads/') === 0) {
        $localFullPath = __DIR__ . '/' . ltrim($storedPath, '/');
        return is_file($localFullPath) ? @unlink($localFullPath) : false;
    }

    try {
        $client = getR2Client();
        $client->deleteObject([
            'Bucket' => R2_BUCKET_NAME,
            'Key'    => $storedPath,
        ]);

        return true;
    } catch (AwsException $e) {
        error_log('R2 Delete Error: ' . $e->getAwsErrorMessage());
        return false;
    }
}