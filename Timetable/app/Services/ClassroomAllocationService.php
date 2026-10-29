<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\LabAllocationGroup;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClassroomAllocationService
{
    public function __construct(
        protected ?FacultyAllocationService $facultyAllocationService = null
    ) {
        $this->facultyAllocationService = $facultyAllocationService ?? new FacultyAllocationService;
    }

    public function generateForSelection(array $selection): array
    {
        $batches = $this->facultyAllocationService->batchesForSelection($selection);
        $selectedClass = $selection['class'] ?? $selection['class_name'] ?? null;

        if (filled($selectedClass)) {
            $batches = $batches->filter(fn (array $batch): bool => (string) $batch['name'] === (string) $selectedClass);
        }

        if ($batches->isEmpty()) {
            $departmentId = $selection['department_id'] ?? null;
            $semester = $selection['semester'] ?? null;
            $class = $selectedClass ?? $selection['division'] ?? null;
            $department = Department::find($departmentId);

            if ($department && ! empty($semester)) {
                $classes = $class ? [$class] : $this->classesForSelection((int) $departmentId, (string) $semester);
                $batches = collect($classes)->map(function (string $selectedClass) use ($department, $semester): array {
                    return [
                        'key' => $department->id.'|'.$semester.'|'.$selectedClass,
                        'department_id' => $department->id,
                        'department_name' => $department->name,
                        'semester' => (string) $semester,
                        'name' => $selectedClass,
                        'division_id' => null,
                        'class_name' => $selectedClass,
                    ];
                });
            }
        }

        return $this->allocateBatches($batches, $selection);
    }

    public function generateAll(): array
    {
        $batches = $this->facultyAllocationService->batches();

        return $this->allocateBatches($batches, [], true);
    }

    public function classesForSelection(int $departmentId, string $semester): array
    {
        $subjects = Subject::query()
            ->with('division')
            ->where('department_id', $departmentId)
            ->where('semester', $semester)
            ->get();

        $classes = collect();

        foreach ($subjects as $subject) {
            foreach ($this->subjectClassValues($subject) as $className) {
                if (preg_match('/^[A-Z]$/', $className) === 1) {
                    $classes->push($className);
                }
            }
        }

        return $classes->unique()->sort(SORT_NATURAL)->values()->all();
    }

    public function divisionsForSelection(int $departmentId, string $semester, string $class): array
    {
        return Subject::query()
            ->where('department_id', $departmentId)
            ->where('semester', $semester)
            ->get()
            ->flatMap(fn (Subject $subject): array => $subject->division_selections ?? [])
            ->filter(fn ($division): bool => is_string($division)
                && strlen($division) > 1
                && $division[0] === $class)
            ->unique()
            ->sort(SORT_NATURAL)
            ->values()
            ->all();
    }

    public function studentStrengthForDivisions(
        int $departmentId,
        string $semester,
        string $class,
        array $divisions
    ): int {
        $department = Department::find($departmentId);
        if (! $department) {
            return 0;
        }

        $departmentCode = $department->code;
        $classStudents = User::query()
            ->whereNotNull('enrollment_number')
            ->where(function ($studentQuery) use ($department, $departmentCode): void {
                $studentQuery->where('department', $department->name);
                if ($departmentCode) {
                    $studentQuery->orWhere('department', $departmentCode);
                }
            })
            ->where('semester', $semester)
            ->where(function ($studentQuery) use ($class): void {
                $studentQuery->where('student_class', $class)
                    ->orWhere('student_class', 'like', '%-'.$class);
            });
        $divisionIdentifiers = collect($divisions)
            ->unique()
            ->flatMap(fn (string $division): array => [
                $division,
                preg_replace('/^[A-Z]+/', '', $division),
            ])
            ->filter(fn (string $identifier): bool => $identifier !== '')
            ->unique()
            ->values()
            ->all();
        $divisionStrength = (clone $classStudents)
            ->whereIn('divcon', $divisionIdentifiers)
            ->count();

        return $divisionStrength > 0
            ? $divisionStrength
            : $this->studentStrength($department->name, $semester, $class);
    }

    private function subjectClassValues(Subject $subject): array
    {
        $classNames = [];

        foreach (['class_selections', 'division_selections', 'tutorial_selections'] as $column) {
            foreach ($subject->{$column} ?? [] as $selection) {
                if (! is_string($selection) || $selection === '') {
                    continue;
                }

                $className = $selection[0] ?? null;
                if (preg_match('/^[A-Z]$/', $className) === 1) {
                    $classNames[] = $className;
                }
            }
        }

        if ($subject->effective_class_selections ?? []) {
            foreach ($subject->effective_class_selections as $className) {
                if (preg_match('/^[A-Z]$/', $className) === 1) {
                    $classNames[] = $className;
                }
            }
        }

        $divisionName = $subject->division?->name;
        if (preg_match('/^[A-Z]$/', (string) $divisionName) === 1) {
            $classNames[] = $divisionName;
        }

        return array_values(array_unique($classNames));
    }

    private function allocateBatches(Collection $batches, array $selection = [], bool $isAll = false): array
    {
        $classrooms = Classroom::query()
            ->where('availability', 'Available')
            ->where('room_type', 'not like', '%lab%')
            ->whereNotNull('room_number')
            ->orderBy('room_capacity')
            ->get();

        $labs = Classroom::query()
            ->where('availability', 'Available')
            ->where('room_type', 'like', '%lab%')
            ->whereNotNull('room_number')
            ->orderBy('room_capacity')
            ->get();

        $created = 0;
        $allocatedClassrooms = 0;
        $allocatedLabs = 0;
        $unallocated = 0;
        $allocationIssues = [];
        $classroomAssignments = [];
        $labAssignments = [];
        $usedClassroomIds = [];
        $usedLabIds = [];
        $targetClassesByScope = $batches
            ->groupBy(fn (array $batch): string => $batch['department_id'].'|'.$batch['semester'])
            ->map(fn (Collection $scopeBatches): array => $scopeBatches
                ->map(fn (array $batch): string => (string) ($batch['name'] ?? $batch['class_name']))
                ->unique()
                ->values()
                ->all())
            ->all();

        DB::transaction(function () use (
            $batches,
            $classrooms,
            $labs,
            $selection,
            $isAll,
            $targetClassesByScope,
            &$created,
            &$allocatedClassrooms,
            &$allocatedLabs,
            &$unallocated,
            &$allocationIssues,
            &$classroomAssignments,
            &$labAssignments,
            &$usedClassroomIds,
            &$usedLabIds
        ): void {
            $existingResourceAllocations = RoomAllocation::query()
                ->whereIn('department_id', $batches->pluck('department_id')->unique()->all())
                ->whereIn('semester', $batches->pluck('semester')->unique()->all())
                ->whereNull('activity_type')
                ->whereIn('allocation_type', ['Classroom', 'Lab'])
                ->get([
                    'department_id',
                    'semester',
                    'subject_id',
                    'class_name',
                    'division',
                    'allocation_type',
                    'classroom_id',
                ]);

            foreach ($batches as $batch) {
                $selectedClass = $batch['name'] ?? $batch['class_name'] ?? null;
                if (! $selectedClass) {
                    continue;
                }

                $selectedClass = (string) $selectedClass;
                $scopeKey = $batch['department_id'].'|'.$batch['semester'];
                $classKey = $scopeKey.'|'.$selectedClass;
                $subjects = Subject::query()
                    ->with('division')
                    ->where('department_id', $batch['department_id'])
                    ->where('semester', $batch['semester'])
                    ->orderBy('name')
                    ->get();

                $classHasClassroomSessions = $subjects->contains(fn (Subject $subject): bool => $this->subjectAppliesToClass($subject, $selectedClass)
                    && (((int) ($subject->lecture_credit ?? 0)) > 0
                        || ((int) ($subject->tutorial_credit ?? 0)) > 0
                        || str_contains(strtolower((string) ($subject->subject_type ?? '')), 'lecture')
                        || str_contains(strtolower((string) ($subject->subject_type ?? '')), 'tutorial')
                        || (((int) ($subject->lecture_credit ?? 0)) === 0
                            && ((int) ($subject->tutorial_credit ?? 0)) === 0
                            && ((int) ($subject->lab_credit ?? 0)) === 0
                            && ! str_contains(strtolower((string) ($subject->subject_type ?? '')), 'lab')
                            && ! str_contains(strtolower((string) ($subject->subject_type ?? '')), 'practical'))));
                $departmentName = (string) ($batch['department_name'] ?? Department::find($batch['department_id'])?->name ?? '');
                $studentStrength = $this->studentStrength($departmentName, (string) $batch['semester'], $selectedClass);
                $allClassLabDivisions = $subjects
                    ->filter(fn (Subject $subject): bool => $this->subjectAppliesToClass($subject, $selectedClass)
                        && (((int) ($subject->lab_credit ?? 0)) > 0
                            || str_contains(strtolower((string) ($subject->subject_type ?? '')), 'lab')
                            || str_contains(strtolower((string) ($subject->subject_type ?? '')), 'practical')))
                    ->flatMap(fn (Subject $subject): array => collect($subject->division_selections ?? [])
                        ->filter(fn (mixed $division): bool => is_string($division)
                            && strlen($division) > 1
                            && strtoupper($division[0]) === $selectedClass)
                        ->all())
                    ->unique()
                    ->sort(SORT_NATURAL)
                    ->values();

                if ($classHasClassroomSessions && ! array_key_exists($classKey, $classroomAssignments)) {
                    $usedClassroomIds[$scopeKey] ??= [];
                    $externalClassroomIds = $existingResourceAllocations
                        ->filter(fn (RoomAllocation $allocation): bool => (string) $allocation->department_id === (string) $batch['department_id']
                            && (string) $allocation->semester === (string) $batch['semester']
                            && $allocation->allocation_type === 'Classroom'
                            && ($isAll
                                ? ! in_array((string) $allocation->class_name, $targetClassesByScope[$scopeKey] ?? [], true)
                                : (string) $allocation->class_name !== $selectedClass))
                        ->pluck('classroom_id')
                        ->filter()
                        ->map(fn (int|string $id): int => (int) $id)
                        ->unique()
                        ->all();
                    $reservedClassroomIds = array_values(array_unique([
                        ...$usedClassroomIds[$scopeKey],
                        ...$externalClassroomIds,
                    ]));
                    $existingClassroom = $existingResourceAllocations
                        ->filter(fn (RoomAllocation $allocation): bool => (string) $allocation->department_id === (string) $batch['department_id']
                            && (string) $allocation->semester === (string) $batch['semester']
                            && (string) $allocation->class_name === $selectedClass
                            && $allocation->allocation_type === 'Classroom')
                        ->map(fn (RoomAllocation $allocation): ?Classroom => $classrooms->firstWhere('id', $allocation->classroom_id))
                        ->first(fn (?Classroom $room): bool => $room !== null
                            && ! in_array((int) $room->id, $reservedClassroomIds, true)
                            && (int) $room->room_capacity >= $studentStrength);
                    $room = $existingClassroom
                        ?? $this->pickClassroom(
                            $classrooms->reject(fn (Classroom $candidate): bool => in_array((int) $candidate->id, $reservedClassroomIds, true))->values(),
                            $studentStrength
                        )
                        ?? $this->pickClassroom($classrooms, $studentStrength);
                    $classroomAssignments[$classKey] = $room;
                    if ($room) {
                        $usedClassroomIds[$scopeKey][] = (int) $room->id;
                    } else {
                        $allocationIssues[] = "Class {$selectedClass} in semester {$batch['semester']} has no available classroom with capacity {$studentStrength}.";
                    }
                }

                if (! array_key_exists($classKey, $labAssignments)) {
                    $usedLabIds[$scopeKey] ??= [];
                    $configuredLabGroups = LabAllocationGroup::query()
                        ->with('classroom')
                        ->where('department_id', $batch['department_id'])
                        ->where('semester', $batch['semester'])
                        ->where('class_name', $selectedClass)
                        ->get();
                    $externalLabIds = $existingResourceAllocations
                        ->filter(fn (RoomAllocation $allocation): bool => (string) $allocation->department_id === (string) $batch['department_id']
                            && (string) $allocation->semester === (string) $batch['semester']
                            && $allocation->allocation_type === 'Lab'
                            && ($isAll
                                ? ! in_array((string) $allocation->class_name, $targetClassesByScope[$scopeKey] ?? [], true)
                                : (string) $allocation->class_name !== $selectedClass))
                        ->pluck('classroom_id')
                        ->filter()
                        ->map(fn (int|string $id): int => (int) $id)
                        ->unique()
                        ->all();
                    $reservedLabIds = array_values(array_unique([
                        ...$usedLabIds[$scopeKey],
                        ...$externalLabIds,
                    ]));
                    $classLabGroups = [];

                    foreach ($allClassLabDivisions as $labDivision) {
                        $pairedDivision = $this->pairedPracticalDivision($labDivision);
                        $members = [$labDivision];
                        if ($pairedDivision !== null && $allClassLabDivisions->contains($pairedDivision)) {
                            $members = [$labDivision, $pairedDivision];
                            sort($members, SORT_NATURAL);
                        }
                        $groupKey = implode('|', $members);
                        $classLabGroups[$groupKey] = $members;
                    }

                    $labAssignments[$classKey] = [];
                    $classUsedLabIds = [];
                    foreach ($classLabGroups as $groupKey => $members) {
                        $groupStrength = $this->studentStrengthForDivisions(
                            (int) $batch['department_id'],
                            (string) $batch['semester'],
                            $selectedClass,
                            $members
                        );
                        $configuredGroup = $configuredLabGroups->first(
                            fn (LabAllocationGroup $group): bool => collect($group->divisions ?? [])
                                ->sort(SORT_NATURAL)
                                ->values()
                                ->all() === $members
                        );
                        $configuredRoom = $configuredGroup?->classroom;
                        $configuredRoomIsSuitable = $configuredRoom
                            && $configuredRoom->availability === 'Available'
                            && str_contains(strtolower((string) $configuredRoom->room_type), 'lab')
                            && (int) $configuredRoom->room_capacity >= $groupStrength
                            && ! in_array((int) $configuredRoom->id, $reservedLabIds, true);
                        $existingLab = $existingResourceAllocations
                            ->filter(fn (RoomAllocation $allocation): bool => (string) $allocation->department_id === (string) $batch['department_id']
                                && (string) $allocation->semester === (string) $batch['semester']
                                && (string) $allocation->class_name === $selectedClass
                                && $allocation->allocation_type === 'Lab'
                                && in_array((string) $allocation->division, $members, true))
                            ->map(fn (RoomAllocation $allocation): ?Classroom => $labs->firstWhere('id', $allocation->classroom_id))
                            ->first(fn (?Classroom $room): bool => $room !== null
                                && ! in_array((int) $room->id, $reservedLabIds, true)
                                && (int) $room->room_capacity >= $groupStrength);
                        $lab = $configuredGroup
                            ? ($configuredRoomIsSuitable ? $configuredRoom : null)
                            : ($existingLab
                                ?? $this->pickLab(
                                    $labs->reject(fn (Classroom $candidate): bool => in_array((int) $candidate->id, $reservedLabIds, true))->values(),
                                    $groupStrength
                                )
                                ?? $this->pickLab(
                                    $labs->reject(fn (Classroom $candidate): bool => in_array((int) $candidate->id, $classUsedLabIds, true))->values(),
                                    $groupStrength
                                ));
                        $message = $configuredGroup
                            ? 'Configured lab is unavailable or is not a suitable laboratory.'
                            : "No available lab has capacity {$groupStrength}.";
                        $labAssignments[$classKey][$groupKey] = [
                            'room' => $lab,
                            'strength' => $groupStrength,
                            'message' => $message,
                        ];

                        if ($lab) {
                            $usedLabIds[$scopeKey][] = (int) $lab->id;
                            $classUsedLabIds[] = (int) $lab->id;
                            $reservedLabIds[] = (int) $lab->id;
                        } else {
                            $memberLabel = implode('/', $members);
                            $allocationIssues[] = "Class {$selectedClass}, practical batch group {$memberLabel} in semester {$batch['semester']} has no suitable available lab. {$message}";
                        }
                    }
                }

                foreach ($subjects as $subject) {
                    $savedClassNames = $this->subjectClassValues($subject);
                    $hasSavedClassSelection = $subject->class_selections !== null
                        || $subject->division_selections !== null
                        || $subject->tutorial_selections !== null
                        || filled($subject->division?->name);
                    $staleClassNames = array_values(array_diff(range('A', 'Z'), $savedClassNames));
                    if ($hasSavedClassSelection && $staleClassNames !== []) {
                        RoomAllocation::query()
                            ->where('department_id', $batch['department_id'])
                            ->where('semester', $batch['semester'])
                            ->where('subject_id', $subject->id)
                            ->whereIn('class_name', $staleClassNames)
                            ->whereIn('allocation_type', ['Lab', 'Classroom'])
                            ->whereNull('activity_type')
                            ->delete();
                    }

                    if (! $this->subjectAppliesToClass($subject, $selectedClass)) {
                        RoomAllocation::query()
                            ->where('department_id', $batch['department_id'])
                            ->where('semester', $batch['semester'])
                            ->where('subject_id', $subject->id)
                            ->where('class_name', (string) $selectedClass)
                            ->whereIn('allocation_type', ['Lab', 'Classroom'])
                            ->whereNull('activity_type')
                            ->delete();

                        continue;
                    }

                    $hasLecture = ((int) ($subject->lecture_credit ?? 0)) > 0 || str_contains(strtolower((string) ($subject->subject_type ?? '')), 'lecture');
                    $hasTutorial = ((int) ($subject->tutorial_credit ?? 0)) > 0 || str_contains(strtolower((string) ($subject->subject_type ?? '')), 'tutorial');
                    $hasLab = ((int) ($subject->lab_credit ?? 0)) > 0 || str_contains(strtolower((string) ($subject->subject_type ?? '')), 'lab') || str_contains(strtolower((string) ($subject->subject_type ?? '')), 'practical');

                    if (! $hasLecture && ! $hasTutorial && ! $hasLab) {
                        $hasLecture = true;
                    }
                    $classroomGroups = $hasLecture ? [$selectedClass] : [];
                    $tutorialGroups = collect($subject->tutorial_selections ?? [])
                        ->filter(fn ($tutorial): bool => is_string($tutorial)
                            && strlen($tutorial) > 1
                            && strtoupper($tutorial[0]) === $selectedClass)
                        ->values()
                        ->all();
                    if ($hasTutorial && $tutorialGroups === [] && ! $hasLecture) {
                        $tutorialGroups[] = $selectedClass;
                    }
                    $classroomGroups = array_values(array_unique([
                        ...$classroomGroups,
                        ...$tutorialGroups,
                    ]));

                    if ($hasLecture || $hasTutorial) {
                        $room = $classroomAssignments[$classKey] ?? null;
                        $status = $room ? 'Allocated' : 'Unallocated';
                        $roomMessage = "No available classroom for class {$selectedClass} with capacity {$studentStrength}.";
                        foreach ($classroomGroups as $groupName) {
                            RoomAllocation::updateOrCreate([
                                'department_id' => $batch['department_id'],
                                'semester' => $batch['semester'],
                                'subject_id' => $subject->id,
                                'class_name' => (string) $selectedClass,
                                'division' => (string) $groupName,
                                'allocation_type' => 'Classroom',
                                'activity_type' => null,
                            ], [
                                'faculty_id' => $subject->faculty_id,
                                'classroom_id' => $room?->id,
                                'second_classroom_id' => null,
                                'term' => $selection['term'] ?? null,
                                'academic_year' => $selection['academic_year'] ?? null,
                                'status' => $status,
                                'notes' => $room?->room_number ?? $roomMessage,
                                'student_count' => $studentStrength,
                            ]);

                            $created++;
                            if ($room) {
                                $allocatedClassrooms++;
                            } else {
                                $unallocated++;
                                $allocationIssues[] = "{$subject->name}: {$roomMessage}";
                            }
                        }
                    }
                    $staleClassroomAllocations = RoomAllocation::query()
                        ->where('department_id', $batch['department_id'])
                        ->where('semester', $batch['semester'])
                        ->where('subject_id', $subject->id)
                        ->where('class_name', (string) $selectedClass)
                        ->where('allocation_type', 'Classroom')
                        ->whereNull('activity_type');
                    if ($hasLecture || $hasTutorial) {
                        $staleClassroomAllocations
                            ->where(function ($query) use ($classroomGroups): void {
                                $query->whereNotIn('division', $classroomGroups)
                                    ->orWhereNull('division');
                            })
                            ->delete();
                    } else {
                        $staleClassroomAllocations->delete();
                    }

                    if ($hasLab) {
                        $divisionNames = collect($subject->division_selections ?? [])
                            ->filter(fn ($division): bool => is_string($division)
                                && strlen($division) > 1
                                && strtoupper($division[0]) === $selectedClass)
                            ->unique()
                            ->values();
                        $expectedLabDivisions = $divisionNames->isEmpty()
                            ? [$selectedClass]
                            : $divisionNames->all();

                        if ($divisionNames->isEmpty()) {
                            $legacyAssignmentKey = 'legacy';
                            if (! isset($labAssignments[$classKey][$legacyAssignmentKey])) {
                                $externalLabIds = $existingResourceAllocations
                                    ->filter(fn (RoomAllocation $allocation): bool => (string) $allocation->department_id === (string) $batch['department_id']
                                        && (string) $allocation->semester === (string) $batch['semester']
                                        && $allocation->allocation_type === 'Lab'
                                        && ($isAll
                                            ? ! in_array((string) $allocation->class_name, $targetClassesByScope[$scopeKey] ?? [], true)
                                            : (string) $allocation->class_name !== $selectedClass))
                                    ->pluck('classroom_id')
                                    ->filter()
                                    ->map(fn (int|string $id): int => (int) $id)
                                    ->unique()
                                    ->all();
                                $reservedLabIds = array_values(array_unique([
                                    ...($usedLabIds[$scopeKey] ?? []),
                                    ...$externalLabIds,
                                ]));
                                $lab1 = $this->pickLab(
                                    $labs->reject(fn (Classroom $lab): bool => in_array((int) $lab->id, $reservedLabIds, true))->values(),
                                    $studentStrength
                                );
                                $lab2 = $this->pickLab(
                                    $labs->reject(fn (Classroom $lab): bool => in_array((int) $lab->id, [...$reservedLabIds, (int) ($lab1?->id ?? 0)], true))->values(),
                                    $studentStrength
                                );
                                $labAssignments[$classKey][$legacyAssignmentKey] = [
                                    'room' => $lab1,
                                    'second_room' => $lab2,
                                    'strength' => $studentStrength,
                                ];
                                if ($lab1) {
                                    $usedLabIds[$scopeKey][] = (int) $lab1->id;
                                }
                                if ($lab2) {
                                    $usedLabIds[$scopeKey][] = (int) $lab2->id;
                                }
                            }

                            $legacyAssignment = $labAssignments[$classKey][$legacyAssignmentKey];
                            $lab1 = $legacyAssignment['room'];
                            $lab2 = $legacyAssignment['second_room'];
                            $isSuitable = $lab1 !== null && $lab2 !== null;
                            $status = $isSuitable ? 'Allocated' : 'Unallocated';
                            $legacyDivision = $selectedClass;
                            $labRooms = collect([$lab1, $lab2])->filter();
                            $message = "No two separate available labs have capacity {$studentStrength} for class {$selectedClass}.";

                            RoomAllocation::updateOrCreate([
                                'department_id' => $batch['department_id'],
                                'semester' => $batch['semester'],
                                'subject_id' => $subject->id,
                                'class_name' => (string) $selectedClass,
                                'division' => $legacyDivision,
                                'allocation_type' => 'Lab',
                                'activity_type' => null,
                            ], [
                                'faculty_id' => $subject->faculty_id,
                                'classroom_id' => $lab1?->id,
                                'second_classroom_id' => $lab2?->id,
                                'term' => $selection['term'] ?? null,
                                'academic_year' => $selection['academic_year'] ?? null,
                                'status' => $status,
                                'notes' => $labRooms->pluck('room_number')->implode(' + ')
                                    ?: $message,
                                'student_count' => $studentStrength,
                            ]);

                            $created++;
                            if ($isSuitable) {
                                $allocatedLabs += 2;
                            } else {
                                $unallocated++;
                                $allocationIssues[] = $subject->name.': '.$message;
                            }
                        } else {
                            $labGroupPartitions = [];
                            $assignedDivisions = [];

                            foreach ($divisionNames->all() as $divisionName) {
                                if (! in_array($divisionName, $assignedDivisions, true)) {
                                    $pairedDivision = $this->pairedPracticalDivision($divisionName);
                                    $members = [$divisionName];

                                    if ($pairedDivision !== null
                                        && in_array($pairedDivision, $divisionNames->all(), true)
                                        && ! in_array($pairedDivision, $assignedDivisions, true)) {
                                        $members[] = $pairedDivision;
                                        $assignedDivisions[] = $pairedDivision;
                                    }

                                    $labGroupPartitions[] = [
                                        'divisions' => $members,
                                    ];
                                    $assignedDivisions[] = $divisionName;
                                }
                            }

                            foreach ($labGroupPartitions as $partition) {
                                $members = $partition['divisions'];
                                sort($members, SORT_NATURAL);
                                $groupKey = implode('|', $members);
                                $assignment = $labAssignments[$classKey][$groupKey] ?? null;
                                $lab = $assignment['room'] ?? null;
                                $groupStrength = $assignment['strength']
                                    ?? $this->studentStrengthForDivisions(
                                        (int) $batch['department_id'],
                                        (string) $batch['semester'],
                                        $selectedClass,
                                        $members
                                    );
                                $isSuitable = $lab !== null && (int) $lab->room_capacity >= $groupStrength;
                                $status = $isSuitable ? 'Allocated' : 'Unallocated';
                                $message = (string) ($assignment['message']
                                    ?? "No available lab has capacity {$groupStrength} for practical batch group ".implode('/', $members).'.');

                                foreach ($members as $divisionName) {
                                    if (! $divisionNames->contains($divisionName)) {
                                        continue;
                                    }
                                    RoomAllocation::updateOrCreate([
                                        'department_id' => $batch['department_id'],
                                        'semester' => $batch['semester'],
                                        'subject_id' => $subject->id,
                                        'class_name' => (string) $selectedClass,
                                        'division' => $divisionName,
                                        'allocation_type' => 'Lab',
                                        'activity_type' => null,
                                    ], [
                                        'faculty_id' => $subject->faculty_id,
                                        'classroom_id' => $isSuitable ? $lab->id : null,
                                        'second_classroom_id' => null,
                                        'term' => $selection['term'] ?? null,
                                        'academic_year' => $selection['academic_year'] ?? null,
                                        'status' => $status,
                                        'notes' => $isSuitable ? $lab->room_number : $message,
                                        'student_count' => $groupStrength,
                                    ]);

                                    $created++;
                                    if ($isSuitable) {
                                        $allocatedLabs++;
                                    } else {
                                        $unallocated++;
                                        $allocationIssues[] = "{$subject->name}: {$message}";
                                    }
                                }
                            }
                        }

                        RoomAllocation::query()
                            ->where('department_id', $batch['department_id'])
                            ->where('semester', $batch['semester'])
                            ->where('subject_id', $subject->id)
                            ->where('class_name', (string) $selectedClass)
                            ->where('allocation_type', 'Lab')
                            ->whereNull('activity_type')
                            ->where(function ($query) use ($expectedLabDivisions): void {
                                $query->whereNotIn('division', $expectedLabDivisions)
                                    ->orWhereNull('division');
                            })
                            ->delete();
                    } else {
                        RoomAllocation::query()
                            ->where('department_id', $batch['department_id'])
                            ->where('semester', $batch['semester'])
                            ->where('subject_id', $subject->id)
                            ->where('class_name', (string) $selectedClass)
                            ->where('allocation_type', 'Lab')
                            ->whereNull('activity_type')
                            ->delete();
                    }
                }
            }
        });

        return [
            'created' => $created,
            'allocatedClassrooms' => $allocatedClassrooms,
            'allocatedLabs' => $allocatedLabs,
            'unallocated' => $unallocated,
            'issues' => array_values(array_unique($allocationIssues)),
        ];
    }

    private function subjectAppliesToClass(Subject $subject, string $class): bool
    {
        if (in_array($class, $subject->effective_class_selections ?? [], true)) {
            return true;
        }

        $hasSavedClassSelection = $subject->class_selections !== null
            || $subject->division_selections !== null
            || $subject->tutorial_selections !== null
            || filled($subject->division?->name);
        if (! $hasSavedClassSelection) {
            return true;
        }

        foreach (['division_selections', 'tutorial_selections'] as $column) {
            foreach ($subject->{$column} ?? [] as $selection) {
                if (is_string($selection) && $selection !== '' && $selection[0] === $class) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pickClassroom(Collection $classrooms, int $studentStrength): ?Classroom
    {
        return $classrooms
            ->filter(fn (Classroom $room): bool => (int) $room->room_capacity >= $studentStrength)
            ->sortBy('room_capacity')
            ->first();
    }

    private function pickLab(Collection $labs, int $studentStrength): ?Classroom
    {
        return $labs
            ->filter(fn (Classroom $room): bool => (int) $room->room_capacity >= $studentStrength)
            ->sortBy('room_capacity')
            ->first();
    }

    private function pairedPracticalDivision(string $division): ?string
    {
        if (preg_match('/^(.*?)([1-9][0-9]*)$/', $division, $matches) !== 1) {
            return null;
        }

        $pairStart = intdiv((int) $matches[2] - 1, 2) * 2 + 1;
        $pairEnd = $pairStart + 1;
        $pairedNumber = (int) $matches[2] === $pairStart ? $pairEnd : $pairStart;

        return $matches[1].$pairedNumber;
    }

    private function findFacultyId(Subject $subject, array $batch): ?int
    {
        if ($subject->faculty_id) {
            return $subject->faculty_id;
        }

        return Faculty::where('department_id', $batch['department_id'])->orderBy('name')->value('id');
    }

    private function studentStrength(
        string $department,
        string $semester,
        ?string $division,
        ?string $class = null
    ): int {
        $departmentCode = Department::query()
            ->where('name', $department)
            ->value('code');
        $students = User::query()
            ->whereNotNull('enrollment_number')
            ->where(function ($studentQuery) use ($department, $departmentCode): void {
                $studentQuery->where('department', $department);
                if ($departmentCode) {
                    $studentQuery->orWhere('department', $departmentCode);
                }
            })
            ->where('semester', $semester);

        $class = $class ?: (preg_match('/^([A-Z])/', (string) $division, $matches) === 1 ? $matches[1] : null);
        $classStudents = clone $students;
        if (filled($class)) {
            $classStudents->where(function ($query) use ($class): void {
                $query->where('student_class', $class)
                    ->orWhere('student_class', 'like', '%-'.$class);
            });
        }

        if (! filled($division) || $division === $class) {
            $classCount = $classStudents->count();

            return $classCount > 0 ? $classCount : $students->count();
        }

        $divisionSuffix = preg_replace('/^[A-Z]+/', '', (string) $division);
        $divisionStudents = clone $classStudents;
        $divisionStudents->where(function ($query) use ($division, $divisionSuffix): void {
            $query->where('divcon', $division);
            if ($divisionSuffix !== '') {
                $query->orWhere('divcon', $divisionSuffix);
            }
        });
        $divisionCount = $divisionStudents->count();

        if ($divisionCount > 0) {
            return $divisionCount;
        }

        $classCount = $classStudents->count();

        return $classCount > 0 ? $classCount : $students->count();
    }

    public function subjectType(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return str_contains($value, 'lab') || str_contains($value, 'practical') ? 'Lab'
            : (str_contains($value, 'tutorial') ? 'Tutorial' : 'Lecture');
    }

    public function roomType(?string $value): string
    {
        return str_contains(strtolower(trim((string) $value)), 'lab') ? 'Lab' : 'Classroom';
    }
}
