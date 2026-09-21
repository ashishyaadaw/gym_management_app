@extends('layouts.app')

@section('title', 'Staff')

@section('content')
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Staff</h1>
            <p class="text-secondary small mb-0">Accounts, pay, shifts and weekly offs for everyone who works at the gym</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-light d-inline-flex align-items-center gap-2" href="{{ route('staff.attendance') }}">
                <svg class="gf-ico gf-ico--sm"><use href="#i-clock"/></svg> Attendance
            </a>
            <a class="btn btn-light d-inline-flex align-items-center gap-2" href="{{ route('payroll') }}">
                <svg class="gf-ico gf-ico--sm"><use href="#i-cash"/></svg> Payroll
            </a>
            <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="button" id="btn-add-staff">
                <svg class="gf-ico"><use href="#i-plus"/></svg> Add staff
            </button>
        </div>
    </div>

    <div id="staff-kpis" class="row g-2 g-lg-3 mb-3"></div>

    <div class="row g-2 mb-3">
        <div class="col-12 col-lg-5">
            <div class="position-relative">
                <input id="staff-search" type="search" class="form-control ps-5" placeholder="Search name, code or role…" autocomplete="off">
                <svg class="gf-ico position-absolute text-secondary" style="left:1rem;top:50%;transform:translateY(-50%)"><use href="#i-search"/></svg>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div id="staff-filters" class="d-flex flex-wrap gap-2">
                @foreach (['all' => 'All', 'active' => 'Working', 'inactive' => 'Left / inactive', 'noprofile' => 'No profile'] as $key => $label)
                    <button type="button" class="btn btn-light btn-sm gf-chip {{ $key === 'all' ? 'active' : '' }}" data-filter="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body py-2 px-3"><div id="staff-list"><div class="gf-empty">Loading staff…</div></div></div>
    </div>

    <div class="modal fade" id="staff-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <form id="staff-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6" id="staff-modal-title">Add staff</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="gf-eyebrow mb-2">Account</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Full name *</label>
                            <input name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Role *</label>
                            <select name="role" class="form-select">
                                <option value="trainer">Trainer</option>
                                <option value="receptionist">Receptionist</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Email (login) *</label>
                            <input name="email" type="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Phone</label>
                            <input name="phone" class="form-control" inputmode="tel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium js-pw-label">Password *</label>
                            <input name="password" type="password" class="form-control" autocomplete="new-password" placeholder="At least 8 characters">
                            <div class="form-text js-pw-hint d-none">Leave blank to keep the current password.</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end js-active-wrap d-none">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="staff-active" checked>
                                <label class="form-check-label small" for="staff-active">Currently working here (can log in)</label>
                            </div>
                        </div>
                    </div>

                    <div class="gf-eyebrow mb-2">Employment</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Employee code</label>
                            <input name="employee_code" class="form-control text-uppercase" maxlength="20" placeholder="Auto (EMP-005)">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-medium">Designation</label>
                            <input name="designation" class="form-control" maxlength="100" placeholder="e.g. Head trainer">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Joining date *</label>
                            <input name="joining_date" type="date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Leaving date</label>
                            <input name="leaving_date" type="date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Weekly off *</label>
                            <select name="weekly_off" class="form-select">
                                @foreach (\App\Models\StaffProfile::DAYS as $i => $day)
                                    <option value="{{ $i }}">{{ $day }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Paid *</label>
                            <select name="pay_type" class="form-select">
                                <option value="monthly">Monthly salary</option>
                                <option value="hourly">By the hour</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium js-salary-label">Monthly salary ({{ config('gym.currency') }}) *</label>
                            <input name="base_salary" type="number" min="0" step="0.01" class="form-control" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end"><div class="form-text mb-2">Enter 0 to keep someone off payroll (e.g. the owner). Their attendance is still tracked.</div></div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Shift starts</label>
                            <input name="shift_start" type="time" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Shift ends</label>
                            <input name="shift_end" type="time" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex align-items-end"><div class="form-text mb-2">Clocking in more than {{ config('gym.late_grace_minutes') }} min after the start is marked late.</div></div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Notes</label>
                            <input name="notes" class="form-control" maxlength="255">
                        </div>
                    </div>
                    <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/staff.js') }}?v={{ filemtime(public_path('js/pages/staff.js')) }}"></script>
@endpush
