<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\Report;
use ScamCheck\Helper;

Auth::requireAuth();
$currentUser = Auth::user();

// Retrieve user's submitted reports from Redis
$userReportsResult = Report::getReports([
    'user_id' => $currentUser['id'],
    'sort'    => 'newest',
], 100, 0);

$reports = $userReportsResult['reports'];

// Calculate user-specific stats
$totalSubmitted = count($reports);
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$reviewCount = 0;

foreach ($reports as $r) {
    match ($r['status']) {
        Report::STATUS_PENDING   => $pendingCount++,
        Report::STATUS_APPROVED  => $approvedCount++,
        Report::STATUS_REJECTED  => $rejectedCount++,
        Report::STATUS_REVIEW    => $reviewCount++,
        default                  => null,
    };
}

$pageTitle = 'My Dashboard';
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="page-intro">
        <div>
            <h1 class="page-heading">User Submissions Dashboard</h1>
            <p class="page-description">
                Welcome back, <strong><?= Helper::escape($currentUser['full_name'] ?: $currentUser['username']) ?></strong>. Track your submitted recruitment scam reports and monitor real-time review progress.
            </p>
        </div>
        <div>
            <a href="submit-report.php" class="btn btn-teal">
                <?= Helper::icon('plus-circle', 16) ?>
                <span>Submit New Scam Report</span>
            </a>
        </div>
    </div>

    <!-- User Submission Stats Strip -->
    <div class="stats-strip">
        <div class="stat-card">
            <div class="stat-card-label">Total Submitted</div>
            <div class="stat-card-value"><?= $totalSubmitted ?></div>
        </div>
        <div class="stat-card stat-teal">
            <div class="stat-card-label">Approved &amp; Live</div>
            <div class="stat-card-value" style="color: var(--muted-teal);"><?= $approvedCount ?></div>
        </div>
        <div class="stat-card stat-amber">
            <div class="stat-card-label">Pending / In Review</div>
            <div class="stat-card-value" style="color: var(--warning-amber);"><?= $pendingCount + $reviewCount ?></div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-card-label">Rejected / Duplicate</div>
            <div class="stat-card-value" style="color: var(--text-muted);"><?= $rejectedCount ?></div>
        </div>
    </div>

    <!-- User Reports List -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-header-title">
                <?= Helper::icon('file-text', 17) ?>
                <span>My Submitted Reports (<?= $totalSubmitted ?>)</span>
            </span>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
                Stored in Redis set: <code>scamcheck_redis:reports:by_user:<?= Helper::escape($currentUser['id']) ?></code>
            </span>
        </div>

        <?php if (empty($reports)): ?>
            <div class="panel-body" style="text-align: center; padding: 3.5rem 1rem;">
                <div style="color: var(--text-light); margin-bottom: 0.75rem;">
                    <?= Helper::icon('shield', 42) ?>
                </div>
                <h3 style="font-size: 1.15rem; color: var(--navy); margin-bottom: 0.35rem;">You haven't submitted any reports yet</h3>
                <p style="font-size: 0.9rem; color: var(--text-muted); max-width: 480px; margin: 0 auto 1.5rem auto;">
                    Have you been asked for registration fees, received an offer letter without an interview, or contacted by a suspicious recruiter? Submit your incident to safeguard fellow students.
                </p>
                <a href="submit-report.php" class="btn btn-primary">Submit Your First Scam Report</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Role / Job Title</th>
                            <th>Category</th>
                            <th>Calculated Risk</th>
                            <th>Moderation Status</th>
                            <th>Submitted Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= Helper::escape($r['company_name']) ?></strong>
                                </td>
                                <td><?= Helper::escape($r['job_title']) ?></td>
                                <td>
                                    <span style="font-size: 0.82rem; color: var(--text-muted);"><?= Helper::escape($r['scam_type']) ?></span>
                                </td>
                                <td><?= Helper::riskBadge($r['risk_level'], (int)$r['risk_score']) ?></td>
                                <td><?= Helper::statusBadge($r['status']) ?></td>
                                <td>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= Helper::formatDate($r['created_at']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="report-detail.php?id=<?= urlencode($r['id']) ?>" class="btn btn-outline btn-sm">
                                        <?= Helper::icon('eye', 13) ?> View
                                    </a>

                                    <?php if (in_array($r['status'], [Report::STATUS_PENDING, Report::STATUS_REVIEW], true)): ?>
                                        <a href="edit-report.php?id=<?= urlencode($r['id']) ?>" class="btn btn-outline btn-sm">
                                            <?= Helper::icon('edit', 13) ?> Edit
                                        </a>
                                    <?php endif; ?>

                                    <form method="POST" action="delete-report.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this report?');">
                                        <?= Auth::csrfField() ?>
                                        <input type="hidden" name="id" value="<?= Helper::escape($r['id']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <?= Helper::icon('trash', 13) ?> Delete
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
