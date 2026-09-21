@extends('layouts.app')

@section('title', 'Staff Attendance')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Staff Attendance</h1>
            <p class="text-secondary small mb-0">Click any day to mark or correct it. Staff clock themselves in from My Attendance.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="gf-monthnav">
                <button type="button" class="btn btn-light btn-sm" id="month-prev" aria-label="Previous month"><svg class="gf-ico gf-ico--sm"><use href="#i-chevron-left"/></svg></button>
                <span class="gf-monthlabel" id="month-label">…</span>
                <button type="button" class="btn btn-light btn-sm" id="month-next" aria-label="Next month"><svg class="gf-ico gf-ico--sm"><use href="#i-chevron-right"/></svg></button>
            </div>
            <button type="button" class="btn btn-light btn-sm" id="month-today">This month</button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-bulk">Mark a day for everyone</button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="gf-legend">
                <span><span class="gf-cell gf-cell--present">P</span>Present</span>
                <span><span class="gf-cell gf-cell--half_day">½</span>Half day</span>
                <span><span class="gf-cell gf-cell--absent">A</span>Absent</span>
                <span><span class="gf-cell gf-cell--leave">L</span>Paid leave</span>
                <span><span class="gf-cell gf-cell--holiday">H</span>Holiday</span>
                <span><span class="gf-cell gf-cell--weekly_off">·</span>Weekly off / no shift</span>
                <span><span class="gf-cell gf-cell--unmarked">?</span>Not marked</span>
                <span><span class="gf-cell gf-cell--present gf-late">P</span>Late arrival</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="gf-grid-wrap" id="grid"><div class="gf-empty">Loading…</div></div>
        </div>
    </div>

    {{-- Mark / correct one day --}}
    <div class="modal fade" id="day-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="day-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h6 js-who"></h2>
                        <div class="small text-secondary js-when"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-medium">Status</label>
                            <select name="status" class="form-select">
                                <option value="present">Present</option>
                                <option value="half_day">Half day</option>
                                <option value="absent">Absent</option>
                                <option value="leave">Paid leave</option>
                                <option value="holiday">Holiday</option>
                                <option value="clear">Not marked (clear)</option>
                            </select>
                        </div>
                        <div class="col-6 js-times">
                            <label class="form-label small fw-medium">Clock in</label>
                            <input name="check_in" type="time" class="form-control">
                        </div>
                        <div class="col-6 js-times">
                            <label class="form-label small fw-medium">Clock out</label>
                            <input name="check_out" type="time" class="form-control">
                        </div>
                        <div class="col-12 js-times"><div class="form-text mt-0 js-time-hint">Late arrivals are flagged automatically from the shift start.</div></div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Note</label>
                            <input name="note" class="form-control" maxlength="255" placeholder="Optional">
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

    {{-- Mark everyone unmarked on a day --}}
    <div class="modal fade" id="bulk-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="bulk-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6">Mark a day for everyone</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary">Fills in staff who have nothing recorded that day. Weekly offs and anything already marked are left alone.</p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Date</label>
                            <input name="date" type="date" class="form-control" max="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Mark as</label>
                            <select name="status" class="form-select">
                                <option value="present">Present</option>
                                <option value="holiday">Gym holiday (paid)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text mt-2">Hourly staff are skipped for "Present" because their pay needs real clock times.</div>
                    <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mark</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/staff-attendance.js') }}?v={{ filemtime(public_path('js/pages/staff-attendance.js')) }}"></script>
@endpush
