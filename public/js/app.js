/**
 * GymFit Manager — shared jQuery helpers.
 * Loaded on every page after jQuery/Bootstrap and after the inline `window.App`
 * config from layouts/base.blade.php ({ baseUrl, user, role }).
 */
(function ($, window) {
  'use strict';

  var App = window.App = window.App || {};
  var baseUrl = String(App.baseUrl || '').replace(/\/$/, '');

  // ---------- AJAX defaults: CSRF token + JSON ----------
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json'
    }
  });

  // Session expired (401) or CSRF token stale (419) on a signed-in page -> back to login.
  $(document).ajaxError(function (event, xhr) {
    if (App.user && (xhr.status === 401 || xhr.status === 419)) {
      window.location.href = App.url('/login');
    }
  });

  // ---------- URLs & requests ----------
  App.url = function (path) {
    return baseUrl + path;
  };

  /** JSON request to a /ajax endpoint. Returns the jqXHR promise. */
  App.api = function (method, path, data) {
    var opts = { url: App.url('/ajax' + path), method: method, dataType: 'json' };
    if (data !== undefined) {
      if (method === 'GET') {
        opts.data = data;
      } else {
        opts.data = JSON.stringify(data);
        opts.contentType = 'application/json';
      }
    }
    return $.ajax(opts);
  };
  App.get = function (path, data) { return App.api('GET', path, data); };
  App.post = function (path, data) { return App.api('POST', path, data || {}); };

  /** POST a form to a page-level route (login/register/logout). */
  App.postPage = function (path, data) {
    return $.ajax({
      url: App.url(path), method: 'POST', dataType: 'json',
      data: JSON.stringify(data || {}), contentType: 'application/json'
    });
  };

  /** Readable message from a failed request (422 field errors, {message}, or fallback). */
  App.errorMessage = function (xhr, fallback) {
    var body = xhr && xhr.responseJSON;
    if (body && body.errors) {
      var all = [];
      $.each(body.errors, function (_, msgs) { all = all.concat(msgs); });
      if (all.length) { return all.join(' '); }
    }
    return (body && body.message) || fallback || 'Something went wrong.';
  };

  // ---------- Formatting ----------
  /** HTML-escape anything that goes into a template string. */
  App.esc = function (value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  };
  App.date = function (value) { return value ? new Date(value).toLocaleString() : ''; };
  /** Date-only strings ("2026-09-25") are local dates here; plain new Date() would read them as UTC midnight. */
  App.parse = function (value) {
    return new Date(/^\d{4}-\d{2}-\d{2}$/.test(String(value)) ? value + 'T00:00:00' : value);
  };
  /** "12 Sep 2026" */
  App.day = function (value) {
    return value ? App.parse(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
  };
  /** "07:30 am" */
  App.time = function (value) {
    return value ? new Date(value).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '';
  };
  /** ₹4,500 — Indian digit grouping, no trailing .00. */
  App.money = function (value) {
    return (App.currency || '₹') + Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
  };
  /** 455 -> "7h 35m" */
  App.hm = function (minutes) {
    minutes = Math.round(Number(minutes) || 0);
    if (minutes <= 0) { return '0h'; }
    var h = Math.floor(minutes / 60), m = minutes % 60;
    return h + 'h' + (m ? ' ' + m + 'm' : '');
  };
  App.WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  App.label = function (value) { return String(value || '').replace(/_/g, ' '); };
  App.initials = function (name) {
    var parts = String(name || '?').trim().split(/\s+/);
    return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
  };
  /** Local YYYY-MM-DD (toISOString would shift the date for timezones ahead of UTC). */
  App.today = function () {
    var d = new Date();
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  };

  /** Bootstrap "text-bg-*" badge. */
  App.badge = function (text, variant) {
    return '<span class="badge text-bg-' + (variant || 'secondary') + ' text-capitalize">' +
      App.esc(App.label(text)) + '</span>';
  };

  /** Compact amounts for chart labels: ₹950, ₹1.2k, ₹3.4L (lakh). */
  App.short = function (v) {
    v = Number(v) || 0;
    var c = App.currency || '₹';
    if (v >= 100000) { return c + (v / 100000).toFixed(1).replace(/\.0$/, '') + 'L'; }
    if (v >= 1000) { return c + (v / 1000).toFixed(1).replace(/\.0$/, '') + 'k'; }
    return c + Math.round(v);
  };

  /**
   * CSS bar chart. items: rows; valueKey/labelKey: fields; fmt: number -> label.
   * With many bars (e.g. a month of days) only every few labels are printed; hover shows each one.
   */
  App.bars = function (items, valueKey, labelKey, fmt) {
    var nums = $.map(items, function (i) { return Number(i[valueKey]) || 0; });
    var max = Math.max.apply(null, nums.concat([1]));
    var every = Math.max(1, Math.ceil(items.length / 10));
    return '<div class="gf-bars">' + $.map(items, function (i, idx) {
      var v = nums[idx];
      var h = v > 0 ? Math.max(4, Math.round(v / max * 100)) : 0;
      var show = idx % every === 0 || idx === items.length - 1;
      return '<div class="gf-bar-col" title="' + App.esc(i[labelKey] + ': ' + fmt(v)) + '">' +
        '<span class="gf-bar-val">' + (v > 0 && every === 1 ? App.esc(fmt(v)) : '') + '</span>' +
        '<div class="gf-bar' + (v > 0 ? '' : ' gf-bar--dim') + '" style="height:' + (h || 3) + '%"></div>' +
        '<span class="gf-bar-lbl">' + (show ? App.esc(i[labelKey]) : '&nbsp;') + '</span></div>';
    }).join('') + '</div>';
  };

  var METHOD = { cash: 'cash', upi: 'upi', card: 'card', credit_card: 'card', debit_card: 'card' };
  var METHOD_LABEL = { cash: 'Cash', upi: 'UPI', card: 'Card', other: 'Other' };
  /** Coloured Cash / UPI / Card pill (credit/debit card collapse into Card). */
  App.methodPill = function (method) {
    var key = METHOD[method] || 'other';
    return '<span class="gf-pill gf-pill--' + key + '">' + (METHOD_LABEL[key] || 'Other') + '</span>';
  };

  var STATUS_LABEL = { active: 'Active', expiring: 'Expiring', expired: 'Expired', none: 'No plan' };
  /** Membership status pill: active / expiring / expired / none. */
  App.statusPill = function (status) {
    return '<span class="gf-pill gf-pill--' + App.esc(status) + '">' + (STATUS_LABEL[status] || App.esc(status)) + '</span>';
  };

  /** "in 3 days" / "today" / "2 days ago" for a member's days_left. */
  App.daysLeft = function (days) {
    if (days === null || days === undefined) { return ''; }
    if (days === 0) { return 'today'; }
    return days > 0 ? 'in ' + days + ' day' + (days === 1 ? '' : 's') : Math.abs(days) + ' day' + (days === -1 ? '' : 's') + ' ago';
  };

  /**
   * wa.me link with a ready-to-send message for a member row ({name, phone, status, ends_on, days_left, due}).
   * `kind` = 'inactive' sends a "we miss you" nudge instead of a renewal notice. Returns null without a phone.
   */
  App.whatsappLink = function (m, kind) {
    var digits = String(m.phone || '').replace(/\D/g, '');
    if (!digits) { return null; }
    if (digits.length === 10) { digits = (App.countryCode || '') + digits; }

    var brand = App.brand || 'the gym';
    var due = m.due > 0 ? ' Pending due: ' + App.money(m.due) + '.' : '';
    var ends = m.ends_on ? App.day(m.ends_on) : '';
    var text;
    if (kind === 'inactive') {
      text = 'Hi ' + m.name + ', we have not seen you at ' + brand + ' for a while. Your goals are waiting — come train with us this week! - ' + brand;
    } else if (m.status === 'expired') {
      text = 'Hi ' + m.name + ', ' + brand + ' - your membership expired on ' + ends + ' (' + Math.abs(m.days_left) +
        ' days ago). Please renew to continue your fitness journey.' + due + ' - ' + brand;
    } else if (m.status === 'expiring') {
      text = 'Hi ' + m.name + ', ' + brand + ' - your membership expires on ' + ends + ' (' + App.daysLeft(m.days_left) +
        '). Renew now and keep the momentum going!' + due + ' - ' + brand;
    } else {
      text = 'Hi ' + m.name + ', ' + brand + ' - reminder: your membership is active' + (ends ? ' till ' + ends : '') + '.' + due +
        ' Keep grinding! - ' + brand;
    }
    return 'https://wa.me/' + digits + '?text=' + encodeURIComponent(text);
  };

  /** Green WhatsApp button for a member row, or a disabled one when there is no phone number. */
  App.whatsappButton = function (m, kind, label) {
    var href = App.whatsappLink(m, kind);
    var icon = '<svg class="gf-ico gf-ico--sm"><use href="#i-whatsapp"/></svg>';
    return href
      ? '<a class="btn btn-wa btn-sm d-inline-flex align-items-center gap-1" target="_blank" rel="noopener" href="' + App.esc(href) +
        '" title="Send a WhatsApp message">' + icon + (label === false ? '' : ' ' + (label || 'WhatsApp')) + '</a>'
      : '<button class="btn btn-light btn-sm" disabled title="No phone number">' + icon + '</button>';
  };

  /** "" -> null so optional numeric fields validate as nullable. */
  App.num = function (value) {
    return value === '' || value === null || value === undefined ? null : Number(value);
  };

  // ---------- UI helpers ----------
  /** Dismissible alert in the top-right corner. */
  App.flash = function (message, type) {
    var $box = $('#gf-flash');
    if (!$box.length) { window.alert(message); return; }
    var $alert = $('<div class="alert alert-dismissible fade show shadow-sm" role="alert"></div>')
      .addClass('alert-' + (type || 'success'))
      .text(message)
      .append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
    $box.append($alert);
    setTimeout(function () { $alert.alert('close'); }, 4000);
  };

  App.modal = function (selector) {
    return window.bootstrap.Modal.getOrCreateInstance($(selector)[0]);
  };

  /** Show/clear an inline error element (`.gf-error`) inside a form. */
  App.formError = function ($form, message) {
    $form.find('.gf-error').text(message || '').toggleClass('d-none', !message);
  };

  // ---------- Member picker: type a name/phone, choose from the matches ----------
  // Markup: <div class="gf-picker" data-picker> <input class="form-control gf-picker-input"> <input type="hidden" name="user_id"> </div>
  // Staff-only (uses /ajax/members). Fires `picked` / `cleared` events on the picker element.
  App.pickerValue = function ($picker) { return App.num($picker.find('input[type="hidden"]').val()); };
  App.pickerReset = function ($picker) {
    $picker.removeClass('is-picked').find('input[type="hidden"]').val('');
    $picker.find('.gf-picker-input').val('');
    $picker.find('.gf-suggest').addClass('d-none').empty();
    $picker.trigger('cleared');
  };

  $(function () {
    $('[data-picker]').each(function () {
      var $picker = $(this);
      var $input = $picker.find('.gf-picker-input');
      var $hidden = $picker.find('input[type="hidden"]');
      var $list = $('<div class="gf-suggest d-none"></div>').appendTo($picker);
      var $picked = $('<div class="gf-picked align-items-center justify-content-between gap-2 form-control">' +
        '<span class="gf-truncate js-picked-name"></span>' +
        '<button type="button" class="btn btn-light btn-sm js-picked-clear" aria-label="Change member">Change</button></div>').appendTo($picker);
      var timer = null;
      var seq = 0;

      function render(members) {
        if (!members.length) {
          $list.html('<div class="gf-empty py-3">No member found</div>').removeClass('d-none');
          return;
        }
        $list.html($.map(members.slice(0, 6), function (m) {
          return '<button type="button" data-id="' + App.esc(m.id) + '" data-name="' + App.esc(m.name) + '">' +
            '<span class="gf-avatar">' + App.esc(App.initials(m.name)) + '</span>' +
            '<span class="flex-grow-1 gf-truncate"><span class="d-block fw-medium gf-truncate">' + App.esc(m.name) + '</span>' +
            '<span class="d-block small text-secondary">' + App.esc(m.phone || 'No phone') + '</span></span>' +
            App.statusPill(m.status) + '</button>';
        }).join('')).removeClass('d-none');
      }

      $input.on('input', function () {
        var q = $.trim($input.val());
        clearTimeout(timer);
        if (q.length < 2) { $list.addClass('d-none').empty(); return; }
        timer = setTimeout(function () {
          var mine = ++seq;
          App.get('/members', { q: q }).done(function (res) { if (mine === seq) { render(res.data); } });
        }, 200);
      });

      $list.on('click', 'button', function () {
        var $b = $(this);
        $hidden.val($b.data('id'));
        $picked.find('.js-picked-name').text($b.data('name'));
        $picker.addClass('is-picked');
        $list.addClass('d-none').empty();
        $picker.trigger('picked', [{ id: $b.data('id'), name: $b.data('name') }]);
      });

      $picked.on('click', '.js-picked-clear', function () {
        App.pickerReset($picker);
        $input.trigger('focus');
      });

      $(document).on('click', function (e) {
        if (!$(e.target).closest($picker).length) { $list.addClass('d-none'); }
      });
    });
  });

  // ---------- Logout buttons (sidebar + mobile top bar) ----------
  $(document).on('click', '.js-logout', function () {
    App.postPage('/logout').always(function (res) {
      window.location.href = (res && res.redirect) || App.url('/login');
    });
  });
})(jQuery, window);
