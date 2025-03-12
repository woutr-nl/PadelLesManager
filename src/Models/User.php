<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class User {
    private int $id;
    private string $username;
    private string $email;
    private string $password;
    private string $created_at;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? 0;
        $this->username = $data['username'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->password = $data['password'] ?? '';
        $this->created_at = $data['created_at'] ?? '';
    }

    public static function findByEmail(string $email): ?User {
        $stmt = Database::query(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );
        
        $result = $stmt->fetch();
        return $result ? new User($result) : null;
    }

    public static function create(array $data): ?User {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        try {
            $stmt = Database::query(
                "INSERT INTO users (username, email, password) VALUES (?, ?, ?)",
                [$data['username'], $data['email'], $hashedPassword]
            );
            
            if ($stmt->rowCount() > 0) {
                $data['id'] = Database::getInstance()->lastInsertId();
                $data['password'] = $hashedPassword;
                return new User($data);
            }
        } catch (\PDOException $e) {
            // Log error or handle duplicate entries
            return null;
        }
        
        return null;
    }

    public function verifyPassword(string $password): bool {
        return password_verify($password, $this->password);
    }

    // Getters
    public function getId(): int {
        return $this->id;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function getCreatedAt(): string {
        return $this->created_at;
    }
} 