<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class Student {
    private int $id;
    private string $firstName;
    private string $lastName;
    private string $email;
    private ?string $phone;
    private int $lessonsRemaining;
    private string $createdAt;
    private ?string $status = null;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? 0;
        $this->firstName = $data['first_name'] ?? '';
        $this->lastName = $data['last_name'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->phone = $data['phone'] ?? null;
        $this->lessonsRemaining = $data['lessons_remaining'] ?? 0;
        $this->createdAt = $data['created_at'] ?? '';
        $this->status = $data['status'] ?? null;
    }

    public static function create(array $data): ?Student {
        try {
            $stmt = Database::query(
                "INSERT INTO students (first_name, last_name, email, phone, lessons_remaining) VALUES (?, ?, ?, ?, ?)",
                [$data['first_name'], $data['last_name'], $data['email'], $data['phone'] ?? null, $data['lessons_remaining'] ?? 0]
            );
            
            if ($stmt->rowCount() > 0) {
                $data['id'] = Database::getInstance()->lastInsertId();
                return new Student($data);
            }
        } catch (\PDOException $e) {
            // Log error or handle duplicate entries
            return null;
        }
        
        return null;
    }

    public static function findAll(): array {
        $stmt = Database::query("SELECT * FROM students ORDER BY last_name, first_name");
        return array_map(fn($row) => new Student($row), $stmt->fetchAll());
    }

    public static function findById(int $id): ?Student {
        $stmt = Database::query("SELECT * FROM students WHERE id = ?", [$id]);
        $result = $stmt->fetch();
        return $result ? new Student($result) : null;
    }

    public function update(array $data): bool {
        try {
            $stmt = Database::query(
                "UPDATE students SET first_name = ?, last_name = ?, email = ?, phone = ?, lessons_remaining = ? WHERE id = ?",
                [
                    $data['first_name'] ?? $this->firstName,
                    $data['last_name'] ?? $this->lastName,
                    $data['email'] ?? $this->email,
                    $data['phone'] ?? $this->phone,
                    $data['lessons_remaining'] ?? $this->lessonsRemaining,
                    $this->id
                ]
            );
            
            if ($stmt->rowCount() > 0) {
                $this->firstName = $data['first_name'] ?? $this->firstName;
                $this->lastName = $data['last_name'] ?? $this->lastName;
                $this->email = $data['email'] ?? $this->email;
                $this->phone = $data['phone'] ?? $this->phone;
                $this->lessonsRemaining = $data['lessons_remaining'] ?? $this->lessonsRemaining;
                return true;
            }
        } catch (\PDOException $e) {
            // Log error or handle duplicate entries
            return false;
        }
        
        return false;
    }

    public function addLessons(int $amount): bool {
        try {
            $stmt = Database::query(
                "UPDATE students SET lessons_remaining = lessons_remaining + ? WHERE id = ?",
                [$amount, $this->id]
            );
            
            if ($stmt->rowCount() > 0) {
                $this->lessonsRemaining += $amount;
                return true;
            }
        } catch (\PDOException $e) {
            return false;
        }
        
        return false;
    }

    public function deductLesson(): bool {
        if ($this->lessonsRemaining <= 0) {
            return false;
        }

        try {
            $stmt = Database::query(
                "UPDATE students SET lessons_remaining = lessons_remaining - 1 WHERE id = ? AND lessons_remaining > 0",
                [$this->id]
            );
            
            if ($stmt->rowCount() > 0) {
                $this->lessonsRemaining--;
                return true;
            }
        } catch (\PDOException $e) {
            return false;
        }
        
        return false;
    }

    public function delete(): bool {
        try {
            $stmt = Database::query(
                "DELETE FROM students WHERE id = ?",
                [$this->id]
            );
            
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    // Getters
    public function getId(): int {
        return $this->id;
    }

    public function getFirstName(): string {
        return $this->firstName;
    }

    public function getLastName(): string {
        return $this->lastName;
    }

    public function getFullName(): string {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function getPhone(): ?string {
        return $this->phone;
    }

    public function getLessonsRemaining(): int {
        return $this->lessonsRemaining;
    }

    public function getCreatedAt(): string {
        return $this->createdAt;
    }

    public function getStatus(): ?string {
        return $this->status;
    }

    public function setStatus(?string $status): void {
        $this->status = $status;
    }

    public function getLessonHistory(): array {
        $stmt = Database::query(
            "SELECT l.* FROM lessons l 
            JOIN lesson_students ls ON l.id = ls.lesson_id 
            WHERE ls.student_id = ? 
            ORDER BY l.lesson_date DESC, l.start_time DESC",
            [$this->id]
        );
        
        $lessons = [];
        while ($row = $stmt->fetch()) {
            $lesson = new Lesson($row);
            $lesson->loadStudents();  // Load all students for the lesson
            $lessons[] = $lesson;
        }
        
        return $lessons;
    }
} 