# SK29 Elite Gym — Laravel Gym Management System

A fullstack gym management application built entirely in PHP: Laravel 11 (routes, controllers, Blade views) with a jQuery + Bootstrap 5 frontend. No Node/Vite build step.

## Features implemented

- **Auth & roles**: Laravel session auth (CSRF-protected). Roles — `admin`, `trainer`, `member`, `receptionist`. Members self-register; staff accounts are created by admin.
- **Member management**: profiles, contact/emergency info, admin CRUD, activate/deactivate.
- **Plan management (admin)**: create and edit plans (price, duration, class credits, billing cycle), deactivate/reactivate them, and see live usage ("12 / 50 signed up"). Members subscribe/cancel; each subscription tracks status, credits, renewal date. Editing a plan only affects future sign-ups.
- **Limit plan use**: per plan, cap the **total sign-ups**, the **times one member can take it** (e.g. 1 for a trial), and an optional **on-sale window** (from / until). A plan that is inactive, not yet on sale, ended, sold out or already used by that member is refused everywhere (member self-subscribe, front-desk enrol and renew). Cancelling a subscription frees its spot. Checked under a row lock, so two sign-ups cannot take the last spot.
- **Coupons (admin)**: percent or fixed-amount discount codes, either for **one plan** or for **all plans**, with limits of their own: total redemptions, one use per member, valid-from/until, on/off. Staff and members type the code at sign-up and see the discounted price first; the invoice/payment is for the discounted amount and every use is recorded. Codes are case-insensitive, and the price-check endpoint is rate-limited. The coupon applies to the first invoice only; auto-renewals are billed at the plan price.
- **Automated billing**: `BillingService` generates invoices on subscribe and on renewal. `php artisan billing:run` (scheduled daily at 01:00 in `routes/console.php`) auto-renews due plans and expires lapsed ones.
- **Class scheduling & booking**: admin creates classes and schedules sessions with a trainer/room/time. Members book with capacity limits, automatic waitlisting, and credit deduction; cancelling promotes the next waitlisted member.
- **Trainer management**: trainer profiles (specialization, bio, rate, availability), trainers see their own schedule and roster counts.
- **Attendance tracking**: front-desk/trainer check-in (gym visit or class), check-out, per-member history.
- **Billing/payments**: invoice list, manual payment recording (cash/card/bank transfer), mark-paid, refund.
- **Equipment inventory**: CRUD, status (available/in use/under maintenance/retired), maintenance logs.
- **Daily Collection** (owner + reception dashboard): today's total split Cash / UPI / Card, morning-afternoon-evening split, bank-settlement summary, live transaction list, and a 7-day table with per-day drill-down. Combines membership payments and store sales.
- **Members page**: search by name/phone, Active / Expiring / Expired filters, walk-in enrolment (plan, amount paid, auto balance-due and expiry), one-click renewal, quick check-in, and a **WhatsApp reminder** button that opens `wa.me` with a ready-made message.
- **Attention lists**: members expiring within 5 days, expired members needing renewal, and members with no visit for 7+ days.
- **POS Supplement Store**: product catalogue with stock and low-stock flags, cart, Cash/UPI/Card checkout (atomic stock check), optional member link, printable receipts. Owners add/edit products and adjust stock (restock / correction / damaged) with a movement history per product.
- **Sales page** (`/sales`): pick Today / 7 days / 30 days / This month or any range; revenue, units, average sale, payment-mode split, revenue-by-day chart, best sellers, and every sale with a receipt link. **Owners also see cost and profit**; reception sees revenue only. Owners can **void** a mistaken sale (stock goes back, it stops counting as revenue) and **export CSV**.
- **Low-stock alerts** on the dashboard and a "Low stock" filter in the store (threshold in `config/gym.php`).
- **Attendance**: type a name or phone to check in, check-out, date picker, headcount and a 6 AM–10 PM peak-hours chart.
- **Staff management (admin)**: a Staff page for everyone who works at the gym — account plus employment profile (auto employee code, designation, joining/leaving date, monthly or hourly pay, shift start/end, weekly off). Deactivating someone sets their leaving date and blocks their login; an account with attendance or payroll history can't be deleted.
- **Staff attendance**: staff clock in/out from **My Attendance** (live clock, late flag when they arrive more than 10 min after the shift start, month calendar). The admin sees a **monthly grid** for all staff (P / ½ / A / L / H / weekly off / not marked), can click any day to mark or correct it (status, times, note), and can mark everyone for a day (present or gym holiday) in one go. Hourly staff are recorded from their clock times.
- **Salary generation (payroll)**: at month end, generate payslips from attendance. **Monthly pay** = salary × payable days ÷ days in the month (payable = present + ½ per half day + paid leave + holidays + weekly offs; absences and unmarked working days earn nothing; days before joining/after leaving are excluded, so a mid-month joiner is pro-rated). **Late deduction**: every 3 late arrivals cost ½ a day. **Hourly pay** = hours worked × rate. Add a bonus or deduction with a note, mark **paid** (method + reference), and print the **payslip**. Unmarked days are flagged and need confirmation before they are treated as absences; a draft flags itself when attendance changes afterwards; a paid payslip is locked. Staff see their own payslips once paid. A base salary of 0 keeps someone (e.g. the owner) off payroll while still tracking attendance.
- **Owner analytics**: 30-day KPIs, monthly revenue and new-member charts (6 months), payment-mode split.
- **Responsive gold-on-black UI**: dark Bootstrap 5.3 theme, sidebar on desktop, off-canvas menu on mobile, installable as a home-screen app (web manifest). Display currency, country code and thresholds live in `config/gym.php`.
- **Role-based access**: Laravel middleware (`role:admin,trainer,...`) protects every sensitive page and AJAX endpoint; the Blade layout only shows links a role may use.

