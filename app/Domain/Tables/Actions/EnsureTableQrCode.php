<?php

namespace App\Domain\Tables\Actions;

use App\Models\DiningTable;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode as QrRenderer;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

class EnsureTableQrCode
{
    public function __construct(private FeatureGate $features) {}

    public function handle(DiningTable $table, bool $force = false): QrCode
    {
        if (! $this->features->allows('module.online_ordering')) {
            abort(404);
        }

        abort_unless(auth()->user()?->hasPermission('tables.layout.manage'), 403);

        $existing = QrCode::query()
            ->where('target_type', 'table')
            ->where('target_public_id', $table->public_id)
            ->first();

        if ($existing && ! $force && Storage::disk('local')->exists($existing->image_path)) {
            return $existing;
        }

        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
        $url = route('public.table.show', [
            'slug' => $tenant->slug,
            'tablePublicId' => $table->public_id,
        ]);

        $options = new QROptions;
        $options->outputInterface = QRGdImagePNG::class;
        $options->outputBase64 = false;
        $options->scale = 8;

        $binary = (new QrRenderer($options))->render($url);
        $relative = 'tenants/'.$tenant->public_id.'/qr/table-'.$table->public_id.'.png';
        Storage::disk('local')->put($relative, $binary);

        if ($existing) {
            if ($existing->image_path !== $relative) {
                Storage::disk('local')->delete($existing->image_path);
            }

            $existing->update([
                'image_path' => $relative,
                'branch_id' => $table->branch_id,
            ]);

            return $existing->refresh();
        }

        return QrCode::query()->create([
            'branch_id' => $table->branch_id,
            'target_type' => 'table',
            'target_public_id' => $table->public_id,
            'image_path' => $relative,
        ]);
    }
}
