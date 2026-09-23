$(function () {
  'use strict';

  var esc = App.esc;
  var GLYPH = { present: 'P', half_day: '½', absent: 'A', leave: 'L', holiday: 'H', weekly_off: '·', not_worked: '·', unmarked: '?', pending: '…', future: '', outside: '' };
  var NAME = {
    present: 'Present', half_day: 'Half day', absent: 'Absent', leave: 'Paid leave', holiday: 'Holiday',
    weekly_off: 'Weekly off', not_worked: 'No shift', unmarked: 'Not marked', pending: 'Not in yet', future: 'Upcoming', outside: 'Not employed'
  };

  var month = new Date().getFullYear() + '-' + ('0' + (new Date().getMonth() + 1)).slice(-2);
  var data = null;
  var current = null;   // {row, day} being edited

  function shift(delta) {
    var p = month.split('-');
    var d = new Date(Number(p[0]), Number(p[1]) - 1 + delta, 1);
    month = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2);
    load();
  }
  $('#month-prev').on('click', function () { shift(-1); });
  $('#month-next').on('click', function () { shift(1); });
  $('#month-today').on('click', function () {
    var n = new Date();
    month = n.getFullYear() + '-' + ('0' + (n.getMonth() + 1)).slice(-2);
    load();
  });

  // ---------- Grid ----------
  function tip(row, d) {
    var parts = [App.day(d.date) + ' — ' + NAME[d.state]];
    if (d.in) { parts.push('in ' + d.in + (d.late ? ' (late)' : '')); }
    if (d.out) { parts.push('out ' + d.out); }
    if (d.minutes) { parts.push(App.hm(d.minutes)); }
    if (d.note) { parts.push('“' + d.note + '”'); }
    return parts.join(' · ');
  }

  function render() {
    $('#month-label').text(data.label);
    if (!data.rows.length) { $('#grid').html('<div class="gf-empty py-5">No staff were employed this month. Add staff profiles on the Staff page.</div>'); return; }

    var todayStr = App.today();
    var first = data.rows[0].days;

    var head = '<tr><th class="gf-sticky">Staff</th>' + $.map(first, function (d) {
      var wd = App.WEEKDAYS[new Date(d.date + 'T00:00:00').getDay()].charAt(0);
      return '<th class="' + (d.date === todayStr ? 'gf-today' : '') + '">' + d.day + '<div style="font-weight:500;opacity:.7">' + wd + '</div></th>';
    }).join('') +
      '<th class="gf-sum-head" title="Present days">P</th><th class="gf-sum-head" title="Half days">½</th><th class="gf-sum-head" title="Absent days">A</th>' +
      '<th class="gf-sum-head" title="Paid leave">L</th><th class="gf-sum-head" title="Late arrivals">Late</th><th class="gf-sum-head" title="Not marked">?</th><th class="gf-sum-head">Hours</th></tr>';

    var body = $.map(data.rows, function (r, ri) {
      var s = r.summary;
      return '<tr><td class="gf-sticky"><div class="fw-semibold gf-truncate">' + esc(r.name) + '</div>' +
        '<div class="text-secondary" style="font-size:.68rem">' + esc(r.employee_code) + ' · ' + (r.pay_type === 'hourly' ? 'hourly' : (r.shift_start ? esc(r.shift_start + '–' + (r.shift_end || '')) : 'monthly')) + '</div></td>' +
        $.map(r.days, function (d, di) {
          var editable = d.state !== 'outside';
          var cls = 'gf-cell gf-cell--' + d.state + (d.late ? ' gf-late' : '');
          var cell = editable
            ? '<button type="button" class="' + cls + ' js-cell" data-r="' + ri + '" data-d="' + di + '" title="' + esc(tip(r, d)) + '">' + GLYPH[d.state] + '</button>'
            : '<span class="' + cls + '"></span>';
          var wknd = new Date(d.date + 'T00:00:00').getDay() === r.weekly_off ? ' gf-wknd' : '';
          return '<td class="' + wknd + (d.date === todayStr ? ' gf-today' : '') + '">' + cell + '</td>';
        }).join('') +
        '<td class="gf-sum">' + s.present + '</td><td class="gf-sum">' + s.half_day + '</td>' +
        '<td class="gf-sum" style="color:' + (s.absent ? 'var(--gf-bad)' : 'inherit') + '">' + s.absent + '</td><td class="gf-sum">' + s.leave + '</td>' +
        '<td class="gf-sum" style="color:' + (s.late ? 'var(--gf-warn)' : 'inherit') + '">' + s.late + '</td>' +
        '<td class="gf-sum" style="color:' + (s.unmarked ? 'var(--gf-warn)' : 'inherit') + '">' + s.unmarked + '</td>' +
        '<td class="gf-sum">' + (s.minutes ? esc(App.hm(s.minutes)) : '—') + '</td></tr>';
    }).join('');

    $('#grid').html('<table class="gf-att"><thead>' + head + '</thead><tbody>' + body + '</tbody></table>');
  }

  function load() {
    return App.get('/staff-attendance', { month: month })
      .done(function (res) { data = res; month = res.month; render(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load attendance.'), 'danger'); });
  }

  // ---------- Mark / correct one day ----------
  var $form = $('#day-form');
  var f = function (name) { return $form.find('[name="' + name + '"]'); };

  function syncTimes() {
    var works = f('status').val() === 'present' || f('status').val() === 'half_day';
    $form.find('.js-times').toggleClass('d-none', !works);
    $form.find('.js-time-hint').text(current && current.row.pay_type === 'hourly'
      ? 'Hourly staff need both times — pay is worked out from them.'
      : 'Late arrivals are flagged automatically from the shift start.');
  }
  f('status').on('change', syncTimes);

  $('#grid').on('click', '.js-cell', function () {
    var row = data.rows[$(this).data('r')];
    var d = row.days[$(this).data('d')];
    current = { row: row, day: d };

    App.formError($form, '');
    $form.find('.js-who').text(row.name);
    $form.find('.js-when').text(App.WEEKDAYS[new Date(d.date + 'T00:00:00').getDay()] + ', ' + App.day(d.date));

    var status = ['present', 'half_day', 'absent', 'leave', 'holiday'].indexOf(d.state) !== -1 ? d.state : 'present';
    f('status').val(status);
    f('check_in').val(d.in || (d.state === 'unmarked' || d.state === 'pending' ? (row.shift_start || '') : ''));
    f('check_out').val(d.out || (d.state === 'unmarked' || d.state === 'pending' ? (row.shift_end || '') : ''));
    f('note').val(d.note || '');
    syncTimes();
    App.modal('#day-modal').show();
  });

  $form.on('submit', function (e) {
    e.preventDefault();
    App.formError($form, '');
    var $btn = $form.find('button[type="submit"]').prop('disabled', true);
    var works = f('status').val() === 'present' || f('status').val() === 'half_day';

    App.api('PUT', '/staff-attendance', {
      user_id: current.row.user_id,
      date: current.day.date,
      status: f('status').val(),
      check_in: works ? (f('check_in').val() || null) : null,
      check_out: works ? (f('check_out').val() || null) : null,
      note: $.trim(f('note').val()) || null
    })
      .done(function () { App.modal('#day-modal').hide(); App.flash('Saved.'); load(); })
      .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not save.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Bulk ----------
  var $bulk = $('#bulk-form');
  $('#btn-bulk').on('click', function () {
    App.formError($bulk, '');
    $bulk.find('[name="date"]').val(App.today());
    App.modal('#bulk-modal').show();
  });

  $bulk.on('submit', function (e) {
    e.preventDefault();
    App.formError($bulk, '');
    var $btn = $bulk.find('button[type="submit"]').prop('disabled', true);

    App.post('/staff-attendance/bulk', { date: $bulk.find('[name="date"]').val(), status: $bulk.find('[name="status"]').val() })
      .done(function (res) {
        App.modal('#bulk-modal').hide();
        App.flash(res.marked + ' marked' + (res.skipped_hourly ? ' · ' + res.skipped_hourly + ' hourly skipped (need clock times)' : '') + '.');
        load();
      })
      .fail(function (xhr) { App.formError($bulk, App.errorMessage(xhr, 'Could not mark the day.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  load();
});
