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
            
            // Add location if available
            if ($location = $lesson->getLocation()) {
                $locationText = $location->getName();
                if ($location->getAddress()) {
                    $locationText .= ' (' . $location->getAddress() . ')';
                }
                $event->setLocation($locationText);
            }

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

        // Add location if available
        if ($location = $lesson->getLocation()) {
            $locationText = $location->getName();
            if ($location->getAddress()) {
                $locationText .= ' (' . $location->getAddress() . ')';
            }
            $event->setLocation($locationText);
        }

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

    private function buildEventData(Lesson $lesson, array $students): array {
        // Start with instructor information
        $description = "Instructor: " . $lesson->getInstructor() . "\n\n";
        
        // Add entry code to description if available - make it VERY prominent at the top
        if ($lesson->getEntryCode()) {
            $description = "🔑 ENTRY CODE: " . $lesson->getEntryCode() . " 🔑\n\n" . $description;
        }
        
        // Add students
        $description .= "Students:\n";
        foreach ($students as $student) {
            $description .= "- " . $student->getFullName() . "\n";
        }
        
        // Add notes if available
        if ($lesson->getNotes()) {
            $description .= "\nNotes:\n" . $lesson->getNotes();
        }

        // Build the event data array
        $eventData = [
            'summary' => $this->buildEventSummaryFromStudents($students),
            'description' => $description,
            'start' => [
                'dateTime' => $lesson->getStartDateTime()->format('c'),
                'timeZone' => 'Europe/Amsterdam',
            ],
            'end' => [
                'dateTime' => $lesson->getEndDateTime()->format('c'),
                'timeZone' => 'Europe/Amsterdam',
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 30]
                ]
            ]
        ];

        // Add location if set
        if ($location = $lesson->getLocation()) {
            error_log("Setting location for event: " . $location->getName());
            $eventData['location'] = $location->getName();
            if ($location->getAddress()) {
                $eventData['location'] .= ', ' . $location->getAddress();
            }
            
            // Also add entry code to location if available
            if ($lesson->getEntryCode()) {
                $eventData['location'] .= ' (Code: ' . $lesson->getEntryCode() . ')';
            }
        } else {
            error_log("No location found for lesson ID: " . $lesson->getId());
        }

        error_log("Entry code in event data: " . ($lesson->getEntryCode() ?? 'None'));
        return $eventData;
    }

    public function createLessonEvent(Lesson $lesson, array $students): ?string {
        try {
            error_log("Creating Google Calendar event for lesson ID: " . $lesson->getId());
            
            // Build the event data
            $eventData = $this->buildEventData($lesson, $students);
            error_log("Event data for creation: " . json_encode($eventData));
            
            // Create the event
            $event = new \Google_Service_Calendar_Event($eventData);
            $createdEvent = $this->service->events->insert($this->calendarId, $event);
            
            $eventId = $createdEvent->getId();
            error_log("Successfully created event with ID: " . $eventId);
            
            return $eventId;
        } catch (\Exception $e) {
            error_log("Failed to create Google Calendar event: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            return null;
        }
    }

    public function updateLessonEvent(string $eventId, Lesson $lesson, array $students): bool {
        try {
            error_log("Updating Google Calendar event: " . $eventId);
            error_log("Lesson data: " . json_encode([
                'id' => $lesson->getId(),
                'date' => $lesson->getLessonDate(),
                'start' => $lesson->getStartTime(),
                'end' => $lesson->getEndTime(),
                'location_id' => $lesson->getLocationId(),
                'entry_code' => $lesson->getEntryCode()
            ]));
            
            // Build the event data
            $eventData = $this->buildEventData($lesson, $students);
            error_log("Event data for update: " . json_encode($eventData));
            
            // Direct API approach - get the event first
            try {
                // Get the existing event
                $existingEvent = $this->service->events->get($this->calendarId, $eventId);
                error_log("Successfully retrieved existing event: " . $eventId);
                
                // Log the current event data for debugging
                error_log("Current event summary: " . $existingEvent->getSummary());
                error_log("Current event description: " . $existingEvent->getDescription());
                error_log("Current event location: " . $existingEvent->getLocation());
                
                // Update all fields manually
                $existingEvent->setSummary($eventData['summary']);
                $existingEvent->setDescription($eventData['description']);
                
                // Set start time
                $startDateTime = new Google_Service_Calendar_EventDateTime();
                $startDateTime->setDateTime($eventData['start']['dateTime']);
                $startDateTime->setTimeZone($eventData['start']['timeZone']);
                $existingEvent->setStart($startDateTime);
                
                // Set end time
                $endDateTime = new Google_Service_Calendar_EventDateTime();
                $endDateTime->setDateTime($eventData['end']['dateTime']);
                $endDateTime->setTimeZone($eventData['end']['timeZone']);
                $existingEvent->setEnd($endDateTime);
                
                // Set location if available
                if (isset($eventData['location'])) {
                    $existingEvent->setLocation($eventData['location']);
                    error_log("Setting location to: " . $eventData['location']);
                }
                
                // Set reminders
                $reminders = new \Google_Service_Calendar_EventReminders();
                $reminders->setUseDefault(false);
                $reminderOverrides = [];
                foreach ($eventData['reminders']['overrides'] as $override) {
                    $reminderOverride = new \Google_Service_Calendar_EventReminder();
                    $reminderOverride->setMethod($override['method']);
                    $reminderOverride->setMinutes($override['minutes']);
                    $reminderOverrides[] = $reminderOverride;
                }
                $reminders->setOverrides($reminderOverrides);
                $existingEvent->setReminders($reminders);
                
                // Update the event
                $updatedEvent = $this->service->events->update($this->calendarId, $eventId, $existingEvent);
                error_log("Successfully updated event with ID: " . $updatedEvent->getId());
                
                // Verify the update was successful
                $verifiedEvent = $this->service->events->get($this->calendarId, $updatedEvent->getId());
                error_log("Verified event description: " . $verifiedEvent->getDescription());
                error_log("Verified event location: " . $verifiedEvent->getLocation());
                
                return true;
            } catch (\Exception $e) {
                error_log("Error updating existing event: " . $e->getMessage());
                error_log("Attempting to recreate event...");
                
                // If we can't update, try to delete and recreate
                try {
                    // Delete the old event
                    $this->service->events->delete($this->calendarId, $eventId);
                    error_log("Successfully deleted old event: " . $eventId);
                    
                    // Create a new event
                    $newEvent = new \Google_Service_Calendar_Event($eventData);
                    $createdEvent = $this->service->events->insert($this->calendarId, $newEvent);
                    
                    // Update the lesson with the new event ID
                    $lesson->update(['google_event_id' => $createdEvent->getId()]);
                    error_log("Successfully recreated event with new ID: " . $createdEvent->getId());
                    
                    return true;
                } catch (\Exception $recreateEx) {
                    error_log("Failed to recreate event: " . $recreateEx->getMessage());
                    return false;
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to update Google Calendar event: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function deleteLessonEvent(string $eventId): bool {
        try {
            $this->service->events->delete($this->calendarId, $eventId);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to delete Google Calendar event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build a summary for the event based on the students
     */
    private function buildEventSummaryFromStudents(array $students): string {
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
} 