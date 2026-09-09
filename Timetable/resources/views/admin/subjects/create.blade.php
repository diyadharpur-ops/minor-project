@extends('admin.layout')

@section('title', 'Create Subject')

@section('content')

@php
    if (!isset($semesters)) {
        $semesters = collect(range(1, 6));
    }
    if (!isset($departments)) {
        $departments = \App\Models\Department::orderBy('name')->get();
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
                <select name="semester" required>
                    <option value="">Select semester</option>
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester }}" {{ old('semester') == $semester ? 'selected' : '' }}>
                            Semester {{ $semester }}
                        </option>
                    @endforeach
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
                <label>Lecture Credit</label>
                <input type="number" name="lecture_credit" min="0" max="10" value="{{ old('lecture_credit') }}" required />
            </div>
            <div class="form-row">
                <label>Lab Credit</label>
                <input type="number" name="lab_credit" min="0" max="10" value="{{ old('lab_credit') }}" required />
            </div>
            <div class="form-row">
                <label>Tutorial Credit</label>
                <input type="number" name="tutorial_credit" min="0" max="10" value="{{ old('tutorial_credit') }}" />
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/subjects" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>

@endsection
