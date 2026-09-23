@extends('layouts.app')

@section('title', 'Members')

@section('content')
    @php($canWrite = in_array(auth()->user()->role, ['admin', 'receptionist'], true))

    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Members</h1>
            <p class="text-secondary small mb-0">Membership status, renewals and WhatsApp reminders</p>
        </div>
        @if ($canWrite)
            <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="button" id="btn-add-member">
                <svg class="gf-ico"><use href="#i-plus"/></svg> Add Member
            </button>
        @endif
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12 col-lg-5">
            <div class="position-relative">
                <input id="member-search" type="search" class="form-control ps-5" placeholder="Search by name or phone…" autocomplete="off">
                <svg class="gf-ico position-absolute text-secondary" style="left:1rem;top:50%;transform:translateY(-50%)"><use href="#i-search"/></svg>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div id="member-filters" class="d-flex flex-wrap gap-2">
                @foreach (['all' => 'All', 'active' => 'Active', 'expiring' => 'Expiring', 'expired' => 'Expired', 'deactivated' => 'Deactivated'] as $key => $label)
                    <button type="button" class="btn btn-light btn-sm gf-chip {{ $key === 'all' ? 'active' : '' }}" data-status="{{ $key }}">
                        {{ $label }} <span class="badge text-bg-secondary ms-1" data-count="{{ $key }}">0</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body py-2 px-3">
            <div id="member-list"><div class="gf-empty">Loading members…</div></div>
        </div>
    </div>

    {{-- Member details (read-only; filled in by members.js). Trainers can open it too. --}}
    <div class="modal fade" id="view-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6">Member details</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="view-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    @if ($canWrite)
                        <button type="button" class="btn btn-primary js-edit-from-view" disabled>Edit details</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($canWrite)
        {{-- Add member --}}
        <div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form id="add-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6">Add Member</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-medium">Full name *</label>
                                <input name="name" class="form-control" placeholder="Aarav Sharma" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Phone * (10 digits)</label>
                                <input name="phone" class="form-control" inputmode="numeric" maxlength="10" placeholder="9876543210" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Joining / plan start date</label>
                                <input name="joining_date" type="date" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Payment date</label>
                                <input name="paid_on" type="date" class="form-control" max="{{ today()->toDateString() }}">
                                <div class="form-text">For an old purchase entered late, the day the money was received.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Plan *</label>
                                <select name="membership_plan_id" class="form-select js-plan-select" required></select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Payment mode</label>
                                <select name="method" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank transfer</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Coupon (optional)</label>
                                <div class="input-group">
                                    <input name="coupon_code" class="form-control text-uppercase" maxlength="40" placeholder="Enter code" autocomplete="off">
                                    <button type="button" class="btn btn-light js-apply">Apply</button>
                                </div>
                                <div class="small mt-1 js-coupon-msg"></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Paid now ({{ config('gym.currency') }})</label>
                                <input name="paid" type="number" min="0" step="1" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Balance due</label>
                                <input class="form-control js-due" readonly value="—">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Email (optional)</label>
                                <input name="email" type="email" class="form-control" placeholder="Only if they want an online login">
                            </div>
                            <div class="col-12">
                                <div class="gf-card-inset p-3 small d-flex justify-content-between">
                                    <span class="text-secondary">Auto-calculated expiry</span>
                                    <span class="fw-bold js-expiry" style="color:var(--gf-gold)">—</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#add-more" aria-expanded="false" aria-controls="add-more">
                                    Member details — address, emergency contact, fitness &amp; health (optional)
                                </button>
                                <div class="collapse mt-3" id="add-more">
                                    <div class="row g-3">@include('partials.member-profile-fields')</div>
                                </div>
                            </div>
                        </div>
                        <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add member</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit member details --}}
        <div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form id="edit-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6">Edit member</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Full name *</label>
                                <input name="name" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Phone * (10 digits)</label>
                                <input name="phone" class="form-control" inputmode="numeric" maxlength="10" required>
                            </div>
                            @if (auth()->user()->isAdmin())
                                <div class="col-sm-6">
                                    <label class="form-label small fw-medium">Member since (join date)</label>
                                    <input name="joined_on" type="date" class="form-control" max="{{ today()->toDateString() }}" required>
                                    <div class="form-text">Only changes the date shown as “Member since”. Plans and payments stay as they are.</div>
                                </div>
                            @endif
                            @include('partials.member-profile-fields')
                        </div>
                        <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Renew membership --}}
        <div class="modal fade" id="renew-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form id="renew-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6">Renew / add plan</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="gf-avatar js-renew-avatar"></span>
                            <div class="gf-truncate">
                                <div class="fw-semibold js-renew-name"></div>
                                <div class="small text-secondary js-renew-current"></div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Plan *</label>
                                <select name="membership_plan_id" class="form-select js-plan-select" required></select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Payment mode</label>
                                <select name="method" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank transfer</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Coupon (optional)</label>
                                <div class="input-group">
                                    <input name="coupon_code" class="form-control text-uppercase" maxlength="40" placeholder="Enter code" autocomplete="off">
                                    <button type="button" class="btn btn-light js-apply">Apply</button>
                                </div>
                                <div class="small mt-1 js-coupon-msg"></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Paid now ({{ config('gym.currency') }})</label>
                                <input name="paid" type="number" min="0" step="1" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Balance due</label>
                                <input class="form-control js-due" readonly value="—">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Plan start date</label>
                                <input name="start_date" type="date" class="form-control">
                                <div class="form-text">Leave empty to start when the current plan ends.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Payment date</label>
                                <input name="paid_on" type="date" class="form-control" max="{{ today()->toDateString() }}">
                                <div class="form-text">Change only for a past purchase entered late.</div>
                            </div>
                            <div class="col-12">
                                <div class="gf-card-inset p-3 small d-flex justify-content-between">
                                    <span class="text-secondary">New expiry</span>
                                    <span class="fw-bold js-expiry" style="color:var(--gf-gold)">—</span>
                                </div>
                            </div>
                        </div>
                        <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Renew</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        window.MembersPage = {
            canWrite: @json($canWrite),
            canDeactivate: @json(auth()->user()->isAdmin()),
            canEditJoinDate: @json(auth()->user()->isAdmin()),
            labels: {
                goal: @json(\App\Models\MemberProfile::GOALS),
                level: @json(\App\Models\MemberProfile::LEVELS),
                timing: @json(\App\Models\MemberProfile::TIMINGS),
                source: @json(\App\Models\MemberProfile::SOURCES)
            }
        };
    </script>
    <script src="{{ asset('js/pages/members.js') }}?v={{ filemtime(public_path('js/pages/members.js')) }}"></script>
@endpush
