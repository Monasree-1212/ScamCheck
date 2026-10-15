<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Report;
use ScamCheck\RiskCalculator;
use ScamCheck\Auth;
use ScamCheck\Helper;

$id = trim($_GET['id'] ?? '');
if (empty($id)) {
    Helper::setFlash('danger', 'Report ID is required.');
    header('Location: reports.php');
    exit;
}

$report = Report::findById($id);
if (!$report) {
    Helper::setFlash('danger', 'Report not found or has been removed.');
    header('Location: reports.php');
    exit;
}

// Access control: If not approved, only owner or admin can view
$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$isOwner = $currentUser && ((string)$currentUser['id'] === (string)$report['user_id']);

if ($report['status'] !== Report::STATUS_APPROVED && !$isAdmin && !$isOwner) {
    Helper::setFlash('warning', 'This report is currently pending moderation and is only visible to the submitter and administrators.');
    header('Location: reports.php');
    exit;
}

// Increment views
Report::incrementViews($id);

$allFlags = RiskCalculator::getAllFlags();
$reviewHistory = Report::getReviewHistory($id);

$pageTitle = "Report: " . $report['company_name'] . " - " . $report['job_title'];
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="page-intro">
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.45rem; flex-wrap: wrap;">
                <?= Helper::statusBadge($report['status']) ?>
                <?= Helper::riskBadge($report['risk_level'], (int)$report['risk_score']) ?>
                <span style="font-size: 0.8rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 0.25rem;">
                    <?= Helper::icon('eye', 13) ?> <?= (int)$report['views_count'] ?> views
                </span>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                    <code>#<?= Helper::escape($report['id']) ?></code>
                </span>
            </div>
            <h1 class="page-heading"><?= Helper::escape($report['company_name']) ?> &mdash; <?= Helper::escape($report['job_title']) ?></h1>
            <p class="page-description">
                Reported by <strong><?= Helper::escape($report['user_name']) ?></strong> &bull; <?= Helper::formatDate($report['created_at']) ?> &bull; Platform: <?= Helper::escape($report['platform']) ?>
            </p>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <?php if ($isAdmin): ?>
                <a href="admin-review.php?id=<?= urlencode($report['id']) ?>" class="btn btn-primary btn-sm">
                    <?= Helper::icon('shield', 14) ?> Moderate
                </a>
            <?php endif; ?>

            <?php if ($isAdmin || ($isOwner && in_array($report['status'], [Report::STATUS_PENDING, Report::STATUS_REVIEW], true))): ?>
                <a href="edit-report.php?id=<?= urlencode($report['id']) ?>" class="btn btn-outline btn-sm">
                    <?= Helper::icon('edit', 14) ?> Edit
                </a>
            <?php endif; ?>

            <?php if ($isAdmin || $isOwner): ?>
                <form method="POST" action="delete-report.php" style="display: inline;" onsubmit="return confirm('Permanently delete this report?');">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="id" value="<?= Helper::escape($report['id']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <?= Helper::icon('trash', 14) ?> Delete
                    </button>
                </form>
            <?php endif; ?>

            <a href="reports.php" class="btn btn-outline btn-sm">&larr; Back to Directory</a>
        </div>
    </div>

    <div class="detail-grid">
        <div>
            <!-- Incident Narrative -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('file-text', 17) ?>
                        <span>Incident Narrative &amp; Full Details</span>
                    </span>
                </div>
                <div class="panel-body">
                    <p style="white-space: pre-line; line-height: 1.65; font-size: 0.95rem; color: var(--text-main);"><?= Helper::escape($report['description']) ?></p>
                </div>
            </div>

            <!-- Identified Red Flags -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('alert-triangle', 17) ?>
                        <span>Observed Red Flags &amp; Fraud Indicators</span>
                    </span>
                    <span style="font-size: 0.8rem; font-weight: 600; color: var(--navy);"><?= count($report['red_flags']) ?> Identified</span>
                </div>
                <div class="panel-body">
                    <?php if (empty($report['red_flags'])): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">No standardized checklist indicators were selected during initial submission.</p>
                    <?php else: ?>
                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php foreach ($report['red_flags'] as $flagKey): ?>
                                <?php $flagInfo = $allFlags[$flagKey] ?? null; ?>
                                <li style="display: flex; align-items: center; justify-content: space-between; background-color: #FAFCFE; border: 1px solid var(--border-light); padding: 0.6rem 0.85rem; border-radius: var(--radius-sm); font-size: 0.88rem;">
                                    <span style="display: flex; align-items: center; gap: 0.45rem;">
                                        <span style="color: var(--muted-red);"><?= Helper::icon('alert-circle', 15) ?></span>
                                        <strong><?= Helper::escape($flagInfo['label'] ?? $flagKey) ?></strong>
                                    </span>
                                    <?php if ($flagInfo): ?>
                                        <span class="badge" style="background-color: var(--bg-subtle); color: var(--navy); font-weight: 700;">+<?= (int)$flagInfo['weight'] ?> pts</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Evidence Notes -->
            <?php if (!empty($report['evidence_text'])): ?>
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('info', 17) ?>
                            <span>Supporting Evidence &amp; Transcripts</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <p style="white-space: pre-line; font-size: 0.9rem; color: var(--text-main); line-height: 1.55;"><?= Helper::escape($report['evidence_text']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Audit Trail & Moderation Timeline -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('clock', 17) ?>
                        <span>Moderation Audit Trail</span>
                    </span>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">Stored in Redis List</span>
                </div>
                <div class="panel-body">
                    <?php if (empty($reviewHistory)): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">No audit logs recorded for this report.</p>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach ($reviewHistory as $entry): ?>
                                <li class="timeline-item">
                                    <div class="timeline-bullet"></div>
                                    <div class="timeline-header">
                                        <?= Helper::escape($entry['action'] ?? 'Status Change') ?>
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

        <!-- Metadata Sidebar -->
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
                            <td>Scam Category:</td>
                            <td><strong><?= Helper::escape($report['scam_type']) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Platform:</td>
                            <td><?= Helper::escape($report['platform']) ?></td>
                        </tr>
                        <tr>
                            <td>Risk Evaluation:</td>
                            <td><?= Helper::riskBadge($report['risk_level'], (int)$report['risk_score']) ?></td>
                        </tr>
                        <tr>
                            <td>Money Requested:</td>
                            <td>
                                <?php if ($report['payment_demanded'] === 'yes'): ?>
                                    <strong style="color: var(--muted-red);">Yes</strong>
                                    <?php if (!empty($report['payment_amount'])): ?>
                                        <br><span style="font-size: 0.82rem; color: var(--text-muted);"><?= Helper::escape($report['payment_amount']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">None reported</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Recruiter Email:</td>
                            <td>
                                <?php if (!empty($report['contact_email'])): ?>
                                    <code><?= Helper::escape($report['contact_email']) ?></code>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Recruiter Phone:</td>
                            <td>
                                <?php if (!empty($report['contact_phone'])): ?>
                                    <code><?= Helper::escape($report['contact_phone']) ?></code>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Other Handles:</td>
                            <td>
                                <?php if (!empty($report['contact_other'])): ?>
                                    <?= Helper::escape($report['contact_other']) ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Submitted:</td>
                            <td><?= Helper::formatDate($report['created_at']) ?></td>
                        </tr>
                        <tr>
                            <td>Last Updated:</td>
                            <td><?= Helper::formatDate($report['updated_at']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if (!empty($report['admin_notes'])): ?>
                <div class="panel" style="border-color: var(--info-border);">
                    <div class="panel-header" style="background-color: var(--info-bg); color: var(--info-text);">
                        <span class="panel-header-title">
                            <?= Helper::icon('shield-check', 17) ?>
                            <span>Moderator Decision Notes</span>
                        </span>
                    </div>
                    <div class="panel-body" style="font-size: 0.9rem; line-height: 1.55;">
                        <p style="white-space: pre-line;"><?= Helper::escape($report['admin_notes']) ?></p>
                        <div style="margin-top: 0.65rem; font-size: 0.78rem; color: var(--text-muted); border-top: 1px solid var(--border-light); padding-top: 0.45rem;">
                            Moderated by <strong><?= Helper::escape($report['reviewed_by'] ?: 'Admin') ?></strong> &bull; <?= Helper::formatDate($report['reviewed_at']) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
