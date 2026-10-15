<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Report;
use ScamCheck\Helper;

$pageTitle = 'Browse Verified Scam Reports';

// Retrieve filter query parameters
$search    = trim($_GET['search'] ?? '');
$scamType  = trim($_GET['scam_type'] ?? '');
$riskLevel = trim($_GET['risk_level'] ?? '');
$sort      = trim($_GET['sort'] ?? 'newest');

$filters = [
    'status'     => Report::STATUS_APPROVED,
    'search'     => $search,
    'scam_type'  => $scamType,
    'risk_level' => $riskLevel,
    'sort'       => $sort,
];

try {
    $result     = Report::getReports($filters, 50, 0);
    $reports    = $result['reports'];
    $totalCount = $result['total'];
    $stats      = Report::getStats();
} catch (Throwable $e) {
    $reports    = [];
    $totalCount = 0;
    $stats      = ['total' => 0, 'approved' => 0, 'critical' => 0, 'high' => 0, 'users' => 0];
    Helper::setFlash('danger', 'Error loading reports from Redis: ' . $e->getMessage());
}

require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="page-intro">
        <div>
            <h1 class="page-heading">Verified Scam Reports Directory</h1>
            <p class="page-description">
                Explore confirmed job, internship, and freelance scams submitted by the community and verified by moderators.
            </p>
        </div>
        <div>
            <a href="submit-report.php" class="btn btn-primary">
                <?= Helper::icon('plus-circle', 16) ?>
                <span>Submit Scam Report</span>
            </a>
        </div>
    </div>

    <!-- Quick Stats Strip -->
    <div class="stats-strip">
        <div class="stat-card stat-teal">
            <div class="stat-card-label">Approved &amp; Live</div>
            <div class="stat-card-value"><?= (int)($stats['approved'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Total Submissions</div>
            <div class="stat-card-value"><?= (int)($stats['total'] ?? 0) ?></div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-card-label">Critical / High Severity</div>
            <div class="stat-card-value" style="color: var(--muted-red);"><?= (int)(($stats['critical'] ?? 0) + ($stats['high'] ?? 0)) ?></div>
        </div>
        <div class="stat-card stat-amber">
            <div class="stat-card-label">Registered Contributors</div>
            <div class="stat-card-value"><?= (int)($stats['users'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <section class="filter-panel" aria-label="Search and Filter Directory">
        <form method="GET" action="reports.php" class="filter-grid">
            <div>
                <label for="search" class="form-label">Search Keywords</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Company, title, email, phone, keyword..." value="<?= Helper::escape($search) ?>">
            </div>

            <div>
                <label for="scam_type" class="form-label">Scam Category</label>
                <select id="scam_type" name="scam_type" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach (Report::SCAM_TYPES as $type): ?>
                        <option value="<?= Helper::escape($type) ?>" <?= $scamType === $type ? 'selected' : '' ?>><?= Helper::escape($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="risk_level" class="form-label">Risk Severity</label>
                <select id="risk_level" name="risk_level" class="form-control">
                    <option value="">All Levels</option>
                    <option value="Critical" <?= $riskLevel === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    <option value="High" <?= $riskLevel === 'High' ? 'selected' : '' ?>>High</option>
                    <option value="Medium" <?= $riskLevel === 'Medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="Low" <?= $riskLevel === 'Low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>

            <div>
                <label for="sort" class="form-label">Sort Order</label>
                <select id="sort" name="sort" class="form-control">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="highest_risk" <?= $sort === 'highest_risk' ? 'selected' : '' ?>>Highest Risk</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.4rem;">
                <button type="submit" class="btn btn-accent" style="height: 38px;">
                    <?= Helper::icon('filter', 14) ?> Filter
                </button>
                <?php if (!empty($search) || !empty($scamType) || !empty($riskLevel) || $sort !== 'newest'): ?>
                    <a href="reports.php" class="btn btn-outline" style="height: 38px;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Reports Table -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-header-title">
                <?= Helper::icon('file-text', 17) ?>
                <span>Showing <?= count($reports) ?> of <?= $totalCount ?> Verified Reports</span>
            </span>
            <span style="font-size: 0.8rem; font-weight: 500; color: var(--text-muted);">
                Redis namespace: <code>scamcheck_redis:*</code>
            </span>
        </div>

        <?php if (empty($reports)): ?>
            <div class="panel-body" style="text-align: center; padding: 3rem 1rem;">
                <div style="color: var(--text-light); margin-bottom: 0.75rem;">
                    <?= Helper::icon('search', 40) ?>
                </div>
                <h3 style="font-size: 1.15rem; color: var(--navy); margin-bottom: 0.35rem;">No verified reports matched your query</h3>
                <p style="font-size: 0.9rem; color: var(--text-muted); max-width: 480px; margin: 0 auto 1.25rem auto;">
                    Try clearing your search terms or selecting a broader category filter to see existing alerts.
                </p>
                <a href="reports.php" class="btn btn-outline btn-sm">Clear All Filters</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Company / Organization</th>
                            <th>Job / Role Title</th>
                            <th>Scam Category</th>
                            <th>Platform</th>
                            <th>Risk Score</th>
                            <th>Money Demanded</th>
                            <th>Verified Date</th>
                            <th style="text-align: right;">Action</th>
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
                                <td>
                                    <span style="font-size: 0.85rem;"><?= Helper::escape($r['platform']) ?></span>
                                </td>
                                <td>
                                    <?= Helper::riskBadge($r['risk_level'], (int)$r['risk_score']) ?>
                                </td>
                                <td>
                                    <?php if ($r['payment_demanded'] === 'yes'): ?>
                                        <strong style="color: var(--muted-red); font-size: 0.85rem;">
                                            Yes <?= !empty($r['payment_amount']) ? '(' . Helper::escape($r['payment_amount']) . ')' : '' ?>
                                        </strong>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">No fee</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= Helper::formatDate($r['reviewed_at'] ?: $r['created_at']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="report-detail.php?id=<?= urlencode($r['id']) ?>" class="btn btn-outline btn-sm">
                                        <?= Helper::icon('eye', 14) ?> View Details
                                    </a>
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
