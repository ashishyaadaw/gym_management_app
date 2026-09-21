<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ClassSchedule;
use App\Models\Coupon;
use App\Models\Equipment;
use App\Models\GymClass;
use App\Models\MemberPlan;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StaffProfile;
use App\Models\StockMovement;
use App\Models\StoreSale;
use App\Models\StoreSaleItem;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\StaffAttendanceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // --- Staff accounts (password for all demo accounts: "password") ---
        $admin = User::create([
            'name' => 'Gym Owner',
            'email' => 'admin@sk29gym.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $receptionist = User::create([
            'name' => 'Front Desk',
            'email' => 'reception@sk29gym.com',
            'password' => Hash::make('password'),
            'role' => 'receptionist',
        ]);

        $trainer = User::create([
            'name' => 'Alex Trainer',
            'email' => 'trainer@sk29gym.com',
            'password' => Hash::make('password'),
            'role' => 'trainer',
        ]);
        TrainerProfile::create([
            'user_id' => $trainer->id,
            'specialization' => 'Strength & Conditioning',
            'bio' => 'Certified strength coach with 8 years of experience.',
            'experience_years' => 8,
            'hourly_rate' => 500,
        ]);

        }
}