## Project layout

```
app/Http/Controllers/AuthController.php   Session login / register / logout (JSON for jQuery)
app/Http/Controllers/Api/   JSON controllers behind /ajax (User, MembershipPlan, MemberPlan,
                             GymClass, ClassSchedule, Booking, Attendance, Payment,
                             Equipment, Dashboard)
app/Http/Middleware/        EnsureUserHasRole (role: middleware alias)
app/Models/                 User, TrainerProfile, MembershipPlan, MemberPlan,
                             GymClass, ClassSchedule, Booking, Attendance, Payment,
                             Equipment, EquipmentMaintenanceLog
app/Services/BillingService.php   Subscription, front-desk enrolment/renewal + automated renewal/expiry logic
app/Services/CollectionService.php    Daily collection (payments + store sales by Cash/UPI/Card)
app/Services/MemberStatusService.php  Active / expiring / expired classification
config/gym.php              Currency symbol, WhatsApp country code, expiring/inactive/low-stock thresholds
app/Console/Commands/RunBillingCommand.php
database/migrations/        Full schema
database/seeders/           Demo admin/trainer/member/receptionist + plans/classes/equipment
routes/web.php              ALL routes: Blade pages + /ajax JSON endpoints, grouped by role
resources/views/            Blade templates (layouts/, auth/, one view per page)
public/js/app.js            Shared jQuery helpers (CSRF, ajax wrapper, escaping, flash, modals)
public/js/pages/*.js        One jQuery script per page
public/css/app.css          Small stylesheet on top of Bootstrap
public/vendor/              jQuery 3.7.1 and Bootstrap 5.3.3 (vendored, no CDN/npm needed)
```

## ⚠️ About this delivery

This code was generated in a sandbox that has **no PHP/Composer and no internet
access**, so it could not be `composer install`-ed or executed here to test it live.
Every file was hand-written to the standard Laravel 11 conventions, but please run
a local test pass (`composer install`, migrate, seed, boot it) before treating it as
production-ready — review especially the middleware/route wiring and the billing
scheduler.

## Setup (run locally)

### 1. Backend

```bash
composer install
cp .env.example .env
php artisan key:generate

# create a MySQL database named gym_management (or edit .env), then:
php artisan migrate --seed   # seeds the SK29 demo data: 12 members, plans in ₹, today's collection, store products

php artisan serve   # http://localhost:8000
```

Demo accounts (seeded, password for all: `password`; on the login page in local mode you can click a role to fill it in):
| Role | Email |
|---|---|
| Admin (owner) | admin@gymfit.test |
| Trainer | trainer@gymfit.test |
| Receptionist | reception@gymfit.test |
| Member | member@gymfit.test |

**Upgrading an existing install:** run `php artisan migrate` (adds the UPI/Card payment methods, the store tables, sale voiding, the stock-movement log, plan-use limits, coupons, and the staff / attendance / payroll tables; keeps your data). After migrating, open **Staff** and use *Set up profile* on each existing staff account (joining date, pay, shift) — that is what puts them on the attendance grid and payroll. Late-arrival rules live in `config/gym.php` (`GYM_LATE_GRACE`, `GYM_LATE_MARKS_PER_HALF_DAY`). `php artisan migrate:fresh --seed` gives you the demo data but wipes the database. The default timezone is now `Asia/Kolkata` (`APP_TIMEZONE` in `.env`) so "today" matches the front desk clock.

### 2. Frontend

There is nothing to build. jQuery and Bootstrap are vendored in `public/vendor/`, and
the pages are plain Blade views served by `php artisan serve` (or your web server's
document root pointed at `public/`). Open http://localhost:8000 and sign in.

