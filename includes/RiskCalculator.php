<?php

namespace ScamCheck;

class RiskCalculator
{
    public const RED_FLAGS = [
        'fee_demanded' => [
            'label' => 'Payment Demanded (Registration, Training, Laptop, ID card, or Security Deposit)',
            'weight' => 35,
            'category' => 'Financial'
        ],
        'no_interview' => [
            'label' => 'Direct Offer Issued without Formal Screening or Interview',
            'weight' => 20,
            'category' => 'Process'
        ],
        'free_email' => [
            'label' => 'Recruiter uses generic free email (@gmail, @yahoo, etc.) instead of official corporate domain',
            'weight' => 15,
            'category' => 'Verification'
        ],
        'chat_only' => [
            'label' => 'Hiring/Interview process conducted exclusively via Telegram, WhatsApp, or Signal',
            'weight' => 15,
            'category' => 'Communication'
        ],
        'unrealistic_salary' => [
            'label' => 'Unusually high salary/stipend promised for entry-level or minimal work',
            'weight' => 10,
            'category' => 'Offer'
        ],
        'sensitive_info' => [
            'label' => 'Demanded bank account credentials, OTP, credit card details, or sensitive IDs upfront',
            'weight' => 15,
            'category' => 'Security'
        ],
        'urgent_pressure' => [
            'label' => 'High-pressure deadline demanding immediate acceptance or payment within 24 hours',
            'weight' => 15,
            'category' => 'Behavioral'
        ],
        'suspicious_domain' => [
            'label' => 'Spoofed company name, mismatched URL, or unofficial lookalike website domain',
            'weight' => 15,
            'category' => 'Verification'
        ],
        'vague_tasks' => [
            'label' => 'Vague tasks such as package forwarding, money transfer, YouTube video rating, or task scams',
            'weight' => 15,
            'category' => 'Nature of Work'
        ]
    ];

    public static function getAllFlags(): array
    {
        return self::RED_FLAGS;
    }

    public static function calculate(array $selectedFlags, bool $paymentDemanded = false, ?string $contactEmail = null): array
    {
        $score = 0;
        $breakdown = [];

        // Auto-detect free email domain if email provided and flag not already set
        if (!empty($contactEmail)) {
            $freeDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'rediffmail.com', 'aol.com', 'proton.me', 'protonmail.com'];
            $parts = explode('@', $contactEmail);
            if (count($parts) === 2 && in_array(strtolower(trim($parts[1])), $freeDomains, true)) {
                if (!in_array('free_email', $selectedFlags, true)) {
                    $selectedFlags[] = 'free_email';
                }
            }
        }

        // Auto-detect fee demanded flag
        if ($paymentDemanded && !in_array('fee_demanded', $selectedFlags, true)) {
            $selectedFlags[] = 'fee_demanded';
        }

        foreach ($selectedFlags as $flagKey) {
            if (isset(self::RED_FLAGS[$flagKey])) {
                $flag = self::RED_FLAGS[$flagKey];
                $score += $flag['weight'];
                $breakdown[] = [
                    'key' => $flagKey,
                    'label' => $flag['label'],
                    'weight' => $flag['weight'],
                    'category' => $flag['category']
                ];
            }
        }

        if ($score > 100) {
            $score = 100;
        }

        $level = self::determineLevel($score);

        return [
            'score' => $score,
            'level' => $level,
            'breakdown' => $breakdown,
            'normalized_flags' => array_values(array_unique($selectedFlags))
        ];
    }

    public static function determineLevel(int $score): string
    {
        if ($score >= 85) {
            return 'Critical';
        }
        if ($score >= 60) {
            return 'High';
        }
        if ($score >= 30) {
            return 'Medium';
        }
        return 'Low';
    }
}
