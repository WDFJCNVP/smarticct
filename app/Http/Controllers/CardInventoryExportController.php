<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Services\AuditLogsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CardInventoryExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'search'      => 'nullable|string',
            'status'      => 'nullable|in:active,suspended,terminated',
            'paper'       => 'nullable|in:letter,legal,a4',
            'orientation' => 'nullable|in:portrait,landscape',
            'preview'     => 'nullable|boolean',
        ]);

        $paper       = $validated['paper'] ?? 'legal';
        $orientation = $validated['orientation'] ?? 'portrait';
        $isPreview   = $request->boolean('preview');

        $cards = Card::with('user')
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['operator', 'commuter']))
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('card_number', 'like', '%' . $validated['search'] . '%')
                      ->orWhereHas('user', function ($u) use ($validated) {
                          $u->where('name', 'like', '%' . $validated['search'] . '%')
                            ->orWhere('user_code', 'like', '%' . $validated['search'] . '%');
                      });
                });
            })
            ->when(!empty($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->orderBy('created_at')
            ->get();

        $stats = [
            'total'    => $cards->count(),
            'active'   => $cards->where('status', 'active')->count(),
            'inactive' => $cards->where('status', '!=', 'active')->count(),
            'balance'  => $cards->sum('balance'),
        ];

        $scopeParts = array_filter([
            !empty($validated['status']) ? 'Status: ' . ucfirst($validated['status']) : null,
            !empty($validated['search']) ? 'Search: "' . $validated['search'] . '"' : null,
        ]);

        $pdf = Pdf::loadView('pdf.card-inventory', [
            'cards'       => $cards,
            'stats'       => $stats,
            'generatedBy' => auth()->user()?->name ?? 'System',
            'generatedAt' => now(),
            'scopeNote'   => $scopeParts ? implode(' • ', $scopeParts) : null,
            'paper'       => $paper,
            'orientation' => $orientation,
        ])->setPaper($paper, $orientation);

        $filename = 'card-inventory-' . now()->format('Y-m-d') . '.pdf';

        // Previewing (the modal's live iframe) just renders the PDF inline —
        // it isn't a real export yet, so it shouldn't show up in the audit
        // trail or count as an actual download.
        if ($isPreview) {
            return $pdf->stream($filename);
        }

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Card Inventory',
            'subject'  => 'Card inventory exported as PDF',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => $request->ip(),
                'status'     => $validated['status'] ?? 'All',
                'records'    => $cards->count(),
            ],
        ]);

        return $pdf->download($filename);
    }
}
