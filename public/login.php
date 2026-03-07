<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Auth;
use KSG\Database;

Auth::startSession();

if (Auth::isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = null;
$ip    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
// If behind a proxy/load balancer, X-FORWARDED-FOR may contain a comma-separated
// chain. We only want the original client IP, which is always the first one.
$ip = trim(explode(',', $ip)[0]);

const MAX_ATTEMPTS  = 5;
const LOCKOUT_MINS  = 30;
const WINDOW_MINS   = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please provide both email and password.';
    } else {
        try {
            $db = Database::getInstance()->getPdo();

            // Count failed attempts from this IP within the rolling window
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE ip = :ip
                  AND success = FALSE
                  AND attempted_at > NOW() - INTERVAL '" . WINDOW_MINS . " minutes'
            ");
            $stmt->execute([':ip' => $ip]);
            $recentFailures = (int)$stmt->fetchColumn();

            if ($recentFailures >= MAX_ATTEMPTS) {
                // Don't reveal lockout details — same generic message prevents enumeration
                $error = 'Too many failed attempts. Please try again in ' . LOCKOUT_MINS . ' minutes.';
            } else {
                $stmt = $db->prepare("
                    SELECT id, name, email, password_hash, campus, role,
                           department, hod_name, designation
                    FROM users
                    WHERE email = :email AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Log the success so we have a full audit trail
                    $db->prepare("
                        INSERT INTO login_attempts (ip, email, success)
                        VALUES (:ip, :email, TRUE)
                    ")->execute([':ip' => $ip, ':email' => $email]);

                    unset($user['password_hash']);
                    Auth::login($user);

                    $redirect = $_GET['redirect'] ?? '/index.php';
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $db->prepare("
                        INSERT INTO login_attempts (ip, email, success)
                        VALUES (:ip, :email, FALSE)
                    ")->execute([':ip' => $ip, ':email' => $email]);

                    $error = 'Invalid email or password.';
                    // Small delay makes automated credential stuffing slower
                    sleep(1);
                }
            }
        } catch (\Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again later.';
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <img src="./assets/img/ksg-logo.png" alt="KSG Logo" class="login-logo">
            <h1>Kenya School of Government</h1>
            <h2>Weekly Status Reports</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="./login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email"
                       value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <p class="login-footer">
            Forgot your password? Contact your system administrator.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>