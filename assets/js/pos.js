(function () {
  var P = window.POS;
  var cart = {};                       // product id -> quantity
  var byId = {};
  var byBarcode = {};
  P.products.forEach(function (p) { byId[p.id] = p; if (p.barcode) { byBarcode[String(p.barcode).toLowerCase()] = p.id; } });

  var $ = function (id) { return document.getElementById(id); };
  var list = $('product-list'), cartEl = $('cart'), totalEl = $('total');
  var receivedEl = $('received'), changeEl = $('change'), msg = $('msg');

  function money(n) { return P.currency + ' ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function payMethod() { return document.querySelector('input[name="pay"]:checked').value; }
  function total() {
    var t = 0;
    Object.keys(cart).forEach(function (id) { t += byId[id].price * cart[id]; });
    return t;
  }
  function showError(text) { msg.textContent = text; msg.hidden = !text; }

  function renderProducts() {
    var q = $('search').value.trim().toLowerCase();
    var shown = P.products.filter(function (p) { return !q || p.name.toLowerCase().indexOf(q) !== -1; });
    list.innerHTML = shown.map(function (p) {
      var left = p.stock - (cart[p.id] || 0);
      return '<button type="button" class="tile" data-id="' + p.id + '"' + (left <= 0 ? ' disabled' : '') + '>' +
        '<span class="tile-name">' + esc(p.name) + '</span>' +
        '<span class="tile-price">' + money(p.price) + '</span>' +
        '<span class="tile-stock' + (left <= 5 ? ' low' : '') + '">' + left + ' left</span></button>';
    }).join('');
    $('no-products').hidden = shown.length > 0;
  }

  function renderCart() {
    var ids = Object.keys(cart);
    $('cart-empty').hidden = ids.length > 0;
    cartEl.innerHTML = ids.map(function (id) {
      var p = byId[id], q = cart[id];
      return '<li><div class="line-name">' + esc(p.name) + '</div>' +
        '<div class="line-controls">' +
        '<button type="button" class="qty" data-act="dec" data-id="' + id + '" aria-label="Remove one ' + esc(p.name) + '">-</button>' +
        '<span class="qty-n">' + q + '</span>' +
        '<button type="button" class="qty" data-act="inc" data-id="' + id + '" aria-label="Add one ' + esc(p.name) + '">+</button>' +
        '<span class="line-sub">' + money(p.price * q) + '</span>' +
        '<button type="button" class="qty x" data-act="del" data-id="' + id + '" aria-label="Remove ' + esc(p.name) + ' from sale">x</button>' +
        '</div></li>';
    }).join('');
    totalEl.textContent = money(total());
    updateChange();
    renderProducts();
  }

  function updateChange() {
    var got = parseFloat(receivedEl.value);
    var change = isNaN(got) ? 0 : Math.max(0, got - total());
    changeEl.textContent = money(change);
  }

  function add(id) {
    var p = byId[id];
    if ((cart[id] || 0) < p.stock) { cart[id] = (cart[id] || 0) + 1; showError(''); }
    renderCart();
  }

  list.addEventListener('click', function (e) {
    var b = e.target.closest('.tile');
    if (b) { add(b.getAttribute('data-id')); }
  });
  cartEl.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-act]');
    if (!b) { return; }
    var id = b.getAttribute('data-id'), act = b.getAttribute('data-act');
    if (act === 'inc') { add(id); return; }
    if (act === 'dec') { cart[id] = (cart[id] || 1) - 1; if (cart[id] <= 0) { delete cart[id]; } }
    if (act === 'del') { delete cart[id]; }
    renderCart();
  });

  var barcodeEl = $('barcode');
  barcodeEl.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') { return; }
    e.preventDefault();
    var code = barcodeEl.value.trim().toLowerCase();
    barcodeEl.value = '';
    if (!code) { return; }
    var id = byBarcode[code];
    if (id) { add(id); showError(''); }
    else { showError('No product has the barcode "' + code + '".'); }
  });

  $('search').addEventListener('input', renderProducts);
  receivedEl.addEventListener('input', updateChange);
  Array.prototype.forEach.call(document.querySelectorAll('input[name="pay"]'), function (r) {
    r.addEventListener('change', function () {
      var method = payMethod();
      $('mpesa-box').hidden = method !== 'mpesa';
      $('bank-box').hidden = method !== 'bank';
      $('cash-box').hidden = method !== 'cash';
      showError('');
    });
  });
  $('clear').addEventListener('click', function () { cart = {}; showError(''); renderCart(); });

  $('checkout').addEventListener('click', function () {
    var btn = this;
    var items = Object.keys(cart).map(function (id) { return { id: parseInt(id, 10), qty: cart[id] }; });
    if (!items.length) { showError('Add at least one product before completing the sale.'); return; }
    var method = payMethod();
    var body = { csrf: P.csrf, items: items, payment_method: method, mpesa_code: $('mpesa-code').value, bank_reference: $('bank-ref').value };
    if (method === 'cash' && receivedEl.value !== '' && parseFloat(receivedEl.value) < total()) {
      showError('The cash received is less than the total.'); return;
    }
    btn.disabled = true; btn.textContent = 'Saving...'; showError('');
    fetch(P.checkoutUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) { window.location.href = P.receiptUrl + d.sale_id; return; }
        showError(d.error || 'The sale could not be saved.');
        btn.disabled = false; btn.textContent = 'Complete sale';
      })
      .catch(function () {
        showError('Could not reach the server. Check that Apache is running and try again.');
        btn.disabled = false; btn.textContent = 'Complete sale';
      });
  });

  $('tape-date').textContent = new Date().toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
  renderCart();
})();
