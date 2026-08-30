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
        ])->setPaper('legal', 'portrait');

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

        $filename = 'card-statement-' . str($user->user_code ?: $user->id)->slug() . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
