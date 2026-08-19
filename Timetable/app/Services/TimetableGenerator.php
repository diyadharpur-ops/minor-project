<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\Faculty;
use App\Models\Classroom;
use App\Models\TimetableEntry;
use App\Models\Department;
use App\Models\Notification;
use App\Models\FacultyWorkload;
use Exception;

class TimetableGenerator
{
    // College Timings based on requirements
    protected $timeSlots = [
        '10:30-11:30', // Slot 0
        '11:30-12:30', // Slot 1
        // Lunch Break 12:30 PM - 1:00 PM
        '01:00-02:00', // Slot 2
        '02:00-03:00', // Slot 3
        // Tea Break 3:00 PM - 3:10 PM
        '03:10-04:10', // Slot 4
        '04:10-05:10', // Slot 5
    ];
    
    protected $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function generate($deptId, $semester, $division, $academicYear, $term)
    {
        // Check if classrooms exist globally (since classrooms do not have department_id in schema)
        $classrooms = Classroom::all();
        if ($classrooms->isEmpty()) {
            throw new Exception("No classrooms found in the database. Please add classrooms before generating.");
        }

        // Delete existing entries for this specific class
        TimetableEntry::where([
            'department_id' => $deptId,
            'semester' => $semester,
            'division' => $division
        ])->delete();

        $subjects = Subject::where('department_id', $deptId)->where('semester', $semester)->get();
        if ($subjects->isEmpty()) {
            return false;
        }

        $allFaculty = Faculty::where('department_id', $deptId)->get();
        if ($allFaculty->isEmpty()) {
            $allFaculty = Faculty::all(); // Fallback if no faculty mapped to department
        }

        // Separate lecture rooms and labs
        $lectureRooms = $classrooms->filter(fn($c) => stripos($c->room_type, 'lab') === false)->values();
        $labRooms = $classrooms->filter(fn($c) => stripos($c->room_type, 'lab') !== false)->values();
        
        // Fallbacks if strictly named 'lab' doesn't exist
        if ($lectureRooms->isEmpty()) {
            $lectureRooms = $classrooms;
        }
        if ($labRooms->isEmpty()) {
            $labRooms = $classrooms; // Very rare, but prevents failure if no labs defined
        }

        // Build occupancy maps to prevent conflicts globally
        // format: $facultyOccupied[faculty_id][day][time_slot] = true
        $facultyOccupied = [];
        $roomOccupied = [];

        $existingEntries = TimetableEntry::all();
        foreach ($existingEntries as $entry) {
            $slotsToMark = [$entry->time_slot];
            if ($entry->duration == 2) {
                $idx = array_search($entry->time_slot, $this->timeSlots);
                if ($idx !== false && isset($this->timeSlots[$idx + 1])) {
                    $slotsToMark[] = $this->timeSlots[$idx + 1];
                }
            }

            foreach ($slotsToMark as $s) {
                if ($entry->faculty_id) {
                    $facultyOccupied[$entry->faculty_id][$entry->day][$s] = true;
                }
                if ($entry->classroom_id) {
                    $roomOccupied[$entry->classroom_id][$entry->day][$s] = true;
                }
            }
        }

        // Helper to find faculty based on subject name
        $getFaculty = function($subject) use ($allFaculty) {
            if ($allFaculty->isEmpty()) return null;
            if ($subject->faculty_name) {
                $f = $allFaculty->first(fn($fac) => stripos($fac->name, $subject->faculty_name) !== false);
                if ($f) return $f;
            }
            $f = $allFaculty->first(function($fac) use ($subject) {
                return $fac->subjects && stripos($fac->subjects, $subject->name) !== false;
            });
            if ($f) return $f;
            
            return $allFaculty->random();
        };

        $assignments = [];

        foreach ($subjects as $subject) {
            $faculty = $getFaculty($subject);
            
            // Determine weekly requirements dynamically
            // Theory = credit hours (fallback to 3)
            // If subject_type is practical, we assign more labs, otherwise 1 lab.
            $isPracticalSubject = stripos($subject->subject_type ?? '', 'practical') !== false || stripos($subject->subject_type ?? '', 'lab') !== false;
            
            $theoryCount = $isPracticalSubject ? 0 : max($subject->credit ?? 3, 2);
            $practicalCount = $isPracticalSubject ? max($subject->credit ?? 2, 2) : 1; 

            // Assign Theory Lectures
            for ($i = 0; $i < $theoryCount; $i++) {
                $this->assignSlot($assignments, 'Theory', 1, $subject, $faculty, $lectureRooms, $facultyOccupied, $roomOccupied, $deptId, $semester, $division, $academicYear, $term);
            }
            
            // Assign Lab Practicals
            for ($i = 0; $i < $practicalCount; $i++) {
                $this->assignSlot($assignments, 'Practical', 2, $subject, $faculty, $labRooms, $facultyOccupied, $roomOccupied, $deptId, $semester, $division, $academicYear, $term);
            }
        }
        
        // Save to existing timetable_entries table directly
        foreach ($assignments as $a) {
            TimetableEntry::create($a);
        }

        $dept = Department::find($deptId);
        if ($dept) {
            Notification::trigger('Timetable Generated', [
                'department_name' => $dept->name,
                'semester' => $semester,
                'academic_year' => $academicYear,
            ]);
        }
        
        return true;
    }

