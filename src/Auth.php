<?php

declare(strict_types=1);

namespace KSG;

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function user(): ?array
    {
        return self::currentUser();
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
    }

    
    public static function refreshSession(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return;
        }

        try {
            $db   = Database::getInstance()->getPdo();
            $stmt = $db->prepare("
                SELECT id, name, email, campus, role,
                       department, hod_name, designation
                FROM users
                WHERE id = :id AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user'] = $user;
            }
        } catch (\Exception $e) {
            error_log('Session refresh error: ' . $e->getMessage());
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function generateCsrf(): string
    {
        return self::csrfToken();
    }

    public static function validateCsrf(string $token): bool
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}