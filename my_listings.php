<?php
session_start();
require_once 'db_connect.php';

// 1. Restrict access strictly to Keepers (user_code = 2)
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_code'] !== 2) {
    header("Location: login.php");
    exit();
}

$keeper_id = (int) $_SESSION['user_id'];
$message   = "";
$error     = "";

// 2. HANDLE POST ACTIONS (Quick Status Toggle or Edit Listing)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action     = $_POST['action'] ?? '';
    $listing_id = (int) ($_POST['listing_id'] ?? 0);

    // Verify ownership before making any changes
    $own_stmt = $conn->prepare("SELECT status FROM listing WHERE listing_id = ? AND keeper_id = ?");
    $own_stmt->bind_param("ii", $listing_id, $keeper_id);
    $own_stmt->execute();
    $owned_listing = $own_stmt->get_result()->fetch_assoc();
    $own_stmt->close();

    if (!$owned_listing) {
        $error = "Listing not found or you do not have permission to modify it.";
    } elseif ($owned_listing['status'] === 'Taken Down') {
        $error = "This listing was taken down by an Admin and cannot be modified.";
    } else {
        // ACTION A: Quick Status Update ('Available', 'Occupied', 'Removed')
        if ($action === 'update_status') {
            $new_status      = trim($_POST['new_status'] ?? '');
            $allowed_statuses = ['Available', 'Occupied', 'Removed'];

            if (!in_array($new_status, $allowed_statuses, true)) {
                $error = "Invalid listing status selected.";
            } else {
                $upd_stmt = $conn->prepare("UPDATE listing SET status = ? WHERE listing_id = ? AND keeper_id = ?");
                $upd_stmt->bind_param("sii", $new_status, $listing_id, $keeper_id);
                if ($upd_stmt->execute()) {
                    $message = "Listing status updated to '{$new_status}'.";
                } else {
                    $error = "Failed to update listing status.";
                }
                $upd_stmt->close();
            }
        }

        // ACTION B: Edit Listing Details
        if ($action === 'edit_listing') {
            $title                = trim($_POST['title'] ?? '');
            $purpose              = trim($_POST['purpose'] ?? 'Lessor');
            $furnish              = trim($_POST['furnish'] ?? 'Semi-furnished');
            $monthly_rent         = (float) ($_POST['monthly_rent'] ?? 0);
            $assoc_dues           = (float) ($_POST['assoc_dues'] ?? 0);
            $advance_months       = (int) ($_POST['advance_months'] ?? 1);
            $deposit_months       = (int) ($_POST['deposit_months'] ?? 1);
            $no_tenant            = (int) ($_POST['no_tenant'] ?? 1);
            $water_included       = isset($_POST['water_included']) ? 1 : 0;
            $electricity_included = isset($_POST['electricity_included']) ? 1 : 0;
            $wifi_included        = isset($_POST['wifi_included']) ? 1 : 0;
            $is_long_term         = isset($_POST['is_long_term']) ? 1 : 0;
            $description          = trim($_POST['description'] ?? '');

            $valid_purposes = ['Lessor', 'Sharer'];
            $valid_furnish  = ['Unfurnished', 'Semi-furnished', 'Furnished'];

            if ($title === '' || $monthly_rent <= 0 || $no_tenant < 1) {
                $error = "Title, Monthly Rent, and Max Tenants are required.";
            } elseif (!in_array($purpose, $valid_purposes, true) || !in_array($furnish, $valid_furnish, true)) {
                $error = "Invalid purpose or furnishing value.";
            } else {
                $edit_stmt = $conn->prepare("
                    UPDATE listing SET
                        title = ?,
                        purpose = ?,
                        furnish = ?,
                        monthly_rent = ?,
                        assoc_dues = ?,
                        advance_months = ?,
                        deposit_months = ?,
                        no_tenant = ?,
                        water_included = ?,
                        electricity_included = ?,
                        wifi_included = ?,
                        is_long_term = ?,
                        description = ?
                    WHERE listing_id = ? AND keeper_id = ?
                ");
                $edit_stmt->bind_param(
                    "sssddiiiiiiisii",
                    $title, $purpose, $furnish,
                    $monthly_rent, $assoc_dues,
                    $advance_months, $deposit_months, $no_tenant,
                    $water_included, $electricity_included, $wifi_included, $is_long_term,
                    $description, $listing_id, $keeper_id
                );

                if ($edit_stmt->execute()) {
                    $message = "Listing #{$listing_id} updated successfully.";
                } else {
                    $error = "Failed to save listing changes.";
                }
                $edit_stmt->close();
            }
        }
    }
}

