<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Student;
use App\Middleware\AuthMiddleware;

// Require authentication
AuthMiddleware::requireAuth();

$error = '';
$success = '';
$student = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $data = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'phone' => $_POST['phone'] ?? ''
        ];
        
        if ($action === 'create') {
            $student = Student::create($data);
            if ($student) {
                $success = 'Student created successfully!';
                header('Location: /students.php');
                exit();
            } else {
                $error = 'Failed to create student. Please try again.';
            }
        } else {
            $student = Student::findById((int)$_POST['id']);
            if ($student && $student->update($data)) {
                $success = 'Student updated successfully!';
            } else {
                $error = 'Failed to update student. Please try again.';
            }
        }
    } elseif ($action === 'delete') {
        $student = Student::findById((int)$_POST['id']);
        if ($student && $student->delete()) {
            header('Location: /students.php');
            exit();
        } else {
            $error = 'Failed to delete student. Please try again.';
        }
    } elseif ($action === 'add_lessons') {
        $student = Student::findById((int)$_POST['student_id']);
        $amount = (int)$_POST['amount'];
        if ($student && $amount > 0 && $student->addLessons($amount)) {
            $success = "Successfully added {$amount} lesson(s) to {$student->getFullName()}.";
        } else {
            $error = 'Failed to add lessons. Please try again.';
        }
    } elseif ($action === 'lower_lessons') {
        $student = Student::findById((int)$_POST['student_id']);
        $amount = (int)$_POST['amount'];
        if ($student && $amount > 0 && $student->lowerLessons($amount)) {
            $success = "Successfully lowered {$student->getFullName()}'s lessons by {$amount}.";
        } else {
            $error = 'Failed to lower lessons. Please ensure the amount is valid and the student has enough lessons.';
        }
    }
}

// Handle GET requests
$action = $_GET['action'] ?? 'list';
if ($action === 'edit' || $action === 'delete' || $action === 'history') {
    $student = Student::findById((int)$_GET['id']);
    if (!$student) {
        header('Location: /students.php');
        exit();
    }
}

