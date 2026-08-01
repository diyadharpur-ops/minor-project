<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Classroom</title>
    <style>body{font-family:Arial, sans-serif; padding:24px; background:#f4f7fb} .card{background:white;padding:18px;border-radius:10px;max-width:700px;margin:0 auto} .btn{padding:8px 12px;border-radius:8px;text-decoration:none;color:white;background:#2563eb;border:none;cursor:pointer}</style>
</head>
<body>
    <div class="card">
        <h1>Add Classroom</h1>
        @if ($errors->any())
            <div style="color:#b91c1c">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/classrooms">
            @csrf
            <div>
                <label>Room Number</label><br>
                <input type="text" name="room_number" value="{{ old('room_number') }}" required />
            </div>
            <div>
                <label>Room Capacity</label><br>
                <input type="number" name="room_capacity" min="1" value="{{ old('room_capacity') }}" required />
            </div>
            <div>
                <label>Lab/Classroom Type</label><br>
                <select name="room_type" required>
                    <option value="">Select type</option>
                    <option value="Classroom" {{ old('room_type') === 'Classroom' ? 'selected' : '' }}>Classroom</option>
                    <option value="Lab" {{ old('room_type') === 'Lab' ? 'selected' : '' }}>Lab</option>
                </select>
            </div>
            <div>
                <label>Availability</label><br>
                <select name="availability" required>
                    <option value="Available" {{ old('availability') === 'Available' ? 'selected' : '' }}>Available</option>
                    <option value="Booked" {{ old('availability') === 'Booked' ? 'selected' : '' }}>Booked</option>
                    <option value="Maintenance" {{ old('availability') === 'Maintenance' ? 'selected' : '' }}>Maintenance</option>
                </select>
            </div>
            <div style="margin-top:12px">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/classrooms" style="background:#6b7280; color:white; padding:8px 12px; border-radius:8px; text-decoration:none; margin-left:8px">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
