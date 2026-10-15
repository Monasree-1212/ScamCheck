<?php
require_once __DIR__ . '/vendor/autoload.php';

use ScamCheck\Auth;
use ScamCheck\User;
use ScamCheck\Helper;

Auth::requireGuest();

$errors = [];
$formData = [
    'full_name' => '',
    'username'  => '',
    'email'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired CSRF security token.';
    } else {
        $formData['full_name'] = trim($_POST['full_name'] ?? '');
        $formData['username']  = trim($_POST['username'] ?? '');
        $formData['email']     = trim($_POST['email'] ?? '');
        $password              = $_POST['password'] ?? '';
        $confirmPassword       = $_POST['confirm_password'] ?? '';

        if (empty($formData['username']) || strlen($formData['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['username'])) {
            $errors[] = 'Username can only contain letters, numbers, and underscores.';
        }

        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            try {
                $user = User::create([
                    'full_name' => $formData['full_name'],
                    'username'  => $formData['username'],
                    'email'     => $formData['email'],
                    'password'  => $password,
                    'role'      => 'user',
                ]);

                Auth::login($user);
                Helper::setFlash('success', 'Registration successful! Welcome to ScamCheck.');
                header('Location: dashboard.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Register Account';
require_once __DIR__ . '/includes/templates/header.php';
?>

<div class="container">
    <div class="auth-wrapper" style="max-width: 480px;">
        <div class="panel">
            <div class="panel-header" style="justify-content: center; padding: 1.15rem 1.25rem;">
                <div class="panel-header-title">
                    <?= Helper::icon('user', 18) ?>
                    <span style="font-size: 1.05rem;">Create ScamCheck Account</span>
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

                <form method="POST" action="register.php">
                    <?= Auth::csrfField() ?>

                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g. Rohan Verma" value="<?= Helper::escape($formData['full_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">Username <span class="req">*</span></label>
                        <input type="text" id="username" name="username" class="form-control" required placeholder="e.g. rohanv" value="<?= Helper::escape($formData['username']) ?>">
                        <div class="form-help">Letters, numbers, and underscores only.</div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address <span class="req">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" required placeholder="e.g. student@college.edu" value="<?= Helper::escape($formData['email']) ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">Password <span class="req">*</span></label>
                            <input type="password" id="password" name="password" class="form-control" required placeholder="At least 6 chars">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="req">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Repeat password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="padding: 0.7rem; font-size: 0.95rem; margin-top: 0.5rem;">
                        <span>Create Account</span>
                        <?= Helper::icon('arrow-right', 16) ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="auth-footer">
            Already registered? <a href="login.php" style="font-weight: 600;">Sign in to existing account &rarr;</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/templates/footer.php'; ?>
