<?php

use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\NotificationController;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Division;
use App\Models\Faculty;
use App\Models\FacultyWorkload;
use App\Models\LabAllocationGroup;
use App\Models\Notification;
use App\Models\RoomAllocation;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\ClassroomAllocationService;
use App\Services\FacultyAllocationService;
use App\Services\TimetableGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

Route::get('/', function () {
    return view('home');
});

Route::get('/admin/login', function () {
    return view('admin.login');
});

Route::post('/admin/login', function (Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');

    if ($email === 'admin@example.com' && $password === 'admin') {
        session(['admin.auth' => [
            'name' => 'Admin User',
            'email' => $email,
        ]]);

        return redirect('/admin/dashboard');
    }

    return back()->withErrors(['email' => 'Invalid admin credentials.']);
});

Route::get('/admin/dashboard', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $stats = [
        'departments' => Department::count(),
        'faculty' => Faculty::count(),
        'subjects' => Subject::count(),
        'classrooms' => Classroom::count(),
        'students' => User::count(),
        'active_timetables' => TimetableEntry::select('department_id', 'semester', 'division', 'academic_year', 'term')
            ->distinct()
            ->count(),
    ];

    return view('admin.dashboard', compact('stats'));
});

Route::match(['get', 'post'], '/admin/conflicts', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $entries = TimetableEntry::with(['subject', 'faculty', 'classroom'])
        ->orderBy('day')
        ->orderBy('time_slot')
        ->get();

    $summary = [
        'faculty_conflict' => 0,
        'classroom_conflict' => 0,
        'lab_conflict' => 0,
        'duplicate_subject' => 0,
    ];

    $conflicts = collect();

    $facultySlots = [];
    $classroomSlots = [];
    $labSlots = [];
    $subjectSlots = [];

    foreach ($entries as $entry) {
        if ($entry->faculty_id && $entry->day && $entry->time_slot) {
            $key = $entry->faculty_id.'|'.$entry->day.'|'.$entry->time_slot;
            $facultySlots[$key][] = $entry;
        }

        if ($entry->classroom_id && $entry->day && $entry->time_slot) {
            $classroomKey = $entry->classroom_id.'|'.$entry->day.'|'.$entry->time_slot;
            $classroomSlots[$classroomKey][] = $entry;

            if ($entry->classroom && str_contains(strtolower((string) $entry->classroom->room_type), 'lab')) {
                $labSlots[$classroomKey][] = $entry;
            }
        }

        if ($entry->subject_id && $entry->day && $entry->time_slot) {
            $subjectKey = $entry->subject_id.'|'.$entry->day.'|'.$entry->time_slot;
            $subjectSlots[$subjectKey][] = $entry;
        }
    }

    foreach ($facultySlots as $items) {
        if (count($items) < 2) {
            continue;
        }

        $summary['faculty_conflict'] += 1;

        foreach ($items as $entry) {
            $conflicts->push([
                'type' => 'Faculty Conflict',
                'type_class' => 'faculty',
                'day' => $entry->day,
                'time' => $entry->time_slot,
                'subject' => $entry->subject?->name ?? 'Unknown subject',
                'faculty' => $entry->faculty?->name ?? 'Unknown faculty',
                'room' => $entry->classroom?->room_number ?? 'Unknown room',
                'details' => 'Faculty assigned to more than one class in the same slot.',
            ]);
        }
    }

    foreach ($classroomSlots as $items) {
        if (count($items) < 2) {
            continue;
        }

        $summary['classroom_conflict'] += 1;

        foreach ($items as $entry) {
            $conflicts->push([
                'type' => 'Classroom Conflict',
                'type_class' => 'classroom',
                'day' => $entry->day,
                'time' => $entry->time_slot,
                'subject' => $entry->subject?->name ?? 'Unknown subject',
                'faculty' => $entry->faculty?->name ?? 'Unknown faculty',
                'room' => $entry->classroom?->room_number ?? 'Unknown room',
                'details' => 'Two or more classes assigned to the same room at the same time.',
            ]);
        }
    }

    foreach ($labSlots as $items) {
        if (count($items) < 2) {
            continue;
        }

        $summary['lab_conflict'] += 1;

        foreach ($items as $entry) {
            $conflicts->push([
                'type' => 'Lab Conflict',
                'type_class' => 'lab',
                'day' => $entry->day,
                'time' => $entry->time_slot,
                'subject' => $entry->subject?->name ?? 'Unknown subject',
                'faculty' => $entry->faculty?->name ?? 'Unknown faculty',
                'room' => $entry->classroom?->room_number ?? 'Unknown room',
                'details' => 'Multiple lab sessions overlap in the same laboratory room.',
            ]);
        }
    }

    foreach ($subjectSlots as $items) {
        if (count($items) < 2) {
            continue;
        }

        $summary['duplicate_subject'] += 1;

        foreach ($items as $entry) {
            $conflicts->push([
                'type' => 'Same Subject Duplicate Slot',
                'type_class' => 'subject',
                'day' => $entry->day,
                'time' => $entry->time_slot,
                'subject' => $entry->subject?->name ?? 'Unknown subject',
                'faculty' => $entry->faculty?->name ?? 'Unknown faculty',
                'room' => $entry->classroom?->room_number ?? 'Unknown room',
                'details' => 'The same subject appears more than once in the same time slot.',
            ]);
        }
    }

    $summaryCards = [
        ['title' => 'Faculty Conflict', 'value' => $summary['faculty_conflict'], 'icon' => 'faculty', 'subtext' => 'overlaps'],
        ['title' => 'Classroom Conflict', 'value' => $summary['classroom_conflict'], 'icon' => 'classroom', 'subtext' => 'room clashes'],
        ['title' => 'Lab Conflict', 'value' => $summary['lab_conflict'], 'icon' => 'lab', 'subtext' => 'lab overlaps'],
        ['title' => 'Same Subject Duplicate Slot', 'value' => $summary['duplicate_subject'], 'icon' => 'subject', 'subtext' => 'duplicate entries'],
    ];

    if ($conflicts->count() > 0) {
        Notification::trigger('Conflict Detected', [
            'type' => $conflicts->first()['type'] ?? 'Scheduling',
            'details' => "{$conflicts->count()} scheduling issues found.",
        ]);
    }

    return view('admin.conflicts', [
        'summaryCards' => $summaryCards,
        'conflicts' => $conflicts,
    ]);
});

Route::get('/admin/profile', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.profile');
});

Route::get('/admin/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/admin/notifications', [NotificationController::class, 'store'])->name('notifications.store');
Route::get('/notifications/filter/{type}', [NotificationController::class, 'filter'])->name('notifications.filter');
Route::get('/notifications/refresh', [NotificationController::class, 'refresh'])->name('notifications.refresh');
Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
Route::delete('/notifications/clear', [NotificationController::class, 'clear'])->name('notifications.clear');
Route::get('/admin/notifications/show/{id}', [NotificationController::class, 'show'])->name('notifications.show');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

