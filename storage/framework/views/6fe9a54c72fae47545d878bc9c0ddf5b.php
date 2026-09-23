<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="theme-color" content="#121212">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo e(config('app.name')); ?>">
    <title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> &middot; <?php echo e(config('app.name')); ?></title>
    <script>
        // Apply the saved light/dark choice before the stylesheet paints (dark is the default). See App.setTheme in js/app.js.
        (function () {
            var theme = 'dark';
            try { theme = localStorage.getItem('gf-theme') === 'light' ? 'light' : 'dark'; } catch (e) {}
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.querySelector('meta[name="theme-color"]').setAttribute('content', theme === 'light' ? '#f5f5f3' : '#121212');
        })();
    </script>

    <link rel="manifest" href="<?php echo e(route('manifest')); ?>">
    <link rel="icon" type="image/png" href="<?php echo e(asset('images/icon-192.png')); ?>">
    <link rel="apple-touch-icon" href="<?php echo e(asset('images/icon-192.png')); ?>">

    <link href="<?php echo e(asset('vendor/bootstrap/bootstrap.min.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('css/app.css')); ?>?v=<?php echo e(filemtime(public_path('css/app.css'))); ?>" rel="stylesheet">
</head>
<body>
    <?php echo $__env->make('partials.icons', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->yieldContent('body'); ?>

    <script src="<?php echo e(asset('vendor/jquery/jquery.min.js')); ?>"></script>
    <script src="<?php echo e(asset('vendor/bootstrap/bootstrap.bundle.min.js')); ?>"></script>
    <script>
        window.App = <?php echo e(Illuminate\Support\Js::from([
            'baseUrl' => url('/'),
            'user' => auth()->check() ? ['id' => auth()->id(), 'name' => auth()->user()->name] : null,
            'role' => auth()->user()?->role,
            'brand' => config('app.name'),
            'currency' => config('gym.currency'),
            'countryCode' => config('gym.country_code'),
            'expiringDays' => config('gym.expiring_days'),
            'inactiveDays' => config('gym.inactive_days'),
        ])); ?>;
    </script>
    <script src="<?php echo e(asset('js/app.js')); ?>?v=<?php echo e(filemtime(public_path('js/app.js'))); ?>"></script>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/layouts/base.blade.php ENDPATH**/ ?>