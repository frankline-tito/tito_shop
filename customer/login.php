<?php
require_once __DIR__ . '/../includes/auth.php';
if (current_customer()) { redirect('store/index.php'); }
$pageTitle = 'Log in';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $st = $pdo->prepare('SELECT * FROM customers WHERE phone = ?');
    $st->execute([$phone]);
    $c = $st->fetch();
    if ($c && password_verify($password, $c['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['customer'] = ['id' => (int)$c['id'], 'name' => $c['name'], 'phone' => $c['phone']];
        redirect('store/index.php');
    }
    $error = 'Wrong phone number or password.';
}
include __DIR__ . '/../includes/store_header.php';
?>
<section class="panel" style="max-width:380px">
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Phone number <input type="text" name="phone" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button class="btn primary" type="submit">Log in</button>
  </form>
  <p class="muted">New here? <a href="<?= app_url('customer/register.php') ?>">Create an account</a></p>
</section>
<?php include __DIR__ . '/../includes/store_footer.php'; ?>
