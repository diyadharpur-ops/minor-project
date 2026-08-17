<?php

$file = 'routes/web.php';
$content = file_get_contents($file);

$start = strpos($content, "Route::match(['get', 'post'], '/admin/classroom-allocation', function (Illuminate\Http\Request \$request) {");
if ($start === false) {
    die("Start not found\n");
}

$end = $start;
$braceCount = 0;
$foundFirstBrace = false;

for ($i = $start; $i < strlen($content); $i++) {
    if ($content[$i] === '{') {
        $braceCount++;
        $foundFirstBrace = true;
    } elseif ($content[$i] === '}') {
        $braceCount--;
    }
    
    if ($foundFirstBrace && $braceCount === 0) {
        $end = strpos($content, ';', $i);
        break;
    }
}

if ($end === false) {
    die("End not found\n");
}

$newLogic = <<<'PHP'
Route::match(['get', 'post'], '/admin/classroom-allocation', function (Illuminate\Http\Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $allocations = \App\Models\RoomAllocation::with(['department', 'subject', 'faculty', 'classroom'])
        ->orderBy('day')
        ->orderBy('start_time')
        ->paginate(10);

    $totalLectures = \App\Models\RoomAllocation::count();
    $allocatedCount = \App\Models\RoomAllocation::where('status', 'Allocated')->count();
    $unallocatedCount = \App\Models\RoomAllocation::where('status', 'Unallocated')->count();
    
    $allocationStatus = null;

    if ($request->isMethod('post')) {
        if ($request->input('form_type') === 'auto-allocate' || $request->input('form_type') === 're-generate') {
            
            // Re-generate or clear existing for a clean state
            \App\Models\RoomAllocation::query()->delete();

            // 1. Check if Subjects exist
            $subjectCountCheck = \App\Models\Subject::count();
            if ($subjectCountCheck === 0) {
                return back()->withErrors(['auto' => 'No subjects found for the selected academic year/semester.']);
            }

            // 2. Check if Classrooms and Labs exist
            $classrooms = \App\Models\Classroom::where('availability', 'Available')
                ->orderBy('room_capacity', 'asc')
                ->get();

            if ($classrooms->isEmpty()) {
                $totalRooms = \App\Models\Classroom::count();
                if ($totalRooms === 0) {
                    return back()->withErrors(['auto' => 'No classrooms available. Please add classrooms first.']);
                } else {
                    return back()->withErrors(['auto' => 'No available classrooms/labs found.']);
                }
            }

            // 3. Find base classes from Subjects
            $baseClasses = \App\Models\Subject::select('department_id', 'semester')
                ->groupBy('department_id', 'semester')
                ->get();
                
            if ($baseClasses->isEmpty()) {
                return back()->withErrors(['auto' => 'No subject groupings found.']);
            }

            $allocationGroups = [];

            // 4. Derive divisions from Student records if they exist
            foreach ($baseClasses as $base) {
                $dept = \App\Models\Department::find($base->department_id);
                if (!$dept) continue;

                // Check student records for divisions
                $divisions = \App\Models\User::whereNotNull('enrollment_number')
                    ->where('department', clone $dept->name)
                    ->where('semester', clone $base->semester)
                    ->whereNotNull('divcon')
                    ->where('divcon', '!=', '')
                    ->select('divcon')
                    ->groupBy('divcon')
                    ->pluck('divcon');

                if ($divisions->isEmpty()) {
                    // No explicit division found in students, use just Dept + Semester
                    $allocationGroups[] = [
                        'department_id' => $dept->id,
                        'department_name' => $dept->name,
                        'semester' => $base->semester,
                        'division' => null,
                        'class_name' => $dept->name . '-' . $base->semester
                    ];
                } else {
                    // Split into multiple class groups based on divisions
                    foreach ($divisions as $div) {
                        $allocationGroups[] = [
                            'department_id' => $dept->id,
                            'department_name' => $dept->name,
                            'semester' => $base->semester,
                            'division' => $div,
                            'class_name' => $dept->name . '-' . $base->semester . '-' . $div
                        ];
                    }
                }
            }

            \Illuminate\Support\Facades\Log::info('Derived Allocation Groups:', $allocationGroups);

            $facultyMap = \App\Models\Faculty::pluck('id', 'name')->toArray();

            $theorySlots = [
                ['start' => '10:30', 'end' => '11:30'],
                ['start' => '11:30', 'end' => '12:30'],
                ['start' => '13:00', 'end' => '14:00'],
                ['start' => '14:00', 'end' => '15:00'],
                ['start' => '15:10', 'end' => '16:10'],
                ['start' => '16:10', 'end' => '17:10'],
            ];

            $labSlots = [
                ['start' => '10:30', 'end' => '12:30'],
                ['start' => '13:00', 'end' => '15:00'],
                ['start' => '15:10', 'end' => '17:10'],
            ];

            $workingDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            $countAllocated = 0;
            $countUnallocated = 0;

            foreach ($allocationGroups as $group) {
                // 5. Calculate Student Strength
                $query = \App\Models\User::whereNotNull('enrollment_number')
                    ->where('department', $group['department_name'])
                    ->where('semester', $group['semester']);
                
                if ($group['division']) {
                    $query->where('divcon', $group['division']);
                }

                $studentStrength = $query->count();
                $capacityToUse = $studentStrength > 0 ? $studentStrength : 60; // Fallback capacity

                // 6. Find Subjects
                $subjects = \App\Models\Subject::where('department_id', $group['department_id'])
                    ->where('semester', $group['semester'])
                    ->get();

                foreach ($subjects as $subject) {
                    // 7. Find Faculty
                    $facultyId = null;
                    if (!empty($subject->faculty_name) && isset($facultyMap[$subject->faculty_name])) {
                        $facultyId = $facultyMap[$subject->faculty_name];
                    }

                    // 8. Read Subject Type and Credit
                    $isLab = str_contains(strtolower($subject->subject_type), 'lab') || str_contains(strtolower($subject->subject_type), 'practical');
                    $credit = max(1, (int) $subject->credit);
                    
                    // 9. Generate sessions automatically
                    $sessionsRequired = $isLab ? max(1, (int) round($credit / 2)) : $credit;
                    $slotsToTry = $isLab ? $labSlots : $theorySlots;

                    for ($i = 0; $i < $sessionsRequired; $i++) {
                        // 10. Generate days and distribute evenly
                        $dayCounts = [];
                        foreach ($workingDays as $d) {
                            $count = \App\Models\RoomAllocation::where('department_id', $group['department_id'])
                                ->where('semester', $group['semester'])
                                ->where('class_name', $group['class_name'])
                                ->where('subject_id', $subject->id)
                                ->where('day', $d)
                                ->count();
                            $dayCounts[$d] = $count;
                        }
                        asort($dayCounts); 

                        $allocatedRoom = null;
                        $allocatedSlot = null;
                        $allocatedDay = null;

                        foreach ($dayCounts as $day => $count) {
                            // 11. Generate time slots
                            foreach ($slotsToTry as $slot) {
                                $startTime = $slot['start'];
                                $endTime = $slot['end'];

                                // 12. Check faculty conflicts
                                if ($facultyId) {
                                    $facultyConflict = \App\Models\RoomAllocation::where('faculty_id', $facultyId)
                                        ->where('day', $day)
                                        ->where('status', '!=', 'Cancelled')
                                        ->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<', $endTime)
                                                ->where('end_time', '>', $startTime);
                                        })->exists();
                                    if ($facultyConflict) continue;
                                }

                                // 13. Check class conflicts
                                $classConflict = \App\Models\RoomAllocation::where('department_id', $group['department_id'])
                                    ->where('semester', $group['semester'])
                                    ->where('class_name', $group['class_name'])
                                    ->where('day', $day)
                                    ->where('status', '!=', 'Cancelled')
                                    ->where(function ($q) use ($startTime, $endTime) {
                                        $q->where('start_time', '<', $endTime)
                                            ->where('end_time', '>', $startTime);
                                    })->exists();
                                if ($classConflict) continue;

                                // 14. Check classroom/lab conflicts and capacity
                                $suitableRooms = $classrooms->filter(function ($room) use ($capacityToUse, $isLab, $day, $startTime, $endTime) {
                                    if ((int) $room->room_capacity < $capacityToUse) return false;

                                    $isRoomLab = str_contains(strtolower($room->room_type), 'lab');
                                    if ($isLab && !$isRoomLab) return false;
                                    if (!$isLab && $isRoomLab) return false;

                                    $roomConflict = \App\Models\RoomAllocation::where('classroom_id', $room->id)
                                        ->where('day', $day)
                                        ->where('status', '!=', 'Cancelled')
                                        ->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<', $endTime)
                                                ->where('end_time', '>', $startTime);
                                        })->exists();

                                    return !$roomConflict;
                                });

                                // 15. Allocate Classroom/Lab
                                if ($suitableRooms->isNotEmpty()) {
                                    $allocatedRoom = $suitableRooms->first();
                                    $allocatedSlot = $slot;
                                    $allocatedDay = $day;
                                    break;
                                }
                            }

                            if ($allocatedRoom) {
                                break;
                            }
                        }

                        // 16. Save Allocation
                        if ($allocatedRoom && $allocatedSlot && $allocatedDay) {
                            \App\Models\RoomAllocation::create([
                                'department_id' => $group['department_id'],
                                'semester' => $group['semester'],
                                'subject_id' => $subject->id,
                                'faculty_id' => $facultyId,
                                'classroom_id' => $allocatedRoom->id,
                                'class_name' => $group['class_name'],
                                'student_count' => $capacityToUse,
                                'day' => $allocatedDay,
                                'start_time' => $allocatedSlot['start'],
                                'end_time' => $allocatedSlot['end'],
                                'status' => 'Allocated',
                                'notes' => '-',
                            ]);
                            $countAllocated++;
                        } else {
                            \App\Models\RoomAllocation::create([
                                'department_id' => $group['department_id'],
                                'semester' => $group['semester'],
                                'subject_id' => $subject->id,
                                'faculty_id' => $facultyId,
                                'classroom_id' => null,
                                'class_name' => $group['class_name'],
                                'student_count' => $capacityToUse,
                                'day' => '-',
                                'start_time' => null,
                                'end_time' => null,
                                'status' => 'Unallocated',
                                'notes' => $isLab ? 'No lab available or slots full' : 'No classroom available or slots full',
                            ]);
                            $countUnallocated++;
                        }
                    }
                }
            }

            $actionName = $request->input('form_type') === 're-generate' ? 'Re-Generation' : 'Auto Allocation';
            $allocationStatus = "{$actionName} complete: {$countAllocated} allocated, {$countUnallocated} unallocated.";
            // 17. Display Allocation Results
            return redirect('/admin/classroom-allocation')->with('allocation_status', $allocationStatus);
        }
    }

    return view('admin.classrooms.allocation', [
        'allocations' => $allocations,
        'totalLectures' => $totalLectures,
        'allocatedCount' => $allocatedCount,
        'unallocatedCount' => $unallocatedCount,
    ]);
});
PHP;

$newContent = substr($content, 0, $start) . $newLogic . substr($content, $end + 1);

file_put_contents($file, $newContent);
echo "Successfully patched $file\n";
