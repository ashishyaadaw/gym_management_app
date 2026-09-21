<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Body, fitness and health details for a member. Gender, date of birth, address and the emergency
        // contact's name and phone already live on the users table.
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('height_cm', 4, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->string('fitness_goal', 30)->nullable();
            $table->string('fitness_level', 20)->nullable();        // beginner | intermediate | advanced
            $table->string('blood_group', 3)->nullable();
            $table->text('medical_conditions')->nullable();         // injuries, allergies, medication, doctor's advice
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->string('occupation')->nullable();
            $table->string('preferred_timing', 20)->nullable();     // morning | afternoon | evening | flexible
            $table->string('referral_source', 30)->nullable();      // how they heard about the gym
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
