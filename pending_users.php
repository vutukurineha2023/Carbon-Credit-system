<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include 'db.php';

// Allowed languages
$allowed_langs = ['eng', 'hin', 'telugu'];

// Handle language selection
if (isset($_POST['lang']) && in_array($_POST['lang'], $allowed_langs)) {
    $_SESSION['lang'] = $_POST['lang'];
}

// Load language
$lang = $_SESSION['lang'] ?? 'eng';
$translations = include "lang/$lang.php";

function t($key, $params = []) {
    global $translations;
    $str = $translations[$key] ?? $key;
    if (!empty($params)) $str = vsprintf($str, $params);
    return $str;
}

// Check login & role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'community') {
    header("Location: login.php");
    exit;
}

$community_id = $_SESSION['user_id'];

// Handle approval/rejection POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action']; // approve / reject

    // Validate request belongs to this community & pending
    $check = $conn->query("SELECT id FROM users WHERE id=$request_id AND panchayat_id=$community_id AND role='user' AND status='pending'");
    
    if ($check->num_rows > 0) {
        if ($action === 'approve') {
            $conn->query("UPDATE users SET status='approved' WHERE id=$request_id");
            $_SESSION['msg'] = t("user_approved", ["User"]);
        } elseif ($action === 'reject') {
            $conn->query("UPDATE users SET status='rejected' WHERE id=$request_id");
            $_SESSION['msg'] = t("user_rejected", ["User"]);
        }
    }

    header("Location: pending_users.php");
    exit;
}

// Fetch pending user requests
$pending_requests = $conn->query("
    SELECT id, name, email, role 
    FROM users 
    WHERE panchayat_id=$community_id 
      AND role='user' 
      AND status='pending' 
    ORDER BY created_at ASC
");

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<title><?= t("pending_user_requests") ?></title>
<style>
body { font-family: Arial,sans-serif; background:#f9f9f9; padding:20px; }
.lang-select { text-align:right; margin-bottom:10px; }
h2 { text-align:center; margin-bottom:20px; }
table { width:100%; border-collapse: collapse; margin-top:20px; }
th, td { padding:10px; border:1px solid #ddd; text-align:left; }
th { background:#f2f2f2; }
button { padding:6px 12px; border:none; border-radius:5px; cursor:pointer; }
.approve { background:#28a745; color:white; }
.reject { background:#dc3545; color:white; }
.message { padding:10px; margin-bottom:15px; background:#e7f4e4; border-left:4px solid #28a745; }
a { display:inline-block; margin-top:20px; text-decoration:none; color:#2d7dd2; }
</style>
</head>
<body>

<div class="lang-select">
<form method="POST">
    <select name="lang" onchange="this.form.submit()">
        <option value="eng" <?= ($lang=='eng')?'selected':'' ?>>English</option>
        <option value="hin" <?= ($lang=='hin')?'selected':'' ?>>Hindi</option>
        <option value="telugu" <?= ($lang=='telugu')?'selected':'' ?>>Telugu</option>
    </select>
</form>
</div>

<h2><?= t("pending_user_requests") ?></h2>

<?php if ($msg): ?>
    <div class="message"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($pending_requests->num_rows > 0): ?>
<table>
    <tr>
        <th><?= t("name") ?></th>
        <th><?= t("email") ?></th>
        <th><?= t("role") ?></th>
        <th><?= t("actions") ?></th>
    </tr>
    <?php while ($row = $pending_requests->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars($row['role']) ?></td>
        <td>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button class="approve"><?= t("approve") ?></button>
            </form>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button class="reject"><?= t("reject") ?></button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<?php else: ?>
    <p><?= t("no_pending_requests") ?></p>
<?php endif; ?>

<a href="community_dashboard.php"><?= t("back") ?></a>

</body>
</html>
