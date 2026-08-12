<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include 'db.php';

// Handle language selection
$allowed_langs = ['eng','hin','telugu'];
if(isset($_POST['lang']) && in_array($_POST['lang'],$allowed_langs)){
    $_SESSION['lang'] = $_POST['lang'];
}

// Default language
$lang = $_SESSION['lang'] ?? 'eng';
$translations = include "lang/$lang.php";

function t($key, $params=[]){
    global $translations;
    $str = $translations[$key] ?? $key;
    if(!empty($params)) $str = vsprintf($str, $params);
    return $str;
}

// Check login
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle accept/reject POST
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['negotiation_id'], $_POST['response'])){
    $negotiation_id = intval($_POST['negotiation_id']);
    $response = $_POST['response']; // accepted / rejected

    // Update negotiation status
    $stmt = $conn->prepare("UPDATE negotiations SET status=? WHERE id=?");
    $stmt->bind_param("si",$response,$negotiation_id);
    $stmt->execute();

    if($response==='accepted'){
        // Complete listing
        $stmt2 = $conn->prepare("
            UPDATE marketplace 
            SET status='completed', buyer_id=(SELECT buyer_id FROM negotiations WHERE id=?)
            WHERE id=(SELECT listing_id FROM negotiations WHERE id=?)
        ");
        $stmt2->bind_param("ii",$negotiation_id,$negotiation_id);
        $stmt2->execute();

        // Handle balance & credits transfer
        $stmt3 = $conn->prepare("
            SELECT n.buyer_id, m.seller_id, n.offer_price, n.offer_credits
            FROM negotiations n
            JOIN marketplace m ON n.listing_id = m.id
            WHERE n.id=? LIMIT 1
        ");
        $stmt3->bind_param("i",$negotiation_id);
        $stmt3->execute();
        $stmt3->bind_result($buyer_id,$seller_id,$offer_price,$offer_credits);
        $stmt3->fetch();
        $stmt3->close();

        if($buyer_id && $seller_id && $offer_price>0){
            // Check buyer balance
            $stmt4 = $conn->prepare("SELECT balance, credits FROM users WHERE id=?");
            $stmt4->bind_param("i",$buyer_id);
            $stmt4->execute();
            $stmt4->bind_result($buyer_balance,$buyer_credits);
            $stmt4->fetch();
            $stmt4->close();

            if($buyer_balance >= $offer_price){
                // Deduct balance & add credits for buyer
                $stmt5 = $conn->prepare("UPDATE users SET balance = balance - ?, credits = credits + ? WHERE id=?");
                $stmt5->bind_param("dii",$offer_price,$offer_credits,$buyer_id);
                $stmt5->execute();

                // Credit balance & deduct credits for seller
                $stmt6 = $conn->prepare("UPDATE users SET balance = balance + ?, credits = credits - ? WHERE id=?");
                $stmt6->bind_param("dii",$offer_price,$offer_credits,$seller_id);
                $stmt6->execute();

                // Insert transaction
                $stmt7 = $conn->prepare("INSERT INTO transactions (buyer_id, seller_id, credits, price, created_at) VALUES (?,?,?,?, NOW())");
                $stmt7->bind_param("iiid",$buyer_id,$seller_id,$offer_credits,$offer_price);
                $stmt7->execute();
            }else{
                $_SESSION['msg'] = t("insufficient_balance");
                header("Location: pending_offers.php");
                exit;
            }
        }
    }

    $_SESSION['msg'] = t("offer_response_msg",[$response]);
    header("Location: pending_offers.php");
    exit;
}

// Fetch pending offers for listings owned by this user
$offers = $conn->query("
    SELECT n.*, u.name AS buyer_name, m.type, m.credits AS listing_credits, m.price AS listing_price
    FROM negotiations n
    JOIN users u ON n.buyer_id = u.id
    JOIN marketplace m ON n.listing_id = m.id
    WHERE m.seller_id = $user_id AND n.status='pending'
    ORDER BY n.created_at DESC
");

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<title><?= t("pending_offers") ?></title>
<style>
body { font-family: Arial,sans-serif; padding:20px; background:#f9f9f9; }
.lang-select { text-align:right; margin-bottom:10px; }
h2 { text-align:center; margin-bottom:20px; }
table { width:100%; border-collapse: collapse; margin-top:20px; }
th, td { border:1px solid #ccc; padding:10px; text-align:left; }
th { background:#f2f2f2; }
button { padding:5px 10px; border:none; border-radius:5px; cursor:pointer; }
.accept { background:#28a745; color:white; }
.reject { background:#dc3545; color:white; }
.message { padding:10px; margin-bottom:10px; background:#e7f4e4; border-left:4px solid #28a745; }
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

<h2><?= t("pending_offers") ?></h2>

<?php if($msg): ?>
<div class="message"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if($offers->num_rows>0): ?>
<table>
<tr>
    <th><?= t("buyer") ?></th>
    <th><?= t("listing_type") ?></th>
    <th><?= t("listing_credits") ?></th>
    <th><?= t("listing_price") ?></th>
    <th><?= t("offer_credits") ?></th>
    <th><?= t("offer_price") ?></th>
    <th><?= t("action") ?></th>
</tr>
<?php while($offer=$offers->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($offer['buyer_name']); ?></td>
<td><?= ucfirst($offer['type']); ?></td>
<td><?= $offer['listing_credits']; ?></td>
<td><?= $offer['listing_price']; ?></td>
<td><?= $offer['offer_credits']; ?></td>
<td><?= $offer['offer_price']; ?></td>
<td>
<form method="POST" style="display:inline;">
    <input type="hidden" name="negotiation_id" value="<?= $offer['id'] ?>">
    <button class="accept" name="response" value="accepted"><?= t("accept") ?></button>
    <button class="reject" name="response" value="rejected"><?= t("reject") ?></button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p><?= t("no_offers") ?></p>
<?php endif; ?>

<a href="community_dashboard.php"><?= t("back") ?></a>

</body>
</html>
