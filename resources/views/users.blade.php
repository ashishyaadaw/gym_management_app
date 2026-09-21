@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="gf-page-title">Users</h1>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#user-modal">+ Add User</button>
    </div>

    <div id="user-filters" class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['all', 'admin', 'trainer', 'receptionist', 'member'] as $role)
            <button type="button" class="btn btn-sm text-capitalize {{ $role === 'all' ? 'btn-primary' : 'btn-light' }}"
                    data-role="{{ $role }}">{{ $role }}</button>
        @endforeach
    </div>

    <div id="user-list" class="d-grid gap-3"></div>

    <div class="modal fade" id="user-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="user-form" class="modal-content" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6">Add User</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Name</label>
                        <input name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Email</label>
                        <input name="email" type="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Password</label>
                        <input name="password" type="password" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label small fw-medium">Role</label>
                        <select name="role" class="form-select">
                            <option value="member">Member</option>
                            <option value="trainer">Trainer</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <p class="gf-error text-danger small mt-3 mb-0 d-none"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/users.js') }}"></script>
@endpush
