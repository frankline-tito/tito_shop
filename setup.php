<?php
// Run ONCE: http://localhost/shopsystem/setup.php  -- then delete this file.
require_once __DIR__ . '/includes/auth.php';

$count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$created = false;
if ($count === 0) {
    $ins = $pdo->prepare('INSERT INTO users (name, username, password_hash, role) VALUES (?,?,?,?)');
    $ins->execute(['Shop Admin', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    $ins->execute(['Cashier One', 'cashier', password_hash('cashier123', PASSWORD_DEFAULT), 'cashier']);
    $created = true;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Setup</title>
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>"></head>
<body class="login-body">
<div class="login">
  <h1>Setup</h1>
  <?php if ($created): ?>
    <div class="flash ok">Two accounts were created.</div>
    <p>Admin: <b>admin</b> / <b>admin123</b><br>Cashier: <b>cashier</b> / <b>cashier123</b></p>
    <p class="muted">Change these passwords under Staff after you log in, then delete setup.php from the project folder.</p>
    <a class="btn primary" href="<?= app_url('login.php') ?>">Go to log in</a>
  <?php else: ?>
    <div class="flash error">Accounts already exist, so nothing was changed. Delete setup.php.</div>
    <a class="btn" href="<?= app_url('login.php') ?>">Go to log in</a>
  <?php endif; ?>
</div>
</body></html>
