$(function () {
  'use strict';

  var esc = App.esc;
  var money = App.money;
  var isAdmin = !!(window.SalesPage && window.SalesPage.isAdmin);

  var $from = $('#range-from');
  var $to = $('#range-to');
  var sales = {};   // id -> sale, for the void button

  // ---------- Date range ----------
  function ymd(d) {
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }

  function preset(key) {
    key = String(key); // jQuery .data() turns "7" into the number 7
    var now = new Date();
    var start = new Date(now);
    if (key === '7') { start.setDate(now.getDate() - 6); }
    else if (key === '30') { start.setDate(now.getDate() - 29); }
    else if (key === 'month') { start = new Date(now.getFullYear(), now.getMonth(), 1); }
    $from.val(ymd(start));
    $to.val(ymd(now));
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

  $('#btn-export').on('click', function () {
    this.href = App.url('/ajax/store/sales/export?' + $.param(query()));
  });

  // ---------- Rendering ----------
  function kpi(cls, label, value, sub) {
    return '<div class="col-6 col-lg"><div class="gf-kpi gf-kpi--' + cls + '"><div class="gf-kpi-label"><span>' + esc(label) + '</span></div>' +
      '<div class="gf-kpi-value">' + esc(value) + '</div><div class="gf-kpi-sub">' + esc(sub || '') + '</div></div></div>';
  }

  function renderReport(r) {
    var margin = r.revenue > 0 && r.profit !== null ? Math.round(r.profit / r.revenue * 100) : 0;
    $('#sales-kpis').html(
      kpi('total', 'Revenue', money(r.revenue), r.sales_count + ' sale' + (r.sales_count === 1 ? '' : 's')) +
      kpi('', 'Units sold', r.units, 'Avg sale ' + money(r.average_sale)) +
      (isAdmin
        ? kpi(r.profit >= 0 ? 'good' : 'bad', 'Profit', money(r.profit), margin + '% margin · cost ' + money(r.cost))
        : '') +
      kpi(r.voided_count ? 'warn' : '', 'Voided', r.voided_count, r.voided_count ? money(r.voided_amount) + ' not counted' : 'None')
    );

    $('#sales-chart').html(r.revenue > 0
      ? App.bars(r.by_day, 'total', 'label', App.short)
      : '<div class="gf-empty">No sales in this period.</div>');

    var total = Math.max(r.revenue, 1);
    var pct = function (v) { return Math.round(v / total * 100); };
    var m = r.by_method;
    $('#sales-methods').html(
      '<div class="gf-split mb-3">' +
        '<span style="width:' + pct(m.cash) + '%;background:var(--gf-cash)"></span>' +
        '<span style="width:' + pct(m.upi) + '%;background:var(--gf-upi)"></span>' +
        '<span style="width:' + pct(m.card) + '%;background:var(--gf-card)"></span></div>' +
      '<div class="d-flex justify-content-between small">' +
        '<span style="color:var(--gf-cash)">Cash <b class="tabular">' + esc(money(m.cash)) + '</b></span>' +
        '<span style="color:var(--gf-upi)">UPI <b class="tabular">' + esc(money(m.upi)) + '</b></span>' +
        '<span style="color:var(--gf-card)">Card <b class="tabular">' + esc(money(m.card)) + '</b></span></div>');

    $('#sales-top').html(r.top_products.length
      ? '<table class="table table-sm align-middle small"><tbody>' + $.map(r.top_products, function (p) {
          return '<tr><td class="gf-truncate" style="max-width:9rem">' + esc(p.name) + '</td>' +
            '<td class="text-secondary tabular">× ' + p.quantity + '</td>' +
            '<td class="text-end fw-bold tabular">' + esc(money(p.revenue)) + '</td>' +
            (isAdmin ? '<td class="text-end tabular" style="color:var(--gf-cash)">+' + esc(money(p.profit)) + '</td>' : '') + '</tr>';
        }).join('') + '</tbody></table>'
      : '<div class="gf-empty">Nothing sold yet.</div>');
  }

  function renderSales(list) {
    sales = {};
    $.each(list, function (_, s) { sales[s.id] = s; });
    $('#sales-count').text(list.length + (list.length === 500 ? '+' : '') + ' record' + (list.length === 1 ? '' : 's'));

    if (!list.length) { $('#sales-table').html('<div class="gf-empty">No sales in this period.</div>'); return; }

    $('#sales-table').html('<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr>' +
      '<th>Receipt</th><th>Items</th><th class="d-none d-md-table-cell">Customer</th><th>Mode</th><th class="text-end">Total</th><th></th></tr></thead><tbody>' +
      $.map(list, function (s) {
        var voided = s.status === 'voided';
        var lines = $.map(s.items, function (i) { return i.name + ' x' + i.quantity; }).join(', ');
        return '<tr' + (voided ? ' class="opacity-50"' : '') + '>' +
          '<td><div class="fw-medium tabular">' + esc(s.receipt_number) + '</div><div class="text-secondary" style="font-size:.72rem">' +
            esc(App.day(s.sold_at)) + ' · ' + esc(App.time(s.sold_at)) + '</div></td>' +
          '<td style="max-width:18rem"><div class="gf-truncate' + (voided ? ' text-decoration-line-through' : '') + '">' + esc(lines) + '</div>' +
            (voided ? '<div style="font-size:.72rem;color:var(--gf-bad)">Voided' + (s.void_reason ? ' — ' + esc(s.void_reason) : '') + '</div>' : '') + '</td>' +
          '<td class="d-none d-md-table-cell">' + esc(s.member ? s.member.name : 'Walk-in') + '</td>' +
          '<td>' + App.methodPill(s.method) + '</td>' +
          '<td class="text-end fw-bold tabular' + (voided ? ' text-decoration-line-through' : '') + '">' + esc(money(s.total)) + '</td>' +
          '<td class="text-end text-nowrap">' +
            '<a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="' + esc(App.url('/sales/' + s.id + '/receipt')) + '" title="Receipt">' +
              '<svg class="gf-ico gf-ico--sm"><use href="#i-receipt"/></svg></a>' +
            (isAdmin && !voided ? ' <button class="btn btn-light btn-sm js-void" data-id="' + esc(s.id) + '" title="Void sale">' +
              '<svg class="gf-ico gf-ico--sm"><use href="#i-ban"/></svg></button>' : '') +
          '</td></tr>';
      }).join('') + '</tbody></table></div>');
  }

  function load() {
    var q = query();
    App.get('/store/report', q).done(renderReport)
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load the report.'), 'danger'); });
    return App.get('/store/sales', q).done(renderSales)
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load sales.'), 'danger'); });
  }

  // ---------- Void (admin) ----------
  if (isAdmin) {
    var $form = $('#void-form');
    var voiding = null;

    $('#sales-table').on('click', '.js-void', function () {
      voiding = sales[$(this).data('id')];
      $form[0].reset();
      App.formError($form, '');
      $form.find('.js-void-receipt').text(voiding.receipt_number);
      App.modal('#void-modal').show();
    });

    $form.on('submit', function (e) {
      e.preventDefault();
      var $btn = $form.find('button[type="submit"]').prop('disabled', true);
      App.post('/store/sales/' + voiding.id + '/void', { reason: $.trim($('#void-reason').val()) || null })
        .done(function () {
          App.modal('#void-modal').hide();
          App.flash(voiding.receipt_number + ' voided — stock restored.');
          load();
        })
        .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not void the sale.')); })
        .always(function () { $btn.prop('disabled', false); });
    });
  }

  preset('today');
  load();
});
