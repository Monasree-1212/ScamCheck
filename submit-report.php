<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\Report;
use ScamCheck\RiskCalculator;
use ScamCheck\Helper;

Auth::requireAuth();
$currentUser = Auth::user();

$errors = [];
$formData = [
    'company_name'     => '',
    'job_title'        => '',
    'platform'         => 'LinkedIn',
    'scam_type'        => 'Fake Offer Letter',
    'payment_demanded' => 'no',
    'payment_amount'   => '',
    'contact_email'    => '',
    'contact_phone'    => '',
    'contact_other'    => '',
    'description'      => '',
    'evidence_text'    => '',
    'red_flags'        => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired CSRF security token. Please try again.';
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
            $created = Report::create($formData, $currentUser);
            Helper::setFlash('success', 'Scam report successfully submitted! It has entered the moderation queue for verification.');
            header('Location: report-detail.php?id=' . urlencode($created['id']));
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$allFlags = RiskCalculator::getAllFlags();
$pageTitle = 'Submit Scam Report';
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="page-intro">
        <div>
            <h1 class="page-heading">Report a Job or Internship Scam</h1>
            <p class="page-description">
                Provide factual details regarding suspicious recruiters, fake offers, or fraudulent fees to help protect the student and job seeker community.
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">&larr; Back to Dashboard</a>
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
            <form method="POST" action="submit-report.php">
                <?= Auth::csrfField() ?>

                <!-- Section 1 -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('file-text', 17) ?>
                            <span>1. Company &amp; Role Information</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="company_name" class="form-label">Company / Recruiter Organization Name <span class="req">*</span></label>
                                <input type="text" id="company_name" name="company_name" class="form-control" required placeholder="e.g. Apex Infotech Solutions or Impersonator" value="<?= Helper::escape($formData['company_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="job_title" class="form-label">Advertised Job / Internship Title <span class="req">*</span></label>
                                <input type="text" id="job_title" name="job_title" class="form-control" required placeholder="e.g. Python Developer Intern, Remote Virtual Assistant" value="<?= Helper::escape($formData['job_title']) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="platform" class="form-label">Platform Where Encountered <span class="req">*</span></label>
                                <select id="platform" name="platform" class="form-control">
                                    <?php foreach (Report::PLATFORMS as $p): ?>
                                        <option value="<?= Helper::escape($p) ?>" <?= $formData['platform'] === $p ? 'selected' : '' ?>><?= Helper::escape($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="scam_type" class="form-label">Scam Category / Modus Operandi <span class="req">*</span></label>
                                <select id="scam_type" name="scam_type" class="form-control">
                                    <?php foreach (Report::SCAM_TYPES as $st): ?>
                                        <option value="<?= Helper::escape($st) ?>" <?= $formData['scam_type'] === $st ? 'selected' : '' ?>><?= Helper::escape($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2 -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('user', 17) ?>
                            <span>2. Suspicious Contact &amp; Payment Demands</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_email" class="form-label">Recruiter Contact Email</label>
                                <input type="email" id="contact_email" name="contact_email" class="form-control" placeholder="e.g. recruiter.corp@gmail.com" value="<?= Helper::escape($formData['contact_email']) ?>">
                                <div class="form-help">Generic free domains (Gmail, Yahoo) automatically alert our risk engine.</div>
                            </div>
                            <div class="form-group">
                                <label for="contact_phone" class="form-label">Recruiter Phone / WhatsApp Number</label>
                                <input type="text" id="contact_phone" name="contact_phone" class="form-control" placeholder="e.g. +91 98765 43210" value="<?= Helper::escape($formData['contact_phone']) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_other" class="form-label">Telegram Handle or Website URL</label>
                                <input type="text" id="contact_other" name="contact_other" class="form-control" placeholder="e.g. @hr_recruiter_bot or suspicious-domain.xyz" value="<?= Helper::escape($formData['contact_other']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="payment_demanded" class="form-label">Did they demand money or fees? <span class="req">*</span></label>
                                <select id="payment_demanded" name="payment_demanded" class="form-control">
                                    <option value="no" <?= $formData['payment_demanded'] === 'no' ? 'selected' : '' ?>>No payment demanded</option>
                                    <option value="yes" <?= $formData['payment_demanded'] === 'yes' ? 'selected' : '' ?>>Yes, payment requested</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="payment_amount" class="form-label">Payment Amount &amp; Reason (if requested)</label>
                            <input type="text" id="payment_amount" name="payment_amount" class="form-control" placeholder="e.g. ₹3,500 for laptop courier insurance or training kit" value="<?= Helper::escape($formData['payment_amount']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Section 3 -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('alert-triangle', 17) ?>
                            <span>3. Standardized Red Flag Indicators</span>
                        </span>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Select all that apply</span>
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

                <!-- Section 4 -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-header-title">
                            <?= Helper::icon('info', 17) ?>
                            <span>4. Incident Narrative &amp; Evidence</span>
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="description" class="form-label">Detailed Incident Description <span class="req">*</span></label>
                            <textarea id="description" name="description" class="form-control" rows="5" required placeholder="Describe what happened: how they contacted you, what task was offered, why you suspect fraud, and any financial transactions requested."><?= Helper::escape($formData['description']) ?></textarea>
                            <div class="form-help">Minimum 15 characters. Be objective, factual, and clear.</div>
                        </div>

                        <div class="form-group">
                            <label for="evidence_text" class="form-label">Evidence Links, Transcripts, or Notes</label>
                            <textarea id="evidence_text" name="evidence_text" class="form-control" rows="3" placeholder="Paste relevant chat excerpts, UPI handles, or links to cloud drive screenshots."><?= Helper::escape($formData['evidence_text']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-teal btn-block" style="padding: 0.75rem; font-size: 1rem;">
                            <?= Helper::icon('shield-check', 18) ?>
                            <span>Submit Report for Moderation</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Live Risk Engine Sidebar -->
        <div>
            <div class="panel" style="position: sticky; top: 5rem;">
                <div class="panel-header">
                    <span class="panel-header-title">
                        <?= Helper::icon('alert-circle', 17) ?>
                        <span>Live Risk Evaluation</span>
                    </span>
                </div>
                <div class="panel-body">
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 0.85rem; line-height: 1.5;">
                        As you check red flags and fill in recruiter information, our deterministic algorithm computes your risk score in real time.
                    </p>

                    <div class="risk-meter-box">
                        <div style="display: flex; justify-content: space-between; align-items: baseline;">
                            <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Estimated Score:</span>
                            <span id="preview-score" style="font-size: 1.5rem; font-weight: 800; color: var(--navy);">0/100</span>
                        </div>

                        <div class="risk-bar-container">
                            <div id="preview-bar" class="risk-bar-fill"></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.45rem;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Risk Tier:</span>
                            <span id="preview-level" class="risk-badge risk-low">Low</span>
                        </div>
                    </div>

                    <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.55; border-top: 1px solid var(--border-light); padding-top: 0.85rem;">
                        <strong style="color: var(--navy);">Severity Scale:</strong>
                        <ul style="margin-left: 1.15rem; margin-top: 0.35rem;">
                            <li><strong>0 &ndash; 29:</strong> Low Risk (Informational)</li>
                            <li><strong>30 &ndash; 59:</strong> Medium Risk (Suspicious)</li>
                            <li><strong>60 &ndash; 84:</strong> High Risk (Dangerous)</li>
                            <li><strong>85 &ndash; 100:</strong> Critical Risk (Confirmed Scam)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
