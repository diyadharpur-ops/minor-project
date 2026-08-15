@extends('admin.layout')

@section('title', 'Students')

@section('content')
    <div class="page-header">
        <div>
            <h1>Students</h1>
            <p>Manage and review all registered student accounts.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/dashboard" class="btn btn-muted">Back</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" style="margin-bottom: 16px; padding: 12px 14px; background: #e8f9ee; color: #166534; border: 1px solid #a7f3d0; border-radius: 10px;">
            ✓ {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom: 16px; padding: 12px 14px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 10px;">
            {{ session('error') }}
        </div>
    @endif

    <div class="page-card">
        <form method="GET" action="/admin/students" class="search">
            <input type="text" name="q" placeholder="Search by name, enrollment, email or department" value="{{ $q ?? '' }}" />
            <button type="submit" class="btn">Search</button>
            <a href="/admin/students" class="btn btn-muted">Clear</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Enrollment Number</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department / Course</th>
                        <th>Semester</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>{{ $student->id }}</td>
                            <td>{{ $student->enrollment_number ?? 'N/A' }}</td>
                            <td>{{ $student->name }}</td>
                            <td>{{ $student->email }}</td>
                            <td>{{ $student->department ?? 'N/A' }}</td>
                            <td>{{ $student->semester ?? 'N/A' }}</td>
                            <td>
                                <span style="display:inline-block; padding: 5px 10px; border-radius: 999px; background: #dcfce7; color: #166534; font-weight: 600;">Active</span>
                            </td>
                            <td class="actions" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <a href="/student/dashboard" class="btn btn-muted" onclick="return false;" style="display:inline-flex; align-items:center; gap:6px; background:#6b7280;">View</a>
                                <button type="button" class="btn btn-danger delete-student-btn" data-student-id="{{ $student->id }}" data-student-name="{{ $student->name }}" style="display:inline-flex; align-items:center; gap:6px; background:#dc2626;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No students found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="delete-student-modal" style="display:none; position:fixed; inset:0; background: rgba(15, 23, 42, 0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; width:min(440px, calc(100% - 32px)); border-radius:16px; box-shadow:0 30px 80px rgba(15, 23, 42, 0.25); padding:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="margin:0; color:#111827; font-size:1.25rem;">Delete Student?</h3>
                <button type="button" id="close-delete-modal" style="background:transparent; border:none; font-size:1.5rem; cursor:pointer; color:#64748b;">×</button>
            </div>
            <p style="margin:0 0 20px; color:#475569; line-height:1.6;">
                Are you sure you want to delete this student?<br>
                This action cannot be undone.
            </p>
            <div id="delete-student-name" style="margin-bottom:20px; font-weight:700; color:#0f172a;"></div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" id="cancel-delete-student" class="btn btn-muted" style="background:#e2e8f0; color:#0f172a;">Cancel</button>
                <form id="delete-student-form" method="POST" action="">
                    @csrf
                    <button type="submit" class="btn btn-danger">Delete Student</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const deleteModal = document.getElementById('delete-student-modal');
        const deleteStudentName = document.getElementById('delete-student-name');
        const deleteStudentForm = document.getElementById('delete-student-form');
        const closeDeleteModal = document.getElementById('close-delete-modal');
        const cancelDeleteStudent = document.getElementById('cancel-delete-student');

        document.querySelectorAll('.delete-student-btn').forEach((button) => {
            button.addEventListener('click', function () {
                const studentId = this.dataset.studentId;
                const studentName = this.dataset.studentName || 'this student';

                deleteStudentName.textContent = studentName;
                deleteStudentForm.action = '/admin/students/' + studentId + '/delete';
                deleteModal.style.display = 'flex';
            });
        });

        function hideDeleteModal() {
            deleteModal.style.display = 'none';
        }

        closeDeleteModal.addEventListener('click', hideDeleteModal);
        cancelDeleteStudent.addEventListener('click', hideDeleteModal);
        deleteModal.addEventListener('click', function (event) {
            if (event.target === deleteModal) {
                hideDeleteModal();
            }
        });
    </script>
@endsection
