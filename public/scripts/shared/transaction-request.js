(function () {
    'use strict';

    // Keep only an unanswered request, never an offline queue. Every retry is explicit.
    App.transactionRequest = function (scope, container) {
        const userId = document.querySelector('meta[name="app-user-id"]')?.content || 'current';
        const storageKey = `mykasir.pending.${userId}.${scope}`;
        const reviewPath = { sales: '/penjualan', purchases: '/pembelian', payments: '/piutang', 'stock-adjustments': '/stok' }[scope];
        let pending = null;
        let sending = false;
        let banner = null;
        const clone = (value) => JSON.parse(JSON.stringify(value));
        const fingerprint = (url, payload) => {
            const data = { ...payload };
            delete data.request_id;
            return JSON.stringify({ url: new URL(App.url(url), window.location.href).href, data });
        };

        try {
            const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
            if (saved?.payload?.request_id && saved.url && new URL(saved.url, window.location.href).origin === window.location.origin) {
                // A reload can interrupt the response after the server already committed.
                pending = { ...saved, attempted: true };
            }
        } catch (error) { /* Requests still remain protected in memory when storage is unavailable. */ }

        function persist() {
            try {
                if (pending) sessionStorage.setItem(storageKey, JSON.stringify(pending));
                else sessionStorage.removeItem(storageKey);
            } catch (error) { /* A disabled browser storage must not prevent checkout. */ }
        }

        function render() {
            if (!pending) { banner?.remove(); banner = null; return; }
            if (!banner) {
                banner = document.createElement('div');
                banner.className = 'transaction-pending mb-4';
                banner.setAttribute('role', 'status');
                container.prepend(banner);
            }
            banner.innerHTML = `<div><strong>${sending ? 'Sedang menyimpan transaksi…' : 'Hasil transaksi sebelumnya belum dikonfirmasi'}</strong><p class="small mb-0">${sending ? 'Tunggu sampai proses selesai sebelum menutup halaman.' : 'Data pengiriman tetap tersimpan. Periksa transaksi ini dahulu sebelum membuat transaksi lain agar tidak tercatat dua kali. Jika sesi berakhir, masuk kembali lalu periksa lagi.'}</p>${!sending && reviewPath ? `<a class="small text-reset text-decoration-underline" href="${App.escape(App.url(reviewPath))}">Lihat riwayat transaksi</a>` : ''}</div><button type="button" class="btn btn-soft btn-sm flex-shrink-0" ${sending ? 'disabled' : ''}><i class="bi bi-arrow-clockwise me-1"></i>Periksa & lanjutkan</button>`;
            banner.querySelector('button').addEventListener('click', retry);
        }

        async function dispatch(form) {
            if (sending || !pending) return;
            const hadUnconfirmedAttempt = pending.attempted === true;
            pending.attempted = true;
            sending = true; persist(); render();
            try {
                const response = await App.http('POST', pending.url, clone(pending.payload), form);
                pending = null; persist();
                return response;
            } catch (error) {
                // A timeout, interrupted connection or server failure may occur after commit.
                // A rejection only rules out this attempt. It cannot rule out an older
                // attempt whose response was lost, even when a retry is now rejected.
                if (!hadUnconfirmedAttempt && [400, 401, 403, 404, 405, 419, 422, 429].includes(Number(error.status))) {
                    pending = null; persist();
                }
                throw error;
            } finally { sending = false; render(); }
        }

        async function retry() {
            if (sending || !pending) return;
            try {
                const response = await dispatch();
                const number = response?.data?.number || response?.data?.sale?.number || response?.data?.reference;
                await Swal.fire({ icon: 'success', title: 'Transaksi sudah tercatat', text: number ? `Nomor transaksi ${number}. Data tersimpan satu kali.` : 'Data telah tercatat satu kali. Daftar akan diperbarui.', confirmButtonText: 'Lihat data terbaru', confirmButtonColor: '#166534' });
                window.location.reload();
            } catch (error) { /* App.http displays the server response. */ }
        }

        render();
        return {
            get pending() { return Boolean(pending); },
            retry,
            async send(url, payload, form) {
                if (sending) throw { status: 429, responseJSON: { errors: { request_id: ['Transaksi masih diproses. Tunggu sebentar.'] } } };
                if (pending && fingerprint(pending.url, pending.payload) !== fingerprint(url, payload)) {
                    const message = 'Periksa hasil transaksi sebelumnya melalui tombol Periksa & lanjutkan sebelum mengirim data berbeda.';
                    await Swal.fire({ icon: 'info', title: 'Selesaikan transaksi sebelumnya', text: message, confirmButtonText: 'Mengerti', confirmButtonColor: '#166534' });
                    throw { status: 409, responseJSON: { errors: { request_id: [message] } } };
                }
                if (!pending) pending = { url: App.url(url), payload: { ...clone(payload), request_id: payload.request_id || App.uuid() } };
                return dispatch(form);
            },
        };
    };
})();
