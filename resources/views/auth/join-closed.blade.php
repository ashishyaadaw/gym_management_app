@extends('layouts.guest')

@section('title', 'Link not available')

@section('content')
    <h1 class="h4 mb-2">This link can't be used</h1>
    <p class="text-secondary mb-0">
        @switch($status)
            @case('used') This registration link has already been used. @break
            @case('expired') This registration link has expired. @break
            @case('revoked') This registration link has been cancelled. @break
            @default This registration link is not valid.
        @endswitch
        Please ask the front desk at {{ config('app.name') }} for a new one.
    </p>
@endsection
