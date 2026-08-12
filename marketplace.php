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
function t($key){ global $translations; return $translations[$key] ?? $key; }

// Check login
if(!isset($_SESSION['user_id'])) header("Location: index.php");

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name'] ?? '');

// Handle POST actions
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Post new offer
    if(isset($_POST['action']) && $_POST['action']==='post_offer'){
        $type = $_POST['type'];
        $credits = intval($_POST['credits']);
        $price = floatval($_POST['price']);

        $stmt = $conn->prepare("INSERT INTO marketplace (seller_id,type,credits,price,status) VALUES (?,?,?,?, 'active')");
        $stmt->bind_param("isid",$user_id,$type,$credits,$price);
        $stmt->execute();

        $_SESSION['msg'] = t("offer_posted");
        header("Location: marketplace.php");
        exit;
    }

    // Send negotiation offer
    if(isset($_POST['action']) && $_POST['action']==='send_offer'){
        $listing_id = intval($_POST['listing_id']);
        $offer_credits = intval($_POST['offer_credits']);
        $offer_price = floatval($_POST['offer_price']);

        $stmt = $conn->prepare("INSERT INTO negotiations (listing_id,buyer_id,offer_credits,offer_price,status) VALUES (?,?,?,?, 'pending')");
        $stmt->bind_param("iiii",$listing_id,$user_id,$offer_credits,$offer_price);
        $stmt->execute();

        $_SESSION['msg'] = t("offer_sent");
        header("Location: marketplace.php");
        exit;
    }

    // Delete listing
    if(isset($_POST['action']) && $_POST['action']==='delete_listing'){
        $listing_id = intval($_POST['listing_id']);
        $stmt = $conn->prepare("UPDATE marketplace SET status='deleted' WHERE id=? AND seller_id=?");
        $stmt->bind_param("ii",$listing_id,$user_id);
        $stmt->execute();
        echo json_encode(["success"=>true,"listing_id"=>$listing_id]);
        exit;
    }

    // Undo delete
    if(isset($_POST['action']) && $_POST['action']==='undo_delete'){
        $listing_id = intval($_POST['listing_id']);
        $stmt = $conn->prepare("UPDATE marketplace SET status='active' WHERE id=? AND seller_id=?");
        $stmt->bind_param("ii",$listing_id,$user_id);
        $stmt->execute();
        echo json_encode(["success"=>true,"listing_id"=>$listing_id]);
        exit;
    }
}

