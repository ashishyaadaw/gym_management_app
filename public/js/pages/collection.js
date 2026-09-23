$(function () {
  'use strict';

  var esc = App.esc;
  var money = App.money;

  var $from = $('#range-from');
  var $to = $('#range-to');
  var report = null;
  var seq = 0;   // bumped per load, so a slow reply for an old range is ignored

  // ---------- Date range ----------
  function ymd(d) {
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }

  /** Presets. "Last 3/6 months" are rolling: the same date 3/6 months ago (+1 day) up to today. */
  function preset(key) {
    var now = new Date();
    var y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
    var start = new Date(now), end = new Date(now);
    switch (String(key)) {
      case 'yesterday': start = new Date(y, m, d - 1); end = new Date(y, m, d - 1); break;
      case 'month': start = new Date(y, m, 1); break;
      case 'last-month': start = new Date(y, m - 1, 1); end = new Date(y, m, 0); break;
      case '3m': start = new Date(y, m - 3, d + 1); break;
      case '6m': start = new Date(y, m - 6, d + 1); break;
    }
    $from.val(ymd(start));
    $to.val(ymd(end));
  }

  function query() { return { from: $from.val(), to: $to.val() }; }

  $('#range-presets').on('click', 'button', function () {
    $('#range-presets button').removeClass('active');
    $(this).addClass('active');
    preset($(this).data('preset'));
    load();
  });

  $('#range-from, #range-to').on('change', function () {
    $('#range-presets button').removeClass('active');
    if ($from.val() && $to.val() && $to.val() < $from.val()) { $to.val($from.val()); }
    load();
  });

  $('.js-export').on('click', function () {
    this.href = App.url('/ajax/collection/export?' + $.param($.extend(query(), { type: $(this).data('type') })));
  });

  $('#hide-empty').on('change', function () { if (report) { renderDays(report.by_day); } });

  // ---------- Rendering ----------
  function kpi(cls, label, value, sub) {
    return '<div class="col-6 col-md-4 col-xl-2"><div class="gf-kpi gf-kpi--' + cls + '"><div class="gf-kpi-label"><span>' + esc(label) + '</span></div>' +
      '<div class="gf-kpi-value" style="font-size:1.35rem">' + esc(value) + '</div><div class="gf-kpi-sub">' + esc(sub || '') + '</div></div></div>';
  }

  function pct(v, total) { return total > 0 ? Math.round(v / total * 100) : 0; }

  function render(r) {
    var t = r.totals;
    $('#range-label').text(App.day(r.from) + (r.from === r.to ? '' : ' – ' + App.day(r.to)) + ' · ' + t.days + ' day' + (t.days === 1 ? '' : 's'));

    $('#col-kpis').html(
      kpi('total', 'Collection', money(t.total), t.count + ' transaction' + (t.count === 1 ? '' : 's')) +
      kpi('cash', 'Cash', money(t.cash), pct(t.cash, t.total) + '%') +
      kpi('upi', 'UPI', money(t.upi), pct(t.upi, t.total) + '%') +
      kpi('card', 'Card', money(t.card), pct(t.card, t.total) + '%' + (t.other > 0 ? ' · other ' + money(t.other) : '')) +
      kpi(t.expenses > 0 ? 'bad' : '', 'Expenses', money(t.expenses), 'Recorded in Expenses') +
      kpi(t.net >= 0 ? 'good' : 'bad', 'Net', money(t.net), 'Collection − expenses'));

    // Long ranges read better as one bar per month than 180 thin day bars.
    var monthly = r.by_day.length > 45;
    $('#col-chart-title').text(monthly ? 'Month-wise collection' : 'Day-wise collection');
    $('#col-chart').html(t.total > 0
      ? (monthly ? App.bars(r.by_month, 'total', 'label', App.short) : App.bars(r.by_day, 'total', 'label', App.short))
      : '<div class="gf-empty">No collection in this period.</div>');

    var total = Math.max(t.total, 1);
    $('#col-methods').html(
      '<div class="gf-split mb-3">' +
        '<span style="width:' + pct(t.cash, total) + '%;background:var(--gf-cash)"></span>' +
        '<span style="width:' + pct(t.upi, total) + '%;background:var(--gf-upi)"></span>' +
        '<span style="width:' + pct(t.card, total) + '%;background:var(--gf-card)"></span>' +
        '<span style="width:' + pct(t.other, total) + '%;background:var(--gf-faint)"></span></div>' +
      '<div class="d-flex flex-wrap justify-content-between gap-2 small">' +
        '<span style="color:var(--gf-cash)">Cash <b class="tabular">' + esc(money(t.cash)) + '</b></span>' +
        '<span style="color:var(--gf-upi)">UPI <b class="tabular">' + esc(money(t.upi)) + '</b></span>' +
        '<span style="color:var(--gf-card)">Card <b class="tabular">' + esc(money(t.card)) + '</b></span></div>');

    var src = r.by_type;
    $('#col-sources').html($.map([['membership', 'Memberships', src.membership], ['store', 'Store (POS)', src.store], ['payment', 'Other payments', src.payment]],
      function (s) {
        return '<div class="d-flex justify-content-between align-items-center small py-1">' +
          '<span class="gf-pill gf-pill--' + s[0] + '">' + esc(s[1]) + '</span>' +
          '<span class="tabular"><b>' + esc(money(s[2])) + '</b> <span class="text-secondary">' + pct(s[2], t.total) + '%</span></span></div>';
      }).join(''));

    $('#col-facts').html(
      '<div class="d-flex justify-content-between small py-1"><span class="text-secondary">Average per day</span><b class="tabular">' + esc(money(t.average_per_day)) + '</b></div>' +
      '<div class="d-flex justify-content-between small py-1"><span class="text-secondary">Best day</span><b class="tabular">' +
        (t.best_day ? esc(App.day(t.best_day.date)) + ' · ' + esc(money(t.best_day.total)) : '—') + '</b></div>');

    renderMonths(r.by_month);
    renderDays(r.by_day);
  }

  function amountCells(row) {
    return '<td class="text-end fw-bold tabular">' + esc(money(row.total)) + '</td>' +
      '<td class="text-end d-none d-md-table-cell tabular" style="color:var(--gf-cash)">' + esc(money(row.cash)) + '</td>' +
      '<td class="text-end d-none d-md-table-cell tabular" style="color:var(--gf-upi)">' + esc(money(row.upi)) + '</td>' +
      '<td class="text-end d-none d-md-table-cell tabular" style="color:var(--gf-card)">' + esc(money(row.card)) + '</td>' +
      '<td class="text-end d-none d-lg-table-cell tabular">' + row.count + '</td>' +
      '<td class="text-end d-none d-sm-table-cell tabular" style="color:' + (row.expenses > 0 ? 'var(--gf-bad)' : 'inherit') + '">' +
        (row.expenses > 0 ? '−' + esc(money(row.expenses)) : '—') + '</td>' +
      '<td class="text-end tabular fw-semibold" style="color:' + (row.net < 0 ? 'var(--gf-bad)' : 'inherit') + '">' + esc(money(row.net)) + '</td>';
  }

  var HEAD = '<th class="text-end">Collection</th><th class="text-end d-none d-md-table-cell">Cash</th><th class="text-end d-none d-md-table-cell">UPI</th>' +
    '<th class="text-end d-none d-md-table-cell">Card</th><th class="text-end d-none d-lg-table-cell">Txn</th>' +
    '<th class="text-end d-none d-sm-table-cell">Expenses</th><th class="text-end">Net</th>';

  function totalRow(label, t) {
    return '<tr class="fw-bold" style="border-top:2px solid var(--gf-line)"><td>' + esc(label) + '</td>' + amountCells(t) + '<td></td></tr>';
  }

  function renderMonths(months) {
    // Only worth a table when the range spans more than one month.
    $('#col-months-card').toggleClass('d-none', months.length < 2);
    if (months.length < 2) { return; }
    $('#col-months').html('<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr><th>Month</th>' + HEAD + '<th></th></tr></thead><tbody>' +
      $.map(months, function (mo) {
        return '<tr><td class="fw-medium">' + esc(mo.label) + ' <span class="text-secondary" style="font-size:.7rem">· ' + mo.days + ' d</span></td>' +
          amountCells(mo) + '<td></td></tr>';
      }).join('') + totalRow('Total', report.totals) + '</tbody></table></div>');
  }

  function renderDays(days) {
    var hide = $('#hide-empty').is(':checked');
    var today = App.today();
    var rows = $.grep(days.slice().reverse(), function (d) { return !hide || d.total > 0 || d.expenses > 0; });

    if (!rows.length) {
      $('#col-days').html('<div class="gf-empty">No collection in this period.</div>');
      return;
    }

    $('#col-days').html('<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr><th>Date</th>' + HEAD + '<th></th></tr></thead><tbody>' +
      $.map(rows, function (d) {
        var isToday = d.date === today;
        return '<tr data-date="' + esc(d.date) + '"' + (isToday ? ' style="background:rgba(var(--gf-gold-rgb),.06)"' : '') + '>' +
          '<td class="text-nowrap"><span class="fw-medium">' + esc(App.day(d.date)) + '</span> <span class="text-secondary" style="font-size:.7rem">' + esc(d.weekday) + '</span>' +
            (isToday ? ' <span class="badge text-bg-primary ms-1">Today</span>' : '') + '</td>' +
          amountCells(d) +
          '<td class="text-end">' + (d.count ? '<button class="btn btn-light btn-sm js-day" data-date="' + esc(d.date) + '">View</button>' : '') + '</td></tr>';
      }).join('') + totalRow('Total', report.totals) + '</tbody></table></div>');
  }

  // ---------- One day's transactions, opened under its row ----------
  $('#col-days').on('click', '.js-day', function () {
    var $btn = $(this);
    var date = $btn.data('date');
    var $row = $btn.closest('tr');
    var $open = $row.next('.js-detail');
    if ($open.length) { $open.remove(); $btn.text('View'); return; }

    $btn.text('Hide');
    var $detail = $('<tr class="js-detail"><td colspan="9"><div class="gf-empty">Loading…</div></td></tr>').insertAfter($row);
    App.get('/collection', { date: date })
      .done(function (c) {
        $detail.find('td').html('<div class="gf-card-inset p-2">' + (c.transactions.length
          ? '<table class="table table-sm align-middle small mb-0"><tbody>' + $.map(c.transactions, function (t) {
              return '<tr><td class="text-secondary tabular text-nowrap">' + esc(App.time(t.time)) + '</td>' +
                '<td><div class="fw-medium">' + esc(t.label) + '</div><div class="text-secondary" style="font-size:.72rem">' + esc(t.detail) + '</div></td>' +
                '<td class="d-none d-sm-table-cell"><span class="gf-pill gf-pill--' + esc(t.type) + '">' + esc(t.type) + '</span></td>' +
                '<td>' + App.methodPill(t.method) + '</td>' +
                '<td class="text-end fw-bold tabular">' + esc(money(t.amount)) + '</td></tr>';
            }).join('') + '</tbody></table>'
          : '<div class="gf-empty">No transactions.</div>') + '</div>');
      })
      .fail(function (xhr) { $detail.remove(); $btn.text('View'); App.flash(App.errorMessage(xhr, 'Could not load that day.'), 'danger'); });
  });

  function load() {
    var mine = ++seq;
    $('#col-days').html('<div class="gf-empty">Loading…</div>');
    return App.get('/collection/report', query())
      .done(function (r) { if (mine === seq) { report = r; render(r); } })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load the collection report.'), 'danger'); });
  }

  preset('month');
  load();
});
