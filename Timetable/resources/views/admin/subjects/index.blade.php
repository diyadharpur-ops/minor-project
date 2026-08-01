<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects</title>
    <style>
        body{ font-family: Arial, sans-serif; background:#f4f7fb; color:#1f2937; padding:24px }
        .container{ max-width:1200px; margin:0 auto }
        .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px }
        .card{ background:white; padding:16px; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,0.06) }
        table{ width:100%; border-collapse:collapse; margin-top:12px }
        th,td{ text-align:left; padding:8px; border-bottom:1px solid #eef2f7 }
        .actions form{ display:inline }
        .btn{ padding:8px 12px; border-radius:8px; text-decoration:none; color:white; background:#2563eb }
        .btn-muted{ background:#6b7280 }
        .search{ display:flex; gap:8px }
    </style>
</head>
<body>
    <div class="container">
        <div class="top">
            <h1>Subjects</h1>
            <div>
                <a href="/admin/dashboard" class="btn btn-muted">Back</a>
                <a href="/admin/subjects/create" class="btn">Add Subject</a>
            </div>
        </div>

        <div class="card">
            <form method="GET" action="/admin/subjects" class="search">
                <input type="text" name="q" placeholder="Search by name, code, semester, faculty" value="{{ $q ?? '' }}" />
                <button type="submit" class="btn">Search</button>
                <a href="/admin/subjects" class="btn btn-muted" style="margin-left:8px">Clear</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Subject Code</th>
                        <th>Semester</th>
                        <th>Department</th>
                        <th>Credit</th>
                        <th>Faculty</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr>
                            <td>{{ $subject->id }}</td>
                            <td>{{ $subject->name }}</td>
                            <td>{{ $subject->subject_code }}</td>
                            <td>{{ $subject->semester }}</td>
                            <td>{{ $subject->department?->name ?? 'N/A' }}</td>
                            <td>{{ $subject->credit }}</td>
                            <td>{{ $subject->faculty_name }}</td>
                            <td class="actions">
                                <a href="/admin/subjects/{{ $subject->id }}/edit" class="btn btn-muted">Edit</a>
                                <form method="POST" action="/admin/subjects/{{ $subject->id }}/delete" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn" style="background:#ef4444">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No subjects found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
