@extends('admin.layout')

@section('title', 'Edit Faculty Workload')

@section('content')
    <div class="page-header">
        <div>
            <h1>Faculty Workload Management</h1>
            <p>Edit faculty workload information and update the workload status automatically.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/faculty-workload" class="btn btn-muted">Back</a>
        </div>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/faculty-workload/{{ $workload->id }}">
            @csrf

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="form-row">
                    <label for="faculty_name">Faculty Name</label>
                    <input id="faculty_name" type="text" name="faculty_name" value="{{ old('faculty_name', $workload->faculty_name) }}" required>
                </div>

                <div class="form-row">
                    <label for="faculty_id">Faculty ID</label>
                    <input id="faculty_id" type="text" name="faculty_id" value="{{ old('faculty_id', $workload->faculty_id) }}" required>
                </div>

                <div class="form-row">
                    <label for="department">Department</label>
                    <input id="department" type="text" name="department" value="{{ old('department', $workload->department) }}" required>
                </div>

                <div class="form-row" style="grid-column: 1 / -1;">
                    <label for="subjects_assigned">Subjects Assigned</label>
                    <input id="subjects_assigned" type="text" name="subjects_assigned" value="{{ old('subjects_assigned', $workload->subjects_assigned) }}" required>
                </div>

                <div class="form-row">
                    <label for="theory_hours">Theory Hours per Week</label>
                    <input id="theory_hours" type="number" min="0" name="theory_hours" value="{{ old('theory_hours', $workload->theory_hours) }}" required>
                </div>

                <div class="form-row">
                    <label for="practical_hours">Practical/Lab Hours per Week</label>
                    <input id="practical_hours" type="number" min="0" name="practical_hours" value="{{ old('practical_hours', $workload->practical_hours) }}" required>
                </div>

                <div class="form-row">
                    <label for="assigned_classes">Assigned Classes</label>
                    <input id="assigned_classes" type="text" name="assigned_classes" value="{{ old('assigned_classes', $workload->assigned_classes) }}">
                </div>

                <div class="form-row">
                    <label for="free_periods">Free Periods</label>
                    <input id="free_periods" type="text" name="free_periods" value="{{ old('free_periods', $workload->free_periods) }}">
                </div>

                <div class="form-row">
                    <label for="total_hours">Total Hours per Week</label>
                    <input id="total_hours" type="text" name="total_hours" value="{{ old('total_hours', $workload->total_hours) }}" readonly>
                </div>

                <div class="form-row">
                    <label for="workload_status">Workload Status</label>
                    <input id="workload_status" type="text" name="workload_status" value="{{ old('workload_status', $workload->workload_status) }}" readonly>
                </div>
            </div>

            <div class="page-actions" style="margin-top: 20px;">
                <button type="submit" class="btn">Update Faculty Workload</button>
                <a href="/admin/faculty-workload" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        function updateWorkloadSummary() {
            const theory = parseInt(document.getElementById('theory_hours').value || 0, 10);
            const practical = parseInt(document.getElementById('practical_hours').value || 0, 10);
            const total = theory + practical;
            const threshold = 18;
            const status = total > threshold ? 'Overloaded' : 'Normal';

            document.getElementById('total_hours').value = total;
            document.getElementById('workload_status').value = status;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const theoryInput = document.getElementById('theory_hours');
            const practicalInput = document.getElementById('practical_hours');

            if (theoryInput && practicalInput) {
                theoryInput.addEventListener('input', updateWorkloadSummary);
                practicalInput.addEventListener('input', updateWorkloadSummary);
                updateWorkloadSummary();
            }
        });
    </script>
@endsection
