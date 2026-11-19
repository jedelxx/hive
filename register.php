<?php
session_start();
require_once 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_code  = (int) $_POST['user_code']; // 1 = Seeker, 2 = Keeper
    $username   = trim($_POST['username']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $contact_no = trim($_POST['contact_no']);
    $sex        = $_POST['sex'];
    $id_type    = !empty($_POST['id_type']) ? trim($_POST['id_type']) : null;
    $id_doc_url = null;

    // Validate Keeper ID upload requirement
    if ($user_code === 2) {
        if (empty($id_type) || empty($_FILES['id_document']['name'])) {
            $message = "Keepers must select an ID type and upload a valid ID image.";
        } else {
            $upload_dir = "uploads/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

            if (!in_array($ext, $allowed)) {
                $message = "Invalid file type. Only JPG, PNG, or PDF allowed.";
            } else {
                $filename = uniqid("id_", true) . "." . $ext;
                $target_file = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['id_document']['tmp_name'], $target_file)) {
                    $id_doc_url = $target_file;
                } else {
                    $message = "Failed to upload ID file.";
                }
            }
        }
    }

    if (empty($message)) {
        // Hash password securely
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - Sign Up</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 450px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #f59e0b; border: none; color: #fff; font-weight: bold; cursor: pointer; }
        .error { color: red; margin-bottom: 10px; }
        #keeperFields { display: none; background: #fffbeb; padding: 10px; margin-top: 12px; border: 1px solid #fde68a; border-radius: 4px; }
    </style>
</head>
<body>
    <h2>Create a Hive Account</h2>
    <?php if ($message): ?><p class="error"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Account Type</label>
        <select name="user_code" id="userCodeSelect" required onchange="toggleKeeperFields()">
            <option value="1">Seeker (Looking for a dorm/roommate)</option>
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

        <!-- Shown only when Keeper (2) is selected -->
        <div id="keeperFields">
            <label>Valid ID Type (Required for Keepers)</label>
            <select name="id_type">
                <option value="">-- Select ID Type --</option>
                <option value="PUP Student ID">PUP Student ID</option>
                <option value="National ID">Philippine National ID</option>
                <option value="Driver License">Driver's License</option>
                <option value="Passport">Passport</option>
            </select>

            <label>Upload ID Image / Document</label>
            <input type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf">
        </div>

        <button type="submit">Sign Up</button>
    </form>
    <p>Already have an account? <a href="login.php">Log in here</a></p>

    <script>
        function toggleKeeperFields() {
            const role = document.getElementById('userCodeSelect').value;
            document.getElementById('keeperFields').style.display = (role === '2') ? 'block' : 'none';
        }
    </script>
</body>
</html>