(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const page = document.getElementById('purchase-page');
        if (!page) return;
        const el = (id) => document.getElementById(id);
        const form = el('purchase-form');
        const modal = new bootstrap.Modal(el('purchase-modal'));
        const productSelect = $(el('purchase-product')).select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $(el('purchase-modal')),
            placeholder: 'Cari nama atau kode barang...',
            minimumResultsForSearch: 0,
            language: { noResults: () => 'Barang tidak ditemukan.' },
        });
        productSelect.on('select2:open', function () {
            const search = el('purchase-modal').querySelector('.select2-search--dropdown .select2-search__field');
            if (search) {
                search.placeholder = 'Ketik nama atau kode barang...';
                search.setAttribute('aria-label', 'Cari nama atau kode barang');
                search.focus();
            }
        });
        const request = App.transactionRequest('purchases', page);
        const state = { products: [], items: [], saving: false, opening: false };
        const cents = (value) => Math.round((Number(value) || 0) * 100);
        const productById = (id) => state.products.find((item) => String(item.id) === String(id));
        const unitFor = (line) => productById(line.product_id).units.find((unit) => String(unit.id) === String(line.unit_id));
        const total = () => state.items.reduce((sum, item) => sum + cents(item.unit_price) * Number(item.quantity), 0) / 100;
        const table = App.table('#purchase-table', {
            ajax: { url: page.dataset.purchasesUrl, data: (data) => { data.from = el('purchase-from').value; data.to = el('purchase-to').value; } },
            order: [[1, 'desc']],
            columns: [
                { data: 'number', render: (data) => `<span class="transaction-row-code">${App.escape(data)}</span>` },
                { data: 'date', render: (data) => App.date(data) },
                { data: 'supplier_name', orderable: false, render: (data) => App.escape(data) },
                { data: 'total', className: 'text-end fw-semibold', render: (data) => App.money(data) },
                { data: null, orderable: false, searchable: false, render: () => '<span class="badge-soft-success">Lunas · diterima</span>' },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data) => `<button type="button" class="btn btn-soft transaction-action" data-purchase-detail="${Number(data)}"><i class="bi bi-eye me-1"></i>Lihat</button>` },
            ],
        });

        function validation(message) {
            el('purchase-validation').textContent = message || '';
            el('purchase-validation').classList.toggle('d-none', !message);
        }

        function renderItems() {
            if (!state.items.length) el('purchase-items').innerHTML = '<tr><td colspan="6"><div class="empty-state py-4"><i class="bi bi-box-seam fs-2 d-block mb-2"></i>Belum ada barang. Pilih barang untuk mencatat kulakan.</div></td></tr>';
            else el('purchase-items').innerHTML = state.items.map((line, index) => {
                const product = productById(line.product_id);
                const unit = unitFor(line);
                return `<tr data-index="${index}"><td><strong>${App.escape(product.name)}</strong><div class="transaction-microcopy">${App.escape(product.sku)} · +${App.number(Number(line.quantity) * Number(unit.multiplier))} ${App.escape(product.base_unit)}</div></td><td><select class="form-select form-select-sm" data-field="unit_id" aria-label="Satuan ${App.escape(product.name)}">${product.units.map((item) => `<option value="${Number(item.id)}" ${String(item.id) === String(line.unit_id) ? 'selected' : ''}>${App.escape(item.name)}${Number(item.multiplier) > 1 ? ` (isi ${App.number(item.multiplier)})` : ''}</option>`).join('')}</select></td><td><input class="form-control form-control-sm" type="number" min="1" max="1000000" step="1" data-field="quantity" value="${Number(line.quantity)}" aria-label="Jumlah ${App.escape(product.name)}"></td><td><div class="input-group input-group-sm"><span class="input-group-text">Rp</span><input class="form-control" type="number" min="0.01" step="0.01" data-field="unit_price" value="${Number(line.unit_price)}" aria-label="Harga beli per ${App.escape(unit.name)}"></div></td><td class="text-end fw-semibold text-nowrap" data-subtotal>${App.money(cents(line.unit_price) * Number(line.quantity) / 100)}</td><td><button type="button" class="cart-remove" data-remove="${index}" aria-label="Hapus ${App.escape(product.name)}"><i class="bi bi-trash3"></i></button></td></tr>`;
            }).join('');
            el('purchase-total').textContent = App.money(total());
        }

        async function openNew() {
            if (state.saving || state.opening) return;
            if (request.pending) {
                await Swal.fire({ icon: 'info', title: 'Periksa pembelian sebelumnya', text: 'Gunakan tombol Periksa & lanjutkan sebelum mencatat pembelian lain.', confirmButtonText: 'Mengerti', confirmButtonColor: '#166534' });
                return;
            }
            state.opening = true;
            App.busy(el('purchase-new'), true);
            try {
                const response = await App.http('GET', page.dataset.lookupsUrl);
                state.products = response.data.products || [];
                state.items = [];
                form.reset(); App.formErrors(form, {}); validation('');
                el('purchase-date').value = App.today();
                el('purchase-supplier').innerHTML = '<option value="">Pilih supplier</option>' + (response.data.suppliers || []).map((item) => `<option value="${Number(item.id)}">${App.escape(item.name)}</option>`).join('');
                el('purchase-product').innerHTML = '<option value="">Pilih barang dari katalog</option>' + state.products.map((item) => `<option value="${Number(item.id)}">${App.escape(item.name)} · ${App.escape(item.sku)}</option>`).join('');
                productSelect.val('').trigger('change.select2');
                renderItems(); modal.show();
                if (!state.products.length || !response.data.suppliers?.length) validation('Tambahkan data barang dan supplier terlebih dahulu sebelum mencatat pembelian.');
            } finally { state.opening = false; App.busy(el('purchase-new'), false); }
        }

        function addItem() {
            if (state.saving) return;
            const product = productById(el('purchase-product').value);
            if (!product || !product.units?.length) { validation('Pilih barang yang ingin ditambahkan.'); return; }
            const unit = [...product.units].sort((a, b) => Number(b.multiplier) - Number(a.multiplier))[0];
            const existing = state.items.find((item) => String(item.product_id) === String(product.id) && String(item.unit_id) === String(unit.id));
            if (existing) existing.quantity += 1;
            else state.items.push({ product_id: product.id, unit_id: unit.id, quantity: 1, unit_price: cents(Number(product.average_cost || 0) * Number(unit.multiplier)) / 100 });
            productSelect.val('').trigger('change.select2'); validation(''); renderItems();
        }

        function detailHtml(purchase) {
            return `<div class="detail-receipt"><div class="receipt-top"><div><h3>${App.escape(purchase.number)}</h3><p>Pembelian barang · ${App.date(purchase.date)}</p></div><span class="badge-soft-success align-self-start">Lunas · diterima</span></div><dl class="receipt-meta"><div><dt>Supplier</dt><dd>${App.escape(purchase.supplier?.name || purchase.supplier_name || '—')}</dd></div><div><dt>Jumlah jenis barang</dt><dd>${App.number(purchase.items?.length || 0)} baris</dd></div></dl><div class="receipt-overflow"><table class="receipt-table"><thead><tr><th>Barang</th><th>Jumlah</th><th>Harga beli</th><th>Subtotal</th></tr></thead><tbody>${(purchase.items || []).map((item) => `<tr><td><div class="item-name">${App.escape(item.product_name)}</div><div class="item-unit">1 ${App.escape(item.unit_name)} = ${App.number(item.multiplier)} satuan dasar</div></td><td>${App.number(item.quantity)} ${App.escape(item.unit_name)}</td><td>${App.money(item.unit_price)}</td><td>${App.money(item.subtotal)}</td></tr>`).join('')}</tbody></table></div><div class="receipt-totals"><div class="receipt-total-line is-total"><span>Total pembelian</span><span>${App.money(purchase.total)}</span></div></div>${purchase.notes ? `<div class="receipt-note">${App.escape(purchase.notes)}</div>` : ''}</div>`;
        }

        async function reloadWithDates() {
            const from = el('purchase-from').value;
            const to = el('purchase-to').value;
            if (from && to && from > to) { await Swal.fire({ icon: 'info', title: 'Periksa tanggal', text: 'Tanggal awal tidak boleh melewati tanggal akhir.', confirmButtonText: 'Mengerti' }); return; }
            table.ajax.reload();
        }

        el('purchase-new').addEventListener('click', () => openNew().catch(() => {}));
        el('purchase-add').addEventListener('click', addItem);
        el('purchase-from').addEventListener('change', reloadWithDates);
        el('purchase-to').addEventListener('change', reloadWithDates);
        el('purchase-reset').addEventListener('click', () => { el('purchase-from').value = ''; el('purchase-to').value = ''; table.ajax.reload(); });
        el('purchase-modal').addEventListener('hide.bs.modal', (event) => {
            if (state.saving) event.preventDefault();
            else productSelect.select2('close');
        });
        el('purchase-items').addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove]');
            if (!button || state.saving) return;
            state.items.splice(Number(button.dataset.remove), 1); renderItems();
        });
        el('purchase-items').addEventListener('change', (event) => {
            const input = event.target.closest('[data-field]');
            if (!input || state.saving) return;
            const line = state.items[Number(input.closest('[data-index]').dataset.index)];
            const field = input.dataset.field;
            if (field === 'unit_id') {
                const oldMultiplier = Number(unitFor(line).multiplier);
                line.unit_id = Number(input.value);
                line.unit_price = cents(Number(line.unit_price) / oldMultiplier * Number(unitFor(line).multiplier)) / 100;
                renderItems();
            } else {
                line[field] = input.value === '' ? 0 : Number(input.value);
                if (field === 'quantity') renderItems();
                else {
                    input.closest('tr').querySelector('[data-subtotal]').textContent = App.money(cents(line.unit_price) * Number(line.quantity) / 100);
                    el('purchase-total').textContent = App.money(total());
                }
            }
            validation('');
        });
        el('purchase-items').addEventListener('input', (event) => {
            const input = event.target.closest('[data-field="unit_price"], [data-field="quantity"]');
            if (!input || state.saving) return;
            const line = state.items[Number(input.closest('[data-index]').dataset.index)];
            line[input.dataset.field] = Number(input.value) || 0;
            input.closest('tr').querySelector('[data-subtotal]').textContent = App.money(cents(line.unit_price) * Number(line.quantity) / 100);
            el('purchase-total').textContent = App.money(total());
        });
        el('purchase-table').addEventListener('click', async (event) => {
            const button = event.target.closest('[data-purchase-detail]');
            if (!button) return;
            App.busy(button, true);
            try { const response = await App.http('GET', `${page.dataset.purchasesUrl}/${button.dataset.purchaseDetail}`); App.showDetails('Rincian pembelian', detailHtml(response.data)); }
            catch (error) { /* App.http displays the error. */ }
            finally { App.busy(button, false); }
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (state.saving) return;
            let message = '';
            if (!el('purchase-supplier').value) message = 'Pilih supplier terlebih dahulu.';
            else if (!el('purchase-date').value) message = 'Isi tanggal penerimaan barang.';
            else if (!state.items.length) message = 'Tambahkan setidaknya satu barang.';
            else if (state.items.length > 100) message = 'Satu pembelian maksimal berisi 100 baris barang.';
            else if (state.items.some((item) => !Number.isInteger(Number(item.quantity)) || Number(item.quantity) < 1 || Number(item.quantity) > 1000000)) message = 'Jumlah barang harus berupa bilangan bulat antara 1 dan 1.000.000.';
            else if (state.items.some((item) => !Number.isFinite(Number(item.unit_price)) || Number(item.unit_price) <= 0)) message = 'Isi harga beli setiap barang, lebih dari nol.';
            validation(message); if (message) return;
            state.saving = true;
            try {
                if (!await App.confirm('Simpan pembelian ini?', `Total ${App.money(total())}. Barang dinyatakan sudah diterima dan dibayar lunas. Stok akan bertambah.`)) return;
                App.busy(el('purchase-submit'), true);
                form.querySelectorAll('input,select,textarea,button').forEach((control) => { control.disabled = true; });
                const payload = { supplier_id: Number(el('purchase-supplier').value), date: el('purchase-date').value, items: state.items.map((item) => ({ product_id: Number(item.product_id), unit_id: Number(item.unit_id), quantity: Number(item.quantity), unit_price: cents(item.unit_price) / 100 })), notes: el('purchase-notes').value.trim() || null };
                const response = await request.send(page.dataset.purchasesUrl, payload, form);
                state.saving = false;
                modal.hide(); table.ajax.reload(null, false); App.toast(response.message || 'Pembelian tersimpan dan stok bertambah.');
            } catch (error) {
                const errors = error.responseJSON?.errors;
                validation(errors ? Object.values(errors).flat().join(' ') : 'Pembelian belum dapat dikonfirmasi. Data tetap tersimpan; coba lagi dengan data yang sama atau tutup formulir dan pilih Periksa & lanjutkan.');
            } finally {
                state.saving = false;
                form.querySelectorAll('input,select,textarea,button').forEach((control) => { control.disabled = false; });
                App.busy(el('purchase-submit'), false);
            }
        });
    });
})();
