<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Middleware\AuthMiddleware;

// If user is already authenticated, redirect to dashboard
if (AuthMiddleware::isAuthenticated()) {
    header('Location: /dashboard.php');
    exit();
}

// Otherwise, redirect to login page
header('Location: /login.php');
exit(); 