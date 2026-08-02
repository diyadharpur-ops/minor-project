@extends('admin.layout')

@section('title', 'Generate Timetable')

@section('content')
    <div class="page-header">
        <div>
            <h1>Generate Timetable</h1>
            <p>Automatically generate timetable based on subjects, faculties and classrooms.</p>
        </div>
        <a href="/admin/dashboard" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if(session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        <form method="POST" action="/admin/timetable/generate">
            @csrf
            <p>Press the button below to generate a new timetable. This is a placeholder generator — implement scheduling logic as needed.</p>
            <div class="page-actions">
                <button type="submit" class="btn">Generate Timetable</button>
                <a href="/admin/timetable" class="btn btn-muted">Refresh</a>
            </div>
        </form>
    </div>
@endsection
