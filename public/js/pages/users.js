$(function () {
  'use strict';

  var esc = App.esc;
  var $list = $('#user-list');
  var $filters = $('#user-filters');
  var filter = 'all';

  function render(users) {
    if (!users.length) {
      $list.html('<p class="small text-secondary">No users found.</p>');
      return;
    }

    $list.html($.map(users, function (u) {
      return '<div class="card"><div class="card-body d-flex align-items-center justify-content-between gap-3">' +
        '<div>' +
          '<div class="fw-medium">' + esc(u.name) + ' <span class="small text-secondary opacity-75">#' + esc(u.id) + '</span></div>' +
          '<div class="small text-secondary">' + esc(u.email) + ' · <span class="text-capitalize">' + esc(u.role) + '</span></div>' +
        '</div>' +
        (u.is_active ? App.badge('Active', 'success') : App.badge('Inactive', 'secondary')) +
      '</div></div>';
    }).join(''));
  }

  function load() {
    return App.get('/users', filter === 'all' ? {} : { role: filter })
      .done(function (res) { render(res.data); })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load users.'), 'danger'); });
  }

  // ---------- Role filter ----------
  $filters.on('click', 'button', function () {
    filter = $(this).data('role');
    $filters.find('button').removeClass('btn-primary').addClass('btn-light');
    $(this).removeClass('btn-light').addClass('btn-primary');
    load();
  });

  // ---------- Add user ----------
  var $form = $('#user-form');
  $form.on('submit', function (e) {
    e.preventDefault();
    App.formError($form, '');

    var data = {};
    $.each($form.serializeArray(), function (_, f) { data[f.name] = f.value; });

    App.post('/users', data)
      .done(function () {
        App.modal('#user-modal').hide();
        $form[0].reset();
        App.flash('User created.');
        load();
      })
      .fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not create the user.')); });
  });

  load();
});
