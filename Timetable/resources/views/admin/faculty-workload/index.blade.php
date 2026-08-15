@extends('admin.layout')

@section('title', 'Faculty Workload Management')

@section('content')
    <style>
        .workload-page { display: flex; flex-direction: column; gap: 20px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
        .summary-card { background: #fff; border-radius: 16px; padding: 18px; box-shadow: 0 8px 24px rgba(15,23,42,0.06); border: 1px solid rgba(148,163,184,0.18); }
        .summary-card .label { display: block; font-size: 12px; color: #64748b; margin-bottom: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .summary-card .value { font-size: 30px; font-weight: 700; color: #0f172a; }
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status-badge.normal { background: #dcfce7; color: #166534; }
        .status-badge.high { background: #fef3c7; color: #92400e; }
        .status-badge.overloaded { background: #fee2e2; color: #991b1b; }
        .chart-box { height: 180px; display: flex; align-items: flex-end; justify-content: space-around; gap: 12px; background: #f8fafc; border-radius: 16px; padding: 24px 18px 12px; border: 1px solid #e2e8f0; }
        .bar-group { display: flex; flex-direction: column; align-items: center; gap: 10px; flex: 1; }
        .bar { width: 100%; max-width: 80px; border-radius: 10px 10px 0 0; }
        .bar.normal { background: linear-gradient(180deg, #22c55e, #16a34a); }
        .bar.high { background: linear-gradient(180deg, #f59e0b, #d97706); }
        .bar.overloaded { background: linear-gradient(180deg, #ef4444, #dc2626); }
        .empty-state { text-align: center; padding: 40px 20px; }
        .empty-state h3 { margin-bottom: 8px; }
        .insights { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .insight-card { background: #fff; border-radius: 16px; padding: 18px; border: 1px solid rgba(148,163,184,0.2); }
    </style>

    <div class="workload-page">
        <div class="page-header">
            <div>
                <h1>Faculty Workload Management</h1>
                <p>Track teaching loads, subject assignments, and faculty availability across departments.</p>
            </div>
            <div class="page-actions">
                <a href="/admin/faculty-workload/export" class="btn btn-muted">Export PDF</a>
                <a href="/admin/faculty-workload/export" class="btn">Export Excel</a>
                <a href="/admin/faculty-workload/create" class="btn">Assign Workload</a>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <span class="label">Total Faculty</span>
                <span class="value">{{ $summary['total_faculty'] }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Total Teaching Hours</span>
                <span class="value">{{ $summary['total_teaching_hours'] }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Average Hours / Faculty</span>
                <span class="value">{{ $summary['average_hours'] }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Normal Faculty</span>
                <span class="value">{{ $summary['normal'] }}</span>
            </div>
            <div class="summary-card">
                <span class="label">High Workload Faculty</span>
                <span class="value">{{ $summary['high'] }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Overloaded Faculty</span>
                <span class="value">{{ $summary['overloaded'] }}</span>
            </div>
        </div>

        <div class="page-card">
            <h2>Workload Distribution</h2>
            @php
                $totalStatuses = max(1, $summary['normal'] + $summary['high'] + $summary['overloaded']);
                $normalHeight = round(($summary['normal'] / $totalStatuses) * 100);
                $highHeight = round(($summary['high'] / $totalStatuses) * 100);
                $overloadedHeight = round(($summary['overloaded'] / $totalStatuses) * 100);
            @endphp
            <div class="chart-box">
                <div class="bar-group">
                    <div class="bar normal" style="height: {{ $normalHeight }}%;"></div>
                    <strong>Normal</strong>
                </div>
                <div class="bar-group">
                    <div class="bar high" style="height: {{ $highHeight }}%;"></div>
                    <strong>High</strong>
                </div>
                <div class="bar-group">
                    <div class="bar overloaded" style="height: {{ $overloadedHeight }}%;"></div>
                    <strong>Overloaded</strong>
                </div>
            </div>
        </div>

        <div class="page-card">
            <h2>Workload Insights</h2>
            <div class="insights">
                <div class="insight-card">
                    <strong>{{ $summary['normal'] }} faculty members are in normal workload range.</strong>
                </div>
                <div class="insight-card">
                    <strong>{{ $summary['high'] }} faculty members have high workload.</strong>
                </div>
                <div class="insight-card">
                    <strong>{{ $summary['overloaded'] }} faculty members are overloaded.</strong>
                </div>
            </div>
        </div>

        <div class="page-card">
            <form method="GET" action="/admin/faculty-workload" class="search">
                <input type="text" name="q" placeholder="Search by Faculty Name or Faculty ID" value="{{ $q }}">
                <select name="department_id">
                    <option value="">Department</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" {{ $departmentId == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
                <select name="semester">
                    <option value="">Semester</option>
                    @foreach($semesters as $semesterOption)
                        <option value="{{ $semesterOption }}" {{ $semester === (string) $semesterOption ? 'selected' : '' }}>{{ $semesterOption }}</option>
                    @endforeach
                </select>
                <select name="status">
                    <option value="">Workload Status</option>
                    <option value="Normal" {{ $status === 'Normal' ? 'selected' : '' }}>Normal</option>
                    <option value="High" {{ $status === 'High' ? 'selected' : '' }}>High</option>
                    <option value="Overloaded" {{ $status === 'Overloaded' ? 'selected' : '' }}>Overloaded</option>
                </select>
                <select name="subject_id">
                    <option value="">Subject</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" {{ $subjectId == $subject->id ? 'selected' : '' }}>{{ $subject->name }}</option>
                    @endforeach
                </select>
                <select name="sort">
                    <option value="total_hours_desc" {{ $sort === 'total_hours_desc' ? 'selected' : '' }}>Sort by Total Hours</option>
                    <option value="total_hours_asc" {{ $sort === 'total_hours_asc' ? 'selected' : '' }}>Lowest to Highest</option>
                </select>
                <button type="submit" class="btn">Search</button>
                <a href="/admin/faculty-workload" class="btn btn-muted">Reset</a>
            </form>

            @if ($workloads->isEmpty())
                <div class="empty-state">
                    <h3>No Faculty Workload Data Yet</h3>
                    <p>Add workload assignments to start tracking faculty workload.</p>
                    <a href="/admin/faculty-workload/create" class="btn">Assign Workload</a>
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Faculty Name</th>
                                <th>Faculty ID</th>
                                <th>Department</th>
                                <th>Subjects</th>
                                <th>Theory Hours</th>
                                <th>Practical/Lab Hours</th>
                                <th>Total Hours / Week</th>
                                <th>Assigned Classes</th>
                                <th>Free Periods</th>
                                <th>Workload Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($workloads as $index => $workload)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $workload->faculty?->name ?? 'N/A' }}</td>
                                    <td>{{ $workload->faculty?->id ?? 'N/A' }}</td>
                                    <td>{{ $workload->department?->name ?? 'N/A' }}</td>
                                    <td>{{ $workload->subject?->name ?? 'N/A' }}</td>
                                    <td>{{ $workload->theory_hours }}</td>
                                    <td>{{ $workload->practical_hours }}</td>
                                    <td>{{ $workload->total_hours }}</td>
                                    <td>{{ $workload->assigned_classes ?? 'N/A' }}</td>
                                    <td>{{ $workload->free_periods ?? 'N/A' }}</td>
                                    <td><span class="status-badge {{ strtolower($workload->status) }}">{{ $workload->status }}</span></td>
                                    <td class="actions">
                                        <a href="/admin/faculty-workload/{{ $workload->id }}" class="btn btn-muted">View</a>
                                        <a href="/admin/faculty-workload/{{ $workload->id }}/edit" class="btn btn-muted">Edit</a>
                                        <form method="POST" action="/admin/faculty-workload/{{ $workload->id }}/delete" onsubmit="return confirm('Are you sure you want to delete this workload assignment?');">
                                            @csrf
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
