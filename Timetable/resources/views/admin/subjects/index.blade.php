@extends('admin.layout')

@section('title', 'Subjects')

@section('content')
    <div class="page-header">
        <div>
            <h1>Subjects</h1>
            <p>Manage curriculum subjects and assignments.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/dashboard" class="btn btn-muted">Back</a>
            <a href="/admin/subjects/create" class="btn">Add Subject</a>
        </div>
    </div>

    <div class="page-card">
        <form method="GET" action="/admin/subjects" class="search">
            <input type="text" name="q" placeholder="Search by name, code, semester, faculty" value="{{ $q ?? '' }}" />
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
                        <th>Credit</th>
                        <th>Faculty</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr>
                            <td>{{ $subject->id }}</td>
                            <td>{{ $subject->name }}</td>
                            <td>{{ $subject->subject_code }}</td>
                            <td>{{ $subject->semester }}</td>
                            <td>{{ $subject->department?->name ?? 'N/A' }}</td>
                            <td>{{ $subject->credit }}</td>
                            <td>{{ $subject->faculty_name }}</td>
                            <td class="actions">
                                <a href="/admin/subjects/{{ $subject->id }}/edit" class="btn btn-muted">Edit</a>
                                <form method="POST" action="/admin/subjects/{{ $subject->id }}/delete">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No subjects found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
