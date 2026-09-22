<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Browse our goods';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT id, quantity FROM products WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if ($p && (int)$p['quantity'] > 0) {
        $_SESSION['store_cart'][$id] = min((int)$p['quantity'], (int)($_SESSION['store_cart'][$id] ?? 0) + 1);
        flash('ok', 'Added to your cart.');
    } else {
        flash('error', 'That item is out of stock.');
    }
    redirect('store/index.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT p.id, p.name, p.selling_price, p.quantity, COALESCE(c.name,'') AS category FROM products p LEFT JOIN categories c ON c.id = p.category_id";
$args = [];
if ($q !== '') { $sql .= ' WHERE p.name LIKE ?'; $args[] = '%' . $q . '%'; }
$sql .= ' ORDER BY p.name';
$st = $pdo->prepare($sql);
$st->execute($args);
$products = $st->fetchAll();

include __DIR__ . '/../includes/store_header.php';
?>
<form class="searchbar" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search goods">
  <button class="btn" type="submit">Search</button>
</form>

<?php if (!$products): ?>
  <p class="empty">No products match your search.</p>
<?php else: ?>
<div class="store-grid">
  <?php foreach ($products as $p): $out = (int)$p['quantity'] <= 0; ?>
    <div class="store-card">
      <b><?= e($p['name']) ?></b>
      <span class="muted"><?= e($p['category'] ?: 'General') ?></span>
      <span class="store-price"><?= money($p['selling_price']) ?></span>
      <?php if ($out): ?>
        <span class="bad">Out of stock</span>
      <?php else: ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn primary small" type="submit">Add to cart</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/store_footer.php'; ?>
