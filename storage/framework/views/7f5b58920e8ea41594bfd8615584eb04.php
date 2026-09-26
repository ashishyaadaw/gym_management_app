<?php $__env->startSection('title', 'Register'); ?>
<?php $__env->startSection('card-width', '36rem'); ?>

<?php $__env->startSection('content'); ?>
    <div id="join-body">
        <h1 class="h4 mb-1">Welcome to <?php echo e(config('app.name')); ?></h1>
        <p class="text-secondary small mb-4">
            Fill in your details to register. You can choose your membership plan at the front desk.
            This link works once and expires <?php echo e($link->expires_at->timezone(config('app.timezone'))->format('j M, g:i a')); ?>.
        </p>

        <form id="join-form" novalidate data-action="<?php echo e(route('join.store', $link->token)); ?>">
            <div class="row g-3">
                <div class="col-12"><div class="gf-eyebrow">Contact</div></div>
                <div class="col-12">
                    <label class="form-label small fw-medium" for="name">Full name *</label>
                    <input id="name" name="name" class="form-control" maxlength="255" autocomplete="name" required autofocus>
                </div>
                <div class="col-sm-6">
                    <label class="form-label small fw-medium" for="phone">Phone * (10 digits)</label>
                    <input id="phone" name="phone" class="form-control" inputmode="numeric" maxlength="10" autocomplete="tel-national" placeholder="9876543210" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label small fw-medium" for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control" autocomplete="email" placeholder="Optional">
                </div>
                <div class="col-sm-6">
                    <label class="form-label small fw-medium" for="password">Password</label>
                    <input id="password" name="password" type="password" class="form-control" autocomplete="new-password" placeholder="Optional, min 8 characters">
                </div>
                <div class="col-sm-6">
                    <label class="form-label small fw-medium" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-12 form-text mt-1">Set an email and password only if you want to sign in online to see your plan, attendance and payments.</div>

                <?php echo $__env->make('partials.member-profile-fields', ['public' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

            <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
            <button class="btn btn-primary btn-lg w-100 mt-3" type="submit">Register</button>
        </form>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/pages/join.js')); ?>?v=<?php echo e(filemtime(public_path('js/pages/join.js'))); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/auth/join.blade.php ENDPATH**/ ?>