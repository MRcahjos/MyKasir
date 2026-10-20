@extends('layouts.app')
@section('title', 'Kasir')
@section('page_title', 'Kasir')
@section('page_description', 'Pilih barang, tentukan satuan, lalu selesaikan pembayaran.')

@push('css')
<link rel="stylesheet" href="{{ asset('css/transactions.css') }}">
@endpush

@section('content')
<div id="pos-page" data-lookups-url="{{ url('/ajax/lookups') }}" data-sales-url="{{ url('/ajax/sales') }}">
    <div id="pos-last-sale" class="transaction-success d-none mb-4" role="status">
        <div class="d-flex align-items-center gap-3"><span class="success-check"><i class="bi bi-check-lg"></i></span><div><strong>Penjualan berhasil disimpan</strong><div id="pos-last-number" class="small"></div></div></div>
        <div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-soft" id="pos-view-receipt"><i class="bi bi-receipt me-1"></i>Lihat struk</button><button type="button" class="btn btn-sm btn-brand" id="pos-print-receipt"><i class="bi bi-printer me-1"></i>Cetak</button></div>
    </div>
    <div class="pos-workspace">
        <section class="surface-card pos-catalog" aria-labelledby="catalog-heading">
            <div class="p-4 pb-3">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><div class="page-eyebrow">PILIH BARANG</div><h2 id="catalog-heading" class="section-heading mb-0">Mau belanja apa?</h2></div><span class="catalog-count" id="catalog-count">Memuat…</span></div>
                <div class="pos-search"><i class="bi bi-search"></i><input type="search" id="pos-search" class="form-control" placeholder="Cari nama barang atau kode SKU…" aria-label="Cari barang" autocomplete="off"><kbd>/</kbd></div>
                <div class="d-flex align-items-center gap-2 mt-3"><label for="pos-category" class="small text-muted text-nowrap">Kategori</label><select id="pos-category" class="form-select form-select-sm"><option value="">Semua kategori</option></select><button type="button" class="btn btn-soft btn-sm flex-shrink-0" id="pos-refresh" title="Perbarui stok" aria-label="Perbarui stok"><i class="bi bi-arrow-clockwise"></i></button></div>
            </div>
            <div id="pos-products" class="pos-product-grid" aria-live="polite"><div class="catalog-empty"><span class="spinner-border spinner-border-sm text-success me-2"></span>Memuat barang toko…</div></div>
            <div class="catalog-footnote"><i class="bi bi-info-circle me-1"></i> Klik barang untuk menambah. Satuan ecer atau dus bisa dipilih di keranjang.</div>
        </section>

        <section class="surface-card pos-checkout" aria-labelledby="cart-heading">
            <form id="pos-form" novalidate>
                <div class="p-4 pb-3 border-bottom"><div class="d-flex justify-content-between align-items-center"><div class="d-flex gap-2 align-items-center"><span class="checkout-icon"><i class="bi bi-basket2"></i></span><h2 id="cart-heading" class="section-heading mb-0">Keranjang</h2><span class="badge-soft-neutral" id="cart-count">0</span></div><button type="button" class="btn btn-link btn-sm text-muted p-0" id="cart-clear" disabled>Kosongkan</button></div></div>
                <div id="pos-cart" class="pos-cart-items" aria-live="polite"><div class="empty-state py-5"><i class="bi bi-basket2 fs-1 d-block mb-2"></i><strong>Keranjang masih kosong</strong><p class="small mb-0">Pilih barang di sebelah untuk mulai berjualan.</p></div></div>
                <div class="checkout-details">
                    <div class="row g-3">
                        <div class="col-sm-7"><label class="form-label" for="pos-customer">Pelanggan <span class="text-muted fw-normal">(opsional)</span></label><select id="pos-customer" name="customer_id" class="form-select"><option value="">Pembeli umum</option></select></div>
                        <div class="col-sm-5"><label class="form-label" for="pos-date">Tanggal</label><input type="date" id="pos-date" name="date" class="form-control" required></div>
                    </div>
                    <div id="pos-debt-warning" class="debt-notice d-none mt-3" role="status"><i class="bi bi-info-circle-fill"></i><div><div id="pos-debt-text"></div><a href="{{ url('/piutang') }}" target="_blank" rel="noopener" class="d-inline-block mt-1">Catat pelunasan <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i><span class="visually-hidden"> (buka tab baru)</span></a><div class="mt-1">Setelah lunas, klik Perbarui stok untuk memperbarui status pelanggan.</div></div></div>
                    <div class="mt-3"><label class="form-label" for="pos-payment-status">Pembayaran</label><div class="row g-2"><div class="col-7"><select id="pos-payment-status" class="form-select"><option value="paid">Langsung lunas</option><option value="partial">Bayar sebagian</option><option value="unpaid">Utang dulu</option></select></div><div class="col-5"><select id="pos-method" name="payment_method" class="form-select" aria-label="Metode pembayaran"><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></select></div></div></div>
                    <div id="pos-received-wrap" class="mt-3"><label class="form-label" for="pos-received">Uang diterima</label><div class="input-group"><span class="input-group-text">Rp</span><input type="number" id="pos-received" name="received_amount" class="form-control" min="0" step="0.01" inputmode="decimal" value="0"></div><div class="payment-shortcuts mt-2" id="pos-cash-shortcuts"><button type="button" class="cash-shortcut" data-cash="exact">Uang pas</button><button type="button" class="cash-shortcut" data-cash="50000">50.000</button><button type="button" class="cash-shortcut" data-cash="100000">100.000</button><button type="button" class="cash-shortcut" data-cash="200000">200.000</button></div></div>
                    <div class="mt-3"><label for="pos-notes" class="form-label">Catatan <span class="text-muted fw-normal">(opsional)</span></label><input id="pos-notes" name="notes" class="form-control" maxlength="500" placeholder="Catatan untuk transaksi ini"></div>
                </div>
                <div class="checkout-summary"><div class="d-flex justify-content-between align-items-center mb-3"><span class="fw-semibold">Total belanja</span><strong id="pos-total" class="checkout-total">Rp0</strong></div><div class="d-flex justify-content-between small mb-3"><span id="pos-difference-label" class="text-muted">Kembalian</span><strong id="pos-difference">Rp0</strong></div><div id="pos-validation" class="text-danger small mb-3 d-none" role="alert"></div><button type="submit" id="pos-submit" class="btn btn-brand w-100 checkout-submit" disabled><i class="bi bi-check2-circle me-2"></i>Selesaikan penjualan</button><div class="checkout-note"><i class="bi bi-shield-check me-1"></i>Stok dan pembayaran tercatat saat transaksi disimpan.</div></div>
            </form>
        </section>
    </div>
</div>
@endsection

@push('js')
<script src="{{ asset('scripts/shared/transaction-request.js') }}"></script>
<script src="{{ asset('scripts/kasir/index.js') }}"></script>
@endpush
