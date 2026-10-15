<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Database;
use ScamCheck\User;
use ScamCheck\Report;
use ScamCheck\Helper;

$isCli = (php_sapi_name() === 'cli');

function logMsg(string $msg, bool $isCli): void {
    if ($isCli) {
        echo "[SEED] " . $msg . "\n";
    } else {
        echo "<p style='font-family: monospace; font-size: 14px;'><strong>[SEED]</strong> " . htmlspecialchars($msg) . "</p>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Database Seeder - ScamCheck Redis</title><link rel='stylesheet' href='assets/css/style.css'></head><body style='padding: 2rem;'><div class='panel' style='max-width: 800px; margin: 0 auto;'><div class='panel-header'>ScamCheck Redis Database Seeder</div><div class='panel-body'>";
}

try {
    $redis = Database::getClient();
    $prefix = Database::getPrefix();
    logMsg("Connected to Redis successfully (Namespace: {$prefix})", $isCli);

    // 1. Seed Users
    $admin = User::findByEmail('admin@scamcheck.local');
    if (!$admin) {
        $admin = User::create([
            'username'  => 'admin',
            'email'     => 'admin@scamcheck.local',
            'password'  => 'Admin@123',
            'full_name' => 'System Administrator',
            'role'      => 'admin',
        ]);
        logMsg("Created default Admin account: admin@scamcheck.local / Admin@123", $isCli);
    } else {
        logMsg("Admin account already exists (ID: {$admin['id']})", $isCli);
    }

    $student = User::findByEmail('student@college.edu');
    if (!$student) {
        $student = User::create([
            'username'  => 'student',
            'email'     => 'student@college.edu',
            'password'  => 'Student@123',
            'full_name' => 'Rohan Verma',
            'role'      => 'user',
        ]);
        logMsg("Created demo Student account: student@college.edu / Student@123", $isCli);
    } else {
        logMsg("Student account already exists (ID: {$student['id']})", $isCli);
    }

    $priya = User::findByEmail('priya.sharma@example.com');
    if (!$priya) {
        $priya = User::create([
            'username'  => 'priyasharma',
            'email'     => 'priya.sharma@example.com',
            'password'  => 'User@123',
            'full_name' => 'Priya Sharma',
            'role'      => 'user',
        ]);
        logMsg("Created demo user account: priya.sharma@example.com / User@123", $isCli);
    } else {
        logMsg("User Priya account already exists (ID: {$priya['id']})", $isCli);
    }

    // 2. Check if reports already seeded
    $existingCount = (int)$redis->zcard('reports:all');
    if ($existingCount === 0) {
        logMsg("No reports found. Seeding realistic sample scam reports...", $isCli);

        // Report 1: Laptop Security Deposit Scam
        $r1 = Report::create([
            'company_name'     => 'Apex Infotech Solutions',
            'job_title'        => 'Associate Software Developer Intern',
            'platform'         => 'LinkedIn',
            'scam_type'        => 'Fake Offer Letter',
            'payment_demanded' => 'yes',
            'payment_amount'   => '₹4,500 (Refundable Laptop Security & Courier Fee)',
            'contact_email'    => 'hr.apexinfotech@gmail.com',
            'contact_phone'    => '+91 98231 09841',
            'contact_other'    => 'http://apex-infotech-careers.xyz',
            'description'      => "I applied via a sponsored post on LinkedIn. Within 4 hours, without any technical interview or coding test, I received an official-looking offer letter promising ₹35,000/month stipend. The HR then demanded an immediate transfer of ₹4,500 via UPI for dispatching a pre-configured company MacBook Air. When I requested to pick it up from their Bangalore office, they became aggressive and threatened to cancel the offer.",
            'evidence_text'    => "Offer letter PDF contained a forged MCA corporate stamp and fake CIN number. UPI handle provided: apexinfotech98@okhdfcbank.",
            'red_flags'        => ['fee_demanded', 'no_interview', 'free_email', 'urgent_pressure', 'suspicious_domain'],
        ], $student);

        // Approve Report 1
        Report::updateStatus($r1['id'], Report::STATUS_APPROVED, $admin, "Verified fraudulent entity. MCA registration lookup confirmed fake CIN. UPI ID reported to national cybercrime reporting portal.");
        logMsg("Seeded Report #{$r1['id']} (Apex Infotech Solutions) -> Approved", $isCli);

        // Report 2: Telegram Video Rating / Task Scam
        $r2 = Report::create([
            'company_name'     => 'Global Media Marketing Corp',
            'job_title'        => 'Part-Time YouTube Video Reviewer',
            'platform'         => 'Telegram',
            'scam_type'        => 'Task / Rating / Investment Scam',
            'payment_demanded' => 'yes',
            'payment_amount'   => '₹10,000 for VIP task tier activation',
            'contact_email'    => '',
            'contact_phone'    => '+91 87654 32109',
            'contact_other'    => '@globalmedia_task_reception',
            'description'      => "Recruiter reached out via WhatsApp offering ₹150 for every YouTube video liked and screenshotted. They initially paid ₹450 for the first 3 tasks to build trust, then moved me to a Telegram group with 50+ supposed workers. They demanded a deposit of ₹10,000 into a crypto wallet to unlock 'VIP high-commission tasks'. When I refused, they locked my earnings balance.",
            'evidence_text'    => "Telegram group screenshots and fake bank transaction slips shared by group bots.",
            'red_flags'        => ['fee_demanded', 'chat_only', 'unrealistic_salary', 'vague_tasks', 'urgent_pressure'],
        ], $priya);

        // Approve Report 2
        Report::updateStatus($r2['id'], Report::STATUS_APPROVED, $admin, "Confirmed classic task/prepaid recharge scam archetype. Matches known Telegram bot syndicates.");
        logMsg("Seeded Report #{$r2['id']} (Global Media Marketing Corp) -> Approved", $isCli);

        // Report 3: Amazon Recruiter Impersonation
        $r3 = Report::create([
            'company_name'     => 'Amazon Customer Support Operations (Impersonation)',
            'job_title'        => 'Work-from-Home Customer Support Executive',
            'platform'         => 'WhatsApp',
            'scam_type'        => 'Impersonation of Reputed Company',
            'payment_demanded' => 'no',
            'payment_amount'   => '',
            'contact_email'    => 'amazon.recruitment.desk@gmail.com',
            'contact_phone'    => '+91 91234 56789',
            'contact_other'    => 'WhatsApp group: Amazon WFH Batch 2026',
            'description'      => "The recruiter used the Amazon logo as their WhatsApp DP and claimed to be hiring 200 freshers for night shift customer support. They asked for Aadhaar card, PAN card, and bank account cancelled cheque before any written test or interview. Upon checking with official Amazon HR, this recruiter and batch was confirmed fraudulent.",
            'evidence_text'    => "WhatsApp chat history and phishing form link (docs.google.com/forms/d/e/...)",
            'red_flags'        => ['no_interview', 'free_email', 'chat_only', 'sensitive_info'],
        ], $student);

        // Approve Report 3
        Report::updateStatus($r3['id'], Report::STATUS_APPROVED, $admin, "Phishing campaign confirmed targeting student identity credentials for mule accounts.");
        logMsg("Seeded Report #{$r3['id']} (Amazon Impersonation) -> Approved", $isCli);

        // Report 4: Pending Review - Data Entry Scam
        $r4 = Report::create([
            'company_name'     => 'FastTypers Digital Services',
            'job_title'        => 'Online Data Entry Freelancer',
            'platform'         => 'Job Portal (Indeed, Naukri, Glassdoor)',
            'scam_type'        => 'Remote Work / Data Entry Fraud',
            'payment_demanded' => 'yes',
            'payment_amount'   => '₹1,200 Registration Stamp Paper Fee',
            'contact_email'    => 'careers@fasttypers-work.in',
            'contact_phone'    => '+91 97890 12345',
            'contact_other'    => 'fasttypers-work.in',
            'description'      => "Promised ₹50 per page data entry. After submitting 100 pages, they alleged 98% accuracy failure based on hidden rules and threatened legal action via fake lawyer notices unless a settlement fee of ₹1,200 was paid immediately.",
            'evidence_text'    => "Draft legal notice PDF on fake advocate letterhead.",
            'red_flags'        => ['fee_demanded', 'urgent_pressure', 'vague_tasks'],
        ], $student);
        // Leave Report 4 in Pending status so moderation queue has active items!
        logMsg("Seeded Report #{$r4['id']} (FastTypers Digital Services) -> Status: Pending", $isCli);

        // Report 5: Under Review - Campus Placement Impersonator
        $r5 = Report::create([
            'company_name'     => 'Tata Tech Global Placement Desk',
            'job_title'        => 'Graduate Engineer Trainee (GET)',
            'platform'         => 'Email / Cold Outreach',
            'scam_type'        => 'Fake Offer Letter',
            'payment_demanded' => 'yes',
            'payment_amount'   => '₹3,000 Medical Examination Fee',
            'contact_email'    => 'careers-tataglobal@outlook.com',
            'contact_phone'    => '+91 94567 89012',
            'contact_other'    => '',
            'description'      => "Email claimed I was shortlisted based on college training and placement cell database. They asked me to attend an interview at a private hotel and deposit medical examination fee in advance.",
            'evidence_text'    => "Email headers show sent from residential IP in another state.",
            'red_flags'        => ['fee_demanded', 'free_email', 'suspicious_domain'],
        ], $priya);

        // Set Report 5 to Under Review
        Report::updateStatus($r5['id'], Report::STATUS_REVIEW, $admin, "Under investigation: cross-referencing college placement cell advisory lists.");
        logMsg("Seeded Report #{$r5['id']} (Tata Tech Global) -> Status: Under Review", $isCli);

        // Report 6: Duplicate Report
        $r6 = Report::create([
            'company_name'     => 'Apex Infotech Solutions (Duplicate)',
            'job_title'        => 'Junior Web Developer',
            'platform'         => 'LinkedIn',
            'scam_type'        => 'Fake Offer Letter',
            'payment_demanded' => 'yes',
            'payment_amount'   => '₹4,500',
            'contact_email'    => 'hr.apexinfotech@gmail.com',
            'contact_phone'    => '+91 98231 09841',
            'contact_other'    => '',
            'description'      => "Same company as Report #1, asking for ₹4,500 laptop deposit.",
            'evidence_text'    => "Identical to report #1.",
            'red_flags'        => ['fee_demanded', 'no_interview', 'free_email'],
        ], $student);

        // Set Report 6 to Duplicate
        Report::updateStatus($r6['id'], Report::STATUS_DUPLICATE, $admin, "Duplicate of Report #1. Consolidating under master record.");
        logMsg("Seeded Report #{$r6['id']} (Duplicate) -> Status: Duplicate", $isCli);
    } else {
        logMsg("Reports already exist in Redis (Count: {$existingCount}). Skipping report creation.", $isCli);
    }

    $finalStats = Report::getStats();
    logMsg("Seeding completed successfully!", $isCli);
    logMsg("Total Reports: {$finalStats['total']} | Approved: {$finalStats['approved']} | Pending: {$finalStats['pending']} | Under Review: {$finalStats['review']} | Registered Users: {$finalStats['users']}", $isCli);

    if (!$isCli) {
        echo "<div style='margin-top: 1.5rem;'><a href='index.php' class='btn btn-primary'>Go to Public Reports</a> <a href='login.php' class='btn btn-secondary' style='margin-left: 0.5rem;'>Go to Login</a></div></div></div></body></html>";
    }
} catch (Throwable $e) {
    logMsg("ERROR during seeding: " . $e->getMessage(), $isCli);
    if (!$isCli) {
        echo "<div class='alert alert-danger' style='margin-top: 1rem;'>" . htmlspecialchars($e->getMessage()) . "</div></div></div></body></html>";
    }
}
