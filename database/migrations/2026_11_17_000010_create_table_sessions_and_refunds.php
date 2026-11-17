<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('table_id');
            $table->unsignedSmallInteger('guest_count')->default(1);
            $table->string('status', 32);
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['table_id', 'status']);
            $table->foreign(['tenant_id', 'table_id'])->references(['tenant_id', 'id'])->on('dining_tables');
        });

        Schema::create('table_session_orders', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('table_session_id');
            $table->unsignedBigInteger('order_id');

            $table->primary(['table_session_id', 'order_id']);
            $table->foreign(['tenant_id', 'table_session_id'])->references(['tenant_id', 'id'])->on('table_sessions');
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
        });

        Schema::create('bill_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('order_id');
            $table->string('mode', 32);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
        });

        Schema::create('bill_split_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('split_id');
            $table->string('label', 120);
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'split_id'])->references(['tenant_id', 'id'])->on('bill_splits');
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments');
        });

        Schema::create('table_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('table_session_id');
            $table->unsignedBigInteger('from_table_id');
            $table->unsignedBigInteger('to_table_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'table_session_id'])->references(['tenant_id', 'id'])->on('table_sessions');
            $table->foreign(['tenant_id', 'from_table_id'])->references(['tenant_id', 'id'])->on('dining_tables');
            $table->foreign(['tenant_id', 'to_table_id'])->references(['tenant_id', 'id'])->on('dining_tables');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->string('status', 32);
            $table->char('idempotency_key', 26);
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments');
            $table->foreign(['tenant_id', 'order_id'])->references(['tenant_id', 'id'])->on('orders');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'table_session_id'])->references(['tenant_id', 'id'])->on('table_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'table_session_id']);
        });
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('table_transfers');
        Schema::dropIfExists('bill_split_parts');
        Schema::dropIfExists('bill_splits');
        Schema::dropIfExists('table_session_orders');
        Schema::dropIfExists('table_sessions');
    }
};
