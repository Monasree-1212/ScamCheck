<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\User;
use ScamCheck\Helper;

Auth::requireGuest();

$errors = [];
$loginInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired CSRF security token.';
    } else {
        $loginInput = trim($_POST['login'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($loginInput) || empty($password)) {
            $errors[] = 'Please enter both your username/email and password.';
        } else {
            $user = User::findByCredentials($loginInput, $password);
            if (!$user) {
                $errors[] = 'Invalid login credentials. Please verify and try again.';
            } elseif (($user['status'] ?? 'active') !== 'active') {
                $errors[] = 'This account has been suspended.';
            } else {
                Auth::login($user);
                Helper::setFlash('success', "Welcome back, {$user['username']}!");

                if ($user['role'] === 'admin') {
                    header('Location: admin-dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            }
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="panel">
            <div class="panel-header" style="justify-content: center; padding: 1.15rem 1.25rem;">
                <div class="panel-header-title">
                    <?= Helper::icon('lock', 18) ?>
                    <span style="font-size: 1.05rem;">Sign In to ScamCheck</span>
                </div>
            </div>
            <div class="panel-body" style="padding: 1.5rem;">
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

                <form method="POST" action="login.php">
                    <?= Auth::csrfField() ?>

                    <div class="form-group">
                        <label for="login" class="form-label">Username or Email Address</label>
                        <input type="text" id="login" name="login" class="form-control" required autofocus value="<?= Helper::escape($loginInput) ?>" placeholder="e.g. student@college.edu or admin">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="padding: 0.7rem; font-size: 0.95rem; margin-top: 0.5rem;">
                        <span>Sign In</span>
                        <?= Helper::icon('arrow-right', 16) ?>
                    </button>
                </form>

                <div style="margin-top: 1.5rem; padding: 0.85rem 1rem; background-color: var(--bg-subtle); border: 1px solid var(--border-light); border-radius: var(--radius-sm); font-size: 0.82rem; color: var(--text-muted); line-height: 1.55;">
                    <strong style="color: var(--navy); display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                        <?= Helper::icon('info', 14) ?> Default Evaluation Accounts:
                    </strong>
                    <ul style="margin-left: 1.25rem;">
                        <li><strong>Admin:</strong> <code>admin@scamcheck.local</code> &bull; <code>Admin@123</code></li>
                        <li><strong>Student:</strong> <code>student@college.edu</code> &bull; <code>Student@123</code></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="auth-footer">
            Don't have an account yet? <a href="register.php" style="font-weight: 600;">Create a student account &rarr;</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
