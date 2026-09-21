$(function () {
  'use strict';

  var esc = App.esc;
  var $list = $('#schedule-list');

  function render(schedules) {
    if (!schedules.length) {
      $list.html('<p class="small text-secondary">No upcoming class sessions.</p>');
      return;
    }

    $list.html($.map(schedules, function (s) {
      var capacity = s.capacity_override || s.gym_class.capacity;
      var action = App.role === 'member'
        ? '<button class="btn btn-primary js-book" data-id="' + esc(s.id) + '"' +
          (s.status !== 'scheduled' ? ' disabled' : '') + '>Book</button>'
        : App.badge(s.status, 'secondary');

      return '<div class="card"><div class="card-body d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center gap-3">' +
        '<div>' +
          '<div class="fw-semibold">' + esc(s.gym_class.name) + '</div>' +
          '<div class="small text-secondary">' + esc(App.date(s.start_time)) + ' · ' +
            esc(s.room || 'Main Floor') + ' · Trainer: ' + esc(s.trainer.name) + '</div>' +
          '<div class="small text-secondary opacity-75 mt-1">' + esc(s.confirmed_bookings_count) + ' / ' + esc(capacity) + ' booked</div>' +
        '</div>' +
        '<div>' + action + '</div>' +
      '</div></div>';
    }).join(''));
  }

  function load() {
    // Upcoming sessions only; format as UTC "YYYY-MM-DD HH:MM:SS" to match the DB.
    var from = new Date().toISOString().slice(0, 19).replace('T', ' ');
    return App.get('/class-schedules', { from: from })
      .done(function (res) { render(res.data); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load classes.'), 'danger'); });
  }

  // ---------- Member: book ----------
  $list.on('click', '.js-book', function () {
    var $btn = $(this).prop('disabled', true);
    App.post('/bookings', { class_schedule_id: $btn.data('id') })
      .done(function () { App.flash('Booked!'); load(); })
      .fail(function (xhr) {
        App.flash(App.errorMessage(xhr, 'Booking failed.'), 'danger');
        $btn.prop('disabled', false);
      });
  });

  // ---------- Admin: schedule a session ----------
  var $form = $('#schedule-form');
  if ($form.length) {
    function options(items) {
      return $.map(items, function (i) {
        return '<option value="' + esc(i.id) + '">' + esc(i.name) + '</option>';
      }).join('');
    }

    App.get('/gym-classes').done(function (classes) {
      $form.find('[name="gym_class_id"]').html(options(classes));
    });
    App.get('/users', { role: 'trainer' }).done(function (res) {
      $form.find('[name="trainer_id"]').html(options(res.data));
    });

    $form.on('submit', function (e) {
      e.preventDefault();
      App.formError($form, '');

      var data = {};
      $.each($form.serializeArray(), function (_, f) { data[f.name] = f.value; });

      App.post('/class-schedules', data)
        .done(function () {
          App.modal('#schedule-modal').hide();
          $form[0].reset();
          App.flash('Session scheduled.');
          load();
        })
        .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not schedule the session.')); });
    });
  }

  load();
});
