@extends('student.layout')

@section('content')
    <div class="card">
        <h2>Update Profile</h2>
        <p class="section-intro">Update your student profile details here.</p>
        <form action="/student/profile" method="POST" class="grid">
            @csrf
            @method('PUT')
            <div class="profile-field">
                <label for="name">Student Name</label>
                <input id="name" name="name" value="{{ old('name', session('student.auth.name')) }}" required>
            </div>
            <div class="profile-field">
                <label for="department">Department</label>
                <input id="department" name="department" value="{{ old('department', session('student.auth.department')) }}">
            </div>
            <div class="profile-field">
                <label for="semester">Semester</label>
                <input id="semester" name="semester" value="{{ old('semester', session('student.auth.semester')) }}">
            </div>
            <div class="profile-field">
                <label for="student_class">Class</label>
                <input id="student_class" name="student_class" value="{{ old('student_class', session('student.auth.student_class')) }}">
            </div>
            <div class="profile-field">
                <label for="divcon">DivCon</label>
                <input id="divcon" name="divcon" value="{{ old('divcon', session('student.auth.divcon')) }}">
            </div>
            <button class="button" type="submit">Save profile</button>
        </form>
    </div>
@endsection
