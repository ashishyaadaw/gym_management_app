<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // member
            $table->foreignId('class_schedule_id')->constrained()->cascadeOnDelete();
            // confirmed | waitlisted | cancelled | attended | no_show
            $table->enum('status', ['confirmed', 'waitlisted', 'cancelled', 'attended', 'no_show'])->default('confirmed');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'class_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
