<?php
session_start();
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

// Fetch active communities
$communities = $conn->query("SELECT id, name FROM users WHERE role='community' AND status='approved'");

$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['name'], $_POST['email'], $_POST['password'], $_POST['role'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Validate role
    if (!in_array($role, ['user', 'community', 'corporate'])) {
        $message = t("invalid_role");
    } else {
        $panchayat_id = ($role === 'user') ? intval($_POST['panchayat_id'] ?? 0) : 0;

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, panchayat_id, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ssssi", $name, $email, $password, $role, $panchayat_id);

        if ($stmt->execute()) {
            $message = t("registration_sent");
        } else {
            $message = t("error_occurred") . ": " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<title><?= t("registration_request") ?></title>
<style>
body { font-family: Arial,sans-serif; background:#f9f9f9; padding:20px; }
h2 { text-align:center; margin-bottom:20px; }
form { max-width:500px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
label { display:block; margin-top:10px; font-weight:bold; }
input[type="text"], input[type="email"], input[type="password"], select { width:100%; padding:8px; margin-top:5px; border:1px solid #ccc; border-radius:4px; }
input[type="submit"] { margin-top:15px; padding:10px 20px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; }
.message { text-align:center; margin-bottom:15px; padding:10px; background:#e7f4e4; border-left:4px solid #28a745; }
.lang-select { text-align:right; margin-bottom:15px; }
</style>
<script>
function toggleCommunitySelection() {
    var role = document.getElementById('role').value;
    document.getElementById('communitySelection').style.display = (role === 'user') ? 'block' : 'none';
}
</script>
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

<h2><?= t("registration_request") ?></h2>

<?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <label><?= t("name") ?>:</label>
    <input type="text" name="name" required>

    <label><?= t("email") ?>:</label>
    <input type="email" name="email" required>

    <label><?= t("password") ?>:</label>
    <input type="password" name="password" required>

    <label><?= t("role") ?>:</label>
    <select name="role" id="role" onchange="toggleCommunitySelection()" required>
        <option value=""><?= t("select") ?></option>
        <option value="user"><?= t("user") ?></option>
        <option value="community"><?= t("community") ?></option>
        <option value="corporate"><?= t("corporate") ?></option>
    </select>

    <div id="communitySelection" style="display:none; margin-top:10px;">
        <label><?= t("select_community") ?>:</label>
        <select name="panchayat_id">
            <option value=""><?= t("select_community") ?></option>
            <?php while($row = $communities->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <input type="submit" value="<?= t("send_request") ?>">
</form>

</body>
</html>
