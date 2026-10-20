'use strict';
document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('product-page');
    const form = document.getElementById('product-form');
    const modalElement = document.getElementById('product-modal');
    const modal = new bootstrap.Modal(modalElement);
    const unitList = document.getElementById('product-units');
    let editingId = null;
    let unitCounter = 0;
    let saving = false;
    let conversionLocked = false;
    const text = value => App.escape(value);
    const table = App.table('#product-table', { ajax: page.dataset.endpoint, order: [[1, 'asc']], columns: [
        { data: 'sku', render: value => `<span class="catalog-table-code">${text(value)}</span>` },
        { data: 'name', render: (value, type, row) => `<strong>${text(value)}</strong><div class="text-muted small mt-1">${text(row.category || 'Tanpa kategori')}</div>` },
        { data: 'units', orderable: false, searchable: false, render: units => (units || []).map(unit => `<div class="catalog-unit-price"><span>${text(unit.name)}${unit.multiplier > 1 ? ` (${App.number(unit.multiplier)} satuan)` : ''}</span><strong>${App.money(unit.selling_price)}</strong></div>`).join('') },
        { data: 'stock', render: (value, type, row) => {
            const box = [...(row.units || [])].filter(unit => unit.multiplier > 1).sort((a, b) => b.multiplier - a.multiplier)[0];
            const equivalent = box && value >= box.multiplier ? `<div class="text-muted small mt-1">Setara ${App.number(Math.floor(value / box.multiplier))} ${text(box.name)} + ${App.number(value % box.multiplier)} ${text(row.base_unit)}</div>` : '';
            return `<span class="${value <= row.minimum_stock ? 'text-danger' : 'text-success'} fw-semibold">${App.number(value)} ${text(row.base_unit)}</span>${equivalent}${value <= row.minimum_stock ? '<div class="small text-muted">Perlu diisi kembali</div>' : ''}`;
        } },
        { data: 'active', render: value => `<span class="badge ${value ? 'badge-soft-success' : 'badge-soft-neutral'}">${value ? 'Aktif' : 'Nonaktif'}</span>` },
        { data: null, orderable: false, searchable: false, render: () => '<button class="btn btn-soft btn-sm product-edit" type="button"><i class="bi bi-pencil me-1"></i>Edit</button>' },
    ] });

    function renumber() {
        unitList.querySelectorAll('.catalog-unit').forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach(input => input.name = `units[${index}][${input.dataset.field}]`);
        });
        const addButton = document.getElementById('product-add-unit');
        addButton.disabled = unitList.children.length >= 20;
        addButton.title = addButton.disabled ? 'Maksimal 20 satuan untuk satu barang.' : '';
    }
    function addUnit(unit = {}, isBase = false) {
        const key = ++unitCounter;
        const row = document.createElement('div');
        row.className = 'catalog-unit';
        row.dataset.base = isBase ? 'true' : 'false';
        if (unit.id) row.dataset.id = unit.id;
        const locked = Boolean(unit.id) && conversionLocked;
        row.innerHTML = `<div class="catalog-unit-head"><strong>${isBase ? '<i class="bi bi-dot me-1"></i>Satuan terkecil' : '<i class="bi bi-box-seam me-1"></i>Satuan kemasan'}</strong>${isBase ? '<span class="badge badge-soft-success">Dasar</span>' : locked ? '<span class="badge badge-soft-neutral"><i class="bi bi-lock me-1"></i>Isi tetap</span>' : '<button class="btn btn-sm btn-light unit-remove" type="button" aria-label="Hapus satuan kemasan"><i class="bi bi-trash3"></i></button>'}</div><div class="catalog-unit-grid"><div><label class="form-label" for="unit-name-${key}">Nama satuan</label><input class="form-control" id="unit-name-${key}" data-field="name" maxlength="40" required value="${text(unit.name || '')}" ${isBase ? 'readonly' : ''} placeholder="Dus"></div><div><label class="form-label" for="unit-multiplier-${key}">Isi satuan dasar</label><input type="number" class="form-control" id="unit-multiplier-${key}" data-field="multiplier" min="${isBase ? 1 : 2}" max="1000000" step="1" required value="${text(unit.multiplier || (isBase ? 1 : ''))}" ${isBase || locked ? 'readonly' : ''} placeholder="20"></div><div><label class="form-label" for="unit-price-${key}">Harga jual (Rp)</label><input type="number" class="form-control" id="unit-price-${key}" data-field="selling_price" min="0.01" max="999999999999.99" step="0.01" required value="${text(unit.selling_price ?? '')}" placeholder="17000"></div></div>`;
        unitList.append(row);
        renumber();
    }
    function openForm(product = null) {
        form.reset();
        App.formErrors(form);
        editingId = product?.id || null;
        conversionLocked = Boolean(product?.conversion_locked || product?.movements_exists || Number(product?.stock || 0) !== 0);
        document.getElementById('product-modal-title').textContent = product ? 'Edit barang' : 'Tambah barang';
        ['name', 'sku', 'category', 'base_unit', 'minimum_stock'].forEach(name => form.elements[name].value = product?.[name] ?? (name === 'minimum_stock' ? '0' : ''));
        form.elements.active.checked = product ? Boolean(product.active) : true;
        form.elements.base_unit.readOnly = conversionLocked;
        document.getElementById('product-conversion-note').textContent = conversionLocked ? 'Isi satuan dan satuan terkecil terkunci karena sudah ada riwayat stok. Anda tetap bisa mengubah harga jual atau menambah satuan baru.' : 'Isi kemasan yang sudah memiliki stok atau riwayat transaksi tidak dapat diubah. Harga jual tetap bisa diperbarui.';
        unitList.replaceChildren();
        const units = product ? [...(product.units || [])].sort((a, b) => a.multiplier - b.multiplier) : [{ name: '', multiplier: 1, selling_price: '' }];
        units.forEach((unit, index) => addUnit(unit, index === 0 && Number(unit.multiplier) === 1));
        document.getElementById('product-stock-note').textContent = product ? `Stok saat ini: ${App.number(product.stock)} ${product.base_unit}. Perubahan stok dicatat melalui Pembelian, Kasir, atau menu Stok.` : 'Barang baru dimulai dengan stok 0. Catat pembelian atau stok awal melalui menu Stok setelah barang disimpan.';
        modal.show();
    }
    document.getElementById('product-new').addEventListener('click', () => openForm());
    $('#product-table').on('click', '.product-edit', function () { openForm(table.row(this.closest('tr')).data()); });
    document.getElementById('product-add-unit').addEventListener('click', () => {
        if (unitList.children.length >= 20) return;
        addUnit();
        unitList.lastElementChild.querySelector('input').focus();
    });
    unitList.addEventListener('click', event => { if (event.target.closest('.unit-remove')) { event.target.closest('.catalog-unit').remove(); renumber(); } });
    form.elements.base_unit.addEventListener('input', event => { const input = unitList.querySelector('[data-base="true"] [data-field="name"]'); if (input) input.value = event.target.value; });
    modalElement.addEventListener('shown.bs.modal', () => form.elements.name.focus());
    modalElement.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving || !form.reportValidity()) return;
        const data = Object.fromEntries(new FormData(form));
        const payload = { sku: data.sku.trim(), name: data.name.trim(), category: data.category.trim() || null, base_unit: data.base_unit.trim(), minimum_stock: Number(data.minimum_stock), active: form.elements.active.checked,
            units: Array.from(unitList.children).map(row => ({ ...(row.dataset.id ? { id: Number(row.dataset.id) } : {}), name: row.querySelector('[data-field="name"]').value.trim(), multiplier: Number(row.querySelector('[data-field="multiplier"]').value), selling_price: row.querySelector('[data-field="selling_price"]').value })) };
        saving = true;
        const button = document.getElementById('product-submit');
        App.busy(button, true);
        try {
            const response = await App.http(editingId ? 'PUT' : 'POST', page.dataset.endpoint + (editingId ? `/${editingId}` : ''), payload, form);
            saving = false;
            modal.hide();
            table.ajax.reload(null, false);
            App.toast(response.message);
        } catch (_) {} finally { saving = false; App.busy(button, false); }
    });
});