$students = Student::findAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - PadelLesManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .lessons-remaining {
            font-weight: bold;
        }
        .lessons-remaining.low {
            color: #dc3545;
        }
        .lessons-remaining.warning {
            color: #ffc107;
        }
        .lessons-remaining.good {
            color: #198754;
        }
    </style>
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
                        <a class="nav-link active" href="/students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/lessons.php">Lessons</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/locations.php">Locations</a>
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
                    <h5 class="mb-0"><?= $action === 'create' ? 'Add New Student' : 'Edit Student' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/students.php">
                        <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                        <?php if ($student): ?>
                            <input type="hidden" name="id" value="<?= $student->getId() ?>">
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?= $student ? htmlspecialchars($student->getFirstName()) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?= $student ? htmlspecialchars($student->getLastName()) : '' ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= $student ? htmlspecialchars($student->getPhone()) : '' ?>">
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="/students.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($action === 'history'): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Lesson History - <?= htmlspecialchars($student->getFullName()) ?></h5>
                    <div>
                        <span class="me-3">
                            Lessons Remaining: 
                            <span class="badge bg-<?= $student->getLessonsRemaining() <= 0 ? 'danger' : ($student->getLessonsRemaining() <= 2 ? 'warning' : 'success') ?>">
                                <?= $student->getLessonsRemaining() ?>
                            </span>
                        </span>
                        <a href="/students.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Students
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    $lessons = $student->getLessonHistory();
                    if (empty($lessons)): ?>
                        <p class="text-muted">No lessons found for this student.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Instructor</th>
                                        <th>Other Students</th>
                                        <th>Status</th>
                                        <th>Google Calendar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(date('d-m-Y', strtotime($lesson->getLessonDate()))) ?></td>
                                            <td><?= htmlspecialchars($lesson->getStartTime() . ' - ' . $lesson->getEndTime()) ?></td>
                                            <td><?= htmlspecialchars($lesson->getInstructor()) ?></td>
                                            <td>
                                                <?php
                                                $otherStudents = array_filter($lesson->getStudents(), fn($s) => $s->getId() !== $student->getId());
                                                echo htmlspecialchars(implode(', ', array_map(fn($s) => $s->getFirstName(), $otherStudents)));
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = match($lesson->getStatus()) {
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    'upcoming' => 'primary',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= ucfirst($lesson->getStatus() ?? 'scheduled') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($lesson->getGoogleEventId()): ?>
                                                    <i class="bi bi-calendar-check text-success"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-calendar-x text-muted"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Students</h5>
                    <a href="/students.php?action=create" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add New Student
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <p class="text-muted">No students found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Lessons Remaining</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s->getFullName()) ?></td>
                                            <td><?= htmlspecialchars($s->getPhone()) ?></td>
                                            <td>
                                                <?php
                                                    $remaining = $s->getLessonsRemaining();
                                                    $class = 'lessons-remaining ';
                                                    if ($remaining <= 0) {
                                                        $class .= 'low';
                                                    } elseif ($remaining <= 2) {
                                                        $class .= 'warning';
                                                    } else {
                                                        $class .= 'good';
                                                    }
                                                ?>
                                                <span class="<?= $class ?>"><?= $remaining ?></span>
                                                <button type="button" class="btn btn-sm btn-outline-primary ms-2" 
                                                        onclick="showAddLessonsModal(<?= $s->getId() ?>, '<?= htmlspecialchars($s->getFullName()) ?>')">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger ms-1" 
                                                        onclick="showLowerLessonsModal(<?= $s->getId() ?>, '<?= htmlspecialchars($s->getFullName()) ?>', <?= $remaining ?>)">
                                                    <i class="bi bi-dash-circle"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="/students.php?action=history&id=<?= $s->getId() ?>" 
                                                       class="btn btn-outline-info" title="View Lesson History">
                                                        <i class="bi bi-clock-history"></i>
                                                    </a>
                                                    <a href="/students.php?action=edit&id=<?= $s->getId() ?>" 
                                                       class="btn btn-outline-primary" title="Edit Student">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteStudent(<?= $s->getId() ?>)" title="Delete Student">
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

    <!-- Add Lessons Modal -->
    <div class="modal fade" id="addLessonsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="/students.php">
                    <input type="hidden" name="action" value="add_lessons">
                    <input type="hidden" name="student_id" id="modalStudentId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Add Lessons</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <p>Add lessons for <span id="modalStudentName"></span></p>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Number of Lessons</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   min="1" max="50" value="1" required>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Lessons</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Lower Lessons Modal -->
    <div class="modal fade" id="lowerLessonsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="/students.php">
                    <input type="hidden" name="action" value="lower_lessons">
                    <input type="hidden" name="student_id" id="lowerModalStudentId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Lower Lessons</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <p>Lower lessons for <span id="lowerModalStudentName"></span></p>
                        <p>Current lessons remaining: <span id="currentLessonsRemaining" class="fw-bold"></span></p>
                        <div class="mb-3">
                            <label for="lowerAmount" class="form-label">Number of Lessons to Remove</label>
                            <input type="number" class="form-control" id="lowerAmount" name="amount" 
                                   min="1" value="1" required>
                            <div class="form-text">The student will have <span id="resultingLessons"></span> lessons after this operation.</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Lower Lessons</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let addLessonsModal;
        let lowerLessonsModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            addLessonsModal = new bootstrap.Modal(document.getElementById('addLessonsModal'));
            lowerLessonsModal = new bootstrap.Modal(document.getElementById('lowerLessonsModal'));
            
            // Set up the dynamic calculation for the lower lessons modal
            const lowerAmountInput = document.getElementById('lowerAmount');
            if (lowerAmountInput) {
                lowerAmountInput.addEventListener('input', updateResultingLessons);
            }
        });

        function showAddLessonsModal(studentId, studentName) {
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('modalStudentName').textContent = studentName;
            addLessonsModal.show();
        }
        
        function showLowerLessonsModal(studentId, studentName, currentLessons) {
            document.getElementById('lowerModalStudentId').value = studentId;
            document.getElementById('lowerModalStudentName').textContent = studentName;
            document.getElementById('currentLessonsRemaining').textContent = currentLessons;
            
            // Set max value to current lessons
            const lowerAmountInput = document.getElementById('lowerAmount');
            lowerAmountInput.max = currentLessons;
            lowerAmountInput.value = Math.min(1, currentLessons);
            
            // Update the resulting lessons display
            updateResultingLessons();
            
            lowerLessonsModal.show();
        }
        
        function updateResultingLessons() {
            const currentLessons = parseInt(document.getElementById('currentLessonsRemaining').textContent);
            const amountToLower = parseInt(document.getElementById('lowerAmount').value) || 0;
            const resulting = Math.max(0, currentLessons - amountToLower);
            
            document.getElementById('resultingLessons').textContent = resulting;
        }

        function deleteStudent(id) {
            if (confirm('Are you sure you want to delete this student?')) {
                const form = document.getElementById('deleteForm');
                form.querySelector('input[name="id"]').value = id;
                form.submit();
            }
        }
    </script>
</body>
</html> 