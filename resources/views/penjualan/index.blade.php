@extends('layouts.app')
@section('title', 'Riwayat penjualan')
@section('page_title', 'Riwayat penjualan')
@section('page_description', 'Telusuri transaksi, rincian barang, dan riwayat pembayaran pelanggan.')

@push('css')
<link rel="stylesheet" href="{{ asset('css/transactions.css') }}">
@endpush

@section('content')
<div id="sales-page" data-sales-url="{{ url('/ajax/sales') }}">
    <section class="surface-card"><div class="table-toolbar d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><div class="page-eyebrow">TRANSAKSI TOKO</div><h2 class="section-heading mb-1">Semua penjualan</h2><p class="text-muted small mb-0">Penjualan tunai, cicilan, dan utang dalam satu tempat.</p></div><a class="btn btn-brand" href="{{ url('/kasir') }}"><i class="bi bi-plus-lg me-2"></i>Penjualan baru</a></div><div class="transaction-filters px-4 pb-3"><div><label for="sales-from" class="form-label small">Dari tanggal</label><input type="date" class="form-control form-control-sm" id="sales-from"></div><div><label for="sales-to" class="form-label small">Sampai tanggal</label><input type="date" class="form-control form-control-sm" id="sales-to"></div><button type="button" class="btn btn-soft btn-sm" id="sales-reset">Semua tanggal</button></div><div class="px-4 pb-4"><table id="sales-table" class="table align-middle w-100"><thead><tr><th>No. transaksi</th><th>Tanggal</th><th>Pelanggan</th><th>Total belanja</th><th>Sudah dibayar</th><th>Status</th><th>Rincian</th></tr></thead></table></div></section>
    <div class="transaction-tip mt-4"><span class="icon-tile"><i class="bi bi-receipt"></i></span><div><strong>Penjualan dan pembayaran dicatat sesuai tanggalnya.</strong><p class="mb-0 small text-muted">Pembayaran utang menambah penerimaan uang tanpa membuat penjualan atau mengurangi stok lagi.</p></div></div>
</div>
@endsection

@push('js')
<script src="{{ asset('scripts/penjualan/index.js') }}"></script>
@endpush
