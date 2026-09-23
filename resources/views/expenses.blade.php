@extends('layouts.app')

@section('title', 'Expenses')

@section('content')
    @php($isAdmin = auth()->user()->isAdmin())

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Expenses</h1>
            <p class="text-secondary small mb-0">Daily spending — rent, bills, repairs, supplies and petty cash{{ $isAdmin ? ', against income' : '' }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-light d-inline-flex align-items-center gap-2" id="btn-export" href="#">
                <svg class="gf-ico gf-ico--sm"><use href="#i-download"/></svg> Export CSV
            </a>
            <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="button" id="btn-add-expense">
                <svg class="gf-ico"><use href="#i-plus"/></svg> Add Expense
            </button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-xl">
                    <div id="range-presets" class="d-flex flex-wrap gap-2">
                        @foreach (['today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', 'month' => 'This month', 'last-month' => 'Last month'] as $key => $label)
                            <button type="button" class="btn btn-light btn-sm gf-chip {{ $key === 'month' ? 'active' : '' }}" data-preset="{{ $key }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-auto">
                    <label class="form-label gf-eyebrow mb-1" for="range-from">From</label>
                    <input type="date" id="range-from" class="form-control form-control-sm" max="{{ today()->toDateString() }}">
                </div>
                <div class="col-6 col-md-4 col-xl-auto">
                    <label class="form-label gf-eyebrow mb-1" for="range-to">To</label>
                    <input type="date" id="range-to" class="form-control form-control-sm" max="{{ today()->toDateString() }}">
                </div>
                <div class="col-12 col-md-4 col-xl-auto">
                    <label class="form-label gf-eyebrow mb-1" for="filter-category">Category</label>
                    <select id="filter-category" class="form-select form-select-sm">
                        <option value="">All categories</option>
                        @foreach (\App\Models\Expense::CATEGORIES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div id="expense-kpis" class="row g-2 g-lg-3 mb-3"></div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">Spent by day</h2>
                <div id="expense-chart"></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">By category</h2>
                <div id="expense-categories"></div>
                <h2 class="h6 mt-4 mb-2">Paid by</h2>
                <div id="expense-methods"></div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0">Entries</h2>
                <span class="gf-eyebrow" id="expense-count"></span>
            </div>
            <div id="expense-table"><div class="gf-empty">Loading…</div></div>
        </div>
    </div>

    {{-- Add / edit an expense --}}
    <div class="modal fade" id="expense-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form id="expense-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6 js-title">Add expense</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label small fw-medium" for="ex-date">Date *</label>
                            <input id="ex-date" name="spent_on" type="date" class="form-control" max="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-medium" for="ex-amount">Amount ({{ config('gym.currency') }}) *</label>
                            <input id="ex-amount" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" class="form-control" placeholder="0" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-medium" for="ex-category">Category *</label>
                            <select id="ex-category" name="category" class="form-select" required>
                                @foreach (\App\Models\Expense::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-medium" for="ex-method">Paid by *</label>
                            <select id="ex-method" name="method" class="form-select" required>
                                @foreach (\App\Models\Expense::METHODS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium" for="ex-description">What was it for? *</label>
                            <input id="ex-description" name="description" class="form-control" maxlength="255" placeholder="e.g. Electricity bill for August" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-medium" for="ex-paid-to">Paid to</label>
                            <input id="ex-paid-to" name="paid_to" class="form-control" maxlength="255" placeholder="Vendor or person">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-medium" for="ex-reference">Bill / reference no.</label>
                            <input id="ex-reference" name="reference" class="form-control" maxlength="100" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium" for="ex-notes">Notes</label>
                            <textarea id="ex-notes" name="notes" class="form-control" rows="2" maxlength="1000"></textarea>
                        </div>
                    </div>
                    <p class="small text-secondary mt-3 mb-0">Staff salaries are paid from <b>Payroll</b> — don't add them here too.</p>
                    <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save expense</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete confirmation --}}
    <div class="modal fade" id="delete-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6">Delete expense?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body small"><span class="js-delete-what"></span> will be removed. This can't be undone.</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep</button>
                    <button type="button" class="btn btn-danger js-confirm-delete">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.ExpensesPage = {
            methods: @json(\App\Models\Expense::METHODS)
        };
    </script>
    <script src="{{ asset('js/pages/expenses.js') }}?v={{ filemtime(public_path('js/pages/expenses.js')) }}"></script>
@endpush
