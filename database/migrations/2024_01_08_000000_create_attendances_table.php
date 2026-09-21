<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // member
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete(); // receptionist/admin
            // gym_visit | class
            $table->enum('type', ['gym_visit', 'class'])->default('gym_visit');
            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'check_in_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
