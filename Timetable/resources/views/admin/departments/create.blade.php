<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Department</title>
    <style>body{font-family:Arial, sans-serif; padding:24px; background:#f4f7fb} .card{background:white;padding:18px;border-radius:10px;max-width:700px;margin:0 auto}</style>
</head>
<body>
    <div class="card">
        <h1>Create Department</h1>
        @if ($errors->any())
            <div style="color:#b91c1c">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/departments">
            @csrf
            <div>
                <label>Name</label><br>
                <input type="text" name="name" value="{{ old('name') }}" required />
            </div>
            <div>
                <label>Code</label><br>
                <input type="text" name="code" value="{{ old('code') }}" />
            </div>
            <div>
                <label>Description</label><br>
                <textarea name="description">{{ old('description') }}</textarea>
            </div>
            <div style="margin-top:12px">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/departments" class="btn btn-muted" style="background:#6b7280; color:white; padding:8px 12px; border-radius:8px; text-decoration:none; margin-left:8px">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
