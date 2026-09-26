@extends('admin.layout')

@section('title', 'Faculty Allocation')

@section('content')
<style>
    .faculty-allocation-shell { background: #eef4fb; min-height: 100%; }
    .faculty-allocation-page { padding: 24px; }
    .faculty-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); }
    .page-header-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 18px; }
    .page-header-row h1 { margin: 0; color: #1f2937; font-size: 2rem; }
    .page-header-row p { margin: 4px 0 0; color: #64748b; }
    .batch-card { padding: 18px 20px 14px; margin-bottom: 18px; }
    .batch-header { margin: 0 0 12px; font-size: 1.05rem; font-weight: 700; color: #1f2937; }
    .batch-list { display: flex; flex-wrap: wrap; gap: 10px; }
    .batch-pill { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 8px; border: 1px solid #dbeafe; background: #f8fbff; color: #334155; font-weight: 600; text-decoration: none; }
    .batch-pill.active { background: #2563eb; border-color: #2563eb; color: #fff; }
    .batch-pill small { font-size: 0.76rem; opacity: 0.9; }
    .filter-card { padding: 18px 20px; margin-bottom: 18px; }
    .filter-row { display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 16px; align-items: end; }
    .field-group { display: flex; flex-direction: column; gap: 6px; }
    .field-group label { font-size: 0.78rem; font-weight: 700; color: #475569; }
    .field-group select { width: 100%; padding: 10px 12px; border: 1px solid #dbe2ea; border-radius: 8px; background: #fff; color: #1f2937; }
    .generate-wrap { display: flex; align-items: end; justify-content: flex-end; }
    .btn-primary { border: none; border-radius: 8px; background: linear-gradient(180deg, #2a6ae6 0%, #1f5bc7 100%); color: #fff; font-weight: 700; padding: 10px 18px; cursor: pointer; }
    .details-card { padding: 18px 20px; }
    .details-title { margin: 0 0 12px; font-size: 1.2rem; color: #1f2937; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 760px; }
    th { background: #f8fafc; color: #475569; text-align: left; padding: 12px 14px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e2e8f0; }
    td { padding: 12px 14px; border-bottom: 1px solid #edf2f7; color: #334155; }
    tbody tr:hover { background: #f8fbff; }
    .muted { color: #64748b; }
    .status-note { padding: 10px 12px; margin-bottom: 12px; border-radius: 8px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
    .warning-box { padding: 10px 12px; margin-bottom: 12px; border-radius: 8px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a4d00; }
    @media (max-width: 900px) {
        .filter-row { grid-template-columns: 1fr; }
        .generate-wrap { justify-content: flex-start; }
    }
</style>

<div class="faculty-allocation-shell">
    <div class="faculty-allocation-page">
        <div class="page-header-row">
            <div>
                <h1>Faculty Allocation</h1>
            </div>
        </div>

        @if (session('faculty_allocation_status'))
            <div class="status-note">{{ session('faculty_allocation_status') }}</div>
        @endif

        @if (session('faculty_allocation_warnings'))
            <div class="warning-box">
                @foreach (session('faculty_allocation_warnings') as $warning)
                    <div>{{ $warning }}</div>
                @endforeach
            </div>
        @endif

        <div class="faculty-card batch-card">
            <h2 class="batch-header">Available Batches</h2>
            @if ($batches->isEmpty())
                <div class="muted">No batches available.</div>
            @else
                <div class="batch-list">
                    @foreach ($batches as $batch)
                        @php
                            $active = ($selectedBatchKey ?? null) === $batch['key'];
                            $batchUrl = url('/admin/faculty-allocation') . '?' . http_build_query([
                                'department_id' => request('department_id'),
                                'semester' => request('semester'),
                                'division' => request('division'),
                                'batch' => $batch['key'],
                            ]);
                        @endphp
                        <a href="{{ $batchUrl }}" class="batch-pill {{ $active ? 'active' : '' }}">
                            {{ $batch['name'] }}
                            <small>({{ $batch['department_name'] }} - {{ $batch['semester'] }})</small>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="faculty-card filter-card">
            <form method="GET" action="{{ url('/admin/faculty-allocation') }}">
                <div class="filter-row">
                    <div class="field-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" onchange="this.form.submit()">
                            <option value="">Select Department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" onchange="this.form.submit()">
                            <option value="">Select Semester</option>
                            @foreach ($semesterOptions as $semesterOption)
                                <option value="{{ $semesterOption }}" @selected((string) $selectedSemester === (string) $semesterOption)>Semester {{ $semesterOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="division">Class / Division</label>
                        <select id="division" name="division" onchange="this.form.submit()">
                            <option value="">Select Class / Division</option>
                            @foreach ($divisionOptions as $divisionOption)
                                <option value="{{ $divisionOption }}" @selected((string) $selectedDivision === (string) $divisionOption)>{{ $divisionOption }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            <div class="generate-wrap" style="margin-top: 16px;">
                <form method="POST" action="{{ url('/admin/faculty-allocation/generate') }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                    <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                    <input type="hidden" name="division" value="{{ $selectedDivision }}">
                    <input type="hidden" name="batch" value="{{ $selectedBatchKey }}">
                    <button type="submit" class="btn-primary">⚙ Auto Generate Faculty Allocation</button>
                </form>
            </div>
        </div>

        <div class="faculty-card details-card">
            <h2 class="details-title">Faculty Allocation Details</h2>

            @if ($selectedBatch && $allocations->isEmpty())
                <div class="muted">No subjects available for the selected semester.</div>
            @elseif (! $selectedBatch)
                <div class="muted">No batches available.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Subject Type</th>
                                <th>Faculty</th>
                                <th>Weekly Hours</th>
                                <th>Allocation Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allocations as $allocation)
                                @php
                                    $subject = $allocation->subject;
                                    $facultyName = $allocation->faculty?->name ?? ($subject?->faculty?->name ?? '—');
                                    $allocationRows = collect([
                                        ['type' => 'Lecture', 'hours' => (int) ($subject?->lecture_credit ?? 0)],
                                        ['type' => 'Lab', 'hours' => (int) ($subject?->lab_credit ?? 0) * 2],
                                        ['type' => 'Tutorial', 'hours' => (int) ($subject?->tutorial_credit ?? 0)],
                                    ])->filter(fn (array $row): bool => $row['hours'] > 0);

                                    if ($allocationRows->isEmpty() && $subject) {
                                        $subTypeRaw = strtolower((string) $subject->subject_type);
                                        $detectedType = str_contains($subTypeRaw, 'lab') || str_contains($subTypeRaw, 'practical') ? 'Lab' : (str_contains($subTypeRaw, 'tutorial') ? 'Tutorial' : 'Lecture');
                                        $allocationRows = collect([
                                            ['type' => $detectedType, 'hours' => (int) ($subject->weekly_hours ?: 1)],
                                        ]);
                                    }
                                @endphp
                                @foreach ($allocationRows as $allocationRow)
                                    @php
                                        $allocType = match ($allocationRow['type']) {
                                            'Lecture', 'Tutorial' => 'Classroom',
                                            'Lab' => 'Lab',
                                            default => 'Classroom',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $subject?->name ?? '—' }}</td>
                                        <td>{{ $allocationRow['type'] }}</td>
                                        <td>{{ $facultyName }}</td>
                                        <td>{{ $allocationRow['hours'] }} {{ $allocationRow['hours'] === 1 ? 'Hour' : 'Hours' }}</td>
                                        <td>{{ $allocType }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
