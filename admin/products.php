<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Products and stock';
$activeNav = 'products';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id      = (int)($_POST['id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '') ?: null;
            $cat     = (int)($_POST['category_id'] ?? 0) ?: null;
            $buy     = (float)($_POST['buying_price'] ?? 0);
            $sell    = (float)($_POST['selling_price'] ?? 0);
            $qty     = max(0, (int)($_POST['quantity'] ?? 0));
            $reorder = max(0, (int)($_POST['reorder_level'] ?? 5));
            $expiry  = trim($_POST['expiry_date'] ?? '');
            $expiry  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) ? $expiry : null;

            if ($name === '' || $sell <= 0) {
                flash('error', 'Enter a product name and a selling price above zero.');
            } elseif ($id) {
                $old = $pdo->prepare('SELECT quantity FROM products WHERE id = ?');
                $old->execute([$id]);
                $oldQty = $old->fetchColumn();
                $pdo->prepare('UPDATE products SET name=?, barcode=?, category_id=?, buying_price=?, selling_price=?, quantity=?, reorder_level=?, expiry_date=? WHERE id=?')
                    ->execute([$name, $barcode, $cat, $buy, $sell, $qty, $reorder, $expiry, $id]);
                if ($oldQty !== false && (int)$oldQty !== $qty) {
                    $pdo->prepare('INSERT INTO stock_movements (product_id, type, quantity, user_id) VALUES (?,?,?,?)')
                        ->execute([$id, 'adjustment', $qty - (int)$oldQty, $me['id']]);
                }
                flash('ok', 'Changes saved for ' . $name . '.');
            } else {
                $pdo->prepare('INSERT INTO products (name, barcode, category_id, buying_price, selling_price, quantity, reorder_level, expiry_date) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$name, $barcode, $cat, $buy, $sell, $qty, $reorder, $expiry]);
                if ($qty > 0) {
                    $pdo->prepare('INSERT INTO stock_movements (product_id, type, quantity, user_id) VALUES (?,?,?,?)')
                        ->execute([(int)$pdo->lastInsertId(), 'restock', $qty, $me['id']]);
                }
                flash('ok', $name . ' added to your products.');
            }
        } elseif ($action === 'scan_restock') {
            $barcode = trim($_POST['barcode'] ?? '');
            $add = max(1, (int)($_POST['add_qty'] ?? 1));
            if ($barcode === '') {
                flash('error', 'Scan or type a barcode first.');
            } else {
                $st = $pdo->prepare('SELECT id, name FROM products WHERE barcode = ?');
                $st->execute([$barcode]);
                $prod = $st->fetch();
                if (!$prod) {
                    flash('error', 'No product has the barcode "' . $barcode . '". Add it to a product first.');
                } else {
                    $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?')->execute([$add, $prod['id']]);
                    $pdo->prepare('INSERT INTO stock_movements (product_id, type, quantity, user_id) VALUES (?,?,?,?)')
                        ->execute([$prod['id'], 'restock', $add, $me['id']]);
                    flash('ok', 'Added ' . $add . ' to stock for ' . $prod['name'] . '.');
                }
            }
        } elseif ($action === 'restock') {
            $id  = (int)($_POST['id'] ?? 0);
            $add = (int)($_POST['add_qty'] ?? 0);
            if ($id && $add > 0) {
                $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?')->execute([$add, $id]);
                $pdo->prepare('INSERT INTO stock_movements (product_id, type, quantity, user_id) VALUES (?,?,?,?)')
                    ->execute([$id, 'restock', $add, $me['id']]);
                flash('ok', "Added $add to stock.");
            } else {
                flash('error', 'Enter a quantity above zero to restock.');
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $used = $pdo->prepare('SELECT COUNT(*) FROM sale_items WHERE product_id = ?');
            $used->execute([$id]);
            if ((int)$used->fetchColumn() > 0) {
                flash('error', 'This product already has sales, so it cannot be deleted. Set its stock to 0 instead.');
            } else {
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                flash('ok', 'Product deleted.');
            }
        } elseif ($action === 'add_category') {
            $cname = trim($_POST['category_name'] ?? '');
            if ($cname === '') {
                flash('error', 'Type a category name first.');
            } else {
                $pdo->prepare('INSERT IGNORE INTO categories (name) VALUES (?)')->execute([$cname]);
                flash('ok', 'Category added.');
            }
        }
    } catch (PDOException $ex) {
        flash('error', 'Something went wrong saving that. Check the values and try again.');
    }
    redirect('admin/products.php');
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON c.id = p.category_id';
$args = [];
if ($q !== '') { $sql .= ' WHERE p.name LIKE ?'; $args[] = '%' . $q . '%'; }
$sql .= ' ORDER BY p.name';
$st = $pdo->prepare($sql);
$st->execute($args);
$products = $st->fetchAll();
$today = date('Y-m-d');
$soon  = date('Y-m-d', strtotime('+30 days'));

include __DIR__ . '/../includes/header.php';
?>
<div class="split">
  <section class="panel wide">
    <form class="searchbar" method="get">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products">
      <button class="btn" type="submit">Search</button>
      <?php if ($q !== ''): ?><a class="btn ghost" href="<?= app_url('admin/products.php') ?>">Clear</a><?php endif; ?>
    </form>
    <?php if (!$products): ?>
      <p class="empty"><?= $q !== '' ? 'No products match that search.' : 'No products yet. Add your first product using the form.' ?></p>
    <?php else: ?>
    <div class="scroll">
    <table class="table">
      <thead><tr><th>Product</th><th class="r">Buy</th><th class="r">Sell</th><th class="r">In stock</th><th>Expiry</th><th>Restock</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($products as $p):
          $low = (int)$p['quantity'] <= (int)$p['reorder_level'];
          $exp = $p['expiry_date'];
      ?>
        <tr>
          <td><b><?= e($p['name']) ?></b><br><small class="muted"><?= e($p['category'] ?? 'No category') ?><?= $p['barcode'] ? ' &middot; ' . e($p['barcode']) : '' ?></small></td>
          <td class="r"><?= number_format((float)$p['buying_price'], 2) ?></td>
          <td class="r"><?= number_format((float)$p['selling_price'], 2) ?></td>
          <td class="r <?= (int)$p['quantity'] === 0 ? 'bad' : ($low ? 'warn' : '') ?>"><?= (int)$p['quantity'] ?><?= $low ? '<br><small>low</small>' : '' ?></td>
          <td class="<?= $exp && $exp < $today ? 'bad' : ($exp && $exp <= $soon ? 'warn' : '') ?>"><?= $exp ? e(date('j M Y', strtotime($exp))) : '-' ?></td>
          <td>
            <form class="inline" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="restock">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <input type="number" name="add_qty" min="1" placeholder="+qty" class="tiny" aria-label="Quantity to add to <?= e($p['name']) ?>">
              <button class="btn small" type="submit">Add</button>
            </form>
          </td>
          <td class="actions">
            <a class="btn small ghost" href="<?= app_url('admin/products.php?edit=' . (int)$p['id']) ?>">Edit</a>
            <form class="inline" method="post" onsubmit="return confirm('Delete <?= e(addslashes($p['name'])) ?>? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn small danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </section>

  <aside class="stack">
    <section class="panel">
      <h2><?= $edit ? 'Edit product' : 'Add a product' ?></h2>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
        <label>Product name
          <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>">
        </label>
        <label>Barcode (optional - click here, then scan)
          <input type="text" name="barcode" value="<?= e($edit['barcode'] ?? '') ?>" autocomplete="off">
        </label>
        <label>Category
          <select name="category_id">
            <option value="0">No category</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $edit && (int)$edit['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="row2">
          <label>Buying price
            <input type="number" step="0.01" min="0" name="buying_price" value="<?= e($edit['buying_price'] ?? '') ?>">
          </label>
          <label>Selling price
            <input type="number" step="0.01" min="0.01" name="selling_price" required value="<?= e($edit['selling_price'] ?? '') ?>">
          </label>
        </div>
        <div class="row2">
          <label>In stock
            <input type="number" min="0" name="quantity" value="<?= e($edit['quantity'] ?? 0) ?>">
          </label>
          <label>Reorder at
            <input type="number" min="0" name="reorder_level" value="<?= e($edit['reorder_level'] ?? 5) ?>">
          </label>
        </div>
        <label>Expiry date (optional)
          <input type="date" name="expiry_date" value="<?= e($edit['expiry_date'] ?? '') ?>">
        </label>
        <div class="btnrow">
          <button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Add product' ?></button>
          <?php if ($edit): ?><a class="btn ghost" href="<?= app_url('admin/products.php') ?>">Cancel</a><?php endif; ?>
        </div>
      </form>
    </section>
    <section class="panel">
      <h2>Scan to restock</h2>
      <p class="muted">Click into the box below, then scan a product's barcode with your scanner. It adds straight to that product's stock.</p>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="scan_restock">
        <label>Barcode <input type="text" name="barcode" autocomplete="off" autofocus placeholder="Scan here"></label>
        <label>Quantity to add <input type="number" name="add_qty" value="1" min="1"></label>
        <button class="btn primary" type="submit">Add to stock</button>
      </form>
    </section>
    <section class="panel">
      <h2>New category</h2>
      <form method="post" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <input type="text" name="category_name" placeholder="e.g. Stationery" aria-label="Category name">
        <button class="btn" type="submit">Add category</button>
      </form>
    </section>
  </aside>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
