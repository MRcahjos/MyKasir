(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const page = document.getElementById('sales-page');
        if (!page) return;
        const el = (id) => document.getElementById(id);
        const methodName = (method) => ({ cash: 'Tunai', transfer: 'Transfer', qris: 'QRIS' }[method] || method || '—');
        let currentSale = null;
        const statusBadge = (sale) => Number(sale.balance) <= 0 ? '<span class="badge-soft-success">Lunas</span>' : Number(sale.paid_amount) > 0 ? '<span class="badge-soft-warning">Dibayar sebagian</span>' : '<span class="badge-soft-danger">Belum dibayar</span>';
        const table = App.table('#sales-table', {
            ajax: { url: page.dataset.salesUrl, data: (data) => { data.from = el('sales-from').value; data.to = el('sales-to').value; } },
            order: [[1, 'desc']],
            columns: [
                { data: 'number', render: (data) => `<span class="transaction-row-code">${App.escape(data)}</span>` },
                { data: 'date', render: (data) => App.date(data) },
                { data: 'customer_name', orderable: false, render: (data) => App.escape(data || 'Pembeli umum') },
                { data: 'total', className: 'text-end fw-semibold', render: (data) => App.money(data) },
                { data: 'paid_amount', className: 'text-end', render: (data) => App.money(data) },
                { data: 'status', orderable: false, render: (data, type, row) => statusBadge(row) },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data) => `<button type="button" class="btn btn-soft transaction-action" data-sale-detail="${Number(data)}"><i class="bi bi-receipt me-1"></i>Lihat struk</button>` },
            ],
        });

        function receiptHtml(sale, withButton) {
            return `<div class="detail-receipt"><div class="receipt-top"><div><h3>MyKasir</h3><p>Bukti penjualan · ${App.escape(sale.number)}</p></div><div>${statusBadge(sale)}</div></div><dl class="receipt-meta"><div><dt>Tanggal</dt><dd>${App.date(sale.date)}</dd></div><div><dt>Pelanggan</dt><dd>${App.escape(sale.customer?.name || sale.customer_name || 'Pembeli umum')}</dd></div><div><dt>Metode pembayaran awal</dt><dd>${App.escape(methodName(sale.payment_method))}</dd></div></dl><div class="receipt-overflow"><table class="receipt-table"><thead><tr><th>Barang</th><th>Jumlah</th><th>Subtotal</th></tr></thead><tbody>${(sale.items || []).map((item) => `<tr><td><div class="item-name">${App.escape(item.product_name)}</div><div class="item-unit">${App.money(item.unit_price)} / ${App.escape(item.unit_name)}</div></td><td>${App.number(item.quantity)} ${App.escape(item.unit_name)}</td><td>${App.money(item.subtotal)}</td></tr>`).join('')}</tbody></table></div><div class="receipt-totals"><div class="receipt-total-line is-total"><span>Total belanja</span><span>${App.money(sale.total)}</span></div><div class="receipt-total-line"><span>Sudah dibayar</span><span>${App.money(sale.paid_amount)}</span></div>${Number(sale.change_amount) > 0 ? `<div class="receipt-total-line"><span>Kembalian saat transaksi</span><span>${App.money(sale.change_amount)}</span></div>` : ''}<div class="receipt-total-line"><span>Sisa utang</span><strong>${App.money(sale.balance)}</strong></div></div><div class="receipt-payments"><h4>Riwayat pembayaran</h4>${sale.payments?.length ? `<table class="receipt-table"><thead><tr><th>Tanggal</th><th>Metode</th><th>Nominal</th></tr></thead><tbody>${sale.payments.map((payment) => `<tr><td>${App.date(payment.date)}${payment.notes ? `<div class="item-unit">${App.escape(payment.notes)}</div>` : ''}</td><td>${App.escape(methodName(payment.method))}</td><td>${App.money(payment.amount)}</td></tr>`).join('')}</tbody></table>` : '<p class="small text-muted mb-0">Belum ada pembayaran.</p>'}</div>${sale.notes ? `<div class="receipt-note">${App.escape(sale.notes)}</div>` : ''}<p class="small text-muted text-center mt-4 mb-0">Terima kasih sudah berbelanja.</p>${withButton ? '<div class="print-action-row"><button type="button" class="btn btn-brand btn-sm" data-receipt-print><i class="bi bi-printer me-1"></i>Cetak struk</button></div>' : ''}</div>`;
        }

        async function reloadWithDates() {
            const from = el('sales-from').value;
            const to = el('sales-to').value;
            if (from && to && from > to) { await Swal.fire({ icon: 'info', title: 'Periksa tanggal', text: 'Tanggal awal tidak boleh melewati tanggal akhir.', confirmButtonText: 'Mengerti' }); return; }
            table.ajax.reload();
        }

        function printReceipt() {
            if (!currentSale) return;
            let target = document.querySelector('.receipt-print-root');
            if (!target) { target = document.createElement('div'); target.className = 'receipt-print-root'; document.body.appendChild(target); }
            target.innerHTML = receiptHtml(currentSale, false);
            document.body.classList.add('receipt-print-mode');
            window.print();
        }

        window.addEventListener('afterprint', () => document.body.classList.remove('receipt-print-mode'));
        document.addEventListener('click', (event) => { if (event.target.closest('[data-receipt-print]')) printReceipt(); });
        el('sales-from').addEventListener('change', reloadWithDates);
        el('sales-to').addEventListener('change', reloadWithDates);
        el('sales-reset').addEventListener('click', () => { el('sales-from').value = ''; el('sales-to').value = ''; table.ajax.reload(); });
        el('sales-table').addEventListener('click', async (event) => {
            const button = event.target.closest('[data-sale-detail]');
            if (!button) return;
            App.busy(button, true);
            try {
                const response = await App.http('GET', `${page.dataset.salesUrl}/${button.dataset.saleDetail}`);
                currentSale = response.data;
                App.showDetails('Rincian penjualan', receiptHtml(currentSale, true));
            } catch (error) { /* App.http displays the error. */ }
            finally { App.busy(button, false); }
        });
    });
})();
