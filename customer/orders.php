<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer_login();
$pageTitle = 'My orders';
$customer = current_customer();

$st = $pdo->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC');
$st->execute([$customer['id']]);
$orders = $st->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT oi.order_id, p.name, oi.quantity, oi.unit_price FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id IN ($in)");
    $st->execute($ids);
    foreach ($st->fetchAll() as $row) { $itemsByOrder[$row['order_id']][] = $row; }
}
include __DIR__ . '/../includes/store_header.php';
?>
<?php if (!$orders): ?>
  <p class="empty">You have no orders yet. <a href="<?= app_url('store/index.php') ?>">Browse our goods</a>.</p>
<?php else: foreach ($orders as $o): ?>
  <section class="panel">
    <h2>Order #<?= (int)$o['id'] ?> - <span class="<?= $o['status'] === 'cancelled' ? 'bad' : ($o['status'] === 'delivered' ? 'good' : 'warn') ?>"><?= e(ucfirst($o['status'])) ?></span></h2>
    <p class="muted"><?= e(date('j M Y, g:i a', strtotime($o['created_at']))) ?></p>
    <table class="table"><thead><tr><th>Product</th><th class="r">Qty</th><th class="r">Price</th></tr></thead><tbody>
    <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
      <tr><td><?= e($it['name']) ?></td><td class="r"><?= (int)$it['quantity'] ?></td><td class="r"><?= money($it['unit_price']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <p><b>Total: <?= money($o['total']) ?></b></p>
  </section>
<?php endforeach; endif; ?>
<?php include __DIR__ . '/../includes/store_footer.php'; ?>
