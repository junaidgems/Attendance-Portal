<!DOCTYPE html>
<html lang="ur">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            padding: 30px;
        }
        h2 {
            color: #222;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #2c3e50;
            color: #fff;
            font-weight: 600;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .status {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-ontime {
            background: #e3f9e5;
            color: #1e7e34;
        }
        .status-late {
            background: #fdecea;
            color: #c0392b;
        }
        .empty {
            text-align: center;
            padding: 40px;
            color: #888;
        }
    </style>
</head>
<body>
    <h2>Attendance Report ({{ count($report) }} records)</h2>

    @if(count($report) > 0)
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>User ID</th>
                    <th>Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['user_id'] }}</td>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['check_in'] ?? '-' }}</td>
                        <td>{{ $row['check_out'] ?? '-' }}</td>
                        <td>
                            <span class="status {{ $row['status'] === 'Late' ? 'status-late' : 'status-ontime' }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">Koi attendance record nahi mila.</div>
    @endif
</body>
</html>