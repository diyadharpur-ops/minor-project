@extends('admin.layout')

@section('title', 'Manual Timetable Builder')

@section('content')
<style>
    .builder-container {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .grid-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .grid-table th, .grid-table td {
        border: 1px solid #ccc;
        padding: 5px;
        text-align: center;
        vertical-align: top;
        font-size: 13px;
    }
    .grid-table th {
        background: #f8f9fa;
        font-weight: bold;
    }
    .entry-select {
        width: 100%;
        margin-bottom: 5px;
        padding: 4px;
        font-size: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .recess-row td {
        background: #f2f2f2;
        font-weight: bold;
        letter-spacing: 2px;
        padding: 10px;
    }
    .cell-container {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
</style>

<div class="page-header">
    <div>
        <h1>Manual Timetable Builder</h1>
        <p>Manually assign lectures and practicals for specific time slots.</p>
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
        
        <div class="row" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <div style="flex: 1;">
                <label>Department</label>
                <select name="department_id" class="entry-select" required>
                    <option value="">Select Department</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id', request('department_id')) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1;">
                <label>Semester</label>
                <select name="semester" class="entry-select" required>
                    <option value="">Select Semester</option>
                    @foreach(['1','2','3','4','5','6','7','8'] as $sem)
                        <option value="{{ $sem }}" {{ old('semester', request('semester')) == $sem ? 'selected' : '' }}>Semester {{ $sem }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1;">
                <label>Division</label>
                <input type="text" name="division" class="entry-select" placeholder="e.g. A" value="{{ old('division', request('division', 'A')) }}" required>
            </div>
            <div style="flex: 1;">
                <label>Academic Year</label>
                <input type="text" name="academic_year" class="entry-select" placeholder="e.g. 2026-2027" value="{{ old('academic_year', request('academic_year', date('Y').'-'.(date('Y')+1))) }}" required>
            </div>
            <div style="flex: 1;">
                <label>Term</label>
                <select name="term" class="entry-select" required>
                    <option value="Odd" {{ old('term', request('term')) == 'Odd' ? 'selected' : '' }}>Odd</option>
                    <option value="Even" {{ old('term', request('term')) == 'Even' ? 'selected' : '' }}>Even</option>
                </select>
            </div>
            <div style="flex: 0; display:flex; align-items:flex-end;">
                <button type="button" class="btn btn-muted" onclick="loadTimetable()">Load</button>
            </div>
        </div>

        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 10%">TIME</th>
                    @foreach($days as $day)
                        <th style="width: 15%">{{ strtoupper($day) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($timeSlots as $slotIndex => $slot)
                    @if ($slot === '01:00-02:00')
                        <tr class="recess-row">
                            <td>12:30-01:00</td>
                            <td colspan="{{ count($days) }}">RECESS - 1</td>
                        </tr>
                    @endif
                    @if ($slot === '03:10-04:10')
                        <tr class="recess-row">
                            <td>03:00-03:10</td>
                            <td colspan="{{ count($days) }}">RECESS - 2</td>
                        </tr>
                    @endif
                    
                    <tr>
                        <th>{{ $slot }}</th>
                        @foreach($days as $day)
                            @php
                                $cellKey = $day . '_' . $slot;
                                $existingEntry = $entries->where('day', $day)->where('time_slot', $slot)->first();
                            @endphp
                            <td>
                                <div class="cell-container">
                                    <input type="hidden" name="entries[{{ $cellKey }}][day]" value="{{ $day }}">
                                    <input type="hidden" name="entries[{{ $cellKey }}][time_slot]" value="{{ $slot }}">
                                    
                                    <select name="entries[{{ $cellKey }}][subject_id]" class="entry-select">
                                        <option value="">-- Subject --</option>
                                        @foreach($subjects as $sub)
                                            <option value="{{ $sub->id }}" {{ ($existingEntry && $existingEntry->subject_id == $sub->id) ? 'selected' : '' }}>{{ $sub->name }} ({{ $sub->subject_code }})</option>
                                        @endforeach
                                    </select>
                                    
                                    <select name="entries[{{ $cellKey }}][faculty_id]" class="entry-select">
                                        <option value="">-- Faculty --</option>
                                        @foreach($faculties as $fac)
                                            <option value="{{ $fac->id }}" {{ ($existingEntry && $existingEntry->faculty_id == $fac->id) ? 'selected' : '' }}>{{ $fac->name }}</option>
                                        @endforeach
                                    </select>
                                    
                                    <select name="entries[{{ $cellKey }}][classroom_id]" class="entry-select">
                                        <option value="">-- Room --</option>
                                        @foreach($classrooms as $room)
                                            <option value="{{ $room->id }}" {{ ($existingEntry && $existingEntry->classroom_id == $room->id) ? 'selected' : '' }}>{{ $room->room_number }} ({{ $room->room_type }})</option>
                                        @endforeach
                                    </select>
                                    
                                    <select name="entries[{{ $cellKey }}][lecture_type]" class="entry-select">
                                        <option value="">-- Type --</option>
                                        @foreach(['Theory', 'Practical', 'Tutorial', 'Minor Project', 'Seminar'] as $type)
                                            <option value="{{ $type }}" {{ ($existingEntry && $existingEntry->lecture_type == $type) ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    
                                    <select name="entries[{{ $cellKey }}][duration]" class="entry-select" title="Duration in periods">
                                        <option value="1" {{ ($existingEntry && $existingEntry->duration == 1) ? 'selected' : '' }}>1 Period</option>
                                        <option value="2" {{ ($existingEntry && $existingEntry->duration == 2) ? 'selected' : '' }}>2 Periods</option>
                                        <option value="3" {{ ($existingEntry && $existingEntry->duration == 3) ? 'selected' : '' }}>3 Periods</option>
                                        <option value="4" {{ ($existingEntry && $existingEntry->duration == 4) ? 'selected' : '' }}>4 Periods</option>
                                    </select>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        
        <div style="margin-top: 20px; text-align: right;">
            <button type="submit" class="btn">Save Timetable</button>
        </div>
    </form>
</div>

<script>
    function loadTimetable() {
        const dept = document.querySelector('select[name="department_id"]').value;
        const sem = document.querySelector('select[name="semester"]').value;
        const div = document.querySelector('input[name="division"]').value;
        const year = document.querySelector('input[name="academic_year"]').value;
        const term = document.querySelector('select[name="term"]').value;
        
        if(dept && sem && div && year && term) {
            window.location.href = `/admin/timetable/builder?department_id=${dept}&semester=${sem}&division=${div}&academic_year=${year}&term=${term}`;
        } else {
            alert('Please fill out Department, Semester, Division, Academic Year, and Term to load.');
        }
    }
</script>
@endsection
