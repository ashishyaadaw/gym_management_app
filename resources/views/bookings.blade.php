@extends('layouts.app')

@section('title', 'Bookings')

@section('content')
    <h1 class="gf-page-title mb-4">Bookings</h1>

    <div id="booking-list" class="d-grid gap-3"></div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/bookings.js') }}"></script>
@endpush
