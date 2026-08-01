<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f7fb; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: 100%; max-width: 460px; background: white; padding: 28px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; font-size: 1.5rem; }
        .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        label { font-weight: 600; }
        input { padding: 10px 12px; border: 1px solid #dce4ee; border-radius: 10px; }
        button { padding: 10px 16px; background: #2563eb; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .error { color: #dc2626; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Admin Login</h1>
            @if ($errors->any())
                <div class="error">{{ $errors->first('email') }}</div>
            @endif
            <form method="POST" action="/admin/login">
                @csrf
                <div class="field">
                    <label for="email">Admin Email</label>
                    <input id="email" name="email" type="email" value="admin@example.com" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" value="admin" required>
                </div>
                <button type="submit">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