// Fetch listings (exclude own deleted)
$listings = $conn->query("SELECT m.*, u.name FROM marketplace m JOIN users u ON m.seller_id=u.id WHERE m.seller_id!=$user_id AND m.status='active' ORDER BY m.id DESC");
$my_listings = $conn->query("SELECT * FROM marketplace WHERE seller_id=$user_id ORDER BY id DESC");

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<title><?= t("marketplaceCT") ?></title>
<style>
body { font-family: Arial,sans-serif; margin:0; background:#f4f6f8; }
.container { max-width:1000px; margin:auto; padding:20px; }
.lang-select { text-align:right; margin-bottom:10px; }
h2,h3 { text-align:center; }
form { margin-bottom:20px; }
input,select,button { padding:8px; margin:5px; }
button { background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; }
table { width:100%; border-collapse:collapse; margin-bottom:30px; }
th,td { padding:10px; border:1px solid #ccc; text-align:left; }
.popup { position:fixed; top:0;left:0;right:0;bottom:0; display:none; justify-content:center; align-items:center; background:rgba(0,0,0,0.5); z-index:9999; }
.popup-content { background:white; padding:20px 30px; border-radius:10px; text-align:center; }
.popup-content button { margin-top:10px; }
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

<div class="container">
<h2><?= t("marketplace") ?></h2>

<!-- Post new offer -->
<form method="POST">
<input type="hidden" name="action" value="post_offer">
<select name="type">
    <option value="sell"><?= t("sell") ?></option>
    <option value="buy"><?= t("buy") ?></option>
</select>
<input type="number" name="credits" placeholder="<?= t("credits") ?>" required>
<input type="number" step="0.01" name="price" placeholder="<?= t("price") ?>" required>
<button type="submit"><?= t("postoffer") ?></button>
</form>

<h3><?= t("listings") ?></h3>
<table>
<tr><th>S.No</th><th><?= t("user") ?></th><th><?= t("type") ?></th><th><?= t("credits") ?></th><th><?= t("price") ?></th><th><?= t("action") ?></th></tr>
<?php $sn=1; while($row=$listings->fetch_assoc()){ ?>
<tr>
<td><?= $sn++; ?></td>
<td><?= htmlspecialchars($row['name']); ?></td>
<td><?= ucfirst(htmlspecialchars($row['type'])); ?></td>
<td><?= htmlspecialchars($row['credits']); ?></td>
<td><?= htmlspecialchars($row['price']); ?></td>
<td>
<form method="POST" style="display:inline;">
<input type="hidden" name="action" value="send_offer">
<input type="hidden" name="listing_id" value="<?= $row['id'] ?>">
<input type="number" name="offer_credits" placeholder="<?= t("credits") ?>" required>
<input type="number" step="0.01" name="offer_price" placeholder="<?= t("price") ?>" required>
<button type="submit"><?= t("sendoffer") ?></button>
</form>
</td>
</tr>
<?php } ?>
</table>

<h3><?= t("my_listings") ?></h3>
<table>
<tr><th>S.No</th><th><?= t("type") ?></th><th><?= t("credits") ?></th><th><?= t("price") ?></th><th><?= t("status") ?></th><th><?= t("action") ?></th></tr>
<?php $sn=1; while($my=$my_listings->fetch_assoc()){ 
$deleted = $my['status']==='deleted' ? '1' : '0'; ?>
<tr id="mylist-<?= $my['id']; ?>" data-deleted="<?= $deleted ?>">
<td><?= $sn++; ?></td>
<td><?= ucfirst(htmlspecialchars($my['type'])); ?></td>
<td><?= htmlspecialchars($my['credits']); ?></td>
<td><?= htmlspecialchars($my['price']); ?></td>
<td><?= ucfirst(htmlspecialchars($my['status'])); ?></td>
<td>
<?php if($my['status']==='active'){ ?>
<button class="delete-btn" data-id="<?= $my['id']; ?>">🗑 <?= t("delete") ?></button>
<?php } elseif($my['status']==='deleted'){ ?>
<button class="delete-btn" data-id="<?= $my['id']; ?>">↩ <?= t("undo") ?></button>
<?php } ?>
</td>
</tr>
<?php } ?>
</table>

<a href="community_dashboard.php"><?= t("back") ?></a>
</div>

<!-- Popup -->
<div id="popup" class="popup">
<div class="popup-content" id="popup-content">
<p id="popup-msg"></p>
</div>
</div>

<script>
const popup=document.getElementById("popup");
const popupMsg=document.getElementById("popup-msg");

function showPopup(msg){
    popupMsg.innerHTML=msg+'<br><br><button onclick="closePopup()">OK</button>';
    popup.style.display="flex";
}

function closePopup(){ popup.style.display="none"; }

function undoDelete(id,row,btn){
    fetch("",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:`action=undo_delete&listing_id=${id}`})
    .then(res=>res.json()).then(data=>{
        if(data.success){
            row.dataset.deleted="0";
            row.querySelector("td:nth-child(5)").innerText="active";
            showPopup("✅ Listing restored!");
            btn.remove();
        }
    });
}

function confirmDelete(id,row,btn){
    popupMsg.innerHTML=`🗑️ <?= t("confirm_delete") ?><br><br><button id="confirm-delete"><?= t("yes") ?></button><button onclick="closePopup()"><?= t("no") ?></button>`;
    popup.style.display="flex";
    document.getElementById("confirm-delete").onclick=()=>{
        fetch("",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:`action=delete_listing&listing_id=${id}`})
        .then(res=>res.json()).then(data=>{
            if(data.success){
                row.dataset.deleted="1";
                row.querySelector("td:nth-child(5)").innerText="deleted";
                btn.innerText="↩ <?= t("undo") ?>";
                closePopup();
                btn.onclick=()=>undoDelete(id,row,btn);
            }
        });
    };
}

document.querySelectorAll(".delete-btn").forEach(btn=>{
    const id=btn.dataset.id;
    const row=document.getElementById(`mylist-${id}`);
    const isDeleted=row.dataset.deleted==="1";
    if(isDeleted) btn.onclick=()=>undoDelete(id,row,btn);
    else btn.addEventListener("click",()=>confirmDelete(id,row,btn));
});

<?php if(!empty($msg)){ ?>
showPopup("<?= addslashes($msg) ?>");
<?php } ?>
</script>
</body>
</html>
