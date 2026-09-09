@extends('admin.layout')

@section('title', 'Manage Subjects')

@section('content')

@php
    if (!isset($groupedSubjects)) {
        $subjects = \App\Models\Subject::with('department', 'division', 'faculty')->orderBy('semester')->orderBy('created_at', 'desc')->get();
        $groupedSubjects = $subjects->groupBy('semester')->map(function ($semesterGroup) {
            return $semesterGroup->groupBy(function ($subject) {
                return $subject->division?->name ?? 'A';
            });
        });
    }
    if (!isset($searchResults)) {
        $searchResults = collect();
    }
@endphp

    <style>
        .folder-structure {
            margin-top: 20px;
        }
        .semester-folder {
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .semester-header {
            background: #f3f4f6;
            padding: 12px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            user-select: none;
            border-bottom: 1px solid #e5e7eb;
        }
        .semester-header:hover {
            background: #e5e7eb;
        }
        .semester-header.collapsed::before {
            content: '▶ ';
        }
        .semester-header.expanded::before {
            content: '▼ ';
        }
        .semester-content {
            padding: 0;
            max-height: 100vh;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .semester-content.collapsed {
            max-height: 0;
            border-top: none;
        }
        .division-folder {
            margin: 12px 16px 12px 32px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
        }
        .division-header {
            background: #f9fafb;
            padding: 8px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            user-select: none;
        }
        .division-header:hover {
            background: #f3f4f6;
        }
        .division-header.collapsed::before {
            content: '▶ 📂 ';
        }
        .division-header.expanded::before {
            content: '▼ 📂 ';
        }
        .division-content {
            max-height: 100vh;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .division-content.collapsed {
            max-height: 0;
        }
        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .subjects-table thead {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }
        .subjects-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
        }
        .subjects-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        .subjects-table tbody tr:hover {
            background: #f9fafb;
        }
        .subjects-table .actions {
            display: flex;
            gap: 6px;
        }
        .subjects-table .actions form {
            display: inline;
        }
        .subjects-table .actions .btn {
            padding: 6px 10px;
            font-size: 0.875rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-header-left h1 {
            margin: 0;
        }
        .page-header-left p {
            margin: 4px 0 0;
            color: #6b7280;
        }
        .no-data {
            padding: 24px;
            text-align: center;
            color: #6b7280;
        }
    </style>

    <div class="page-header">
        <div class="page-header-left">
            <h1>Manage Subjects</h1>
            <p>Organize subjects by semester and division.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/dashboard" class="btn btn-muted">Back</a>
            <a href="/admin/subjects/create" class="btn">+ Add Subject</a>
        </div>
    </div>

    @if (request()->has('q'))
        <div class="page-card">
            <form method="GET" action="/admin/subjects" class="search">
                <input type="text" name="q" placeholder="Search by name, code, semester, division, faculty" value="{{ request('q') }}" />
                <button type="submit" class="btn">Search</button>
                <a href="/admin/subjects" class="btn btn-muted">Clear</a>
            </form>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Subject Code</th>
                            <th>Semester</th>
                            <th>Division</th>
                            <th>Department</th>
                            <th>Lecture</th>
                            <th>Lab</th>
                            <th>Tutorial</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($searchResults ?? [] as $subject)
                            <tr>
                                <td>{{ $subject->id }}</td>
                                <td>{{ $subject->name }}</td>
                                <td>{{ $subject->subject_code }}</td>
                                <td>{{ $subject->semester }}</td>
                                <td>{{ $subject->division?->name ?? 'A' }}</td>
                                <td>{{ $subject->department?->name ?? 'N/A' }}</td>
                                <td>{{ $subject->lecture_credit ?? 0 }}</td>
                                <td>{{ $subject->lab_credit ?? 0 }}</td>
                                <td>{{ $subject->tutorial_credit ?? 0 }}</td>
                                <td class="actions">
                                    <a href="/admin/subjects/{{ $subject->id }}/edit" class="btn btn-muted">Edit</a>
                                    <form method="POST" action="/admin/subjects/{{ $subject->id }}/delete">
                                        @csrf
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="no-data">No subjects found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="page-card">
            @if ($groupedSubjects->isEmpty())
                <div class="no-data">No subjects found. Create your first subject by clicking the "Add Subject" button.</div>
            @else
                <div class="folder-structure">
                    @foreach ($groupedSubjects as $semester => $divisions)
                        <div class="semester-folder">
                            <div class="semester-header expanded" onclick="toggleSemester(this)">
                                📚 Semester {{ $semester }}
                            </div>
                            <div class="semester-content">
                                @if ((string) $semester === '5')
                                    @php
                                        $semesterSubjects = collect($divisions)->flatten(1);
                                    @endphp

                                    <table class="subjects-table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Subject Code</th>
                                                <th>Semester</th>
                                                <th>Department</th>
                                                <th>Lecture</th>
                                                <th>Lab</th>
                                                <th>Tutorial</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($semesterSubjects as $subject)
                                                <tr>
                                                    <td>{{ $subject->name }}</td>
                                                    <td>{{ $subject->subject_code }}</td>
                                                    <td>{{ $subject->semester }}</td>
                                                    <td>{{ $subject->department?->name ?? 'N/A' }}</td>
                                                    <td>{{ $subject->lecture_credit ?? 0 }}</td>
                                                    <td>{{ $subject->lab_credit ?? 0 }}</td>
                                                    <td>{{ $subject->tutorial_credit ?? 0 }}</td>
                                                    <td class="actions">
                                                        <a href="/admin/subjects/{{ $subject->id }}/edit" class="btn btn-muted">Edit</a>
                                                        <form method="POST" action="/admin/subjects/{{ $subject->id }}/delete">
                                                            @csrf
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    @foreach ($divisions as $division => $subjects)
                                        <div class="division-folder">
                                            <div class="division-header expanded" onclick="toggleDivision(event)">
                                                Division {{ $division }}
                                            </div>
                                            <div class="division-content">
                                                <table class="subjects-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Subject Code</th>
                                                            <th>Semester</th>
                                                            <th>Division</th>
                                                            <th>Department</th>
                                                            <th>Lecture</th>
                                                            <th>Lab</th>
                                                            <th>Tutorial</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($subjects as $subject)
                                                            <tr>
                                                                <td>{{ $subject->name }}</td>
                                                                <td>{{ $subject->subject_code }}</td>
                                                                <td>{{ $subject->semester }}</td>
                                                                <td>{{ $subject->division?->name ?? 'A' }}</td>
                                                                <td>{{ $subject->department?->name ?? 'N/A' }}</td>
                                                                <td>{{ $subject->lecture_credit ?? 0 }}</td>
                                                                <td>{{ $subject->lab_credit ?? 0 }}</td>
                                                                <td>{{ $subject->tutorial_credit ?? 0 }}</td>
                                                                <td class="actions">
                                                                    <a href="/admin/subjects/{{ $subject->id }}/edit" class="btn btn-muted">Edit</a>
                                                                    <form method="POST" action="/admin/subjects/{{ $subject->id }}/delete">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-danger">Delete</button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <script>
        function toggleSemester(header) {
            const content = header.nextElementSibling;
            header.classList.toggle('collapsed');
            header.classList.toggle('expanded');
            content.classList.toggle('collapsed');
        }

        function toggleDivision(event) {
            const header = event.currentTarget;
            const content = header.nextElementSibling;
            header.classList.toggle('collapsed');
            header.classList.toggle('expanded');
            content.classList.toggle('collapsed');
        }
    </script>
@endsection