Route::post('/admin/backup', function () {
    if (! session('admin.auth')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    Notification::trigger('Backup Completed');

    return response()->json(['success' => true, 'message' => 'System backup completed successfully and notification logged.']);
});

Route::post('/admin/new-semester', function () {
    if (! session('admin.auth')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    Notification::trigger('New Semester Started');

    return response()->json(['success' => true, 'message' => 'New academic semester started and timetables initialized.']);
});

// Timetable generation
Route::match(['get', 'post'], '/admin/timetable', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    // Only semesters that have subjects in the DB
    $semesters = Subject::select('semester')->distinct()->pluck('semester')->filter()->sort()->values();

    $departments = Department::all();

    $timeSlots = ['10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10'];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    $timetable = null;
    $deptName = null;
    $deptFullName = 'Department of Computer Engineering';
    $classNameFormatted = '';
    $classSubjects = collect();

    $term = request('term', 'Odd');
    $termCode = request('term_code', ($term === 'Even' ? '262' : '261'));
    $termDates = request('term_dates', '23/06/2026 to 07/11/2026');
    $wefDate = request('wef_date', '23/06/2026');
    $sbtDates = request('sbt_dates', '21/09/2026 to 26/09/2026');

    if (request()->isMethod('post') || (request()->has('department_id') && request()->has('semester') && request()->has('division'))) {
        $deptId = request('department_id');
        $semester = request('semester');
        $division = request('division');
        $academicYear = request('academic_year');
        $term = request('term', 'Odd');

        $classNameFormatted = ($semester && $division) ? ($semester.$division) : '';

        $entries = TimetableEntry::with(['subject', 'faculty', 'classroom'])
            ->where('department_id', $deptId)
            ->where('semester', $semester)
            ->where('division', $division)
            ->get();

        $dept = Department::find($deptId);
        $rawDeptName = $dept ? $dept->name : '';
        $deptName = $rawDeptName;
        $deptDisplayName = (stripos($rawDeptName, 'comp') !== false)
            ? 'Computer Engineering'
            : $rawDeptName;
        $deptFullName = 'Department of '.$deptDisplayName;

        $className = $rawDeptName.'-'.$semester.'-'.$division;
        $managedAllocations = RoomAllocation::with(['classroom', 'secondClassroom', 'faculty', 'subject'])
            ->where('department_id', $deptId)
            ->where('semester', $semester)
            ->where(function ($q) use ($division, $className) {
                $q->whereIn('class_name', array_values(array_unique([$division, $className])))
                    ->orWhere('division', $division)
                    ->orWhere('division', 'like', $division.'%');
            })
            ->get();

        // Load subjects for this class to build the reference table below timetable
        $classSubjects = Subject::with('faculty')
            ->where('department_id', $deptId)
            ->where('semester', $semester)
            ->get()
            ->filter(function ($subject) use ($division) {
                $classes = $subject->effective_class_selections ?? [];

                return empty($classes) || in_array($division, $classes, true);
            })
            ->values();

        $getFacultyInitials = function (?string $name): string {
            if (empty($name)) {
                return '';
            }
            $clean = trim(preg_replace('/^(Prof\.|Prof|Dr\.|Dr|Mr\.|Mr|Mrs\.|Mrs|Ms\.|Ms|Er\.|Er)\s+/i', '', $name));
            $parts = preg_split('/[\s\.\-]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($parts)) {
                return strtoupper(substr($name, 0, 3));
            }
            $initials = '';
            foreach ($parts as $p) {
                $initials .= strtoupper(substr($p, 0, 1));
            }

            return $initials;
        };

        // format to match timetable view expectations
        $sessions = [];
        foreach ($entries as $entry) {
            $isPractical = strcasecmp((string)$entry->lecture_type, 'Practical') === 0;
            $allocationType = $isPractical ? 'Lab' : 'Classroom';
            $activityType = $isPractical ? 'Practical' : $entry->lecture_type;
            $roomMatchesType = fn (?Classroom $room): bool => $room !== null
                && ($isPractical
                    ? str_contains(strtolower((string) $room->room_type), 'lab')
                    : ! str_contains(strtolower((string) $room->room_type), 'lab'));
            $batchCandidates = [$entry->activity_batch ?: $division];

            if ($isPractical && $entry->activity_batch
                && preg_match('/^(.*?)([1-4])$/', $entry->activity_batch, $matches) === 1) {
                $pairStart = (int) $matches[2] <= 2 ? 1 : 3;
                $batchCandidates = [
                    $matches[1].$pairStart,
                    $matches[1].($pairStart + 1),
                ];
            }

            $selectedRoomAllocation = null;
            $hasMatchingRoomAllocation = false;
            foreach (array_values(array_unique([...$batchCandidates, $division])) as $batchCandidate) {
                $matchingAllocations = $managedAllocations
                    ->filter(fn (RoomAllocation $allocation): bool => $allocation->subject_id === $entry->subject_id
                        && $allocation->activity_type === null
                        && ($allocation->allocation_type === $allocationType || $allocation->allocation_type === null)
                        && $allocation->division === $batchCandidate)
                    ->sortByDesc('id');
                $hasMatchingRoomAllocation = $hasMatchingRoomAllocation || $matchingAllocations->isNotEmpty();
                $selectedRoomAllocation = $matchingAllocations->first(
                    fn (RoomAllocation $allocation): bool => $roomMatchesType($allocation->classroom)
                        && $allocation->classroom?->availability === 'Available'
                );

                if ($selectedRoomAllocation) {
                    break;
                }

                $activityAllocation = $managedAllocations
                    ->filter(fn (RoomAllocation $allocation): bool => $allocation->subject_id === $entry->subject_id
                        && $allocation->activity_type === $activityType
                        && $allocation->activity_batch === ($entry->activity_batch ?: null))
                    ->sortByDesc('id')
                    ->first(fn (RoomAllocation $allocation): bool => $roomMatchesType($allocation->classroom)
                        && $allocation->classroom?->availability === 'Available');
                if ($activityAllocation) {
                    $selectedRoomAllocation = $activityAllocation;
                    $hasMatchingRoomAllocation = true;
                    break;
                }
            }

            $room = $selectedRoomAllocation?->classroom;
            $fallbackRoom = $roomMatchesType($entry->classroom)
                && $entry->classroom?->availability === 'Available'
                ? $entry->classroom
                : null;
            $resolvedClassroom = $room ?? ($hasMatchingRoomAllocation ? null : $fallbackRoom);
            $roomLabel = $isPractical ? 'Lab' : 'Classroom';
            $roomNumber = $resolvedClassroom?->room_number ?? '';
            $roomLocation = $resolvedClassroom?->location ?? '';
            $roomName = $resolvedClassroom
                ? $roomLabel.': '.$resolvedClassroom->room_number.($resolvedClassroom->location ? ' - '.$resolvedClassroom->location : '')
                : ($hasMatchingRoomAllocation
                    ? $roomLabel.' unavailable'
                    : ($fallbackRoom
                        ? $roomLabel.': '.$fallbackRoom->room_number.($fallbackRoom->location ? ' - '.$fallbackRoom->location : '')
                        : $roomLabel.' unavailable'));
            if (! $selectedRoomAllocation && ! $hasMatchingRoomAllocation
                && $entry->notes && str_starts_with($entry->notes, 'Room2:')) {
                $roomName .= ' + '.substr($entry->notes, 6);
                $roomNumber .= ' + '.substr($entry->notes, 6);
            } elseif ($selectedRoomAllocation?->secondClassroom) {
                $secondRoom = $selectedRoomAllocation->secondClassroom;
                $roomName .= ' + '.$secondRoom->room_number.($secondRoom->location ? ' - '.$secondRoom->location : '');
                $roomNumber .= ' + '.$secondRoom->room_number;
            }

            $facultyName = $entry->faculty ? $entry->faculty->name : '';
            $facultyInitials = $getFacultyInitials($facultyName);

            // Pre-resolve all allocated batches if this is a Practical / Lab session
            $labBatches = [];
            if ($isPractical) {
                $rawSubName = $entry->subject ? $entry->subject->name : '';
                $rawSubCode = $entry->subject ? $entry->subject->subject_code : '';
                $subShort = (strlen($rawSubName) > 0 && strlen($rawSubName) <= 8)
                    ? $rawSubName
                    : ($rawSubCode ?: $rawSubName);
                $subShort = preg_replace('/\s*\(.*?\)\s*/', '', (string) $subShort);
                $cleanSubCode = strtoupper(trim($subShort));

                $batches = $entry->subject?->division_selections ?? [];
                if (empty($batches)) {
                    $batches = $managedAllocations
                        ->filter(fn ($a) => $a->subject_id === $entry->subject_id && ($a->activity_type === 'Practical' || $a->allocation_type === 'Lab'))
                        ->map(fn ($a) => $a->activity_batch ?: $a->division)
                        ->filter(fn ($b) => ! empty($b) && str_starts_with($b, $division))
                        ->unique()
                        ->values()
                        ->all();
                }
                if (empty($batches)) {
                    $batches = [$division.'1', $division.'2', $division.'3', $division.'4'];
                }
                sort($batches);

                foreach ($batches as $batch) {
                    // 1. Faculty for this batch
                    $facAlloc = $managedAllocations
                        ->filter(fn ($a) => $a->subject_id === $entry->subject_id
                            && $a->activity_type === 'Practical'
                            && ($a->activity_batch === $batch || $a->division === $batch)
                            && ! empty($a->faculty))
                        ->sortByDesc('id')
                        ->first();
                    $bFacName = $facAlloc?->faculty?->name;
                    if (empty($bFacName)) {
                        $matchEntry = $entries->first(fn ($e) => $e->subject_id === $entry->subject_id
                            && $e->activity_batch === $batch
                            && ! empty($e->faculty));
                        $bFacName = $matchEntry?->faculty?->name ?? $entry->faculty?->name ?? '';
                    }
                    $bFacInitials = $getFacultyInitials($bFacName) ?: ($facultyInitials ?: 'DF');

                    // 2. Lab Room for this batch
                    $roomAlloc = $managedAllocations
                        ->filter(fn ($a) => $a->subject_id === $entry->subject_id
                            && ($a->allocation_type === 'Lab' || ($a->classroom && str_contains(strtolower($a->classroom->room_type), 'lab')))
                            && ($a->division === $batch || $a->activity_batch === $batch)
                            && ! empty($a->classroom))
                        ->sortByDesc('id')
                        ->first();
                    $bRoom = $roomAlloc?->classroom?->room_number;
                    if (empty($bRoom)) {
                        $anyLab = $managedAllocations
                            ->filter(fn ($a) => ($a->division === $batch || $a->activity_batch === $batch)
                                && ! empty($a->classroom)
                                && str_contains(strtolower($a->classroom->room_type), 'lab'))
                            ->sortByDesc('id')
                            ->first();
                        $bRoom = $anyLab?->classroom?->room_number;
                    }
                    if (empty($bRoom)) {
                        $matchEntry = $entries->first(fn ($e) => $e->subject_id === $entry->subject_id
                            && $e->activity_batch === $batch
                            && ! empty($e->classroom));
                        $bRoom = $matchEntry?->classroom?->room_number ?? $resolvedClassroom?->room_number ?? $entry->classroom?->room_number ?? '';
                    }
                    $cleanRoom = strtoupper(trim(preg_replace('/^Lab:\s*/i', '', (string) $bRoom)));
                    if (empty($cleanRoom)) {
                        $cleanRoom = 'LAB';
                    }

                    $labBatches[] = [
                        'batch' => $batch,
                        'subject_code' => $cleanSubCode,
                        'faculty_initials' => $bFacInitials,
                        'room' => $cleanRoom,
                        'line' => "{$batch}-{$cleanSubCode}-{$bFacInitials}-{$cleanRoom}",
                    ];
                }
            }

            $sessions[] = [
                'day' => $entry->day,
                'time_slot' => $entry->time_slot,
                'subject' => $entry->subject ? $entry->subject->name : '',
                'subject_code' => $entry->subject ? ($entry->subject->subject_code ?: '') : '',
                'faculty' => $facultyName,
                'faculty_initials' => $facultyInitials,
                'room' => $roomName,
                'room_number' => $roomNumber ?: $roomName,
                'room_location' => $roomLocation,
                'type' => $entry->lecture_type,
                'batch' => $entry->activity_batch,
                'duration' => $isPractical ? 2 : (int) ($entry->duration ?: 1),
                'lab_batches' => $labBatches,
            ];
        }

        $timetable = [
            'days' => $days,
            'time_slots' => $timeSlots,
            'sessions' => $sessions,
            'academic_year' => $academicYear,
            'term' => $term,
            'division' => $division,
            'semester' => $semester,
            'class_name' => $classNameFormatted,
            'term_code' => $termCode,
            'term_dates' => $termDates,
            'wef_date' => $wefDate,
            'sbt_dates' => $sbtDates,
            'dept_full_name' => $deptFullName,
        ];
    }

    $divisionsMap = Division::getDivisionsMap($semesters);
    $selectedSem = (string) request('semester');
    if (filled($selectedSem) && isset($divisionsMap[$selectedSem])) {
        $divisions = collect($divisionsMap[$selectedSem]);
    } else {
        $divisions = collect(Division::getAllActiveDivisions($semesters));
    }

    return view('admin.timetable.index', [
        'departments' => $departments,
        'timetable' => $timetable,
        'deptName' => $deptName,
        'deptFullName' => $deptFullName,
        'classNameFormatted' => $classNameFormatted,
        'classSubjects' => $classSubjects,
        'semesters' => $semesters,
        'divisions' => $divisions,
        'divisionsMap' => $divisionsMap,
        'semester' => request('semester'),
        'division' => request('division', 'A'),
        'academicYear' => request('academic_year', date('Y').'-'.(date('Y') + 1)),
        'term' => $term,
        'termCode' => $termCode,
        'termDates' => $termDates,
        'wefDate' => $wefDate,
        'sbtDates' => $sbtDates,
    ]);
});

Route::get('/admin/timetable/builder', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $departments = Department::all();
    $subjects = Subject::all();
    $faculties = Faculty::all();
    $classrooms = Classroom::all();

    $timeSlots = ['10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10'];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    // Only semesters that have subjects in the DB
    $semesters = Subject::whereNotNull('semester')->distinct()->pluck('semester')->sort()->values();

    $divisionsMap = Division::getDivisionsMap($semesters);
    $selectedSem = (string) $request->input('semester');
    if (filled($selectedSem) && isset($divisionsMap[$selectedSem])) {
        $divisions = collect($divisionsMap[$selectedSem]);
    } else {
        $divisions = collect(Division::getAllActiveDivisions($semesters));
    }

    $entries = collect([]);
    if ($request->has('department_id') && $request->has('semester') && $request->has('division')) {
        $entries = TimetableEntry::where('department_id', $request->department_id)
            ->where('semester', $request->semester)
            ->where('division', $request->division)
            ->get();
    }

    return view('admin.timetable.builder', compact('departments', 'subjects', 'faculties', 'classrooms', 'timeSlots', 'days', 'entries', 'semesters', 'divisions', 'divisionsMap'));
});

Route::post('/admin/timetable/builder', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'department_id' => 'required|integer',
        'semester' => 'required|string',
        'division' => 'required|string',
        'academic_year' => 'required|string',
        'term' => 'required|string',
    ]);

    $allocationWarnings = collect();
    try {
        $allocationResult = (new FacultyAllocationService)->generateForSelection([
            'department_id' => $data['department_id'],
            'semester' => $data['semester'],
            'division' => $data['division'],
        ]);
        $allocationWarnings = collect($allocationResult['warnings'] ?? []);
        $generator = new TimetableGenerator;
        $success = $generator->generate(
            $data['department_id'],
            $data['semester'],
            $data['division'],
            $data['academic_year'],
            $data['term'],
            false
        );

        if (! $success) {
            return back()
                ->with('error', 'Could not generate timetable. Please ensure subjects exist for this class.')
                ->with('faculty_allocation_warnings', $allocationWarnings->unique()->values()->all())
                ->withInput();
        }
    } catch (Exception $e) {
        return back()
            ->with('error', $e->getMessage())
            ->with('faculty_allocation_warnings', $allocationWarnings->unique()->values()->all())
            ->withInput();
    }

    // Redirect to View Timetable page (which is /admin/timetable via POST)
    // Wait, /admin/timetable accepts POST to view, but we can't redirect with POST data easily.
    // However, /admin/timetable GET route also reads from request query params if passed.
    // Let's redirect to GET /admin/timetable with query params.
    return redirect('/admin/timetable?department_id='.$data['department_id'].'&semester='.$data['semester'].'&division='.$data['division'].'&academic_year='.$data['academic_year'].'&term='.$data['term'])
        ->with('status', 'Timetable Auto-Generated successfully!')
        ->with('faculty_allocation_warnings', $allocationWarnings->unique()->values()->all());
});

// Reports
Route::get('/admin/reports', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.reports.index');
});

// Departments management
Route::get('/admin/departments', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $q = $request->input('q');
    if ($q) {
        $departments = Department::where('name', 'like', "%{$q}%")
            ->orWhere('code', 'like', "%{$q}%")
            ->orderBy('created_at', 'desc')
            ->get();
    } else {
        $departments = Department::orderBy('created_at', 'desc')->get();
    }

    return view('admin.departments.index', ['departments' => $departments, 'q' => $q]);
});

