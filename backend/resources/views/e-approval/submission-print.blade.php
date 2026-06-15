<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document_no }} — {{ $form_name }}</title>
    <style>
        body { font-family: Inter, system-ui, sans-serif; font-size: 13px; color: #0f172a; margin: 24px; }
        h1 { font-size: 20px; font-weight: 600; margin: 0 0 4px; }
        .meta { color: #64748b; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; font-weight: 500; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <h1>{{ $form_name }}</h1>
    <p class="meta">
        Document <strong>{{ $document_no }}</strong> · {{ $status }} · Requestor {{ $requestor }} · {{ $created_at }}
    </p>

    <h2 style="font-size: 14px; font-weight: 600;">Field values</h2>
    <table>
        <thead>
            <tr><th>Field</th><th>Value</th></tr>
        </thead>
        <tbody>
            @forelse ($fields as $field)
                <tr>
                    <td>{{ $field['label'] }}</td>
                    <td>{{ $field['value'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No values</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (count($approvals) > 0)
        <h2 style="font-size: 14px; font-weight: 600; margin-top: 24px;">Approval trail</h2>
        <table>
            <thead>
                <tr><th>Step</th><th>Approver</th><th>Status</th><th>Acted</th></tr>
            </thead>
            <tbody>
                @foreach ($approvals as $row)
                    <tr>
                        <td>{{ $row['step'] }}</td>
                        <td>{{ $row['approver'] }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td>{{ $row['acted_at'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
