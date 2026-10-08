<?php
require_once __DIR__ . '/includes/auth.php';
$u = current_user();
if (!$u) { redirect('login.php'); }
redirect($u['role'] === 'admin' ? 'admin/dashboard.php' : 'cashier/pos.php');
