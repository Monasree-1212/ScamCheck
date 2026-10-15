<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Report;
use ScamCheck\Helper;

$pageTitle = 'Stay Alert. Spot Job Scams Before You Apply';

// Retrieve latest approved reports for the public homepage
try {
    $recentResult = Report::getReports([
        'status' => Report::STATUS_APPROVED,
        'sort'   => 'newest'
    ], 4, 0);
    $recentReports = $recentResult['reports'];
    $stats = Report::getStats();
} catch (Throwable $e) {
    $recentReports = [];
    $stats = ['total' => 0, 'approved' => 0, 'critical' => 0, 'high' => 0];
}

require_once __DIR__ . '/includes/templates/header.php';
?>

<!-- 1. HERO SECTION -->
<section class="hero-section">
    <div class="hero-container">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <?= Helper::icon('shield-check', 14) ?>
                <span>Student &amp; Job Seeker Protection</span>
            </div>
            <h1 class="hero-title">Stay Alert. Spot Job Scams Before You Apply.</h1>
            <p class="hero-description">
                ScamCheck is a community-powered fraud detection platform designed to help college students and job seekers verify suspicious employment offers, expose deceptive recruiters, and prevent financial loss.
            </p>
            <div class="hero-actions">
                <a href="reports.php" class="btn btn-primary" style="padding: 0.7rem 1.4rem; font-size: 0.95rem;">
                    <?= Helper::icon('search', 16) ?> Browse Reports
                </a>
                <a href="submit-report.php" class="btn btn-teal" style="padding: 0.7rem 1.4rem; font-size: 0.95rem;">
                    <?= Helper::icon('plus-circle', 16) ?> Report a Scam
                </a>
            </div>
        </div>

        <!-- Right Visual: Clean Mock Scam Assessment Card -->
        <div class="hero-preview-card">
            <div class="preview-card-header">
                <div>
                    <div class="preview-company-name">Apex Global Recruitment</div>
                    <div class="preview-role">Data Analyst Intern &bull; LinkedIn Offer</div>
                </div>
                <div>
                    <?= Helper::riskBadge('Critical', 85) ?>
                </div>
            </div>

            <div class="preview-indicators">
                <div class="preview-indicator-row">
                    <span style="display: flex; align-items: center; gap: 0.4rem; color: var(--navy); font-weight: 600;">
                        <?= Helper::icon('alert-triangle', 14, 'sc-icon') ?>
                        <span>Upfront Fee Demand</span>
                    </span>
                    <span style="color: var(--muted-red); font-weight: 700;">₹3,500 for Laptop Deposit</span>
                </div>

                <div class="preview-indicator-row">
                    <span style="display: flex; align-items: center; gap: 0.4rem; color: var(--navy); font-weight: 600;">
                        <?= Helper::icon('alert-circle', 14, 'sc-icon') ?>
                        <span>Screening Process</span>
                    </span>
                    <span style="color: var(--warning-text); font-weight: 600;">No Interview Conducted</span>
                </div>

                <div class="preview-indicator-row">
                    <span style="display: flex; align-items: center; gap: 0.4rem; color: var(--navy); font-weight: 600;">
                        <?= Helper::icon('info', 14, 'sc-icon') ?>
                        <span>Recruiter Email</span>
                    </span>
                    <span style="font-family: monospace; font-size: 0.8rem;">hr.apexcorp@gmail.com</span>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-light); padding-top: 0.75rem;">
                <span class="badge badge-approved">
                    <span class="status-dot dot-teal"></span> Verified Scam Alert
                </span>
                <span class="preview-footer-note">Redis Key: <code>scamcheck_redis:reports:1</code></span>
            </div>
        </div>
    </div>
</section>

