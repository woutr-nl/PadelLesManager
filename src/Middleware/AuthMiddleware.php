<?php

namespace App\Middleware;

class AuthMiddleware {
    public static function isAuthenticated(): bool {
        session_start();
        return isset($_SESSION['user_id']);
    }

    public static function authenticate(int $userId): void {
        session_start();
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void {
        session_start();
        session_destroy();
    }

    public static function requireAuth(): void {
        if (!self::isAuthenticated()) {
            header('Location: /login.php');
            exit();
        }
    }
} 