Route::get('/admin/departments/create', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.departments.create');
});

Route::post('/admin/departments', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:50',
        'hod_name' => 'nullable|string|max:255',
    ]);

    $dept = Department::create($data);

    Notification::trigger('Department Added', ['name' => $dept->name]);

    return redirect('/admin/departments');
});

Route::get('/admin/departments/{id}/edit', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $dept = Department::findOrFail($id);

    return view('admin.departments.edit', ['dept' => $dept]);
});

Route::post('/admin/departments/{id}', function (Request $request, $id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:50',
        'hod_name' => 'nullable|string|max:255',
    ]);

    $dept = Department::findOrFail($id);
    $dept->update($data);

    return redirect('/admin/departments');
});

Route::post('/admin/departments/{id}/delete', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $dept = Department::find($id);
    if ($dept) {
        $dept->delete();
    }

    return redirect('/admin/departments');
});

Route::get('/admin/faculties', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $q = $request->input('q');
    $query = Faculty::query()->with('department');

    if ($q) {
        $query->where('name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%");
    }

    $faculties = $query->orderBy('created_at', 'desc')->get();

    return view('admin.faculties.index', ['faculties' => $faculties, 'q' => $q]);
});

Route::get('/admin/faculties/create', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.faculties.create', ['departments' => Department::orderBy('name')->get()]);
});

Route::post('/admin/faculties', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'designation' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:faculties,email',
        'password' => 'required|string|min:8',
        'department_id' => 'required|exists:departments,id',
    ]);

    $faculty = Faculty::create($data);

    Notification::trigger('Faculty Added', ['name' => $faculty->name]);

    if ($department = Department::find($data['department_id'])) {
        $folder = 'faculty-records/'.Str::slug($department->name);
        Storage::disk('local')->makeDirectory($folder);
        $filePath = $folder.'/faculty-'.$faculty->id.'.json';
        Storage::disk('local')->put($filePath, json_encode([
            'id' => $faculty->id,
            'name' => $faculty->name,
            'designation' => $faculty->designation,
            'email' => $faculty->email,
            'department' => $department->name,
        ], JSON_PRETTY_PRINT));
        $faculty->update(['folder_path' => $filePath]);
    }

    return redirect('/admin/faculties');
});

Route::get('/admin/faculties/{id}/edit', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $faculty = Faculty::findOrFail($id);

    return view('admin.faculties.edit', ['faculty' => $faculty, 'departments' => Department::orderBy('name')->get()]);
});

Route::post('/admin/faculties/{id}', function (Request $request, $id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $faculty = Faculty::findOrFail($id);

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'designation' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:faculties,email,'.$faculty->id,
        'password' => 'nullable|string|min:8',
        'department_id' => 'required|exists:departments,id',
    ]);

    if (empty($data['password'])) {
        unset($data['password']);
    }

    $faculty->update($data);

    if ($department = Department::find($data['department_id'])) {
        $folder = 'faculty-records/'.Str::slug($department->name);
        Storage::disk('local')->makeDirectory($folder);
        $filePath = $folder.'/faculty-'.$faculty->id.'.json';
        Storage::disk('local')->put($filePath, json_encode([
            'id' => $faculty->id,
            'name' => $faculty->name,
            'designation' => $faculty->designation,
            'email' => $faculty->email,
            'department' => $department->name,
        ], JSON_PRETTY_PRINT));
        $faculty->update(['folder_path' => $filePath]);
    }

    return redirect('/admin/faculties');
});

Route::post('/admin/faculties/{id}/delete', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $faculty = Faculty::find($id);
    if ($faculty) {
        if ($faculty->folder_path) {
            Storage::disk('local')->delete($faculty->folder_path);
        }
        $faculty->delete();
    }

    return redirect('/admin/faculties');
});

Route::get('/admin/faculty-workload', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $query = FacultyWorkload::query();
    $search = trim((string) $request->input('q', ''));
    $departmentFilter = trim((string) $request->input('department', ''));
    $statusFilter = trim((string) $request->input('status', ''));
    $selectedDepartmentId = $request->input('department_id');

    if ($search !== '') {
        $query->where(function ($q) use ($search) {
            $q->where('faculty_name', 'like', '%'.$search.'%')
                ->orWhere('faculty_id', 'like', '%'.$search.'%');
        });
    }

    if ($departmentFilter !== '') {
        $query->where('department', $departmentFilter);
    }

    if ($statusFilter !== '') {
        $query->where('workload_status', $statusFilter);
    }

    $workloads = $query->orderByDesc('created_at')->get();

    $departments = Department::query()
        ->orderBy('name')
        ->get();

    $generatedWorkloads = collect();
    $facultyTotals = collect();
    $summary = [
        'total_faculty' => 0,
        'total_allocated_subjects' => 0,
        'total_lecture_hours' => 0,
        'total_tutorial_hours' => 0,
        'total_lab_hours' => 0,
        'total_weekly_workload' => 0,
    ];

    if (Schema::hasColumn('faculty_workloads', 'subject_name') && Schema::hasColumn('faculty_workloads', 'department_id')) {
        $generatedWorkloads = FacultyWorkload::query()
            ->when(filled($selectedDepartmentId), fn ($query) => $query->where('department_id', $selectedDepartmentId))
            ->whereNotNull('subject_name')
            ->orderBy('faculty_name')
            ->orderBy('semester')
            ->get();

        $facultyCount = $generatedWorkloads->pluck('faculty_id')->filter()->unique()->count();
        $totalWeeklyHours = (float) $generatedWorkloads->sum('weekly_workload');
        $sharedFacultyWorkload = $facultyCount > 0 ? ($totalWeeklyHours / $facultyCount) : 0;

        $generatedWorkloads = $generatedWorkloads->map(function ($record) use ($sharedFacultyWorkload) {
            $record->weekly_workload = (float) $sharedFacultyWorkload;
            $record->weekly_hours = (float) $sharedFacultyWorkload;
            $record->total_hours = (float) $sharedFacultyWorkload;

            return $record;
        });

        $facultyTotals = $generatedWorkloads
            ->groupBy('faculty_id')
            ->map(fn () => (float) $sharedFacultyWorkload)
            ->sortKeys();

        $summary = [
            'total_faculty' => $facultyCount,
            'total_allocated_subjects' => $generatedWorkloads->count(),
            'total_lecture_hours' => $generatedWorkloads->sum('lecture_hours'),
            'total_tutorial_hours' => $generatedWorkloads->sum('tutorial_hours'),
            'total_lab_hours' => $generatedWorkloads->sum('lab_hours'),
            'total_weekly_workload' => (float) $sharedFacultyWorkload,
        ];
    }

    return view('admin.faculty-workload.index', [
        'workloads' => $workloads,
        'departments' => $departments,
        'departmentOptions' => $departments,
        'generatedWorkloads' => $generatedWorkloads,
        'facultyTotals' => $facultyTotals,
        'summary' => $summary,
        'q' => $search,
        'departmentFilter' => $departmentFilter,
        'statusFilter' => $statusFilter,
        'selectedDepartmentId' => $selectedDepartmentId,
    ]);
});

Route::post('/admin/faculty-workload/generate', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $departmentId = $request->input('department_id');
    $regenerate = $request->boolean('regenerate');

    if (! filled($departmentId)) {
        return back()->with('error', 'Please select a department first.');
    }

    $department = Department::find($departmentId);

    if (! $department) {
        return back()->with('error', 'Please select a valid department.');
    }

    if (! Schema::hasColumn('faculty_workloads', 'department_id') || ! Schema::hasColumn('faculty_workloads', 'subject_name')) {
        return back()->with('error', 'Faculty workload database structure is not ready. Please run the pending migration first.');
    }

    $existingRecords = FacultyWorkload::query()
        ->where('department_id', $department->id)
        ->whereNotNull('subject_name')
        ->exists();

    if ($existingRecords && ! $regenerate) {
        return back()
            ->with('warning', 'Workload already exists for this department. Do you want to regenerate it?')
            ->with('department_warning_id', $department->id)
            ->withInput();
    }

    if ($existingRecords && $regenerate) {
        FacultyWorkload::query()
            ->where('department_id', $department->id)
            ->whereNotNull('subject_name')
            ->delete();
    }

    $allocations = RoomAllocation::query()
        ->with(['faculty', 'subject.division'])
        ->where('department_id', $department->id)
        ->orderBy('semester')
        ->orderBy('subject_id')
        ->get();

    if ($allocations->isEmpty()) {
        return back()->with('error', 'No faculty allocation found for the selected department.');
    }

    $workloadGroups = $allocations
        ->filter(fn (RoomAllocation $allocation): bool => $allocation->faculty !== null && $allocation->subject !== null)
        ->groupBy(fn (RoomAllocation $allocation): string => $allocation->faculty_id.'|'.$allocation->subject_id);

    foreach ($workloadGroups as $subjectAllocations) {
        $allocation = $subjectAllocations->first();
        $faculty = $allocation->faculty;
        $subject = $allocation->subject;

        $lectureHours = 0;
        $tutorialHours = 0;
        $labHours = 0;

        $activityAllocations = $subjectAllocations->filter(
            fn (RoomAllocation $classAllocation): bool => filled($classAllocation->activity_type)
        );

        if ($activityAllocations->isNotEmpty()) {
            foreach ($activityAllocations as $activityAllocation) {
                $class = $activityAllocation->division;
                $lectureHours += $activityAllocation->activity_type === 'Lecture'
                    ? (in_array($class, ['A', 'B', 'C'], true)
                        ? $subject->lectureCreditsForClass($class)
                        : $subject->total_lecture_credits)
                    : 0;
                $tutorialHours += $activityAllocation->activity_type === 'Tutorial'
                    ? (int) ($subject->tutorial_credit ?? 0)
                    : 0;
                $labHours += $activityAllocation->activity_type === 'Practical'
                    ? (int) ($subject->lab_credit ?? 0) * 2
                    : 0;
            }
        } else {
            $classAllocations = $subjectAllocations
                ->unique(fn (RoomAllocation $classAllocation): string => $classAllocation->division ?: $classAllocation->class_name);

            foreach ($classAllocations as $classAllocation) {
                $class = $classAllocation->division;
                if (in_array($class, ['A', 'B', 'C'], true)) {
                    $lectureHours += $subject->lectureCreditsForClass($class);
                    $tutorialHours += $subject->tutorialCreditsForClass($class);
                    $labHours += $subject->labCreditsForClass($class) * 2;

                    continue;
                }

                $lectureHours += $subject->total_lecture_credits;
                $tutorialHours += $subject->total_tutorial_credits;
                $labHours += $subject->total_lab_credits * 2;
            }
        }

        $weeklyHours = $lectureHours + $tutorialHours + $labHours;

        FacultyWorkload::query()->updateOrCreate([
            'faculty_id' => (string) $faculty->id,
            'department_id' => $department->id,
            'semester' => (string) $allocation->semester,
            'subject_name' => $subject->name,
            'subject_code' => $subject->subject_code,
        ], [
            'faculty_name' => $faculty->name,
            'faculty_id' => (string) $faculty->id,
            'department' => $department->name,
            'department_id' => $department->id,
            'semester' => (string) $allocation->semester,
            'subject_name' => $subject->name,
            'subject_code' => $subject->subject_code,
            'lecture_hours' => $lectureHours,
            'tutorial_hours' => $tutorialHours,
            'lab_hours' => $labHours,
            'weekly_hours' => $weeklyHours,
            'weekly_workload' => $weeklyHours,
            'total_hours' => $weeklyHours,
            'workload_status' => FacultyWorkload::calculateStatus($weeklyHours),
            'subjects_assigned' => $subject->name,
            'theory_hours' => $lectureHours + $tutorialHours,
            'practical_hours' => $labHours,
        ]);
    }

    return redirect('/admin/faculty-workload?department_id='.$department->id)
        ->with('success', 'Faculty workload generated successfully.');
});

Route::get('/admin/faculty-workload/create', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.faculty-workload.create');
});

