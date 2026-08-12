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

function t($key) {
    global $translations;
    return $translations[$key] ?? $key;
}

// Check login & role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user info and credits
$user_res = $conn->query("
    SELECT u.name, u.email, u.credits, c.name AS community_name
    FROM users u
    LEFT JOIN users c ON u.panchayat_id = c.id
    WHERE u.id = $user_id
");
$user = $user_res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<title><?= t("user_dashboard") ?></title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f9f9f9;
    padding: 20px;
}
.container {
    max-width: 800px;
    margin: auto;
}
h2, h3 {
    margin-top: 0;
}
.info-box {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.credits {
    font-size: 26px;
    font-weight: bold;
    color: #28a745;
}
.lang-select {
    text-align: right;
    margin-bottom: 20px;
}
.back-link {
    display: inline-block;
    margin-top: 10px;
    text-decoration: none;
    color: #007bff;
}
.back-link:hover {
    text-decoration: underline;
}
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
    <h2><?= t("welcome") ?>, <?= htmlspecialchars($user['name']) ?></h2>

    <div class="info-box">
        <h3><?= t("your_community") ?>:</h3>
        <p><?= htmlspecialchars($user['community_name'] ?? t("not_assigned")) ?></p>
    </div>

    <div class="info-box">
        <h3><?= t("total_credits") ?>:</h3>
        <p class="credits"><?= htmlspecialchars($user['credits'] ?? 0) ?> <?= t("credits") ?></p>
    </div>

    
</div>

</body>
</html>
