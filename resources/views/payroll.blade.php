@extends('layouts.app')

@section('title', 'Payroll')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Payroll</h1>
            <p class="text-secondary small mb-0">Salaries are worked out from attendance. Generate, adjust, then mark paid.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="gf-monthnav">
                <button type="button" class="btn btn-light btn-sm" id="month-prev" aria-label="Previous month"><svg class="gf-ico gf-ico--sm"><use href="#i-chevron-left"/></svg></button>
                <span class="gf-monthlabel" id="month-label">…</span>
                <button type="button" class="btn btn-light btn-sm" id="month-next" aria-label="Next month"><svg class="gf-ico gf-ico--sm"><use href="#i-chevron-right"/></svg></button>
            </div>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="btn-generate">
                <svg class="gf-ico gf-ico--sm"><use href="#i-refresh"/></svg> Generate payroll
            </button>
        </div>
    </div>

    <div id="payroll-note"></div>
    <div id="payroll-kpis" class="row g-2 g-lg-3 mb-3"></div>

    <div class="card">
        <div class="card-body py-2 px-3"><div id="payroll-table"><div class="gf-empty">Loading…</div></div></div>
    </div>

    <div class="card mt-3">
        <div class="card-body small text-secondary">
            <div class="gf-eyebrow mb-2">How salary is worked out</div>
            <ul class="mb-0 ps-3">
                <li><b class="text-light">Monthly pay</b> = salary × payable days ÷ days in the month. Payable days = present + ½ per half day + paid leave + holidays + weekly offs. Absences and past working days nobody marked earn nothing; days before joining or after leaving are excluded.</li>
                <li><b class="text-light">Late deduction</b>: every {{ config('gym.late_marks_per_half_day') ?: '—' }} late arrivals in a month cost half a day's pay (clocking in more than {{ config('gym.late_grace_minutes') }} min after the shift start is late).</li>
                <li><b class="text-light">Hourly pay</b> = hours worked (from clock-in / clock-out) × hourly rate.</li>
                <li>Net pay = earned − late deduction + bonus − other deduction. A month can be generated once it has ended; a paid payslip is final.</li>
            </ul>
        </div>
    </div>

    {{-- Confirm: some staff have unmarked days --}}
    <div class="modal fade" id="unmarked-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6">Some days are not marked</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small">These staff have working days nobody marked. Unmarked days are <b>counted as absent</b> and are not paid:</p>
                    <ul class="small js-names"></ul>
                    <p class="small text-secondary mb-0">You can mark their attendance first, or generate now and treat the gaps as absences.</p>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-light" href="{{ route('staff.attendance') }}">Mark attendance</a>
                    <button type="button" class="btn btn-primary js-confirm">Generate — count as absent</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bonus / deduction --}}
    <div class="modal fade" id="adjust-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="adjust-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <div><h2 class="modal-title h6">Adjust salary</h2><div class="small text-secondary js-who"></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="gf-card-inset p-3 small mb-3">
                        <div class="d-flex justify-content-between"><span class="text-secondary">Earned</span><span class="tabular js-gross"></span></div>
                        <div class="d-flex justify-content-between"><span class="text-secondary">Late deduction</span><span class="tabular js-late"></span></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Bonus / incentive ({{ config('gym.currency') }})</label>
                            <input name="bonus" type="number" min="0" step="0.01" class="form-control" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Other deduction ({{ config('gym.currency') }})</label>
                            <input name="other_deduction" type="number" min="0" step="0.01" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Reason / note</label>
                            <input name="adjustment_note" class="form-control" maxlength="255" placeholder="e.g. Festival bonus, advance recovery">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between fw-bold mt-3"><span>Net pay</span><span class="tabular fs-5 js-net" style="color:var(--gf-gold)"></span></div>
                    <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Mark paid --}}
    <div class="modal fade" id="pay-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="pay-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <div><h2 class="modal-title h6">Mark salary paid</h2><div class="small text-secondary js-who"></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="gf-card-inset p-3 d-flex justify-content-between align-items-center mb-3">
                        <span class="gf-eyebrow">Net pay</span><span class="fs-4 fw-bold tabular js-net" style="color:var(--gf-gold)"></span>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Paid on</label>
                            <input name="paid_on" type="date" class="form-control" max="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="upi">UPI</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Reference</label>
                            <input name="payment_reference" class="form-control" maxlength="255" placeholder="UTR / cheque no. (optional)">
                        </div>
                    </div>
                    <p class="small text-secondary mt-3 mb-0">Once paid, the payslip is locked and the employee can see it.</p>
                    <p class="gf-error text-danger small mt-2 mb-0 d-none" role="alert"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mark paid</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/payroll.js') }}?v={{ filemtime(public_path('js/pages/payroll.js')) }}"></script>
@endpush
