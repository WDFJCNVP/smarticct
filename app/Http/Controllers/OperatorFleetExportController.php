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
        // Scoped strictly to the logged-in operator's own vehicles — this is
        // a "my fleet" printout, not a directory of every operator's fleet
        // (that's OperatorsExport, admin-only).
        $vehicles = Vehicle::with('route_list')
            ->where('user_id', auth()->id())
            ->orderBy('plate_number')
            ->get();

        $pdf = Pdf::loadView('pdf.operator-fleet', [
            'operator'    => auth()->user(),
            'vehicles'    => $vehicles,
            'generatedBy' => auth()->user()?->name ?? 'System',
            'generatedAt' => now(),
        ])->setPaper('legal', 'portrait');

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

        return $pdf->download('my-fleet-' . now()->format('Y-m-d') . '.pdf');
    }
}
