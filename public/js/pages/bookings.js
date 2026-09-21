$(function () {
  'use strict';

  var esc = App.esc;
  var $list = $('#booking-list');

  var STATUS_VARIANT = {
    confirmed: 'success',
    waitlisted: 'warning',
    cancelled: 'secondary',
    attended: 'primary',
    no_show: 'danger'
  };

  function render(bookings) {
    if (!bookings.length) {
      $list.html('<p class="small text-secondary">No bookings yet.</p>');
      return;
    }

    $list.html($.map(bookings, function (b) {
      var canCancel = App.role === 'member' && $.inArray(b.status, ['confirmed', 'waitlisted']) !== -1;
      return '<div class="card"><div class="card-body d-flex align-items-center justify-content-between gap-3">' +
        '<div>' +
          '<div class="fw-medium">' + esc(b.class_schedule.gym_class.name) + '</div>' +
          '<div class="small text-secondary">' + esc(App.date(b.class_schedule.start_time)) + '</div>' +
          (App.role !== 'member' ? '<div class="small text-secondary opacity-75">Member: ' + esc(b.user.name) + '</div>' : '') +
        '</div>' +
        '<div class="d-flex align-items-center gap-3">' +
          App.badge(b.status, STATUS_VARIANT[b.status]) +
          (canCancel ? '<button class="btn btn-outline-danger btn-sm js-cancel" data-id="' + esc(b.id) + '">Cancel</button>' : '') +
        '</div>' +
      '</div></div>';
    }).join(''));
  }

  function load() {
    return App.get('/bookings')
      .done(function (res) { render(res.data); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load bookings.'), 'danger'); });
  }

  $list.on('click', '.js-cancel', function () {
    if (!window.confirm('Cancel this booking?')) { return; }
    App.post('/bookings/' + $(this).data('id') + '/cancel')
      .done(function () { App.flash('Booking cancelled.'); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not cancel the booking.'), 'danger'); });
  });

  load();
});
