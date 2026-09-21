$(function () {
  'use strict';

  var $form = $('#login-form');

  // Local-only shortcut buttons: fill the demo credentials.
  $('.js-demo').on('click', function () {
    $form.find('[name="email"]').val($(this).data('email'));
    $form.find('[name="password"]').val('password').trigger('focus');
  });

  $form.on('submit', function (e) {
    e.preventDefault();
    var $btn = $form.find('button[type="submit"]');

    App.formError($form, '');
    $btn.prop('disabled', true).text('Signing in…');

    App.postPage('/login', {
      email: $.trim($form.find('[name="email"]').val()),
      password: $form.find('[name="password"]').val()
    })
      .done(function (res) {
        window.location.href = res.redirect;
      })
      .fail(function (xhr) {
        App.formError($form, App.errorMessage(xhr, 'Login failed.'));
        $btn.prop('disabled', false).text('Sign in');
      });
  });
});
