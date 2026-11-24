<?php
session_start();
require_once 'db_connect.php';
require_once 'turnstile_verify.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cf_token = $_POST['cf-turnstile-response'] ?? '';
    if (!verify_turnstile($cf_token)) {
        $message = "Security verification failed. Please complete the Cloudflare check and try again.";
    } else {
        $user_code  = (int) $_POST['user_code']; // 1 = Seeker, 2 = Keeper
        $username   = trim($_POST['username']);
        $email      = trim($_POST['email']);
        $password   = $_POST['password'];
        $first_name = trim($_POST['first_name']);
        $last_name  = trim($_POST['last_name']);
        $contact_no = trim($_POST['contact_no']);
        $sex        = $_POST['sex'];
        $id_type    = null;
        $id_doc_url = null;

        // Validate document upload for both Seekers (PUP COR) and Keepers (Valid ID)
        if (in_array($user_code, [1, 2], true)) {
            if ($user_code === 2 && empty(trim($_POST['id_type'] ?? ''))) {
                $message = "Please select a valid ID type.";
            } elseif (!isset($_FILES['id_document']) || $_FILES['id_document']['error'] !== UPLOAD_ERR_OK) {
                $message = ($user_code === 1)
                    ? "Please upload your PUP Certificate of Registration to verify your student status."
                    : "Please upload a valid ID document.";
            } else {
                $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
                $ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_ext, true)) {
                    $message = "Invalid file format. Please upload JPG, PNG, or PDF.";
                } elseif ($_FILES['id_document']['size'] > 5 * 1024 * 1024) {
                    $message = "Uploaded document exceeds the 5MB limit.";
                } else {
                    $upload_dir = 'uploads/ids/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $prefix = ($user_code === 1) ? 'cor_' : 'id_';
                    $filename = $prefix . uniqid() . '.' . $ext;
                    $target_path = $upload_dir . $filename;

                    if (move_uploaded_file($_FILES['id_document']['tmp_name'], $target_path)) {
                        $id_doc_url = $target_path;
                        $id_type = ($user_code === 1)
                            ? 'PUP Certificate of Registration (COR)'
                            : trim($_POST['id_type']);
                    } else {
                        $message = "Failed to save uploaded document.";
                    }
                }
            }
        }

        if (empty($message)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO user (user_code, username, email, password_hash, first_name, last_name, contact_no, sex, id_type, id_document_url, is_id_verified, account_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Active')
            ");
            $stmt->bind_param(
                "isssssssss",
                $user_code, $username, $email, $password_hash,
                $first_name, $last_name, $contact_no, $sex, $id_type, $id_doc_url
            );

            try {
                if ($stmt->execute()) {
                    header("Location: login.php?registered=1");
                    exit();
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $message = "Username or email is already taken.";
                } else {
                    $message = "Database error: " . $e->getMessage();
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - Sign Up</title>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 460px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        label { display: block; margin-top: 12px; font-weight: bold; font-size: 14px; }
        input, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { margin-top: 20px; width: 100%; padding: 11px; background: #f59e0b; border: none; color: #fff; font-weight: bold; font-size: 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #d97706; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 12px; font-size: 14px; }
        .verification-box { background: #fffbeb; padding: 12px; margin-top: 14px; border: 1px solid #fde68a; border-radius: 6px; }
        .hint { font-size: 12px; color: #6b7280; margin-top: 5px; line-height: 1.4; }
    </style>
</head>
<body>
    <h2>Create a Hive Account</h2>
    <?php if ($message): ?><div class="error"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Account Type</label>
        <select name="user_code" id="userCodeSelect" required onchange="toggleVerificationFields()">
            <option value="1">Seeker (Looking for a dorm / roommate)</option>
            <option value="2">Keeper (Lessor / Sharer posting a room)</option>
        </select>

        <label>First Name</label>
        <input type="text" name="first_name" required>

        <label>Last Name</label>
        <input type="text" name="last_name" required>

        <label>Username</label>
        <input type="text" name="username" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Contact Number</label>
        <input type="text" name="contact_no" placeholder="09XXXXXXXXX" required>

        <label>Sex</label>
        <select name="sex" required>
            <option value="Female">Female</option>
            <option value="Male">Male</option>
            <option value="Prefer not to say">Prefer not to say</option>
        </select>

        <label>Password</label>
        <input type="password" name="password" required>

        <!-- Dynamic Verification Section (Seeker COR vs. Keeper ID) -->
        <div class="verification-box">
            <div id="keeperIdTypeGroup" style="display: none;">
                <label style="margin-top: 0;">Valid ID Type (Required for Keepers)</label>
                <select name="id_type" id="idTypeSelect">
                    <option value="">-- Select ID Type --</option>
                    <option value="PUP Student ID">PUP Student ID</option>
                    <option value="National ID">Philippine National ID</option>
                    <option value="Driver License">Driver's License</option>
                    <option value="Passport">Passport</option>
                </select>
            </div>

            <label id="docUploadLabel" style="margin-top: 0;">Upload PUP Certificate of Registration (COR) *</label>
            <input type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" required>
            <div class="hint" id="docUploadHint">
                Accepted: JPG, PNG, or PDF (max 5MB). You can browse listings immediately after signing up, while messaging, reviewing, and reporting unlock once Admin verifies your COR.
            </div>
        </div>
        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(CF_TURNSTILE_SITE_KEY) ?>" style="margin-top: 14px;"></div>
        <button type="submit">Sign Up</button>
    </form>
    <p style="font-size: 14px; margin-top: 14px;">Already have an account? <a href="login.php">Log in here</a></p>

    <script>
        function toggleVerificationFields() {
            const role = document.getElementById('userCodeSelect').value;
            const keeperGroup = document.getElementById('keeperIdTypeGroup');
            const idTypeSelect = document.getElementById('idTypeSelect');
            const docLabel = document.getElementById('docUploadLabel');
            const docHint = document.getElementById('docUploadHint');

            if (role === '2') {
                keeperGroup.style.display = 'block';
                idTypeSelect.required = true;
                docLabel.style.marginTop = '12px';
                docLabel.textContent = 'Upload Valid ID Image / Document *';
                docHint.textContent = 'Accepted: JPG, PNG, or PDF (max 5MB). Your account must be verified by an Admin before you can publish room listings.';
            } else {
                keeperGroup.style.display = 'none';
                idTypeSelect.required = false;
                idTypeSelect.value = '';
                docLabel.style.marginTop = '0';
                docLabel.textContent = 'Upload PUP Certificate of Registration *';
                docHint.textContent = 'Accepted: JPG, PNG, or PDF (max 5MB). You can browse listings immediately after signing up, while messaging, reviewing, and reporting unlock once an administrator verifies your COR.';
            }
        }

        // Initialize state on page load
        toggleVerificationFields();
    </script>
</body>
</html>