<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments</title>
    <style>
        body{ font-family: Arial, sans-serif; background:#f4f7fb; color:#1f2937; padding:24px }
        .container{ max-width:1000px; margin:0 auto }
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
            <h1>Departments</h1>
            <div>
                <a href="/admin/dashboard" class="btn btn-muted">Back</a>
                <a href="/admin/departments/create" class="btn">Add Department</a>
            </div>
        </div>

        <div class="card">
            <form method="GET" action="/admin/departments" class="search">
                <input type="text" name="q" placeholder="Search by name or code" value="{{ $q ?? '' }}" />
                <button type="submit" class="btn">Search</button>
                <a href="/admin/departments" class="btn btn-muted" style="margin-left:8px">Clear</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $d)
                        <tr>
                            <td>{{ $d->id }}</td>
                            <td>{{ $d->name }}</td>
                            <td>{{ $d->code }}</td>
                            <td>{{ $d->description }}</td>
                            <td class="actions">
                                <a href="/admin/departments/{{ $d->id }}/edit" class="btn btn-muted">Edit</a>
                                <form method="POST" action="/admin/departments/{{ $d->id }}/delete" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn" style="background:#ef4444">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No departments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
