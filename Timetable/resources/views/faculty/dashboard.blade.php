@extends('faculty.layout')

@section('content')
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
