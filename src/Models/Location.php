<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class Location {
    private int $id;
    private string $name;
    private ?string $address;
    private bool $hasEntryCode;
    private ?string $defaultEntryCode;
    private string $createdAt;

    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? 0;
        $this->name = $data['name'] ?? '';
        $this->address = $data['address'] ?? null;
        $this->hasEntryCode = (bool)($data['has_entry_code'] ?? false);
        $this->defaultEntryCode = $data['default_entry_code'] ?? null;
        $this->createdAt = $data['created_at'] ?? '';
    }

    public static function create(array $data): ?Location {
        try {
            $stmt = Database::query(
                "INSERT INTO locations (name, address, has_entry_code, default_entry_code) VALUES (?, ?, ?, ?)",
                [
                    $data['name'],
                    $data['address'] ?? null,
                    $data['has_entry_code'] ?? false,
                    $data['default_entry_code'] ?? null
                ]
            );
            
            if ($stmt->rowCount() > 0) {
                $data['id'] = Database::getInstance()->lastInsertId();
                return new Location($data);
            }
        } catch (\PDOException $e) {
            return null;
        }
        
        return null;
    }

    public static function findAll(): array {
        $stmt = Database::query("SELECT * FROM locations ORDER BY name");
        return array_map(fn($row) => new Location($row), $stmt->fetchAll());
    }

    public static function findById(int $id): ?Location {
        $stmt = Database::query("SELECT * FROM locations WHERE id = ?", [$id]);
        $result = $stmt->fetch();
        return $result ? new Location($result) : null;
    }

    public function update(array $data): bool {
        try {
            $stmt = Database::query(
                "UPDATE locations SET name = ?, address = ?, has_entry_code = ?, default_entry_code = ? WHERE id = ?",
                [
                    $data['name'] ?? $this->name,
                    $data['address'] ?? $this->address,
                    $data['has_entry_code'] ?? $this->hasEntryCode,
                    $data['default_entry_code'] ?? $this->defaultEntryCode,
                    $this->id
                ]
            );
            
            if ($stmt->rowCount() > 0) {
                $this->name = $data['name'] ?? $this->name;
                $this->address = $data['address'] ?? $this->address;
                $this->hasEntryCode = $data['has_entry_code'] ?? $this->hasEntryCode;
                $this->defaultEntryCode = $data['default_entry_code'] ?? $this->defaultEntryCode;
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
                "DELETE FROM locations WHERE id = ?",
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

    public function getName(): string {
        return $this->name;
    }

    public function getAddress(): ?string {
        return $this->address;
    }

    public function getCreatedAt(): string {
        return $this->createdAt;
    }

    public function hasEntryCode(): bool {
        return $this->hasEntryCode;
    }

    public function getDefaultEntryCode(): ?string {
        return $this->defaultEntryCode;
    }
} 