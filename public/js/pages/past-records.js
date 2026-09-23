$(function () {
  'use strict';

  var esc = App.esc;
  var money = App.money;
  var BATCH = 100;   // rows per request (the server's limit)
  var METHOD_LABEL = { cash: 'Cash', upi: 'UPI', card: 'Card', bank_transfer: 'Bank transfer', other: 'Other' };

  var plans = [];
  var $tbody = $('#entry-table tbody');
  var nextKey = 1;

  // ---------- Tabs ----------
  $('[data-tab]').on('click', function () {
    var tab = $(this).data('tab');
    $('[data-tab]').removeClass('active');
    $(this).addClass('active');
    $('[data-pane]').addClass('d-none').filter('[data-pane="' + tab + '"]').removeClass('d-none');
    if (tab === 'list') { loadList(); }
  });
  function showTab(tab) { $('[data-tab="' + tab + '"]').trigger('click'); }

  // ---------- Dates ----------
  function ymd(d) {
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }

  var MONTHS = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, sept: 9, oct: 10, nov: 11, dec: 12 };

  /** Spreadsheet dates -> YYYY-MM-DD. Day-first (Indian) order: 25-03-2025, 25/3/25, 25.03.2025, 25-Mar-2025, or ISO 2025-03-25. */
  function parseDate(v) {
    v = $.trim(v || '');
    if (!v) { return ''; }
    var m, y, mo, d;
    if ((m = v.match(/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/))) { y = +m[1]; mo = +m[2]; d = +m[3]; }
    else if ((m = v.match(/^(\d{1,2})[-\/. ](\d{1,2})[-\/. ](\d{2,4})$/))) { d = +m[1]; mo = +m[2]; y = +m[3]; }
    else if ((m = v.match(/^(\d{1,2})[-\/. ]([a-z]{3,4})[a-z]*[-\/., ]+(\d{2,4})$/i)) && MONTHS[m[2].toLowerCase()]) {
      d = +m[1]; mo = MONTHS[m[2].toLowerCase()]; y = +m[3];
    } else { return null; }
    if (y < 100) { y += 2000; }
    var date = new Date(y, mo - 1, d);
    if (date.getFullYear() !== y || date.getMonth() !== mo - 1 || date.getDate() !== d) { return null; }
    return ymd(date);
  }

  // ---------- Grid ----------
  function planOptions(selected) {
    return '<option value="">— none —</option>' + $.map(plans, function (p) {
      return '<option value="' + esc(p.id) + '"' + (String(p.id) === String(selected) ? ' selected' : '') + '>' +
        esc(p.name) + ' · ' + esc(money(p.price)) + (p.is_active ? '' : ' (retired)') + '</option>';
    }).join('');
  }

  function methodOptions(selected) {
    return $.map(METHOD_LABEL, function (label, key) {
      return '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + label + '</option>';
    }).join('');
  }

  function addRow(r) {
    r = r || {};
    var key = nextKey++;
    var $tr = $('<tr data-key="' + key + '">' +
      '<td class="text-secondary tabular js-num"></td>' +
      '<td><input type="date" class="form-control form-control-sm" name="paid_on" max="' + App.today() + '" value="' + esc(r.paid_on || $('#default-date').val()) + '"></td>' +
      '<td><input class="form-control form-control-sm" name="name" maxlength="255" placeholder="Blank = day total" value="' + esc(r.name || '') + '"></td>' +
      '<td><input class="form-control form-control-sm" name="phone" maxlength="10" inputmode="numeric" value="' + esc(r.phone || '') + '"></td>' +
      '<td><select class="form-select form-select-sm" name="membership_plan_id">' + planOptions(r.membership_plan_id) + '</select></td>' +
      '<td><input type="date" class="form-control form-control-sm" name="start_date" value="' + esc(r.start_date || '') + '" title="Leave empty = same as payment date"></td>' +
      '<td><input type="number" class="form-control form-control-sm" name="amount" min="0" step="0.01" inputmode="decimal" value="' + esc(r.amount || '') + '"></td>' +
      '<td><select class="form-select form-select-sm" name="method">' + methodOptions(r.method || $('#default-method').val()) + '</select></td>' +
      '<td><input class="form-control form-control-sm" name="notes" maxlength="500" value="' + esc(r.notes || '') + '"></td>' +
      '<td class="text-end"><button type="button" class="btn btn-light btn-sm js-remove" title="Remove row"><svg class="gf-ico gf-ico--sm"><use href="#i-x"/></svg></button></td>' +
      '</tr>');
    $tbody.append($tr);
    if (r.error) { markError($tr, r.error); }
    placeholderFor($tr);
    return $tr;
  }

  /** Amount placeholder shows the plan price that will be used when the amount is left empty. */
  function placeholderFor($tr) {
    var id = $tr.find('[name="membership_plan_id"]').val();
    var plan = $.grep(plans, function (p) { return String(p.id) === String(id); })[0];
    $tr.find('[name="amount"]').attr('placeholder', plan ? String(Math.round(plan.price)) : '');
  }

  function markError($tr, message) {
    $tr.addClass('is-error');
    $tr.next('.js-error').remove();
    $('<tr class="js-error"><td></td><td colspan="9" class="gf-row-error pt-0">' + esc(message) + '</td></tr>').insertAfter($tr);
  }

  function clearError($tr) {
    $tr.removeClass('is-error').next('.js-error').remove();
  }

  function dataRows() { return $tbody.children('tr[data-key]'); }

  function readRow($tr) {
    function val(name) { return $.trim($tr.find('[name="' + name + '"]').val() || ''); }
    return {
      key: $tr.data('key'),
      paid_on: val('paid_on'),
      name: val('name') || null,
      phone: val('phone') || null,
      membership_plan_id: App.num(val('membership_plan_id')),
      start_date: val('start_date') || null,
      amount: App.num(val('amount')),
      method: val('method'),
      notes: val('notes') || null
    };
  }

  /** A row the user hasn't filled in at all (the date and mode defaults don't count). */
  function isBlank(r) { return !r.name && !r.phone && !r.membership_plan_id && r.amount === null && !r.notes; }

  function refreshSummary() {
    var n = 0, sum = 0;
    dataRows().each(function (i) {
      var $tr = $(this);
      $tr.find('.js-num').text(i + 1);
      var r = readRow($tr);
      if (isBlank(r)) { return; }
      n++;
      var plan = $.grep(plans, function (p) { return String(p.id) === String(r.membership_plan_id); })[0];
      sum += r.amount !== null ? r.amount : (plan ? Number(plan.price) : 0);
    });
    $('#entry-summary').text(n + ' filled row' + (n === 1 ? '' : 's') + ' · ' + money(sum));
  }

  $tbody.on('input change', 'input, select', function () {
    var $tr = $(this).closest('tr');
    if ($tr.hasClass('is-error')) { clearError($tr); }
    if (this.name === 'membership_plan_id') { placeholderFor($tr); }
    refreshSummary();
  });

  $tbody.on('click', '.js-remove', function () {
    var $tr = $(this).closest('tr');
    $tr.next('.js-error').remove();
    $tr.remove();
    if (!dataRows().length) { addRow(); }
    refreshSummary();
  });

  // Enter in the last row adds a fresh one (and jumps to it) — fast keyboard entry.
  $tbody.on('keydown', 'input', function (e) {
    if (e.key !== 'Enter') { return; }
    e.preventDefault();
    var $tr = $(this).closest('tr');
    if ($tr.is(dataRows().last())) {
      // Carry the date over: history is usually typed in day by day.
      var $new = addRow({ paid_on: $tr.find('[name="paid_on"]').val(), method: $tr.find('[name="method"]').val() });
      $new.find('[name="name"]').trigger('focus');
      refreshSummary();
    }
  });

  $('#btn-add-row').on('click', function () { addRow().find('[name="name"]').trigger('focus'); refreshSummary(); });
  $('#btn-add-10').on('click', function () { for (var i = 0; i < 10; i++) { addRow(); } refreshSummary(); });
  $('#btn-clear').on('click', function () {
    if (dataRows().length > 1 && !window.confirm('Remove all rows from the grid? Nothing saved is affected.')) { return; }
    $tbody.empty();
    addRow();
    refreshSummary();
  });

  // ---------- Save (in batches) ----------
  $('#btn-save').on('click', function () {
    var rows = [];
    dataRows().each(function () {
      var $tr = $(this);
      var r = readRow($tr);
      if (isBlank(r)) { return; }
      if (!r.paid_on) { markError($tr, 'Payment date is missing.'); return; }
      if (r.amount === null && !r.membership_plan_id) { markError($tr, 'Enter the amount (or choose a plan).'); return; }
      rows.push(r);
    });

    if (!rows.length) {
      App.flash($tbody.find('.is-error').length ? 'Fix the highlighted rows first.' : 'Nothing to save — fill in at least one row.', 'warning');
      return;
    }

    var $btn = $(this).prop('disabled', true);
    var totals = { saved: 0, failed: 0, members: 0, amount: 0 };
    var batches = [];
    for (var i = 0; i < rows.length; i += BATCH) { batches.push(rows.slice(i, i + BATCH)); }

    function next(n) {
      if (n >= batches.length) { return finish(); }
      $('#save-progress').text('Saving ' + Math.min((n + 1) * BATCH, rows.length) + ' / ' + rows.length + '…');
      App.post('/past-records', { rows: batches[n] })
        .done(function (res) {
          totals.saved += res.saved; totals.failed += res.failed;
          totals.members += res.members_created; totals.amount += res.amount;
          $.each(res.results, function (_, r) {
            var $tr = $tbody.children('tr[data-key="' + r.key + '"]');
            if (r.ok) { $tr.next('.js-error').remove(); $tr.remove(); } else { markError($tr, r.error); }
          });
          next(n + 1);
        })
        .fail(function (xhr) {
          App.flash(App.errorMessage(xhr, 'Saving stopped — the remaining rows are still in the grid.'), 'danger');
          finish();
        });
    }

    function finish() {
      $btn.prop('disabled', false);
      $('#save-progress').text('');
      if (!dataRows().length) { addRow(); }
      refreshSummary();
      if (totals.saved) {
        App.flash(totals.saved + ' record' + (totals.saved === 1 ? '' : 's') + ' saved (' + money(totals.amount) + ')' +
          (totals.members ? ', ' + totals.members + ' new member' + (totals.members === 1 ? '' : 's') + ' added' : '') +
          (totals.failed ? '. ' + totals.failed + ' need fixing — see the highlighted rows.' : '.'), totals.failed ? 'warning' : 'success');
        $('#list-count').text(Number($('#list-count').text()) + totals.saved);
      } else if (totals.failed) {
        App.flash('No rows saved — see the highlighted rows.', 'danger');
      }
    }

    next(0);
  });

  // ---------- CSV import ----------
  /** Minimal RFC-4180 parser; also accepts tab-separated text pasted straight from Excel. */
  function parseCsv(text) {
    var firstLine = text.split(/\r?\n/)[0] || '';
    var sep = firstLine.indexOf('\t') !== -1 ? '\t' : (firstLine.split(';').length > firstLine.split(',').length ? ';' : ',');
    var rows = [], row = [], cell = '', quoted = false;
    for (var i = 0; i < text.length; i++) {
      var ch = text[i];
      if (quoted) {
        if (ch === '"' && text[i + 1] === '"') { cell += '"'; i++; }
        else if (ch === '"') { quoted = false; }
        else { cell += ch; }
      } else if (ch === '"') { quoted = true; }
      else if (ch === sep) { row.push(cell); cell = ''; }
      else if (ch === '\n' || ch === '\r') {
        if (ch === '\r' && text[i + 1] === '\n') { i++; }
        row.push(cell); rows.push(row); row = []; cell = '';
      } else { cell += ch; }
    }
    if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
    return $.grep(rows, function (r) { return $.grep(r, function (c) { return $.trim(c) !== ''; }).length > 0; });
  }

  var HEADERS = {
    paid_on: ['date', 'paid on', 'paid_on', 'payment date', 'paid date', 'day'],
    name: ['name', 'member', 'member name', 'customer'],
    phone: ['phone', 'mobile', 'phone number', 'mobile number', 'contact'],
    plan: ['plan', 'membership', 'package', 'plan name'],
    start_date: ['start', 'start date', 'start_date', 'plan start', 'from'],
    amount: ['amount', 'paid', 'amount paid', 'fee', 'total'],
    method: ['mode', 'method', 'payment mode', 'payment method', 'payment'],
    notes: ['notes', 'note', 'remarks', 'remark', 'description']
  };

  function methodFrom(v) {
    v = $.trim(v || '').toLowerCase();
    if (!v || v === 'cash') { return 'cash'; }
    if (/upi|gpay|google pay|phonepe|paytm|bhim/.test(v)) { return 'upi'; }
    if (/card|credit|debit|swipe|pos/.test(v)) { return 'card'; }
    if (/bank|neft|imps|rtgs|transfer|cheque|check/.test(v)) { return 'bank_transfer'; }
    return 'other';
  }

  function planFrom(v) {
    v = $.trim(v || '').toLowerCase();
    if (!v) { return { id: null }; }
    var hit = $.grep(plans, function (p) { return p.name.toLowerCase() === v; })[0];
    return hit ? { id: hit.id } : { id: null, error: 'Unknown plan “' + v + '” — choose one from the list.' };
  }

  function loadCsv(text) {
    var $form = $('[data-pane="import"]');
    App.formError($form, '');
    var rows = parseCsv(text || '');
    if (rows.length < 2) { App.formError($form, 'Need a header row and at least one data row.'); return; }

    var header = $.map(rows[0], function (h) { return $.trim(h).toLowerCase().replace(/\s+/g, ' '); });
    var col = {};
    $.each(HEADERS, function (field, names) {
      for (var i = 0; i < header.length; i++) { if ($.inArray(header[i], names) !== -1) { col[field] = i; return; } }
    });
    if (col.paid_on === undefined) { App.formError($form, 'No “date” column found in the header row.'); return; }
    if (col.amount === undefined && col.plan === undefined) { App.formError($form, 'Need an “amount” or a “plan” column.'); return; }

    // Replace untouched blank rows, keep anything already typed in.
    dataRows().each(function () { if (isBlank(readRow($(this)))) { $(this).next('.js-error').remove(); $(this).remove(); } });

    var loaded = 0, flagged = 0;
    $.each(rows.slice(1), function (_, cells) {
      function cell(f) { return col[f] === undefined ? '' : $.trim(cells[col[f]] || ''); }
      var errors = [];
      var paidOn = parseDate(cell('paid_on'));
      if (paidOn === null) { errors.push('Can’t read the date “' + cell('paid_on') + '”.'); }
      var start = parseDate(cell('start_date'));
      if (start === null) { errors.push('Can’t read the start date “' + cell('start_date') + '”.'); }
      var plan = planFrom(cell('plan'));
      if (plan.error) { errors.push(plan.error); }
      var amount = cell('amount').replace(/[₹,\s]|rs\.?/gi, '');

      addRow({
        paid_on: paidOn || '',
        name: cell('name'),
        phone: cell('phone').replace(/\D/g, '').replace(/^91(?=\d{10}$)/, ''),
        membership_plan_id: plan.id,
        start_date: start || '',
        amount: amount,
        method: methodFrom(cell('method')),
        notes: cell('notes'),
        error: errors.join(' ') || null
      });
      loaded++;
      if (errors.length) { flagged++; }
    });

    refreshSummary();
    showTab('entry');
    App.flash(loaded + ' row' + (loaded === 1 ? '' : 's') + ' loaded — check them, then press Save all.' +
      (flagged ? ' ' + flagged + ' need attention.' : ''), flagged ? 'warning' : 'success');
    $('#csv-text').val('');
    $('#csv-file').val('');
  }

  $('#btn-load').on('click', function () {
    var file = $('#csv-file')[0].files[0];
    if (file) {
      var reader = new FileReader();
      reader.onload = function () { loadCsv(String(reader.result).replace(/^﻿/, '')); };
      reader.readAsText(file);
    } else {
      loadCsv($('#csv-text').val());
    }
  });

  $('#btn-template').on('click', function () {
    var sample = plans[0] ? plans[0].name : 'Monthly';
    var csv = 'date,name,phone,plan,start,amount,mode,notes\r\n' +
      '05-04-2025,Rahul Verma,9876543210,' + sample + ',,,' + 'upi,\r\n' +
      '06-04-2025,Priya Singh,9812345678,,,500,cash,Personal training\r\n' +
      '07-04-2025,,,,,12500,cash,Daybook total for the day\r\n';
    var url = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
    $('<a>').attr({ href: url, download: 'past-records-template.csv' })[0].click();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  });

  // ---------- Recorded list ----------
  var listed = {};
  var listSeq = 0;

  function loadList() {
    var mine = ++listSeq;
    return App.get('/past-records', { from: $('#list-from').val(), to: $('#list-to').val() })
      .done(function (res) {
        if (mine !== listSeq) { return; }
        $('#list-count').text(res.count);
        $('#list-total').text(money(res.total) + ' · ' + res.count + ' record' + (res.count === 1 ? '' : 's'));
        listed = {};
        $.each(res.data, function (_, r) { listed[r.id] = r; });
        if (!res.data.length) { $('#list-table').html('<div class="gf-empty">No past records in this period yet.</div>'); return; }

        $('#list-table').html('<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr>' +
          '<th>Paid on</th><th>Member</th><th class="d-none d-md-table-cell">Plan</th><th>Mode</th><th class="text-end">Amount</th>' +
          '<th class="d-none d-lg-table-cell">Entered</th><th></th></tr></thead><tbody>' +
          $.map(res.data, function (r) {
            return '<tr><td class="text-nowrap tabular">' + esc(App.day(r.paid_on)) + '</td>' +
              '<td>' + (r.member
                  ? '<div class="fw-medium">' + esc(r.member) + '</div><div class="text-secondary" style="font-size:.72rem">' + esc(r.phone || '') + '</div>'
                  : '<span class="gf-pill gf-pill--other">Day total</span>') +
                (r.notes ? '<div class="text-secondary gf-truncate" style="font-size:.72rem;max-width:14rem">' + esc(r.notes) + '</div>' : '') + '</td>' +
              '<td class="d-none d-md-table-cell">' + (r.plan
                  ? esc(r.plan) + '<div class="text-secondary" style="font-size:.72rem">' + esc(App.day(r.start_date)) + (r.end_date ? ' – ' + esc(App.day(r.end_date)) : '') + '</div>'
                  : '<span class="text-secondary">—</span>') + '</td>' +
              '<td>' + App.methodPill(r.method) + '</td>' +
              '<td class="text-end fw-bold tabular">' + esc(money(r.amount)) + '</td>' +
              '<td class="d-none d-lg-table-cell text-secondary" style="font-size:.72rem">' + esc(App.day(r.recorded_at)) + (r.recorded_by ? '<br>by ' + esc(r.recorded_by) : '') + '</td>' +
              '<td class="text-end"><button class="btn btn-light btn-sm js-undo" data-id="' + esc(r.id) + '" title="Remove this record">' +
                '<svg class="gf-ico gf-ico--sm"><use href="#i-x"/></svg></button></td></tr>';
          }).join('') + '</tbody></table></div>');
      })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load past records.'), 'danger'); });
  }

  $('#list-from, #list-to').on('change', loadList);

  var undoing = null;
  $('#list-table').on('click', '.js-undo', function () {
    undoing = listed[$(this).data('id')];
    $('#undo-modal .js-undo-what').text(money(undoing.amount) + ' on ' + App.day(undoing.paid_on) + (undoing.member ? ' from ' + undoing.member : ' (day total)'));
    App.modal('#undo-modal').show();
  });

  $('#undo-modal .js-confirm-undo').on('click', function () {
    var $btn = $(this).prop('disabled', true);
    App.api('DELETE', '/past-records/' + undoing.id)
      .done(function () { App.modal('#undo-modal').hide(); App.flash('Past record removed.'); loadList(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not remove it.'), 'danger'); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Start ----------
  var yesterday = new Date(); yesterday.setDate(yesterday.getDate() - 1);
  $('#default-date').val(ymd(yesterday));
  var yearAgo = new Date(); yearAgo.setFullYear(yearAgo.getFullYear() - 1); yearAgo.setDate(yearAgo.getDate() + 1);
  $('#list-from').val(ymd(yearAgo));
  $('#list-to').val(App.today());

  App.get('/membership-plans', { include_inactive: 1 })
    .done(function (res) { plans = res; })
    .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load plans.'), 'danger'); })
    .always(function () {
      for (var i = 0; i < 5; i++) { addRow(); }
      refreshSummary();
      loadList();
    });
});
