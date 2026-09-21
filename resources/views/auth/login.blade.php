@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1 class="h4 text-center mb-1">Welcome back</h1>
    <p class="text-secondary small text-center mb-4">Sign in to {{ config('app.name') }}</p>

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
        New member? <a href="{{ route('register') }}" class="fw-semibold text-decoration-none">Create an account</a>
    </p>

    @if (app()->environment('local'))
        <!-- <div class="mt-4 pt-3 border-top" style="border-color: var(--gf-line-soft) !important">
            <div class="gf-eyebrow mb-1">Demo logins</div>
            <p class="small text-secondary mb-2">Every demo account uses the password <code>password</code>.</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach (['admin' => 'Owner', 'reception' => 'Reception', 'trainer' => 'Trainer', 'member' => 'Member'] as $key => $label)
                    <button type="button" class="btn btn-light btn-sm js-demo" data-email="{{ $key }}@gymfit.test">{{ $label }}</button>
                @endforeach
            </div>
        </div> -->
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/login.js') }}"></script>
@endpush
