@extends('student.layout')

@section('content')
    <div class="card">
        <h2>Student Dashboard</h2>
        <p class="section-intro">Your profile is now created and available in the sidebar.</p>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <div class="list-card">
                <h3>View Timetable</h3>
                <p>Open your class timetable and see your schedule.</p>
                <a href="/student/timetable">Open timetable</a>
            </div>
            <div class="list-card">
                <h3>Receive Notifications</h3>
                <p>See the latest announcements from your institute.</p>
                <a href="/student/notifications">Read notifications</a>
            </div>
            <div class="list-card">
                <h3>Update Profile</h3>
                <p>Keep your student data up to date.</p>
                <a href="/student/profile/edit">Edit profile</a>
            </div>
        </div>
    </div>
@endsection
