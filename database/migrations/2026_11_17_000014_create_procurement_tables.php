<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('name', 190);
            $table->string('phone', 40);
            $table->string('email', 190)->nullable();
            $table->string('tax_number', 64)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('status', 32)->default('draft');
            $table->dateTime('ordered_at')->nullable();
            $table->dateTime('expected_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->char('currency_code', 3)->default('EGP');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'supplier_id'])->references(['tenant_id', 'id'])->on('suppliers');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('received_qty', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['purchase_order_id', 'stock_item_id']);
            $table->foreign(['tenant_id', 'purchase_order_id'])->references(['tenant_id', 'id'])->on('purchase_orders');
            $table->foreign(['tenant_id', 'stock_item_id'])->references(['tenant_id', 'id'])->on('stock_items');
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('branch_id');
            $table->dateTime('received_at');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'purchase_order_id'])->references(['tenant_id', 'id'])->on('purchase_orders');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('purchase_order_line_id');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 12, 4);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'goods_receipt_id'])->references(['tenant_id', 'id'])->on('goods_receipts');
            $table->foreign(['tenant_id', 'purchase_order_line_id'])->references(['tenant_id', 'id'])->on('purchase_order_lines');
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('from_branch_id');
            $table->unsignedBigInteger('to_branch_id');
            $table->string('status', 32)->default('draft');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'from_branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'to_branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['transfer_id', 'stock_item_id']);
            $table->foreign(['tenant_id', 'transfer_id'])->references(['tenant_id', 'id'])->on('stock_transfers');
            $table->foreign(['tenant_id', 'stock_item_id'])->references(['tenant_id', 'id'])->on('stock_items');
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->string('status', 32)->default('draft');
            $table->dateTime('counted_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('count_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->decimal('expected_qty', 14, 3);
            $table->decimal('counted_qty', 14, 3);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['count_id', 'stock_item_id']);
            $table->foreign(['tenant_id', 'count_id'])->references(['tenant_id', 'id'])->on('stock_counts');
            $table->foreign(['tenant_id', 'stock_item_id'])->references(['tenant_id', 'id'])->on('stock_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
