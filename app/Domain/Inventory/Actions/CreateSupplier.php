<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Supplier;
use App\Support\Features\FeatureGate;

class CreateSupplier
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{name: string, phone: string, email?: ?string, tax_number?: ?string, address?: ?string, is_active?: bool}  $data
     */
    public function handle(array $data): Supplier
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('purchasing.orders.manage'), 403);
        abort_unless($this->features->allows('module.inventory'), 404);

        return Supplier::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
