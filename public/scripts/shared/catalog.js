'use strict';
App.mountContactCatalog = () => {
    const page = document.getElementById('contact-page');
    const form = document.getElementById('contact-form');
    const modalElement = document.getElementById('contact-modal');
    const modal = new bootstrap.Modal(modalElement);
    const isCustomer = page.dataset.kind === 'customer';
    let editingId = null;
    let saving = false;
    const columns = [
        { data: 'name', render: value => `<div class="catalog-person"><span class="catalog-person-icon"><i class="bi ${isCustomer ? 'bi-person' : 'bi-building'}"></i></span><strong>${App.escape(value)}</strong></div>` },
        { data: 'phone', render: value => App.escape(value || '—') },
        { data: 'address', render: value => `<div class="catalog-wrap text-muted small">${App.escape(value || 'Belum ditambahkan')}</div>` },
    ];
    if (isCustomer) columns.push({ data: 'outstanding', render: value => Number(value) > 0 ? `<a href="${App.escape(page.dataset.receivables)}" class="fw-semibold text-danger text-decoration-none">${App.money(value)} <i class="bi bi-arrow-up-right small ms-1"></i></a>` : '<span class="badge badge-soft-success"><i class="bi bi-check2 me-1"></i>Tidak ada utang</span>' });
    columns.push({ data: null, orderable: false, searchable: false, render: () => '<button type="button" class="btn btn-soft btn-sm contact-edit"><i class="bi bi-pencil me-1"></i>Edit</button>' });
    const table = App.table('#contact-table', { ajax: page.dataset.endpoint, order: [[0, 'asc']], columns });
    function openForm(contact = null) {
        form.reset();
        App.formErrors(form);
        editingId = contact?.id || null;
        document.getElementById('contact-modal-title').textContent = `${contact ? 'Edit' : 'Tambah'} ${page.dataset.label}`;
        ['name', 'phone', 'address'].forEach(name => form.elements[name].value = contact?.[name] || '');
        modal.show();
    }
    document.getElementById('contact-new').addEventListener('click', () => openForm());
    $('#contact-table').on('click', '.contact-edit', function () { openForm(table.row(this.closest('tr')).data()); });
    modalElement.addEventListener('shown.bs.modal', () => form.elements.name.focus());
    modalElement.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving || !form.reportValidity()) return;
        saving = true;
        const button = document.getElementById('contact-submit');
        App.busy(button, true);
        try {
            const data = { name: form.elements.name.value.trim(), phone: form.elements.phone.value.trim() || null, address: form.elements.address.value.trim() || null };
            const result = await App.http(editingId ? 'PUT' : 'POST', page.dataset.endpoint + (editingId ? `/${editingId}` : ''), data, form);
            saving = false;
            modal.hide();
            table.ajax.reload(null, false);
            App.toast(result.message);
        } catch (_) {} finally { saving = false; App.busy(button, false); }
    });
};
