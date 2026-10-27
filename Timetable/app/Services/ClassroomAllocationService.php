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
                if (in_array($className, ['A', 'B', 'C'], true)) {
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

        $divisionStrength = collect($divisions)
            ->unique()
            ->sum(fn (string $division): int => $this->studentStrength(
                $department->name,
                $semester,
                $division,
                $class
            ));

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
                if (in_array($className, ['A', 'B', 'C'], true)) {
                    $classNames[] = $className;
                }
            }
        }

        if ($subject->effective_class_selections ?? []) {
            foreach ($subject->effective_class_selections as $className) {
                if (in_array($className, ['A', 'B', 'C'], true)) {
                    $classNames[] = $className;
                }
            }
        }

        $divisionName = $subject->division?->name;
        if (in_array($divisionName, ['A', 'B', 'C'], true)) {
            $classNames[] = $divisionName;
        }

        return array_values(array_unique($classNames));
    }

    private function allocateBatches(Collection $batches, array $selection = [], bool $isAll = false): array
    {
        $classrooms = Classroom::query()
            ->where('availability', 'Available')
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

        DB::transaction(function () use ($batches, $classrooms, $labs, $selection, &$created, &$allocatedClassrooms, &$allocatedLabs, &$unallocated): void {
            foreach ($batches as $batch) {
                $selectedClass = $batch['name'] ?? $batch['class_name'] ?? null;
                if (! $selectedClass) {
                    continue;
                }

                $subjects = Subject::query()
                    ->where('department_id', $batch['department_id'])
                    ->where('semester', $batch['semester'])
                    ->orderBy('name')
                    ->get();

                foreach ($subjects as $subject) {
                    $savedClassNames = $this->subjectClassValues($subject);
                    $hasSavedClassSelection = $subject->class_selections !== null
                        || $subject->division_selections !== null
                        || $subject->tutorial_selections !== null
                        || filled($subject->division?->name);
                    $staleClassNames = array_values(array_diff(['A', 'B', 'C'], $savedClassNames));
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

                    if (! $this->subjectAppliesToClass($subject, (string) $selectedClass)) {
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
                    $classroomGroups = $hasLecture ? [(string) $selectedClass] : [];
                    $tutorialGroups = collect($subject->tutorial_selections ?? [])
                        ->filter(fn ($tutorial): bool => is_string($tutorial)
                            && strlen($tutorial) > 1
                            && $tutorial[0] === (string) $selectedClass)
                        ->values()
                        ->all();
                    if ($hasTutorial && $tutorialGroups === [] && ! $hasLecture) {
                        $tutorialGroups[] = (string) $selectedClass;
                    }
                    $classroomGroups = array_values(array_unique([
                        ...$classroomGroups,
                        ...$tutorialGroups,
                    ]));

                    $studentStrength = $this->studentStrength(
                        (string) ($batch['department_name'] ?? Department::find($batch['department_id'])?->name ?? ''),
                        (string) $batch['semester'],
                        (string) $selectedClass
                    );

                    if ($hasLecture || $hasTutorial) {
                        foreach ($classroomGroups as $groupName) {
                            $room = $this->pickClassroom($classrooms, $studentStrength);
                            $status = $room ? 'Allocated' : 'Unallocated';

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
                                'day' => '-',
                                'start_time' => null,
                                'end_time' => null,
                                'status' => $status,
                                'notes' => $room?->room_number,
                                'student_count' => $studentStrength,
                            ]);

                            $created++;
                            if ($room) {
                                $allocatedClassrooms++;
                            } else {
                                $unallocated++;
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
                                && $division[0] === (string) $selectedClass)
                            ->unique()
                            ->values();
                        $expectedLabDivisions = $divisionNames->isEmpty()
                            ? [(string) $selectedClass]
                            : $divisionNames->all();

                        if ($divisionNames->isEmpty()) {
                            $lab1 = $this->pickLab($labs, $studentStrength);
                            $lab2 = $this->pickLab(
                                $labs->reject(fn (Classroom $lab): bool => $lab->id === ($lab1?->id ?? -1))->values(),
                                $studentStrength
                            );
                            $labRooms = collect([$lab1, $lab2])->filter();
                            $status = $labRooms->isNotEmpty() ? 'Allocated' : 'Unallocated';
                            $legacyDivision = (string) $selectedClass;

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
                                'day' => '-',
                                'start_time' => null,
                                'end_time' => null,
                                'status' => $status,
                                'notes' => $labRooms->pluck('room_number')->implode(' + ')
                                    ?: 'No suitable lab available for class '.$selectedClass.'.',
                                'student_count' => $studentStrength,
                            ]);

                            $created++;
                            if ($labRooms->isNotEmpty()) {
                                $allocatedLabs += $labRooms->count();
                            } else {
                                $unallocated++;
                            }
                        } else {
                            $configuredGroups = LabAllocationGroup::query()
                                ->where('department_id', $batch['department_id'])
                                ->where('semester', $batch['semester'])
                                ->where('class_name', (string) $selectedClass)
                                ->get();
                            $labGroupPartitions = [];
                            $assignedDivisions = [];

                            foreach ($configuredGroups as $configuredGroup) {
                                $members = collect($configuredGroup->divisions ?? [])
                                    ->intersect($divisionNames)
                                    ->values()
                                    ->all();

                                if ($members === []) {
                                    continue;
                                }

                                $labGroupPartitions[] = [
                                    'divisions' => $members,
                                    'classroom_id' => $configuredGroup->classroom_id,
                                    'is_configured' => true,
                                ];
                                $assignedDivisions = array_merge($assignedDivisions, $members);
                            }

                            foreach ($divisionNames->all() as $divisionName) {
                                if (! in_array($divisionName, $assignedDivisions, true)) {
                                    $labGroupPartitions[] = [
                                        'divisions' => [$divisionName],
                                        'classroom_id' => null,
                                        'is_configured' => false,
                                    ];
                                }
                            }

                            $usedLabIds = [];
                            foreach ($labGroupPartitions as $partition) {
                                $members = $partition['divisions'];
                                $groupStrength = $this->studentStrengthForDivisions(
                                    (int) $batch['department_id'],
                                    (string) $batch['semester'],
                                    (string) $selectedClass,
                                    $members
                                );
                                $configuredLab = $partition['classroom_id']
                                    ? $labs->firstWhere('id', $partition['classroom_id'])
                                    : null;
                                $lab = $partition['is_configured']
                                    ? $configuredLab
                                    : $this->pickLab(
                                        $labs->reject(fn (Classroom $candidate): bool => in_array($candidate->id, $usedLabIds, true))->values(),
                                        $groupStrength
                                    );
                                $isSuitable = $lab && (int) $lab->room_capacity >= $groupStrength;
                                $status = $isSuitable ? 'Allocated' : 'Unallocated';
                                $message = $partition['is_configured'] && ! $configuredLab
                                    ? 'Configured lab is unavailable or is not a suitable laboratory.'
                                    : 'No available lab has enough capacity for the selected division group.';

                                if ($isSuitable) {
                                    $usedLabIds[] = $lab->id;
                                }

                                foreach ($members as $divisionName) {
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
                                        'day' => '-',
                                        'start_time' => null,
                                        'end_time' => null,
                                        'status' => $status,
                                        'notes' => $isSuitable ? $lab->room_number : $message,
                                        'student_count' => $groupStrength,
                                    ]);

                                    $created++;
                                    if ($isSuitable) {
                                        $allocatedLabs++;
                                    } else {
                                        $unallocated++;
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

        return compact('created', 'allocatedClassrooms', 'allocatedLabs', 'unallocated');
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
    ): int
    {
        $departmentCode = Department::query()
            ->where('name', $department)
            ->value('code');
        $query = User::query()
            ->whereNotNull('enrollment_number')
            ->where(function ($studentQuery) use ($department, $departmentCode): void {
                $studentQuery->where('department', $department);
                if ($departmentCode) {
                    $studentQuery->orWhere('department', $departmentCode);
                }
            })
            ->where('semester', $semester);

        if (filled($division)) {
            $divisionCount = (clone $query)->where('divcon', $division)->count();

            if ($divisionCount > 0) {
                return $divisionCount;
            }

            $query->where(function ($studentQuery) use ($division, $class): void {
                $studentQuery->where('student_class', $division);

                if ($class) {
                    $studentQuery->where('student_class', '!=', $class);
                }
            });
        }

        return $query->count();
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
