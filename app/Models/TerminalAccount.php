<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminalAccount extends Model
{
    protected $fillable = ['name', 'balance', 'total_earned'];

    // Always call inside DB::transaction
    public static function locked(): self
    {
        return static::where('name', 'main')->lockForUpdate()->firstOrFail();
    }

    public static function credit(float $amount): void
    {
        $a = static::locked();
        $a->update([
            'balance'      => round((float) $a->balance + $amount, 2),
            'total_earned' => round((float) $a->total_earned + $amount, 2),
        ]);
    }

    public static function debit(float $amount): void
    {
        $a = static::locked();

        if ((float) $a->balance < $amount) {
            throw new \RuntimeException('Insufficient terminal balance.', 422);
        }

        $a->update(['balance' => round((float) $a->balance - $amount, 2)]);
    }

    public function pendingWithdrawals(): float
    {
        return (float) CardTransaction::where('transaction_type', 'admin_withdrawal')
            ->where('status', 'pending')
            ->sum('amount');
    }

    public function available(): float
    {
        return max(0.0, round((float) $this->balance - $this->pendingWithdrawals(), 2));
    }
}
