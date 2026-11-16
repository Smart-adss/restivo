<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value_type', 16);
            $table->string('name_ar', 160);
            $table->string('name_en', 160);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id');
            $table->string('interval', 16);
            $table->char('currency_code', 3);
            $table->decimal('amount', 12, 2);
            $table->boolean('is_active')->default(true);

            $table->unique(['plan_id', 'interval', 'currency_code']);
            $table->foreign('plan_id')->references('id')->on('plans');
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->foreignId('plan_id');
            $table->foreignId('feature_id');
            $table->string('value', 64)->nullable();

            $table->primary(['plan_id', 'feature_id']);
            $table->foreign('plan_id')->references('id')->on('plans');
            $table->foreign('feature_id')->references('id')->on('features');
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('addon_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id');
            $table->string('interval', 16);
            $table->char('currency_code', 3);
            $table->decimal('amount', 12, 2);
            $table->boolean('is_active')->default(true);

            $table->unique(['addon_id', 'interval', 'currency_code']);
            $table->foreign('addon_id')->references('id')->on('addons');
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('plan_id');
            $table->string('status', 32);
            $table->string('interval', 16);
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            $table->dateTime('grace_ends_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->unique(['tenant_id', 'id']);
            $table->index(['status', 'current_period_end']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('plan_id')->references('id')->on('plans');
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->foreignId('subscription_id');
            $table->foreignId('addon_id');
            $table->unsignedInteger('quantity');

            $table->primary(['subscription_id', 'addon_id']);
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
            $table->foreign('addon_id')->references('id')->on('addons');
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id');
            $table->foreignId('subscription_id');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->char('currency_code', 3);
            $table->string('status', 32);
            $table->dateTime('issued_at');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['subscription_id', 'period_start']);
            $table->foreign(['tenant_id', 'subscription_id'])->references(['tenant_id', 'id'])->on('subscriptions');
            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('invoice_id');
            $table->decimal('amount', 12, 2);
            $table->string('method', 32);
            $table->string('provider_ref', 120)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['method', 'provider_ref']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('subscription_invoices');
        });

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('feature_key', 100);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('used')->default(0);

            $table->unique(['tenant_id', 'feature_key', 'period_start']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('action', 100);
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id');
            $table->foreignId('user_id');
            $table->string('subject', 190);
            $table->string('status', 32)->default('open');
            $table->string('priority', 32)->default('normal');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('user_id');
            $table->text('body');
            $table->timestamp('created_at')->nullable();

            $table->foreign('ticket_id')->references('id')->on('support_tickets');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscription_addons');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('addon_prices');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('features');
        Schema::dropIfExists('system_settings');
    }
};
