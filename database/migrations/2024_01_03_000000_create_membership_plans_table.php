<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            // monthly | yearly | pay_per_class
            $table->enum('billing_cycle', ['monthly', 'yearly', 'pay_per_class']);
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('class_credits')->nullable(); // null = unlimited
            $table->unsignedInteger('duration_days')->nullable(); // for monthly/yearly
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
