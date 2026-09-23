

<?php $__env->startSection('title', 'Past Records'); ?>

<?php $__env->startSection('content'); ?>
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="gf-page-title">Past Records</h1>
            <p class="text-secondary small mb-0">Enter the gym's payment history from before this app — each on its real date</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body small">
            <div class="row g-3">
                <div class="col-md-4 d-flex gap-2">
                    <span class="gf-hero-icon" style="width:2rem;height:2rem;border-radius:.6rem"><svg class="gf-ico gf-ico--sm"><use href="#i-users"/></svg></span>
                    <div><b>Member payments</b><div class="text-secondary">Name and/or phone. An existing member is matched by phone (or exact name); anyone else is added as a new member.</div></div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <span class="gf-hero-icon" style="width:2rem;height:2rem;border-radius:.6rem"><svg class="gf-ico gf-ico--sm"><use href="#i-tag"/></svg></span>
                    <div><b>With a plan</b><div class="text-secondary">Also records the membership period (start date = payment date unless you set one), so history and expiry are right. Amount defaults to the plan price.</div></div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <span class="gf-hero-icon" style="width:2rem;height:2rem;border-radius:.6rem"><svg class="gf-ico gf-ico--sm"><use href="#i-cash"/></svg></span>
                    <div><b>Day totals</b><div class="text-secondary">Only have a daybook total? Leave name and phone empty to record a lump sum for that date.</div></div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills gap-2 mb-3" role="tablist">
        <li class="nav-item"><button class="btn btn-light btn-sm gf-chip active" data-tab="entry" type="button">Enter records</button></li>
        <li class="nav-item"><button class="btn btn-light btn-sm gf-chip" data-tab="import" type="button">Import CSV / Excel</button></li>
        <li class="nav-item"><button class="btn btn-light btn-sm gf-chip" data-tab="list" type="button">Recorded <span class="badge text-bg-secondary ms-1" id="list-count">0</span></button></li>
    </ul>

    
    <div class="card" data-pane="entry">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                <div>
                    <label class="form-label gf-eyebrow mb-1" for="default-date">Date for new rows</label>
                    <input type="date" id="default-date" class="form-control form-control-sm" max="<?php echo e(today()->toDateString()); ?>">
                </div>
                <div>
                    <label class="form-label gf-eyebrow mb-1" for="default-method">Mode for new rows</label>
                    <select id="default-method" class="form-select form-select-sm">
                        <option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option>
                        <option value="bank_transfer">Bank transfer</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="d-flex gap-2 ms-md-auto">
                    <button type="button" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1" id="btn-add-row"><svg class="gf-ico gf-ico--sm"><use href="#i-plus"/></svg> Row</button>
                    <button type="button" class="btn btn-light btn-sm" id="btn-add-10">+10 rows</button>
                    <button type="button" class="btn btn-light btn-sm" id="btn-clear">Clear</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle small gf-entry" id="entry-table">
                    <thead><tr>
                        <th style="width:2rem">#</th>
                        <th style="min-width:9.5rem">Payment date *</th>
                        <th style="min-width:10rem">Member name</th>
                        <th style="min-width:8.5rem">Phone</th>
                        <th style="min-width:10rem">Plan</th>
                        <th style="min-width:9.5rem">Plan start</th>
                        <th style="min-width:7rem">Amount</th>
                        <th style="min-width:7rem">Mode</th>
                        <th style="min-width:9rem">Notes</th>
                        <th></th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-3 border-top" style="border-color:var(--gf-line-soft)!important">
                <div class="small text-secondary"><span id="entry-summary">0 rows</span> · <kbd>Enter</kbd> in the last row adds another</div>
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-secondary" id="save-progress"></span>
                    <button type="button" class="btn btn-primary" id="btn-save">Save all</button>
                </div>
            </div>
        </div>
    </div>

    
    <div class="card d-none" data-pane="import">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-5 small">
                    <h2 class="h6">How to import</h2>
                    <ol class="ps-3 text-secondary mb-3">
                        <li>In Excel / Google Sheets, lay out one payment per row with a header row. Only <b>date</b> and <b>amount</b> (or <b>plan</b>) are required.</li>
                        <li>Save / download as <b>CSV</b>, then choose the file here — or copy the cells and paste them in the box.</li>
                        <li>Press <b>Load into grid</b>, check the rows, then <b>Save all</b>. Nothing is saved before that.</li>
                    </ol>
                    <div class="gf-card-inset p-3 mb-3">
                        <div class="gf-eyebrow mb-2">Columns (any order)</div>
                        <div><code>date</code> — 25-03-2025, 25/03/2025 or 2025-03-25</div>
                        <div><code>name</code>, <code>phone</code> — leave both empty for a day total</div>
                        <div><code>plan</code> — plan name as in this app (optional)</div>
                        <div><code>start</code> — plan start date (optional)</div>
                        <div><code>amount</code> — optional when a plan is given</div>
                        <div><code>mode</code> — cash / upi / card / bank (default cash)</div>
                        <div><code>notes</code> (optional)</div>
                    </div>
                    <button type="button" class="btn btn-light btn-sm d-inline-flex align-items-center gap-1" id="btn-template">
                        <svg class="gf-ico gf-ico--sm"><use href="#i-download"/></svg> Download template
                    </button>
                </div>
                <div class="col-lg-7">
                    <label class="form-label small fw-medium" for="csv-file">CSV file</label>
                    <input type="file" id="csv-file" class="form-control mb-3" accept=".csv,text/csv,.txt">
                    <label class="form-label small fw-medium" for="csv-text">…or paste rows (with the header row)</label>
                    <textarea id="csv-text" class="form-control font-monospace" rows="9" placeholder="date,name,phone,plan,start,amount,mode,notes&#10;05-04-2025,Rahul Verma,9876543210,Monthly,,1600,upi,&#10;06-04-2025,,,,,12500,cash,Daybook total"></textarea>
                    <p class="gf-error text-danger small mt-2 mb-0 d-none" role="alert"></p>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="btn-load">
                            <svg class="gf-ico gf-ico--sm"><use href="#i-upload"/></svg> Load into grid
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="card d-none" data-pane="list">
        <div class="card-body">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-auto">
                    <label class="form-label gf-eyebrow mb-1" for="list-from">From</label>
                    <input type="date" id="list-from" class="form-control form-control-sm" max="<?php echo e(today()->toDateString()); ?>">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label gf-eyebrow mb-1" for="list-to">To</label>
                    <input type="date" id="list-to" class="form-control form-control-sm" max="<?php echo e(today()->toDateString()); ?>">
                </div>
                <div class="col-12 col-md text-md-end small">
                    <span class="text-secondary">Total</span> <b class="tabular" id="list-total">—</b>
                </div>
            </div>
            <div id="list-table"><div class="gf-empty">Loading…</div></div>
        </div>
    </div>

    <div class="modal fade" id="undo-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6">Remove past record?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body small"><span class="js-undo-what"></span><div class="text-secondary mt-2">The plan period it created is removed too. A member it added stays on the Members page.</div></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep</button>
                    <button type="button" class="btn btn-danger js-confirm-undo">Remove</button>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/pages/past-records.js')); ?>?v=<?php echo e(filemtime(public_path('js/pages/past-records.js'))); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\00_LARAVEL_PROJECT\gym-management-laravel\gym-management\resources\views/past-records.blade.php ENDPATH**/ ?>