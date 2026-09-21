<?php

return [
    // Shown in the UI. Prices are stored as plain decimals; this is only the display symbol.
    'currency' => env('GYM_CURRENCY', '₹'),

    // Prefixed to 10-digit phone numbers when building wa.me (WhatsApp) links.
    'country_code' => env('GYM_COUNTRY_CODE', '91'),

    // A membership ending within this many days counts as "expiring soon".
    'expiring_days' => (int) env('GYM_EXPIRING_DAYS', 5),

    // Members with no check-in for this many days show up as "inactive".
    'inactive_days' => (int) env('GYM_INACTIVE_DAYS', 7),

    // Products at or below this stock level are flagged "low stock" in the store.
    'low_stock' => (int) env('GYM_LOW_STOCK', 3),

    // ---- Staff attendance & payroll ----
    // Clocking in up to this many minutes after the shift start is still "on time".
    'late_grace_minutes' => (int) env('GYM_LATE_GRACE', 10),

    // Every this many late arrivals in a month cost half a day's pay. 0 turns the late deduction off.
    'late_marks_per_half_day' => (int) env('GYM_LATE_MARKS_PER_HALF_DAY', 3),
];
