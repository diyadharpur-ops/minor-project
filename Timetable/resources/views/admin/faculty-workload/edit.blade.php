@extends('admin.layout')

@section('title', 'Edit Faculty Workload')

@section('content')
    <div class="page-header">
        <div>
            <h1>Edit Workload</h1>
            <p>Update workload assignments and teaching hours for the selected faculty.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/faculty-workload" class="btn btn-muted">Back</a>
        </div>
    </div>

    <div class="page-card">
        <form method="POST" action="/admin/faculty-workload/{{ $workload->id }}">
            @csrf

            <div class="form-row">
                <label for="faculty_id">Faculty</label>
                <select id="faculty_id" name="faculty_id" required>
                    <option value="">Select Faculty</option>
                    @foreach($faculties as $faculty)
                        <option value="{{ $faculty->id }}" {{ $workload->faculty_id == $faculty->id ? 'selected' : '' }}>{{ $faculty->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-row">
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id" required>
                    <option value="">Select Department</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" {{ $workload->department_id == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-row">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">Select Subject</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" {{ $workload->subject_id == $subject->id ? 'selected' : '' }}>{{ $subject->name }} ({{ $subject->subject_code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="form-row">
                <label for="subject_type">Subject Type</label>
                <select id="subject_type" name="subject_type" required>
                    <option value="Theory" {{ $workload->subject_type == 'Theory' ? 'selected' : '' }}>Theory</option>
                    <option value="Practical" {{ $workload->subject_type == 'Practical' ? 'selected' : '' }}>Practical</option>
                    <option value="Lab" {{ $workload->subject_type == 'Lab' ? 'selected' : '' }}>Lab</option>
                </select>
            </div>

            <div class="form-row">
                <label for="semester">Semester</label>
                <input id="semester" type="text" name="semester" value="{{ old('semester', $workload->semester) }}" required>
            </div>

            <div class="form-row">
                <label for="class_name">Class / Division</label>
                <input id="class_name" type="text" name="class_name" value="{{ old('class_name', $workload->class_name) }}" placeholder="e.g. CS-A">
            </div>

            <div class="form-row">
                <label for="division">Division</label>
                <input id="division" type="text" name="division" value="{{ old('division', $workload->division) }}" placeholder="A / B / C">
            </div>

            <div class="form-row">
                <label for="theory_hours">Theory Hours / Week</label>
                <input id="theory_hours" type="number" min="0" name="theory_hours" value="{{ old('theory_hours', $workload->theory_hours) }}" required>
            </div>

            <div class="form-row">
                <label for="practical_hours">Practical / Lab Hours / Week</label>
                <input id="practical_hours" type="number" min="0" name="practical_hours" value="{{ old('practical_hours', $workload->practical_hours) }}" required>
            </div>

            <div class="form-row">
                <label for="assigned_classes">Assigned Classes</label>
                <input id="assigned_classes" type="text" name="assigned_classes" value="{{ old('assigned_classes', $workload->assigned_classes) }}" placeholder="e.g. CS-A, CS-B">
            </div>

            <div class="form-row">
                <label for="free_periods">Free Periods</label>
                <input id="free_periods" type="text" name="free_periods" value="{{ old('free_periods', $workload->free_periods) }}" placeholder="e.g. Tuesday 2nd period">
            </div>

            <div class="page-actions">
                <button type="submit" class="btn">Update Workload</button>
                <a href="/admin/faculty-workload" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>
@endsection
