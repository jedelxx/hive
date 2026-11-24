<?php
session_start();
require_once 'db_connect.php';

// Restrict access strictly to Admin (user_code = 3)
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_code'] !== 3) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

// 1. HANDLE ADMIN POST ACTIONS
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // A. Approve Seeker COR or Keeper ID
    if ($action === 'approve_id' && isset($_POST['target_user_id'])) {
        $target_id = (int) $_POST['target_user_id'];
        $stmt = $conn->prepare("UPDATE user SET is_id_verified = 1, account_status = 'Active' WHERE user_id = ? AND user_code IN (1, 2)");
        $stmt->bind_param("i", $target_id);
        if ($stmt->execute()) {
            $message = "User verification document approved successfully.";
        }
        $stmt->close();
    }

    // B. Reject Seeker COR or Keeper ID
    elseif ($action === 'reject_id' && isset($_POST['target_user_id'])) {
        $target_id = (int) $_POST['target_user_id'];
        // Seekers remain Active in read-only mode and must re-upload; Keepers are suspended
        $stmt = $conn->prepare("
            UPDATE user 
            SET is_id_verified = 0,
                id_document_url = NULL,
                account_status = CASE WHEN user_code = 2 THEN 'Suspended' ELSE 'Active' END
            WHERE user_id = ? AND user_code IN (1, 2)
        ");
        $stmt->bind_param("i", $target_id);
        if ($stmt->execute()) {
            $message = "Verification document rejected. User must re-submit a valid document.";
        }
        $stmt->close();
    }

    // C. Resolve Listing Fraud Report
    elseif ($action === 'resolve_report' && isset($_POST['report_id'], $_POST['resolution'])) {
        $report_id        = (int) $_POST['report_id'];
        $resolution       = $_POST['resolution']; // 'Confirmed Fraud' or 'Dismissed'
        $listing_id       = (int) ($_POST['listing_id'] ?? 0);
        $keeper_id        = (int) ($_POST['keeper_id'] ?? 0);
        $admin_id         = (int) $_SESSION['user_id'];
        $resolution_notes = trim($_POST['resolution_notes'] ?? "Resolved as {$resolution}");

        if (in_array($resolution, ['Confirmed Fraud', 'Dismissed'])) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    UPDATE report 
                    SET status = ?, reviewed_by = ?, reviewed_at = NOW(), resolution_notes = ? 
                    WHERE report_id = ?
                ");
                $stmt->bind_param("sisi", $resolution, $admin_id, $resolution_notes, $report_id);
                $stmt->execute();
                $stmt->close();

                if ($resolution === 'Confirmed Fraud') {
                    if ($listing_id > 0) {
                        $stmt2 = $conn->prepare("UPDATE listing SET status = 'Taken Down' WHERE listing_id = ?");
                        $stmt2->bind_param("i", $listing_id);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                    if ($keeper_id > 0) {
                        $stmt3 = $conn->prepare("UPDATE user SET account_status = 'Banned' WHERE user_id = ? AND user_code != 3");
                        $stmt3->bind_param("i", $keeper_id);
                        $stmt3->execute();
                        $stmt3->close();
                    }
                }

                $conn->commit();
                $message = ($resolution === 'Confirmed Fraud')
                    ? "Report confirmed: Listing taken down and Keeper banned."
                    : "Report marked as Dismissed.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to process report: " . $e->getMessage();
            }
        }
    }

    // D. Update User Account Status (Active / Suspended / Banned)
    elseif ($action === 'update_user_status' && isset($_POST['target_user_id'], $_POST['new_status'])) {
        $target_id  = (int) $_POST['target_user_id'];
        $new_status = $_POST['new_status'];

        if (in_array($new_status, ['Active', 'Suspended', 'Banned'])) {
            $stmt = $conn->prepare("UPDATE user SET account_status = ? WHERE user_id = ? AND user_code != 3");
            $stmt->bind_param("si", $new_status, $target_id);
            if ($stmt->execute()) {
                $message = "User account status updated to {$new_status}.";
            }
            $stmt->close();
        }
    }

    // E. Moderate Tenant Review Proof
    elseif ($action === 'moderate_review' && isset($_POST['review_id'], $_POST['decision'])) {
        $review_id = (int) $_POST['review_id'];
        $decision  = $_POST['decision']; // 'Verified' or 'Unverified'

        if (in_array($decision, ['Verified', 'Unverified'])) {
            $is_verified = ($decision === 'Verified') ? 1 : 0;
            $stmt = $conn->prepare("UPDATE review SET is_verified_tenant = ?, verification_status = ? WHERE review_id = ?");
            $stmt->bind_param("isi", $is_verified, $decision, $review_id);
            if ($stmt->execute()) {
                $message = "Review #{$review_id} marked as {$decision}.";
            }
            $stmt->close();
        }
    }
}

