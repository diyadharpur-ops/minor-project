<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Panel')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2937; }
        .admin-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #0f3d5e 0%, #0b2d4a 100%); color: white; padding: 24px 18px; display: flex; flex-direction: column; gap: 10px; }
        .sidebar .brand { font-size: 1.15rem; font-weight: 700; margin-bottom: 10px; }
        .sidebar .brand small { display: block; font-size: 0.82rem; color: #dbeafe; margin-top: 6px; }
        .sidebar-link { display: block; color: white; text-decoration: none; padding: 10px 12px; border-radius: 10px; background: rgba(255,255,255,0.12); transition: background 0.2s ease; }
        .sidebar-link:hover, .sidebar-link.active { background: #1f6f9f; }
        .sidebar form { margin-top: auto; }
        .logout-btn { width: 100%; padding: 10px 12px; border: none; border-radius: 8px; background: #ef4444; color: white; cursor: pointer; font-weight: 600; }
        .main-content { flex: 1; padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 16px 20px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); margin-bottom: 18px; gap: 16px; }
        .page-header h1, .page-header h2 { margin: 0; }
        .page-header p { margin: 4px 0 0; color: #6b7280; }
        .page-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); margin-bottom: 16px; }
        .page-card h1, .page-card h2, .page-card h3 { margin-top: 0; }
        .page-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 8px 12px; border-radius: 8px; text-decoration: none; color: white; background: #2563eb; border: none; cursor: pointer; font-weight: 600; }
        .btn-muted { background: #6b7280; }
        .btn-danger { background: #ef4444; }
        .profile { color: #2563eb; font-weight: 600; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .card { background: white; padding: 18px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .card h3 { margin-top: 0; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #eef2f7; }
        .actions form { display: inline; }
        .search { display: flex; gap: 8px; flex-wrap: wrap; }
        .search input, .form-row input, .form-row select, .form-row textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; }
        .form-row { display: grid; gap: 8px; margin-bottom: 12px; }
        .form-row label { font-weight: 600; }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; background: #fee2e2; color: #991b1b; }
        .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .label { font-weight: 600; }
        @media (max-width: 900px) {
            .admin-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .main-content { padding: 16px; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                Admin Panel
                <small>Timetable Management</small>
            </div>
            <a href="/admin/dashboard" class="sidebar-link {{ request()->is('admin/dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="/admin/departments" class="sidebar-link {{ request()->is('admin/departments*') ? 'active' : '' }}">Manage Departments</a>
            <a href="/admin/faculties" class="sidebar-link {{ request()->is('admin/faculties*') ? 'active' : '' }}">Manage Faculty</a>
            <a href="/admin/subjects" class="sidebar-link {{ request()->is('admin/subjects*') ? 'active' : '' }}">Manage Subjects</a>
            <a href="/admin/classrooms" class="sidebar-link {{ request()->is('admin/classrooms*') ? 'active' : '' }}">Manage Classrooms</a>
            <a href="/admin/notifications" class="sidebar-link {{ request()->is('admin/notifications*') ? 'active' : '' }}">Notification Management</a>
            <a href="/admin/profile" class="sidebar-link {{ request()->is('admin/profile') ? 'active' : '' }}">Admin Profile</a>
            <form method="POST" action="/admin/logout">
                @csrf
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </aside>

        <main class="main-content">
            @yield('content')
        </main>
    </div>
</body>
</html>
