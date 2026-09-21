@extends('layouts.app')

@section('title', 'Equipment')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="gf-page-title">Equipment Inventory</h1>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#equipment-modal">+ Add Equipment</button>
    </div>

    <div id="equipment-list" class="row g-3"></div>

    <div class="modal fade" id="equipment-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="equipment-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6">Add Equipment</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Name</label>
                        <input name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Category</label>
                        <input name="category" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Location</label>
                        <input name="location" class="form-control">
                    </div>
                    <div>
                        <label class="form-label small fw-medium">Quantity</label>
                        <input name="quantity" type="number" min="1" value="1" class="form-control" required>
                    </div>
                    <p class="gf-error text-danger small mt-3 mb-0 d-none"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/equipment.js') }}"></script>
@endpush
