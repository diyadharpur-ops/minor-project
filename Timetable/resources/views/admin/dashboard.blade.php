@extends('admin.layout')

@section('title', 'Admin Dashboard')

@php
    $statsList = [
        ['title' => 'Total Departments', 'value' => $stats['departments'] ?? 0, 'subtext' => 'Campus units'],
        ['title' => 'Total Faculty', 'value' => $stats['faculty'] ?? 0, 'subtext' => 'Active members'],
        ['title' => 'Total Subjects', 'value' => $stats['subjects'] ?? 0, 'subtext' => 'Course offerings'],
        ['title' => 'Total Classrooms', 'value' => $stats['classrooms'] ?? 0, 'subtext' => 'Available rooms'],
        ['title' => 'Total Students', 'value' => $stats['students'] ?? 0, 'subtext' => 'Across all years'],
        ['title' => 'Active Timetable', 'value' => $stats['active_timetables'] ?? 0, 'subtext' => 'Current schedules'],
    ];

    $timetable = [
        ['time' => '09:00 - 10:00', 'subject' => 'Add subject', 'meta' => 'Sem • Room'],
        ['time' => '10:00 - 11:00', 'subject' => 'Add subject', 'meta' => 'Sem • Room'],
        ['time' => '11:00 - 12:00', 'subject' => 'Add subject', 'meta' => 'Sem • Room'],
    ];

    $alerts = [
        ['type' => 'success', 'text' => 'No alerts yet'],
        ['type' => 'warning', 'text' => 'Add alert message'],
        ['type' => 'danger', 'text' => 'Add alert message'],
    ];
@endphp

