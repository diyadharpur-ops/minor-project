@extends('student.layout')

@section('content')
<style>
    .timetable-container {
        background-color: #ffffff;
        color: #000000;
        font-family: 'Times New Roman', Times, Georgia, serif;
        margin: 0 auto;
        padding: 24px;
        border: 1px solid #000000;
        border-radius: 4px;
    }

    .official-header {
        text-align: center;
        margin-bottom: 16px;
        border-bottom: 1.5px solid #000000;
        padding-bottom: 12px;
    }

    .official-header .college-name {
        font-weight: 900;
        font-size: 20px;
        margin: 0 0 4px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .official-header .timetable-title {
        font-size: 15px;
        font-weight: 700;
        margin: 0 0 4px;
        text-transform: uppercase;
    }

    .official-header .department-name {
        font-size: 14px;
        font-weight: 700;
        margin: 0;
        text-transform: uppercase;
    }

    .meta-strip {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 12px;
        line-height: 1.4;
    }

    .meta-strip p {
        margin: 2px 0;
    }

    .timetable-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-bottom: 16px;
        background: #ffffff;
    }

    .timetable-table th, 
    .timetable-table td {
        border: 1px solid #000000 !important;
        padding: 6px 4px;
        text-align: center;
        vertical-align: middle;
        color: #000000;
        font-size: 12px;
        line-height: 1.25;
        word-wrap: break-word;
    }

    .timetable-table th {
        font-weight: 800;
        text-transform: uppercase;
        background-color: #ffffff;
        padding: 8px 4px;
    }

    .recess-row td {
        background-color: #f2f2f2 !important;
        font-weight: 800;
        letter-spacing: 2.5px;
        text-align: center;
        padding: 5px;
        font-size: 12px;
        text-transform: uppercase;
    }

    .session-code {
        font-weight: 800;
        font-size: 12.5px;
        color: #000000;
        margin-bottom: 2px;
    }

    .session-code sup {
        font-size: 11px;
        font-weight: 900;
    }

    .session-faculty {
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .session-location {
        font-size: 10.5px;
        font-weight: 600;
    }

    .session-type {
        font-size: 10px;
        font-style: italic;
        color: #333333;
    }

    .batch-grid-cell {
        display: flex;
        flex-direction: column;
        gap: 3px;
        text-align: left;
        padding: 2px 4px;
        font-size: 11px;
    }

    .batch-entry-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        border-bottom: 1px dotted #888888;
        padding-bottom: 2px;
    }

    .batch-entry-row:last-child {
        border-bottom: none;
    }

    .batch-tag {
        font-weight: 800;
        margin-right: 4px;
    }

    .timetable-bottom-info {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        margin-top: 14px;
        font-size: 11.5px;
        line-height: 1.4;
    }

    .info-legend p {
        margin: 3px 0;
        font-weight: 600;
    }

    .subject-reference-wrapper {
        margin-top: 16px;
        border-top: 1px solid #000000;
        padding-top: 12px;
    }

    .subject-reference-title {
        font-weight: 800;
        font-size: 12px;
        text-transform: uppercase;
        margin-bottom: 6px;
        text-align: left;
    }

    .subject-summary-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        margin-bottom: 16px;
    }

    .subject-summary-table th,
    .subject-summary-table td {
        border: 1px solid #000000 !important;
        padding: 4px 6px;
        text-align: left;
        vertical-align: middle;
        color: #000000;
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
        margin-top: 35px;
        padding-top: 10px;
        text-align: center;
        font-weight: 700;
        font-size: 12px;
    }

    .signature-block {
        width: 28%;
    }

    .signature-line {
        border-top: 1px solid #000000;
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
            font-size: 10pt !important;
        }

        .sidebar, .actions-bar, header, nav, button {
            display: none !important;
        }

        .content-pane {
            padding: 0 !important;
            margin: 0 !important;
        }

        .timetable-container {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }

        .timetable-table {
            page-break-inside: avoid !important;
        }
    }
</style>

<div class="actions-bar d-print-none">
    <button class="button" onclick="window.print()" style="padding: 9px 18px; font-size: 13px;">
        Print Timetable
    </button>
</div>

