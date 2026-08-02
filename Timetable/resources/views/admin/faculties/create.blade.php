@extends('admin.layout')

@section('title', 'Create Faculty')

@section('content')
    <div class="page-header">
        <div>
            <h1>Add Faculty</h1>
            <p>Create a faculty profile linked to a department.</p>
        </div>
        <a href="/admin/faculties" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/faculties">
            @csrf
            <div class="form-row">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required />
            </div>
            <div class="form-row">
                <label>Mobile Number</label>
                <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" required />
            </div>
            <div class="form-row">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required />
            </div>
            <div class="form-row">
                <label>Qualification</label>
                <input type="text" name="qualification" value="{{ old('qualification') }}" required />
            </div>
            <div class="form-row">
                <label>Department</label>
                <select name="department_id" required>
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label>Subjects</label>
                <textarea name="subjects" rows="4">{{ old('subjects') }}</textarea>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/faculties" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>
@endsection
