(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const page = document.getElementById('receivable-page');
        if (!page) return;
        const el = (id) => document.getElementById(id);
        const form = el('payment-form');
        const modal = new bootstrap.Modal(el('payment-modal'));
        const request = App.transactionRequest('payments', page);
        const state = { sale: null, saving: false, opening: false };
        const cents = (value) => Math.round((Number(value) || 0) * 100);
        const methodName = (method) => ({ cash: 'Tunai', transfer: 'Transfer', qris: 'QRIS' }[method] || method || '—');
        const table = App.table('#receivable-table', {
            ajax: page.dataset.receivablesUrl,
            order: [[2, 'asc']],
            columns: [
                { data: 'customer_name', orderable: false, render: (data) => `<span class="transaction-customer-name">${App.escape(data || '—')}</span>` },
                { data: 'number', render: (data) => `<span class="transaction-row-code">${App.escape(data)}</span>` },
                { data: 'date', render: (data) => App.date(data) },
                { data: 'total', className: 'text-end', render: (data) => App.money(data) },
                { data: 'paid_amount', className: 'text-end', render: (data) => App.money(data) },
                { data: 'balance', className: 'text-end fw-bold text-warning', render: (data) => App.money(data) },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data) => `<div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-soft transaction-action" data-debt-detail="${Number(data)}" aria-label="Lihat rincian tagihan"><i class="bi bi-eye"></i></button><button type="button" class="btn btn-brand transaction-action" data-pay="${Number(data)}"><i class="bi bi-wallet2 me-1"></i>Catat pembayaran</button></div>` },
            ],
        });

        function validation(message) {
            el('payment-validation').textContent = message || '';
            el('payment-validation').classList.toggle('d-none', !message);
        }

        function updateBalance() {
            el('payment-after').textContent = App.money(Math.max(0, cents(state.sale?.balance) - cents(el('payment-amount').value)) / 100);
        }

        async function openPayment(id) {
            if (request.pending) {
                await Swal.fire({ icon: 'info', title: 'Periksa pembayaran sebelumnya', text: 'Gunakan tombol Periksa & lanjutkan sebelum mencatat pembayaran lain.', confirmButtonText: 'Mengerti', confirmButtonColor: '#166534' });
                return;
            }
            const response = await App.http('GET', `${page.dataset.salesUrl}/${id}`);
            const sale = response.data;
            if (Number(sale.balance) <= 0) { table.ajax.reload(null, false); App.toast('Tagihan ini sudah lunas.'); return; }
            state.sale = sale;
            form.reset(); App.formErrors(form, {}); validation('');
            el('payment-customer').textContent = sale.customer?.name || sale.customer_name || 'Pelanggan';
            el('payment-number').textContent = `${sale.number} · ${App.date(sale.date)}`;
            el('payment-balance').textContent = App.money(sale.balance);
            el('payment-amount').max = sale.balance;
            el('payment-amount').value = Number(sale.balance);
            el('payment-date').min = String(sale.date).slice(0, 10);
            el('payment-date').value = App.today() < el('payment-date').min ? el('payment-date').min : App.today();
            updateBalance(); modal.show();
        }

        function detailHtml(sale) {
            return `<div class="detail-receipt"><div class="receipt-top"><div><h3>${App.escape(sale.customer?.name || sale.customer_name || 'Pelanggan')}</h3><p>${App.escape(sale.number)} · ${App.date(sale.date)}</p></div><span class="${Number(sale.balance) <= 0 ? 'badge-soft-success' : 'badge-soft-warning'} align-self-start">${Number(sale.balance) <= 0 ? 'Lunas' : Number(sale.paid_amount) > 0 ? 'Dibayar sebagian' : 'Belum dibayar'}</span></div><div class="receipt-overflow"><table class="receipt-table"><thead><tr><th>Barang</th><th>Jumlah</th><th>Subtotal</th></tr></thead><tbody>${(sale.items || []).map((item) => `<tr><td><div class="item-name">${App.escape(item.product_name)}</div><div class="item-unit">${App.money(item.unit_price)} / ${App.escape(item.unit_name)}</div></td><td>${App.number(item.quantity)} ${App.escape(item.unit_name)}</td><td>${App.money(item.subtotal)}</td></tr>`).join('')}</tbody></table></div><div class="receipt-totals"><div class="receipt-total-line"><span>Total belanja</span><span>${App.money(sale.total)}</span></div><div class="receipt-total-line"><span>Sudah dibayar</span><span>${App.money(sale.paid_amount)}</span></div><div class="receipt-total-line is-total"><span>Sisa utang</span><span>${App.money(sale.balance)}</span></div></div><div class="receipt-payments"><h4>Riwayat pembayaran</h4>${sale.payments?.length ? `<table class="receipt-table"><thead><tr><th>Tanggal</th><th>Metode</th><th>Nominal</th></tr></thead><tbody>${sale.payments.map((payment) => `<tr><td>${App.date(payment.date)}${payment.notes ? `<div class="item-unit">${App.escape(payment.notes)}</div>` : ''}</td><td>${App.escape(methodName(payment.method))}</td><td>${App.money(payment.amount)}</td></tr>`).join('')}</tbody></table>` : '<p class="text-muted small mb-0">Belum ada pembayaran untuk tagihan ini.</p>'}</div>${sale.notes ? `<div class="receipt-note">${App.escape(sale.notes)}</div>` : ''}</div>`;
        }

        el('payment-amount').addEventListener('input', updateBalance);
        el('payment-full').addEventListener('click', () => { if (state.sale) { el('payment-amount').value = Number(state.sale.balance); updateBalance(); } });
        el('receivable-refresh').addEventListener('click', () => table.ajax.reload(null, false));
        el('payment-modal').addEventListener('hide.bs.modal', (event) => { if (state.saving) event.preventDefault(); });
        el('payment-modal').addEventListener('shown.bs.modal', () => { el('payment-amount').focus(); el('payment-amount').select(); });
        el('receivable-table').addEventListener('click', async (event) => {
            const button = event.target.closest('[data-pay],[data-debt-detail]');
            if (!button || state.saving || state.opening) return;
            state.opening = true;
            App.busy(button, true);
            try {
                if (button.dataset.pay) await openPayment(button.dataset.pay);
                else {
                    const response = await App.http('GET', `${page.dataset.salesUrl}/${button.dataset.debtDetail}`);
                    App.showDetails('Rincian utang pelanggan', detailHtml(response.data));
                }
            } catch (error) { /* App.http displays the error. */ }
            finally { state.opening = false; App.busy(button, false); }
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (state.saving || !state.sale) return;
            const amount = cents(el('payment-amount').value) / 100;
            let message = '';
            if (!Number.isFinite(Number(el('payment-amount').value)) || amount <= 0) message = 'Masukkan jumlah pembayaran lebih dari nol.';
            else if (cents(amount) > cents(state.sale.balance)) message = 'Pembayaran tidak boleh melebihi sisa utang.';
            else if (!el('payment-date').value) message = 'Tanggal pembayaran wajib diisi.';
            else if (el('payment-date').value < String(state.sale.date).slice(0, 10)) message = 'Tanggal pembayaran tidak boleh sebelum tanggal penjualan.';
            validation(message); if (message) return;
            state.saving = true;
            try {
                if (!await App.confirm('Catat pembayaran pelanggan?', `${App.money(amount)} untuk tagihan ${state.sale.number}. Sisa utang setelah pembayaran ${App.money((cents(state.sale.balance) - cents(amount)) / 100)}.`)) return;
                App.busy(el('payment-submit'), true);
                form.querySelectorAll('input,select,textarea,button').forEach((control) => { control.disabled = true; });
                const payload = { amount, date: el('payment-date').value, method: el('payment-method').value, notes: el('payment-notes').value.trim() || null };
                const response = await request.send(`${page.dataset.receivablesUrl}/${state.sale.id}/payments`, payload, form);
                state.saving = false;
                modal.hide(); table.ajax.reload(null, false);
                App.toast(response.message || 'Pembayaran berhasil dicatat.');
            } catch (error) {
                const errors = error.responseJSON?.errors;
                validation(errors ? Object.values(errors).flat().join(' ') : 'Pembayaran belum dapat dikonfirmasi. Coba kembali dengan data yang sama atau tutup formulir dan pilih Periksa & lanjutkan.');
            } finally {
                state.saving = false;
                form.querySelectorAll('input,select,textarea,button').forEach((control) => { control.disabled = false; });
                App.busy(el('payment-submit'), false);
            }
        });
    });
})();
