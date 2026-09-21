$(function () {
  'use strict';

  var esc = App.esc;
  var isStaff = App.role !== 'member';
  var $list = $('#attendance-list');

  // ---------- Rows ----------
  function row(a) {
    var user = a.user || {};
    var inside = !a.check_out_time;
    return '<div class="gf-list-item d-flex align-items-center gap-3 py-3">' +
      '<span class="gf-avatar">' + esc(App.initials(user.name)) + '</span>' +
      '<div class="flex-grow-1 gf-truncate">' +
        (isStaff
          ? '<div class="fw-semibold gf-truncate">' + esc(user.name) + '</div><div class="small text-secondary">' + esc(user.phone || '') + '</div>'
          : '<div class="fw-semibold">' + esc(App.day(a.check_in_time)) + '</div><div class="small text-secondary text-capitalize">' + esc(App.label(a.type)) + '</div>') +
      '</div>' +
      '<div class="small text-end"><div class="tabular">In ' + esc(App.time(a.check_in_time)) + '</div>' +
        '<div class="text-secondary tabular">' + (a.check_out_time ? 'Out ' + esc(App.time(a.check_out_time)) : '—') + '</div></div>' +
      '<div style="min-width:5.5rem" class="text-end">' +
        (inside && isStaff
          ? '<button class="btn btn-light btn-sm js-check-out" data-id="' + esc(a.id) + '">Check out</button>'
          : '<span class="gf-pill gf-pill--' + (inside ? 'active' : 'none') + '">' + (inside ? 'Present' : 'Left') + '</span>') +
      '</div></div>';
  }

  function render(records, emptyText) {
    $list.html(records.length ? $.map(records, row).join('') : '<div class="gf-empty">' + esc(emptyText) + '</div>');
  }

  // ---------- Member view: own history ----------
  if (!isStaff) {
    App.get('/attendances')
      .done(function (res) { render(res.data, 'No visits recorded yet.'); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load attendance.'), 'danger'); });
    return;
  }

  // ---------- Staff view: a day's board ----------
  var $date = $('#att-date');

  function renderPeak(hours) {
    var items = [], max = 0, peakHour = null;
    $.each(hours, function (h, n) { if (n > max) { max = n; peakHour = h; } });
    $.each(hours, function (h, n) {
      var hh = Number(h);
      items.push({ h: hh, n: n, label: (hh % 12 || 12) + (hh < 12 ? 'a' : 'p') });
    });

    $('#att-peak').html('<div class="gf-bars" style="height:6.5rem">' + $.map(items, function (i) {
      var pct = max ? Math.round(i.n / max * 100) : 0;
      return '<div class="gf-bar-col" title="' + esc(i.label + ': ' + i.n + ' check-ins') + '">' +
        '<span class="gf-bar-val">' + (i.n || '') + '</span>' +
        '<div class="gf-bar ' + (i.n ? (String(i.h) === String(peakHour) ? 'gf-bar--peak' : '') : 'gf-bar--dim') + '" style="height:' + (i.n ? Math.max(6, pct) : 3) + '%"></div>' +
        '<span class="gf-bar-lbl' + (i.h % 2 ? ' gf-hide-xs' : '') + '">' + i.label + '</span></div>';
    }).join('') + '</div>');
  }

  function load() {
    var day = $date.val() || App.today();
    return App.get('/attendances/day', { date: day })
      .done(function (res) {
        var isToday = res.date === App.today();
        $('#att-count').text(res.count);
        $('#att-inside').text(res.inside);
        $('#att-day-label').text(isToday ? 'Today' : App.day(res.date));
        renderPeak(res.peak_hours);
        render(res.data, isToday ? 'No check-ins yet today.' : 'No check-ins on this day.');
      })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load attendance.'), 'danger'); });
  }

  $date.on('change', load);

  // ---------- Check in ----------
  var $form = $('#checkin-form');
  var $picker = $form.find('[data-picker]');
  var $btn = $('#btn-checkin');

  $picker.on('picked', function () { $btn.prop('disabled', false); });
  $picker.on('cleared', function () { $btn.prop('disabled', true); });

  $form.on('submit', function (e) {
    e.preventDefault();
    var id = App.pickerValue($picker);
    if (!id) { return; }
    App.formError($form, '');
    $btn.prop('disabled', true);

    App.post('/attendances/check-in', { user_id: id })
      .done(function (a) {
        App.flash((a.user ? a.user.name : 'Member') + ' checked in at ' + App.time(a.check_in_time) + '.');
        App.pickerReset($picker);
        $date.val(App.today());
        load();
      })
      .fail(function (xhr) {
        App.formError($form, App.errorMessage(xhr, 'Check-in failed.'));
        $btn.prop('disabled', false);
      });
  });

  // ---------- Check out ----------
  $list.on('click', '.js-check-out', function () {
    App.post('/attendances/' + $(this).data('id') + '/check-out')
      .done(function () { App.flash('Checked out.'); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Check-out failed.'), 'danger'); });
  });

  load();
});
