<?php

namespace App\Domain\Menu\Support;

use App\Models\Branch;
use Illuminate\Support\Facades\Cache;

class PublicMenuCache
{
    public const TTL_SECONDS = 60;

    public static function key(int $branchId): string
    {
        return 'public_menu:branch:'.$branchId;
    }

    public static function forget(int $branchId): void
    {
        Cache::forget(self::key($branchId));
    }

    public static function forgetMany(iterable $branchIds): void
    {
        foreach ($branchIds as $branchId) {
            self::forget((int) $branchId);
        }
    }

    public static function forgetTenant(): void
    {
        self::forgetMany(Branch::query()->pluck('id'));
    }
}