    /**
     * Attempts to find a valid non-conflicting slot and assigns it.
     */
    private function assignSlot(&$assignments, $type, $duration, $subject, $faculty, $rooms, &$facultyOccupied, &$roomOccupied, $deptId, $semester, $division, $academicYear, $term) {
        $maxAttempts = 500;
        
        for ($attempts = 0; $attempts < $maxAttempts; $attempts++) {
            $day = $this->days[array_rand($this->days)];
            
            if ($duration == 2) {
                // Lab practicals: 2 consecutive hours without crossing breaks
                // Valid start indices: 0 (10:30), 2 (01:00), 4 (03:10)
                $validStarts = [0, 2, 4];
                $startIndex = $validStarts[array_rand($validStarts)];
                $slots = [$this->timeSlots[$startIndex], $this->timeSlots[$startIndex + 1]];
            } else {
                // Theory lecture: 1 hour
                $startIndex = array_rand($this->timeSlots);
                $slots = [$this->timeSlots[$startIndex]];
            }

            $room = $rooms->isNotEmpty() ? $rooms->random() : null;
            $roomId = $room ? $room->id : null;
            $facultyId = $faculty ? $faculty->id : null;

            // Constraint 1: Division/Class Conflict (No duplicate lecture for same class at same time)
            $classConflict = false;
            foreach ($assignments as $a) {
                if ($a['day'] == $day) {
                    $aSlots = [$a['time_slot']];
                    if ($a['duration'] == 2) {
                        $idx = array_search($a['time_slot'], $this->timeSlots);
                        if ($idx !== false && isset($this->timeSlots[$idx + 1])) {
                            $aSlots[] = $this->timeSlots[$idx + 1];
                        }
                    }
                    if (array_intersect($slots, $aSlots)) {
                        $classConflict = true; 
                        break;
                    }
                }
            }
            if ($classConflict) continue;

            // Constraint 2: Faculty Conflict (Faculty cannot teach two classes at same time)
            $facConflict = false;
            if ($facultyId) {
                foreach ($slots as $s) {
                    if (isset($facultyOccupied[$facultyId][$day][$s])) {
                        $facConflict = true; 
                        break;
                    }
                }
                
                // Avoid assigning same faculty multiple times in same day if possible (soft constraint)
                if (!$facConflict && $attempts < 50) {
                    $dayAssignments = collect($assignments)->where('day', $day)->where('faculty_id', $facultyId)->count();
                    if ($dayAssignments >= 2) {
                        $facConflict = true;
                    }
                }
            }
            if ($facConflict) continue;

            // Constraint 3: Room/Lab Conflict (Room cannot be assigned twice at same time)
            $roomConflict = false;
            if ($roomId) {
                foreach ($slots as $s) {
                    if (isset($roomOccupied[$roomId][$day][$s])) {
                        $roomConflict = true; 
                        break;
                    }
                }
            }
            if ($roomConflict) continue;

            // Avoid repeating same subject multiple times in same day (soft constraint)
            $subjectDuplicate = false;
            if ($attempts < 100) {
                $sameSubjectToday = collect($assignments)->where('day', $day)->where('subject_id', $subject->id)->count();
                if ($sameSubjectToday >= 1) {
                    $subjectDuplicate = true;
                }
            }
            if ($subjectDuplicate) continue;

            // If we passed all constraints, assign it
            $assignments[] = [
                'department_id' => $deptId,
                'semester' => $semester,
                'division' => $division,
                'academic_year' => $academicYear,
                'term' => $term,
                'day' => $day,
                'time_slot' => $slots[0],
                'subject_id' => $subject->id,
                'faculty_id' => $facultyId,
                'classroom_id' => $roomId,
                'lecture_type' => $type,
                'duration' => $duration,
                'notes' => null,
            ];

            // Mark globally as occupied
            if ($facultyId) {
                foreach ($slots as $s) {
                    $facultyOccupied[$facultyId][$day][$s] = true;
                }
            }
            if ($roomId) {
                foreach ($slots as $s) {
                    $roomOccupied[$roomId][$day][$s] = true;
                }
            }

            return true;
        }
        
        // If we fail after max attempts, just log or skip, algorithm did its best
        return false; 
    }
}
