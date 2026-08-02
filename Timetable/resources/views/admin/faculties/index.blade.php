@extends('admin.layout')

@section('title', 'Faculty')

@section('content')
    <div class="page-header">
        <div>
            <h1>Faculty</h1>
            <p>Manage faculty profile and department assignments.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/dashboard" class="btn btn-muted">Back</a>
            <a href="/admin/faculties/create" class="btn">Add Faculty</a>
        </div>
    </div>

    <div class="page-card">
        <form method="GET" action="/admin/faculties" class="search">
            <input type="text" name="q" placeholder="Search by name or email" value="{{ $q ?? '' }}" />
            <button type="submit" class="btn">Search</button>
            <a href="/admin/faculties" class="btn btn-muted">Clear</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Subjects</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($faculties as $faculty)
                        <tr>
                            <td>{{ $faculty->id }}</td>
                            <td>{{ $faculty->name }}</td>
                            <td>{{ $faculty->designation }}</td>
                            <td>{{ $faculty->email }}</td>
                            <td>{{ $faculty->department?->name ?? 'N/A' }}</td>
                            <td>{{ $faculty->subjects }}</td>
                            <td class="actions">
                                <a href="/admin/faculties/{{ $faculty->id }}/edit" class="btn btn-muted">Edit</a>
                                <form method="POST" action="/admin/faculties/{{ $faculty->id }}/delete">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No faculty found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
