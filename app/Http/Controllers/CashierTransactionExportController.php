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
            'type'        => 'nullable|in:all,queue_fees,topups',
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
        $includeTopUps = in_array($type, ['all', 'topups']);

        // --- Queue fee payments (cash) -------------------------------------
        $queueFees = $includeQueueFees
            ? CashTransaction::query()
                ->with(['processedBy', 'operator', 'vehicle', 'queue'])
                ->where('status', 'success')
                ->when($rangeStart, fn ($q) => $q->where('created_at', '>=', $rangeStart))
                ->when($rangeEnd, fn ($q) => $q->where('created_at', '<=', $rangeEnd))
                ->when($cashierId, fn ($q) => $q->where('processed_by', $cashierId))
                ->orderBy('created_at')
                ->get()
            : collect();

        // --- Card top-ups paid in cash --------------------------------------
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

        // When scoped to "all cashiers" (admin, no cashier filter), group each
        // section by who processed it so the report reads like one section
        // per cashier rather than one long undifferentiated list.
        $groupedQueueFees = $cashierId ? null : $queueFees->groupBy(fn ($t) => $t->processedBy?->name ?? 'Unknown');
        $groupedTopUps = $cashierId ? null : $topUps->groupBy(fn ($t) => $t->processedBy?->name ?? 'Unknown');

        $queueFeeTotal = $queueFees->sum('amount');
        $topUpTotal = $topUps->sum('amount_paid');

        $pdf = Pdf::loadView('pdf.cashier-transactions', [
            'from'             => $rangeStart,
            'to'               => $rangeEnd,
            'cashier'          => $cashier,
            'queueFees'        => $queueFees,
            'topUps'           => $topUps,
            'groupedQueueFees' => $groupedQueueFees,
            'groupedTopUps'    => $groupedTopUps,
            'queueFeeTotal'    => $queueFeeTotal,
            'topUpTotal'       => $topUpTotal,
            'grandTotal'       => $queueFeeTotal + $topUpTotal,
            'generatedBy'      => $actingUser->name ?? 'System',
            'generatedAt'      => now(),
            'includeQueueFees' => $includeQueueFees,
            'includeTopUps'    => $includeTopUps,
            'paper'            => $paper,
            'orientation'      => $orientation,
        ])->setPaper($paper, $orientation);

        $filenameScope = $cashier ? '-' . str($cashier->name)->slug() : '';
        $filenameType = match ($type) {
            'queue_fees' => '-queue-fees',
            'topups'     => '-topups',
            default      => '',
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
                'records'    => $queueFees->count() + $topUps->count(),
            ],
        ]);

        return $pdf->download($filename);
    }
}