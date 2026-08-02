@extends('admin.layout')

@section('title', 'Reports')

@section('content')
    <div class="page-header">
        <div>
            <h1>Reports</h1>
            <p>View and export generated reports for timetable and attendance.</p>
        </div>
        <a href="/admin/dashboard" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        <h3>Available Reports</h3>
        <ul>
            <li><a href="#">Timetable PDF</a></li>
            <li><a href="#">Faculty Assignments</a></li>
            <li><a href="#">Classroom Utilization</a></li>
        </ul>
    </div>
@endsection
