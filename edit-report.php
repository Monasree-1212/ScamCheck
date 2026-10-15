<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\Report;
use ScamCheck\RiskCalculator;
use ScamCheck\Helper;

Auth::requireAuth();
$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();

$id = trim($_GET['id'] ?? '');
if (empty($id)) {
    Helper::setFlash('danger', 'Report ID is required.');
    header('Location: dashboard.php');
    exit;
}

$report = Report::findById($id);
if (!$report) {
    Helper::setFlash('danger', 'Report not found.');
    header('Location: dashboard.php');
    exit;
}

$isOwner = ((string)$currentUser['id'] === (string)$report['user_id']);
if (!$isAdmin && !$isOwner) {
    Helper::setFlash('danger', 'Unauthorized access.');
    header('Location: dashboard.php');
    exit;
}

if (!$isAdmin && !in_array($report['status'], [Report::STATUS_PENDING, Report::STATUS_REVIEW], true)) {
    Helper::setFlash('warning', "Report is currently '{$report['status']}' and can no longer be edited by the user.");
    header('Location: report-detail.php?id=' . urlencode($id));
    exit;
}

$errors = [];
$formData = [
    'company_name'     => $report['company_name'],
    'job_title'        => $report['job_title'],
    'platform'         => $report['platform'],
    'scam_type'        => $report['scam_type'],
    'payment_demanded' => $report['payment_demanded'],
    'payment_amount'   => $report['payment_amount'],
    'contact_email'    => $report['contact_email'],
    'contact_phone'    => $report['contact_phone'],
    'contact_other'    => $report['contact_other'],
    'description'      => $report['description'],
    'evidence_text'    => $report['evidence_text'],
    'red_flags'        => $report['red_flags'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired CSRF token.';
    } else {
        $formData['company_name']     = trim($_POST['company_name'] ?? '');
        $formData['job_title']        = trim($_POST['job_title'] ?? '');
        $formData['platform']         = trim($_POST['platform'] ?? '');
        $formData['scam_type']        = trim($_POST['scam_type'] ?? '');
        $formData['payment_demanded'] = trim($_POST['payment_demanded'] ?? 'no');
        $formData['payment_amount']   = trim($_POST['payment_amount'] ?? '');
        $formData['contact_email']    = trim($_POST['contact_email'] ?? '');
        $formData['contact_phone']    = trim($_POST['contact_phone'] ?? '');
        $formData['contact_other']    = trim($_POST['contact_other'] ?? '');
        $formData['description']      = trim($_POST['description'] ?? '');
        $formData['evidence_text']    = trim($_POST['evidence_text'] ?? '');
        $formData['red_flags']        = is_array($_POST['red_flags'] ?? null) ? $_POST['red_flags'] : [];

        try {
            Report::update($id, $formData, $currentUser);
            Helper::setFlash('success', 'Report details updated successfully in Redis.');
            header('Location: report-detail.php?id=' . urlencode($id));
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$allFlags = RiskCalculator::getAllFlags();
$pageTitle = 'Edit Report #' . $report['id'];
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="page-intro">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                <?= Helper::statusBadge($report['status']) ?>
                <span style="font-size: 0.82rem; color: var(--text-muted);">Report #<?= Helper::escape($report['id']) ?></span>
            </div>
            <h1 class="page-heading">Edit Scam Report &mdash; <?= Helper::escape($report['company_name']) ?></h1>
            <p class="page-description">Modify information or update observed red flags before final moderation.</p>
        </div>
        <div>
            <a href="report-detail.php?id=<?= urlencode($id) ?>" class="btn btn-outline btn-sm">&larr; Cancel &amp; Back</a>
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
            <form method="POST" action="edit-report.php?id=<?= urlencode($id) ?>">
                <?= Auth::csrfField() ?>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('file-text', 17) ?>
                            <span>Company &amp; Role Details</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="company_name" class="form-label">Company Name <span class="req">*</span></label>
                                <input type="text" id="company_name" name="company_name" class="form-control" required value="<?= Helper::escape($formData['company_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="job_title" class="form-label">Job Title <span class="req">*</span></label>
                                <input type="text" id="job_title" name="job_title" class="form-control" required value="<?= Helper::escape($formData['job_title']) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="platform" class="form-label">Platform <span class="req">*</span></label>
                                <select id="platform" name="platform" class="form-control">
                                    <?php foreach (Report::PLATFORMS as $p): ?>
                                        <option value="<?= Helper::escape($p) ?>" <?= $formData['platform'] === $p ? 'selected' : '' ?>><?= Helper::escape($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="scam_type" class="form-label">Scam Category <span class="req">*</span></label>
                                <select id="scam_type" name="scam_type" class="form-control">
                                    <?php foreach (Report::SCAM_TYPES as $st): ?>
                                        <option value="<?= Helper::escape($st) ?>" <?= $formData['scam_type'] === $st ? 'selected' : '' ?>><?= Helper::escape($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('user', 17) ?>
                            <span>Contact Information &amp; Payment</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_email" class="form-label">Recruiter Email</label>
                                <input type="email" id="contact_email" name="contact_email" class="form-control" value="<?= Helper::escape($formData['contact_email']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="contact_phone" class="form-label">Recruiter Phone / WhatsApp</label>
                                <input type="text" id="contact_phone" name="contact_phone" class="form-control" value="<?= Helper::escape($formData['contact_phone']) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_other" class="form-label">Website or Telegram Handle</label>
                                <input type="text" id="contact_other" name="contact_other" class="form-control" value="<?= Helper::escape($formData['contact_other']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="payment_demanded" class="form-label">Payment Demanded? <span class="req">*</span></label>
                                <select id="payment_demanded" name="payment_demanded" class="form-control">
                                    <option value="no" <?= $formData['payment_demanded'] === 'no' ? 'selected' : '' ?>>No payment demanded</option>
                                    <option value="yes" <?= $formData['payment_demanded'] === 'yes' ? 'selected' : '' ?>>Yes, payment demanded</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="payment_amount" class="form-label">Payment Amount &amp; Reason</label>
                            <input type="text" id="payment_amount" name="payment_amount" class="form-control" value="<?= Helper::escape($formData['payment_amount']) ?>">
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('alert-triangle', 17) ?>
                            <span>Red Flag Indicators</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="checkbox-list">
                            <?php foreach ($allFlags as $flagKey => $flag): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="red_flags[]" class="flag-checkbox" value="<?= Helper::escape($flagKey) ?>" data-weight="<?= (int)$flag['weight'] ?>" <?= in_array($flagKey, $formData['red_flags'], true) ? 'checked' : '' ?>>
                                    <span><?= Helper::escape($flag['label']) ?></span>
                                    <span class="flag-weight">+<?= (int)$flag['weight'] ?> pts</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('info', 17) ?>
                            <span>Incident Narrative &amp; Evidence</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="description" class="form-label">Description <span class="req">*</span></label>
                            <textarea id="description" name="description" class="form-control" rows="5" required><?= Helper::escape($formData['description']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="evidence_text" class="form-label">Additional Evidence / Links</label>
                            <textarea id="evidence_text" name="evidence_text" class="form-control" rows="3"><?= Helper::escape($formData['evidence_text']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-teal btn-block" style="padding: 0.75rem;">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>

        <div>
            <div class="panel" style="position: sticky; top: 5rem;">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('alert-circle', 17) ?>
                        <span>Live Risk Evaluation</span>
                    </span>
                </div>
                <div class="panel-body">
                    <div class="risk-meter-box">
                        <div style="display: flex; justify-content: space-between; align-items: baseline;">
                            <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Current Score:</span>
                            <span id="preview-score" style="font-size: 1.5rem; font-weight: 800; color: var(--navy);"><?= (int)$report['risk_score'] ?>/100</span>
                        </div>

                        <div class="risk-bar-container">
                            <div id="preview-bar" class="risk-bar-fill"></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.45rem;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Severity Level:</span>
                            <span id="preview-level" class="risk-badge"><?= Helper::escape($report['risk_level']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
