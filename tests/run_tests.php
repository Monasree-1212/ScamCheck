<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use ScamCheck\Database;
use ScamCheck\Config;
use ScamCheck\User;
use ScamCheck\Report;
use ScamCheck\RiskCalculator;
use ScamCheck\Auth;
use ScamCheck\Helper;

$isCli = (php_sapi_name() === 'cli');

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $results = [];
    private bool $isCli;

    public function __construct(bool $isCli)
    {
        $this->isCli = $isCli;
    }

    public function assert(string $testName, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            $this->results[] = ['name' => $testName, 'status' => 'PASS', 'detail' => $detail];
            if ($this->isCli) {
                echo "[PASS] {$testName}\n";
            }
        } else {
            $this->failed++;
            $this->results[] = ['name' => $testName, 'status' => 'FAIL', 'detail' => $detail];
            if ($this->isCli) {
                echo "[FAIL] {$testName} - Detail: {$detail}\n";
            }
        }
    }

    public function getPassed(): int { return $this->passed; }
    public function getFailed(): int { return $this->failed; }
    public function getResults(): array { return $this->results; }
}

$runner = new TestRunner($isCli);

if ($isCli) {
    echo "========================================================\n";
    echo " ScamCheck Redis - Comprehensive Test Suite\n";
    echo "========================================================\n\n";
}

// TEST 1: Redis Connection & Ping
try {
    $pingOk = Database::ping();
    $runner->assert("Redis Connection & Ping", $pingOk === true, "Database::ping() returned true");
} catch (Throwable $e) {
    $runner->assert("Redis Connection & Ping", false, $e->getMessage());
}

// TEST 2: Namespace & Prefix Isolation
try {
    $prefix = Database::getPrefix();
    $runner->assert("Redis Prefix Configuration", $prefix === 'scamcheck_redis:', "Configured prefix is: {$prefix}");
} catch (Throwable $e) {
    $runner->assert("Redis Prefix Configuration", false, $e->getMessage());
}

// TEST 3: Risk Calculator Rules
try {
    $calc1 = RiskCalculator::calculate(['fee_demanded', 'no_interview']);
    $score1 = $calc1['score']; // 35 + 20 = 55
    $runner->assert("Risk Calculation: Fee + No Interview (55pts => Medium)", $score1 === 55 && $calc1['level'] === 'Medium', "Score: {$score1}, Level: {$calc1['level']}");

    $calc2 = RiskCalculator::calculate(['fee_demanded', 'no_interview', 'free_email', 'chat_only', 'unrealistic_salary', 'sensitive_info']);
    // 35+20+15+15+10+15 = 110 => capped at 100 => Critical
    $runner->assert("Risk Calculation: Cap at 100pts => Critical", $calc2['score'] === 100 && $calc2['level'] === 'Critical', "Score: {$calc2['score']}, Level: {$calc2['level']}");
} catch (Throwable $e) {
    $runner->assert("Risk Calculation Logic", false, $e->getMessage());
}

// TEST 4: User Model CRUD & Duplicate Prevention
$testEmail = 'test_user_' . time() . '@example.com';
$testUsername = 'testuser_' . time();
$createdUserId = null;

try {
    $user = User::create([
        'username'  => $testUsername,
        'email'     => $testEmail,
        'password'  => 'Secret@123',
        'full_name' => 'Unit Tester',
        'role'      => 'user',
    ]);
    $createdUserId = $user['id'];
    $runner->assert("User Creation in Redis", !empty($createdUserId) && $user['username'] === $testUsername, "User ID: {$createdUserId}");

    // Test findByEmail
    $foundByEmail = User::findByEmail($testEmail);
    $runner->assert("User Lookup by Email", $foundByEmail !== null && $foundByEmail['id'] === $createdUserId, "Found ID: " . ($foundByEmail['id'] ?? 'null'));

    // Test password verification
    $authCheck = User::findByCredentials($testEmail, 'Secret@123');
    $runner->assert("User Credential Authentication (bcrypt verify)", $authCheck !== null && $authCheck['id'] === $createdUserId, "Auth verified");

    // Test duplicate email rejection
    $duplicateThrown = false;
    try {
        User::create([
            'username' => 'another_' . time(),
            'email'    => $testEmail,
            'password' => 'Secret@123',
        ]);
    } catch (InvalidArgumentException) {
        $duplicateThrown = true;
    }
    $runner->assert("User Duplicate Email Prevention", $duplicateThrown, "Caught expected duplicate exception");
} catch (Throwable $e) {
    $runner->assert("User Model CRUD", false, $e->getMessage());
}

