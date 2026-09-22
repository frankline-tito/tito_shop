<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Payment settings';
$activeNav = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mpesa = trim($_POST['mpesa_number'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAcctName = trim($_POST['bank_account_name'] ?? '');
    $bankAcctNo = trim($_POST['bank_account_number'] ?? '');
    $pdo->prepare('REPLACE INTO settings (id, mpesa_number, bank_name, bank_account_name, bank_account_number) VALUES (1,?,?,?,?)')
        ->execute([$mpesa, $bankName, $bankAcctName, $bankAcctNo]);
    flash('ok', 'Payment details updated. Customers will see these when they check out.');
    redirect('admin/settings.php');
}

$s = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch() ?: [];
include __DIR__ . '/../includes/header.php';
?>
<section class="panel" style="max-width:480px">
  <h2>How customers can pay you</h2>
  <p class="muted">Shown to customers on the checkout and order confirmation pages.</p>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>M-Pesa number (Paybill, Till, or phone number)
      <input type="text" name="mpesa_number" value="<?= e($s['mpesa_number'] ?? '') ?>" placeholder="e.g. Till 123456">
    </label>
    <label>Bank name
      <input type="text" name="bank_name" value="<?= e($s['bank_name'] ?? '') ?>" placeholder="e.g. Equity Bank">
    </label>
    <label>Bank account name
      <input type="text" name="bank_account_name" value="<?= e($s['bank_account_name'] ?? '') ?>">
    </label>
    <label>Bank account number
      <input type="text" name="bank_account_number" value="<?= e($s['bank_account_number'] ?? '') ?>">
    </label>
    <button class="btn primary" type="submit">Save</button>
  </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
