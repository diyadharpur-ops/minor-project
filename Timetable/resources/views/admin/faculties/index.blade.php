<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty</title>
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
            <h1>Faculty</h1>
            <div>
                <a href="/admin/dashboard" class="btn btn-muted">Back</a>
                <a href="/admin/faculties/create" class="btn">Add Faculty</a>
            </div>
        </div>

        <div class="card">
            <form method="GET" action="/admin/faculties" class="search">
                <input type="text" name="q" placeholder="Search by name, email or qualification" value="{{ $q ?? '' }}" />
                <button type="submit" class="btn">Search</button>
                <a href="/admin/faculties" class="btn btn-muted" style="margin-left:8px">Clear</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Qualification</th>
                        <th>Department</th>
                        <th>Subjects</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($faculties as $faculty)
                        <tr>
                            <td>{{ $faculty->id }}</td>
                            <td>{{ $faculty->name }}</td>
                            <td>{{ $faculty->mobile_number }}</td>
                            <td>{{ $faculty->email }}</td>
                            <td>{{ $faculty->qualification }}</td>
                            <td>{{ $faculty->department?->name ?? 'N/A' }}</td>
                            <td>{{ $faculty->subjects }}</td>
                            <td class="actions">
                                <a href="/admin/faculties/{{ $faculty->id }}/edit" class="btn btn-muted">Edit</a>
                                <form method="POST" action="/admin/faculties/{{ $faculty->id }}/delete" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn" style="background:#ef4444">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No faculty found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
