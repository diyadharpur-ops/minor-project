@extends('student.layout')

@section('content')
    <div class="card">
        <h2>Welcome, {{ $student->name }}</h2>
        <p class="section-intro">Your student account details are shown below.</p>

        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <div class="list-card">
                <h3>Enrollment No</h3>
                <p>{{ $student->enrollment_number ?? 'Not available' }}</p>
            </div>
            <div class="list-card">
                <h3>Department</h3>
                <p>{{ $student->department ?? 'Not available' }}</p>
            </div>
            <div class="list-card">
                <h3>Semester</h3>
                <p>{{ $student->semester ?? 'Not available' }}</p>
            </div>
            <div class="list-card">
                <h3>Email</h3>
                <p>{{ $student->email ?? 'Not available' }}</p>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Quick Access</h2>
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
