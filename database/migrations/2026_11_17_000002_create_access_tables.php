<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('group_name', 50);
            $table->string('description');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->string('key', 64);
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id');
            $table->foreignId('permission_id');

            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles');
            $table->foreign('permission_id')->references('id')->on('permissions');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('role_id');
            $table->foreignId('branch_id')->nullable();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('role_id')->references('id')->on('roles');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->index(['user_id', 'role_id']);
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('branch_id');
            $table->boolean('is_primary')->default(false);

            $table->primary(['user_id', 'branch_id']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
