@extends('admin.layout')

@section('title', 'Faculty Workload Details')

@section('content')
    <div class="page-header">
        <div>
            <h1>Faculty Workload Details</h1>
            <p>Complete workload breakdown for the selected faculty assignment.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/faculty-workload" class="btn btn-muted">Back</a>
            <a href="/admin/faculty-workload/{{ $workload->id }}/edit" class="btn">Edit</a>
        </div>
    </div>

    <div class="page-card">
        <div class="row">
            <span class="label">Faculty Name</span>
            <span>{{ $workload->faculty?->name ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">Faculty ID</span>
            <span>{{ $workload->faculty?->id ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">Department</span>
            <span>{{ $workload->department?->name ?? 'N/A' }}</span>
        </div>
        <div class="row">
            <span class="label">Assigned Subject</span>
            <span>{{ $workload->subject?->name ?? 'N/A' }}</span>
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
            <span class="label">Total Weekly Hours</span>
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
                <span class="status-badge {{ strtolower($workload->status) }}">{{ $workload->status }}</span>
            </span>
        </div>
    </div>
@endsection
