<?php
// Expects: $pageTitle
$customer = current_customer();
$cartCount = 0;
foreach (($_SESSION['store_cart'] ?? []) as $q) { $cartCount += $q; }
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Shop') ?> - <?= e(SHOP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body>
<div class="store-shell">
  <header class="store-top">
    <a class="brand-lite" href="<?= app_url('store/index.php') ?>"><?= e(SHOP_NAME) ?></a>
    <nav class="store-nav">
      <a href="<?= app_url('store/index.php') ?>">Browse goods</a>
      <a href="<?= app_url('store/cart.php') ?>">Cart<?= $cartCount ? ' (' . $cartCount . ')' : '' ?></a>
      <?php if ($customer): ?>
        <a href="<?= app_url('customer/orders.php') ?>">My orders</a>
        <a href="<?= app_url('customer/logout.php') ?>">Log out (<?= e($customer['name']) ?>)</a>
      <?php else: ?>
        <a href="<?= app_url('customer/login.php') ?>">Log in</a>
        <a href="<?= app_url('customer/register.php') ?>">Register</a>
      <?php endif; ?>
      <a href="<?= app_url('login.php') ?>">Staff login</a>
    </nav>
  </header>
  <main class="store-main">
    <h1 class="page-title"><?= e($pageTitle ?? '') ?></h1>
    <?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