// TEST 5: Report Creation & Indexing
$testReportId = null;
try {
    $mockUser = ['id' => $createdUserId ?: '1', 'username' => $testUsername, 'email' => $testEmail, 'role' => 'user'];
    $report = Report::create([
        'company_name'     => 'Test Scam Corp ' . time(),
        'job_title'        => 'Fake Quality Tester',
        'platform'         => 'Telegram',
        'scam_type'        => 'Task / Rating / Investment Scam',
        'payment_demanded' => 'yes',
        'payment_amount'   => '₹5,000 deposit',
        'contact_email'    => 'recruiter.fake@gmail.com',
        'contact_phone'    => '+91 99999 11111',
        'contact_other'    => '@fake_telegram_bot',
        'description'      => 'This is a synthetic test report for automated verification of Redis CRUD operations.',
        'evidence_text'    => 'Test evidence url https://example.com/proof.png',
        'red_flags'        => ['fee_demanded', 'chat_only'],
    ], $mockUser);

    $testReportId = $report['id'];
    $runner->assert("Report Creation & Hash Storage", !empty($testReportId) && $report['status'] === Report::STATUS_PENDING, "Report ID: {$testReportId}");

    // Verify retrieval
    $retrieved = Report::findById($testReportId);
    $runner->assert("Report Retrieval by ID", $retrieved !== null && $retrieved['company_name'] === $report['company_name'], "Company: " . ($retrieved['company_name'] ?? 'none'));

    // Verify index membership in Redis sets
    $redis = Database::getClient();
    $inPendingSet = (bool)$redis->sismember('reports:by_status:' . Report::STATUS_PENDING, $testReportId);
    $runner->assert("Report Index: Member of reports:by_status:Pending", $inPendingSet, "sismember returned true");

    $inUserSet = $redis->zscore("reports:by_user:{$mockUser['id']}", $testReportId);
    $runner->assert("Report Index: Member of reports:by_user:{$mockUser['id']}", $inUserSet !== null, "zscore exists: {$inUserSet}");
} catch (Throwable $e) {
    $runner->assert("Report Creation & Indexing", false, $e->getMessage());
}

// TEST 6: Inverted Index Search
try {
    $searchRes = Report::getReports(['search' => 'synthetic'], 10, 0);
    $foundSearch = false;
    foreach ($searchRes['reports'] as $r) {
        if ($r['id'] === (string)$testReportId) {
            $foundSearch = true;
            break;
        }
    }
    $runner->assert("Search Inverted Index Match on Token 'synthetic'", $foundSearch, "Report #{$testReportId} matched via token set");
} catch (Throwable $e) {
    $runner->assert("Search Inverted Index", false, $e->getMessage());
}

// TEST 7: Status Moderation & Audit Log
try {
    $adminUser = ['id' => '1', 'username' => 'admin', 'role' => 'admin'];
    $statusUpdated = Report::updateStatus($testReportId, Report::STATUS_APPROVED, $adminUser, "Automated test approval note.");
    $runner->assert("Report Status Update to 'Approved for Display'", $statusUpdated === true, "updateStatus returned true");

    $updatedReport = Report::findById($testReportId);
    $runner->assert("Report Status Verified in Hash", $updatedReport['status'] === Report::STATUS_APPROVED, "Current status: {$updatedReport['status']}");

    $redis = Database::getClient();
    $notInPending = !(bool)$redis->sismember('reports:by_status:' . Report::STATUS_PENDING, $testReportId);
    $inApproved   = (bool)$redis->sismember('reports:by_status:' . Report::STATUS_APPROVED, $testReportId);
    $runner->assert("Status Set Migration (Removed from Pending, Added to Approved)", $notInPending && $inApproved, "Set membership verified");

    // Audit log check
    $history = Report::getReviewHistory($testReportId);
    $hasApprovalLog = false;
    foreach ($history as $h) {
        if (($h['new_status'] ?? '') === Report::STATUS_APPROVED) {
            $hasApprovalLog = true;
            break;
        }
    }
    $runner->assert("Moderation Audit Trail Appended", $hasApprovalLog && count($history) >= 2, "History entries count: " . count($history));
} catch (Throwable $e) {
    $runner->assert("Status Moderation & Audit Log", false, $e->getMessage());
}

