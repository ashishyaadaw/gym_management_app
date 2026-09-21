$(function () {
  'use strict';

  var esc = App.esc;
  var $list = $('#equipment-list');

  var STATUS_VARIANT = {
    available: 'success',
    in_use: 'primary',
    under_maintenance: 'warning',
    retired: 'secondary'
  };

  function render(items) {
    if (!items.length) {
      $list.html('<div class="col-12"><p class="small text-secondary">No equipment recorded.</p></div>');
      return;
    }

    $list.html($.map(items, function (e) {
      return '<div class="col-12 col-sm-6 col-lg-4"><div class="card h-100"><div class="card-body">' +
        '<div class="d-flex justify-content-between align-items-start gap-2">' +
          '<div class="fw-semibold">' + esc(e.name) + '</div>' +
          App.badge(e.status, STATUS_VARIANT[e.status]) +
        '</div>' +
        '<div class="small text-secondary">' + esc(e.category) + ' · Qty: ' + esc(e.quantity) + '</div>' +
        '<div class="small text-secondary opacity-75">' + esc(e.location) + '</div>' +
        '<button class="btn btn-light btn-sm mt-3 js-maintenance" data-id="' + esc(e.id) + '">Log Maintenance</button>' +
      '</div></div></div>';
    }).join(''));
  }

  function load() {
    return App.get('/equipment')
      .done(function (res) { render(res.data); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load equipment.'), 'danger'); });
  }

  // ---------- Add equipment ----------
  var $form = $('#equipment-form');
  $form.on('submit', function (e) {
    e.preventDefault();
    App.formError($form, '');

    var f = function (name) { return $form.find('[name="' + name + '"]').val(); };

    App.post('/equipment', {
      name: f('name'),
      category: f('category'),
      location: f('location'),
      quantity: App.num(f('quantity'))
    })
      .done(function () {
        App.modal('#equipment-modal').hide();
        $form[0].reset();
        App.flash('Equipment added.');
        load();
      })
      .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not add the equipment.')); });
  });

  // ---------- Log maintenance ----------
  $list.on('click', '.js-maintenance', function () {
    var description = window.prompt('Maintenance description:');
    if (!description) { return; }

    App.post('/equipment/' + $(this).data('id') + '/maintenance', {
      maintenance_date: new Date().toISOString().slice(0, 10),
      description: description
    })
      .done(function () { App.flash('Maintenance logged.'); load(); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not log maintenance.'), 'danger'); });
  });

  load();
});
