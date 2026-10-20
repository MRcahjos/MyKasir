'use strict';
App.http = (method, url, data = {}, form = null) => {
    if (form) App.formErrors(form);
    return new Promise((resolve, reject) => {
        $.ajax({
            url: App.url(url), type: method, data: method.toUpperCase() === 'GET' ? data : JSON.stringify(data),
            contentType: 'application/json', dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            success: resolve,
            error: xhr => {
                const payload = xhr.responseJSON || {};
                if (xhr.status === 422 && form) App.formErrors(form, payload.errors || {});
                const message = xhr.status === 419 ? 'Sesi Anda berakhir. Muat ulang halaman, lalu coba kembali.' : xhr.status === 401 ? 'Silakan masuk kembali untuk melanjutkan.' : xhr.status === 429 ? 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.' : xhr.status >= 500 ? 'Permintaan belum dapat diproses. Silakan coba kembali.' : payload.errors ? Object.values(payload.errors).flat().join('\n') : payload.message || 'Koneksi terputus. Periksa koneksi Anda, lalu coba kembali.';
                Swal.fire({ icon: 'error', title: xhr.status === 422 ? 'Periksa isian Anda' : 'Belum berhasil', text: message, confirmButtonText: 'Mengerti', confirmButtonColor: '#166534' });
                reject(xhr);
            }
        });
    });
};
