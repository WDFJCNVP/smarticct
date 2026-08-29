<?php

namespace App\Http\Controllers;

use App\Exports\OperatorsExport;
use App\Services\AuditLogsService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class OperatorsExportController extends Controller
{
    public function export(Request $request)
    {
        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Operators',
            'subject'  => 'Registered operators exported to Excel',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => $request->ip(),
            ],
        ]);

        return Excel::download(new OperatorsExport, 'operators-' . now()->format('Y-m-d') . '.xlsx');
    }
}
