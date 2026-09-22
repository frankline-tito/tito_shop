<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle = 'My account';
$me = current_user();
$error = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$me['id']]);
    $hash = $st->fetchColumn();
    if (!password_verify($current, $hash)) {
        $error = 'Your current password is not correct.';
    } elseif (strlen($new) < 6) {
        $error = 'Your new password needs at least 6 characters.';
    } else {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
        $ok = 'Your password has been changed.';
    }
}
include __DIR__ . '/../includes/header.php';
?>
<section class="panel" style="max-width:420px">
  <h2>Change your password</h2>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="flash ok"><?= e($ok) ?></div><?php endif; ?>
  <form method="post" class="form" autocomplete="off">
    <?= csrf_field() ?>
    <label>Current password <input type="password" name="current_password" required></label>
    <label>New password <input type="password" name="new_password" minlength="6" required></label>
    <button class="btn primary" type="submit">Change password</button>
  </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
