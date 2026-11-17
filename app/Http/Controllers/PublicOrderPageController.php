<?php

namespace App\Http\Controllers;

use App\Domain\Menu\Actions\BuildPublicMenu;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicOrderPageController extends Controller
{
    public function track(Request $request, string $slug, string $orderPublicId): View
    {
        $tenant = Tenant::query()->where('slug', $slug)->first() ?? abort(404);

        if ($tenant->status !== 'active') {
            abort(404);
        }

        app(TenantContext::class)->set($tenant->id);
        app(FeatureGate::class)->flush();

        $phone = (string) $request->query('phone', '');

        if ($phone === '') {
            abort(404);
        }

        $order = Order::query()
            ->where('public_id', $orderPublicId)
            ->where('customer_phone', $phone)
            ->with(['items.options'])
            ->first();

        if ($order === null) {
            abort(404);
        }

        $statuses = ['placed', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'completed', 'cancelled'];
        $statusLabels = [];
        foreach ($statuses as $status) {
            $statusLabels[$status] = __('app.order_status_'.$status);
        }

        return view('public.orders.track', [
            'tenant' => $tenant,
            'order' => $order,
            'phone' => $phone,
            'status_labels' => $statusLabels,
            'poll_url' => route('api.public.orders.show', [
                'slug' => $slug,
                'orderPublicId' => $order->public_id,
                'phone' => $phone,
            ]),
        ]);
    }

    public function orderContext(string $slug, string $branchPublicId, BuildPublicMenu $buildPublicMenu, FeatureGate $features): array
    {
        $menu = $buildPublicMenu->handle($slug, $branchPublicId);

        if (! $features->allows('module.online_ordering')) {
            return $menu + [
                'ordering_enabled' => false,
                'delivery_zones' => [],
                'catalog' => [],
            ];
        }

        /** @var Branch $branch */
        $branch = $menu['branch'];

        $zones = DeliveryZone::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (DeliveryZone $zone): array => [
                'public_id' => $zone->public_id,
                'name_ar' => $zone->name_ar,
                'name_en' => $zone->name_en,
                'fee' => (string) $zone->fee,
                'min_order' => $zone->min_order !== null ? (string) $zone->min_order : null,
            ])
            ->all();

        $catalog = Product::query()
            ->with([
                'branchSettings',
                'optionGroups' => fn ($q) => $q->orderBy('sort_order')->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')->with('branchSettings')]),
            ])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Product $product) => $product->isAvailableAt($branch))
            ->map(function (Product $product) use ($branch): array {
                return [
                    'public_id' => $product->public_id,
                    'name_ar' => $product->name_ar,
                    'name_en' => $product->name_en,
                    'price' => $product->priceAt($branch),
                    'option_groups' => $product->optionGroups->map(function ($group) use ($branch): array {
                        return [
                            'public_id' => $group->public_id,
                            'name_ar' => $group->name_ar,
                            'name_en' => $group->name_en,
                            'selection' => $group->selection,
                            'is_required' => $group->is_required,
                            'min_select' => $group->min_select,
                            'max_select' => $group->max_select,
                            'options' => $group->options
                                ->filter(fn ($option) => $option->isAvailableAt($branch))
                                ->map(fn ($option): array => [
                                    'public_id' => $option->public_id,
                                    'name_ar' => $option->name_ar,
                                    'name_en' => $option->name_en,
                                    'price_delta' => $option->priceDeltaAt($branch),
                                ])
                                ->values()
                                ->all(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();

        return $menu + [
            'ordering_enabled' => true,
            'delivery_zones' => $zones,
            'catalog' => $catalog,
        ];
    }
}
