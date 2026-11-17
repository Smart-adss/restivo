<?php

namespace App\Domain\Tables\Actions;

use App\Models\DiningTable;
use App\Models\TableSession;
use App\Models\TableTransfer;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TransferTableSession
{
    public function __construct(private FeatureGate $features) {}

    public function handle(TableSession $session, int $toTableId): TableSession
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('tables.sessions.manage'), 403);

        return DB::transaction(function () use ($session, $toTableId): TableSession {
            $locked = TableSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new HttpException(409, 'Only an open session can be transferred.');
            }

            $from = DiningTable::query()->whereKey($locked->table_id)->lockForUpdate()->firstOrFail();
            $to = DiningTable::query()->whereKey($toTableId)->lockForUpdate()->first();

            if ($to === null || (int) $to->branch_id !== (int) $from->branch_id) {
                throw new HttpException(422, 'The destination table is unavailable.');
            }

            if ((int) $to->id === (int) $from->id) {
                throw new HttpException(422, 'The destination table must be different.');
            }

            if ($to->status !== 'available') {
                throw new HttpException(409, 'The destination table is occupied.');
            }

            $existingOpen = TableSession::query()
                ->where('table_id', $to->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->exists();

            if ($existingOpen) {
                throw new HttpException(409, 'The destination table is occupied.');
            }

            TableTransfer::query()->create([
                'table_session_id' => $locked->id,
                'from_table_id' => $from->id,
                'to_table_id' => $to->id,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);

            $locked->update(['table_id' => $to->id]);
            $from->update(['status' => 'available']);
            $to->update(['status' => 'occupied']);

            return $locked->fresh(['table']);
        });
    }
}
