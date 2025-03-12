-- Add lessons_remaining column to students table
ALTER TABLE students ADD COLUMN lessons_remaining INT NOT NULL DEFAULT 0; 