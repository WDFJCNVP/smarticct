<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\AuditLogsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OperatorFleetExportController extends Controller
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

        $vehicles = Vehicle::with('route_list')
            ->where('user_id', auth()->id())
            ->orderBy('plate_number')
            ->get();

        $pdf = Pdf::loadView('pdf.operator-fleet', [
            'operator'    => auth()->user(),
            'vehicles'    => $vehicles,
            'generatedBy' => auth()->user()?->name ?? 'System',
            'generatedAt' => now(),
            'paper'       => $paper,
            'orientation' => $orientation,
        ])->setPaper($paper, $orientation);

        $filename = 'my-fleet-' . now()->format('Y-m-d') . '.pdf';

        if ($isPreview) {
            return $pdf->stream($filename);
        }

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Fleet Summary',
            'subject'  => 'Operator exported their own fleet/compliance summary as PDF',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => $request->ip(),
                'records'    => $vehicles->count(),
            ],
        ]);

        return $pdf->download($filename);
    }
}
