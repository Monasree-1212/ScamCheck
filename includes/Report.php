<?php

namespace ScamCheck;

use Predis\Client;
use InvalidArgumentException;
use RuntimeException;

class Report
{
    public const STATUS_PENDING   = 'Pending';
    public const STATUS_REVIEW    = 'Under Review';
    public const STATUS_APPROVED  = 'Approved for Display';
    public const STATUS_REJECTED  = 'Rejected';
    public const STATUS_DUPLICATE = 'Duplicate';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_DUPLICATE,
    ];

    public const PLATFORMS = [
        'LinkedIn',
        'WhatsApp',
        'Telegram',
        'Email / Cold Outreach',
        'Job Portal (Indeed, Naukri, Glassdoor)',
        'Instagram / Social Media',
        'SMS / Text Message',
        'Campus Placement Drive / WhatsApp Group',
        'Freelance Platform (Upwork, Fiverr)',
        'Other / Direct Website'
    ];

    public const SCAM_TYPES = [
        'Fake Offer Letter',
        'Payment Demand / Training / Laptop Fee',
        'Phishing / Identity Theft',
        'Task / Rating / Investment Scam',
        'Remote Work / Data Entry Fraud',
        'Impersonation of Reputed Company',
        'Unpaid Internship / Exploitative Work',
        'Check Overpayment Fraud',
        'Other Deceptive Practice'
    ];

    private static function getRedis(): Client
    {
        return Database::getClient();
    }

    public static function create(array $data, array $user): array
    {
        $redis = self::getRedis();

        $companyName = trim($data['company_name'] ?? '');
        $jobTitle    = trim($data['job_title'] ?? '');
        $platform    = trim($data['platform'] ?? 'Other / Direct Website');
        $scamType    = trim($data['scam_type'] ?? 'Other Deceptive Practice');
        $description = trim($data['description'] ?? '');

        if (empty($companyName)) {
            throw new InvalidArgumentException("Company or organization name is required.");
        }
        if (empty($jobTitle)) {
            throw new InvalidArgumentException("Job or internship title is required.");
        }
        if (empty($description) || strlen($description) < 15) {
            throw new InvalidArgumentException("Please provide a descriptive explanation (minimum 15 characters).");
        }

        $paymentDemanded = strtolower(trim($data['payment_demanded'] ?? 'no')) === 'yes' ? 'yes' : 'no';
        $paymentAmount   = trim($data['payment_amount'] ?? '');
        $contactEmail    = trim($data['contact_email'] ?? '');
        $contactPhone    = trim($data['contact_phone'] ?? '');
        $contactOther    = trim($data['contact_other'] ?? '');
        $evidenceText    = trim($data['evidence_text'] ?? '');

        $rawFlags = $data['red_flags'] ?? [];
        if (!is_array($rawFlags)) {
            $rawFlags = [];
        }

        $riskResult = RiskCalculator::calculate($rawFlags, $paymentDemanded === 'yes', $contactEmail);
        $riskScore  = $riskResult['score'];
        $riskLevel  = $riskResult['level'];
        $cleanFlags = $riskResult['normalized_flags'];

        $reportId = $redis->incr('reports:counter');
        $now = date('Y-m-d H:i:s');
        $timestamp = time();

        $reportData = [
            'id'               => (string)$reportId,
            'user_id'          => (string)($user['id'] ?? 0),
            'user_name'        => (string)($user['username'] ?? 'Anonymous'),
            'user_email'       => (string)($user['email'] ?? ''),
            'company_name'     => $companyName,
            'job_title'        => $jobTitle,
            'platform'         => $platform,
            'scam_type'        => $scamType,
            'contact_email'    => $contactEmail,
            'contact_phone'    => $contactPhone,
            'contact_other'    => $contactOther,
            'payment_demanded' => $paymentDemanded,
            'payment_amount'   => $paymentAmount,
            'description'      => $description,
            'evidence_text'    => $evidenceText,
            'red_flags'        => json_encode($cleanFlags),
            'risk_score'       => (string)$riskScore,
            'risk_level'       => $riskLevel,
            'status'           => self::STATUS_PENDING,
            'admin_notes'      => '',
            'reviewed_by'      => '',
            'reviewed_at'      => '',
            'views_count'      => '0',
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        // Pipeline execution to store hash and maintain all indexes
        $pipe = $redis->pipeline();
        $pipe->hmset("reports:{$reportId}", $reportData);
        $pipe->zadd('reports:all', [$reportId => $timestamp]);
        $pipe->sadd("reports:by_status:" . self::STATUS_PENDING, [(string)$reportId]);
        $pipe->zadd("reports:by_user:" . $user['id'], [$reportId => $timestamp]);
        $pipe->sadd("reports:by_type:{$scamType}", [(string)$reportId]);
        $pipe->sadd("reports:by_risk:{$riskLevel}", [(string)$reportId]);
        $pipe->execute();

        // Index search tokens
        self::indexTokens((string)$reportId, $reportData);

        // Initial review history audit log
        self::appendReviewLog((string)$reportId, [
            'action'        => 'Created',
            'reviewer_id'   => (string)$user['id'],
            'reviewer_name' => (string)$user['username'],
            'old_status'    => '',
            'new_status'    => self::STATUS_PENDING,
            'notes'         => 'Report submitted by user.',
            'timestamp'     => $now
        ]);

        $reportData['red_flags'] = $cleanFlags;
        return $reportData;
    }

    public static function findById(int|string $id): ?array
    {
        $redis = self::getRedis();
        $report = $redis->hgetall("reports:{$id}");
        if (empty($report)) {
            return null;
        }

        if (!empty($report['red_flags'])) {
            $decoded = json_decode($report['red_flags'], true);
            $report['red_flags'] = is_array($decoded) ? $decoded : [];
        } else {
            $report['red_flags'] = [];
        }

        $report['risk_score'] = (int)($report['risk_score'] ?? 0);
        $report['views_count'] = (int)($report['views_count'] ?? 0);

        return $report;
    }

    public static function update(int|string $id, array $data, ?array $user = null): bool
    {
        $existing = self::findById($id);
        if (!$existing) {
            return false;
        }

        $isAdmin = ($user['role'] ?? '') === 'admin';
        $isOwner = ((string)($user['id'] ?? '')) === ((string)$existing['user_id']);

        if (!$isAdmin && !$isOwner) {
            throw new RuntimeException("Unauthorized: You do not have permission to edit this report.");
        }

        if (!$isAdmin && !in_array($existing['status'], [self::STATUS_PENDING, self::STATUS_REVIEW], true)) {
            throw new RuntimeException("This report has already been reviewed ({$existing['status']}) and cannot be edited by the user.");
        }

        $redis = self::getRedis();

        $companyName = trim($data['company_name'] ?? $existing['company_name']);
        $jobTitle    = trim($data['job_title'] ?? $existing['job_title']);
        $platform    = trim($data['platform'] ?? $existing['platform']);
        $scamType    = trim($data['scam_type'] ?? $existing['scam_type']);
        $description = trim($data['description'] ?? $existing['description']);

        if (empty($companyName) || empty($jobTitle) || empty($description)) {
            throw new InvalidArgumentException("Company name, job title, and description cannot be empty.");
        }

        $paymentDemanded = isset($data['payment_demanded'])
            ? (strtolower(trim($data['payment_demanded'])) === 'yes' ? 'yes' : 'no')
            : $existing['payment_demanded'];

        $paymentAmount = trim($data['payment_amount'] ?? $existing['payment_amount']);
        $contactEmail  = trim($data['contact_email'] ?? $existing['contact_email']);
        $contactPhone  = trim($data['contact_phone'] ?? $existing['contact_phone']);
        $contactOther  = trim($data['contact_other'] ?? $existing['contact_other']);
        $evidenceText  = trim($data['evidence_text'] ?? $existing['evidence_text']);

        $rawFlags = $data['red_flags'] ?? $existing['red_flags'];
        if (!is_array($rawFlags)) {
            $rawFlags = [];
        }

        $riskResult = RiskCalculator::calculate($rawFlags, $paymentDemanded === 'yes', $contactEmail);
        $riskScore  = $riskResult['score'];
        $riskLevel  = $riskResult['level'];
        $cleanFlags = $riskResult['normalized_flags'];

        $now = date('Y-m-d H:i:s');

        $updates = [
            'company_name'     => $companyName,
            'job_title'        => $jobTitle,
            'platform'         => $platform,
            'scam_type'        => $scamType,
            'contact_email'    => $contactEmail,
            'contact_phone'    => $contactPhone,
            'contact_other'    => $contactOther,
            'payment_demanded' => $paymentDemanded,
            'payment_amount'   => $paymentAmount,
            'description'      => $description,
            'evidence_text'    => $evidenceText,
            'red_flags'        => json_encode($cleanFlags),
            'risk_score'       => (string)$riskScore,
            'risk_level'       => $riskLevel,
            'updated_at'       => $now,
        ];

        // Manage index changes if scam type or risk level changed
        $pipe = $redis->pipeline();
        if ($scamType !== $existing['scam_type']) {
            $pipe->srem("reports:by_type:{$existing['scam_type']}", [(string)$id]);
            $pipe->sadd("reports:by_type:{$scamType}", [(string)$id]);
        }
        if ($riskLevel !== $existing['risk_level']) {
            $pipe->srem("reports:by_risk:{$existing['risk_level']}", [(string)$id]);
            $pipe->sadd("reports:by_risk:{$riskLevel}", [(string)$id]);
        }
        $pipe->hmset("reports:{$id}", $updates);
        $pipe->execute();

        // Update search tokens
        self::deindexTokens((string)$id, $existing);
        self::indexTokens((string)$id, array_merge($existing, $updates));

        // Audit log entry
        self::appendReviewLog((string)$id, [
            'action'        => 'Updated',
            'reviewer_id'   => (string)($user['id'] ?? '0'),
            'reviewer_name' => (string)($user['username'] ?? 'User'),
            'old_status'    => $existing['status'],
            'new_status'    => $existing['status'],
            'notes'         => 'Report content updated.',
            'timestamp'     => $now
        ]);

        return true;
    }

    public static function updateStatus(int|string $id, string $newStatus, array $adminUser, string $notes = ''): bool
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$newStatus}");
        }

        $existing = self::findById($id);
        if (!$existing) {
            return false;
        }

        $oldStatus = $existing['status'];
        $redis = self::getRedis();
        $now = date('Y-m-d H:i:s');
        $adminName = $adminUser['username'] ?? 'Admin';

        $updates = [
            'status'      => $newStatus,
            'reviewed_by' => $adminName,
            'reviewed_at' => $now,
            'admin_notes' => trim($notes),
            'updated_at'  => $now,
        ];

        $pipe = $redis->pipeline();
        $pipe->srem("reports:by_status:{$oldStatus}", [(string)$id]);
        $pipe->sadd("reports:by_status:{$newStatus}", [(string)$id]);
        $pipe->hmset("reports:{$id}", $updates);
        $pipe->execute();

        self::appendReviewLog((string)$id, [
            'action'        => 'Status Changed',
            'reviewer_id'   => (string)($adminUser['id'] ?? '0'),
            'reviewer_name' => $adminName,
            'old_status'    => $oldStatus,
            'new_status'    => $newStatus,
            'notes'         => trim($notes) ?: "Status changed from {$oldStatus} to {$newStatus}.",
            'timestamp'     => $now
        ]);

        return true;
    }

    public static function delete(int|string $id, ?array $user = null): bool
    {
        $existing = self::findById($id);
        if (!$existing) {
            return false;
        }

        $isAdmin = ($user['role'] ?? '') === 'admin';
        $isOwner = ((string)($user['id'] ?? '')) === ((string)$existing['user_id']);

        if (!$isAdmin && !$isOwner) {
            throw new RuntimeException("Unauthorized: You do not have permission to delete this report.");
        }

        $redis = self::getRedis();

        // Remove from all sets and indexes
        $pipe = $redis->pipeline();
        $pipe->zrem('reports:all', [(string)$id]);
        $pipe->srem("reports:by_status:{$existing['status']}", [(string)$id]);
        $pipe->zrem("reports:by_user:{$existing['user_id']}", [(string)$id]);
        $pipe->srem("reports:by_type:{$existing['scam_type']}", [(string)$id]);
        $pipe->srem("reports:by_risk:{$existing['risk_level']}", [(string)$id]);
        $pipe->del(["reports:{$id}", "reports:reviews:{$id}"]);
        $pipe->execute();

        self::deindexTokens((string)$id, $existing);

        return true;
    }

    public static function incrementViews(int|string $id): void
    {
        $redis = self::getRedis();
        $redis->hincrby("reports:{$id}", 'views_count', 1);
    }

    public static function getReviewHistory(int|string $id): array
    {
        $redis = self::getRedis();
        $rawLogs = $redis->lrange("reports:reviews:{$id}", 0, -1);
        if (empty($rawLogs)) {
            return [];
        }

        $logs = [];
        foreach ($rawLogs as $log) {
            $decoded = json_decode($log, true);
            if (is_array($decoded)) {
                $logs[] = $decoded;
            }
        }
        return $logs;
    }

    private static function appendReviewLog(string $id, array $entry): void
    {
        $redis = self::getRedis();
        $redis->rpush("reports:reviews:{$id}", [json_encode($entry)]);
    }

    public static function getReports(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $redis = self::getRedis();

        // 1. Determine base candidate IDs
        $candidateIds = null;

        // Status filter
        if (!empty($filters['status'])) {
            $statusSet = (array)$redis->smembers("reports:by_status:" . $filters['status']);
            $candidateIds = $candidateIds === null ? $statusSet : array_intersect($candidateIds, $statusSet);
        }

        // User filter
        if (!empty($filters['user_id'])) {
            $userReports = (array)$redis->zrevrange("reports:by_user:" . $filters['user_id'], 0, -1);
            $candidateIds = $candidateIds === null ? $userReports : array_intersect($candidateIds, $userReports);
        }

        // Scam type filter
        if (!empty($filters['scam_type'])) {
            $typeSet = (array)$redis->smembers("reports:by_type:" . $filters['scam_type']);
            $candidateIds = $candidateIds === null ? $typeSet : array_intersect($candidateIds, $typeSet);
        }

        // Risk level filter
        if (!empty($filters['risk_level'])) {
            $riskSet = (array)$redis->smembers("reports:by_risk:" . $filters['risk_level']);
            $candidateIds = $candidateIds === null ? $riskSet : array_intersect($candidateIds, $riskSet);
        }

        // Keyword search via inverted index tokens
        if (!empty($filters['search'])) {
            $searchTokens = self::tokenize($filters['search']);
            if (!empty($searchTokens)) {
                $tokenMatches = [];
                foreach ($searchTokens as $token) {
                    $tokenSet = (array)$redis->smembers("search:tokens:{$token}");
                    $tokenMatches = array_merge($tokenMatches, $tokenSet);
                }
                $tokenMatches = array_unique($tokenMatches);
                $candidateIds = $candidateIds === null ? $tokenMatches : array_intersect($candidateIds, $tokenMatches);
            }
        }

        // If no filter was specified, start from all reports ordered by score (timestamp)
        if ($candidateIds === null) {
            $candidateIds = (array)$redis->zrevrange('reports:all', 0, -1);
        } else {
            // Re-order candidates by timestamp from reports:all
            $candidateIds = array_values($candidateIds);
        }

        $totalCount = count($candidateIds);

        // Fetch hashes for the matched IDs
        $reports = [];
        foreach ($candidateIds as $id) {
            $report = self::findById($id);
            if ($report) {
                $reports[] = $report;
            }
        }

        // Sorting
        $sort = $filters['sort'] ?? 'newest';
        if ($sort === 'oldest') {
            usort($reports, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
        } elseif ($sort === 'highest_risk') {
            usort($reports, fn($a, $b) => ($b['risk_score'] <=> $a['risk_score']) ?: strcmp($b['created_at'], $a['created_at']));
        } else {
            // default: newest
            usort($reports, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        }

        // Pagination
        $paginated = array_slice($reports, $offset, $limit);

        return [
            'reports' => $paginated,
            'total'   => $totalCount,
            'limit'   => $limit,
            'offset'  => $offset
        ];
    }

    public static function getStats(): array
    {
        $redis = self::getRedis();

        $totalReports  = (int)$redis->zcard('reports:all');
        $pendingCount  = (int)$redis->scard('reports:by_status:' . self::STATUS_PENDING);
        $reviewCount   = (int)$redis->scard('reports:by_status:' . self::STATUS_REVIEW);
        $approvedCount = (int)$redis->scard('reports:by_status:' . self::STATUS_APPROVED);
        $rejectedCount = (int)$redis->scard('reports:by_status:' . self::STATUS_REJECTED);
        $dupCount      = (int)$redis->scard('reports:by_status:' . self::STATUS_DUPLICATE);
        $usersCount    = (int)$redis->scard('users:all');

        $criticalCount = (int)$redis->scard('reports:by_risk:Critical');
        $highCount     = (int)$redis->scard('reports:by_risk:High');
        $mediumCount   = (int)$redis->scard('reports:by_risk:Medium');
        $lowCount      = (int)$redis->scard('reports:by_risk:Low');

        return [
            'total'       => $totalReports,
            'pending'     => $pendingCount,
            'review'      => $reviewCount,
            'approved'    => $approvedCount,
            'rejected'    => $rejectedCount,
            'duplicate'   => $dupCount,
            'users'       => $usersCount,
            'critical'    => $criticalCount,
            'high'        => $highCount,
            'medium'      => $mediumCount,
            'low'         => $lowCount,
        ];
    }

    private static function tokenize(string $text): array
    {
        $text = strtolower(strip_tags($text));
        $cleaned = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        $stopwords = ['and', 'the', 'for', 'with', 'from', 'this', 'that', 'they', 'have', 'were', 'been', 'about', 'your'];
        
        $tokens = [];
        foreach ($words as $w) {
            if (strlen($w) >= 2 && !in_array($w, $stopwords, true)) {
                $tokens[] = $w;
            }
        }
        return array_values(array_unique($tokens));
    }

    private static function indexTokens(string $id, array $report): void
    {
        $redis = self::getRedis();
        $corpus = implode(' ', [
            $report['company_name'] ?? '',
            $report['job_title'] ?? '',
            $report['platform'] ?? '',
            $report['scam_type'] ?? '',
            $report['contact_email'] ?? '',
            $report['contact_phone'] ?? '',
            $report['contact_other'] ?? '',
            $report['description'] ?? '',
            $report['evidence_text'] ?? '',
        ]);

        $tokens = self::tokenize($corpus);
        if (!empty($tokens)) {
            $pipe = $redis->pipeline();
            foreach ($tokens as $token) {
                $pipe->sadd("search:tokens:{$token}", [(string)$id]);
            }
            $pipe->execute();
        }
    }

    private static function deindexTokens(string $id, array $report): void
    {
        $redis = self::getRedis();
        $corpus = implode(' ', [
            $report['company_name'] ?? '',
            $report['job_title'] ?? '',
            $report['platform'] ?? '',
            $report['scam_type'] ?? '',
            $report['contact_email'] ?? '',
            $report['contact_phone'] ?? '',
            $report['contact_other'] ?? '',
            $report['description'] ?? '',
            $report['evidence_text'] ?? '',
        ]);

        $tokens = self::tokenize($corpus);
        if (!empty($tokens)) {
            $pipe = $redis->pipeline();
            foreach ($tokens as $token) {
                $pipe->srem("search:tokens:{$token}", [(string)$id]);
            }
            $pipe->execute();
        }
    }
}
