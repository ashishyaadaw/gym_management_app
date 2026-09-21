<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
    <?php ($user = auth()->user()); ?>
    <?php ($isStaff = in_array($user->role, ['admin', 'receptionist'], true)); ?>

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Dashboard Overview</h1>
            <p class="text-secondary small mb-0"><?php echo e(config('app.name')); ?> &bull; <?php echo e(now()->format('l, j F')); ?> &bull; Welcome, <?php echo e($user->name); ?></p>
        </div>
        <?php if($isStaff): ?>
            <a href="<?php echo e(route('members')); ?>?add=1" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2">
                <svg class="gf-ico"><use href="#i-plus"/></svg> Add Member
            </a>
        <?php endif; ?>
    </div>

    <?php if($isStaff): ?>
        
        <div id="dashboard-staff"><div class="gf-empty">Loading dashboard…</div></div>
        <?php if($user->role === 'admin'): ?>
            <h2 class="h5 mt-5 mb-3">Business overview <span class="text-secondary fw-normal small">· last 30 days</span></h2>
            <div id="dashboard-admin"></div>
        <?php endif; ?>
    <?php elseif($user->role === 'trainer'): ?>
        <div class="card">
            <div class="card-body">
                <h2 class="h6 mb-3">Upcoming classes</h2>
                <div id="dashboard-trainer"></div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="gf-eyebrow mb-2">Your plan</div>
                        <div id="dashboard-member-plan"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="gf-eyebrow mb-2">Upcoming bookings</div>
                        <div id="dashboard-member-bookings"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/pages/dashboard.js')); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/dashboard.blade.php ENDPATH**/ ?>