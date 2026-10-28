<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Division;
use App\Models\Faculty;
use App\Models\FacultyWorkload;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FacultyAllocationService
{
    public function batches(): Collection
    {
        $batches = collect();

        User::query()
            ->whereNotNull('enrollment_number')
            ->whereNotNull('department')
            ->whereNotNull('semester')
            ->whereNotNull('divcon')
            ->where('divcon', '!=', '')
            ->select(['department', 'semester', 'divcon'])
            ->distinct()
            ->orderBy('department')
            ->orderBy('semester')
            ->orderBy('divcon')
            ->get()
            ->each(function (User $student) use ($batches): void {
                $department = Department::query()
                    ->where('name', $student->department)
                    ->orWhere('code', $student->department)
                    ->first();

                if ($department) {
                    $batches->push($this->makeBatch($department, $student->semester, $student->divcon));
                }
            });

        Division::query()
            ->with('subjects')
            ->orderBy('semester')
            ->orderBy('name')
            ->get()
            ->each(function (Division $division) use ($batches): void {
                foreach ($division->subjects->pluck('department_id')->unique() as $departmentId) {
                    $department = Department::find($departmentId);
                    if ($department) {
                        $batches->push($this->makeBatch($department, $division->semester, $division->name, $division->id));
                    }
                }
            });

        Subject::query()
            ->select(['department_id', 'semester'])
            ->whereNull('division_id')
            ->distinct()
            ->get()
            ->each(function (Subject $subject) use ($batches): void {
                $department = Department::find($subject->department_id);

                if (! $department) {
                    return;
                }

                $hasBatchForSemester = $batches->contains(fn (array $batch): bool => $batch['department_id'] === $department->id
                    && $batch['semester'] === $subject->semester
                );

                if (! $hasBatchForSemester) {
                    $batches->push($this->makeBatch($department, $subject->semester));
                }

                foreach (['A', 'B', 'C'] as $division) {
                    $batches->push($this->makeBatch($department, $subject->semester, $division));
                }
            });

        return $batches
            ->unique(fn (array $batch): string => $batch['key'])
            ->sortBy(['department_name', 'semester', 'name'])
            ->values();
    }

    public function allocations(?array $selectedBatch = null, array $filters = []): Collection
    {
        $query = RoomAllocation::query()
            ->with(['department', 'subject.faculty', 'faculty', 'classroom'])
            ->orderBy('class_name')
            ->orderBy('subject_id')
            ->orderBy('activity_type')
            ->orderBy('activity_batch');

        if ($selectedBatch) {
            $query->where('department_id', $selectedBatch['department_id'])
                ->where('semester', $selectedBatch['semester'])
                ->where('class_name', $selectedBatch['class_name']);
        }

        foreach (['department_id', 'semester', 'subject_id', 'faculty_id', 'classroom_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['subject_type'] ?? null)) {
            $query->whereHas('subject', fn ($subjectQuery) => $subjectQuery->where('subject_type', $filters['subject_type']));
        }

        $allocations = $query->get();
        $explicitActivityKeys = $allocations
            ->filter(fn (RoomAllocation $allocation): bool => filled($allocation->activity_type))
            ->map(fn (RoomAllocation $allocation): string => $allocation->class_name.'|'.$allocation->subject_id)
            ->unique()
            ->flip();

        return $allocations
            ->filter(fn (RoomAllocation $allocation): bool => filled($allocation->activity_type)
                || ! $explicitActivityKeys->has($allocation->class_name.'|'.$allocation->subject_id))
            ->values();
    }

    public function generate(): array
    {
        return $this->generateForSelection();
    }

    public function generateForSelection(array $selection = []): array
    {
        $assigned = 0;
        $warnings = collect();
        $batches = $this->batchesForSelection($selection);

        if ($batches->isEmpty()) {
            return [
                'count' => 0,
                'warnings' => collect(['No batches available for the selected department, semester, or class/division.']),
            ];
        }

        $targetClassKeys = $batches
            ->map(fn (array $batch): string => $batch['department_id'].'|'.$batch['semester'].'|'.$batch['class_name'])
            ->all();
        $targetTimetableKeys = $batches
            ->map(fn (array $batch): string => $batch['department_id'].'|'.$batch['semester'].'|'.$batch['name'])
            ->all();
        $facultyOccupancy = $this->facultyOccupancy($targetClassKeys, $targetTimetableKeys);
        $workloadData = $this->workloadData();
        $hoursByFaculty = $this->assignedHoursByFaculty($targetClassKeys, $workloadData['hours']);
        $overloadedFacultyIds = $workloadData['overloaded'];
        $normalThreshold = (int) config('faculty_workload.normal_threshold', 18);
        $eligibleFaculty = Faculty::query()
            ->whereIn('department_id', $batches->pluck('department_id')->unique())
            ->orderBy('name')
            ->get();

        DB::transaction(function () use (
            &$assigned,
            $warnings,
            $batches,
            $eligibleFaculty,
            &$hoursByFaculty,
            $overloadedFacultyIds,
            $normalThreshold,
            $facultyOccupancy
        ): void {
            foreach ($batches as $batch) {
                $batchFaculty = $eligibleFaculty
                    ->where('department_id', (int) $batch['department_id'])
                    ->values();
                $subjects = Subject::query()
                    ->with('faculty', 'division')
                    ->where('department_id', $batch['department_id'])
                    ->where('semester', $batch['semester'])
                    ->where(function ($query) use ($batch): void {
                        $query->whereNull('division_id');

                        if ($batch['division_id']) {
                            $query->orWhere('division_id', $batch['division_id']);
                        }
                    })
                    ->orderBy('name')
                    ->get()
                    ->filter(fn (Subject $subject): bool => in_array($batch['name'], $subject->effective_class_selections, true))
                    ->values();

                if ($subjects->isEmpty()) {
                    $warnings->push("No subjects available for {$batch['class_name']}.");

                    continue;
                }

                foreach ($subjects as $subject) {
                    $existingAllocations = RoomAllocation::query()->where([
                        'department_id' => $batch['department_id'],
                        'semester' => $batch['semester'],
                        'subject_id' => $subject->id,
                        'class_name' => $batch['class_name'],
                    ])->get();

                    $activityRows = [];
                    $lectureHours = $subject->lectureCreditsForClass($batch['name']);

                    if ($lectureHours > 0) {
                        $activityRows[] = [
                            'type' => 'Lecture',
                            'batch' => null,
                            'hours' => $lectureHours,
                            'mandatory' => false,
                        ];
                    }

                    $practicalBatches = collect($subject->division_selections ?? [])
                        ->filter(fn (string $group): bool => str_starts_with($group, $batch['name'])
                            && (int) ($subject->lab_credit ?? 0) > 0)
                        ->values();
                    foreach ($practicalBatches as $practicalBatch) {
                        $activityRows[] = [
                            'type' => 'Practical',
                            'batch' => $practicalBatch,
                            'hours' => (int) ($subject->lab_credit ?? 0) * 2,
                            'mandatory' => str_ends_with($practicalBatch, '1'),
                        ];
                    }

                    $tutorialBatches = collect($subject->tutorial_selections ?? [])
                        ->filter(fn (string $group): bool => str_starts_with($group, $batch['name'])
                            && (int) ($subject->tutorial_credit ?? 0) > 0)
                        ->values();
                    foreach ($tutorialBatches as $tutorialBatch) {
                        $activityRows[] = [
                            'type' => 'Tutorial',
                            'batch' => $tutorialBatch,
                            'hours' => (int) ($subject->tutorial_credit ?? 0),
                            'mandatory' => str_ends_with($tutorialBatch, 'T1'),
                        ];
                    }

                    if ($activityRows === [] && (int) ($subject->credit ?? 0) > 0) {
                        $subjectType = mb_strtolower((string) $subject->subject_type);
                        $fallbackType = match (true) {
                            str_contains($subjectType, 'lab'), str_contains($subjectType, 'practical') => 'Practical',
                            str_contains($subjectType, 'tutorial') => 'Tutorial',
                            str_contains($subjectType, 'project') => str_contains($subjectType, 'minor') ? 'Minor Project' : 'Project',
                            default => 'Lecture',
                        };
                        $activityRows[] = [
                            'type' => $fallbackType,
                            'batch' => null,
                            'hours' => $fallbackType === 'Practical'
                                ? (int) $subject->credit * 2
                                : (int) $subject->credit,
                            'mandatory' => false,
                        ];
                    }

                    $existingTheoryAllocation = $existingAllocations->first(
                        fn (RoomAllocation $allocation): bool => $allocation->activity_type === 'Lecture'
                    );
                    $legacyFacultyId = $existingAllocations
                        ->first(fn (RoomAllocation $allocation): bool => $allocation->activity_type === null && $allocation->faculty_id)
                        ?->faculty_id;
                    $preferredFacultyIds = collect([
                        $subject->faculty_id,
                        $existingTheoryAllocation?->faculty_id,
                        $legacyFacultyId,
                    ])->filter()->unique();
                    $theoryFaculty = $preferredFacultyIds
                        ->map(fn (int|string $facultyId): ?Faculty => $batchFaculty->firstWhere('id', (int) $facultyId))
                        ->filter()
                        ->first()
                        ?? $batchFaculty->first(fn (Faculty $faculty): bool => filled($subject->faculty_name)
                            && mb_stripos($faculty->name, (string) $subject->faculty_name) !== false);

                    $leadActivities = collect($activityRows)->filter(
                        fn (array $row): bool => $row['type'] === 'Lecture' || $row['mandatory'] || $row['batch'] === null
                    );

                    if (! $theoryFaculty && $leadActivities->isNotEmpty()) {
                        $leadHours = (int) $leadActivities->sum('hours');
                        $leadDuration = $leadActivities->contains(fn (array $row): bool => $row['type'] === 'Practical') ? 2 : 1;
                        $theoryFaculty = $this->selectFaculty(
                            $batchFaculty,
                            $subject,
                            [],
                            $leadHours,
                            $hoursByFaculty,
                            $overloadedFacultyIds,
                            $normalThreshold,
                            $leadDuration,
                            $facultyOccupancy
                        );
                    }

                    if (! $theoryFaculty && $leadActivities->isNotEmpty()) {
                        $warnings->push("No eligible faculty available for {$subject->name} in {$batch['class_name']}.");
                    }

                    foreach ($activityRows as $activity) {
                        if ($activity['type'] === 'Lecture' || $activity['mandatory'] || $activity['batch'] === null) {
                            $faculty = $theoryFaculty;
                        } else {
                            $existingActivityAllocation = $existingAllocations->first(
                                fn (RoomAllocation $allocation): bool => $allocation->activity_type === $activity['type']
                                    && $allocation->activity_batch === $activity['batch']
                            );
                            $faculty = $this->selectFaculty(
                                $batchFaculty,
                                $subject,
                                $theoryFaculty ? [$theoryFaculty->id] : [],
                                $activity['hours'],
                                $hoursByFaculty,
                                $overloadedFacultyIds,
                                $normalThreshold,
                                $activity['type'] === 'Practical' ? 2 : 1,
                                $facultyOccupancy,
                                $existingActivityAllocation?->faculty_id
                            );
                        }

                        if (! $faculty && $activity['type'] !== 'Lecture' && ! $activity['mandatory'] && $activity['batch'] !== null) {
                            $warnings->push(
                                "No eligible faculty within workload limits for {$subject->name} {$activity['batch']} in {$batch['class_name']}."
                            );
                        } elseif (! $faculty && $activity['mandatory']) {
                            $warnings->push(
                                "Mandatory {$activity['batch']} for {$subject->name} has no assigned theory faculty in {$batch['class_name']}."
                            );
                        }

                        if ($faculty && $activity['mandatory'] && ! $this->hasAvailableSlot(
                            $faculty->id,
                            $activity['type'] === 'Practical' ? 2 : 1,
                            $facultyOccupancy
                        )) {
                            $warnings->push(
                                "Mandatory {$activity['batch']} for {$subject->name} conflicts with the theory faculty's existing timetable; resolve the conflict without replacing that faculty."
                            );
                        }

                        if ($faculty) {
                            $hoursByFaculty[$faculty->id] = ($hoursByFaculty[$faculty->id] ?? 0) + $activity['hours'];
                            if (($hoursByFaculty[$faculty->id] ?? 0) > $normalThreshold) {
                                $warnings->push(
                                    "Mandatory assignments exceed the configured workload limit for {$faculty->name} ({$subject->name}, {$batch['class_name']})."
                                );
                            }
                        }

                        $allocation = RoomAllocation::firstOrNew([
                            'department_id' => $batch['department_id'],
                            'semester' => $batch['semester'],
                            'subject_id' => $subject->id,
                            'class_name' => $batch['class_name'],
                            'activity_type' => $activity['type'],
                            'activity_batch' => $activity['batch'],
                        ]);
                        $allocation->fill([
                            'faculty_id' => $faculty?->id,
                            'division' => $batch['name'],
                            'allocation_type' => $activity['type'] === 'Practical' ? 'Lab' : 'Classroom',
                            'status' => $allocation->exists ? $allocation->status : 'Unallocated',
                        ]);
                        $allocation->save();
                        if ($faculty) {
                            $assigned++;
                        }
                    }

                    $expectedActivityKeys = collect($activityRows)
                        ->map(fn (array $activity): string => $activity['type'].'|'.($activity['batch'] ?? ''))
                        ->all();
                    $existingAllocations
                        ->filter(fn (RoomAllocation $allocation): bool => filled($allocation->activity_type))
                        ->each(function (RoomAllocation $allocation) use ($expectedActivityKeys): void {
                            $key = $allocation->activity_type.'|'.($allocation->activity_batch ?? '');
                            if (! in_array($key, $expectedActivityKeys, true)) {
                                $allocation->delete();
                            }
                        });
                }
            }
        });

        return [
            'count' => $assigned,
            'warnings' => $warnings->unique()->values(),
        ];
    }

    private function assignedHoursByFaculty(array $excludedClassKeys, array $recordedHours): array
    {
        $allocations = RoomAllocation::query()
            ->whereNotNull('faculty_id')
            ->with('subject')
            ->get()
            ->reject(fn (RoomAllocation $allocation): bool => in_array(
                $allocation->department_id.'|'.$allocation->semester.'|'.$allocation->class_name,
                $excludedClassKeys,
                true
            ));

        $hoursByFaculty = [];
        $allocations
            ->groupBy(fn (RoomAllocation $allocation): string => $allocation->department_id.'|'.$allocation->semester.'|'.$allocation->class_name.'|'.$allocation->subject_id)
            ->each(function ($classAllocations) use (&$hoursByFaculty): void {
                $activityAllocations = $classAllocations->filter(
                    fn (RoomAllocation $allocation): bool => filled($allocation->activity_type)
                );

                if ($activityAllocations->isNotEmpty()) {
                    foreach ($activityAllocations as $allocation) {
                        $subject = $allocation->subject;
                        if (! $subject || ! $allocation->faculty_id) {
                            continue;
                        }

                        $class = $allocation->division;
                        $hours = match ($allocation->activity_type) {
                            'Lecture' => $subject->lectureCreditsForClass((string) $class),
                            'Practical' => (int) ($subject->lab_credit ?? 0) * 2,
                            'Tutorial' => (int) ($subject->tutorial_credit ?? 0),
                            'Minor Project', 'Project' => (int) ($subject->credit ?? 0),
                            default => 0,
                        };
                        $hoursByFaculty[$allocation->faculty_id] = ($hoursByFaculty[$allocation->faculty_id] ?? 0) + $hours;
                    }

                    return;
                }

                $allocation = $classAllocations->first();
                if (! $allocation?->faculty_id || ! $allocation->subject) {
                    return;
                }

                $subjectHours = $allocation->division
                    ? $allocation->subject->weeklyHoursForClass($allocation->division)
                    : $allocation->subject->weekly_hours;
                $hoursByFaculty[$allocation->faculty_id] = ($hoursByFaculty[$allocation->faculty_id] ?? 0) + $subjectHours;
            });

        foreach ($recordedHours as $facultyId => $hours) {
            $hoursByFaculty[$facultyId] = max($hoursByFaculty[$facultyId] ?? 0, $hours);
        }

        return $hoursByFaculty;
    }

    private function facultyOccupancy(array $excludedClassKeys, array $excludedTimetableKeys): array
    {
        $timeSlots = ['10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10'];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $occupancy = [];
        $entries = TimetableEntry::query()->whereNotNull('faculty_id')->get();

        foreach ($entries as $entry) {
            if (in_array($entry->department_id.'|'.$entry->semester.'|'.$entry->division, $excludedTimetableKeys, true)) {
                continue;
            }

            $slotIndex = array_search($entry->time_slot, $timeSlots, true);
            if ($slotIndex === false) {
                continue;
            }

            $slots = [$entry->time_slot];
            if ($entry->duration === 2 && isset($timeSlots[$slotIndex + 1])) {
                $slots[] = $timeSlots[$slotIndex + 1];
            }

            foreach ($slots as $slot) {
                $occupancy[$entry->faculty_id][$entry->day][$slot] = true;
            }
        }

        $allocations = RoomAllocation::query()
            ->whereNotNull('faculty_id')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get()
            ->reject(fn (RoomAllocation $allocation): bool => in_array(
                $allocation->department_id.'|'.$allocation->semester.'|'.$allocation->class_name,
                $excludedClassKeys,
                true
            ));

        foreach ($allocations as $allocation) {
            $allocationStart = $this->timeInMinutes((string) $allocation->start_time);
            $allocationEnd = $this->timeInMinutes((string) $allocation->end_time);
            if ($allocationStart === null || $allocationEnd === null) {
                continue;
            }

            foreach ($timeSlots as $timeSlot) {
                [$start, $end] = explode('-', $timeSlot);
                $slotStart = $this->timeInMinutes($start);
                $slotEnd = $this->timeInMinutes($end);
                if ($slotStart === null || $slotEnd === null
                    || $allocationStart >= $slotEnd
                    || $allocationEnd <= $slotStart) {
                    continue;
                }

                $occupancy[$allocation->faculty_id][$allocation->day][$timeSlot] = true;
            }
        }

        return ['days' => $days, 'time_slots' => $timeSlots, 'occupied' => $occupancy];
    }

    private function hasAvailableSlot(int $facultyId, int $duration, array $facultyOccupancy): bool
    {
        $occupied = $facultyOccupancy['occupied'][$facultyId] ?? [];
        $slotCount = count($facultyOccupancy['time_slots']);
        $validStarts = $duration === 2 ? [0, 2, 4] : range(0, $slotCount - 1);

        foreach ($facultyOccupancy['days'] as $day) {
            foreach ($validStarts as $start) {
                if ($start + $duration > $slotCount) {
                    continue;
                }

                $isAvailable = true;
                for ($offset = 0; $offset < $duration; $offset++) {
                    $slot = $facultyOccupancy['time_slots'][$start + $offset];
                    if (! empty($occupied[$day][$slot])) {
                        $isAvailable = false;
                        break;
                    }
                }

                if ($isAvailable) {
                    return true;
                }
            }
        }

        return false;
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

    private function workloadData(): array
    {
        $facultyById = Faculty::query()->get(['id', 'name'])->keyBy('id');
        $facultyIdsByName = $facultyById
            ->mapWithKeys(fn (Faculty $faculty): array => [mb_strtolower($faculty->name) => $faculty->id]);
        $hoursByFaculty = [];
        $overloadedFacultyIds = [];

        FacultyWorkload::query()
            ->whereNotNull('faculty_id')
            ->get()
            ->each(function (FacultyWorkload $workload) use (
                $facultyById,
                $facultyIdsByName,
                &$hoursByFaculty,
                &$overloadedFacultyIds
            ): void {
                $storedFacultyId = (string) $workload->faculty_id;
                $facultyId = ctype_digit($storedFacultyId) && $facultyById->has((int) $storedFacultyId)
                    ? (int) $storedFacultyId
                    : $facultyIdsByName->get(mb_strtolower((string) $workload->faculty_name));

                if (! $facultyId) {
                    return;
                }

                $hoursByFaculty[$facultyId] = ($hoursByFaculty[$facultyId] ?? 0) + $workload->total_hours;
                if ($workload->workload_status === 'Overloaded') {
                    $overloadedFacultyIds[$facultyId] = $facultyId;
                }
            });

        return [
            'hours' => $hoursByFaculty,
            'overloaded' => array_values($overloadedFacultyIds),
        ];
    }

    private function selectFaculty(
        Collection $candidates,
        Subject $subject,
        array $excludedFacultyIds,
        int $hours,
        array $hoursByFaculty,
        array $overloadedFacultyIds,
        int $normalThreshold,
        int $duration,
        array $facultyOccupancy,
        ?int $preferredFacultyId = null
    ): ?Faculty {
        $availableCandidates = $candidates
            ->filter(function (Faculty $faculty) use (
                $excludedFacultyIds,
                $hours,
                $hoursByFaculty,
                $overloadedFacultyIds,
                $normalThreshold,
                $duration,
                $facultyOccupancy
            ): bool {
                if (in_array($faculty->id, $excludedFacultyIds, true)
                    || in_array($faculty->id, $overloadedFacultyIds, true)
                    || ($hoursByFaculty[$faculty->id] ?? 0) + $hours > $normalThreshold
                    || ! $this->hasAvailableSlot($faculty->id, $duration, $facultyOccupancy)) {
                    return false;
                }

                return true;
            });
        $expertiseCandidates = $availableCandidates->filter(function (Faculty $faculty) use ($subject): bool {
            $expertise = trim((string) $faculty->subjects);

            return $expertise !== ''
                && (mb_stripos($expertise, $subject->name) !== false
                    || mb_stripos($expertise, $subject->subject_code) !== false);
        });
        $unspecifiedExpertiseCandidates = $availableCandidates->filter(
            fn (Faculty $faculty): bool => trim((string) $faculty->subjects) === ''
        );
        $eligibleCandidates = $expertiseCandidates->isNotEmpty()
            ? $expertiseCandidates
            : ($unspecifiedExpertiseCandidates->isNotEmpty() ? $unspecifiedExpertiseCandidates : $availableCandidates);

        $preferredFaculty = $preferredFacultyId
            ? $eligibleCandidates->firstWhere('id', $preferredFacultyId)
            : null;
        if ($preferredFaculty) {
            return $preferredFaculty;
        }

        return $eligibleCandidates->sort(function (Faculty $left, Faculty $right) use ($hoursByFaculty): int {
            $loadComparison = ($hoursByFaculty[$left->id] ?? 0) <=> ($hoursByFaculty[$right->id] ?? 0);

            return $loadComparison !== 0
                ? $loadComparison
                : strcasecmp($left->name, $right->name);
        })
            ->first();
    }

    public function validateAlternativeAssignment(RoomAllocation $allocation, Faculty $faculty): ?string
    {
        if (! in_array($allocation->activity_type, ['Practical', 'Tutorial'], true)
            || ! $allocation->activity_batch
            || str_ends_with($allocation->activity_batch, '1')) {
            return 'Only non-mandatory practical and tutorial batches can be reassigned.';
        }

        if ((int) $faculty->department_id !== (int) $allocation->department_id) {
            return 'Choose a faculty member from the same department.';
        }

        $subject = $allocation->subject;
        if (! $subject) {
            return 'The subject for this allocation could not be found.';
        }

        if ((int) $subject->faculty_id === (int) $faculty->id) {
            return 'The theory faculty must remain assigned to the mandatory first batch.';
        }

        $theoryFacultyId = $subject->faculty_id
            ?? RoomAllocation::query()
                ->where('department_id', $allocation->department_id)
                ->where('semester', $allocation->semester)
                ->where('subject_id', $allocation->subject_id)
                ->where('class_name', $allocation->class_name)
                ->where('activity_type', 'Lecture')
                ->value('faculty_id');
        if ($theoryFacultyId && (int) $theoryFacultyId === (int) $faculty->id) {
            return 'The theory faculty cannot be assigned to an additional practical or tutorial batch.';
        }

        $expertise = trim((string) $faculty->subjects);
        if ($expertise !== ''
            && mb_stripos($expertise, $subject->name) === false
            && mb_stripos($expertise, $subject->subject_code) === false) {
            return 'This faculty member is not listed as eligible for the selected subject.';
        }

        $hours = match ($allocation->activity_type) {
            'Practical' => (int) ($subject->lab_credit ?? 0) * 2,
            'Tutorial' => (int) ($subject->tutorial_credit ?? 0),
            default => 0,
        };
        $workloadData = $this->workloadData();
        if (in_array($faculty->id, $workloadData['overloaded'], true)) {
            return 'This faculty member is already over the configured workload limit.';
        }

        if ($allocation->faculty_id !== $faculty->id) {
            $hoursByFaculty = $this->assignedHoursByFaculty([], $workloadData['hours']);
            $normalThreshold = (int) config('faculty_workload.normal_threshold', 18);
            if (($hoursByFaculty[$faculty->id] ?? 0) + $hours > $normalThreshold) {
                return 'This assignment would exceed the configured workload limit.';
            }
        }

        $facultyOccupancy = $this->facultyOccupancy(
            [$allocation->department_id.'|'.$allocation->semester.'|'.$allocation->class_name],
            [$allocation->department_id.'|'.$allocation->semester.'|'.$allocation->division]
        );
        $duration = $allocation->activity_type === 'Practical' ? 2 : 1;
        if (! $this->hasAvailableSlot($faculty->id, $duration, $facultyOccupancy)) {
            return 'This faculty member has no available timetable slot for this batch.';
        }

        return null;
    }

    public function batchesForSelection(array $selection = []): Collection
    {
        $batches = $this->batches();

        if (! empty($selection['department_id'])) {
            $batches = $batches->filter(fn (array $batch): bool => (int) $batch['department_id'] === (int) $selection['department_id']);
        }

        if (! empty($selection['semester'])) {
            $batches = $batches->filter(fn (array $batch): bool => (string) $batch['semester'] === (string) $selection['semester']);
        }

        if (! empty($selection['division'])) {
            $batches = $batches->filter(function (array $batch) use ($selection): bool {
                if (! empty($batch['name']) && (string) $batch['name'] === (string) $selection['division']) {
                    return true;
                }

                return str_contains((string) $batch['class_name'], (string) $selection['division']);
            });
        }

        if (! empty($selection['batch'])) {
            $batches = $batches->filter(fn (array $batch): bool => (string) $batch['key'] === (string) $selection['batch']);
        }

        return $batches->values();
    }

    private function roomFor(Subject $subject): ?Classroom
    {
        $isLab = str_contains(strtolower((string) $subject->subject_type), 'lab')
            || str_contains(strtolower((string) $subject->subject_type), 'practical');

        return Classroom::query()
            ->where('availability', 'Available')
            ->when($isLab, fn ($query) => $query->where('room_type', 'like', '%lab%'))
            ->when(! $isLab, fn ($query) => $query->where('room_type', 'not like', '%lab%'))
            ->whereNotNull('room_number')
            ->orderBy('room_number')
            ->first();
    }

    private function findFacultyForSubject(Subject $subject, array $batch): ?Faculty
    {
        if ($subject->faculty_id) {
            return $subject->faculty;
        }

        $department = Department::find($batch['department_id']);
        $query = Faculty::query();

        if ($department) {
            $query->where('department_id', $department->id);
        }

        $facultyCandidates = $query->orderBy('name')->get();

        if ($facultyCandidates->isEmpty()) {
            return null;
        }

        $assignedHours = $this->assignedHoursForBatch($batch);
        $normalThreshold = (int) config('faculty_workload.normal_threshold', 18);

        $eligible = $facultyCandidates->filter(function (Faculty $faculty) use ($assignedHours, $normalThreshold): bool {
            $workload = FacultyWorkload::query()
                ->where('faculty_id', (string) $faculty->id)
                ->first();

            if ($workload && $workload->workload_status === 'Overloaded') {
                return false;
            }

            $effectiveLoad = (int) ($assignedHours[$faculty->id] ?? 0) + (int) ($workload?->total_hours ?? 0);

            return $effectiveLoad < $normalThreshold;
        });

        $candidates = $eligible->isNotEmpty() ? $eligible : $facultyCandidates;
        $sorted = $candidates->all();

        usort($sorted, function (Faculty $left, Faculty $right) use ($assignedHours): int {
            $leftWorkload = (int) ($assignedHours[$left->id] ?? 0) + (int) FacultyWorkload::query()
                ->where('faculty_id', (string) $left->id)
                ->value('total_hours');
            $rightWorkload = (int) ($assignedHours[$right->id] ?? 0) + (int) FacultyWorkload::query()
                ->where('faculty_id', (string) $right->id)
                ->value('total_hours');

            if ($leftWorkload !== $rightWorkload) {
                return $leftWorkload <=> $rightWorkload;
            }

            return strcmp(strtolower($left->name), strtolower($right->name));
        });

        return $sorted[0] ?? $candidates->first();
    }

    private function assignedHoursForBatch(array $batch): array
    {
        $hoursByFaculty = [];

        RoomAllocation::query()
            ->where('department_id', $batch['department_id'])
            ->where('semester', $batch['semester'])
            ->with('subject.division')
            ->get()
            ->groupBy(fn (RoomAllocation $allocation): string => $allocation->subject_id.'|'.$allocation->class_name)
            ->each(function ($allocations) use (&$hoursByFaculty): void {
                $allocation = $allocations->first(fn (RoomAllocation $candidate): bool => ! empty($candidate->faculty_id));

                if (! $allocation) {
                    return;
                }

                if (empty($allocation->faculty_id)) {
                    return;
                }

                $subjectHours = $allocation->division
                    ? (int) ($allocation->subject?->weeklyHoursForClass($allocation->division) ?? 0)
                    : (int) ($allocation->subject?->weekly_hours ?? 0);
                $hoursByFaculty[$allocation->faculty_id] = ($hoursByFaculty[$allocation->faculty_id] ?? 0) + $subjectHours;
            });

        return $hoursByFaculty;
    }

    private function makeBatch(Department $department, string $semester, ?string $name = null, ?int $divisionId = null): array
    {
        $name = filled($name) ? trim($name) : null;
        $className = $department->name.'-'.$semester.($name ? '-'.$name : '');

        return [
            'key' => $department->id.'|'.$semester.'|'.($name ?? ''),
            'department_id' => $department->id,
            'department_name' => $department->name,
            'semester' => $semester,
            'name' => $name ?? $semester,
            'division_id' => $divisionId,
            'class_name' => $className,
        ];
    }

    private function formatAllocation(RoomAllocation $allocation): array
    {
        $subjectType = (string) ($allocation->subject?->subject_type ?? 'Theory');
        $isLab = str_contains(strtolower($subjectType), 'lab')
            || str_contains(strtolower($subjectType), 'practical');

        return [
            'id' => $allocation->id,
            'batch' => $allocation->class_name,
            'department' => $allocation->department?->name,
            'semester' => $allocation->semester,
            'subject_id' => $allocation->subject_id,
            'subject' => $allocation->subject?->name,
            'faculty_id' => $allocation->faculty_id,
            'faculty' => $allocation->faculty?->name ?? $allocation->subject?->faculty?->name,
            'location' => $allocation->notes ?: $allocation->classroom?->room_number,
            'type' => $isLab
                ? 'Practical'
                : (in_array($allocation->activity_type, ['Minor Project', 'Project'], true) ? $allocation->activity_type : 'Theory'),
            'status' => $allocation->status,
            'warning' => $allocation->faculty_id ? null : 'Faculty is not assigned to this subject.',
        ];
    }
}
