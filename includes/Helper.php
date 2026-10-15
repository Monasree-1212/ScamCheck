<?php

namespace ScamCheck;

class Helper
{
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public static function setFlash(string $type, string $message): void
    {
        Auth::startSession();
        $_SESSION['flash_messages'][] = [
            'type'    => in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info',
            'message' => $message,
        ];
    }

    public static function getFlash(): array
    {
        Auth::startSession();
        $messages = $_SESSION['flash_messages'] ?? [];
        $_SESSION['flash_messages'] = [];
        return $messages;
    }

    public static function displayFlash(): string
    {
        $messages = self::getFlash();
        if (empty($messages)) {
            return '';
        }

        $html = '<div class="flash-messages">';
        foreach ($messages as $m) {
            $type = self::escape($m['type']);
            $msg  = self::escape($m['message']);
            $iconName = match ($type) {
                'success' => 'check-circle',
                'danger'  => 'alert-circle',
                'warning' => 'alert-triangle',
                default   => 'info',
            };
            $icon = self::icon($iconName, 18);
            $html .= "<div class=\"alert alert-{$type}\"><span class=\"alert-icon\">{$icon}</span><span class=\"alert-text\">{$msg}</span></div>";
        }
        $html .= '</div>';
        return $html;
    }

    public static function statusBadge(string $status): string
    {
        $statusClean = self::escape($status);
        $class = match ($status) {
            Report::STATUS_PENDING   => 'badge-pending',
            Report::STATUS_REVIEW    => 'badge-review',
            Report::STATUS_APPROVED  => 'badge-approved',
            Report::STATUS_REJECTED  => 'badge-rejected',
            Report::STATUS_DUPLICATE => 'badge-duplicate',
            default                  => 'badge-neutral',
        };

        $dot = match ($status) {
            Report::STATUS_PENDING   => '<span class="status-dot dot-amber"></span>',
            Report::STATUS_REVIEW    => '<span class="status-dot dot-blue"></span>',
            Report::STATUS_APPROVED  => '<span class="status-dot dot-teal"></span>',
            Report::STATUS_REJECTED  => '<span class="status-dot dot-red"></span>',
            Report::STATUS_DUPLICATE => '<span class="status-dot dot-purple"></span>',
            default                  => '<span class="status-dot dot-gray"></span>',
        };

        return "<span class=\"badge {$class}\">{$dot}{$statusClean}</span>";
    }

    public static function riskBadge(string $level, int $score = 0): string
    {
        $levelClean = self::escape($level);
        $scoreText = $score > 0 ? " ({$score}/100)" : "";

        $class = match ($level) {
            'Low'      => 'risk-low',
            'Medium'   => 'risk-medium',
            'High'     => 'risk-high',
            'Critical' => 'risk-critical',
            default    => 'risk-neutral',
        };

        $icon = match ($level) {
            'Critical', 'High' => self::icon('alert-triangle', 13),
            'Medium'           => self::icon('alert-circle', 13),
            default            => self::icon('shield', 13),
        };

        return "<span class=\"risk-badge {$class}\">{$icon} {$levelClean}{$scoreText}</span>";
    }

    public static function formatDate(string $dateStr): string
    {
        if (empty($dateStr)) {
            return '—';
        }
        $timestamp = strtotime($dateStr);
        if (!$timestamp) {
            return self::escape($dateStr);
        }
        return date('M j, Y g:i A', $timestamp);
    }

    public static function truncate(string $text, int $limit = 120): string
    {
        $text = trim(strip_tags($text));
        if (mb_strlen($text) <= $limit) {
            return self::escape($text);
        }
        return self::escape(mb_substr($text, 0, $limit)) . '...';
    }

    /**
     * Crisp, consistent inline SVG icons
     */
    public static function icon(string $name, int $size = 18, string $class = ''): string
    {
        $cls = $class ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '';
        $s = (int)$size;

        return match ($name) {
            'shield', 'shield-check' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
SVG,
            'alert-triangle' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
SVG,
            'alert-circle' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
SVG,
            'check-circle' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
SVG,
            'info' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
SVG,
            'search' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
SVG,
            'filter' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
SVG,
            'user' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
SVG,
            'users' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
SVG,
            'file-text' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
SVG,
            'plus-circle' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
SVG,
            'arrow-right' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
SVG,
            'eye' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
SVG,
            'edit' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
SVG,
            'trash' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
SVG,
            'clock' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
SVG,
            'check' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
SVG,
            'lock' => <<<SVG
<svg class="sc-icon{$cls}" width="{$s}" height="{$s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
SVG,
            default => '',
        };
    }
}
