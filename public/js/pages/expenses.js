$(function () {
  'use strict';

  var esc = App.esc;
  var money = App.money;
  var page = window.ExpensesPage || {};
  var METHODS = page.methods || {};
  var METHOD_PILL = { cash: 'cash', upi: 'upi', card: 'card', bank_transfer: 'other' };

  var $from = $('#range-from');
  var $to = $('#range-to');
  var $category = $('#filter-category');
  var expenses = {};   // id -> row, so the edit/delete buttons can look up their entry
  var seq = 0;         // bumped per load, so a slow reply for an old filter is ignored

  // ---------- Date range + filters ----------
  function ymd(d) {
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }

  function preset(key) {
    key = String(key); // jQuery .data() turns "7" into the number 7
    var now = new Date();
    var start = new Date(now);
    var end = new Date(now);
    if (key === '7') { start.setDate(now.getDate() - 6); }
    else if (key === '30') { start.setDate(now.getDate() - 29); }
    else if (key === 'month') { start = new Date(now.getFullYear(), now.getMonth(), 1); }
    else if (key === 'last-month') {
      start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      end = new Date(now.getFullYear(), now.getMonth(), 0);
    }
    $from.val(ymd(start));
    $to.val(ymd(end));
  }

  function query() {
    var q = { from: $from.val(), to: $to.val() };
    if ($category.val()) { q.category = $category.val(); }
    return q;
  }

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

  $category.on('change', load);

  $('#btn-export').on('click', function () {
    this.href = App.url('/ajax/expenses/export?' + $.param(query()));
  });

  // ---------- Rendering ----------
  function kpi(cls, label, value, sub) {
    return '<div class="col-6 col-lg"><div class="gf-kpi gf-kpi--' + cls + '"><div class="gf-kpi-label"><span>' + esc(label) + '</span></div>' +
      '<div class="gf-kpi-value">' + esc(value) + '</div><div class="gf-kpi-sub">' + esc(sub || '') + '</div></div></div>';
  }

  function renderSummary(s) {
    var days = s.by_day.length;
    var html = kpi('bad', 'Total spent', money(s.total), s.count + ' entr' + (s.count === 1 ? 'y' : 'ies')) +
      kpi('', 'Daily average', money(days ? s.total / days : 0), 'over ' + days + ' day' + (days === 1 ? '' : 's')) +
      kpi('cash', 'Paid in cash', money(s.by_method.cash || 0), 'Out of the cash drawer');

    // Owner's profit view (only sent for admins, and only when no category filter is applied).
    if (s.income !== undefined) {
      html += kpi('total', 'Income', money(s.income), 'Memberships + store') +
        kpi(s.net >= 0 ? 'good' : 'bad', 'Net profit', money(s.net),
          s.salaries > 0 ? 'after ' + money(s.salaries) + ' salaries (Payroll)' : 'Income − expenses');
    }
    $('#expense-kpis').html(html);

    $('#expense-chart').html(s.total > 0
      ? App.bars(s.by_day, 'total', 'label', App.short)
      : '<div class="gf-empty">No expenses in this period.</div>');

    var max = s.by_category.length ? s.by_category[0].total : 1;
    $('#expense-categories').html(s.by_category.length
      ? $.map(s.by_category, function (c) {
          var pct = Math.round(c.total / Math.max(s.total, 1) * 100);
          return '<div class="mb-2"><div class="d-flex justify-content-between gap-2 small">' +
              '<span class="gf-truncate">' + esc(c.label) + ' <span class="text-secondary">· ' + c.count + '</span></span>' +
              '<span class="fw-bold tabular text-nowrap">' + esc(money(c.total)) + ' <span class="text-secondary fw-normal">' + pct + '%</span></span></div>' +
            '<div class="gf-split mt-1" style="height:.4rem"><span style="width:' + Math.round(c.total / max * 100) + '%;background:var(--gf-gold)"></span></div></div>';
        }).join('')
      : '<div class="gf-empty">Nothing recorded yet.</div>');

    $('#expense-methods').html('<div class="d-flex flex-wrap gap-3 small">' + $.map(METHODS, function (label, key) {
      return '<span>' + esc(label) + ' <b class="tabular">' + esc(money(s.by_method[key] || 0)) + '</b></span>';
    }).join('') + '</div>');
  }

  function renderList(list) {
    expenses = {};
    $.each(list, function (_, e) { expenses[e.id] = e; });
    $('#expense-count').text(list.length + ' entr' + (list.length === 1 ? 'y' : 'ies'));

    if (!list.length) {
      $('#expense-table').html('<div class="gf-empty">No expenses in this period. Use “Add Expense” to record one.</div>');
      return;
    }

    $('#expense-table').html('<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr>' +
      '<th>Date</th><th>Expense</th><th class="d-none d-md-table-cell">Category</th><th>Paid by</th>' +
      '<th class="text-end">Amount</th><th></th></tr></thead><tbody>' +
      $.map(list, function (e) {
        var meta = [e.paid_to ? 'To ' + e.paid_to : null, e.reference ? '#' + e.reference : null, e.recorded_by ? 'by ' + e.recorded_by : null]
          .filter(Boolean).join(' · ');
        return '<tr>' +
          '<td class="text-nowrap tabular">' + esc(App.day(e.spent_on)) + '</td>' +
          '<td style="max-width:20rem"><div class="fw-medium gf-truncate" title="' + esc(e.description) + '">' + esc(e.description) + '</div>' +
            '<div class="text-secondary gf-truncate" style="font-size:.72rem">' +
              '<span class="d-md-none">' + esc(e.category_label) + (meta ? ' · ' : '') + '</span>' + esc(meta) + '</div>' +
            (e.notes ? '<div class="text-secondary gf-truncate" style="font-size:.72rem" title="' + esc(e.notes) + '">' + esc(e.notes) + '</div>' : '') + '</td>' +
          '<td class="d-none d-md-table-cell"><span class="gf-pill gf-pill--other">' + esc(e.category_label) + '</span></td>' +
          '<td><span class="gf-pill gf-pill--' + (METHOD_PILL[e.method] || 'other') + '">' + esc(METHODS[e.method] || e.method) + '</span></td>' +
          '<td class="text-end fw-bold tabular text-nowrap">' + esc(money(e.amount)) + '</td>' +
          '<td class="text-end text-nowrap">' + (e.can_edit
            ? '<button class="btn btn-light btn-sm js-edit" data-id="' + esc(e.id) + '" title="Edit"><svg class="gf-ico gf-ico--sm"><use href="#i-edit"/></svg></button> ' +
              '<button class="btn btn-light btn-sm js-delete" data-id="' + esc(e.id) + '" title="Delete"><svg class="gf-ico gf-ico--sm"><use href="#i-x"/></svg></button>'
            : '') + '</td></tr>';
      }).join('') + '</tbody></table></div>');
  }

  function load() {
    var mine = ++seq;
    return App.get('/expenses', query())
      .done(function (res) {
        if (mine !== seq) { return; }
        renderSummary(res.summary);
        renderList(res.data);
      })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load expenses.'), 'danger'); });
  }

  // ---------- Add / edit ----------
  var $form = $('#expense-form');
  var editing = null;
  var FIELDS = ['spent_on', 'amount', 'category', 'method', 'description', 'paid_to', 'reference', 'notes'];

  function openForm(e) {
    editing = e || null;
    $form[0].reset();
    App.formError($form, '');
    $form.find('.js-title').text(editing ? 'Edit expense' : 'Add expense');
    if (editing) {
      $.each(FIELDS, function (_, name) { $form.find('[name="' + name + '"]').val(editing[name] === null ? '' : editing[name]); });
    } else {
      $form.find('[name="spent_on"]').val(App.today());
      $form.find('[name="method"]').val('cash');
      // Keep the category filter's choice as the default, handy when entering several bills of one kind.
      if ($category.val()) { $form.find('[name="category"]').val($category.val()); }
    }
    App.modal('#expense-modal').show();
  }

  $('#expense-modal').on('shown.bs.modal', function () { $form.find('[name="amount"]').trigger('focus'); });
  $('#btn-add-expense').on('click', function () { openForm(null); });
  $('#expense-table').on('click', '.js-edit', function () { openForm(expenses[$(this).data('id')]); });

  $form.on('submit', function (ev) {
    ev.preventDefault();
    var data = {};
    $.each(FIELDS, function (_, name) {
      var v = $.trim($form.find('[name="' + name + '"]').val());
      data[name] = v === '' ? null : v;
    });
    data.amount = App.num(data.amount);

    var $btn = $form.find('button[type="submit"]').prop('disabled', true);
    App.formError($form, '');
    (editing ? App.api('PUT', '/expenses/' + editing.id, data) : App.post('/expenses', data))
      .done(function () {
        App.modal('#expense-modal').hide();
        App.flash(editing ? 'Expense updated.' : 'Expense of ' + money(data.amount) + ' recorded.');
        load();
      })
      .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not save the expense.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Delete ----------
  var deleting = null;

  $('#expense-table').on('click', '.js-delete', function () {
    deleting = expenses[$(this).data('id')];
    $('#delete-modal .js-delete-what').text('“' + deleting.description + '” (' + money(deleting.amount) + ')');
    App.modal('#delete-modal').show();
  });

  $('#delete-modal .js-confirm-delete').on('click', function () {
    var $btn = $(this).prop('disabled', true);
    App.api('DELETE', '/expenses/' + deleting.id)
      .done(function () {
        App.modal('#delete-modal').hide();
        App.flash('Expense deleted.');
        load();
      })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not delete the expense.'), 'danger'); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // Deep link from the dashboard: /expenses?add=1
  preset('month');
  load();
  if (new URLSearchParams(window.location.search).get('add')) { openForm(null); }
});
