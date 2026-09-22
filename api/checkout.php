<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 422): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$user = current_user();
if (!$user) { fail('You were logged out. Log in again to finish this sale.', 401); }

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !hash_equals($_SESSION['csrf'] ?? '', (string)($in['csrf'] ?? ''))) {
    fail('Your session expired. Refresh the page and try again.', 400);
}

$method = $in['payment_method'] ?? '';
if (!in_array($method, ['cash', 'mpesa', 'bank'], true)) { fail('Choose cash, M-Pesa or bank.'); }

$mpesaCode = null;
$bankRef = null;
if ($method === 'mpesa') {
    $mpesaCode = strtoupper(trim((string)($in['mpesa_code'] ?? '')));
    if (!preg_match('/^[A-Z0-9]{10}$/', $mpesaCode)) {
        fail('The M-Pesa code must be 10 letters and numbers, like SHK4A7B2CD.');
    }
} elseif ($method === 'bank') {
    $bankRef = strtoupper(trim((string)($in['bank_reference'] ?? '')));
    if (!preg_match('/^[A-Z0-9]{4,20}$/', $bankRef)) {
        fail('Enter the bank reference or receipt number from the deposit slip.');
    }
}

// Merge repeated products so stock checks cannot be bypassed
$wanted = [];
foreach ((array)($in['items'] ?? []) as $it) {
    $pid = (int)($it['id'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    if ($pid <= 0 || $qty <= 0) { fail('One of the items in the cart is not valid.'); }
    $wanted[$pid] = ($wanted[$pid] ?? 0) + $qty;
}
if (!$wanted) { fail('The cart is empty. Add at least one product.'); }

try {
    $pdo->beginTransaction();

    if ($mpesaCode !== null) {
        $dup = $pdo->prepare('SELECT id FROM sales WHERE mpesa_code = ?');
        $dup->execute([$mpesaCode]);
        if ($dup->fetch()) { throw new RuntimeException('That M-Pesa code was already used on another sale.'); }
    }
    if ($bankRef !== null) {
        $dup = $pdo->prepare('SELECT id FROM sales WHERE bank_reference = ?');
        $dup->execute([$bankRef]);
        if ($dup->fetch()) { throw new RuntimeException('That bank reference was already used on another sale.'); }
    }

    $lines = [];
    $total = 0.0;
    $get = $pdo->prepare('SELECT id, name, buying_price, selling_price, quantity FROM products WHERE id = ? FOR UPDATE');
    foreach ($wanted as $pid => $qty) {
        $get->execute([$pid]);
        $p = $get->fetch();
        if (!$p) { throw new RuntimeException('A product in the cart no longer exists. Refresh the page.'); }
        if ((int)$p['quantity'] < $qty) {
            throw new RuntimeException('Only ' . (int)$p['quantity'] . ' of ' . $p['name'] . ' left in stock.');
        }
        $sub = round((float)$p['selling_price'] * $qty, 2);
        $total += $sub;
        $lines[] = ['id' => $pid, 'qty' => $qty, 'price' => (float)$p['selling_price'], 'cost' => (float)$p['buying_price'], 'sub' => $sub];
    }

    $pdo->prepare('INSERT INTO sales (user_id, total, payment_method, mpesa_code, bank_reference) VALUES (?,?,?,?,?)')
        ->execute([$user['id'], $total, $method, $mpesaCode, $bankRef]);
    $saleId = (int)$pdo->lastInsertId();

    $addItem = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, unit_cost, subtotal) VALUES (?,?,?,?,?,?)');
    $cut     = $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ?');
    $move    = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, user_id) VALUES (?, 'sale', ?, ?)");
    foreach ($lines as $l) {
        $addItem->execute([$saleId, $l['id'], $l['qty'], $l['price'], $l['cost'], $l['sub']]);
        $cut->execute([$l['qty'], $l['id']]);
        $move->execute([$l['id'], -$l['qty'], $user['id']]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'sale_id' => $saleId]);
} catch (RuntimeException $ex) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail($ex->getMessage());
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    if ((int)($ex->errorInfo[1] ?? 0) === 1062) { fail('That payment reference was already used on another sale.'); }
    fail('The sale could not be saved. Nothing was charged. Try again.', 500);
}
