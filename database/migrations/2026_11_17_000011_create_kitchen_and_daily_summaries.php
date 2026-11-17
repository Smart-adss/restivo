<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prep_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('kind', 32);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['branch_id', 'is_active', 'sort_order']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'prep_station_id'])->references(['tenant_id', 'id'])->on('prep_stations');
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('station_id');
            $table->string('status', 32);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['branch_id', 'station_id', 'status', 'updated_at']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
            $table->foreign(['tenant_id', 'station_id'])->references(['tenant_id', 'id'])->on('prep_stations');
        });

        Schema::create('kitchen_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->decimal('quantity', 10, 3);
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'ticket_id'])->references(['tenant_id', 'id'])->on('kitchen_tickets');
            $table->foreign(['tenant_id', 'order_item_id'])->references(['tenant_id', 'id'])->on('order_items');
        });

        Schema::create('daily_branch_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('branch_id');
            $table->date('business_date');
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('gross_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('net_total', 12, 2)->default(0);
            $table->decimal('cash_total', 12, 2)->default(0);
            $table->decimal('electronic_total', 12, 2)->default(0);
            $table->decimal('refund_total', 12, 2)->default(0);
            $table->decimal('delivery_fee_total', 12, 2)->default(0);
            $table->dateTime('calculated_at');
            $table->timestamps();

            $table->unique(['branch_id', 'business_date']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_branch_summaries');
        Schema::dropIfExists('kitchen_ticket_items');
        Schema::dropIfExists('kitchen_tickets');
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'prep_station_id']);
        });
        Schema::dropIfExists('prep_stations');
    }
};
