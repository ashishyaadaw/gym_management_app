$(function () {
  'use strict';

  $('#register-form').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');

    App.formError($form, '');
    $btn.prop('disabled', true).text('Creating account…');

    var data = {};
    $.each($form.serializeArray(), function (_, f) { data[f.name] = f.value; });

    App.postPage('/register', data)
      .done(function (res) {
        window.location.href = res.redirect;
      })
      .fail(function (xhr) {
        App.formError($form, App.errorMessage(xhr, 'Registration failed.'));
        $btn.prop('disabled', false).text('Create account');
      });
  });
});