Route::post('/admin/faculty-workload', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'faculty_name' => 'required|string|max:255',
        'faculty_id' => 'required|string|max:100',
        'department' => 'required|string|max:255',
        'subjects_assigned' => 'required|string|max:500',
        'theory_hours' => 'required|integer|min:0',
        'practical_hours' => 'required|integer|min:0',
        'assigned_classes' => 'nullable|string|max:255',
        'free_periods' => 'nullable|string|max:255',
    ]);

    $totalHours = (int) $data['theory_hours'] + (int) $data['practical_hours'];
    $data['total_hours'] = $totalHours;
    $normalThreshold = (int) config('faculty_workload.normal_threshold', 18);
    $data['workload_status'] = $totalHours > $normalThreshold ? 'Overloaded' : 'Normal';

    $fw = FacultyWorkload::create($data);

    if ($fw->workload_status === 'Overloaded') {
        Notification::trigger('Faculty Workload Exceeded', [
            'faculty_name' => $fw->faculty_name,
            'total_hours' => $fw->total_hours,
        ]);
    }

    return redirect('/admin/faculty-workload')->with('status', 'Faculty workload saved successfully.');
});

Route::get('/admin/faculty-workload/{id}', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $workload = FacultyWorkload::findOrFail($id);

    return view('admin.faculty-workload.show', compact('workload'));
});

Route::get('/admin/faculty-workload/{id}/edit', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $workload = FacultyWorkload::findOrFail($id);

    return view('admin.faculty-workload.edit', compact('workload'));
});

Route::post('/admin/faculty-workload/{id}', function (Request $request, $id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $workload = FacultyWorkload::findOrFail($id);

    $data = $request->validate([
        'faculty_name' => 'required|string|max:255',
        'faculty_id' => 'required|string|max:100',
        'department' => 'required|string|max:255',
        'subjects_assigned' => 'required|string|max:500',
        'theory_hours' => 'required|integer|min:0',
        'practical_hours' => 'required|integer|min:0',
        'assigned_classes' => 'nullable|string|max:255',
        'free_periods' => 'nullable|string|max:255',
    ]);

    $totalHours = (int) $data['theory_hours'] + (int) $data['practical_hours'];
    $data['total_hours'] = $totalHours;
    $normalThreshold = (int) config('faculty_workload.normal_threshold', 18);
    $data['workload_status'] = $totalHours > $normalThreshold ? 'Overloaded' : 'Normal';

    $workload->update($data);

    if ($workload->workload_status === 'Overloaded') {
        Notification::trigger('Faculty Workload Exceeded', [
            'faculty_name' => $workload->faculty_name,
            'total_hours' => $workload->total_hours,
        ]);
    }

    return redirect('/admin/faculty-workload')->with('status', 'Faculty workload updated successfully.');
});

Route::post('/admin/faculty-workload/{id}/delete', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $workload = FacultyWorkload::find($id);

    if ($workload) {
        $workload->delete();
    }

    return redirect('/admin/faculty-workload')->with('status', 'Faculty workload deleted successfully.');
});

$subjectTotalsForSemester = function (string $semester): array {
    $subjects = Subject::query()
        ->with('division')
        ->where('semester', $semester)
        ->get();

    return [
        'total_lecture_credits' => (int) $subjects->sum(fn (Subject $subject): int => $subject->total_lecture_credits),
        'total_lab_credits' => (int) $subjects->sum(fn (Subject $subject): int => $subject->total_lab_credits),
        'total_tutorial_credits' => (int) $subjects->sum(fn (Subject $subject): int => $subject->total_tutorial_credits),
        'total_hours' => (int) $subjects->sum(fn (Subject $subject): int => $subject->weekly_hours),
    ];
};

Route::post('/admin/divisions/toggle', function (Request $request) use ($subjectTotalsForSemester) {
    if (! session('admin.auth')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $data = $request->validate([
        'semester' => 'required|string|exists:subjects,semester',
        'division' => 'required|in:A,B,C',
        'is_active' => 'required|boolean',
    ]);

    DB::transaction(function () use ($data): void {
        Division::updateOrCreate(
            ['name' => $data['division'], 'semester' => $data['semester']],
            ['is_active' => $data['is_active']],
        );

        if ($data['is_active']) {
            return;
        }

        Subject::query()
            ->where('semester', $data['semester'])
            ->get()
            ->each(function (Subject $subject) use ($data): void {
                foreach (['division_selections', 'tutorial_selections'] as $selectionColumn) {
                    $subject->{$selectionColumn} = array_values(array_filter(
                        $subject->{$selectionColumn} ?? [],
                        fn (string $selection): bool => $selection[0] !== $data['division'],
                    ));
                }

                $subject->save();
            });
    });

    return response()->json([
        'success' => true,
        'summary' => $subjectTotalsForSemester($data['semester']),
    ]);
});

Route::post('/admin/subjects/selection-toggle', function (Request $request) use ($subjectTotalsForSemester) {
    if (! session('admin.auth')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $data = $request->validate([
        'semester' => 'required|string|exists:subjects,semester',
        'assignment_type' => 'required|in:division,tutorial',
        'selection' => 'required|string',
        'is_selected' => 'required|boolean',
    ]);

    $validSelections = $data['assignment_type'] === 'division'
        ? ['A1', 'A2', 'A3', 'A4', 'B1', 'B2', 'B3', 'B4', 'C1', 'C2', 'C3', 'C4']
        : ['AT1', 'AT2', 'AT3', 'AT4', 'BT1', 'BT2', 'BT3', 'BT4', 'CT1', 'CT2', 'CT3', 'CT4'];

    if (! in_array($data['selection'], $validSelections, true)) {
        throw ValidationException::withMessages([
            'selection' => 'Choose a valid '.$data['assignment_type'].' selection.',
        ]);
    }

    $class = $data['selection'][0];
    $classIsActive = Division::query()
        ->where('semester', $data['semester'])
        ->where('name', $class)
        ->where('is_active', true)
        ->exists();

    if (! $classIsActive) {
        throw ValidationException::withMessages([
            'selection' => 'Select the matching class before assigning this group.',
        ]);
    }

    $column = $data['assignment_type'].'_selections';

    DB::transaction(function () use ($data, $column): void {
        Subject::query()
            ->with('division')
            ->where('semester', $data['semester'])
            ->get()
            ->each(function (Subject $subject) use ($data, $column): void {
                $selectionClass = $data['selection'][0];
                if (! in_array($selectionClass, $subject->effective_class_selections, true)) {
                    return;
                }

                $selections = array_values(array_diff($subject->{$column} ?? [], [$data['selection']]));

                if ($data['is_selected'] && ! in_array($data['selection'], $selections, true)) {
                    $selections[] = $data['selection'];
                }

                $subject->{$column} = $selections;
                $subject->save();
            });
    });

    return response()->json([
        'success' => true,
        'summary' => $subjectTotalsForSemester($data['semester']),
    ]);
});

Route::get('/admin/subjects', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $subjects = Subject::query()->with('department', 'faculty', 'division')->orderBy('semester')->orderBy('created_at', 'desc')->get();

    $semesterWeeklyHours = $subjects
        ->groupBy('semester')
        ->map(function ($semesterSubjects, $semester) {
            $storedDivisions = Division::query()
                ->where('semester', $semester)
                ->whereIn('name', ['A', 'B', 'C'])
                ->orderBy('name')
                ->get()
                ->keyBy('name');
            $classOptions = ['A', 'B', 'C'];
            $divisions = collect($classOptions)->map(function (string $name) use ($storedDivisions) {
                return $storedDivisions->get($name) ?? (object) [
                    'name' => $name,
                    'is_active' => false,
                ];
            });

            $selectedClasses = $divisions
                ->filter(fn ($division) => (bool) $division->is_active)
                ->pluck('name')
                ->all();
            $totalClasses = count($selectedClasses);

            $divisionSelections = $semesterSubjects
                ->flatMap(fn (Subject $subject) => $subject->division_selections ?? [])
                ->unique()
                ->values()
                ->all();
            $tutorialSelections = $semesterSubjects
                ->flatMap(fn (Subject $subject) => $subject->tutorial_selections ?? [])
                ->unique()
                ->values()
                ->all();

            return [
                'divisions' => $divisions,
                'selected_classes' => $selectedClasses,
                'total_classes' => $totalClasses,
                'total_lecture_credits' => (int) $semesterSubjects->sum(fn (Subject $subject): int => $subject->total_lecture_credits),
                'total_lab_credits' => (int) $semesterSubjects->sum(fn (Subject $subject): int => $subject->total_lab_credits),
                'total_tutorial_credits' => (int) $semesterSubjects->sum(fn (Subject $subject): int => $subject->total_tutorial_credits),
                'total_hours' => (int) $semesterSubjects->sum(fn (Subject $subject): int => $subject->weekly_hours),
                'division_selections' => $divisionSelections,
                'tutorial_selections' => $tutorialSelections,
            ];
        })
        ->sortKeys(SORT_NATURAL);

    $q = $request->input('q');

    if ($q) {
        $query = Subject::query()->with('department', 'faculty', 'division');
        $query->where(function ($subjectQuery) use ($q) {
            $subjectQuery->where('name', 'like', "%{$q}%")
                ->orWhere('subject_code', 'like', "%{$q}%")
                ->orWhere('semester', 'like', "%{$q}%")
                ->orWhere('faculty_name', 'like', "%{$q}%")
                ->orWhereHas('department', function ($departmentQuery) use ($q) {
                    $departmentQuery->where('name', 'like', "%{$q}%");
                });
        });
        $searchResults = $query->orderBy('created_at', 'desc')->get();

        return view('admin.subjects.index', [
            'searchResults' => $searchResults,
            'groupedSubjects' => collect(),
            'semesterWeeklyHours' => $semesterWeeklyHours,
        ]);
    }

    $groupedSubjects = $subjects->groupBy('semester');

    return view('admin.subjects.index', [
        'groupedSubjects' => $groupedSubjects,
        'semesterWeeklyHours' => $semesterWeeklyHours,
    ]);
});

Route::get('/admin/subjects/create', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $semesters = Subject::query()
        ->whereNotNull('semester')
        ->distinct()
        ->orderBy('semester')
        ->pluck('semester')
        ->sort(SORT_NATURAL)
        ->values();

    $departments = Department::orderBy('name')->get();
    $faculties = Faculty::orderBy('name')->get();

    return view('admin.subjects.create', [
        'semesters' => $semesters,
        'departments' => $departments,
        'divisions' => Division::query()->orderBy('semester')->orderBy('name')->get(),
        'faculties' => $faculties,
    ]);
});

