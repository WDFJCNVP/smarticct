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
        ])->setPaper('legal', 'portrait');

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Routes & Fares',
            'subject'  => 'Routes and fare matrix exported as PDF',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => $request->ip(),
            ],
        ]);

        return $pdf->download('routes-fares-' . now()->format('Y-m-d') . '.pdf');
    }
}
