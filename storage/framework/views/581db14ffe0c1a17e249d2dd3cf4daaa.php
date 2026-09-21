

<?php $__env->startSection('title', 'Sign in'); ?>

<?php $__env->startSection('content'); ?>
    <h1 class="h4 text-center mb-1">Welcome back</h1>
    <p class="text-secondary small text-center mb-4">Sign in to <?php echo e(config('app.name')); ?></p>

    <form id="login-form" novalidate>
        <div class="mb-3">
            <label class="form-label gf-eyebrow" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control form-control-lg" placeholder="you@example.com" autocomplete="username" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label gf-eyebrow" for="password">Password</label>
            <input id="password" name="password" type="password" class="form-control form-control-lg" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <p class="gf-error text-danger small d-none" role="alert"></p>
        <button class="btn btn-primary btn-lg w-100 mt-1" type="submit">Sign in</button>
    </form>

    <p class="small text-secondary text-center mt-3 mb-0">
        New member? <a href="<?php echo e(route('register')); ?>" class="fw-semibold text-decoration-none">Create an account</a>
    </p>

    <?php if(app()->environment('local')): ?>
        <!-- <div class="mt-4 pt-3 border-top" style="border-color: var(--gf-line-soft) !important">
            <div class="gf-eyebrow mb-1">Demo logins</div>
            <p class="small text-secondary mb-2">Every demo account uses the password <code>password</code>.</p>
            <div class="d-flex flex-wrap gap-2">
                <?php $__currentLoopData = ['admin' => 'Owner', 'reception' => 'Reception', 'trainer' => 'Trainer', 'member' => 'Member']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <button type="button" class="btn btn-light btn-sm js-demo" data-email="<?php echo e($key); ?>@gymfit.test"><?php echo e($label); ?></button>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div> -->
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/pages/login.js')); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/auth/login.blade.php ENDPATH**/ ?>