@extends('admin.layout')

@section('title', 'Edit Faculty')

@section('content')
    <div class="page-header">
        <div>
            <h1>Edit Faculty</h1>
            <p>Update the selected faculty record.</p>
        </div>
        <a href="/admin/faculties" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/faculties/{{ $faculty->id }}">
            @csrf
            <div class="form-row">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $faculty->name) }}" required />
            </div>
            <div class="form-row">
                <label>Designation</label>
                <input type="text" name="designation" value="{{ old('designation', $faculty->designation) }}" required />
            </div>
            <div class="form-row">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $faculty->email) }}" required />
            </div>
            <div class="form-row">
                <label>Change Password (leave blank to keep)</label>
                <input type="password" name="password" value="" />
            </div>
            <div class="form-row">
                <label>Change Password (leave blank to keep)</label>
                <input type="password" name="password" value="" />
            </div>
            <div class="form-row">
                <label>Department</label>
                <select name="department_id" required>
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ old('department_id', $faculty->department_id) == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label>Subjects</label>
                <textarea name="subjects" rows="4">{{ old('subjects', $faculty->subjects) }}</textarea>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Save</button>
                <a href="/admin/faculties" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>
@endsection
