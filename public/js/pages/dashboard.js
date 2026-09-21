$(function () {
  'use strict';

  var esc = App.esc;
  var money = App.money;
  var isStaff = App.role === 'admin' || App.role === 'receptionist';

  // ---------- Small building blocks ----------
  function icon(name, extra) {
    return '<svg class="gf-ico ' + (extra || '') + '"><use href="#i-' + name + '"/></svg>';
  }

  function kpi(cls, label, value, sub, ico) {
    return '<div class="gf-kpi gf-kpi--' + cls + '">' +
      '<div class="gf-kpi-label"><span>' + esc(label) + '</span>' + (ico ? icon(ico, 'gf-ico--sm') : '') + '</div>' +
      '<div class="gf-kpi-value">' + esc(value) + '</div>' +
      (sub ? '<div class="gf-kpi-sub">' + esc(sub) + '</div>' : '') +
      '</div>';
  }

  function col(cls, html) { return '<div class="' + cls + '">' + html + '</div>'; }

  var bars = App.bars;
  var short = App.short;

  function txnTable(rows, limit) {
    if (!rows.length) { return '<div class="gf-empty">No transactions yet.</div>'; }
    var shown = limit ? rows.slice(0, limit) : rows;
    return '<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr>' +
      '<th>Time</th><th>Member / Item</th><th class="d-none d-sm-table-cell">Type</th><th>Mode</th><th class="text-end">Amount</th></tr></thead><tbody>' +
      $.map(shown, function (t) {
        return '<tr><td class="text-secondary tabular">' + esc(App.time(t.time)) + '</td>' +
          '<td><div class="fw-medium">' + esc(t.label) + '</div><div class="text-secondary" style="font-size:.72rem">' + esc(t.detail) + '</div></td>' +
          '<td class="d-none d-sm-table-cell"><span class="gf-pill gf-pill--' + esc(t.type) + '">' + esc(t.type) + '</span></td>' +
          '<td>' + App.methodPill(t.method) + '</td>' +
          '<td class="text-end fw-bold tabular">' + esc(money(t.amount)) + '</td></tr>';
      }).join('') + '</tbody></table></div>' +
      (limit && rows.length > limit ? '<div class="text-center text-secondary small pt-2">Showing latest ' + limit + ' of ' + rows.length + '</div>' : '');
  }

  // ---------- Staff: daily collection ----------
  function renderCollection(c) {
    var t = c.totals, ts = c.time_split;
    var bank = t.upi + t.card + t.other;
    var pretty = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

    function slot(ico, tint, label, hours, amount) {
      return col('col-4', '<div class="gf-card-inset p-2 p-sm-3 text-center h-100">' +
        '<div class="gf-avatar mx-auto" style="color:' + tint + '">' + icon(ico) + '</div>' +
        '<div class="gf-eyebrow mt-2" style="letter-spacing:.06em;font-size:.62rem">' + label + '</div>' +
        '<div class="text-secondary" style="font-size:.62rem">' + hours + '</div>' +
        '<div class="fw-bold mt-1 tabular" style="font-size:.9rem;white-space:nowrap">' + esc(money(amount)) + '</div></div>');
    }

    function settle(label, cls, ico, amount) {
      return '<div class="d-flex justify-content-between align-items-center gf-card-inset p-2 px-3 mb-2">' +
        '<span class="d-inline-flex align-items-center gap-2 small">' + icon(ico, 'gf-ico--sm') + label + '</span>' +
        '<span class="fw-bold tabular" style="color:var(--gf-' + cls + ')">' + esc(money(amount)) + '</span></div>';
    }

    return '<div class="card gf-hero mb-4"><div class="card-body p-3 p-lg-4">' +
      '<div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">' +
        '<div class="d-flex align-items-center gap-3"><span class="gf-hero-icon">' + icon('cash') + '</span>' +
          '<div><h2 class="h5 mb-0">Daily Collection</h2>' +
          '<div class="gf-eyebrow">' + esc(pretty) + ' &bull; ' + t.count + ' transaction' + (t.count === 1 ? '' : 's') + ' &bull; Membership + Store</div></div></div>' +
        '<div class="d-flex align-items-center gap-2"><span class="gf-live">LIVE</span>' +
          '<span class="badge text-bg-primary rounded-pill px-3 py-2 tabular">' + esc(money(t.total)) + ' TODAY</span></div>' +
      '</div>' +

      '<div class="row g-2 g-lg-3">' +
        col('col-6 col-lg-3', kpi('total', 'Today total', money(t.total), t.count + ' txn' + (t.other > 0 ? ' · incl. ' + money(t.other) + ' other' : ''), 'cash')) +
        col('col-6 col-lg-3', kpi('cash', 'Cash', money(t.cash), 'Cash in hand', 'cash')) +
        col('col-6 col-lg-3', kpi('upi', 'UPI', money(t.upi), 'UPI settlement', 'phone')) +
        col('col-6 col-lg-3', kpi('card', 'Card', money(t.card), 'Card swipe', 'card')) +
      '</div>' +

      '<div class="row g-3 mt-1">' +
        col('col-lg-8', '<div class="gf-card-inset p-3 h-100"><div class="gf-eyebrow mb-3">Time split</div><div class="row g-2">' +
          slot('sun', '#fb923c', 'Morning', '5 AM – 12 PM', ts.morning) +
          slot('sunset', '#fbbf24', 'Afternoon', '12 PM – 5 PM', ts.afternoon) +
          slot('moon', '#818cf8', 'Evening', '5 PM – 5 AM', ts.evening) +
        '</div></div>') +
        col('col-lg-4', '<div class="gf-card-inset p-3 h-100"><div class="gf-eyebrow mb-3">Cash in hand vs bank</div>' +
          settle('Cash in hand', 'cash', 'cash', t.cash) +
          settle('UPI', 'upi', 'phone', t.upi) +
          settle('Card', 'card', 'card', t.card) +
          '<div class="d-flex justify-content-between small pt-2 border-top" style="border-color:var(--gf-line-soft)!important">' +
            '<span class="text-secondary">Total bank settlements</span><span class="fw-bold tabular">' + esc(money(bank)) + '</span></div></div>') +
      '</div>' +

      '<div class="gf-card-inset p-3 mt-3"><div class="gf-eyebrow mb-2">Today’s transactions</div>' + txnTable(c.transactions, 8) + '</div>' +
    '</div></div>';
  }

  // ---------- Staff: member status + attention lists ----------
  function renderMemberKpis(s) {
    var m = s.members;
    return '<div class="row g-2 g-lg-3 mb-3">' +
      col('col-6 col-lg-3', kpi('', 'Total members', m.total, 'All time', 'users')) +
      col('col-6 col-lg-3', kpi('good', 'Active', m.active, 'Membership running')) +
      col('col-6 col-lg-3', kpi('warn', 'Expiring soon', m.expiring, 'Within ' + App.expiringDays + ' days', 'alert')) +
      col('col-6 col-lg-3', kpi('bad', 'Expired', m.expired, 'Need renewal')) +
    '</div><div class="row g-2 g-lg-3 mb-4">' +
      col('col-6 col-lg-4', kpi('', 'Check-ins today', s.checkins_today, 'Members through the door', 'check')) +
      col('col-6 col-lg-4', kpi('', 'Revenue this month', money(s.month_revenue), 'Total collected', 'cash')) +
      col('col-12 col-lg-4', kpi(s.pending_dues > 0 ? 'warn' : 'good', 'Pending dues', money(s.pending_dues), 'To collect')) +
    '</div>';
  }

  function memberRow(m, kind) {
    var meta;
    if (kind === 'inactive') {
      meta = m.last_visit ? 'Last visit ' + App.day(m.last_visit) : 'Never checked in';
    } else {
      meta = (m.ends_on ? App.day(m.ends_on) + ' · ' : '') + App.daysLeft(m.days_left);
    }
    return '<div class="gf-list-item d-flex align-items-center gap-2 py-2">' +
      '<span class="gf-avatar">' + esc(App.initials(m.name)) + '</span>' +
      '<div class="flex-grow-1 gf-truncate"><div class="fw-medium gf-truncate">' + esc(m.name) + '</div>' +
      '<div class="small text-secondary gf-truncate">' + esc(meta) + (m.due > 0 ? ' · due ' + esc(money(m.due)) : '') + '</div></div>' +
      App.whatsappButton(m, kind, false) +
      (kind !== 'inactive'
        ? '<a class="btn btn-light btn-sm" href="' + esc(App.url('/members?renew=' + m.id)) + '" title="Renew membership">' + icon('refresh', 'gf-ico--sm') + '</a>'
        : '') +
      '</div>';
  }

  function attentionCard(title, ico, tone, rows, kind, empty) {
    return col('col-lg-4', '<div class="card h-100"><div class="card-body">' +
      '<div class="d-flex align-items-center justify-content-between mb-2">' +
        '<h3 class="h6 mb-0 d-flex align-items-center gap-2" style="color:' + tone + '">' + icon(ico, 'gf-ico--sm') + esc(title) + '</h3>' +
        '<span class="badge rounded-pill text-bg-secondary">' + rows.length + '</span></div>' +
      (rows.length ? $.map(rows, function (m) { return memberRow(m, kind); }).join('') : '<div class="gf-empty">' + esc(empty) + '</div>') +
    '</div></div>');
  }

  /** Owner only: who is in today. */
  function staffTodayCard(t) {
    if (!t || !t.total) { return ''; }
    function chip(cls, label, n) { return n ? '<span class="gf-pill gf-pill--' + cls + '">' + n + ' ' + label + '</span>' : ''; }
    return '<div class="card mb-4"><div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3">' +
      '<h3 class="h6 mb-0 d-flex align-items-center gap-2" style="color:var(--gf-gold)">' + icon('briefcase', 'gf-ico--sm') + 'Staff today</h3>' +
      '<div class="d-flex flex-wrap gap-2 flex-grow-1">' +
        chip('active', 'in', t.present) + chip('expiring', 'not in yet', t.pending) + chip('expired', 'absent', t.absent) +
        chip('none', 'on leave / holiday', t.leave) + chip('none', 'weekly off', t.weekly_off) + chip('expired', 'late', t.late) + '</div>' +
      '<a class="btn btn-light btn-sm" href="' + esc(App.url('/staff/attendance')) + '">Staff attendance</a></div></div>';
  }

  function lowStockCard(rows) {
    if (!rows.length) { return ''; }
    return '<div class="card mb-4" style="border-color:rgba(248,113,113,.3)"><div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3">' +
      '<h3 class="h6 mb-0 d-flex align-items-center gap-2" style="color:#f87171">' + icon('box', 'gf-ico--sm') + 'Low stock</h3>' +
      '<div class="d-flex flex-wrap gap-2 flex-grow-1">' + $.map(rows, function (p) {
        return '<span class="gf-pill gf-pill--' + (p.stock <= 0 ? 'low' : 'expiring') + '">' + esc(p.name) + ' · ' + (p.stock <= 0 ? 'out of stock' : p.stock + ' left') + '</span>';
      }).join('') + '</div>' +
      '<a class="btn btn-light btn-sm" href="' + esc(App.url('/store')) + '">Open store</a></div></div>';
  }

  function renderAttention(s) {
    return lowStockCard(s.low_stock) + '<div class="row g-3 mb-4">' +
      attentionCard('Expiring soon', 'alert', '#fbbf24', s.expiring_members, 'expiring', 'All good — nobody is about to expire.') +
      attentionCard('Expired · need renewal', 'refresh', '#f87171', s.expired_members, 'expired', 'No lapsed memberships.') +
      attentionCard('Inactive ' + App.inactiveDays + '+ days', 'users', '#a1a1aa', s.inactive_members, 'inactive', 'Everyone has visited recently.') +
    '</div>';
  }

  // ---------- Staff: last 7 days ----------
  function renderWeek(days) {
    var today = App.today();
    return '<div class="card mb-4"><div class="card-body">' +
      '<div class="d-flex align-items-center justify-content-between mb-2"><h3 class="h6 mb-0">Last 7 days</h3>' +
      '<span class="gf-eyebrow">Click View for day details</span></div>' +
      '<div class="mb-3">' + bars(days, 'total', 'label', short) + '</div>' +
      '<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr>' +
        '<th>Date</th><th class="text-end">Total</th><th class="text-end d-none d-md-table-cell">Cash</th><th class="text-end d-none d-md-table-cell">UPI</th>' +
        '<th class="text-end d-none d-md-table-cell">Card</th><th class="text-end">Txn</th><th></th></tr></thead><tbody>' +
      $.map(days.slice().reverse(), function (d) {
        var isToday = d.date === today;
        return '<tr' + (isToday ? ' style="background:rgba(212,175,55,.06)"' : '') + '><td class="fw-medium">' + esc(d.label) +
          (isToday ? ' <span class="badge text-bg-primary ms-1">Today</span>' : '') + '</td>' +
          '<td class="text-end fw-bold tabular">' + esc(money(d.total)) + '</td>' +
          '<td class="text-end d-none d-md-table-cell tabular" style="color:var(--gf-cash)">' + esc(money(d.cash)) + '</td>' +
          '<td class="text-end d-none d-md-table-cell tabular" style="color:var(--gf-upi)">' + esc(money(d.upi)) + '</td>' +
          '<td class="text-end d-none d-md-table-cell tabular" style="color:var(--gf-card)">' + esc(money(d.card)) + '</td>' +
          '<td class="text-end tabular">' + d.count + '</td>' +
          '<td class="text-end"><button class="btn btn-light btn-sm js-day" data-date="' + esc(d.date) + '">View</button></td></tr>';
      }).join('') + '</tbody></table></div>' +
      '<div id="day-detail" class="mt-3"></div>' +
    '</div></div>';
  }

  $(document).on('click', '.js-day', function () {
    var $box = $('#day-detail').html('<div class="gf-empty">Loading…</div>');
    App.get('/collection', { date: $(this).data('date') })
      .done(function (c) {
        var t = c.totals;
        $box.html('<div class="gf-card-inset p-3"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2">' +
          '<div class="fw-semibold">' + esc(App.day(c.date)) + '</div>' +
          '<div class="small d-flex flex-wrap gap-3"><span>Total <b class="tabular">' + esc(money(t.total)) + '</b></span>' +
          '<span style="color:var(--gf-cash)">Cash ' + esc(money(t.cash)) + '</span><span style="color:var(--gf-upi)">UPI ' + esc(money(t.upi)) + '</span>' +
          '<span style="color:var(--gf-card)">Card ' + esc(money(t.card)) + '</span></div></div>' +
          txnTable(c.transactions) + '</div>');
      })
      .fail(function (xhr) { $box.html(''); App.flash(App.errorMessage(xhr, 'Could not load that day.'), 'danger'); });
  });

  function renderStaff(s) {
    $('#dashboard-staff').html(
      renderCollection(s.collection) + renderMemberKpis(s) + staffTodayCard(s.staff_today) + renderAttention(s) + renderWeek(s.last_7_days)
    );
  }

  // ---------- Admin: business overview ----------
  function renderAdmin(s) {
    var split = s.method_split;
    var total = Math.max(split.total, 1);
    var pct = function (v) { return Math.round(v / total * 100); };

    var stats = [
      ['', 'Active plans', s.active_members],
      ['', 'Trainers', s.total_trainers],
      ['', 'Revenue (30d)', money(s.revenue_period)],
      [s.pending_invoices ? 'warn' : '', 'Pending invoices', s.pending_invoices],
      ['', 'Classes this week', s.classes_this_week],
      ['', 'Bookings (30d)', s.bookings_period],
      ['', 'Check-ins (30d)', s.checkins_period],
      [s.equipment_needing_maintenance ? 'bad' : '', 'Equipment needing service', s.equipment_needing_maintenance]
    ];

    $('#dashboard-admin').html(
      '<div class="row g-2 g-lg-3 mb-3">' + $.map(stats, function (x) { return col('col-6 col-lg-3', kpi(x[0], x[1], x[2])); }).join('') + '</div>' +
      '<div class="row g-3">' +
        col('col-lg-6', '<div class="card h-100"><div class="card-body"><h3 class="h6 mb-3">Monthly revenue <span class="text-secondary fw-normal">· 6 months</span></h3>' +
          bars(s.revenue_by_month, 'revenue', 'label', short) + '</div></div>') +
        col('col-lg-6', '<div class="card h-100"><div class="card-body"><h3 class="h6 mb-3">New members <span class="text-secondary fw-normal">· 6 months</span></h3>' +
          bars(s.revenue_by_month, 'new_members', 'label', String) + '</div></div>') +
        col('col-12', '<div class="card"><div class="card-body"><h3 class="h6 mb-3">Cash vs UPI vs Card <span class="text-secondary fw-normal">· 6 months</span></h3>' +
          '<div class="gf-split mb-3">' +
            '<span style="width:' + pct(split.cash) + '%;background:var(--gf-cash)"></span>' +
            '<span style="width:' + pct(split.upi) + '%;background:var(--gf-upi)"></span>' +
            '<span style="width:' + pct(split.card) + '%;background:var(--gf-card)"></span>' +
            '<span style="width:' + pct(split.other) + '%;background:#71717a"></span></div>' +
          '<div class="row g-2">' +
            col('col-6 col-lg-3', kpi('cash', 'Cash', money(split.cash), pct(split.cash) + '%')) +
            col('col-6 col-lg-3', kpi('upi', 'UPI', money(split.upi), pct(split.upi) + '%')) +
            col('col-6 col-lg-3', kpi('card', 'Card', money(split.card), pct(split.card) + '%')) +
            col('col-6 col-lg-3', kpi('total', 'Total revenue', money(split.total), split.count + ' transactions')) +
          '</div></div></div>') +
      '</div>');
  }

  // ---------- Trainer / member ----------
  function renderTrainer(s) {
    var $box = $('#dashboard-trainer');
    if (!s.upcoming_classes.length) {
      $box.html('<div class="gf-empty">No upcoming classes scheduled.</div>');
      return;
    }
    $box.html($.map(s.upcoming_classes, function (c) {
      return '<div class="gf-list-item d-flex justify-content-between align-items-center py-2">' +
        '<div><div class="fw-medium">' + esc(c.gym_class.name) + '</div>' +
        '<div class="small text-secondary">' + esc(App.date(c.start_time)) + '</div></div>' +
        '<span class="badge text-bg-primary">' + esc(c.confirmed_bookings_count) + ' booked</span></div>';
    }).join(''));
  }

  function renderMember(s) {
    var $plan = $('#dashboard-member-plan');
    if (s.active_plan) {
      var credits = s.active_plan.remaining_credits !== null
        ? ' · ' + esc(s.active_plan.remaining_credits) + ' credits left' : '';
      var ends = s.active_plan.end_date ? '<div class="small text-secondary mt-1">Valid till ' + esc(App.day(s.active_plan.end_date)) + '</div>' : '';
      $plan.html('<div class="fs-4 fw-bold" style="color:var(--gf-gold)">' + esc(s.active_plan.plan.name) + '</div>' +
        '<div class="small text-secondary">Status: <span class="text-capitalize">' + esc(s.active_plan.status) + '</span>' + credits + '</div>' + ends +
        '<div class="small mt-3">' + esc(s.attendance_count_30d) + ' check-ins in the last 30 days</div>');
    } else {
      $plan.html('<div class="small text-secondary">No active plan. ' +
        '<a href="' + esc(App.url('/plans')) + '" class="fw-semibold text-decoration-none">Browse plans</a></div>');
    }

    var $bookings = $('#dashboard-member-bookings');
    if (!s.upcoming_bookings.length) {
      $bookings.html('<div class="gf-empty">No upcoming class bookings.</div>');
      return;
    }
    $bookings.html($.map(s.upcoming_bookings, function (b) {
      return '<div class="gf-list-item d-flex justify-content-between align-items-center py-2">' +
        '<div><div class="fw-medium">' + esc(b.class_schedule.gym_class.name) + '</div>' +
        '<div class="small text-secondary">' + esc(App.date(b.class_schedule.start_time)) + '</div></div>' +
        App.badge(b.status, b.status === 'confirmed' ? 'success' : 'warning') + '</div>';
    }).join(''));
  }

  // ---------- Load ----------
  function fail(xhr) { App.flash(App.errorMessage(xhr, 'Could not load the dashboard.'), 'danger'); }

  if (isStaff) {
    App.get('/dashboard/staff').done(renderStaff).fail(fail);
    if (App.role === 'admin') { App.get('/dashboard/admin').done(renderAdmin).fail(fail); }
  } else if (App.role === 'trainer') {
    App.get('/dashboard/trainer').done(renderTrainer).fail(fail);
  } else if (App.role === 'member') {
    App.get('/dashboard/member').done(renderMember).fail(fail);
  }
});
