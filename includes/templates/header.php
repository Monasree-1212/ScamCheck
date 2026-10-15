<?php
use ScamCheck\Auth;
use ScamCheck\Helper;

Auth::startSession();
$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Helper::escape($pageTitle) . ' — ' : '' ?>ScamCheck</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <nav class="navbar">
        <a href="index.php" class="brand-wrapper">
            <span class="brand-icon"><?= Helper::icon('shield-check', 20) ?></span>
            <span class="brand-title">ScamCheck</span>
            <span class="brand-tag">Redis</span>
        </a>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="<?= $currentPage === 'reports.php' ? 'active' : '' ?>">Browse Reports</a>
            </li>
            <li class="nav-item">
                <a href="index.php#how-it-works">How It Works</a>
            </li>
            <li class="nav-item">
                <a href="index.php#about">About</a>
            </li>

            <?php if (Auth::check()): ?>
                <li class="nav-item">
                    <a href="submit-report.php" class="<?= $currentPage === 'submit-report.php' ? 'active' : '' ?>">+ Report Scam</a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
                </li>
                <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a href="admin-dashboard.php" class="<?= in_array($currentPage, ['admin-dashboard.php', 'admin-review.php']) ? 'active' : '' ?>">Moderation</a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-users.php" class="<?= $currentPage === 'admin-users.php' ? 'active' : '' ?>">Users</a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>

        <div class="nav-actions">
            <?php if (Auth::check()): ?>
                <div class="user-status-pill">
                    <?= Helper::icon('user', 14) ?>
                    <span><?= Helper::escape($currentUser['username'] ?? 'User') ?></span>
                    <?php if ($isAdmin): ?>
                        <span class="role-badge-tag">Admin</span>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="btn btn-outline btn-sm">Sign Out</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline btn-sm">Sign In</a>
                <a href="register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<div class="main-wrapper">
    <?= Helper::displayFlash() ?>
