@extends('admin.layout')

@section('title', 'Create Subject')

@section('content')

@php
    if (!isset($semesters)) {
        $semesters = \App\Models\Division::select('semester')->distinct()->orderBy('semester')->get()->pluck('semester');
    }
    if (!isset($divisionsBySemester)) {
        $divisionsBySemester = \App\Models\Division::all()->groupBy('semester')->mapWithKeys(function ($divisions, $semester) {
            return [$semester => $divisions->values()];
        });
    }
    if (!isset($departments)) {
        $departments = \App\Models\Department::orderBy('name')->get();
    }
    if (!isset($faculties)) {
        $faculties = \App\Models\Faculty::orderBy('name')->get();
    }
@endphp

    <div class="page-header">
        <div>
            <h1>Add Subject</h1>
            <p>Create a new subject entry for the timetable system.</p>
        </div>
        <a href="/admin/subjects" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/subjects">
            @csrf
            <div class="form-row">
                <label>Subject Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required />
            </div>
            <div class="form-row">
                <label>Subject Code</label>
                <input type="text" name="subject_code" value="{{ old('subject_code') }}" required />
            </div>
            <div class="form-row">
                <label>Semester</label>
                <select name="semester" id="semesterSelect" required onchange="updateDivisions()">
                    <option value="">Select semester</option>
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester }}" {{ old('semester') == $semester ? 'selected' : '' }}>
                            Semester {{ $semester }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label>Division</label>
                <select name="division_id" id="divisionSelect" required>
                    <option value="">Select a semester first</option>
                    @if (old('semester') && isset($divisionsBySemester[old('semester')]))
                        @foreach ($divisionsBySemester[old('semester')] as $division)
                            <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>
                                Division {{ $division->name }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-row">
                <label>Department</label>
                <select name="department_id" required>
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>
                            {{ $department->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label>Credit</label>
                <input type="number" name="credit" min="1" max="10" value="{{ old('credit') }}" />
            </div>
            <div class="form-row">
                <label>Faculty</label>
                <select name="faculty_id">
                    <option value="">Select faculty</option>
                    @foreach ($faculties as $faculty)
                        <option value="{{ $faculty->id }}" {{ old('faculty_id') == $faculty->id ? 'selected' : '' }}>
                            {{ $faculty->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label>Subject Type</label>
                <select name="subject_type" required>
                    <option value="lecture" {{ old('subject_type', 'lecture') === 'lecture' ? 'selected' : '' }}>Lecture</option>
                    <option value="lab" {{ old('subject_type') === 'lab' ? 'selected' : '' }}>Lab</option>
                    <option value="tutorial" {{ old('subject_type') === 'tutorial' ? 'selected' : '' }}>Tutorial</option>
                </select>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/subjects" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        const divisionData = @json($divisionsBySemester);

        function updateDivisions() {
            const semesterSelect = document.getElementById('semesterSelect');
            const divisionSelect = document.getElementById('divisionSelect');
            const selectedSemester = semesterSelect.value;

            // Clear existing options
            divisionSelect.innerHTML = '<option value="">Select division</option>';

            if (selectedSemester && divisionData[selectedSemester]) {
                divisionData[selectedSemester].forEach(division => {
                    const option = document.createElement('option');
                    option.value = division.id;
                    option.textContent = 'Division ' + division.name;
                    divisionSelect.appendChild(option);
                });
            }
        }
    </script>
@endsection
