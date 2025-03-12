<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Lesson;
use App\Models\Student;
use App\Models\Location;
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
    
    error_log("Lesson form submitted with action: " . $action);
    error_log("POST data: " . json_encode($_POST));
    
    if ($action === 'create') {
        $data = [
            'lesson_date' => $_POST['lesson_date'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'instructor' => $_POST['instructor'] ?? '',
            'location_id' => $_POST['location_id'] ?? null,
            'notes' => $_POST['notes'] ?? '',
            'entry_code' => $_POST['entry_code'] ?? null
        ];
        
        // Add student_ids to data if present
        if (isset($_POST['student_ids']) && is_array($_POST['student_ids'])) {
            $data['student_ids'] = array_map('intval', $_POST['student_ids']);
        }
        
        $lesson = Lesson::create($data);
        if ($lesson) {
            // The Google Calendar event is already created in the Lesson::create method
            // No need to create it again here
            $success = 'Lesson created successfully!';
            
            header('Location: /lessons.php');
            exit();
        } else {
            $error = 'Failed to create lesson. Please try again.';
        }
    } elseif ($action === 'edit' || $action === 'update') {
        $lessonId = (int)($_POST['lesson_id'] ?? $_POST['id'] ?? 0);
        error_log("Attempting to update lesson ID: " . $lessonId);
        
        if ($lessonId <= 0) {
            error_log("Invalid lesson ID for update: " . $lessonId);
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid lesson ID.'];
            header('Location: /lessons.php');
            exit;
        }
        
        $lesson = Lesson::findById($lessonId);
        if ($lesson) {
            error_log("Found lesson to update: " . json_encode([
                'id' => $lesson->getId(),
                'date' => $lesson->getLessonDate(),
                'instructor' => $lesson->getInstructor()
            ]));
            
            // Include student_ids in the update data
            $updateData = [
                'lesson_date' => $_POST['lesson_date'] ?? $lesson->getLessonDate(),
                'start_time' => $_POST['start_time'] ?? $lesson->getStartTime(),
                'end_time' => $_POST['end_time'] ?? $lesson->getEndTime(),
                'instructor' => $_POST['instructor'] ?? $lesson->getInstructor(),
                'location_id' => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
                'notes' => $_POST['notes'] ?? null,
                'entry_code' => $_POST['entry_code'] ?? null,
                'force_update' => true // Force the update to apply even if values appear identical
            ];
            
            // Add student_ids to data if present
            if (isset($_POST['student_ids']) && is_array($_POST['student_ids'])) {
                $updateData['student_ids'] = array_map('intval', $_POST['student_ids']);
            }
            
            error_log("Update data prepared: " . json_encode($updateData));
            
            $success = $lesson->update($updateData);
            error_log("Lesson update result: " . ($success ? "Success" : "Failed"));
            
            if ($success) {
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Lesson updated successfully!'];
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Failed to update lesson.'];
            }
        } else {
            error_log("Lesson not found for ID: " . $lessonId);
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Lesson not found.'];
        }
        
        header('Location: /lessons.php');
        exit;
    } elseif ($action === 'delete') {
        $lessonId = (int)($_POST['lesson_id'] ?? $_POST['id'] ?? 0);
        error_log("Attempting to delete lesson ID: " . $lessonId);
        
        if ($lessonId <= 0) {
            error_log("Invalid lesson ID for deletion: " . $lessonId);
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid lesson ID.'];
            header('Location: /lessons.php');
            exit;
        }
        
        $lesson = Lesson::findById($lessonId);
        if ($lesson) {
            // Delete Google Calendar event first
            try {
                if ($lesson->getGoogleEventId()) {
                    $calendarService->deleteLessonEvent($lesson->getGoogleEventId());
                    error_log("Deleted Google Calendar event: " . $lesson->getGoogleEventId());
                }
            } catch (Exception $e) {
                // Continue with deletion even if Google Calendar sync fails
                error_log('Failed to delete Google Calendar event: ' . $e->getMessage());
            }
            
            if ($lesson->delete()) {
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Lesson deleted successfully!'];
                header('Location: /lessons.php');
                exit();
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Failed to delete lesson.'];
            }
        } else {
            error_log("Lesson not found for deletion, ID: " . $lessonId);
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Lesson not found.'];
        }
        
        header('Location: /lessons.php');
        exit;
    } elseif ($action === 'sync') {
        $lessonId = (int)($_POST['lesson_id'] ?? $_POST['id'] ?? 0);
        error_log("Attempting to sync lesson ID: " . $lessonId);
        
        if ($lessonId <= 0) {
            error_log("Invalid lesson ID for sync: " . $lessonId);
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid lesson ID.'];
            header('Location: /lessons.php');
            exit;
        }
        
        $lesson = Lesson::findById($lessonId);
        if ($lesson) {
            try {
                $students = $lesson->getStudents();
                
                // Force reload location to ensure we have the latest data
                if ($lesson->getLocationId()) {
                    $location = Location::findById($lesson->getLocationId());
                    error_log("Reloaded location for sync: " . ($location ? $location->getName() : 'None'));
                }
                
                error_log("Entry code for sync: " . ($lesson->getEntryCode() ?? 'None'));
                
                if ($lesson->getGoogleEventId()) {
                    // Update existing event
                    error_log("Updating existing Google Calendar event: " . $lesson->getGoogleEventId());
                    $success = $calendarService->updateLessonEvent($lesson->getGoogleEventId(), $lesson, $students);
                    if ($success) {
                        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Lesson updated in Google Calendar!'];
                    } else {
                        $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Failed to update Google Calendar event.'];
                    }
                } else {
                    // Create new event
                    error_log("Creating new Google Calendar event for lesson ID: " . $lessonId);
                    $eventId = $calendarService->createLessonEvent($lesson, $students);
                    if ($eventId) {
                        // Update the lesson with the new event ID
                        $lesson->update(['google_event_id' => $eventId]);
                        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Lesson synced with Google Calendar!'];
                    } else {
                        $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Failed to create Google Calendar event.'];
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to sync with Google Calendar: " . $e->getMessage());
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Failed to sync with Google Calendar: ' . $e->getMessage()];
            }
        } else {
            error_log("Lesson not found for sync, ID: " . $lessonId);
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Lesson not found.'];
        }
        
        header('Location: /lessons.php');
        exit;
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
$locations = Location::findAll();
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
                    <h5 class="mb-0"><?= $action === 'create' ? 'Schedule New Lesson' : 'Edit Lesson' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/lessons.php" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'edit' ?>">
                        <?php if ($lesson): ?>
                            <input type="hidden" name="lesson_id" value="<?= $lesson->getId() ?>">
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
                            <label for="location_id" class="form-label">Location</label>
                            <select class="form-select" id="location_id" name="location_id" required>
                                <option value="">Select a location...</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc->getId() ?>" 
                                            data-has-entry-code="<?= $loc->hasEntryCode() ? '1' : '0' ?>"
                                            data-default-code="<?= htmlspecialchars($loc->getDefaultEntryCode() ?? '') ?>"
                                            <?= ($lesson && $lesson->getLocationId() === $loc->getId()) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($loc->getName()) ?>
                                        <?= $loc->getAddress() ? ' (' . htmlspecialchars($loc->getAddress()) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 entry-code-field" style="display: none;">
                            <label for="entry_code" class="form-label">Entry Code</label>
                            <?php 
                                $entryCodeValue = $lesson ? $lesson->getEntryCode() : '';
                                error_log("Entry code value for form: " . ($entryCodeValue ?? 'null'));
                            ?>
                            <input type="text" class="form-control" id="entry_code" name="entry_code" 
                                   value="<?= htmlspecialchars($entryCodeValue ?? '') ?>"
                                   maxlength="20">
                            <div class="form-text">The code needed to enter this location for this lesson.</div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= $lesson ? htmlspecialchars($lesson->getNotes()) : '' ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= $action === 'create' ? 'Select Students' : 'Manage Students' ?></label>
                            <div class="row row-cols-1 row-cols-md-3 g-3">
                                <?php 
                                $lessonStudentIds = $lesson ? array_map(fn($s) => $s->getId(), $lesson->getStudents()) : [];
                                foreach ($students as $student): 
                                ?>
                                    <div class="col">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="student_ids[]" 
                                                   value="<?= $student->getId() ?>" id="student_<?= $student->getId() ?>"
                                                   <?= in_array($student->getId(), $lessonStudentIds) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="student_<?= $student->getId() ?>">
                                                <?= htmlspecialchars($student->getFullName()) ?>
                                                (<?= $student->getLessonsRemaining() ?> lessons remaining)
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if ($action === 'edit' && $lesson && count($lesson->getStudents()) > 0): ?>
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

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <?= $action === 'create' ? 'Create Lesson' : 'Save Changes' ?>
                            </button>
                            <a href="/lessons.php" class="btn btn-secondary ms-2">Cancel</a>
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
                                        <th>Location</th>
                                        <th>Entry Code</th>
                                        <th>Students</th>
                                        <th>Calendar</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $l): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(date('d-m-Y', strtotime($l->getLessonDate()))) ?></td>
                                            <td><?= htmlspecialchars($l->getStartTime() . ' - ' . $l->getEndTime()) ?></td>
                                            <td><?= htmlspecialchars($l->getInstructor()) ?></td>
                                            <td>
                                                <?php if ($location = $l->getLocation()): ?>
                                                    <?= htmlspecialchars($location->getName()) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No location set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($location = $l->getLocation()): ?>
                                                    <?php if ($location->hasEntryCode()): ?>
                                                        <?php if ($l->getEntryCode()): ?>
                                                            <span class="badge bg-info"><?= htmlspecialchars($l->getEntryCode()) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Not set</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php foreach ($l->getStudents() as $student): ?>
                                                    <span class="badge bg-primary"><?= htmlspecialchars($student->getFullName()) ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php if ($l->getGoogleEventId()): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-calendar2-check"></i> Synced
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-calendar2-x"></i> Not synced
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="/lessons.php?action=edit&id=<?= $l->getId() ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" action="/lessons.php" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="lesson_id" value="<?= $l->getId() ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                title="Delete"
                                                                onclick="return confirm('Are you sure you want to delete this lesson?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="/lessons.php" class="d-inline">
                                                        <input type="hidden" name="action" value="sync">
                                                        <input type="hidden" name="lesson_id" value="<?= $l->getId() ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" 
                                                               title="<?= $l->getGoogleEventId() ? 'Update in Google Calendar' : 'Sync to Google Calendar' ?>">
                                                            <i class="bi bi-calendar2-plus"></i>
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
    <script>
        // Handle entry code field visibility and default value
        document.addEventListener('DOMContentLoaded', function() {
            const locationSelect = document.getElementById('location_id');
            const entryCodeField = document.querySelector('.entry-code-field');
            const entryCodeInput = document.getElementById('entry_code');
            
            if (locationSelect && entryCodeField && entryCodeInput) {
                // Store the original entry code value when the page loads
                const originalEntryCode = entryCodeInput.value;
                console.log('Original entry code value:', originalEntryCode);
                
                function updateEntryCodeField() {
                    const selectedOption = locationSelect.options[locationSelect.selectedIndex];
                    if (!selectedOption) return;
                    
                    const hasEntryCode = selectedOption.dataset.hasEntryCode === '1';
                    const defaultCode = selectedOption.dataset.defaultCode;
                    
                    // Only show the entry code field if the location requires it
                    if (hasEntryCode) {
                        entryCodeField.style.display = 'block';
                        console.log('Showing entry code field: Location requires entry code');
                    } else {
                        entryCodeField.style.display = 'none';
                        console.log('Hiding entry code field: Location does not require entry code');
                        // Clear the entry code value if the location doesn't require it
                        if (entryCodeInput.value) {
                            console.log('Clearing entry code value');
                            entryCodeInput.value = '';
                        }
                    }
                    
                    // Only set default code if the location requires it and we're changing to a new location
                    if (hasEntryCode && locationSelect.dataset.previousValue !== locationSelect.value) {
                        // Don't overwrite existing value when editing
                        if (!entryCodeInput.value) {
                            entryCodeInput.value = defaultCode || '';
                            console.log('Setting default code:', defaultCode);
                        }
                    }
                    
                    locationSelect.dataset.previousValue = locationSelect.value;
                }
                
                locationSelect.addEventListener('change', updateEntryCodeField);
                
                // Trigger immediately on page load
                setTimeout(updateEntryCodeField, 0);
                
                // Debug output
                console.log('Initial entry code value:', entryCodeInput.value);
                console.log('Entry code field display:', entryCodeField.style.display);
            }
        });
    </script>
</body>
</html> 