<?php
require_once __DIR__ . '/../includes/auth.php';
unset($_SESSION['customer']);
redirect('store/index.php');
