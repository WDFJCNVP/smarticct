<?php

namespace App\Http\Controllers;

use App\Models\CardTransaction;
use App\Models\User;
use App\Services\AuditLogsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CardStatementExportController extends Controller
{
    public function export(Request $request, User $user)
    {
        $validated = $request->validate([
            'paper'       => 'nullable|in:letter,legal,a4',
            'orientation' => 'nullable|in:portrait,landscape',
            'preview'     => 'nullable|boolean',
        ]);

        $paper       = $validated['paper'] ?? 'legal';
        $orientation = $validated['orientation'] ?? 'portrait';
        $isPreview   = $request->boolean('preview');

        $user->load('card');

        if (!$user->card) {
            abort(404, 'This user does not have a card on file.');
        }

        $transactions = CardTransaction::where('card_id', $user->card->id)
            ->orderBy('transaction_time')
            ->get();

        $pdf = Pdf::loadView('pdf.card-statement', [
            'cardholder'   => $user,
            'transactions' => $transactions,
            'closingBalance' => $transactions->last()?->balance_after ?? $user->card->balance,
            'generatedBy' => auth()->user()?->name ?? 'System',
            'generatedAt' => now(),
            'paper'       => $paper,
            'orientation' => $orientation,
        ])->setPaper($paper, $orientation);

        $filename = 'card-statement-' . str($user->user_code ?: $user->id)->slug() . '-' . now()->format('Y-m-d') . '.pdf';

        // Previewing (the modal's live iframe) just renders the PDF inline —
        // it isn't a real export yet, so it shouldn't show up in the audit
        // trail or count as an actual download.
        if ($isPreview) {
            return $pdf->stream($filename);
        }

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Export Card Statement',
            'subject'  => "Card statement exported as PDF for {$user->name} ({$user->user_code})",
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'   => $request->ip(),
                'cardholder'   => $user->name,
                'card_number'  => $user->card->card_number,
                'records'      => $transactions->count(),
            ],
        ]);

        return $pdf->download($filename);
    }
}
