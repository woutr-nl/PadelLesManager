<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Lesson;
use App\Models\Student;
use App\Middleware\AuthMiddleware;
use App\Services\GoogleCalendarService;

// Require authentication
AuthMiddleware::requireAuth();

$error = '';
$success = '';
$lesson = null;
$calendarService = new GoogleCalendarService();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $data = [
            'lesson_date' => $_POST['lesson_date'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'instructor' => $_POST['instructor'] ?? '',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        if ($action === 'create') {
            $lesson = Lesson::create($data);
            if ($lesson) {
                // Add selected students to the lesson
                if (isset($_POST['students']) && is_array($_POST['students'])) {
                    foreach ($_POST['students'] as $studentId) {
                        $student = Student::findById((int)$studentId);
                        if ($student) {
                            $lesson->addStudent($student);
                        }
                    }
                }
                
                // Create Google Calendar event
                try {
                    $calendarService->createEvent($lesson);
                    $success = 'Lesson created and synced with Google Calendar!';
                } catch (Exception $e) {
                    $success = 'Lesson created but failed to sync with Google Calendar: ' . $e->getMessage();
                }
                
                header('Location: /lessons.php');
                exit();
            } else {
                $error = 'Failed to create lesson. Please try again.';
            }
        } else {
            $lesson = Lesson::findById((int)$_POST['id']);
            if ($lesson && $lesson->update($data)) {
                $success = 'Lesson updated successfully!';
                
                // Update student attendance status
                if (isset($_POST['student_status']) && is_array($_POST['student_status'])) {
                    foreach ($_POST['student_status'] as $studentId => $status) {
                        $student = Student::findById((int)$studentId);
                        if ($student) {
                            $lesson->updateStudentStatus($student, $status);
                        }
                    }
                }
                
                // Update Google Calendar event
                try {
                    $calendarService->updateEvent($lesson);
                    $success .= ' and synced with Google Calendar!';
                } catch (Exception $e) {
                    $success .= ' but failed to sync with Google Calendar: ' . $e->getMessage();
                }
            } else {
                $error = 'Failed to update lesson. Please try again.';
            }
        }
    } elseif ($action === 'delete') {
        $lesson = Lesson::findById((int)$_POST['id']);
        if ($lesson) {
            // Delete Google Calendar event first
            try {
                $calendarService->deleteEvent($lesson);
            } catch (Exception $e) {
                // Continue with deletion even if Google Calendar sync fails
                error_log('Failed to delete Google Calendar event: ' . $e->getMessage());
            }
            
            if ($lesson->delete()) {
                header('Location: /lessons.php');
                exit();
            }
        }
        $error = 'Failed to delete lesson. Please try again.';
    } elseif ($action === 'sync') {
        $lesson = Lesson::findById((int)$_POST['id']);
        if ($lesson) {
            try {
                if ($lesson->getGoogleEventId()) {
                    $calendarService->updateEvent($lesson);
                } else {
                    $calendarService->createEvent($lesson);
                }
                $success = 'Lesson synced with Google Calendar!';
            } catch (Exception $e) {
                $error = 'Failed to sync with Google Calendar: ' . $e->getMessage();
            }
        }
    }
}

// Handle GET requests
$action = $_GET['action'] ?? 'list';
if ($action === 'edit' || $action === 'delete') {
    $lesson = Lesson::findById((int)$_GET['id']);
    if (!$lesson) {
        header('Location: /lessons.php');
        exit();
    }
}

$lessons = Lesson::findAll();
$students = Student::findAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - PadelLesManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .calendar-synced {
            color: #198754;
        }
        .calendar-not-synced {
            color: #dc3545;
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
                        <a class="nav-link" href="/students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/lessons.php">Lessons</a>
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
                    <h5 class="mb-0"><?= $action === 'create' ? 'Schedule New Lesson' : 'Edit Lesson' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/lessons.php">
                        <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                        <?php if ($lesson): ?>
                            <input type="hidden" name="id" value="<?= $lesson->getId() ?>">
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="lesson_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="lesson_date" name="lesson_date" 
                                       value="<?= $lesson ? htmlspecialchars($lesson->getLessonDate()) : '' ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" 
                                       value="<?= $lesson ? htmlspecialchars($lesson->getStartTime()) : '' ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" 
                                       value="<?= $lesson ? htmlspecialchars($lesson->getEndTime()) : '' ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="instructor" class="form-label">Instructor</label>
                            <input type="text" class="form-control" id="instructor" name="instructor" 
                                   value="<?= $lesson ? htmlspecialchars($lesson->getInstructor()) : '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= $lesson ? htmlspecialchars($lesson->getNotes()) : '' ?></textarea>
                        </div>
                        
                        <?php if ($action === 'create'): ?>
                            <div class="mb-3">
                                <label class="form-label">Select Students</label>
                                <div class="row row-cols-1 row-cols-md-3 g-3">
                                    <?php foreach ($students as $student): ?>
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="students[]" 
                                                       value="<?= $student->getId() ?>" id="student_<?= $student->getId() ?>">
                                                <label class="form-check-label" for="student_<?= $student->getId() ?>">
                                                    <?= htmlspecialchars($student->getFullName()) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Student Attendance</label>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lesson->getStudents() as $student): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($student->getFullName()) ?></td>
                                                    <td>
                                                        <select class="form-select" name="student_status[<?= $student->getId() ?>]">
                                                            <option value="Present" <?= $student->getStatus() === 'Present' ? 'selected' : '' ?>>Present</option>
                                                            <option value="Absent" <?= $student->getStatus() === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                                            <option value="Canceled" <?= $student->getStatus() === 'Canceled' ? 'selected' : '' ?>>Canceled</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="/lessons.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Lessons</h5>
                    <a href="/lessons.php?action=create" class="btn btn-primary">
                        <i class="bi bi-calendar-plus"></i> Schedule New Lesson
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($lessons)): ?>
                        <p class="text-muted">No lessons found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Instructor</th>
                                        <th>Students</th>
                                        <th>Calendar</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($lesson->getLessonDate()) ?></td>
                                            <td><?= htmlspecialchars($lesson->getStartTime()) ?> - <?= htmlspecialchars($lesson->getEndTime()) ?></td>
                                            <td><?= htmlspecialchars($lesson->getInstructor()) ?></td>
                                            <td>
                                                <?php foreach ($lesson->getStudents() as $student): ?>
                                                    <span class="badge bg-primary"><?= htmlspecialchars($student->getFullName()) ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php if ($lesson->getGoogleEventId()): ?>
                                                    <a href="https://calendar.google.com/calendar/event?eid=<?= htmlspecialchars($lesson->getGoogleEventId()) ?>" 
                                                       target="_blank" class="btn btn-sm btn-link calendar-synced" title="View in Google Calendar">
                                                        <i class="bi bi-calendar2-check"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <form method="POST" action="/lessons.php" class="d-inline">
                                                        <input type="hidden" name="action" value="sync">
                                                        <input type="hidden" name="id" value="<?= $lesson->getId() ?>">
                                                        <button type="submit" class="btn btn-sm btn-link calendar-not-synced" title="Sync to Google Calendar">
                                                            <i class="bi bi-calendar2-plus"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="/lessons.php?action=edit&id=<?= $lesson->getId() ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" action="/lessons.php" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $lesson->getId() ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this lesson?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 