$(function () {
  'use strict';

  var esc = App.esc;
  var NAME = {
    present: 'Present', half_day: 'Half day', absent: 'Absent', leave: 'Leave', holiday: 'Holiday',
    weekly_off: 'Off', not_worked: '', unmarked: 'Not marked', pending: '', future: '', outside: ''
  };

  var now = new Date();
  var month = now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2);
  var data = null;
  var busy = false;

  function shift(delta) {
    var p = month.split('-');
    var d = new Date(Number(p[0]), Number(p[1]) - 1 + delta, 1);
    month = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2);
    load();
  }
  $('#month-prev').on('click', function () { shift(-1); });
  $('#month-next').on('click', function () { shift(1); });

  // ---------- Live clock ----------
  function tick() {
    var d = new Date();
    $('#clock-now').text(('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2) + ':' + ('0' + d.getSeconds()).slice(-2));
    $('#clock-date').text(d.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long' }));
  }
  tick();
  setInterval(tick, 1000);

  // ---------- Today's clock card ----------
  function paintClock(t) {
    var $btn = $('#btn-clock').prop('disabled', false).removeClass('btn-light btn-primary');
    var state, label, cls = 'btn-primary', disabled = false;

    if (t.open) {
      state = 'You clocked in at <b>' + esc(t.in) + '</b>' + (t.late ? ' <span class="gf-pill gf-pill--expired">Late</span>' : '');
      label = 'Clock out';
    } else if (t.in) {
      state = 'Done for today — <b>' + esc(t.in) + ' – ' + esc(t.out) + '</b> (' + esc(App.hm(t.minutes)) + ')' + (t.late ? ' <span class="gf-pill gf-pill--expired">Late</span>' : '');
      label = 'Clocked out'; cls = 'btn-light'; disabled = true;
    } else if (t.state === 'leave' || t.state === 'holiday') {
      state = 'Today is marked as <b>' + (t.state === 'leave' ? 'paid leave' : 'a holiday') + '</b>';
      label = 'Clock in'; cls = 'btn-light'; disabled = true;
    } else if (t.state === 'weekly_off') {
      state = 'Today is your <b>weekly off</b>. You can still clock in if you are working.';
      label = 'Clock in';
    } else {
      state = 'You have not clocked in yet';
      label = 'Clock in';
    }
    $('#clock-state').html(state);
    $btn.addClass(cls).prop('disabled', disabled).text(label);
  }

  $('#btn-clock').on('click', function () {
    if (busy) { return; }
    busy = true;
    var url = data.today.open ? '/my/clock-out' : '/my/clock-in';
    $('#clock-error').addClass('d-none');

    App.post(url)
      .done(function (t) {
        App.flash(url === '/my/clock-in' ? 'Clocked in at ' + t.in + (t.late ? ' — marked late.' : '.') : 'Clocked out. You worked ' + App.hm(t.minutes) + ' today.');
        load();
      })
      .fail(function (xhr) { $('#clock-error').text(App.errorMessage(xhr, 'Could not record that.')).removeClass('d-none'); })
      .always(function () { busy = false; });
  });

  // ---------- Month ----------
  function paintMonth() {
    $('#month-label').text(data.label);
    var s = data.row.summary;
    function kpi(cls, label, value, sub) {
      return '<div class="col-6 col-md-3"><div class="gf-kpi gf-kpi--' + cls + '"><div class="gf-kpi-label"><span>' + label + '</span></div>' +
        '<div class="gf-kpi-value">' + esc(value) + '</div><div class="gf-kpi-sub">' + esc(sub) + '</div></div></div>';
    }
    $('#my-kpis').html(
      kpi('good', 'Present', s.present + s.half_day * 0.5, s.half_day ? s.half_day + ' half day(s)' : 'days worked') +
      kpi(s.late ? 'warn' : '', 'Late', s.late, 'arrivals') +
      kpi('', 'Leave', s.leave, 'paid leave days') +
      kpi(s.absent + s.unmarked ? 'bad' : '', 'Absent', s.absent + s.unmarked, s.unmarked ? s.unmarked + ' not marked yet' : 'days')
    );

    $('#cal-head').html($.map(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], function (d) { return '<div class="gf-cal-head">' + d + '</div>'; }).join(''));

    var cells = [];
    for (var i = 0; i < data.first_weekday; i++) { cells.push('<div></div>'); }
    var today = App.today();
    $.each(data.row.days, function (_, d) {
      var lines = NAME[d.state] || '';
      var extra = d.in ? '<span class="text-secondary" style="font-size:.62rem">' + esc(d.in) + (d.out ? '–' + esc(d.out) : '') + '</span>' : '';
      cells.push('<div class="gf-cal-day gf-cal-day--' + d.state + (d.date === today ? ' is-today' : '') + '" title="' + esc(d.note || '') + '">' +
        '<span class="gf-num">' + d.day + '</span>' + (d.late ? ' <span title="Late" style="color:var(--gf-bad)">●</span>' : '') +
        '<span class="gf-tag">' + esc(lines) + '</span>' + extra + '</div>');
    });
    $('#cal').html(cells.join(''));
  }

  function load() {
    return App.get('/my/attendance', { month: month }).done(function (res) {
      if (!res.profile) { $('#my-empty').removeClass('d-none'); $('#my-body').addClass('d-none'); $('#month-label').text(''); return; }
      data = res;
      month = res.month;
      $('#my-empty').addClass('d-none');
      $('#my-body').removeClass('d-none');

      var p = res.profile;
      $('#my-sub').text(p.employee_code + (p.designation ? ' · ' + p.designation : ''));
      $('#clock-shift').text((p.shift_start ? 'Shift ' + p.shift_start + ' – ' + (p.shift_end || '') + ' · ' : '') + 'weekly off ' + App.WEEKDAYS[p.weekly_off] + ' · paid ' + (p.pay_type === 'hourly' ? 'by the hour' : 'monthly'));
      paintClock(res.today);
      paintMonth();
    }).fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load your attendance.'), 'danger'); });
  }

  // ---------- Payslips ----------
  function loadSlips() {
    App.get('/my/payslips').done(function (slips) {
      $('#my-payslips').html(slips.length ? '<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr><th>Month</th><th class="text-end">Net pay</th><th class="d-none d-sm-table-cell">Paid on</th><th class="d-none d-sm-table-cell">Slip no.</th><th></th></tr></thead><tbody>' +
        $.map(slips, function (s) {
          return '<tr><td class="fw-semibold">' + esc(new Date(s.month + 'T00:00:00').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })) + '</td>' +
            '<td class="text-end fw-bold tabular">' + esc(App.money(s.net_pay)) + '</td>' +
            '<td class="d-none d-sm-table-cell">' + esc(App.day(s.paid_on)) + '</td><td class="d-none d-sm-table-cell tabular text-secondary">' + esc(s.slip_number) + '</td>' +
            '<td class="text-end"><a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="' + esc(App.url('/payroll/' + s.id + '/slip')) + '">View</a></td></tr>';
        }).join('') + '</tbody></table></div>'
        : '<div class="gf-empty">No payslips yet. They appear here once your salary has been paid.</div>');
    });
  }

  load().always(loadSlips);
});
