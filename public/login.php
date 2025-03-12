<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../scripts/create_admin.php';

use App\Models\User;
use App\Database\Database;
use App\Middleware\AuthMiddleware;

$error = '';
$success = '';
$debug_info = [];

// Check if any users exist
try {
    $checkStmt = Database::query("SELECT COUNT(*) as count FROM users");
    $result = $checkStmt->fetch();
    
    if ($result['count'] === 0) {
        // No users exist - show admin creation form
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
            $adminEmail = $_POST['admin_email'] ?? '';
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminUsername = $_POST['admin_username'] ?? 'Admin';
            
            if (createAdminUser($adminEmail, $adminPassword, $adminUsername)) {
                $success = 'Admin user created successfully! You can now log in.';
            } else {
                $error = 'Failed to create admin user. Please try again.';
            }
        }
        
        // Show admin creation form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Create Admin - PadelLesManager</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-light">
            <div class="container">
                <div class="row justify-content-center mt-5">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="text-center">Create Admin User</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>
                                <?php if ($success): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                                <?php endif; ?>
                                
                                <div class="alert alert-info">
                                    No users exist in the system. Please create an admin user to continue.
                                </div>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="create_admin" value="1">
                                    <div class="mb-3">
                                        <label for="admin_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="admin_username" name="admin_username" required value="Admin">
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Create Admin User</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Normal login logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Add debug information
    $debug_info['email_provided'] = !empty($email);
    $debug_info['password_provided'] = !empty($password);
    
    if ($email && $password) {
        try {
            $debug_info['attempting_user_lookup'] = true;
            $user = User::findByEmail($email);
            $debug_info['user_found'] = ($user !== null);
            
            if ($user) {
                $debug_info['attempting_password_verify'] = true;
                $password_verified = $user->verifyPassword($password);
                $debug_info['password_verified'] = $password_verified;
                
                if ($password_verified) {
                    AuthMiddleware::authenticate($user->getId());
                    header('Location: /dashboard.php');
                    exit();
                }
            }
            
            $error = 'Invalid email or password';
        } catch (Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
            $debug_info['error'] = $e->getMessage();
            $debug_info['error_trace'] = $e->getTraceAsString();
        }
    }
}

// Only show debug info in development environment
$show_debug = ($_ENV['APP_ENV'] ?? '') === 'development';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PadelLesManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">PadelLesManager Login</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($show_debug && !empty($debug_info)): ?>
                            <div class="alert alert-info">
                                <h5>Debug Information:</h5>
                                <pre><?php echo htmlspecialchars(print_r($debug_info, true)); ?></pre>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 