### 3. Automated billing scheduler

In production, run Laravel's scheduler via cron (standard for any Laravel app):

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

This triggers `billing:run` daily at 01:00, which renews plans due for billing
and expires lapsed ones. You can also run it manually any time:

```bash
php artisan billing:run
```

### 4. Queue worker (optional, for notifications/emails)

```bash
php artisan queue:work
```

## Routes overview

Everything is in `routes/web.php`.

**Pages** (Blade): `/login`, `/register`, `/` (dashboard), `/classes`, `/bookings`, `/plans`,
`/billing`, `/attendance`, `/my-attendance` (staff), `/staff`, `/staff/attendance` and `/payroll` (admin), `/payroll/{id}/slip` (printable payslip), `/members` (admin/reception/trainer), `/store` and `/sales` (admin/reception), `/sales/{id}/receipt` (printable), and admin-only `/equipment`, `/users`.

**Auth actions**: `POST /login` · `POST /register` · `POST /logout` (session cookie + CSRF token).

**AJAX endpoints** (JSON, called by the pages with jQuery; require a signed-in session and are
role-checked with `role:` middleware):

- `GET /ajax/membership-plans` (everyone; admins add `?include_inactive=1` and also get usage counts + limits), `POST/PUT/DELETE /ajax/membership-plans/{id}` (admin; DELETE deactivates)
- `GET/POST/PUT /ajax/coupons` (admin), `POST /ajax/coupons/check` (any signed-in user: price with a coupon; rate-limited)
- `POST /ajax/member-plans`, `POST /ajax/members`, `POST /ajax/members/{id}/renew` all accept an optional `coupon_code`
- `GET/POST /ajax/member-plans`, `POST /ajax/member-plans/{id}/cancel`
- `GET/POST /ajax/gym-classes`, `GET/POST /ajax/class-schedules`
- `GET/POST /ajax/bookings`, `POST /ajax/bookings/{id}/cancel`
- `GET /ajax/attendances`, `POST /ajax/attendances/check-in`, `POST /ajax/attendances/{id}/check-out`
- `GET/POST /ajax/payments`, `POST /ajax/payments/{id}/mark-paid`, `POST /ajax/payments/{id}/refund`
- `GET/POST/PUT/DELETE /ajax/equipment`, `POST /ajax/equipment/{id}/maintenance`
- `GET /ajax/members` (search + status filter), `POST /ajax/members` (enrol), `POST /ajax/members/{id}/renew`
- `GET /ajax/attendances/day` (a day's check-ins + peak hours)
- `GET /ajax/dashboard/staff`, `GET /ajax/collection?date=` (admin + receptionist)
- `GET/POST /ajax/store/sales` (range with `?from=&to=`), `GET /ajax/store/report`, `GET /ajax/store/sales/export` (CSV)
- `GET /ajax/store/products` (admin + reception); admin only: `POST/PUT /ajax/store/products`, `POST /ajax/store/products/{id}/stock`, `GET /ajax/store/products/{id}/movements`, `POST /ajax/store/sales/{id}/void`
- Staff (admin): `GET/POST /ajax/staff`, `PUT /ajax/staff/{user}`; attendance: `GET/PUT /ajax/staff-attendance` (month grid / mark a day), `POST /ajax/staff-attendance/bulk`; payroll: `GET /ajax/payroll?month=`, `POST /ajax/payroll/generate`, `PUT /ajax/payslips/{id}` (bonus/deduction), `POST /ajax/payslips/{id}/pay`, `DELETE /ajax/payslips/{id}` (drafts only)
- Staff (self-service): `GET /ajax/my/attendance`, `POST /ajax/my/clock-in`, `POST /ajax/my/clock-out`, `GET /ajax/my/payslips`
- `GET/POST/PUT/DELETE /ajax/users` (admin only)
- `GET /ajax/dashboard/admin|trainer|member`

## Suggested next steps

- Add real payment gateway integration (Stripe/Razorpay) in place of the manual `PaymentController@store`.
- Add email/SMS notifications (class reminders, invoice due, low credits) via Laravel Notifications — the `notifications` table is already migrated.
- Add PDF invoice export (barryvdh/laravel-dompdf is already in `composer.json`).
- Add automated tests (PHPUnit/Pest) for the booking/billing edge cases (capacity, waitlist promotion, renewal).
- Add automatic WhatsApp/SMS sending (currently the reminder opens WhatsApp with the message pre-filled for staff to send).
- Add an offline-capable service worker if you want the installed app to open without a connection.
#   g y m _ m a n a g e m e n t _ a p p  
 