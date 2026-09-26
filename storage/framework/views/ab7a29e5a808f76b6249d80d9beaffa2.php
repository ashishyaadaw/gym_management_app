<?php $__env->startSection('title', 'Link not available'); ?>

<?php $__env->startSection('content'); ?>
    <h1 class="h4 mb-2">This link can't be used</h1>
    <p class="text-secondary mb-0">
        <?php switch($status):
            case ('used'): ?> This registration link has already been used. <?php break; ?>
            <?php case ('expired'): ?> This registration link has expired. <?php break; ?>
            <?php case ('revoked'): ?> This registration link has been cancelled. <?php break; ?>
            <?php default: ?> This registration link is not valid.
        <?php endswitch; ?>
        Please ask the front desk at <?php echo e(config('app.name')); ?> for a new one.
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/auth/join-closed.blade.php ENDPATH**/ ?>