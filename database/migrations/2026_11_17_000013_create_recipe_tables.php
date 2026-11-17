<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('product_id');
            $table->decimal('yield_qty', 14, 3)->default(1);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'product_id']);
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products');
        });

        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('unit_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['recipe_id', 'stock_item_id']);
            $table->foreign(['tenant_id', 'recipe_id'])->references(['tenant_id', 'id'])->on('recipes');
            $table->foreign(['tenant_id', 'stock_item_id'])->references(['tenant_id', 'id'])->on('stock_items');
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_lines');
        Schema::dropIfExists('recipes');
    }
};
