<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dispatch Log</title>
    <style>
        /*
         * dompdf does NOT support background-image on @page (confirmed in
         * dompdf's own source, vendor/dompdf/dompdf/src/Css/Stylesheet.php —
         * background-color/-image on @page is explicitly listed as a
         * "non-working property"). The supported way to get a repeating
         * full-page letterhead is a `position: fixed` element: dompdf
         * repaints anything position:fixed on every generated page, which
         * is exactly the behavior we want here.
         *
         * @page still legitimately handles `size` and `margin` (those ARE
         * on dompdf's working list), so the margin box below still exists
         * to keep flowing content clear of the artwork.
         *
         * If you resize/replace the letterhead image later, re-check these
         * margins — they were measured against the current file's header
         * (~1.5in), footer (~1.6in) and left ribbon (~0.95in) safe zones.
         */
        @page {
            size: legal portrait;
            margin: 1.55in 0.9in 1.65in 1.3in;
        }

        .letterhead-bg {
            position: fixed;
            top: -1.55in;
            left: -1.3in;
            width: 8.5in;
            height: 14in;
            z-index: -1;
        }
        .letterhead-bg img {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Helvetica', Arial, sans-serif;
            color: #1a1a1a;
            font-size: 10.5px;
            margin: 0;
            padding: 0;
        }

        .header {
            width: 100%;
            padding-bottom: 8px;
            margin-bottom: 6px;
            border-bottom: 1px solid #d5d5db;
        }
        .header td { vertical-align: top; }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            color: #1a1a2e;
        }
        .doc-subtitle {
            font-size: 9.5px;
            color: #666666;
            margin-top: 2px;
        }
        .meta {
            text-align: right;
            font-size: 9.5px;
            color: #444444;
        }
        .meta strong { color: #1a1a1a; }

        .route-title {
            background-color: #1a1a2e;
            color: #ffffff;
            font-size: 11.5px;
            font-weight: bold;
            padding: 5px 8px;
            margin-top: 14px;
            margin-bottom: 0;
        }

        table.log-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        table.log-table th {
            background-color: #f0f0f3;
            border: 1px solid #d5d5db;
            padding: 5px 6px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            color: #444444;
        }
        table.log-table td {
            border: 1px solid #d5d5db;
            padding: 5px 6px;
            font-size: 9.5px;
            background-color: #ffffff;
        }
        table.log-table tr:nth-child(even) td {
            background-color: #fafafa;
        }
        .status-departed {
            color: #0a7a3d;
            font-weight: bold;
        }
        .status-staging {
            color: #a35b00;
            font-weight: bold;
        }
        .empty-note {
            font-size: 10px;
            color: #888888;
            font-style: italic;
            padding: 8px 0;
        }

        .summary {
            margin-top: 16px;
            font-size: 9.5px;
            color: #444444;
            border-top: 1px solid #d5d5db;
            padding-top: 8px;
        }
        .signatures {
            width: 100%;
            margin-top: 36px;
            border-collapse: collapse;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            font-size: 9px;
            color: #444444;
            padding-top: 28px;
        }
        .signature-line {
            border-top: 1px solid #888888;
            width: 80%;
            margin: 0 auto 4px auto;
        }
    </style>
</head>
<body>

    <div class="letterhead-bg">
        <img src="{{ public_path('images/pdf-bg.jpg') }}" alt="">
    </div>

    <table class="header">
        <tr>
            <td>
                <div class="doc-title">{{ $title ?? 'Daily Dispatch Log' }}</div>
                <div class="doc-subtitle">{{ $subtitle ?? 'Vehicle queueing & departure record' }}</div>
            </td>
            <td class="meta">
                <div>
                    <strong>Period:</strong>
                    {{ $from->format('M d, Y') }}
                    @if (!$from->isSameDay($to))
                        &ndash; {{ $to->format('M d, Y') }}
                    @endif
                </div>
                @if (!empty($scopeNote))
                    <div><strong>Scope:</strong> {{ $scopeNote }}</div>
                @endif
                <div><strong>Generated by:</strong> {{ $generatedBy }}</div>
                <div><strong>Generated on:</strong> {{ $generatedAt->format('M d, Y g:i A') }}</div>
            </td>
        </tr>
    </table>

    @forelse ($grouped as $destination => $entries)
        <div class="route-title">{{ $destination }}</div>
        <table class="log-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Plate No.</th>
                    <th style="width: 16%;">Driver</th>
                    <th style="width: 12%;">Vehicle Type</th>
                    <th style="width: 10%;">Occupancy</th>
                    <th style="width: 16%;">Queued At</th>
                    <th style="width: 16%;">Departed At</th>
                    <th style="width: 16%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ $entry->plate_number }}</td>
                        <td>{{ $entry->driver_name }}</td>
                        <td>{{ $entry->vehicle_type }}</td>
                        <td>{{ $entry->seat_count }}/{{ $entry->seat_capacity }}</td>
                        <td>{{ $entry->time_queued?->format('M d, g:i A') ?? '—' }}</td>
                        <td>{{ $entry->time_departed?->format('M d, g:i A') ?? '—' }}</td>
                        <td>
                            @if ($entry->time_departed)
                                <span class="status-departed">Departed</span>
                            @else
                                <span class="status-staging">{{ ucfirst($entry->status) }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="empty-note">No vehicles were logged for the selected period.</p>
    @endforelse

    <div class="summary">
        <strong>Total vehicles logged:</strong> {{ $totalCount }}
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line"></div>
                Prepared by
            </td>
            <td>
                <div class="signature-line"></div>
                Verified by
            </td>
        </tr>
    </table>

</body>
</html>