@extends('admin.layout')

@section('title', 'Notification Details')

@section('content')
<!-- Bootstrap 5 CSS CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome CDN -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

<div class="container-fluid p-0" style="font-family: 'Inter', system-ui, -apple-system, sans-serif;">
    <div class="row justify-content-center py-4">
        <div class="col-xl-8 col-lg-10">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="/admin/notifications" class="btn btn-outline-secondary px-3 py-2 rounded-3 fw-bold small-text d-inline-flex align-items-center gap-2" style="border: 1px solid rgba(148, 163, 184, 0.3);">
                    <i class="fas fa-arrow-left"></i> Back to Notifications Dashboard
                </a>
            </div>

            <!-- Detailed Panel -->
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white">
                <!-- Color bar depending on priority -->
                <div style="height: 6px; background-color: {{ $notification->priority == 'High' ? '#ef4444' : ($notification->priority == 'Medium' ? '#f59e0b' : '#3b82f6') }};"></div>
                
                <div class="card-body p-5">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-4 pb-4 border-bottom mb-4">
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px; background-color: {{ $notification->priority == 'High' ? '#fee2e2' : ($notification->priority == 'Medium' ? '#fef3c7' : '#dbeafe') }}; color: {{ $notification->priority == 'High' ? '#ef4444' : ($notification->priority == 'Medium' ? '#d97706' : '#2563eb') }};">
                                @if($notification->priority == 'High')
                                    <i class="fas fa-exclamation-circle fa-2x"></i>
                                @elseif($notification->priority == 'Medium')
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                @else
                                    <i class="fas fa-info-circle fa-2x"></i>
                                @endif
                            </div>
                            <div>
                                <h1 class="h4 fw-bold text-dark mb-2">{{ $notification->title }}</h1>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="badge rounded-pill bg-light text-dark border px-2 py-1 small-badge">
                                        <i class="fas fa-cube text-secondary me-1"></i>{{ $notification->module_name ?? 'General' }}
                                    </span>
                                    <span class="badge rounded-pill {{ $notification->priority == 'High' ? 'bg-danger text-white' : ($notification->priority == 'Medium' ? 'bg-warning text-dark' : 'bg-primary text-white') }} px-2 py-1 small-badge">
                                        Priority: {{ $notification->priority }}
                                    </span>
                                    <span class="badge rounded-pill {{ $notification->status == 'Read' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} px-2 py-1 small-badge">
                                        Status: {{ $notification->status }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Single action button inside detail -->
                        @if($notification->status == 'Unread')
                        <form method="POST" action="{{ route('notifications.markAsRead', $notification->id) }}" class="flex-shrink-0">
                            @csrf
                            <button type="submit" class="btn btn-success px-4 py-2 rounded-3 fw-bold small-text d-flex align-items-center gap-2">
                                <i class="fas fa-check"></i> Mark as Read
                            </button>
                        </form>
                        @endif
                    </div>

                    <!-- Description Block -->
                    <div class="mb-5">
                        <h4 class="h6 fw-bold text-secondary text-uppercase letter-spacing-01 mb-3">Notification Message</h4>
                        <div class="p-4 rounded-4 bg-light border border-light" style="font-size: 0.95rem; line-height: 1.6; color: #1e293b; background-color: #f8fafc !important;">
                            {{ $notification->description }}
                        </div>
                    </div>

                    <!-- Metadata Table Grid -->
                    <div class="mb-4">
                        <h4 class="h6 fw-bold text-secondary text-uppercase letter-spacing-01 mb-3">Announcement Metadata</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-white h-100 d-flex flex-column gap-1">
                                    <span class="text-muted small-text fw-medium">Category Topic</span>
                                    <strong class="text-dark"><i class="fas fa-tags text-primary me-2"></i>{{ $notification->category ?? 'General' }}</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-white h-100 d-flex flex-column gap-1">
                                    <span class="text-muted small-text fw-medium">Module / Reference ID</span>
                                    <strong class="text-dark">
                                        <i class="fas fa-key text-success me-2"></i>
                                        @if($notification->reference_id)
                                            ID: {{ $notification->reference_id }} (Scope: {{ $notification->module_name }})
                                        @else
                                            No Reference Associated
                                        @endif
                                    </strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-white h-100 d-flex flex-column gap-1">
                                    <span class="text-muted small-text fw-medium">Published By</span>
                                    <strong class="text-dark"><i class="fas fa-user-shield text-info me-2"></i>{{ $notification->created_by ?? 'System' }}</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-white h-100 d-flex flex-column gap-1">
                                    <span class="text-muted small-text fw-medium">Posted Timestamp</span>
                                    <strong class="text-dark">
                                        <i class="far fa-calendar-alt text-danger me-2"></i>
                                        {{ $notification->created_at->format('d M Y - h:i A') }}
                                        <small class="text-muted fw-normal ms-1">({{ $notification->created_at->diffForHumans() }})</small>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Compatibility Block (Accordion style or simple block) -->
                    <div class="mt-5 border-top pt-4">
                        <p class="text-muted" style="font-size: 0.72rem;">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Developer Compatibility Metadata</strong>: Audience: <em>{{ $notification->audience ?? 'all' }}</em> • Old Type: <em>{{ $notification->type ?? 'N/A' }}</em> • Old Message Length: <em>{{ strlen($notification->message ?? '') }} chars</em>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
