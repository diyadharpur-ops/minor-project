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
                <select name="department_id" id="departmentSelect" required onchange="updateSemesters(true); updateClasses(true);">
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
                <select name="semester" id="semesterSelect" required onchange="updateClasses(true);">
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
                <label>Class</label>
                <select name="class" id="classSelect" disabled>
                    <option value="">Select Class</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class }}"
                            {{ old('class', request('class') ?: request('class_name') ?: request('division')) == $class ? 'selected' : '' }}>
                            {{ $class }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Term</label>
                <select name="term" required>
                    <option value="">Select Term</option>
                    @foreach ($terms as $term)
                        <option value="{{ $term }}" {{ old('term', request('term')) == $term ? 'selected' : '' }}>{{ $term }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Academic Year</label>
                <select name="academic_year" required>
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
    const classesMap = @json($classesMap ?? []);
    const semestersMap = @json($semestersMap ?? []);
    const divisionsMap = @json($divisionsMap ?? []);
    const subjectOptionsMap = @json($subjectOptionsMap ?? []);
    const departmentSelect = document.getElementById('departmentSelect');
    const semesterSelect = document.getElementById('semesterSelect');
    const classSelect = document.getElementById('classSelect');
    const oldSemester = @json((string) request('semester', ''));
    const oldClass = @json((string) (request('class') ?: request('class_name') ?: request('division')));

    function updateSemesters(resetSelection) {
        if (!semesterSelect) {
            return;
        }

        const selectedDepartment = departmentSelect ? departmentSelect.value : '';
        const selectedSemester = resetSelection ? '' : (semesterSelect.value || oldSemester);
        const optionsList = selectedDepartment ? (semestersMap[selectedDepartment] || []) : [];

        semesterSelect.innerHTML = '<option value="">Select Semester</option>';
        semesterSelect.disabled = !selectedDepartment || optionsList.length === 0;

        optionsList.forEach(function (semester) {
            const option = document.createElement('option');
            option.value = semester;
            option.textContent = 'Semester ' + semester;
            option.selected = semester === selectedSemester;
            semesterSelect.appendChild(option);
        });

        if (!optionsList.includes(selectedSemester)) {
            semesterSelect.value = '';
        }
    }

    function updateClasses(resetSelection) {
        const selectedDepartment = departmentSelect ? departmentSelect.value : '';
        const selectedSemester = semesterSelect ? semesterSelect.value : '';
        const currentSelection = resetSelection ? '' : (classSelect ? (classSelect.value || oldClass) : oldClass);

        if (!classSelect) {
            return;
        }

        classSelect.innerHTML = '<option value="">Select Class</option>';
        const optionsList = selectedDepartment && selectedSemester && classesMap[selectedDepartment]
            ? (classesMap[selectedDepartment][selectedSemester] || [])
            : [];

        if (!(selectedDepartment && selectedSemester) || optionsList.length === 0) {
            classSelect.disabled = true;
            classSelect.required = false;
            classSelect.innerHTML = '<option value="">Select Class</option>';
            return;
        }

        classSelect.disabled = false;
        classSelect.required = true;
        optionsList.forEach(function(className) {
            const option = document.createElement('option');
            option.value = className;
            option.textContent = className;
            if (className === currentSelection) {
                option.selected = true;
            }
            classSelect.appendChild(option);
        });

        if (currentSelection && optionsList.includes(currentSelection)) {
            classSelect.value = currentSelection;
        }
    }

    function updateResultFilters() {
        const department = document.getElementById('resultsDepartment');
        const semester = document.getElementById('resultsSemester');
        const selectedClass = document.getElementById('resultsClass');
        const division = document.getElementById('resultsDivision');
        const subject = document.getElementById('resultsSubject');

        if (!department || !semester || !selectedClass || !division || !subject) {
            return;
        }

        const departmentId = department.value;
        const semesterValue = semester.value;
        const classValue = selectedClass.dataset.selected || '';
        const divisionValue = division.dataset.selected || '';
        const subjectValue = subject.dataset.selected || '';
        const semesterValues = departmentId ? (semestersMap[departmentId] || []) : [];
        semester.innerHTML = '<option value="">All Semesters</option>';
        semesterValues.forEach(function (value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = 'Semester ' + value;
            option.selected = value === semesterValue;
            semester.appendChild(option);
        });

        const classValues = departmentId && semesterValue && classesMap[departmentId]
            ? (classesMap[departmentId][semesterValue] || [])
            : [];
        selectedClass.innerHTML = '<option value="">All Classes</option>';
        classValues.forEach(function (value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            option.selected = value === classValue;
            selectedClass.appendChild(option);
        });

        const divisions = departmentId && semesterValue && classValue && divisionsMap[departmentId]
            && divisionsMap[departmentId][semesterValue]
            ? (divisionsMap[departmentId][semesterValue][classValue] || [])
            : [];
        division.innerHTML = '<option value="">All Divisions</option>';
        divisions.forEach(function (value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            option.selected = value === divisionValue;
            division.appendChild(option);
        });

        const subjects = departmentId && semesterValue && subjectOptionsMap[departmentId]
            ? (subjectOptionsMap[departmentId][semesterValue] || [])
            : [];
        subject.innerHTML = '<option value="">All Subjects</option>';
        subjects.forEach(function (item) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            option.selected = String(item.id) === subjectValue;
            subject.appendChild(option);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateSemesters();
        updateClasses();
        const resultsDepartment = document.getElementById('resultsDepartment');
        const resultsSemester = document.getElementById('resultsSemester');
        const resultsClass = document.getElementById('resultsClass');
        if (resultsDepartment) {
            resultsDepartment.addEventListener('change', function () {
                resultsSemester.value = '';
                resultsClass.dataset.selected = '';
                document.getElementById('resultsDivision').dataset.selected = '';
                document.getElementById('resultsSubject').dataset.selected = '';
                updateResultFilters();
            });
        }
        if (resultsSemester) {
            resultsSemester.addEventListener('change', function () {
                resultsClass.dataset.selected = '';
                document.getElementById('resultsDivision').dataset.selected = '';
                document.getElementById('resultsSubject').dataset.selected = '';
                updateResultFilters();
            });
        }
        if (resultsClass) {
            resultsClass.addEventListener('change', function () {
                resultsClass.dataset.selected = this.value;
                document.getElementById('resultsDivision').dataset.selected = '';
                updateResultFilters();
            });
        }
        const resultsDivision = document.getElementById('resultsDivision');
        const resultsSubject = document.getElementById('resultsSubject');
        if (resultsDivision) {
            resultsDivision.addEventListener('change', function () {
                resultsDivision.dataset.selected = this.value;
            });
        }
        if (resultsSubject) {
            resultsSubject.addEventListener('change', function () {
                resultsSubject.dataset.selected = this.value;
            });
        }

        updateResultFilters();
    });
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
        <form method="GET" action="/admin/classroom-allocation" class="filter-row" style="padding: 0 0 16px;">
            <div class="filter-group">
                <label for="resultsDepartment">Department</label>
                <select id="resultsDepartment" name="department_id">
                    <option value="">All Departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ (string) request('department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label for="resultsSemester">Semester</label>
                <select id="resultsSemester" name="semester">
                    <option value="">All Semesters</option>
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester }}" {{ (string) request('semester') === (string) $semester ? 'selected' : '' }}>Semester {{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label for="resultsClass">Class</label>
                <select id="resultsClass" name="class" data-selected="{{ request('class', request('class_name', '')) }}">
                    <option value="">All Classes</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class }}" {{ request('class', request('class_name')) === $class ? 'selected' : '' }}>{{ $class }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label for="resultsDivision">Division</label>
                <select id="resultsDivision" name="division_filter" data-selected="{{ request('division_filter', '') }}">
                    <option value="">All Divisions</option>
                    @foreach ($selectedDivisions as $divisionName)
                        <option value="{{ $divisionName }}" {{ request('division_filter') === $divisionName ? 'selected' : '' }}>{{ $divisionName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label for="resultsSubject">Subject</label>
                <select id="resultsSubject" name="subject_id" data-selected="{{ request('subject_id', '') }}">
                    <option value="">All Subjects</option>
                    @foreach ($subjectOptions as $subjectOption)
                        <option value="{{ $subjectOption->id }}" {{ (string) request('subject_id') === (string) $subjectOption->id ? 'selected' : '' }}>{{ $subjectOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn-generate">Filter Results</button>
                <a class="btn btn-muted" href="/admin/classroom-allocation">Clear</a>
            </div>
        </form>
        <table style="min-width: 1050px;">
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Class</th>
                    <th>Division</th>
                    <th>Subject</th>
                    <th>Subject Type</th>
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
                        $isLabAlloc = $allocation->allocation_type === 'Lab'
                            || (! $allocation->allocation_type && str_contains(strtolower((string) $subject?->subject_type), 'lab'));
                        $isTutorial = str_starts_with((string) $allocation->division, (string) $allocation->class_name.'T')
                            || strtolower((string) $subject?->subject_type) === 'tutorial'
                            || (((int) ($subject?->tutorial_credit ?? 0)) > 0 && ((int) ($subject?->lecture_credit ?? 0)) === 0);
                        $subjectType = $isLabAlloc
                            ? 'Lab'
                            : ($isTutorial ? 'Tutorial' : 'Lecture');
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
                            <td>{{ $allocation->division && $allocation->division !== $allocation->class_name ? $allocation->division : '—' }}</td>
                            <td>{{ $subject?->name ?? '—' }}</td>
                            <td><span class="badge badge-lab">{{ $subjectType }}</span></td>
                            <td><span class="badge badge-lab">Lab</span></td>
                            <td>{{ $labRooms }}</td>
                            <td>
                                <span class="badge {{ $allocation->status === 'Allocated' ? 'badge-allocated' : 'badge-unallocated' }}">
                                    {{ $allocation->status }}
                                </span>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td>{{ $rowNumber++ }}</td>
                            <td>{{ $allocation->class_name }}</td>
                            <td>{{ $allocation->division && $allocation->division !== $allocation->class_name ? $allocation->division : '—' }}</td>
                            <td>{{ $subject?->name ?? '—' }}</td>
                            <td><span class="badge badge-classroom">{{ $subjectType }}</span></td>
                            <td><span class="badge badge-classroom">Classroom</span></td>
                            <td>{{ $allocation->classroom?->room_number ?? 'No suitable classroom available' }}</td>
                            <td>
                                <span class="badge {{ $allocation->status === 'Allocated' ? 'badge-allocated' : 'badge-unallocated' }}">
                                    {{ $allocation->status }}
                                </span>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8" style="text-align: center; padding: 24px;">No allocation records found. Click Auto Generate to start.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

@endsection
