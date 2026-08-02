@extends('faculty.layout')

@section('content')
<div class="card">
    <h2>Your Timetable</h2>
    <p class="section-intro">Weekly schedule and assigned classes.</p>
    
    <div style="padding: 40px; text-align: center; background: #f8fafc; border-radius: 12px; border: 2px dashed #e2e8f0; color: #64748b;">
        <svg style="width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        <h3 style="margin: 0 0 8px; color: #334155;">No Timetable Available</h3>
        <p style="margin: 0;">Your timetable for the current semester has not been generated yet.</p>
    </div>
</div>
@endsection
