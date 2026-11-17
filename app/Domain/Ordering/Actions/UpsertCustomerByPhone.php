<?php

namespace App\Domain\Ordering\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class UpsertCustomerByPhone
{
    public function handle(string $phone, string $name, ?string $addressLine = null, ?string $areaName = null): Customer
    {
        return DB::transaction(function () use ($phone, $name, $addressLine, $areaName): Customer {
            $customer = Customer::query()
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if ($customer === null) {
                $customer = Customer::query()->create([
                    'name' => $name,
                    'phone' => $phone,
                ]);
            } else {
                $customer->update([
                    'name' => $name !== '' ? $name : $customer->name,
                ]);
            }

            if ($addressLine !== null && $addressLine !== '') {
                $existing = $customer->addresses()
                    ->where('address_line', $addressLine)
                    ->first();

                if ($existing === null) {
                    $customer->addresses()->update(['is_default' => false]);
                    $customer->addresses()->create([
                        'label' => $areaName,
                        'area_name' => $areaName,
                        'address_line' => $addressLine,
                        'is_default' => true,
                    ]);
                }
            }

            return $customer->fresh(['addresses']);
        });
    }
}
