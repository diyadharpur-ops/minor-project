@extends('student.layout')

@section('content')
    <div class="card">
        <h2>View Timetable</h2>
        <p class="section-intro">This is your timetable section.</p>
        <div class="list-card">
            <p>Monday to Friday: 9:00 AM - 4:00 PM</p>
            <ul style="margin: 16px 0 0; padding-left: 18px;">
                <li>09:00 - 10:00 | Mathematics</li>
                <li>10:15 - 11:15 | Science</li>
                <li>11:30 - 12:30 | English</li>
                <li>01:30 - 02:30 | Practical Training</li>
                <li>02:45 - 03:45 | Sports / Clubs</li>
            </ul>
        </div>
    </div>
@endsection
