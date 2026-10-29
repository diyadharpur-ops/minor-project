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
use Illuminate\Support\Collection;
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
        return DB::transaction(function () use (
            $deptId,
            $semester,
            $division,
            $academicYear,
            $term,
            $allocateFaculty
        ): bool {
            DB::table('faculties')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            DB::table('classrooms')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            DB::table('room_allocations')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            DB::table('timetable_entries')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            return $this->generateWithinTransaction(
                $deptId,
                $semester,
                $division,
                $academicYear,
                $term,
                $allocateFaculty
            );
        }, 3);
    }

    private function generateWithinTransaction(
        int|string $deptId,
        string $semester,
        string $division,
        string $academicYear,
        string $term,
        bool $allocateFaculty
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

        $className = $department->name.'-'.$semester.'-'.$division;

        $existingEntries = TimetableEntry::whereNot(function ($q) use ($deptId, $semester, $division) {
            $q->where('department_id', $deptId)
                ->where('semester', $semester)
                ->where('division', $division);
        })->where('academic_year', $academicYear)
            ->where('term', $term)
            ->with(['subject', 'faculty', 'classroom'])
            ->get();

        $scheduledAllocations = RoomAllocation::query()
            ->whereNotNull('day')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where(function ($query) use ($academicYear): void {
                $query->whereNull('academic_year')
                    ->orWhere('academic_year', $academicYear);
            })
            ->where(function ($query) use ($term): void {
                $query->whereNull('term')
                    ->orWhere('term', $term);
            })
            ->with(['subject', 'faculty', 'classroom', 'secondClassroom'])
            ->get();

        $roomAllocations = RoomAllocation::with(['faculty', 'classroom', 'secondClassroom'])
            ->where('department_id', $deptId)
            ->where('semester', $semester)
            ->where(function ($query) use ($academicYear): void {
                $query->whereNull('academic_year')
                    ->orWhere('academic_year', $academicYear);
            })
            ->where(function ($query) use ($term): void {
                $query->whereNull('term')
                    ->orWhere('term', $term);
            })
            ->where(function ($query) use ($className, $division): void {
                $query->where('class_name', $className)
                    ->orWhere('class_name', $division)
                    ->orWhere(function ($batchQuery) use ($division): void {
                        $batchQuery->where('division', 'like', $division.'%')
                            ->orWhere('activity_batch', 'like', $division.'%');
                    });
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
            $allocationForBatch = function (string $selectedBatch) use (
                $subject,
                $type,
                $allocationType,
                $findActivityAllocation,
                $roomAllocations
            ): ?RoomAllocation {
                $activityAllocation = $findActivityAllocation($subject, $type, $selectedBatch);
                $legacyAllocation = $roomAllocations->first(fn (RoomAllocation $allocation): bool => $allocation->subject_id === $subject->id
                    && $allocation->activity_type === null
                    && ($allocation->allocation_type === $allocationType || $allocation->allocation_type === null)
                    && $allocation->division === $selectedBatch);

                return $activityAllocation?->classroom_id
                    ? $activityAllocation
                    : ($legacyAllocation ?? $activityAllocation);
            };

            if ($type === 'Tutorial' && $batch !== null) {
                $tutorialPair = $this->pairedTutorialBatches($batch);
                $tutorialSelections = $subject->tutorial_selections ?? [];
                if ($tutorialPair !== null
                    && in_array($tutorialPair[0], $tutorialSelections, true)
                    && in_array($tutorialPair[1], $tutorialSelections, true)) {
                    $ownAllocation = $allocationForBatch($batch);
                    $partnerBatch = $tutorialPair[0] === $batch ? $tutorialPair[1] : $tutorialPair[0];
                    $partnerAllocation = $allocationForBatch($partnerBatch);

                    if ($ownAllocation?->classroom_id) {
                        return $ownAllocation;
                    }

                    if ($partnerAllocation?->classroom_id) {
                        $sharedAllocation = $ownAllocation ? clone $ownAllocation : clone $partnerAllocation;
                        if (! $ownAllocation) {
                            $sharedAllocation->setAttribute('day', null);
                            $sharedAllocation->setAttribute('start_time', null);
                            $sharedAllocation->setAttribute('end_time', null);
                        }
                        $sharedAllocation->setAttribute('classroom_id', $partnerAllocation->classroom_id);
                        $sharedAllocation->setRelation('classroom', $partnerAllocation->classroom);
                        $sharedAllocation->setAttribute(
                            'student_count',
                            $ownAllocation?->student_count ?? $partnerAllocation->student_count
                        );

                        return $sharedAllocation;
                    }

                }
            }

            $batchCandidates = [$batch];

            if ($type === 'Practical' && $batch !== null) {
                $pair = $this->pairedPracticalBatches($batch);
                if ($pair !== null) {
                    $batchCandidates = $pair;
                }
            } elseif ($type === 'Tutorial' && $batch !== null) {
                $pair = $this->pairedTutorialBatches($batch);
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
            $legacyAllocation = $findLegacyAllocation($subject, $type, $batch);
            if ($activityAllocation?->classroom_id) {
                return $activityAllocation;
            }

            return $legacyAllocation?->classroom_id
                ? $legacyAllocation
                : ($activityAllocation ?? $legacyAllocation);
        };
        $getTheoryFaculty = function (Subject $subject) use ($findActivityAllocation, $findLegacyAllocation): ?Faculty {
            $allocation = $findActivityAllocation($subject, 'Lecture', null)
                ?? $findLegacyAllocation($subject, 'Lecture');

            return $allocation?->faculty;
        };
        $getActivityFaculty = function (Subject $subject, string $type, ?string $batch, ?Faculty $theoryFaculty) use (
            $findActivityAllocation,
            $findLegacyAllocation
        ): ?Faculty {
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

            return null;
        };

        // Create Blocks
        $blocks = collect();

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
                    }
                } elseif (stripos($subject->subject_type ?? '', 'project') !== false) {
                    $projectType = stripos($subject->subject_type ?? '', 'minor') !== false ? 'Minor Project' : 'Project';
                    for ($i = 0; $i < (int) $subject->credit; $i++) {
                        $blocks->push((object) [
                            'type' => $projectType,
                            'batch' => null,
                            'duration' => 1,
                            'subject' => $subject,
                            'faculty' => $getActivityFaculty($subject, $projectType, null, $theoryFaculty),
                            'allocation' => $findRoomAllocation($subject, $projectType, null),
                        ]);
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
            }

            foreach ($labBatches as $labBatch) {
                for ($i = 0; $i < (int) ($subject->lab_credit ?? 0); $i++) {
                    $blocks->push((object) [
                        'type' => 'Practical',
                        'batch' => $labBatch,
                        'duration' => 2,
                        'schedule_group' => 'practical:'.$subject->id.':'.$i,
                        'room_share_group' => $this->pairedBatchGroupKey($subject, 'Practical', $labBatch, $i),
                        'subject' => $subject,
                        'faculty' => $getActivityFaculty($subject, 'Practical', $labBatch, $theoryFaculty),
                        'allocation' => $findRoomAllocation($subject, 'Practical', $labBatch),
                    ]);
                }
            }

            foreach ($tutorialBatches as $tutorialBatch) {
                for ($i = 0; $i < (int) ($subject->tutorial_credit ?? 0); $i++) {
                    $tutorialSessionGroup = 'tutorial:'.$subject->id.':'.$i;

                    $blocks->push((object) [
                        'type' => 'Tutorial',
                        'batch' => $tutorialBatch,
                        'duration' => 1,
                        'schedule_group' => $tutorialSessionGroup,
                        'room_share_group' => $tutorialSessionGroup,
                        'subject' => $subject,
                        'faculty' => $getActivityFaculty($subject, 'Tutorial', $tutorialBatch, $theoryFaculty),
                        'allocation' => $findRoomAllocation($subject, 'Tutorial', $tutorialBatch),
                    ]);
                }
            }
        }

        $unallocatedBlock = $blocks->first(fn ($block): bool => $block->type === 'Practical'
            && $block->allocation?->status === 'Unallocated');
        if ($unallocatedBlock) {
            $batchLabel = $unallocatedBlock->batch ? " {$unallocatedBlock->batch}" : '';
            $reason = filled($unallocatedBlock->allocation->notes)
                ? (string) $unallocatedBlock->allocation->notes
                : 'No suitable laboratory has been allocated.';

            throw new Exception(
                "Cannot generate Practical{$batchLabel} for {$unallocatedBlock->subject->name}: {$reason}"
            );
        }

        $unassignedBlock = $blocks->first(fn ($block): bool => ! $block->faculty);
        if ($unassignedBlock) {
            $batchLabel = $unassignedBlock->batch ? " {$unassignedBlock->batch}" : '';
            throw new Exception(
                "No faculty is assigned to {$unassignedBlock->type}{$batchLabel} for {$unassignedBlock->subject->name}. Resolve the faculty allocation before generating the timetable."
            );
        }

        foreach ($blocks as $index => $block) {
            $block->id = $index;
        }
        $blockAllocationIds = $blocks
            ->pluck('allocation')
            ->filter()
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $result = $this->findConflictFreeSchedule(
            $blocks->values()->all(),
            $classrooms,
            $existingEntries,
            $scheduledAllocations,
            $blockAllocationIds,
            $deptId,
            $semester,
            $division,
            $academicYear,
            $term,
            $className
        );
        if ($result['schedule'] === null) {
            throw new Exception($result['error'] ?? 'Timetable generation failed without a conflict report.');
        }

        TimetableEntry::query()
            ->where([
                'department_id' => $deptId,
                'semester' => $semester,
                'division' => $division,
                'academic_year' => $academicYear,
                'term' => $term,
            ])
            ->delete();
        foreach ($result['schedule'] as $assignment) {
            TimetableEntry::create($assignment);
        }

        Notification::trigger('Timetable Generated', [
            'department_name' => $department->name,
            'semester' => $semester,
            'academic_year' => $academicYear,
        ]);

        return true;
    }

    /**
     * @param  array<int, object>  $blocks
     * @param  array<int, int|string>  $blockAllocationIds
     * @return array{schedule: array<int, array<string, mixed>>|null, error: string|null}
     */
    private function findConflictFreeSchedule(
        array $blocks,
        Collection $classrooms,
        Collection $existingEntries,
        Collection $scheduledAllocations,
        array $blockAllocationIds,
        int|string $deptId,
        string $semester,
        string $division,
        string $academicYear,
        string $term,
        string $className
    ): array {
        $roomIdByNumber = Classroom::query()->pluck('id', 'room_number');
        $state = [
            'faculty' => [],
            'rooms' => [],
            'room_share_groups' => [],
            'division' => [],
            'batches' => [],
            'schedule_groups' => [],
            'subject_day' => [],
            'assignments' => [],
        ];
        $addOccupant = static function (array &$occupancy, int|string $resourceId, string $day, string $slot, string $label): void {
            $occupancy[$resourceId][$day][$slot][] = $label;
        };
        $addRoomOccupant = static function (
            array &$state,
            int|string $roomId,
            string $day,
            string $slot,
            string $label,
            ?string $shareGroup = null
        ): void {
            $state['rooms'][$roomId][$day][$slot][] = $label;
            $state['room_share_groups'][$roomId][$day][$slot][] = $shareGroup;
        };
        $addTargetClassOccupant = static function (array &$state, ?string $batch, string $day, string $slot, string $label) use ($division): void {
            if ($batch === null || $batch === '' || $batch === $division || ! str_starts_with($batch, $division)) {
                $state['division'][$day][$slot][] = $label;

                return;
            }

            $state['batches'][$batch][$day][$slot][] = $label;
        };

        foreach ($existingEntries as $entry) {
            $slotIndex = array_search($entry->time_slot, $this->timeSlots, true);
            if ($slotIndex === false) {
                continue;
            }

            $label = $this->sessionDescription(
                $entry->subject?->name,
                $entry->subject?->subject_code,
                $entry->lecture_type,
                $entry->activity_batch,
                $entry->faculty?->name,
                $entry->classroom?->room_number,
                'existing timetable'
            )." on {$entry->day} at {$entry->time_slot}";
            $slots = [$entry->time_slot];
            if ((int) $entry->duration === 2 && isset($this->timeSlots[$slotIndex + 1])) {
                $slots[] = $this->timeSlots[$slotIndex + 1];
            }

            foreach ($slots as $slot) {
                if ($entry->faculty_id) {
                    $addOccupant($state['faculty'], $entry->faculty_id, $entry->day, $slot, $label);
                }
                if ($entry->classroom_id) {
                    $addRoomOccupant($state, $entry->classroom_id, $entry->day, $slot, $label);
                }
                if ($entry->notes && str_starts_with($entry->notes, 'Room2:')) {
                    $secondRoomId = $roomIdByNumber->get(substr($entry->notes, 6));
                    if ($secondRoomId) {
                        $addRoomOccupant($state, $secondRoomId, $entry->day, $slot, $label);
                    }
                }
            }
        }

        foreach ($scheduledAllocations as $allocation) {
            if (in_array($allocation->id, $blockAllocationIds, true)) {
                continue;
            }

            $allocationStart = $this->timeInMinutes((string) $allocation->start_time);
            $allocationEnd = $this->timeInMinutes((string) $allocation->end_time);
            if ($allocationStart === null || $allocationEnd === null || $allocationStart >= $allocationEnd) {
                continue;
            }

            $label = $this->sessionDescription(
                $allocation->subject?->name,
                $allocation->subject?->subject_code,
                $allocation->activity_type ?? $allocation->allocation_type,
                $allocation->activity_batch ?? $allocation->division,
                $allocation->faculty?->name,
                $allocation->classroom?->room_number,
                'fixed room allocation'
            )." on {$allocation->day} from {$allocation->start_time} to {$allocation->end_time}";
            $isTargetClass = (string) $allocation->department_id === (string) $deptId
                && (string) $allocation->semester === $semester
                && in_array($allocation->class_name, [$division, $className], true);

            foreach ($this->timeSlots as $slot) {
                [$slotStart, $slotEnd] = explode('-', $slot);
                $slotStartMinute = $this->timeInMinutes($slotStart);
                $slotEndMinute = $this->timeInMinutes($slotEnd);
                if ($slotStartMinute === null || $slotEndMinute === null
                    || $allocationStart >= $slotEndMinute
                    || $allocationEnd <= $slotStartMinute) {
                    continue;
                }

                if ($allocation->faculty_id) {
                    $addOccupant($state['faculty'], $allocation->faculty_id, $allocation->day, $slot, $label);
                }
                foreach ([$allocation->classroom_id, $allocation->second_classroom_id] as $roomId) {
                    if ($roomId) {
                        $addRoomOccupant($state, $roomId, $allocation->day, $slot, $label);
                    }
                }
                if ($isTargetClass) {
                    $batch = $allocation->activity_batch ?: $allocation->division;
                    $addTargetClassOccupant($state, $batch, $allocation->day, $slot, $label);
                }
            }
        }

        $roomOptions = [];
        $roomErrors = [];
        $fixedTimes = [];
        foreach ($blocks as $block) {
            [$roomOptions[$block->id], $roomErrors[$block->id]] = $this->roomOptionsForBlock($block, $classrooms);
            $fixedTimes[$block->id] = $this->fixedTimeForBlock($block);
        }

        $bestAssignments = [];
        $bestState = $state;
        $failureReasons = [];
        $recordFailure = static function (int $blockId, array $issues) use (&$failureReasons): void {
            foreach ($issues as $day => $slots) {
                foreach ($slots as $slot => $reasons) {
                    foreach ($reasons as $reason) {
                        $failureReasons[$blockId][$day][$slot][$reason] = $reason;
                    }
                }
            }
        };

        $search = function (array $remaining, array $currentState) use (
            &$search,
            &$bestAssignments,
            &$bestState,
            $recordFailure,
            $roomOptions,
            $roomErrors,
            $fixedTimes,
            $division,
            $deptId,
            $semester,
            $addRoomOccupant
        ): ?array {
            if (count($currentState['assignments']) > count($bestAssignments)) {
                $bestAssignments = $currentState['assignments'];
                $bestState = $currentState;
            }
            if ($remaining === []) {
                return $currentState['assignments'];
            }

            $selectedBlock = null;
            $selectedPlacements = [];
            $fewestPlacements = PHP_INT_MAX;
            foreach ($remaining as $block) {
                [$placements, $issues] = $this->placementsForBlock(
                    $block,
                    $currentState,
                    $roomOptions[$block->id],
                    $roomErrors[$block->id],
                    $fixedTimes[$block->id],
                    $division
                );
                if ($placements === []) {
                    $recordFailure($block->id, $issues);
                }
                if (count($placements) < $fewestPlacements) {
                    $selectedBlock = $block;
                    $selectedPlacements = $placements;
                    $fewestPlacements = count($placements);
                }
                if ($fewestPlacements === 0) {
                    break;
                }
            }

            if ($selectedBlock === null || $selectedPlacements === []) {
                return null;
            }

            usort($selectedPlacements, static function (array $left, array $right): int {
                return [$left['penalty'], $left['room_id'], $left['day'], $left['start_index']]
                    <=> [$right['penalty'], $right['room_id'], $right['day'], $right['start_index']];
            });

            foreach ($selectedPlacements as $placement) {
                $nextState = $currentState;
                $block = $selectedBlock;
                $facultyId = $block->faculty->id;
                $label = $this->sessionDescription(
                    $block->subject->name,
                    $block->subject->subject_code,
                    $block->type,
                    $block->batch,
                    $block->faculty->name,
                    $placement['room_number'],
                    'scheduled session'
                )." on {$placement['day']} at {$placement['slots'][0]}";
                $entry = [
                    'department_id' => $deptId,
                    'semester' => $semester,
                    'division' => $division,
                    'academic_year' => $block->academic_year,
                    'term' => $block->term,
                    'day' => $placement['day'],
                    'time_slot' => $placement['slots'][0],
                    'subject_id' => $block->subject->id,
                    'faculty_id' => $facultyId,
                    'classroom_id' => $placement['room_id'],
                    'lecture_type' => $block->type,
                    'activity_batch' => $block->batch,
                    'duration' => $block->duration,
                    'notes' => $placement['notes'],
                ];
                $nextState['assignments'][$block->id] = $entry;
                $scheduleGroup = $block->schedule_group ?? null;
                if ($scheduleGroup !== null) {
                    $nextState['schedule_groups'][$scheduleGroup] = [
                        'day' => $placement['day'],
                        'start_index' => $placement['start_index'],
                    ];
                }
                foreach ($placement['slots'] as $slot) {
                    $nextState['faculty'][$facultyId][$placement['day']][$slot][] = $label;
                    $addRoomOccupant(
                        $nextState,
                        $placement['room_id'],
                        $placement['day'],
                        $slot,
                        $label,
                        $block->room_share_group ?? null
                    );
                    if ($placement['second_room_id']) {
                        $addRoomOccupant(
                            $nextState,
                            $placement['second_room_id'],
                            $placement['day'],
                            $slot,
                            $label,
                            $block->room_share_group ?? null
                        );
                    }
                    if ($block->batch === null) {
                        $nextState['division'][$placement['day']][$slot][] = $label;
                    } else {
                        $nextState['batches'][$block->batch][$placement['day']][$slot][] = $label;
                    }
                }
                $nextState['subject_day'][$block->subject->id][$placement['day']][$block->type] = $label;

                $nextRemaining = array_values(array_filter(
                    $remaining,
                    fn (object $remainingBlock): bool => $remainingBlock->id !== $block->id
                ));
                $result = $search($nextRemaining, $nextState);
                if ($result !== null) {
                    return $result;
                }
            }

            return null;
        };

        foreach ($blocks as $index => $block) {
            $block->id = $index;
            $block->academic_year = $academicYear;
            $block->term = $term;
        }

        $schedule = $search($blocks, $state);
        if ($schedule !== null) {
            return [
                'schedule' => array_values($schedule),
                'error' => null,
            ];
        }

        $missingBlocks = array_values(array_filter(
            $blocks,
            fn (object $block): bool => ! isset($bestAssignments[$block->id])
        ));
        $error = $this->formatSchedulingFailure(
            $missingBlocks,
            $failureReasons,
            $bestState,
            $roomOptions,
            $roomErrors,
            $fixedTimes,
            $division
        );

        return [
            'schedule' => null,
            'error' => $error,
        ];
    }

    /**
     * @return array{0: array<int, array{room_id: int, second_room_id: int|null, room_number: string, notes: string|null}>, 1: array<int, string>}
     */
    private function roomOptionsForBlock(object $block, Collection $classrooms): array
    {
        $allocation = $block->allocation;
        $isLab = $block->type === 'Practical';
        $capacity = (int) ($allocation?->student_count ?? 0);

        if ($allocation && ! $allocation->classroom_id) {
            if ($allocation->status === 'Unallocated') {
                $reason = filled($allocation->notes)
                    ? (string) $allocation->notes
                    : 'The room allocation is marked unallocated.';

                return [[], [$reason]];
            }

            return [[], ['No room is assigned to this activity in the allocation records.']];
        }

        if ($allocation) {
            $room = $allocation->classroom;
            if (! $room) {
                return [[], ['The configured classroom no longer exists.']];
            }
            if ($room->availability !== 'Available') {
                return [[], ["Required classroom {$room->room_number} is not available."]];
            }
            if (($isLab && ! str_contains(strtolower((string) $room->room_type), 'lab'))
                || (! $isLab && str_contains(strtolower((string) $room->room_type), 'lab'))) {
                return [[], ["Required classroom {$room->room_number} has the wrong room type."]];
            }
            if ((int) $room->room_capacity < $capacity) {
                return [[], ["Required classroom {$room->room_number} capacity ({$room->room_capacity}) is below the allocation size ({$capacity})."]];
            }

            $secondRoom = $allocation->secondClassroom;
            if ($allocation->second_classroom_id
                && (! $secondRoom
                    || $secondRoom->availability !== 'Available'
                    || ! str_contains(strtolower((string) $secondRoom->room_type), 'lab')
                    || (int) $secondRoom->room_capacity < $capacity)) {
                return [[], ['The configured second laboratory is unavailable or unsuitable.']];
            }

            return [[[
                'room_id' => (int) $room->id,
                'second_room_id' => $secondRoom ? (int) $secondRoom->id : null,
                'room_number' => (string) $room->room_number,
                'notes' => $secondRoom ? 'Room2:'.$secondRoom->room_number : null,
            ]], []];
        }

        return [[], ['No room allocation exists for this activity.']];
    }

    /**
     * @return array{day: string|null, start_index: int|null, error: string|null}|null
     */
    private function fixedTimeForBlock(object $block): ?array
    {
        $allocation = $block->allocation;
        if (! $allocation || (! filled($allocation->start_time) && ! filled($allocation->end_time))) {
            return null;
        }

        $day = (string) $allocation->day;
        $startMinute = $this->timeInMinutes((string) $allocation->start_time);
        $endMinute = $this->timeInMinutes((string) $allocation->end_time);
        if (! in_array($day, $this->days, true) || $startMinute === null || $endMinute === null) {
            return [
                'day' => null,
                'start_index' => null,
                'error' => 'The configured fixed day or time is invalid.',
            ];
        }

        $startIndex = null;
        foreach ($this->timeSlots as $index => $slot) {
            [$start] = explode('-', $slot);
            if ($this->timeInMinutes($start) === $startMinute) {
                $startIndex = $index;
                break;
            }
        }

        if ($startIndex === null || $startIndex + $block->duration > count($this->timeSlots)) {
            return [
                'day' => $day,
                'start_index' => null,
                'error' => 'The configured fixed time does not start in a valid timetable slot.',
            ];
        }

        if ($block->duration === 2 && ! in_array($startIndex, [0, 2, 4], true)) {
            return [
                'day' => $day,
                'start_index' => $startIndex,
                'error' => 'The configured two-hour session overlaps a recess period.',
            ];
        }

        [, $lastSlotEnd] = explode('-', $this->timeSlots[$startIndex + $block->duration - 1]);
        if ($this->timeInMinutes($lastSlotEnd) !== $endMinute) {
            return [
                'day' => $day,
                'start_index' => $startIndex,
                'error' => 'The configured fixed time does not match the required session duration.',
            ];
        }

        return [
            'day' => $day,
            'start_index' => $startIndex,
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<int, array{room_id: int, second_room_id: int|null, room_number: string, notes: string|null}>  $roomOptions
     * @param  array<int, string>  $roomErrors
     * @param  array{day: string|null, start_index: int|null, error: string|null}|null  $fixedTime
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array<string, array<int, string>>>}
     */
    private function placementsForBlock(
        object $block,
        array $state,
        array $roomOptions,
        array $roomErrors,
        ?array $fixedTime,
        string $division
    ): array {
        $placements = [];
        $issues = [];
        $startIndices = range(0, 6 - $block->duration);
        foreach ($this->days as $day) {
            foreach ($startIndices as $startIndex) {
                $startSlot = $this->timeSlots[$startIndex];
                $candidateLabel = $block->duration === 2
                    ? $startSlot.' + '.$this->timeSlots[$startIndex + 1]
                    : $startSlot;
                $slots = [];
                for ($offset = 0; $offset < $block->duration; $offset++) {
                    $slots[] = $this->timeSlots[$startIndex + $offset];
                }

                if ($block->duration === 2 && ! in_array($startIndex, [0, 2, 4], true)) {
                    $issues[$day][$candidateLabel][] = 'The two-hour practical would cross a recess period.';

                    continue;
                }

                $scheduleGroup = $block->schedule_group ?? null;
                $roomShareGroup = $block->room_share_group ?? null;
                $groupPlacement = $scheduleGroup !== null
                    ? ($state['schedule_groups'][$scheduleGroup] ?? null)
                    : null;
                if ($groupPlacement !== null
                    && ($groupPlacement['day'] !== $day || $groupPlacement['start_index'] !== $startIndex)) {
                    $issues[$day][$candidateLabel][] = 'This batch must be scheduled with its paired groups at the same time.';

                    continue;
                }

                $fixedTimeReasons = [];
                if ($fixedTime !== null) {
                    if ($fixedTime['error'] !== null) {
                        $fixedTimeReasons[] = $fixedTime['error'];
                    } elseif ($fixedTime['day'] !== $day || $fixedTime['start_index'] !== $startIndex) {
                        $fixedTimeReasons[] = "The allocation is fixed to {$fixedTime['day']} at {$this->timeSlots[$fixedTime['start_index']]}";
                    }
                }

                $roomPlacementFound = false;
                $rejectedReasons = $fixedTimeReasons;
                if ($roomOptions === []) {
                    $rejectedReasons = [...$rejectedReasons, ...$roomErrors];
                }

                foreach ($roomOptions as $roomOption) {
                    $reasons = $fixedTimeReasons;
                    $facultyId = $block->faculty->id;
                    foreach ($slots as $slot) {
                        foreach ($state['faculty'][$facultyId][$day][$slot] ?? [] as $occupant) {
                            $reasons[] = "Faculty conflict with {$occupant}.";
                        }

                        foreach ([$roomOption['room_id'], $roomOption['second_room_id']] as $roomId) {
                            if (! $roomId) {
                                continue;
                            }
                            foreach ($state['rooms'][$roomId][$day][$slot] ?? [] as $occupantIndex => $occupant) {
                                $occupantShareGroup = $state['room_share_groups'][$roomId][$day][$slot][$occupantIndex] ?? null;
                                // Paired batches sharing one lab/tutorial room form a single concurrent session.
                                if ($roomShareGroup !== null && $roomShareGroup === $occupantShareGroup) {
                                    continue;
                                }

                                $reasons[] = "Classroom conflict with {$occupant}.";
                            }
                        }

                        foreach ($state['division'][$day][$slot] ?? [] as $occupant) {
                            $reasons[] = "Division conflict with {$occupant}.";
                        }
                        if ($block->batch === null) {
                            foreach ($state['batches'] as $batchOccupancy) {
                                foreach ($batchOccupancy[$day][$slot] ?? [] as $occupant) {
                                    $reasons[] = "Division conflict with {$occupant}.";
                                }
                            }
                        } else {
                            foreach ($state['batches'][$block->batch][$day][$slot] ?? [] as $occupant) {
                                $reasons[] = "Batch conflict with {$occupant}.";
                            }
                        }
                    }

                    $subjectDay = $state['subject_day'][$block->subject->id][$day] ?? [];
                    if (in_array($block->type, ['Lecture', 'Tutorial'], true)
                        && isset($subjectDay['Practical'])) {
                        $reasons[] = "Subject/day rule conflict with {$subjectDay['Practical']}.";
                    }
                    if ($block->type === 'Practical') {
                        foreach (['Lecture', 'Tutorial'] as $otherType) {
                            if (isset($subjectDay[$otherType])) {
                                $reasons[] = "Subject/day rule conflict with {$subjectDay[$otherType]}.";
                            }
                        }
                    }

                    $reasons = array_values(array_unique($reasons));
                    if ($reasons !== []) {
                        $rejectedReasons = [...$rejectedReasons, ...$reasons];

                        continue;
                    }

                    $roomPlacementFound = true;
                    $dayLoad = count($state['division'][$day] ?? []);
                    foreach ($state['batches'] as $batchOccupancy) {
                        $dayLoad += count($batchOccupancy[$day] ?? []);
                    }
                    $placements[] = [
                        ...$roomOption,
                        'day' => $day,
                        'start_index' => $startIndex,
                        'slots' => $slots,
                        'penalty' => ($dayLoad * 20)
                            + ($startIndex * 2)
                            + (isset($subjectDay[$block->type]) ? 100 : 0),
                    ];
                }

                if (! $roomPlacementFound) {
                    $issues[$day][$candidateLabel] = array_values(array_unique(
                        $rejectedReasons !== [] ? $rejectedReasons : ['No valid classroom allocation is available.']
                    ));
                }
            }
        }

        return [$placements, $issues];
    }

    /**
     * @param  array<int, object>  $missingBlocks
     * @param  array<int, array<string, array<string, array<string, string>>>>  $failureReasons
     * @param  array<string, mixed>  $state
     * @param  array<int, array<int, array{room_id: int, second_room_id: int|null, room_number: string, notes: string|null}>>  $roomOptions
     * @param  array<int, array<int, string>>  $roomErrors
     * @param  array<int, array{day: string|null, start_index: int|null, error: string|null}|null>  $fixedTimes
     */
    private function formatSchedulingFailure(
        array $missingBlocks,
        array $failureReasons,
        array $state,
        array $roomOptions,
        array $roomErrors,
        array $fixedTimes,
        string $division
    ): string {
        $message = 'Timetable generation was rolled back because all required sessions could not be scheduled.';
        $message .= "\nUnscheduled sessions and rejected candidate slots:";

        foreach ($missingBlocks as $block) {
            $facultyName = (string) ($block->faculty?->name ?? 'Unassigned');
            $roomRequirement = $block->allocation?->classroom?->room_number
                ?? ($roomOptions[$block->id] !== []
                    ? 'Available room(s): '.implode(', ', array_column($roomOptions[$block->id], 'room_number'))
                    : 'No valid room assignment');
            $message .= "\n\n{$block->subject->name} ({$block->subject->subject_code}) — {$block->type}"
                .($block->batch ? " {$block->batch}" : '')
                ."; faculty: {$facultyName}; required classroom: {$roomRequirement}.";
            $message .= "\nCandidate days/time slots checked:";

            [, $currentIssues] = $this->placementsForBlock(
                $block,
                $state,
                $roomOptions[$block->id],
                $roomErrors[$block->id],
                $fixedTimes[$block->id],
                $division
            );
            $issues = $failureReasons[$block->id] ?? [];
            foreach ($currentIssues as $day => $slots) {
                foreach ($slots as $slot => $reasons) {
                    foreach ($reasons as $reason) {
                        $issues[$day][$slot][$reason] = $reason;
                    }
                }
            }

            foreach ($this->days as $day) {
                foreach (range(0, 6 - $block->duration) as $startIndex) {
                    $slot = $block->duration === 2
                        ? $this->timeSlots[$startIndex].' + '.$this->timeSlots[$startIndex + 1]
                        : $this->timeSlots[$startIndex];
                    $slotReasons = array_values($issues[$day][$slot] ?? []);
                    if ($slotReasons === []) {
                        $slotReasons[] = 'No direct conflict in the deepest partial schedule; the solver could not place all remaining required sessions together.';
                    }
                    $message .= "\n- {$day} {$slot}: ".implode(' ', array_unique($slotReasons));
                }
            }
        }

        return $message;
    }

    private function sessionDescription(
        ?string $subjectName,
        ?string $subjectCode,
        ?string $type,
        ?string $batch,
        ?string $facultyName,
        ?string $roomNumber,
        string $source
    ): string {
        $subject = $subjectName ?? 'Unknown subject';
        if ($subjectCode) {
            $subject .= " ({$subjectCode})";
        }
        $activity = $type ?? 'session';
        if ($batch) {
            $activity .= " {$batch}";
        }

        return "{$source}: {$subject} {$activity}; faculty ".($facultyName ?? 'unassigned')
            .'; classroom '.($roomNumber ?? 'unassigned');
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
        if (preg_match('/^(.*?)([1-9][0-9]*)$/', $batch, $matches) !== 1) {
            return null;
        }

        $pairStart = intdiv((int) $matches[2] - 1, 2) * 2 + 1;

        return [
            $matches[1].$pairStart,
            $matches[1].($pairStart + 1),
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function pairedTutorialBatches(string $batch): ?array
    {
        if (preg_match('/^(.*?T?)([1-9][0-9]*)$/i', $batch, $matches) !== 1) {
            return null;
        }

        $pairStart = intdiv((int) $matches[2] - 1, 2) * 2 + 1;

        return [
            $matches[1].$pairStart,
            $matches[1].($pairStart + 1),
        ];
    }

    private function pairedBatchGroupKey(Subject $subject, string $type, ?string $batch, int $creditIndex): ?string
    {
        if ($batch === null) {
            return null;
        }

        $pair = $type === 'Practical'
            ? $this->pairedPracticalBatches($batch)
            : $this->pairedTutorialBatches($batch);
        $selections = $type === 'Practical'
            ? ($subject->division_selections ?? [])
            : ($subject->tutorial_selections ?? []);

        if ($pair === null
            || ! in_array($pair[0], $selections, true)
            || ! in_array($pair[1], $selections, true)) {
            return null;
        }

        return implode(':', [$type, $subject->id, $pair[0], $pair[1], $creditIndex]);
    }
}
