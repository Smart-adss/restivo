<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->decimal('fee', 12, 2);
            $table->decimal('min_order', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'branch_id', 'is_active']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('code', 40);
            $table->string('kind', 32);
            $table->decimal('value', 12, 2);
            $table->decimal('min_order', 12, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_phone')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('coupon_branches', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('branch_id');

            $table->primary(['coupon_id', 'branch_id']);
            $table->foreign(['tenant_id', 'coupon_id'])->references(['tenant_id', 'id'])->on('coupons');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('branch_order_sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->primary();
            $table->foreignId('tenant_id');
            $table->unsignedInteger('last_number')->default(0);

            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedInteger('number');
            $table->string('type', 32);
            $table->string('source', 32);
            $table->string('status', 32);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('table_session_id')->nullable();
            $table->unsignedBigInteger('cashier_shift_id')->nullable();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->json('delivery_address')->nullable();
            $table->string('notes', 500)->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->char('currency_code', 3);
            $table->boolean('prices_include_tax')->default(false);
            $table->char('idempotency_key', 26);
            $table->dateTime('placed_at');
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['branch_id', 'number']);
            $table->index(['tenant_id', 'branch_id', 'status', 'placed_at']);
            $table->index(['tenant_id', 'branch_id', 'placed_at']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'delivery_zone_id'])->references(['tenant_id', 'id'])->on('delivery_zones');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('options_total', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('tax_rate_percent', 6, 3)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->string('notes', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index('order_id');
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
        });

        Schema::create('order_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('order_item_id');
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->timestamps();

            $table->foreign(['tenant_id', 'order_item_id'])->references(['tenant_id', 'id'])->on('order_items');
        });

        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('order_id');
            $table->string('kind', 32);
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('label', 190);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
            $table->foreign(['tenant_id', 'coupon_id'])->references(['tenant_id', 'id'])->on('coupons');
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('order_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('order_id');
            $table->string('phone', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index('coupon_id');
            $table->index(['tenant_id', 'phone']);
            $table->foreign(['tenant_id', 'coupon_id'])->references(['tenant_id', 'id'])->on('coupons');
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('branch_order_sequences');
        Schema::dropIfExists('coupon_branches');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('delivery_zones');
    }
};
