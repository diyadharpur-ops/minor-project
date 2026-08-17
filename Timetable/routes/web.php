<?php

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\FacultyWorkload;
use App\Models\Notification;
use App\Models\Subject;
use App\Models\User;
use App\Services\TimetableGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'classrooms' => \App\Models\Classroom::count(),
        'students' => User::count(),
        'active_timetables' => \App\Models\TimetableEntry::select('department_id', 'semester', 'division', 'academic_year', 'term')
            ->distinct()
            ->count(),
    ];

    return view('admin.dashboard', compact('stats'));
});

Route::match(['get', 'post'], '/admin/conflicts', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $entries = \App\Models\TimetableEntry::with(['subject', 'faculty', 'classroom'])
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
            $key = $entry->faculty_id . '|' . $entry->day . '|' . $entry->time_slot;
            $facultySlots[$key][] = $entry;
        }

        if ($entry->classroom_id && $entry->day && $entry->time_slot) {
            $classroomKey = $entry->classroom_id . '|' . $entry->day . '|' . $entry->time_slot;
            $classroomSlots[$classroomKey][] = $entry;

            if ($entry->classroom && str_contains(strtolower((string) $entry->classroom->room_type), 'lab')) {
                $labSlots[$classroomKey][] = $entry;
            }
        }

        if ($entry->subject_id && $entry->day && $entry->time_slot) {
            $subjectKey = $entry->subject_id . '|' . $entry->day . '|' . $entry->time_slot;
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

Route::get('/admin/notifications', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $notifications = Notification::orderBy('created_at', 'desc')->get();

    return view('admin.notifications.index', ['notifications' => $notifications]);
});

Route::post('/admin/notifications', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'title' => 'required|string|max:255',
        'type' => 'required|string|max:100',
        'message' => 'required|string',
        'audience' => 'required|string|in:student,faculty,all',
    ]);

    Notification::create($data);

    return redirect('/admin/notifications');
});

