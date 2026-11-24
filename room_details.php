<?php
session_start();
require_once 'db_connect.php';

// Enforce login (Guests are restricted to Hives Hub preview)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = (int) $_SESSION['user_id'];
$user_code  = (int) $_SESSION['user_code'];
$listing_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($listing_id <= 0) {
    header("Location: hives_hub.php");
    exit();
}

// Fetch live verification status
$v_stmt = $conn->prepare("SELECT is_id_verified FROM user WHERE user_id = ?");
$v_stmt->bind_param("i", $user_id);
$v_stmt->execute();
$v_row = $v_stmt->get_result()->fetch_assoc();
$v_stmt->close();
$is_verified_user = ($v_row && (int)$v_row['is_id_verified'] === 1);

$message = "";
$error   = "";

// 1. FETCH LISTING DETAILS (Joined with Property, Housing Type, and Keeper)
$stmt = $conn->prepare("
    SELECT 
        l.*,
        p.name AS property_name,
        p.address AS property_address,
        ht.housing_name,
        u.user_id AS keeper_id,
        u.first_name AS keeper_first_name,
        u.last_name AS keeper_last_name,
        u.contact_no AS keeper_contact,
        u.is_id_verified,
        u.account_status AS keeper_status
    FROM listing l
    JOIN property p ON l.property_id = p.property_id
    JOIN housing_type ht ON p.type_id = ht.type_id
    JOIN user u ON l.keeper_id = u.user_id
    WHERE l.listing_id = ?
");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect if listing doesn't exist or was taken down (unless viewed by Admin or the Keeper who owns it)
if (!$listing || ($listing['status'] === 'Taken Down' && $user_code !== 3 && (int)$listing['keeper_id'] !== $user_id)) {
    header("Location: hives_hub.php");
    exit();
}

// 2. HANDLE POST ACTIONS (Review, Report, or Start Chat Inquiry)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (!$is_verified_user) {
        $error = "Your PUP Certificate of Registration is still pending verification. You can browse listings, but reviewing, reporting, and messaging are locked until verified.";
    }
    // A. Start Chat Inquiry (Redirects to chat.php)
    elseif ($action === 'inquire') {
        $keeper_id = (int) $listing['keeper_id'];
        if ($keeper_id === $user_id) {
            $error = "You cannot send an inquiry to your own listing.";
        } else {
            $stmt = $conn->prepare("INSERT IGNORE INTO chat_thread (listing_id, seeker_id, keeper_id) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $listing_id, $user_id, $keeper_id);
            $stmt->execute();
            $stmt->close();

            $stmt2 = $conn->prepare("SELECT thread_id FROM chat_thread WHERE listing_id = ? AND seeker_id = ? AND keeper_id = ?");
            $stmt2->bind_param("iii", $listing_id, $user_id, $keeper_id);
            $stmt2->execute();
            $thread = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();

            if ($thread) {
                header("Location: chat.php?thread_id=" . $thread['thread_id']);
                exit();
            }
        }
    }
    // B. Submit a Review with Proof of Tenancy
    elseif ($action === 'submit_review') {
        $rating        = (int) ($_POST['rating'] ?? 0);
        $tenant_status = $_POST['tenant_status'] ?? 'Current';
        $comment       = trim($_POST['comment'] ?? '');

        if ((int)$listing['keeper_id'] === $user_id) {
            $error = "Keepers cannot review their own listing.";
        } elseif ($rating < 1 || $rating > 5) {
            $error = "Please select a rating between 1 and 5 stars.";
        } elseif (!in_array($tenant_status, ['Current', 'Past'])) {
            $error = "Invalid tenant status selected.";
        } elseif (!isset($_FILES['proof_photo']) || $_FILES['proof_photo']['error'] !== UPLOAD_ERR_OK) {
            $error = "Please upload a valid photo proving your tenancy.";
        } else {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $file_ext    = strtolower(pathinfo($_FILES['proof_photo']['name'], PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_ext)) {
                $error = "Invalid file type. Only JPG, PNG, WEBP, or PDF files are allowed.";
            } elseif ($_FILES['proof_photo']['size'] > 5 * 1024 * 1024) {
                $error = "Proof file must be under 5MB.";
            } else {
                $upload_dir = 'uploads/review_proofs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $new_filename = "proof_{$listing_id}_{$user_id}_" . time() . "." . $file_ext;
                $target_path  = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['proof_photo']['tmp_name'], $target_path)) {
                    $stmt = $conn->prepare("
                        INSERT INTO review (listing_id, reviewer_id, rating, comment, proof_document_url, tenant_status, is_verified_tenant, verification_status)
                        VALUES (?, ?, ?, ?, ?, ?, 0, 'Pending')
                    ");
                    $stmt->bind_param("iiisss", $listing_id, $user_id, $rating, $comment, $target_path, $tenant_status);
                    if ($stmt->execute()) {
                        $message = "Review submitted! It will appear once an administrator verifies your proof of tenancy.";
                    } else {
                        $error = "Database error while saving your review.";
                    }
                    $stmt->close();
                } else {
                    $error = "Failed to save uploaded proof file.";
                }
            }
        }
    }
    // C. Submit a Fraud / Issue Report
    elseif ($action === 'submit_report') {
        $reason_category = trim($_POST['reason'] ?? '');
        $extra_details   = trim($_POST['details'] ?? '');

        if ((int)$listing['keeper_id'] === $user_id) {
            $error = "You cannot report your own listing.";
        } elseif (empty($reason_category)) {
            $error = "Please select a reason for reporting this listing.";
        } else {
            $full_reason = ($extra_details !== '')
                ? "{$reason_category}: {$extra_details}"
                : $reason_category;

            $stmt = $conn->prepare("
                INSERT INTO report (listing_id, reporter_id, reason, status)
                VALUES (?, ?, ?, 'Pending')
            ");
            $stmt->bind_param("iis", $listing_id, $user_id, $full_reason);
            if ($stmt->execute()) {
                $message = "Report submitted. Our administrators will review this listing shortly.";
            } else {
                $error = "Failed to submit report.";
            }
            $stmt->close();
        }
    }
}

