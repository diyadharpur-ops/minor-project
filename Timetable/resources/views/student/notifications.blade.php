@extends('student.layout')

@section('content')
    <div class="card">
        <h2>Receive Notifications</h2>
        <p class="section-intro">Latest announcements for your student account.</p>
        <div class="list-card">
            <div style="margin-bottom: 14px;"><strong>Timetable released</strong><br><span style="color:#4e607f;">Your updated class timetable is now available.</span></div>
            <div style="margin-bottom: 14px;"><strong>Exam schedule update</strong><br><span style="color:#4e607f;">Please check the notice board for exam dates.</span></div>
            <div style="margin-bottom: 14px;"><strong>Profile reminder</strong><br><span style="color:#4e607f;">Make sure your profile information is correct.</span></div>
        </div>
    </div>
@endsection
