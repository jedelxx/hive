<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle Profile Update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
        $message = "Failed to update profile.";
    }
    $stmt->close();
}

// Fetch current user details
$stmt = $conn->prepare("
    SELECT u.username, u.email, u.first_name, u.last_name, u.contact_no, u.sex, u.is_id_verified, ut.user_type
    FROM user u
    JOIN user_type ut ON u.user_code = ut.user_code
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
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
        .btn-logout { background: #ef4444; margin-top: 12px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; background: #fef3c7; color: #92400e; }
        .success { color: #065f46; background: #d1fae5; padding: 8px 12px; border-radius: 4px; font-size: 14px; }
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
        <?php if ($_SESSION['user_code'] == 2): ?>
            <span class="badge" style="background: <?= $user['is_id_verified'] ? '#d1fae5' : '#fee2e2' ?>; color: <?= $user['is_id_verified'] ? '#065f46' : '#991b1b' ?>;">
                <?= $user['is_id_verified'] ? 'ID Verified' : 'ID Pending Verification' ?>
            </span>
        <?php endif; ?>
    </p>

    <?php if ($message): ?>
        <p class="success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

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