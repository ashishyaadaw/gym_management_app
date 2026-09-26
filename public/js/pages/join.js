$(function () {
  'use strict';

  var esc = App.esc;
  var $form = $('#join-form');

  $form.on('submit', function (e) {
    e.preventDefault();
    var $btn = $form.find('button[type="submit"]');

    App.formError($form, '');
    $btn.prop('disabled', true).text('Registering…');

    var data = {};
    $.each($form.serializeArray(), function (_, f) { data[f.name] = f.value === '' ? null : f.value; });

    $.ajax({
      url: $form.data('action'), method: 'POST', dataType: 'json',
      data: JSON.stringify(data), contentType: 'application/json'
    })
      .done(function (res) {
        $('#join-body').html(
          '<h1 class="h4 mb-2">You\'re registered, ' + esc(res.name) + '!</h1>' +
          '<p class="text-secondary mb-3">Your details are saved. Visit the front desk to choose your membership plan and start training.</p>' +
          (res.can_sign_in
            ? '<a class="btn btn-primary w-100" href="' + esc(App.url('/login')) + '">Sign in</a>'
            : '<p class="small text-secondary mb-0">You can close this page now.</p>'));
        window.scrollTo(0, 0);
      })
      .fail(function (xhr) {
        App.formError($form, App.errorMessage(xhr, 'Registration failed. Please try again.'));
        $btn.prop('disabled', false).text('Register');
      });
  });
});
