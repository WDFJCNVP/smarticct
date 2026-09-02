<?php

namespace App\Http\Controllers;

use App\Models\OperatorTicketRate;
use App\Services\AuditLogsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class RouteFareExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'paper'       => 'nullable|in:letter,legal,a4',
            'orientation' => 'nullable|in:portrait,landscape',
            'preview'     => 'nullable|boolean',
        ]);

        $paper       = $validated['paper'] ?? 'legal';
        $orientation = $validated['orientation'] ?? 'portrait';
        $isPreview   = $request->boolean('preview');

        $vehicleTypes = OperatorTicketRate::query()
            ->with(['routeList' => function ($q) {
                $q->withCount('vehicles')->orderBy('terminal');
            }])
            ->orderBy('vehicle_type')
            ->get();

        $pdf = Pdf::loadView('pdf.route-fares', [
            'vehicleTypes' => $vehicleTypes,
            'generatedBy'  => auth()->user()?->name ?? 'System',
            'generatedAt'  => now(),
            'paper'        => $paper,
            'orientation'  => $orientation,
        ])->setPaper($paper, $orientation);

        $filename = 'routes-fares-' . now()->format('Y-m-d') . '.pdf';

        // Previewing (the modal's live iframe) just renders the PDF inline —
        // it isn't a real export yet, so it shouldn't show up in the audit
        // trail or count as an actual download.
        if ($isPreview) {
            return $pdf->stream($filename);
        }

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Routes & Fares',
            'subject'  => 'Routes and fare matrix exported as PDF',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => $request->ip(),
            ],
        ]);

        return $pdf->download($filename);
    }
}
