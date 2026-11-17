<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('totp_secret')->nullable()->after('remember_token');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
        });

        Schema::table('orders', function (Blueprint $table): void {
            // Supports day-report style filters: tenant + branch + placed_at window, then status.
            $table->index(['tenant_id', 'branch_id', 'placed_at', 'status'], 'orders_tenant_branch_placed_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_tenant_branch_placed_status_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['totp_secret', 'totp_confirmed_at']);
        });
    }
};
