<?php

namespace App\Domain\Tables\Actions;

use App\Models\DiningTable;
use App\Models\TableSession;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OpenTableSession
{
    public function __construct(private FeatureGate $features) {}

    public function handle(DiningTable $table, int $guestCount = 1): TableSession
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('tables.sessions.manage'), 403);

        if ($guestCount < 1 || $guestCount > 30) {
            throw new HttpException(422, 'Guest count must be between 1 and 30.');
        }

        return DB::transaction(function () use ($table, $guestCount): TableSession {
            $locked = DiningTable::query()->whereKey($table->id)->lockForUpdate()->firstOrFail();

            $open = TableSession::query()
                ->where('table_id', $locked->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                throw new HttpException(409, 'The table already has an open session.');
            }

            if ($locked->status === 'occupied') {
                throw new HttpException(409, 'The table is occupied.');
            }

            $session = TableSession::query()->create([
                'table_id' => $locked->id,
                'guest_count' => $guestCount,
                'status' => 'open',
                'opened_at' => now(),
            ]);

            $locked->update(['status' => 'occupied']);

            return $session->fresh(['table']);
        });
    }
}
