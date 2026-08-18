@forelse($notifications as $notification)
<div class="card shadow-sm border-0 mb-3 rounded-4 notification-card overflow-hidden transition-all-200" id="notification-row-{{ $notification->id }}" style="border-left: 5px solid {{ $notification->priority == 'High' ? '#ef4444' : ($notification->priority == 'Medium' ? '#f59e0b' : '#3b82f6') }};">
    <div class="card-body p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="priority-icon rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; background-color: {{ $notification->priority == 'High' ? '#fee2e2' : ($notification->priority == 'Medium' ? '#fef3c7' : '#dbeafe') }}; color: {{ $notification->priority == 'High' ? '#ef4444' : ($notification->priority == 'Medium' ? '#d97706' : '#2563eb') }};">
                @if($notification->priority == 'High')
                    <i class="fas fa-exclamation-circle fa-lg"></i>
                @elseif($notification->priority == 'Medium')
                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                @else
                    <i class="fas fa-info-circle fa-lg"></i>
                @endif
            </div>
            
            <div class="notification-content">
                <h5 class="mb-1 fw-bold text-dark h6" style="font-size: 0.95rem;">{{ $notification->title }}</h5>
                <p class="text-muted small mb-1" style="font-size: 0.85rem; line-height: 1.4;">{{ $notification->description }}</p>
                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                    <span class="badge rounded-pill bg-light text-dark border small-badge px-2 py-1">
                        <i class="fas fa-cube text-secondary me-1"></i>{{ $notification->module_name ?? 'General' }}
                    </span>
                    <span class="badge rounded-pill {{ $notification->priority == 'High' ? 'bg-danger-subtle text-danger' : ($notification->priority == 'Medium' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-primary-subtle text-primary') }} small-badge px-2 py-1">
                        {{ $notification->priority }}
                    </span>
                    <span class="badge rounded-pill {{ $notification->status == 'Read' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} small-badge px-2 py-1" id="badge-status-{{ $notification->id }}">
                        {{ $notification->status }}
                    </span>
                    @if($notification->created_by)
                    <span class="text-muted d-inline-flex align-items-center" style="font-size: 0.72rem;">
                        <i class="fas fa-user-circle me-1 text-secondary"></i>{{ $notification->created_by }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="d-flex flex-wrap align-items-center gap-2 justify-content-md-end text-md-end flex-shrink-0">
            <div class="text-muted small me-md-2" style="font-size: 0.74rem; min-width: 100px; line-height: 1.35;">
                <div><i class="far fa-calendar-alt me-1 text-secondary"></i>{{ $notification->created_at->format('d M Y') }}</div>
                <div><i class="far fa-clock me-1 text-secondary"></i>{{ $notification->created_at->format('h:i A') }}</div>
            </div>
            
            <div class="d-flex gap-1 action-buttons-group">
                <a href="{{ route('notifications.show', $notification->id) }}" class="btn btn-sm px-3 py-1-5 rounded-3 fw-bold text-white transition-all-100" style="background-color: #2563eb; font-size: 0.75rem; border: none;">
                    View Details
                </a>
                
                @if($notification->status == 'Unread')
                <button type="button" class="btn btn-sm btn-outline-success px-3 py-1-5 rounded-3 fw-bold btn-mark-read transition-all-100" data-id="{{ $notification->id }}" id="btn-read-{{ $notification->id }}" style="font-size: 0.75rem;">
                    Mark as Read
                </button>
                @endif
                
                <button type="button" class="btn btn-sm btn-outline-danger px-3 py-1-5 rounded-3 fw-bold btn-delete-notification transition-all-100" data-id="{{ $notification->id }}" style="font-size: 0.75rem;">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
@empty
<div class="text-center py-5 bg-white rounded-4 shadow-sm border border-light" style="border: 1px solid rgba(148, 163, 184, 0.12) !important;">
    <div class="mb-3 text-muted">
        <i class="fas fa-bell-slash fa-4x" style="color: #cbd5e1;"></i>
    </div>
    <h4 class="fw-bold text-dark mb-1 h5">No Notifications Available</h4>
    <p class="text-muted small mb-0" style="font-size: 0.85rem;">There are no notifications to display in this category.</p>
</div>
@endforelse

@if($notifications->hasPages())
<div class="mt-4 d-flex justify-content-center list-pagination-wrapper">
    {{ $notifications->links() }}
</div>
@endif
