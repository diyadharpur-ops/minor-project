<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\Faculty;
use App\Models\Classroom;
use App\Models\TimetableEntry;
use App\Models\Department;
use App\Models\Notification;
use Exception;

class TimetableGenerator
{
    // College Timings (Valid non-break slots)
    protected $timeSlots = [
        '10:30-11:30', // Slot 0
        '11:30-12:30', // Slot 1
        '01:00-02:00', // Slot 2
        '02:00-03:00', // Slot 3
        '03:10-04:10', // Slot 4
        '04:10-05:10', // Slot 5
    ];
    
    protected $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function generate($deptId, $semester, $division, $academicYear, $term)
    {
        $classrooms = Classroom::all();
        if ($classrooms->isEmpty()) {
            throw new Exception("No classrooms found in the database. Please add classrooms before generating.");
        }

        $subjects = Subject::where('department_id', $deptId)->where('semester', $semester)->get();
        if ($subjects->isEmpty()) {
            return false;
        }

        $allFaculty = Faculty::where('department_id', $deptId)->get();
        if ($allFaculty->isEmpty()) {
            $allFaculty = Faculty::all();
        }

        $lectureRooms = $classrooms->filter(fn($c) => stripos($c->room_type, 'lab') === false)->values();
        $labRooms = $classrooms->filter(fn($c) => stripos($c->room_type, 'lab') !== false)->values();
        
        if ($lectureRooms->isEmpty()) $lectureRooms = $classrooms;
        if ($labRooms->isEmpty()) $labRooms = $classrooms;

        $facultyOccupiedGlobal = [];
        $roomOccupiedGlobal = [];

        // Build occupancy map for existing timetables (preventing cross-class conflicts)
        $existingEntries = TimetableEntry::whereNot(function($q) use ($deptId, $semester, $division) {
            $q->where('department_id', $deptId)
              ->where('semester', $semester)
              ->where('division', $division);
        })->get();

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
                    $facultyOccupiedGlobal[$entry->faculty_id][$entry->day][$s] = true;
                }
                if ($entry->classroom_id) {
                    $roomOccupiedGlobal[$entry->classroom_id][$entry->day][$s] = true;
                }
            }
        }

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

        // Create Blocks
        $blocks = collect();
        $totalHours = 0;

        foreach ($subjects as $subject) {
            $faculty = $getFaculty($subject);
            $isPracticalSubject = stripos($subject->subject_type ?? '', 'practical') !== false || stripos($subject->subject_type ?? '', 'lab') !== false;
            
            $theoryCount = $isPracticalSubject ? 0 : max($subject->credit ?? 3, 2);
            $practicalCount = $isPracticalSubject ? max($subject->credit ?? 2, 2) : 1; 

            for ($i = 0; $i < $theoryCount; $i++) {
                $blocks->push((object)[
                    'type' => 'Theory',
                    'duration' => 1,
                    'subject' => $subject,
                    'faculty' => $faculty
                ]);
                $totalHours += 1;
            }
            
            for ($i = 0; $i < $practicalCount; $i++) {
                $blocks->push((object)[
                    'type' => 'Practical',
                    'duration' => 2,
                    'subject' => $subject,
                    'faculty' => $faculty
                ]);
                $totalHours += 2;
            }
        }

        if ($totalHours > 36) {
            throw new Exception("Error: The subjects require {$totalHours} weekly hours, but a 6-day schedule only supports 36 hours. Please reduce subject credits.");
        }

        // Backtracking / Constraint Satisfaction Loop
        $maxAttempts = 500;
        $bestSchedule = [];
        $bestPlacedCount = -1;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Track next available slot index per day to GUARANTEE top-to-bottom filling with zero gaps
            $nextSlot = ['Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0];
            
            $subjectOnDay = [];
            $facultyOccupiedLocal = $facultyOccupiedGlobal;
            $roomOccupiedLocal = $roomOccupiedGlobal;
            
            $localAssignments = [];
            $placedAll = true;

            // Important: Place 2-hour Practicals first so they don't get blocked by 1-hour gaps near breaks
            $practicals = $blocks->where('type', 'Practical')->shuffle();
            $theories = $blocks->where('type', 'Theory')->shuffle();
            $orderedBlocks = $practicals->merge($theories);

            foreach ($orderedBlocks as $block) {
                $validPlacements = [];

                foreach ($this->days as $day) {
                    $ns = $nextSlot[$day];
                    
                    // Capacity Check
                    if ($ns + $block->duration > 6) continue;
                    
                    // Lab specific index alignment check (cannot span across breaks)
                    // Valid Lab starts: 0 (10:30), 2 (01:00), 4 (03:10)
                    if ($block->duration == 2 && !in_array($ns, [0, 2, 4])) continue;
                    
                    // Rule 1 & 2 Check: Theory & Lab of same subject MUST be on DIFFERENT days
                    if ($block->type == 'Theory' && !empty($subjectOnDay[$block->subject->id][$day]['Practical'])) continue;
                    if ($block->type == 'Practical' && !empty($subjectOnDay[$block->subject->id][$day]['Theory'])) continue;

                    $slots = [];
                    for ($i = 0; $i < $block->duration; $i++) {
                        $slots[] = $this->timeSlots[$ns + $i];
                    }

                    $facultyId = $block->faculty ? $block->faculty->id : null;
                    
                    // Check Faculty Conflict
                    $facConflict = false;
                    if ($facultyId) {
                        foreach ($slots as $s) {
                            if (!empty($facultyOccupiedLocal[$facultyId][$day][$s])) {
                                $facConflict = true;
                                break;
                            }
                        }
                    }
                    if ($facConflict) continue;

                    // Find Available Room
                    $availableRoomId = null;
                    $roomsList = ($block->type == 'Practical') ? $labRooms : $lectureRooms;
                    foreach ($roomsList->shuffle() as $room) {
                        $roomConflict = false;
                        foreach ($slots as $s) {
                            if (!empty($roomOccupiedLocal[$room->id][$day][$s])) {
                                $roomConflict = true;
                                break;
                            }
                        }
                        if (!$roomConflict) {
                            $availableRoomId = $room->id;
                            break;
                        }
                    }
                    if (!$availableRoomId && $roomsList->isNotEmpty()) continue;

                    // Calculate Penalty to pick the most balanced day
                    $penalty = rand(0, 5); // Add stochasticity to enable backtracking exploration
                    
                    $penalty += $ns * 10; // Favor emptier days

                    // Heavy penalty to avoid repeating same subject multiple times on same day
                    if (!empty($subjectOnDay[$block->subject->id][$day])) {
                        $penalty += 500; 
                    }

                    // Rule 8: Balance evenly, avoid consecutive days if possible
                    $dayIdx = array_search($day, $this->days);
                    if ($dayIdx > 0 && !empty($subjectOnDay[$block->subject->id][$this->days[$dayIdx - 1]])) $penalty += 50;
                    if ($dayIdx < 5 && !empty($subjectOnDay[$block->subject->id][$this->days[$dayIdx + 1]])) $penalty += 50;

                    $validPlacements[] = [
                        'day' => $day,
                        'ns' => $ns,
                        'slots' => $slots,
                        'room_id' => $availableRoomId,
                        'penalty' => $penalty
                    ];
                }

                if (empty($validPlacements)) {
                    $placedAll = false;
                    break; // Move to next attempt if this attempt hit a dead-end
                }

                // Sort by penalty and pick the best placement
                usort($validPlacements, fn($a, $b) => $a['penalty'] <=> $b['penalty']);
                $best = $validPlacements[0];

                $day = $best['day'];
                $slots = $best['slots'];
                $roomId = $best['room_id'];
                $facultyId = $block->faculty ? $block->faculty->id : null;

                $localAssignments[] = [
                    'department_id' => $deptId,
                    'semester' => $semester,
                    'division' => $division,
                    'academic_year' => $academicYear,
                    'term' => $term,
                    'day' => $day,
                    'time_slot' => $slots[0],
                    'subject_id' => $block->subject->id,
                    'faculty_id' => $facultyId,
                    'classroom_id' => $roomId,
                    'lecture_type' => $block->type,
                    'duration' => $block->duration,
                    'notes' => null,
                ];

                // Update constraints
                $nextSlot[$day] += $block->duration;
                $subjectOnDay[$block->subject->id][$day][$block->type] = true;

                if ($facultyId) {
                    foreach ($slots as $s) {
                        $facultyOccupiedLocal[$facultyId][$day][$s] = true;
                    }
                }
                if ($roomId) {
                    foreach ($slots as $s) {
                        $roomOccupiedLocal[$roomId][$day][$s] = true;
                    }
                }
            }

            // Keep track of the best schedule we found
            if (count($localAssignments) > $bestPlacedCount) {
                $bestPlacedCount = count($localAssignments);
                $bestSchedule = $localAssignments;
            }

            // If we successfully placed ALL blocks, we are done!
            if ($placedAll && count($localAssignments) == $blocks->count()) {
                break;
            }
        }

        // Delete existing entries for this class before saving new ones
        TimetableEntry::where([
            'department_id' => $deptId,
            'semester' => $semester,
            'division' => $division
        ])->delete();

        // Save the best timetable we found
        foreach ($bestSchedule as $a) {
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
        
        // Return true if we successfully placed all required blocks
        if ($bestPlacedCount < $blocks->count()) {
            $missing = $blocks->count() - $bestPlacedCount;
            throw new Exception("Algorithm could only place {$bestPlacedCount} out of {$blocks->count()} blocks due to tight conflicts/constraints. Please check faculty workloads or room limits.");
        }

        return true;
    }
}
