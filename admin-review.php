<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\Report;
use ScamCheck\RiskCalculator;
use ScamCheck\Helper;

Auth::requireAdmin();
$currentUser = Auth::user();

$id = trim($_GET['id'] ?? '');
if (empty($id)) {
    Helper::setFlash('danger', 'Report ID is required.');
    header('Location: admin-dashboard.php');
    exit;
}

$report = Report::findById($id);
if (!$report) {
    Helper::setFlash('danger', 'Report not found.');
    header('Location: admin-dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired CSRF token.';
    } else {
        $newStatus = trim($_POST['status'] ?? '');
        $adminNotes = trim($_POST['admin_notes'] ?? '');

        try {
            Report::updateStatus($id, $newStatus, $currentUser, $adminNotes);
            Helper::setFlash('success', "Report #{$id} status updated to '{$newStatus}' in Redis.");
            header('Location: admin-dashboard.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$allFlags = RiskCalculator::getAllFlags();
$reviewHistory = Report::getReviewHistory($id);

$pageTitle = 'Moderate Report #' . $report['id'];
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="page-intro">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                <span style="font-size: 0.82rem; color: var(--text-muted);">Moderation Decision for</span>
                <code>#<?= Helper::escape($report['id']) ?></code>
            </div>
            <h1 class="page-heading"><?= Helper::escape($report['company_name']) ?> &mdash; <?= Helper::escape($report['job_title']) ?></h1>
            <p class="page-description">Assign moderation status, document verification checks, and append to the permanent audit trail.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="report-detail.php?id=<?= urlencode($id) ?>" class="btn btn-outline btn-sm">
                <?= Helper::icon('eye', 13) ?> View Public Page
            </a>
            <a href="admin-dashboard.php" class="btn btn-outline btn-sm">&larr; Back to Queue</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <span class="alert-icon"><?= Helper::icon('alert-circle', 18) ?></span>
            <div class="alert-text">
                <ul style="margin-left: 1rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= Helper::escape($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="detail-grid">
        <div>
            <!-- Moderator Decision Card -->
            <div class="panel" style="border: 1px solid var(--info-border); background-color: #FAFBFD;">
                <div class="panel-header" style="background-color: var(--info-bg); color: var(--info-text);">
                    <span class="panel-header-title">
                        <?= Helper::icon('shield-check', 17) ?>
                        <span>Official Administrator Status Decision</span>
                    </span>
                </div>
                <div class="panel-body">
                    <form method="POST" action="admin-review.php?id=<?= urlencode($id) ?>">
                        <?= Auth::csrfField() ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Current State in Redis</label>
                                <div style="padding: 0.35rem 0;"><?= Helper::statusBadge($report['status']) ?></div>
                            </div>

                            <div class="form-group">
                                <label for="status" class="form-label">Assign New Lifecycle Status <span class="req">*</span></label>
                                <select id="status" name="status" class="form-control" required>
                                    <?php foreach (Report::STATUSES as $st): ?>
                                        <option value="<?= Helper::escape($st) ?>" <?= $report['status'] === $st ? 'selected' : '' ?>><?= Helper::escape($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="admin_notes" class="form-label">Moderator Assessment Notes &amp; Verification Rationale</label>
                            <textarea id="admin_notes" name="admin_notes" class="form-control" rows="4" placeholder="Explain the verification checks performed, reason for approval/rejection, or duplicate references..."><?= Helper::escape($report['admin_notes']) ?></textarea>
                            <div class="form-help">These notes are permanently logged into the Redis review audit list for this report.</div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <a href="admin-dashboard.php" class="btn btn-outline">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <?= Helper::icon('check', 16) ?>
                                <span>Save Decision &amp; Update Sets</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Incident Narrative -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('file-text', 17) ?>
                        <span>Submitted Incident Narrative</span>
                    </span>
                </div>
                <div class="panel-body">
                    <p style="white-space: pre-line; line-height: 1.65; font-size: 0.95rem;"><?= Helper::escape($report['description']) ?></p>
                </div>
            </div>

            <!-- Red Flags -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('alert-triangle', 17) ?>
                        <span>Checked Indicators &amp; Red Flags (<?= count($report['red_flags']) ?>)</span>
                    </span>
                </div>
                <div class="panel-body">
                    <?php if (empty($report['red_flags'])): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">No red flag items were checked.</p>
                    <?php else: ?>
                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php foreach ($report['red_flags'] as $flagKey): ?>
                                <?php $flagInfo = $allFlags[$flagKey] ?? null; ?>
                                <li style="display: flex; align-items: center; justify-content: space-between; background-color: #FAFCFE; border: 1px solid var(--border-light); padding: 0.6rem 0.85rem; border-radius: var(--radius-sm); font-size: 0.88rem;">
                                    <span style="display: flex; align-items: center; gap: 0.45rem;">
                                        <span style="color: var(--muted-red);"><?= Helper::icon('alert-circle', 15) ?></span>
                                        <strong><?= Helper::escape($flagInfo['label'] ?? $flagKey) ?></strong>
                                    </span>
                                    <span class="badge" style="background-color: var(--bg-subtle); color: var(--navy);">+<?= (int)($flagInfo['weight'] ?? 0) ?> pts</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Evidence Provided -->
            <?php if (!empty($report['evidence_text'])): ?>
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('info', 17) ?>
                            <span>Evidence &amp; Notes Provided</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <p style="white-space: pre-line; font-size: 0.9rem; line-height: 1.55;"><?= Helper::escape($report['evidence_text']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Audit Trail -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('clock', 17) ?>
                        <span>Moderation Audit History</span>
                    </span>
                </div>
                <div class="panel-body">
                    <?php if (empty($reviewHistory)): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">No review entries recorded yet.</p>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach ($reviewHistory as $entry): ?>
                                <li class="timeline-item">
                                    <div class="timeline-bullet"></div>
                                    <div class="timeline-header">
                                        <?= Helper::escape($entry['action'] ?? 'Action') ?>
                                        <span class="timeline-meta">&bull; by <strong><?= Helper::escape($entry['reviewer_name'] ?? 'System') ?></strong> &bull; <?= Helper::formatDate($entry['timestamp'] ?? '') ?></span>
                                    </div>
                                    <?php if (!empty($entry['new_status'])): ?>
                                        <div style="margin-top: 0.25rem;">
                                            <span style="font-size: 0.78rem; color: var(--text-muted);">Assigned State:</span>
                                            <?= Helper::statusBadge($entry['new_status']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['notes'])): ?>
                                        <div class="timeline-body"><?= Helper::escape($entry['notes']) ?></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Incident Metadata Sidebar -->
        <div>
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('info', 17) ?>
                        <span>Incident Metadata</span>
                    </span>
                </div>
                <div class="panel-body">
                    <table class="meta-table">
                        <tr>
                            <td>Submitter:</td>
                            <td><strong><?= Helper::escape($report['user_name']) ?></strong> (<code><?= Helper::escape($report['user_email']) ?></code>)</td>
                        </tr>
                        <tr>
                            <td>Company:</td>
                            <td><strong><?= Helper::escape($report['company_name']) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Role:</td>
                            <td><?= Helper::escape($report['job_title']) ?></td>
                        </tr>
                        <tr>
                            <td>Platform:</td>
                            <td><?= Helper::escape($report['platform']) ?></td>
                        </tr>
                        <tr>
                            <td>Category:</td>
                            <td><?= Helper::escape($report['scam_type']) ?></td>
                        </tr>
                        <tr>
                            <td>Risk Rating:</td>
                            <td><?= Helper::riskBadge($report['risk_level'], (int)$report['risk_score']) ?></td>
                        </tr>
                        <tr>
                            <td>Fee Demanded:</td>
                            <td>
                                <?php if ($report['payment_demanded'] === 'yes'): ?>
                                    <strong style="color: var(--muted-red);">Yes</strong> (<?= Helper::escape($report['payment_amount'] ?: 'Amount not stated') ?>)
                                <?php else: ?>
                                    <span>No fee demanded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Recruiter Email:</td>
                            <td><?= Helper::escape($report['contact_email'] ?: '—') ?></td>
                        </tr>
                        <tr>
                            <td>Recruiter Phone:</td>
                            <td><?= Helper::escape($report['contact_phone'] ?: '—') ?></td>
                        </tr>
                        <tr>
                            <td>Other Info:</td>
                            <td><?= Helper::escape($report['contact_other'] ?: '—') ?></td>
                        </tr>
                        <tr>
                            <td>Submitted:</td>
                            <td><?= Helper::formatDate($report['created_at']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
