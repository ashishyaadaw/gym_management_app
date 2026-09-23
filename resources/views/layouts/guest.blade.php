@extends('layouts.base')

@section('body')
    <button type="button" class="btn btn-light btn-sm gf-theme-corner js-theme-toggle" aria-label="Switch light / dark mode" title="Switch light / dark mode">
        <svg class="gf-ico gf-when-dark"><use href="#i-sun"/></svg>
        <svg class="gf-ico gf-when-light"><use href="#i-moon"/></svg>
    </button>
    <div class="gf-auth">
        <div class="card w-100" style="max-width: @yield('card-width', '26rem')">
            <div class="card-body p-4 p-sm-5">
                <div class="gf-brand-logo gf-brand-logo--lg mb-4" role="img" aria-label="{{ config('app.name') }}"></div>
                @yield('content')
            </div>
        </div>
    </div>
    <div id="gf-flash" class="gf-flash" role="status" aria-live="polite"></div>
@endsection
