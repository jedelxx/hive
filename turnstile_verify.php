<?php
if (file_exists(__DIR__ . '/config_secrets.php')) {
    require_once __DIR__ . '/config_secrets.php';
} elseif (file_exists(__DIR__ . '/config_secrets.example.php')) {
    require_once __DIR__ . '/config_secrets.example.php';
} else {
    define('CF_TURNSTILE_SITE_KEY', '1x00000000000000000000AA');
    define('CF_TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA');
}

function verify_turnstile(string $token): bool {
    if (empty($token)) {
        return false;
    }

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret'   => CF_TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return false;
    }

    $result = json_decode($response, true);
    return isset($result['success']) && $result['success'] === true;
}