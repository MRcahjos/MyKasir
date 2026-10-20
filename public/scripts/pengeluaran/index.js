'use strict';
document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('expense-page');
    const form = document.getElementById('expense-form');
    const filter = document.getElementById('expense-filter');
    const modalElement = document.getElementById('expense-modal');
    const modal = new bootstrap.Modal(modalElement);
    const paymentNames = { cash: 'Tunai', transfer: 'Transfer', qris: 'QRIS' };
    let saving = false;
    let dates = { from: App.today().slice(0, 7) + '-01', to: App.today() };

    function showFilterDates() {
        filter.elements.from.value = dates.from;
        filter.elements.to.value = dates.to;
    }
    showFilterDates();

    const table = App.table('#expense-table', {
        ajax: { url: page.dataset.endpoint, data: data => Object.assign(data, dates) },
        order: [[0, 'desc']],
        columns: [
            { data: 'date', render: value => `<span class="text-nowrap">${App.escape(App.date(value))}</span>` },
            { data: 'category', render: value => `<span class="badge badge-soft-neutral">${App.escape(value)}</span>` },
            { data: 'description', render: value => `<div class="catalog-wrap fw-medium">${App.escape(value)}</div>` },
            { data: 'payment_method', render: value => App.escape(paymentNames[value] || value) },
            { data: 'amount', className: 'text-end text-nowrap', render: value => `<strong>${App.money(value)}</strong>` },
        ],
    });

    filter.elements.from.addEventListener('input', () => filter.elements.to.setCustomValidity(''));
    filter.elements.to.addEventListener('input', () => filter.elements.to.setCustomValidity(''));
    filter.addEventListener('submit', event => {
        event.preventDefault();
        filter.elements.to.setCustomValidity(filter.elements.to.value < filter.elements.from.value ? 'Tanggal akhir harus sama atau setelah tanggal awal.' : '');
        if (!filter.reportValidity()) return;
        dates = { from: filter.elements.from.value, to: filter.elements.to.value };
        table.ajax.reload();
    });
    document.getElementById('expense-filter-reset').addEventListener('click', () => {
        dates = { from: App.today().slice(0, 7) + '-01', to: App.today() };
        filter.elements.to.setCustomValidity('');
        showFilterDates();
        table.ajax.reload();
    });
    document.getElementById('expense-new').addEventListener('click', () => {
        form.reset();
        App.formErrors(form);
        form.elements.date.value = App.today();
        modal.show();
    });
    modalElement.addEventListener('shown.bs.modal', () => form.elements.category.focus());
    modalElement.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving || !form.reportValidity()) return;
        saving = true;
        const button = document.getElementById('expense-submit');
        App.busy(button, true);
        try {
            const payload = {
                date: form.elements.date.value,
                category: form.elements.category.value.trim(),
                description: form.elements.description.value.trim(),
                amount: form.elements.amount.value,
                payment_method: form.elements.payment_method.value,
            };
            const response = await App.http('POST', page.dataset.endpoint, payload, form);
            saving = false;
            modal.hide();
            if (payload.date < dates.from || payload.date > dates.to) {
                dates = { from: payload.date.slice(0, 7) + '-01', to: payload.date };
                showFilterDates();
            }
            table.ajax.reload();
            App.toast(response.message);
        } catch (_) {
            // Shared HTTP handling keeps the form open and displays validation errors.
        } finally {
            saving = false;
            App.busy(button, false);
        }
    });
});
