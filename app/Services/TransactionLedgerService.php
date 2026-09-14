<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the unified "all money-in" transaction ledger — top-ups, queue fee
 * payments, fare payments, and card issuance — as a single normalized list,
 * shared by the admin Transactions tab and its PDF export so both always
 * agree on exactly what counts and how it's categorized.
 *
 * Column shape every subquery must produce, in this order:
 *   row_id, occurred_at, category, mode, channel, user_name, reference,
 *   amount, status, processed_by_name
 */
class TransactionLedgerService
{
    public const CATEGORIES = ['topup', 'queue_fee', 'fare_payment', 'card_issuance'];
    public const MODES = ['cash', 'card', 'online'];
    public const STATUSES = ['completed', 'pending', 'failed'];

    /**
     * The unfiltered unified ledger as a UNION ALL query builder.
     */
    public function baseQuery(): Builder
    {
        // Top-ups — cash (cashier window) or online (PayMongo: gcash/card/paymaya/qrph).
        $topups = DB::table('top_up_transactions as t')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('cards as c', 't.card_id', '=', 'c.id')
            ->leftJoin('users as p', 't.processed_by', '=', 'p.id')
            ->select([
                DB::raw("CONCAT('topup-', t.id) as row_id"),
                't.created_at as occurred_at',
                DB::raw("'topup' as category"),
                DB::raw("CASE WHEN t.payment_method = 'cash' THEN 'cash' ELSE 'online' END as mode"),
                DB::raw("COALESCE(t.payment_method, 'online') as channel"),
                'u.name as user_name',
                DB::raw("CONCAT('Card **** ', RIGHT(COALESCE(c.card_number, '0000'), 4)) as reference"),
                't.amount_paid as amount',
                DB::raw("CASE WHEN t.status = 'paid' THEN 'completed' WHEN t.status = 'pending' THEN 'pending' ELSE 'failed' END as status"),
                DB::raw("COALESCE(p.name, 'Online (PayMongo)') as processed_by_name"),
            ]);

        // Queue fees paid via RFID card (currently the only way to pay one).
        $queueFeeCard = DB::table('card_transactions as ct')
            ->join('cards as c', 'ct.card_id', '=', 'c.id')
            ->join('users as u', 'c.user_id', '=', 'u.id')
            ->leftJoin('users as p', 'ct.processed_by', '=', 'p.id')
            ->where('ct.transaction_type', 'queueing_fee')
            ->select([
                DB::raw("CONCAT('cardq-', ct.id) as row_id"),
                'ct.transaction_time as occurred_at',
                DB::raw("'queue_fee' as category"),
                DB::raw("'card' as mode"),
                DB::raw("'RFID tap' as channel"),
                'u.name as user_name',
                'ct.message as reference',
                'ct.amount as amount',
                DB::raw("CASE WHEN ct.status = 'success' THEN 'completed' ELSE 'failed' END as status"),
                DB::raw("COALESCE(p.name, 'System') as processed_by_name"),
            ]);

        // Queue fees paid in cash — not implemented yet in the cashier flow,
        // but kept here (currently always empty) so it lights up automatically
        // the moment that payment path is added.
        $queueFeeCash = DB::table('cash_transactions as csh')
            ->join('users as op', 'csh.operator_id', '=', 'op.id')
            ->leftJoin('users as p', 'csh.processed_by', '=', 'p.id')
            ->where('csh.reference_no', 'like', 'CASH-%')
            ->select([
                DB::raw("CONCAT('cashq-', csh.id) as row_id"),
                'csh.created_at as occurred_at',
                DB::raw("'queue_fee' as category"),
                DB::raw("'cash' as mode"),
                DB::raw("'Cashier' as channel"),
                'op.name as user_name',
                'csh.notes as reference',
                'csh.amount as amount',
                DB::raw("CASE WHEN csh.status = 'success' THEN 'completed' ELSE 'failed' END as status"),
                DB::raw("COALESCE(p.name, 'Cashier') as processed_by_name"),
            ]);

        // Fare payments collected in cash by a cashier.
        $fareCash = DB::table('cash_transactions as csh2')
            ->join('users as op2', 'csh2.operator_id', '=', 'op2.id')
            ->leftJoin('users as p2', 'csh2.processed_by', '=', 'p2.id')
            ->where('csh2.reference_no', 'like', 'FARECASH-%')
            ->select([
                DB::raw("CONCAT('cashf-', csh2.id) as row_id"),
                'csh2.created_at as occurred_at',
                DB::raw("'fare_payment' as category"),
                DB::raw("'cash' as mode"),
                DB::raw("'Cashier' as channel"),
                'op2.name as user_name',
                'csh2.notes as reference',
                'csh2.amount as amount',
                DB::raw("CASE WHEN csh2.status = 'success' THEN 'completed' ELSE 'failed' END as status"),
                DB::raw("COALESCE(p2.name, 'Cashier') as processed_by_name"),
            ]);

        // Fare payments paid by tapping an RFID card at the kiosk. This is
        // the rider's card being deducted — the real payment event. The
        // matching operator-side credit ("fare_earning") is intentionally
        // excluded to avoid counting the same payment twice.
        $fareCard = DB::table('card_transactions as ct2')
            ->join('cards as c2', 'ct2.card_id', '=', 'c2.id')
            ->join('users as u2', 'c2.user_id', '=', 'u2.id')
            ->leftJoin('users as p3', 'ct2.processed_by', '=', 'p3.id')
            ->where('ct2.transaction_type', 'queue_deduction')
            ->where('ct2.source', 'kiosk_tap_in')
            ->select([
                DB::raw("CONCAT('cardf-', ct2.id) as row_id"),
                'ct2.transaction_time as occurred_at',
                DB::raw("'fare_payment' as category"),
                DB::raw("'card' as mode"),
                DB::raw("'RFID tap' as channel"),
                'u2.name as user_name',
                'ct2.message as reference',
                'ct2.amount as amount',
                DB::raw("CASE WHEN ct2.status = 'success' THEN 'completed' ELSE 'failed' END as status"),
                DB::raw("COALESCE(p3.name, 'Kiosk') as processed_by_name"),
            ]);

        // Card issuance — no fee decided yet, so amount is always 0. Kept in
        // the ledger for visibility/audit; will carry real revenue once a
        // price is configured.
        $cardIssuance = DB::table('cards as ci')
            ->join('users as u3', 'ci.user_id', '=', 'u3.id')
            ->select([
                DB::raw("CONCAT('cardissue-', ci.id) as row_id"),
                'ci.created_at as occurred_at',
                DB::raw("'card_issuance' as category"),
                DB::raw("'n/a' as mode"),
                DB::raw("'n/a' as channel"),
                'u3.name as user_name',
                DB::raw("CONCAT('Card **** ', RIGHT(COALESCE(ci.card_number, '0000'), 4)) as reference"),
                DB::raw('0 as amount'),
                DB::raw("'completed' as status"),
                DB::raw("'-' as processed_by_name"),
            ]);

        return $topups
            ->unionAll($queueFeeCard)
            ->unionAll($queueFeeCash)
            ->unionAll($fareCash)
            ->unionAll($fareCard)
            ->unionAll($cardIssuance);
    }

    /**
     * Wraps baseQuery() in a subquery so it can be filtered/sorted/paginated
     * as a single virtual table.
     */
    public function query(): Builder
    {
        return DB::query()->fromSub($this->baseQuery(), 'tx');
    }

    /**
     * Applies the common set of filters shared by the in-app ledger and the
     * PDF export, so the two never drift apart.
     *
     * @param  array{search?: ?string, category?: ?string, mode?: ?string, status?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $search   = $filters['search'] ?? null;
        $category = $filters['category'] ?? null;
        $mode     = $filters['mode'] ?? null;
        $status   = $filters['status'] ?? null;
        $from     = $filters['from'] ?? null;
        $to       = $filters['to'] ?? null;

        return $query
            ->when(filled($search), function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('user_name', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('processed_by_name', 'like', $term);
                });
            })
            ->when(filled($category), fn ($q) => $q->where('category', $category))
            ->when(filled($mode), fn ($q) => $q->where('mode', $mode))
            ->when(filled($status), fn ($q) => $q->where('status', $status))
            ->when(filled($from), fn ($q) => $q->whereDate('occurred_at', '>=', $from))
            ->when(filled($to), fn ($q) => $q->whereDate('occurred_at', '<=', $to));
    }
}