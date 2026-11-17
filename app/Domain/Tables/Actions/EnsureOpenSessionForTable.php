<?php

namespace App\Domain\Tables\Actions;

use App\Models\DiningTable;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureOpenSessionForTable
{
    public function handle(DiningTable $table, int $guestCount = 1): TableSession
    {
        return DB::transaction(function () use ($table, $guestCount): TableSession {
            $locked = DiningTable::query()->whereKey($table->id)->lockForUpdate()->firstOrFail();

            $open = TableSession::query()
                ->where('table_id', $locked->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                return $open;
            }

            if (! in_array($locked->status, ['available', 'needs_cleaning'], true) && $locked->status !== 'occupied') {
                throw new HttpException(409, 'The table cannot accept guest orders right now.');
            }

            $session = TableSession::query()->create([
                'table_id' => $locked->id,
                'guest_count' => max(1, min(30, $guestCount)),
                'status' => 'open',
                'opened_at' => now(),
            ]);

            $locked->update(['status' => 'occupied']);

            return $session;
        });
    }
}
