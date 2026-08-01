<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Notification;
use App\Models\Subject;
use App\Models\User;
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
        'description' => 'nullable|string',
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
        'description' => 'nullable|string',
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
            ->orWhere('email', 'like', "%{$q}%")
            ->orWhere('qualification', 'like', "%{$q}%");
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
        'mobile_number' => 'required|string|max:20',
        'email' => 'required|email|max:255|unique:faculties,email',
        'qualification' => 'required|string|max:255',
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
            'mobile_number' => $faculty->mobile_number,
            'email' => $faculty->email,
            'qualification' => $faculty->qualification,
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
        'mobile_number' => 'required|string|max:20',
        'email' => 'required|email|max:255|unique:faculties,email,'.$faculty->id,
        'qualification' => 'required|string|max:255',
        'department_id' => 'required|exists:departments,id',
        'subjects' => 'nullable|string|max:1000',
    ]);

    $faculty->update($data);

    if ($department = Department::find($data['department_id'])) {
        $folder = 'faculty-records/'.Str::slug($department->name);
        Storage::disk('local')->makeDirectory($folder);
        $filePath = $folder.'/faculty-'.$faculty->id.'.json';
        Storage::disk('local')->put($filePath, json_encode([
            'id' => $faculty->id,
            'name' => $faculty->name,
            'mobile_number' => $faculty->mobile_number,
            'email' => $faculty->email,
            'qualification' => $faculty->qualification,
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
});
