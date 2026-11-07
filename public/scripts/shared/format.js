'use strict';
window.App = window.App || {};
Object.assign(window.App, {
    money: value => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2, minimumFractionDigits: 0 }).format(Number(value) || 0),
    number: value => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(value) || 0),
    escape: value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),
    date: value => value ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(String(value).substring(0, 10) + 'T12:00:00')) : '—',
    today: () => document.querySelector('meta[name="app-today"]')?.content || new Date().toLocaleDateString('en-CA'),
    uuid: () => crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c => (Number(c) ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> Number(c) / 4).toString(16)),
    url: path => /^https?:\/\//.test(path) ? path : (document.querySelector('meta[name="app-base"]')?.content || '').replace(/\/$/, '') + '/' + path.replace(/^\//, ''),
    toast: message => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: message, showConfirmButton: false, timer: 3200, timerProgressBar: true }),
    confirm: async (title, text) => (await Swal.fire({ title, text, icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, lanjutkan', cancelButtonText: 'Kembali', confirmButtonColor: '#166534', reverseButtons: true })).isConfirmed,
    busy(button, state) { if (!button) return; if (state && !button.disabled) { button.dataset.originalHtml = button.innerHTML; button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Memproses…'; } else if (!state && button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml; button.disabled = state; },
    showDetails: (title, html) => Swal.fire({ title, html, width: 760, confirmButtonText: 'Tutup', confirmButtonColor: '#166534' }),
    formErrors(form, errors = {}) {
        if (!form) return;
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
        const messages = [];
        Object.entries(errors).forEach(([name, list]) => {
            const field = Array.from(form.elements).find(el => el.name === name || el.name === name.replace(/\.([^\.]+)/g, '[$1]'));
            if (field) { field.classList.add('is-invalid'); const note = document.createElement('div'); note.className = 'invalid-feedback d-block'; note.textContent = list[0]; field.parentElement.append(note); }
            else messages.push(list[0]);
        });
        const feedback = form.querySelector('.form-feedback');
        if (feedback) feedback.textContent = messages.join(' ');
        form.querySelector('.is-invalid')?.focus();
    }
});

(function () {
    // Display values use Indonesian punctuation; API values always use a decimal dot.
    const group = value => value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const display = value => {
        const original = String(value ?? '').trim();
        if (!original) return '';
        if (!/^-?(?:[\d.]+(?:,\d*)?|,\d*)$/.test(original)) return original;
        const compact = original.replace(/\./g, '');
        if (!/^-?(?:\d+(?:,\d*)?|,\d*)$/.test(compact)) return original;
        const negative = compact.startsWith('-');
        const [integer, fraction] = compact.replace(/^-/, '').split(',');
        return (negative ? '-' : '') + group(integer || '0') + (fraction === undefined ? '' : ',' + fraction);
    };
    const raw = value => {
        const original = String(value ?? '').trim();
        if (!original) return '';
        if (!/^-?(?:[\d.]+(?:,\d*)?|,\d*)$/.test(original)) return 'NaN';
        const compact = original.replace(/\./g, '');
        if (!/^-?(?:\d+(?:,\d{0,2})?|,\d{1,2})$/.test(compact)) return 'NaN';
        return compact.replace(/^(-?),/, (_, sign) => `${sign}0,`).replace(',', '.');
    };
    Object.assign(window.App, {
        moneyInputValue(value) {
            if (value === null || value === undefined || value === '') return '';
            const canonical = String(value);
            if (!/^-?\d+(?:\.\d*)?$/.test(canonical)) return canonical;
            const [integer, fraction] = canonical.split('.');
            return group(integer) + (fraction && !/^0+$/.test(fraction) ? ',' + fraction : '');
        },
        moneyInputRaw: raw,
        bindMoneyInput(input, { min = 0, max = 999999999999.99 } = {}) {
            input.inputMode = 'decimal';
            const refresh = event => {
                if (event?.isComposing) return;
                const previous = input.value;
                const start = input.selectionStart;
                const end = input.selectionEnd;
                const formatted = display(previous);
                if (formatted !== previous) {
                    const position = offset => {
                        let count = previous.slice(0, offset).replace(/\./g, '').length;
                        // A leading decimal comma inserts an integer zero before the caret.
                        const leadingComma = previous.match(/^(-?),/);
                        if (leadingComma && offset > leadingComma[1].length) count++;
                        let index = 0, seen = 0;
                        while (index < formatted.length && seen < count) {
                            if (formatted[index] !== '.') seen++;
                            index++;
                        }
                        // Delete should move past a regenerated separator instead of getting stuck.
                        if (event?.inputType === 'deleteContentForward' && formatted[index] === '.') index++;
                        return index;
                    };
                    input.value = formatted;
                    if (start !== null && end !== null) input.setSelectionRange(position(start), position(end));
                }
                const value = raw(input.value);
                const amount = Number(value);
                let message = '';
                if (value && !Number.isFinite(amount)) message = 'Gunakan angka dengan maksimal dua desimal setelah koma.';
                else if (value && amount < min) message = `Nominal minimal ${window.App.number(min)}.`;
                else if (value && amount > max) message = `Nominal maksimal ${window.App.number(max)}.`;
                input.setCustomValidity(message);
            };
            input.addEventListener('input', refresh);
            input.addEventListener('compositionend', refresh);
            refresh();
            return refresh;
        },
    });
})();
