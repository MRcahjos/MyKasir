'use strict';
App.table = (selector, options) => {
    const ajax = typeof options.ajax === 'string' ? {url: App.url(options.ajax)} : {...options.ajax};
    if (ajax.url) ajax.url = App.url(ajax.url);
    ajax.error = () => Swal.fire({ icon: 'error', title: 'Data belum dapat dimuat', text: 'Periksa koneksi Anda atau muat ulang halaman.', confirmButtonText: 'Mengerti', confirmButtonColor: '#166534' });
    return $(selector).DataTable({ processing: true, serverSide: true, searchDelay: 350, pageLength: 10, lengthMenu: [10,25,50], autoWidth: false, scrollX: true,
        language: { search: '', searchPlaceholder: 'Cari di tabel…', lengthMenu: 'Tampilkan _MENU_', info: '_START_–_END_ dari _TOTAL_ data', infoEmpty: 'Belum ada data', infoFiltered: '(dari _MAX_ data)', emptyTable: '<div class="empty-state"><i class="bi bi-inbox"></i><strong>Belum ada data</strong><span>Data yang Anda catat akan muncul di sini.</span></div>', zeroRecords: 'Tidak ada data yang sesuai pencarian.', processing: '<span class="spinner-border spinner-border-sm me-2"></span>Memuat data…', paginate: { first: 'Awal', last: 'Akhir', next: '›', previous: '‹' } }, ...options, ajax });
};
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar-wrapper');
    if (sidebar && window.OverlayScrollbarsGlobal) OverlayScrollbarsGlobal.OverlayScrollbars(sidebar, { scrollbars: { theme: 'os-theme-dark', autoHide: 'leave' } });
    document.getElementById('logout-button')?.addEventListener('click', async event => {
        const url = event.currentTarget.dataset.url;
        if (!await App.confirm('Keluar dari MyKasir?', 'Pastikan transaksi Anda sudah disimpan.')) return;
        try { const result = await App.http('POST', url); Object.keys(sessionStorage).filter(key => key.startsWith('mykasir.pending.')).forEach(key => sessionStorage.removeItem(key)); location.href = result.redirect; } catch (_) {}
    });
});
