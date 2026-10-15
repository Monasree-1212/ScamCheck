<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\Report;
use ScamCheck\Helper;

Auth::requireAuth();
$currentUser = Auth::user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? null)) {
    Helper::setFlash('danger', 'Invalid or expired CSRF security token.');
    header('Location: dashboard.php');
    exit;
}

$id = trim($_POST['id'] ?? '');
if (empty($id)) {
    Helper::setFlash('danger', 'Report ID is required.');
    header('Location: dashboard.php');
    exit;
}

try {
    Report::delete($id, $currentUser);
    Helper::setFlash('success', "Report #{$id} has been permanently deleted.");
} catch (Throwable $e) {
    Helper::setFlash('danger', 'Delete failed: ' . $e->getMessage());
}

$redirect = Auth::isAdmin() && isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], 'admin')
    ? 'admin-dashboard.php'
    : 'dashboard.php';

header("Location: {$redirect}");
exit;
