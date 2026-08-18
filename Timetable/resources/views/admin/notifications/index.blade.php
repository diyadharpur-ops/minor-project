@extends('admin.layout')

@section('title', 'Smart Notifications Dashboard')

@section('content')
<!-- Bootstrap 5 CSS CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome CDN -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

<style>
    /* Styling System for Premium Look */
    .notif-dashboard-wrapper {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .notif-header {
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.15);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }

    .notification-stat-card {
        border: 1px solid rgba(148, 163, 184, 0.15) !important;
        transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .notification-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(37, 99, 235, 0.08) !important;
        background-color: #fcfdfe;
    }

    .notification-stat-card.active-card {
        border-color: #2563eb !important;
        background-color: #f5f8ff;
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.05) !important;
    }

    .stat-icon-wrapper {
        transition: transform 0.2s ease;
    }

    .notification-stat-card:hover .stat-icon-wrapper {
        transform: scale(1.1);
    }

    .notification-card {
        border: 1px solid rgba(148, 163, 184, 0.15) !important;
        transition: all 0.2s ease-in-out;
    }

    .notification-card:hover {
        transform: translateX(4px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05) !important;
    }

    .btn-filter {
        background-color: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.18);
        color: #475569;
        transition: all 0.15s ease;
    }

    .btn-filter:hover, .btn-filter.active {
        background-color: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
    }

    .py-1-5 {
        padding-top: 0.38rem;
        padding-bottom: 0.38rem;
    }

    .small-badge {
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .small-text {
        font-size: 0.76rem;
    }

    /* Soft priority colors */
    .bg-danger-subtle { background-color: #fee2e2 !important; }
    .text-danger { color: #ef4444 !important; }
    .bg-warning-subtle { background-color: #fef3c7 !important; }
    .text-warning-emphasis { color: #b45309 !important; }
    .bg-primary-subtle { background-color: #dbeafe !important; }
    .text-primary { color: #2563eb !important; }
    .bg-success-subtle { background-color: #dcfce7 !important; }
    .text-success { color: #16a34a !important; }
    .bg-secondary-subtle { background-color: #f1f5f9 !important; }
    .text-secondary { color: #64748b !important; }

    /* Custom Toast styles */
    #notif-toast-container {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1060;
    }

    /* AJAX Fade-in list animation */
    .fade-in-list {
        animation: fadeInList 0.35s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }

    @keyframes fadeInList {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="notif-dashboard-wrapper container-fluid p-0">
    <!-- Header Panel -->
    <div class="notif-header p-4 mb-4 bg-white">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">Smart Notifications Dashboard</h1>
                <p class="text-muted mb-0 small-text">Monitor system activity, allocate resources, and publish manual announcements.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary px-3 rounded-3 fw-bold small-text d-flex align-items-center gap-2" id="btn-refresh-all">
                    <i class="fas fa-sync-alt" id="refresh-icon"></i> Refresh
                </button>
                <button type="button" class="btn btn-outline-success px-3 rounded-3 fw-bold small-text d-flex align-items-center gap-2" id="btn-read-all">
                    <i class="fas fa-check-double"></i> Mark All As Read
                </button>
                <button type="button" class="btn btn-outline-danger px-3 rounded-3 fw-bold small-text d-flex align-items-center gap-2" id="btn-clear-all">
                    <i class="fas fa-trash-alt"></i> Clear All
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Statistics Grid -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Notifications -->
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card shadow-sm rounded-4 notification-stat-card active-card" data-filter="All">
                <div class="card-body text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div class="stat-icon-wrapper bg-primary-subtle text-primary rounded-circle mb-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fas fa-bell fa-lg"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark h4" id="stat-total">{{ $totalNotifications }}</h3>
                    <p class="text-muted small mb-0 fw-medium" style="font-size: 0.74rem;">Total Notifications</p>
                </div>
            </div>
        </div>
        <!-- Card 2: High Priority -->
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card shadow-sm rounded-4 notification-stat-card" data-filter="High">
                <div class="card-body text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div class="stat-icon-wrapper bg-danger-subtle text-danger rounded-circle mb-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark h4" id="stat-high">{{ $highPriority }}</h3>
                    <p class="text-muted small mb-0 fw-medium" style="font-size: 0.74rem;">High Priority</p>
                </div>
            </div>
        </div>
        <!-- Card 3: Medium Priority -->
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card shadow-sm rounded-4 notification-stat-card" data-filter="Medium">
                <div class="card-body text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div class="stat-icon-wrapper bg-warning-subtle text-warning-emphasis rounded-circle mb-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fas fa-exclamation-triangle fa-lg"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark h4" id="stat-medium">{{ $mediumPriority }}</h3>
                    <p class="text-muted small mb-0 fw-medium" style="font-size: 0.74rem;">Medium Priority</p>
                </div>
            </div>
        </div>
        <!-- Card 4: Information -->
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card shadow-sm rounded-4 notification-stat-card" data-filter="Info">
                <div class="card-body text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div class="stat-icon-wrapper bg-primary-subtle text-primary rounded-circle mb-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fas fa-info-circle fa-lg"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark h4" id="stat-info">{{ $information }}</h3>
                    <p class="text-muted small mb-0 fw-medium" style="font-size: 0.74rem;">Information</p>
                </div>
            </div>
        </div>
        <!-- Card 5: Unread -->
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card shadow-sm rounded-4 notification-stat-card" data-filter="Unread">
                <div class="card-body text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div class="stat-icon-wrapper bg-secondary-subtle text-secondary rounded-circle mb-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fas fa-envelope fa-lg"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark h4" id="stat-unread">{{ $unread }}</h3>
                    <p class="text-muted small mb-0 fw-medium" style="font-size: 0.74rem;">Unread</p>
                </div>
            </div>
        </div>
        <!-- Card 6: Read -->
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card shadow-sm rounded-4 notification-stat-card" data-filter="Read">
                <div class="card-body text-center d-flex flex-column align-items-center justify-content-center p-3">
                    <div class="stat-icon-wrapper bg-success-subtle text-success rounded-circle mb-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fas fa-envelope-open fa-lg"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark h4" id="stat-read">{{ $read }}</h3>
                    <p class="text-muted small mb-0 fw-medium" style="font-size: 0.74rem;">Read</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Module Area -->
    <div class="row g-4">
        <!-- Left Side: Notifications List & Filters (8 Columns) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 fw-bold text-dark mb-0">Notification Feed</h2>
                    <span class="text-muted small-text" id="filter-status-text">Showing: All Notifications</span>
                </div>

                <!-- Filter Pills -->
                <div class="d-flex flex-wrap gap-2 mb-4" id="filter-pills-container">
                    <button class="btn btn-sm btn-filter rounded-pill px-3 py-1-5 fw-bold active" data-filter="All">All</button>
                    <button class="btn btn-sm btn-filter rounded-pill px-3 py-1-5 fw-bold" data-filter="High">High</button>
                    <button class="btn btn-sm btn-filter rounded-pill px-3 py-1-5 fw-bold" data-filter="Medium">Medium</button>
                    <button class="btn btn-sm btn-filter rounded-pill px-3 py-1-5 fw-bold" data-filter="Info">Information</button>
                    <button class="btn btn-sm btn-filter rounded-pill px-3 py-1-5 fw-bold" data-filter="Unread">Unread</button>
                    <button class="btn btn-sm btn-filter rounded-pill px-3 py-1-5 fw-bold" data-filter="Read">Read</button>
                </div>

                <!-- Dynamic Notification List Container -->
                <div id="notifications-list-container" class="fade-in-list">
                    @include('admin.notifications._list')
                </div>
            </div>
        </div>

        <!-- Right Side: Action Forms & Simulator Panel (4 Columns) -->
        <div class="col-lg-4">
            <div class="d-flex flex-column gap-4">
                <!-- Form Card: Publish Announcement -->
                <div class="card border-0 shadow-sm rounded-4 bg-white">
                    <div class="card-body p-4">
                        <h3 class="h5 fw-bold text-dark mb-3"><i class="fas fa-bullhorn text-primary me-2"></i>Publish Announcement</h3>
                        <form method="POST" action="/admin/notifications">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small-text fw-bold text-secondary">Announcement Title</label>
                                <input type="text" name="title" class="form-control rounded-3 py-2 small-text" placeholder="e.g. Schedule Altered" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small-text fw-bold text-secondary">Category</label>
                                <select name="category" class="form-select rounded-3 py-2 small-text" required>
                                    <option value="General">General Announcement</option>
                                    <option value="Timetable">Timetable Schedule</option>
                                    <option value="Holiday">Holiday & Closure</option>
                                    <option value="Exam">Exam Schedule</option>
                                    <option value="Event">Campus Event</option>
                                    <option value="Faculty">Faculty Notice</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small-text fw-bold text-secondary">Priority Level</label>
                                <select name="priority" class="form-select rounded-3 py-2 small-text" required>
                                    <option value="Info" selected>Information (Low)</option>
                                    <option value="Medium">Medium Warning</option>
                                    <option value="High">High Priority Alert</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small-text fw-bold text-secondary">Target Audience</label>
                                <select name="audience" class="form-select rounded-3 py-2 small-text" required>
                                    <option value="all" selected>Everyone (Students & Faculty)</option>
                                    <option value="student">Students Only</option>
                                    <option value="faculty">Faculty Only</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small-text fw-bold text-secondary">Message Description</label>
                                <textarea name="description" class="form-control rounded-3 small-text" rows="3" placeholder="Provide complete announcement details..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-bold small-text" style="background-color: #2563eb; border: none;">
                                Publish Announcement
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Simulation Panel Card -->
                <div class="card border-0 shadow-sm rounded-4 bg-white">
                    <div class="card-body p-4">
                        <h3 class="h5 fw-bold text-dark mb-2"><i class="fas fa-terminal text-success me-2"></i>Simulation Tools</h3>
                        <p class="text-muted small-text mb-3">Manually trigger automated system notifications to verify dashboard dynamic behaviors.</p>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary text-start rounded-3 py-2 small-text fw-bold d-flex align-items-center justify-content-between" id="btn-trigger-backup">
                                <span><i class="fas fa-hdd text-info me-2"></i>Trigger System Backup</span>
                                <i class="fas fa-arrow-right text-muted"></i>
                            </button>
                            
                            <button type="button" class="btn btn-outline-primary text-start rounded-3 py-2 small-text fw-bold d-flex align-items-center justify-content-between" id="btn-trigger-semester">
                                <span><i class="fas fa-graduation-cap text-warning me-2"></i>Start New Semester</span>
                                <i class="fas fa-arrow-right text-muted"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Simple Bootstrap Toast for AJAX Feedback -->
<div id="notif-toast-container">
    <div class="toast align-items-center text-white border-0 rounded-3 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" id="ajax-toast" style="background-color: #1e293b;">
        <div class="d-flex">
            <div class="toast-body small-text d-flex align-items-center gap-2">
                <i class="fas fa-check-circle text-success" id="toast-icon"></i>
                <span id="toast-message">Action completed successfully.</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- AJAX Scripts -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let currentFilter = 'All';
        const csrfToken = '{{ csrf_token() }}';

        // Toast element helper
        const toastEl = document.getElementById('ajax-toast');
        let bootstrapToast = null;
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            bootstrapToast = new bootstrap.Toast(toastEl, { delay: 3000 });
        }

        function showNotificationToast(message, isSuccess = true) {
            const toastMessage = document.getElementById('toast-message');
            const toastIcon = document.getElementById('toast-icon');
            toastMessage.textContent = message;
            
            if (isSuccess) {
                toastIcon.className = 'fas fa-check-circle text-success';
            } else {
                toastIcon.className = 'fas fa-exclamation-circle text-danger';
            }

            if (bootstrapToast) {
                bootstrapToast.show();
            } else {
                // Fallback if Bootstrap JS is not loaded yet
                alert(message);
            }
        }

        // Helper to update summary statistic cards
        function updateStats(stats) {
            if (!stats) return;
            document.getElementById('stat-total').textContent = stats.totalNotifications ?? 0;
            document.getElementById('stat-high').textContent = stats.highPriority ?? 0;
            document.getElementById('stat-medium').textContent = stats.mediumPriority ?? 0;
            document.getElementById('stat-info').textContent = stats.information ?? 0;
            document.getElementById('stat-unread').textContent = stats.unread ?? 0;
            document.getElementById('stat-read').textContent = stats.read ?? 0;

            // Sync with main layout navbar notification dot if present
            const navBadge = document.querySelector('.notification-dot');
            if (navBadge) {
                if ((stats.unread ?? 0) > 0) {
                    navBadge.style.display = 'block';
                } else {
                    navBadge.style.display = 'none';
                }
            }
        }

        // Fetch notifications and inject HTML
        async function fetchNotifications(url) {
            const listContainer = document.getElementById('notifications-list-container');
            const refreshIcon = document.getElementById('refresh-icon');

            if (refreshIcon) {
                refreshIcon.classList.add('fa-spin');
            }

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Network error. Status: ' + response.status);
                }

                const data = await response.json();
                if (data.success) {
                    // Smooth reload of HTML
                    listContainer.classList.remove('fade-in-list');
                    // Trigger reflow
                    void listContainer.offsetWidth;
                    listContainer.innerHTML = data.html;
                    listContainer.classList.add('fade-in-list');

                    // Update count stats
                    updateStats(data.stats);
                    
                    // Bind actions inside new HTML
                    bindListEventHandlers();
                }
            } catch (error) {
                console.error('AJAX fetch error:', error);
                showNotificationToast('Failed to load notifications.', false);
            } finally {
                if (refreshIcon) {
                    refreshIcon.classList.remove('fa-spin');
                }
            }
        }

        // Filter Action
        function filterType(type) {
            currentFilter = type;
            document.getElementById('filter-status-text').textContent = 'Showing: ' + type + ' Notifications';

            // Active pill style update
            document.querySelectorAll('#filter-pills-container .btn-filter').forEach(btn => {
                if (btn.getAttribute('data-filter') === type) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            // Active stat card style update
            document.querySelectorAll('.notification-stat-card').forEach(card => {
                if (card.getAttribute('data-filter') === type) {
                    card.classList.add('active-card');
                } else {
                    card.classList.remove('active-card');
                }
            });

            const filterUrl = `/notifications/filter/${type}`;
            fetchNotifications(filterUrl);
        }

        // Trigger filters on pill clicks
        document.querySelectorAll('#filter-pills-container .btn-filter').forEach(btn => {
            btn.addEventListener('click', function () {
                filterType(this.getAttribute('data-filter'));
            });
        });

        // Trigger filters on stat card clicks
        document.querySelectorAll('.notification-stat-card').forEach(card => {
            card.addEventListener('click', function () {
                filterType(this.getAttribute('data-filter'));
            });
        });

        // Refresh action
        document.getElementById('btn-refresh-all').addEventListener('click', function () {
            const url = `/notifications/refresh?type=${currentFilter}`;
            fetchNotifications(url);
            showNotificationToast('Dashboard updated.');
        });

        // Mark All as Read
        document.getElementById('btn-read-all').addEventListener('click', async function () {
            try {
                const response = await fetch('/notifications/read-all', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    showNotificationToast(data.message);
                    filterType(currentFilter); // reload active feed
                }
            } catch (error) {
                console.error(error);
                showNotificationToast('Failed to mark all as read.', false);
            }
        });

        // Clear All Notifications
        document.getElementById('btn-clear-all').addEventListener('click', async function () {
            if (!confirm('Are you sure you want to delete ALL notifications? This action is permanent.')) {
                return;
            }

            try {
                const response = await fetch('/notifications/clear', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    showNotificationToast(data.message);
                    filterType(currentFilter); // reload active feed
                }
            } catch (error) {
                console.error(error);
                showNotificationToast('Failed to clear notifications.', false);
            }
        });

        // Bind single action handlers for mark-read and delete in feed lists
        function bindListEventHandlers() {
            // Single Mark as Read
            document.querySelectorAll('.btn-mark-read').forEach(btn => {
                btn.addEventListener('click', async function (e) {
                    e.preventDefault();
                    const notifId = this.getAttribute('data-id');
                    try {
                        const response = await fetch(`/notifications/${notifId}/read`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (data.success) {
                            showNotificationToast(data.message);
                            
                            // Dynamically update UI state
                            const row = document.getElementById(`notification-row-${notifId}`);
                            const badge = document.getElementById(`badge-status-${notifId}`);
                            
                            if (badge) {
                                badge.className = 'badge rounded-pill bg-success-subtle text-success small-badge px-2 py-1';
                                badge.textContent = 'Read';
                            }
                            this.remove(); // Remove mark as read button
                            updateStats(data.stats);
                        }
                    } catch (error) {
                        console.error(error);
                        showNotificationToast('Failed to update notification.', false);
                    }
                });
            });

            // Single Delete
            document.querySelectorAll('.btn-delete-notification').forEach(btn => {
                btn.addEventListener('click', async function (e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to delete this notification?')) {
                        return;
                    }

                    const notifId = this.getAttribute('data-id');
                    try {
                        const response = await fetch(`/notifications/${notifId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (data.success) {
                            showNotificationToast(data.message);
                            
                            // Animate out row deletion
                            const row = document.getElementById(`notification-row-${notifId}`);
                            if (row) {
                                row.style.transition = 'all 0.3s ease';
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(-20px)';
                                setTimeout(() => {
                                    row.remove();
                                    // If list is empty after deletion, refresh to show empty state
                                    const feedRows = document.querySelectorAll('.notification-card');
                                    if (feedRows.length === 0) {
                                        filterType(currentFilter);
                                    }
                                }, 300);
                            }
                            updateStats(data.stats);
                        }
                    } catch (error) {
                        console.error(error);
                        showNotificationToast('Failed to delete notification.', false);
                    }
                });
            });

            // Handle Pagination Link clicks via AJAX
            document.querySelectorAll('.list-pagination-wrapper a').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    fetchNotifications(this.getAttribute('href'));
                });
            });
        }

        // SIMULATOR HANDLERS
        // Trigger Backup Simulation
        document.getElementById('btn-trigger-backup').addEventListener('click', async function () {
            this.disabled = true;
            this.querySelector('.fa-hdd').className = 'fas fa-spinner fa-spin text-info me-2';
            
            try {
                const response = await fetch('/admin/backup', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    showNotificationToast(data.message);
                    filterType(currentFilter);
                }
            } catch (error) {
                console.error(error);
                showNotificationToast('Failed to trigger backup.', false);
            } finally {
                this.disabled = false;
                this.querySelector('.fa-spinner').className = 'fas fa-hdd text-info me-2';
            }
        });

        // Trigger New Semester Simulation
        document.getElementById('btn-trigger-semester').addEventListener('click', async function () {
            if (!confirm('Simulate starting a new semester? This will create an automated semester alert.')) {
                return;
            }

            this.disabled = true;
            this.querySelector('.fa-graduation-cap').className = 'fas fa-spinner fa-spin text-warning me-2';
            
            try {
                const response = await fetch('/admin/new-semester', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    showNotificationToast(data.message);
                    filterType(currentFilter);
                }
            } catch (error) {
                console.error(error);
                showNotificationToast('Failed to trigger new semester.', false);
            } finally {
                this.disabled = false;
                this.querySelector('.fa-spinner').className = 'fas fa-graduation-cap text-warning me-2';
            }
        });

        // Bootstrap CSS CDN loading fallback check
        setTimeout(() => {
            // Load Bootstrap JS script if not already loaded
            if (typeof bootstrap === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js';
                document.body.appendChild(script);
                script.onload = function() {
                    bootstrapToast = new bootstrap.Toast(toastEl, { delay: 3000 });
                };
            }
        }, 100);

        // Run initial bind
        bindListEventHandlers();
    });
</script>
@endsection
