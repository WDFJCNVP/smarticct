<?php

namespace App\Http\Controllers;

use App\Services\AuditLogsService;
use App\Services\TransactionLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionsExportController extends Controller
{
    public function export(Request $request, TransactionLedgerService $ledger)
    {
        $validated = $request->validate([
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'category'    => 'nullable|in:topup,queue_fee,fare_payment,card_issuance',
            'mode'        => 'nullable|in:cash,card,online',
            'status'      => 'nullable|in:completed,pending,failed',
            'paper'       => 'nullable|in:letter,legal,a4',
            'orientation' => 'nullable|in:portrait,landscape',
            'preview'     => 'nullable|boolean',
        ]);

        $paper       = $validated['paper'] ?? 'legal';
        $orientation = $validated['orientation'] ?? 'portrait';
        $isPreview   = $request->boolean('preview');
        $actingUser  = $request->user();

        // Blank from/to = "All time" (a real unbounded query, not a silent
        // fallback to today) — matches the "All Time"/"Today" quick-select
        // buttons in the export modal.
        $hasFrom = !empty($validated['from']);
        $hasTo   = !empty($validated['to']);

        $rangeStart = $hasFrom ? Carbon::parse($validated['from'])->startOfDay() : null;
        $rangeEnd   = $hasTo
            ? Carbon::parse($validated['to'])->endOfDay()
            : ($hasFrom ? Carbon::parse($validated['from'])->endOfDay() : null);

        $category = $validated['category'] ?? null;
        $mode     = $validated['mode'] ?? null;
        $status   = $validated['status'] ?? null;

        $rows = $ledger->applyFilters($ledger->query(), [
                'category' => $category,
                'mode'     => $mode,
                'status'   => $status,
                'from'     => $validated['from'] ?? null,
                'to'       => $validated['to'] ?? null,
            ])
            ->orderBy('occurred_at')
            ->get()
            ->map(function ($row) {
                $row->occurred_at = Carbon::parse($row->occurred_at);
                $row->amount = (float) $row->amount;
                return $row;
            });

        // Fixed, human-friendly section order regardless of what's present.
        $categoryOrder = ['topup', 'queue_fee', 'fare_payment', 'card_issuance'];
        $grouped = $rows->groupBy('category')->sortBy(fn ($_, $key) => array_search($key, $categoryOrder));

        $totalsByCategory = $rows->groupBy('category')->map(fn ($group) => $group->sum('amount'));
        $totalsByMode     = $rows->groupBy('mode')->map(fn ($group) => $group->sum('amount'));
        $grandTotal       = $rows->sum('amount');

        $categoryLabels = [
            'topup'         => 'Top-Ups',
            'queue_fee'     => 'Queue Fee Payments',
            'fare_payment'  => 'Fare Payments',
            'card_issuance' => 'Card Issuance',
        ];

        $modeLabels = [
            'cash'   => 'Cash',
            'card'   => 'Card / Wallet',
            'online' => 'Online',
            'n/a'    => 'N/A',
        ];

        $pdf = Pdf::loadView('pdf.transactions-report', [
            'from'             => $rangeStart,
            'to'               => $rangeEnd,
            'category'         => $category,
            'mode'             => $mode,
            'status'           => $status,
            'categoryLabels'   => $categoryLabels,
            'modeLabels'       => $modeLabels,
            'rows'             => $rows,
            'grouped'          => $grouped,
            'totalsByCategory' => $totalsByCategory,
            'totalsByMode'     => $totalsByMode,
            'grandTotal'       => $grandTotal,
            'generatedBy'      => $actingUser->name ?? 'System',
            'generatedAt'      => now(),
            'paper'            => $paper,
            'orientation'      => $orientation,
        ])->setPaper($paper, $orientation);

        $filenameParts = array_filter([
            'transactions',
            $category,
            $mode,
        ]);
        $dateLabel = $rangeStart && $rangeEnd
            ? ($rangeStart->isSameDay($rangeEnd)
                ? $rangeStart->format('Y-m-d')
                : $rangeStart->format('Y-m-d') . '-to-' . $rangeEnd->format('Y-m-d'))
            : 'all-time';
        $filename = implode('-', $filenameParts) . '-' . $dateLabel . '.pdf';

        // Previewing (the modal's live iframe) just renders the PDF inline —
        // it isn't a real export yet, so it shouldn't show up in the audit
        // trail or count as an actual download.
        if ($isPreview) {
            return $pdf->stream($filename);
        }

        app(AuditLogsService::class)->create([
            'user_id'  => $actingUser->id,
            'action'   => 'Export Transactions Report',
            'subject'  => 'Admin transactions ledger exported as PDF',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => $request->ip(),
                'from'       => $rangeStart?->toDateString() ?? 'All time',
                'to'         => $rangeEnd?->toDateString() ?? 'All time',
                'category'   => $category ?? 'All',
                'mode'       => $mode ?? 'All',
                'status'     => $status ?? 'All',
                'records'    => $rows->count(),
            ],
        ]);

        return $pdf->download($filename);
    }
}