// Timetable generation
Route::match(['get', 'post'], '/admin/timetable', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $semesters = Subject::select('semester')->distinct()->pluck('semester')->filter()->values();

    $departments = Department::all();

    $timeSlots = ['08:30-09:30', '09:30-10:30', '10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10', '05:10-06:10'];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    $timetable = null;
    $deptName = null;

    if (request()->isMethod('post') || (request()->has('department_id') && request()->has('semester') && request()->has('division'))) {
        $deptId = request('department_id');
        $semester = request('semester');
        $division = request('division');
        $academicYear = request('academic_year');
        $term = request('term');

        $entries = \App\Models\TimetableEntry::with(['subject', 'faculty', 'classroom'])
            ->where('department_id', $deptId)
            ->where('semester', $semester)
            ->where('division', $division)
            ->get();
        
        $dept = Department::find($deptId);
        $deptName = $dept ? $dept->name : '';

        // format to match index.blade.php expectations
        $sessions = [];
        foreach ($entries as $entry) {
            $sessions[] = [
                'day' => $entry->day,
                'time_slot' => $entry->time_slot,
                'subject' => $entry->subject ? $entry->subject->name : '',
                'faculty' => $entry->faculty ? $entry->faculty->name : '',
                'room' => $entry->classroom ? $entry->classroom->room_number : '',
                'type' => $entry->lecture_type,
                'duration' => $entry->duration,
            ];
        }

        $timetable = [
            'days' => $days,
            'time_slots' => $timeSlots,
            'sessions' => $sessions,
            'academic_year' => $academicYear,
            'term' => $term,
            'division' => $division
        ];
    }

    return view('admin.timetable.index', [
        'departments' => $departments,
        'timetable' => $timetable,
        'deptName' => $deptName,
        'semester' => request('semester'),
        'division' => request('division', 'A'),
        'academicYear' => request('academic_year', date('Y').'-'.(date('Y')+1)),
        'term' => request('term', 'Odd')
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

    $timeSlots = ['08:30-09:30', '09:30-10:30', '10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10', '05:10-06:10'];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    $entries = collect([]);
    if ($request->has('department_id') && $request->has('semester') && $request->has('division')) {
        $entries = \App\Models\TimetableEntry::where('department_id', $request->department_id)
            ->where('semester', $request->semester)
            ->where('division', $request->division)
            ->get();
    }

    return view('admin.timetable.builder', compact('departments', 'subjects', 'faculties', 'classrooms', 'timeSlots', 'days', 'entries'));
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
        'entries' => 'nullable|array'
    ]);

    // Validation: prevent duplicate faculty and room
    $entriesData = $request->input('entries', []);
    $facultyTimeSlots = [];
    $roomTimeSlots = [];

    foreach ($entriesData as $key => $entry) {
        if (empty($entry['subject_id'])) continue; // skip empty cells

        $daySlot = $entry['day'] . '_' . $entry['time_slot'];

        if (!empty($entry['faculty_id'])) {
            $facultySlot = $entry['faculty_id'] . '_' . $daySlot;
            if (isset($facultyTimeSlots[$facultySlot])) {
                return back()->with('error', 'Duplicate faculty assignment detected for ' . $entry['day'] . ' at ' . $entry['time_slot'])->withInput();
            }
            $facultyTimeSlots[$facultySlot] = true;
        }

        if (!empty($entry['classroom_id'])) {
            $roomSlot = $entry['classroom_id'] . '_' . $daySlot;
            if (isset($roomTimeSlots[$roomSlot])) {
                return back()->with('error', 'Duplicate room assignment detected for ' . $entry['day'] . ' at ' . $entry['time_slot'])->withInput();
            }
            $roomTimeSlots[$roomSlot] = true;
        }
    }

    // Delete existing entries for this class
    \App\Models\TimetableEntry::where('department_id', $data['department_id'])
        ->where('semester', $data['semester'])
        ->where('division', $data['division'])
        ->delete();

    // Insert new entries
    foreach ($entriesData as $key => $entry) {
        if (empty($entry['subject_id'])) continue;

        \App\Models\TimetableEntry::create([
            'department_id' => $data['department_id'],
            'semester' => $data['semester'],
            'division' => $data['division'],
            'academic_year' => $data['academic_year'],
            'term' => $data['term'],
            'day' => $entry['day'],
            'time_slot' => $entry['time_slot'],
            'subject_id' => $entry['subject_id'] ?: null,
            'faculty_id' => $entry['faculty_id'] ?: null,
            'classroom_id' => $entry['classroom_id'] ?: null,
            'lecture_type' => $entry['lecture_type'] ?: null,
            'duration' => $entry['duration'] ?? 1,
            'notes' => null
        ]);
    }

    return redirect('/admin/timetable/builder?department_id='.$data['department_id'].'&semester='.$data['semester'].'&division='.$data['division'].'&academic_year='.$data['academic_year'].'&term='.$data['term'])->with('status', 'Timetable saved successfully!');
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

    Department::create($data);

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
        'subjects' => 'nullable|string|max:1000',
    ]);

    $faculty = Faculty::create($data);

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
            'subjects' => $faculty->subjects,
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
        'subjects' => 'nullable|string|max:1000',
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
            'subjects' => $faculty->subjects,
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

    $departments = FacultyWorkload::query()
        ->whereNotNull('department')
        ->where('department', '!=', '')
        ->distinct()
        ->orderBy('department')
        ->pluck('department');

    return view('admin.faculty-workload.index', [
        'workloads' => $workloads,
        'departments' => $departments,
        'q' => $search,
        'departmentFilter' => $departmentFilter,
        'statusFilter' => $statusFilter,
    ]);
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

    FacultyWorkload::create($data);

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

