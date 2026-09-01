@if ($rows->isEmpty())
    <p class="empty-note">No cash queue fee payments were recorded for this period.</p>
@else
    <table class="log-table">
        <thead>
            <tr>
                <th style="width: 12%;">Time</th>
                <th style="width: 20%;">Operator</th>
                <th style="width: 14%;">Plate No.</th>
                <th style="width: 14%;">Queue Dest.</th>
                <th style="width: 12%;" class="amount">Fee</th>
                <th style="width: 12%;" class="amount">Received</th>
                <th style="width: 10%;" class="amount">Change</th>
                <th style="width: 16%;">Reference</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->created_at->format('g:i A') }}</td>
                    <td>{{ $row->operator?->name ?? '—' }}</td>
                    <td>{{ $row->vehicle?->plate_number ?? '—' }}</td>
                    <td>{{ $row->queue?->destination ?? '—' }}</td>
                    <td class="amount">&#8369;{{ number_format($row->amount, 2) }}</td>
                    <td class="amount">&#8369;{{ number_format($row->amount_received, 2) }}</td>
                    <td class="amount">&#8369;{{ number_format($row->change, 2) }}</td>
                    <td>{{ $row->reference_no ?? '—' }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="4">Subtotal</td>
                <td class="amount">&#8369;{{ number_format($rows->sum('amount'), 2) }}</td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>
@endif
