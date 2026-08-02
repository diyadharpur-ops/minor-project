@extends('admin.layout')

@section('title', 'Manage Notifications')

@section('content')
    <div class="page-header">
        <div>
            <h1>Manage Notifications</h1>
            <p>Create announcements for students, faculty, or everyone.</p>
        </div>
    </div>

    <div class="page-card">
        <form method="POST" action="/admin/notifications">
            @csrf
            <div class="form-row">
                <label>Title</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-row">
                <label>Type</label>
                <select name="type" required>
                    <option>Holiday</option>
                    <option>Exam Schedule</option>
                    <option>New Timetable</option>
                    <option>Event</option>
                    <option>Meeting</option>
                    <option>Faculty</option>
                </select>
            </div>
            <div class="form-row">
                <label>Audience</label>
                <select name="audience" required>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="form-row">
                <label>Message</label>
                <textarea name="message" rows="4" required></textarea>
            </div>
            <button class="btn" type="submit">Publish Notification</button>
        </form>
    </div>

    <div class="page-card">
        <h3>Recent Notifications</h3>
        @forelse($notifications as $notification)
            <div style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                <strong>{{ $notification->title }}</strong> <span style="color:#4b5563;">({{ $notification->type }})</span><br>
                <span style="color:#4b5563;">{{ $notification->message }}</span><br>
                <small>Audience: {{ $notification->audience }} • Posted on {{ $notification->created_at->format('M d, Y') }}</small>
            </div>
        @empty
            <p>No notifications yet.</p>
        @endforelse
    </div>
@endsection
