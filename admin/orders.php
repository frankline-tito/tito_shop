<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Customer orders';
$activeNav = 'orders';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'update_reference') {
        $ref = trim($_POST['payment_reference'] ?? '') ?: null;
        $pdo->prepare("UPDATE orders SET payment_reference = ? WHERE id = ? AND status = 'pending'")->execute([$ref, $id]);
        flash('ok', 'Payment reference updated.');
    } elseif ($action === 'cancel') {
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'")->execute([$id]);
        flash('ok', 'Order cancelled.');
    } elseif ($action === 'deliver') {
        $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ? AND status = 'confirmed'")->execute([$id]);
        flash('ok', 'Order marked as delivered.');
    } elseif ($action === 'confirm') {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'pending' FOR UPDATE");
            $st->execute([$id]);
            $order = $st->fetch();
            if (!$order) { throw new RuntimeException('This order is no longer pending.'); }

            if ($order['payment_method'] !== 'cash' && $order['payment_reference']) {
                $col = $order['payment_method'] === 'mpesa' ? 'mpesa_code' : 'bank_reference';
                $dup = $pdo->prepare("SELECT id FROM sales WHERE $col = ?");
                $dup->execute([$order['payment_reference']]);
                if ($dup->fetch()) { throw new RuntimeException('That payment reference was already used on another sale. Correct it first.'); }
            }

            $itemsSt = $pdo->prepare('SELECT oi.*, p.buying_price FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
            $itemsSt->execute([$id]);
            $items = $itemsSt->fetchAll();
            $lock = $pdo->prepare('SELECT quantity, name FROM products WHERE id = ? FOR UPDATE');
            foreach ($items as $it) {
                $lock->execute([$it['product_id']]);
                $p = $lock->fetch();
                if (!$p || (int)$p['quantity'] < (int)$it['quantity']) {
                    throw new RuntimeException('Not enough stock of ' . ($p['name'] ?? 'a product') . ' to confirm this order.');
                }
            }

            $mpesaCode = $order['payment_method'] === 'mpesa' ? $order['payment_reference'] : null;
            $bankRef   = $order['payment_method'] === 'bank' ? $order['payment_reference'] : null;
            $pdo->prepare('INSERT INTO sales (user_id, total, payment_method, mpesa_code, bank_reference) VALUES (?,?,?,?,?)')
                ->execute([$me['id'], $order['total'], $order['payment_method'], $mpesaCode, $bankRef]);
            $saleId = (int)$pdo->lastInsertId();

            $addItem = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, unit_cost, subtotal) VALUES (?,?,?,?,?,?)');
            $cut = $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ?');
            $move = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, user_id) VALUES (?, 'sale', ?, ?)");
            foreach ($items as $it) {
                $addItem->execute([$saleId, $it['product_id'], $it['quantity'], $it['unit_price'], $it['buying_price'], $it['subtotal']]);
                $cut->execute([$it['quantity'], $it['product_id']]);
                $move->execute([$it['product_id'], -$it['quantity'], $me['id']]);
            }
            $pdo->prepare("UPDATE orders SET status = 'confirmed', confirmed_sale_id = ? WHERE id = ?")->execute([$saleId, $id]);
            $pdo->commit();
            flash('ok', 'Order confirmed, stock updated and the sale was recorded.');
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', $ex->getMessage());
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', 'Could not confirm this order. Try again.');
        }
    }
    redirect('admin/orders.php');
}

$status = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'confirmed', 'delivered', 'cancelled', 'all'];
if (!in_array($status, $allowed, true)) { $status = 'pending'; }
$sql = "SELECT o.*, c.name AS customer_name, c.phone AS customer_phone FROM orders o JOIN customers c ON c.id = o.customer_id";
$args = [];
if ($status !== 'all') { $sql .= ' WHERE o.status = ?'; $args[] = $status; }
$sql .= ' ORDER BY o.created_at DESC LIMIT 100';
$st = $pdo->prepare($sql);
$st->execute($args);
$orders = $st->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT oi.order_id, p.name, oi.quantity, oi.unit_price FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id IN ($in)");
    $st->execute($ids);
    foreach ($st->fetchAll() as $row) { $itemsByOrder[$row['order_id']][] = $row; }
}
include __DIR__ . '/../includes/header.php';
?>
<div class="filters">
  <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $label): ?>
    <a class="btn <?= $status === $k ? 'primary' : '' ?>" href="?status=<?= e($k) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$orders): ?>
  <p class="empty">No orders with this status.</p>
<?php else: foreach ($orders as $o): ?>
  <section class="panel">
    <div class="split" style="grid-template-columns: 1fr 260px;">
      <div>
        <h2 style="margin-bottom:4px">Order #<?= (int)$o['id'] ?> - <?= e($o['customer_name']) ?></h2>
        <p class="muted" style="margin:0 0 10px">
          <?= e(date('j M Y, g:i a', strtotime($o['created_at']))) ?> &middot;
          Deliver to <?= e($o['delivery_address']) ?> (<?= e($o['delivery_phone']) ?>)
        </p>
        <table class="table"><thead><tr><th>Product</th><th class="r">Qty</th><th class="r">Price</th></tr></thead><tbody>
        <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
          <tr><td><?= e($it['name']) ?></td><td class="r"><?= (int)$it['quantity'] ?></td><td class="r"><?= money($it['unit_price']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <p><b>Total: <?= money($o['total']) ?></b></p>
      </div>
      <div>
        <p><span class="label">Payment method</span><br><b><?= e(ucfirst($o['payment_method'] === 'mpesa' ? 'M-Pesa' : $o['payment_method'])) ?></b></p>
        <?php if ($o['status'] === 'pending' && $o['payment_method'] !== 'cash'): ?>
          <form method="post" class="inline-form" style="margin-bottom:10px">
            <?= csrf_field() ?><input type="hidden" name="action" value="update_reference"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="text" name="payment_reference" value="<?= e($o['payment_reference'] ?? '') ?>" placeholder="Payment reference" class="mid">
            <button class="btn small" type="submit">Save</button>
          </form>
        <?php elseif ($o['payment_reference']): ?>
          <p><span class="label">Reference</span><br><?= e($o['payment_reference']) ?></p>
        <?php endif; ?>
        <p><span class="label">Status</span><br><b><?= e(ucfirst($o['status'])) ?></b></p>
        <div class="btnrow" style="flex-wrap:wrap">
          <?php if ($o['status'] === 'pending'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <button class="btn primary small" type="submit">Confirm</button></form>
            <form method="post" onsubmit="return confirm('Cancel this order?');"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <button class="btn danger small" type="submit">Cancel</button></form>
          <?php elseif ($o['status'] === 'confirmed'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="deliver"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <button class="btn primary small" type="submit">Mark delivered</button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
<?php endforeach; endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
