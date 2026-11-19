<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; }
        .badge { background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Welcome to Hive, <?= htmlspecialchars($_SESSION['first_name']) ?>!</h2>
    <p>Role: <span class="badge"><?= htmlspecialchars($_SESSION['user_type']) ?></span></p>

    <?php if ($_SESSION['user_code'] == 1): ?>
        <h3>Seeker Actions</h3>
        <ul>
            <li><a href="hives_hub.php">Browse Hives Hub (Listings & Filters)</a></li>
            <li><a href="beestie.php">Find a Beestie! (Roommate Profile)</a></li>
            <li><a href="chat.php">My Inquiries (Shared Chat)</a></li>
        </ul>
    <?php elseif ($_SESSION['user_code'] == 2): ?>
        <h3>Keeper Actions</h3>
        <ul>
            <li><a href="post_listing.php">Post a New Room Listing</a></li>
            <li><a href="my_listings.php">Manage My Listings</a></li>
            <li><a href="chat.php">Check Seeker Inquiries</a></li>
        </ul>
    <?php endif; ?>

    <p><a href="logout.php">Log Out</a></p>
</body>
</html>