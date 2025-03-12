<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;

// Get the email from command line argument
$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$email || !$password) {
    die("Usage: php update_password.php <email> <new_password>\n");
}

try {
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    echo "Generated hash: $hashedPassword\n";
    
    // First check if the user exists
    $checkStmt = Database::query(
        "SELECT id FROM users WHERE email = ?",
        [$email]
    );
    $user = $checkStmt->fetch();
    
    if (!$user) {
        die("No user found with email: $email\n");
    }
    
    echo "Found user with ID: {$user['id']}\n";
    
    // Update the password in the database
    $stmt = Database::query(
        "UPDATE users SET password = ? WHERE email = ?",
        [$hashedPassword, $email]
    );
    
    if ($stmt->rowCount() > 0) {
        echo "Password updated successfully for user: $email\n";
        
        // Verify the update
        $verifyStmt = Database::query(
            "SELECT password FROM users WHERE email = ?",
            [$email]
        );
        $updated = $verifyStmt->fetch();
        echo "Verification: password was " . ($updated && $updated['password'] === $hashedPassword ? "correctly" : "not") . " updated in database\n";
    } else {
        echo "Failed to update password. No rows affected.\n";
    }
} catch (Exception $e) {
    echo "Error updating password: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 