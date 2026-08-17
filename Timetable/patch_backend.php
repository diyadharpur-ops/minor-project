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

    $allocations = \App\Models\RoomAllocation::with(['department', 'subject', 'classroom'])
        ->orderBy('id', 'desc')
        ->paginate(10);

    $totalSubjects = \App\Models\RoomAllocation::count();
    $allocatedClassroomCount = \App\Models\RoomAllocation::where('status', 'Allocated')
        ->whereHas('subject', function ($query) {
            $query->where('subject_type', 'not like', '%lab%')
                  ->where('subject_type', 'not like', '%practical%');
        })->count();
    $allocatedLabCount = \App\Models\RoomAllocation::where('status', 'Allocated')
        ->whereHas('subject', function ($query) {
            $query->where('subject_type', 'like', '%lab%')
                  ->orWhere('subject_type', 'like', '%practical%');
        })->count();
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

            $countAllocatedClassrooms = 0;
            $countAllocatedLabs = 0;
            $countUnallocated = 0;

            // To avoid assigning the same room to multiple subjects if possible
            // though without time, there's no real "conflict". We just assign one to each.
            foreach ($allocationGroups as $group) {
                // Calculate Student Strength
                $query = \App\Models\User::whereNotNull('enrollment_number')
                    ->where('department', clone $group['department_name'])
                    ->where('semester', clone $group['semester']);
                
                if ($group['division']) {
                    $query->where('divcon', clone $group['division']);
                }

                $studentStrength = $query->count();
                $capacityToUse = $studentStrength > 0 ? $studentStrength : 60; // Fallback capacity

                // Find Subjects
                $subjects = \App\Models\Subject::where('department_id', clone $group['department_id'])
                    ->where('semester', clone $group['semester'])
                    ->get();

                foreach ($subjects as $subject) {
                    $isLab = str_contains(strtolower($subject->subject_type), 'lab') || str_contains(strtolower($subject->subject_type), 'practical');
                    
                    // Filter suitable rooms
                    $suitableRooms = $classrooms->filter(function ($room) use ($capacityToUse, $isLab) {
                        if ((int) $room->room_capacity < $capacityToUse) return false;

                        $isRoomLab = str_contains(strtolower($room->room_type), 'lab');
                        if ($isLab && !$isRoomLab) return false;
                        if (!$isLab && $isRoomLab) return false;

                        return true;
                    });

                    // To add basic variance, we pick the first suitable, or maybe pick randomly
                    $allocatedRoom = $suitableRooms->first();

                    if ($allocatedRoom) {
                        \App\Models\RoomAllocation::create([
                            'department_id' => clone $group['department_id'],
                            'semester' => clone $group['semester'],
                            'subject_id' => $subject->id,
                            'faculty_id' => null, // No faculty logic
                            'classroom_id' => clone $allocatedRoom->id,
                            'class_name' => clone $group['class_name'],
                            'student_count' => $capacityToUse,
                            'day' => '-',
                            'start_time' => null,
                            'end_time' => null,
                            'status' => 'Allocated',
                            'notes' => '-',
                        ]);
                        if ($isLab) {
                            $countAllocatedLabs++;
                        } else {
                            $countAllocatedClassrooms++;
                        }
                    } else {
                        \App\Models\RoomAllocation::create([
                            'department_id' => clone $group['department_id'],
                            'semester' => clone $group['semester'],
                            'subject_id' => $subject->id,
                            'faculty_id' => null,
                            'classroom_id' => null,
                            'class_name' => clone $group['class_name'],
                            'student_count' => $capacityToUse,
                            'day' => '-',
                            'start_time' => null,
                            'end_time' => null,
                            'status' => 'Unallocated',
                            'notes' => '-',
                        ]);
                        $countUnallocated++;
                    }
                }
            }

            $actionName = $request->input('form_type') === 're-generate' ? 'Re-Generation' : 'Auto Allocation';
            $allocationStatus = "{$actionName} complete: {$countAllocatedClassrooms} Classroom(s) allocated, {$countAllocatedLabs} Lab(s) allocated, {$countUnallocated} unallocated.";
            // 17. Display Allocation Results
            return redirect('/admin/classroom-allocation')->with('allocation_status', $allocationStatus);
        }
    }

    return view('admin.classrooms.allocation', [
        'allocations' => $allocations,
        'totalLectures' => $totalSubjects, // Passing as totalLectures so we don't break UI vars unnecessarily
        'allocatedCount' => $allocatedClassroomCount,
        'allocatedLabCount' => $allocatedLabCount,
        'unallocatedCount' => $unallocatedCount,
    ]);
});
PHP;

// Wait, I should ensure I don't use "clone" on primitive strings or integers!
// PHP 8 throws TypeError. Let me strip out all "clone" keywords from $newLogic string before saving.
$newLogic = preg_replace('/clone\s+\$/', '$', $newLogic);

$newContent = substr($content, 0, $start) . $newLogic . substr($content, $end + 1);

file_put_contents($file, $newContent);
echo "Successfully patched $file\n";
