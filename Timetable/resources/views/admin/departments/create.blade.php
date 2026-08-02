@extends('admin.layout')

@section('title', 'Create Department')

@section('content')
    <div class="page-header">
        <div>
            <h1>Create Department</h1>
            <p>Add a new academic department.</p>
        </div>
        <a href="/admin/departments" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/departments">
            @csrf
            <div class="form-row">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required />
            </div>
            <div class="form-row">
                <label>Code</label>
                <input type="text" name="code" value="{{ old('code') }}" />
            </div>
            <div class="form-row">
                <label>Description</label>
                <textarea name="description" rows="4">{{ old('description') }}</textarea>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Create</button>
                <a href="/admin/departments" class="btn btn-muted">Cancel</a>
            </div>
        </form>
    </div>
@endsection
