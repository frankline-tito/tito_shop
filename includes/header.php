<?php
// Expects: $pageTitle (string), $activeNav (string)
$user  = current_user();
$flash = get_flash();
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Shop') ?> - <?= e(SHOP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Courier+Prime:wght@400;700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="brand" href="<?= app_url('index.php') ?>"><?= e(SHOP_NAME) ?></a>
    <nav class="nav">
      <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= app_url('admin/dashboard.php') ?>" class="<?= $activeNav === 'dashboard' ? 'on' : '' ?>">Dashboard</a>
        <a href="<?= app_url('admin/products.php') ?>" class="<?= $activeNav === 'products' ? 'on' : '' ?>">Products and stock</a>
        <a href="<?= app_url('admin/reports.php') ?>" class="<?= $activeNav === 'reports' ? 'on' : '' ?>">Sales reports</a>
        <a href="<?= app_url('admin/users.php') ?>" class="<?= $activeNav === 'users' ? 'on' : '' ?>">Staff</a>
        <a href="<?= app_url('admin/orders.php') ?>" class="<?= $activeNav === 'orders' ? 'on' : '' ?>">Customer orders</a>
        <a href="<?= app_url('admin/settings.php') ?>" class="<?= $activeNav === 'settings' ? 'on' : '' ?>">Payment settings</a>
      <?php endif; ?>
      <a href="<?= app_url('cashier/pos.php') ?>" class="<?= $activeNav === 'pos' ? 'on' : '' ?>">Make a sale</a>
      <a href="<?= app_url('cashier/account.php') ?>" class="<?= $activeNav === 'account' ? 'on' : '' ?>">My account</a>
    </nav>
    <div class="who">
      <span><?= e($user['name']) ?></span>
      <small><?= e($user['role']) ?></small>
      <a href="<?= app_url('logout.php') ?>">Log out</a>
    </div>
  </aside>
  <main class="main">
    <h1 class="page-title"><?= e($pageTitle ?? '') ?></h1>
    <?php if ($flash): ?>
      <div class="flash <?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
    <?php endif; ?>
