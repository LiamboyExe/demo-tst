<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }}</title>
    <style>
        @page {
            margin: 28px 30px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #18181b;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            line-height: 1.4;
        }

        .header {
            border-bottom: 2px solid #18181b;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .eyebrow {
            color: #71717a;
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        h1 {
            margin: 3px 0 0;
            font-size: 19px;
            letter-spacing: -0.3px;
        }

        .meta {
            margin-top: 4px;
            color: #71717a;
            font-size: 8px;
        }

        .filters {
            margin-bottom: 16px;
            padding: 8px 10px;
            background: #fafafa;
            border: 1px solid #e4e4e7;
        }

        .filters span {
            display: inline-block;
            margin-right: 16px;
            color: #52525b;
        }

        .filters strong {
            color: #18181b;
        }

        h2 {
            margin: 16px 0 6px;
            font-size: 11px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th {
            background: #f4f4f5;
            border: 1px solid #d4d4d8;
            color: #3f3f46;
            font-size: 8px;
            letter-spacing: 0.3px;
            padding: 5px 6px;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            border: 1px solid #e4e4e7;
            padding: 4px 6px;
            vertical-align: top;
        }

        tbody tr:nth-child(even) td {
            background: #fcfcfc;
        }

        .notes {
            margin-top: 14px;
            padding: 8px 10px;
            border-left: 3px solid #a1a1aa;
            background: #fafafa;
            color: #52525b;
            font-size: 8px;
        }

        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #d4d4d8;
            color: #71717a;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="eyebrow">{{ $document['app_name'] }} / Reports and analytics</div>
        <h1>{{ $document['title'] }}</h1>
        <div class="meta">Generated {{ $document['generated_at'] }}</div>
    </div>

    <div class="filters">
        @foreach ($document['filters'] as $filter)
            <span><strong>{{ $filter['label'] }}:</strong> {{ $filter['value'] }}</span>
        @endforeach
    </div>

    @foreach ($document['sections'] as $section)
        <h2>{{ $section['heading'] }}</h2>
        <table>
            <thead>
                <tr>
                    @foreach ($section['headers'] as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($section['headers']) }}">No records match the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    @if (! empty($document['notes']))
        <div class="notes">
            @foreach ($document['notes'] as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif

    <div class="footer">
        {{ $document['app_name'] }} · Administrative report generated from live membership records.
    </div>
</body>
</html>
