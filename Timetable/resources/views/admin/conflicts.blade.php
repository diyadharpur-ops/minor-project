@extends('admin.layout')

@section('title', 'Conflict Detection')

@section('content')
    <style>
        .conflict-shell {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .conflict-header {
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
            gap: 12px;
        }

        .scan-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid #2563eb;
            background: #2563eb;
            color: white;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .scan-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 18px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 18px;
            padding: 18px 18px 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .summary-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(37, 99, 235, 0.12);
            color: #1d4ed8;
            flex-shrink: 0;
        }

        .summary-card .card-content {
            min-width: 0;
            flex: 1;
        }

        .summary-title {
            display: block;
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .summary-value {
            display: block;
            font-size: 1.9rem;
            color: #0f172a;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 4px;
            letter-spacing: -0.03em;
        }

        .summary-subtext {
            display: block;
            font-size: 0.74rem;
            color: #94a3b8;
        }

        .table-panel {
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 20px;
            padding: 22px 22px 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .table-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.25rem;
            letter-spacing: -0.02em;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef4ff;
            color: #1d4ed8;
            border: 1px solid #dfe9ff;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid #eef2f7;
            font-size: 0.94rem;
        }

        th {
            color: #475569;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        td {
            color: #334155;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 0.73rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .badge.faculty { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #b91c1c; }
        .badge.classroom { background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2); color: #1d4ed8; }
        .badge.lab { background: rgba(168, 85, 247, 0.1); border-color: rgba(168, 85, 247, 0.2); color: #7c3aed; }
        .badge.subject { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: #047857; }

        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 180px;
            padding: 20px;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            background: #f8fafc;
            color: #64748b;
            font-size: 1rem;
            text-align: center;
        }

        @media (max-width: 1200px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(180px, 1fr));
            }
        }

        @media (max-width: 900px) {
            .main-content {
                padding: 18px !important;
            }

            .conflict-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="conflict-shell">
        <header class="conflict-header">
            <div class="header-copy">
                <h1>Conflict Detection</h1>
                <p>Scan the live timetable data for faculty, classroom, lab, and duplicate subject conflicts.</p>
            </div>

            <div class="header-actions">
                <form method="POST" action="/admin/conflicts" style="margin: 0;">
                    @csrf
                    <button type="submit" class="scan-btn">Scan for Conflicts</button>
                </form>
            </div>
        </header>

        <section class="summary-grid" aria-label="Conflict overview">
            @foreach ($summaryCards as $card)
                <div class="summary-card">
                    <div class="summary-icon">
                        @switch($card['icon'])
                            @case('faculty')
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9.5" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                @break
                            @case('classroom')
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 21h18"/>
                                    <path d="M5 21V7l7-4 7 4v14"/>
                                    <path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M9 13h.01"/><path d="M15 13h.01"/>
                                </svg>
                                @break
                            @case('lab')
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v15H6.5A2.5 2.5 0 0 1 4 14.5v-10A2.5 2.5 0 0 1 6.5 2Z"/>
                                    <path d="M8 6h8"/><path d="M8 10h8"/><path d="M8 14h5"/>
                                </svg>
                                @break
                            @default
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v15H6.5A2.5 2.5 0 0 1 4 14.5v-10A2.5 2.5 0 0 1 6.5 2Z"/>
                                    <path d="M8 6h8"/><path d="M8 10h8"/><path d="M8 14h5"/>
                                </svg>
                        @endswitch
                    </div>
                    <div class="card-content">
                        <span class="summary-title">{{ $card['title'] }}</span>
                        <strong class="summary-value">{{ $card['value'] }}</strong>
                        <span class="summary-subtext">{{ $card['subtext'] }}</span>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="table-panel" aria-label="Conflict list">
            <div class="table-header">
                <h2>Detected Conflicts</h2>
                <span class="status-chip">{{ $conflicts->count() }} issues found</span>
            </div>

            @if ($conflicts->isEmpty())
                <div class="empty-state">
                    No conflicts detected in the current timetable data.
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Subject</th>
                                <th>Faculty</th>
                                <th>Room</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($conflicts as $conflict)
                                <tr>
                                    <td><span class="badge {{ $conflict['type_class'] ?? 'faculty' }}">{{ $conflict['type'] }}</span></td>
                                    <td>{{ $conflict['day'] }}</td>
                                    <td>{{ $conflict['time'] }}</td>
                                    <td>{{ $conflict['subject'] }}</td>
                                    <td>{{ $conflict['faculty'] }}</td>
                                    <td>{{ $conflict['room'] }}</td>
                                    <td>{{ $conflict['details'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
