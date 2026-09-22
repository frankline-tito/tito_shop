<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle = 'Make a sale';
$activeNav = 'pos';

$products = $pdo->query("SELECT p.id, p.name, p.barcode, p.selling_price AS price, p.quantity AS stock, COALESCE(c.name, '') AS category
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.quantity > 0 ORDER BY p.name")->fetchAll();
foreach ($products as &$p) { $p['id'] = (int)$p['id']; $p['price'] = (float)$p['price']; $p['stock'] = (int)$p['stock']; }
unset($p);

include __DIR__ . '/../includes/header.php';
?>
<div class="pos">
  <section class="pos-products">
    <input type="text" id="barcode" placeholder="Scan a barcode here" autofocus autocomplete="off" aria-label="Scan a barcode">
    <input type="search" id="search" placeholder="Or search products by name" aria-label="Search products">
    <div id="product-list" class="product-list" role="list"></div>
    <p id="no-products" class="empty" hidden>No products match that search, or they are out of stock.</p>
  </section>

  <section class="receipt-tape" aria-label="Current sale">
    <div class="tape-head">
      <b><?= e(SHOP_NAME) ?></b>
      <span id="tape-date"></span>
    </div>
    <div id="cart-empty" class="tape-empty">Tap a product to add it to this sale.</div>
    <ul id="cart" class="cart"></ul>
    <div class="tape-total"><span>Total</span><b id="total">KSh 0.00</b></div>

    <fieldset class="pay">
      <legend>Payment</legend>
      <label class="choice"><input type="radio" name="pay" value="cash" checked> Cash</label>
      <label class="choice"><input type="radio" name="pay" value="mpesa"> M-Pesa</label>
      <label class="choice"><input type="radio" name="pay" value="bank"> Bank</label>
    </fieldset>
    <div id="cash-box">
      <label>Cash received <input type="number" id="received" min="0" step="1" inputmode="numeric"></label>
      <div class="change">Change to give: <b id="change">KSh 0.00</b></div>
    </div>
    <div id="mpesa-box" hidden>
      <label>M-Pesa transaction code
        <input type="text" id="mpesa-code" maxlength="10" placeholder="e.g. SHK4A7B2CD" autocomplete="off">
      </label>
    </div>
    <div id="bank-box" hidden>
      <label>Bank reference / receipt number
        <input type="text" id="bank-ref" maxlength="20" placeholder="e.g. FT24187233" autocomplete="off">
      </label>
    </div>
    <div id="msg" class="flash error" role="alert" hidden></div>
    <div class="btnrow">
      <button class="btn primary big" id="checkout" type="button">Complete sale</button>
      <button class="btn ghost" id="clear" type="button">Clear</button>
    </div>
  </section>
</div>

<script>
window.POS = {
  products: <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  checkoutUrl: <?= json_encode(app_url('api/checkout.php')) ?>,
  receiptUrl: <?= json_encode(app_url('cashier/receipt.php?id=')) ?>,
  currency: <?= json_encode(CURRENCY) ?>
};
</script>
<script src="<?= app_url('assets/js/pos.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