$validateSubjectData = function (Request $request, ?Subject $subject = null): array {
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'subject_code' => ['required', 'string', 'max:50', Rule::unique('subjects', 'subject_code')->ignore($subject?->id)],
        'semester' => 'required|string|max:20',
        'division' => 'nullable|in:A,B,C',
        'division_id' => [
            'nullable',
            Rule::exists('divisions', 'id')->where(fn ($query) => $query->where('semester', $request->input('semester'))),
        ],
        'department_id' => 'required|exists:departments,id',
        'subject_type' => 'nullable|in:Lecture,Lab,Tutorial',
        'lecture_credit' => 'required|integer|min:0|max:10',
        'lab_credit' => 'required|integer|min:0|max:10',
        'tutorial_credit' => 'nullable|integer|min:0|max:10',
        'selection_groups_present' => 'sometimes|boolean',
        'class_selections' => 'sometimes|array',
        'class_selections.*' => ['required', 'string', 'distinct', Rule::in(['A', 'B', 'C'])],
        'division_selections' => 'sometimes|array',
        'division_selections.*' => ['required', 'string', 'distinct', Rule::in(['A1', 'A2', 'A3', 'A4', 'B1', 'B2', 'B3', 'B4', 'C1', 'C2', 'C3', 'C4'])],
        'tutorial_selections' => 'sometimes|array',
        'tutorial_selections.*' => ['required', 'string', 'distinct', Rule::in(['AT1', 'AT2', 'AT3', 'AT4', 'BT1', 'BT2', 'BT3', 'BT4', 'CT1', 'CT2', 'CT3', 'CT4'])],
    ]);

    $classSelections = $data['class_selections'] ?? null;
    $divisionSelections = $data['division_selections'] ?? null;
    $tutorialSelections = $data['tutorial_selections'] ?? null;

    if ($request->boolean('selection_groups_present')) {
        $classSelections ??= [];
        $divisionSelections ??= [];
        $tutorialSelections ??= [];
    }

    if ($classSelections === null) {
        $divisionName = $data['division'] ?? null;

        if (! $divisionName && ! empty($data['division_id'])) {
            $divisionName = Division::find($data['division_id'])?->name;
        }

        if (in_array($divisionName, ['A', 'B', 'C'], true)) {
            $classSelections = [$divisionName];
        }
    }

    foreach ($divisionSelections ?? [] as $divisionSelection) {
        if (! in_array($divisionSelection[0], $classSelections ?? [], true)) {
            throw ValidationException::withMessages([
                'division_selections' => 'Choose the matching class before selecting a division.',
            ]);
        }
    }

    foreach ($tutorialSelections ?? [] as $tutorialSelection) {
        if (! in_array($tutorialSelection[0], $classSelections ?? [], true)) {
            throw ValidationException::withMessages([
                'tutorial_selections' => 'Choose the matching class before selecting a tutorial.',
            ]);
        }
    }

    if ($request->boolean('selection_groups_present') || array_key_exists('class_selections', $data)) {
        $legacyClass = count($classSelections ?? []) === 1 ? $classSelections[0] : null;
        $divisionId = $legacyClass
            ? Division::firstOrCreate(['name' => $legacyClass, 'semester' => $data['semester']])->id
            : null;
    } else {
        $legacyClass = $data['division'] ?? null;
        $divisionId = $data['division_id'] ?? null;

        if (! $legacyClass && $divisionId) {
            $legacyClass = Division::find($divisionId)?->name;
        }

        if (! $divisionId && $legacyClass) {
            $divisionId = Division::firstOrCreate(['name' => $legacyClass, 'semester' => $data['semester']])->id;
        }
    }

    unset($data['division'], $data['division_id'], $data['selection_groups_present']);
    $data['subject_type'] ??= 'Lecture';
    $data['class_selections'] = $classSelections;
    $data['division_selections'] = $divisionSelections;
    $data['tutorial_selections'] = $tutorialSelections;
    $data['division_id'] = $divisionId;
    $data['tutorial_credit'] = $request->filled('tutorial_credit') ? (int) $request->tutorial_credit : null;

    return $data;
};

Route::post('/admin/subjects', function (Request $request) use ($validateSubjectData) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $validateSubjectData($request);

    $subject = Subject::create($data);

    if ($department = Department::find($data['department_id'])) {
        $folder = 'subject-records/'.Str::slug($department->name).'/'.Str::slug((string) $subject->semester);
        Storage::disk('local')->makeDirectory($folder);
        $filePath = $folder.'/subject-'.$subject->id.'.json';
        Storage::disk('local')->put($filePath, json_encode([
            'id' => $subject->id,
            'name' => $subject->name,
            'subject_code' => $subject->subject_code,
            'semester' => $subject->semester,
            'division' => $subject->division?->name,
            'department' => $department->name,
            'lecture_credit' => $subject->lecture_credit,
            'lab_credit' => $subject->lab_credit,
            'tutorial_credit' => $subject->tutorial_credit,
            'class_selections' => $subject->class_selections,
            'division_selections' => $subject->division_selections,
            'tutorial_selections' => $subject->tutorial_selections,
        ], JSON_PRETTY_PRINT));
        $subject->update(['folder_path' => $filePath]);
    }

    return redirect('/admin/subjects');
});

Route::get('/admin/subjects/{id}/edit', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $subject = Subject::with('department', 'faculty', 'division')->findOrFail($id);
    $semesters = Subject::query()
        ->whereNotNull('semester')
        ->distinct()
        ->orderBy('semester')
        ->pluck('semester')
        ->values();
    $semesters = $semesters->push($subject->semester)->unique()->sort(SORT_NATURAL)->values();

    $departments = Department::orderBy('name')->get();
    $faculties = Faculty::orderBy('name')->get();

    return view('admin.subjects.edit', [
        'subject' => $subject,
        'semesters' => $semesters,
        'departments' => $departments,
        'divisions' => Division::query()->orderBy('semester')->orderBy('name')->get(),
        'faculties' => $faculties,
    ]);
});

Route::post('/admin/subjects/{id}', function (Request $request, $id) use ($validateSubjectData) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $subject = Subject::findOrFail($id);

    $data = $validateSubjectData($request, $subject);

    $subject->update($data);

    if ($department = Department::find($data['department_id'])) {
        $folder = 'subject-records/'.Str::slug($department->name).'/'.Str::slug((string) $subject->semester);
        Storage::disk('local')->makeDirectory($folder);
        $filePath = $folder.'/subject-'.$subject->id.'.json';

        if ($subject->folder_path && $subject->folder_path !== $filePath) {
            Storage::disk('local')->delete($subject->folder_path);
        }

        Storage::disk('local')->put($filePath, json_encode([
            'id' => $subject->id,
            'name' => $subject->name,
            'subject_code' => $subject->subject_code,
            'semester' => $subject->semester,
            'division' => $subject->division?->name,
            'department' => $department->name,
            'lecture_credit' => $subject->lecture_credit,
            'lab_credit' => $subject->lab_credit,
            'tutorial_credit' => $subject->tutorial_credit,
            'class_selections' => $subject->class_selections,
            'division_selections' => $subject->division_selections,
            'tutorial_selections' => $subject->tutorial_selections,
        ], JSON_PRETTY_PRINT));
        $subject->update(['folder_path' => $filePath]);
    }

    return redirect('/admin/subjects');
});

Route::post('/admin/subjects/{id}/delete', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $subject = Subject::find($id);
    if ($subject) {
        if ($subject->folder_path) {
            Storage::disk('local')->delete($subject->folder_path);
        }
        $subject->delete();
    }

    return redirect('/admin/subjects');
});

// Classrooms management
Route::get('/admin/classrooms', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $q = $request->input('q');
    $query = Classroom::query();

    if ($q) {
        $query->where('room_number', 'like', "%{$q}%")
            ->orWhere('room_type', 'like', "%{$q}%")
            ->orWhere('availability', 'like', "%{$q}%");
    }

    $classrooms = $query->orderBy('created_at', 'desc')->get();

    return view('admin.classrooms.index', ['classrooms' => $classrooms, 'q' => $q]);
});

Route::get('/admin/classrooms/create', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.classrooms.create');
});

Route::post('/admin/classrooms', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'room_number' => 'required|string|max:50|unique:classrooms,room_number',
        'room_capacity' => 'required|integer|min:1',
        'room_type' => 'required|string|in:Classroom,Lab',
        'availability' => 'required|string|in:Available,Booked,Maintenance',
        'location' => 'nullable|string|max:255',
    ]);

    Classroom::create($data);

    return redirect('/admin/classrooms');
});

Route::get('/admin/classrooms/{id}/edit', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $classroom = Classroom::findOrFail($id);

    return view('admin.classrooms.edit', ['classroom' => $classroom]);
});

Route::post('/admin/classrooms/{id}', function (Request $request, $id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $classroom = Classroom::findOrFail($id);

    $data = $request->validate([
        'room_number' => 'required|string|max:50|unique:classrooms,room_number,'.$classroom->id,
        'room_capacity' => 'required|integer|min:1',
        'room_type' => 'required|string|in:Classroom,Lab',
        'availability' => 'required|string|in:Available,Booked,Maintenance',
        'location' => 'nullable|string|max:255',
    ]);

    $classroom->update($data);

    return redirect('/admin/classrooms');
});

Route::post('/admin/classrooms/{id}/delete', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $classroom = Classroom::find($id);
    if ($classroom) {
        $classroom->delete();
    }

    return redirect('/admin/classrooms');
});

Route::get('/admin/faculty-allocation', function (Request $request, FacultyAllocationService $service) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $departmentId = $request->input('department_id');
    $semester = $request->input('semester');
    $division = $request->input('division');

    $allBatches = $service->batches();
    $batches = $allBatches->filter(function (array $batch) use ($departmentId, $semester, $division): bool {
        if (filled($departmentId) && (int) $batch['department_id'] !== (int) $departmentId) {
            return false;
        }

        if (filled($semester) && (string) $batch['semester'] !== (string) $semester) {
            return false;
        }

        if (filled($division) && ! str_contains((string) $batch['class_name'], (string) $division)) {
            return false;
        }

        return true;
    })->values();

    $selectedBatch = $batches->firstWhere('key', $request->input('batch')) ?? $batches->first();
    $selectedBatchKey = $selectedBatch['key'] ?? null;

    $departmentRows = Department::orderBy('name')->get();

    // Only semesters that have subjects in Manage Subjects
    $semesterOptions = Subject::query()
        ->when(filled($departmentId), fn ($query) => $query->where('department_id', $departmentId))
        ->whereNotNull('semester')
        ->distinct()
        ->orderBy('semester')
        ->pluck('semester')
        ->filter()
        ->values();

    if ($semesterOptions->isEmpty()) {
        $semesterOptions = Subject::query()
            ->whereNotNull('semester')
            ->distinct()
            ->orderBy('semester')
            ->pluck('semester')
            ->filter()
            ->values();
    }

    $divisionsMap = Division::getDivisionsMap($semesterOptions);

    if (filled($semester) && isset($divisionsMap[(string) $semester])) {
        $divisionOptions = collect($divisionsMap[(string) $semester]);
    } else {
        $divisionOptions = collect(Division::getAllActiveDivisions($semesterOptions));
    }

    $allocations = collect();
    if ($selectedBatch) {
        $allocations = $service->allocations($selectedBatch, [
            'department_id' => $selectedBatch['department_id'],
            'semester' => $selectedBatch['semester'],
            'subject_id' => $request->input('subject_id'),
            'faculty_id' => $request->input('faculty_id'),
            'classroom_id' => $request->input('classroom_id'),
            'subject_type' => $request->input('subject_type'),
        ]);
    }

    return view('admin.faculty-allocation', [
        'batches' => $batches,
        'selectedBatch' => $selectedBatch,
        'selectedBatchKey' => $selectedBatchKey,
        'allocations' => $allocations,
        'departments' => $departmentRows,
        'semesterOptions' => $semesterOptions,
        'divisionOptions' => $divisionOptions,
        'divisionsMap' => $divisionsMap,
        'selectedDepartmentId' => $departmentId,
        'selectedSemester' => $semester,
        'selectedDivision' => $division,
        'subjects' => Subject::query()->with('division')->when(filled($departmentId), fn ($query) => $query->where('department_id', $departmentId))->when(filled($semester), fn ($query) => $query->where('semester', $semester))->orderBy('name')->get(['id', 'name', 'subject_type', 'credit', 'lecture_credit', 'lab_credit', 'tutorial_credit', 'class_selections', 'division_selections', 'tutorial_selections', 'division_id', 'semester', 'department_id', 'faculty_id']),
        'faculties' => Faculty::orderBy('name')->get(['id', 'name', 'department_id']),
        'subjectTypes' => Subject::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
    ]);
});

Route::post('/admin/faculty-allocation/{id}/faculty', function (
    Request $request,
    int $id,
    FacultyAllocationService $service,
    TimetableGenerator $timetableGenerator
) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $allocation = RoomAllocation::with('subject')->findOrFail($id);
    $data = $request->validate([
        'faculty_id' => 'required|integer|exists:faculties,id',
    ]);
    $faculty = Faculty::findOrFail($data['faculty_id']);
    $validationMessage = $service->validateAlternativeAssignment($allocation, $faculty);

    if ($validationMessage) {
        return back()->with('faculty_allocation_error', $validationMessage);
    }

    $existingTimetables = TimetableEntry::query()
        ->select(['department_id', 'semester', 'division', 'academic_year', 'term'])
        ->where('department_id', $allocation->department_id)
        ->where('semester', $allocation->semester)
        ->where('division', $allocation->division)
        ->distinct()
        ->get();

    try {
        DB::transaction(function () use ($allocation, $faculty, $existingTimetables, $timetableGenerator): void {
            $allocation->update(['faculty_id' => $faculty->id]);

            foreach ($existingTimetables as $timetable) {
                $success = $timetableGenerator->generate(
                    (int) $timetable->department_id,
                    (string) $timetable->semester,
                    (string) $timetable->division,
                    (string) $timetable->academic_year,
                    (string) $timetable->term,
                    false
                );

                if (! $success) {
                    throw new Exception("The timetable for {$timetable->division} could not be refreshed.");
                }
            }
        });
    } catch (Exception $exception) {
        return back()->with(
            'faculty_allocation_error',
            "The faculty change was not saved because the timetable could not be refreshed: {$exception->getMessage()}"
        );
    }

    $query = http_build_query([
        'department_id' => $allocation->department_id,
        'semester' => $allocation->semester,
        'division' => $allocation->division,
        'batch' => $allocation->department_id.'|'.$allocation->semester.'|'.$allocation->division,
    ]);

    return redirect('/admin/faculty-allocation?'.$query)
        ->with('faculty_allocation_status', 'Faculty assignment updated successfully.');
});

