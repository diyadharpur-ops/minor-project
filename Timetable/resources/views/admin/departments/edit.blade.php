@extends('admin.layout')

@section('title', 'Edit Department')

@section('content')
    <div class="page-header">
        <div>
            <h1>Edit Department</h1>
            <p>Update the selected department record.</p>
        </div>
        <a href="/admin/departments" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/departments/{{ $dept->id }}">
            @csrf
            <div class="form-row">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $dept->name) }}" required />
            </div>
            <div class="form-row">
                <label>Code</label>
                <input type="text" name="code" value="{{ old('code', $dept->code) }}" />
            </div>
            <div class="form-row">
                <label>HOD Name</label>
                <input type="text" name="hod_name" value="{{ old('hod_name', $dept->hod_name) }}" />
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Save</button>
                <a href="/admin/departments" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>
@endsection
