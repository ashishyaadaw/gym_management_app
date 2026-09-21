@extends('layouts.base')

@php
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
        ['classes', 'Classes', 'calendar', ['admin', 'trainer', 'member', 'receptionist']],
        ['bookings', 'Bookings', 'bookmark', ['admin', 'trainer', 'member', 'receptionist']],
        ['plans', 'Membership Plans', 'tag', ['admin', 'trainer', 'member', 'receptionist']],
        ['billing', 'Billing', 'card', ['admin', 'trainer', 'member', 'receptionist']],
        ['equipment', 'Equipment', 'tool', ['admin']],
        ['users', 'Users', 'shield', ['admin']],
        ['staff', 'Staff', 'briefcase', ['admin']],
        ['staff.attendance', 'Staff Attendance', 'clock', ['admin']],
        ['payroll', 'Payroll', 'cash', ['admin']],
    ];
@endphp

@section('body')
    <div class="gf-shell">
        <header class="gf-topbar d-lg-none">
            <button class="btn btn-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#gf-nav" aria-controls="gf-nav" aria-label="Open menu">
                <svg class="gf-ico"><use href="#i-menu"/></svg>
            </button>
            <div class="gf-brand-logo gf-brand-logo--sm" role="img" aria-label="{{ config('app.name') }}"></div>
            <button type="button" class="btn btn-light btn-sm js-logout" aria-label="Log out">
                <svg class="gf-ico"><use href="#i-logout"/></svg>
            </button>
        </header>

        <div class="gf-sidebar">
            <div id="gf-nav" class="offcanvas-lg offcanvas-start" tabindex="-1" aria-label="Main menu">
                <div class="gf-side-inner p-3">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="gf-brand-logo flex-grow-1" role="img" aria-label="{{ config('app.name') }}"></div>
                        <button type="button" class="btn btn-light btn-sm d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#gf-nav" aria-label="Close menu">
                            <svg class="gf-ico"><use href="#i-x"/></svg>
                        </button>
                    </div>

                    <nav class="flex-grow-1 overflow-auto">
                        @foreach ($links as [$route, $label, $icon, $roles])
                            {{-- The owner only needs "My Attendance" once they have a staff profile of their own. --}}
                            @continue($route === 'my.attendance' && $role === 'admin' && ! $user->staffProfile)
                            @if (in_array($role, $roles, true))
                                <a href="{{ route($route) }}" class="gf-nav-link {{ request()->routeIs($route) ? 'active' : '' }}"
                                   @if (request()->routeIs($route)) aria-current="page" @endif>
                                    <svg class="gf-ico"><use href="#i-{{ $icon }}"/></svg>
                                    {{ $label }}
                                </a>
                            @endif
                        @endforeach
                    </nav>

                    <div class="pt-3 mt-2 border-top" style="border-color: var(--gf-line-soft) !important">
                        <div class="gf-who mb-2">
                            <div class="gf-eyebrow">Signed in</div>
                            <div class="fw-semibold gf-truncate">{{ $user->name }}</div>
                            <div class="small text-secondary text-capitalize">{{ $role }}</div>
                        </div>
                        <button type="button" class="btn btn-light w-100 js-logout d-flex align-items-center justify-content-center gap-2">
                            <svg class="gf-ico gf-ico--sm"><use href="#i-logout"/></svg> Log out
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <main class="gf-main p-3 p-sm-4 p-lg-5">
            @yield('content')
        </main>
    </div>

    <div id="gf-flash" class="gf-flash" role="status" aria-live="polite"></div>
@endsection
