$(function () {
  'use strict';

  var esc = App.esc;
  var money = App.money;

  // Payroll is normally done for the month that just ended.
  var now = new Date();
  var prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  var month = prev.getFullYear() + '-' + ('0' + (prev.getMonth() + 1)).slice(-2);

  var data = null;
  var rows = {};   // user_id -> row

  function shift(delta) {
    var p = month.split('-');
    var d = new Date(Number(p[0]), Number(p[1]) - 1 + delta, 1);
    month = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2);
    load();
  }
  $('#month-prev').on('click', function () { shift(-1); });
  $('#month-next').on('click', function () { shift(1); });

  function figures(r) { return r.slip || r.preview; }
  function hasUnmarked(r) { return !!(r.slip ? r.slip.status !== 'paid' : true) && $.grep(r.warnings, function (w) { return /unmarked/.test(w); }).length > 0; }

  // ---------- Rendering ----------
  function attendanceText(r) {
    var x = figures(r);
    if (r.pay_type === 'hourly') {
      return '<span class="tabular">' + esc(App.hm(x.worked_minutes)) + '</span> <span class="text-secondary">· ' + (x.present_days + x.half_days) + ' days</span>';
    }
    var bits = ['<span style="color:var(--gf-cash)">P ' + x.present_days + '</span>'];
    if (x.half_days) { bits.push('<span style="color:var(--gf-warn)">½ ' + x.half_days + '</span>'); }
    if (x.leave_days) { bits.push('<span style="color:var(--gf-upi)">L ' + x.leave_days + '</span>'); }
    if (x.holiday_days) { bits.push('<span style="color:var(--gf-card)">H ' + x.holiday_days + '</span>'); }
    bits.push('<span style="color:' + (x.absent_days ? 'var(--gf-bad)' : 'inherit') + '">A ' + x.absent_days + '</span>');
    if (x.late_marks) { bits.push('<span style="color:var(--gf-warn)">Late ' + x.late_marks + '</span>'); }
    return '<span class="tabular">' + bits.join(' · ') + '</span><div class="text-secondary" style="font-size:.68rem">' + x.payable_days + ' payable of ' + x.days_in_month + ' days</div>';
  }

  function statusPill(r) {
    if (!r.slip) { return '<span class="gf-pill gf-pill--none">Not generated</span>'; }
    if (r.slip.status === 'paid') { return '<span class="gf-pill gf-pill--active">Paid</span>' + (r.slip.paid_on ? '<div class="text-secondary" style="font-size:.68rem">' + esc(App.day(r.slip.paid_on)) + '</div>' : ''); }
    return '<span class="gf-pill gf-pill--expiring">Draft</span>' + (r.stale ? '<div style="font-size:.68rem;color:var(--gf-warn)">Attendance changed</div>' : '');
  }

  function actions(r) {
    var s = r.slip;
    var slipLink = s ? '<a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="' + esc(App.url('/payroll/' + s.id + '/slip')) + '" title="Payslip"><svg class="gf-ico gf-ico--sm"><use href="#i-receipt"/></svg></a>' : '';

    if (!s) {
      return '<button class="btn btn-light btn-sm js-gen" data-u="' + esc(r.user_id) + '"' + (data.can_generate ? '' : ' disabled title="Available after the month ends"') + '>Generate</button>';
    }
    if (s.status === 'paid') { return slipLink; }
    return '<div class="d-inline-flex flex-wrap justify-content-end gap-1">' +
      '<button class="btn btn-light btn-sm js-adjust" data-u="' + esc(r.user_id) + '">Adjust</button>' +
      '<button class="btn btn-primary btn-sm js-pay" data-u="' + esc(r.user_id) + '">Pay</button>' +
      '<button class="btn ' + (r.stale ? 'btn-outline-gold' : 'btn-light') + ' btn-sm js-gen" data-u="' + esc(r.user_id) + '" title="Recalculate from attendance">' +
        '<svg class="gf-ico gf-ico--sm"><use href="#i-refresh"/></svg></button>' +
      slipLink +
      '<button class="btn btn-light btn-sm js-del" data-u="' + esc(r.user_id) + '" title="Discard draft"><svg class="gf-ico gf-ico--sm"><use href="#i-x"/></svg></button></div>';
  }

  function render() {
    $('#month-label').text(data.label);
    rows = {};
    $.each(data.rows, function (_, r) { rows[r.user_id] = r; });

    $('#payroll-note').html(data.can_generate ? '' :
      '<div class="alert alert-warning small py-2">Salary for <b>' + esc(data.label) + '</b> can be generated after the month ends (' + esc(App.day(data.ends_on)) + '). ' +
      'The figures below are a live preview.</div>');
    $('#btn-generate').prop('disabled', !data.can_generate || !data.rows.length);

    var t = data.totals;
    function kpi(cls, label, value, sub) {
      return '<div class="col-6 col-lg"><div class="gf-kpi gf-kpi--' + cls + '"><div class="gf-kpi-label"><span>' + label + '</span></div>' +
        '<div class="gf-kpi-value">' + esc(value) + '</div><div class="gf-kpi-sub">' + esc(sub) + '</div></div></div>';
    }
    $('#payroll-kpis').html(
      kpi('', 'Staff', t.staff, t.generated + ' payslip' + (t.generated === 1 ? '' : 's') + ' generated') +
      kpi('total', 'Net payroll', money(t.net), 'Across generated payslips') +
      kpi('good', 'Paid', money(t.paid), 'Locked, visible to staff') +
      kpi(t.pending > 0 ? 'warn' : '', 'To pay', money(t.pending), 'Drafts awaiting payment')
    );

    if (!data.rows.length) { $('#payroll-table').html('<div class="gf-empty py-4">Nobody was employed in ' + esc(data.label) + '. Add staff profiles on the Staff page.</div>'); return; }

    $('#payroll-table').html('<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr>' +
      '<th>Staff</th><th>Attendance</th><th class="text-end">Earned</th><th class="text-end d-none d-md-table-cell">Deductions</th><th class="text-end d-none d-md-table-cell">Bonus</th>' +
      '<th class="text-end">Net pay</th><th>Status</th><th></th></tr></thead><tbody>' +
      $.map(data.rows, function (r) {
        var x = figures(r);
        var deductions = Number(x.late_deduction || 0) + Number(x.other_deduction || 0);
        var net = r.slip ? Number(r.slip.net_pay) : Number(x.gross_pay) - Number(x.late_deduction);
        return '<tr' + (r.is_active ? '' : ' class="opacity-75"') + '>' +
          '<td><div class="fw-semibold">' + esc(r.name) + (r.is_active ? '' : ' <span class="gf-pill gf-pill--none">Left</span>') + '</div>' +
            '<div class="text-secondary" style="font-size:.7rem">' + esc(r.employee_code) + ' · ' + (r.pay_type === 'hourly' ? money(r.base_salary) + '/hr' : money(r.base_salary) + '/mo') + '</div>' +
            $.map(r.warnings, function (w) { return '<div style="font-size:.7rem;color:var(--gf-warn)">⚠ ' + esc(w) + '</div>'; }).join('') + '</td>' +
          '<td>' + attendanceText(r) + '</td>' +
          '<td class="text-end tabular">' + esc(money(x.gross_pay)) + '</td>' +
          '<td class="text-end tabular d-none d-md-table-cell" style="color:' + (deductions ? 'var(--gf-bad)' : 'inherit') + '">' + (deductions ? '−' + esc(money(deductions)) : '—') + '</td>' +
          '<td class="text-end tabular d-none d-md-table-cell" style="color:' + (Number(x.bonus) ? 'var(--gf-cash)' : 'inherit') + '">' + (Number(x.bonus) ? '+' + esc(money(x.bonus)) : '—') + '</td>' +
          '<td class="text-end fw-bold tabular">' + esc(money(net)) + (r.slip ? '' : '<div class="text-secondary fw-normal" style="font-size:.66rem">preview</div>') + '</td>' +
          '<td>' + statusPill(r) + '</td>' +
          '<td class="text-end text-nowrap">' + actions(r) + '</td></tr>';
      }).join('') + '</tbody></table></div>');
  }

  function load() {
    return App.get('/payroll', { month: month })
      .done(function (res) { data = res; month = res.month; render(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load payroll.'), 'danger'); });
  }

  // ---------- Generate ----------
  function runGenerate(userIds, treat) {
    return App.post('/payroll/generate', { month: month, user_ids: userIds, treat_unmarked_absent: treat })
      .done(function (res) {
        var bits = [];
        if (res.created) { bits.push(res.created + ' generated'); }
        if (res.updated) { bits.push(res.updated + ' refreshed'); }
        if (res.skipped_paid.length) { bits.push(res.skipped_paid.length + ' already paid (unchanged)'); }
        App.flash(bits.length ? bits.join(' · ') + '.' : 'Nothing to generate.', res.blocked.length ? 'warning' : 'success');
        if (res.blocked.length) {
          App.flash('Skipped (unmarked days): ' + $.map(res.blocked, function (b) { return b.name; }).join(', ') + '.', 'warning');
        }
        load();
      })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not generate payroll.'), 'danger'); });
  }

  /** Generate for these people; if any have unmarked days, ask first. userIds null = everyone. */
  function generate(userIds) {
    var targets = $.grep(data.rows, function (r) {
      return (userIds === null || userIds.indexOf(r.user_id) !== -1) && (!r.slip || r.slip.status !== 'paid');
    });
    var gaps = $.grep(targets, hasUnmarked);

    if (!gaps.length) { runGenerate(userIds, false); return; }

    $('#unmarked-modal .js-names').html($.map(gaps, function (r) {
      var n = 0;
      $.each(r.warnings, function (_, w) { var m = /^(\d+) unmarked/.exec(w); if (m) { n = m[1]; } });
      return '<li><b>' + esc(r.name) + '</b> — ' + n + ' day' + (n === '1' ? '' : 's') + '</li>';
    }).join(''));
    $('#unmarked-modal .js-confirm').off('click').on('click', function () {
      App.modal('#unmarked-modal').hide();
      runGenerate(userIds, true);
    });
    App.modal('#unmarked-modal').show();
  }

  $('#btn-generate').on('click', function () { generate(null); });
  $('#payroll-table').on('click', '.js-gen', function () { generate([Number($(this).data('u'))]); });

  // ---------- Adjust ----------
  var $adj = $('#adjust-form');
  var adjusting = null;

  function paintNet() {
    var x = adjusting.slip;
    var net = Number(x.gross_pay) - Number(x.late_deduction) + (Number($adj.find('[name="bonus"]').val()) || 0) - (Number($adj.find('[name="other_deduction"]').val()) || 0);
    $adj.find('.js-net').text(money(net)).css('color', net < 0 ? 'var(--gf-bad)' : 'var(--gf-gold)');
  }
  $adj.find('[name="bonus"], [name="other_deduction"]').on('input', paintNet);

  $('#payroll-table').on('click', '.js-adjust', function () {
    adjusting = rows[$(this).data('u')];
    var s = adjusting.slip;
    App.formError($adj, '');
    $adj.find('.js-who').text(adjusting.name + ' · ' + data.label);
    $adj.find('.js-gross').text(money(s.gross_pay));
    $adj.find('.js-late').text(Number(s.late_deduction) ? '−' + money(s.late_deduction) : '—');
    $adj.find('[name="bonus"]').val(Number(s.bonus));
    $adj.find('[name="other_deduction"]').val(Number(s.other_deduction));
    $adj.find('[name="adjustment_note"]').val(s.adjustment_note || '');
    paintNet();
    App.modal('#adjust-modal').show();
  });

  $adj.on('submit', function (e) {
    e.preventDefault();
    App.formError($adj, '');
    var $btn = $adj.find('button[type="submit"]').prop('disabled', true);

    App.api('PUT', '/payslips/' + adjusting.slip.id, {
      bonus: App.num($adj.find('[name="bonus"]').val()) || 0,
      other_deduction: App.num($adj.find('[name="other_deduction"]').val()) || 0,
      adjustment_note: $.trim($adj.find('[name="adjustment_note"]').val()) || null
    })
      .done(function () { App.modal('#adjust-modal').hide(); App.flash('Salary adjusted.'); load(); })
      .fail(function (xhr) { App.formError($adj, App.errorMessage(xhr, 'Could not save.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Pay ----------
  var $pay = $('#pay-form');
  var paying = null;

  $('#payroll-table').on('click', '.js-pay', function () {
    paying = rows[$(this).data('u')];
    App.formError($pay, '');
    $pay.find('.js-who').text(paying.name + ' · ' + data.label);
    $pay.find('.js-net').text(money(paying.slip.net_pay));
    $pay.find('[name="paid_on"]').val(App.today());
    $pay.find('[name="payment_reference"]').val('');
    App.modal('#pay-modal').show();
  });

  $pay.on('submit', function (e) {
    e.preventDefault();
    App.formError($pay, '');
    var $btn = $pay.find('button[type="submit"]').prop('disabled', true);

    App.post('/payslips/' + paying.slip.id + '/pay', {
      paid_on: $pay.find('[name="paid_on"]').val() || null,
      payment_method: $pay.find('[name="payment_method"]').val(),
      payment_reference: $.trim($pay.find('[name="payment_reference"]').val()) || null
    })
      .done(function () { App.modal('#pay-modal').hide(); App.flash(paying.name + '’s salary marked paid.'); load(); })
      .fail(function (xhr) { App.formError($pay, App.errorMessage(xhr, 'Could not mark paid.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Discard a draft ----------
  $('#payroll-table').on('click', '.js-del', function () {
    var r = rows[$(this).data('u')];
    if (!window.confirm('Discard the draft payslip for ' + r.name + '? You can generate it again.')) { return; }
    App.api('DELETE', '/payslips/' + r.slip.id)
      .done(function () { App.flash('Draft discarded.'); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not discard.'), 'danger'); });
  });

  load();
});
