<?php

namespace App\Domain\Menu\Actions;

use App\Models\Branch;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode as QrRenderer;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

class EnsureMenuQrCode
{
    public function __construct(private FeatureGate $features) {}

    public function handle(Branch $branch, bool $force = false): QrCode
    {
        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }

        $existing = QrCode::query()
            ->where('target_type', 'menu')
            ->where('target_public_id', $branch->public_id)
            ->first();

        if ($existing && ! $force && Storage::disk('local')->exists($existing->image_path)) {
            return $existing;
        }

        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
        $url = route('public.menu.show', [
            'slug' => $tenant->slug,
            'branchPublicId' => $branch->public_id,
        ]);

        $options = new QROptions;
        $options->outputInterface = QRGdImagePNG::class;
        $options->outputBase64 = false;
        $options->scale = 8;

        $binary = (new QrRenderer($options))->render($url);
        $relative = 'tenants/'.$tenant->public_id.'/qr/menu-'.$branch->public_id.'.png';
        Storage::disk('local')->put($relative, $binary);

        if ($existing) {
            if ($existing->image_path !== $relative) {
                Storage::disk('local')->delete($existing->image_path);
            }

            $existing->update([
                'image_path' => $relative,
                'branch_id' => $branch->id,
            ]);

            return $existing->refresh();
        }

        return QrCode::query()->create([
            'branch_id' => $branch->id,
            'target_type' => 'menu',
            'target_public_id' => $branch->public_id,
            'image_path' => $relative,
        ]);
    }
}
