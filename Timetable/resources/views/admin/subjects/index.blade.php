@extends('admin.layout')

@section('title', 'Manage Subjects')

@section('content')

@php
    if (!isset($groupedSubjects)) {
        $subjects = \App\Models\Subject::with('department', 'faculty')->orderBy('semester')->orderBy('created_at', 'desc')->get();
        $groupedSubjects = $subjects->groupBy('semester');
    }
    if (!isset($searchResults)) {
        $searchResults = collect();
    }
    if (!isset($semesterWeeklyHours)) {
        $semesterWeeklyHours = collect();
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
            <p>Organize subjects by semester.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/dashboard" class="btn btn-muted">Back</a>
            <a href="/admin/subjects/create" class="btn">+ Add Subject</a>
        </div>
    </div>

    @if (request()->has('q'))
        <div class="page-card">
            <form method="GET" action="/admin/subjects" class="search">
                <input type="text" name="q" placeholder="Search by name, code, semester, department, faculty" value="{{ request('q') }}" />
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
                            <th>Department</th>
                            <th>Lecture</th>
                            <th>Lab</th>
                            <th>Tutorial</th>
                            <th>Weekly Hours</th>
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
                                <td>{{ $subject->department?->name ?? 'N/A' }}</td>
                                <td>{{ $subject->lecture_credit ?? 0 }}</td>
                                <td>{{ $subject->lab_credit ?? 0 }}</td>
                                <td>{{ $subject->tutorial_credit ?? 0 }}</td>
                                <td>{{ $subject->weekly_hours }}</td>
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
                    @foreach ($groupedSubjects as $semester => $subjects)
                        <div class="semester-folder">
                            <div class="semester-header expanded" onclick="toggleSemester(this)">
                                📚 Semester {{ $semester }}
                            </div>
                            <div class="semester-content">
                                    @php
                                        $semesterSummary = $semesterWeeklyHours->get($semester, [
                                            'per_class_hours' => 0,
                                            'selected_classes' => [],
                                            'total_classes' => 0,
                                            'total_hours' => 0,
                                            'divisions' => collect(),
                                        ]);
                                        $selectedClasses = $semesterSummary['selected_classes'];
                                    @endphp
                                    <div
                                        data-semester-summary="{{ $semester }}"
                                        data-per-class-hours="{{ $semesterSummary['per_class_hours'] }}"
                                        style="padding: 16px; border-bottom: 1px solid #e5e7eb; background: #fafafa;"
                                    >
                                        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
                                            <div>
                                                <strong>Per Class Weekly Hours:</strong> {{ $semesterSummary['per_class_hours'] }} Hours
                                            </div>
                                            <div>
                                                <strong>Selected Classes:</strong> <span data-field="selected-classes">{{ empty($selectedClasses) ? 'None' : implode(', ', $selectedClasses) }}</span>
                                            </div>
                                            <div>
                                                <strong>Total Classes:</strong> <span data-field="total-classes">{{ $semesterSummary['total_classes'] }}</span>
                                            </div>
                                        </div>
                                        <div style="margin-top: 12px;">
                                            <strong>Class/Division Selection:</strong>
                                            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                                                @foreach ($semesterSummary['divisions'] as $division)
                                                    <label style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 999px; background: white;">
                                                        <input type="checkbox" class="division-checkbox" data-semester="{{ $semester }}" data-division="{{ $division->name }}" checked>
                                                        <span>{{ $division->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div style="margin-top: 16px; font-size: 1.05rem; color: #111827;">
                                            <strong>Total Weekly Hours:</strong> <span data-field="total-hours">{{ $semesterSummary['total_hours'] }} Hours</span>
                                        </div>
                                    </div>
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
                                                <th>Weekly Hours</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($subjects as $subject)
                                                <tr>
                                                    <td>{{ $subject->name }}</td>
                                                    <td>{{ $subject->subject_code }}</td>
                                                    <td>{{ $subject->semester }}</td>
                                                    <td>{{ $subject->department?->name ?? 'N/A' }}</td>
                                                    <td>{{ $subject->lecture_credit ?? 0 }}</td>
                                                    <td>{{ $subject->lab_credit ?? 0 }}</td>
                                                    <td>{{ $subject->tutorial_credit ?? 0 }}</td>
                                                    <td>{{ $subject->weekly_hours }}</td>
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
                </div>
            @endif
        </div>
    @endif

    <div class="page-card" style="margin-top: 20px;">
        <h2 style="margin-top: 0;">Semester-wise Weekly Hours Summary</h2>
        @if ($semesterWeeklyHours->isEmpty())
            <div class="no-data">No weekly hours to summarize.</div>
        @else
            <div style="display: grid; gap: 8px;">
                @php
                    $overallTotal = $semesterWeeklyHours->sum(fn ($summary) => $summary['total_hours']);
                @endphp
                @foreach ($semesterWeeklyHours as $semester => $summary)
                    <div
                        data-semester-summary-row="{{ $semester }}"
                        data-per-class-hours="{{ $summary['per_class_hours'] }}"
                        style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f9fafb; border-radius: 6px; gap: 12px; flex-wrap: wrap;"
                    >
                        <div>
                            <strong>Semester {{ $semester }}</strong>
                        </div>
                        <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                            <span>Per Class Weekly Hours: {{ $summary['per_class_hours'] }} Hours</span>
                            <span>Selected Classes: <span data-field="selected-classes">{{ empty($summary['selected_classes']) ? 'None' : implode(', ', $summary['selected_classes']) }}</span></span>
                            <span>Total Classes: <span data-field="total-classes">{{ $summary['total_classes'] }}</span></span>
                            <span><strong>Total Weekly Hours:</strong> <span data-field="total-hours">{{ $summary['total_hours'] }} Hours</span></span>
                        </div>
                    </div>
                @endforeach
                <div id="overall-total-weekly-hours" style="padding: 12px 16px; background: #e0f2fe; border-radius: 6px; font-weight: 700;">
                    Overall Total Weekly Hours: {{ $overallTotal }} Hours
                </div>
            </div>
        @endif
    </div>

    <script>
        function toggleSemester(header) {
            const content = header.nextElementSibling;
            header.classList.toggle('collapsed');
            header.classList.toggle('expanded');
            content.classList.toggle('collapsed');
        }

        function updateOverallTotal() {
            const totalHours = Array.from(document.querySelectorAll('[data-semester-summary-row]')).reduce(function (sum, row) {
                const totalText = row.querySelector('[data-field="total-hours"]').textContent.trim();
                const value = Number(totalText.replace(/[^0-9.-]+/g, '')) || 0;
                return sum + value;
            }, 0);

            const overall = document.getElementById('overall-total-weekly-hours');
            if (overall) {
                overall.textContent = 'Overall Total Weekly Hours: ' + totalHours + ' Hours';
            }
        }

        function updateSemesterSummary(semester, selectedClasses) {
            const summaryCard = document.querySelector('[data-semester-summary="' + semester + '"]');
            const summaryRow = document.querySelector('[data-semester-summary-row="' + semester + '"]');
            if (!summaryCard || !summaryRow) {
                return;
            }

            const totalClasses = selectedClasses.length;
            const perClassValue = Number(summaryCard.dataset.perClassHours || 0);
            const totalHours = perClassValue * totalClasses;
            const selectedLabel = totalClasses > 0 ? selectedClasses.join(', ') : 'None';

            summaryCard.querySelector('[data-field="selected-classes"]').textContent = selectedLabel;
            summaryCard.querySelector('[data-field="total-classes"]').textContent = String(totalClasses);
            summaryCard.querySelector('[data-field="total-hours"]').textContent = String(totalHours) + ' Hours';

            summaryRow.querySelector('[data-field="selected-classes"]').textContent = selectedLabel;
            summaryRow.querySelector('[data-field="total-classes"]').textContent = String(totalClasses);
            summaryRow.querySelector('[data-field="total-hours"]').textContent = String(totalHours) + ' Hours';

            updateOverallTotal();
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.division-checkbox').forEach(function (checkbox) {
                const semester = checkbox.dataset.semester;

                checkbox.addEventListener('change', function () {
                    const updatedClasses = [];
                    document.querySelectorAll('.division-checkbox[data-semester="' + semester + '"]').forEach(function (input) {
                        if (input.checked) {
                            updatedClasses.push(input.dataset.division);
                        }
                    });

                    updateSemesterSummary(semester, updatedClasses);
                });
            });
        });

    </script>
@endsection
