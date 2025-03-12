<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Middleware\AuthMiddleware;

// Destroy the session
AuthMiddleware::logout();

// Redirect to login page
header('Location: /login.php');
exit(); 