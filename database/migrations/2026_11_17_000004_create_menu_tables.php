<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'parent_id', 'sort_order']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'parent_id'])->references(['tenant_id', 'id'])->on('menu_categories');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->string('description_ar', 500)->nullable();
            $table->string('description_en', 500)->nullable();
            $table->decimal('base_price', 12, 2);
            $table->string('sku', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('prep_station_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'category_id', 'is_active']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'category_id'])->references(['tenant_id', 'id'])->on('menu_categories');
            $table->foreign(['tenant_id', 'tax_rate_id'])->references(['tenant_id', 'id'])->on('tax_rates');
        });

        Schema::create('product_branch_settings', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('branch_id');
            $table->boolean('is_available')->default(true);
            $table->decimal('price_override', 12, 2)->nullable();
            $table->timestamps();

            $table->primary(['product_id', 'branch_id']);
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('product_id');
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'product_id', 'sort_order']);
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products');
        });

        Schema::create('product_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('product_id');
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->string('kind', 32);
            $table->string('selection', 32);
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'product_id', 'sort_order']);
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products');
        });

        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('group_id');
            $table->string('name_ar', 190);
            $table->string('name_en', 190);
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'group_id', 'sort_order']);
            $table->foreign(['tenant_id', 'group_id'])->references(['tenant_id', 'id'])->on('product_option_groups');
        });

        Schema::create('product_option_branch_settings', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('branch_id');
            $table->boolean('is_available')->default(true);
            $table->decimal('price_delta_override', 12, 2)->nullable();
            $table->timestamps();

            $table->primary(['option_id', 'branch_id']);
            $table->foreign(['tenant_id', 'option_id'])->references(['tenant_id', 'id'])->on('product_options');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_branch_settings');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_option_groups');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_branch_settings');
        Schema::dropIfExists('products');
        Schema::dropIfExists('menu_categories');
    }
};
