<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Staff';
$activeNav = 'users';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'cashier';
            $question = trim($_POST['security_question'] ?? '');
            $answer = trim($_POST['security_answer'] ?? '');
            if ($name === '' || $username === '' || strlen($password) < 6) {
                flash('error', 'Enter a name, a username and a password of at least 6 characters.');
            } elseif ($question === '' || $answer === '') {
                flash('error', 'Set a security question and answer, so this person can reset their own password later.');
            } else {
                $pdo->prepare('INSERT INTO users (name, username, password_hash, role, status, security_question, security_answer_hash)
                    VALUES (?,?,?,?,?,?,?)')
                    ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, 'active', $question, hash_answer($answer)]);
                flash('ok', $name . ' can now log in as ' . $role . '.');
            }
        } elseif ($action === 'approve') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
            flash('ok', 'Account approved. They can now log in.');
        } elseif ($action === 'reject') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM users WHERE id = ? AND status = 'pending'")->execute([$id]);
            flash('ok', 'Request rejected and removed.');
        } elseif ($action === 'reset') {
            $id = (int)($_POST['id'] ?? 0);
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 6) {
                flash('error', 'A new password needs at least 6 characters.');
            } else {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                flash('ok', 'Password changed.');
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$me['id']) {
                flash('error', 'You cannot delete the account you are logged in with.');
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                flash('ok', 'Account deleted.');
            }
        }
    } catch (PDOException $ex) {
        if ((int)($ex->errorInfo[1] ?? 0) === 1062) {
            flash('error', 'That username is already taken. Choose another one.');
        } elseif ((int)($ex->errorInfo[1] ?? 0) === 1451) {
            flash('error', 'This person has recorded sales, so the account cannot be deleted.');
        } else {
            flash('error', 'Something went wrong. Try again.');
        }
    }
    redirect('admin/users.php');
}

$pending = $pdo->query("SELECT id, name, username, role, created_at FROM users WHERE status = 'pending' ORDER BY created_at")->fetchAll();
$users = $pdo->query("SELECT id, name, username, role, created_at FROM users WHERE status = 'active' ORDER BY role, name")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<?php if ($pending): ?>
<section class="panel">
  <h2>Waiting for approval</h2>
  <table class="table"><thead><tr><th>Name</th><th>Username</th><th>Requested role</th><th>Requested</th><th></th></tr></thead><tbody>
  <?php foreach ($pending as $p): ?>
    <tr>
      <td><b><?= e($p['name']) ?></b></td>
      <td><?= e($p['username']) ?></td>
      <td><?= e($p['role']) ?></td>
      <td><?= e(date('j M Y', strtotime($p['created_at']))) ?></td>
      <td class="actions">
        <form class="inline" method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn small primary" type="submit">Approve</button>
        </form>
        <form class="inline" method="post" onsubmit="return confirm('Reject and delete this request?');">
          <?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn small danger" type="submit">Reject</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</section>
<?php endif; ?>

<div class="split">
  <section class="panel wide">
    <div class="scroll">
    <table class="table">
      <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>New password</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><b><?= e($u['name']) ?></b></td>
          <td><?= e($u['username']) ?></td>
          <td><?= e($u['role']) ?></td>
          <td>
            <form class="inline" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="reset">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="password" name="password" minlength="6" placeholder="6+ characters" class="mid" aria-label="New password for <?= e($u['name']) ?>">
              <button class="btn small" type="submit">Change</button>
            </form>
          </td>
          <td class="actions">
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
            <form class="inline" method="post" onsubmit="return confirm('Delete this account?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn small danger" type="submit">Delete</button>
            </form>
            <?php else: ?><small class="muted">You</small><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
  <aside class="stack">
    <section class="panel">
      <h2>Add a staff account</h2>
      <p class="muted">Staff can also request their own account from the login page; requests appear above for you to approve.</p>
      <form method="post" class="form" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>Full name <input type="text" name="name" required></label>
        <label>Username <input type="text" name="username" required></label>
        <label>Password <input type="password" name="password" minlength="6" required></label>
        <label>Role
          <select name="role"><option value="cashier">Cashier - can only make sales</option><option value="admin">Admin - full access</option></select>
        </label>
        <label>Security question <input type="text" name="security_question" required placeholder="e.g. First school attended?"></label>
        <label>Answer <input type="text" name="security_answer" required></label>
        <button class="btn primary" type="submit">Add account</button>
      </form>
    </section>
  </aside>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
