<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Faculty;
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

        if ($batches->isEmpty()) {
            $department = Department::find($selection['department_id'] ?? null);
            if ($department && ! empty($selection['semester'])) {
                $div = ! empty($selection['division']) ? $selection['division'] : null;
                $className = $department->name.'-'.$selection['semester'].($div ? '-'.$div : '');
                $batches = collect([[
                    'key' => $department->id.'|'.$selection['semester'].'|'.($div ?? ''),
                    'department_id' => $department->id,
                    'department_name' => $department->name,
                    'semester' => (string) $selection['semester'],
                    'name' => $div ?? (string) $selection['semester'],
                    'division_id' => null,
                    'class_name' => $className,
                ]]);
            }
        }

        return $this->allocateBatches($batches, $selection);
    }

    public function generateAll(): array
    {
        $batches = $this->facultyAllocationService->batches();

        return $this->allocateBatches($batches, [], true);
    }

    private function allocateBatches(Collection $batches, array $selection = [], bool $isAll = false): array
    {
        $classrooms = Classroom::query()
            ->where('availability', 'Available')
            ->where(function ($query) {
                $query->where('room_type', 'Classroom')
                    ->orWhere('room_type', 'not like', '%lab%');
            })
            ->whereNotNull('room_number')
            ->orderBy('room_number')
            ->get();

        $labs = Classroom::query()
            ->where('availability', 'Available')
            ->where('room_type', 'like', '%lab%')
            ->whereNotNull('room_number')
            ->orderBy('room_number')
            ->get();

        $created = 0;
        $allocatedClassrooms = 0;
        $allocatedLabs = 0;
        $unallocated = 0;

        DB::transaction(function () use (
            $batches, $classrooms, $labs, $selection,
            &$created, &$allocatedClassrooms, &$allocatedLabs, &$unallocated
        ): void {
            $classroomIndex = 0;
            $labIndex = 0;

            foreach ($batches as $batch) {
                $existingFacultyAllocations = RoomAllocation::query()
                    ->where('department_id', $batch['department_id'])
                    ->where('semester', $batch['semester'])
                    ->where('class_name', $batch['class_name'])
                    ->whereNotNull('faculty_id')
                    ->get()
                    ->keyBy('subject_id');

                $subjects = Subject::query()
                    ->with('faculty')
                    ->where('department_id', $batch['department_id'])
                    ->where('semester', $batch['semester'])
                    ->orderBy('name')
                    ->get();

                if ($subjects->isEmpty()) {
                    continue;
                }

                $studentStrength = $this->studentStrength(
                    $batch['department_name'] ?? '',
                    (string) $batch['semester'],
                    $batch['name'] ?? null
                );

                // RULE: For Lecture and Tutorial belonging to the SAME existing Batch / Class / Division:
                // Use the SAME Classroom. Reuse existing classroom assigned to this batch if available.
                $existingAssignedRoomId = RoomAllocation::query()
                    ->where('class_name', $batch['class_name'])
                    ->where('allocation_type', 'Classroom')
                    ->whereNotNull('classroom_id')
                    ->value('classroom_id');

                $batchClassroom = null;
                if ($existingAssignedRoomId) {
                    $batchClassroom = $classrooms->firstWhere('id', $existingAssignedRoomId);
                }
                if (! $batchClassroom && $classrooms->isNotEmpty()) {
                    $batchClassroom = $classrooms[$classroomIndex % $classrooms->count()];
                    $classroomIndex++;
                }

                foreach ($subjects as $subject) {
                    $facultyId = $existingFacultyAllocations->get($subject->id)?->faculty_id
                        ?? $subject->faculty_id
                        ?? $this->findFacultyId($subject, $batch);

                    $hasLecture = ((int) ($subject->lecture_credit ?? 0)) > 0;
                    $hasTutorial = ((int) ($subject->tutorial_credit ?? 0)) > 0;
                    $hasLab = ((int) ($subject->lab_credit ?? 0)) > 0
                        || str_contains(strtolower((string) $subject->subject_type), 'lab')
                        || str_contains(strtolower((string) $subject->subject_type), 'practical');

                    if (! $hasLecture && ! $hasTutorial && ! $hasLab) {
                        $hasLecture = true;
                    }

                    // 1. Lecture and/or Tutorial -> Allocation Type = Classroom (1 Classroom)
                    if ($hasLecture || $hasTutorial) {
                        RoomAllocation::updateOrCreate([
                            'department_id' => $batch['department_id'],
                            'semester' => $batch['semester'],
                            'subject_id' => $subject->id,
                            'class_name' => $batch['class_name'],
                            'allocation_type' => 'Classroom',
                        ], [
                            'faculty_id' => $facultyId,
                            'classroom_id' => $batchClassroom?->id,
                            'second_classroom_id' => null,
                            'division' => $batch['name'] ?? null,
                            'term' => $selection['term'] ?? null,
                            'academic_year' => $selection['academic_year'] ?? null,
                            'day' => '-',
                            'start_time' => null,
                            'end_time' => null,
                            'status' => $batchClassroom ? 'Allocated' : 'Unallocated',
                            'notes' => $batchClassroom?->room_number,
                            'student_count' => $studentStrength,
                        ]);

                        $created++;
                        if ($batchClassroom) {
                            $allocatedClassrooms++;
                        } else {
                            $unallocated++;
                        }
                    }

                    // 2. Lab -> Allocation Type = Lab (EXACTLY 2 Labs from Manage Classrooms)
                    if ($hasLab) {
                        $selectedLabs = collect();
                        if ($labs->count() >= 2) {
                            $selectedLabs->push($labs[$labIndex % $labs->count()]);
                            $selectedLabs->push($labs[($labIndex + 1) % $labs->count()]);
                            $labIndex += 2;
                        } elseif ($labs->count() === 1) {
                            $selectedLabs->push($labs[0]);
                        }

                        $lab1 = $selectedLabs->first();
                        $lab2 = $selectedLabs->skip(1)->first();
                        $isAllocated = ($lab1 && $lab2);
                        $labNotes = $isAllocated
                            ? "{$lab1->room_number} + {$lab2->room_number}"
                            : ($lab1?->room_number ?? null);

                        RoomAllocation::updateOrCreate([
                            'department_id' => $batch['department_id'],
                            'semester' => $batch['semester'],
                            'subject_id' => $subject->id,
                            'class_name' => $batch['class_name'],
                            'allocation_type' => 'Lab',
                        ], [
                            'faculty_id' => $facultyId,
                            'classroom_id' => $lab1?->id,
                            'second_classroom_id' => $lab2?->id,
                            'division' => $batch['name'] ?? null,
                            'term' => $selection['term'] ?? null,
                            'academic_year' => $selection['academic_year'] ?? null,
                            'day' => '-',
                            'start_time' => null,
                            'end_time' => null,
                            'status' => $isAllocated ? 'Allocated' : 'Unallocated',
                            'notes' => $labNotes,
                            'student_count' => $studentStrength,
                        ]);

                        $created++;
                        if ($isAllocated) {
                            $allocatedLabs += 2;
                        } else {
                            $unallocated++;
                        }
                    }
                }
            }
        });

        return compact('created', 'allocatedClassrooms', 'allocatedLabs', 'unallocated');
    }

    private function findFacultyId(Subject $subject, array $batch): ?int
    {
        if ($subject->faculty_id) {
            return $subject->faculty_id;
        }

        return Faculty::where('department_id', $batch['department_id'])->orderBy('name')->value('id');
    }

    private function studentStrength(string $department, string $semester, ?string $division): int
    {
        $query = User::query()
            ->whereNotNull('enrollment_number')
            ->where('department', $department)
            ->where('semester', $semester);
        if (filled($division)) {
            $query->where('divcon', $division);
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