// 3. FETCH ALL LISTINGS OWNED BY THIS KEEPER (with Property, Primary Photo, & Review Stats)
$sql = "
    SELECT 
        l.*,
        p.name AS property_name,
        p.address AS property_address,
        ht.housing_name,
        (SELECT lp.image_url FROM listing_photo lp WHERE lp.listing_id = l.listing_id ORDER BY lp.is_primary DESC, lp.photo_id ASC LIMIT 1) AS cover_photo,
        (SELECT COUNT(*) FROM review r WHERE r.listing_id = l.listing_id AND r.verification_status = 'Verified') AS verified_review_count,
        (SELECT ROUND(AVG(r.rating), 1) FROM review r WHERE r.listing_id = l.listing_id AND r.verification_status = 'Verified') AS avg_rating
    FROM listing l
    JOIN property p ON l.property_id = p.property_id
    JOIN housing_type ht ON p.type_id = ht.type_id
    WHERE l.keeper_id = ?
    ORDER BY l.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $keeper_id);
$stmt->execute();
$my_listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if Keeper clicked "Edit" on a specific listing (?edit=ID)
$edit_id      = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing_item = null;
if ($edit_id > 0) {
    foreach ($my_listings as $item) {
        if ((int)$item['listing_id'] === $edit_id && $item['status'] !== 'Taken Down') {
            $editing_item = $item;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - My Listings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f3f4f6; color: #1f2937; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: #111827; color: #fff; padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; }
        .topbar a { color: #fbbf24; text-decoration: none; margin-left: 14px; font-size: 14px; }
        .container { max-width: 1050px; margin: 0 auto; }
        .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .btn-primary { background: #d97706; color: #fff; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px; border: none; cursor: pointer; }
        .btn-primary:hover { background: #b45309; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        
        /* Listing Cards */
        .listing-card { display: grid; grid-template-columns: 220px 1fr 230px; gap: 18px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; align-items: center; }
        .listing-card img { width: 220px; height: 150px; object-fit: cover; border-radius: 6px; background: #e5e7eb; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .status-Available { background: #d1fae5; color: #065f46; }
        .status-Occupied { background: #fef3c7; color: #92400e; }
        .status-Removed { background: #e5e7eb; color: #374151; }
        .status-Taken { background: #fee2e2; color: #991b1b; }
        .meta { font-size: 13px; color: #4b5563; margin: 6px 0; }
        .actions-col { display: flex; flex-direction: column; gap: 8px; border-left: 1px solid #e5e7eb; padding-left: 16px; }
        .actions-col select, .actions-col button, .actions-col a { width: 100%; padding: 8px; border-radius: 6px; font-size: 13px; text-align: center; box-sizing: border-box; }
        .btn-outline { border: 1px solid #d1d5db; background: #fff; color: #1f2937; text-decoration: none; font-weight: bold; }
        .btn-outline:hover { background: #f9fafb; }
        .btn-dark { background: #111827; color: #fff; border: none; cursor: pointer; font-weight: bold; }

        /* Edit Panel */
        .edit-panel { background: #fff; border: 2px solid #d97706; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        label { display: block; font-size: 13px; font-weight: bold; margin-top: 10px; margin-bottom: 4px; }
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; }
        .checkbox-row { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 12px; }
        .checkbox-row label { font-weight: normal; display: flex; align-items: center; gap: 6px; margin: 0; }
    </style>
</head>
<body>

<div class="topbar">
    <div><strong>HIVE KEEPER PORTAL</strong> — My Listings</div>
    <div>
        <a href="hives_hub.php">Hives Hub</a>
        <a href="post_listing.php">+ Post New Listing</a>
        <a href="chat.php">Messages</a>
        <a href="settings.php">Settings</a>
        <a href="logout.php">Log Out</a>
    </div>
</div>

<div class="container">
    <div class="header-row">
        <h2 style="margin:0;">My Property Listings (<?= count($my_listings) ?>)</h2>
        <a href="post_listing.php" class="btn-primary">+ Post a New Room</a>
    </div>

    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- EDIT FORM (Appears when ?edit=listing_id is clicked) -->
    <?php if ($editing_item): ?>
        <div class="edit-panel">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;">Editing: <?= htmlspecialchars($editing_item['title']) ?></h3>
                <a href="my_listings.php" style="color:#6b7280; font-size:13px;">Cancel Edit ✕</a>
            </div>
            <form method="POST" action="my_listings.php">
                <input type="hidden" name="action" value="edit_listing">
                <input type="hidden" name="listing_id" value="<?= $editing_item['listing_id'] ?>">

                <label>Listing Title *</label>
                <input type="text" name="title" value="<?= htmlspecialchars($editing_item['title']) ?>" required>

                <div class="grid-3">
                    <div>
                        <label>Purpose *</label>
                        <select name="purpose">
                            <option value="Lessor" <?= $editing_item['purpose'] === 'Lessor' ? 'selected' : '' ?>>Lessor</option>
                            <option value="Sharer" <?= $editing_item['purpose'] === 'Sharer' ? 'selected' : '' ?>>Sharer</option>
                        </select>
                    </div>
                    <div>
                        <label>Furnishing *</label>
                        <select name="furnish">
                            <option value="Unfurnished" <?= $editing_item['furnish'] === 'Unfurnished' ? 'selected' : '' ?>>Unfurnished</option>
                            <option value="Semi-furnished" <?= $editing_item['furnish'] === 'Semi-furnished' ? 'selected' : '' ?>>Semi-furnished</option>
                            <option value="Furnished" <?= $editing_item['furnish'] === 'Furnished' ? 'selected' : '' ?>>Furnished</option>
                        </select>
                    </div>
                    <div>
                        <label>Max Tenants *</label>
                        <input type="number" name="no_tenant" min="1" value="<?= (int)$editing_item['no_tenant'] ?>" required>
                    </div>
                </div>

                <div class="grid-2">
                    <div>
                        <label>Monthly Rent (₱) *</label>
                        <input type="number" name="monthly_rent" min="500" step="50" value="<?= htmlspecialchars($editing_item['monthly_rent']) ?>" required>
                    </div>
                    <div>
                        <label>Association Dues (₱)</label>
                        <input type="number" name="assoc_dues" min="0" step="50" value="<?= htmlspecialchars($editing_item['assoc_dues']) ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div>
                        <label>Advance Payment (Months)</label>
                        <input type="number" name="advance_months" min="0" max="12" value="<?= (int)$editing_item['advance_months'] ?>">
                    </div>
                    <div>
                        <label>Security Deposit (Months)</label>
                        <input type="number" name="deposit_months" min="0" max="12" value="<?= (int)$editing_item['deposit_months'] ?>">
                    </div>
                </div>

                <div class="checkbox-row">
                    <label><input type="checkbox" name="water_included" value="1" <?= $editing_item['water_included'] ? 'checked' : '' ?>> Water Included</label>
                    <label><input type="checkbox" name="electricity_included" value="1" <?= $editing_item['electricity_included'] ? 'checked' : '' ?>> Electricity Included</label>
                    <label><input type="checkbox" name="wifi_included" value="1" <?= $editing_item['wifi_included'] ? 'checked' : '' ?>> Wi-Fi Included</label>
                    <label><input type="checkbox" name="is_long_term" value="1" <?= $editing_item['is_long_term'] ? 'checked' : '' ?>> Long-Term Stay</label>
                </div>

                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($editing_item['description']) ?></textarea>

                <button type="submit" class="btn-primary" style="margin-top:16px;">Save Changes</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- LISTINGS FEED -->
    <?php if (empty($my_listings)): ?>
        <div style="background:#fff; padding:32px; text-align:center; border-radius:8px; border:1px solid #e5e7eb;">
            <p style="color:#4b5563; margin-bottom:16px;">You haven't posted any rooms or bedspaces yet.</p>
            <a href="post_listing.php" class="btn-primary">Create Your First Listing</a>
        </div>
    <?php else: ?>
        <?php foreach ($my_listings as $row): ?>
            <?php
                $photo = !empty($row['cover_photo']) ? $row['cover_photo'] : 'https://via.placeholder.com/220x150?text=No+Photo';
                $status_class = ($row['status'] === 'Taken Down') ? 'status-Taken' : 'status-' . $row['status'];
            ?>
            <div class="listing-card">
                <img src="<?= htmlspecialchars($photo) ?>" alt="Room Cover">

                <div>
                    <span class="badge <?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span>
                    <span style="font-size:12px; color:#6b7280; margin-left:6px;">
                        <?= htmlspecialchars($row['housing_name']) ?> • <?= htmlspecialchars($row['purpose']) ?> • <?= htmlspecialchars($row['furnish']) ?>
                    </span>

                    <h3 style="margin:6px 0 4px 0;"><?= htmlspecialchars($row['title']) ?></h3>
                    <div class="meta"><strong><?= htmlspecialchars($row['property_name']) ?></strong> — <?= htmlspecialchars($row['property_address']) ?></div>
                    <div style="font-size:16px; font-weight:bold; color:#d97706; margin:6px 0;">
                        ₱<?= number_format((float)$row['monthly_rent'], 2) ?> <span style="font-size:12px; font-weight:normal; color:#4b5563;">/ month</span>
                        <?php if ((float)$row['assoc_dues'] > 0): ?>
                            <span style="font-size:12px; font-weight:normal; color:#6b7280;">(+ ₱<?= number_format((float)$row['assoc_dues'], 2) ?> dues)</span>
                        <?php endif; ?>
                    </div>
                    <div class="meta">
                        Max Tenants: <strong><?= (int)$row['no_tenant'] ?></strong> • 
                        Rating: <strong><?= $row['avg_rating'] ? $row['avg_rating'] . ' ★ (' . $row['verified_review_count'] . ' verified)' : 'No verified reviews yet' ?></strong>
                    </div>
                </div>

                <div class="actions-col">
                    <a href="room_details.php?id=<?= $row['listing_id'] ?>" class="btn-outline">View Public Page</a>

                    <?php if ($row['status'] !== 'Taken Down'): ?>
                        <a href="my_listings.php?edit=<?= $row['listing_id'] ?>" class="btn-outline">Edit Details</a>

                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="listing_id" value="<?= $row['listing_id'] ?>">
                            <select name="new_status" style="margin-bottom:6px;">
                                <option value="Available" <?= $row['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                <option value="Occupied" <?= $row['status'] === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
                                <option value="Removed" <?= $row['status'] === 'Removed' ? 'selected' : '' ?>>Removed (Archive)</option>
                            </select>
                            <button type="submit" class="btn-dark">Update Status</button>
                        </form>
                    <?php else: ?>
                        <div style="font-size:12px; color:#991b1b; text-align:center;">
                            Locked by Admin moderation.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>