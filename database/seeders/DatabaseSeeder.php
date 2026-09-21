<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ClassSchedule;
use App\Models\Coupon;
use App\Models\Equipment;
use App\Models\GymClass;
use App\Models\MemberPlan;
use App\Models\MemberProfile;
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

class DatabaseSeeder extends Seeder
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

        // --- Membership plans: 1 / 3 / 6 / 12 months ---
        $plans = [
            1 => MembershipPlan::create([
                'name' => '1 Month', 'description' => 'Full gym access for one month.',
                'billing_cycle' => 'monthly', 'price' => 1500, 'duration_days' => 30,
            ]),
            3 => MembershipPlan::create([
                'name' => '3 Months', 'description' => 'Full gym access for three months.',
                'billing_cycle' => 'quarterly', 'price' => 4500, 'duration_days' => 90,
            ]),
            6 => MembershipPlan::create([
                'name' => '6 Months', 'description' => 'Full gym access for six months.',
                'billing_cycle' => 'half_yearly', 'price' => 8000, 'duration_days' => 180,
            ]),
            12 => MembershipPlan::create([
                'name' => '12 Months', 'description' => 'Full gym access for a year — best value.',
                'billing_cycle' => 'yearly', 'price' => 15000, 'duration_days' => 365,
            ]),
        ];
        MembershipPlan::create([
            'name' => '10-Class Pack', 'description' => 'Ten group-class credits, no expiry.',
            'billing_cycle' => 'pay_per_class', 'price' => 2000, 'class_credits' => 10,
        ]);

        // Plan-use limits: a one-time trial with a cap on total sign-ups, and a capped annual plan.
        MembershipPlan::create([
            'name' => '7-Day Trial', 'description' => 'Try the gym for a week — once per person.',
            'billing_cycle' => 'monthly', 'price' => 199, 'duration_days' => 7,
            'usage_limit' => 30, 'per_member_limit' => 1,
        ]);
        $plans[12]->update(['usage_limit' => 50]);

        // Coupons: one for any plan, one tied to the annual plan with a cap, one that has already expired.
        Coupon::create([
            'code' => 'WELCOME10', 'description' => '10% off for new members',
            'discount_type' => 'percent', 'discount_value' => 10, 'once_per_member' => true,
        ]);
        Coupon::create([
            'code' => 'YEAR500', 'description' => '₹500 off the 12-month plan',
            'discount_type' => 'fixed', 'discount_value' => 500, 'membership_plan_id' => $plans[12]->id, 'max_redemptions' => 20,
        ]);
        Coupon::create([
            'code' => 'OLDOFFER', 'description' => 'Expired demo coupon',
            'discount_type' => 'percent', 'discount_value' => 15, 'valid_until' => today()->subDays(10),
        ]);

        // --- Members. [name, phone, plan months, days until expiry, balance due, paid via, emergency contact] ---
        // Negative "days until expiry" = already lapsed. Covers active, expiring (<= 5 days) and expired.
        $rows = [
            ['Aarav Sharma', '9876543210', 3, 45, 0, 'upi', '9876543211'],
            ['Vikram Singh', '9876543212', 6, 90, 0, 'cash', '9876543213'],
            ['Rohan Mehta', '9876543214', 12, 200, 0, 'card', '9876543215'],
            ['Kabir Verma', '9876543216', 1, 15, 0, 'upi', '9876543217'],
            ['Aditya Patel', '9876543218', 3, 30, 500, 'cash', '9876543219'],
            ['Siddharth Joshi', '9876543220', 6, 60, 0, 'card', '9876543233'],
            ['Arjun Kapoor', '9876543221', 1, 25, 0, 'upi', '9876543222'],
            ['Rahul Desai', '9876543223', 1, 2, 200, 'cash', '9876543224'],
            ['Karan Malhotra', '9876543225', 3, 4, 0, 'upi', '9876543226'],
            ['Ishaan Reddy', '9876543227', 6, 3, 1000, 'card', '9876543228'],
            ['Varun Nair', '9876543229', 1, -10, 500, 'cash', '9876543230'],
            ['Samar Khan', '9876543231', 3, -12, 0, 'upi', '9876543232'],
        ];

        $members = [];
        foreach ($rows as [$name, $phone, $months, $daysLeft, $due, $method, $emergency]) {
            $members[$name] = $this->enrolMember($plans[$months], $name, $phone, $daysLeft, $due, $method, $emergency);
        }

        // Profile details for a few members, so the member view has something to show.
        // [contact + emergency-contact fields on the user, body/fitness/health fields on the profile]
        $details = [
            'Aarav Sharma' => [
                ['gender' => 'male', 'date_of_birth' => '1996-04-12', 'address' => '12 MG Road, Kothrud, Pune', 'emergency_contact_name' => 'Sunita Sharma'],
                ['height_cm' => 176, 'weight_kg' => 84.5, 'fitness_goal' => 'weight_loss', 'fitness_level' => 'beginner', 'blood_group' => 'B+', 'emergency_contact_relation' => 'Mother', 'occupation' => 'Software engineer', 'preferred_timing' => 'morning', 'referral_source' => 'friend'],
            ],
            'Vikram Singh' => [
                ['gender' => 'male', 'date_of_birth' => '1989-11-03', 'address' => 'B-204, Lake View Apartments, Baner, Pune', 'emergency_contact_name' => 'Neha Singh'],
                ['height_cm' => 181, 'weight_kg' => 78, 'fitness_goal' => 'muscle_gain', 'fitness_level' => 'advanced', 'blood_group' => 'O+', 'medical_conditions' => 'Old right-shoulder injury — avoid heavy overhead pressing.', 'emergency_contact_relation' => 'Spouse', 'occupation' => 'Business owner', 'preferred_timing' => 'evening', 'referral_source' => 'social_media', 'notes' => 'Trains 5 days a week. Interested in personal training.'],
            ],
            'Rohan Mehta' => [
                ['gender' => 'male', 'date_of_birth' => '2001-07-21', 'address' => 'Hostel 3, College Road, Pune', 'emergency_contact_name' => 'Anil Mehta'],
                ['height_cm' => 169, 'weight_kg' => 58, 'fitness_goal' => 'strength', 'fitness_level' => 'intermediate', 'blood_group' => 'A+', 'emergency_contact_relation' => 'Father', 'occupation' => 'Student', 'preferred_timing' => 'afternoon', 'referral_source' => 'walk_in'],
            ],
            'Samar Khan' => [
                ['gender' => 'male', 'date_of_birth' => '1993-02-15', 'address' => '7 Station Road, Camp, Pune', 'emergency_contact_name' => 'Zoya Khan'],
                ['height_cm' => 173, 'weight_kg' => 91, 'fitness_goal' => 'general_fitness', 'fitness_level' => 'beginner', 'blood_group' => 'AB-', 'medical_conditions' => 'Mild asthma — keeps an inhaler in the gym bag. Doctor advised no high-intensity cardio for now.', 'emergency_contact_relation' => 'Sister', 'occupation' => 'Accountant', 'preferred_timing' => 'flexible', 'referral_source' => 'advertisement'],
            ],
        ];
        foreach ($details as $name => [$contact, $profile]) {
            $members[$name]->update($contact);
            MemberProfile::create(['user_id' => $members[$name]->id] + $profile);
        }

        // The demo login for the member portal.
        $sam = User::create([
            'name' => 'Sam Member',
            'email' => 'member@sk29gym.com',
            'phone' => '9876500000',
            'password' => Hash::make('password'),
            'role' => 'member',
        ]);
        $this->enrolMember($plans[1], $sam, null, 20, 0, 'cash', null);

        // --- Today's counter collection (times are capped at "now" so nothing sits in the future) ---
        $at = fn (int $h, int $m) => min(now(), today()->setTime($h, $m));
        $this->payToday($members['Aarav Sharma'], 1500, 'cash', $at(7, 30), '1M Renewal');
        $this->payToday($members['Rahul Desai'], 4500, 'upi', $at(11, 15), '3M Plan');

        // --- Store (POS) ---
        $products = collect([
            ['Whey Protein 1kg', 'Supplement', 2800, 3500, 12],
            ['Creatine Monohydrate', 'Supplement', 900, 1300, 3],
            ['Pre-Workout 300g', 'Supplement', 1200, 1800, 8],
            ['Gym Shaker 600ml', 'Accessory', 150, 350, 20],
            ['SK29 Elite T-Shirt', 'Apparel', 400, 799, 2],
            ['BCAA 250g', 'Supplement', 1100, 1600, 6],
        ])->mapWithKeys(function ($p) use ($admin) {
            $product = Product::create([
                'name' => $p[0], 'category' => $p[1], 'cost_price' => $p[2], 'selling_price' => $p[3], 'stock' => $p[4],
            ]);
            // Opening stock is recorded as a movement, so the stock history starts from the delivery.
            StockMovement::create([
                'product_id' => $product->id, 'user_id' => $admin->id,
                'change' => $p[4], 'reason' => 'restock', 'note' => 'Opening stock',
            ]);

            return [$p[0] => $product];
        });

        $this->sale($receptionist, $at(18, 45), 'card', [[$products['Whey Protein 1kg'], 1]]);
        $this->sale($receptionist, $at(19, 20), 'cash', [[$products['Gym Shaker 600ml'], 2]]);

        // A week of earlier sales (so the Sales page has a trend), plus one that was rung up wrongly and voided.
        foreach (range(1, 6) as $daysAgo) {
            foreach (range(1, rand(1, 3)) as $_) {
                $line = $products->only(['Whey Protein 1kg', 'Pre-Workout 300g', 'BCAA 250g', 'Gym Shaker 600ml'])->random();
                if ($line->fresh()->stock < 1) {
                    continue;
                }
                $this->sale(
                    $receptionist,
                    today()->subDays($daysAgo)->setTime(rand(7, 20), rand(0, 59)),
                    collect(['cash', 'upi', 'card'])->random(),
                    [[$line, 1]],
                );
            }
        }
        $this->sale($receptionist, $at(12, 10), 'cash', [[$products['Creatine Monohydrate'], 1]], void: 'Wrong item rung up');

        // --- Attendance: a few visits today plus the last week, so peak hours and "inactive" lists have data ---
        $active = collect(['Aarav Sharma', 'Vikram Singh', 'Rohan Mehta', 'Kabir Verma', 'Arjun Kapoor', 'Siddharth Joshi', 'Aditya Patel']);
        $this->checkIn($members['Aarav Sharma'], $receptionist, $at(6, 30));
        $this->checkIn($members['Vikram Singh'], $receptionist, $at(7, 15));
        $this->checkIn($members['Kabir Verma'], $receptionist, $at(18, 0));
        foreach (range(1, 6) as $daysAgo) {
            foreach ($active->random(rand(3, 5)) as $name) {
                $this->checkIn($members[$name], $receptionist, today()->subDays($daysAgo)->setTime(collect([6, 7, 8, 17, 18, 19, 20])->random(), rand(0, 59)));
            }
        }

        // --- Classes ---
        $yoga = GymClass::create(['name' => 'Vinyasa Yoga', 'category' => 'yoga', 'duration_minutes' => 60, 'capacity' => 15]);
        $hiit = GymClass::create(['name' => 'HIIT Blast', 'category' => 'cardio', 'duration_minutes' => 45, 'capacity' => 20]);
        GymClass::create(['name' => 'Strength Fundamentals', 'category' => 'strength', 'duration_minutes' => 60, 'capacity' => 12]);

        ClassSchedule::create([
            'gym_class_id' => $yoga->id, 'trainer_id' => $trainer->id, 'room' => 'Studio A',
            'start_time' => now()->addDay()->setTime(9, 0), 'end_time' => now()->addDay()->setTime(10, 0),
        ]);
        ClassSchedule::create([
            'gym_class_id' => $hiit->id, 'trainer_id' => $trainer->id, 'room' => 'Studio B',
            'start_time' => now()->addDays(2)->setTime(18, 0), 'end_time' => now()->addDays(2)->setTime(18, 45),
        ]);

        // --- Equipment ---
        Equipment::create(['name' => 'Treadmill', 'category' => 'cardio', 'quantity' => 6, 'location' => 'Cardio Floor', 'status' => 'available']);
        Equipment::create(['name' => 'Olympic Barbell Set', 'category' => 'strength', 'quantity' => 10, 'location' => 'Free Weights Area', 'status' => 'available']);
        Equipment::create([
            'name' => 'Rowing Machine', 'category' => 'cardio', 'quantity' => 3, 'location' => 'Cardio Floor',
            'status' => 'under_maintenance', 'next_maintenance_date' => now()->addDays(5),
        ]);

        $this->seedStaff($admin, $receptionist, $trainer);

        $this->command->info('Seed complete. Log in as admin@sk29gym.com (owner) or reception@sk29gym.com — password: "password".');
    }

    /**
     * Create (or reuse) a member whose current plan ends $daysLeft days from today,
     * with the joining payment recorded on the day the plan started.
     */
    private function enrolMember(MembershipPlan $plan, User|string $member, ?string $phone, int $daysLeft, float $due, string $method, ?string $emergency): User
    {
        $ends = today()->addDays($daysLeft);
        $starts = $ends->copy()->subDays($plan->duration_days);

        if (is_string($member)) {
            $member = new User([
                'name' => $member,
                'email' => $phone.'@members.gym.local',
                'phone' => $phone,
                'password' => Hash::make('password'),
                'role' => 'member',
                'emergency_contact_phone' => $emergency,
            ]);
            $member->created_at = $starts;   // so the "new members per month" chart has history
            $member->save();
        }

        $memberPlan = MemberPlan::create([
            'user_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $starts,
            'end_date' => $ends,
            'status' => $daysLeft < 0 ? 'expired' : 'active',
            'auto_renew' => false,
        ]);

        $paidAt = min(now(), $starts->copy()->setTime(10, 30));
        Payment::create([
            'user_id' => $member->id, 'member_plan_id' => $memberPlan->id,
            'amount' => (float) $plan->price - $due, 'method' => $method,
            'status' => 'paid', 'paid_at' => $paidAt,
        ]);
        if ($due > 0) {
            Payment::create([
                'user_id' => $member->id, 'member_plan_id' => $memberPlan->id,
                'amount' => $due, 'method' => 'other', 'status' => 'pending',
                'due_date' => $starts, 'notes' => 'Balance due',
            ]);
        }

        return $member;
    }

    /** A membership payment taken at the counter earlier today. */
    private function payToday(User $member, float $amount, string $method, Carbon $when, string $note): void
    {
        Payment::create([
            'user_id' => $member->id, 'member_plan_id' => $member->memberPlans()->latest('id')->value('id'),
            'amount' => $amount, 'method' => $method, 'status' => 'paid', 'paid_at' => $when, 'notes' => $note,
        ]);
    }

    /**
     * @param array<int, array{0: Product, 1: int}> $lines
     * @param string|null $void  give a reason to record the sale as voided (stock is put back)
     */
    private function sale(User $seller, Carbon $when, string $method, array $lines, ?string $void = null): void
    {
        $sale = StoreSale::create([
            'sold_by' => $seller->id, 'method' => $method, 'sold_at' => $when,
            'total' => collect($lines)->sum(fn ($l) => $l[0]->selling_price * $l[1]),
        ]);

        foreach ($lines as [$product, $qty]) {
            StoreSaleItem::create([
                'store_sale_id' => $sale->id, 'product_id' => $product->id, 'name' => $product->name,
                'quantity' => $qty, 'unit_price' => $product->selling_price, 'unit_cost' => $product->cost_price,
            ]);
            $product->decrement('stock', $qty);
            $this->move($product, $seller, -$qty, 'sale', $sale->receipt_number, $sale->id);

            if ($void !== null) {
                $product->increment('stock', $qty);
                $this->move($product, $seller, $qty, 'void', 'Void '.$sale->receipt_number, $sale->id);
            }
        }

        if ($void !== null) {
            $sale->update(['status' => 'voided', 'voided_at' => $when, 'voided_by' => $seller->id, 'void_reason' => $void]);
        }
    }

    private function move(Product $product, User $by, int $change, string $reason, string $note, int $saleId): void
    {
        StockMovement::create([
            'product_id' => $product->id, 'user_id' => $by->id, 'store_sale_id' => $saleId,
            'change' => $change, 'reason' => $reason, 'note' => $note,
        ]);
    }

    /**
     * Staff profiles, a month and a bit of attendance, and last month's payslips — arranged so every
     * salary rule shows up: late deductions, leave, a half day, an absence, a mid-month joiner with an
     * unmarked day, and an hourly part-timer. Nobody is clocked in today, so the clock can be tried.
     */
    private function seedStaff(User $admin, User $receptionist, User $trainer): void
    {
        $attendance = app(StaffAttendanceService::class);
        $payroll = app(PayrollService::class);
        $lastMonth = today()->subMonthNoOverflow()->startOfMonth();
        $thisMonth = today()->startOfMonth();

        $priya = User::create(['name' => 'Priya Evening Desk', 'email' => 'priya@sk29gym.com', 'phone' => '9811100001', 'password' => Hash::make('password'), 'role' => 'receptionist']);
        $ravi = User::create(['name' => 'Ravi Part-time Trainer', 'email' => 'ravi@sk29gym.com', 'phone' => '9811100002', 'password' => Hash::make('password'), 'role' => 'trainer']);
        $ravi->trainerProfile()->create(['specialization' => 'Weight training']);

        $profile = fn (User $u, array $f) => StaffProfile::create($f + ['user_id' => $u->id, 'employee_code' => StaffProfile::nextCode(), 'weekly_off' => 0]);

        $front = $profile($receptionist, ['designation' => 'Front desk (morning)', 'joining_date' => '2025-06-01', 'pay_type' => 'monthly', 'base_salary' => 18000, 'shift_start' => '06:00', 'shift_end' => '14:00']);
        $alex = $profile($trainer, ['designation' => 'Head trainer', 'joining_date' => '2024-11-15', 'pay_type' => 'monthly', 'base_salary' => 25000, 'shift_start' => '16:00', 'shift_end' => '22:00']);
        $pri = $profile($priya, ['designation' => 'Front desk (evening)', 'joining_date' => $lastMonth->copy()->day(10)->toDateString(), 'pay_type' => 'monthly', 'base_salary' => 15000, 'shift_start' => '14:00', 'shift_end' => '22:00', 'weekly_off' => 1]);
        $rav = $profile($ravi, ['designation' => 'Part-time trainer', 'joining_date' => today()->subMonths(3)->toDateString(), 'pay_type' => 'hourly', 'base_salary' => 400]);

        // Specials keyed by "nth working day of the month" (weekly offs don't count), so they never land on a day off.
        $specials = [
            $front->user_id => [4 => ['in' => '06:25'], 11 => ['in' => '06:30'], 18 => ['in' => '06:40'],   // three late marks → half a day off
                                7 => ['status' => 'leave'], 21 => ['status' => 'half_day', 'in' => '06:00', 'out' => '10:00']],
            $alex->user_id => [3 => ['in' => '16:30'], 9 => ['status' => 'absent']],
            $pri->user_id => [6 => ['skip' => true]],                                                        // left unmarked on purpose
            $rav->user_id => [],
        ];

        foreach ([$lastMonth, $thisMonth] as $month) {
            $until = $month->isSameMonth(today()) ? today()->subDay() : $month->copy()->endOfMonth();

            foreach ([$front, $alex, $pri, $rav] as $p) {
                $nth = 0;

                for ($d = $month->copy(); $d->lte($until); $d->addDay()) {
                    if (! $p->isEmployedOn($d) || $p->isWeeklyOff($d)) {
                        continue;
                    }
                    $nth++;
                    $sp = $specials[$p->user_id][$nth] ?? [];

                    if (! empty($sp['skip']) || ($p->isHourly() && $nth % 2 === 0)) {
                        continue;
                    }

                    $start = $p->shift_start ? substr($p->shift_start, 0, 5) : '17:00';
                    $end = $p->shift_end ? substr($p->shift_end, 0, 5) : '20:30';
                    $onTime = Carbon::createFromFormat('H:i', $start)->addMinutes([0, 2, 5, 3, 0, 7][$nth % 6])->format('H:i');

                    $attendance->mark(
                        $p, $d->copy(), $sp['status'] ?? 'present',
                        ($sp['status'] ?? 'present') === 'present' || ($sp['status'] ?? '') === 'half_day' ? ($sp['in'] ?? $onTime) : null,
                        ($sp['status'] ?? 'present') === 'present' || ($sp['status'] ?? '') === 'half_day' ? ($sp['out'] ?? $end) : null,
                        null, $admin,
                    );
                }
            }
        }

        // Last month's salaries: everyone generated (the unmarked day is treated as absent); two already paid.
        $payroll->generate($lastMonth, null, true, $admin);
        $slip = fn (User $u) => \App\Models\Payslip::where('user_id', $u->id)->whereDate('month', $lastMonth)->first();

        $payroll->adjust($slip($trainer), 1000, 0, 'Festival bonus');
        $payroll->pay($slip($receptionist), $lastMonth->copy()->endOfMonth()->addDays(2), 'bank_transfer', 'NEFT-118240');
        $payroll->pay($slip($trainer), $lastMonth->copy()->endOfMonth()->addDays(2), 'bank_transfer', 'NEFT-118241');
    }

    private function checkIn(User $member, User $by, Carbon $when): void
    {
        Attendance::create([
            'user_id' => $member->id, 'checked_in_by' => $by->id, 'type' => 'gym_visit', 'check_in_time' => $when,
        ]);
    }
}
