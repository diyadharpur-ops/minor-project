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
            <div class="page-actions">
                <a href="/admin/faculty-workload/create" class="btn">+ Add Faculty Workload</a>
            </div>
        </div>

        <div class="page-card">
            <form method="GET" action="/admin/faculty-workload" class="search-bar">
                <input type="text" name="q" value="{{ $q }}" placeholder="Search by Faculty Name or Faculty ID">

                <select name="department">
                    <option value="">Department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department }}" {{ $departmentFilter === $department ? 'selected' : '' }}>{{ $department }}</option>
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
