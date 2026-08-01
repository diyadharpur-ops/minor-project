<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Subject</title>
    <style>body{font-family:Arial, sans-serif; padding:24px; background:#f4f7fb} .card{background:white;padding:18px;border-radius:10px;max-width:700px;margin:0 auto} .btn{padding:8px 12px;border-radius:8px;text-decoration:none;color:white;background:#2563eb;border:none;cursor:pointer}</style>
</head>
<body>
    <div class="card">
        <h1>Add Subject</h1>
        @if ($errors->any())
            <div style="color:#b91c1c">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/subjects">
            @csrf
            <div>
                <label>Subject Name</label><br>
                <input type="text" name="name" value="{{ old('name') }}" required />
            </div>
            <div>
                <label>Subject Code</label><br>
                <input type="text" name="subject_code" value="{{ old('subject_code') }}" required />
            </div>
            <div>
                <label>Semester</label><br>
                <input type="text" name="semester" value="{{ old('semester') }}" required />
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
                <label>Credit</label><br>
                <input type="number" name="credit" min="1" max="10" value="{{ old('credit') }}" />
            </div>
            <div>
                <label>Faculty Name</label><br>
                <input type="text" name="faculty_name" value="{{ old('faculty_name') }}" />
            </div>
            <div style="margin-top:12px">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/subjects" style="background:#6b7280; color:white; padding:8px 12px; border-radius:8px; text-decoration:none; margin-left:8px">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
