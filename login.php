<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Include DB
include 'db.php';

// Handle language selection
$allowed_langs = ['eng', 'hin', 'telugu'];
if (isset($_POST['lang']) && in_array($_POST['lang'], $allowed_langs)) {
    $_SESSION['lang'] = $_POST['lang'];
}

// Default language = English
$lang = $_SESSION['lang'] ?? 'eng';
$translations = include "lang/$lang.php";
function t($key) { global $translations; return $translations[$key] ?? $key; }

$error = "";

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {

            // Check approval status
            if ($user['role'] === 'user' && $user['status'] !== 'approved') {
                $error = t("pending_community_approval");
            } elseif (in_array($user['role'], ['community','corporate']) && $user['status'] !== 'approved') {
                $error = t("pending_admin_approval");
            } else {
                // Login successful
                session_regenerate_id(true); // Security: regenerate session ID
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin': header("Location: admin_dashboard.php"); break;
                    case 'community': header("Location: community_dashboard.php"); break;
                    case 'corporate': header("Location: corporate_dashboard.php"); break;
                    case 'user': header("Location: user_dashboard.php"); break;
                    default: $error = t("unknown_role");
                }
                exit;
            }
        } else {
            $error = t("incorrect_password");
        }
    } else {
        $error = t("email_not_found");
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t("login") ?></title>
    <style>
        body { font-family: Arial, sans-serif; background:#f0f2f5; display:flex; justify-content:center; align-items:center; height:100vh; margin:0; }
        .login-box { background:white; padding:30px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); width:350px; }
        h2 { text-align:center; margin-bottom:20px; }
        form { display:flex; flex-direction:column; }
        label { margin-top:10px; margin-bottom:5px; }
        input[type=email], input[type=password], select { padding:10px; border:1px solid #ccc; border-radius:5px; }
        input[type=submit] { margin-top:20px; padding:10px; background:#2d7dd2; color:white; border:none; border-radius:5px; cursor:pointer; }
        input[type=submit]:hover { background:#1a5fa8; }
        .error { color:red; margin-top:10px; text-align:center;  }
        .lang-select { margin-bottom:15px; text-align:center; width:80px; align-items:right; }
        p { text-align:center; margin-top:15px; }
        a { color:#2d7dd2; text-decoration:none; }
    </style>
</head>
<body>

<div class="login-box">

    <!-- Language Selector -->
<div class="langsel">
    <form method="POST" class="lang-select">
        <select name="lang" onchange="this.form.submit()">
            <option value="eng" <?= ($lang==='eng')?'selected':'' ?>>English</option>
            <option value="hin" <?= ($lang==='hin')?'selected':'' ?>>Hindi</option>
            <option value="telugu" <?= ($lang==='telugu')?'selected':'' ?>>Telugu</option>
        </select>
    </form>
</div>

    <h2><?= t("login") ?></h2>

    <?php if($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label><?= t("email") ?>:</label>
        <input type="email" name="email" required placeholder="<?= t("email") ?>">

        <label><?= t("password") ?>:</label>
        <input type="password" name="password" required placeholder="<?= t("password") ?>">

        <input type="submit" value="<?= t("login") ?>">
    </form>

    <p><?= t("dont_have_account") ?> <a href="signup.php"><?= t("register_here") ?></a></p>
</div>

</body>
</html>
