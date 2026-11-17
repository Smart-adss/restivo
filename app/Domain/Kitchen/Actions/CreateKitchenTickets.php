<?php

namespace App\Domain\Kitchen\Actions;

use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\Product;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Collection;

class CreateKitchenTickets
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @return list<KitchenTicket>
     */
    public function handle(Order $order): array
    {
        if (! $this->features->allows('module.kitchen')) {
            return [];
        }

        if (KitchenTicket::query()->where('order_id', $order->id)->exists()) {
            return KitchenTicket::query()
                ->where('order_id', $order->id)
                ->with('items')
                ->get()
                ->all();
        }

        $order->loadMissing(['items']);

        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $grouped = [];

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $product = $products->get($item->product_id);

            if ($product === null || $product->prep_station_id === null) {
                continue;
            }

            $stationId = (int) $product->prep_station_id;
            $grouped[$stationId][] = $item;
        }

        $tickets = [];

        foreach ($grouped as $stationId => $items) {
            $ticket = KitchenTicket::query()->create([
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'station_id' => $stationId,
                'status' => 'queued',
            ]);

            foreach ($items as $item) {
                KitchenTicketItem::query()->create([
                    'ticket_id' => $ticket->id,
                    'order_item_id' => $item->id,
                    'quantity' => $item->quantity,
                    'name_ar' => $item->name_ar,
                    'name_en' => $item->name_en,
                    'notes' => $item->notes,
                ]);
            }

            $tickets[] = $ticket->load('items');
        }

        return $tickets;
    }
}
