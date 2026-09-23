@extends('layouts.app')

@section('title', 'Collection')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Collection</h1>
            <p class="text-secondary small mb-0">Money received day by day — memberships and store — for any period</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-light d-inline-flex align-items-center gap-2 js-export" data-type="days" href="#">
                <svg class="gf-ico gf-ico--sm"><use href="#i-download"/></svg> Day-wise CSV
            </a>
            <a class="btn btn-light d-inline-flex align-items-center gap-2 js-export" data-type="transactions" href="#">
                <svg class="gf-ico gf-ico--sm"><use href="#i-download"/></svg> All transactions CSV
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-xl">
                    <div id="range-presets" class="d-flex flex-wrap gap-2">
                        @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'month' => 'This month', 'last-month' => 'Last month', '3m' => 'Last 3 months', '6m' => 'Last 6 months'] as $key => $label)
                            <button type="button" class="btn btn-light btn-sm gf-chip {{ $key === 'month' ? 'active' : '' }}" data-preset="{{ $key }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="col-6 col-xl-auto">
                    <label class="form-label gf-eyebrow mb-1" for="range-from">From</label>
                    <input type="date" id="range-from" class="form-control form-control-sm" max="{{ today()->toDateString() }}">
                </div>
                <div class="col-6 col-xl-auto">
                    <label class="form-label gf-eyebrow mb-1" for="range-to">To</label>
                    <input type="date" id="range-to" class="form-control form-control-sm" max="{{ today()->toDateString() }}">
                </div>
            </div>
            <div class="small text-secondary mt-2" id="range-label"></div>
        </div>
    </div>

    <div id="col-kpis" class="row g-2 g-lg-3 mb-3"></div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3" id="col-chart-title">Day-wise collection</h2>
                <div id="col-chart"></div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">Payment mode</h2>
                <div id="col-methods"></div>
                <h2 class="h6 mt-4 mb-2">Source</h2>
                <div id="col-sources"></div>
                <div id="col-facts" class="mt-4"></div>
            </div></div>
        </div>
    </div>

    <div class="card mb-3 d-none" id="col-months-card">
        <div class="card-body">
            <h2 class="h6 mb-2">Month-wise</h2>
            <div id="col-months"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h2 class="h6 mb-0">Day-wise</h2>
                <div class="form-check form-switch small mb-0">
                    <input class="form-check-input" type="checkbox" id="hide-empty" checked>
                    <label class="form-check-label text-secondary" for="hide-empty">Hide days with no collection</label>
                </div>
            </div>
            <div id="col-days"><div class="gf-empty">Loading…</div></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/collection.js') }}?v={{ filemtime(public_path('js/pages/collection.js')) }}"></script>
@endpush
