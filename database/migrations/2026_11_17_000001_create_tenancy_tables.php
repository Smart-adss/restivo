<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('slug', 40)->unique();
            $table->string('legal_name', 190);
            $table->string('display_name_ar', 190);
            $table->string('display_name_en', 190);
            $table->string('status', 32)->default('active');
            $table->string('timezone', 64)->default('Africa/Cairo');
            $table->char('currency_code', 3);
            $table->char('default_locale', 2)->default('ar');
            $table->string('email', 190);
            $table->string('phone', 20);
            $table->string('logo_path')->nullable();
            $table->decimal('credit_balance', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->string('code', 20);
            $table->string('phone', 20)->nullable();
            $table->string('city', 80);
            $table->string('address');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->string('key', 100);
            $table->json('value');

            $table->primary(['tenant_id', 'key']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('branch_settings', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->string('key', 100);
            $table->json('value');

            $table->primary(['branch_id', 'key']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);

            $table->unique(['branch_id', 'day_of_week']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('name', 80);
            $table->decimal('rate_percent', 6, 3);
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('operating_hours');
        Schema::dropIfExists('branch_settings');
        Schema::dropIfExists('tenant_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('currencies');
    }
};
