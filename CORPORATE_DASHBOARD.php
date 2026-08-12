<?php
session_start();

// Prevent caching issues (fix back button / navigation redirect)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Include database
include 'db.php';

// Handle language selection
if (isset($_POST['lang'])) {
    $lang = $_POST['lang'];
    $allowed = ['eng', 'hin', 'telugu'];
    if (in_array($lang, $allowed)) {
        $_SESSION['lang'] = $lang;
    }
}

// Load translations
$lang = $_SESSION['lang'] ?? "eng";
$translations = include "lang/$lang.php";
function t($key) { 
    global $translations; 
    return $translations[$key] ?? $key; 
}

// Check login & role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') != 'corporate') {
    header("Location: login.php");
    exit;
}

$corporate_id = $_SESSION['user_id'];

// Fetch corporate stats
$pooled_credits = $conn->query("SELECT credits FROM users WHERE id=$corporate_id")->fetch_assoc()['credits'] ?? 0;
$total_listings = $conn->query("SELECT COUNT(*) as cnt FROM marketplace WHERE seller_id=$corporate_id")->fetch_assoc()['cnt'] ?? 0;
$total_transactions = $conn->query("SELECT COUNT(*) as cnt FROM transactions WHERE seller_id=$corporate_id OR buyer_id=$corporate_id")->fetch_assoc()['cnt'] ?? 0;

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= t("corporate_dashboard") ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f9f9f9; }
        .container { max-width: 900px; margin: auto; padding: 20px; }
        .navbar { background: #2d7dd2; padding: 15px; color: white; display: flex; justify-content: space-between; }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; }
        .summary { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .popup { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); display:none; justify-content:center; align-items:center; z-index:9999; }
        .popup-content { background:white; padding:20px; border-radius:10px; text-align:center; }
        .popup button { margin-top:10px; padding:8px 16px; border:none; background:#28a745; color:white; border-radius:5px; cursor:pointer; }
    </style>
</head>
<body>

<!-- Language Selector -->
<form method="POST" style="display:inline;">
    <select name="lang" onchange="this.form.submit()">
        <option value="eng" <?= ($lang=='eng')?'selected':'' ?>>English</option>
        <option value="hin" <?= ($lang=='hin')?'selected':'' ?>>Hindi</option>
        <option value="telugu" <?= ($lang=='telugu')?'selected':'' ?>>Telugu</option>
    </select>
</form>

<!-- Navbar -->
<div class="navbar">
    <div><?= t("corporate_dashboard") ?> - <?= htmlspecialchars($_SESSION['name']) ?></div>
    <div>
        <a href="PENDING_OFFERC.php"><?= t("pending_offers") ?></a>
        <a href="MARKETPLACEC.php"><?= t("marketplace") ?></a>
        <a href="TRANSACTIONC.php"><?= t("transaction") ?></a>
        <a href="logout.php"><?= t("logout") ?></a>
    </div>
</div>

<!-- Main container -->
<div class="container">
    <h2><?= t("corporate_statistics") ?></h2>
    <div class="summary">
        <div class="card">
            <h3><?= $pooled_credits ?></h3>
            <p><?= t("total_credits") ?></p>
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
// Popup functions
function showPopup(msg) {
    document.getElementById("popup-msg").innerText = msg;
    document.getElementById("popup").style.display = "flex";
}
function closePopup() {
    document.getElementById("popup").style.display = "none";
}

// Show any session message
<?php if (!empty($msg)) { ?>
    showPopup("<?= addslashes($msg) ?>");
<?php } ?>
</script>

</body>
</html>
