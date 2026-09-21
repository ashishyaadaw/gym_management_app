$(function () {
  'use strict';

  var esc = App.esc;
  var canWrite = !!(window.MembersPage && window.MembersPage.canWrite);

  var $list = $('#member-list');
  var $search = $('#member-search');
  var status = 'all';
  var members = {};   // id -> row, so buttons can look up the member they belong to
  var plans = [];
  var timer = null;

  // ---------- List ----------
  function row(m) {
    var sub = m.plan ? esc(m.plan) : 'No active plan';
    var ends = m.ends_on
      ? (m.status === 'expired' ? 'Expired ' : 'Ends ') + esc(App.day(m.ends_on)) + (m.days_left !== null ? ' · ' + esc(App.daysLeft(m.days_left)) : '')
      : '';

    return '<div class="gf-list-item d-flex flex-wrap align-items-center gap-2 gap-sm-3 py-3">' +
      '<span class="gf-avatar">' + esc(App.initials(m.name)) + '</span>' +
      '<div class="flex-grow-1" style="min-width:9rem">' +
        '<div class="fw-semibold gf-truncate">' + esc(m.name) + '</div>' +
        '<div class="small text-secondary">' + esc(m.phone || 'No phone') + '</div></div>' +
      '<div class="small d-none d-md-block" style="min-width:12rem"><div>' + sub + '</div><div class="text-secondary">' + (ends || '&nbsp;') + '</div></div>' +
      '<div class="d-flex flex-column align-items-start align-items-sm-end gap-1">' + App.statusPill(m.status) +
        (m.due > 0 ? '<span class="small" style="color:#fbbf24">Due ' + esc(App.money(m.due)) + '</span>' : '') + '</div>' +
      '<div class="d-flex gap-1 ms-auto">' +
        (canWrite ? '<button class="btn btn-light btn-sm js-checkin" data-id="' + esc(m.id) + '" title="Check in">' +
          '<svg class="gf-ico gf-ico--sm"><use href="#i-check"/></svg></button>' : '') +
        (canWrite ? '<button class="btn btn-light btn-sm js-renew" data-id="' + esc(m.id) + '" title="Renew membership">' +
          '<svg class="gf-ico gf-ico--sm"><use href="#i-refresh"/></svg><span class="gf-hide-xs"> Renew</span></button>' : '') +
        App.whatsappButton(m, null, false) +
      '</div></div>';
  }

  function load() {
    return App.get('/members', { q: $.trim($search.val()), status: status })
      .done(function (res) {
        members = {};
        $.each(res.data, function (_, m) { members[m.id] = m; });

        var c = res.counts;
        $('[data-count="all"]').text(c.total);
        $('[data-count="active"]').text(c.active);
        $('[data-count="expiring"]').text(c.expiring);
        $('[data-count="expired"]').text(c.expired);

        $list.html(res.data.length
          ? $.map(res.data, row).join('')
          : '<div class="gf-empty">No members match this view.</div>');
      })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load members.'), 'danger'); });
  }

  $search.on('input', function () { clearTimeout(timer); timer = setTimeout(load, 250); });

  $('#member-filters').on('click', 'button', function () {
    status = $(this).data('status');
    $('#member-filters button').removeClass('active');
    $(this).addClass('active');
    load();
  });

  $list.on('click', '.js-checkin', function () {
    var m = members[$(this).data('id')];
    App.post('/attendances/check-in', { user_id: m.id })
      .done(function () { App.flash(m.name + ' checked in at ' + App.time(new Date()) + '.'); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Check-in failed.'), 'danger'); });
  });

  if (!canWrite) { load(); return; }

  // ---------- Plans (shared by the add + renew modals) ----------
  function planById(id) {
    for (var i = 0; i < plans.length; i++) { if (String(plans[i].id) === String(id)) { return plans[i]; } }
    return null;
  }

  /**
   * Keep "balance due" and "expiry" in step with the chosen plan, coupon, paid amount and start date.
   * `memberId` (optional fn) tells the coupon check who it is for, so per-member coupon limits apply.
   */
  function wire($form, startDate, memberId) {
    var $plan = $form.find('[name="membership_plan_id"]');
    var $paid = $form.find('[name="paid"]');
    var $code = $form.find('[name="coupon_code"]');
    var $msg = $form.find('.js-coupon-msg');
    var discount = 0;
    var seq = 0;   // bumped whenever the coupon state changes, so a slow reply for an old code is ignored

    function netPrice(p) { return Math.max(0, p.price - discount); }

    function resetCoupon() { seq++; discount = 0; $msg.text(''); }

    function refresh() {
      var p = planById($plan.val());
      if (!p) { $form.find('.js-due').val('—'); $form.find('.js-expiry').text('—'); return; }
      $form.find('.js-due').val(App.money(Math.max(0, netPrice(p) - (Number($paid.val()) || 0))));

      if (p.duration_days) {
        var start = App.parse(startDate());
        start.setDate(start.getDate() + p.duration_days);
        $form.find('.js-expiry').text(App.day(start));
      } else {
        $form.find('.js-expiry').text('No expiry (credit pack)');
      }
    }

    $plan.on('change', function () {
      resetCoupon();
      var p = planById($plan.val());
      if (p) { $paid.val(Math.round(netPrice(p))); }
      refresh();
    });
    $paid.on('input', refresh);
    $form.find('[name="joining_date"]').on('change', refresh);

    // A coupon is checked against the server (plan, dates, limits) before it changes the price.
    $code.on('input', function () {
      var had = discount;
      resetCoupon();   // also discards any check still in flight for the old text
      if (had) { var p = planById($plan.val()); if (p) { $paid.val(Math.round(netPrice(p))); } refresh(); }
    });
    $form.find('.js-apply').on('click', function () {
      var code = $.trim($code.val());
      var p = planById($plan.val());
      if (!code || !p) { return; }
      var mine = ++seq;

      App.post('/coupons/check', { code: code, membership_plan_id: p.id, user_id: memberId ? memberId() : null })
        .done(function (r) {
          if (mine !== seq) { return; }
          discount = r.discount;
          $msg.css('color', 'var(--gf-cash)').text(r.code + ' applied: −' + App.money(r.discount) + ' → ' + App.money(r.final_price) + ' to pay.');
          $paid.val(Math.round(netPrice(p)));
          refresh();
        })
        .fail(function (xhr) {
          if (mine !== seq) { return; }
          resetCoupon();
          $msg.css('color', '#f87171').text(App.errorMessage(xhr, 'That coupon can\'t be used.'));
          refresh();
        });
    });

    $form.data('resetCoupon', function () { resetCoupon(); });
    return refresh;
  }

  function fillPlans() {
    var opts = $.map(plans, function (p) {
      return '<option value="' + esc(p.id) + '">' + esc(p.name) + ' — ' + esc(App.money(p.price)) +
        (p.remaining !== null && p.remaining !== undefined ? ' · ' + p.remaining + ' left' : '') + '</option>';
    }).join('');
    $('.js-plan-select').html(opts);
  }

  var $add = $('#add-form');
  wire($add, function () { return $add.find('[name="joining_date"]').val() || App.today(); });
  var $renew = $('#renew-form');
  var renewMember = null;
  wire($renew, function () {
    // A renewal starts when the current plan ends (or today if it already ended).
    var end = renewMember && renewMember.ends_on ? App.parse(renewMember.ends_on) : null;
    return end && end > new Date() ? end : new Date();
  }, function () { return renewMember ? renewMember.id : null; });

  var plansReady = App.get('/membership-plans').done(function (res) {
    plans = $.grep(res, function (p) { return p.sale_status === 'on_sale'; });
    fillPlans();
    $('.js-plan-select').val(plans.length ? plans[0].id : '').trigger('change');
  }).fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load plans.'), 'danger'); });

  // ---------- Add member ----------
  function openAdd() {
    $add[0].reset();
    App.formError($add, '');
    $add.find('[name="joining_date"]').val(App.today());
    $add.data('resetCoupon')();
    if (plans.length) { $add.find('[name="membership_plan_id"]').val(plans[0].id).trigger('change'); }
    App.modal('#add-modal').show();
  }
  $('#btn-add-member').on('click', openAdd);

  $add.on('submit', function (e) {
    e.preventDefault();
    var $btn = $add.find('button[type="submit"]').prop('disabled', true);
    App.formError($add, '');

    App.post('/members', {
      name: $.trim($add.find('[name="name"]').val()),
      phone: $.trim($add.find('[name="phone"]').val()),
      email: $.trim($add.find('[name="email"]').val()) || null,
      joining_date: $add.find('[name="joining_date"]').val() || null,
      membership_plan_id: App.num($add.find('[name="membership_plan_id"]').val()),
      paid: App.num($add.find('[name="paid"]').val()),
      method: $add.find('[name="method"]').val(),
      emergency_contact_phone: $.trim($add.find('[name="emergency_contact_phone"]').val()) || null,
      coupon_code: $.trim($add.find('[name="coupon_code"]').val()) || null
    })
      .done(function () {
        App.modal('#add-modal').hide();
        App.flash('Member added successfully.');
        load();
      })
      .fail(function (xhr) { App.formError($add, App.errorMessage(xhr, 'Could not add the member.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Renew ----------
  function openRenew(m) {
    renewMember = m;
    App.formError($renew, '');
    $renew.data('resetCoupon')();
    $renew.find('.js-renew-avatar').text(App.initials(m.name));
    $renew.find('.js-renew-name').text(m.name);
    $renew.find('.js-renew-current').text(m.ends_on ? (m.status === 'expired' ? 'Expired ' : 'Ends ') + App.day(m.ends_on) : 'No membership yet');
    if (plans.length) { $renew.find('[name="membership_plan_id"]').val(plans[0].id).trigger('change'); }
    App.modal('#renew-modal').show();
  }

  $list.on('click', '.js-renew', function () { openRenew(members[$(this).data('id')]); });

  $renew.on('submit', function (e) {
    e.preventDefault();
    var $btn = $renew.find('button[type="submit"]').prop('disabled', true);
    App.formError($renew, '');

    App.post('/members/' + renewMember.id + '/renew', {
      membership_plan_id: App.num($renew.find('[name="membership_plan_id"]').val()),
      paid: App.num($renew.find('[name="paid"]').val()),
      method: $renew.find('[name="method"]').val(),
      coupon_code: $.trim($renew.find('[name="coupon_code"]').val()) || null
    })
      .done(function () {
        App.modal('#renew-modal').hide();
        App.flash(renewMember.name + '’s membership renewed.');
        load();
      })
      .fail(function (xhr) { App.formError($renew, App.errorMessage(xhr, 'Could not renew.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Deep links from the dashboard: /members?add=1 and /members?renew=ID ----------
  var params = new URLSearchParams(window.location.search);
  load().always(function () {
    plansReady.always(function () {
      if (params.get('add')) { openAdd(); }
      var id = params.get('renew');
      if (id) {
        if (members[id]) { openRenew(members[id]); }
        else { App.get('/members').done(function (res) { $.each(res.data, function (_, m) { if (String(m.id) === id) { openRenew(m); } }); }); }
      }
    });
  });
});
