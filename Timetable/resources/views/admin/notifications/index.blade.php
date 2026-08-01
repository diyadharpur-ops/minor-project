<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notifications</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: #0f3d5e; color: white; padding: 24px 18px; }
        .sidebar a { display: block; color: white; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; background: rgba(255,255,255,0.12); }
        .main { flex: 1; padding: 24px; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); margin-bottom: 16px; }
        .row { display: grid; gap: 12px; margin-bottom: 12px; }
        .row label { font-weight: 600; }
        .row input, .row select, .row textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; }
        .btn { display: inline-block; padding: 10px 14px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .list-item { padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <h2>Admin Panel</h2>
        <a href="/admin/dashboard">Dashboard</a>
        <a href="/admin/notifications">Notification Management</a>
        <a href="/admin/profile">Admin Profile</a>
    </aside>
    <main class="main">
        <div class="card">
            <h2>Manage Notifications</h2>
            <p>Create announcements for students, faculty, or everyone.</p>
            <form method="POST" action="/admin/notifications">
                @csrf
                <div class="row">
                    <label>Title</label>
                    <input type="text" name="title" required>
                </div>
                <div class="row">
                    <label>Type</label>
                    <select name="type" required>
                        <option>Holiday</option>
                        <option>Exam Schedule</option>
                        <option>New Timetable</option>
                        <option>Event</option>
                        <option>Meeting</option>
                        <option>Faculty</option>
                    </select>
                </div>
                <div class="row">
                    <label>Audience</label>
                    <select name="audience" required>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="row">
                    <label>Message</label>
                    <textarea name="message" rows="4" required></textarea>
                </div>
                <button class="btn" type="submit">Publish Notification</button>
            </form>
        </div>

        <div class="card">
            <h3>Recent Notifications</h3>
            @forelse($notifications as $notification)
                <div class="list-item">
                    <strong>{{ $notification->title }}</strong> <span style="color:#4b5563;">({{ $notification->type }})</span><br>
                    <span style="color:#4b5563;">{{ $notification->message }}</span><br>
                    <small>Audience: {{ $notification->audience }} • Posted on {{ $notification->created_at->format('M d, Y') }}</small>
                </div>
            @empty
                <p>No notifications yet.</p>
            @endforelse
        </div>
    </main>
</div>
</body>
</html>
