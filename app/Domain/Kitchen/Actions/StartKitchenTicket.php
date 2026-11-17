<?php

namespace App\Domain\Kitchen\Actions;

use App\Models\KitchenTicket;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StartKitchenTicket
{
    public function __construct(private FeatureGate $features) {}

    public function handle(KitchenTicket $ticket): KitchenTicket
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('kitchen.tickets.update'), 403);

        if (! $this->features->allows('module.kitchen')) {
            abort(404);
        }

        return DB::transaction(function () use ($ticket): KitchenTicket {
            $locked = KitchenTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'queued') {
                throw new HttpException(409, 'Only queued tickets can be started.');
            }

            $locked->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            return $locked->fresh(['items', 'station', 'order']);
        });
    }
}
