

<?php $__env->startSection('body'); ?>
    <button type="button" class="btn btn-light btn-sm gf-theme-corner js-theme-toggle" aria-label="Switch light / dark mode" title="Switch light / dark mode">
        <svg class="gf-ico gf-when-dark"><use href="#i-sun"/></svg>
        <svg class="gf-ico gf-when-light"><use href="#i-moon"/></svg>
    </button>
    <div class="gf-auth">
        <div class="card w-100" style="max-width: <?php echo $__env->yieldContent('card-width', '26rem'); ?>">
            <div class="card-body p-4 p-sm-5">
                <div class="gf-brand-logo gf-brand-logo--lg mb-4" role="img" aria-label="<?php echo e(config('app.name')); ?>"></div>
                <?php echo $__env->yieldContent('content'); ?>
            </div>
        </div>
    </div>
    <div id="gf-flash" class="gf-flash" role="status" aria-live="polite"></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/layouts/guest.blade.php ENDPATH**/ ?>