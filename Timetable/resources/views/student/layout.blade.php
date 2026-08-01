<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal</title>
    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f5f7fb; color: #17233d; }
        .shell { display: flex; min-height: 100vh; }
        .sidebar { width: 300px; background: #0f3d5e; color: #fff; padding: 28px 22px; display: flex; flex-direction: column; gap: 22px; }
        .sidebar .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: 0.02em; }
        .profile-summary { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14); border-radius: 18px; padding: 18px; }
        .profile-summary h2 { margin: 0 0 8px; font-size: 1rem; }
        .profile-summary p { margin: 0; color: #cbd8eb; line-height: 1.5; }
        .nav-links { display: flex; flex-direction: column; gap: 12px; }
        .nav-links a, .nav-links form button { display: inline-flex; align-items: center; justify-content: flex-start; padding: 12px 14px; border-radius: 12px; text-decoration: none; color: #fff; background: rgba(255,255,255,0.05); border: 1px solid transparent; font-weight: 600; }
        .nav-links a:hover, .nav-links form button:hover { background: rgba(255,255,255,0.12); }
        .nav-links a.active { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.22); }
        .nav-links form { margin: 0; }
        .content-pane { flex: 1; padding: 28px 32px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 28px; }
        .page-header h1 { margin: 0; font-size: 2rem; }
        .page-header .welcome { color: #4e607f; font-size: 1rem; }
        .message { margin-bottom: 18px; padding: 14px 16px; border-radius: 12px; }
        .message.success { background: #e6f9f2; border: 1px solid #8ae3b8; color: #0a5a34; }
        .message.error { background: #ffeced; border: 1px solid #f5a4a6; color: #8b252d; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 24px 80px rgba(15, 35, 82, 0.08); padding: 28px; margin-bottom: 24px; }
        .card h1, .card h2, .card h3 { margin-top: 0; }
        .grid { display: grid; gap: 18px; }
        .profile-field { display: grid; gap: 8px; }
        .profile-field label { font-weight: 600; color: #34415d; }
        .profile-field input { border: 1px solid #dfe4ed; border-radius: 12px; padding: 12px 14px; width: 100%; font-size: 1rem; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 12px; background: #0f3d5e; color: #fff; border: none; cursor: pointer; font-weight: 700; }
        .button.secondary { background: #e7edf5; color: #0f3d5e; }
        .list-card { background: #eef4ff; border-radius: 14px; padding: 18px; }
        .list-card a { display: block; color: #0f3d5e; text-decoration: none; padding: 12px 0; border-bottom: 1px solid rgba(15, 61, 94, 0.08); }
        .list-card a:last-child { border-bottom: none; }
        .section-intro { color: #4e607f; margin-bottom: 16px; }
        @media (max-width: 960px) {
            .shell { flex-direction: column; }
            .sidebar { width: 100%; }
            .content-pane { padding: 24px; }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">K. D. Polytechnic</div>
        <div class="profile-summary">
            <h2>{{ session('student.auth.name') ?? 'Student Name' }}</h2>
            <p>Enrollment: {{ session('student.auth.enrollment_number') ?? 'Not set' }}</p>
            <p>{{ session('student.auth.department') ?? 'Department not set' }}</p>
        </div>
        <nav class="nav-links" aria-label="Student navigation">
            <a href="/student/dashboard" class="{{ request()->is('student/dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="/student/timetable" class="{{ request()->is('student/timetable') ? 'active' : '' }}">View Timetable</a>
            <a href="/student/notifications" class="{{ request()->is('student/notifications') ? 'active' : '' }}">Receive Notifications</a>
            <a href="/student/profile/edit" class="{{ request()->is('student/profile*') ? 'active' : '' }}">Update Profile</a>
            <form action="/student/logout" method="POST">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </nav>
    </aside>

    <main class="content-pane">
        <div class="page-header">
            <h1>Student Portal</h1>
            <div class="welcome">Welcome, {{ session('student.auth.name') ?? 'Student' }}</div>
        </div>

        @if(session('status'))
            <div class="message success">{{ session('status') }}</div>
        @endif

        @if(session('error'))
            <div class="message error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
