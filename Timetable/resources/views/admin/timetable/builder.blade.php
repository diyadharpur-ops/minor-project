@extends('admin.layout')

@section('title', 'Auto Timetable Generation')

@section('content')
<style>
    .builder-container {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .entry-select {
        width: 100%;
        margin-bottom: 5px;
        padding: 8px;
        font-size: 14px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
</style>

<div class="page-header">
    <div>
        <h1>Auto Timetable Generation</h1>
        <p>Automatically generate conflict-free timetables for the selected class.</p>
    </div>
    <a href="/admin/dashboard" class="btn btn-muted">Back</a>
</div>

<div class="builder-container">
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="/admin/timetable/builder">
        @csrf
        
        <div class="row" style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Department</label>
                <select name="department_id" class="entry-select" required>
                    <option value="">Select Department</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id', request('department_id')) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Semester</label>
                <select name="semester" id="semesterSelect" class="entry-select" required onchange="updateDivisions()">
                    <option value="">Select Semester</option>
                    @foreach($semesters as $sem)
                        <option value="{{ $sem }}" {{ old('semester', request('semester')) == $sem ? 'selected' : '' }}>Semester {{ $sem }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Division</label>
                <select name="division" id="divisionSelect" class="entry-select" required>
                    <option value="">Select Division</option>
                    @foreach($divisions as $div)
                        <option value="{{ $div }}" {{ old('division', request('division', 'A')) == $div ? 'selected' : '' }}>{{ $div }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Academic Year</label>
                <input type="text" name="academic_year" class="entry-select" placeholder="e.g. 2026-2027" value="{{ old('academic_year', request('academic_year', date('Y').'-'.(date('Y')+1))) }}" required>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Term</label>
                <select name="term" class="entry-select" required>
                    <option value="Odd" {{ old('term', request('term')) == 'Odd' ? 'selected' : '' }}>Odd</option>
                    <option value="Even" {{ old('term', request('term')) == 'Even' ? 'selected' : '' }}>Even</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <p style="color: #666; margin-bottom: 15px;">
                Clicking the button below will run the automated algorithm to allocate subjects, faculty, and rooms without conflicts.
                If a timetable already exists for this class, it will be <strong>overwritten</strong>.
            </p>
            <button type="submit" class="btn" style="background-color: #10b981; font-size: 16px; padding: 10px 20px;">Generate Auto Timetable</button>
        </div>
    </form>
</div>

<script>
    const divisionsMap = @json($divisionsMap ?? []);
    const oldDivision = "{{ request('division', 'A') }}";

    function updateDivisions() {
        const semesterSelect = document.getElementById('semesterSelect');
        const divisionSelect = document.getElementById('divisionSelect');
        const selectedSemester = semesterSelect.value;

        const currentSelection = divisionSelect.value || oldDivision;
        divisionSelect.innerHTML = '<option value="">Select Division</option>';

        let optionsList = [];
        if (selectedSemester && divisionsMap[selectedSemester]) {
            optionsList = divisionsMap[selectedSemester];
        } else {
            // When no semester is selected yet, show all active divisions
            optionsList = Object.values(divisionsMap).flat().filter((v, i, a) => a.indexOf(v) === i);
        }

        optionsList.forEach(function(division) {
            const option = document.createElement('option');
            option.value = division;
            option.textContent = division;
            if (division === currentSelection) {
                option.selected = true;
            }
            divisionSelect.appendChild(option);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateDivisions();
    });
</script>
@endsection
