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
                <select name="semester" class="entry-select" required>
                    <option value="">Select Semester</option>
                    @foreach(['1','2','3','4','5','6','7','8'] as $sem)
                        <option value="{{ $sem }}" {{ old('semester', request('semester')) == $sem ? 'selected' : '' }}>Semester {{ $sem }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Division</label>
                <input type="text" name="division" class="entry-select" placeholder="e.g. A" value="{{ old('division', request('division', 'A')) }}" required>
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
@endsection
