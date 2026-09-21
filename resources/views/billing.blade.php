@extends('layouts.app')

@section('title', 'Billing')

@section('content')
    <div class="mb-4">
        <h1 class="gf-page-title">Billing &amp; Payments</h1>
        <p class="text-secondary small mb-0">Invoices, dues and manual payments</p>
    </div>

    @if (in_array(auth()->user()->role, ['admin', 'receptionist'], true))
        <div class="card mb-4">
            <div class="card-body">
                <div class="gf-eyebrow mb-3">Record a payment</div>
                <form id="payment-form" class="row g-3 align-items-start" novalidate>
                    <div class="col-lg-4">
                        <label class="form-label small fw-medium">Member</label>
                        <div class="gf-picker" data-picker>
                            <input type="text" class="form-control gf-picker-input" placeholder="Search name or phone…" autocomplete="off">
                            <input type="hidden" name="user_id">
                        </div>
                    </div>
                    <div class="col-sm-4 col-lg-2">
                        <label class="form-label small fw-medium">Amount ({{ config('gym.currency') }})</label>
                        <input name="amount" type="number" step="0.01" min="0" class="form-control" required>
                    </div>
                    <div class="col-sm-4 col-lg-3">
                        <label class="form-label small fw-medium">Method</label>
                        <select name="method" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank transfer</option>
                        </select>
                    </div>
                    <div class="col-sm-4 col-lg-3 d-flex align-items-end" style="min-height:4.4rem">
                        <button type="submit" class="btn btn-primary w-100">Record payment</button>
                    </div>
                    <div class="col-12">
                        <p class="gf-error text-danger small mb-0 d-none" role="alert"></p>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body py-2 px-3">
            <div id="payment-list"><div class="gf-empty">Loading…</div></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/billing.js') }}?v={{ filemtime(public_path('js/pages/billing.js')) }}"></script>
@endpush
