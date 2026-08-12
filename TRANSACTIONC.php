<?php
session_start();

// Prevent caching/back-button redirect issues
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'db.php';

// Handle language selection
if (isset($_POST['lang'])) {
    $lang = $_POST['lang'];
    $allowed = ['eng', 'hin', 'telugu'];
    if (in_array($lang, $allowed)) $_SESSION['lang'] = $lang;
}

// Load translations
$lang = $_SESSION['lang'] ?? "eng";
$translations = include "lang/$lang.php";
function t($key) { global $translations; return $translations[$key] ?? $key; }

// Check login and role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['corporate'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$msg = "";

// Handle adding money
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_money'])) {
    $amount = floatval($_POST['amount']);
    if ($amount > 0) {
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);
        if ($stmt->execute()) {
            $msg = t("success_add_money") . " ₹" . number_format($amount,2) . "!";
        } else {
            $msg = t("fail_add_money");
        }
    } else {
        $msg = t("invalid_amount");
    }
}

// Fetch current balance
$balance = $conn->query("SELECT balance FROM users WHERE id=$user_id")->fetch_assoc()['balance'] ?? 0;

// Fetch transactions
$transactions = $conn->query("
    SELECT t.*, u1.name AS buyer_name, u2.name AS seller_name 
    FROM transactions t
    JOIN users u1 ON t.buyer_id=u1.id
    JOIN users u2 ON t.seller_id=u2.id
    WHERE t.buyer_id=$user_id OR t.seller_id=$user_id
    ORDER BY t.created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= t("transactions_title") ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin:0; padding:0; }
        .container { max-width: 900px; margin:auto; padding:20px; }
        .navbar { background: #2d7dd2; color:white; padding:15px; display:flex; justify-content:space-between; align-items:center; }
        .navbar a { color:white; text-decoration:none; margin-left:10px; }
        h2 { margin-top:0; }
        .card { background:white; padding:20px; border-radius:10px; margin-bottom:20px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
        table { width:100%; border-collapse:collapse; margin-top:10px; background:white; box-shadow:0 2px 6px rgba(0,0,0,0.05); }
        th, td { padding:10px; border:1px solid #ddd; text-align:left; }
        th { background:#f2f2f2; }
        input[type=number] { padding:8px; width:150px; border-radius:5px; border:1px solid #ccc; }
        button { padding:8px 16px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; margin-left:5px; }
        .popup { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); display:none; justify-content:center; align-items:center; z-index:999; }
        .popup-content { background:white; padding:20px; border-radius:10px; text-align:center; width:300px; }
        select { padding:5px; margin-bottom:20px; }
        a { display:inline-block; margin-top:20px; text-decoration:none; color:#2d7dd2; }
    </style>
</head>
<body>

<!-- Language selector -->
<form method="POST" style="display:inline;">
    <select name="lang" onchange="this.form.submit()">
        <option value="eng" <?= ($lang=='eng')?'selected':'' ?>>English</option>
        <option value="hin" <?= ($lang=='hin')?'selected':'' ?>>Hindi</option>
        <option value="telugu" <?= ($lang=='telugu')?'selected':'' ?>>Telugu</option>
    </select>
</form>

<div class="navbar">
    <div><?= ucfirst($role) . " - " . htmlspecialchars($_SESSION['name']) ?></div>
    <div>
        <a href="CORPORATE_DASHBOARD.php"><?= t("dashboard") ?></a>
        <a href="MARKETPLACEC.php"><?= t("marketplace") ?></a>
        <a href="PENDING_OFFERC.php"><?= t("pending_offers") ?></a>
        <a href="logout.php"><?= t("logout") ?></a>
    </div>
</div>

<div class="container">
    <h2><?= t("transactions_title") ?></h2>

    <!-- Balance and Add Money -->
    <div class="card">
        <h3><?= t("current_balance") ?>: ₹<?= number_format($balance,2) ?></h3>
        <form method="POST">
            <input type="number" step="0.01" name="amount" placeholder="<?= t("enter_amount") ?>" required>
            <button type="submit" name="add_money"><?= t("add_money") ?></button>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <h3><?= t("previous_transactions") ?></h3>
        <?php if($transactions->num_rows > 0): ?>
        <table>
            <tr>
                <th><?= t("id") ?></th>
                <th><?= t("buyer") ?></th>
                <th><?= t("seller") ?></th>
                <th><?= t("credits") ?></th>
                <th><?= t("price") ?></th>
                <th><?= t("date") ?></th>
            </tr>
            <?php while($t=$transactions->fetch_assoc()): ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><?= htmlspecialchars($t['buyer_name']) ?></td>
                <td><?= htmlspecialchars($t['seller_name']) ?></td>
                <td><?= $t['credits'] ?></td>
                <td>₹<?= number_format($t['price'],2) ?></td>
                <td><?= $t['created_at'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
        <p><?= t("no_transactions") ?></p>
        <?php endif; ?>
    </div>
</div>

<a href="CORPORATE_DASHBOARD.php"><?= t("back") ?></a>

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
<?php if(!empty($msg)){ ?>
showPopup("<?= addslashes($msg) ?>");
<?php } ?>
</script>

</body>
</html>
