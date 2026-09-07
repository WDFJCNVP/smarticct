<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'search'      => 'nullable|string',
            'action'      => 'nullable|string',
            'channel'     => 'nullable|string',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'paper'       => 'nullable|in:letter,legal,a4',
            'orientation' => 'nullable|in:portrait,landscape',
            'preview'     => 'nullable|boolean',
        ]);

        $paper       = $validated['paper'] ?? 'legal';
        $orientation = $validated['orientation'] ?? 'portrait';
        $isPreview   = $request->boolean('preview');

        // Blank range = all time, same convention as the operators export —
        // an audit trail is exactly the kind of report someone may need in
        // full for an investigation, so it shouldn't default to "today".
        $from = !empty($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to   = !empty($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;

        $logs = AuditLog::with('user')
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('subject', 'like', '%' . $validated['search'] . '%')
                      ->orWhereHas('user', function ($u) use ($validated) {
                          $u->where('name', 'like', '%' . $validated['search'] . '%')
                            ->orWhere('user_code', 'like', '%' . $validated['search'] . '%');
                      });
                });
            })
            ->when(!empty($validated['action']), fn ($q) => $q->where('action', $validated['action']))
            ->when(!empty($validated['channel']), fn ($q) => $q->where('channel', $validated['channel']))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->orderBy('created_at')
            ->get();

        $scopeParts = array_filter([
            !empty($validated['action']) ? 'Action: ' . $validated['action'] : null,
            !empty($validated['channel']) ? 'Channel: ' . $validated['channel'] : null,
            !empty($validated['search']) ? 'Search: "' . $validated['search'] . '"' : null,
        ]);

        $pdf = Pdf::loadView('pdf.audit-logs', [
            'logs'        => $logs,
            'from'        => $from,
            'to'          => $to,
            'totalCount'  => $logs->count(),
            'generatedBy' => auth()->user()?->name ?? 'System',
            'generatedAt' => now(),
            'scopeNote'   => $scopeParts ? implode(' • ', $scopeParts) : null,
            'paper'       => $paper,
            'orientation' => $orientation,
        ])->setPaper($paper, $orientation);

        $filename = ($from && $to)
            ? 'audit-log-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.pdf'
            : 'audit-log-' . now()->format('Y-m-d') . '.pdf';

        // Previewing (the modal's live iframe) just renders the PDF inline —
        // it isn't a real export yet, so it shouldn't show up in the audit
        // trail or count as an actual download.
        if ($isPreview) {
            return $pdf->stream($filename);
        }

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Audit Logs',
            'subject'  => 'Audit log exported as PDF',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => $request->ip(),
                'from'       => $from?->toDateString() ?? 'All time',
                'to'         => $to?->toDateString() ?? 'All time',
                'action'     => $validated['action'] ?? 'All',
                'channel'    => $validated['channel'] ?? 'All',
                'records'    => $logs->count(),
            ],
        ]);

        return $pdf->download($filename);
    }
}