@extends('admin.layout')

@section('title', 'College Timetable - K. D. Polytechnic, Patan')

@section('content')
<div class="page-header d-print-none">
    <div>
        <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0 0 4px;">College Timetable Management</h1>
        <p style="color: #64748b; margin: 0;">Official Timetable View & Print System (K. D. Polytechnic, Patan)</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="/admin/timetable/builder" class="btn" style="background-color: #10b981; color: #fff; padding: 8px 16px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Auto Generate Timetable
        </a>
    </div>
</div>

<div class="page-card d-print-none" style="margin-bottom: 20px; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    @if(session('status'))
        <div class="alert alert-success" style="background-color: #d1e7dd; color: #0f5132; padding: 12px 16px; border-radius: 6px; border: 1px solid #badbcc; font-weight: 600; margin-bottom: 15px;">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="background-color: #f8d7da; color: #842029; padding: 12px 16px; border-radius: 6px; border: 1px solid #f5c2c7; font-weight: 600; margin-bottom: 15px;">{{ session('error') }}</div>
    @endif
    @if(session('faculty_allocation_warnings'))
        <div class="alert" style="background-color: #fff7ed; color: #9a4d00; padding: 12px 16px; border-radius: 6px; border: 1px solid #fed7aa; margin-bottom: 15px;">
            @foreach(session('faculty_allocation_warnings') as $warning)
                <div>{{ $warning }}</div>
            @endforeach
        </div>
    @endif
    
    <form method="POST" action="/admin/timetable" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; align-items: flex-end;">
        @csrf
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">Department</label>
            <select name="department_id" class="entry-select" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" required>
                <option value="">Select Department</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ old('department_id', request('department_id')) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">Semester</label>
            <select name="semester" id="semesterSelect" class="entry-select" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" required onchange="updateDivisions()">
                <option value="">Select Semester</option>
                @foreach($semesters ?? [] as $sem)
                    <option value="{{ $sem }}" {{ old('semester', $semester) == $sem ? 'selected' : '' }}>Semester {{ $sem }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">Division</label>
            <select name="division" id="divisionSelect" class="entry-select" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" required>
                <option value="">Select Division</option>
                @foreach($divisions ?? [] as $div)
                    <option value="{{ $div }}" {{ old('division', $division) == $div ? 'selected' : '' }}>{{ $div }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">Term</label>
            <select name="term" class="entry-select" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" required>
                <option value="Odd" {{ old('term', $term) == 'Odd' ? 'selected' : '' }}>Odd</option>
                <option value="Even" {{ old('term', $term) == 'Even' ? 'selected' : '' }}>Even</option>
            </select>
        </div>
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">Term Code</label>
            <input type="text" name="term_code" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" value="{{ old('term_code', $termCode) }}">
        </div>
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">Academic Year</label>
            <input type="text" name="academic_year" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" value="{{ old('academic_year', $academicYear) }}" required>
        </div>
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">Term Dates</label>
            <input type="text" name="term_dates" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" value="{{ old('term_dates', $termDates) }}">
        </div>
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">w.e.f.</label>
            <input type="text" name="wef_date" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" value="{{ old('wef_date', $wefDate) }}">
        </div>
        <div>
            <label style="font-weight: 600; font-size: 13px; color: #334155; display: block; margin-bottom: 4px;">Skill Based Training</label>
            <input type="text" name="sbt_dates" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" value="{{ old('sbt_dates', $sbtDates) }}" placeholder="e.g. 21/09/2026 to 26/09/2026">
        </div>
        <div style="min-width: 120px;">
            <button type="submit" class="btn" style="width: 100%; padding: 9px 16px; background-color: #2563eb; color: #fff; font-weight: 600; border-radius: 6px; border: none; cursor: pointer;">
                View Timetable
            </button>
        </div>
    </form>
</div>

@if (!empty($timetable['sessions']))
    @php
        $tableDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        $slotRows = [
            [
                'type' => 'slot',
                'slot_key' => '10:30-11:30',
                'label' => '10:30 AM – 11:30 AM',
            ],
            [
                'type' => 'slot',
                'slot_key' => '11:30-12:30',
                'label' => '11:30 AM – 12:30 PM',
            ],
            [
                'type' => 'recess',
                'name' => 'RECESS - 1',
                'timing' => '12:30 PM – 01:00 PM',
                'label' => 'R E C E S S - 1 &nbsp; ( 12:30 PM TO 01:00 PM )',
            ],
            [
                'type' => 'slot',
                'slot_key' => '01:00-02:00',
                'label' => '01:00 PM – 02:00 PM',
            ],
            [
                'type' => 'slot',
                'slot_key' => '02:00-03:00',
                'label' => '02:00 PM – 03:00 PM',
            ],
            [
                'type' => 'recess',
                'name' => 'RECESS - 2',
                'timing' => '03:00 PM – 03:10 PM',
                'label' => 'R E C E S S - 2 &nbsp; ( 03:00 PM TO 03:10 PM )',
            ],
            [
                'type' => 'slot',
                'slot_key' => '03:10-04:10',
                'label' => '03:10 PM – 04:10 PM',
            ],
            [
                'type' => 'slot',
                'slot_key' => '04:10-05:10',
                'label' => '04:10 PM – 05:10 PM',
            ],
        ];

        // Slot normalizer to map any database representation to canonical slot_key
        $normalizeSlot = function($slotStr) {
            $clean = preg_replace('/[^0-9]/', '', (string)$slotStr);
            if (str_starts_with($clean, '1030')) return '10:30-11:30';
            if (str_starts_with($clean, '1130')) return '11:30-12:30';
            if (str_starts_with($clean, '0100') || str_starts_with($clean, '100')) return '01:00-02:00';
            if (str_starts_with($clean, '0200') || str_starts_with($clean, '200')) return '02:00-03:00';
            if (str_starts_with($clean, '0310') || str_starts_with($clean, '310')) return '03:10-04:10';
            if (str_starts_with($clean, '0410') || str_starts_with($clean, '410')) return '04:10-05:10';
            return $slotStr;
        };

        // Initialize 2D collection grid
        $grid = [];
        $skip = [];
        $rowspans = [];
        $teachingSlotKeys = ['10:30-11:30', '11:30-12:30', '01:00-02:00', '02:00-03:00', '03:10-04:10', '04:10-05:10'];

        foreach ($tableDays as $day) {
            foreach ($teachingSlotKeys as $sKey) {
                $grid[$day][$sKey] = collect();
                $skip[$day][$sKey] = false;
                $rowspans[$day][$sKey] = 1;
            }
        }

        foreach ($timetable['sessions'] as $session) {
            $sDay = ucfirst(strtolower((string)$session['day']));
            $normSlot = $normalizeSlot($session['time_slot']);
            if (isset($grid[$sDay][$normSlot])) {
                $grid[$sDay][$normSlot]->push($session);
            }
        }

        // Calculate vertical merges for 2-hour sessions (including Practical / Lab slots)
        foreach ($tableDays as $day) {
            for ($i = 0; $i < count($teachingSlotKeys); $i++) {
                $currKey = $teachingSlotKeys[$i];
                if ($skip[$day][$currKey]) {
                    continue;
                }
                $sessions = $grid[$day][$currKey];
                $hasTwoHour = $sessions->contains(fn($s) => ((int)($s['duration'] ?? 1)) >= 2 || strcasecmp($s['type'] ?? '', 'Practical') === 0);
                if ($hasTwoHour && ($i + 1 < count($teachingSlotKeys))) {
                    // Only merge within valid blocks (0->1, 2->3, 4->5) without crossing recess
                    if (in_array($i, [0, 2, 4], true)) {
                        $rowspans[$day][$currKey] = 2;
                        $nextKey = $teachingSlotKeys[$i + 1];
                        $skip[$day][$nextKey] = true;
                    }
                }
            }
        }

        $displayClassName = $classNameFormatted ?: (($timetable['semester'] ?? $semester) . ($timetable['division'] ?? $division));
    @endphp

    <style>
        /* College Timetable Styles matching Image 2 */
        .timetable-container {
            background-color: #ffffff;
            color: #1e293b;
            font-family: 'Times New Roman', Times, Georgia, serif;
            margin: 0 auto 30px;
            padding: 24px 30px;
            max-width: 100%;
            border: 1px solid #334155;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        .official-header {
            text-align: center;
            margin-bottom: 16px;
            border-bottom: 1.5px solid #334155;
            padding-bottom: 12px;
        }

        .official-header .college-name {
            font-weight: 900;
            font-size: 22px;
            margin: 0 0 4px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #1e293b;
        }

        .official-header .timetable-title {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 4px;
            text-transform: uppercase;
            color: #1e293b;
        }

        .official-header .department-name {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
            color: #1e293b;
        }

        .meta-strip {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1e293b;
            line-height: 1.4;
        }

        .meta-left {
            text-align: left;
        }

        .meta-right {
            text-align: right;
        }

        .meta-strip p {
            margin: 2px 0;
        }

        /* Timetable Grid */
        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 16px;
            background: #ffffff;
        }

        .timetable-table th, 
        .timetable-table td {
            border: 1px solid #334155 !important;
            padding: 6px 4px;
            text-align: center;
            vertical-align: middle;
            color: #1e293b;
            font-size: 12.5px;
            line-height: 1.25;
            word-wrap: break-word;
        }

        .timetable-table th {
            font-weight: 800;
            text-transform: uppercase;
            background-color: #dbeafe;
            font-size: 13px;
            padding: 8px 4px;
        }

        .timetable-table th.time-col {
            width: 13%;
            background-color: #dbeafe;
        }

        .timetable-table th.day-col {
            width: 14.5%;
        }

        .time-cell {
            font-weight: 700;
            font-size: 12px;
            white-space: normal;
        }

        .recess-row td {
            background-color: #dbeafe !important;
            font-weight: 800;
            letter-spacing: 2.5px;
            text-align: center;
            padding: 5px;
            font-size: 12.5px;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Cell content styling */
        .session-block {
            padding: 2px 0;
        }

        .session-sub-name {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 2px;
            color: #1e293b;
        }

        .session-faculty {
            font-size: 11.5px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .session-location {
            font-size: 11px;
            font-weight: 600;
            color: #1e293b;
        }

        .session-type {
            font-size: 10px;
            font-style: italic;
            color: #1e293b;
        }

        /* Footer & Reference Table */
        .timetable-bottom-info {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 14px;
            font-size: 12px;
            line-height: 1.4;
        }

        .info-legend {
            flex: 1;
        }

        .info-legend p {
            margin: 3px 0;
            font-weight: 600;
        }

        .subject-reference-wrapper {
            margin-top: 16px;
            border-top: 1px solid #334155;
            padding-top: 12px;
        }

        .subject-reference-title {
            font-weight: 800;
            font-size: 12.5px;
            text-transform: uppercase;
            margin-bottom: 6px;
            text-align: left;
        }

        .subject-summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
            margin-bottom: 16px;
        }

        .subject-summary-table th,
        .subject-summary-table td {
            border: 1px solid #334155 !important;
            padding: 4px 6px;
            text-align: left;
            vertical-align: middle;
            color: #1e293b;
        }

        .subject-summary-table th {
            background-color: #f7f7f7;
            font-weight: 800;
            text-align: center;
        }

        .signature-strip {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 45px;
            padding-top: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 12.5px;
        }

        .signature-block {
            width: 28%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #334155;
            margin-bottom: 6px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }

        .actions-bar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-bottom: 16px;
        }

        /* Print Media Setup for A4 Landscape matching Image 2 */
        @page {
            size: A4 landscape;
            margin: 8mm 6mm 6mm 6mm;
        }

        @media print {
            body, html {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 10.5pt !important;
                width: 100% !important;
            }

            .d-print-none,
            .page-header,
            .page-card,
            .sidebar,
            .topbar,
            .actions-bar,
            .alert,
            nav,
            header,
            button {
                display: none !important;
            }

            .main-content, 
            .content, 
            .container, 
            .app-container {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                border: none !important;
            }

            .timetable-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .timetable-table {
                page-break-inside: avoid !important;
            }

            .timetable-table th, 
            .timetable-table td {
                border: 1px solid #334155 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .recess-row td {
                background-color: #dbeafe !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .subject-summary-table {
                page-break-inside: avoid !important;
            }

            .signature-strip {
                page-break-inside: avoid !important;
                margin-top: 35px !important;
            }

        }
    </style>

    <div class="actions-bar d-print-none">
        <button class="btn" onclick="window.print()" style="background-color: #1e293b; color: #ffffff; padding: 9px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px;">
            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Timetable
        </button>
        <button class="btn" onclick="window.print()" style="background-color: #0284c7; color: #ffffff; padding: 9px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px;">
            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Download PDF
        </button>
    </div>

    <div class="timetable-container" id="printableTimetable">
        <!-- Official College Header matching Image 2 -->
        <div class="official-header">
            <div class="college-name">K. D. POLYTECHNIC, PATAN</div>
            <div class="timetable-title">Time Table (Term Number: {{ $timetable['term_code'] ?: ($timetable['term'] ?? $term) }})</div>
            <div class="department-name">{{ $timetable['dept_full_name'] ?? $deptFullName }}</div>
        </div>

        <!-- Meta Details Row -->
        <div class="meta-strip">
            <div class="meta-left">
                <p><strong>Class :</strong> {{ $displayClassName }}</p>
                <p><strong>Semester :</strong> {{ $timetable['semester'] ?? $semester }} &nbsp;&nbsp;&nbsp; <strong>Division :</strong> {{ $timetable['division'] ?? $division }}</p>
                <p><strong>Academic Year :</strong> {{ $timetable['academic_year'] ?? $academicYear }}</p>
            </div>
            <div class="meta-right">
                <p><strong>Term Number :</strong> {{ $timetable['term_code'] ?: ($timetable['term'] ?? $term) }}</p>
                <p><strong>Term Dates :</strong> {{ $timetable['term_dates'] ?: 'Not recorded' }}</p>
                <p><strong>w.e.f. :</strong> {{ $timetable['wef_date'] ?: 'Not recorded' }}</p>
                <p><strong>Department :</strong> {{ $deptName }}</p>
            </div>
        </div>

        <!-- Structured Timetable Table -->
        <table class="timetable-table">
            <thead>
                <tr>
                    <th class="time-col">TIME</th>
                    @foreach ($tableDays as $day)
                        <th class="day-col">{{ strtoupper($day) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($slotRows as $row)
                    @if ($row['type'] === 'recess')
                        <tr class="recess-row">
                            <td colspan="{{ count($tableDays) + 1 }}">{!! $row['label'] !!}</td>
                        </tr>
                    @else
                        @php
                            $slotKey = $row['slot_key'];
                        @endphp
                        <tr>
                            <td class="time-cell"><strong>{{ $row['label'] }}</strong></td>
                            @foreach ($tableDays as $day)
                                @if (!empty($skip[$day][$slotKey]))
                                    {{-- Spanned by previous row --}}
                                    @continue
                                @endif

                                @php
                                    $cellSessions = $grid[$day][$slotKey] ?? collect();
                                    $rs = $rowspans[$day][$slotKey] ?? 1;

                                @endphp

                                <td data-day="{{ strtolower($day) }}" data-time-slot="{{ $slotKey }}" rowspan="{{ $rs }}">
                                    @if ($cellSessions->isEmpty())
                                        <span style="color: #666;">-</span>
                                    @else
                                        @foreach ($cellSessions as $session)
                                            @php
                                                $sessionType = strtolower((string) ($session['type'] ?? ''));
                                                $sessionLabel = str_contains($sessionType, 'practical') || str_contains($sessionType, 'lab')
                                                    ? 'PR'
                                                    : (str_contains($sessionType, 'tutorial') ? 'TUT' : 'TH');
                                                $sessionLocation = preg_replace(
                                                    '/^(Classroom|Lab):\s*/i',
                                                    '',
                                                    (string) ($session['room_number'] ?: $session['room'])
                                                );
                                            @endphp
                                            <div class="session-block">
                                                <div class="session-sub-name">{{ $session['subject'] ?: 'Subject unavailable' }}</div>
                                                <div class="session-faculty">{{ $session['faculty'] ?: 'Faculty unavailable' }}</div>
                                                <div class="session-location">{{ $sessionLocation ?: 'Location unavailable' }}</div>
                                                <div class="session-type">({{ $sessionLabel }})</div>
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <!-- Breaks and Additional Information (Section 5) -->
        <div class="timetable-bottom-info">
            <div class="info-legend">
                <p><strong>Recess-1 :</strong> 12:30 PM – 01:00 PM</p>
                <p><strong>Recess-2 :</strong> 03:00 PM – 03:10 PM</p>
                <p><strong>Session Types :</strong> (TH) Theory, (PR) Practical, (TUT) Tutorial</p>
                @if(!empty($sbtDates))
                    <p><strong>Skill Based Training :</strong> {{ $sbtDates }}</p>
                @endif
            </div>
            <div class="info-legend" style="text-align: right;">
                <p><strong>Generated Date :</strong> {{ date('d-m-Y') }}</p>
                <p><strong>Institute Code :</strong> 626 (K. D. Polytechnic, Patan)</p>
                <p><strong>System :</strong> Laravel College Timetable Management System</p>
            </div>
        </div>

        <!-- Subject and allocation details for the selected class -->
        @if($classSubjects->isNotEmpty())
            <div class="subject-reference-wrapper">
                <div class="subject-reference-title">SUBJECT &amp; FACULTY DETAILS</div>
                <table class="subject-summary-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">Sr. No.</th>
                            <th style="width: 14%;">Subject Code</th>
                            <th style="width: 25%;">Subject Name</th>
                            <th style="width: 22%;">Faculty Name</th>
                            <th style="width: 15%;">Location</th>
                            <th style="width: 11%;">Subject Type</th>
                            <th style="width: 8%;">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $detailRowNumber = 0;
                        @endphp
                        @foreach ($classSubjects as $sub)
                            @php
                                $allocationDetails = $sub->allocated_details;
                            @endphp
                            @foreach ($allocationDetails as $detail)
                                <tr>
                                    <td style="text-align: center;">{{ ++$detailRowNumber }}</td>
                                    <td style="font-weight: 700;">{{ $sub->subject_code ?: '—' }}</td>
                                    <td>{{ $sub->name }}</td>
                                    <td>{{ $detail['faculty'] ?: '—' }}</td>
                                    <td>{{ $detail['location'] ?: '—' }}</td>
                                    <td>{{ $sub->subject_type ?: '—' }}</td>
                                    <td style="text-align: center;">{{ (int) $sub->duration_for_class }} h/week</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Formal Signature Section matching Image 2 -->
        <div class="signature-strip">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div>Time Table Coordinator</div>
                <div style="font-size: 11px; font-weight: 600;">{{ $deptFullName }}</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div>Principal</div>
                <div style="font-size: 11px; font-weight: 600;">K. D. Polytechnic, Patan</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div>Head of Department</div>
                <div style="font-size: 11px; font-weight: 600;">{{ $deptFullName }}</div>
            </div>
        </div>
    </div>
@elseif (request()->isMethod('post') || (request()->has('department_id') && request()->has('semester') && request()->has('division')))
    <div class="page-card" style="background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center;">
        <h3 style="color: #334155; margin-bottom: 8px;">No Timetable Entries Found</h3>
        <p style="color: #64748b; margin-bottom: 20px;">No timetable entries were found for the selected department, semester, and division criteria.</p>
        <a href="/admin/timetable/builder" class="btn" style="background-color: #10b981; color: #fff; padding: 10px 20px; border-radius: 6px; font-weight: 600; text-decoration: none;">
            Run Automated Timetable Generator
        </a>
    </div>
@endif

<script>
    const divisionsMap = @json($divisionsMap ?? []);
    const oldDivision = "{{ request('division', 'A') }}";

    function updateDivisions() {
        const semesterSelect = document.getElementById('semesterSelect');
        const divisionSelect = document.getElementById('divisionSelect');
        if (!semesterSelect || !divisionSelect) return;

        const selectedSemester = semesterSelect.value;
        const currentSelection = divisionSelect.value || oldDivision;
        divisionSelect.innerHTML = '<option value="">Select Division</option>';

        let optionsList = [];
        if (selectedSemester && divisionsMap[selectedSemester]) {
            optionsList = divisionsMap[selectedSemester];
        } else {
            optionsList = Object.values(divisionsMap).flat().filter((v, i, a) => a.indexOf(v) === i);
        }

        optionsList.forEach(function(division) {
            const option = document.createElement('option');
            option.value = division;
            option.textContent = division;
            if (division === currentSelection) {
                option.selected = true;
            }
            divisionSelect.appendChild(option);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateDivisions();
    });
</script>
@endsection
