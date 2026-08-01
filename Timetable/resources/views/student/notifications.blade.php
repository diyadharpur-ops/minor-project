@extends('student.layout')

@section('content')
    <div class="card">
        <h2>Receive Notifications</h2>
        <p class="section-intro">Latest announcements for your student account.</p>
        <div class="list-card">
            @forelse($notifications as $notification)
                <div style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid rgba(15, 61, 94, 0.1);">
                    <strong>{{ $notification->title }}</strong><br>
                    <span style="color:#4e607f;">{{ $notification->message }}</span><br>
                    <small style="color:#6b7280;">{{ $notification->type }} • {{ $notification->created_at->format('M d, Y') }}</small>
                </div>
            @empty
                <p>No notifications available right now.</p>
            @endforelse
        </div>
    </div>
@endsection
