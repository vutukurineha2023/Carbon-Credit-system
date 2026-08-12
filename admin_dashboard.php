<?php
session_start();
include 'db.php';

// --- Language Handling ---
$allowed_langs = ['eng', 'hin', 'telugu'];
if (isset($_POST['lang']) && in_array($_POST['lang'], $allowed_langs)) {
    $_SESSION['lang'] = $_POST['lang'];
}
$lang = $_SESSION['lang'] ?? 'eng';
$translations = include "lang/$lang.php";
function t($key) { global $translations; return $translations[$key] ?? $key; }

// --- Check Admin Access ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- Handle Approve/Reject Actions ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];

    if (in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['msg'] = t("user_status_updated") . " $status!";
        header("Location: admin_dashboard.php");
        exit;
    }
}

// --- Fetch Pending Requests ---
$pending_requests = $conn->query("SELECT * FROM users WHERE status='pending' ORDER BY created_at DESC");
$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<title><?= t("admin_dashboard") ?></title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f9f9f9; }
h2 { margin-top: 0; }
.lang-select { text-align: right; margin-bottom: 15px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
th { background: #f2f2f2; }
.btn { padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; }
.approve { background: #28a745; color: #fff; }
.reject { background: #dc3545; color: #fff; }
.msg { padding: 10px; background: #d4edda; color: #155724; margin-bottom: 15px; border-radius: 5px; }
form { display: inline; }
</style>
</head>
<body>

<div class="lang-select">
<form method="POST">
    <select name="lang" onchange="this.form.submit()">
        <option value="eng" <?= $lang=='eng'?'selected':'' ?>>English</option>
        <option value="hin" <?= $lang=='hin'?'selected':'' ?>>Hindi</option>
        <option value="telugu" <?= $lang=='telugu'?'selected':'' ?>>Telugu</option>
    </select>
</form>
</div>

<h2><?= t("pending_user_requests") ?></h2>

<?php if($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($pending_requests->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th><?= t("name") ?></th>
                <th><?= t("email") ?></th>
                <th><?= t("role") ?></th>
                <th><?= t("actions") ?></th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $pending_requests->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="btn approve"><?= t("approve") ?></button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button class="btn reject"><?= t("reject") ?></button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p><?= t("no_pending_requests") ?></p>
<?php endif; ?>

</body>
</html>
