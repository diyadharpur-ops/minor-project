@extends('admin.layout')

@section('title', 'Faculty Allocation')

@section('content')
<style>
    .allocation-toolbar { display: flex; justify-content: space-between; align-items: end; gap: 16px; flex-wrap: wrap; }
    .batch-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 18px 0; }
    .batch-tab { padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 8px; color: #334155; text-decoration: none; background: #f8fafc; font-weight: 600; }
    .batch-tab.active { color: white; background: #2563eb; border-color: #2563eb; }
    .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: end; }
    .filter-grid label { display: block; margin-bottom: 5px; color: #475569; font-size: 0.8rem; font-weight: 700; }
    .filter-grid select { width: 100%; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: white; }
    .compact-list { display: grid; gap: 8px; margin-top: 16px; }
    .compact-row { padding: 10px 12px; border-left: 3px solid #2563eb; background: #f8fafc; color: #334155; }
    .warning { padding: 10px 12px; margin-bottom: 8px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .muted { color: #64748b; }
</style>

<div class="page-header">
    <div>
        <h1>Faculty Allocation</h1>
        <p>Batch-wise subject, faculty, and classroom or lab allocation.</p>
    </div>
    <form method="POST" action="{{ url('/admin/faculty-allocation/generate') }}">
        @csrf
        <button class="btn" type="submit">Auto Generate Faculty Allocation</button>
    </form>
</div>

@if (session('faculty_allocation_status'))
    <div class="alert alert-success">{{ session('faculty_allocation_status') }}</div>
@endif

@if (session('faculty_allocation_warnings'))
    <div class="page-card">
        <h3>Validation Warnings</h3>
        @foreach (session('faculty_allocation_warnings') as $warning)
            <div class="warning">{{ $warning }}</div>
        @endforeach
    </div>
@endif

<div class="page-card">
    <div class="allocation-toolbar">
        <div>
            <h2 style="margin: 0;">Available Batches</h2>
            <div class="muted" style="margin-top: 4px;">Select a batch to isolate its subjects.</div>
        </div>
    </div>
    <div class="batch-tabs">
        @foreach ($batches as $batch)
            <a class="batch-tab {{ (($selectedBatch['key'] ?? null) === $batch['key']) ? 'active' : '' }}"
                href="{{ url('/admin/faculty-allocation') }}?batch={{ urlencode($batch['key']) }}">
                {{ $batch['name'] }}
                <span class="muted">({{ $batch['department_name'] }} · {{ $batch['semester'] }})</span>
            </a>
        @endforeach
    </div>
    @if ($batches->isEmpty())
        <p class="muted">No batches or classes found in the existing student, division, or subject data.</p>
    @endif
</div>

<div class="page-card">
    <form method="GET" action="{{ url('/admin/faculty-allocation') }}" class="filter-grid">
        <input type="hidden" name="batch" value="{{ request('batch') }}">
        <div>
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="semester">Semester</label>
            <select id="semester" name="semester">
                <option value="">All semesters</option>
                @foreach ($batches->pluck('semester')->unique() as $semester)
                    <option value="{{ $semester }}" @selected(request('semester') == $semester)>Semester {{ $semester }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="subject_id">Subject</label>
            <select id="subject_id" name="subject_id">
                <option value="">All subjects</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="faculty_id">Faculty</label>
            <select id="faculty_id" name="faculty_id">
                <option value="">All faculty</option>
                @foreach ($faculties as $faculty)
                    <option value="{{ $faculty->id }}" @selected(request('faculty_id') == $faculty->id)>{{ $faculty->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="classroom_id">Location</label>
            <select id="classroom_id" name="classroom_id">
                <option value="">All locations</option>
                @foreach ($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(request('classroom_id') == $classroom->id)>{{ $classroom->room_number }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="subject_type">Subject type</label>
            <select id="subject_type" name="subject_type">
                <option value="">All types</option>
                @foreach ($subjectTypes as $subjectType)
                    <option value="{{ $subjectType }}" @selected(request('subject_type') == $subjectType)>{{ $subjectType }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn" type="submit">Apply Filters</button>
    </form>
</div>

<div class="page-card">
    <h2>{{ $selectedBatch['name'] ?? 'All' }} Faculty Allocation</h2>
    @if ($selectedBatch && $allocations->isEmpty())
        <p class="muted">No subjects found for this batch. Run Auto Generate after the source data is configured.</p>
    @elseif (! $selectedBatch && $allocations->isEmpty())
        <p class="muted">No allocation records found. Run Auto Generate Faculty Allocation.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Batch / Class</th>
                        <th>Subject</th>
                        <th>Faculty</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($allocations as $allocation)
                        <tr>
                            <td>{{ $allocation['batch'] ?: '—' }}</td>
                            <td>{{ $allocation['subject'] ?: '—' }}</td>
                            <td>{{ $allocation['faculty'] ?: 'Faculty is not assigned to this subject.' }}</td>
                            <td>{{ $allocation['location'] ?: 'No classroom/lab location assigned.' }}</td>
                            <td>{{ $allocation['type'] }}</td>
                            <td>{{ $allocation['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h3 style="margin-top: 24px;">Compact Allocation</h3>
        <div class="compact-list">
            @foreach ($allocations as $allocation)
                <div class="compact-row">
                    {{ $allocation['batch'] ?: '—' }} - {{ $allocation['subject'] ?: '—' }} - {{ $allocation['faculty'] ?: 'Faculty is not assigned to this subject.' }} - {{ $allocation['location'] ?: 'No classroom/lab location assigned.' }}
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
