$(function () {
  'use strict';

  var esc = App.esc;
  var money = App.money;

  var $list = $('#staff-list');
  var staff = [];
  var filter = 'all';
  var editing = null;

  var TODAY = {
    present: ['active', 'Present'], half_day: ['expiring', 'Half day'], absent: ['expired', 'Absent'],
    leave: ['none', 'On leave'], holiday: ['none', 'Holiday'], weekly_off: ['none', 'Weekly off'],
    pending: ['expiring', 'Not in yet'], unmarked: ['expiring', 'Not in yet'], future: ['none', '—'], outside: ['none', 'Not employed']
  };

  function byId(id) {
    for (var i = 0; i < staff.length; i++) { if (String(staff[i].id) === String(id)) { return staff[i]; } }
    return null;
  }

  function payText(p) {
    return p.pay_type === 'hourly' ? money(p.base_salary) + ' / hour' : money(p.base_salary) + ' / month';
  }

  // ---------- List ----------
  function row(u) {
    var p = u.profile;
    var todayPill = '';
    if (p && u.is_active && u.today) {
      var t = TODAY[u.today.state] || ['none', u.today.state];
      todayPill = '<span class="gf-pill gf-pill--' + t[0] + '">' + esc(t[1]) + (u.today.in ? ' · ' + esc(u.today.in) : '') + '</span>' +
        (u.today.late ? ' <span class="gf-pill gf-pill--expired">Late</span>' : '');
    }

    return '<div class="gf-list-item d-flex flex-wrap align-items-center gap-2 gap-md-3 py-3' + (u.is_active ? '' : ' opacity-50') + '">' +
      '<span class="gf-avatar">' + esc(App.initials(u.name)) + '</span>' +
      '<div class="flex-grow-1" style="min-width:11rem">' +
        '<div class="fw-semibold gf-truncate">' + esc(u.name) + (p ? ' <span class="small text-secondary fw-normal">' + esc(p.employee_code) + '</span>' : '') + '</div>' +
        '<div class="small text-secondary gf-truncate"><span class="text-capitalize">' + esc(u.role) + '</span>' + (p && p.designation ? ' · ' + esc(p.designation) : '') + ' · ' + esc(u.email) + '</div></div>' +
      (p
        ? '<div class="small d-none d-lg-block" style="min-width:12rem"><div class="fw-medium tabular">' + esc(payText(p)) + '</div>' +
            '<div class="text-secondary">' + (p.shift_start ? esc(p.shift_start + '–' + (p.shift_end || '?')) + ' · ' : '') + 'off ' + esc(App.WEEKDAYS[p.weekly_off].slice(0, 3)) + '</div></div>'
        : '<div class="small text-secondary d-none d-lg-block" style="min-width:12rem">No employment profile yet</div>') +
      '<div class="d-flex flex-column align-items-start gap-1" style="min-width:8rem">' +
        (u.is_active ? todayPill : '<span class="gf-pill gf-pill--none">Left' + (p && p.leaving_date ? ' ' + esc(App.day(p.leaving_date)) : '') + '</span>') +
      '</div>' +
      '<div class="ms-auto"><button class="btn btn-light btn-sm js-edit" data-id="' + esc(u.id) + '">' + (p ? 'Edit' : 'Set up profile') + '</button></div>' +
    '</div>';
  }

  function visible() {
    var q = $.trim($('#staff-search').val()).toLowerCase();
    return $.grep(staff, function (u) {
      var text = (u.name + ' ' + u.role + ' ' + (u.profile ? u.profile.employee_code + ' ' + (u.profile.designation || '') : '')).toLowerCase();
      if (q && text.indexOf(q) === -1) { return false; }
      if (filter === 'active') { return u.is_active; }
      if (filter === 'inactive') { return !u.is_active; }
      if (filter === 'noprofile') { return !u.profile; }
      return true;
    });
  }

  function render() {
    var rows = visible();
    $list.html(rows.length ? $.map(rows, row).join('') : '<div class="gf-empty">No staff match.</div>');

    var working = $.grep(staff, function (u) { return u.is_active && u.profile; });
    var present = $.grep(working, function (u) { return u.today && (u.today.state === 'present' || u.today.state === 'half_day'); }).length;
    var noProfile = $.grep(staff, function (u) { return !u.profile; }).length;
    var payroll = 0;
    $.each(working, function (_, u) { if (u.profile.pay_type === 'monthly') { payroll += u.profile.base_salary; } });

    function kpi(cls, label, value, sub) {
      return '<div class="col-6 col-lg-3"><div class="gf-kpi gf-kpi--' + cls + '"><div class="gf-kpi-label"><span>' + label + '</span></div>' +
        '<div class="gf-kpi-value">' + esc(value) + '</div><div class="gf-kpi-sub">' + esc(sub) + '</div></div></div>';
    }
    $('#staff-kpis').html(
      kpi('', 'Working', working.length, staff.length + ' accounts in total') +
      kpi('good', 'In today', present, 'Clocked in or marked present') +
      kpi(noProfile ? 'warn' : '', 'No profile', noProfile, noProfile ? 'Set one up to include them in payroll' : 'Everyone is set up') +
      kpi('total', 'Monthly salaries', money(payroll), 'Fixed-pay staff, before deductions')
    );
  }

  function load() {
    return App.get('/staff').done(function (res) { staff = res.data; render(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load staff.'), 'danger'); });
  }

  $('#staff-search').on('input', render);
  $('#staff-filters').on('click', 'button', function () {
    filter = $(this).data('filter');
    $('#staff-filters button').removeClass('active');
    $(this).addClass('active');
    render();
  });

  // ---------- Form ----------
  var $form = $('#staff-form');
  var f = function (name) { return $form.find('[name="' + name + '"]'); };

  function syncPayLabel() {
    $form.find('.js-salary-label').text((f('pay_type').val() === 'hourly' ? 'Hourly rate' : 'Monthly salary') + ' (' + (App.currency || '₹') + ') *');
  }
  f('pay_type').on('change', syncPayLabel);

  function open(u) {
    editing = u;
    $form[0].reset();
    App.formError($form, '');
    var p = u ? u.profile : null;

    $('#staff-modal-title').text(u ? (p ? 'Edit staff' : 'Set up staff profile') : 'Add staff');
    f('email').prop('readonly', false);
    $form.find('.js-pw-label').text(u ? 'New password' : 'Password *');
    $form.find('.js-pw-hint').toggleClass('d-none', !u);
    $form.find('.js-active-wrap').toggleClass('d-none', !u);

    if (u) {
      f('name').val(u.name); f('email').val(u.email); f('phone').val(u.phone || ''); f('role').val(u.role);
      $form.find('[name="is_active"]').prop('checked', u.is_active);
    } else {
      f('role').val('trainer');
    }
    f('joining_date').val(p ? p.joining_date : App.today());
    f('leaving_date').val(p && p.leaving_date ? p.leaving_date : '');
    f('employee_code').val(p ? p.employee_code : '');
    f('designation').val(p ? p.designation || '' : '');
    f('pay_type').val(p ? p.pay_type : 'monthly');
    f('base_salary').val(p ? p.base_salary : '');
    f('shift_start').val(p && p.shift_start ? p.shift_start : '');
    f('shift_end').val(p && p.shift_end ? p.shift_end : '');
    f('weekly_off').val(p ? p.weekly_off : 0);
    f('notes').val(p ? p.notes || '' : '');
    syncPayLabel();
    App.modal('#staff-modal').show();
  }

  $('#btn-add-staff').on('click', function () { open(null); });
  $list.on('click', '.js-edit', function () { open(byId($(this).data('id'))); });

  $form.on('submit', function (e) {
    e.preventDefault();
    App.formError($form, '');
    var $btn = $form.find('button[type="submit"]').prop('disabled', true);

    var payload = {
      name: $.trim(f('name').val()),
      email: $.trim(f('email').val()),
      phone: $.trim(f('phone').val()) || null,
      role: f('role').val(),
      employee_code: $.trim(f('employee_code').val()) || null,
      designation: $.trim(f('designation').val()) || null,
      joining_date: f('joining_date').val() || null,
      leaving_date: f('leaving_date').val() || null,
      pay_type: f('pay_type').val(),
      base_salary: App.num(f('base_salary').val()),
      shift_start: f('shift_start').val() || null,
      shift_end: f('shift_end').val() || null,
      weekly_off: App.num(f('weekly_off').val()),
      notes: $.trim(f('notes').val()) || null
    };
    if (f('password').val()) { payload.password = f('password').val(); }
    if (editing) { payload.is_active = $form.find('[name="is_active"]').is(':checked'); }

    var req = editing ? App.api('PUT', '/staff/' + editing.id, payload) : App.post('/staff', payload);
    req.done(function () {
      App.modal('#staff-modal').hide();
      App.flash(editing ? 'Staff details saved.' : 'Staff member added.');
      load();
    })
      .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not save.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  load();
});
