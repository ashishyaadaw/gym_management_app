<?php $__env->startSection('title', 'Attendance'); ?>

<?php $__env->startSection('content'); ?>
    <?php ($isStaff = in_array(auth()->user()->role, ['admin', 'receptionist', 'trainer'], true)); ?>

    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Attendance &amp; Check-ins</h1>
            <p class="text-secondary small mb-0">
                <?php echo e($isStaff ? 'Check members in at the front desk and watch the floor traffic' : 'Your visit history'); ?>

            </p>
        </div>
        <?php if($isStaff): ?>
            <input type="date" id="att-date" class="form-control" style="max-width:11rem" max="<?php echo e(now()->toDateString()); ?>" value="<?php echo e(now()->toDateString()); ?>" aria-label="Day">
        <?php endif; ?>
    </div>

    <?php if($isStaff): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="gf-eyebrow mb-2">Quick check-in</div>
                <form id="checkin-form" class="row g-2 align-items-start" novalidate>
                    <div class="col">
                        <div class="gf-picker" data-picker>
                            <input type="text" class="form-control form-control-lg gf-picker-input" placeholder="Enter phone or name to check in…" autocomplete="off">
                            <input type="hidden" name="user_id">
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-lg" id="btn-checkin" disabled>Check in</button>
                    </div>
                    <div class="col-12"><p class="gf-error text-danger small mb-0 d-none" role="alert"></p></div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="gf-kpi gf-kpi--total"><div class="gf-kpi-label"><span>Check-ins</span></div>
                    <div class="gf-kpi-value" id="att-count">–</div><div class="gf-kpi-sub" id="att-day-label">Today</div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="gf-kpi gf-kpi--good"><div class="gf-kpi-label"><span>Inside now</span></div>
                    <div class="gf-kpi-value" id="att-inside">–</div><div class="gf-kpi-sub">Not checked out yet</div></div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100"><div class="card-body py-3">
                    <div class="gf-eyebrow mb-2">Peak hours <span class="text-secondary">· 6 AM – 10 PM</span></div>
                    <div id="att-peak"></div>
                </div></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body py-2 px-3">
            <div id="attendance-list"><div class="gf-empty">Loading…</div></div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/pages/attendance.js')); ?>?v=<?php echo e(filemtime(public_path('js/pages/attendance.js'))); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/attendance.blade.php ENDPATH**/ ?>