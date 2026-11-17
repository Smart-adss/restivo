<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp;
use Illuminate\View\View;

class PublicReservationPageController extends Controller
{
    public function show(string $slug, string $branchPublicId, FeatureGate $features): View
    {
        $tenant = Tenant::query()->where('slug', $slug)->first() ?? abort(404);

        if ($tenant->status !== 'active') {
            abort(404);
        }

        app(TenantContext::class)->set($tenant->id);
        $features->flush();

        if (! $features->allows('module.reservations')) {
            abort(404);
        }

        $branch = Branch::query()
            ->where('public_id', $branchPublicId)
            ->where('is_active', true)
            ->first() ?? abort(404);

        $waText = __('app.reservation_wa_guest_text', [
            'branch' => $branch->name(),
            'tenant' => $tenant->displayName(),
        ]);

        return view('public.reservations.show', [
            'tenant' => $tenant,
            'branch' => $branch,
            'slots_url' => route('api.public.reservations.slots', [
                'slug' => $tenant->slug,
                'branchPublicId' => $branch->public_id,
            ]),
            'store_url' => route('api.public.reservations.store', ['slug' => $tenant->slug]),
            'whatsapp_url' => WhatsApp::meUrl($tenant->phone, $waText),
        ]);
    }
}