<!-- 2. PLATFORM FEATURES -->
<section class="features-section">
    <div class="features-container">
        <div class="section-header">
            <h2 class="section-title">Why Use ScamCheck?</h2>
            <p class="section-subtitle">
                A purpose-built detection system delivering transparent threat intelligence for students and job seekers.
            </p>
        </div>

        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-icon-wrapper icon-navy">
                    <?= Helper::icon('users', 20) ?>
                </div>
                <h3 class="feature-heading">Community Reports</h3>
                <p class="feature-text">
                    Real incident reports submitted by students detailing suspicious companies, fraudulent stipends, and deceptive messages.
                </p>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrapper icon-amber">
                    <?= Helper::icon('alert-triangle', 20) ?>
                </div>
                <h3 class="feature-heading">Scam Risk Indicators</h3>
                <p class="feature-text">
                    Our deterministic scoring engine weights 9 concrete red flags to produce an objective risk score from 0 to 100.
                </p>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrapper icon-teal">
                    <?= Helper::icon('search', 20) ?>
                </div>
                <h3 class="feature-heading">Search and Filter</h3>
                <p class="feature-text">
                    Inverted Redis token indexes enable sub-millisecond keyword lookup across companies, job roles, recruiter emails, and platforms.
                </p>
            </div>

            <div class="feature-box">
                <div class="feature-icon-wrapper icon-blue">
                    <?= Helper::icon('shield', 20) ?>
                </div>
                <h3 class="feature-heading">Safer Job Searching</h3>
                <p class="feature-text">
                    Administrator-reviewed evidence and immutable audit trails prevent students from losing money to fraudulent placement syndicates.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- 3. HOW IT WORKS -->
<section id="how-it-works" class="how-it-works-section">
    <div class="how-it-works-container">
        <div class="section-header">
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Three straightforward steps from encountering a suspicious offer to community protection.</p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3 class="step-title">Encounter &amp; Document</h3>
                <p class="step-desc">
                    When approached with an unsolicited job offer, immediate selection without interview, or a training fee demand, note down the details and contact handles.
                </p>
            </div>

            <div class="step-card">
                <div class="step-number" style="background-color: var(--medium-blue);">2</div>
                <h3 class="step-title">Calculate Risk &amp; Submit</h3>
                <p class="step-desc">
                    Use our interactive reporting tool. Check applicable red flags to view an instant real-time risk assessment before securely submitting to the moderation queue.
                </p>
            </div>

            <div class="step-card">
                <div class="step-number" style="background-color: var(--muted-teal);">3</div>
                <h3 class="step-title">Review &amp; Alert Community</h3>
                <p class="step-desc">
                    Moderators review supporting evidence, cross-reference official company registries, and approve genuine alerts into the publicly searchable repository.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- 4. RECENT APPROVED REPORTS -->
<section class="container" style="padding-top: 3rem; padding-bottom: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h2 class="section-title" style="margin-bottom: 0.25rem;">Recent Verified Reports</h2>
            <p style="font-size: 0.92rem; color: var(--text-muted);">
                Latest deceptive hiring alerts verified and approved for public awareness.
            </p>
        </div>
        <div>
            <a href="reports.php" class="btn btn-outline btn-sm">
                <span>View All Verified Reports (<?= (int)($stats['approved'] ?? 0) ?>)</span>
                <?= Helper::icon('arrow-right', 14) ?>
            </a>
        </div>
    </div>

    <?php if (empty($recentReports)): ?>
        <div class="panel">
            <div class="panel-body" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                <p style="font-size: 1.05rem; font-weight: 600; color: var(--navy); margin-bottom: 0.25rem;">No verified reports currently published.</p>
                <p style="font-size: 0.9rem;">Be the first contributor to submit an alert about suspicious recruitment.</p>
                <a href="submit-report.php" class="btn btn-primary btn-sm" style="margin-top: 1rem;">Submit a Report</a>
            </div>
        </div>
    <?php else: ?>
        <div class="panel">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Company / Organization</th>
                            <th>Role Title</th>
                            <th>Scam Type</th>
                            <th>Platform</th>
                            <th>Risk Level</th>
                            <th>Payment Demanded</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReports as $r): ?>
                            <tr>
                                <td><strong><?= Helper::escape($r['company_name']) ?></strong></td>
                                <td><?= Helper::escape($r['job_title']) ?></td>
                                <td><span style="font-size: 0.82rem; color: var(--text-muted);"><?= Helper::escape($r['scam_type']) ?></span></td>
                                <td><?= Helper::escape($r['platform']) ?></td>
                                <td><?= Helper::riskBadge($r['risk_level'], (int)$r['risk_score']) ?></td>
                                <td>
                                    <?php if ($r['payment_demanded'] === 'yes'): ?>
                                        <span style="color: var(--muted-red); font-weight: 700; font-size: 0.85rem;">
                                            Yes <?= !empty($r['payment_amount']) ? '(' . Helper::escape($r['payment_amount']) . ')' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="report-detail.php?id=<?= urlencode($r['id']) ?>" class="btn btn-outline btn-sm">
                                        <?= Helper::icon('eye', 13) ?> Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- 5. WARNING SIGNS -->
