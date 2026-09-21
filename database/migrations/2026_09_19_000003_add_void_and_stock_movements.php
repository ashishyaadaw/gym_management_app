<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A sale is never deleted: a mistaken one is voided (stock goes back, it stops counting as revenue).
        Schema::table('store_sales', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('method'); // completed | voided
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->index('status');
        });

        // Every change to a product's stock, so a count that doesn't add up can be traced.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_sale_id')->nullable()->constrained('store_sales')->nullOnDelete();
            $table->integer('change'); // signed: +restock / -sale
            // restock | adjustment | damaged | sale | void
            $table->string('reason', 20);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::table('store_sales', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['status', 'voided_at', 'void_reason']);
        });
    }
};