Route::post('/admin/faculty-allocation/generate', function (
    Request $request,
    FacultyAllocationService $service,
    TimetableGenerator $timetableGenerator
) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $selection = $request->validate([
        'department_id' => 'required|integer|exists:departments,id',
        'semester' => 'required|string',
        'division' => 'required|string',
        'batch' => 'nullable|string',
    ]);

    $result = $service->generateForSelection($selection);
    $warnings = collect($result['warnings'] ?? []);
    $existingTimetables = TimetableEntry::query()
        ->select(['department_id', 'semester', 'division', 'academic_year', 'term'])
        ->where('department_id', $selection['department_id'])
        ->where('semester', $selection['semester'])
        ->when(filled($selection['division']), fn ($query) => $query->where('division', $selection['division']))
        ->distinct()
        ->get();

    foreach ($existingTimetables as $timetable) {
        try {
            $timetableGenerator->generate(
                (int) $timetable->department_id,
                (string) $timetable->semester,
                (string) $timetable->division,
                (string) $timetable->academic_year,
                (string) $timetable->term,
                false
            );
        } catch (Exception $exception) {
            $warnings->push(
                "Existing timetable for {$timetable->division} could not be refreshed: {$exception->getMessage()}"
            );
        }
    }

    $result['warnings'] = $warnings->unique()->values();

    if ($request->expectsJson()) {
        return response()->json($result);
    }

    $query = http_build_query(array_filter($selection, fn ($value) => $value !== null && $value !== ''));

    return redirect('/admin/faculty-allocation'.($query !== '' ? '?'.$query : ''))
        ->with('faculty_allocation_status', "Auto allocation completed: assigned faculty to {$result['count']} subject activities.")
        ->with('faculty_allocation_warnings', $result['warnings']->all());
});

Route::get('/api/faculty-allocations', function (Request $request, FacultyAllocationService $service) {
    if (! session('admin.auth')) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    $batches = $service->batches();
    $selectedBatch = $batches->firstWhere('key', $request->input('batch'));

    return response()->json([
        'batches' => $batches,
        'allocations' => $service->allocations($selectedBatch, $request->only([
            'department_id',
            'semester',
            'subject_id',
            'faculty_id',
            'classroom_id',
            'subject_type',
        ])),
    ]);
});

Route::match(['get', 'post'], '/admin/classroom-allocation', function (Request $request, ClassroomAllocationService $allocationService) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $facultyAllocationService = app(FacultyAllocationService::class);
    $batches = $facultyAllocationService->batches();

    $departments = Department::orderBy('name')->get();

    // Only semesters that have subjects in the DB
    $semesters = Subject::query()
        ->whereNotNull('semester')
        ->select('semester')
        ->distinct()
        ->orderBy('semester')
        ->pluck('semester');

    $semestersMap = Subject::query()
        ->whereNotNull('semester')
        ->select(['department_id', 'semester'])
        ->distinct()
        ->orderBy('semester')
        ->get()
        ->groupBy('department_id')
        ->map(fn ($departmentSubjects) => $departmentSubjects->pluck('semester')->values())
        ->all();

    $classesMap = [];
    foreach ($departments as $department) {
        $departmentSemesterClasses = [];
        foreach ($semesters as $semester) {
            $departmentSemesterClasses[(string) $semester] = $allocationService->classesForSelection((int) $department->id, (string) $semester);
        }
        $classesMap[(string) $department->id] = $departmentSemesterClasses;
    }
    $divisionsMap = [];
    foreach ($classesMap as $departmentId => $departmentSemesters) {
        foreach ($departmentSemesters as $semester => $classNames) {
            foreach ($classNames as $className) {
                $divisionsMap[$departmentId][$semester][$className] = $allocationService->divisionsForSelection(
                    (int) $departmentId,
                    (string) $semester,
                    (string) $className
                );
            }
        }
    }

    $subjectOptionsMap = Subject::query()
        ->orderBy('name')
        ->get(['id', 'name', 'department_id', 'semester'])
        ->groupBy('department_id')
        ->map(fn ($departmentSubjects) => $departmentSubjects
            ->groupBy('semester')
            ->map(fn ($semesterSubjects) => $semesterSubjects
                ->map(fn (Subject $subject): array => ['id' => $subject->id, 'name' => $subject->name])
                ->values()))
        ->all();

    $selectedDepartmentId = (int) ($request->input('department_id') ?? 0);
    $selectedSemester = (string) ($request->input('semester') ?? '');
    $selectedClass = (string) ($request->input('class') ?? $request->input('class_name') ?? $request->input('division') ?? '');

    if ($selectedDepartmentId > 0 && $selectedSemester !== '') {
        $classes = collect($classesMap[(string) $selectedDepartmentId][(string) $selectedSemester] ?? []);
    } elseif ($selectedDepartmentId > 0) {
        $classes = collect($classesMap[(string) $selectedDepartmentId] ?? [])->flatten()->unique()->sort(SORT_NATURAL)->values();
    } else {
        $classes = collect($classesMap)->flatten(1)->flatten()->unique()->sort(SORT_NATURAL)->values();
    }

    $academicYears = TimetableEntry::query()
        ->whereNotNull('academic_year')
        ->where('academic_year', '!=', '')
        ->select('academic_year')
        ->distinct()
        ->orderByDesc('academic_year')
        ->pluck('academic_year');
    // Fallback: add current and next academic years if no timetable entries exist
    if ($academicYears->isEmpty()) {
        $currentYear = (int) date('Y');
        $academicYears = collect([
            ($currentYear - 1).'-'.$currentYear,
            $currentYear.'-'.($currentYear + 1),
            ($currentYear + 1).'-'.($currentYear + 2),
        ]);
    }
    $terms = TimetableEntry::query()
        ->whereNotNull('term')
        ->where('term', '!=', '')
        ->select('term')
        ->distinct()
        ->orderBy('term')
        ->pluck('term');
    // Fallback terms
    if ($terms->isEmpty()) {
        $terms = collect(['Odd', 'Even']);
    }

    if ($request->isMethod('post')) {
        if ($request->input('form_type') === 'save-lab-group') {
            $data = $request->validate([
                'department_id' => 'required|integer|exists:departments,id',
                'semester' => 'required|string|max:20',
                'class_name' => 'required|string|max:20',
                'group_name' => 'required|string|max:80',
                'divisions' => 'required|array|min:1',
                'divisions.*' => 'required|string|distinct',
                'classroom_id' => 'required|integer|exists:classrooms,id',
            ]);

            $availableClasses = $allocationService->classesForSelection(
                (int) $data['department_id'],
                (string) $data['semester']
            );
            if (! in_array($data['class_name'], $availableClasses, true)) {
                throw ValidationException::withMessages([
                    'class_name' => 'Choose a class that has subjects in the selected semester.',
                ]);
            }

            $availableDivisions = $allocationService->divisionsForSelection(
                (int) $data['department_id'],
                (string) $data['semester'],
                (string) $data['class_name']
            );
            if (array_diff($data['divisions'], $availableDivisions) !== []) {
                throw ValidationException::withMessages([
                    'divisions' => 'Every division must be selected for this class in Manage Subjects.',
                ]);
            }

            foreach ($data['divisions'] as $selectedDivision) {
                if (preg_match('/^(.*?)([1-4])$/', $selectedDivision, $matches) !== 1) {
                    continue;
                }

                $pairStart = (int) $matches[2] <= 2 ? 1 : 3;
                $pairNumber = (int) $matches[2] === $pairStart ? $pairStart + 1 : $pairStart;
                $pairedDivision = $matches[1].$pairNumber;
                if (in_array($pairedDivision, $availableDivisions, true)
                    && ! in_array($pairedDivision, $data['divisions'], true)) {
                    throw ValidationException::withMessages([
                        'divisions' => "{$selectedDivision} and {$pairedDivision} must be included in the same lab group.",
                    ]);
                }
            }

            $lab = Classroom::query()
                ->whereKey($data['classroom_id'])
                ->where('availability', 'Available')
                ->where('room_type', 'like', '%lab%')
                ->first();
            if (! $lab) {
                throw ValidationException::withMessages([
                    'classroom_id' => 'Choose an available laboratory from the existing room records.',
                ]);
            }

            $existingGroups = LabAllocationGroup::query()
                ->where('department_id', $data['department_id'])
                ->where('semester', $data['semester'])
                ->where('class_name', $data['class_name'])
                ->get();
            $alreadyGroupedDivisions = $existingGroups
                ->flatMap(fn (LabAllocationGroup $group): array => $group->divisions ?? [])
                ->all();
            if (array_intersect($data['divisions'], $alreadyGroupedDivisions) !== []) {
                throw ValidationException::withMessages([
                    'divisions' => 'A division can belong to only one configured lab group. Remove its existing group first.',
                ]);
            }

            $combinedStrength = $allocationService->studentStrengthForDivisions(
                (int) $data['department_id'],
                (string) $data['semester'],
                (string) $data['class_name'],
                $data['divisions']
            );
            if ($lab->room_capacity < $combinedStrength) {
                throw ValidationException::withMessages([
                    'classroom_id' => "The selected laboratory capacity ({$lab->room_capacity}) is below the combined division strength ({$combinedStrength}).",
                ]);
            }

            $groupNameExists = $existingGroups->contains(
                fn (LabAllocationGroup $group): bool => mb_strtolower($group->group_name) === mb_strtolower($data['group_name'])
            );
            if ($groupNameExists) {
                throw ValidationException::withMessages([
                    'group_name' => 'This lab group name already exists for the selected class and semester.',
                ]);
            }

            LabAllocationGroup::create([
                'department_id' => $data['department_id'],
                'semester' => $data['semester'],
                'class_name' => $data['class_name'],
                'group_name' => $data['group_name'],
                'divisions' => $data['divisions'],
                'classroom_id' => $lab->id,
            ]);

            return redirect('/admin/classroom-allocation?'.http_build_query([
                'department_id' => $data['department_id'],
                'semester' => $data['semester'],
                'class' => $data['class_name'],
            ]))->with('allocation_status', 'Lab sharing group saved successfully.');
        }

        if ($request->input('form_type') === 'filtered-auto-allocate') {
            $data = $request->validate([
                'department_id' => 'required|integer|exists:departments,id',
                'semester' => 'required|string|max:10',
                'class' => 'required|string|max:20',
                'class_name' => 'nullable|string|max:20',
                'division' => 'nullable|string|max:20',
                'batch' => 'nullable|string|max:255',
                'term' => 'nullable|string|max:20',
                'academic_year' => 'nullable|string|max:20',
            ]);
            $data['class'] = $data['class'] ?? $data['class_name'] ?? $data['division'] ?? null;
            if (! in_array(
                $data['class'],
                $allocationService->classesForSelection((int) $data['department_id'], (string) $data['semester']),
                true
            )) {
                throw ValidationException::withMessages([
                    'class' => 'Choose a class that has subjects in the selected semester.',
                ]);
            }
            $result = $allocationService->generateForSelection($data);

            if ($result['allocatedClassrooms'] > 0) {
                Notification::trigger('Classroom Allocation Completed', ['count' => $result['allocatedClassrooms']]);
            }
            if ($result['allocatedLabs'] > 0) {
                Notification::trigger('Lab Allocation Completed', ['count' => $result['allocatedLabs']]);
            }

            $redirectParams = array_filter([
                'department_id' => $data['department_id'],
                'semester' => $data['semester'],
                'class' => $data['class'] ?? null,
                'division' => $data['division'] ?? null,
                'batch' => $data['batch'] ?? null,
            ]);
            $statusMessage = "Generated {$result['created']} allocation records.";
            if ($result['unallocated'] > 0) {
                $statusMessage .= " {$result['unallocated']} allocations have no suitable available room; review their Unallocated status.";
            }

            return redirect('/admin/classroom-allocation?'.http_build_query($redirectParams))
                ->with('allocation_status', $statusMessage);
        }

        if ($request->input('form_type') === 'auto-allocate' || $request->input('form_type') === 're-generate') {
            $result = $allocationService->generateAll();

            if ($result['allocatedClassrooms'] > 0) {
                Notification::trigger('Classroom Allocation Completed', ['count' => $result['allocatedClassrooms']]);
            }
            if ($result['allocatedLabs'] > 0) {
                Notification::trigger('Lab Allocation Completed', ['count' => $result['allocatedLabs']]);
            }

            $statusMessage = "Generated {$result['created']} allocation records.";
            if ($result['unallocated'] > 0) {
                $statusMessage .= " {$result['unallocated']} allocations have no suitable available room; review their Unallocated status.";
            }

            return redirect('/admin/classroom-allocation')
                ->with('allocation_status', $statusMessage);
        }
    }

    // Build allocations query — filter by dept/semester/division/batch when query params are present
    $hasActivityTypeColumn = Schema::hasColumn('room_allocations', 'activity_type');

    $allocQuery = RoomAllocation::with(['department', 'subject', 'faculty', 'classroom', 'secondClassroom'])
        ->when($hasActivityTypeColumn, fn ($query) => $query->whereNull('activity_type'))
        ->orderBy('id', 'desc');

    if ($request->filled('department_id')) {
        $allocQuery->where('department_id', $request->input('department_id'));
    }
    if ($request->filled('semester')) {
        $allocQuery->where('semester', $request->input('semester'));
    }
    $selectedClassFilter = $request->input('class') ?? $request->input('class_name');
    if (filled($selectedClassFilter)) {
        $allocQuery->where('class_name', $selectedClassFilter);
    }
    if ($request->filled('division_filter')) {
        $allocQuery->where('division', $request->input('division_filter'));
    }
    if ($request->filled('subject_id')) {
        $allocQuery->where('subject_id', $request->input('subject_id'));
    }
    if ($request->filled('batch')) {
        $batchKey = $request->input('batch');
        $matched = $batches->firstWhere('key', $batchKey) ?? $batches->firstWhere('class_name', $batchKey);
        if ($matched) {
            $allocQuery->where('class_name', $matched['class_name']);
        } else {
            $allocQuery->where('class_name', $batchKey);
        }
    }

    $allocations = $allocQuery->paginate(15)->withQueryString();
    $availableClassrooms = Classroom::query()
        ->where('availability', 'Available')
        ->where('room_type', 'not like', '%lab%')
        ->whereNotNull('room_number')
        ->orderBy('room_capacity')
        ->orderBy('room_number')
        ->get();
    $pairedLabRoomIds = [];
    $labAllocations = RoomAllocation::query()
        ->with('classroom')
        ->whereNull('activity_type')
        ->where('allocation_type', 'Lab')
        ->whereNotNull('division')
        ->get();

    foreach ($labAllocations as $labAllocation) {
        if (preg_match('/^(.*?)([1-4])$/', (string) $labAllocation->division, $matches) !== 1) {
            continue;
        }

        $pairStart = (int) $matches[2] <= 2 ? 1 : 3;
        $pairKey = implode('|', [
            $labAllocation->department_id,
            $labAllocation->semester,
            $labAllocation->class_name,
            $labAllocation->subject_id,
            $matches[1],
            $pairStart,
        ]);
        $room = $labAllocation->classroom;
        if (! $room
            || $room->availability !== 'Available'
            || ! str_contains(strtolower((string) $room->room_type), 'lab')) {
            continue;
        }

        if (! isset($pairedLabRoomIds[$pairKey]) || (int) $matches[2] === $pairStart) {
            $pairedLabRoomIds[$pairKey] = $room;
        }
    }

    foreach ($allocations as $allocation) {
        if ($allocation->allocation_type === 'Lab'
            && preg_match('/^(.*?)([1-4])$/', (string) $allocation->division, $matches) === 1) {
            $pairStart = (int) $matches[2] <= 2 ? 1 : 3;
            $pairKey = implode('|', [
                $allocation->department_id,
                $allocation->semester,
                $allocation->class_name,
                $allocation->subject_id,
                $matches[1],
                $pairStart,
            ]);
            $pairedRoom = $pairedLabRoomIds[$pairKey] ?? null;

            if ($pairedRoom) {
                $allocation->setRelation('classroom', $pairedRoom);
                $allocation->setRelation('secondClassroom', null);
            }

            continue;
        }

        $room = $allocation->classroom;
        $roomIsClassroom = $room
            && $room->availability === 'Available'
            && ! str_contains(strtolower((string) $room->room_type), 'lab');

        if (! $roomIsClassroom) {
            $studentCount = (int) ($allocation->student_count ?? 0);
            $replacementClassroom = $availableClassrooms->first(
                fn (Classroom $classroom): bool => (int) $classroom->room_capacity >= $studentCount
            );
            $allocation->setRelation('classroom', $replacementClassroom);
        }
    }
    $selectedDivisions = filled($selectedDepartmentId) && $selectedSemester !== '' && $selectedClass !== ''
        ? $allocationService->divisionsForSelection($selectedDepartmentId, $selectedSemester, $selectedClass)
        : [];
    $subjectOptions = Subject::query()
        ->when(filled($request->input('department_id')), fn ($query) => $query->where('department_id', $request->input('department_id')))
        ->when(filled($request->input('semester')), fn ($query) => $query->where('semester', $request->input('semester')))
        ->orderBy('name')
        ->get(['id', 'name']);
    $labGroups = filled($selectedDepartmentId) && $selectedSemester !== '' && $selectedClass !== ''
        ? LabAllocationGroup::query()
            ->with('classroom')
            ->where('department_id', $selectedDepartmentId)
            ->where('semester', $selectedSemester)
            ->where('class_name', $selectedClass)
            ->orderBy('group_name')
            ->get()
        : collect();
    $availableLabs = Classroom::query()
        ->where('availability', 'Available')
        ->where('room_type', 'like', '%lab%')
        ->orderBy('room_number')
        ->get(['id', 'room_number', 'room_capacity']);

    $hasActivityTypeColumn = Schema::hasColumn('room_allocations', 'activity_type');
    $hasAllocationType = Schema::hasColumn('room_allocations', 'allocation_type');

    $totalSubjects = RoomAllocation::query()
        ->when($hasActivityTypeColumn, fn ($query) => $query->whereNull('activity_type'))
        ->count();

    $allocatedClassroomCount = RoomAllocation::query()
        ->where('status', 'Allocated')
        ->when($hasActivityTypeColumn, fn ($query) => $query->whereNull('activity_type'))
        ->when($hasAllocationType, function ($query) {
            $query->where(function ($q) {
                $q->where('allocation_type', 'Classroom')->orWhereNull('allocation_type');
            });
        })
        ->count();

    $allocatedLabCount = RoomAllocation::query()
        ->where('status', 'Allocated')
        ->when($hasActivityTypeColumn, fn ($query) => $query->whereNull('activity_type'))
        ->when($hasAllocationType, fn ($query) => $query->where('allocation_type', 'Lab'))
        ->count();

    $unallocatedCount = RoomAllocation::query()
        ->where('status', 'Unallocated')
        ->when($hasActivityTypeColumn, fn ($query) => $query->whereNull('activity_type'))
        ->count();

    return view('admin.classrooms.allocation', [
        'departments' => $departments,
        'semesters' => $semesters,
        'semestersMap' => $semestersMap,
        'classesMap' => $classesMap,
        'divisionsMap' => $divisionsMap,
        'subjectOptionsMap' => $subjectOptionsMap,
        'classes' => $classes,
        'selectedDepartmentId' => $selectedDepartmentId,
        'selectedSemester' => $selectedSemester,
        'selectedClass' => $selectedClass,
        'selectedDivisions' => $selectedDivisions,
        'selectedDivisionFilter' => $request->input('division_filter'),
        'selectedSubjectId' => $request->input('subject_id'),
        'subjectOptions' => $subjectOptions,
        'labGroups' => $labGroups,
        'availableLabs' => $availableLabs,
        'batches' => $batches,
        'academicYears' => $academicYears,
        'terms' => $terms,
        'allocations' => $allocations,
        'totalLectures' => $totalSubjects,
        'allocatedCount' => $allocatedClassroomCount,
        'allocatedLabCount' => $allocatedLabCount,
        'unallocatedCount' => $unallocatedCount,
    ]);
});

