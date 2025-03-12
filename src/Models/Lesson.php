<?php

namespace App\Models;

use App\Database\Database;
use App\Services\GoogleCalendarService;
use DateTime;
use PDO;

class Lesson {
    private int $id;
    private string $lessonDate;
    private string $startTime;
    private string $endTime;
    private string $instructor;
    private ?int $locationId;
    private ?string $notes;
    private ?string $googleEventId;
    private string $createdAt;
    private array $students = [];
    private ?Location $location = null;
    private ?string $entryCode;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? 0;
        $this->lessonDate = $data['lesson_date'] ?? '';
        $this->startTime = $data['start_time'] ?? '';
        $this->endTime = $data['end_time'] ?? '';
        $this->instructor = $data['instructor'] ?? '';
        $this->locationId = $data['location_id'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->googleEventId = $data['google_event_id'] ?? null;
        $this->createdAt = $data['created_at'] ?? '';
        $this->entryCode = $data['entry_code'] ?? null;
    }

    public static function create(array $data): ?Lesson {
        try {
            Database::getInstance()->beginTransaction();

            // Create the lesson
            $stmt = Database::query(
                "INSERT INTO lessons (lesson_date, start_time, end_time, instructor, location_id, notes, entry_code) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['lesson_date'],
                    $data['start_time'],
                    $data['end_time'],
                    $data['instructor'],
                    $data['location_id'] ?? null,
                    $data['notes'] ?? null,
                    $data['entry_code'] ?? null
                ]
            );
            
            if ($stmt->rowCount() > 0) {
                $lessonId = Database::getInstance()->lastInsertId();
                $lesson = new Lesson([
                    'id' => $lessonId,
                    ...$data
                ]);

                // Add students and deduct lessons
                if (!empty($data['student_ids'])) {
                    foreach ($data['student_ids'] as $studentId) {
                        $student = Student::findById($studentId);
                        if ($student && $student->deductLesson()) {
                            Database::query(
                                "INSERT INTO student_lesson (student_id, lesson_id) VALUES (?, ?)",
                                [$studentId, $lessonId]
                            );
                            $lesson->students[] = $student;
                        }
                    }
                }

                // Create Google Calendar event
                if (!empty($lesson->students)) {
                    $calendarService = new GoogleCalendarService();
                    $eventId = $calendarService->createLessonEvent($lesson, $lesson->students);
                    if ($eventId) {
                        // Now that we have the google_event_id column, we can store it in the database
                        Database::query(
                            "UPDATE lessons SET google_event_id = ? WHERE id = ?",
                            [$eventId, $lessonId]
                        );
                        $lesson->googleEventId = $eventId;
                        error_log("Google Calendar event created with ID: " . $eventId);
                    }
                }

                Database::getInstance()->commit();
                return $lesson;
            }
        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            error_log("Failed to create lesson: " . $e->getMessage());
        }
        
