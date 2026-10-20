@extends('layouts.app')
@section('title', 'Pengeluaran')
@section('page_title', 'Pengeluaran usaha')
@section('page_description', 'Catat biaya operasional agar laba bersih toko lebih mudah dipahami.')
@section('page_actions')
<button class="btn btn-brand" type="button" id="expense-new"><i class="bi bi-plus-lg me-2"></i>Catat pengeluaran</button>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/catalog.css') }}">
@endpush
@section('content')
<div id="expense-page" data-endpoint="{{ url('/ajax/expenses') }}">
    <div class="catalog-tip mb-4">
        <span class="icon-tile"><i class="bi bi-wallet2"></i></span>
        <div><strong>Pengeluaran yang tercatat, laba bersih lebih jelas.</strong><p class="small text-muted mb-0">Gunakan untuk listrik, sewa, ongkir operasional, dan biaya usaha lainnya. Kulakan barang dicatat di <a class="link-success fw-semibold" href="{{ url('/pembelian') }}">Pembelian</a> agar stok ikut bertambah.</p></div>
    </div>
    <div class="surface-card">
        <div class="table-toolbar">
            <div><h2 class="section-heading mb-1">Riwayat pengeluaran</h2><p class="text-muted small mb-0">Tinjau biaya usaha berdasarkan tanggal pencatatannya.</p></div>
            <form id="expense-filter" class="catalog-filter mt-3" novalidate>
                <div><label class="form-label" for="expense-from">Dari tanggal</label><input class="form-control form-control-sm" type="date" id="expense-from" name="from" required></div>
                <div><label class="form-label" for="expense-to">Sampai tanggal</label><input class="form-control form-control-sm" type="date" id="expense-to" name="to" required></div>
                <button class="btn btn-soft btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                <button class="btn btn-light btn-sm" type="button" id="expense-filter-reset">Bulan ini</button>
            </form>
        </div>
        <div class="px-4 pb-4"><table class="table align-middle w-100" id="expense-table"><thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Pembayaran</th><th class="text-end">Nominal</th></tr></thead></table></div>
    </div>
    <div class="catalog-total-note mt-3">Pengeluaran pada periode terpilih dapat dilihat bersama penjualan dan laba di <a class="link-success fw-semibold" href="{{ url('/laporan') }}">Laporan</a>.</div>

    <div class="modal fade" id="expense-modal" tabindex="-1" aria-labelledby="expense-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="expense-form" novalidate>
            <div class="modal-header"><div><div class="page-eyebrow">KEUANGAN TOKO</div><h2 class="modal-title fs-5" id="expense-modal-title">Catat pengeluaran</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-sm-6"><label class="form-label" for="expense-date">Tanggal <span class="text-danger">*</span></label><input class="form-control" id="expense-date" type="date" name="date" required></div>
                    <div class="col-sm-6"><label class="form-label" for="expense-category">Kategori <span class="text-danger">*</span></label><input class="form-control" id="expense-category" name="category" maxlength="100" list="expense-categories" required placeholder="Pilih atau ketik kategori"><datalist id="expense-categories"><option value="Listrik & air"></option><option value="Sewa toko"></option><option value="Transportasi"></option><option value="Perlengkapan toko"></option><option value="Internet & telepon"></option><option value="Gaji"></option><option value="Biaya pembayaran"></option><option value="Lainnya"></option></datalist></div>
                    <div class="col-12"><label class="form-label" for="expense-description">Keterangan <span class="text-danger">*</span></label><textarea class="form-control" id="expense-description" name="description" rows="2" maxlength="255" required placeholder="Contoh: Pembayaran listrik toko bulan ini"></textarea></div>
                    <div class="col-sm-7"><label class="form-label" for="expense-amount">Nominal <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" id="expense-amount" name="amount" type="number" inputmode="decimal" min="0.01" max="999999999999.99" step="0.01" required placeholder="0"></div></div>
                    <div class="col-sm-5"><label class="form-label" for="expense-method">Pembayaran <span class="text-danger">*</span></label><select class="form-select" id="expense-method" name="payment_method" required><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></select></div>
                </div>
                <div class="catalog-note mt-4"><i class="bi bi-info-circle me-1"></i>Pengeluaran ini mengurangi laba bersih pada tanggal yang Anda pilih. Periksa nominal sebelum menyimpan.</div>
                <div class="form-feedback text-danger small mt-3" role="alert"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-brand" type="submit" id="expense-submit"><i class="bi bi-check2 me-1"></i>Simpan pengeluaran</button></div>
        </form></div></div>
    </div>
</div>
@endsection
@push('js')
<script src="{{ asset('scripts/pengeluaran/index.js') }}"></script>
@endpush
