$(function () {
  'use strict';

  var esc = App.esc;
  var money = App.money;
  var isAdmin = App.role === 'admin';
  var isMember = App.role === 'member';

  var $list = $('#plan-list');
  var plans = [];
  var coupons = [];

  function planById(id) {
    for (var i = 0; i < plans.length; i++) { if (String(plans[i].id) === String(id)) { return plans[i]; } }
    return null;
  }
  function couponById(id) {
    for (var i = 0; i < coupons.length; i++) { if (String(coupons[i].id) === String(id)) { return coupons[i]; } }
    return null;
  }

  // ---------- Labels ----------
  var SALE = {
    on_sale: ['active', 'On sale'], full: ['low', 'Sold out'], scheduled: ['expiring', 'Scheduled'],
    ended: ['none', 'Ended'], inactive: ['none', 'Inactive']
  };
  var COUPON = {
    active: ['active', 'Active'], used_up: ['low', 'Used up'], expired: ['expired', 'Expired'],
    scheduled: ['expiring', 'Scheduled'], inactive: ['none', 'Inactive']
  };

  function pill(map, key) {
    var m = map[key] || ['none', key];
    return '<span class="gf-pill gf-pill--' + m[0] + '">' + esc(m[1]) + '</span>';
  }

  function discountText(c) {
    return c.discount_type === 'percent' ? Number(c.discount_value) + '% off' : money(c.discount_value) + ' off';
  }

  function dateRange(from, to, prefix) {
    if (!from && !to) { return ''; }
    if (from && to) { return prefix + ' ' + App.day(from) + ' – ' + App.day(to); }
    return from ? prefix + ' from ' + App.day(from) : prefix + ' until ' + App.day(to);
  }

  // ---------- Plan cards ----------
  function usageBlock(p) {
    if (isAdmin) {
      if (p.usage_limit) {
        var pct = Math.min(100, Math.round(p.used_count / p.usage_limit * 100));
        return '<div class="mt-2"><div class="d-flex justify-content-between small mb-1"><span>' + p.used_count + ' / ' + p.usage_limit + ' signed up</span>' +
          '<span class="text-secondary">' + p.remaining + ' left</span></div>' +
          '<div class="progress" style="height:.45rem;background:rgba(255,255,255,.08)"><div class="progress-bar" style="width:' + pct + '%;background:' +
          (pct >= 100 ? '#f87171' : 'var(--gf-gold)') + '"></div></div></div>';
      }
      return '<div class="small mt-2">' + p.used_count + ' signed up <span class="text-secondary">· no limit</span></div>';
    }
    if (p.remaining !== null && p.remaining !== undefined) {
      return '<div class="small mt-2" style="color:' + (p.remaining <= 5 ? '#fbbf24' : 'var(--gf-muted)') + '">' +
        (p.remaining === 0 ? 'Sold out' : p.remaining + ' spot' + (p.remaining === 1 ? '' : 's') + ' left') + '</div>';
    }
    return '';
  }

  function planCard(p) {
    var facts = [];
    if (p.duration_days) { facts.push(p.duration_days + ' day' + (p.duration_days === 1 ? '' : 's')); }
    if (p.class_credits) { facts.push(p.class_credits + ' class credits'); }
    if (isAdmin && p.per_member_limit) { facts.push('max ' + p.per_member_limit + ' per member'); }
    var window = dateRange(p.available_from, p.available_until, 'On sale');

    var attached = isAdmin ? $.grep(coupons, function (c) { return c.membership_plan_id === p.id; }) : [];

    var actions;
    if (isAdmin) {
      actions = '<div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top" style="border-color:var(--gf-line-soft)!important">' +
        '<button class="btn btn-light btn-sm js-edit" data-id="' + esc(p.id) + '">Edit</button>' +
        '<button class="btn btn-light btn-sm js-coupon-for" data-id="' + esc(p.id) + '">+ Coupon</button>' +
        '<button class="btn btn-light btn-sm ms-auto js-toggle" data-id="' + esc(p.id) + '">' + (p.is_active ? 'Deactivate' : 'Activate') + '</button></div>';
    } else if (isMember) {
      var ok = p.sale_status === 'on_sale';
      actions = '<button class="btn btn-primary w-100 mt-3 js-subscribe" data-id="' + esc(p.id) + '"' + (ok ? '' : ' disabled') + '>' +
        (ok ? 'Subscribe' : esc((SALE[p.sale_status] || [0, 'Unavailable'])[1])) + '</button>';
    } else {
      actions = '';
    }

    return '<div class="col-12 col-md-6 col-xl-4"><div class="card h-100' + (p.is_active ? '' : ' opacity-75') + '"><div class="card-body d-flex flex-column">' +
      '<div class="d-flex justify-content-between align-items-start gap-2"><div class="fw-bold fs-5">' + esc(p.name) + '</div>' +
        (isAdmin || p.sale_status !== 'on_sale' ? pill(SALE, p.sale_status) : '') + '</div>' +
      '<div class="my-2"><span class="fs-2 fw-bold tabular" style="color:var(--gf-gold)">' + esc(money(p.price)) + '</span> ' +
        '<span class="small text-secondary">/ ' + esc(App.label(p.billing_cycle)) + '</span></div>' +
      (p.description ? '<p class="small text-secondary mb-2">' + esc(p.description) + '</p>' : '') +
      (facts.length ? '<div class="small text-secondary">' + esc(facts.join(' · ')) + '</div>' : '') +
      (window ? '<div class="small text-secondary">' + esc(window) + '</div>' : '') +
      usageBlock(p) +
      (attached.length ? '<div class="d-flex flex-wrap gap-1 mt-3">' + $.map(attached, function (c) {
        return '<span class="gf-pill gf-pill--' + (COUPON[c.status] || ['none'])[0] + '" title="' + esc(discountText(c) + ' · ' + c.redeemed_count + ' used') + '">' +
          esc(c.code) + ' · ' + esc(discountText(c)) + '</span>';
      }).join('') + '</div>' : '') +
      '<div class="mt-auto">' + actions + '</div>' +
    '</div></div></div>';
  }

  function renderPlans() {
    $list.html(plans.length ? $.map(plans, planCard).join('')
      : '<div class="col-12 gf-empty">No membership plans yet.</div>');
  }

  // ---------- Coupon table (admin) ----------
  function renderCoupons() {
    var $box = $('#coupon-list');
    if (!$box.length) { return; }
    if (!coupons.length) { $box.html('<div class="gf-empty">No coupons yet. Create one to offer a discount.</div>'); return; }

    $box.html('<div class="table-responsive"><table class="table table-hover align-middle small"><thead><tr>' +
      '<th>Code</th><th>Discount</th><th class="d-none d-md-table-cell">Applies to</th><th>Used</th><th class="d-none d-lg-table-cell">Valid</th><th>Status</th><th></th></tr></thead><tbody>' +
      $.map(coupons, function (c) {
        var validity = dateRange(c.valid_from, c.valid_until, '') || 'Always';
        return '<tr' + (c.status === 'active' ? '' : ' class="opacity-75"') + '>' +
          '<td><div class="fw-bold tabular">' + esc(c.code) + '</div>' + (c.description ? '<div class="text-secondary" style="font-size:.72rem">' + esc(c.description) + '</div>' : '') + '</td>' +
          '<td class="fw-semibold" style="color:var(--gf-gold)">' + esc(discountText(c)) + '</td>' +
          '<td class="d-none d-md-table-cell">' + esc(c.plan ? c.plan.name : 'All plans') + '</td>' +
          '<td class="tabular">' + c.redeemed_count + (c.max_redemptions ? ' / ' + c.max_redemptions : '') +
            (c.once_per_member ? '<div class="text-secondary" style="font-size:.7rem">1 per member</div>' : '') + '</td>' +
          '<td class="d-none d-lg-table-cell text-secondary">' + esc(validity.trim()) + '</td>' +
          '<td>' + pill(COUPON, c.status) + '</td>' +
          '<td class="text-end text-nowrap"><button class="btn btn-light btn-sm js-coupon-edit" data-id="' + esc(c.id) + '">Edit</button> ' +
            '<button class="btn btn-light btn-sm js-coupon-toggle" data-id="' + esc(c.id) + '">' + (c.is_active ? 'Disable' : 'Enable') + '</button></td></tr>';
      }).join('') + '</tbody></table></div>');
  }

  function load() {
    var reqs = [App.get('/membership-plans', isAdmin ? { include_inactive: 1 } : undefined).done(function (res) { plans = res; })];
    if (isAdmin) { reqs.push(App.get('/coupons').done(function (res) { coupons = res; })); }

    return $.when.apply($, reqs)
      .done(function () { renderPlans(); renderCoupons(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load plans.'), 'danger'); });
  }

  // ---------- Member: subscribe with an optional coupon ----------
  if (isMember) {
    var $sub = $('#subscribe-form');
    var subPlan = null;
    var applied = null;   // {code, discount, final_price} once a coupon has been accepted
    var seq = 0;          // bumped when the code changes, so a slow reply for an old code is ignored

    function paintTotals() {
      var discount = applied ? applied.discount : 0;
      $sub.find('.js-sub-price').text(money(subPlan.price));
      $sub.find('.js-sub-discount-row').toggleClass('d-none', !applied);
      $sub.find('.js-sub-discount').text('−' + money(discount));
      $sub.find('.js-sub-total').text(money(Math.max(0, subPlan.price - discount)));
    }

    $list.on('click', '.js-subscribe', function () {
      subPlan = planById($(this).data('id'));
      seq++;
      applied = null;
      $sub[0].reset();
      App.formError($sub, '');
      $sub.find('.js-coupon-msg').text('');
      $sub.find('.js-sub-name').text(subPlan.name);
      $sub.find('.js-sub-desc').text(subPlan.description || '');
      paintTotals();
      App.modal('#subscribe-modal').show();
    });

    $sub.find('[name="coupon_code"]').on('input', function () {
      seq++;
      applied = null;
      $sub.find('.js-coupon-msg').text('');
      paintTotals();
    });

    $sub.find('.js-apply').on('click', function () {
      var code = $.trim($sub.find('[name="coupon_code"]').val());
      var $msg = $sub.find('.js-coupon-msg');
      if (!code) { return; }
      var mine = ++seq;

      App.post('/coupons/check', { code: code, membership_plan_id: subPlan.id })
        .done(function (r) {
          if (mine !== seq) { return; }
          applied = { code: r.code, discount: r.discount, final_price: r.final_price };
          $msg.css('color', 'var(--gf-cash)').text(r.code + ' applied — you save ' + money(r.discount) + '.');
          paintTotals();
        })
        .fail(function (xhr) {
          if (mine !== seq) { return; }
          applied = null;
          $msg.css('color', '#f87171').text(App.errorMessage(xhr, 'That coupon can\'t be used.'));
          paintTotals();
        });
    });

    $sub.on('submit', function (e) {
      e.preventDefault();
      var $btn = $sub.find('button[type="submit"]').prop('disabled', true);
      App.formError($sub, '');

      App.post('/member-plans', {
        membership_plan_id: subPlan.id,
        coupon_code: $.trim($sub.find('[name="coupon_code"]').val()) || null
      })
        .done(function () {
          App.modal('#subscribe-modal').hide();
          App.flash('Subscribed! Check Billing for your invoice.');
          load();
        })
        .fail(function (xhr) { App.formError($sub, App.errorMessage(xhr, 'Subscription failed.')); })
        .always(function () { $btn.prop('disabled', false); });
    });
  }

  if (!isAdmin) { load(); return; }

  // ---------- Admin: create / edit plan ----------
  var $form = $('#plan-form');
  var editingPlan = null;

  function openPlanForm(p) {
    editingPlan = p;
    $form[0].reset();
    App.formError($form, '');
    $('#plan-modal-title').text(p ? 'Edit plan' : 'New Membership Plan');
    $form.find('.js-edit-note').toggleClass('d-none', !p);
    $form.find('.js-used-hint').toggleClass('d-none', !p).text(p ? p.used_count + ' already signed up — the limit can\'t go below that.' : '');

    var v = function (name, val) { $form.find('[name="' + name + '"]').val(val === null || val === undefined ? '' : val); };
    if (p) {
      v('name', p.name); v('description', p.description); v('billing_cycle', p.billing_cycle); v('price', p.price);
      v('duration_days', p.duration_days); v('class_credits', p.class_credits);
      v('usage_limit', p.usage_limit); v('per_member_limit', p.per_member_limit);
      v('available_from', p.available_from); v('available_until', p.available_until);
      $form.find('[name="is_active"]').prop('checked', p.is_active);
    } else {
      v('duration_days', 30);
      $form.find('[name="is_active"]').prop('checked', true);
    }
    App.modal('#plan-modal').show();
  }

  $('#btn-new-plan').on('click', function () { openPlanForm(null); });
  $list.on('click', '.js-edit', function () { openPlanForm(planById($(this).data('id'))); });

  $form.on('submit', function (e) {
    e.preventDefault();
    App.formError($form, '');
    var f = function (name) { return $form.find('[name="' + name + '"]').val(); };
    var $btn = $form.find('button[type="submit"]').prop('disabled', true);

    var payload = {
      name: $.trim(f('name')),
      description: $.trim(f('description')) || null,
      billing_cycle: f('billing_cycle'),
      price: App.num(f('price')),
      class_credits: App.num(f('class_credits')),
      duration_days: App.num(f('duration_days')),
      usage_limit: App.num(f('usage_limit')),
      per_member_limit: App.num(f('per_member_limit')),
      available_from: f('available_from') || null,
      available_until: f('available_until') || null,
      is_active: $form.find('[name="is_active"]').is(':checked')
    };

    var req = editingPlan ? App.api('PUT', '/membership-plans/' + editingPlan.id, payload) : App.post('/membership-plans', payload);
    req.done(function () {
      App.modal('#plan-modal').hide();
      App.flash(editingPlan ? 'Plan updated.' : 'Plan created.');
      load();
    })
      .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not save the plan.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  $list.on('click', '.js-toggle', function () {
    var p = planById($(this).data('id'));
    App.api('PUT', '/membership-plans/' + p.id, { is_active: !p.is_active })
      .done(function () { App.flash(p.name + (p.is_active ? ' deactivated — no new sign-ups.' : ' is available again.')); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not update the plan.'), 'danger'); });
  });

  // ---------- Admin: create / edit coupon ----------
  var $cform = $('#coupon-form');
  var editingCoupon = null;

  function fillCouponPlans(selected) {
    $cform.find('[name="membership_plan_id"]').html('<option value="">All plans</option>' + $.map(plans, function (p) {
      return '<option value="' + esc(p.id) + '">' + esc(p.name) + (p.is_active ? '' : ' (inactive)') + '</option>';
    }).join('')).val(selected ? String(selected) : '');
  }

  function openCouponForm(c, planId) {
    editingCoupon = c;
    $cform[0].reset();
    App.formError($cform, '');
    $('#coupon-modal-title').text(c ? 'Edit coupon' : 'New Coupon');
    fillCouponPlans(c ? c.membership_plan_id : planId);

    var v = function (name, val) { $cform.find('[name="' + name + '"]').val(val === null || val === undefined ? '' : val); };
    if (c) {
      v('code', c.code); v('description', c.description); v('discount_type', c.discount_type); v('discount_value', Number(c.discount_value));
      v('max_redemptions', c.max_redemptions); v('valid_from', c.valid_from); v('valid_until', c.valid_until);
      $cform.find('[name="once_per_member"]').prop('checked', c.once_per_member);
      $cform.find('[name="is_active"]').prop('checked', c.is_active);
    } else {
      $cform.find('[name="once_per_member"], [name="is_active"]').prop('checked', true);
    }
    App.modal('#coupon-modal').show();
  }

  function couponPayload(c) {
    return {
      code: c.code, description: c.description || null, discount_type: c.discount_type, discount_value: Number(c.discount_value),
      membership_plan_id: c.membership_plan_id, max_redemptions: c.max_redemptions,
      once_per_member: c.once_per_member, valid_from: c.valid_from, valid_until: c.valid_until, is_active: c.is_active
    };
  }

  $('#btn-new-coupon').on('click', function () { openCouponForm(null, null); });
  $list.on('click', '.js-coupon-for', function () { openCouponForm(null, $(this).data('id')); });
  $('#coupon-list').on('click', '.js-coupon-edit', function () { openCouponForm(couponById($(this).data('id')), null); });

  $('#coupon-list').on('click', '.js-coupon-toggle', function () {
    var c = couponById($(this).data('id'));
    var payload = couponPayload(c);
    payload.is_active = !c.is_active;
    App.api('PUT', '/coupons/' + c.id, payload)
      .done(function () { App.flash(c.code + (payload.is_active ? ' enabled.' : ' disabled.')); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not update the coupon.'), 'danger'); });
  });

  $cform.on('submit', function (e) {
    e.preventDefault();
    App.formError($cform, '');
    var f = function (name) { return $cform.find('[name="' + name + '"]').val(); };
    var $btn = $cform.find('button[type="submit"]').prop('disabled', true);

    var payload = {
      code: $.trim(f('code')).toUpperCase(),
      description: $.trim(f('description')) || null,
      discount_type: f('discount_type'),
      discount_value: App.num(f('discount_value')),
      membership_plan_id: App.num(f('membership_plan_id')),
      max_redemptions: App.num(f('max_redemptions')),
      once_per_member: $cform.find('[name="once_per_member"]').is(':checked'),
      valid_from: f('valid_from') || null,
      valid_until: f('valid_until') || null,
      is_active: $cform.find('[name="is_active"]').is(':checked')
    };

    var req = editingCoupon ? App.api('PUT', '/coupons/' + editingCoupon.id, payload) : App.post('/coupons', payload);
    req.done(function () {
      App.modal('#coupon-modal').hide();
      App.flash(editingCoupon ? 'Coupon updated.' : 'Coupon created.');
      load();
    })
      .fail(function (xhr) { App.formError($cform, App.errorMessage(xhr, 'Could not save the coupon.')); })
      .always(function () { $btn.prop('disabled', false); });
  });

  load();
});
