<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { redirect('index.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        if ($u['status'] === 'pending') {
            $error = 'Your account is still waiting for admin approval.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'username' => $u['username'], 'role' => $u['role']];
            redirect('index.php');
        }
    } else {
        $error = 'Wrong username or password. Check both and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in - <?= e(SHOP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="login-body">
  <form class="login" method="post" autocomplete="off">
    <h1><?= e(SHOP_NAME) ?></h1>
    <p class="muted">Log in to record sales and manage stock.</p>
    <?php if ($error): ?><div class="flash error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Username
      <input type="text" name="username" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button class="btn primary" type="submit">Log in</button>
    <p class="muted">
      <a href="<?= app_url('forgot-password.php') ?>">Forgot password?</a> &middot;
      <a href="<?= app_url('register.php') ?>">Request a cashier account</a>
    </p>
    <p class="muted"><a href="<?= app_url('store/index.php') ?>">Shopping? Browse our goods</a></p>
  </form>
</body>
</html>