@if (!empty($timetable['sessions']))
    @php
        $tableDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        $slotRows = [
            ['type' => 'slot', 'slot_key' => '10:30-11:30', 'label' => '10:30 AM – 11:30 AM'],
            ['type' => 'slot', 'slot_key' => '11:30-12:30', 'label' => '11:30 AM – 12:30 PM'],
            ['type' => 'recess', 'name' => 'RECESS - 1', 'label' => 'R E C E S S - 1 &nbsp; ( 12:30 PM TO 01:00 PM )'],
            ['type' => 'slot', 'slot_key' => '01:00-02:00', 'label' => '01:00 PM – 02:00 PM'],
            ['type' => 'slot', 'slot_key' => '02:00-03:00', 'label' => '02:00 PM – 03:00 PM'],
            ['type' => 'recess', 'name' => 'RECESS - 2', 'label' => 'R E C E S S - 2 &nbsp; ( 03:00 PM TO 03:10 PM )'],
            ['type' => 'slot', 'slot_key' => '03:10-04:10', 'label' => '03:10 PM – 04:10 PM'],
            ['type' => 'slot', 'slot_key' => '04:10-05:10', 'label' => '04:10 PM – 05:10 PM'],
        ];

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

        foreach ($tableDays as $day) {
            for ($i = 0; $i < count($teachingSlotKeys); $i++) {
                $currKey = $teachingSlotKeys[$i];
                if ($skip[$day][$currKey]) continue;

                $sessions = $grid[$day][$currKey];
                $hasTwoHour = $sessions->contains(fn($s) => ((int)($s['duration'] ?? 1)) >= 2);
                if ($hasTwoHour && ($i + 1 < count($teachingSlotKeys))) {
                    if (in_array($i, [0, 2, 4], true)) {
                        $rowspans[$day][$currKey] = 2;
                        $skip[$day][$teachingSlotKeys[$i + 1]] = true;
                    }
                }
            }
        }

        $displayClassName = $timetable['class_name'] ?? ($semester . $division);
    @endphp

    <div class="timetable-container">
        <div class="official-header">
            <div class="college-name">K. D. POLYTECHNIC, PATAN</div>
            <div class="timetable-title">Time Table (Term: {{ $timetable['term_code'] ?? '261' }})</div>
            <div class="department-name">{{ $timetable['dept_full_name'] ?? $deptFullName }}</div>
        </div>

        <div class="meta-strip">
            <div>
                <p><strong>Class :</strong> {{ $displayClassName }}</p>
                <p><strong>Semester :</strong> {{ $timetable['semester'] ?? $semester }} &nbsp;&nbsp;&nbsp; <strong>Division :</strong> {{ $timetable['division'] ?? $division }}</p>
                <p><strong>Academic Year :</strong> {{ $timetable['academic_year'] ?? '2026-2027' }}</p>
            </div>
            <div style="text-align: right;">
                <p><strong>Term Dates :</strong> {{ $timetable['term_dates'] ?? '23/06/2026 to 07/11/2026' }}</p>
                <p><strong>w.e.f. :</strong> {{ $timetable['wef_date'] ?? '23/06/2026' }}</p>
                <p><strong>Department :</strong> {{ $deptDisplayName ?? 'Computer Engineering' }}</p>
            </div>
        </div>

        <table class="timetable-table">
            <thead>
                <tr>
                    <th style="width: 13%;">TIME</th>
                    @foreach ($tableDays as $day)
                        <th style="width: 14.5%;">{{ strtoupper($day) }}</th>
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
                        @php $slotKey = $row['slot_key']; @endphp
                        <tr>
                            <td><strong>{{ $row['label'] }}</strong></td>
                            @foreach ($tableDays as $day)
                                @if (!empty($skip[$day][$slotKey]))
                                    @continue
                                @endif

                                @php
                                    $cellSessions = $grid[$day][$slotKey] ?? collect();
                                    $rs = $rowspans[$day][$slotKey] ?? 1;
                                @endphp

                                <td rowspan="{{ $rs }}">
                                    @if ($cellSessions->isEmpty())
                                        <span style="color: #666;">-</span>
                                    @elseif ($cellSessions->count() === 1)
                                        @php
                                            $s = $cellSessions->first();
                                            $isTut = strcasecmp($s['type'] ?? '', 'Tutorial') === 0;
                                            $code = $s['subject_code'] ?: $s['subject'];
                                        @endphp
                                        <div>
                                            <div class="session-code">
                                                {{ strtoupper($code) }}@if($isTut)<sup>^</sup>@endif
                                            </div>
                                            @if(!empty($s['faculty']))
                                                <div class="session-faculty">{{ $s['faculty'] }}</div>
                                            @endif
                                            @if(!empty($s['room']))
                                                <div class="session-location">{{ $s['room'] }}</div>
                                            @endif
                                            <div class="session-type">
                                                ({{ strtoupper($s['type']) }}{{ !empty($s['batch']) ? ' - '.strtoupper($s['batch']) : '' }})
                                            </div>
                                        </div>
                                    @else
                                        <div class="batch-grid-cell">
                                            @foreach ($cellSessions->sortBy('batch') as $s)
                                                @php
                                                    $isTut = strcasecmp($s['type'] ?? '', 'Tutorial') === 0;
                                                    $code = $s['subject_code'] ?: $s['subject'];
                                                @endphp
                                                <div class="batch-entry-row">
                                                    <div>
                                                        <span class="batch-tag">{{ $s['batch'] ?: 'Batch' }}:</span>
                                                        <span>{{ $code }}@if($isTut)<sup>^</sup>@endif</span>
                                                        <span>({{ $s['faculty_initials'] ?: $s['faculty'] }})</span>
                                                    </div>
                                                    <div>{{ $s['room_number'] ?: $s['room'] }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="timetable-bottom-info">
            <div class="info-legend">
                <p><strong>Recess-1 :</strong> 12:30 PM – 01:00 PM</p>
                <p><strong>Recess-2 :</strong> 03:00 PM – 03:10 PM</p>
                <p><strong>Tutorial Legend :</strong> <code>^ = Tutorial</code></p>
                <p><strong>Skill Based Training :</strong> {{ $timetable['sbt_dates'] ?? '21/09/2026 to 26/09/2026' }}</p>
            </div>
            <div class="info-legend" style="text-align: right;">
                <p><strong>Generated Date :</strong> {{ date('d-m-Y') }}</p>
                <p><strong>Institute Code :</strong> 626 (K. D. Polytechnic, Patan)</p>
            </div>
        </div>

        @if(!empty($classSubjects) && $classSubjects->isNotEmpty())
            <div class="subject-reference-wrapper">
                <div class="subject-reference-title">Subject & Faculty Details</div>
                <table class="subject-summary-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">Sr.</th>
                            <th style="width: 15%;">Subject Code</th>
                            <th style="width: 35%;">Subject Name</th>
                            <th style="width: 15%;">Scheme (L-T-P)</th>
                            <th style="width: 10%;">Credits</th>
                            <th style="width: 20%;">Allocated Faculty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classSubjects as $idx => $sub)
                            @php
                                $lec = (int)($sub->lecture_credit ?? 0);
                                $tut = (int)($sub->tutorial_credit ?? 0);
                                $lab = (int)($sub->lab_credit ?? 0);
                                $totalCr = $sub->credit ?? ($lec + $tut + $lab);
                            @endphp
                            <tr>
                                <td style="text-align: center;">{{ $idx + 1 }}</td>
                                <td style="font-weight: 700;">{{ $sub->subject_code ?: '-' }}</td>
                                <td>{{ $sub->name }}</td>
                                <td style="text-align: center;">{{ $lec }} - {{ $tut }} - {{ $lab * 2 }}</td>
                                <td style="text-align: center; font-weight: 700;">{{ $totalCr }}</td>
                                <td>{{ $sub->faculty ? $sub->faculty->name : 'Department Faculty' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="signature-strip">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div>Time Table Coordinator</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div>Principal</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div>Head of Department</div>
            </div>
        </div>
    </div>
@else
    <div class="card" style="text-align: center; padding: 40px;">
        <h3>No Timetable Available</h3>
        <p>The timetable for your class has not been published yet.</p>
    </div>
@endif
@endsection
