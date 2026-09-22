<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Your cart';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'remove') {
        unset($_SESSION['store_cart'][$id]);
    } elseif ($action === 'update') {
        $qty = max(0, (int)($_POST['qty'] ?? 0));
        if ($qty <= 0) { unset($_SESSION['store_cart'][$id]); }
        else { $_SESSION['store_cart'][$id] = $qty; }
    }
    redirect('store/cart.php');
}

$cart = $_SESSION['store_cart'] ?? [];
$items = [];
$total = 0.0;
if ($cart) {
    $ids = array_keys($cart);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, name, selling_price, quantity FROM products WHERE id IN ($in)");
    $st->execute($ids);
    foreach ($st->fetchAll() as $p) {
        $qty = min((int)$cart[$p['id']], (int)$p['quantity']);
        if ($qty <= 0) { continue; }
        $sub = $qty * (float)$p['selling_price'];
        $total += $sub;
        $items[] = ['id' => $p['id'], 'name' => $p['name'], 'price' => $p['selling_price'], 'qty' => $qty, 'stock' => $p['quantity'], 'sub' => $sub];
    }
}
include __DIR__ . '/../includes/store_header.php';
?>
<?php if (!$items): ?>
  <p class="empty">Your cart is empty. <a href="<?= app_url('store/index.php') ?>">Browse our goods</a>.</p>
<?php else: ?>
<table class="table">
  <thead><tr><th>Product</th><th class="r">Price</th><th>Qty</th><th class="r">Subtotal</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr>
      <td><?= e($it['name']) ?></td>
      <td class="r"><?= money($it['price']) ?></td>
      <td>
        <form class="inline" method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
          <input type="number" name="qty" value="<?= (int)$it['qty'] ?>" min="1" max="<?= (int)$it['stock'] ?>" class="tiny">
          <button class="btn small" type="submit">Update</button>
        </form>
      </td>
      <td class="r"><?= money($it['sub']) ?></td>
      <td>
        <form class="inline" method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
          <button class="btn small danger" type="submit">Remove</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p><b>Total: <?= money($total) ?></b></p>
<a class="btn primary" href="<?= app_url('store/checkout.php') ?>">Proceed to checkout</a>
<?php endif; ?>
<?php include __DIR__ . '/../includes/store_footer.php'; ?>
