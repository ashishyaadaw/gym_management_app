@extends('layouts.app')

@section('title', 'Classes')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="gf-page-title">Classes &amp; Schedule</h1>
        @if (auth()->user()->role === 'admin')
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#schedule-modal">+ Schedule Session</button>
        @endif
    </div>

    <div id="schedule-list" class="d-grid gap-3"></div>

    @if (auth()->user()->role === 'admin')
        <div class="modal fade" id="schedule-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form id="schedule-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6">Schedule a Class Session</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Class</label>
                            <select name="gym_class_id" class="form-select" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Trainer</label>
                            <select name="trainer_id" class="form-select" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Room</label>
                            <input name="room" class="form-control">
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-medium">Start</label>
                                <input name="start_time" type="datetime-local" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-medium">End</label>
                                <input name="end_time" type="datetime-local" class="form-control" required>
                            </div>
                        </div>
                        <p class="gf-error text-danger small mt-3 mb-0 d-none"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/classes.js') }}"></script>
@endpush
