<?php

namespace App\Domain\Tables\Actions;

use App\Models\DiningTable;
use App\Models\TableSession;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CloseTableSession
{
    public function __construct(private FeatureGate $features) {}

    public function handle(TableSession $session): TableSession
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('tables.sessions.manage'), 403);

        return DB::transaction(function () use ($session): TableSession {
            $locked = TableSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new HttpException(409, 'The session is already closed.');
            }

            $locked->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            DiningTable::query()->whereKey($locked->table_id)->update([
                'status' => 'available',
            ]);

            return $locked->fresh(['table', 'orders']);
        });
    }
}
