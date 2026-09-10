<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Division;
use App\Models\FacultyWorkload;
use App\Models\RoomAllocation;
use App\Models\Subject;
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
                if ($department && ! $batches->contains(fn (array $batch): bool => $batch['department_id'] === $department->id && $batch['semester'] === $subject->semester
                )) {
                    $batches->push($this->makeBatch($department, $subject->semester));
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
            ->orderBy('subject_id');

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

        return $query->get()->map(fn (RoomAllocation $allocation): array => $this->formatAllocation($allocation));
    }

    public function generate(): array
    {
        $created = 0;
        $warnings = collect();

        DB::transaction(function () use (&$created, $warnings): void {
            foreach ($this->batches() as $batch) {
                $subjects = Subject::query()
                    ->with('faculty')
                    ->where('department_id', $batch['department_id'])
                    ->where('semester', $batch['semester'])
                    ->where(function ($query) use ($batch): void {
                        $query->whereNull('division_id');

                        if ($batch['division_id']) {
                            $query->orWhere('division_id', $batch['division_id']);
                        }
                    })
                    ->orderBy('name')
                    ->get();

                if ($subjects->isEmpty()) {
                    $warnings->push("No subjects found for {$batch['class_name']}.");

                    continue;
                }

                foreach ($subjects as $subject) {
                    $allocation = RoomAllocation::query()->firstOrNew([
                        'department_id' => $batch['department_id'],
                        'semester' => $batch['semester'],
                        'subject_id' => $subject->id,
                        'class_name' => $batch['class_name'],
                    ]);

                    $room = $allocation->classroom_id
                        ? Classroom::find($allocation->classroom_id)
                        : $this->roomFor($subject);

                    if (! $subject->faculty_id) {
                        $warnings->push("Faculty is not assigned to {$subject->name} for {$batch['class_name']}.");
                    }

                    if ($subject->faculty_id && FacultyWorkload::query()
                        ->where('faculty_id', (string) $subject->faculty_id)
                        ->get()
                        ->contains(fn (FacultyWorkload $workload): bool => $workload->workload_status === 'Overloaded')) {
                        $warnings->push("Faculty workload is overloaded for {$subject->name} in {$batch['class_name']}.");
                    }

                    if (! $room) {
                        $warnings->push("No classroom/lab location assigned for {$subject->name} in {$batch['class_name']}.");
                    }

                    $allocation->fill([
                        'faculty_id' => $subject->faculty_id,
                        'classroom_id' => $room?->id,
                        'status' => $room ? 'Allocated' : 'Unallocated',
                        'notes' => $room?->room_number,
                    ]);
                    $allocation->save();
                    $created++;
                }
            }
        });

        return [
            'count' => $created,
            'warnings' => $warnings->unique()->values(),
        ];
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
            'type' => $isLab ? 'Practical' : 'Theory',
            'status' => $allocation->status,
            'warning' => $allocation->faculty_id ? null : 'Faculty is not assigned to this subject.',
        ];
    }
}
