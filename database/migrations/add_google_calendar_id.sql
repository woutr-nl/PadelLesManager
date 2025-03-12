-- Add Google Calendar event ID column to lessons table
ALTER TABLE lessons ADD COLUMN google_event_id VARCHAR(255) NULL; 