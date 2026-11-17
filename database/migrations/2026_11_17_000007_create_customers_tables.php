<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('name', 120);
            $table->string('phone', 20);
            $table->string('email', 190)->nullable();
            $table->string('notes', 500)->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'phone']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('label', 80)->nullable();
            $table->string('area_name', 120)->nullable();
            $table->string('address_line', 255);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'customer_id']);
        });
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
