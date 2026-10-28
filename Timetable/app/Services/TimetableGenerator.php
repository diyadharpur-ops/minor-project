<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Division;
use App\Models\Faculty;
use App\Models\Notification;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Models\TimetableEntry;
use Exception;
use Illuminate\Support\Facades\DB;

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

    public function __construct(private ?FacultyAllocationService $facultyAllocationService = null)
    {
        $this->facultyAllocationService ??= new FacultyAllocationService;
    }

    public function generate(
        int|string $deptId,
        string $semester,
        string $division,
        string $academicYear,
        string $term,
        bool $allocateFaculty = true
    ): bool {
        $classrooms = Classroom::query()
            ->where('availability', 'Available')
            ->get();
        if ($classrooms->isEmpty()) {
            throw new Exception('No classrooms found in the database. Please add classrooms before generating.');
        }

        $department = Department::find($deptId);
        if (! $department) {
            throw new Exception('The selected department could not be found.');
        }

        if ($allocateFaculty) {
            $this->facultyAllocationService->generateForSelection([
                'department_id' => $deptId,
                'semester' => $semester,
                'division' => $division,
            ]);
        }

        $divModel = Division::where('name', $division)->where('semester', $semester)->first();

        $subjects = collect();
        if ($divModel) {
            $subjects = Subject::where('department_id', $deptId)
                ->where('semester', $semester)
                ->where(function ($query) use ($divModel): void {
                    $query->where('division_id', $divModel->id)
                        ->orWhereNull('division_id');
                })
                ->get();
        } else {
            $subjects = Subject::where('department_id', $deptId)
                ->where('semester', $semester)
                ->whereNull('division_id')
                ->get();
        }

        if ($subjects->isEmpty()) {
            return false;
        }

        $subjects = $subjects
            ->loadMissing('division')
            ->filter(fn (Subject $subject): bool => in_array($division, $subject->effective_class_selections, true))
            ->values();

        if ($subjects->isEmpty()) {
            return false;
        }

        $allFaculty = Faculty::where('department_id', $deptId)->get();

        $lectureRooms = $classrooms->filter(fn ($c) => stripos($c->room_type, 'lab') === false)->values();
        $labRooms = $classrooms->filter(fn ($c) => stripos($c->room_type, 'lab') !== false)->values();
        $roomIdByNumber = $classrooms->pluck('id', 'room_number');

        $facultyOccupiedGlobal = [];
        $roomOccupiedGlobal = [];
        $className = $department->name.'-'.$semester.'-'.$division;

        // Build occupancy map for existing timetables (preventing cross-class conflicts)
        $existingEntries = TimetableEntry::whereNot(function ($q) use ($deptId, $semester, $division) {
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
                if ($entry->notes && str_starts_with($entry->notes, 'Room2:')) {
                    $secondRoom = $roomIdByNumber->get(substr($entry->notes, 6));
                    if ($secondRoom) {
                        $roomOccupiedGlobal[$secondRoom][$entry->day][$s] = true;
                    }
                }
            }
        }

        $scheduledAllocations = RoomAllocation::query()
            ->whereNotNull('day')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where(function ($query) use ($className): void {
                $query->where('class_name', '!=', $className)
                    ->orWhereNull('class_name');
            })
            ->get();

        foreach ($scheduledAllocations as $allocation) {
            $allocationStart = $this->timeInMinutes((string) $allocation->start_time);
            $allocationEnd = $this->timeInMinutes((string) $allocation->end_time);
            if ($allocationStart === null || $allocationEnd === null) {
                continue;
            }

            foreach ($this->timeSlots as $timeSlot) {
                [$slotStart, $slotEnd] = explode('-', $timeSlot);
                $slotStartTime = $this->timeInMinutes($slotStart);
                $slotEndTime = $this->timeInMinutes($slotEnd);
                if ($slotStartTime === null || $slotEndTime === null
                    || $allocationStart >= $slotEndTime
                    || $allocationEnd <= $slotStartTime) {
                    continue;
                }

                if ($allocation->faculty_id) {
                    $facultyOccupiedGlobal[$allocation->faculty_id][$allocation->day][$timeSlot] = true;
                }

                foreach ([$allocation->classroom_id, $allocation->second_classroom_id] as $roomId) {
                    if ($roomId) {
                        $roomOccupiedGlobal[$roomId][$allocation->day][$timeSlot] = true;
                    }
                }
            }
        }

        $roomAllocations = RoomAllocation::with(['faculty', 'classroom', 'secondClassroom'])
            ->where('department_id', $deptId)
            ->where('semester', $semester)
            ->where(function ($query) use ($className, $division): void {
                $query->where('class_name', $className)
                    ->orWhere('class_name', $division);
            })
            ->get();

        $findActivityAllocation = function (Subject $subject, string $type, ?string $batch) use ($roomAllocations): ?RoomAllocation {
            return $roomAllocations->first(fn (RoomAllocation $allocation): bool => $allocation->subject_id === $subject->id
                && $allocation->activity_type === $type
                && $allocation->activity_batch === $batch);
        };
        $findLegacyAllocation = function (Subject $subject, string $type, ?string $batch = null) use ($roomAllocations, $division): ?RoomAllocation {
            $allocationType = $type === 'Practical' ? 'Lab' : 'Classroom';
            $subjectAllocations = $roomAllocations->filter(fn (RoomAllocation $allocation): bool => $allocation->subject_id === $subject->id
                && $allocation->activity_type === null
                && ($allocation->allocation_type === $allocationType || $allocation->allocation_type === null));

            if ($batch !== null) {
                $batchAllocation = $subjectAllocations->first(
                    fn (RoomAllocation $allocation): bool => $allocation->division === $batch
                );
                if ($batchAllocation) {
                    return $batchAllocation;
                }

                return $subjectAllocations->first(
                    fn (RoomAllocation $allocation): bool => $allocation->division === $division
                );
            }

            return $subjectAllocations->first()
                ?? $roomAllocations->first(fn (RoomAllocation $allocation): bool => $allocation->subject_id === $subject->id
                    && $allocation->activity_type === null);
        };
        $findRoomAllocation = function (Subject $subject, string $type, ?string $batch) use (
            $findActivityAllocation,
            $findLegacyAllocation,
            $roomAllocations

        ): ?RoomAllocation {
            $allocationType = $type === 'Practical' ? 'Lab' : 'Classroom';
            $batchCandidates = [$batch];

            if ($type === 'Practical' && $batch !== null) {
                $pair = $this->pairedPracticalBatches($batch);
                if ($pair !== null) {
                    $batchCandidates = $pair;
                }
            }

            $fallbackAllocation = null;
            foreach (array_unique($batchCandidates) as $candidateBatch) {
                if ($candidateBatch === null) {
                    continue;
                }

                $activityAllocation = $findActivityAllocation($subject, $type, $candidateBatch);
                $legacyAllocation = $roomAllocations->first(fn (RoomAllocation $allocation): bool => $allocation->subject_id === $subject->id
                    && $allocation->activity_type === null
                    && ($allocation->allocation_type === $allocationType || $allocation->allocation_type === null)
                    && $allocation->division === $candidateBatch);
                $candidateAllocation = $activityAllocation?->classroom_id
                    ? $activityAllocation
                    : $legacyAllocation;

                if (! $candidateAllocation) {
                    continue;
                }

                $fallbackAllocation ??= $candidateAllocation;
                $room = $candidateAllocation->classroom;
                $isSuitableType = $room && ($type === 'Practical'
                    ? str_contains(strtolower((string) $room->room_type), 'lab')
                    : ! str_contains(strtolower((string) $room->room_type), 'lab'));
                $hasCapacity = $room
                    && (int) $room->room_capacity >= (int) ($candidateAllocation->student_count ?? 0);

                if ($room?->availability === 'Available' && $isSuitableType && $hasCapacity) {
                    return $candidateAllocation;
                }
            }

            if ($fallbackAllocation) {
                return $fallbackAllocation;
            }

            $activityAllocation = $findActivityAllocation($subject, $type, $batch);
            if ($activityAllocation?->classroom_id) {
                return $activityAllocation;
            }

            return $findLegacyAllocation($subject, $type, $batch);
        };
        $getTheoryFaculty = function (Subject $subject) use ($findActivityAllocation, $findLegacyAllocation, $allFaculty): ?Faculty {
            $allocation = $findActivityAllocation($subject, 'Lecture', null)
                ?? $findLegacyAllocation($subject, 'Lecture');

            return $subject->faculty
                ?? $allocation?->faculty
                ?? $allFaculty->first(fn (Faculty $faculty): bool => filled($subject->faculty_name)
                    && mb_stripos($faculty->name, (string) $subject->faculty_name) !== false);
        };
        $getActivityFaculty = function (Subject $subject, string $type, ?string $batch, ?Faculty $theoryFaculty) use (
            $findActivityAllocation,
            $findLegacyAllocation,
            $allFaculty
        ): ?Faculty {
            if (($type === 'Practical' && $batch === 'A1') || ($type === 'Tutorial' && $batch === 'AT1')) {
                return $theoryFaculty;
            }

            $allocation = $findActivityAllocation($subject, $type, $batch);
            if ($allocation) {
                return $allocation->faculty;
            }

            $legacyAllocation = $findLegacyAllocation($subject, $type, $batch);
            if ($legacyAllocation?->faculty) {
                return $legacyAllocation->faculty;
            }

            if ($batch === null || $type === 'Lecture') {
                return $theoryFaculty;
            }

            return $allFaculty->first(fn (Faculty $faculty): bool => $faculty->id === $subject->faculty_id);
        };

        // Create Blocks
        $blocks = collect();
        $totalHours = 0;

        foreach ($subjects as $subject) {
            $theoryFaculty = $getTheoryFaculty($subject);

            $lectureCount = $subject->lectureCreditsForClass($division);
            $labBatches = collect($subject->division_selections ?? [])
                ->filter(fn (string $batch): bool => str_starts_with($batch, $division))
                ->values();
            $tutorialBatches = collect($subject->tutorial_selections ?? [])
                ->filter(fn (string $batch): bool => str_starts_with($batch, $division))
                ->values();

            // Fallback for older data without detailed credits
            if ($lectureCount === 0 && $labBatches->isEmpty() && $tutorialBatches->isEmpty() && ($subject->credit ?? 0) > 0) {
                $isPracticalSubject = stripos($subject->subject_type ?? '', 'practical') !== false || stripos($subject->subject_type ?? '', 'lab') !== false;
                if ($isPracticalSubject) {
                    for ($i = 0; $i < (int) $subject->credit; $i++) {
                        $blocks->push((object) [
                            'type' => 'Practical',
                            'batch' => null,
                            'duration' => 2,
                            'subject' => $subject,
                            'faculty' => $getActivityFaculty($subject, 'Practical', null, $theoryFaculty),
                            'allocation' => $findRoomAllocation($subject, 'Practical', null),
                        ]);
                        $totalHours += 2;
                    }
                } elseif (stripos($subject->subject_type ?? '', 'tutorial') !== false) {
                    for ($i = 0; $i < (int) $subject->credit; $i++) {
                        $blocks->push((object) [
                            'type' => 'Tutorial',
                            'batch' => null,
                            'duration' => 1,
                            'subject' => $subject,
                            'faculty' => $getActivityFaculty($subject, 'Tutorial', null, $theoryFaculty),
                            'allocation' => $findActivityAllocation($subject, 'Tutorial', null)
                                ?? $findLegacyAllocation($subject, 'Tutorial'),
                        ]);
                        $totalHours++;
                    }
                } else {
                    $lectureCount = (int) $subject->credit;
                }
            }

            for ($i = 0; $i < $lectureCount; $i++) {
                $blocks->push((object) [
                    'type' => 'Lecture',
                    'batch' => null,
                    'duration' => 1,
                    'subject' => $subject,
                    'faculty' => $theoryFaculty,
                    'allocation' => $findRoomAllocation($subject, 'Lecture', null),
                ]);
                $totalHours += 1;
            }

            foreach ($labBatches as $labBatch) {
                for ($i = 0; $i < (int) ($subject->lab_credit ?? 0); $i++) {
                    $blocks->push((object) [
                        'type' => 'Practical',
                        'batch' => $labBatch,
                        'duration' => 2,
                        'subject' => $subject,
                        'faculty' => $getActivityFaculty($subject, 'Practical', $labBatch, $theoryFaculty),
                        'allocation' => $findRoomAllocation($subject, 'Practical', $labBatch),
                    ]);
                    $totalHours += 2;
                }
            }

            foreach ($tutorialBatches as $tutorialBatch) {
                for ($i = 0; $i < (int) ($subject->tutorial_credit ?? 0); $i++) {
                    $blocks->push((object) [
                        'type' => 'Tutorial',
                        'batch' => $tutorialBatch,
                        'duration' => 1,
                        'subject' => $subject,
                        'faculty' => $getActivityFaculty($subject, 'Tutorial', $tutorialBatch, $theoryFaculty),
                        'allocation' => $findRoomAllocation($subject, 'Tutorial', $tutorialBatch),
                    ]);
                    $totalHours++;
                }
            }
        }

        $unassignedBlock = $blocks->first(fn ($block): bool => ! $block->faculty);
        if ($unassignedBlock) {
            $batchLabel = $unassignedBlock->batch ? " {$unassignedBlock->batch}" : '';
            throw new Exception(
                "No faculty is assigned to {$unassignedBlock->type}{$batchLabel} for {$unassignedBlock->subject->name}. Resolve the faculty allocation before generating the timetable."
            );
        }

        if ($totalHours > 36) {
            throw new Exception("Error: The subjects require {$totalHours} weekly hours, but a 6-day schedule only supports 36 hours. Please reduce subject credits.");
        }

        // Backtracking / Constraint Satisfaction Loop
        $maxAttempts = 500;
        $bestSchedule = [];
        $bestPlacedCount = -1;
        $failedBlock = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Track next available slot index per day to GUARANTEE top-to-bottom filling with zero gaps
            $nextSlot = ['Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0];

            $subjectOnDay = [];
            $facultyOccupiedLocal = $facultyOccupiedGlobal;
            $roomOccupiedLocal = $roomOccupiedGlobal;
            $pairedPracticalRooms = [];

            $localAssignments = [];
            $placedAll = true;

            // Important: Place 2-hour Practicals first so they don't get blocked by 1-hour gaps near breaks
            $practicals = $blocks->where('type', 'Practical')->shuffle();
            $theories = $blocks->whereIn('type', ['Lecture', 'Tutorial'])->shuffle();
            $orderedBlocks = $practicals->merge($theories);

            foreach ($orderedBlocks as $block) {
                $validPlacements = [];
                $practicalPair = $block->type === 'Practical' && $block->batch !== null
                    ? $this->pairedPracticalBatches($block->batch)
                    : null;
                $practicalPairKey = $practicalPair
                    ? $block->subject->id.'|'.implode('|', $practicalPair)
                    : null;
                $pairedRoomId = $practicalPairKey
                    ? ($pairedPracticalRooms[$practicalPairKey] ?? null)
                    : null;

                foreach ($this->days as $day) {
                    $ns = $nextSlot[$day];

                    // Capacity Check
                    if ($ns + $block->duration > 6) {
                        continue;
                    }

                    // Lab specific index alignment check (cannot span across breaks)
                    // Valid Lab starts: 0 (10:30), 2 (01:00), 4 (03:10)
                    if ($block->duration == 2 && ! in_array($ns, [0, 2, 4])) {
                        continue;
                    }

                    // Rule 1 & 2 Check: Lecture/Tutorial & Lab of same subject MUST be on DIFFERENT days
                    if (in_array($block->type, ['Lecture', 'Tutorial']) && ! empty($subjectOnDay[$block->subject->name][$day]['Practical'])) {
                        continue;
                    }
                    if ($block->type == 'Practical' && (! empty($subjectOnDay[$block->subject->name][$day]['Lecture']) || ! empty($subjectOnDay[$block->subject->name][$day]['Tutorial']))) {
                        continue;
                    }

                    $slots = [];
                    for ($i = 0; $i < $block->duration; $i++) {
                        $slots[] = $this->timeSlots[$ns + $i];
                    }

                    $facultyId = $block->faculty ? $block->faculty->id : null;

                    // Check Faculty Conflict
                    $facConflict = false;
                    if ($facultyId) {
                        foreach ($slots as $s) {
                            if (! empty($facultyOccupiedLocal[$facultyId][$day][$s])) {
                                $facConflict = true;
                                break;
                            }
                        }
                    }
                    if ($facConflict) {
                        continue;
                    }

                    // Find Available Room
                    $availableRoomId = null;
                    $availableSecondRoomId = null;
                    $secondRoomNotes = null;

                    // Check if there is a pre-allocated room
                    $alloc = $block->allocation;
                    $hasExplicitAllocation = $alloc !== null;
                    if (! $alloc) {
                        $allocationType = $block->type === 'Practical' ? 'Lab' : 'Classroom';
                        $alloc = $roomAllocations->first(fn (RoomAllocation $candidate): bool => $candidate->subject_id === $block->subject->id
                            && $candidate->activity_type === null
                            && $candidate->allocation_type === $allocationType
                            && $candidate->division === $block->batch
                            && $candidate->classroom_id !== null);
                        if (! $alloc && $block->batch === null) {
                            $alloc = $roomAllocations->first(fn (RoomAllocation $candidate): bool => $candidate->subject_id === $block->subject->id
                                && $candidate->activity_type === null
                                && $candidate->allocation_type === $allocationType
                                && $candidate->classroom_id !== null);
                        }
                        $hasExplicitAllocation = $hasExplicitAllocation || $alloc !== null;
                    }
                    if ($alloc && $alloc->classroom_id) {
                        $allocatedRoom = $alloc->classroom;
                        $roomTypeIsSuitable = $allocatedRoom
                            && ($block->type === 'Practical'
                                ? str_contains(strtolower((string) $allocatedRoom->room_type), 'lab')
                                : ! str_contains(strtolower((string) $allocatedRoom->room_type), 'lab'));
                        $roomHasCapacity = $allocatedRoom
                            && (int) $allocatedRoom->room_capacity >= (int) ($alloc->student_count ?? 0);
                        $roomIsAvailable = $allocatedRoom?->availability === 'Available';
                        $allocatedSecondRoom = $alloc->secondClassroom;
                        $secondRoomIsSuitable = ! $alloc->second_classroom_id
                            || ($allocatedSecondRoom
                                && $allocatedSecondRoom->availability === 'Available'
                                && str_contains(strtolower((string) $allocatedSecondRoom->room_type), 'lab')
                                && (int) $allocatedSecondRoom->room_capacity >= (int) ($alloc->student_count ?? 0));
                        $roomConflict = false;
                        foreach ($slots as $s) {
                            if (! empty($roomOccupiedLocal[$alloc->classroom_id][$day][$s])) {
                                $roomConflict = true;
                                break;
                            }
                            if ($alloc->second_classroom_id && ! empty($roomOccupiedLocal[$alloc->second_classroom_id][$day][$s])) {
                                $roomConflict = true;
                                break;
                            }
                        }
                        if (! $roomConflict && $roomTypeIsSuitable && $roomHasCapacity && $roomIsAvailable && $secondRoomIsSuitable) {
                            $availableRoomId = $alloc->classroom_id;
                            if ($allocatedSecondRoom) {
                                $availableSecondRoomId = $alloc->second_classroom_id;
                                $secondRoomNotes = 'Room2:'.$allocatedSecondRoom->room_number;
                            }
                        }
                    }

                    if ($pairedRoomId !== null) {
                        $pairedRoom = $classrooms->firstWhere('id', $pairedRoomId);
                        $pairedRoomIsAvailable = $pairedRoom
                            && str_contains(strtolower((string) $pairedRoom->room_type), 'lab')
                            && (int) $pairedRoom->room_capacity >= (int) ($alloc?->student_count ?? 0);
                        $pairedRoomConflict = false;
                        foreach ($slots as $s) {
                            if (! empty($roomOccupiedLocal[$pairedRoomId][$day][$s])) {
                                $pairedRoomConflict = true;
                                break;
                            }
                        }

                        $availableRoomId = $pairedRoomIsAvailable && ! $pairedRoomConflict
                            ? $pairedRoomId
                            : null;
                        $availableSecondRoomId = null;
                        $secondRoomNotes = null;
                    }

                    if (! $availableRoomId && ! $hasExplicitAllocation && $pairedRoomId === null) {
                        $roomsList = ($block->type == 'Practical') ? $labRooms : $lectureRooms;
                        foreach ($roomsList->shuffle() as $room) {
                            $roomConflict = false;
                            foreach ($slots as $s) {
                                if (! empty($roomOccupiedLocal[$room->id][$day][$s])) {
                                    $roomConflict = true;
                                    break;
                                }
                            }
                            if (! $roomConflict) {
                                $availableRoomId = $room->id;
                                break;
                            }
                        }
                        if (! $availableRoomId) {
                            continue;
                        }
                    }
                    if ($pairedRoomId !== null && ! $availableRoomId) {
                        continue;
                    }
                    if ($hasExplicitAllocation && ! $availableRoomId) {
                        continue;
                    }

                    // Calculate Penalty to pick the most balanced day
                    $penalty = rand(0, 5); // Add stochasticity to enable backtracking exploration

                    $penalty += $ns * 10; // Favor emptier days

                    // Heavy penalty to avoid repeating same subject multiple times on same day
                    if (! empty($subjectOnDay[$block->subject->name][$day])) {
                        $penalty += 500;
                    }

                    // Rule 8: Balance evenly, avoid consecutive days if possible
                    $dayIdx = array_search($day, $this->days);
                    if ($dayIdx > 0 && ! empty($subjectOnDay[$block->subject->name][$this->days[$dayIdx - 1]])) {
                        $penalty += 50;
                    }
                    if ($dayIdx < 5 && ! empty($subjectOnDay[$block->subject->name][$this->days[$dayIdx + 1]])) {
                        $penalty += 50;
                    }

                    $validPlacements[] = [
                        'day' => $day,
                        'ns' => $ns,
                        'slots' => $slots,
                        'room_id' => $availableRoomId,
                        'second_room_id' => $availableSecondRoomId,
                        'notes' => $secondRoomNotes,
                        'penalty' => $penalty,
                    ];
                }

                if (empty($validPlacements)) {
                    $placedAll = false;
                    $failedBlock = $block;
                    break; // Move to next attempt if this attempt hit a dead-end
                }

                // Sort by penalty and pick the best placement
                usort($validPlacements, fn ($a, $b) => $a['penalty'] <=> $b['penalty']);
                $best = $validPlacements[0];

                $day = $best['day'];
                $slots = $best['slots'];
                $roomId = $best['room_id'];
                $secondRoomId = $best['second_room_id'];
                $notes = $best['notes'];
                $facultyId = $block->faculty ? $block->faculty->id : null;

                if ($practicalPairKey && $roomId !== null) {
                    $pairedPracticalRooms[$practicalPairKey] = $roomId;
                }

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
                    'activity_batch' => $block->batch,
                    'duration' => $block->duration,
                    'notes' => $notes,
                ];

                // Update constraints
                $nextSlot[$day] += $block->duration;
                $subjectOnDay[$block->subject->name][$day][$block->type] = true;

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
                if ($secondRoomId) {
                    foreach ($slots as $s) {
                        $roomOccupiedLocal[$secondRoomId][$day][$s] = true;
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

        $scheduledCounts = collect($bestSchedule)
            ->countBy(fn (array $assignment): string => $assignment['subject_id'].'|'.$assignment['lecture_type'].'|'.($assignment['activity_batch'] ?? ''))
            ->all();
        $missingBlocks = collect();
        foreach ($blocks as $block) {
            $key = $block->subject->id.'|'.$block->type.'|'.($block->batch ?? '');
            if (($scheduledCounts[$key] ?? 0) > 0) {
                $scheduledCounts[$key]--;
            } else {
                $missingBlocks->push($block);
            }
        }
        $failedBlock = $missingBlocks->first(
            fn ($block): bool => ($block->type === 'Practical' && $block->batch === 'A1')
                || ($block->type === 'Tutorial' && $block->batch === 'AT1')
        ) ?? $missingBlocks->first() ?? $failedBlock;

        if ($bestPlacedCount < $blocks->count()) {
            $missing = $missingBlocks->count();
            $blockLabel = $failedBlock
                ? "{$failedBlock->type}".($failedBlock->batch ? " {$failedBlock->batch}" : '')." for {$failedBlock->subject->name}"
                : 'one or more sessions';
            $mandatoryNotice = $failedBlock
                && (($failedBlock->type === 'Practical' && $failedBlock->batch === 'A1')
                    || ($failedBlock->type === 'Tutorial' && $failedBlock->batch === 'AT1'))
                ? ' The mandatory theory faculty remains assigned; resolve their timetable conflict rather than replacing them.'
                : '';

            throw new Exception(
                "Could not schedule {$blockLabel}; {$missing} of {$blocks->count()} sessions could not be placed because of faculty or room conflicts.{$mandatoryNotice}"
            );
        }

        DB::transaction(function () use ($deptId, $semester, $division, $academicYear, $term, $bestSchedule) {
            // Delete existing entries for this class before saving new ones
            TimetableEntry::where([
                'department_id' => $deptId,
                'semester' => $semester,
                'division' => $division,
                'academic_year' => $academicYear,
                'term' => $term,
            ])->delete();

            // Save the best timetable we found
            foreach ($bestSchedule as $a) {
                TimetableEntry::create($a);
            }
        });

        Notification::trigger('Timetable Generated', [
            'department_name' => $department->name,
            'semester' => $semester,
            'academic_year' => $academicYear,
        ]);

        return true;
    }

    private function timeInMinutes(string $time): ?int
    {
        if (preg_match('/^(\\d{1,2}):(\\d{2})$/', $time, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($minute > 59 || $hour > 23) {
            return null;
        }

        if ($hour >= 1 && $hour <= 4) {
            $hour += 12;
        } elseif ($hour >= 13 && $hour <= 16) {
            $hour -= 12;
        }

        return $hour * 60 + $minute;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function pairedPracticalBatches(string $batch): ?array
    {
        if (preg_match('/^(.*?)([1-4])$/', $batch, $matches) !== 1) {
            return null;
        }

        $pairStart = (int) $matches[2] <= 2 ? 1 : 3;

        return [
            $matches[1].$pairStart,
            $matches[1].($pairStart + 1),
        ];
    }
}
