<?php

namespace App\Domain\Ordering;

final class OrderStatusMachine
{
    public const PLACED = 'placed';

    public const ACCEPTED = 'accepted';

    public const PREPARING = 'preparing';

    public const READY = 'ready';

    public const OUT_FOR_DELIVERY = 'out_for_delivery';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function allowedNext(string $from, string $orderType): array
    {
        return match ($from) {
            self::PLACED => [self::ACCEPTED, self::CANCELLED],
            self::ACCEPTED => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::READY],
            self::READY => $orderType === 'delivery'
                ? [self::OUT_FOR_DELIVERY, self::COMPLETED]
                : [self::COMPLETED],
            self::OUT_FOR_DELIVERY => [self::COMPLETED],
            default => [],
        };
    }

    public static function canTransition(string $from, string $to, string $orderType): bool
    {
        return in_array($to, self::allowedNext($from, $orderType), true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::COMPLETED, self::CANCELLED], true);
    }
}
