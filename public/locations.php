<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Location;
use App\Middleware\AuthMiddleware;

// Require authentication
AuthMiddleware::requireAuth();

$error = '';
$success = '';
$location = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $data = [
            'name' => $_POST['name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'has_entry_code' => isset($_POST['has_entry_code']) ? true : false,
            'default_entry_code' => isset($_POST['has_entry_code']) ? ($_POST['default_entry_code'] ?? null) : null
        ];
        
        if ($action === 'create') {
            $location = Location::create($data);
            if ($location) {
                $success = 'Location created successfully!';
                header('Location: /locations.php');
                exit();
            } else {
                $error = 'Failed to create location. Please try again.';
            }
        } else {
            $location = Location::findById((int)$_POST['id']);
            if ($location && $location->update($data)) {
                $success = 'Location updated successfully!';
            } else {
                $error = 'Failed to update location. Please try again.';
            }
        }
    } elseif ($action === 'delete') {
        $location = Location::findById((int)$_POST['id']);
        if ($location && $location->delete()) {
            header('Location: /locations.php');
            exit();
        } else {
            $error = 'Failed to delete location. Please try again.';
        }
    }
}

// Handle GET requests
$action = $_GET['action'] ?? 'list';
if ($action === 'edit' || $action === 'delete') {
    $location = Location::findById((int)$_GET['id']);
    if (!$location) {
        header('Location: /locations.php');
        exit();
    }
}

$locations = Location::findAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locations - PadelLesManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/dashboard.php">PadelLesManager</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/lessons.php">Lessons</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/locations.php">Locations</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($action === 'create' || $action === 'edit'): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= $action === 'create' ? 'Add New Location' : 'Edit Location' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/locations.php">
                        <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                        <?php if ($location): ?>
                            <input type="hidden" name="id" value="<?= $location->getId() ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= $location ? htmlspecialchars($location->getName()) : '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= $location ? htmlspecialchars($location->getAddress()) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="has_entry_code" name="has_entry_code" value="1"
                                       <?= $location && $location->hasEntryCode() ? 'checked' : '' ?>>
                                <label class="form-check-label" for="has_entry_code">Location requires entry code</label>
                            </div>
                        </div>

                        <div class="mb-3 entry-code-field" style="display: none;">
                            <label for="default_entry_code" class="form-label">Default Entry Code</label>
                            <input type="text" class="form-control" id="default_entry_code" name="default_entry_code" 
                                   value="<?= $location ? htmlspecialchars($location->getDefaultEntryCode() ?? '') : '' ?>"
                                   maxlength="20">
                            <div class="form-text">This code will be pre-filled when creating new lessons at this location.</div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="/locations.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Locations</h5>
                    <a href="/locations.php?action=create" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Location
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($locations)): ?>
                        <p class="text-muted">No locations found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>Entry Code</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($locations as $loc): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($loc->getName()) ?></td>
                                            <td><?= nl2br(htmlspecialchars($loc->getAddress() ?? '')) ?></td>
                                            <td>
                                                <?php if ($loc->hasEntryCode()): ?>
                                                    <?php if ($loc->getDefaultEntryCode()): ?>
                                                        <span class="badge bg-info"><?= htmlspecialchars($loc->getDefaultEntryCode()) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Required (no default)</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not required</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="/locations.php?action=edit&id=<?= $loc->getId() ?>" 
                                                       class="btn btn-outline-primary" title="Edit Location">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteLocation(<?= $loc->getId() ?>)" title="Delete Location">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteLocation(id) {
            if (confirm('Are you sure you want to delete this location?')) {
                const form = document.getElementById('deleteForm');
                form.querySelector('input[name="id"]').value = id;
                form.submit();
            }
        }

        // Handle entry code field visibility
        document.addEventListener('DOMContentLoaded', function() {
            const hasEntryCodeCheckbox = document.getElementById('has_entry_code');
            const entryCodeField = document.querySelector('.entry-code-field');
            
            if (hasEntryCodeCheckbox && entryCodeField) {
                function updateEntryCodeFieldVisibility() {
                    entryCodeField.style.display = hasEntryCodeCheckbox.checked ? 'block' : 'none';
                }
                
                hasEntryCodeCheckbox.addEventListener('change', updateEntryCodeFieldVisibility);
                updateEntryCodeFieldVisibility();
            }
        });
    </script>
</body>
</html> 