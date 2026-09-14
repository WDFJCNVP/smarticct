@if ($rows->isEmpty())
    <p class="empty-note">No transactions of this type were recorded for this period.</p>
@else
    <table class="log-table">
        <thead>
            <tr>
                <th style="width: 13%;">Date &amp; Time</th>
                <th style="width: 18%;">Name</th>
                <th style="width: 24%;">Reference</th>
                <th style="width: 10%;">Mode</th>
                <th style="width: 12%;" class="amount">Amount</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 13%;">Processed By</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->occurred_at->format('M d, Y g:i A') }}</td>
                    <td>{{ $row->user_name ?? '—' }}</td>
                    <td>{{ $row->reference ?? '—' }}</td>
                    <td>{{ $modeLabels[$row->mode] ?? ucfirst($row->mode) }}</td>
                    <td class="amount">&#8369;{{ number_format($row->amount, 2) }}</td>
                    <td>{{ ucfirst($row->status) }}</td>
                    <td>{{ $row->processed_by_name }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="4">Subtotal</td>
                <td class="amount">&#8369;{{ number_format($rows->sum('amount'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
@endif