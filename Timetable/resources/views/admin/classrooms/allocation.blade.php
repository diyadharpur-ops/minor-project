@extends('admin.layout')

@section('title', 'Classroom & Lab Allocation')

@section('content')

@php
    // Ensure $departments is always available as a fallback
    if (! isset($departments)) {
        $departments = \App\Models\Department::orderBy('name')->get();
    }
@endphp

<style>
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .summary-card {
        background: white;
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }
    .summary-card h3 {
        margin: 0;
        font-size: 0.9rem;
        color: #6b7280;
        font-weight: 500;
    }
    .summary-card .val {
        font-size: 1.8rem;
        font-weight: 700;
        color: #111827;
        margin-top: 8px;
    }
    .timing-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 24px;
    }
    .timing-card h2 {
        margin-top: 0;
        font-size: 1.2rem;
        margin-bottom: 16px;
    }
    .timing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }
    .timing-box {
        background: #f9fafb;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
    }
    .timing-box h4 {
        margin: 0 0 4px 0;
        font-size: 0.85rem;
        color: #4b5563;
    }
    .timing-box p {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
    }
    .slots-container {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .slot-badge {
        background: #e0f2fe;
        color: #0369a1;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .slot-badge.theory {
        background: #dcfce7;
        color: #166534;
    }
    .badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-block;
    }
    .badge-allocated { background: #dcfce7; color: #166534; }
    .badge-unallocated { background: #fee2e2; color: #991b1b; }
    .badge-classroom { background: #dcfce7; color: #166534; }
    .badge-lab { background: #e0f2fe; color: #0369a1; }
    
    .allocation-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    /* Filter Form */
    .filter-card {
        background: white;
        padding: 20px 24px;
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.10);
        margin-bottom: 24px;
    }
    .filter-card h2 {
        margin: 0 0 16px 0;
        font-size: 1.05rem;
        font-weight: 600;
        color: #111827;
    }
    .filter-row {
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 130px;
    }
    .filter-group label {
        font-size: 0.82rem;
        font-weight: 600;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .filter-group select,
    .filter-group input[type="text"] {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.9rem;
        color: #111827;
        background: #f9fafb;
        transition: border-color 0.2s;
        outline: none;
    }
    .filter-group select:focus,
    .filter-group input[type="text"]:focus {
        border-color: #2563eb;
        background: #fff;
    }
    .btn-generate {
        padding: 9px 20px;
        background: #10b981;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 0.92rem;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.2s;
    }
    .btn-generate:hover { background: #059669; }
    .btn-generate:disabled { background: #6ee7b7; cursor: not-allowed; }

    .filter-active-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 0.82rem;
        font-weight: 600;
        margin-left: 10px;
    }
    .filter-active-badge a {
        color: #6b7280;
        text-decoration: none;
        font-size: 1rem;
        line-height: 1;
    }
    .filter-active-badge a:hover { color: #ef4444; }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1>Classroom &amp; Lab Allocation</h1>
        <p style="color: #6b7280; font-size: 0.9rem; margin-top: 4px;">Select criteria and auto-allocate classrooms &amp; labs based on subject type.</p>
    </div>
    <div style="text-align: right;">
        <div style="font-weight: 600; font-size: 0.95rem; margin-bottom: 4px;">Academic Year : {{ $academicYears->first() ?? '' }}</div>
    </div>
</div>

@if (session('allocation_status'))
    <div class="alert" style="background:#dcfce7;color:#166534;">{{ session('allocation_status') }}</div>
@endif

@if ($errors->any())
    <div class="alert" style="background:#fee2e2;color:#991b1b;">
        <ul style="margin:0;padding-left:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ===== Filter / Auto Generate Form ===== --}}
<div class="filter-card">
    <h2>⚙️ Generate Allocation for a Specific Class</h2>
    <form method="POST" action="/admin/classroom-allocation" id="filteredGenForm">
        @csrf
        <input type="hidden" name="form_type" value="filtered-auto-allocate">
        <div class="filter-row">
            <div class="filter-group">
                <label>Department</label>
                <select name="department_id" required onchange="submitFilteredAllocationWhenReady()">
                    <option value="">Select Department</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}"
                            {{ old('department_id', request('department_id')) == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Semester</label>
                <select name="semester" required onchange="submitFilteredAllocationWhenReady()">
                    <option value="">Select Semester</option>
                    @foreach ($semesters as $sem)
                        <option value="{{ $sem }}"
                            {{ old('semester', request('semester')) == $sem ? 'selected' : '' }}>
                            Semester {{ $sem }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Division</label>
                <select name="division" onchange="submitFilteredAllocationWhenReady()">
                    <option value="">Select Division</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division }}"
                            {{ old('division', request('division')) == $division ? 'selected' : '' }}>
                            {{ $division }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Term</label>
                <select name="term" required onchange="submitFilteredAllocationWhenReady()">
                    <option value="">Select Term</option>
                    @foreach ($terms as $term)
                        <option value="{{ $term }}" {{ old('term', request('term')) == $term ? 'selected' : '' }}>{{ $term }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Academic Year</label>
                <select name="academic_year" required onchange="submitFilteredAllocationWhenReady()">
                    <option value="">Select Academic Year</option>
                    @foreach ($academicYears as $academicYear)
                        <option value="{{ $academicYear }}" {{ old('academic_year', request('academic_year')) == $academicYear ? 'selected' : '' }}>{{ $academicYear }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn-generate" id="filteredGenBtn"
                    onclick="this.innerHTML='Generating...'; this.disabled=true; document.getElementById('filteredGenForm').submit();">
                    ⚙️ Auto Generate
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function submitFilteredAllocationWhenReady() {
        const form = document.getElementById('filteredGenForm');
        const button = document.getElementById('filteredGenBtn');

        if (form.checkValidity() && !button.disabled) {
            button.click();
        }
    }
</script>

<div class="summary-grid">
    <div class="summary-card">
        <h3>Total Subjects</h3>
        <div class="val">{{ $totalLectures ?? 0 }}</div>
    </div>
    <div class="summary-card">
        <h3>Classroom Allocated</h3>
        <div class="val" style="color: #166534;">{{ $allocatedCount ?? 0 }}</div>
    </div>
    <div class="summary-card">
        <h3>Lab Allocated</h3>
        <div class="val" style="color: #0369a1;">{{ $allocatedLabCount ?? 0 }}</div>
    </div>
    <div class="summary-card">
        <h3>Unallocated</h3>
        <div class="val" style="color: #991b1b;">{{ $unallocatedCount ?? 0 }}</div>
    </div>
</div>

<div class="page-card">
    <div class="allocation-header">
        <h2 style="margin: 0;">
            Allocation Results
            @if(request('department_id') || request('semester'))
                <span class="filter-active-badge">
                    🔍 Filtered
                    <a href="/admin/classroom-allocation" title="Clear filter">✕</a>
                </span>
            @endif
        </h2>
        <div class="page-actions">
            <form method="POST" action="/admin/classroom-allocation" style="display:inline;" id="autoGenForm">
                @csrf
                <input type="hidden" name="form_type" value="auto-allocate">
                <button type="submit" class="btn" onclick="this.innerHTML='Generating...'; this.disabled=true; document.getElementById('autoGenForm').submit();">
                    ⚙️ Auto Generate All
                </button>
            </form>
            <form method="POST" action="/admin/classroom-allocation" style="display:inline;" onsubmit="return confirm('This will clear current allocations and re-generate. Continue?');" id="regenForm">
                @csrf
                <input type="hidden" name="form_type" value="re-generate">
                <button type="submit" class="btn btn-danger" onclick="this.innerHTML='Re-Generating...'; this.disabled=true; document.getElementById('regenForm').submit();">
                    🔄 Re-Generate All
                </button>
            </form>
        </div>
    </div>

    <div class="table-wrap">
        <table style="min-width: 900px;">
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Class / Division</th>
                    <th>Subject</th>
                    <th>Subject Type</th>
                    <th>Faculty</th>
                    <th>Allocation Type</th>
                    <th>Allocated Classroom / Lab</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $rowNumber = method_exists($allocations, 'firstItem') ? ($allocations->firstItem() ?? 1) : 1;
                @endphp
                @forelse ($allocations as $allocation)
                    @php
                        $subject = $allocation->subject;
                        $facultyName = $allocation->faculty?->name ?? ($subject?->faculty?->name ?? '—');
                        $isLabAlloc = $allocation->allocation_type === 'Lab'
                            || (! $allocation->allocation_type && str_contains(strtolower((string) $subject?->subject_type), 'lab'));
                    @endphp

                    @if ($isLabAlloc)
                        @php
                            // Build combined lab room string: "F011, F012"
                            $lab1Room = $allocation->classroom?->room_number;
                            $lab2Room = $allocation->secondClassroom?->room_number;
                            if (! $lab1Room && ! $lab2Room && $allocation->notes) {
                                $parts    = explode(' + ', $allocation->notes);
                                $lab1Room = trim($parts[0] ?? '');
                                $lab2Room = trim($parts[1] ?? '');
                            }
                            $labRooms = collect([$lab1Room, $lab2Room])->filter()->implode(', ');
                            if (! $labRooms) {
                                $labRooms = '—';
                            }
                        @endphp
                        {{-- Single Lab Row with both labs --}}
                        <tr>
                            <td>{{ $rowNumber++ }}</td>
                            <td>{{ $allocation->class_name }}</td>
                            <td>{{ $subject?->name ?? '—' }}</td>
                            <td><span class="badge badge-lab">Lab</span></td>
                            <td>{{ $facultyName }}</td>
                            <td><span class="badge badge-lab">Lab</span></td>
                            <td>{{ $labRooms }}</td>
                            <td>
                                <span class="badge {{ $allocation->status === 'Allocated' ? 'badge-allocated' : 'badge-unallocated' }}">
                                    {{ $allocation->status }}
                                </span>
                            </td>
                        </tr>
                    @else
                        @php
                            $subTypes = collect();
                            $hasLecture  = ((int) ($subject?->lecture_credit  ?? 0)) > 0;
                            $hasTutorial = ((int) ($subject?->tutorial_credit ?? 0)) > 0;

                            if ($hasLecture)  { $subTypes->push('Lecture'); }
                            if ($hasTutorial) { $subTypes->push('Tutorial'); }
                            if ($subTypes->isEmpty()) {
                                $rawType = strtolower((string) ($subject?->subject_type ?? 'lecture'));
                                $subTypes->push(str_contains($rawType, 'tutorial') ? 'Tutorial' : 'Lecture');
                            }
                        @endphp

                        @foreach ($subTypes as $sType)
                            <tr>
                                <td>{{ $rowNumber++ }}</td>
                                <td>{{ $allocation->class_name }}</td>
                                <td>{{ $subject?->name ?? '—' }}</td>
                                <td><span class="badge badge-classroom">{{ $sType }}</span></td>
                                <td>{{ $facultyName }}</td>
                                <td><span class="badge badge-classroom">Classroom</span></td>
                                <td>{{ $allocation->classroom?->room_number ?? ($allocation->notes ?: '—') }}</td>
                                <td>
                                    <span class="badge {{ $allocation->status === 'Allocated' ? 'badge-allocated' : 'badge-unallocated' }}">
                                        {{ $allocation->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr><td colspan="8" style="text-align: center; padding: 24px;">No allocation records found. Click Auto Generate to start.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

@endsection
