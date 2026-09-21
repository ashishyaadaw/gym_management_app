$(function () {
  'use strict';

  var esc = App.esc;
  var $list = $('#payment-list');
  var isStaff = App.role !== 'member';

  var STATUS_VARIANT = {
    paid: 'success',
    pending: 'warning',
    failed: 'danger',
    refunded: 'secondary'
  };

  function render(payments) {
    if (!payments.length) {
      $list.html('<div class="gf-empty">No payments found.</div>');
      return;
    }

    $list.html($.map(payments, function (p) {
      var planName = (p.member_plan && p.member_plan.plan && p.member_plan.plan.name) || p.notes || 'Manual payment';
      return '<div class="gf-list-item d-flex flex-wrap align-items-center justify-content-between gap-2 gap-sm-3 py-3">' +
        '<div class="flex-grow-1" style="min-width:12rem">' +
          '<div class="fw-semibold tabular">' + esc(App.money(p.amount)) + ' <span class="small text-secondary fw-normal">· ' + esc(p.invoice_number) + '</span></div>' +
          '<div class="small text-secondary">' + esc(planName) + (isStaff && p.user ? ' · ' + esc(p.user.name) : '') +
            (p.paid_at ? ' · ' + esc(App.day(p.paid_at)) : p.due_date ? ' · due ' + esc(App.day(p.due_date)) : '') + '</div>' +
        '</div>' +
        '<div class="d-flex align-items-center gap-2">' +
          (p.status === 'paid' ? App.methodPill(p.method) : '') +
          App.badge(p.status, STATUS_VARIANT[p.status]) +
          (isStaff && p.status === 'pending'
            ? '<button class="btn btn-light btn-sm js-mark-paid" data-id="' + esc(p.id) + '">Mark paid</button>' : '') +
        '</div>' +
      '</div>';
    }).join(''));
  }

  function load() {
    return App.get('/payments')
      .done(function (res) { render(res.data); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load payments.'), 'danger'); });
  }

  // ---------- Staff: record a payment ----------
  var $form = $('#payment-form');
  var $picker = $form.find('[data-picker]');

  $form.on('submit', function (e) {
    e.preventDefault();
    App.formError($form, '');

    var userId = App.pickerValue($picker);
    if (!userId) { App.formError($form, 'Choose a member first.'); return; }

    App.post('/payments', {
      user_id: userId,
      amount: App.num($form.find('[name="amount"]').val()),
      method: $form.find('[name="method"]').val()
    })
      .done(function () {
        $form[0].reset();
        App.pickerReset($picker);
        App.flash('Payment recorded.');
        load();
      })
      .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not record the payment.')); });
  });

  // ---------- Staff: mark an invoice paid ----------
  $list.on('click', '.js-mark-paid', function () {
    App.post('/payments/' + $(this).data('id') + '/mark-paid', { method: 'cash' })
      .done(function () { App.flash('Marked as paid (cash).'); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not update the payment.'), 'danger'); });
  });

  load();
});
