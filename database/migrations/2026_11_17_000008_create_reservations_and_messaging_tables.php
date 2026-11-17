<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('branch_id');
            $table->char('public_id', 26)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('name', 80);
            $table->unsignedSmallInteger('seats')->default(2);
            $table->string('status', 32)->default('available');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['branch_id', 'name']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'area_id'])->references(['tenant_id', 'id'])->on('dining_areas');
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            $table->unsignedSmallInteger('guest_count');
            $table->dateTime('reserved_at');
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->string('status', 32);
            $table->string('notes', 500)->nullable();
            $table->string('source', 32);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['branch_id', 'reserved_at', 'status']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
        });

        Schema::create('reservation_tables', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('table_id');

            $table->primary(['reservation_id', 'table_id']);
            $table->foreign(['tenant_id', 'reservation_id'])->references(['tenant_id', 'id'])->on('reservations');
            $table->foreign(['tenant_id', 'table_id'])->references(['tenant_id', 'id'])->on('dining_tables');
        });

        Schema::create('reservation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('reservation_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'reservation_id'])->references(['tenant_id', 'id'])->on('reservations');
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->string('channel', 16);
            $table->string('key', 100);
            $table->text('body_ar');
            $table->text('body_en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'key']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->string('channel', 16);
            $table->string('recipient', 190);
            $table->string('template_key', 100);
            $table->json('payload');
            $table->string('status', 32);
            $table->string('provider', 32)->nullable();
            $table->string('provider_ref', 120)->nullable();
            $table->string('error', 255)->nullable();
            $table->string('dedupe_key', 120)->unique();
            $table->dateTime('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('reservation_status_histories');
        Schema::dropIfExists('reservation_tables');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('dining_areas');
    }
};
