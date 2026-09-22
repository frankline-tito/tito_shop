<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$me = current_user();
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare('SELECT s.*, u.name AS cashier FROM sales s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
$st->execute([$id]);
$sale = $st->fetch();
if (!$sale || ($me['role'] !== 'admin' && (int)$sale['user_id'] !== (int)$me['id'])) {
    http_response_code(404);
    die('Receipt not found.');
}
$st = $pdo->prepare('SELECT si.*, p.name FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id');
$st->execute([$id]);
$items = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt #<?= (int)$sale['id'] ?> - <?= e(SHOP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Courier+Prime:wght@400;700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="receipt-body">
  <div class="receipt-actions">
    <button class="btn primary" onclick="window.print()">Print receipt</button>
    <a class="btn" href="<?= app_url('cashier/pos.php') ?>">New sale</a>
  </div>
  <article class="receipt">
    <h1><?= e(SHOP_NAME) ?></h1>
    <p class="center">Receipt #<?= (int)$sale['id'] ?><br><?= e(date('j M Y, g:i a', strtotime($sale['created_at']))) ?><br>Served by <?= e($sale['cashier']) ?></p>
    <hr>
    <table>
      <?php foreach ($items as $it): ?>
        <tr><td colspan="2"><?= e($it['name']) ?></td></tr>
        <tr class="sub"><td><?= (int)$it['quantity'] ?> x <?= number_format((float)$it['unit_price'], 2) ?></td><td class="r"><?= number_format((float)$it['subtotal'], 2) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <hr>
    <table><tr class="total"><td>TOTAL</td><td class="r"><?= money($sale['total']) ?></td></tr></table>
    <p>Paid by <?php
      if ($sale['payment_method'] === 'mpesa') { echo 'M-Pesa (' . e($sale['mpesa_code']) . ')'; }
      elseif ($sale['payment_method'] === 'bank') { echo 'Bank (' . e($sale['bank_reference']) . ')'; }
      else { echo 'cash'; }
    ?></p>
    <hr>
    <p class="center">Thank you for shopping with us.</p>
  </article>
</body>
</html>
