<?php
session_start();

// Prevent caching for session security
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include 'db.php';

// Handle language selection
$allowed_langs = ['eng', 'hin', 'telugu'];
if (isset($_POST['lang']) && in_array($_POST['lang'], $allowed_langs)) {
    $_SESSION['lang'] = $_POST['lang'];
}

// Default language
$lang = $_SESSION['lang'] ?? 'eng';
$translations = include "lang/$lang.php";
function t($key) { global $translations; return $translations[$key] ?? $key; }

// Check login & role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'community') {
    header("Location: login.php");
    exit;
}

$community_id = $_SESSION['user_id'];
$community_name = htmlspecialchars($_SESSION['name']);

// Fetch stats
$member_count = $conn->query("SELECT COUNT(*) AS total FROM users WHERE panchayat_id=$community_id AND status='approved'")->fetch_assoc()['total'];
$pooled_credits = $conn->query("SELECT SUM(credits) AS total FROM users WHERE panchayat_id=$community_id")->fetch_assoc()['total'] ?? 0;
$total_listings = $conn->query("SELECT COUNT(*) AS cnt FROM marketplace WHERE seller_id=$community_id")->fetch_assoc()['cnt'];
$total_transactions = $conn->query("SELECT COUNT(*) AS cnt FROM transactions WHERE seller_id=$community_id OR buyer_id=$community_id")->fetch_assoc()['cnt'];

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t("community_dashboard") ?></title>
    <style>
        body { font-family: Arial,sans-serif; margin:0; background:#f4f6f8; }
        .navbar { display:flex; justify-content:space-between; align-items:center; background:#2d7dd2; padding:15px; color:white; }
        .navbar a { color:white; text-decoration:none; margin-left:15px; font-weight:bold; }
        .container { max-width: 1000px; margin: auto; padding:20px; }
        .lang-select { text-align:right; margin-bottom:10px; }
        .summary { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:20px; margin-top:20px; }
        .card { background:white; padding:20px; border-radius:10px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
        .card h3 { font-size:2rem; margin:0; }
        .card p { margin-top:5px; font-weight:bold; color:#555; }
        .popup { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:none; justify-content:center; align-items:center; }
        .popup-content { background:white; padding:25px; border-radius:10px; text-align:center; }
        .popup-content button { margin-top:15px; padding:10px 20px; border:none; background:#28a745; color:white; border-radius:5px; cursor:pointer; }
        h2 { text-align:center; margin-bottom:20px; }
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

<div class="navbar">
    <div><?= t("community_dashboard") ?> - <?= $community_name ?></div>
    <div>
        <a href="pending_users.php"><?= t("pending_user_requests") ?></a>
        <a href="pending_offers.php"><?= t("pending_offers") ?></a>
        <a href="marketplace.php"><?= t("marketplace") ?></a>
        <a href="transactions.php"><?= t("transactions") ?></a>
        <a href="logout.php"><?= t("logout") ?></a>
    </div>
</div>

<div class="container">
    <h2><?= t("community_statistics") ?></h2>
    <div class="summary">
        <div class="card">
            <h3><?= $member_count ?></h3>
            <p><?= t("approved_members") ?></p>
        </div>
        <div class="card">
            <h3><?= $pooled_credits ?></h3>
            <p><?= t("total_credits_pooled") ?></p>
        </div>
        <div class="card">
            <h3><?= $total_listings ?></h3>
            <p><?= t("marketplace_listings") ?></p>
        </div>
        <div class="card">
            <h3><?= $total_transactions ?></h3>
            <p><?= t("total_transactions") ?></p>
        </div>
    </div>
</div>

<!-- Popup for messages -->
<div class="popup" id="popup">
    <div class="popup-content">
        <p id="popup-msg"></p>
        <button onclick="closePopup()"><?= t("ok") ?></button>
    </div>
</div>

<script>
function showPopup(msg) {
    document.getElementById("popup-msg").innerText = msg;
    document.getElementById("popup").style.display = "flex";
}
function closePopup() {
    document.getElementById("popup").style.display = "none";
}
<?php if(!empty($msg)) { ?>
    showPopup("<?= addslashes($msg) ?>");
<?php } ?>
</script>

</body>
</html>
