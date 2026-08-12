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

// --- Check Login & Role ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'community') {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$msg = "";

// --- Handle Add Money ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_money'])) {
    $amount = floatval($_POST['amount']);
    if ($amount > 0) {
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);
        $msg = $stmt->execute() ? t("success_add_money") . " ₹$amount!" : t("fail_add_money");
    } else {
        $msg = t("invalid_amount");
    }
}

// --- Fetch Balance ---
$balance = $conn->query("SELECT balance FROM users WHERE id=$user_id")->fetch_assoc()['balance'] ?? 0;

// --- Fetch Transactions ---
$transactions = $conn->query("
    SELECT t.*, u1.name AS buyer_name, u2.name AS seller_name
    FROM transactions t
    JOIN users u1 ON t.buyer_id = u1.id
    JOIN users u2 ON t.seller_id = u2.id
    WHERE t.buyer_id=$user_id OR t.seller_id=$user_id
    ORDER BY t.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<title><?= t("transactions_title") ?></title>
<style>
body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 0; }
.container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
.navbar { background: #2d7dd2; color: #fff; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
.navbar a { color: #fff; text-decoration: none; margin-left: 15px; }
h2, h3 { margin-top: 0; }
.card { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
th { background: #f2f2f2; }
input[type=number] { padding: 8px; width: 150px; margin-right: 10px; }
button { padding: 8px 16px; background: #28a745; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
.popup { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); display:none; justify-content:center; align-items:center; }
.popup-content { background: #fff; padding: 20px; border-radius: 10px; text-align: center; }
.lang-select { text-align: right; margin-bottom: 15px; }
.back-link { display:inline-block; margin-top:10px; text-decoration:none; color:#007bff; }
.back-link:hover { text-decoration:underline; }
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

<div class="navbar">
    <div><?= ucfirst($role) . " - " . htmlspecialchars($_SESSION['name']) ?></div>
    <div>
        <a href="community_dashboard.php"><?= t("dashboard") ?></a>
        <a href="marketplace.php"><?= t("marketplace") ?></a>
        <a href="pending_offers.php"><?= t("pending_offers") ?></a>
        <a href="logout.php"><?= t("logout") ?></a>
    </div>
</div>

<div class="container">
    <h2><?= t("transactions_title") ?></h2>

    <div class="card">
        <h3><?= t("current_balance") ?>: ₹<?= number_format($balance,2) ?></h3>
        <form method="POST">
            <input type="number" step="0.01" name="amount" placeholder="<?= t("enter_amount") ?>" required>
            <button type="submit" name="add_money"><?= t("add_money") ?></button>
        </form>
    </div>

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
            <?php while($t = $transactions->fetch_assoc()): ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><?= htmlspecialchars($t['buyer_name']) ?></td>
                <td><?= htmlspecialchars($t['seller_name']) ?></td>
                <td><?= $t['credits'] ?></td>
                <td><?= number_format($t['price'],2) ?></td>
                <td><?= $t['created_at'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
        <p><?= t("no_transactions") ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="popup" id="popup">
    <div class="popup-content">
        <p id="popup-msg"></p>
        <button onclick="closePopup()"><?= t("ok") ?></button>
    </div>
</div>

<script>
function showPopup(msg){
    document.getElementById("popup-msg").innerText = msg;
    document.getElementById("popup").style.display = "flex";
}
function closePopup(){
    document.getElementById("popup").style.display = "none";
}
<?php if(!empty($msg)): ?>
showPopup("<?= addslashes($msg) ?>");
<?php endif; ?>
</script>

</body>
</html>
