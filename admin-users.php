<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\User;
use ScamCheck\Database;
use ScamCheck\Helper;

Auth::requireAdmin();

$users = User::getAll();
$redis = Database::getClient();

$pageTitle = 'Registered Users Management';
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="page-intro">
        <div>
            <h1 class="page-heading">Registered System Users</h1>
            <p class="page-description">
                Community member accounts, access roles, and scam report submission activity stored under <code>scamcheck_redis:users:*</code>.
            </p>
        </div>
        <div>
            <a href="admin-dashboard.php" class="btn btn-outline btn-sm">&larr; Back to Moderation Dashboard</a>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-header-title">
                <?= Helper::icon('users', 17) ?>
                <span>All Registered Users (<?= count($users) ?>)</span>
            </span>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
                Redis set: <code>scamcheck_redis:users:all</code>
            </span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Reports Submitted</th>
                        <th>Registered Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                            $reportsCount = (int)$redis->zcard("reports:by_user:{$u['id']}");
                        ?>
                        <tr>
                            <td><code>#<?= Helper::escape($u['id']) ?></code></td>
                            <td>
                                <strong><?= Helper::escape($u['username']) ?></strong>
                            </td>
                            <td><?= Helper::escape($u['full_name']) ?></td>
                            <td><code><?= Helper::escape($u['email']) ?></code></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="role-badge-tag">Admin</span>
                                <?php else: ?>
                                    <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;">User</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $u['status'] === 'active' ? 'badge-approved' : 'badge-rejected' ?>">
                                    <span class="status-dot <?= $u['status'] === 'active' ? 'dot-teal' : 'dot-red' ?>"></span>
                                    <?= Helper::escape(ucfirst($u['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= $reportsCount ?></strong> report(s)
                            </td>
                            <td>
                                <span style="font-size: 0.82rem; color: var(--text-muted);"><?= Helper::formatDate($u['created_at']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
