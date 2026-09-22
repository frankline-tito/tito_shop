<?php
require_once __DIR__ . '/../includes/auth.php';
if (current_customer()) { redirect('store/index.php'); }
$pageTitle = 'Create your account';
$error = ''; $old = ['name' => '', 'phone' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name'] = trim($_POST['name'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($old['name'] === '' || $old['phone'] === '' || strlen($password) < 6) {
        $error = 'Enter your name, phone number and a password of at least 6 characters.';
    } else {
        try {
            $pdo->prepare('INSERT INTO customers (name, phone, email, password_hash) VALUES (?,?,?,?)')
                ->execute([$old['name'], $old['phone'], $old['email'] ?: null, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int)$pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['customer'] = ['id' => $id, 'name' => $old['name'], 'phone' => $old['phone']];
            redirect('store/index.php');
        } catch (PDOException $ex) {
            $error = ((int)($ex->errorInfo[1] ?? 0) === 1062) ? 'That phone number is already registered. Try logging in.' : 'Something went wrong. Try again.';
        }
    }
}
include __DIR__ . '/../includes/store_header.php';
?>
<section class="panel" style="max-width:380px">
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form" autocomplete="off">
    <?= csrf_field() ?>
    <label>Full name <input type="text" name="name" required value="<?= e($old['name']) ?>"></label>
    <label>Phone number <input type="text" name="phone" required value="<?= e($old['phone']) ?>"></label>
    <label>Email (optional) <input type="email" name="email" value="<?= e($old['email']) ?>"></label>
    <label>Password <input type="password" name="password" minlength="6" required></label>
    <button class="btn primary" type="submit">Create account</button>
  </form>
  <p class="muted">Already registered? <a href="<?= app_url('customer/login.php') ?>">Log in</a></p>
</section>
<?php include __DIR__ . '/../includes/store_footer.php'; ?>
