@extends('layouts.guest')

@section('title', 'Create account')
@section('card-width', '28rem')

@section('content')
    <h1 class="h4 mb-1">Join the gym</h1>
    <p class="text-secondary small mb-4">Create your member account</p>

    <form id="register-form" novalidate>
        <div class="mb-3">
            <label class="form-label small fw-medium" for="name">Full name</label>
            <input id="name" name="name" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-medium" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-medium" for="phone">Phone</label>
            <input id="phone" name="phone" class="form-control">
        </div>
        <div class="row g-3 mb-3">
            <div class="col-6">
                <label class="form-label small fw-medium" for="password">Password</label>
                <input id="password" name="password" type="password" class="form-control" required>
            </div>
            <div class="col-6">
                <label class="form-label small fw-medium" for="password_confirmation">Confirm</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required>
            </div>
        </div>
        <p class="gf-error text-danger small d-none"></p>
        <button class="btn btn-primary w-100" type="submit">Create account</button>
    </form>

    <p class="small text-secondary mt-3 mb-0">
        Already a member? <a href="{{ route('login') }}" class="fw-medium text-decoration-none">Sign in</a>
    </p>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/register.js') }}"></script>
@endpush
