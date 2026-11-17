<?php

namespace App\Http\Controllers;

use App\Domain\Menu\Actions\BuildPublicMenu;
use App\Models\DiningTable;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicTablePageController extends Controller
{
    public function show(
        Request $request,
        string $slug,
        string $tablePublicId,
        PublicOrderPageController $orderPage,
    ): View {
        $tenant = Tenant::query()->where('slug', $slug)->first() ?? abort(404);

        if ($tenant->status !== 'active') {
            abort(404);
        }

        app(TenantContext::class)->set($tenant->id);
        app(FeatureGate::class)->flush();

        if (! app(FeatureGate::class)->allows('module.online_ordering')) {
            abort(404);
        }

        $table = DiningTable::query()->where('public_id', $tablePublicId)->with('branch')->first();

        if ($table === null) {
            abort(404);
        }

        $branchPublicId = (string) $request->query('branch', $table->branch->public_id);

        if ($branchPublicId !== $table->branch->public_id) {
            abort(404);
        }

        $menu = $orderPage->orderContext(
            $slug,
            $table->branch->public_id,
            app(BuildPublicMenu::class),
            app(FeatureGate::class),
        );

        $session = TableSession::query()
            ->where('table_id', $table->id)
            ->where('status', 'open')
            ->first();

        return view('public.table.order', $menu + [
            'table' => $table,
            'session' => $session,
            'quote_url' => route('api.public.table.orders.quote', [
                'slug' => $slug,
                'tablePublicId' => $table->public_id,
            ]),
            'order_url' => route('api.public.table.orders.store', [
                'slug' => $slug,
                'tablePublicId' => $table->public_id,
            ]),
        ]);
    }
}
