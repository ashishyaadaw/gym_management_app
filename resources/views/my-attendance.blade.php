@extends('layouts.app')

@section('title', 'My Attendance')

@section('content')
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">My Attendance</h1>
            <p class="text-secondary small mb-0" id="my-sub">Clock in when you arrive, clock out when you leave</p>
        </div>
        <div class="gf-monthnav">
            <button type="button" class="btn btn-light btn-sm" id="month-prev" aria-label="Previous month"><svg class="gf-ico gf-ico--sm"><use href="#i-chevron-left"/></svg></button>
            <span class="gf-monthlabel" id="month-label">…</span>
            <button type="button" class="btn btn-light btn-sm" id="month-next" aria-label="Next month"><svg class="gf-ico gf-ico--sm"><use href="#i-chevron-right"/></svg></button>
        </div>
    </div>

    <div id="my-empty" class="card d-none"><div class="card-body text-center py-5">
        <div class="fs-5 fw-bold mb-1">No staff profile yet</div>
        <p class="text-secondary mb-0">Ask the gym owner to set up your employment details so you can clock in and receive payslips.</p>
    </div></div>

    <div id="my-body" class="d-none">
        <div class="row g-3 mb-3">
            <div class="col-lg-5">
                <div class="card gf-hero h-100"><div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                    <div class="gf-eyebrow" id="clock-date"></div>
                    <div class="gf-clock my-2" id="clock-now">--:--:--</div>
                    <div class="mb-3 small" id="clock-state"></div>
                    <div class="d-grid"><button type="button" class="btn btn-primary btn-lg" id="btn-clock">…</button></div>
                    <p class="gf-error text-danger small mt-3 mb-0 d-none" id="clock-error" role="alert"></p>
                    <div class="small text-secondary mt-3" id="clock-shift"></div>
                </div></div>
            </div>
            <div class="col-lg-7">
                <div class="row g-2 g-lg-3 mb-3" id="my-kpis"></div>
                <div class="card"><div class="card-body">
                    <div class="gf-cal mb-1" id="cal-head"></div>
                    <div class="gf-cal" id="cal"></div>
                </div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">My payslips</h2>
                    <span class="gf-eyebrow">Shown once paid</span>
                </div>
                <div id="my-payslips"><div class="gf-empty">Loading…</div></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/my-attendance.js') }}?v={{ filemtime(public_path('js/pages/my-attendance.js')) }}"></script>
@endpush
