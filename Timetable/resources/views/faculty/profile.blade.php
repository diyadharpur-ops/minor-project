@extends('faculty.layout')

@section('content')
<div class="card" style="max-width: 600px;">
    <h2>Update Profile</h2>
    <p class="section-intro">Manage your personal information.</p>
    
    <form action="/faculty/profile" method="POST" class="grid">
        @csrf
        @method('PUT')
        
        <div class="profile-field">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="{{ session('faculty.auth.name') }}" required>
        </div>

        <div class="profile-field">
            <label for="email">Email Address</label>
            <input type="email" id="email" value="{{ session('faculty.auth.email') }}" readonly style="background: #f5f7fb; cursor: not-allowed;">
            <small style="color: #6b7280; font-size: 0.85rem;">Email cannot be changed.</small>
        </div>

        <div class="profile-field">
            <label for="designation">Designation</label>
            <input type="text" id="designation" name="designation" value="{{ session('faculty.auth.designation') }}" required>
        </div>

        <div class="profile-field">
            <label for="subjects">Subjects (Informational)</label>
            <textarea id="subjects" name="subjects" rows="3">{{ session('faculty.auth.subjects') }}</textarea>
        </div>

        <div style="margin-top: 10px;">
            <button type="submit" class="button">Save Changes</button>
        </div>
    </form>
</div>
@endsection
