<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Supplements / accessories / apparel sold over the front-desk counter (POS).
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('Supplement');
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total', 10, 2);
            // cash | upi | card
            $table->string('method', 20)->default('cash');
            $table->timestamp('sold_at')->useCurrent();
            $table->timestamps();

            $table->index('sold_at');
        });

        Schema::create('store_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // snapshot, so history survives product edits/deletes
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sale_items');
        Schema::dropIfExists('store_sales');
        Schema::dropIfExists('products');
    }
};
