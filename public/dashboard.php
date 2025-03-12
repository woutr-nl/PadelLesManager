<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Student;
use App\Models\Lesson;
use App\Middleware\AuthMiddleware;

// Require authentication
AuthMiddleware::requireAuth();

// Get statistics
$totalStudents = count(Student::findAll());
$totalLessons = count(Lesson::findAll());
$upcomingLessons = Lesson::findUpcoming();
$todaysLessons = array_filter($upcomingLessons, fn($l) => $l->getLessonDate() === date('Y-m-d'));
$futureUpcomingLessons = array_filter($upcomingLessons, fn($l) => $l->getLessonDate() > date('Y-m-d'));
$studentsNeedingLessons = array_filter(Student::findAll(), fn($s) => $s->getLessonsRemaining() <= 2);

// Sort students by lessons remaining (ascending)
usort($studentsNeedingLessons, fn($a, $b) => $a->getLessonsRemaining() <=> $b->getLessonsRemaining());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PadelLesManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2rem;
            opacity: 0.7;
        }
        /* Floating Action Button styles */
        .fab-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 999;
        }
        .fab-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .fab-button:hover {
            transform: scale(1.1);
        }
        .fab-menu {
            position: absolute;
            bottom: 80px;
            right: 0;
            display: none;
            flex-direction: column;
            gap: 1rem;
            min-width: 200px;
        }
        .fab-menu.show {
            display: flex;
        }
        .fab-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            background: white;
            color: var(--bs-primary);
            transition: transform 0.2s;
        }
        .fab-item:hover {
            transform: translateX(-5px);
            color: var(--bs-primary);
        }
        .fab-item i {
            font-size: 1.2rem;
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
                        <a class="nav-link active" href="/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/lessons.php">Lessons</a>
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
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">Total Students</h6>
                                <h2 class="mb-0"><?= $totalStudents ?></h2>
                            </div>
                            <i class="bi bi-people stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">Total Lessons</h6>
                                <h2 class="mb-0"><?= $totalLessons ?></h2>
                            </div>
                            <i class="bi bi-calendar3 stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">Today's Lessons</h6>
                                <h2 class="mb-0"><?= count($todaysLessons) ?></h2>
                            </div>
                            <i class="bi bi-calendar-check stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-warning text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">Low on Lessons</h6>
                                <h2 class="mb-0"><?= count($studentsNeedingLessons) ?></h2>
                            </div>
                            <i class="bi bi-exclamation-triangle stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Today's Lessons -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-check me-2"></i>
                            Today's Lessons
                        </h5>
                        <span class="badge bg-primary"><?= count($todaysLessons) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todaysLessons)): ?>
                            <p class="text-muted">No lessons scheduled for today.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($todaysLessons as $lesson): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-1">
                                                <?= htmlspecialchars($lesson->getStartTime()) ?> - 
                                                <?= htmlspecialchars($lesson->getEndTime()) ?>
                                            </h6>
                                            <?php if ($lesson->getGoogleEventId()): ?>
                                                <i class="bi bi-calendar-check text-success"></i>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-1">
                                            <strong>Instructor:</strong> <?= htmlspecialchars($lesson->getInstructor()) ?>
                                        </p>
                                        <p class="mb-0 text-muted">
                                            <strong>Students:</strong> 
                                            <?= htmlspecialchars(implode(', ', array_map(fn($s) => $s->getFirstName(), $lesson->getStudents()))) ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Students Needing Lessons -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Students Needing Lessons
                        </h5>
                        <span class="badge bg-warning text-dark"><?= count($studentsNeedingLessons) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($studentsNeedingLessons)): ?>
                            <p class="text-muted">All students have sufficient lessons.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($studentsNeedingLessons as $student): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-1"><?= htmlspecialchars($student->getFullName()) ?></h6>
                                            <span class="badge bg-<?= $student->getLessonsRemaining() <= 0 ? 'danger' : 'warning' ?>">
                                                <?= $student->getLessonsRemaining() ?> lessons
                                            </span>
                                        </div>
                                        <p class="mb-0">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="showAddLessonsModal(<?= $student->getId() ?>, '<?= htmlspecialchars($student->getFullName()) ?>')">
                                                <i class="bi bi-plus-circle"></i> Add Lessons
                                            </button>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <a href="/students.php?action=create" class="btn btn-success w-100">
                                    <i class="bi bi-person-plus"></i> Add New Student
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="/lessons.php?action=create" class="btn btn-success w-100">
                                    <i class="bi bi-calendar-plus"></i> Schedule New Lesson
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="/lessons.php" class="btn btn-primary w-100">
                                    <i class="bi bi-calendar-week"></i> View All Lessons
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Lessons -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar3-week me-2"></i>
                            Upcoming Lessons
                        </h5>
                        <span class="badge bg-primary"><?= count($futureUpcomingLessons) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($futureUpcomingLessons)): ?>
                            <p class="text-muted">No upcoming lessons scheduled.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Instructor</th>
                                            <th>Students</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $currentDate = null;
                                        foreach (array_slice($futureUpcomingLessons, 0, 10) as $lesson): 
                                            $lessonDate = date('Y-m-d', strtotime($lesson->getLessonDate()));
                                            $isNewDate = $currentDate !== $lessonDate;
                                            $currentDate = $lessonDate;
                                        ?>
                                            <?php if ($isNewDate): ?>
                                                <tr class="table-light">
                                                    <td colspan="5" class="fw-bold">
                                                        <?= date('l, F j', strtotime($lesson->getLessonDate())) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td class="text-muted">
                                                    <?= date('d-m-Y', strtotime($lesson->getLessonDate())) ?>
                                                </td>
                                                <td>
                                                    <strong>
                                                        <?= htmlspecialchars($lesson->getStartTime()) ?> - 
                                                        <?= htmlspecialchars($lesson->getEndTime()) ?>
                                                    </strong>
                                                </td>
                                                <td><?= htmlspecialchars($lesson->getInstructor()) ?></td>
                                                <td>
                                                    <?php foreach ($lesson->getStudents() as $student): ?>
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($student->getFirstName()) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </td>
                                                <td>
                                                    <?php if ($lesson->getGoogleEventId()): ?>
                                                        <i class="bi bi-calendar-check text-success" title="Synced with Google Calendar"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-calendar-x text-warning" title="Not synced with Google Calendar"></i>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if (count($futureUpcomingLessons) > 10): ?>
                                    <div class="text-center mt-3">
                                        <a href="/lessons.php" class="btn btn-outline-primary">
                                            View All Lessons
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
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

    <!-- Floating Action Button -->
    <div class="fab-container">
        <button class="fab-button btn btn-primary" onclick="toggleFabMenu()">
            <i class="bi bi-plus"></i>
        </button>
        <div class="fab-menu" id="fabMenu">
            <a href="/students.php?action=create" class="fab-item">
                <i class="bi bi-person-plus"></i>
                Add New Student
            </a>
            <a href="/lessons.php?action=create" class="fab-item">
                <i class="bi bi-calendar-plus"></i>
                Schedule New Lesson
            </a>
            <a href="/lessons.php" class="fab-item">
                <i class="bi bi-calendar-week"></i>
                View All Lessons
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let addLessonsModal;
        let fabMenuVisible = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            addLessonsModal = new bootstrap.Modal(document.getElementById('addLessonsModal'));

            // Close FAB menu when clicking outside
            document.addEventListener('click', function(event) {
                const fabContainer = document.querySelector('.fab-container');
                const fabButton = document.querySelector('.fab-button');
                if (!fabContainer.contains(event.target) || event.target === fabButton) {
                    hideFabMenu();
                }
            });
        });

        function showAddLessonsModal(studentId, studentName) {
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('modalStudentName').textContent = studentName;
            addLessonsModal.show();
        }

        function toggleFabMenu() {
            const menu = document.getElementById('fabMenu');
            fabMenuVisible = !fabMenuVisible;
            menu.classList.toggle('show', fabMenuVisible);
        }

        function hideFabMenu() {
            const menu = document.getElementById('fabMenu');
            fabMenuVisible = false;
            menu.classList.remove('show');
        }
    </script>
</body>
</html> 