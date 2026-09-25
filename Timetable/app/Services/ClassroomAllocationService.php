<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClassroomAllocationService
{
    private const CLASSROOM_CAPACITY = 80;

    private const LAB_CAPACITY = 40;

    public function generateForSelection(array $selection): array
    {
        $department = Department::findOrFail($selection['department_id']);
        $className = $department->name.'-'.$selection['semester'].'-'.$selection['division'];
        $subjects = Subject::query()
            ->where('department_id', $department->id)
            ->where('semester', $selection['semester'])
            ->orderBy('name')
            ->get();

        return $this->generateGroups([[
            'department_id' => $department->id,
            'department_name' => $department->name,
            'semester' => $selection['semester'],
            'division' => $selection['division'],
            'class_name' => $className,
            'subjects' => $subjects,
        ]], [$className]);
    }

    public function generateAll(): array
    {
        $groups = collect();

        Subject::query()
            ->select(['department_id', 'semester'])
            ->whereNotNull('semester')
            ->distinct()
            ->get()
            ->each(function (Subject $subject) use ($groups): void {
                $department = Department::find($subject->department_id);

                if (! $department) {
                    return;
                }

                $divisions = User::query()
                    ->whereNotNull('enrollment_number')
                    ->where('department', $department->name)
                    ->where('semester', $subject->semester)
                    ->whereNotNull('divcon')
                    ->where('divcon', '!=', '')
                    ->select('divcon')
                    ->distinct()
                    ->orderBy('divcon')
                    ->pluck('divcon');

                if ($divisions->isEmpty()) {
                    $divisions = collect([null]);
                }

                foreach ($divisions as $division) {
                    $className = $department->name.'-'.$subject->semester.($division ? '-'.$division : '');
                    $groups->push([
                        'department_id' => $department->id,
                        'department_name' => $department->name,
                        'semester' => $subject->semester,
                        'division' => $division,
                        'class_name' => $className,
                        'subjects' => Subject::query()
                            ->where('department_id', $department->id)
                            ->where('semester', $subject->semester)
                            ->orderBy('name')
                            ->get(),
                    ]);
                }
            });

        return $this->generateGroups($groups->all(), $groups->pluck('class_name')->all(), true);
    }

    private function generateGroups(array $groups, array $classNames, bool $deleteAll = false): array
    {
        $rooms = Classroom::query()
            ->where('availability', 'Available')
            ->whereNotNull('room_number')
            ->orderBy('room_number')
            ->get();
        $classrooms = $rooms->filter(fn (Classroom $room): bool => $this->roomType($room->room_type) === 'Classroom')->values();
        $labs = $rooms->filter(fn (Classroom $room): bool => $this->roomType($room->room_type) === 'Lab')->values();
        $created = 0;
        $allocatedClassrooms = 0;
        $allocatedLabs = 0;
        $unallocated = 0;

        DB::transaction(function () use ($groups, $classNames, $deleteAll, $classrooms, $labs, &$created, &$allocatedClassrooms, &$allocatedLabs, &$unallocated): void {
            if ($deleteAll) {
                RoomAllocation::query()->delete();
            } else {
                RoomAllocation::query()->whereIn('class_name', $classNames)->delete();
            }

            foreach ($groups as $group) {
                $studentStrength = $this->studentStrength($group['department_name'], $group['semester'], $group['division']);
                $usedRoomIds = [];
                $classroomOffset = 0;
                $labOffset = 0;

                foreach ($group['subjects'] as $subject) {
                    $subjectType = $this->subjectType($subject->subject_type);
                    $allocationType = $subjectType === 'Lab' ? 'Lab' : 'Classroom';
                    $capacity = $allocationType === 'Lab' ? self::LAB_CAPACITY : self::CLASSROOM_CAPACITY;
                    $requiredRooms = $studentStrength > 0 ? (int) ceil($studentStrength / $capacity) : 0;
                    $roomPool = $allocationType === 'Lab' ? $labs : $classrooms;
                    $offset = $allocationType === 'Lab' ? $labOffset : $classroomOffset;
                    $selectedRooms = $this->selectRooms($roomPool, $requiredRooms, $offset, $usedRoomIds);
                    $isAllocated = $requiredRooms > 0 && $selectedRooms->count() === $requiredRooms;
                    $roomNumbers = $selectedRooms->pluck('room_number')->values();

                    if ($isAllocated) {
                        foreach ($selectedRooms as $room) {
                            $usedRoomIds[$room->id] = true;
                        }

                        if ($allocationType === 'Lab') {
                            $labOffset = ($labOffset + $requiredRooms) % max(1, $labs->count());
                            $allocatedLabs += $roomNumbers->count();
                        } else {
                            $classroomOffset = ($classroomOffset + $requiredRooms) % max(1, $classrooms->count());
                            $allocatedClassrooms += $roomNumbers->count();
                        }
                    } else {
                        $unallocated++;
                    }

                    RoomAllocation::create([
                        'department_id' => $group['department_id'],
                        'semester' => $group['semester'],
                        'subject_id' => $subject->id,
                        'faculty_id' => $subject->faculty_id,
                        'classroom_id' => $selectedRooms->first()?->id,
                        'class_name' => $group['class_name'],
                        'student_count' => $studentStrength,
                        'allocation_type' => $allocationType,
                        'day' => '-',
                        'start_time' => null,
                        'end_time' => null,
                        'status' => $isAllocated ? 'Allocated' : 'Unallocated',
                        'notes' => $roomNumbers->implode(', '),
                    ]);
                    $created++;
                }
            }
        });

        return compact('created', 'allocatedClassrooms', 'allocatedLabs', 'unallocated');
    }

    private function selectRooms(Collection $rooms, int $requiredRooms, int $offset, array $usedRoomIds): Collection
    {
        if ($requiredRooms === 0 || $rooms->isEmpty()) {
            return collect();
        }

        $ordered = $rooms->concat($rooms)->slice($offset, $rooms->count());

        return $ordered
            ->filter(fn (Classroom $room): bool => ! isset($usedRoomIds[$room->id]))
            ->take($requiredRooms)
            ->values();
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

    private function roomType(?string $value): string
    {
        return str_contains(strtolower(trim((string) $value)), 'lab') ? 'Lab' : 'Classroom';
    }
}
