<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ClassScheduleController;
use App\Http\Controllers\Api\CollectionReportController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GymClassController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MemberPlanController;
use App\Http\Controllers\Api\MembershipPlanController;
use App\Http\Controllers\Api\PastRecordController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\StaffAttendanceController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AuthController;
use App\Models\Payslip;
use App\Models\StoreSale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes — every page and every jQuery/AJAX endpoint lives here.
|--------------------------------------------------------------------------
| Pages are Blade views (resources/views). They call the JSON endpoints
| under the /ajax prefix with jQuery (public/js/pages/*.js). Authentication
| is the normal Laravel session guard + CSRF token; there are no API tokens.
*/

// ---------- PWA manifest (lets phones "Add to Home Screen" as an app) ----------
Route::get('/manifest.webmanifest', fn () => response()->json([
    'name' => config('app.name'),
    'short_name' => 'SK29',
    'description' => 'Members, attendance, POS and daily collection',
    'start_url' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#121212',
    'theme_color' => '#121212',
    'icons' => [
        ['src' => asset('images/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => asset('images/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
], 200, ['Content-Type' => 'application/manifest+json']))->name('manifest');

// ---------- Guest: login / register ----------
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

// ---------- Authenticated ----------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // ----- Pages -----
    Route::view('/', 'dashboard')->name('dashboard');
    Route::view('/classes', 'classes')->name('classes');
    Route::view('/bookings', 'bookings')->name('bookings');
    Route::view('/plans', 'plans')->name('plans');
    Route::view('/billing', 'billing')->name('billing');
    Route::view('/attendance', 'attendance')->name('attendance');

    Route::view('/members', 'members')->name('members')->middleware('role:admin,receptionist,trainer');
    Route::view('/store', 'store')->name('store')->middleware('role:admin,receptionist');
    Route::view('/sales', 'sales')->name('sales')->middleware('role:admin,receptionist');
    Route::view('/collection', 'collection')->name('collection')->middleware('role:admin,receptionist');
    Route::view('/expenses', 'expenses')->name('expenses')->middleware('role:admin,receptionist');
    Route::get('/sales/{sale}/receipt', fn (StoreSale $sale) => view('receipt', ['sale' => $sale->load('items', 'seller:id,name', 'member:id,name')]))
        ->name('receipt')->middleware('role:admin,receptionist');

    // Staff: management, attendance and salary
    Route::view('/staff', 'staff')->name('staff')->middleware('role:admin');
    Route::view('/staff/attendance', 'staff-attendance')->name('staff.attendance')->middleware('role:admin');
    Route::view('/payroll', 'payroll')->name('payroll')->middleware('role:admin');
    Route::view('/my-attendance', 'my-attendance')->name('my.attendance')->middleware('role:admin,trainer,receptionist');
    // A payslip: the admin can open any; a staff member only their own once it has been paid.
    Route::get('/payroll/{payslip}/slip', function (Payslip $payslip) {
        $user = auth()->user();
        abort_unless($user->isAdmin() || ($payslip->user_id === $user->id && $payslip->isPaid()), 403);

        return view('payslip', ['slip' => $payslip->load('user:id,name,email', 'user.staffProfile')]);
    })->name('payslip');

    Route::middleware('role:admin')->group(function () {
        Route::view('/equipment', 'equipment')->name('equipment');
        Route::view('/users', 'users')->name('users');
        Route::view('/past-records', 'past-records')->name('past.records');
    });

    // ----- JSON endpoints used by the pages' jQuery -----
    Route::prefix('ajax')->name('ajax.')->group(function () {
        // Any authenticated role
        Route::get('/membership-plans', [MembershipPlanController::class, 'index']);
        // Price check with a coupon (members for themselves; staff for a member). Rate-limited against code guessing.
        Route::post('/coupons/check', [CouponController::class, 'check'])->middleware('throttle:30,1');

        // Classes catalogue & schedule — everyone can view
        Route::get('/gym-classes', [GymClassController::class, 'index']);
        Route::get('/gym-classes/{gymClass}', [GymClassController::class, 'show']);
        Route::get('/class-schedules', [ClassScheduleController::class, 'index']);
        Route::get('/class-schedules/{classSchedule}', [ClassScheduleController::class, 'show']);

        // Bookings — members create/cancel their own; staff can view all
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store'])->middleware('role:member');
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

        // Member plan self-service
        Route::get('/member-plans', [MemberPlanController::class, 'index']);
        Route::post('/member-plans', [MemberPlanController::class, 'store']);
        Route::get('/member-plans/{id}', [MemberPlanController::class, 'show']);
        Route::post('/member-plans/{id}/cancel', [MemberPlanController::class, 'cancel']);

        // Payment history (own, for members)
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);

        // Attendance — members see own history
        Route::get('/attendances', [AttendanceController::class, 'index']);

        // Dashboards
        Route::get('/dashboard/member', [DashboardController::class, 'memberSummary'])->middleware('role:member');
        Route::get('/dashboard/trainer', [DashboardController::class, 'trainerSummary'])->middleware('role:trainer');
        Route::get('/dashboard/admin', [DashboardController::class, 'adminSummary'])->middleware('role:admin');

        // ---------- Front desk: trainer + receptionist + admin ----------
        Route::middleware('role:trainer,receptionist,admin')->group(function () {
            Route::get('/attendances/day', [AttendanceController::class, 'day']);

            // A staff member's own attendance (clock in/out) and paid payslips
            Route::get('/my/attendance', [StaffAttendanceController::class, 'my']);
            Route::post('/my/clock-in', [StaffAttendanceController::class, 'clockIn']);
            Route::post('/my/clock-out', [StaffAttendanceController::class, 'clockOut']);
            Route::get('/my/payslips', [PayrollController::class, 'mine']);
            Route::get('/members', [MemberController::class, 'index']);
            Route::get('/members/{member}', [MemberController::class, 'show']);
            Route::post('/attendances/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('/attendances/{attendance}/check-out', [AttendanceController::class, 'checkOut']);
        });

        // ---------- Receptionist + Admin ----------
        Route::middleware('role:receptionist,admin')->group(function () {
            Route::get('/dashboard/staff', [DashboardController::class, 'staffSummary']);
            Route::get('/collection', [DashboardController::class, 'collection']);
            Route::get('/collection/report', [CollectionReportController::class, 'report']);
            Route::get('/collection/export', [CollectionReportController::class, 'export']);

            // Walk-in enrolment + renewals
            Route::post('/members', [MemberController::class, 'store']);
            Route::put('/members/{member}', [MemberController::class, 'update']);
            Route::post('/members/{member}/renew', [MemberController::class, 'renew']);

            // Store (POS)
            Route::get('/store/products', [StoreController::class, 'products']);
            Route::get('/store/sales', [StoreController::class, 'sales']);
            Route::get('/store/sales/export', [StoreController::class, 'export']);
            Route::get('/store/report', [StoreController::class, 'report']);
            Route::post('/store/sales', [StoreController::class, 'sell']);

            Route::post('/payments', [PaymentController::class, 'store']);
            Route::post('/payments/{payment}/mark-paid', [PaymentController::class, 'markPaid']);

            // Expenses — the desk records them; edit/delete rules (own entries, same day) are in the controller
            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::get('/expenses/export', [ExpenseController::class, 'export']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
            Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
        });

        // ---------- Admin only ----------
        Route::middleware('role:admin')->group(function () {
            // User management (members, trainers, receptionists, admins)
            Route::apiResource('users', UserController::class);

            // Switch a member's account off / on (front desk can add and renew, only the admin can deactivate)
            Route::post('/members/{member}/deactivate', [MemberController::class, 'deactivate']);
            Route::post('/members/{member}/activate', [MemberController::class, 'activate']);

            // Staff management, attendance and payroll
            Route::get('/staff', [StaffController::class, 'index']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::put('/staff/{user}', [StaffController::class, 'update']);
            Route::get('/staff-attendance', [StaffAttendanceController::class, 'month']);
            Route::put('/staff-attendance', [StaffAttendanceController::class, 'mark']);
            Route::post('/staff-attendance/bulk', [StaffAttendanceController::class, 'bulk']);
            Route::get('/payroll', [PayrollController::class, 'index']);
            Route::post('/payroll/generate', [PayrollController::class, 'generate']);
            Route::put('/payslips/{payslip}', [PayrollController::class, 'update']);
            Route::post('/payslips/{payslip}/pay', [PayrollController::class, 'pay']);
            Route::delete('/payslips/{payslip}', [PayrollController::class, 'destroy']);

            // Coupons
            Route::get('/coupons', [CouponController::class, 'index']);
            Route::post('/coupons', [CouponController::class, 'store']);
            Route::put('/coupons/{coupon}', [CouponController::class, 'update']);

            // Membership plan catalogue
            Route::post('/membership-plans', [MembershipPlanController::class, 'store']);
            Route::get('/membership-plans/{membershipPlan}', [MembershipPlanController::class, 'show']);
            Route::put('/membership-plans/{membershipPlan}', [MembershipPlanController::class, 'update']);
            Route::delete('/membership-plans/{membershipPlan}', [MembershipPlanController::class, 'destroy']);

            // Class catalogue & scheduling
            Route::post('/gym-classes', [GymClassController::class, 'store']);
            Route::put('/gym-classes/{gymClass}', [GymClassController::class, 'update']);
            Route::delete('/gym-classes/{gymClass}', [GymClassController::class, 'destroy']);
            Route::post('/class-schedules', [ClassScheduleController::class, 'store']);
            Route::put('/class-schedules/{classSchedule}', [ClassScheduleController::class, 'update']);
            Route::delete('/class-schedules/{classSchedule}', [ClassScheduleController::class, 'destroy']);

            // Store catalogue
            Route::post('/store/products', [StoreController::class, 'storeProduct']);
            Route::put('/store/products/{product}', [StoreController::class, 'updateProduct']);
            Route::post('/store/products/{product}/stock', [StoreController::class, 'adjustStock']);
            Route::get('/store/products/{product}/movements', [StoreController::class, 'movements']);
            Route::post('/store/sales/{sale}/void', [StoreController::class, 'void']);

            // Past records: the gym's history before this software (bulk entry + undo)
            Route::get('/past-records', [PastRecordController::class, 'index']);
            Route::post('/past-records', [PastRecordController::class, 'store']);
            Route::delete('/past-records/{payment}', [PastRecordController::class, 'destroy']);

            // Payments admin actions
            Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);

            // Equipment inventory
            Route::apiResource('equipment', EquipmentController::class);
            Route::post('/equipment/{equipment}/maintenance', [EquipmentController::class, 'logMaintenance']);
        });
    });
});
