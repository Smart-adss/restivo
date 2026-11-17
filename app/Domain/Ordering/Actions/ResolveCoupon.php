<?php

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Support\InvalidCoupon;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

class ResolveCoupon
{
    /**
     * @return array{kind: string, coupon_id: int, label: string, amount: string, coupon: Coupon}
     */
    public function handle(
        string $code,
        Branch $branch,
        string $grossSubtotal,
        ?string $phone = null,
    ): array {
        $coupon = Coupon::query()
            ->where('code', $code)
            ->lockForUpdate()
            ->first();

        if ($coupon === null || ! $coupon->is_active) {
            throw new InvalidCoupon('The coupon is inactive or missing.');
        }

        $now = now();

        if ($coupon->starts_at !== null && $now->lt($coupon->starts_at)) {
            throw new InvalidCoupon('The coupon has not started yet.');
        }

        if ($coupon->ends_at !== null && $now->gt($coupon->ends_at)) {
            throw new InvalidCoupon('The coupon has expired.');
        }

        $branchIds = DB::table('coupon_branches')
            ->where('coupon_id', $coupon->id)
            ->pluck('branch_id');

        if ($branchIds->isNotEmpty() && ! $branchIds->contains($branch->id)) {
            throw new InvalidCoupon('The coupon is not valid for this branch.');
        }

        if ($coupon->min_order !== null && Money::compare($grossSubtotal, (string) $coupon->min_order) === -1) {
            throw new InvalidCoupon('The order is below the coupon minimum.');
        }

        if ($coupon->max_uses !== null) {
            $uses = CouponRedemption::query()->where('coupon_id', $coupon->id)->count();

            if ($uses >= $coupon->max_uses) {
                throw new InvalidCoupon('The coupon has reached its usage limit.');
            }
        }

        if ($coupon->max_uses_per_phone !== null) {
            if ($phone === null || $phone === '') {
                throw new InvalidCoupon('A phone number is required for this coupon.');
            }

            $phoneUses = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('phone', $phone)
                ->count();

            if ($phoneUses >= $coupon->max_uses_per_phone) {
                throw new InvalidCoupon('This phone has used the coupon too many times.');
            }
        }

        $amount = match ($coupon->kind) {
            'percent' => Money::percent($grossSubtotal, (string) $coupon->value),
            'fixed' => Money::of((string) $coupon->value),
            default => throw new InvalidCoupon('Unsupported coupon kind.'),
        };

        if (Money::compare($amount, $grossSubtotal) === 1) {
            $amount = Money::of($grossSubtotal);
        }

        if (Money::compare($amount, '0') !== 1) {
            throw new InvalidCoupon('The coupon produces no discount.');
        }

        return [
            'kind' => 'coupon',
            'coupon_id' => $coupon->id,
            'label' => $coupon->code,
            'amount' => $amount,
            'coupon' => $coupon,
        ];
    }
}