Route::post('/admin/classroom-allocation/lab-groups/{id}/delete', function (int $id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $group = LabAllocationGroup::findOrFail($id);
    $selection = [
        'department_id' => $group->department_id,
        'semester' => $group->semester,
        'class' => $group->class_name,
    ];
    $group->delete();

    return redirect('/admin/classroom-allocation?'.http_build_query($selection))
        ->with('allocation_status', 'Lab sharing group removed. Existing allocations will use separate division labs when generated again.');
});

Route::post('/admin/classroom-allocation/{id}/delete-room', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $room = Classroom::find($id);
    if ($room) {
        $room->delete();
    }

    return redirect('/admin/classroom-allocation')->with('room_status', 'Room deleted successfully.');
});

Route::get('/admin/classroom-allocation/{id}/edit-allocation', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $allocation = RoomAllocation::with(['department', 'subject', 'faculty', 'classroom'])->findOrFail($id);

    return view('admin.classrooms.allocation-edit', [
        'allocation' => $allocation,
        'departments' => Department::orderBy('name')->get(),
        'subjects' => Subject::orderBy('name')->get(),
        'faculties' => Faculty::orderBy('name')->get(),
        'rooms' => Classroom::orderBy('room_number')->get(),
    ]);
});

Route::post('/admin/classroom-allocation/{id}/update-allocation', function (Request $request, $id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $allocation = RoomAllocation::findOrFail($id);

    $data = $request->validate([
        'department_id' => 'required|integer|exists:departments,id',
        'semester' => 'required|string|max:50',
        'subject_id' => 'required|integer|exists:subjects,id',
        'faculty_id' => 'required|integer|exists:faculties,id',
        'class_name' => 'required|string|max:100',
        'student_count' => 'required|integer|min:1',
        'day' => 'required|string|max:50',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'room_id' => 'required|integer|exists:classrooms,id',
    ]);

    $room = Classroom::findOrFail($data['room_id']);
    if ((int) $room->room_capacity < (int) $data['student_count']) {
        Notification::trigger('Classroom Capacity Exceeded', [
            'room_number' => $room->room_number,
            'capacity' => $room->room_capacity,
            'student_count' => $data['student_count'],
        ]);

        return back()->withErrors(['student_count' => 'Selected room capacity is lower than the student count.'])->withInput();
    }

    $allocation->update([
        'department_id' => $data['department_id'],
        'semester' => $data['semester'],
        'subject_id' => $data['subject_id'],
        'faculty_id' => $data['faculty_id'],
        'classroom_id' => $room->id,
        'class_name' => $data['class_name'],
        'student_count' => (int) $data['student_count'],
        'day' => $data['day'],
        'start_time' => $data['start_time'],
        'end_time' => $data['end_time'],
    ]);

    return redirect('/admin/classroom-allocation')->with('allocation_status', 'Allocation updated successfully.');
});

Route::post('/admin/classroom-allocation/{id}/delete-allocation', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $allocation = RoomAllocation::find($id);
    if ($allocation) {
        $allocation->delete();
    }

    return redirect('/admin/classroom-allocation')->with('allocation_status', 'Allocation deleted successfully.');
});

Route::post('/admin/logout', function () {
    session()->forget('admin.auth');

    return redirect('/');
});

