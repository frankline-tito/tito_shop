<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { redirect('index.php'); }

$stage = $_POST['stage'] ?? 'find';
$username = trim($_POST['username'] ?? '');
$error = ''; $question = null; $done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($stage === 'find') {
        $st = $pdo->prepare('SELECT security_question FROM users WHERE username = ?');
        $st->execute([$username]);
        $q = $st->fetchColumn();
        if ($q) { $question = $q; $stage = 'answer'; }
        else { $error = 'No account with a security question matches that username.'; }
    } elseif ($stage === 'answer') {
        $answer = trim($_POST['security_answer'] ?? '');
        $password = $_POST['password'] ?? '';
        $st = $pdo->prepare('SELECT id, security_answer_hash FROM users WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch();
        if (!$u || !verify_answer($answer, $u['security_answer_hash'])) {
            $error = 'That answer does not match. Try again.';
            $st = $pdo->prepare('SELECT security_question FROM users WHERE username = ?');
            $st->execute([$username]);
            $question = $st->fetchColumn();
            $stage = 'answer';
        } elseif (strlen($password) < 6) {
            $error = 'Your new password needs at least 6 characters.';
            $question = 'shown above';
            $stage = 'answer';
            $st = $pdo->prepare('SELECT security_question FROM users WHERE username = ?');
            $st->execute([$username]);
            $question = $st->fetchColumn();
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot password - <?= e(SHOP_NAME) ?></title>
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="login-body">
  <?php if ($done): ?>
  <div class="login">
    <h1>Password changed</h1>
    <div class="flash ok">You can now log in with your new password.</div>
    <a class="btn primary" href="<?= app_url('login.php') ?>">Go to log in</a>
  </div>
  <?php else: ?>
  <form class="login" method="post" autocomplete="off">
    <h1>Forgot password</h1>
    <?php if ($error): ?><div class="flash error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <?php if ($stage === 'answer' && $question): ?>
      <input type="hidden" name="stage" value="answer">
      <input type="hidden" name="username" value="<?= e($username) ?>">
      <label>Username <input type="text" value="<?= e($username) ?>" disabled></label>
      <label><?= e($question) ?> <input type="text" name="security_answer" required autofocus></label>
      <label>New password <input type="password" name="password" minlength="6" required></label>
      <button class="btn primary" type="submit">Change password</button>
    <?php else: ?>
      <input type="hidden" name="stage" value="find">
      <label>Username <input type="text" name="username" required autofocus value="<?= e($username) ?>"></label>
      <button class="btn primary" type="submit">Continue</button>
    <?php endif; ?>
    <p class="muted"><a href="<?= app_url('login.php') ?>">Back to log in</a></p>
  </form>
  <?php endif; ?>
</body>
</html>
