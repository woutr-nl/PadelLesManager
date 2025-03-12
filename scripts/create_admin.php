<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;

function createAdminUser(string $email, string $password, string $username = 'Admin'): bool {
    try {
        // Check if any users exist
        $checkStmt = Database::query("SELECT COUNT(*) as count FROM users");
        $result = $checkStmt->fetch();
        
        if ($result['count'] > 0) {
            echo "Users already exist in the database. Cannot create default admin.\n";
            return false;
        }
        
        // Create admin user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = Database::query(
            "INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, 1)",
            [$username, $email, $hashedPassword]
        );
        
        if ($stmt->rowCount() > 0) {
            echo "Admin user created successfully!\n";
            echo "Email: $email\n";
            echo "Username: $username\n";
            return true;
        }
        
        echo "Failed to create admin user.\n";
        return false;
        
    } catch (Exception $e) {
        echo "Error creating admin user: " . $e->getMessage() . "\n";
        return false;
    }
}

// If script is run directly from command line
if (php_sapi_name() === 'cli') {
    $email = $argv[1] ?? null;
    $password = $argv[2] ?? null;
    $username = $argv[3] ?? 'Admin';
    
    if (!$email || !$password) {
        die("Usage: php create_admin.php <email> <password> [username]\n");
    }
    
    createAdminUser($email, $password, $username);
} 