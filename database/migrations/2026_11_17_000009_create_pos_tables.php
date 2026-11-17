<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('register_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 32);
            $table->decimal('opening_float', 12, 2);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['register_id', 'status']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'register_id'])->references(['tenant_id', 'id'])->on('cash_registers');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('shift_id');
            $table->string('kind', 32);
            $table->decimal('amount', 12, 2);
            $table->decimal('signed_amount', 12, 2);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('shift_id');
            $table->foreign(['tenant_id', 'shift_id'])->references(['tenant_id', 'id'])->on('cashier_shifts');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('order_id');
            $table->string('method', 32);
            $table->string('status', 32);
            $table->decimal('amount', 12, 2);
            $table->char('currency_code', 3);
            $table->string('provider', 32)->nullable();
            $table->string('provider_ref', 120)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['order_id', 'status']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
        });

        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('header_text', 255)->nullable();
            $table->string('footer_text', 255)->nullable();
            $table->unsignedSmallInteger('paper_width')->default(80);
            $table->boolean('show_tax')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'cashier_shift_id'])->references(['tenant_id', 'id'])->on('cashier_shifts');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'cashier_shift_id']);
        });
        Schema::dropIfExists('receipt_templates');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('cash_registers');
    }
};
