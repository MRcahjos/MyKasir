@extends('layouts.app')
@section('title', 'Barang & Satuan')
@section('page_title', 'Barang & satuan')
@section('page_description', 'Satu persediaan untuk semua satuan. Atur harga ecer dan dus sesuai kebutuhan toko.')
@section('page_actions')
<button class="btn btn-brand" id="product-new" type="button"><i class="bi bi-plus-lg me-2"></i>Tambah barang</button>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/catalog.css') }}">
@endpush
@section('content')
<div id="product-page" data-endpoint="{{ url('/ajax/products') }}">
    <div class="catalog-tip mb-4"><span class="icon-tile"><i class="bi bi-boxes"></i></span><div><strong>Beli dus, jual ecer. Stok tetap terhubung.</strong><p class="small text-muted mb-0">Tambahkan satuan terkecil terlebih dahulu, lalu kemasan lainnya. Isi kemasan dan harga jual bisa berbeda untuk setiap barang.</p></div></div>
    <div class="surface-card"><div class="table-toolbar"><h2 class="section-heading mb-1">Katalog barang</h2><p class="text-muted small mb-0">Harga, satuan, dan persediaan toko dalam satu tempat.</p></div><div class="px-4 pb-4"><table id="product-table" class="table align-middle w-100"><thead><tr><th>Kode</th><th>Barang</th><th>Satuan & harga jual</th><th>Stok tersedia</th><th>Status</th><th>Tindakan</th></tr></thead></table></div></div>
    <div class="modal fade" id="product-modal" tabindex="-1" aria-labelledby="product-modal-title" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><form id="product-form" novalidate>
        <div class="modal-header"><div><div class="page-eyebrow">KATALOG TOKO</div><h2 class="modal-title fs-5" id="product-modal-title">Tambah barang</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body"><div class="row g-4"><div class="col-lg-5"><div class="catalog-step"><span>1</span><h3>Informasi barang</h3></div><div class="row g-3">
            <div class="col-12"><label class="form-label" for="product-name">Nama barang <span class="text-danger">*</span></label><input class="form-control" id="product-name" name="name" maxlength="255" required placeholder="Contoh: Cup Rankong 22 oz"></div>
            <div class="col-sm-6"><label class="form-label" for="product-sku">Kode barang <span class="text-danger">*</span></label><input class="form-control" id="product-sku" name="sku" maxlength="64" required placeholder="Contoh: CUP-22"></div>
            <div class="col-sm-6"><label class="form-label" for="product-category">Kategori</label><input class="form-control" id="product-category" name="category" maxlength="100" placeholder="Contoh: Kemasan"></div>
            <div class="col-sm-6"><label class="form-label" for="product-base">Satuan terkecil <span class="text-danger">*</span></label><input class="form-control" id="product-base" name="base_unit" maxlength="40" required placeholder="Cup, pcs, botol…"><div class="form-hint">Stok dihitung dalam satuan ini.</div></div>
            <div class="col-sm-6"><label class="form-label" for="product-minimum">Pengingat stok minimum</label><input type="number" class="form-control" id="product-minimum" name="minimum_stock" min="0" max="1000000000" step="1" value="0" required><div class="form-hint">Dalam satuan terkecil.</div></div>
            <div class="col-12"><div class="form-check form-switch catalog-active"><input class="form-check-input" type="checkbox" role="switch" id="product-active" name="active" checked><label class="form-check-label" for="product-active">Barang aktif dijual</label><div class="form-hint">Nonaktifkan untuk menyembunyikan barang dari kasir.</div></div></div>
        </div></div><div class="col-lg-7"><div class="catalog-step"><span>2</span><h3>Satuan & harga jual</h3></div><p class="form-hint mb-3">Harga diisi per satuan yang dijual. Harga dus bebas Anda tentukan.</p><div id="product-units" class="catalog-units"></div><button type="button" class="btn btn-soft btn-sm mt-3" id="product-add-unit"><i class="bi bi-plus-lg me-1"></i>Tambah satuan kemasan</button><div class="catalog-note mt-3" id="product-conversion-note"><i class="bi bi-info-circle me-2"></i>Isi kemasan yang sudah memiliki stok atau riwayat transaksi tidak dapat diubah. Harga jual tetap bisa diperbarui.</div></div></div>
        <div class="catalog-note mt-4" id="product-stock-note">Barang baru dimulai dengan stok 0. Catat pembelian atau stok awal melalui menu Stok setelah barang disimpan.</div><div class="form-feedback text-danger small mt-3" role="alert"></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-brand" type="submit" id="product-submit"><i class="bi bi-check2 me-1"></i>Simpan barang</button></div>
    </form></div></div></div>
</div>
@endsection
@push('js')
<script src="{{ asset('scripts/barang/index.js') }}"></script>
@endpush
