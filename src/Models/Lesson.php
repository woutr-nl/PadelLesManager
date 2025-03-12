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
    }

    public static function create(array $data): ?Lesson {
        try {
            Database::getInstance()->beginTransaction();

            // Create the lesson
            $stmt = Database::query(
                "INSERT INTO lessons (lesson_date, start_time, end_time, instructor, location_id, notes) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $data['lesson_date'],
                    $data['start_time'],
                    $data['end_time'],
                    $data['instructor'],
                    $data['location_id'] ?? null,
                    $data['notes'] ?? null
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
                        Database::query(
                            "UPDATE lessons SET google_event_id = ? WHERE id = ?",
                            [$eventId, $lessonId]
                        );
                        $lesson->googleEventId = $eventId;
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

            // Update basic lesson information
            $stmt = Database::query(
                "UPDATE lessons SET lesson_date = ?, start_time = ?, end_time = ?, instructor = ?, location_id = ?, notes = ? WHERE id = ?",
                [
                    $data['lesson_date'] ?? $this->lessonDate,
                    $data['start_time'] ?? $this->startTime,
                    $data['end_time'] ?? $this->endTime,
                    $data['instructor'] ?? $this->instructor,
                    $data['location_id'] ?? $this->locationId,
                    $data['notes'] ?? $this->notes,
                    $this->id
                ]
            );

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
                    $calendarService = new GoogleCalendarService();
                    $students = array_map(fn($id) => Student::findById($id), $newStudentIds);
                    $students = array_filter($students); // Remove null values
                    $calendarService->updateLessonEvent($this->googleEventId, $this, $students);
                }
            }

            Database::getInstance()->commit();
            
            // Update object properties
            $this->lessonDate = $data['lesson_date'] ?? $this->lessonDate;
            $this->startTime = $data['start_time'] ?? $this->startTime;
            $this->endTime = $data['end_time'] ?? $this->endTime;
            $this->instructor = $data['instructor'] ?? $this->instructor;
            $this->locationId = $data['location_id'] ?? $this->locationId;
            $this->notes = $data['notes'] ?? $this->notes;
            $this->location = null; // Reset cached location
            
            return true;
        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            error_log("Failed to update lesson: " . $e->getMessage());
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
                $calendarService = new GoogleCalendarService();
                $calendarService->deleteLessonEvent($this->googleEventId);
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
} 