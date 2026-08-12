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

// Check login
if (!isset($_SESSION['user_id'])) header("Location: login.php");
$user_id = $_SESSION['user_id'];

// Handle accept/reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['negotiation_id'], $_POST['response'])) {
    $negotiation_id = intval($_POST['negotiation_id']);
    $response = $_POST['response']; // accepted / rejected

    // Update negotiation status
    $stmt = $conn->prepare("UPDATE negotiations SET status=? WHERE id=?");
    $stmt->bind_param("si", $response, $negotiation_id);
    $stmt->execute();

    // If accepted, mark listing as completed and assign buyer
    if ($response === "accepted") {
        $stmt2 = $conn->prepare("
            UPDATE marketplace 
            SET status='completed', buyer_id=(SELECT buyer_id FROM negotiations WHERE id=?)
            WHERE id=(SELECT listing_id FROM negotiations WHERE id=?)
        ");
        $stmt2->bind_param("ii", $negotiation_id, $negotiation_id);
        $stmt2->execute();
    }

    $_SESSION['msg'] = t("offer_response_msg") . " " . t($response) . "!";
    header("Location: PENDING_OFFERC.php");
    exit;
}

// Fetch pending offers for user's listings
$offers = $conn->query("
    SELECT n.*, u.name AS buyer_name, m.type, m.credits AS listing_credits, m.price AS listing_price
    FROM negotiations n
    JOIN users u ON n.buyer_id = u.id
    JOIN marketplace m ON n.listing_id = m.id
    WHERE m.seller_id = $user_id AND n.status = 'pending'
    ORDER BY n.created_at DESC
");

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= t("pending_offers") ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f2f2f2; }
        button { padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; }
        .accept { background: #28a745; color: white; }
        .reject { background: #dc3545; color: white; }
        .message { padding: 10px; margin-bottom: 10px; background: #e7f4e4; border-left: 4px solid #28a745; }
        select { padding: 5px; margin-bottom: 20px; }
        a { display: inline-block; margin-top: 20px; text-decoration: none; color: #2d7dd2; }
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

<h2><?= t("pending_offers") ?></h2>

<?php if ($msg): ?>
    <div class="message"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($offers->num_rows > 0): ?>
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
        <?php while ($offer = $offers->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($offer['buyer_name']); ?></td>
            <td><?= ucfirst(htmlspecialchars($offer['type'])); ?></td>
            <td><?= $offer['listing_credits']; ?></td>
            <td><?= $offer['listing_price']; ?></td>
            <td><?= $offer['offer_credits']; ?></td>
            <td><?= $offer['offer_price']; ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="negotiation_id" value="<?= $offer['id'] ?>">
                    <button class="accept" name="response" value="accepted">✅ <?= t("accept") ?></button>
                    <button class="reject" name="response" value="rejected">❌ <?= t("reject") ?></button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
<?php else: ?>
    <p><?= t("no_pending_offers") ?></p>
<?php endif; ?>

<a href="CORPORATE_DASHBOARD.php"><?= t("back") ?></a>

</body>
</html>
