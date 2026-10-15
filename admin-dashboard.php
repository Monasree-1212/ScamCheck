<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\Report;
use ScamCheck\Helper;

Auth::requireAdmin();
$currentUser = Auth::user();

$currentStatus = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$filters = [];
if (!empty($currentStatus)) {
    $filters['status'] = $currentStatus;
}
if (!empty($search)) {
    $filters['search'] = $search;
}

$reportsResult = Report::getReports($filters, 100, 0);
$reports = $reportsResult['reports'];
$stats = Report::getStats();

$pageTitle = 'Admin Moderation Dashboard';
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="page-intro">
        <div>
            <h1 class="page-heading">Administrator Moderation Dashboard</h1>
            <p class="page-description">
                Inspect community scam reports, evaluate evidence, cross-reference employer credibility, and set official moderation states.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="admin-users.php" class="btn btn-outline btn-sm">
                <?= Helper::icon('users', 14) ?> Manage Users
            </a>
            <a href="submit-report.php" class="btn btn-teal btn-sm">
                <?= Helper::icon('plus-circle', 14) ?> + Add Report
            </a>
        </div>
    </div>

    <!-- Moderation Stats Strip -->
    <div class="stats-strip">
        <div class="stat-card stat-amber">
            <div class="stat-card-label">Pending Review</div>
            <div class="stat-card-value" style="color: var(--warning-amber);"><?= (int)($stats['pending'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Under Active Review</div>
            <div class="stat-card-value" style="color: var(--medium-blue);"><?= (int)($stats['review'] ?? 0) ?></div>
        </div>
        <div class="stat-card stat-teal">
            <div class="stat-card-label">Approved for Display</div>
            <div class="stat-card-value" style="color: var(--muted-teal);"><?= (int)($stats['approved'] ?? 0) ?></div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-card-label">Rejected / Duplicate</div>
            <div class="stat-card-value" style="color: var(--text-muted);"><?= (int)(($stats['rejected'] ?? 0) + ($stats['duplicate'] ?? 0)) ?></div>
        </div>
    </div>

    <!-- Moderation Filter Panel -->
    <section class="filter-panel" aria-label="Moderation Filters">
        <form method="GET" action="admin-dashboard.php" class="filter-grid" style="grid-template-columns: 2fr 1.5fr auto;">
            <div>
                <label for="search" class="form-label">Search Reports</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Company, title, or keywords..." value="<?= Helper::escape($search) ?>">
            </div>

            <div>
                <label for="status" class="form-label">Filter by Status Set</label>
                <select id="status" name="status" class="form-control">
                    <option value="">All Statuses (<?= (int)$stats['total'] ?>)</option>
                    <option value="<?= Report::STATUS_PENDING ?>" <?= $currentStatus === Report::STATUS_PENDING ? 'selected' : '' ?>>Pending (<?= (int)$stats['pending'] ?>)</option>
                    <option value="<?= Report::STATUS_REVIEW ?>" <?= $currentStatus === Report::STATUS_REVIEW ? 'selected' : '' ?>>Under Review (<?= (int)$stats['review'] ?>)</option>
                    <option value="<?= Report::STATUS_APPROVED ?>" <?= $currentStatus === Report::STATUS_APPROVED ? 'selected' : '' ?>>Approved for Display (<?= (int)$stats['approved'] ?>)</option>
                    <option value="<?= Report::STATUS_REJECTED ?>" <?= $currentStatus === Report::STATUS_REJECTED ? 'selected' : '' ?>>Rejected (<?= (int)$stats['rejected'] ?>)</option>
                    <option value="<?= Report::STATUS_DUPLICATE ?>" <?= $currentStatus === Report::STATUS_DUPLICATE ? 'selected' : '' ?>>Duplicate (<?= (int)$stats['duplicate'] ?>)</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.4rem;">
                <button type="submit" class="btn btn-primary" style="height: 38px;">
                    <?= Helper::icon('filter', 14) ?> Filter
                </button>
                <?php if (!empty($currentStatus) || !empty($search)): ?>
                    <a href="admin-dashboard.php" class="btn btn-outline" style="height: 38px;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Moderation Queue Table -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-header-title">
                <?= Helper::icon('shield', 17) ?>
                <span>Moderation Queue &mdash; <?= count($reports) ?> Report(s)</span>
            </span>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
                Redis namespace: <code>scamcheck_redis:reports:by_status:*</code>
            </span>
        </div>

        <?php if (empty($reports)): ?>
            <div class="panel-body" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                <p style="font-size: 1rem; color: var(--navy); font-weight: 600;">No reports match the selected queue filter.</p>
                <p style="font-size: 0.88rem;">All pending submissions have been handled or none exist for this query.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Company &amp; Role</th>
                            <th>Submitter</th>
                            <th>Category</th>
                            <th>Risk Score</th>
                            <th>Current State</th>
                            <th>Date</th>
                            <th style="text-align: right;">Moderation Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td><code>#<?= Helper::escape($r['id']) ?></code></td>
                                <td>
                                    <strong><?= Helper::escape($r['company_name']) ?></strong>
                                    <div style="font-size: 0.82rem; color: var(--text-muted);"><?= Helper::escape($r['job_title']) ?></div>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; font-weight: 500;"><?= Helper::escape($r['user_name']) ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 0.82rem; color: var(--text-muted);"><?= Helper::escape($r['scam_type']) ?></span>
                                </td>
                                <td><?= Helper::riskBadge($r['risk_level'], (int)$r['risk_score']) ?></td>
                                <td><?= Helper::statusBadge($r['status']) ?></td>
                                <td>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?= Helper::formatDate($r['created_at']) ?></span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="admin-review.php?id=<?= urlencode($r['id']) ?>" class="btn btn-accent btn-sm">
                                        <?= Helper::icon('shield', 13) ?> Moderate
                                    </a>
                                    <a href="report-detail.php?id=<?= urlencode($r['id']) ?>" class="btn btn-outline btn-sm">
                                        <?= Helper::icon('eye', 13) ?> View
                                    </a>
                                    <form method="POST" action="delete-report.php" style="display: inline;" onsubmit="return confirm('Permanently delete report #<?= $r['id'] ?>?');">
                                        <?= Auth::csrfField() ?>
                                        <input type="hidden" name="id" value="<?= Helper::escape($r['id']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <?= Helper::icon('trash', 13) ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
