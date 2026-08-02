@extends('admin.layout')

@section('title', 'Classrooms')

@section('content')
    <div class="page-header">
        <div>
            <h1>Classrooms</h1>
            <p>Keep room availability and room details organized.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/dashboard" class="btn btn-muted">Back</a>
            <a href="/admin/classrooms/create" class="btn">Add Classroom</a>
        </div>
    </div>

    <div class="page-card">
        <form method="GET" action="/admin/classrooms" class="search">
            <input type="text" name="q" placeholder="Search by room number or type" value="{{ $q ?? '' }}" />
            <button type="submit" class="btn">Search</button>
            <a href="/admin/classrooms" class="btn btn-muted">Clear</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Room Number</th>
                        <th>Capacity</th>
                        <th>Type</th>
                        <th>Availability</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classrooms as $classroom)
                        <tr>
                            <td>{{ $classroom->id }}</td>
                            <td>{{ $classroom->room_number }}</td>
                            <td>{{ $classroom->room_capacity }}</td>
                            <td>{{ $classroom->room_type }}</td>
                            <td>{{ $classroom->availability }}</td>
                            <td class="actions">
                                <a href="/admin/classrooms/{{ $classroom->id }}/edit" class="btn btn-muted">Edit</a>
                                <form method="POST" action="/admin/classrooms/{{ $classroom->id }}/delete">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No classrooms found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
