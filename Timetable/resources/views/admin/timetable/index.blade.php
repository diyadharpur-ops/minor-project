@extends('admin.layout')

@section('title', 'Generate Timetable')

@section('content')
    <div class="page-header">
        <div>
            <h1>Generate Timetable</h1>
            <p>Create a conflict-free timetable for a selected semester using the registered subjects, faculty, and classrooms.</p>
        </div>
        <a href="/admin/dashboard" class="btn btn-muted">Back</a>
    </div>

    <div class="page-card">
        @if(session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        <form method="POST" action="/admin/timetable/generate">
            @csrf
            <div class="form-row">
                <label>Semester</label>
                <select name="semester" required>
                    <option value="">Select semester</option>
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester }}" {{ old('semester') == $semester ? 'selected' : '' }}>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label>Working Days</label>
                <textarea name="days" rows="3">{{ old('days', implode(PHP_EOL, $days)) }}</textarea>
                <small>Enter one day per line or separate with commas.</small>
            </div>
            <div class="form-row">
                <label>Time Slots</label>
                <textarea name="time_slots" rows="4">{{ old('time_slots', implode(PHP_EOL, $timeSlots)) }}</textarea>
                <small>Enter one time slot per line or separate with commas.</small>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Generate Timetable</button>
                <a href="/admin/timetable" class="btn btn-muted">Refresh</a>
            </div>
        </form>
    </div>

    @if (!empty($timetable['sessions']))
        <div class="page-card">
            <h2>Generated Timetable</h2>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">Day</th>
                        @foreach ($timetable['time_slots'] as $slot)
                            <th style="border:1px solid #ddd; padding:8px; text-align:left;">{{ $slot }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timetable['days'] as $day)
                        <tr>
                            <td style="border:1px solid #ddd; padding:8px; font-weight:600;">{{ $day }}</td>
                            @foreach ($timetable['time_slots'] as $slot)
                                @php
                                    $entry = collect($timetable['sessions'])->first(function ($session) use ($day, $slot) {
                                        return $session['day'] === $day && $session['time_slot'] === $slot;
                                    });
                                @endphp
                                <td style="border:1px solid #ddd; padding:8px; vertical-align:top;">
                                    @if ($entry)
                                        <strong>{{ $entry['subject'] }}</strong><br>
                                        {{ $entry['faculty'] }}<br>
                                        <small>{{ $entry['room'] }} · {{ $entry['type'] }}</small>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif (session('timetable'))
        <div class="page-card">
            <p>No valid sessions could be generated for the selected semester.</p>
        </div>
    @endif
@endsection
