<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Faculty</title>
    <style>body{font-family:Arial, sans-serif; padding:24px; background:#f4f7fb} .card{background:white;padding:18px;border-radius:10px;max-width:700px;margin:0 auto} .btn{padding:8px 12px;border-radius:8px;text-decoration:none;color:white;background:#2563eb;border:none;cursor:pointer}</style>
</head>
<body>
    <div class="card">
        <h1>Add Faculty</h1>
        @if ($errors->any())
            <div style="color:#b91c1c">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/faculties">
            @csrf
            <div>
                <label>Name</label><br>
                <input type="text" name="name" value="{{ old('name') }}" required />
            </div>
            <div>
                <label>Mobile Number</label><br>
                <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" required />
            </div>
            <div>
                <label>Email</label><br>
                <input type="email" name="email" value="{{ old('email') }}" required />
            </div>
            <div>
                <label>Qualification</label><br>
                <input type="text" name="qualification" value="{{ old('qualification') }}" required />
            </div>
            <div>
                <label>Department</label><br>
                <select name="department_id" required>
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Subjects</label><br>
                <textarea name="subjects">{{ old('subjects') }}</textarea>
            </div>
            <div style="margin-top:12px">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/faculties" style="background:#6b7280; color:white; padding:8px 12px; border-radius:8px; text-decoration:none; margin-left:8px">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
