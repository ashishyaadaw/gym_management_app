@extends('layouts.app')

@section('title', 'Membership Plans')

@section('content')
    @php($role = auth()->user()->role)
    @php($isAdmin = $role === 'admin')

    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Membership Plans</h1>
            <p class="text-secondary small mb-0">
                {{ $isAdmin ? 'Create and edit plans, set how many times each can be used, and attach coupons' : 'Choose the plan that fits you' }}
            </p>
        </div>
        @if ($isAdmin)
            <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="button" id="btn-new-plan">
                <svg class="gf-ico"><use href="#i-plus"/></svg> New Plan
            </button>
        @endif
    </div>

    <div id="plan-list" class="row g-3"><div class="col-12 gf-empty">Loading plans…</div></div>

    @if ($isAdmin)
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mt-5 mb-3">
            <div>
                <h2 class="h4 mb-0" style="font-weight:900">Coupons</h2>
                <p class="text-secondary small mb-0">Discount codes for a single plan or for every plan. Staff and members enter them at sign-up.</p>
            </div>
            <button class="btn btn-light d-inline-flex align-items-center justify-content-center gap-2" type="button" id="btn-new-coupon">
                <svg class="gf-ico"><use href="#i-plus"/></svg> New Coupon
            </button>
        </div>
        <div class="card">
            <div class="card-body py-2 px-3"><div id="coupon-list"><div class="gf-empty">Loading coupons…</div></div></div>
        </div>

        {{-- Create / edit plan --}}
        <div class="modal fade" id="plan-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form id="plan-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="plan-modal-title">New Membership Plan</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-medium">Name *</label>
                                <input name="name" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Description</label>
                                <input name="description" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Billing cycle *</label>
                                <select name="billing_cycle" class="form-select" required>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="pay_per_class">Pay per class</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Price ({{ config('gym.currency') }}) *</label>
                                <input name="price" type="number" step="0.01" min="0" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Duration (days)</label>
                                <input name="duration_days" type="number" min="1" class="form-control" placeholder="e.g. 30">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Class credits</label>
                                <input name="class_credits" type="number" min="0" class="form-control" placeholder="Blank = unlimited">
                            </div>

                            <div class="col-12"><div class="gf-eyebrow mt-2">Limit plan use</div></div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Total sign-ups allowed</label>
                                <input name="usage_limit" type="number" min="1" class="form-control" placeholder="Blank = unlimited">
                                <div class="form-text js-used-hint d-none"></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Times per member</label>
                                <input name="per_member_limit" type="number" min="1" class="form-control" placeholder="Blank = unlimited">
                                <div class="form-text">Set 1 for a one-time trial or offer.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">On sale from</label>
                                <input name="available_from" type="date" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">On sale until</label>
                                <input name="available_until" type="date" class="form-control">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="plan-active" checked>
                                    <label class="form-check-label small" for="plan-active">Available for sign-up</label>
                                </div>
                            </div>
                        </div>
                        <p class="text-secondary small mt-3 mb-0 d-none js-edit-note">Changes apply to future sign-ups. Members already on this plan keep what they bought.</p>
                        <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save plan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Create / edit coupon --}}
        <div class="modal fade" id="coupon-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form id="coupon-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="coupon-modal-title">New Coupon</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Code *</label>
                                <input name="code" class="form-control text-uppercase" maxlength="40" placeholder="WELCOME10" autocomplete="off" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Applies to</label>
                                <select name="membership_plan_id" class="form-select"></select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Description</label>
                                <input name="description" class="form-control" maxlength="255" placeholder="Shown to staff, e.g. New-year offer">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Discount type *</label>
                                <select name="discount_type" class="form-select">
                                    <option value="percent">Percent (%)</option>
                                    <option value="fixed">Fixed amount ({{ config('gym.currency') }})</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Discount value *</label>
                                <input name="discount_value" type="number" step="0.01" min="0.01" class="form-control" required>
                            </div>

                            <div class="col-12"><div class="gf-eyebrow mt-2">Limit coupon use</div></div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Total redemptions</label>
                                <input name="max_redemptions" type="number" min="1" class="form-control" placeholder="Blank = unlimited">
                            </div>
                            <div class="col-sm-6 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="once_per_member" id="coupon-once" checked>
                                    <label class="form-check-label small" for="coupon-once">One use per member</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Valid from</label>
                                <input name="valid_from" type="date" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Valid until</label>
                                <input name="valid_until" type="date" class="form-control">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="coupon-active" checked>
                                    <label class="form-check-label small" for="coupon-active">Active</label>
                                </div>
                            </div>
                        </div>
                        <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save coupon</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($role === 'member')
        {{-- Subscribe, with an optional coupon --}}
        <div class="modal fade" id="subscribe-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form id="subscribe-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6">Subscribe</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="fs-5 fw-bold js-sub-name" style="color:var(--gf-gold)"></div>
                        <div class="text-secondary small js-sub-desc mb-3"></div>

                        <label class="form-label small fw-medium" for="sub-coupon">Have a coupon?</label>
                        <div class="input-group">
                            <input id="sub-coupon" name="coupon_code" class="form-control text-uppercase" maxlength="40" placeholder="Enter code" autocomplete="off">
                            <button type="button" class="btn btn-light js-apply">Apply</button>
                        </div>
                        <div class="small mt-2 js-coupon-msg"></div>

                        <div class="gf-card-inset p-3 mt-3">
                            <div class="d-flex justify-content-between small"><span class="text-secondary">Plan price</span><span class="js-sub-price tabular"></span></div>
                            <div class="d-flex justify-content-between small d-none js-sub-discount-row"><span class="text-secondary">Coupon discount</span><span class="js-sub-discount tabular" style="color:var(--gf-cash)"></span></div>
                            <div class="d-flex justify-content-between fw-bold mt-1"><span>You pay</span><span class="js-sub-total tabular fs-5"></span></div>
                        </div>
                        <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>window.PlansPage = { role: @json($role) };</script>
    <script src="{{ asset('js/pages/plans.js') }}?v={{ filemtime(public_path('js/pages/plans.js')) }}"></script>
@endpush