// 2. FETCH DASHBOARD METRICS
$stats = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM user WHERE user_code != 3 AND account_status = 'Active') AS active_users,
        (SELECT COUNT(*) FROM user WHERE user_code IN (1, 2) AND is_id_verified = 0 AND id_document_url IS NOT NULL AND id_document_url != '') AS pending_ids,
        (SELECT COUNT(*) FROM listing WHERE status = 'Available') AS active_listings,
        (SELECT COUNT(*) FROM report WHERE status = 'Pending') AS pending_reports
")->fetch_assoc();

// 3. FETCH PENDING SEEKER COR & KEEPER ID VERIFICATIONS
$pending_verifications = $conn->query("
    SELECT user_id, user_code, username, email, first_name, last_name, id_type, id_document_url, account_status, created_at
    FROM user
    WHERE is_id_verified = 0
      AND id_document_url IS NOT NULL
      AND id_document_url != ''
      AND user_code IN (1, 2)
    ORDER BY created_at ASC
")->fetch_all(MYSQLI_ASSOC);

// 4. FETCH FRAUD REPORTS
$reports = $conn->query("
    SELECT 
        r.report_id,
        r.reason,
        r.status AS report_status,
        r.resolution_notes,
        r.created_at,
        l.listing_id,
        l.title AS listing_title,
        l.status AS listing_status,
        reporter.username AS reporter_username,
        keeper.user_id AS keeper_id,
        keeper.username AS keeper_username,
        keeper.account_status AS keeper_status
    FROM report r
    JOIN listing l ON r.listing_id = l.listing_id
    JOIN user reporter ON r.reporter_id = reporter.user_id
    JOIN user keeper ON l.keeper_id = keeper.user_id
    ORDER BY FIELD(r.status, 'Pending', 'Confirmed Fraud', 'Dismissed'), r.report_id DESC
")->fetch_all(MYSQLI_ASSOC);

// 5. FETCH ALL NON-ADMIN USERS FOR ACCOUNT MODERATION
$all_users = $conn->query("
    SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.is_id_verified, u.account_status, ut.user_type
    FROM user u
    JOIN user_type ut ON u.user_code = ut.user_code
    WHERE u.user_code != 3
    ORDER BY u.user_id DESC
")->fetch_all(MYSQLI_ASSOC);

$pending_reviews = $conn->query("
    SELECT 
        r.review_id,
        r.rating,
        r.comment,
        r.proof_document_url,
        r.tenant_status,
        l.title AS listing_title,
        u.username AS reviewer_username,
        u.first_name,
        u.last_name
    FROM review r
    JOIN listing l ON r.listing_id = l.listing_id
    JOIN user u ON r.reviewer_id = u.user_id
    WHERE r.verification_status = 'Pending'
    ORDER BY r.review_id DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hive - Administrator Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f3f4f6; color: #1f2937; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: #111827; color: #fff; padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; }
        .topbar a { color: #fbbf24; text-decoration: none; margin-left: 14px; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .stat-card { background: #fff; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb; }
        .stat-card h4 { margin: 0 0 6px; font-size: 13px; color: #6b7280; text-transform: uppercase; }
        .stat-card .num { font-size: 26px; font-weight: bold; color: #111827; }
        .section { background: #fff; padding: 18px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 24px; overflow-x: auto; }
        .section h3 { margin-top: 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: bold; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 12px; font-weight: bold; }
        .bg-green { background: #d1fae5; color: #065f46; }
        .bg-yellow { background: #fef3c7; color: #92400e; }
        .bg-red { background: #fee2e2; color: #991b1b; }
        .bg-blue { background: #dbeafe; color: #1e40af; }
        .btn { padding: 6px 10px; border: none; border-radius: 4px; color: #fff; font-size: 12px; font-weight: bold; cursor: pointer; }
        .btn-approve { background: #10b981; }
        .btn-reject, .btn-ban { background: #ef4444; }
        .btn-neutral { background: #6b7280; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
    </style>
</head>
<body>

<div class="topbar">
    <div><strong>HIVE ADMIN</strong> — Moderation & Verification Panel</div>
    <div>
        <span>Logged in as <?= htmlspecialchars($_SESSION['first_name']) ?></span>
        <a href="hives_hub.php">View Public Hub</a>
        <a href="logout.php">Log Out</a>
    </div>
</div>

<?php if ($message): ?><div class="alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- OVERVIEW METRICS -->
<div class="stats-grid">
    <div class="stat-card">
        <h4>Active Users</h4>
        <div class="num"><?= (int)$stats['active_users'] ?></div>
    </div>
    <div class="stat-card">
        <h4>Pending Verifications</h4>
        <div class="num" style="color:#d97706;"><?= (int)$stats['pending_ids'] ?></div>
    </div>
    <div class="stat-card">
        <h4>Available Listings</h4>
        <div class="num" style="color:#059669;"><?= (int)$stats['active_listings'] ?></div>
    </div>
    <div class="stat-card">
        <h4>Pending Reports</h4>
        <div class="num" style="color:#dc2626;"><?= (int)$stats['pending_reports'] ?></div>
    </div>
</div>

<!-- 1. SEEKER COR & KEEPER ID VERIFICATION QUEUE -->
<div class="section">
    <h3>User Verification Queue (Seeker PUP COR & Keeper Valid ID)</h3>
    <?php if (empty($pending_verifications)): ?>
        <p style="color:#6b7280;">No Seekers or Keepers currently awaiting document verification.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Role</th>
                    <th>Full Name</th>
                    <th>Username / Email</th>
                    <th>Document Type</th>
                    <th>Uploaded File</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_verifications as $u): ?>
                    <?php $is_seeker = ((int)$u['user_code'] === 1); ?>
                    <tr>
                        <td>#<?= $u['user_id'] ?></td>
                        <td>
                            <span class="badge <?= $is_seeker ? 'bg-blue' : 'bg-yellow' ?>">
                                <?= $is_seeker ? 'Seeker' : 'Keeper' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><?= htmlspecialchars($u['username']) ?><br><small><?= htmlspecialchars($u['email']) ?></small></td>
                        <td><?= htmlspecialchars($u['id_type'] ?? 'N/A') ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($u['id_document_url']) ?>" target="_blank">
                                <?= $is_seeker ? 'View PUP COR' : 'View ID File' ?>
                            </a>
                        </td>
                        <td><span class="badge bg-yellow"><?= htmlspecialchars($u['account_status']) ?></span></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="approve_id">
                                <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" class="btn btn-approve">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="reject_id">
                                <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" class="btn btn-reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- TENANT REVIEW PROOF QUEUE -->
<div class="section">
    <h3>Tenant Review Proof Verification Queue</h3>
    <?php if (empty($pending_reviews)): ?>
        <p style="color:#6b7280;">No tenant reviews awaiting verification.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Review ID</th>
                    <th>Listing</th>
                    <th>Reviewer</th>
                    <th>Rating & Comment</th>
                    <th>Tenancy Proof</th>
                    <th>Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_reviews as $rev): ?>
                    <tr>
                        <td>#<?= $rev['review_id'] ?></td>
                        <td><?= htmlspecialchars($rev['listing_title']) ?></td>
                        <td>
                            <?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?><br>
                            <small>(<?= htmlspecialchars($rev['tenant_status']) ?> Tenant)</small>
                        </td>
                        <td>
                            <strong><?= (int)$rev['rating'] ?> ★</strong><br>
                            <small><?= htmlspecialchars($rev['comment']) ?></small>
                        </td>
                        <td>
                            <?php if (!empty($rev['proof_document_url'])): ?>
                                <a href="<?= htmlspecialchars($rev['proof_document_url']) ?>" target="_blank">View Proof Photo</a>
                            <?php else: ?>
                                <span style="color:#9ca3af;">No proof attached</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="moderate_review">
                                <input type="hidden" name="review_id" value="<?= $rev['review_id'] ?>">
                                <input type="hidden" name="decision" value="Verified">
                                <button type="submit" class="btn btn-approve">Accept as Verified</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="moderate_review">
                                <input type="hidden" name="review_id" value="<?= $rev['review_id'] ?>">
                                <input type="hidden" name="decision" value="Unverified">
                                <button type="submit" class="btn btn-neutral">Mark Unverified</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- 2. FRAUD & LISTING REPORTS QUEUE -->
<div class="section">
    <h3>Listing Fraud Reports</h3>
    <?php if (empty($reports)): ?>
        <p style="color:#6b7280;">No reports submitted yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Reported Listing</th>
                    <th>Keeper</th>
                    <th>Reporter</th>
                    <th>Reason & Details</th>
                    <th>Status</th>
                    <th>Moderation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $rep): ?>
                    <tr>
                        <td>#<?= $rep['report_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($rep['listing_title']) ?></strong><br>
                            <small>Listing Status: <?= htmlspecialchars($rep['listing_status']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($rep['keeper_username']) ?><br>
                            <small>Account: <?= htmlspecialchars($rep['keeper_status']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($rep['reporter_username']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($rep['reason']) ?></strong><br>
                        </td>
                        <td>
                            <?php
                                $badgeClass = ($rep['report_status'] === 'Pending') ? 'bg-yellow' : (($rep['report_status'] === 'Confirmed Fraud') ? 'bg-red' : 'bg-green');
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($rep['report_status']) ?></span>
                        </td>
                        <td>
                            <?php if ($rep['report_status'] === 'Pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="resolve_report">
                                    <input type="hidden" name="report_id" value="<?= $rep['report_id'] ?>">
                                    <input type="hidden" name="listing_id" value="<?= $rep['listing_id'] ?>">
                                    <input type="hidden" name="keeper_id" value="<?= $rep['keeper_id'] ?>">
                                    <input type="hidden" name="resolution" value="Confirmed Fraud">
                                    <button type="submit" class="btn btn-ban" onclick="return confirm('Confirm fraud? This will take down the listing and ban the Keeper.');">Confirm Fraud</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="resolve_report">
                                    <input type="hidden" name="report_id" value="<?= $rep['report_id'] ?>">
                                    <input type="hidden" name="resolution" value="Dismissed">
                                    <button type="submit" class="btn btn-neutral">Dismiss</button>
                                </form>
                            <?php else: ?>
                                <span style="color:#6b7280; font-size:12px;">Resolved</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- 3. USER ACCOUNT MANAGEMENT -->
<div class="section">
    <h3>User Accounts Management</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Verified</th>
                <th>Account Status</th>
                <th>Change Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_users as $u): ?>
                <tr>
                    <td>#<?= $u['user_id'] ?></td>
                    <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['user_type']) ?></td>
                    <td>
                        <span class="badge <?= $u['is_id_verified'] ? 'bg-green' : 'bg-yellow' ?>">
                            <?= $u['is_id_verified'] ? 'Verified' : 'Pending' ?>
                        </span>
                    </td>
                    <td>
                        <?php
                            $uBadge = ($u['account_status'] === 'Active') ? 'bg-green' : (($u['account_status'] === 'Suspended') ? 'bg-yellow' : 'bg-red');
                        ?>
                        <span class="badge <?= $uBadge ?>"><?= htmlspecialchars($u['account_status']) ?></span>
                    </td>
                    <td>
                        <form method="POST" style="display:flex; gap:6px;">
                            <input type="hidden" name="action" value="update_user_status">
                            <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                            <select name="new_status" style="padding:4px;">
                                <option value="Active" <?= $u['account_status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Suspended" <?= $u['account_status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="Banned" <?= $u['account_status'] === 'Banned' ? 'selected' : '' ?>>Banned</option>
                            </select>
                            <button type="submit" class="btn btn-neutral">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>