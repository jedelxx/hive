<?php
session_start();
require_once 'db_connect.php';
require_once 'turnstile_verify.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cf_token = $_POST['cf-turnstile-response'] ?? '';
    if (!verify_turnstile($cf_token)) {
        $error = "Security verification failed. Please complete the Cloudflare check and try again.";
    }else{
        $login_input = trim($_POST['login_input']); // Accepts username OR email
        $password    = $_POST['password'];

        $stmt = $conn->prepare("
            SELECT u.user_id, u.user_code, u.username, u.first_name, u.password_hash, u.account_status, ut.user_type
            FROM user u
            JOIN user_type ut ON u.user_code = ut.user_code
            WHERE u.username = ? OR u.email = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Check password (supports bcrypt hashes AND plain-text seed accounts)
            $is_valid = password_verify($password, $user['password_hash']) || ($password === $user['password_hash']);

            if ($is_valid) {
                if ($user['account_status'] === 'Banned') {
                    $error = "Your account has been permanently banned.";
                } elseif ($user['account_status'] === 'Suspended') {
                    $error = "Your account is currently suspended.";
                } else {
                    // Store session variables
                    $_SESSION['user_id']    = $user['user_id'];
                    $_SESSION['user_code']  = $user['user_code'];
                    $_SESSION['user_type']  = $user['user_type']; // 'Seeker' or 'Keeper'
                    $_SESSION['username']   = $user['username'];
                    $_SESSION['first_name'] = $user['first_name'];

                    if ($user['user_code'] == 3) {
                        header("Location: admin_dashboard.php");
                    } else {
                        header("Location: hives_hub.php");
                    }
                    exit();
                }
            } else {
                $error = "Invalid username/email or password.";
            }
        } else {
            $error = "Invalid username/email or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - Login</title>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 60px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #f59e0b; border: none; color: #fff; font-weight: bold; cursor: pointer; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h2>Sign In to Hive</h2>
    <?php if (isset($_GET['registered'])): ?>
        <p class="success">Registration successful! Please log in.</p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Username or Email</label>
        <input type="text" name="login_input" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(CF_TURNSTILE_SITE_KEY) ?>" style="margin-top: 14px;"></div>
        <button type="submit">Log In</button>
    </form>
    <p>Don't have an account? <a href="register.php">Sign up here</a></p>
</body>
</html>