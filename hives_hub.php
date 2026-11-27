<?php
session_start();
require_once 'db_connect.php';

$is_logged_in = isset($_SESSION['user_id']);

// 1. FETCH FILTER OPTIONS FROM DATABASE
$properties = $conn->query("SELECT property_id, name FROM property ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$housing_types = $conn->query("SELECT type_id, housing_name FROM housing_type ORDER BY housing_name ASC")->fetch_all(MYSQLI_ASSOC);
$inclusions_list = $conn->query("SELECT inclusion_id, inclusion_name FROM inclusion ORDER BY inclusion_name ASC")->fetch_all(MYSQLI_ASSOC);
$rules_list = $conn->query("SELECT rule_id, rule_name FROM house_rule ORDER BY rule_name ASC")->fetch_all(MYSQLI_ASSOC);

// 2. CAPTURE FILTER INPUTS ($_GET)
$property_id   = isset($_GET['property_id']) && $_GET['property_id'] !== '' ? (int) $_GET['property_id'] : null;
$type_id       = isset($_GET['type_id']) && $_GET['type_id'] !== '' ? (int) $_GET['type_id'] : null;
$purpose       = isset($_GET['purpose']) && in_array($_GET['purpose'], ['Lessor', 'Sharer']) ? $_GET['purpose'] : null;
$furnish       = isset($_GET['furnish']) && in_array($_GET['furnish'], ['Unfurnished', 'Semi-furnished', 'Furnished']) ? $_GET['furnish'] : null;
$min_price     = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$max_price     = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$min_rating    = isset($_GET['min_rating']) && $_GET['min_rating'] !== '' ? (float) $_GET['min_rating'] : null;
$selected_inc  = isset($_GET['inclusions']) && is_array($_GET['inclusions']) ? array_map('intval', $_GET['inclusions']) : [];
$selected_rule = isset($_GET['rules']) && is_array($_GET['rules']) ? array_map('intval', $_GET['rules']) : [];

// 3. BUILD DYNAMIC SQL QUERY
$sql = "
    SELECT 
        l.listing_id,
        l.title,
        l.purpose,
        l.monthly_rent,
        l.assoc_dues,
        l.furnish,
        l.no_tenant,
        l.status,
        l.is_featured,
        l.water_included,
        l.electricity_included,
        l.wifi_included,
        p.name AS property_name,
        p.address AS property_address,
        ht.housing_name,
        u.first_name AS keeper_first_name,
        u.is_id_verified,
        (SELECT lp.image_url FROM listing_photo lp WHERE lp.listing_id = l.listing_id ORDER BY lp.is_primary DESC, lp.photo_id ASC LIMIT 1) AS primary_photo,
        COALESCE(AVG(r.rating), 0) AS avg_rating,
        COUNT(DISTINCT r.review_id) AS review_count
    FROM listing l
    JOIN property p ON l.property_id = p.property_id
    JOIN housing_type ht ON p.type_id = ht.type_id
    JOIN user u ON l.keeper_id = u.user_id
    LEFT JOIN review r ON l.listing_id = r.listing_id
    WHERE l.status = 'Available'
      AND u.account_status = 'Active'
";

$params = [];
$types  = "";

if ($property_id !== null) {
    $sql .= " AND l.property_id = ?";
    $params[] = $property_id;
    $types .= "i";
}
if ($type_id !== null) {
    $sql .= " AND p.type_id = ?";
    $params[] = $type_id;
    $types .= "i";
}
if ($purpose !== null) {
    $sql .= " AND l.purpose = ?";
    $params[] = $purpose;
    $types .= "s";
}
if ($furnish !== null) {
    $sql .= " AND l.furnish = ?";
    $params[] = $furnish;
    $types .= "s";
}
if ($min_price !== null) {
    $sql .= " AND l.monthly_rent >= ?";
    $params[] = $min_price;
    $types .= "d";
}
if ($max_price !== null) {
    $sql .= " AND l.monthly_rent <= ?";
    $params[] = $max_price;
    $types .= "d";
}

// Match ALL selected inclusions
if (!empty($selected_inc)) {
    $placeholders = implode(',', array_fill(0, count($selected_inc), '?'));
    $sql .= " AND l.listing_id IN (
        SELECT li.listing_id 
        FROM listing_inclusion li 
        WHERE li.inclusion_id IN ($placeholders)
        GROUP BY li.listing_id 
        HAVING COUNT(DISTINCT li.inclusion_id) = ?
    )";
    foreach ($selected_inc as $inc_id) {
        $params[] = $inc_id;
        $types .= "i";
    }
    $params[] = count($selected_inc);
    $types .= "i";
}