Route::get('/admin/subjects', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $q = $request->input('q');
    $query = Subject::query()->with('department');

    if ($q) {
        $query->where('name', 'like', "%{$q}%")
            ->orWhere('subject_code', 'like', "%{$q}%")
            ->orWhere('semester', 'like', "%{$q}%")
            ->orWhere('faculty_name', 'like', "%{$q}%");
    }

    $subjects = $query->orderBy('created_at', 'desc')->get();

    return view('admin.subjects.index', ['subjects' => $subjects, 'q' => $q]);
});

Route::get('/admin/subjects/create', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.subjects.create', ['departments' => Department::orderBy('name')->get()]);
});

Route::post('/admin/subjects', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'subject_code' => 'required|string|max:50|unique:subjects,subject_code',
        'semester' => 'required|string|max:20',
        'department_id' => 'required|exists:departments,id',
        'credit' => 'nullable|integer|min:1|max:10',
        'faculty_name' => 'nullable|string|max:255',
        'subject_type' => 'required|string|in:lecture,lab,tutorial',
    ]);

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
            'department' => $department->name,
            'credit' => $subject->credit,
            'faculty_name' => $subject->faculty_name,
            'subject_type' => $subject->subject_type,
        ], JSON_PRETTY_PRINT));
        $subject->update(['folder_path' => $filePath]);
    }

    return redirect('/admin/subjects');
});

Route::get('/admin/subjects/{id}/edit', function ($id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $subject = Subject::findOrFail($id);

    return view('admin.subjects.edit', ['subject' => $subject, 'departments' => Department::orderBy('name')->get()]);
});

Route::post('/admin/subjects/{id}', function (Request $request, $id) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $subject = Subject::findOrFail($id);

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'subject_code' => 'required|string|max:50|unique:subjects,subject_code,'.$subject->id,
        'semester' => 'required|string|max:20',
        'department_id' => 'required|exists:departments,id',
        'credit' => 'nullable|integer|min:1|max:10',
        'faculty_name' => 'nullable|string|max:255',
        'subject_type' => 'required|string|in:lecture,lab,tutorial',
    ]);

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
            'department' => $department->name,
            'credit' => $subject->credit,
            'faculty_name' => $subject->faculty_name,
            'subject_type' => $subject->subject_type,
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
                    ->where('department', $dept->name)
                    ->where('semester', $base->semester)
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
                    ->where('department', $group['department_name'])
                    ->where('semester', $group['semester']);
                
                if ($group['division']) {
                    $query->where('divcon', $group['division']);
                }

                $studentStrength = $query->count();
                $capacityToUse = $studentStrength > 0 ? $studentStrength : 60; // Fallback capacity

                // Find Subjects
                $subjects = \App\Models\Subject::where('department_id', $group['department_id'])
                    ->where('semester', $group['semester'])
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
                            'department_id' => $group['department_id'],
                            'semester' => $group['semester'],
                            'subject_id' => $subject->id,
                            'faculty_id' => null, // No faculty logic
                            'classroom_id' => $allocatedRoom->id,
                            'class_name' => $group['class_name'],
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
                            'department_id' => $group['department_id'],
                            'semester' => $group['semester'],
                            'subject_id' => $subject->id,
                            'faculty_id' => null,
                            'classroom_id' => null,
                            'class_name' => $group['class_name'],
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

    $allocation = \App\Models\RoomAllocation::with(['department', 'subject', 'faculty', 'classroom'])->findOrFail($id);

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

    $allocation = \App\Models\RoomAllocation::findOrFail($id);

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

    $allocation = \App\Models\RoomAllocation::find($id);
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

    User::create([
        'name' => $data['name'],
        'enrollment_number' => $data['enrollment_number'],
        'email' => $data['email'],
        'department' => $data['department'],
        'semester' => $data['semester'],
        'student_class' => $data['student_class'] ?? null,
        'divcon' => $data['divcon'] ?? null,
        'password' => Hash::make($data['password']),
    ]);

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

    if (! Hash::check($data['password'], $faculty->password)) {
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

    if (! Hash::check($data['password'], $user->password)) {
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
        } catch (\Throwable $e) {
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

        return view('student.timetable');
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
        return view('faculty.timetable');
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

