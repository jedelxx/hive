<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = (int) $_SESSION['user_id'];
$user_code = (int) $_SESSION['user_code']; // 1 = Seeker, 2 = Keeper, 3 = Admin
$message   = "";
$error     = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['reupload_verification'])) {
        // Handle COR / Valid ID Re-Upload for Rejected Users
        $chk = $conn->prepare("SELECT is_id_verified, id_document_url FROM user WHERE user_id = ? LIMIT 1");
        $chk->bind_param("i", $user_id);
        $chk->execute();
        $curr = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$curr || (int)$curr['is_id_verified'] === 1) {
            $error = "Your account is already verified.";
        } elseif ($user_code === 2 && empty(trim($_POST['id_type'] ?? ''))) {
            $error = "Please select a valid ID type.";
        } elseif (!isset($_FILES['id_document']) || $_FILES['id_document']['error'] !== UPLOAD_ERR_OK) {
            $error = ($user_code === 1)
                ? "Please select a valid PUP Certificate of Registration (COR) file to upload."
                : "Please select a valid ID document to upload.";
        } else {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
            $ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_ext, true)) {
                $error = "Invalid file format. Please upload JPG, PNG, or PDF.";
            } elseif ($_FILES['id_document']['size'] > 5 * 1024 * 1024) {
                $error = "Uploaded document exceeds the 5MB limit.";
            } else {
                $upload_dir = 'uploads/ids/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $prefix      = ($user_code === 1) ? 'cor_' : 'id_';
                $filename    = $prefix . uniqid() . '.' . $ext;
                $target_path = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['id_document']['tmp_name'], $target_path)) {
                    // Delete old rejected file if present
                    if (!empty($curr['id_document_url']) && file_exists($curr['id_document_url'])) {
                        @unlink($curr['id_document_url']);
                    }

                    $new_id_type = ($user_code === 1)
                        ? 'PUP Certificate of Registration (COR)'
                        : trim($_POST['id_type']);

                    // Reset is_id_verified back to 0 (Pending) so Admin can review again
                    $upd = $conn->prepare("
                        UPDATE user 
                        SET id_type = ?, id_document_url = ?, is_id_verified = 0 
                        WHERE user_id = ?
                    ");
                    $upd->bind_param("ssi", $new_id_type, $target_path, $user_id);

                    if ($upd->execute()) {
                        $message = "Document re-uploaded! Your verification is now Pending Admin Review.";
                    } else {
                        $error = "Database error while updating verification document.";
                    }
                    $upd->close();
                } else {
                    $error = "Failed to save uploaded document.";
                }
            }
        }
    } else {
        // Handle Profile Update
        $first_name = trim($_POST['first_name']);
        $last_name  = trim($_POST['last_name']);
        $contact_no = trim($_POST['contact_no']);
        $sex        = $_POST['sex'];

        $stmt = $conn->prepare("UPDATE user SET first_name = ?, last_name = ?, contact_no = ?, sex = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $contact_no, $sex, $user_id);

        if ($stmt->execute()) {
            $_SESSION['first_name'] = $first_name;
            $message = "Profile updated successfully.";
        } else {
            $error = "Failed to update profile.";
        }
        $stmt->close();
    }
}

