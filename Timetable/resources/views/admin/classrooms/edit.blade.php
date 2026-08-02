@extends('admin.layout')

@section('title', 'Edit Classroom')

@section('content')
    <div class="page-header">
        <div>
            <h1>Edit Classroom</h1>
            <p>Update the selected room or lab record.</p>
        </div>
        <a href="/admin/classrooms" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/classrooms/{{ $classroom->id }}">
            @csrf
            <div class="form-row">
                <label>Room Number</label>
                <input type="text" name="room_number" value="{{ old('room_number', $classroom->room_number) }}" required />
            </div>
            <div class="form-row">
                <label>Room Capacity</label>
                <input type="number" name="room_capacity" min="1" value="{{ old('room_capacity', $classroom->room_capacity) }}" required />
            </div>
            <div class="form-row">
                <label>Lab/Classroom Type</label>
                <select name="room_type" required>
                    <option value="">Select type</option>
                    <option value="Classroom" {{ old('room_type', $classroom->room_type) === 'Classroom' ? 'selected' : '' }}>Classroom</option>
                    <option value="Lab" {{ old('room_type', $classroom->room_type) === 'Lab' ? 'selected' : '' }}>Lab</option>
                </select>
            </div>
            <div class="form-row">
                <label>Availability</label>
                <select name="availability" required>
                    <option value="Available" {{ old('availability', $classroom->availability) === 'Available' ? 'selected' : '' }}>Available</option>
                    <option value="Booked" {{ old('availability', $classroom->availability) === 'Booked' ? 'selected' : '' }}>Booked</option>
                    <option value="Maintenance" {{ old('availability', $classroom->availability) === 'Maintenance' ? 'selected' : '' }}>Maintenance</option>
                </select>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Save</button>
                <a href="/admin/classrooms" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>
@endsection
