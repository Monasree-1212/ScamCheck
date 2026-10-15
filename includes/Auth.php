<?php

namespace ScamCheck;

class Auth
{
    private static ?array $currentUser = null;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                session_set_cookie_params([
                    'lifetime' => 86400 * 7,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            @session_start();
        }
    }

    public static function login(array $user): void
    {
        self::startSession();
        // Prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = (string)$user['id'];
        $_SESSION['username']  = (string)$user['username'];
        $_SESSION['email']     = (string)$user['email'];
        $_SESSION['role']      = (string)($user['role'] ?? 'user');
        $_SESSION['logged_in'] = true;

        self::$currentUser = $user;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        self::$currentUser = null;
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        self::$currentUser = User::findById($userId);
        return self::$currentUser;
    }

    public static function id(): ?string
    {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }

    public static function username(): ?string
    {
        self::startSession();
        return $_SESSION['username'] ?? null;
    }

    public static function isAdmin(): bool
    {
        self::startSession();
        return self::check() && (($_SESSION['role'] ?? '') === 'admin');
    }

    public static function requireAuth(string $redirectTo = 'login.php'): void
    {
        if (!self::check()) {
            Helper::setFlash('warning', 'Please sign in to access that page.');
            header("Location: {$redirectTo}");
            exit;
        }
    }

    public static function requireAdmin(string $redirectTo = 'login.php'): void
    {
        if (!self::check()) {
            Helper::setFlash('warning', 'Admin authentication required.');
            header("Location: {$redirectTo}");
            exit;
        }
        if (!self::isAdmin()) {
            Helper::setFlash('danger', 'Access denied: Administrator privileges required.');
            header("Location: index.php");
            exit;
        }
    }

    public static function requireGuest(string $redirectTo = 'dashboard.php'): void
    {
        if (self::check()) {
            header("Location: {$redirectTo}");
            exit;
        }
    }

    public static function generateCsrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function getCsrfToken(): string
    {
        return self::generateCsrfToken();
    }

    public static function validateCsrfToken(?string $token): bool
    {
        self::startSession();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function csrfField(): string
    {
        $token = htmlspecialchars(self::getCsrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