// Match ALL selected house rules
if (!empty($selected_rule)) {
    $placeholders = implode(',', array_fill(0, count($selected_rule), '?'));
    $sql .= " AND l.listing_id IN (
        SELECT lr.listing_id 
        FROM listing_rule lr 
        WHERE lr.rule_id IN ($placeholders)
        GROUP BY lr.listing_id 
        HAVING COUNT(DISTINCT lr.rule_id) = ?
    )";
    foreach ($selected_rule as $r_id) {
        $params[] = $r_id;
        $types .= "i";
    }
    $params[] = count($selected_rule);
    $types .= "i";
}

$sql .= " GROUP BY l.listing_id";

if ($min_rating !== null && $min_rating > 0) {
    $sql .= " HAVING avg_rating >= ?";
    $params[] = $min_rating;
    $types .= "d";
}

// Featured listings first, then newest
$sql .= " ORDER BY l.is_featured DESC, l.listing_id DESC";

// Scope rule: Guests see a maximum preview of 25 listings
if (!$is_logged_in) {
    $sql .= " LIMIT 25";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - Hives Hub (Listings)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f9fafb; color: #1f2937; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 14px 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 20px; }
        .layout { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
        .sidebar { background: #fff; padding: 18px; border-radius: 8px; border: 1px solid #e5e7eb; }
        .sidebar label { display: block; font-weight: bold; margin-top: 12px; font-size: 14px; }
        .sidebar select, .sidebar input[type="number"] { width: 100%; padding: 7px; margin-top: 4px; box-sizing: border-box; }
        .checkbox-group { max-height: 140px; overflow-y: auto; border: 1px solid #e5e7eb; padding: 8px; margin-top: 4px; border-radius: 4px; font-size: 13px; }
        .checkbox-group label { font-weight: normal; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
        .btn { display: inline-block; padding: 9px 14px; background: #f59e0b; color: #fff; text-decoration: none; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-align: center; }
        .btn-secondary { background: #6b7280; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
        .card img { width: 100%; height: 170px; object-fit: cover; background: #e5e7eb; }
        .card-body { padding: 14px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .badge { display: inline-block; font-size: 11px; padding: 3px 8px; border-radius: 10px; font-weight: bold; background: #fef3c7; color: #92400e; }
        .badge-verified { background: #d1fae5; color: #065f46; }
        .price { font-size: 18px; font-weight: bold; color: #d97706; margin: 8px 0; }
        .guest-banner { background: #fffbeb; border: 1px solid #fde68a; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
    </style>
</head>
<body>

<div class="topbar">
    <!-- <div>
        <a href="hives_hub.php" style="text-decoration:none; color:#1f2937; font-size:18px;">
            <strong>HIVE</strong>
        </a>
        <span style="font-size:13px; color:#6b7280; margin-left:6px;">Hub</span>
    </div> -->

    <nav style="display:flex; align-items:center; gap:16px; font-size:14px;">
        <a href="hives_hub.php" style="text-decoration:none; color:#d97706; font-weight:bold;">Hives Hub</a>

        <?php if ($is_logged_in): ?>
            <?php if ($_SESSION['user_code'] == 1): ?>
                <!-- Seeker Links -->
                <a href="beestie.php" style="text-decoration:none; color:#374151;">Find a Beestie!</a>
            <?php elseif ($_SESSION['user_code'] == 2): ?>
                <!-- Keeper Links -->
                <a href="post_listing.php" style="text-decoration:none; color:#374151;">+ Post a Room</a>
                <a href="my_listings.php" style="text-decoration:none; color:#374151;">My Listings</a>
            <?php endif; ?>

            <a href="chat.php" style="text-decoration:none; color:#374151;">Messages</a>
            <a href="settings.php" style="text-decoration:none; color:#374151; font-weight:bold;">
                Settings
            </a>
        <?php else: ?>
            <!-- Guest Links -->
            <a href="login.php" style="text-decoration:none; color:#374151;">Log In</a>
            <a href="register.php" class="btn" style="padding:6px 12px;">Sign Up</a>
        <?php endif; ?>
    </nav>
</div>

<?php if (!$is_logged_in): ?>
    <div class="guest-banner">
        <strong>Guest Preview Mode:</strong> You are viewing up to 25 available listings. <a href="login.php">Log in</a> or <a href="register.php">create an account</a> to view full room details, chat with Keepers, and access "Find a Beestie!".
    </div>
<?php endif; ?>

<div class="layout">
    <aside class="sidebar">
        <h3 style="margin-top:0;">Filter Hives</h3>
        <form method="GET" action="hives_hub.php">
            <label>Building / Property</label>
            <select name="property_id">
                <option value="">All Properties</option>
                <?php foreach ($properties as $prop): ?>
                    <option value="<?= $prop['property_id'] ?>" <?= ($property_id === (int)$prop['property_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prop['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Housing Type</label>
            <select name="type_id">
                <option value="">All Types</option>
                <?php foreach ($housing_types as $ht): ?>
                    <option value="<?= $ht['type_id'] ?>" <?= ($type_id === (int)$ht['type_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ht['housing_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Listing Purpose</label>
            <select name="purpose">
                <option value="">Any (Lessor / Sharer)</option>
                <option value="Lessor" <?= ($purpose === 'Lessor') ? 'selected' : '' ?>>Lessor (Whole Unit / Landlord)</option>
                <option value="Sharer" <?= ($purpose === 'Sharer') ? 'selected' : '' ?>>Sharer (Looking for Roommate)</option>
            </select>

            <label>Monthly Budget (₱)</label>
            <div style="display:flex; gap:6px;">
                <input type="number" name="min_price" placeholder="Min" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>">
                <input type="number" name="max_price" placeholder="Max" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
            </div>

            <label>Furnishing</label>
            <select name="furnish">
                <option value="">Any</option>
                <option value="Unfurnished" <?= ($furnish === 'Unfurnished') ? 'selected' : '' ?>>Unfurnished</option>
                <option value="Semi-furnished" <?= ($furnish === 'Semi-furnished') ? 'selected' : '' ?>>Semi-furnished</option>
                <option value="Furnished" <?= ($furnish === 'Furnished') ? 'selected' : '' ?>>Furnished</option>
            </select>

            <label>Minimum Rating</label>
            <select name="min_rating">
                <option value="">Any Rating</option>
                <option value="4" <?= ($min_rating == 4) ? 'selected' : '' ?>>4★ & above</option>
                <option value="3" <?= ($min_rating == 3) ? 'selected' : '' ?>>3★ & above</option>
            </select>

            <label>Inclusions</label>
            <div class="checkbox-group">
                <?php foreach ($inclusions_list as $inc): ?>
                    <label>
                        <input type="checkbox" name="inclusions[]" value="<?= $inc['inclusion_id'] ?>"
                            <?= in_array((int)$inc['inclusion_id'], $selected_inc) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($inc['inclusion_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <label>House Rules</label>
            <div class="checkbox-group">
                <?php foreach ($rules_list as $rule): ?>
                    <label>
                        <input type="checkbox" name="rules[]" value="<?= $rule['rule_id'] ?>"
                            <?= in_array((int)$rule['rule_id'], $selected_rule) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($rule['rule_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="submit" class="btn" style="flex:1;">Apply</button>
                <a href="hives_hub.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </aside>

    <!-- LISTINGS GRID -->
    <main>
        <p style="margin-top:0;">Showing <strong><?= count($listings) ?></strong> available listing(s)</p>

        <?php if (empty($listings)): ?>
            <p>No listings matched your filter criteria. Try adjusting or resetting your filters.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($listings as $row): ?>
                    <div class="card">
                        <img src="<?= htmlspecialchars($row['primary_photo'] ?? 'https://via.placeholder.com/400x220?text=Hive+Room') ?>" alt="Room Photo">
                        <div class="card-body">
                            <div>
                                <span class="badge"><?= htmlspecialchars($row['purpose']) ?></span>
                                <span class="badge" style="background:#e0e7ff; color:#3730a3;"><?= htmlspecialchars($row['housing_name']) ?></span>
                                <?php if ($row['is_id_verified']): ?>
                                    <span class="badge badge-verified">Verified Keeper</span>
                                <?php endif; ?>

                                <h3 style="margin:8px 0 4px;"><?= htmlspecialchars($row['title']) ?></h3>
                                <div style="font-size:13px; color:#4b5563;">
                                    <?= htmlspecialchars($row['property_name']) ?> — <?= htmlspecialchars($row['property_address']) ?>
                                </div>
                                <div class="price">₱<?= number_format($row['monthly_rent'], 2) ?> <span style="font-size:12px; color:#6b7280; font-weight:normal;">/ mo</span></div>
                                <div style="font-size:12px; color:#6b7280;">
                                    Rating: <?= $row['review_count'] > 0 ? number_format($row['avg_rating'], 1) . " ★ ({$row['review_count']})" : "No reviews yet" ?>
                                </div>
                            </div>

                            <div style="margin-top:12px;">
                                <?php if ($is_logged_in): ?>
                                    <a href="room_details.php?id=<?= $row['listing_id'] ?>" class="btn" style="width:100%; box-sizing:border-box;">View Room Details</a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-secondary" style="width:100%; box-sizing:border-box;">Log in to View Details</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>