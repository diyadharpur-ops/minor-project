<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f7fb; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: 100%; max-width: 560px; background: white; padding: 24px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .label { font-weight: 600; }
        .btn { display: inline-block; margin-top: 16px; padding: 10px 14px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Admin Profile</h1>
            <div class="row"><span class="label">Name</span><span>{{ session('admin.auth.name') }}</span></div>
            <div class="row"><span class="label">Email</span><span>{{ session('admin.auth.email') }}</span></div>
            <div class="row"><span class="label">Role</span><span>Administrator</span></div>
            <a href="/admin/dashboard" class="btn">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