// Fetch current user details (including id_type and id_document_url)
$stmt = $conn->prepare("
    SELECT u.username, u.email, u.first_name, u.last_name, u.contact_no, u.sex, 
           u.id_type, u.id_document_url, u.is_id_verified, ut.user_type
    FROM user u
    JOIN user_type ut ON u.user_code = ut.user_code
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$verif_status = (int) $user['is_id_verified']; // 1 = Verified, 0 = Pending, 2/-1 = Rejected
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - Account Settings</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9fafb; margin: 0; padding: 20px; color: #1f2937; }
        .container { max-width: 500px; margin: 30px auto; background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e5e7eb; }
        label { display: block; font-weight: bold; margin-top: 12px; font-size: 14px; }
        input, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 4px; }
        input[disabled] { background: #f3f4f6; color: #6b7280; }
        .btn { display: inline-block; width: 100%; padding: 10px; margin-top: 18px; background: #f59e0b; color: #fff; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-align: center; text-decoration: none; box-sizing: border-box; }
        .btn:hover { background: #d97706; }
        .btn-logout { background: #ef4444; margin-top: 12px; }
        .btn-logout:hover { background: #dc2626; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; background: #fef3c7; color: #92400e; }
        .success { color: #065f46; background: #d1fae5; padding: 8px 12px; border-radius: 4px; font-size: 14px; }
        .error { color: #991b1b; background: #fee2e2; padding: 8px 12px; border-radius: 4px; font-size: 14px; }
        .verif-card { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 14px; margin-top: 16px; }
        .verif-card.rejected { background: #fef2f2; border-color: #fecaca; }
        .verif-card.verified { background: #f0fdf4; border-color: #bbf7d0; }
        .hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2 style="margin:0;">Account Settings</h2>
        <a href="hives_hub.php" style="font-size:14px; color:#d97706; text-decoration:none;">← Back to Hives Hub</a>
    </div>

    <p>
        Role: <span class="badge"><?= htmlspecialchars($user['user_type']) ?></span>
        <?php if (in_array($user_code, [1, 2], true)): ?>
            <?php
                $doc_short = ($user_code === 1) ? 'COR' : 'ID';
                $has_document = !empty($user['id_document_url']);
                
                if ($verif_status === 1) {
                    $badge_bg = '#d1fae5'; $badge_col = '#065f46'; $badge_txt = "$doc_short Verified";
                } elseif ($verif_status === 0 && $has_document) {
                    $badge_bg = '#fef3c7'; $badge_col = '#92400e'; $badge_txt = "$doc_short Pending Review";
                } else {
                    $badge_bg = '#fee2e2'; $badge_col = '#991b1b'; $badge_txt = "$doc_short Rejected";
                }
            ?>
            <span class="badge" style="background: <?= $badge_bg ?>; color: <?= $badge_col ?>;">
                <?= htmlspecialchars($badge_txt) ?>
            </span>
        <?php endif; ?>
    </p>

    <?php if ($message): ?>
        <p class="success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- Verification Status & Re-Upload Section (Seekers & Keepers) -->
    <?php if (in_array($user_code, [1, 2], true)): ?>
        <?php
            $is_seeker    = ($user_code === 1);
            $doc_label    = $is_seeker ? 'PUP Certificate of Registration (COR)' : 'Valid ID Document';
            $has_document = !empty($user['id_document_url']);
            $card_cls     = ($verif_status === 1) ? 'verified' : (($verif_status === 0 && $has_document) ? '' : 'rejected');
        ?>
        <div class="verif-card <?= $card_cls ?>">
            <?php if ($verif_status === 1): ?>
                <strong style="color: #065f46; font-size: 14px;">✓ <?= htmlspecialchars($doc_label) ?> Verified</strong>
                <p style="font-size: 13px; color: #374151; margin: 4px 0 0;">
                    Your identity is confirmed. All Hive features are unlocked.
                </p>

            <?php elseif ($verif_status === 0 && $has_document): ?>
                <strong style="color: #92400e; font-size: 14px;">⏳ <?= htmlspecialchars($doc_label) ?> Under Review</strong>
                <p style="font-size: 13px; color: #4b5563; margin: 4px 0 0;">
                    Your submitted document is waiting for administrator approval.
                    <a href="<?= htmlspecialchars($user['id_document_url']) ?>" target="_blank" style="color: #d97706;">View file</a>
                </p>

            <?php else: ?>
                <strong style="color: #991b1b; font-size: 14px;">✕ <?= htmlspecialchars($doc_label) ?> Rejected</strong>
                <p style="font-size: 13px; color: #7f1d1d; margin: 4px 0 10px;">
                    Your previous submission could not be verified (blurry image, mismatched details, or invalid document). Please upload a clear copy below.
                </p>
                <form method="POST" enctype="multipart/form-data">
                    <?php if (!$is_seeker): ?>
                        <label style="margin-top: 0;">Valid ID Type *</label>
                        <select name="id_type" required>
                            <option value="">-- Select ID Type --</option>
                            <option value="PUP Student ID">PUP Student ID</option>
                            <option value="National ID">Philippine National ID</option>
                            <option value="Driver License">Driver's License</option>
                            <option value="Passport">Passport</option>
                        </select>
                    <?php endif; ?>

                    <label style="margin-top: <?= $is_seeker ? '0' : '10px' ?>;">Re-Upload <?= htmlspecialchars($doc_label) ?> *</label>
                    <input type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" required>
                    <div class="hint">Accepted: JPG, PNG, or PDF (max 5MB).</div>

                    <button type="submit" name="reupload_verification" class="btn" style="margin-top: 12px;">
                        Submit New Document
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Profile Details Form -->
    <form method="POST">
        <label>Username</label>
        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>

        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>

        <label>First Name</label>
        <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>

        <label>Last Name</label>
        <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>

        <label>Contact Number</label>
        <input type="text" name="contact_no" value="<?= htmlspecialchars($user['contact_no']) ?>" required>

        <label>Sex</label>
        <select name="sex">
            <option value="Female" <?= ($user['sex'] === 'Female') ? 'selected' : '' ?>>Female</option>
            <option value="Male" <?= ($user['sex'] === 'Male') ? 'selected' : '' ?>>Male</option>
            <option value="Prefer not to say" <?= ($user['sex'] === 'Prefer not to say') ? 'selected' : '' ?>>Prefer not to say</option>
        </select>

        <button type="submit" class="btn">Save Changes</button>
    </form>

    <hr style="margin: 24px 0; border: none; border-top: 1px solid #e5e7eb;">

    <a href="logout.php" class="btn btn-logout">Log Out</a>
</div>

</body>
</html>