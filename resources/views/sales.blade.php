@extends('layouts.app')

@section('title', 'Sales')

@section('content')
    @php($isAdmin = auth()->user()->role === 'admin')

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Store Sales</h1>
            <p class="text-secondary small mb-0">Sales history, best-sellers{{ $isAdmin ? ', profit' : '' }} and receipts</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-light d-inline-flex align-items-center gap-2" href="{{ route('store') }}">
                <svg class="gf-ico gf-ico--sm"><use href="#i-bag"/></svg> Open POS
            </a>
            <a class="btn btn-light d-inline-flex align-items-center gap-2" id="btn-export" href="#">
                <svg class="gf-ico gf-ico--sm"><use href="#i-download"/></svg> Export CSV
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg">
                    <div id="range-presets" class="d-flex flex-wrap gap-2">
                        @foreach (['today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', 'month' => 'This month'] as $key => $label)
                            <button type="button" class="btn btn-light btn-sm gf-chip {{ $key === 'today' ? 'active' : '' }}" data-preset="{{ $key }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="col-6 col-lg-auto">
                    <label class="form-label gf-eyebrow mb-1" for="range-from">From</label>
                    <input type="date" id="range-from" class="form-control form-control-sm" max="{{ now()->toDateString() }}">
                </div>
                <div class="col-6 col-lg-auto">
                    <label class="form-label gf-eyebrow mb-1" for="range-to">To</label>
                    <input type="date" id="range-to" class="form-control form-control-sm" max="{{ now()->toDateString() }}">
                </div>
            </div>
        </div>
    </div>

    <div id="sales-kpis" class="row g-2 g-lg-3 mb-3"></div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">Revenue by day</h2>
                <div id="sales-chart"></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">Payment mode</h2>
                <div id="sales-methods"></div>
                <h2 class="h6 mt-4 mb-2">Best sellers</h2>
                <div id="sales-top"></div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0">Sales</h2>
                <span class="gf-eyebrow" id="sales-count"></span>
            </div>
            <div id="sales-table"><div class="gf-empty">Loading…</div></div>
        </div>
    </div>

    @if ($isAdmin)
        <div class="modal fade" id="void-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form id="void-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6">Void sale <span class="js-void-receipt text-secondary"></span></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-secondary">The items go back into stock and this sale stops counting as revenue. This can't be undone.</p>
                        <label class="form-label small fw-medium" for="void-reason">Reason (optional)</label>
                        <input id="void-reason" class="form-control" maxlength="255" placeholder="e.g. wrong item rung up">
                        <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep sale</button>
                        <button type="submit" class="btn btn-danger">Void sale</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>window.SalesPage = { isAdmin: @json($isAdmin) };</script>
    <script src="{{ asset('js/pages/sales.js') }}?v={{ filemtime(public_path('js/pages/sales.js')) }}"></script>
@endpush