        return null;
    }

    public function update(array $data): bool {
        try {
            Database::getInstance()->beginTransaction();

            // Log the update data for debugging
            error_log("Updating lesson ID: " . $this->id . " with data: " . json_encode($data));
            
            // Log current values
            error_log("Current lesson values: " . json_encode([
                'lesson_date' => $this->lessonDate,
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'instructor' => $this->instructor,
                'location_id' => $this->locationId,
                'notes' => $this->notes,
                'entry_code' => $this->entryCode,
                'google_event_id' => $this->googleEventId
            ]));

            // Log the actual values being used in the query
            $updateValues = [
                'lesson_date' => $data['lesson_date'] ?? $this->lessonDate,
                'start_time' => $data['start_time'] ?? $this->startTime,
                'end_time' => $data['end_time'] ?? $this->endTime,
                'instructor' => $data['instructor'] ?? $this->instructor,
                'location_id' => $data['location_id'] ?? $this->locationId,
                'notes' => $data['notes'] ?? $this->notes,
                'entry_code' => $data['entry_code'] ?? $this->entryCode,
                'google_event_id' => $data['google_event_id'] ?? $this->googleEventId
            ];
            error_log("Values being used in update query: " . json_encode($updateValues));

            // Update basic lesson information
            $query = "UPDATE lessons SET lesson_date = ?, start_time = ?, end_time = ?, instructor = ?, location_id = ?, notes = ?, entry_code = ?, google_event_id = ? WHERE id = ?";
            error_log("Update query: " . $query);
            
            $params = [
                $data['lesson_date'] ?? $this->lessonDate,
                $data['start_time'] ?? $this->startTime,
                $data['end_time'] ?? $this->endTime,
                $data['instructor'] ?? $this->instructor,
                $data['location_id'] ?? $this->locationId,
                $data['notes'] ?? $this->notes,
                $data['entry_code'] ?? $this->entryCode,
                $data['google_event_id'] ?? $this->googleEventId,
                $this->id
            ];
            error_log("Query parameters: " . json_encode($params));
            
            // Execute the query directly with PDO to get more control
            try {
                $pdo = Database::getInstance();
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute($params);
                error_log("PDO execute result: " . ($result ? "true" : "false"));
                error_log("PDO error info: " . json_encode($stmt->errorInfo()));
                error_log("Update query affected rows: " . $stmt->rowCount());
                
                // If no rows were affected, try to understand why
                if ($stmt->rowCount() === 0) {
                    // Check if the record exists
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE id = ?");
                    $checkStmt->execute([$this->id]);
                    $count = $checkStmt->fetchColumn();
                    error_log("Record exists check: " . ($count > 0 ? "Yes" : "No"));
                    
                    if ($count > 0) {
                        // Record exists but no changes were made
                        error_log("Record exists but no changes were made. This could be because the new values are identical to the old ones.");
                        
                        // Verify current values in database
                        $verifyStmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ?");
                        $verifyStmt->execute([$this->id]);
                        $currentRecord = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                        error_log("Current record in database: " . json_encode($currentRecord));
                        
                        // Force update by adding a timestamp to notes if requested
                        if (!empty($data['force_update'])) {
                            error_log("Forcing update by adding timestamp to notes");
                            $forceQuery = "UPDATE lessons SET notes = CONCAT(IFNULL(notes, ''), ' [Updated: ', NOW(), ']') WHERE id = ?";
                            $forceStmt = $pdo->prepare($forceQuery);
                            $forceResult = $forceStmt->execute([$this->id]);
                            error_log("Force update result: " . ($forceResult ? "true" : "false") . ", rows: " . $forceStmt->rowCount());
                        }
                    }
                }
            } catch (\PDOException $e) {
                error_log("PDO Exception during update: " . $e->getMessage());
                throw $e;
            }

            // Update student assignments if provided
            if (isset($data['student_ids'])) {
                // Get current students
                $currentStudents = $this->getStudents();
                $currentStudentIds = array_map(fn($s) => $s->getId(), $currentStudents);
                
                // Calculate differences
                $newStudentIds = $data['student_ids'];
                $toAdd = array_diff($newStudentIds, $currentStudentIds);
                $toRemove = array_diff($currentStudentIds, $newStudentIds);

                // Remove students
                if (!empty($toRemove)) {
                    Database::query(
                        "DELETE FROM student_lesson WHERE lesson_id = ? AND student_id IN (" . implode(',', $toRemove) . ")",
                        [$this->id]
                    );
                    // Refund lessons
                    foreach ($toRemove as $studentId) {
                        $student = Student::findById($studentId);
                        if ($student) {
                            $student->addLessons(1);
                        }
                    }
                }

                // Add new students
                foreach ($toAdd as $studentId) {
                    $student = Student::findById($studentId);
                    if ($student && $student->deductLesson()) {
                        Database::query(
                            "INSERT INTO student_lesson (student_id, lesson_id) VALUES (?, ?)",
                            [$studentId, $this->id]
                        );
                    }
                }

                // Update Google Calendar event
                if ($this->googleEventId) {
                    try {
                        error_log("Updating Google Calendar event after student changes");
                        error_log("Entry code for student update: " . ($this->entryCode ?? 'None'));
                        
                        // Force reload location to ensure we have the latest data
                        if ($this->locationId) {
                            $this->location = Location::findById($this->locationId);
                            error_log("Reloaded location: " . ($this->location ? $this->location->getName() : 'None'));
                        }
                        
                        $calendarService = new GoogleCalendarService();
                        $students = array_map(fn($id) => Student::findById($id), $newStudentIds);
                        $students = array_filter($students); // Remove null values
                        $success = $calendarService->updateLessonEvent($this->googleEventId, $this, $students);
                        
                        if ($success) {
                            error_log("Successfully updated Google Calendar event after student changes: " . $this->googleEventId);
                        } else {
                            error_log("Failed to update Google Calendar event after student changes: " . $this->googleEventId);
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to update Google Calendar event after student changes: " . $e->getMessage());
                    }
                }
            }

            Database::getInstance()->commit();
            
            // Update object properties regardless of database changes
            // This ensures the object reflects the intended changes even if the database didn't change
            $this->lessonDate = $data['lesson_date'] ?? $this->lessonDate;
            $this->startTime = $data['start_time'] ?? $this->startTime;
            $this->endTime = $data['end_time'] ?? $this->endTime;
            $this->instructor = $data['instructor'] ?? $this->instructor;
            $this->locationId = $data['location_id'] ?? $this->locationId;
            $this->notes = $data['notes'] ?? $this->notes;
            $this->entryCode = $data['entry_code'] ?? $this->entryCode;
            $this->googleEventId = $data['google_event_id'] ?? $this->googleEventId;
            $this->location = null; // Reset cached location
            
            error_log("Object properties updated. New values: " . json_encode([
                'lesson_date' => $this->lessonDate,
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'instructor' => $this->instructor,
                'location_id' => $this->locationId,
                'notes' => $this->notes,
                'entry_code' => $this->entryCode,
                'google_event_id' => $this->googleEventId
            ]));
            
            // Update Google Calendar event if we have an ID and student_ids weren't provided
            // (if student_ids were provided, the calendar update is handled in the student assignment section)
            if ($this->googleEventId && !isset($data['student_ids'])) {
                try {
                    error_log("Updating Google Calendar event after property changes");
                    error_log("Entry code for update: " . ($this->entryCode ?? 'None'));
                    
                    $calendarService = new GoogleCalendarService();
                    $students = $this->getStudents();
                    
                    // Force reload location to ensure we have the latest data
                    if ($this->locationId) {
                        $this->location = Location::findById($this->locationId);
                        error_log("Reloaded location: " . ($this->location ? $this->location->getName() : 'None'));
                    }
                    
                    // Always update the calendar event, even if database update didn't affect rows
                    $success = $calendarService->updateLessonEvent($this->googleEventId, $this, $students);
                    if ($success) {
                        error_log("Successfully updated Google Calendar event: " . $this->googleEventId);
                    } else {
                        error_log("Failed to update Google Calendar event: " . $this->googleEventId);
                    }
                } catch (\Exception $e) {
                    error_log("Failed to update Google Calendar event after property changes: " . $e->getMessage());
                }
            }
            
            return true;
        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            error_log("Failed to update lesson: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            
            // Check if it's a PDO exception for more details
            if ($e instanceof \PDOException) {
                error_log("PDO Error code: " . $e->getCode());
                error_log("SQL State: " . $e->errorInfo[0] ?? 'N/A');
                error_log("Driver Error code: " . $e->errorInfo[1] ?? 'N/A');
                error_log("Driver Error message: " . $e->errorInfo[2] ?? 'N/A');
            }
            
            return false;
        }
    }

    public function delete(): bool {
        try {
            Database::getInstance()->beginTransaction();

            // Refund lessons to students
            $students = $this->getStudents();
            foreach ($students as $student) {
                $student->addLessons(1);
            }

            // Delete Google Calendar event
            if ($this->googleEventId) {
                try {
                    $calendarService = new GoogleCalendarService();
                    $calendarService->deleteLessonEvent($this->googleEventId);
                    error_log("Deleted Google Calendar event: " . $this->googleEventId);
                } catch (\Exception $e) {
                    error_log("Failed to delete Google Calendar event: " . $e->getMessage());
                    // Continue with deletion even if Google Calendar sync fails
                }
            }

            // Delete student assignments and the lesson
            Database::query("DELETE FROM student_lesson WHERE lesson_id = ?", [$this->id]);
            Database::query("DELETE FROM lessons WHERE id = ?", [$this->id]);

            Database::getInstance()->commit();
            return true;
        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            error_log("Failed to delete lesson: " . $e->getMessage());
            return false;
        }
    }

    public static function findAll(): array {
        $stmt = Database::query("SELECT * FROM lessons ORDER BY lesson_date DESC, start_time DESC");
        return array_map(fn($row) => new Lesson($row), $stmt->fetchAll());
    }

    public static function findUpcoming(): array {
        $stmt = Database::query(
            "SELECT * FROM lessons 
            WHERE lesson_date >= CURRENT_DATE 
            ORDER BY lesson_date ASC, start_time ASC"
        );
        
        $lessons = [];
        while ($row = $stmt->fetch()) {
            $lesson = new Lesson($row);
            $lesson->loadStudents();  // Load all students for each lesson
            $lessons[] = $lesson;
        }
        
        return $lessons;
    }

    public static function findById(int $id): ?Lesson {
        $stmt = Database::query("SELECT * FROM lessons WHERE id = ?", [$id]);
        $result = $stmt->fetch();
        if ($result) {
            $lesson = new Lesson($result);
            $lesson->loadStudents();
            return $lesson;
        }
        return null;
    }

    public function getStudents(): array {
        if (empty($this->students)) {
            $this->loadStudents();
        }
        return $this->students;
    }

    private function loadStudents(): void {
        $stmt = Database::query(
            "SELECT s.*, sl.status FROM students s 
            JOIN student_lesson sl ON s.id = sl.student_id 
            WHERE sl.lesson_id = ?",
            [$this->id]
        );
        $this->students = array_map(function($row) {
            $student = new Student($row);
            $student->setStatus($row['status'] ?? 'Present');
            return $student;
        }, $stmt->fetchAll());
    }

    public function getStartDateTime(): DateTime {
        return new DateTime($this->lessonDate . ' ' . $this->startTime);
    }

    public function getEndDateTime(): DateTime {
        return new DateTime($this->lessonDate . ' ' . $this->endTime);
    }

    // Getters
    public function getId(): int {
        return $this->id;
    }

    public function getLessonDate(): string {
        return $this->lessonDate;
    }

    public function getStartTime(): string {
        return $this->startTime;
    }

    public function getEndTime(): string {
        return $this->endTime;
    }

    public function getInstructor(): string {
        return $this->instructor;
    }

    public function getNotes(): ?string {
        return $this->notes;
    }

    public function getGoogleEventId(): ?string {
        return $this->googleEventId;
    }

    public function getCreatedAt(): string {
        return $this->createdAt;
    }

    public function addStudent(Student $student): bool {
        try {
            Database::getInstance()->beginTransaction();

            // Check if student is already in the lesson
            $stmt = Database::query(
                "SELECT * FROM student_lesson WHERE lesson_id = ? AND student_id = ?",
                [$this->id, $student->getId()]
            );
            
            if ($stmt->rowCount() === 0) {
                // Add the student to the lesson with default status
                Database::query(
                    "INSERT INTO student_lesson (lesson_id, student_id, status) VALUES (?, ?, 'Present')",
                    [$this->id, $student->getId()]
                );
                
                // Deduct one lesson from student's remaining lessons
                if ($student->getLessonsRemaining() > 0) {
                    $student->update([
                        'lessons_remaining' => $student->getLessonsRemaining() - 1
                    ]);
                }
                
                // Refresh students array
                $this->loadStudents();
                
                Database::getInstance()->commit();
                return true;
            }
            
            Database::getInstance()->commit();
            return false;
        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            error_log("Failed to add student to lesson: " . $e->getMessage());
            return false;
        }
    }

    public function updateStudentStatus(Student $student, string $status): bool {
        try {
            $stmt = Database::query(
                "UPDATE student_lesson SET status = ? WHERE lesson_id = ? AND student_id = ?",
                [$status, $this->id, $student->getId()]
            );
            
            if ($stmt->rowCount() > 0) {
                // Refresh students array
                $this->loadStudents();
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Failed to update student status: " . $e->getMessage());
            return false;
        }
    }

    public function getLocation(): ?Location {
        if ($this->location === null && $this->locationId !== null) {
            $this->location = Location::findById($this->locationId);
        }
        return $this->location;
    }

    public function getLocationId(): ?int {
        return $this->locationId;
    }

    public function getEntryCode(): ?string {
        // Make sure we return null if the entry code is empty
        return !empty($this->entryCode) ? $this->entryCode : null;
    }
} 