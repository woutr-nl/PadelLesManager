<?php

namespace App\Services;

use App\Core\Application;
use App\Models\Lesson;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventAttendee;
use Exception;

class GoogleCalendarService
{
    private $client;
    private $service;
    private $calendarId;

    public function __construct()
    {
        $credentialsPath = Application::config('google.credentials_path');
        $this->calendarId = Application::config('google.calendar_id');

        if (!$credentialsPath || !$this->calendarId) {
            throw new Exception('Google Calendar configuration is incomplete. Please check your .env file.');
        }

        // Make the path absolute if it's relative
        if (!str_starts_with($credentialsPath, '/') && !preg_match('/^[A-Za-z]:\\\\/', $credentialsPath)) {
            $credentialsPath = __DIR__ . '/../../' . $credentialsPath;
        }

        if (!file_exists($credentialsPath)) {
            throw new Exception('Google Calendar credentials file not found at: ' . $credentialsPath);
        }

        try {
            $this->client = new Google_Client();
            $this->client->setAuthConfig($credentialsPath);
            $this->client->setScopes([
                Google_Service_Calendar::CALENDAR,
                Google_Service_Calendar::CALENDAR_EVENTS
            ]);
            $this->service = new Google_Service_Calendar($this->client);

            // Verify calendar access
            try {
                error_log("Attempting to access calendar with ID: " . $this->calendarId);
                $calendar = $this->service->calendars->get($this->calendarId);
                error_log("Successfully connected to calendar: " . $calendar->getSummary());
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                error_log("Calendar access error: " . $errorMessage);
                
                // Check if the error is a 404
                if (strpos($errorMessage, '"code": 404') !== false) {
                    throw new Exception(
                        "Calendar not found. Please verify that:\n" .
                        "1. The calendar ID is exactly: {$this->calendarId}\n" .
                        "2. The calendar exists and is accessible\n" .
                        "3. The service account email has been added to the calendar's sharing settings\n" .
                        "\nTip: Double-check the calendar ID in your Google Calendar settings under 'Integrate calendar'"
                    );
                }
                
                throw new Exception(
                    "Cannot access calendar with ID: {$this->calendarId}. " .
                    "Please verify that:\n" .
                    "1. The calendar ID is correct\n" .
                    "2. The service account has been given access to the calendar\n" .
                    "3. The calendar is shared with the service account email\n" .
                    "Error: " . $errorMessage
                );
            }
        } catch (\Exception $e) {
            throw new Exception("Failed to initialize Google Calendar service: " . $e->getMessage());
        }
    }

    private function buildEventSummary(Lesson $lesson): string
    {
        $students = $lesson->getStudents();
        if (empty($students)) {
            return 'Padel Lesson';
        }

        $names = array_map(function($student) {
            return $student->getFirstName();
        }, $students);

        if (count($names) === 1) {
            return 'Padelles met ' . $names[0];
        } else {
            $lastStudent = array_pop($names);
            return 'Padelles met ' . implode(', ', $names) . ' en ' . $lastStudent;
        }
    }

    public function createEvent(Lesson $lesson)
    {
        try {
            error_log("Creating Google Calendar event for lesson ID: " . $lesson->getId());
            
            $event = new Google_Service_Calendar_Event([
                'summary' => $this->buildEventSummary($lesson),
                'description' => $this->buildEventDescription($lesson),
                'start' => [
                    'dateTime' => $this->formatDateTime($lesson->getLessonDate(), $lesson->getStartTime()),
                    'timeZone' => 'Europe/Amsterdam',
                ],
                'end' => [
                    'dateTime' => $this->formatDateTime($lesson->getLessonDate(), $lesson->getEndTime()),
                    'timeZone' => 'Europe/Amsterdam',
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 60], // 1 hour before
                    ],
                ],
            ]);

            error_log("Attempting to insert event into calendar: " . $this->calendarId);
            $event = $this->service->events->insert($this->calendarId, $event);
            error_log("Successfully created event with ID: " . $event->getId());
            
            // Update the lesson with the Google Calendar event ID
            $lesson->update(['google_event_id' => $event->getId()]);
            
            return $event;
        } catch (\Exception $e) {
            error_log("Failed to create Google Calendar event: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateEvent(Lesson $lesson)
    {
        if (!$lesson->getGoogleEventId()) {
            return $this->createEvent($lesson);
        }

        $event = $this->service->events->get($this->calendarId, $lesson->getGoogleEventId());
        
        $event->setSummary($this->buildEventSummary($lesson));
        $event->setDescription($this->buildEventDescription($lesson));
        
        $event->setStart(new Google_Service_Calendar_EventDateTime([
            'dateTime' => $this->formatDateTime($lesson->getLessonDate(), $lesson->getStartTime()),
            'timeZone' => 'Europe/Amsterdam',
        ]));
        
        $event->setEnd(new Google_Service_Calendar_EventDateTime([
            'dateTime' => $this->formatDateTime($lesson->getLessonDate(), $lesson->getEndTime()),
            'timeZone' => 'Europe/Amsterdam',
        ]));

        return $this->service->events->update($this->calendarId, $lesson->getGoogleEventId(), $event);
    }

    public function deleteEvent(Lesson $lesson)
    {
        if ($lesson->getGoogleEventId()) {
            $this->service->events->delete($this->calendarId, $lesson->getGoogleEventId(), ['sendUpdates' => 'all']);
            $lesson->update(['google_event_id' => null]);
        }
    }

    private function buildEventDescription(Lesson $lesson)
    {
        $description = "Padel Lesson\n\n";
        $description .= "Instructor: " . $lesson->getInstructor() . "\n\n";
        $description .= "Students:\n";
        
        foreach ($lesson->getStudents() as $student) {
            $description .= "- " . $student->getFullName();
            if ($student->getEmail()) {
                $description .= " (" . $student->getEmail() . ")";
            }
            $description .= "\n";
        }
        
        if ($lesson->getNotes()) {
            $description .= "\nNotes:\n" . $lesson->getNotes();
        }
        
        return $description;
    }

    private function getAttendees(Lesson $lesson)
    {
        $attendees = [];
        
        foreach ($lesson->getStudents() as $student) {
            if ($student->getEmail()) {
                $attendees[] = new Google_Service_Calendar_EventAttendee([
                    'email' => $student->getEmail(),
                    'displayName' => $student->getFullName(),
                ]);
            }
        }
        
        return $attendees;
    }

    private function formatDateTime($date, $time)
    {
        return date('c', strtotime($date . ' ' . $time));
    }
} 