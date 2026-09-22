<?php
require_once __DIR__ . '/../includes/auth.php';
require_customer_login();
$pageTitle = 'Checkout';
$customer = current_customer();

$cart = $_SESSION['store_cart'] ?? [];
if (!$cart) { redirect('store/index.php'); }

$error = '';
$orderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $phone = trim($_POST['delivery_phone'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    $method = $_POST['payment_method'] ?? '';
    $reference = trim($_POST['payment_reference'] ?? '') ?: null;

    if ($phone === '' || $address === '') {
        $error = 'Enter a delivery phone number and address.';
    } elseif (!in_array($method, ['cash', 'mpesa', 'bank'], true)) {
        $error = 'Choose how you will pay.';
    } else {
        try {
            $pdo->beginTransaction();
            $ids = array_keys($cart);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT id, name, selling_price, quantity FROM products WHERE id IN ($in) FOR UPDATE");
            $st->execute($ids);
            $products = [];
            foreach ($st->fetchAll() as $p) { $products[$p['id']] = $p; }

            $lines = [];
            $total = 0.0;
            foreach ($cart as $pid => $qty) {
                if (!isset($products[$pid])) { continue; }
                $p = $products[$pid];
                $qty = min((int)$qty, (int)$p['quantity']);
                if ($qty <= 0) { continue; }
                $sub = round($qty * (float)$p['selling_price'], 2);
                $total += $sub;
                $lines[] = ['id' => $pid, 'qty' => $qty, 'price' => (float)$p['selling_price'], 'sub' => $sub];
            }
            if (!$lines) { throw new RuntimeException('Everything in your cart is now out of stock.'); }

            $pdo->prepare('INSERT INTO orders (customer_id, delivery_phone, delivery_address, payment_method, payment_reference, total) VALUES (?,?,?,?,?,?)')
                ->execute([$customer['id'], $phone, $address, $method, $reference, $total]);
            $orderId = (int)$pdo->lastInsertId();
            $addItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)');
            foreach ($lines as $l) { $addItem->execute([$orderId, $l['id'], $l['qty'], $l['price'], $l['sub']]); }

            $pdo->commit();
            unset($_SESSION['store_cart']);
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = 'Could not place your order. Try again.';
        }
    }
}

$pay = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch() ?: [];

if (!$orderId) {
    $ids = array_keys($cart);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, name, selling_price, quantity FROM products WHERE id IN ($in)");
    $st->execute($ids);
    $items = []; $total = 0.0;
    foreach ($st->fetchAll() as $p) {
        $qty = min((int)$cart[$p['id']], (int)$p['quantity']);
        if ($qty <= 0) { continue; }
        $sub = $qty * (float)$p['selling_price'];
        $total += $sub;
        $items[] = ['name' => $p['name'], 'qty' => $qty, 'sub' => $sub];
    }
}
include __DIR__ . '/../includes/store_header.php';
?>
<?php if ($orderId): ?>
  <div class="flash ok">Order #<?= $orderId ?> placed. We will confirm it and arrange delivery.</div>
  <section class="panel" style="max-width:420px">
    <h2>How to pay</h2>
    <?php if (!empty($pay['mpesa_number'])): ?><p><b>M-Pesa:</b> <?= e($pay['mpesa_number']) ?></p><?php endif; ?>
    <?php if (!empty($pay['bank_account_number'])): ?><p><b>Bank:</b> <?= e($pay['bank_name']) ?> - <?= e($pay['bank_account_name']) ?> - <?= e($pay['bank_account_number']) ?></p><?php endif; ?>
    <p class="muted">If you chose cash, you'll pay when your order is delivered.</p>
    <a class="btn primary" href="<?= app_url('customer/orders.php') ?>">Track my orders</a>
  </section>
<?php else: ?>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <section class="panel" style="max-width:480px">
    <h2>Your order</h2>
    <table class="table"><thead><tr><th>Product</th><th class="r">Qty</th><th class="r">Subtotal</th></tr></thead><tbody>
    <?php foreach ($items as $it): ?>
      <tr><td><?= e($it['name']) ?></td><td class="r"><?= (int)$it['qty'] ?></td><td class="r"><?= money($it['sub']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <p><b>Total: <?= money($total) ?></b></p>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label>Delivery phone <input type="text" name="delivery_phone" required value="<?= e($customer['phone'] ?? '') ?>"></label>
      <label>Delivery address <input type="text" name="delivery_address" required></label>
      <fieldset class="pay">
        <legend>How will you pay?</legend>
        <label class="choice"><input type="radio" name="payment_method" value="cash" checked> Cash on delivery</label>
        <label class="choice"><input type="radio" name="payment_method" value="mpesa"> M-Pesa</label>
        <label class="choice"><input type="radio" name="payment_method" value="bank"> Bank</label>
      </fieldset>
      <label>Payment reference (if you've already paid)
        <input type="text" name="payment_reference" maxlength="20" placeholder="Leave blank if paying on delivery">
      </label>
      <button class="btn primary" type="submit">Place order</button>
    </form>
  </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/store_footer.php'; ?>
