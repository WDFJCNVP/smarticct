@if ($rows->isEmpty())
    <p class="empty-note">No cash card top-ups were recorded for this period.</p>
@else
    <table class="log-table">
        <thead>
            <tr>
                <th style="width: 14%;">Time</th>
                <th style="width: 26%;">Cardholder</th>
                <th style="width: 20%;">Card No.</th>
                <th style="width: 14%;" class="amount">Amount</th>
                <th style="width: 26%;">Reference</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->created_at->format('g:i A') }}</td>
                    <td>{{ $row->user?->name ?? '—' }}</td>
                    <td>{{ $row->card?->card_number ?? '—' }}</td>
                    <td class="amount">&#8369;{{ number_format($row->amount_paid, 2) }}</td>
                    <td>{{ $row->checkout_session_id }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="3">Subtotal</td>
                <td class="amount">&#8369;{{ number_format($rows->sum('amount_paid'), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endif
