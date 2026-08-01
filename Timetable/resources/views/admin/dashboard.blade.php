<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: #0f3d5e; color: white; padding: 24px 18px; }
        .sidebar h2 { margin-top: 0; font-size: 1.15rem; }
        .sidebar a { display: block; color: white; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; background: rgba(255,255,255,0.12); }
        .sidebar a:hover { background: #1f6f9f; }
        .main { flex: 1; padding: 24px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .card { background: white; padding: 18px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .card h3 { margin-top: 0; }
        .btn { display: inline-block; margin-top: 10px; padding: 8px 12px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; }
        .profile { color: #2563eb; font-weight: 600; }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <a href="/admin/dashboard">Dashboard</a>
            <a href="#">Manage Departments</a>
            <a href="/admin/faculties">Manage Faculty</a>
            <a href="/admin/subjects">Manage Subjects</a>
            <a href="#">Manage Classrooms</a>
            <a href="#">Notification Management</a>
            <a href="#">Reports</a>
            <a href="#">Generate Timetable</a>
            <a href="/admin/profile">Admin Profile</a>
            <form method="POST" action="/admin/logout">
                @csrf
                <button type="submit" style="margin-top:12px; padding:10px 12px; border:none; border-radius:8px; background:#ef4444; color:white; width:100%; cursor:pointer;">Logout</button>
            </form>
        </aside>
        <main class="main">
            <div class="topbar">
                <div>
                    <h1 style="margin:0;">Welcome, {{ session('admin.auth.name') }}</h1>
                    <p style="margin:4px 0 0; color:#6b7280;">Admin dashboard for timetable management</p>
                </div>
                <div class="profile">{{ session('admin.auth.email') }}</div>
            </div>

            <div class="card-grid">
                <div class="card">
                    <h3>Manage Departments</h3>
                    <p>Create and update department records.</p>
                    <a href="/admin/departments" class="btn">Open</a>
                </div>
                <div class="card">
                    <h3>Manage Faculty</h3>
                    <p>Track faculty and their assignments.</p>
                    <a href="/admin/faculties" class="btn">Open</a>
                </div>
                <div class="card">
                    <h3>Manage Subjects</h3>
                    <p>Organize subjects and curriculum details.</p>
                    <a href="/admin/subjects" class="btn">Open</a>
                </div>
                <div class="card">
                    <h3>Manage Classrooms</h3>
                    <p>Control available classroom resources.</p>
                    <a href="#" class="btn">Open</a>
                </div>
                <div class="card">
                    <h3>Notification Management</h3>
                    <p>Send updates to faculty and students.</p>
                    <a href="#" class="btn">Open</a>
                </div>
                <div class="card">
                    <h3>Reports</h3>
                    <p>Review staff, classes, and timetables.</p>
                    <a href="#" class="btn">Open</a>
                </div>
                <div class="card">
                    <h3>Generate Timetable</h3>
                    <p>Create and manage the timetable layout.</p>
                    <a href="#" class="btn">Open</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
