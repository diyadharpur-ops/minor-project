@extends('admin.layout')

@section('title', 'Create Classroom')

@section('content')
    <div class="page-header">
        <div>
            <h1>Add Classroom</h1>
            <p>Create a new room or lab entry.</p>
        </div>
        <a href="/admin/classrooms" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/classrooms">
            @csrf
            <div class="form-row">
                <label>Room Number</label>
                <input type="text" name="room_number" value="{{ old('room_number') }}" required />
            </div>
            <div class="form-row">
                <label>Room Capacity</label>
                <input type="number" name="room_capacity" min="1" value="{{ old('room_capacity') }}" required />
            </div>
            <div class="form-row">
                <label>Lab/Classroom Type</label>
                <select name="room_type" required>
                    <option value="">Select type</option>
                    <option value="Classroom" {{ old('room_type') === 'Classroom' ? 'selected' : '' }}>Classroom</option>
                    <option value="Lab" {{ old('room_type') === 'Lab' ? 'selected' : '' }}>Lab</option>
                </select>
            </div>
            <div class="form-row">
                <label>Availability</label>
                <select name="availability" required>
                    <option value="Available" {{ old('availability') === 'Available' ? 'selected' : '' }}>Available</option>
                    <option value="Booked" {{ old('availability') === 'Booked' ? 'selected' : '' }}>Booked</option>
                    <option value="Maintenance" {{ old('availability') === 'Maintenance' ? 'selected' : '' }}>Maintenance</option>
                </select>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/classrooms" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>
@endsection
