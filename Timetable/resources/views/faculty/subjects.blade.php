@extends('faculty.layout')

@section('content')
<div class="card">
    <h2>Assigned Subjects</h2>
    <p class="section-intro">Subjects assigned to you for the current semester.</p>

    @if($subjects->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #eef4ff;">
                        <th style="padding: 12px 16px; color: #4e607f;">Subject Code</th>
                        <th style="padding: 12px 16px; color: #4e607f;">Name</th>
                        <th style="padding: 12px 16px; color: #4e607f;">Semester</th>
                        <th style="padding: 12px 16px; color: #4e607f;">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subjects as $subject)
                        <tr style="border-bottom: 1px solid #eef4ff;">
                            <td style="padding: 14px 16px; font-weight: 600;">{{ $subject->subject_code }}</td>
                            <td style="padding: 14px 16px;">{{ $subject->name }}</td>
                            <td style="padding: 14px 16px;">{{ $subject->semester }}</td>
                            <td style="padding: 14px 16px;">{{ $subject->credit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="padding: 30px; text-align: center; background: #f8fafc; border-radius: 12px; color: #64748b;">
            <p style="margin: 0;">You have no assigned subjects in the system matching your name.</p>
        </div>
    @endif
</div>
@endsection
