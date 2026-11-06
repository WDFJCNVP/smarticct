<?php

namespace App\Enums;

enum RentalRequestStatus: string
{
    case Pending = 'pending';
    case Declined = 'declined';
    case Quoted = 'quoted';
    case QuoteRejected = 'quote_rejected';
    case AwaitingPayment = 'awaiting_payment';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Declined => 'Declined',
            self::Quoted => 'Quote sent',
            self::QuoteRejected => 'Quote rejected',
            self::AwaitingPayment => 'Awaiting first payment',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    /** Statuses where nothing more can happen. */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Declined,
            self::QuoteRejected,
            self::Completed,
            self::Cancelled,
            self::Expired,
        ], true);
    }
}