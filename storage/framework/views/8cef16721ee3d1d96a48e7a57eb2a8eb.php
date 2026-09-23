

<?php
    $user = auth()->user();
    $role = $user->role;
    // [route, label, icon, roles allowed]
    $links = [
        ['dashboard', 'Dashboard', 'dashboard', ['admin', 'trainer', 'member', 'receptionist']],
        ['members', 'Members', 'users', ['admin', 'receptionist', 'trainer']],
        ['attendance', 'Attendance', 'check', ['admin', 'trainer', 'member', 'receptionist']],
        ['my.attendance', 'My Attendance', 'clock', ['admin', 'trainer', 'receptionist']],
        ['store', 'POS Store', 'bag', ['admin', 'receptionist']],
        ['sales', 'Sales', 'chart', ['admin', 'receptionist']],
        ['collection', 'Collection', 'trend', ['admin', 'receptionist']],
        ['expenses', 'Expenses', 'wallet', ['admin', 'receptionist']],
        ['classes', 'Classes', 'calendar', ['admin', 'trainer', 'member', 'receptionist']],
        ['bookings', 'Bookings', 'bookmark', ['admin', 'trainer', 'member', 'receptionist']],
        ['plans', 'Membership Plans', 'tag', ['admin', 'trainer', 'member', 'receptionist']],
        ['billing', 'Billing', 'card', ['admin', 'trainer', 'member', 'receptionist']],
        ['equipment', 'Equipment', 'tool', ['admin']],
        ['users', 'Users', 'shield', ['admin']],
        ['staff', 'Staff', 'briefcase', ['admin']],
        ['staff.attendance', 'Staff Attendance', 'clock', ['admin']],
        ['payroll', 'Payroll', 'cash', ['admin']],
        ['past.records', 'Past Records', 'history', ['admin']],
    ];
?>

<?php $__env->startSection('body'); ?>
    <div class="gf-shell">
        <header class="gf-topbar d-lg-none">
            <button class="btn btn-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#gf-nav" aria-controls="gf-nav" aria-label="Open menu">
                <svg class="gf-ico"><use href="#i-menu"/></svg>
            </button>
            <div class="gf-brand-logo gf-brand-logo--sm" role="img" aria-label="<?php echo e(config('app.name')); ?>"></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light btn-sm js-theme-toggle" aria-label="Switch light / dark mode" title="Switch light / dark mode">
                    <svg class="gf-ico gf-when-dark"><use href="#i-sun"/></svg>
                    <svg class="gf-ico gf-when-light"><use href="#i-moon"/></svg>
                </button>
                <button type="button" class="btn btn-light btn-sm js-logout" aria-label="Log out">
                    <svg class="gf-ico"><use href="#i-logout"/></svg>
                </button>
            </div>
        </header>

        <div class="gf-sidebar">
            <div id="gf-nav" class="offcanvas-lg offcanvas-start" tabindex="-1" aria-label="Main menu">
                <div class="gf-side-inner p-3">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="gf-brand-logo flex-grow-1" role="img" aria-label="<?php echo e(config('app.name')); ?>"></div>
                        <button type="button" class="btn btn-light btn-sm d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#gf-nav" aria-label="Close menu">
                            <svg class="gf-ico"><use href="#i-x"/></svg>
                        </button>
                    </div>

                    <nav class="flex-grow-1 overflow-auto">
                        <?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$route, $label, $icon, $roles]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            
                            <?php if($route === 'my.attendance' && $role === 'admin' && ! $user->staffProfile) continue; ?>
                            <?php if(in_array($role, $roles, true)): ?>
                                <a href="<?php echo e(route($route)); ?>" class="gf-nav-link <?php echo e(request()->routeIs($route) ? 'active' : ''); ?>"
                                   <?php if(request()->routeIs($route)): ?> aria-current="page" <?php endif; ?>>
                                    <svg class="gf-ico"><use href="#i-<?php echo e($icon); ?>"/></svg>
                                    <?php echo e($label); ?>

                                </a>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </nav>

                    <div class="pt-3 mt-2 border-top" style="border-color: var(--gf-line-soft) !important">
                        <div class="gf-who mb-2">
                            <div class="gf-eyebrow">Signed in</div>
                            <div class="fw-semibold gf-truncate"><?php echo e($user->name); ?></div>
                            <div class="small text-secondary text-capitalize"><?php echo e($role); ?></div>
                        </div>
                        <button type="button" class="btn btn-light w-100 mb-2 js-theme-toggle d-flex align-items-center justify-content-center gap-2">
                            <span class="gf-when-dark d-inline-flex align-items-center gap-2"><svg class="gf-ico gf-ico--sm"><use href="#i-sun"/></svg> Light mode</span>
                            <span class="gf-when-light d-inline-flex align-items-center gap-2"><svg class="gf-ico gf-ico--sm"><use href="#i-moon"/></svg> Dark mode</span>
                        </button>
                        <button type="button" class="btn btn-light w-100 js-logout d-flex align-items-center justify-content-center gap-2">
                            <svg class="gf-ico gf-ico--sm"><use href="#i-logout"/></svg> Log out
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <main class="gf-main p-3 p-sm-4 p-lg-5">
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>

    <div id="gf-flash" class="gf-flash" role="status" aria-live="polite"></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/layouts/app.blade.php ENDPATH**/ ?>