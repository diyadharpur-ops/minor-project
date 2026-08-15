@extends('admin.layout')

@section('title', 'Faculty Workload Details')

@section('content')
    <div class="page-header">
        <div>
            <h1>Faculty Workload Details</h1>
            <p>Complete workload information for the selected faculty member.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/faculty-workload" class="btn btn-muted">Back</a>
            <a href="/admin/faculty-workload/{{ $workload->id }}/edit" class="btn">Edit</a>
        </div>
    </div>

    <div class="page-card">
        <div class="row">
            <span class="label">Faculty Name</span>
            <span>{{ $workload->faculty_name }}</span>
        </div>
        <div class="row">
            <span class="label">Faculty ID</span>
            <span>{{ $workload->faculty_id }}</span>
        </div>
        <div class="row">
            <span class="label">Department</span>
            <span>{{ $workload->department }}</span>
        </div>
        <div class="row">
            <span class="label">Subjects Assigned</span>
            <span>{{ $workload->subjects_assigned }}</span>
        </div>
        <div class="row">
            <span class="label">Theory Hours</span>
            <span>{{ $workload->theory_hours }}</span>
        </div>
        <div class="row">
            <span class="label">Practical/Lab Hours</span>
            <span>{{ $workload->practical_hours }}</span>
        </div>
        <div class="row">
            <span class="label">Total Hours per Week</span>
            <span>{{ $workload->total_hours }}</span>
        </div>
        <div class="row">
            <span class="label">Assigned Classes</span>
            <span>{{ $workload->assigned_classes ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">Free Periods</span>
            <span>{{ $workload->free_periods ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">Workload Status</span>
            <span>
                <span class="status-badge {{ strtolower($workload->workload_status) }}">{{ $workload->workload_status }}</span>
            </span>
        </div>
    </div>
@endsection
