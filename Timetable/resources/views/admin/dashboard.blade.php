@extends('admin.layout')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="page-header">
        <div>
            <h1>Welcome, {{ session('admin.auth.name') }}</h1>
            <p>Admin dashboard for timetable management</p>
        </div>
        <div class="profile">{{ session('admin.auth.email') }}</div>
    </div>

    <div class="card-grid">
        <div class="card">
            <h3>Manage Departments</h3>
            <p>Create and update department records.</p>
            <a href="/admin/departments" class="btn">Open</a>
        </div>
        <div class="card">
            <h3>Manage Faculty</h3>
            <p>Track faculty and their assignments.</p>
            <a href="/admin/faculties" class="btn">Open</a>
        </div>
        <div class="card">
            <h3>Manage Subjects</h3>
            <p>Organize subjects and curriculum details.</p>
            <a href="/admin/subjects" class="btn">Open</a>
        </div>
        <div class="card">
            <h3>Manage Classrooms</h3>
            <p>Control available classroom resources.</p>
            <a href="/admin/classrooms" class="btn">Open</a>
        </div>
        <div class="card">
            <h3>Notification Management</h3>
            <p>Send updates to faculty and students.</p>
            <a href="/admin/notifications" class="btn">Open</a>
        </div>
    </div>
@endsection
