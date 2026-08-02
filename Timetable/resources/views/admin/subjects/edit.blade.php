@extends('admin.layout')

@section('title', 'Edit Subject')

@section('content')
    <div class="page-header">
        <div>
            <h1>Edit Subject</h1>
            <p>Update the selected subject details.</p>
        </div>
        <a href="/admin/subjects" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/subjects/{{ $subject->id }}">
            @csrf
            <div class="form-row">
                <label>Subject Name</label>
                <input type="text" name="name" value="{{ old('name', $subject->name) }}" required />
            </div>
            <div class="form-row">
                <label>Subject Code</label>
                <input type="text" name="subject_code" value="{{ old('subject_code', $subject->subject_code) }}" required />
            </div>
            <div class="form-row">
                <label>Semester</label>
                <input type="text" name="semester" value="{{ old('semester', $subject->semester) }}" required />
            </div>
            <div class="form-row">
                <label>Department</label>
                <select name="department_id" required>
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ old('department_id', $subject->department_id) == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label>Credit</label>
                <input type="number" name="credit" min="1" max="10" value="{{ old('credit', $subject->credit) }}" />
            </div>
            <div class="form-row">
                <label>Faculty Name</label>
                <input type="text" name="faculty_name" value="{{ old('faculty_name', $subject->faculty_name) }}" />
            </div>
            <div class="form-row">
                <label>Subject Type</label>
                <select name="subject_type" required>
                    <option value="lecture" {{ old('subject_type', $subject->subject_type ?? 'lecture') === 'lecture' ? 'selected' : '' }}>Lecture</option>
                    <option value="lab" {{ old('subject_type', $subject->subject_type ?? 'lecture') === 'lab' ? 'selected' : '' }}>Lab</option>
                    <option value="tutorial" {{ old('subject_type', $subject->subject_type ?? 'lecture') === 'tutorial' ? 'selected' : '' }}>Tutorial</option>
                </select>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Save</button>
                <a href="/admin/subjects" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>
@endsection
