(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const page = document.getElementById('pos-page');
        if (!page) return;
        const el = (id) => document.getElementById(id);
        const form = el('pos-form');
        const request = App.transactionRequest('sales', page);
        const state = { products: [], customers: [], cart: [], loading: false, saving: false, paymentEdited: false, lastSale: null };
        const cents = (value) => Math.round((Number(value) || 0) * 100);
        const amount = (value) => cents(value) / 100;
        const productById = (id) => state.products.find((product) => String(product.id) === String(id));
        const unitFor = (line) => productById(line.product_id)?.units.find((unit) => String(unit.id) === String(line.unit_id));
        const baseUnit = (product) => product.units.find((unit) => Number(unit.multiplier) === 1) || product.units[0];
        const total = () => state.cart.reduce((sum, line) => sum + cents(unitFor(line)?.selling_price) * Number(line.quantity), 0) / 100;
        const customer = () => state.customers.find((item) => String(item.id) === el('pos-customer').value);
        const customerDebt = () => Number(customer()?.outstanding || 0);
        const roundText = (value) => Number(value).toFixed(2).replace(/\.00$/, '');
        const methodName = (method) => ({ cash: 'Tunai', transfer: 'Transfer', qris: 'QRIS' }[method] || method || '—');

        function showValidation(message) {
            el('pos-validation').textContent = message || '';
            el('pos-validation').classList.toggle('d-none', !message);
        }

        function requestedBase(productId) {
            return state.cart.filter((line) => String(line.product_id) === String(productId))
                .reduce((sum, line) => sum + Number(line.quantity) * Number(unitFor(line)?.multiplier || 0), 0);
        }

        function renderProducts() {
            const term = el('pos-search').value.trim().toLocaleLowerCase('id');
            const category = el('pos-category').value;
            const products = state.products.filter((product) =>
                (!category || (product.category || 'Tanpa kategori') === category)
                && `${product.name} ${product.sku}`.toLocaleLowerCase('id').includes(term));
            el('catalog-count').textContent = `${App.number(products.length)} barang`;
            if (!products.length) {
                el('pos-products').innerHTML = `<div class="catalog-empty"><i class="bi bi-search"></i><strong>${state.products.length ? 'Barang tidak ditemukan' : 'Katalog barang masih kosong'}</strong><p class="small mt-2 mb-0">${state.products.length ? 'Coba nama, kode, atau kategori lainnya.' : 'Tambahkan barang dan catat pembelian terlebih dahulu.'}</p></div>`;
                return;
            }
            el('pos-products').innerHTML = products.map((product, index) => {
                const unit = baseUnit(product);
                const stock = Number(product.stock);
                const available = stock - requestedBase(product.id);
                const initials = product.name.split(/\s+/).slice(0, 2).map((word) => word[0]).join('').toUpperCase();
                return `<button type="button" class="pos-product product-tone-${index % 5}" data-product="${Number(product.id)}" ${available < 1 || state.saving ? 'disabled' : ''} aria-label="Tambahkan ${App.escape(product.name)}">
                    <span class="product-art"><i class="bi bi-box-seam"></i><span class="product-initial">${App.escape(initials)}</span></span>
                    <span class="product-category">${App.escape(product.category || 'Tanpa kategori')}</span>
                    <span class="product-name">${App.escape(product.name)}</span>
                    <span class="product-price">${App.money(unit?.selling_price || 0)} <span class="product-unit">/ ${App.escape(unit?.name || product.base_unit)}</span></span>
                    <span class="product-stock ${stock <= Number(product.minimum_stock) ? 'is-low' : ''}"><span>${stock ? `Stok ${App.number(stock)} ${App.escape(product.base_unit)}` : 'Stok habis'}</span><span class="add-sign" aria-hidden="true">+</span></span>
                </button>`;
            }).join('');
        }

        function renderCart() {
            el('cart-count').textContent = App.number(state.cart.length);
            el('cart-clear').disabled = state.cart.length === 0 || state.saving;
            if (!state.cart.length) {
                el('pos-cart').innerHTML = '<div class="empty-state py-5"><i class="bi bi-basket2 fs-1 d-block mb-2"></i><strong>Keranjang masih kosong</strong><p class="small mb-0">Pilih barang di sebelah untuk mulai berjualan.</p></div>';
            } else {
                el('pos-cart').innerHTML = state.cart.map((line, index) => {
                    const product = productById(line.product_id);
                    const unit = unitFor(line);
                    const insufficient = requestedBase(line.product_id) > Number(product.stock);
                    const unitOptions = product.units.map((item) => `<option value="${Number(item.id)}" ${String(item.id) === String(line.unit_id) ? 'selected' : ''}>${App.escape(item.name)}${Number(item.multiplier) > 1 ? ` (${App.number(item.multiplier)} ${App.escape(product.base_unit)})` : ''}</option>`).join('');
                    return `<div class="cart-item ${insufficient ? 'stock-insufficient' : ''}" data-line="${index}">
                        <div class="cart-item-heading"><div><div class="cart-item-name">${App.escape(product.name)}</div><div class="cart-item-sku">${App.escape(product.sku)}</div></div><button type="button" class="cart-remove" data-remove="${index}" aria-label="Hapus ${App.escape(product.name)}"><i class="bi bi-x-lg"></i></button></div>
                        <div class="cart-item-controls"><select class="form-select form-select-sm" data-unit="${index}" aria-label="Satuan ${App.escape(product.name)}">${unitOptions}</select><div class="quantity-control"><button type="button" data-step="-1" data-index="${index}" aria-label="Kurangi jumlah">−</button><input type="number" min="1" step="1" value="${Number(line.quantity)}" data-quantity="${index}" aria-label="Jumlah ${App.escape(product.name)}"><button type="button" data-step="1" data-index="${index}" aria-label="Tambah jumlah">+</button></div><div class="cart-line-total">${App.money(cents(unit.selling_price) * line.quantity / 100)}</div></div>
                        <div class="cart-item-note">${insufficient ? `Stok kurang. Tersedia ${App.number(product.stock)} ${App.escape(product.base_unit)}.` : `${App.money(unit.selling_price)} / ${App.escape(unit.name)} · Mengurangi ${App.number(line.quantity * Number(unit.multiplier))} ${App.escape(product.base_unit)}`}</div>
                    </div>`;
                }).join('');
            }
            updatePayment();
            renderProducts();
        }

        function updatePayment() {
            const sum = total();
            const status = el('pos-payment-status').value;
            const cash = el('pos-method').value === 'cash';
            if (!state.paymentEdited && status === 'paid') el('pos-received').value = roundText(sum);
            if (status === 'unpaid') el('pos-received').value = '0';
            el('pos-total').textContent = App.money(sum);
            el('pos-received-wrap').classList.toggle('d-none', status === 'unpaid');
            el('pos-cash-shortcuts').classList.toggle('d-none', !cash || status !== 'paid');
            el('pos-method').disabled = status === 'unpaid' || state.saving;
            const received = amount(el('pos-received').value);
            const balance = Math.max(0, amount(sum - received));
            el('pos-difference-label').textContent = balance > 0 || status !== 'paid' ? 'Sisa utang' : 'Kembalian';
            el('pos-difference').textContent = App.money(balance > 0 ? balance : Math.max(0, amount(received - sum)));
            el('pos-difference').classList.toggle('text-warning', balance > 0);
            el('pos-submit').disabled = !state.cart.length || state.saving || state.loading;
        }

        function updateCustomer() {
            const debt = customerDebt();
            el('pos-debt-warning').classList.toggle('d-none', debt <= 0);
            el('pos-debt-text').textContent = debt > 0 ? `Sisa utang ${App.money(debt)}. Pelanggan harus melunasinya sebelum berutang lagi. Transaksi ini harus dibayar lunas.` : '';
            for (const option of el('pos-payment-status').options) option.disabled = option.value !== 'paid' && debt > 0;
            if (debt > 0 && el('pos-payment-status').value !== 'paid') {
                el('pos-payment-status').value = 'paid';
                state.paymentEdited = false;
            }
            updatePayment();
        }

        async function loadLookups() {
            if (state.loading) return;
            state.loading = true;
            el('pos-refresh').disabled = true;
            updatePayment();
            try {
                const response = await App.http('GET', page.dataset.lookupsUrl);
                const selectedCustomer = el('pos-customer').value;
                const selectedCategory = el('pos-category').value;
                state.products = (response.data.products || []).filter((product) => product.active !== false && Number(product.active) !== 0 && product.units?.length);
                state.customers = response.data.customers || [];
                el('pos-customer').innerHTML = '<option value="">Pembeli umum</option>' + state.customers.map((item) => `<option value="${Number(item.id)}">${App.escape(item.name)}${Number(item.outstanding) > 0 ? ` — utang ${App.money(item.outstanding)}` : ''}</option>`).join('');
                el('pos-customer').value = selectedCustomer;
                const categories = [...new Set(state.products.map((item) => item.category || 'Tanpa kategori'))].sort((a, b) => a.localeCompare(b, 'id'));
                el('pos-category').innerHTML = '<option value="">Semua kategori</option>' + categories.map((category) => `<option value="${App.escape(category)}">${App.escape(category)}</option>`).join('');
                if (categories.includes(selectedCategory)) el('pos-category').value = selectedCategory;
                const previousCount = state.cart.length;
                state.cart = state.cart.filter((line) => productById(line.product_id) && unitFor(line));
                if (previousCount !== state.cart.length) App.toast('Barang yang sudah tidak aktif dikeluarkan dari keranjang.');
                renderCart();
                updateCustomer();
            } catch (error) {
                if (!state.products.length) el('pos-products').innerHTML = '<div class="transaction-load-error"><i class="bi bi-wifi-off"></i><strong>Barang belum berhasil dimuat</strong><p class="small mt-2">Periksa koneksi, lalu gunakan tombol perbarui.</p></div>';
            } finally {
                state.loading = false;
                el('pos-refresh').disabled = state.saving;
                updatePayment();
            }
        }

        function setSaving(saving) {
            state.saving = saving;
            App.busy(el('pos-submit'), saving);
            form.querySelectorAll('input,select,button').forEach((control) => { control.disabled = saving; });
            el('pos-refresh').disabled = saving || state.loading;
            renderProducts();
            if (!saving) { updateCustomer(); renderCart(); }
        }

        function validate() {
            if (!state.cart.length) return 'Tambahkan barang terlebih dahulu.';
            if (state.cart.length > 100) return 'Satu transaksi maksimal berisi 100 baris barang.';
            if (!el('pos-date').value) return 'Tanggal penjualan wajib diisi.';
            for (const line of state.cart) {
                const product = productById(line.product_id);
                if (!Number.isInteger(Number(line.quantity)) || Number(line.quantity) < 1 || Number(line.quantity) > 1000000) return 'Jumlah barang harus berupa bilangan bulat antara 1 dan 1.000.000.';
                if (requestedBase(line.product_id) > Number(product.stock)) return `Stok ${product.name} tidak cukup. Tersedia ${App.number(product.stock)} ${product.base_unit}.`;
            }
            const sum = cents(total());
            const receivedRaw = Number(el('pos-received').value);
            const received = cents(receivedRaw);
            const status = el('pos-payment-status').value;
            if (!Number.isFinite(receivedRaw) || receivedRaw < 0) return 'Masukkan jumlah pembayaran yang valid.';
            if (status === 'paid' && received < sum) return 'Uang diterima belum mencukupi. Pilih Bayar sebagian jika sisanya akan diutang.';
            if (status === 'partial' && (received <= 0 || received >= sum)) return 'Pembayaran sebagian harus lebih dari nol dan kurang dari total belanja.';
            if (status !== 'paid' && !el('pos-customer').value) return 'Pilih pelanggan untuk mencatat utang.';
            if (received < sum && customerDebt() > 0) return 'Pelanggan harus melunasi utang sebelumnya.';
            if (el('pos-method').value !== 'cash' && received > sum) return 'Nominal transfer atau QRIS harus sesuai tagihan. Kembalian hanya tersedia untuk pembayaran tunai.';
            return '';
        }

        function receiptHtml(sale) {
            const paid = Number(sale.paid_amount || 0);
            const balance = Number(sale.balance || 0);
            const status = balance <= 0 ? 'Lunas' : paid > 0 ? 'Dibayar sebagian' : 'Belum dibayar';
            return `<div class="detail-receipt"><div class="receipt-top"><div><h3>MyKasir</h3><p>Bukti penjualan · ${App.escape(sale.number)}</p></div><span class="${balance > 0 ? 'badge-soft-warning' : 'badge-soft-success'} align-self-start">${status}</span></div><dl class="receipt-meta"><div><dt>Tanggal</dt><dd>${App.date(sale.date)}</dd></div><div><dt>Pelanggan</dt><dd>${App.escape(sale.customer?.name || sale.customer_name || 'Pembeli umum')}</dd></div><div><dt>Pembayaran</dt><dd>${App.escape(methodName(sale.payment_method))}</dd></div></dl><div class="receipt-overflow"><table class="receipt-table"><thead><tr><th>Barang</th><th>Jumlah</th><th>Subtotal</th></tr></thead><tbody>${(sale.items || []).map((item) => `<tr><td><div class="item-name">${App.escape(item.product_name)}</div><div class="item-unit">${App.money(item.unit_price)} / ${App.escape(item.unit_name)}</div></td><td>${App.number(item.quantity)} ${App.escape(item.unit_name)}</td><td>${App.money(item.subtotal)}</td></tr>`).join('')}</tbody></table></div><div class="receipt-totals"><div class="receipt-total-line is-total"><span>Total belanja</span><span>${App.money(sale.total)}</span></div><div class="receipt-total-line"><span>Sudah dibayar</span><span>${App.money(paid)}</span></div>${Number(sale.change_amount) > 0 ? `<div class="receipt-total-line"><span>Uang diterima</span><span>${App.money(sale.received_amount || paid + Number(sale.change_amount))}</span></div><div class="receipt-total-line"><span>Kembalian</span><span>${App.money(sale.change_amount)}</span></div>` : ''}<div class="receipt-total-line"><span>Sisa utang</span><strong>${App.money(balance)}</strong></div></div>${sale.notes ? `<div class="receipt-note">${App.escape(sale.notes)}</div>` : ''}<p class="small text-muted text-center mt-4 mb-0">Terima kasih sudah berbelanja.</p></div>`;
        }

        function printReceipt() {
            if (!state.lastSale) return;
            let target = document.querySelector('.receipt-print-root');
            if (!target) { target = document.createElement('div'); target.className = 'receipt-print-root'; document.body.appendChild(target); }
            target.innerHTML = receiptHtml(state.lastSale);
            document.body.classList.add('receipt-print-mode');
            window.print();
        }

        window.addEventListener('afterprint', () => document.body.classList.remove('receipt-print-mode'));
        el('pos-view-receipt').addEventListener('click', () => {
            if (state.lastSale) App.showDetails('Struk penjualan', receiptHtml(state.lastSale));
        });
        el('pos-print-receipt').addEventListener('click', printReceipt);
        el('pos-search').addEventListener('input', renderProducts);
        el('pos-category').addEventListener('change', renderProducts);
        el('pos-refresh').addEventListener('click', loadLookups);
        el('pos-customer').addEventListener('change', updateCustomer);
        el('pos-payment-status').addEventListener('change', () => {
            state.paymentEdited = false;
            if (el('pos-payment-status').value === 'partial') { el('pos-received').value = ''; state.paymentEdited = true; }
            showValidation('');
            updatePayment();
        });
        el('pos-method').addEventListener('change', () => {
            if (el('pos-method').value !== 'cash' && el('pos-payment-status').value === 'paid') state.paymentEdited = false;
            updatePayment();
        });
        el('pos-received').addEventListener('input', () => { state.paymentEdited = true; updatePayment(); });
        el('pos-cash-shortcuts').addEventListener('click', (event) => {
            const button = event.target.closest('[data-cash]');
            if (!button || state.saving) return;
            state.paymentEdited = button.dataset.cash !== 'exact';
            el('pos-received').value = roundText(button.dataset.cash === 'exact' ? total() : Number(button.dataset.cash));
            updatePayment();
        });
        el('pos-products').addEventListener('click', (event) => {
            const button = event.target.closest('[data-product]');
            if (!button || state.saving) return;
            const product = productById(button.dataset.product);
            if (!product || requestedBase(product.id) >= Number(product.stock)) return;
            const unit = baseUnit(product);
            const existing = state.cart.find((line) => String(line.product_id) === String(product.id) && String(line.unit_id) === String(unit.id));
            if (existing) existing.quantity += 1;
            else state.cart.push({ product_id: product.id, unit_id: unit.id, quantity: 1 });
            showValidation('');
            renderCart();
        });
        el('pos-cart').addEventListener('click', (event) => {
            if (state.saving) return;
            const remove = event.target.closest('[data-remove]');
            if (remove) { state.cart.splice(Number(remove.dataset.remove), 1); renderCart(); return; }
            const step = event.target.closest('[data-step]');
            if (!step) return;
            const line = state.cart[Number(step.dataset.index)];
            const next = line.quantity + Number(step.dataset.step);
            if (next < 1) return;
            line.quantity = next;
            renderCart();
        });
        el('pos-cart').addEventListener('change', (event) => {
            if (state.saving) return;
            if (event.target.matches('[data-unit]')) {
                const index = Number(event.target.dataset.unit);
                const line = state.cart[index];
                line.unit_id = Number(event.target.value);
                const duplicate = state.cart.find((item, itemIndex) => itemIndex !== index && String(item.product_id) === String(line.product_id) && String(item.unit_id) === String(line.unit_id));
                if (duplicate) { duplicate.quantity += line.quantity; state.cart.splice(index, 1); }
            } else if (event.target.matches('[data-quantity]')) {
                const value = Number(event.target.value);
                state.cart[Number(event.target.dataset.quantity)].quantity = Number.isInteger(value) && value > 0 ? value : 1;
            } else return;
            renderCart();
        });
        el('cart-clear').addEventListener('click', async () => {
            if (!state.cart.length || state.saving) return;
            if (await App.confirm('Kosongkan keranjang?', 'Semua barang dalam keranjang akan dihapus.')) {
                state.cart = []; state.paymentEdited = false; showValidation(''); renderCart();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === '/' && !/INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) { event.preventDefault(); el('pos-search').focus(); }
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (state.saving || state.loading) return;
            const message = validate();
            showValidation(message);
            if (message) return;
            const payload = { customer_id: el('pos-customer').value ? Number(el('pos-customer').value) : null, date: el('pos-date').value, items: state.cart.map((line) => ({ product_id: Number(line.product_id), unit_id: Number(line.unit_id), quantity: Number(line.quantity), expected_unit_price: amount(unitFor(line).selling_price) })), received_amount: amount(el('pos-received').value), payment_method: el('pos-method').value, notes: el('pos-notes').value.trim() || null };
            const receiptItems = state.cart.map((line) => ({ product_name: productById(line.product_id).name, unit_name: unitFor(line).name, unit_price: unitFor(line).selling_price, quantity: line.quantity, subtotal: cents(unitFor(line).selling_price) * line.quantity / 100 }));
            const receiptCustomer = customer();
            setSaving(true);
            try {
                const response = await request.send(page.dataset.salesUrl, payload, form);
                const sale = response.data;
                state.lastSale = { ...sale, items: sale.items?.length ? sale.items : receiptItems, customer: sale.customer || receiptCustomer, received_amount: payload.received_amount, change_amount: sale.change_amount ?? Math.max(0, amount(payload.received_amount - Number(sale.total))) };
                state.cart = []; state.paymentEdited = false;
                App.formErrors(form); showValidation('');
                form.reset(); el('pos-date').value = App.today();
                el('pos-last-number').textContent = `${sale.number} · ${App.money(sale.total)}${Number(sale.balance) > 0 ? ` · Sisa utang ${App.money(sale.balance)}` : ' · Lunas'}`;
                el('pos-last-sale').classList.remove('d-none');
                App.toast(response.message || 'Penjualan berhasil disimpan.');
                await loadLookups();
            } catch (error) {
                const errors = error.responseJSON?.errors;
                showValidation(errors ? Object.values(errors).flat().join(' ') : 'Penjualan belum dapat dikonfirmasi. Keranjang tetap tersimpan. Gunakan Periksa & lanjutkan untuk memeriksa pengiriman sebelumnya.');
            } finally { setSaving(false); }
        });

        el('pos-date').value = App.today();
        loadLookups();
    });
})();
