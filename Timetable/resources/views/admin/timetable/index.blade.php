@extends('admin.layout')

@section('title', 'View Timetable')

@section('content')
    <div class="page-header">
        <div>
            <h1>View Timetable</h1>
            <p>Select the class details to view the generated timetable.</p>
        </div>
        <div>
            <a href="/admin/timetable/builder" class="btn">Timetable Builder</a>
        </div>
    </div>

    <div class="page-card d-print-none" style="margin-bottom: 20px;">
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        
        <form method="POST" action="/admin/timetable" style="display: flex; gap: 10px; align-items: flex-end;">
            @csrf
            <div style="flex: 1;">
                <label style="font-weight: bold; font-size: 14px;">Department</label>
                <select name="department_id" class="entry-select" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;" required>
                    <option value="">Select Department</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id', request('department_id')) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1;">
                <label style="font-weight: bold; font-size: 14px;">Semester</label>
                <select name="semester" class="entry-select" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;" required>
                    <option value="">Select Semester</option>
                    @foreach(['1','2','3','4','5','6','7','8'] as $sem)
                        <option value="{{ $sem }}" {{ old('semester', $semester) == $sem ? 'selected' : '' }}>Semester {{ $sem }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1;">
                <label style="font-weight: bold; font-size: 14px;">Division</label>
                <input type="text" name="division" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;" value="{{ old('division', $division) }}" required>
            </div>
            <div style="flex: 1;">
                <label style="font-weight: bold; font-size: 14px;">Term</label>
                <select name="term" class="entry-select" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;" required>
                    <option value="Odd" {{ old('term', $term) == 'Odd' ? 'selected' : '' }}>Odd</option>
                    <option value="Even" {{ old('term', $term) == 'Even' ? 'selected' : '' }}>Even</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label style="font-weight: bold; font-size: 14px;">Academic Year</label>
                <input type="text" name="academic_year" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;" value="{{ old('academic_year', $academicYear) }}" required>
            </div>
            <div>
                <button type="submit" class="btn">View Timetable</button>
            </div>
        </form>
    </div>

    @if (!empty($timetable['sessions']))
        @php
            $tableDays = $timetable['days'] ?? [];
            $tableTimeSlots = $timetable['time_slots'] ?? [];
            
            // Create a 2D array of grid
            $grid = [];
            $skip = [];
            foreach ($tableDays as $day) {
                foreach ($tableTimeSlots as $slot) {
                    $entry = collect($timetable['sessions'])->first(function($s) use ($day, $slot) {
                        return strcasecmp($s['day'], $day) === 0 && $s['time_slot'] === $slot;
                    });
                    $grid[$day][$slot] = $entry;
                    $skip[$day][$slot] = false;
                }
            }

            $rowspans = [];
            foreach ($tableDays as $day) {
                for ($i = 0; $i < count($tableTimeSlots); $i++) {
                    $slot = $tableTimeSlots[$i];
                    if ($skip[$day][$slot]) continue;
                    
                    $entry = $grid[$day][$slot];
                    $rowspan = 1;
                    
                    if ($entry && isset($entry['duration']) && $entry['duration'] > 1) {
                        $rowspan = $entry['duration'];
                        // Mark subsequent slots as skipped
                        for ($j = 1; $j < $rowspan; $j++) {
                            if (($i + $j) < count($tableTimeSlots)) {
                                $nextSlot = $tableTimeSlots[$i + $j];
                                $skip[$day][$nextSlot] = true;
                            }
                        }
                    }
                    $rowspans[$day][$slot] = $rowspan;
                }
            }
        @endphp

        <style>
            .timetable-wrapper {
                background-color: #fff;
                color: #000;
                font-family: 'Times New Roman', Times, serif;
                margin: 0 auto;
                padding: 20px;
                max-width: 100%;
                border: 1px solid #ccc;
                border-radius: 8px;
            }

            .timetable-header {
                text-align: center;
                margin-bottom: 20px;
                position: relative;
            }

            .timetable-header h2 {
                font-weight: bold;
                font-size: 24px;
                margin: 5px 0;
                text-transform: uppercase;
                color: #000;
            }

            .timetable-header h3 {
                font-size: 18px;
                font-weight: bold;
                margin: 5px 0;
                color: #000;
            }

            .timetable-header h4 {
                font-size: 16px;
                font-weight: bold;
                margin: 5px 0;
                color: #000;
            }

            .meta-info {
                display: flex;
                justify-content: space-between;
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 10px;
                text-align: left;
            }
            .meta-left p, .meta-right p {
                margin: 2px 0;
            }
            .meta-right { text-align: right; }

            .timetable-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            .timetable-table th, .timetable-table td {
                border: 1px solid #000 !important;
                padding: 10px;
                text-align: center;
                vertical-align: middle;
                font-size: 14px;
                color: #000;
            }

            .timetable-table th {
                font-weight: bold;
                text-transform: uppercase;
                background-color: #fff;
                width: 14.28%;
            }

            .subject-name {
                font-weight: bold;
                display: block;
                margin-bottom: 4px;
            }

            .faculty-name {
                display: block;
                margin-bottom: 4px;
            }

            .room-type {
                display: block;
                font-size: 12px;
            }

            .recess-row {
                background-color: #f2f2f2 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                font-weight: bold;
                letter-spacing: 2px;
            }

            .timetable-footer {
                display: flex;
                justify-content: space-between;
                margin-top: 50px;
                font-weight: bold;
                color: #000;
            }
            .footer-left p { margin: 2px 0; font-size: 13px; font-weight: normal; }

            .actions-bar {
                text-align: right;
                margin-bottom: 15px;
            }

            .d-print-none {
                /* used to hide things when printing */
            }

            @media print {
                body * {
                    visibility: hidden;
                }
                .timetable-wrapper, .timetable-wrapper * {
                    visibility: visible;
                }
                .timetable-wrapper {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    max-width: 100%;
                    border: none;
                    padding: 0;
                    margin: 0;
                }
                .d-print-none {
                    display: none !important;
                }
                @page {
                    size: A4 landscape;
                    margin: 10mm;
                }
                .timetable-table {
                    page-break-inside: avoid;
                }
            }
        </style>

        <div class="actions-bar d-print-none">
            <button class="btn" onclick="window.print()">Print Timetable</button>
            <button class="btn btn-muted" onclick="window.print()">Download PDF</button>
        </div>

        <div class="timetable-wrapper">
            <div class="timetable-header">
                <h2>K. D. POLYTECHNIC, PATAN</h2>
                <h3>Time Table (Term: {{ $timetable['term'] }})</h3>
                <h4>Department of {{ $deptName }}</h4>
            </div>

            <div class="meta-info">
                <div class="meta-left">
                    <p>Class : {{ $deptName }}</p>
                    <p>Semester : {{ $timetable['semester'] ?? request('semester') }}</p>
                    <p>Division : {{ $timetable['division'] ?? 'A' }}</p>
                    <p>Academic Year : {{ $timetable['academic_year'] ?? '' }}</p>
                </div>
                <div class="meta-right">
                    <p>Term Dates: _____________</p>
                    <p>Department of {{ $deptName }}</p>
                </div>
            </div>

            <table class="timetable-table">
                <thead>
                    <tr>
                        <th>TIME</th>
                        @foreach ($tableDays as $day)
                            <th>{{ strtoupper($day) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tableTimeSlots as $slot)
                        @if ($slot === '01:00-02:00')
                            <tr class="recess-row">
                                <td>12:30-01:00</td>
                                <td colspan="{{ count($tableDays) }}">RECESS - 1</td>
                            </tr>
                        @endif
                        @if ($slot === '03:10-04:10')
                            <tr class="recess-row">
                                <td>03:00-03:10</td>
                                <td colspan="{{ count($tableDays) }}">RECESS - 2</td>
                            </tr>
                        @endif

                        <tr>
                            <th>{{ $slot }}</th>
                            @foreach ($tableDays as $day)
                                @if ($skip[$day][$slot])
                                    @continue
                                @endif
                                
                                @php 
                                    $entry = $grid[$day][$slot];
                                    $rs = $rowspans[$day][$slot];
                                @endphp
                                <td rowspan="{{ $rs }}">
                                    @if ($entry)
                                        <span class="subject-name">{{ strtoupper($entry['subject']) }}</span>
                                        <span class="faculty-name">{{ $entry['faculty'] }}</span>
                                        <span class="room-type">{{ $entry['room'] }} ({{ strtoupper($entry['type']) }})</span>
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="timetable-footer">
                <div class="footer-left">
                    <p>Tutorial Symbol Explanation: TH (Theory), LAB (Practical), TUT (Tutorial)</p>
                    <p>Generated Date: {{ date('d-m-Y') }}</p>
                </div>
                <div class="footer-right">
                    <br><br>
                    <p>___________________</p>
                    <p>HOD Signature</p>
                </div>
                <div class="footer-right">
                    <br><br>
                    <p>___________________</p>
                    <p>Principal Signature</p>
                </div>
            </div>
        </div>
    @elseif (request()->isMethod('post'))
        <div class="page-card">
            <p>No timetable entries found for the selected criteria. <a href="/admin/timetable/builder">Go to Builder to create one.</a></p>
        </div>
    @endif
@endsection
