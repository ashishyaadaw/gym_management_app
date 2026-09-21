@extends('layouts.app')

@section('title', 'POS Store')

@section('content')
    @php($isAdmin = auth()->user()->role === 'admin')

    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">POS Supplement Store</h1>
            <p class="text-secondary small mb-0">Tap a product to add it to the cart, then take payment</p>
        </div>
        @if ($isAdmin)
            <button class="btn btn-light d-inline-flex align-items-center justify-content-center gap-2" type="button" id="btn-add-product">
                <svg class="gf-ico"><use href="#i-plus"/></svg> Add product
            </button>
        @endif
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <div class="position-relative">
                                <input id="product-search" type="search" class="form-control ps-5" placeholder="Search products…" autocomplete="off">
                                <svg class="gf-ico position-absolute text-secondary" style="left:1rem;top:50%;transform:translateY(-50%)"><use href="#i-search"/></svg>
                            </div>
                        </div>
                        <div class="col-md-7"><div id="product-cats" class="d-flex flex-wrap gap-2"></div></div>
                    </div>
                    <div id="product-grid" class="row g-2"><div class="col-12 gf-empty">Loading products…</div></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card position-sticky" style="top:1rem">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="h6 mb-0 d-flex align-items-center gap-2">
                            <svg class="gf-ico" style="color:var(--gf-gold)"><use href="#i-bag"/></svg> Cart
                        </h2>
                        <button type="button" class="btn btn-light btn-sm" id="cart-clear">Clear</button>
                    </div>

                    <div id="cart-lines" class="gf-scroll mb-3" style="max-height:16rem"></div>

                    <div class="d-flex justify-content-between align-items-baseline mb-3">
                        <span class="gf-eyebrow">Subtotal</span>
                        <span class="fs-3 fw-bold tabular" id="cart-total">{{ config('gym.currency') }}0</span>
                    </div>

                    <div class="gf-eyebrow mb-2">Payment mode</div>
                    <div class="d-flex gap-2 mb-3" id="pay-methods">
                        <button type="button" class="btn btn-light gf-method-btn active" data-method="cash">Cash</button>
                        <button type="button" class="btn btn-light gf-method-btn" data-method="upi">UPI</button>
                        <button type="button" class="btn btn-light gf-method-btn" data-method="card">Card</button>
                    </div>

                    <div class="gf-eyebrow mb-2">Member (optional)</div>
                    <div class="gf-picker mb-3" data-picker id="sale-member">
                        <input type="text" class="form-control gf-picker-input" placeholder="Walk-in — or search a member…" autocomplete="off">
                        <input type="hidden" name="member_id">
                    </div>

                    <p class="gf-error text-danger small d-none" id="sale-error" role="alert"></p>
                    <button type="button" class="btn btn-primary w-100 btn-lg" id="btn-sell" disabled>Complete sale</button>

                    <div id="last-sale" class="gf-card-inset p-3 mt-3 d-none">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div class="small"><span class="text-secondary">Last sale</span>
                                <div class="fw-bold tabular js-last-total"></div></div>
                            <a class="btn btn-light btn-sm d-inline-flex align-items-center gap-1 js-last-receipt" target="_blank" rel="noopener" href="#">
                                <svg class="gf-ico gf-ico--sm"><use href="#i-print"/></svg> Print receipt
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0">Today’s store sales</h2>
                <span class="gf-eyebrow"><span id="sales-summary"></span> &middot; <a href="{{ route('sales') }}" class="text-decoration-none">All sales &amp; reports</a></span>
            </div>
            <div id="sales-list"><div class="gf-empty">Loading…</div></div>
        </div>
    </div>

    @if ($isAdmin)
        <div class="modal fade" id="stock-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form id="stock-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6">Stock &middot; <span class="js-stock-name"></span></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="gf-card-inset p-3 d-flex justify-content-between align-items-center mb-3">
                            <span class="gf-eyebrow">In stock now</span>
                            <span class="fs-3 fw-bold tabular js-stock-now"></span>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-5">
                                <label class="form-label small fw-medium">Change (+ add / &minus; remove)</label>
                                <input name="change" type="number" step="1" class="form-control" placeholder="e.g. 24" required>
                            </div>
                            <div class="col-sm-7">
                                <label class="form-label small fw-medium">Reason</label>
                                <select name="reason" class="form-select">
                                    <option value="restock">Restock (new delivery)</option>
                                    <option value="adjustment">Correction (miscount)</option>
                                    <option value="damaged">Damaged / expired</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Note (optional)</label>
                                <input name="note" class="form-control" maxlength="255" placeholder="Supplier, invoice no., ...">
                            </div>
                        </div>
                        <p class="gf-error text-danger small mt-3 mb-0 d-none" role="alert"></p>
                        <div class="gf-eyebrow mt-4 mb-1">Recent movements</div>
                        <div id="stock-history" class="small"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update stock</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="product-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form id="product-form" class="modal-content" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="product-modal-title">Add product</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-medium">Name *</label>
                                <input name="name" class="form-control" placeholder="Whey Protein 1kg" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Category *</label>
                                <input name="category" class="form-control" list="product-categories" value="Supplement" required>
                                <datalist id="product-categories">
                                    <option value="Supplement"><option value="Accessory"><option value="Apparel">
                                </datalist>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Stock *</label>
                                <input name="stock" type="number" min="0" step="1" class="form-control" required>
                                <div class="form-text js-stock-hint d-none">To change quantities use the Stock button on the product.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Cost price ({{ config('gym.currency') }})</label>
                                <input name="cost_price" type="number" min="0" step="0.01" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium">Selling price ({{ config('gym.currency') }}) *</label>
                                <input name="selling_price" type="number" min="0" step="0.01" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="product-active" checked>
                                    <label class="form-check-label small" for="product-active">Available for sale</label>
                                </div>
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
    @endif
@endsection

@push('scripts')
    <script>window.StorePage = { isAdmin: @json($isAdmin) };</script>
    <script src="{{ asset('js/pages/store.js') }}?v={{ filemtime(public_path('js/pages/store.js')) }}"></script>
@endpush