// 3. FETCH PHOTOS, INCLUSIONS, HOUSE RULES & REVIEWS
$stmt = $conn->prepare("SELECT image_url, is_primary FROM listing_photo WHERE listing_id = ? ORDER BY is_primary DESC, photo_id ASC");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT i.inclusion_name 
    FROM listing_inclusion li 
    JOIN inclusion i ON li.inclusion_id = i.inclusion_id 
    WHERE li.listing_id = ? 
    ORDER BY i.inclusion_name ASC
");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$inclusions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT hr.rule_name 
    FROM listing_rule lr 
    JOIN house_rule hr ON lr.rule_id = hr.rule_id 
    WHERE lr.listing_id = ? AND lr.rule_value = 'Allowed'
    ORDER BY hr.rule_name ASC
");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT r.*, u.first_name, u.last_name, u.username
    FROM review r
    JOIN user u ON r.reviewer_id = u.user_id
    WHERE r.listing_id = ? AND r.verification_status != 'Pending'
    ORDER BY r.is_verified_tenant DESC, r.review_id DESC
");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$avg_rating = 0;
if (!empty($reviews)) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = $sum / count($reviews);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - <?= htmlspecialchars($listing['title']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f9fafb; color: #1f2937; }
        .container { max-width: 1000px; margin: 0 auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 14px 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; }
        .box { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 20px; }
        .gallery { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .gallery img { width: 100%; height: 220px; object-fit: cover; border-radius: 6px; background: #e5e7eb; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-right: 4px; }
        .bg-amber { background: #fef3c7; color: #92400e; }
        .bg-green { background: #d1fae5; color: #065f46; }
        .bg-blue { background: #e0e7ff; color: #3730a3; }
        .bg-gray { background: #f3f4f6; color: #374151; }
        .price { font-size: 24px; font-weight: bold; color: #d97706; margin: 10px 0; }
        .specs-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 14px; }
        .specs-table td { padding: 8px 4px; border-bottom: 1px solid #f3f4f6; }
        .tag { display: inline-block; background: #f3f4f6; padding: 5px 10px; border-radius: 6px; font-size: 13px; margin: 3px; }
        .btn { display: block; width: 100%; padding: 11px; background: #f59e0b; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-align: center; text-decoration: none; box-sizing: border-box; font-size: 14px; }
        .btn-danger { background: #ef4444; margin-top: 10px; }
        .btn-secondary { background: #4b5563; }
        .btn-locked { background: #9ca3af; cursor: not-allowed; opacity: 0.85; }
        .locked-notice { background: #f3f4f6; border: 1px dashed #d1d5db; color: #4b5563; padding: 12px; border-radius: 6px; font-size: 13px; line-height: 1.5; }
        input, select, textarea { width: 100%; padding: 8px; margin-top: 4px; margin-bottom: 10px; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 5px; font-family: inherit; }
        .review-item { border-bottom: 1px solid #e5e7eb; padding: 12px 0; }
        .review-item:last-child { border-bottom: none; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-warn { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
    </style>
</head>
<body>

<div class="container">
    <div class="topbar">
        <div>
            <a href="hives_hub.php" style="text-decoration:none; color:#d97706; font-weight:bold;">← Back to Hives Hub</a>
        </div>
        <div>
            <a href="chat.php" style="text-decoration:none; color:#374151; margin-right:14px;">Messages</a>
            <a href="settings.php" style="text-decoration:none; color:#374151; font-weight:bold;"><?= htmlspecialchars($_SESSION['first_name']) ?></a>
        </div>
    </div>

    <?php if (!$is_verified_user && $user_code !== 3): ?>
        <div class="alert-warn">
            <strong>Verification Pending:</strong> Your <?= $user_code === 1 ? 'PUP Certificate of Registration' : 'Keeper ID' ?> is awaiting Admin approval. You can browse all rooms freely, while <strong>messaging Keepers, submitting reviews, and reporting listings</strong> will unlock once verified.
        </div>
    <?php endif; ?>

    <?php if ($message): ?><div class="alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- PHOTO GALLERY -->
    <div class="box">
        <div class="gallery">
            <?php if (!empty($photos)): ?>
                <?php foreach ($photos as $photo): ?>
                    <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="Listing Photo">
                <?php endforeach; ?>
            <?php else: ?>
                <img src="https://via.placeholder.com/800x350?text=No+Photos+Uploaded+Yet" alt="No Photo">
            <?php endif; ?>
        </div>

        <span class="badge bg-amber"><?= htmlspecialchars($listing['purpose']) ?></span>
        <span class="badge bg-blue"><?= htmlspecialchars($listing['housing_name']) ?></span>
        <span class="badge <?= $listing['status'] === 'Available' ? 'bg-green' : 'bg-gray' ?>"><?= htmlspecialchars($listing['status']) ?></span>

        <h1 style="margin: 10px 0 4px;"><?= htmlspecialchars($listing['title']) ?></h1>
        <p style="margin: 0; color: #4b5563;">
            <strong><?= htmlspecialchars($listing['property_name']) ?></strong> — <?= htmlspecialchars($listing['property_address']) ?>
        </p>
    </div>

    <div class="grid">
        <!-- LEFT COLUMN: DESCRIPTION, SPECS, INCLUSIONS, RULES & REVIEWS -->
        <div>
            <div class="box">
                <h3 style="margin-top:0;">About this Hive</h3>
                <p style="line-height: 1.6; white-space: pre-line;"><?= htmlspecialchars($listing['description'] ?? 'No description provided.') ?></p>

                <h4>Unit & Payment Details</h4>
                <table class="specs-table">
                    <tr>
                        <td><strong>Monthly Rent</strong></td>
                        <td>₱<?= number_format($listing['monthly_rent'], 2) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Association Dues</strong></td>
                        <td><?= $listing['assoc_dues'] > 0 ? '₱' . number_format($listing['assoc_dues'], 2) . ' / mo' : 'Included / None' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Terms</strong></td>
                        <td><?= (int)$listing['advance_months'] ?> Month(s) Advance, <?= (int)$listing['deposit_months'] ?> Month(s) Deposit</td>
                    </tr>
                    <tr>
                        <td><strong>Furnishing</strong></td>
                        <td><?= htmlspecialchars($listing['furnish']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Target Capacity / Tenants</strong></td>
                        <td><?= (int)$listing['no_tenant'] ?> pax</td>
                    </tr>
                    <tr>
                        <td><strong>Utilities Included in Rent</strong></td>
                        <td>
                            Water: <strong><?= $listing['water_included'] ? 'Yes' : 'No' ?></strong> |
                            Electricity: <strong><?= $listing['electricity_included'] ? 'Yes' : 'No' ?></strong> |
                            Wi-Fi: <strong><?= $listing['wifi_included'] ? 'Yes' : 'No' ?></strong>
                        </td>
                    </tr>
                </table>

                <h4>Inclusions & Amenities</h4>
                <?php if (!empty($inclusions)): ?>
                    <?php foreach ($inclusions as $inc): ?>
                        <span class="tag">✓ <?= htmlspecialchars($inc['inclusion_name']) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#6b7280; font-size:14px;">No specific inclusions listed.</p>
                <?php endif; ?>

                <h4>House Rules</h4>
                <?php if (!empty($rules)): ?>
                    <?php foreach ($rules as $rule): ?>
                        <span class="tag">✓ <?= htmlspecialchars($rule['rule_name']) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#6b7280; font-size:14px;">No specific house rules listed.</p>
                <?php endif; ?>
            </div>

            <!-- REVIEWS SECTION -->
            <div class="box">
                <h3 style="margin-top:0;">
                    Tenant Reviews
                    <span style="font-size:14px; font-weight:normal; color:#6b7280;">
                        (<?= count($reviews) > 0 ? number_format($avg_rating, 1) . " ★ from " . count($reviews) . " review(s)" : "No reviews yet" ?>)
                    </span>
                </h3>

                <!-- Add Review Form (Hidden for the listing's own Keeper; Locked if unverified) -->
                <?php if ((int)$listing['keeper_id'] !== $user_id): ?>
                    <?php if ($is_verified_user): ?>
                        <form method="POST" enctype="multipart/form-data" style="background:#f9fafb; padding:14px; border-radius:6px; border:1px solid #e5e7eb; margin-bottom:18px;">
                            <input type="hidden" name="action" value="submit_review">
                            <strong>Leave a Review</strong>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:8px;">
                                <div>
                                    <label style="font-size:13px;">Rating</label>
                                    <select name="rating" required>
                                        <option value="5">5 ★ - Excellent</option>
                                        <option value="4">4 ★ - Good</option>
                                        <option value="3">3 ★ - Average</option>
                                        <option value="2">2 ★ - Poor</option>
                                        <option value="1">1 ★ - Terrible</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:13px;">Your Residency Status</label>
                                    <select name="tenant_status" required>
                                        <option value="Current">Current Tenant</option>
                                        <option value="Past">Past Tenant</option>
                                    </select>
                                </div>
                            </div>

                            <label style="font-size:13px;">Proof of Tenancy (Required: Photo of Receipt, Lease, or Utility Bill)</label>
                            <input type="file" name="proof_photo" accept=".jpg,.jpeg,.png,.webp,.pdf" required>

                            <label style="font-size:13px;">Comment</label>
                            <textarea name="comment" rows="3" placeholder="Share your experience with the unit, landlord, or neighborhood..." required></textarea>
                            <button type="submit" class="btn" style="width:auto; padding:8px 16px;">Submit Review for Verification</button>
                        </form>
                    <?php else: ?>
                        <div class="locked-notice" style="margin-bottom:18px;">
                            <strong>Leave a Review (Locked)</strong><br>
                            Submitting tenant reviews will unlock once an administrator verifies your PUP Certificate of Registration.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Reviews List -->
                <?php if (empty($reviews)): ?>
                    <p style="color:#6b7280; font-size:14px;">Be the first to review this listing!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-item">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <strong><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></strong>
                                    <span class="badge <?= $rev['is_verified_tenant'] ? 'bg-green' : 'bg-gray' ?>">
                                        <?= $rev['is_verified_tenant'] ? '✓ Verified Review' : 'Unverified Review' ?>
                                    </span>
                                    <span class="badge bg-blue"><?= htmlspecialchars($rev['tenant_status']) ?> Tenant</span>
                                </div>
                                <strong style="color:#d97706;"><?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?></strong>
                            </div>
                            <p style="margin:8px 0 0; font-size:14px; line-height:1.5;"><?= htmlspecialchars($rev['comment']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT COLUMN: KEEPER INFO, INQUIRE BUTTON & REPORT BOX -->
        <div>
            <div class="box">
                <div class="price">₱<?= number_format($listing['monthly_rent'], 2) ?> <span style="font-size:13px; color:#6b7280; font-weight:normal;">/ month</span></div>

                <hr style="border:none; border-top:1px solid #e5e7eb; margin:14px 0;">

                <h4 style="margin:0 0 6px;">Posted by Keeper</h4>
                <p style="margin:0 0 6px;">
                    <strong><?= htmlspecialchars($listing['keeper_first_name'] . ' ' . $listing['keeper_last_name']) ?></strong>
                </p>
                <?php if ($listing['is_id_verified']): ?>
                    <span class="badge bg-green">✓ ID Verified Keeper</span>
                <?php else: ?>
                    <span class="badge bg-amber">ID Verification Pending</span>
                <?php endif; ?>
                <p style="font-size:13px; color:#4b5563; margin-top:8px;">
                    Contact: <?= htmlspecialchars($listing['keeper_contact']) ?>
                </p>

                <?php if ((int)$listing['keeper_id'] !== $user_id): ?>
                    <?php if ($is_verified_user): ?>
                        <form method="POST" style="margin-top:14px;">
                            <input type="hidden" name="action" value="inquire">
                            <button type="submit" class="btn">Inquire / Message Keeper</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-locked" style="margin-top:14px;" disabled>
                            Messaging Locked Until Verified
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="my_listings.php" class="btn btn-secondary" style="margin-top:14px;">Manage My Listing</a>
                <?php endif; ?>
            </div>

            <!-- REPORT FRAUDULENT LISTING BOX -->
            <?php if ((int)$listing['keeper_id'] !== $user_id): ?>
                <div class="box">
                    <h4 style="margin-top:0; color:#991b1b;">Report this Listing</h4>
                    <?php if ($is_verified_user): ?>
                        <p style="font-size:12px; color:#6b7280;">Suspect a scam, fake photos, or misleading rent terms? Let our Admin team know.</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="submit_report">
                            <label style="font-size:13px;">Reason</label>
                            <select name="reason" required>
                                <option value="">Select a reason...</option>
                                <option value="Suspected Scam / Fake Listing">Suspected Scam / Fake Listing</option>
                                <option value="Misleading Rent or Fees">Misleading Rent or Fees</option>
                                <option value="Unit Already Occupied">Unit Already Occupied</option>
                                <option value="Inappropriate Content">Inappropriate Content</option>
                            </select>
                            <label style="font-size:13px;">Additional Details</label>
                            <textarea name="details" rows="2" placeholder="Provide extra context..."></textarea>
                            <button type="submit" class="btn btn-danger">Submit Report</button>
                        </form>
                    <?php else: ?>
                        <div class="locked-notice">
                            <strong>Reporting Locked</strong><br>
                            Reporting listings is available once your PUP COR / ID is verified by an administrator.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>