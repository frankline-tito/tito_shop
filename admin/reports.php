<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

function valid_date($d, $fallback) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? $d : $fallback; }
$from = valid_date($_GET['from'] ?? '', date('Y-m-01'));
$to   = valid_date($_GET['to'] ?? '', date('Y-m-d'));
$range = 's.created_at >= ? AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
$args  = [$from, $to];

// CSV download (must run before any HTML is printed)
if (isset($_GET['export'])) {
    $st = $pdo->prepare("SELECT s.id, s.created_at, u.name AS cashier, s.payment_method, s.mpesa_code, s.bank_reference, s.total
        FROM sales s JOIN users u ON u.id = s.user_id WHERE $range ORDER BY s.created_at");
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Receipt no', 'Date and time', 'Cashier', 'Payment', 'Reference', 'Total']);
    foreach ($st as $r) {
        $ref = $r['mpesa_code'] ?: $r['bank_reference'];
        fputcsv($out, [$r['id'], $r['created_at'], $r['cashier'], $r['payment_method'], $ref, $r['total']]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Sales reports';
$activeNav = 'reports';

$st = $pdo->prepare("SELECT COALESCE(SUM(s.total),0) AS revenue, COUNT(*) AS n FROM sales s WHERE $range");
$st->execute($args);
$sum = $st->fetch();

$st = $pdo->prepare("SELECT COALESCE(SUM(si.unit_cost * si.quantity),0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE $range");
$st->execute($args);
$cost = (float)$st->fetchColumn();
$profit = (float)$sum['revenue'] - $cost;

$st = $pdo->prepare("SELECT s.payment_method, COUNT(*) AS n, SUM(s.total) AS t FROM sales s WHERE $range GROUP BY s.payment_method");
$st->execute($args);
$byPay = $st->fetchAll();

$st = $pdo->prepare("SELECT p.name, SUM(si.quantity) AS qty, SUM(si.subtotal) AS revenue,
        SUM(si.subtotal - si.unit_cost * si.quantity) AS profit
    FROM sale_items si JOIN products p ON p.id = si.product_id JOIN sales s ON s.id = si.sale_id
    WHERE $range GROUP BY p.id, p.name ORDER BY qty DESC LIMIT 10");
$st->execute($args);
$best = $st->fetchAll();

$st = $pdo->prepare("SELECT DATE(s.created_at) AS d, COUNT(*) AS n, SUM(s.total) AS t FROM sales s WHERE $range GROUP BY DATE(s.created_at) ORDER BY d DESC");
$st->execute($args);
$daily = $st->fetchAll();

$st = $pdo->prepare("SELECT s.id, s.created_at, s.payment_method, s.mpesa_code, s.bank_reference, s.total, u.name AS cashier
    FROM sales s JOIN users u ON u.id = s.user_id WHERE $range ORDER BY s.created_at DESC LIMIT 200");
$st->execute($args);
$sales = $st->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<form class="filters" method="get">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn primary" type="submit">Show report</button>
  <a class="btn" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=1">Download CSV</a>
  <button class="btn ghost" type="button" onclick="window.print()">Print</button>
</form>

<section class="figures">
  <div><span class="label">Revenue</span><b><?= money($sum['revenue']) ?></b></div>
  <div><span class="label">Cost of goods sold</span><b><?= money($cost) ?></b></div>
  <div><span class="label">Profit</span><b class="<?= $profit < 0 ? 'bad' : 'good' ?>"><?= money($profit) ?></b></div>
  <div><span class="label">Sales made</span><b><?= (int)$sum['n'] ?></b></div>
</section>

<div class="grid two">
  <section class="panel">
    <h2>Best sellers</h2>
    <?php if (!$best): ?><p class="empty">No sales in this date range.</p><?php else: ?>
    <table class="table"><thead><tr><th>Product</th><th class="r">Sold</th><th class="r">Revenue</th><th class="r">Profit</th></tr></thead><tbody>
    <?php foreach ($best as $b): ?>
      <tr><td><?= e($b['name']) ?></td><td class="r"><?= (int)$b['qty'] ?></td><td class="r"><?= money($b['revenue']) ?></td><td class="r"><?= money($b['profit']) ?></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </section>
  <section class="panel">
    <h2>How customers paid</h2>
    <?php if (!$byPay): ?><p class="empty">No sales in this date range.</p><?php else: ?>
    <table class="table"><thead><tr><th>Method</th><th class="r">Sales</th><th class="r">Total</th></tr></thead><tbody>
    <?php foreach ($byPay as $p): ?>
      <tr><td><?= $p['payment_method'] === 'mpesa' ? 'M-Pesa' : ($p['payment_method'] === 'bank' ? 'Bank' : 'Cash') ?></td><td class="r"><?= (int)$p['n'] ?></td><td class="r"><?= money($p['t']) ?></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
    <h2 class="spaced">Day by day</h2>
    <?php if ($daily): ?>
    <table class="table"><thead><tr><th>Date</th><th class="r">Sales</th><th class="r">Total</th></tr></thead><tbody>
    <?php foreach ($daily as $d): ?>
      <tr><td><?= e(date('D j M Y', strtotime($d['d']))) ?></td><td class="r"><?= (int)$d['n'] ?></td><td class="r"><?= money($d['t']) ?></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </section>
</div>

<section class="panel">
  <h2>All sales in this range<?= count($sales) === 200 ? ' (latest 200)' : '' ?></h2>
  <?php if (!$sales): ?><p class="empty">No sales in this date range.</p><?php else: ?>
  <div class="scroll"><table class="table"><thead><tr><th>Receipt</th><th>Date and time</th><th>Cashier</th><th>Payment</th><th class="r">Total</th></tr></thead><tbody>
  <?php foreach ($sales as $s): ?>
    <tr>
      <td><a href="<?= app_url('cashier/receipt.php?id=' . (int)$s['id']) ?>">#<?= (int)$s['id'] ?></a></td>
      <td><?= e(date('j M Y, g:i a', strtotime($s['created_at']))) ?></td>
      <td><?= e($s['cashier']) ?></td>
      <td><?php
        if ($s['payment_method'] === 'mpesa') { echo 'M-Pesa ' . e($s['mpesa_code']); }
        elseif ($s['payment_method'] === 'bank') { echo 'Bank ' . e($s['bank_reference']); }
        else { echo 'Cash'; }
      ?></td>
      <td class="r"><?= money($s['total']) ?></td>
    </tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
