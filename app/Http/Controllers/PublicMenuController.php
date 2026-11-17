<?php

namespace App\Http\Controllers;

use App\Domain\Menu\Actions\BuildPublicMenu;
use App\Domain\Menu\Actions\EnsureMenuQrCode;
use App\Models\Branch;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicMenuController extends Controller
{
    public function show(string $slug, string $branchPublicId, BuildPublicMenu $buildPublicMenu): View
    {
        $menu = $buildPublicMenu->handle($slug, $branchPublicId);

        return view('public.menu.show', $menu);
    }

    public function qr(
        string $slug,
        string $branchPublicId,
        EnsureMenuQrCode $ensureMenuQrCode,
        FeatureGate $features,
    ): Response {
        $tenant = Tenant::query()->where('slug', $slug)->first() ?? abort(404);

        if ($tenant->status !== 'active') {
            abort(404);
        }

        app(TenantContext::class)->set($tenant->id);
        $features->flush();

        if (! $features->allows('module.digital_menu')) {
            abort(404);
        }

        $branch = Branch::query()
            ->where('public_id', $branchPublicId)
            ->where('is_active', true)
            ->first() ?? abort(404);

        $qr = $ensureMenuQrCode->handle($branch);

        abort_unless(Storage::disk('local')->exists($qr->image_path), 404);

        return response(Storage::disk('local')->get($qr->image_path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
