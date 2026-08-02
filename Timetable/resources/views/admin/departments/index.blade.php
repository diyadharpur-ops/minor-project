@extends('admin.layout')

@section('title', 'Departments')

@section('content')
    <div class="page-header">
        <div>
            <h1>Departments</h1>
            <p>Manage department records from one place.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/dashboard" class="btn btn-muted">Back</a>
            <a href="/admin/departments/create" class="btn">Add Department</a>
        </div>
    </div>

    <div class="page-card">
        <form method="GET" action="/admin/departments" class="search">
            <input type="text" name="q" placeholder="Search by name or code" value="{{ $q ?? '' }}" />
            <button type="submit" class="btn">Search</button>
            <a href="/admin/departments" class="btn btn-muted">Clear</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>HOD Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $d)
                        <tr>
                            <td>{{ $d->id }}</td>
                            <td>{{ $d->name }}</td>
                            <td>{{ $d->code }}</td>
                            <td>{{ $d->hod_name }}</td>
                            <td class="actions">
                                <a href="/admin/departments/{{ $d->id }}/edit" class="btn btn-muted">Edit</a>
                                <form method="POST" action="/admin/departments/{{ $d->id }}/delete">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No departments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
