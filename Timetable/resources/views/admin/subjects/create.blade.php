@extends('admin.layout')

@section('title', 'Create Subject')

@section('content')

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

        <form method="POST" action="/admin/subjects" data-subject-selection-form>
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
                <input type="text" name="semester" list="existing-semesters" value="{{ old('semester') }}" required />
                <datalist id="existing-semesters">
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester }}">Semester {{ $semester }}</option>
                    @endforeach
                </datalist>
            </div>
            @include('admin.subjects._group-selections')
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
                <label>Subject Type</label>
                <select name="subject_type" required>
                    <option value="Lecture" {{ old('subject_type', 'Lecture') === 'Lecture' ? 'selected' : '' }}>Lecture</option>
                    <option value="Lab" {{ old('subject_type') === 'Lab' ? 'selected' : '' }}>Lab</option>
                    <option value="Tutorial" {{ old('subject_type') === 'Tutorial' ? 'selected' : '' }}>Tutorial</option>
                </select>
            </div>
            <div class="form-row">
                <label>Lecture Credit per Selected Class</label>
                <input type="number" name="lecture_credit" min="0" max="10" value="{{ old('lecture_credit') }}" required />
            </div>
            <div class="form-row">
                <label>Lab Credit per Selected Division</label>
                <input type="number" name="lab_credit" min="0" max="10" value="{{ old('lab_credit') }}" required />
            </div>
            <div class="form-row">
                <label>Tutorial Credit per Selected Group (optional)</label>
                <input type="number" name="tutorial_credit" min="0" max="10" value="{{ old('tutorial_credit') }}" />
            </div>
            <div class="subject-calculation-preview" aria-live="polite">
                <strong>Calculated Weekly Load</strong>
                <span>Lecture: <span data-calculated-field="lecture-credits">0</span> credits / <span data-calculated-field="lecture-hours">0</span> hours</span>
                <span>Lab: <span data-calculated-field="lab-credits">0</span> credits / <span data-calculated-field="lab-hours">0</span> hours</span>
                <span>Tutorial: <span data-calculated-field="tutorial-credits">0</span> credits / <span data-calculated-field="tutorial-hours">0</span> hours</span>
                <strong>Total Weekly Hours: <span data-calculated-field="total-hours">0</span></strong>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/subjects" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>

@endsection
