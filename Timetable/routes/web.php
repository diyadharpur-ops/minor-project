<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

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
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.dashboard');
});

Route::get('/admin/profile', function () {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.profile');
});

// Departments management
Route::get('/admin/departments', function (Request $request) {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    $q = $request->input('q');
    if ($q) {
        $departments = \App\Models\Department::where('name', 'like', "%{$q}%")
            ->orWhere('code', 'like', "%{$q}%")
            ->orderBy('created_at', 'desc')
            ->get();
    } else {
        $departments = \App\Models\Department::orderBy('created_at', 'desc')->get();
    }

    return view('admin.departments.index', ['departments' => $departments, 'q' => $q]);
});

Route::get('/admin/departments/create', function () {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.departments.create');
});

Route::post('/admin/departments', function (Request $request) {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:50',
        'description' => 'nullable|string',
    ]);

    \App\Models\Department::create($data);

    return redirect('/admin/departments');
});

Route::get('/admin/departments/{id}/edit', function ($id) {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    $dept = \App\Models\Department::findOrFail($id);
    return view('admin.departments.edit', ['dept' => $dept]);
});

Route::post('/admin/departments/{id}', function (Request $request, $id) {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:50',
        'description' => 'nullable|string',
    ]);

    $dept = \App\Models\Department::findOrFail($id);
    $dept->update($data);

    return redirect('/admin/departments');
});

Route::post('/admin/departments/{id}/delete', function ($id) {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    $dept = \App\Models\Department::find($id);
    if ($dept) {
        $dept->delete();
    }

    return redirect('/admin/departments');
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

        return view('student.notifications');
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
