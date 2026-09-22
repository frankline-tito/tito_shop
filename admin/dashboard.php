<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

$today = $pdo->query("SELECT COALESCE(SUM(total),0) AS t, COUNT(*) AS c FROM sales WHERE DATE(created_at) = CURDATE()")->fetch();
$month = $pdo->query("SELECT COALESCE(SUM(total),0) AS t FROM sales WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetch();
$lowCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= reorder_level")->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$pay = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch() ?: [];
$lowStock = $pdo->query("SELECT id, name, quantity, reorder_level FROM products WHERE quantity <= reorder_level ORDER BY quantity ASC LIMIT 8")->fetchAll();
$expiring = $pdo->query("SELECT name, expiry_date, quantity FROM products
    WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND quantity > 0
    ORDER BY expiry_date ASC LIMIT 8")->fetchAll();
$top = $pdo->query("SELECT p.name, SUM(si.quantity) AS qty, SUM(si.subtotal) AS revenue
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    JOIN sales s ON s.id = si.sale_id
    WHERE s.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    GROUP BY p.id, p.name ORDER BY qty DESC LIMIT 5")->fetchAll();

$rows = $pdo->query("SELECT DATE(created_at) AS d, SUM(total) AS t FROM sales
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);
$labels = []; $values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('D j', strtotime($d));
    $values[] = (float)($rows[$d] ?? 0);
}
include __DIR__ . '/../includes/header.php';
?>
<section class="today">
  <div>
    <span class="label">Sold today</span>
    <div class="big"><?= money($today['t']) ?></div>
    <span class="muted"><?= (int)$today['c'] ?> sale<?= (int)$today['c'] === 1 ? '' : 's' ?> so far</span>
  </div>
  <div class="today-side">
    <div><span class="label">This month</span><b><?= money($month['t']) ?></b></div>
    <div><span class="label">Items to restock</span><b class="<?= $lowCount ? 'warn' : '' ?>"><?= $lowCount ?></b></div>
    <div><span class="label">Customer orders waiting</span><b class="<?= $pendingOrders ? 'warn' : '' ?>"><?= $pendingOrders ?></b></div>
  </div>
  <a class="btn primary" href="<?= app_url('cashier/pos.php') ?>">Make a sale</a>
</section>

<section class="panel">
  <h2>Your payment details</h2>
  <?php if (empty($pay['mpesa_number']) && empty($pay['bank_account_number'])): ?>
    <p class="empty">Not set yet. <a href="<?= app_url('admin/settings.php') ?>">Add your M-Pesa and bank details</a> so online customers know how to pay.</p>
  <?php else: ?>
    <p>
      <?php if (!empty($pay['mpesa_number'])): ?><b>M-Pesa:</b> <?= e($pay['mpesa_number']) ?><br><?php endif; ?>
      <?php if (!empty($pay['bank_account_number'])): ?><b>Bank:</b> <?= e($pay['bank_name']) ?> - <?= e($pay['bank_account_name']) ?> - <?= e($pay['bank_account_number']) ?><?php endif; ?>
    </p>
    <a class="btn ghost" href="<?= app_url('admin/settings.php') ?>">Edit</a>
  <?php endif; ?>
</section>

<div class="grid two">
  <section class="panel">
    <h2>Sales in the last 7 days</h2>
    <div class="chart-wrap"><canvas id="weekChart"></canvas></div>
  </section>
  <section class="panel">
    <h2>Best sellers this month</h2>
    <?php if (!$top): ?>
      <p class="empty">No sales yet this month. Make a sale and your best sellers will show here.</p>
    <?php else: ?>
      <table class="table"><thead><tr><th>Product</th><th class="r">Sold</th><th class="r">Revenue</th></tr></thead><tbody>
      <?php foreach ($top as $t): ?>
        <tr><td><?= e($t['name']) ?></td><td class="r"><?= (int)$t['qty'] ?></td><td class="r"><?= money($t['revenue']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
</div>

<div class="grid two">
  <section class="panel">
    <h2>Running low</h2>
    <?php if (!$lowStock): ?>
      <p class="empty">Every product is above its reorder level.</p>
    <?php else: ?>
      <table class="table"><thead><tr><th>Product</th><th class="r">Left</th><th class="r">Reorder at</th></tr></thead><tbody>
      <?php foreach ($lowStock as $p): ?>
        <tr><td><a href="<?= app_url('admin/products.php?edit=' . (int)$p['id']) ?>"><?= e($p['name']) ?></a></td>
            <td class="r <?= (int)$p['quantity'] === 0 ? 'bad' : 'warn' ?>"><?= (int)$p['quantity'] ?></td>
            <td class="r"><?= (int)$p['reorder_level'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
  <section class="panel">
    <h2>Expiring in 30 days</h2>
    <?php if (!$expiring): ?>
      <p class="empty">Nothing in stock expires in the next 30 days.</p>
    <?php else: ?>
      <table class="table"><thead><tr><th>Product</th><th>Expires</th><th class="r">In stock</th></tr></thead><tbody>
      <?php foreach ($expiring as $p): $days = (int)((strtotime($p['expiry_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
        <tr><td><?= e($p['name']) ?></td>
            <td class="<?= $days < 0 ? 'bad' : 'warn' ?>"><?= e(date('j M Y', strtotime($p['expiry_date']))) ?><?= $days < 0 ? ' (expired)' : '' ?></td>
            <td class="r"><?= (int)$p['quantity'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var el = document.getElementById('weekChart');
  if (!el || typeof Chart === 'undefined') { return; }
  new Chart(el, {
    type: 'bar',
    data: { labels: <?= json_encode($labels) ?>, datasets: [{ data: <?= json_encode($values) ?>, backgroundColor: '#F2A900', borderRadius: 3 }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, grid: { color: '#E3E9E5' } }, x: { grid: { display: false } } } }
  });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