// TEST 8: Report Update
try {
    $updateSuccess = Report::update($testReportId, [
        'company_name' => 'Updated Test Corp Name',
        'job_title'    => 'Updated Role',
        'description'  => 'Updated description text with more than 15 characters.',
        'red_flags'    => ['fee_demanded', 'no_interview', 'urgent_pressure'],
    ], $adminUser);
    $runner->assert("Report Metadata & Red Flags Update", $updateSuccess === true, "update returned true");

    $refetched = Report::findById($testReportId);
    $runner->assert("Updated Report Hash Verification", $refetched['company_name'] === 'Updated Test Corp Name', "New name: {$refetched['company_name']}");
} catch (Throwable $e) {
    $runner->assert("Report Update", false, $e->getMessage());
}

// TEST 9: Report Deletion & Index Cleanup
try {
    $deleteSuccess = Report::delete($testReportId, $adminUser);
    $runner->assert("Report Deletion by Authorized Admin", $deleteSuccess === true, "delete returned true");

    $afterDelete = Report::findById($testReportId);
    $runner->assert("Report Hash Removed from Redis", $afterDelete === null, "findById returned null");

    $redis = Database::getClient();
    $notInAll = $redis->zscore('reports:all', $testReportId) === null;
    $notInAppr = !(bool)$redis->sismember('reports:by_status:' . Report::STATUS_APPROVED, $testReportId);
    $runner->assert("Index Cleanup (Removed from reports:all and status sets)", $notInAll && $notInAppr, "Indexes cleaned");
} catch (Throwable $e) {
    $runner->assert("Report Deletion & Cleanup", false, $e->getMessage());
}

// TEST 10: CSRF Protection & Security Helpers
try {
    $token = Auth::generateCsrfToken();
    $valid = Auth::validateCsrfToken($token);
    $invalid = Auth::validateCsrfToken('bad_token_value_xyz');
    $runner->assert("CSRF Token Generation & hash_equals Validation", $valid === true && $invalid === false, "Valid checked true, invalid checked false");
} catch (Throwable $e) {
    $runner->assert("CSRF Protection", false, $e->getMessage());
}

if ($isCli) {
    echo "\n========================================================\n";
    echo " RESULTS: {$runner->getPassed()} Passed, {$runner->getFailed()} Failed\n";
    echo "========================================================\n";
} else {
    echo "<!DOCTYPE html><html><head><title>Test Suite - ScamCheck Redis</title><link rel='stylesheet' href='../assets/css/style.css'></head><body style='padding: 2rem;'><div class='panel' style='max-width: 900px; margin: 0 auto;'><div class='panel-header'>ScamCheck Redis Automated Test Suite</div><div class='panel-body'><div class='stats-grid'><div class='stat-box'><div class='stat-label'>Passed</div><div class='stat-value' style='color: #166534;'>{$runner->getPassed()}</div></div><div class='stat-box'><div class='stat-label'>Failed</div><div class='stat-value' style='color: #991b1b;'>{$runner->getFailed()}</div></div></div><table class='table'><thead><tr><th>Status</th><th>Test Description</th><th>Detail</th></tr></thead><tbody>";
    foreach ($runner->getResults() as $res) {
        $badge = $res['status'] === 'PASS' ? "<span class='badge badge-approved'>PASS</span>" : "<span class='badge badge-rejected'>FAIL</span>";
        echo "<tr><td>{$badge}</td><td><strong>" . htmlspecialchars($res['name']) . "</strong></td><td>" . htmlspecialchars($res['detail']) . "</td></tr>";
    }
    echo "</tbody></table><div style='margin-top: 1.5rem;'><a href='../index.php' class='btn btn-primary'>Return to Application</a></div></div></div></body></html>";
}