<section class="warning-signs-section">
    <div class="warning-container">
        <div class="warning-heading-wrapper">
            <?= Helper::icon('alert-triangle', 24) ?>
            <h2 class="warning-title">Common Recruitment Warning Signs</h2>
        </div>
        <p class="warning-sub">
            Protect yourself during your job search by identifying these frequent recruitment red flags before engaging.
        </p>

        <div class="warning-grid">
            <div class="warning-card">
                <div class="warning-card-title">
                    <?= Helper::icon('alert-circle', 16) ?>
                    <span>Upfront Payment Demands</span>
                </div>
                <p class="warning-card-desc">
                    Legitimate employers never require candidates to transfer funds for training materials, interview registration, background checks, or laptop security deposits.
                </p>
            </div>

            <div class="warning-card">
                <div class="warning-card-title">
                    <?= Helper::icon('alert-circle', 16) ?>
                    <span>Unofficial Recruiter Contact</span>
                </div>
                <p class="warning-card-desc">
                    Recruiters using generic email providers (such as <code>@gmail.com</code> or <code>@yahoo.com</code>) instead of verified corporate domains represent a significant warning sign.
                </p>
            </div>

            <div class="warning-card">
                <div class="warning-card-title">
                    <?= Helper::icon('alert-circle', 16) ?>
                    <span>Instant Offers without Interview</span>
                </div>
                <p class="warning-card-desc">
                    Receiving an immediate formal offer letter within hours of application without technical screening, phone conversation, or interview is typical of fraud syndicates.
                </p>
            </div>

            <div class="warning-card">
                <div class="warning-card-title">
                    <?= Helper::icon('alert-circle', 16) ?>
                    <span>Sensitive Info &amp; Chat Hiring</span>
                </div>
                <p class="warning-card-desc">
                    Conducting the entire hiring process over Telegram or WhatsApp while demanding bank OTPs, login passwords, or netbanking credentials upfront indicates identity theft.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- 6. ABOUT THE PROJECT ARCHITECTURE -->
<section id="about" class="about-section">
    <div class="about-container">
        <div class="about-box">
            <h3>About the ScamCheck Project</h3>
            <p>
                <strong>ScamCheck</strong> is an academic college engineering initiative developed to combat recruitment fraud targeted at university students and fresh graduates. Rather than relying on traditional relational databases, the platform uses <strong>Redis</strong> as its primary in-memory datastore.
            </p>
            <p>
                By employing Redis Hashes for entity persistence, Sorted Sets for chronological indexes, Sets for state partitioning (Pending, Under Review, Approved, Rejected, Duplicate), and Inverted Token Indexes for keyword discovery, the system achieves sub-millisecond query performance while preserving full relational integrity and audit trails.
            </p>
            <div class="tech-pill-row">
                <span class="tech-pill">PHP 8.1+</span>
                <span class="tech-pill">Redis (Memurai on Windows)</span>
                <span class="tech-pill">Predis Client ^2.2</span>
                <span class="tech-pill">Namespace: scamcheck_redis:*</span>
                <span class="tech-pill">Bcrypt Password Hashing</span>
                <span class="tech-pill">CSRF Security Tokens</span>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
