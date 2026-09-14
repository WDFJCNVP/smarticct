<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\TopUpTransaction;
use App\Models\User;
use App\Services\AuditLogsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CashierTransactionExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'cashier_id'  => 'nullable|exists:users,id',
            'type'        => 'nullable|in:all,queue_fees,fare_payments,topups',
            'paper'       => 'nullable|in:letter,legal,a4',
            'orientation' => 'nullable|in:portrait,landscape',
            'preview'     => 'nullable|boolean',
        ]);

        $paper       = $validated['paper'] ?? 'legal';
        $orientation = $validated['orientation'] ?? 'portrait';
        $isPreview   = $request->boolean('preview');

        $actingUser = $request->user();
 
        // Blank from/to = "All time" (a real unbounded query, not a silent
        // fallback to today) — matches the "All Time"/"Today" quick-select
        // buttons in the export modal.
        $hasFrom = !empty($validated['from']);
        $hasTo = !empty($validated['to']);

        $rangeStart = $hasFrom ? Carbon::parse($validated['from'])->startOfDay() : null;
        $rangeEnd = $hasTo
            ? Carbon::parse($validated['to'])->endOfDay()
            : ($hasFrom ? Carbon::parse($validated['from'])->endOfDay() : null);

        $cashierId = $actingUser->role === 'admin'
            ? ($validated['cashier_id'] ?? null)
            : $actingUser->id;

        $cashier = $cashierId ? User::find($cashierId) : null;

        $type = $validated['type'] ?? 'all';
        $includeQueueFees = in_array($type, ['all', 'queue_fees']);
        $includeFarePayments = in_array($type, ['all', 'fare_payments']);
        $includeTopUps = in_array($type, ['all', 'topups']);

        $baseCashQuery = fn () => CashTransaction::query()
            ->with(['processedBy', 'operator', 'vehicle', 'queue'])
            ->where('status', 'success')
            ->when($rangeStart, fn ($q) => $q->where('created_at', '>=', $rangeStart))
            ->when($rangeEnd, fn ($q) => $q->where('created_at', '<=', $rangeEnd))
            ->when($cashierId, fn ($q) => $q->where('processed_by', $cashierId));

        $queueFees = $includeQueueFees
            ? $baseCashQuery()->where('reference_no', 'like', 'CASH-%')->orderBy('created_at')->get()
            : collect();

        $farePayments = $includeFarePayments
            ? $baseCashQuery()->where('reference_no', 'like', 'FARECASH-%')->orderBy('created_at')->get()
            : collect();

        $topUps = $includeTopUps
            ? TopUpTransaction::query()
                ->with(['processedBy', 'user', 'card'])
                ->where('status', 'paid')
                ->where('payment_method', 'cash')
                ->when($rangeStart, fn ($q) => $q->where('created_at', '>=', $rangeStart))
                ->when($rangeEnd, fn ($q) => $q->where('created_at', '<=', $rangeEnd))
                ->when($cashierId, fn ($q) => $q->where('processed_by', $cashierId))
                ->orderBy('created_at')
                ->get()
            : collect();

        $groupedQueueFees = $cashierId ? null : $queueFees->groupBy(fn ($t) => $t->processedBy?->name ?? 'Unknown');
        $groupedFarePayments = $cashierId ? null : $farePayments->groupBy(fn ($t) => $t->processedBy?->name ?? 'Unknown');
        $groupedTopUps = $cashierId ? null : $topUps->groupBy(fn ($t) => $t->processedBy?->name ?? 'Unknown');

        $queueFeeTotal = $queueFees->sum('amount');
        $farePaymentTotal = $farePayments->sum('amount');
        $topUpTotal = $topUps->sum('amount_paid');

        $pdf = Pdf::loadView('pdf.cashier-transactions', [
            'from'                => $rangeStart,
            'to'                  => $rangeEnd,
            'cashier'             => $cashier,
            'queueFees'           => $queueFees,
            'farePayments'        => $farePayments,
            'topUps'              => $topUps,
            'groupedQueueFees'    => $groupedQueueFees,
            'groupedFarePayments' => $groupedFarePayments,
            'groupedTopUps'       => $groupedTopUps,
            'queueFeeTotal'       => $queueFeeTotal,
            'farePaymentTotal'    => $farePaymentTotal,
            'topUpTotal'          => $topUpTotal,
            'grandTotal'          => $queueFeeTotal + $farePaymentTotal + $topUpTotal,
            'generatedBy'         => $actingUser->name ?? 'System',
            'generatedAt'         => now(),
            'includeQueueFees'    => $includeQueueFees,
            'includeFarePayments' => $includeFarePayments,
            'includeTopUps'       => $includeTopUps,
            'paper'               => $paper,
            'orientation'         => $orientation,
        ])->setPaper($paper, $orientation);

        $filenameScope = $cashier ? '-' . str($cashier->name)->slug() : '';
        $filenameType = match ($type) {
            'queue_fees'    => '-queue-fees',
            'fare_payments' => '-fare-payments',
            'topups'        => '-topups',
            default         => '',
        };
        $dateLabel = $rangeStart && $rangeEnd
            ? ($rangeStart->isSameDay($rangeEnd)
                ? $rangeStart->format('Y-m-d')
                : $rangeStart->format('Y-m-d') . '-to-' . $rangeEnd->format('Y-m-d'))
            : 'all-time';
        $filename = 'cashier-transactions' . $filenameScope . $filenameType . '-' . $dateLabel . '.pdf';

        // Previewing (the modal's live iframe) just renders the PDF inline —
        // it isn't a real export yet, so it shouldn't show up in the audit
        // trail or count as an actual download.
        if ($isPreview) {
            return $pdf->stream($filename);
        }

        app(AuditLogsService::class)->create([
            'user_id'  => $actingUser->id,
            'action'   => 'Export Cashier Transactions',
            'subject'  => 'Cashier cash transaction report exported as PDF',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => $request->ip(),
                'from'       => $rangeStart?->toDateString() ?? 'All time',
                'to'         => $rangeEnd?->toDateString() ?? 'All time',
                'cashier'    => $cashier?->name ?? 'All cashiers',
                'type'       => $type,
                'records'    => $queueFees->count() + $farePayments->count() + $topUps->count(),
            ],
        ]);

        return $pdf->download($filename);
    }
}