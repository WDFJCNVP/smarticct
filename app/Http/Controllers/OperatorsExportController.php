<?php

namespace App\Http\Controllers;

use App\Exports\OperatorsExport;
use App\Services\AuditLogsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class OperatorsExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'from'         => 'nullable|date',
            'to'           => 'nullable|date|after_or_equal:from',
            'vehicle_type' => 'nullable|string',
            'status'       => 'nullable|in:active,suspended',
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? $validated['from'] ?? null;
        $vehicleType = $validated['vehicle_type'] ?? null;
        $status = $validated['status'] ?? null;

        $rangeStart = $from ? Carbon::parse($from)->startOfDay() : null;
        $rangeEnd = $to ? Carbon::parse($to)->endOfDay() : null;

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Operators',
            'subject'  => 'Registered operators exported to Excel',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'   => $request->ip(),
                'from'         => $rangeStart?->toDateString() ?? 'All time',
                'to'           => $rangeEnd?->toDateString() ?? 'All time',
                'vehicle_type' => $vehicleType ?? 'All',
                'status'       => $status ?? 'All',
            ],
        ]);

        $filenameParts = array_filter([
            'operators',
            $status,
            $vehicleType ? (string) str($vehicleType)->slug() : null,
            $rangeStart ? $rangeStart->format('Y-m-d') . ($rangeEnd && !$rangeStart->isSameDay($rangeEnd) ? '-to-' . $rangeEnd->format('Y-m-d') : '') : null,
        ]);
        $filename = implode('-', $filenameParts) . '.xlsx';

        return Excel::download(new OperatorsExport($rangeStart, $rangeEnd, $vehicleType, $status), $filename);
    }
}