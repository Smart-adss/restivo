<?php

namespace App\Domain\Reservations;

final class ReservationStatus
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const SEATED = 'seated';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const NO_SHOW = 'no_show';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::CONFIRMED,
            self::SEATED,
            self::COMPLETED,
            self::CANCELLED,
            self::NO_SHOW,
        ];
    }

    public static function isOpen(string $status): bool
    {
        return in_array($status, [self::PENDING, self::CONFIRMED, self::SEATED], true);
    }
}