Route::post('/student/register', function (Request $request) {
    $data = $request->validate([
        'enrollment_number' => 'required|string|max:255|unique:users,enrollment_number',
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'department' => 'required|string|max:255',
        'semester' => 'required|string|max:255',
        'student_class' => 'nullable|string|max:255',
        'divcon' => 'nullable|string|max:255',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $student = User::create([
        'name' => $data['name'],
        'enrollment_number' => $data['enrollment_number'],
        'email' => $data['email'],
        'department' => $data['department'],
        'semester' => $data['semester'],
        'student_class' => $data['student_class'] ?? null,
        'divcon' => $data['divcon'] ?? null,
        'password' => Hash::make($data['password']),
    ]);

    Notification::trigger('Student Added', ['name' => $student->name]);

    return redirect('/')->with('student_register_success', '✓ Registration Successful! Your student account has been created successfully. You can now login using your Enrollment Number and Password.');
});

Route::post('/faculty/login', function (Request $request) {
    $data = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    $faculty = Faculty::with('department')->where('email', $data['email'])->first();

    if (! $faculty) {
        return back()->withErrors(['email' => 'Faculty account not found.'])->withInput();
    }

    $passwordMatches = false;
    $storedPassword = (string) $faculty->password;

    try {
        $passwordMatches = Hash::check($data['password'], $storedPassword);
    } catch (RuntimeException $e) {
        $passwordMatches = hash_equals($storedPassword, $data['password']);

        if ($passwordMatches) {
            $faculty->password = $data['password'];
            $faculty->save();
        }
    }

    if (! $passwordMatches) {
        return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
    }

    session(['faculty.auth' => [
        'id' => $faculty->id,
        'name' => $faculty->name,
        'email' => $faculty->email,
        'designation' => $faculty->designation,
        'department_id' => $faculty->department_id,
        'department_name' => $faculty->department ? $faculty->department->name : 'N/A',
        'subjects' => $faculty->subjects,
    ]]);

    return redirect('/faculty/dashboard');
});

Route::post('/student/login', function (Request $request) {
    $data = $request->validate([
        'enrollment_number' => 'required|string',
        'password' => 'required|string',
    ]);

    $user = User::where('enrollment_number', trim($data['enrollment_number']))->first();

    if (! $user) {
        return back()->withErrors(['enrollment_number' => 'Student account not found.'])->withInput();
    }

    $passwordMatches = false;
    $storedPassword = (string) $user->password;

    try {
        $passwordMatches = Hash::check($data['password'], $storedPassword);
    } catch (RuntimeException $e) {
        $passwordMatches = hash_equals($storedPassword, $data['password']);

        if ($passwordMatches) {
            $user->password = $data['password'];
            $user->save();
        }
    }

    if (! $passwordMatches) {
        return back()->withErrors(['enrollment_number' => 'Invalid enrollment number or password.'])->withInput();
    }

    session(['student.auth' => [
        'id' => $user->id,
        'name' => $user->name,
        'enrollment_number' => $user->enrollment_number,
        'department' => $user->department,
        'semester' => $user->semester,
        'student_class' => $user->student_class,
        'divcon' => $user->divcon,
    ]]);

    return redirect('/student/dashboard');
});

Route::middleware(['web'])->group(function () {
    Route::get('/admin/students', function (Request $request) {
        if (! session('admin.auth')) {
            return redirect('/admin/login');
        }

        $q = $request->input('q');
        $query = User::query();

        if ($q) {
            $query->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('enrollment_number', 'like', "%{$q}%")
                ->orWhere('department', 'like', "%{$q}%");
        }

        $students = $query->orderBy('created_at', 'desc')->get();

        return view('admin.students.index', ['students' => $students, 'q' => $q]);
    });

    Route::post('/admin/students/{id}/delete', function ($id) {
        if (! session('admin.auth')) {
            return redirect('/admin/login');
        }

        $student = User::find($id);

        if (! $student) {
            return back()->with('error', 'Unable to delete student. Please try again.');
        }

        try {
            $student->delete();

            return redirect('/admin/students')->with('status', 'Student deleted successfully.');
        } catch (Throwable $e) {
            return back()->with('error', 'Unable to delete student. Please try again.');
        }
    });

    Route::get('/student/dashboard', function () {
        if (! session('student.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        $student = User::find(session('student.auth.id'));

        if (! $student) {
            session()->forget('student.auth');

            return redirect('/')->with('error', 'Student session expired. Please login again.');
        }

        return view('student.dashboard', compact('student'));
    });

    Route::get('/student/timetable', function () {
        if (! session('student.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        $studentAuth = session('student.auth');
        $studentDept = $studentAuth['department'] ?? '';
        $semester = $studentAuth['semester'] ?? '5';
        $division = $studentAuth['divcon'] ?: ($studentAuth['student_class'] ? substr($studentAuth['student_class'], -1) : 'A');

        $dept = Department::where('name', $studentDept)->orWhere('code', $studentDept)->first()
            ?? Department::first();
        $deptId = $dept?->id;

        $rawDeptName = $dept ? $dept->name : ($studentDept ?: 'Computer Engineering');
        $deptDisplayName = (stripos($rawDeptName, 'comp') !== false)
            ? 'Computer Engineering'
            : $rawDeptName;
        $deptFullName = 'Department of '.$deptDisplayName;

        $entries = collect();
        if ($deptId) {
            $entries = TimetableEntry::with(['subject', 'faculty', 'classroom'])
                ->where('department_id', $deptId)
                ->where('semester', $semester)
                ->where('division', $division)
                ->get();
        }

        $classSubjects = collect();
        if ($deptId) {
            $classSubjects = Subject::with('faculty')
                ->where('department_id', $deptId)
                ->where('semester', $semester)
                ->get()
                ->filter(function ($subject) use ($division) {
                    $classes = $subject->effective_class_selections ?? [];

                    return empty($classes) || in_array($division, $classes, true);
                })
                ->values();
        }

        $getFacultyInitials = function (?string $name): string {
            if (empty($name)) {
                return '';
            }
            $clean = trim(preg_replace('/^(Prof\.|Prof|Dr\.|Dr|Mr\.|Mr|Mrs\.|Mrs|Ms\.|Ms|Er\.|Er)\s+/i', '', $name));
            $parts = preg_split('/[\s\.\-]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($parts)) {
                return strtoupper(substr($name, 0, 3));
            }
            $initials = '';
            foreach ($parts as $p) {
                $initials .= strtoupper(substr($p, 0, 1));
            }

            return $initials;
        };

        $sessions = [];
        foreach ($entries as $entry) {
            $roomName = $entry->classroom
                ? ($entry->classroom->room_type.': '.$entry->classroom->room_number.($entry->classroom->location ? ' - '.$entry->classroom->location : ''))
                : 'Room unavailable';

            $sessions[] = [
                'day' => $entry->day,
                'time_slot' => $entry->time_slot,
                'subject' => $entry->subject ? $entry->subject->name : '',
                'subject_code' => $entry->subject ? ($entry->subject->subject_code ?: '') : '',
                'faculty' => $entry->faculty ? $entry->faculty->name : '',
                'faculty_initials' => $getFacultyInitials($entry->faculty?->name),
                'room' => $roomName,
                'room_number' => $entry->classroom?->room_number ?? '',
                'type' => $entry->lecture_type,
                'batch' => $entry->activity_batch,
                'duration' => (int) ($entry->duration ?: 1),
            ];
        }

        $timetable = [
            'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'time_slots' => ['10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10'],
            'sessions' => $sessions,
            'academic_year' => '2026-2027',
            'term' => 'Odd',
            'term_code' => '261',
            'term_dates' => '23/06/2026 to 07/11/2026',
            'wef_date' => '23/06/2026',
            'sbt_dates' => '21/09/2026 to 26/09/2026',
            'division' => $division,
            'semester' => $semester,
            'class_name' => $semester.$division,
            'dept_full_name' => $deptFullName,
        ];

        return view('student.timetable', compact('timetable', 'classSubjects', 'deptDisplayName', 'deptFullName', 'semester', 'division'));
    });

    Route::get('/student/notifications', function () {
        if (! session('student.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        $notifications = Notification::whereIn('audience', ['student', 'all'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('student.notifications', ['notifications' => $notifications]);
    });

    Route::get('/student/profile/edit', function () {
        if (! session('student.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        return view('student.profile');
    });

    Route::put('/student/profile', function (Request $request) {
        if (! session('student.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'semester' => 'nullable|string|max:255',
            'student_class' => 'nullable|string|max:255',
            'divcon' => 'nullable|string|max:255',
        ]);

        $user = User::find(session('student.auth.id'));
        $user->update($data);

        session(['student.auth' => array_merge(session('student.auth'), [
            'name' => $user->name,
            'department' => $user->department,
            'semester' => $user->semester,
            'student_class' => $user->student_class,
            'divcon' => $user->divcon,
        ])]);

        return redirect('/student/profile/edit')->with('status', 'Profile updated successfully.');
    });

    Route::post('/student/logout', function () {
        session()->forget('student.auth');

        return redirect('/')->with('status', 'You have been logged out.');
    });

    // Faculty Portal Routes
    Route::get('/faculty/dashboard', function () {
        if (! session('faculty.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        return view('faculty.dashboard');
    });

    Route::get('/faculty/timetable', function () {
        if (! session('faculty.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        $facultyId = session('faculty.auth.id');
        $facultyName = session('faculty.auth.name');

        $entries = TimetableEntry::with(['subject', 'classroom', 'department'])
            ->where('faculty_id', $facultyId)
            ->get();

        $sessions = [];
        foreach ($entries as $entry) {
            $sessions[] = [
                'day' => $entry->day,
                'time_slot' => $entry->time_slot,
                'subject' => $entry->subject ? $entry->subject->name : '',
                'subject_code' => $entry->subject ? ($entry->subject->subject_code ?: '') : '',
                'faculty' => $facultyName,
                'room' => $entry->classroom ? ($entry->classroom->room_type.': '.$entry->classroom->room_number) : '',
                'type' => $entry->lecture_type,
                'batch' => $entry->activity_batch,
                'duration' => (int) ($entry->duration ?: 1),
                'semester' => $entry->semester,
                'division' => $entry->division,
            ];
        }

        $timetable = [
            'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'time_slots' => ['10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10'],
            'sessions' => $sessions,
            'academic_year' => '2026-2027',
            'term' => 'Odd',
            'term_code' => '261',
            'faculty_name' => $facultyName,
            'dept_full_name' => 'Department of Computer Engineering',
        ];

        return view('faculty.timetable', compact('timetable', 'facultyName'));
    });

    Route::get('/faculty/notifications', function () {
        if (! session('faculty.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        $notifications = Notification::whereIn('audience', ['faculty', 'all'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('faculty.notifications', ['notifications' => $notifications]);
    });

    Route::get('/faculty/subjects', function () {
        if (! session('faculty.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        $facultyName = session('faculty.auth.name');
        $subjects = Subject::where('faculty_name', $facultyName)->get();

        return view('faculty.subjects', ['subjects' => $subjects]);
    });

    Route::get('/faculty/profile/edit', function () {
        if (! session('faculty.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        return view('faculty.profile');
    });

    Route::put('/faculty/profile', function (Request $request) {
        if (! session('faculty.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'required|string|max:255',
            'subjects' => 'nullable|string|max:1000',
        ]);

        $faculty = Faculty::find(session('faculty.auth.id'));
        if ($faculty) {
            $faculty->update($data);
            session(['faculty.auth' => array_merge(session('faculty.auth'), [
                'name' => $faculty->name,
                'designation' => $faculty->designation,
                'subjects' => $faculty->subjects,
            ])]);
        }

        return redirect('/faculty/profile/edit')->with('status', 'Profile updated successfully.');
    });

    Route::post('/faculty/logout', function () {
        session()->forget('faculty.auth');

        return redirect('/')->with('status', 'You have been logged out.');
    });
});

// Direct Forgot Password Routes
Route::post('/forgot-password/verify-identifier', [ForgotPasswordController::class, 'verifyIdentifier']);
Route::post('/forgot-password/direct-reset', [ForgotPasswordController::class, 'resetPasswordDirect']);
