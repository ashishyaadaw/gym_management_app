$(function () {
  'use strict';

  var esc = App.esc;
  var isAdmin = !!(window.StorePage && window.StorePage.isAdmin);

  var products = [];      // catalogue as returned by the server
  var cart = {};          // product id -> quantity
  var LOW = '__low';       // pseudo-category: everything running low
  var category = 'All';
  var method = 'cash';

  function byId(id) {
    for (var i = 0; i < products.length; i++) { if (String(products[i].id) === String(id)) { return products[i]; } }
    return null;
  }

  // ---------- Catalogue ----------
  function renderCats() {
    var cats = ['All'];
    $.each(products, function (_, p) { if (p.category && cats.indexOf(p.category) === -1) { cats.push(p.category); } });
    var low = $.grep(products, function (p) { return p.is_active && p.is_low_stock; }).length;
    if (low) { cats.push(LOW); }
    $('#product-cats').html($.map(cats, function (c) {
      var label = c === LOW ? 'Low stock (' + low + ')' : c;
      return '<button type="button" class="btn btn-light btn-sm gf-chip' + (c === category ? ' active' : '') + '" data-cat="' + esc(c) + '">' + esc(label) + '</button>';
    }).join(''));
  }

  function renderGrid() {
    var q = $.trim($('#product-search').val()).toLowerCase();
    var shown = $.grep(products, function (p) {
      var inCategory = category === 'All' || (category === LOW ? p.is_low_stock : p.category === category);
      return inCategory && (!q || p.name.toLowerCase().indexOf(q) !== -1);
    });

    if (!shown.length) {
      $('#product-grid').html('<div class="col-12 gf-empty">No products found.</div>');
      return;
    }

    $('#product-grid').html($.map(shown, function (p) {
      var inCart = cart[p.id] || 0;
      var out = p.stock <= 0;
      return '<div class="col-6 col-md-4">' +
        '<button type="button" class="gf-product js-add" data-id="' + esc(p.id) + '"' + (out || inCart >= p.stock ? ' disabled' : '') + '>' +
          '<div class="d-flex justify-content-between align-items-start gap-2">' +
            '<span class="gf-pill gf-pill--other">' + esc(p.category) + '</span>' +
            (inCart ? '<span class="badge text-bg-primary rounded-pill">' + inCart + '</span>' : '') + '</div>' +
          '<div class="fw-semibold mt-2" style="min-height:2.6em;line-height:1.3">' + esc(p.name) + '</div>' +
          '<div class="d-flex justify-content-between align-items-end mt-2">' +
            '<span class="fs-5 fw-bold tabular" style="color:var(--gf-gold)">' + esc(App.money(p.selling_price)) + '</span>' +
            (out ? '<span class="gf-pill gf-pill--low">Out of stock</span>'
              : p.is_low_stock ? '<span class="gf-pill gf-pill--low">' + p.stock + ' left</span>'
              : '<span class="small text-secondary">' + p.stock + ' in stock</span>') +
          '</div></button>' +
        (isAdmin ? '<div class="text-end mt-1 small">' + (p.is_active ? '' : '<span class="text-secondary">Hidden · </span>') +
          '<button type="button" class="btn btn-link btn-sm text-secondary p-0 js-stock" data-id="' + esc(p.id) + '">Stock</button>' +
          '<span class="text-secondary"> · </span>' +
          '<button type="button" class="btn btn-link btn-sm text-secondary p-0 js-edit" data-id="' + esc(p.id) + '">Edit</button></div>' : '') +
      '</div>';
    }).join(''));
  }

  function loadProducts() {
    return App.get('/store/products', isAdmin ? { all: 1 } : {})
      .done(function (res) {
        products = res;
        // Drop cart lines for products that disappeared or ran out.
        $.each(cart, function (id) { var p = byId(id); if (!p || !p.is_active || p.stock <= 0) { delete cart[id]; } });
        renderCats(); renderGrid(); renderCart();
      })
      .fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load products.'), 'danger'); });
  }

  $('#product-search').on('input', renderGrid);
  $('#product-cats').on('click', 'button', function () { category = $(this).data('cat'); renderCats(); renderGrid(); });

  // ---------- Cart ----------
  function cartTotal() {
    var t = 0;
    $.each(cart, function (id, qty) { var p = byId(id); if (p) { t += p.selling_price * qty; } });
    return t;
  }

  function renderCart() {
    var ids = Object.keys(cart);
    $('#cart-lines').html(ids.length ? $.map(ids, function (id) {
      var p = byId(id), qty = cart[id];
      return '<div class="gf-list-item d-flex align-items-center justify-content-between gap-2 py-2">' +
        '<div class="gf-truncate"><div class="fw-medium gf-truncate">' + esc(p.name) + '</div>' +
        '<div class="small text-secondary">' + esc(App.money(p.selling_price)) + ' each</div></div>' +
        '<div class="gf-qty">' +
          '<button type="button" class="btn btn-light btn-sm js-dec" data-id="' + esc(id) + '" aria-label="Fewer"><svg class="gf-ico gf-ico--sm"><use href="#i-minus"/></svg></button>' +
          '<span class="fw-bold tabular" style="min-width:1.4rem;text-align:center">' + qty + '</span>' +
          '<button type="button" class="btn btn-light btn-sm js-inc" data-id="' + esc(id) + '" aria-label="More"' + (qty >= p.stock ? ' disabled' : '') + '><svg class="gf-ico gf-ico--sm"><use href="#i-plus"/></svg></button>' +
        '</div></div>';
    }).join('') : '<div class="gf-empty">Cart is empty</div>');

    $('#cart-total').text(App.money(cartTotal()));
    $('#btn-sell').prop('disabled', !ids.length);
  }

  function change(id, delta) {
    var p = byId(id);
    if (!p) { return; }
    var next = (cart[id] || 0) + delta;
    if (next > p.stock) { App.flash('Only ' + p.stock + ' of ' + p.name + ' in stock.', 'warning'); return; }
    if (next <= 0) { delete cart[id]; } else { cart[id] = next; }
    renderCart(); renderGrid();
  }

  $('#product-grid').on('click', '.js-add', function () { change($(this).data('id'), 1); });
  $('#cart-lines').on('click', '.js-inc', function () { change($(this).data('id'), 1); });
  $('#cart-lines').on('click', '.js-dec', function () { change($(this).data('id'), -1); });
  $('#cart-clear').on('click', function () { cart = {}; renderCart(); renderGrid(); });

  $('#pay-methods').on('click', 'button', function () {
    method = $(this).data('method');
    $('#pay-methods button').removeClass('active');
    $(this).addClass('active');
  });

  $('#btn-sell').on('click', function () {
    var $btn = $(this).prop('disabled', true);
    var $err = $('#sale-error').addClass('d-none');
    var total = cartTotal();
    var items = $.map(Object.keys(cart), function (id) { return { product_id: Number(id), quantity: cart[id] }; });

    App.post('/store/sales', { items: items, method: method, member_id: App.pickerValue($('#sale-member')) })
      .done(function (sale) {
        App.flash('Sale completed — ' + App.money(total) + ' via ' + method.toUpperCase() + '.');
        $('#last-sale').removeClass('d-none')
          .find('.js-last-total').text(App.money(sale.total) + ' · ' + sale.receipt_number).end()
          .find('.js-last-receipt').attr('href', App.url('/sales/' + sale.id + '/receipt'));
        cart = {};
        App.pickerReset($('#sale-member'));
        loadProducts(); loadSales();
      })
      .fail(function (xhr) {
        $err.text(App.errorMessage(xhr, 'Could not complete the sale.')).removeClass('d-none');
        loadProducts();
      })
      .always(function () { $btn.prop('disabled', !Object.keys(cart).length); });
  });

  // ---------- Today's sales ----------
  function loadSales() {
    return App.get('/store/sales').done(function (sales) {
      var sum = 0, count = 0;
      $.each(sales, function (_, s) { if (s.status !== 'voided') { sum += Number(s.total); count++; } });
      $('#sales-summary').text(count + ' sale' + (count === 1 ? '' : 's') + ' · ' + App.money(sum));

      $('#sales-list').html(sales.length ? $.map(sales, function (s) {
        var lines = $.map(s.items, function (i) { return i.name + ' x' + i.quantity; }).join(', ');
        var voided = s.status === 'voided';
        return '<div class="gf-list-item d-flex align-items-center justify-content-between gap-3 py-2' + (voided ? ' opacity-50' : '') + '">' +
          '<div class="gf-truncate"><div class="fw-medium gf-truncate' + (voided ? ' text-decoration-line-through' : '') + '">' + esc(lines) + '</div>' +
          '<div class="small text-secondary">' + esc(s.receipt_number) + ' · ' + esc(App.time(s.sold_at)) + ' · ' + esc(s.member ? s.member.name : 'Walk-in') +
            (s.seller ? ' · by ' + esc(s.seller.name) : '') + (voided ? ' · <span style="color:var(--gf-bad)">Voided</span>' : '') + '</div></div>' +
          '<div class="d-flex align-items-center gap-2">' + App.methodPill(s.method) +
          '<span class="fw-bold tabular' + (voided ? ' text-decoration-line-through' : '') + '">' + esc(App.money(s.total)) + '</span>' +
          '<a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="' + esc(App.url('/sales/' + s.id + '/receipt')) + '" title="Receipt">' +
            '<svg class="gf-ico gf-ico--sm"><use href="#i-receipt"/></svg></a></div></div>';
      }).join('') : '<div class="gf-empty">No store sales yet today.</div>');
    }).fail(function (xhr) { App.flash(App.errorMessage(xhr, 'Could not load sales.'), 'danger'); });
  }

  // ---------- Admin: add / edit product ----------
  if (isAdmin) {
    var $form = $('#product-form');
    var editing = null;

    function openForm(p) {
      editing = p;
      App.formError($form, '');
      $form[0].reset();
      $form.find('[name="stock"]').prop('readonly', false);
      $form.find('.js-stock-hint').addClass('d-none');
      $('#product-modal-title').text(p ? 'Edit product' : 'Add product');
      if (p) {
        $form.find('[name="name"]').val(p.name);
        $form.find('[name="category"]').val(p.category);
        $form.find('[name="stock"]').val(p.stock).prop('readonly', true);
        $form.find('.js-stock-hint').removeClass('d-none');
        $form.find('[name="cost_price"]').val(p.cost_price);
        $form.find('[name="selling_price"]').val(p.selling_price);
        $form.find('[name="is_active"]').prop('checked', p.is_active);
      }
      App.modal('#product-modal').show();
    }

    $('#btn-add-product').on('click', function () { openForm(null); });
    $('#product-grid').on('click', '.js-edit', function () { openForm(byId($(this).data('id'))); });

    $form.on('submit', function (e) {
      e.preventDefault();
      App.formError($form, '');
      var payload = {
        name: $.trim($form.find('[name="name"]').val()),
        category: $.trim($form.find('[name="category"]').val()),
        stock: App.num($form.find('[name="stock"]').val()),
        cost_price: App.num($form.find('[name="cost_price"]').val()),
        selling_price: App.num($form.find('[name="selling_price"]').val()),
        is_active: $form.find('[name="is_active"]').is(':checked')
      };
      var req = editing ? App.api('PUT', '/store/products/' + editing.id, payload) : App.post('/store/products', payload);
      req.done(function () {
        App.modal('#product-modal').hide();
        App.flash('Product saved.');
        loadProducts();
      }).fail(function (xhr) { App.formError($form, App.errorMessage(xhr, 'Could not save the product.')); });
    });
  }

  // ---------- Admin: stock adjustments ----------
  if (isAdmin) {
    var $stockForm = $('#stock-form');
    var stockProduct = null;

    var REASON = { restock: 'Restock', adjustment: 'Correction', damaged: 'Damaged', sale: 'Sale', void: 'Void' };

    function loadMovements() {
      $('#stock-history').html('<div class="text-secondary">Loading…</div>');
      App.get('/store/products/' + stockProduct.id + '/movements').done(function (rows) {
        $('#stock-history').html(rows.length ? $.map(rows, function (m) {
          return '<div class="gf-list-item d-flex justify-content-between gap-2 py-1">' +
            '<span class="gf-truncate"><span class="text-secondary">' + esc(App.day(m.created_at)) + '</span> · ' + esc(REASON[m.reason] || m.reason) +
              (m.note ? ' <span class="text-secondary">(' + esc(m.note) + ')</span>' : '') + '</span>' +
            '<b class="tabular" style="color:' + (m.change > 0 ? 'var(--gf-cash)' : 'var(--gf-bad)') + '">' + (m.change > 0 ? '+' : '') + m.change + '</b></div>';
        }).join('') : '<div class="text-secondary">No movements recorded yet.</div>');
      });
    }

    $('#product-grid').on('click', '.js-stock', function () {
      stockProduct = byId($(this).data('id'));
      $stockForm[0].reset();
      App.formError($stockForm, '');
      $stockForm.find('.js-stock-name').text(stockProduct.name);
      $stockForm.find('.js-stock-now').text(stockProduct.stock);
      App.modal('#stock-modal').show();
      loadMovements();
    });

    $stockForm.on('submit', function (e) {
      e.preventDefault();
      App.formError($stockForm, '');
      var $btn = $stockForm.find('button[type="submit"]').prop('disabled', true);

      App.post('/store/products/' + stockProduct.id + '/stock', {
        change: App.num($stockForm.find('[name="change"]').val()),
        reason: $stockForm.find('[name="reason"]').val(),
        note: $.trim($stockForm.find('[name="note"]').val()) || null
      })
        .done(function (p) {
          stockProduct.stock = p.stock;
          $stockForm.find('.js-stock-now').text(p.stock);
          $stockForm[0].reset();
          App.flash(p.name + ' stock is now ' + p.stock + '.');
          loadProducts();
          loadMovements();
        })
        .fail(function (xhr) { App.formError($stockForm, App.errorMessage(xhr, 'Could not update the stock.')); })
        .always(function () { $btn.prop('disabled', false); });
    });
  }

  loadProducts();
  loadSales();
});
