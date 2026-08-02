<?php

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Faculty;
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

    return view('admin.dashboard');
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
Route::get('/admin/timetable', function () {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $semesters = Subject::select('semester')->distinct()->pluck('semester')->filter()->values();

    return view('admin.timetable.index', [
        'semesters' => $semesters,
        'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'timeSlots' => ['09:00-10:00', '10:00-11:00', '11:00-12:00', '01:00-02:00', '02:00-03:00'],
        'timetable' => session('timetable'),
    ]);
});

Route::post('/admin/timetable/generate', function (Request $request) {
    if (! session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'semester' => 'required|string',
        'days' => 'nullable|string',
        'time_slots' => 'nullable|string',
    ]);

    $days = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|,/', $data['days'] ?? ''))));
    $timeSlots = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|,/', $data['time_slots'] ?? ''))));

    if ($days === []) {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    }

    if ($timeSlots === []) {
        $timeSlots = ['09:00-10:00', '10:00-11:00', '11:00-12:00', '01:00-02:00', '02:00-03:00'];
    }

    $subjects = Subject::where('semester', $data['semester'])->get()->map(function ($subject) {
        $type = Str::contains(Str::lower($subject->name), 'lab') ? 'lab' : 'theory';
        $theoryHours = $subject->credit ?? 0;
        $practicalHours = $type === 'lab' ? 2 : 0;

        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'subject_code' => $subject->subject_code,
            'semester' => $subject->semester,
            'credit' => (int) ($subject->credit ?? 0),
            'theory_hours' => $type === 'theory' ? $theoryHours : 0,
            'practical_hours' => $practicalHours,
            'faculty_name' => $subject->faculty_name,
        ];
    })->values()->all();

    $faculties = Faculty::all()->map(function ($faculty) {
        return [
            'id' => $faculty->id,
            'name' => $faculty->name,
            'availability' => $faculty->designation ?? 'Available',
            'subjects' => $faculty->subjects ? explode(',', $faculty->subjects) : [],
        ];
    })->values()->all();

    $classrooms = Classroom::all()->map(function ($classroom) {
        return [
            'id' => $classroom->id,
            'room_number' => $classroom->room_number,
            'room_type' => $classroom->room_type,
            'availability' => $classroom->availability,
        ];
    })->values()->all();

    $generator = new TimetableGenerator();
    $timetable = $generator->generate($data['semester'], $days, $timeSlots, $subjects, $faculties, $classrooms);

    $path = 'timetables/generated-'.now()->format('YmdHis').'.json';
    Storage::disk('local')->put($path, json_encode($timetable, JSON_PRETTY_PRINT));

    session(['timetable' => $timetable]);

    return redirect('/admin/timetable')->with('status', 'Timetable generated: '.$path);
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

Route::post('/admin/logout', function () {
    session()->forget('admin.auth');

    return redirect('/');
});

Route::post('/student/register', function (Request $request) {
    $data = $request->validate([
        'enrollment_number' => 'required|string|max:255|unique:users,enrollment_number',
        'name' => 'required|string|max:255',
        'department' => 'nullable|string|max:255',
        'semester' => 'nullable|string|max:255',
        'student_class' => 'nullable|string|max:255',
        'divcon' => 'nullable|string|max:255',
        'password' => 'required|string|confirmed|min:8',
    ]);

    $user = User::create([
        'name' => $data['name'],
        'enrollment_number' => $data['enrollment_number'],
        'email' => $data['enrollment_number'].'@student.local',
        'department' => $data['department'] ?? null,
        'semester' => $data['semester'] ?? null,
        'student_class' => $data['student_class'] ?? null,
        'divcon' => $data['divcon'] ?? null,
        'password' => Hash::make($data['password']),
    ]);

    session(['student.auth' => [
        'id' => $user->id,
        'name' => $user->name,
        'enrollment_number' => $user->enrollment_number,
        'department' => $user->department,
        'semester' => $user->semester,
        'student_class' => $user->student_class,
        'divcon' => $user->divcon,
    ]]);

    return redirect('/student/dashboard')->with('status', 'Successfully registered.');
});

Route::post('/faculty/login', function (Request $request) {
    $data = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    $faculty = Faculty::where('email', $data['email'])->first();

    // Fallback for default testing credentials
    if ($data['email'] === 'faculty@example.com' && $data['password'] === 'password' && !$faculty) {
        session(['faculty.auth' => [
            'id' => 999,
            'name' => 'Demo Faculty',
            'email' => 'faculty@example.com',
            'designation' => 'Professor',
            'department_id' => null,
            'subjects' => 'Demo Subject',
        ]]);
        return redirect('/faculty/dashboard');
    }

    if (! $faculty || ! Hash::check($data['password'], $faculty->password)) {
        return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
    }

    session(['faculty.auth' => [
        'id' => $faculty->id,
        'name' => $faculty->name,
        'email' => $faculty->email,
        'designation' => $faculty->designation,
        'department_id' => $faculty->department_id,
        'subjects' => $faculty->subjects,
    ]]);

    return redirect('/faculty/dashboard');
});

Route::post('/student/login', function (Request $request) {
    $data = $request->validate([
        'enrollment_number' => 'required|string',
        'password' => 'required|string',
    ]);

    $user = User::where('enrollment_number', $data['enrollment_number'])->first();

    if (! $user || ! Hash::check($data['password'], $user->password)) {
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
    Route::get('/student/dashboard', function () {
        if (! session('student.auth')) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        return view('student.dashboard');
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

