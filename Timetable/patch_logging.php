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

            // 1. Find Students to derive class/division groups
            $studentCountCheck = \App\Models\User::whereNotNull('enrollment_number')->count();
            if ($studentCountCheck === 0) {
                return back()->withErrors(['auto' => 'No student data found for the selected academic year.']);
            }

            // Group students into logical classes
            $classes = \App\Models\User::select('department', 'semester', 'divcon')
                ->whereNotNull('enrollment_number')
                ->whereNotNull('department')
                ->whereNotNull('semester')
                ->groupBy('department', 'semester', 'divcon')
                ->get();

            \Illuminate\Support\Facades\Log::info('Detected Classes/Groups from Student Data:', $classes->toArray());

            // Check if Subjects exist
            $subjectCountCheck = \App\Models\Subject::count();
            if ($subjectCountCheck === 0) {
                return back()->withErrors(['auto' => 'No subjects found for the selected academic year/semester.']);
            }

            // Check if Classrooms and Labs exist
            $classrooms = \App\Models\Classroom::where('availability', 'Available')
                ->orderBy('room_capacity', 'asc')
                ->get();

            \Illuminate\Support\Facades\Log::info('Available Classrooms/Labs:', $classrooms->toArray());

            if ($classrooms->isEmpty()) {
                $totalRooms = \App\Models\Classroom::count();
                if ($totalRooms === 0) {
                    return back()->withErrors(['auto' => 'No classrooms available. Please add classrooms first.']);
                } else {
                    return back()->withErrors(['auto' => 'No available classrooms/labs found.']);
                }
            }

            $facultyMap = \App\Models\Faculty::pluck('id', 'name')->toArray();
            \Illuminate\Support\Facades\Log::info('Detected Faculties:', $facultyMap);

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

            foreach ($classes as $cls) {
                // 2. Calculate Student Strength
                $studentStrength = \App\Models\User::whereNotNull('enrollment_number')
                    ->where('department', $cls->department)
                    ->where('semester', $cls->semester)
                    ->where('divcon', $cls->divcon)
                    ->count();

                $capacityToUse = $studentStrength > 0 ? $studentStrength : 60; // Fallback capacity

                $className = $cls->department . '-' . $cls->semester;
                if (!empty($cls->divcon)) {
                    $className .= $cls->divcon;
                }

                \Illuminate\Support\Facades\Log::info("Group Processed: {$className}", ['student_count' => $capacityToUse]);

                $dept = \App\Models\Department::where('name', $cls->department)->orWhere('code', $cls->department)->first();
                if (!$dept) continue;

                // 3. Find Subjects
                $subjects = \App\Models\Subject::where('department_id', $dept->id)
                    ->where('semester', $cls->semester)
                    ->get();
                    
                \Illuminate\Support\Facades\Log::info("Detected Subjects for {$className}:", $subjects->toArray());

                foreach ($subjects as $subject) {
                    // 4. Find Faculty
                    $facultyId = null;
                    if (!empty($subject->faculty_name) && isset($facultyMap[$subject->faculty_name])) {
                        $facultyId = $facultyMap[$subject->faculty_name];
                    }

                    // 5. Read Subject Type and Credit
                    $isLab = str_contains(strtolower($subject->subject_type), 'lab') || str_contains(strtolower($subject->subject_type), 'practical');
                    $credit = max(1, (int) $subject->credit);
                    
                    // 6. Generate sessions automatically
                    $sessionsRequired = $isLab ? max(1, (int) round($credit / 2)) : $credit;
                    $slotsToTry = $isLab ? $labSlots : $theorySlots;

                    \Illuminate\Support\Facades\Log::info("Subject processing: {$subject->name}", [
                        'credit' => $credit,
                        'subject_type' => $subject->subject_type,
                        'sessions_required' => $sessionsRequired,
                        'faculty_id' => $facultyId
                    ]);

                    for ($i = 0; $i < $sessionsRequired; $i++) {
                        // 7. Generate days and distribute evenly
                        $dayCounts = [];
                        foreach ($workingDays as $d) {
                            $count = \App\Models\RoomAllocation::where('department_id', $dept->id)
                                ->where('semester', $cls->semester)
                                ->where('class_name', $className)
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
                            // 8. Generate time slots
                            foreach ($slotsToTry as $slot) {
                                $startTime = $slot['start'];
                                $endTime = $slot['end'];

                                // 9. Check faculty conflicts
                                if ($facultyId) {
                                    $facultyConflict = \App\Models\RoomAllocation::where('faculty_id', $facultyId)
                                        ->where('day', $day)
                                        ->where('status', '!=', 'Cancelled')
                                        ->where(function ($query) use ($startTime, $endTime) {
                                            $query->where('start_time', '<', $endTime)
                                                ->where('end_time', '>', $startTime);
                                        })->exists();
                                    if ($facultyConflict) continue;
                                }

                                // 10. Check class conflicts
                                $classConflict = \App\Models\RoomAllocation::where('department_id', $dept->id)
                                    ->where('semester', $cls->semester)
                                    ->where('class_name', $className)
                                    ->where('day', $day)
                                    ->where('status', '!=', 'Cancelled')
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where('start_time', '<', $endTime)
                                            ->where('end_time', '>', $startTime);
                                    })->exists();
                                if ($classConflict) continue;

                                // 11, 12, 13. Check classroom/lab conflicts and capacity
                                $suitableRooms = $classrooms->filter(function ($room) use ($capacityToUse, $isLab, $day, $startTime, $endTime) {
                                    if ((int) $room->room_capacity < $capacityToUse) return false;

                                    $isRoomLab = str_contains(strtolower($room->room_type), 'lab');
                                    if ($isLab && !$isRoomLab) return false;
                                    if (!$isLab && $isRoomLab) return false;

                                    $roomConflict = \App\Models\RoomAllocation::where('classroom_id', $room->id)
                                        ->where('day', $day)
                                        ->where('status', '!=', 'Cancelled')
                                        ->where(function ($query) use ($startTime, $endTime) {
                                            $query->where('start_time', '<', $endTime)
                                                ->where('end_time', '>', $startTime);
                                        })->exists();

                                    return !$roomConflict;
                                });

                                // 14. Allocate Classroom/Lab
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

                        // 15. Save Allocation
                        if ($allocatedRoom && $allocatedSlot && $allocatedDay) {
                            \App\Models\RoomAllocation::create([
                                'department_id' => $dept->id,
                                'semester' => $cls->semester,
                                'subject_id' => $subject->id,
                                'faculty_id' => $facultyId,
                                'classroom_id' => $allocatedRoom->id,
                                'class_name' => $className,
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
                                'department_id' => $dept->id,
                                'semester' => $cls->semester,
                                'subject_id' => $subject->id,
                                'faculty_id' => $facultyId,
                                'classroom_id' => null,
                                'class_name' => $className,
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

            // 16. Update summary cards (implicitly handled by reload)
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
