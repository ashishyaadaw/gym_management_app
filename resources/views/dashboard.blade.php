@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php($user = auth()->user())
    @php($isStaff = in_array($user->role, ['admin', 'receptionist'], true))

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Dashboard Overview</h1>
            <p class="text-secondary small mb-0">{{ config('app.name') }} &bull; {{ now()->format('l, j F') }} &bull; Welcome, {{ $user->name }}</p>
        </div>
        @if ($isStaff)
            <a href="{{ route('members') }}?add=1" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2">
                <svg class="gf-ico"><use href="#i-plus"/></svg> Add Member
            </a>
        @endif
    </div>

    @if ($isStaff)
        {{-- Filled by public/js/pages/dashboard.js from /ajax/dashboard/staff (+ /ajax/dashboard/admin for admins) --}}
        <div id="dashboard-staff"><div class="gf-empty">Loading dashboard…</div></div>
        @if ($user->role === 'admin')
            <h2 class="h5 mt-5 mb-3">Business overview <span class="text-secondary fw-normal small">· last 30 days</span></h2>
            <div id="dashboard-admin"></div>
        @endif
    @elseif ($user->role === 'trainer')
        <div class="card">
            <div class="card-body">
                <h2 class="h6 mb-3">Upcoming classes</h2>
                <div id="dashboard-trainer"></div>
            </div>
        </div>
    @else
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
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/dashboard.js') }}"></script>
@endpush
