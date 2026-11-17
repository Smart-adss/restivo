<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->string('abbreviation', 20);
            $table->string('kind', 32);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('from_unit_id');
            $table->unsignedBigInteger('to_unit_id');
            $table->decimal('factor', 16, 6);
            $table->timestamps();

            $table->unique(['tenant_id', 'from_unit_id', 'to_unit_id']);
            $table->foreign(['tenant_id', 'from_unit_id'])->references(['tenant_id', 'id'])->on('units');
            $table->foreign(['tenant_id', 'to_unit_id'])->references(['tenant_id', 'id'])->on('units');
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->string('sku', 64)->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->string('kind', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'sku']);
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units');
        });

        Schema::create('branch_stocks', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('reorder_level', 14, 3)->nullable();
            $table->decimal('avg_cost', 12, 4)->nullable();
            $table->timestamps();

            $table->primary(['branch_id', 'stock_item_id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'stock_item_id'])->references(['tenant_id', 'id'])->on('stock_items');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->string('kind', 32);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['branch_id', 'stock_item_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'stock_item_id'])->references(['tenant_id', 'id'])->on('stock_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('branch_stocks');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('units');
    }
};
