<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal</title>
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
        .nav-links form button { width: 100%; border: none; cursor: pointer; text-align: left; }
        .content-pane { flex: 1; padding: 28px 32px; position: relative; }
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 28px; }
        .page-header h1 { margin: 0; font-size: 2rem; }
        
        /* Dropdown Styles */
        .profile-dropdown-container { position: relative; display: inline-block; }
        .profile-dropdown-btn { background: #fff; border: 1px solid #dfe4ed; padding: 10px 18px; border-radius: 20px; font-weight: 600; color: #34415d; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(15, 35, 82, 0.04); }
        .profile-dropdown-btn:hover { background: #f8fafc; }
        .profile-dropdown-content { display: none; position: absolute; right: 0; background-color: #fff; min-width: 200px; box-shadow: 0 8px 32px rgba(15, 35, 82, 0.12); border-radius: 12px; border: 1px solid #e2e8f0; z-index: 100; margin-top: 8px; overflow: hidden; }
        .profile-dropdown-container:hover .profile-dropdown-content { display: block; }
        .profile-dropdown-content a, .profile-dropdown-content button { color: #34415d; padding: 12px 16px; text-decoration: none; display: block; font-weight: 500; font-size: 0.95rem; border-bottom: 1px solid #f1f5f9; width: 100%; text-align: left; background: none; border-top: none; border-left: none; border-right: none; cursor: pointer; }
        .profile-dropdown-content a:hover, .profile-dropdown-content button:hover { background-color: #f8fafc; color: #0f3d5e; }
        .profile-dropdown-content form { margin: 0; }

        .message { margin-bottom: 18px; padding: 14px 16px; border-radius: 12px; }
        .message.success { background: #e6f9f2; border: 1px solid #8ae3b8; color: #0a5a34; }
        .message.error { background: #ffeced; border: 1px solid #f5a4a6; color: #8b252d; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 24px 80px rgba(15, 35, 82, 0.08); padding: 28px; margin-bottom: 24px; }
        .card h1, .card h2, .card h3 { margin-top: 0; }
        .grid { display: grid; gap: 18px; }
        .profile-field { display: grid; gap: 8px; }
        .profile-field label { font-weight: 600; color: #34415d; }
        .profile-field input, .profile-field textarea { border: 1px solid #dfe4ed; border-radius: 12px; padding: 12px 14px; width: 100%; font-size: 1rem; }
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
            <h2>{{ session('faculty.auth.name') ?? 'Faculty Name' }}</h2>
            <p>{{ session('faculty.auth.designation') ?? 'Designation' }}</p>
        </div>
        <nav class="nav-links" aria-label="Faculty navigation">
            <a href="/faculty/dashboard" class="{{ request()->is('faculty/dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="/faculty/profile/edit" class="{{ request()->is('faculty/profile*') ? 'active' : '' }}">Faculty Profile</a>
            <a href="/faculty/timetable" class="{{ request()->is('faculty/timetable') ? 'active' : '' }}">View Timetable</a>
            <a href="/faculty/subjects" class="{{ request()->is('faculty/subjects') ? 'active' : '' }}">Assigned Subjects</a>
            <a href="/faculty/notifications" class="{{ request()->is('faculty/notifications') ? 'active' : '' }}">Notifications</a>
        </nav>
    </aside>

    <main class="content-pane">
        <div class="page-header">
            <h1>Faculty Portal</h1>
            <div class="profile-dropdown-container">
                <button class="profile-dropdown-btn">
                    {{ session('faculty.auth.name') ?? 'Faculty' }}
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div class="profile-dropdown-content">
                    <a href="/faculty/profile/edit">My Profile</a>
                    <form action="/faculty/logout" method="POST">
                        @csrf
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </div>

        @if(session('status'))
            <div class="message success">{{ session('status') }}</div>
        @endif

        @if(session('error'))
            <div class="message error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="message error">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