@section('content')
    <style>
        .dashboard-shell {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 20px;
            padding: 20px 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            gap: 18px;
        }

        .header-copy h1 {
            margin: 0;
            font-size: 2rem;
            color: #0f172a;
            letter-spacing: -0.03em;
        }

        .header-copy p {
            margin: 8px 0 0;
            font-size: 0.95rem;
            color: #64748b;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .notification-button {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #334155;
            position: relative;
            transition: all 0.2s ease;
        }

        .notification-button:hover {
            background: #eef4ff;
            border-color: #cfe0ff;
            transform: translateY(-1px);
        }

        .notification-dot {
            position: absolute;
            top: 8px;
            right: 9px;
            width: 9px;
            height: 9px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .profile-summary {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px 8px 8px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .profile-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0f172a, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .profile-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .profile-meta strong {
            font-size: 0.96rem;
            color: #0f172a;
        }

        .profile-meta span {
            font-size: 0.78rem;
            color: #64748b;
        }

        .profile-chevron {
            color: #64748b;
            margin-left: 4px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(160px, 1fr));
            gap: 18px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 18px;
            padding: 18px 18px 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(37, 99, 235, 0.08);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: rgba(37, 99, 235, 0.12);
            color: #1d4ed8;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-content {
            min-width: 0;
            flex: 1;
        }

        .stat-title {
            display: block;
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .stat-number {
            display: block;
            font-size: 1.8rem;
            color: #0f172a;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 4px;
            letter-spacing: -0.03em;
        }

        .stat-subtext {
            display: block;
            font-size: 0.74rem;
            color: #94a3b8;
        }

        .quick-access {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(210px, 1fr));
            gap: 18px;
        }

        .module-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 18px 18px 16px;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .module-card:hover {
            border-color: #bfd2ff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.06);
            transform: translateY(-2px);
        }

        .module-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(37, 99, 235, 0.1);
            color: #1d4ed8;
        }

        .module-card h3 {
            margin: 0;
            color: #0f172a;
            font-size: 1.06rem;
        }

        .module-card p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.55;
            flex: 1;
        }

        .module-action {
            margin-top: 4px;
        }

        .module-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 700;
            background: #2563eb;
            color: white;
            border: 1px solid #2563eb;
            transition: all 0.2s ease;
        }

        .module-btn:hover {
            background: #1d4ed8;
            color: white;
        }

        .module-btn.secondary {
            background: #eef4ff;
            color: #1d4ed8;
            border-color: #dfe9ff;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 20px;
        }

        .summary-panel {
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 20px;
            padding: 22px 22px 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .panel-head h3 {
            margin: 0;
            color: #0f172a;
            font-size: 1.25rem;
            letter-spacing: -0.02em;
        }

        .panel-link {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .timetable-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .timetable-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 12px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .time-label {
            font-size: 0.82rem;
            color: #2563eb;
            font-weight: 700;
            margin-bottom: 6px;
            display: block;
        }

        .subject-name {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .subject-meta {
            font-size: 0.82rem;
            color: #64748b;
        }

        .alert-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .alert-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 0.94rem;
            color: #334155;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .dot.success { background: #22c55e; }
        .dot.warning { background: #f59e0b; }
        .dot.danger { background: #ef4444; }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(180px, 1fr));
            }

            .module-grid {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }

        @media (max-width: 900px) {
            .main-content {
                padding: 18px !important;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .stats-grid,
            .module-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="dashboard-shell">
        <header class="dashboard-header">
            <div class="header-copy">
                <h1>Welcome, {{ session('admin.auth.name') ?? 'Admin User' }}</h1>
                <p>Admin dashboard for timetable management</p>
            </div>

            <div class="header-actions">
                @php
                    $unreadNotificationsCount = \App\Models\Notification::where('status', 'Unread')->count();
                @endphp
                <a href="/admin/notifications" class="notification-button" aria-label="Notifications" style="text-decoration: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/>
                        <path d="M10 20a2 2 0 0 0 4 0"/>
                    </svg>
                    @if($unreadNotificationsCount > 0)
                        <span class="notification-dot" style="display: block;"></span>
                    @else
                        <span class="notification-dot" style="display: none;"></span>
                    @endif
                </a>

                <div class="profile-summary" aria-label="Admin profile">
                    <div class="profile-avatar">AU</div>
                    <div class="profile-meta">
                        <strong>Admin User</strong>
                        <span>{{ session('admin.auth.email') ?? 'admin@example.com' }}</span>
                    </div>
                    <svg class="profile-chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </div>
            </div>
        </header>

        <section class="stats-grid" aria-label="Statistics overview">
            @foreach ($statsList as $stat)
                <div class="stat-card">
                    <div class="stat-icon">
                        @switch($loop->index)
                            @case(0)
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 21h18"/>
                                    <path d="M6 21V7l6-4 6 4v14"/>
                                    <path d="M9 10h.01"/><path d="M15 10h.01"/><path d="M9 14h.01"/><path d="M15 14h.01"/>
                                </svg>
                                @break
                            @case(1)
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9.5" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                @break
                            @case(2)
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v15H6.5A2.5 2.5 0 0 1 4 14.5v-10A2.5 2.5 0 0 1 6.5 2Z"/>
                                    <path d="M8 6h8"/><path d="M8 10h8"/><path d="M8 14h5"/>
                                </svg>
                                @break
                            @case(3)
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 21h18"/>
                                    <path d="M5 21V7l7-4 7 4v14"/>
                                    <path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M9 13h.01"/><path d="M15 13h.01"/>
                                </svg>
                                @break
                            @case(4)
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9.5" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                @break
                            @default
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                        @endswitch
                    </div>
                    <div class="stat-content">
                        <span class="stat-title">{{ $stat['title'] }}</span>
                        <span class="stat-number">{{ $stat['value'] }}</span>
                        <span class="stat-subtext">{{ $stat['subtext'] }}</span>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="quick-access" aria-label="Quick access modules">
            <div class="section-title">
                <h2>Quick Access</h2>
            </div>

            <div class="module-grid">
                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M9 13h.01"/><path d="M15 13h.01"/></svg>
                    </div>
                    <h3>Manage Departments</h3>
                    <p>Create and manage department records.</p>
                    <div class="module-action">
                        <a href="/admin/departments" class="module-btn secondary">Open</a>
                    </div>
                </article>

                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3>Manage Faculty</h3>
                    <p>Manage faculty members and assignments.</p>
                    <div class="module-action">
                        <a href="/admin/faculties" class="module-btn secondary">Open</a>
                    </div>
                </article>

                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v15H6.5A2.5 2.5 0 0 1 4 14.5v-10A2.5 2.5 0 0 1 6.5 2Z"/><path d="M8 6h8"/><path d="M8 10h8"/><path d="M8 14h5"/></svg>
                    </div>
                    <h3>Faculty Workload Management</h3>
                    <p>Manage and monitor faculty teaching workload.</p>
                    <div class="module-action">
                        <a href="/admin/faculty-workload" class="module-btn secondary">Open</a>
                    </div>
                </article>

                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3>Manage Students</h3>
                    <p>Review registered student accounts and statuses.</p>
                    <div class="module-action">
                        <a href="/admin/students" class="module-btn secondary">Open</a>
                    </div>
                </article>

                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v15H6.5A2.5 2.5 0 0 1 4 14.5v-10A2.5 2.5 0 0 1 6.5 2Z"/><path d="M8 6h8"/><path d="M8 10h8"/><path d="M8 14h5"/></svg>
                    </div>
                    <h3>Manage Subjects</h3>
                    <p>Manage subjects and curriculum details.</p>
                    <div class="module-action">
                        <a href="/admin/subjects" class="module-btn secondary">Open</a>
                    </div>
                </article>

                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M9 13h.01"/><path d="M15 13h.01"/></svg>
                    </div>
                    <h3>Manage Classrooms</h3>
                    <p>Manage classrooms and laboratory resources.</p>
                    <div class="module-action">
                        <a href="/admin/classrooms" class="module-btn secondary">Open</a>
                    </div>
                </article>

                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 17a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"/><path d="M16 17v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="8" r="3"/></svg>
                    </div>
                    <h3>Notification Management</h3>
                    <p>Send notifications to faculty and students.</p>
                    <div class="module-action">
                        <a href="/admin/notifications" class="module-btn secondary">Open</a>
                    </div>
                </article>

                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <h3>Generate Timetable</h3>
                    <p>Generate timetable automatically.</p>
                    <div class="module-action">
                        <a href="/admin/timetable" class="module-btn">Generate</a>
                    </div>
                </article>

                <article class="module-card">
                    <div class="module-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5"/><path d="M20 19V9"/><path d="M12 19V3"/><path d="M4 19h16"/></svg>
                    </div>
                    <h3>Reports</h3>
                    <p>View timetable and management reports.</p>
                    <div class="module-action">
                        <a href="/admin/reports" class="module-btn secondary">Open</a>
                    </div>
                </article>
            </div>
        </section>

        <section class="summary-grid" aria-label="Dashboard summaries">
            <div class="summary-panel">
                <div class="panel-head">
                    <h3>Today's Timetable</h3>
                    <a href="/admin/timetable" class="panel-link">View Full Timetable</a>
                </div>

                <div class="timetable-list">
                    @foreach ($timetable as $slot)
                        <div class="timetable-item">
                            <div>
                                <span class="time-label">{{ $slot['time'] }}</span>
                                <div class="subject-name">{{ $slot['subject'] }}</div>
                                <div class="subject-meta">{{ $slot['meta'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="summary-panel">
                <div class="panel-head">
                    <h3>System Alerts</h3>
                    <a href="/admin/notifications" class="panel-link">View All Alerts</a>
                </div>

                <ul class="alert-list">
                    @php
                        $recentAlerts = \App\Models\Notification::latest()->take(3)->get();
                    @endphp
                    @forelse ($recentAlerts as $alert)
                        <li>
                            <span class="dot {{ $alert->priority == 'High' ? 'danger' : ($alert->priority == 'Medium' ? 'warning' : 'success') }}"></span>
                            <span class="text-truncate" style="max-width: 85%;">
                                <strong>{{ $alert->title }}:</strong> {{ \Illuminate\Support\Str::limit($alert->description, 45) }}
                            </span>
                        </li>
                    @empty
                        <li>
                            <span class="dot success"></span>
                            <span>No system alerts yet.</span>
                        </li>
                    @endforelse
                </ul>
            </div>
        </section>
    </div>
@endsection
