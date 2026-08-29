<?php

namespace App\Http\Controllers;

use App\Models\Queue;
use App\Services\AuditLogsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DispatchLogExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'from'         => 'nullable|date',
            'to'           => 'nullable|date|after_or_equal:from',
            'vehicle_type' => 'nullable|string',
            'route'        => 'nullable|string',
            'status'       => 'nullable|in:departed,queued',
        ]);

        $from = Carbon::parse($validated['from'] ?? today())->startOfDay();
        $to   = Carbon::parse($validated['to'] ?? $validated['from'] ?? today())->endOfDay();

        $records = Queue::query()
            ->whereBetween('time_queued', [$from, $to])
            ->when(!empty($validated['vehicle_type']), fn ($q) => $q->where('vehicle_type', $validated['vehicle_type']))
            ->when(!empty($validated['route']), fn ($q) => $q->where('destination', $validated['route']))
            ->when(($validated['status'] ?? null) === 'departed', fn ($q) => $q->whereNotNull('time_departed'))
            ->when(($validated['status'] ?? null) === 'queued', fn ($q) => $q->whereNull('time_departed'))
            ->orderBy('time_queued')
            ->get();

        // Group by destination so the printed log reads like the old logbook —
        // one section per route, in departure order.
        $grouped = $records->groupBy('destination');

        $scopeParts = array_filter([
            !empty($validated['vehicle_type']) ? $validated['vehicle_type'] : null,
            !empty($validated['route']) ? $validated['route'] : null,
        ]);

        $pdf = Pdf::loadView('pdf.dispatch-log', [
            'grouped'     => $grouped,
            'from'        => $from,
            'to'          => $to,
            'totalCount'  => $records->count(),
            'generatedBy' => auth()->user()?->name ?? 'System',
            'generatedAt' => now(),
            'scopeNote'   => $scopeParts ? implode(' • ', $scopeParts) : null,
        ])->setPaper('legal', 'portrait');

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Dispatch Log',
            'subject'  => 'Dispatch log exported as PDF',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'   => $request->ip(),
                'from'         => $from->toDateString(),
                'to'           => $to->toDateString(),
                'vehicle_type' => $validated['vehicle_type'] ?? 'All',
                'route'        => $validated['route'] ?? 'All',
                'records'      => $records->count(),
            ],
        ]);

        $filename = $from->isSameDay($to)
            ? 'dispatch-log-' . $from->format('Y-m-d') . '.pdf'
            : 'dispatch-log-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
