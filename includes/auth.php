<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v): string { return CURRENCY . ' ' . number_format((float)$v, 2); }
function app_url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . app_url($path)); exit; }
function current_user() { return $_SESSION['user'] ?? null; }

function require_login(): void {
    if (!current_user()) { redirect('login.php'); }
}
function require_admin(): void {
    require_login();
    if (current_user()['role'] !== 'admin') { redirect('cashier/pos.php'); }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        die('Your session expired. Go back, refresh the page and try again.');
    }
}

function flash(string $type, string $msg): void { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function get_flash() { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

/* ---- Customers (storefront) ---- */
function current_customer() { return $_SESSION['customer'] ?? null; }
function require_customer_login(): void {
    if (!current_customer()) { redirect('customer/login.php'); }
}

/* ---- Security question helpers (case-insensitive answers) ---- */
function hash_answer(string $answer): string {
    return password_hash(mb_strtolower(trim($answer)), PASSWORD_DEFAULT);
}
function verify_answer(string $answer, ?string $hash): bool {
    if (!$hash) { return false; }
    return password_verify(mb_strtolower(trim($answer)), $hash);
}
