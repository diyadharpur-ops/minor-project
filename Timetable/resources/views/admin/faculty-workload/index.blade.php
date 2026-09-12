@extends('admin.layout')

@section('title', 'Faculty Workload Management')

@section('content')
    <style>
        .workload-page {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .workload-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 12px;
        }

        .search-bar input,
        .search-bar select {
            min-width: 170px;
            flex: 1 1 180px;
            padding: 10px 12px;
            border: 1px solid #dbe3ec;
            border-radius: 8px;
            background: #fff;
        }

        .auto-generate-form {
            width: 100%;
        }

        .danger-box,
        .success-box,
        .warning-box {
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid transparent;
            font-size: 14px;
            font-weight: 600;
        }

        .success-box {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .warning-box {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #9a4d00;
        }

        .danger-box {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .alert-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }

        .summary-card {
            background: #f8fbff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 14px;
        }

        .summary-card .label {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
        }

        .summary-card .value {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }

        .faculty-total-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }

        .faculty-total-item {
            background: #eef6ff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 10px 12px;
            color: #0f172a;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 5px 10px;
            font-weight: 700;
            font-size: 12px;
        }

        .status-badge.normal {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.overloaded {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 42px 18px;
            color: #475569;
        }

        .empty-state h3 {
            color: #0f172a;
            margin-bottom: 8px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .table-wrap table {
            min-width: 1200px;
        }

        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #dbe3ec;
            background: white;
            color: #1f2937;
        }

        .table-actions.danger {
            border-color: #fecaca;
            background: #fff1f2;
            color: #b91c1c;
        }

        .table-actions.primary {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
        }

        @media (max-width: 768px) {
            .workload-toolbar {
                align-items: flex-start;
            }
        }
    </style>

    <div class="workload-page">
        <div class="page-header">
            <div>
                <h1>Faculty Workload Management</h1>
                <p>Manage and monitor faculty teaching workload.</p>
            </div>
        </div>

        <div class="page-card">
            <form method="POST" action="/admin/faculty-workload/generate" class="auto-generate-form">
                @csrf
                <div class="search-bar">
                    <select name="department_id" required>
                        <option value="">Select Department</option>
                        @foreach ($departmentOptions as $department)
                            <option value="{{ $department->id }}" {{ (string) ($selectedDepartmentId ?? '') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn" id="auto-generate-btn">⚡ Auto Generate</button>
                </div>
            </form>
        </div>

        @if (session('success'))
            <div class="page-card success-box">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="page-card danger-box">
                {{ session('error') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="page-card warning-box">
                <div>{{ session('warning') }}</div>
                <div class="alert-actions">
                    <a href="/admin/faculty-workload" class="btn btn-muted">Cancel</a>
                    <form method="POST" action="/admin/faculty-workload/generate" style="margin: 0;">
                        @csrf
                        <input type="hidden" name="department_id" value="{{ session('department_warning_id') ?? ($selectedDepartmentId ?? '') }}">
                        <input type="hidden" name="regenerate" value="1">
                        <button type="submit" class="btn">Regenerate</button>
                    </form>
                </div>
            </div>
        @endif

        <div class="page-card">
            <form method="GET" action="/admin/faculty-workload" class="search-bar">
                <input type="text" name="q" value="{{ $q }}" placeholder="Search by Faculty Name or Faculty ID">

                <select name="department">
                    <option value="">Department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->name }}" {{ $departmentFilter === $department->name ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>

                <select name="status">
                    <option value="">Workload Status</option>
                    <option value="Normal" {{ $statusFilter === 'Normal' ? 'selected' : '' }}>Normal</option>
                    <option value="Overloaded" {{ $statusFilter === 'Overloaded' ? 'selected' : '' }}>Overloaded</option>
                </select>

                <button type="submit" class="btn">Search</button>
                <a href="/admin/faculty-workload" class="btn btn-muted">Reset</a>
            </form>
        </div>

        @if ($generatedWorkloads->isNotEmpty())
            <div class="page-card">
                <div class="page-subtitle" style="margin-bottom: 14px;">
                    <h3>Faculty Workload Details</h3>
                </div>

                <div class="summary-grid" style="margin-bottom: 18px;">
                    <div class="summary-card">
                        <span class="label">Total Faculty</span>
                        <span class="value">{{ $summary['total_faculty'] }}</span>
                    </div>
                    <div class="summary-card">
                        <span class="label">Total Allocated Subjects</span>
                        <span class="value">{{ $summary['total_allocated_subjects'] }}</span>
                    </div>
                    <div class="summary-card">
                        <span class="label">Total Lecture Hours</span>
                        <span class="value">{{ $summary['total_lecture_hours'] }}</span>
                    </div>
                    <div class="summary-card">
                        <span class="label">Total Tutorial Hours</span>
                        <span class="value">{{ $summary['total_tutorial_hours'] }}</span>
                    </div>
                    <div class="summary-card">
                        <span class="label">Total Lab Hours</span>
                        <span class="value">{{ $summary['total_lab_hours'] }}</span>
                    </div>
                    <div class="summary-card">
                        <span class="label">Total Weekly Workload</span>
                        <span class="value">{{ $summary['total_weekly_workload'] }} Hours</span>
                    </div>
                </div>

                <div class="faculty-total-list">
                    @foreach ($facultyTotals as $facultyId => $facultyTotal)
                        @php
                            $facultyName = $generatedWorkloads->firstWhere('faculty_id', (string) $facultyId)?->faculty_name ?? 'Faculty';
                        @endphp
                        <div class="faculty-total-item">
                            {{ $facultyName }}: Total Weekly Workload: {{ $facultyTotal }} Hours
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="page-card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Faculty ID</th>
                                <th>Department</th>
                                <th>Semester</th>
                                <th>Subject Name</th>
                                <th>Subject Code</th>
                                <th>Weekly Hours</th>
                                <th>Weekly Workload</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($generatedWorkloads as $allocation)
                                <tr>
                                    <td>{{ $allocation->faculty_name }}</td>
                                    <td>{{ $allocation->faculty_id }}</td>
                                    <td>{{ $allocation->department }}</td>
                                    <td>{{ $allocation->semester }}</td>
                                    <td>{{ $allocation->subject_name }}</td>
                                    <td>{{ $allocation->subject_code }}</td>
                                    <td>{{ $allocation->weekly_hours }}</td>
                                    <td>{{ $allocation->weekly_workload }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($workloads->isEmpty())
            <div class="page-card empty-state">
                <h3>No Faculty Workload Data Yet</h3>
                <p>Add workload assignments to start tracking faculty teaching capacity.</p>
                <a href="/admin/faculty-workload/create" class="btn">+ Add Faculty Workload</a>
            </div>
        @else
            <div class="page-card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Faculty ID</th>
                                <th>Faculty Name</th>
                                <th>Department</th>
                                <th>Subjects Assigned</th>
                                <th>Theory Hours</th>
                                <th>Practical/Lab Hours</th>
                                <th>Total Hours/Week</th>
                                <th>Assigned Classes</th>
                                <th>Free Periods</th>
                                <th>Workload Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($workloads as $index => $workload)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $workload->faculty_id }}</td>
                                    <td>{{ $workload->faculty_name }}</td>
                                    <td>{{ $workload->department }}</td>
                                    <td>{{ $workload->subjects_assigned }}</td>
                                    <td>{{ $workload->theory_hours }}</td>
                                    <td>{{ $workload->practical_hours }}</td>
                                    <td>{{ $workload->total_hours }}</td>
                                    <td>{{ $workload->assigned_classes ?? 'N/A' }}</td>
                                    <td>{{ $workload->free_periods ?? 'N/A' }}</td>
                                    <td>
                                        <span class="status-badge {{ strtolower($workload->workload_status) }}">
                                            {{ $workload->workload_status }}
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="/admin/faculty-workload/{{ $workload->id }}" class="table-actions primary">View</a>
                                        <a href="/admin/faculty-workload/{{ $workload->id }}/edit" class="table-actions">Edit</a>
                                        <form method="POST" action="/admin/faculty-workload/{{ $workload->id }}/delete" onsubmit="return confirm('Are you sure you want to delete this faculty workload record?');">
                                            @csrf
                                            <button type="submit" class="table-actions danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
