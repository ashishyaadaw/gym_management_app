$(function () {
  'use strict';

  var esc = App.esc;
  var canWrite = !!(window.MembersPage && window.MembersPage.canWrite);
  var canDeactivate = !!(window.MembersPage && window.MembersPage.canDeactivate);

  var $list = $('#member-list');
  var $search = $('#member-search');
  var status = 'all';
  var members = {};   // id -> row, so buttons can look up the member they belong to
  var plans = [];
  var timer = null;

  // ---------- List ----------
  function row(m) {
    var off = m.status === 'deactivated';
    var sub = m.plan ? esc(m.plan) : 'No active plan';
    var ends = m.ends_on
      ? (m.days_left !== null && m.days_left < 0 ? 'Expired ' : 'Ends ') + esc(App.day(m.ends_on)) + (m.days_left !== null ? ' · ' + esc(App.daysLeft(m.days_left)) : '')
      : '';

    return '<div class="gf-list-item d-flex flex-wrap align-items-center gap-2 gap-sm-3 py-3' + (off ? ' opacity-75' : '') + '">' +
      '<span class="gf-avatar">' + esc(App.initials(m.name)) + '</span>' +
      '<div class="flex-grow-1" style="min-width:9rem">' +
        '<div class="fw-semibold gf-truncate js-view" data-id="' + esc(m.id) + '" role="button" style="cursor:pointer">' + esc(m.name) + '</div>' +
        '<div class="small text-secondary">' + esc(m.phone || 'No phone') + '</div></div>' +
      '<div class="small d-none d-md-block" style="min-width:12rem"><div>' + sub + '</div><div class="text-secondary">' + (ends || '&nbsp;') + '</div></div>' +
      '<div class="d-flex flex-column align-items-start align-items-sm-end gap-1">' + App.statusPill(m.status) +
        (m.due > 0 ? '<span class="small" style="color:var(--gf-warn)">Due ' + esc(App.money(m.due)) + '</span>' : '') + '</div>' +
      '<div class="d-flex gap-1 ms-auto">' +
        '<button class="btn btn-light btn-sm js-view" data-id="' + esc(m.id) + '" title="View details">' +
          '<svg class="gf-ico gf-ico--sm"><use href="#i-eye"/></svg></button>' +
        (canWrite && !off ? '<button class="btn btn-light btn-sm js-checkin" data-id="' + esc(m.id) + '" title="Check in">' +
          '<svg class="gf-ico gf-ico--sm"><use href="#i-check"/></svg></button>' : '') +
        (canWrite && !off ? '<button class="btn btn-light btn-sm js-renew" data-id="' + esc(m.id) + '" title="Renew membership">' +
          '<svg class="gf-ico gf-ico--sm"><use href="#i-refresh"/></svg><span class="gf-hide-xs"> Renew</span></button>' : '') +
        (off ? '' : App.whatsappButton(m, null, false)) +
        (canDeactivate ? (off
          ? '<button class="btn btn-light btn-sm js-activate" data-id="' + esc(m.id) + '" title="Activate account">' +
            '<svg class="gf-ico gf-ico--sm"><use href="#i-login"/></svg><span class="gf-hide-xs"> Activate</span></button>'
          : '<button class="btn btn-light btn-sm js-deactivate" data-id="' + esc(m.id) + '" title="Deactivate account">' +
            '<svg class="gf-ico gf-ico--sm"><use href="#i-ban"/></svg></button>') : '') +
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
        $('[data-count="deactivated"]').text(c.deactivated);

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

  // ---------- Deactivate / activate (admin only) ----------
  $list.on('click', '.js-deactivate', function () {
    var m = members[$(this).data('id')];
    if (!window.confirm('Deactivate ' + m.name + '? They will be signed out and cannot log in, check in or renew until you activate them again. Their history is kept.')) { return; }
    App.post('/members/' + m.id + '/deactivate')
      .done(function () { App.flash(m.name + ' deactivated.'); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not deactivate the member.'), 'danger'); });
  });

  $list.on('click', '.js-activate', function () {
    var m = members[$(this).data('id')];
    App.post('/members/' + m.id + '/activate')
      .done(function () { App.flash(m.name + ' activated.'); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not activate the member.'), 'danger'); });
  });

  // ---------- Member details (view) ----------
  var labels = (window.MembersPage && window.MembersPage.labels) || {};
  var viewing = null;   // the details currently shown, so "Edit details" can start from them
  var viewSeq = 0;      // bumped per open, so a slow reply for a member the user has already left is ignored

  /** One "label … value" line; blank values show a dash. Pass html = true for a value that is already escaped markup. */
  function fact(label, value, html) {
    var empty = value === null || value === undefined || value === '';
    return '<div class="d-flex justify-content-between gap-3 py-1"><span class="text-secondary">' + esc(label) + '</span>' +
      '<span class="text-end' + (empty ? ' text-secondary' : '') + '" style="min-width:0;overflow-wrap:anywhere">' +
      (empty ? '—' : (html ? value : esc(value))) + '</span></div>';
  }

  function card(title, body) {
    return '<div class="col-md-6"><div class="gf-card-inset p-3 h-100 small"><div class="gf-eyebrow mb-2">' + esc(title) + '</div>' + body + '</div></div>';
  }

  function tel(phone) {
    return phone ? '<a class="text-reset" href="tel:' + esc(phone) + '">' + esc(phone) + '</a>' : '';
  }

  function renderDetails(d, m) {
    var bmi = d.bmi ? d.bmi + ' · ' + d.bmi_category.charAt(0).toUpperCase() + d.bmi_category.slice(1) : null;
    var ends = m && m.ends_on ? App.day(m.ends_on) + (m.days_left !== null ? ' · ' + App.daysLeft(m.days_left) : '') : null;
    var lastVisit = m && m.last_visit ? App.day(String(m.last_visit).replace(' ', 'T')) : null;
    var dob = d.date_of_birth ? App.day(d.date_of_birth) + (d.age !== null ? ' (' + d.age + ' yrs)' : '') : null;
    var medical = d.medical_conditions
      ? '<div class="mt-1" style="white-space:pre-wrap;color:var(--gf-warn)">' + esc(d.medical_conditions) + '</div>'
      : '<div class="mt-1 text-secondary">None recorded</div>';

    return '<div class="d-flex flex-wrap align-items-center gap-3 mb-3">' +
        '<span class="gf-avatar">' + esc(App.initials(d.name)) + '</span>' +
        '<div class="flex-grow-1" style="min-width:0"><div class="fw-bold fs-5 gf-truncate">' + esc(d.name) + '</div>' +
          '<div class="small text-secondary">' + (tel(d.phone) || 'No phone') + (d.email ? ' · ' + esc(d.email) : '') + '</div></div>' +
        (m ? App.statusPill(m.status) : '') +
      '</div>' +
      '<div class="row g-3">' +
        card('Membership',
          fact('Plan', m && m.plan) + fact('Ends', ends) + fact('Last visit', lastVisit) +
          fact('Member since', d.joined_on ? App.day(d.joined_on) : null) +
          fact('Balance due', m && m.due > 0 ? App.money(m.due) : null)) +
        card('Personal',
          fact('Gender', d.gender ? d.gender.charAt(0).toUpperCase() + d.gender.slice(1) : null) + fact('Date of birth', dob) +
          fact('Occupation', d.occupation) + fact('Heard about us via', labels.source && labels.source[d.referral_source]) +
          fact('Address', d.address)) +
        card('Emergency contact',
          fact('Name', d.emergency_contact_name) + fact('Relation', d.emergency_contact_relation) +
          fact('Phone', tel(d.emergency_contact_phone), true)) +
        card('Body & fitness',
          fact('Height', d.height_cm ? d.height_cm + ' cm' : null) + fact('Weight', d.weight_kg ? d.weight_kg + ' kg' : null) +
          fact('BMI', bmi) + fact('Goal', labels.goal && labels.goal[d.fitness_goal]) +
          fact('Experience', labels.level && labels.level[d.fitness_level]) +
          fact('Preferred time', labels.timing && labels.timing[d.preferred_timing])) +
        card('Health', fact('Blood group', d.blood_group) + '<div class="text-secondary pt-1">Medical conditions / injuries</div>' + medical) +
        card('Notes', d.notes ? '<div style="white-space:pre-wrap">' + esc(d.notes) + '</div>' : '<div class="text-secondary">No notes.</div>') +
      '</div>';
  }

  $list.on('click', '.js-view', function () {
    var id = $(this).data('id');
    var m = members[id];
    var mine = ++viewSeq;
    viewing = null;
    $('#view-modal .js-edit-from-view').prop('disabled', true);
    $('#view-body').html('<div class="gf-empty">Loading details…</div>');
    App.modal('#view-modal').show();

    App.get('/members/' + id)
      .done(function (d) {
        if (mine !== viewSeq) { return; }
        viewing = d;
        $('#view-body').html(renderDetails(d, m));
        $('#view-modal .js-edit-from-view').prop('disabled', false);
      })
      .fail(function (xhr) {
        if (mine !== viewSeq) { return; }
        $('#view-body').html('<div class="gf-empty text-danger">' + esc(App.errorMessage(xhr, 'Could not load the details.')) + '</div>');
      });
  });

  if (!canWrite) { load(); return; }

  // ---------- Member details (form fields shared by add + edit) ----------
  var DETAIL_FIELDS = [
    'gender', 'date_of_birth', 'address', 'occupation', 'referral_source',
    'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
    'height_cm', 'weight_kg', 'fitness_goal', 'fitness_level', 'preferred_timing', 'blood_group',
    'medical_conditions', 'notes'
  ];
  var NUMERIC_FIELDS = ['height_cm', 'weight_kg'];

  /** The detail inputs of a form as an API payload: blank -> null, numbers as numbers. */
  function readDetails($form) {
    var out = {};
    $.each(DETAIL_FIELDS, function (_, name) {
      var v = $.trim($form.find('[name="' + name + '"]').val());
      out[name] = v === '' ? null : ($.inArray(name, NUMERIC_FIELDS) !== -1 ? Number(v) : v);
    });
    return out;
  }

  function fillDetails($form, d) {
    $.each(DETAIL_FIELDS, function (_, name) {
      $form.find('[name="' + name + '"]').val(d[name] === null || d[name] === undefined ? '' : d[name]);
    });
  }

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
    $form.find('[name="joining_date"], [name="start_date"]').on('change', refresh);

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
          $msg.css('color', 'var(--gf-bad)').text(App.errorMessage(xhr, 'That coupon can\'t be used.'));
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
    // An explicit start date (e.g. a past plan entered late) wins; otherwise the renewal starts
    // when the current plan ends, or today if it already ended.
    var picked = $renew.find('[name="start_date"]').val();
    if (picked) { return picked; }
    var end = renewMember && renewMember.ends_on ? App.parse(renewMember.ends_on) : null;
    return end && end > new Date() ? end : new Date();
  }, function () { return renewMember ? renewMember.id : null; });

  /**
   * Payment date follows the plan start date while that is in the past (a late entry is usually paid on
   * the day it started), until the user sets the payment date themselves.
   */
  function linkPaymentDate($form, startField) {
    var $paid = $form.find('[name="paid_on"]');
    $paid.on('input change', function () { $paid.data('touched', true); });
    $form.find('[name="' + startField + '"]').on('change', function () {
      if ($paid.data('touched')) { return; }
      var start = $(this).val();
      $paid.val(start && start < App.today() ? start : App.today());
    });
  }
  function resetPaymentDate($form) {
    $form.find('[name="paid_on"]').val(App.today()).data('touched', false);
  }
  linkPaymentDate($add, 'joining_date');
  linkPaymentDate($renew, 'start_date');

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
    resetPaymentDate($add);
    $add.data('resetCoupon')();
    $('#add-more').removeClass('show');   // start with the optional details folded away
    if (plans.length) { $add.find('[name="membership_plan_id"]').val(plans[0].id).trigger('change'); }
    App.modal('#add-modal').show();
  }
  $('#btn-add-member').on('click', openAdd);

  $add.on('submit', function (e) {
    e.preventDefault();
    var $btn = $add.find('button[type="submit"]').prop('disabled', true);
    App.formError($add, '');

    App.post('/members', $.extend({
      name: $.trim($add.find('[name="name"]').val()),
      phone: $.trim($add.find('[name="phone"]').val()),
      email: $.trim($add.find('[name="email"]').val()) || null,
      joining_date: $add.find('[name="joining_date"]').val() || null,
      paid_on: $add.find('[name="paid_on"]').val() || null,
      membership_plan_id: App.num($add.find('[name="membership_plan_id"]').val()),
      paid: App.num($add.find('[name="paid"]').val()),
      method: $add.find('[name="method"]').val(),
      coupon_code: $.trim($add.find('[name="coupon_code"]').val()) || null
    }, readDetails($add)))
      .done(function () {
        App.modal('#add-modal').hide();
        App.flash('Member added successfully.');
        load();
      })
      .fail(function (xhr) { App.formError($add, App.errorMessage(xhr, 'Could not add the member.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Edit details ----------
  var $edit = $('#edit-form');
  var editing = null;

  $('#view-modal .js-edit-from-view').on('click', function () {
    if (!viewing) { return; }
    editing = viewing;
    App.formError($edit, '');
    $edit.find('[name="name"]').val(editing.name);
    $edit.find('[name="phone"]').val(editing.phone || '');
    $edit.find('[name="joined_on"]').val(editing.joined_on || '');
    fillDetails($edit, editing);
    // Swap modals only once the first has finished closing, so the backdrops don't fight.
    $('#view-modal').one('hidden.bs.modal', function () { App.modal('#edit-modal').show(); });
    App.modal('#view-modal').hide();
  });

  $edit.on('submit', function (e) {
    e.preventDefault();
    var $btn = $edit.find('button[type="submit"]').prop('disabled', true);
    App.formError($edit, '');

    var payload = $.extend({
      name: $.trim($edit.find('[name="name"]').val()),
      phone: $.trim($edit.find('[name="phone"]').val())
    }, readDetails($edit));
    // Owner only (the server ignores it from anyone else; the field is only rendered for the owner).
    if (window.MembersPage.canEditJoinDate) { payload.joined_on = $edit.find('[name="joined_on"]').val() || null; }

    App.api('PUT', '/members/' + editing.id, payload)
      .done(function () {
        App.modal('#edit-modal').hide();
        App.flash('Details saved.');
        load();
      })
      .fail(function (xhr) { App.formError($edit, App.errorMessage(xhr, 'Could not save the details.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  // ---------- Renew ----------
  function openRenew(m) {
    renewMember = m;
    App.formError($renew, '');
    $renew.data('resetCoupon')();
    $renew.find('[name="start_date"]').val('');
    resetPaymentDate($renew);
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
      coupon_code: $.trim($renew.find('[name="coupon_code"]').val()) || null,
      start_date: $renew.find('[name="start_date"]').val() || null,
      paid_on: $renew.find('[name="paid_on"]').val() || null
    })
      .done(function () {
        App.modal('#renew-modal').hide();
        var paidOn = $renew.find('[name="paid_on"]').val();
        App.flash(paidOn && paidOn !== App.today()
          ? 'Plan recorded for ' + renewMember.name + ' — payment dated ' + App.day(paidOn) + '.'
          : renewMember.name + '’s membership renewed.');
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
