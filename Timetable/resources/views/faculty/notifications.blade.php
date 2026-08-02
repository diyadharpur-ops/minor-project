@extends('faculty.layout')

@section('content')
<div class="card">
    <h2>Notifications</h2>
    <p class="section-intro">Important announcements and updates.</p>

    <div class="grid">
        @forelse($notifications as $notification)
            <div style="padding: 20px; border-radius: 14px; border: 1px solid #eef4ff; background: #fafcff; display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h3 style="margin: 0; font-size: 1.1rem;">{{ $notification->title }}</h3>
                    <span style="font-size: 0.85rem; color: #64748b; background: #eef4ff; padding: 4px 10px; border-radius: 20px;">
                        {{ $notification->created_at->diffForHumans() }}
                    </span>
                </div>
                <div style="color: #64748b; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">
                    Type: {{ $notification->type }}
                </div>
                <p style="margin: 8px 0 0; color: #334155; line-height: 1.6;">
                    {{ $notification->message }}
                </p>
            </div>
        @empty
            <div style="padding: 30px; text-align: center; background: #f8fafc; border-radius: 12px; color: #64748b;">
                No notifications found.
            </div>
        @endforelse
    </div>
</div>
@endsection
