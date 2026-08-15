@extends('faculty.layout')

@section('content')
<div class="card" style="margin-bottom: 24px;">
    <h3 class="section-intro">Faculty Information</h3>
    <div style="display: grid; gap: 8px; font-size: 1.05rem;">
        <div><strong style="color: #34415d;">Name:</strong> {{ session('faculty.auth.name') }}</div>
        <div><strong style="color: #34415d;">Email:</strong> {{ session('faculty.auth.email') }}</div>
        <div><strong style="color: #34415d;">Department:</strong> {{ session('faculty.auth.department_name') }}</div>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-bottom: 24px;">
    <div class="card" style="margin-bottom: 0;">
        <h3 class="section-intro">Your Classes</h3>
        <p>You have classes scheduled for today.</p>
        <a href="/faculty/timetable" class="button">View Timetable</a>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <h3 class="section-intro">Recent Notifications</h3>
        <p>Check the latest announcements and updates.</p>
        <a href="/faculty/notifications" class="button secondary">View All</a>
    </div>
</div>

<div class="card">
    <h3 class="section-intro">Quick Links</h3>
    <div class="list-card">
        <a href="/faculty/profile/edit">Update Profile Details</a>
        <a href="/faculty/subjects">View Assigned Subjects</a>
    </div>
</div>
@endsection
