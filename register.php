<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { redirect('index.php'); }
$old = ['name' => '', 'username' => '', 'security_question' => ''];
$error = ''; $done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name'] = trim($_POST['name'] ?? '');
    $old['username'] = trim($_POST['username'] ?? '');
    $old['security_question'] = trim($_POST['security_question'] ?? '');
    $password = $_POST['password'] ?? '';
    $answer = trim($_POST['security_answer'] ?? '');

    if ($old['name'] === '' || $old['username'] === '' || strlen($password) < 6) {
        $error = 'Enter your name, a username and a password of at least 6 characters.';
    } elseif ($old['security_question'] === '' || $answer === '') {
        $error = 'Set a security question and answer. You will need it if you forget your password.';
    } else {
        try {
            $pdo->prepare('INSERT INTO users (name, username, password_hash, role, status, security_question, security_answer_hash)
                VALUES (?,?,?,?,?,?,?)')
                ->execute([$old['name'], $old['username'], password_hash($password, PASSWORD_DEFAULT), 'cashier', 'pending',
                    $old['security_question'], hash_answer($answer)]);
            $done = true;
        } catch (PDOException $ex) {
            $error = ((int)($ex->errorInfo[1] ?? 0) === 1062) ? 'That username is already taken. Choose another one.' : 'Something went wrong. Try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Request a staff account - <?= e(SHOP_NAME) ?></title>
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="login-body">
  <?php if ($done): ?>
  <div class="login">
    <h1>Request sent</h1>
    <div class="flash ok">Your account request was sent to the admin. You can log in once it has been approved.</div>
    <a class="btn primary" href="<?= app_url('login.php') ?>">Go to log in</a>
  </div>
  <?php else: ?>
  <form class="login" method="post" autocomplete="off">
    <h1>Request a cashier account</h1>
    <p class="muted">An admin must approve your request before you can log in.</p>
    <?php if ($error): ?><div class="flash error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Full name <input type="text" name="name" required value="<?= e($old['name']) ?>"></label>
    <label>Choose a username <input type="text" name="username" required value="<?= e($old['username']) ?>"></label>
    <label>Choose a password <input type="password" name="password" minlength="6" required></label>
    <label>Security question (e.g. "What is your mother's first name?")
      <input type="text" name="security_question" required value="<?= e($old['security_question']) ?>">
    </label>
    <label>Answer <input type="text" name="security_answer" required></label>
    <button class="btn primary" type="submit">Send request</button>
    <p class="muted">Already have an account? <a href="<?= app_url('login.php') ?>">Log in</a></p>
  </form>
  <?php endif; ?>
</body>
</html>
