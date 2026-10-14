<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        h3 { margin-bottom: 10px; }
    </style>
</head>
<body>
    <h3>Employee List — {{ now()->format('d M Y') }}</h3>
    <table>
        <thead>
            <tr>
                <th>Emp ID</th><th>Name</th><th>Email</th><th>Phone</th>
                <th>Department</th><th>Job Title</th><th>Role</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($employees as $e)
                <tr>
                    <td>{{ $e->employee_id }}</td>
                    <td>{{ $e->first_name }} {{ $e->last_name }}</td>
                    <td>{{ $e->email }}</td>
                    <td>{{ $e->phone }}</td>
                    <td>{{ $e->department->name ?? '-' }}</td>
                    <td>{{ $e->job_title }}</td>
                    <td>{{ $e->role->display_name ?? '-' }}</td>
                    <td>{{ ucfirst($e->employment_status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>