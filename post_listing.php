<?php
session_start();
require_once 'db_connect.php';

// 1. Restrict access strictly to Keepers (user_code = 2)
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_code'] !== 2) {
    header("Location: login.php");
    exit();
}

$keeper_id = (int) $_SESSION['user_id'];
$error     = "";

// Fetch Keeper verification & account status
$keeper_stmt = $conn->prepare("SELECT is_id_verified, account_status FROM user WHERE user_id = ?");
$keeper_stmt->bind_param("i", $keeper_id);
$keeper_stmt->execute();
$keeper_info = $keeper_stmt->get_result()->fetch_assoc();
$keeper_stmt->close();

// Fetch lookup tables directly from DB
$housing_types = $conn->query("SELECT type_id, housing_name FROM housing_type ORDER BY type_id ASC")->fetch_all(MYSQLI_ASSOC);
$inclusions    = $conn->query("SELECT inclusion_id, inclusion_name FROM inclusion ORDER BY inclusion_id ASC")->fetch_all(MYSQLI_ASSOC);
$house_rules   = $conn->query("SELECT rule_id, rule_name FROM house_rule ORDER BY rule_id ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch properties already linked to this Keeper's listings
$prop_stmt = $conn->prepare("
    SELECT DISTINCT p.property_id, p.name, p.address
    FROM property p
    JOIN listing l ON p.property_id = l.property_id
    WHERE l.keeper_id = ?
    ORDER BY p.property_id DESC
");
$prop_stmt->bind_param("i", $keeper_id);
$prop_stmt->execute();
$existing_properties = $prop_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$prop_stmt->close();

// 2. HANDLE FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$keeper_info || (int)$keeper_info['is_id_verified'] !== 1 || $keeper_info['account_status'] !== 'Active') {
        $error = "Your Keeper ID must be verified and your account Active before you can post listings.";
    } else {
        $property_mode = $_POST['property_mode'] ?? 'new';
        $property_id   = (int) ($_POST['existing_property_id'] ?? 0);

        // New property fields (matches `property`: type_id, name, address)
        $type_id       = (int) ($_POST['type_id'] ?? 0);
        $property_name = trim($_POST['property_name'] ?? '');
        $address       = trim($_POST['address'] ?? '');

        // Listing fields (matches `listing` columns)
        $title                = trim($_POST['title'] ?? '');
        $purpose              = trim($_POST['purpose'] ?? 'Lessor');
        $description          = trim($_POST['description'] ?? '');
        $monthly_rent         = (float) ($_POST['monthly_rent'] ?? 0);
        $assoc_dues           = (float) ($_POST['assoc_dues'] ?? 0);
        $advance_months       = (int) ($_POST['advance_months'] ?? 1);
        $deposit_months       = (int) ($_POST['deposit_months'] ?? 1);
        $water_included       = isset($_POST['water_included']) ? 1 : 0;
        $electricity_included = isset($_POST['electricity_included']) ? 1 : 0;
        $wifi_included        = isset($_POST['wifi_included']) ? 1 : 0;
        $no_tenant            = (int) ($_POST['no_tenant'] ?? 1);
        $is_long_term         = isset($_POST['is_long_term']) ? 1 : 0;
        $furnish              = trim($_POST['furnish'] ?? 'Semi-furnished');
        // Junction selections
        $selected_inclusions  = $_POST['inclusion_ids'] ?? []; // array of inclusion_id
        $selected_rules = $_POST['rule_ids'] ?? [];

        // Basic validation
        if ($title === '' || $monthly_rent <= 0 || $no_tenant < 1) {
            $error = "Please fill in Title, Monthly Rent, and Number of Tenants.";
        } elseif ($property_mode === 'new' && ($type_id <= 0 || $property_name === '' || $address === '')) {
            $error = "Please complete Housing Type, Property Name, and Address.";
        } elseif ($property_mode === 'existing' && $property_id <= 0) {
            $error = "Please select an existing property or add a new one.";
        } elseif (!isset($_FILES['room_photos']) || empty($_FILES['room_photos']['name'][0])) {
            $error = "Please upload at least one room photo.";
        } else {
            // Validate images before starting transaction
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $valid_files = [];
            $total_files = count($_FILES['room_photos']['name']);

            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['room_photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                if ($_FILES['room_photos']['error'][$i] !== UPLOAD_ERR_OK) {
                    $error = "Error uploading image #" . ($i + 1) . ".";
                    break;
                }
                $ext = strtolower(pathinfo($_FILES['room_photos']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    $error = "Invalid format for '{$_FILES['room_photos']['name'][$i]}'. Use JPG, PNG, or WEBP.";
                    break;
                }
                if ($_FILES['room_photos']['size'][$i] > 5 * 1024 * 1024) {
                    $error = "Image '{$_FILES['room_photos']['name'][$i]}' exceeds 5MB.";
                    break;
                }
                $valid_files[] = [
                    'tmp_name' => $_FILES['room_photos']['tmp_name'][$i],
                    'ext'      => $ext
                ];
            }

            if ($error === '' && empty($valid_files)) {
                $error = "Please select at least one valid room photo.";
            }

            // Execute Atomic DB Transaction
            if ($error === '') {
                $conn->begin_transaction();
                $saved_paths = [];

                try {
                    // Step A: Insert into `property` if new
                    if ($property_mode === 'new') {
                        $stmt_prop = $conn->prepare("
                            INSERT INTO property (type_id, name, address)
                            VALUES (?, ?, ?)
                        ");
                        $stmt_prop->bind_param("iss", $type_id, $property_name, $address);
                        $stmt_prop->execute();
                        $property_id = $conn->insert_id;
                        $stmt_prop->close();
                    }

                    // Step B: Insert into `listing`
                    $stmt_list = $conn->prepare("
                        INSERT INTO listing (
                            property_id, keeper_id, title, purpose, description,
                            monthly_rent, assoc_dues, advance_months, deposit_months,
                            water_included, electricity_included, wifi_included,
                            status, is_featured, no_tenant, is_long_term, furnish
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', 0, ?, ?, ?)
                    ");
                    $stmt_list->bind_param(
                        "iisssddiiiiiiis",
                        $property_id, $keeper_id, $title, $purpose, $description,
                        $monthly_rent, $assoc_dues, $advance_months, $deposit_months,
                        $water_included, $electricity_included, $wifi_included,
                        $no_tenant, $is_long_term, $furnish
                    );
                    $stmt_list->execute();
                    $listing_id = $conn->insert_id;
                    $stmt_list->close();

                    // Step C: Save photos into `listing_photo` (column: image_url)
                    $upload_dir = 'uploads/listings/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $stmt_photo = $conn->prepare("
                        INSERT INTO listing_photo (listing_id, image_url, is_primary)
                        VALUES (?, ?, ?)
                    ");

                    foreach ($valid_files as $idx => $file) {
                        $filename    = "room_{$listing_id}_{$idx}_" . time() . "." . $file['ext'];
                        $target_path = $upload_dir . $filename;

                        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                            throw new Exception("Failed to move uploaded image file.");
                        }
                        $saved_paths[] = $target_path;
                        $is_primary    = ($idx === 0) ? 1 : 0;

                        $stmt_photo->bind_param("isi", $listing_id, $target_path, $is_primary);
                        $stmt_photo->execute();
                    }
                    $stmt_photo->close();

                    // Step D: Insert into junction table `listing_inclusion` (listing_id, inclusion_id)
                    if (!empty($selected_inclusions)) {
                        $stmt_inc = $conn->prepare("INSERT INTO listing_inclusion (listing_id, inclusion_id) VALUES (?, ?)");
                        foreach ($selected_inclusions as $inc_id) {
                            $inc_id_int = (int) $inc_id;
                            if ($inc_id_int > 0) {
                                $stmt_inc->bind_param("ii", $listing_id, $inc_id_int);
                                $stmt_inc->execute();
                            }
                        }
                        $stmt_inc->close();
                    }

                    // Step E: Insert into junction table `listing_rule` (listing_id, rule_id, rule_value)
                    if (!empty($selected_rules)) {
                        $stmt_rule = $conn->prepare("INSERT INTO listing_rule (listing_id, rule_id, rule_value) VALUES (?, ?, 'Allowed')");
                        foreach ($selected_rules as $r_id) {
                            $r_id_int = (int) $r_id;
                            if ($r_id_int > 0) {
                                $stmt_rule->bind_param("ii", $listing_id, $r_id_int);
                                $stmt_rule->execute();
                            }
                        }
                        $stmt_rule->close();
                    }

                    $conn->commit();
                    header("Location: room_details.php?id=" . $listing_id);
                    exit();

                } catch (Exception $e) {
                    $conn->rollback();
                    foreach ($saved_paths as $path) {
                        if (file_exists($path)) unlink($path);
                    }
                    $error = "Failed to publish listing: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - Post a New Listing</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f3f4f6; color: #1f2937; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: #111827; color: #fff; padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; }
        .topbar a { color: #fbbf24; text-decoration: none; margin-left: 14px; font-size: 14px; }
        .container { max-width: 820px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e5e7eb; }
        h2 { margin-top: 0; }
        h3 { border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-top: 24px; color: #111827; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; margin-top: 12px; }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%; padding: 9px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-size: 14px;
        }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-top: 8px; }
        .checkbox-grid label { font-weight: normal; margin: 0; display: flex; align-items: center; gap: 6px; }
        .rule-row { display: grid; grid-template-columns: 200px 1fr; gap: 10px; align-items: center; margin-bottom: 8px; }
        .btn-submit { background: #d97706; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; margin-top: 24px; width: 100%; }
        .btn-submit:hover { background: #b45309; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-warn { background: #fef3c7; color: #92400e; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
    </style>
</head>
<body>

<div class="topbar">
    <div><strong>HIVE KEEPER PORTAL</strong> — Create Listing</div>
    <div>
        <a href="hives_hub.php">Hives Hub</a>
        <a href="my_listings.php">My Listings</a>
        <a href="logout.php">Log Out</a>
    </div>
</div>

<div class="container">
    <h2>Post a Room / Bedspace Listing</h2>

    <?php if (!$keeper_info || (int)$keeper_info['is_id_verified'] !== 1): ?>
        <div class="alert-warn">
            <strong>Verification Pending:</strong> Your Keeper ID has not been approved by an Admin yet. You will be able to publish listings once verified.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <!-- 1. PROPERTY -->
        <h3>1. Property Information</h3>

        <?php if (!empty($existing_properties)): ?>
            <label><input type="radio" name="property_mode" value="existing" onclick="togglePropertyMode('existing')"> Use an Existing Property</label>
            <select name="existing_property_id" id="existingPropSelect" disabled>
                <option value="">-- Select Your Property --</option>
                <?php foreach ($existing_properties as $p): ?>
                    <option value="<?= $p['property_id'] ?>"><?= htmlspecialchars($p['name'] . ' — ' . $p['address']) ?></option>
                <?php endforeach; ?>
            </select>
            <label><input type="radio" name="property_mode" value="new" checked onclick="togglePropertyMode('new')"> Add a New Property</label>
        <?php else: ?>
            <input type="hidden" name="property_mode" value="new">
        <?php endif; ?>

        <div id="newPropertyFields">
            <div class="grid-2">
                <div>
                    <label>Property / Building Name *</label>
                    <input type="text" name="property_name" placeholder="e.g., Sta. Mesa Student Dormitory">
                </div>
                <div>
                    <label>Housing Type *</label>
                    <select name="type_id">
                        <option value="">-- Select Housing Type --</option>
                        <?php foreach ($housing_types as $ht): ?>
                            <option value="<?= $ht['type_id'] ?>"><?= htmlspecialchars($ht['housing_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label>Complete Address *</label>
            <input type="text" name="address" placeholder="e.g., 123 Teresa St., Brgy. 630, Sta. Mesa, Manila">
        </div>

        <!-- 2. LISTING DETAILS -->
        <h3>2. Listing Details & Pricing</h3>
        <label>Listing Title *</label>
        <input type="text" name="title" placeholder="e.g., Bedspace near PUP Teresa Gate (With WiFi & Submeter)" required>

        <div class="grid-3">
            <div>
                <label>Purpose *</label>
                <select name="purpose">
                    <option value="Lessor">Lessor (Renting out a room/unit)</option>
                    <option value="Sharer">Sharer (Looking for a roommate/bedspacer)</option>
                </select>
            </div>
            <div>
                <label>Furnishing *</label>
                <select name="furnish">
                    <option value="Unfurnished">Unfurnished</option>
                    <option value="Semi-furnished" selected>Semi-furnished</option>
                    <option value="Furnished">Furnished</option>
                </select>
            </div>
            <div>
                <label>Max Tenants (`no_tenant`) *</label>
                <input type="number" name="no_tenant" min="1" value="1" required>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label>Monthly Rent (₱) *</label>
                <input type="number" name="monthly_rent" min="500" step="50" placeholder="3500" required>
            </div>
            <div>
                <label>Association Dues (₱)</label>
                <input type="number" name="assoc_dues" min="0" step="50" value="0">
            </div>
        </div>

        <div class="grid-2">
            <div>
                <label>Advance Payment (Months)</label>
                <input type="number" name="advance_months" min="0" max="12" value="1">
            </div>
            <div>
                <label>Security Deposit (Months)</label>
                <input type="number" name="deposit_months" min="0" max="12" value="1">
            </div>
        </div>

        <div class="checkbox-grid" style="margin-top:14px;">
            <label><input type="checkbox" name="water_included" value="1"> Water Included in Rent</label>
            <label><input type="checkbox" name="electricity_included" value="1"> Electricity Included in Rent</label>
            <label><input type="checkbox" name="wifi_included" value="1"> Wi-Fi Included in Rent</label>
            <label><input type="checkbox" name="is_long_term" value="1" checked> Long-Term Stay Allowed</label>
        </div>

        <label>Description</label>
        <textarea name="description" rows="4" placeholder="Describe ventilation, bed setup, study area, flood-free access, etc."></textarea>

        <!-- 3. INCLUSIONS & HOUSE RULES -->
        <h3>3. Additional Inclusions & House Rules</h3>
        <?php if (!empty($inclusions)): ?>
            <label>Select Included Inclusions</label>
            <div class="checkbox-grid">
                <?php foreach ($inclusions as $inc): ?>
                    <label>
                        <input type="checkbox" name="inclusion_ids[]" value="<?= $inc['inclusion_id'] ?>">
                        <?= htmlspecialchars($inc['inclusion_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($house_rules)): ?>
            <label style="margin-top:16px;">House Rules (Check all that are allowed)</label>
            <div class="checkbox-grid">
                <?php foreach ($house_rules as $hr): ?>
                    <label>
                        <input type="checkbox" name="rule_ids[]" value="<?= $hr['rule_id'] ?>">
                        <?= htmlspecialchars($hr['rule_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 4. PHOTOS -->
        <h3>4. Room Photos</h3>
        <label>Upload Room Photos * (First image becomes the primary cover photo; JPG/PNG/WEBP, max 5MB each)</label>
        <input type="file" name="room_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple required>

        <button type="submit" class="btn-submit">Publish Room Listing</button>
    </form>
</div>

<script>
function togglePropertyMode(mode) {
    const select = document.getElementById('existingPropSelect');
    const newFields = document.getElementById('newPropertyFields');
    if (mode === 'existing') {
        select.disabled = false;
        newFields.style.opacity = '0.45';
    } else {
        select.disabled = true;
        newFields.style.opacity = '1';
    }
}
</script>

</body>
</html>