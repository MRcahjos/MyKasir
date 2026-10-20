@extends('layouts.app')
@section('title', 'Pembelian')
@section('page_title', 'Pembelian & kulakan')
@section('page_description', 'Catat barang yang sudah diterima dan dibayar untuk menambah stok toko.')

@push('css')
<link rel="stylesheet" href="{{ asset('css/transactions.css') }}">
@endpush

@section('content')
<div id="purchase-page" data-lookups-url="{{ url('/ajax/lookups') }}" data-purchases-url="{{ url('/ajax/purchases') }}">
    <div class="surface-card mb-4">
        <div class="table-toolbar d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><div class="page-eyebrow">PERSEDIAAN TOKO</div><h2 class="section-heading mb-1">Riwayat kulakan</h2><p class="text-muted small mb-0">Harga beli dan isi kemasan tersimpan untuk setiap pembelian.</p></div><button type="button" class="btn btn-brand" id="purchase-new"><i class="bi bi-plus-lg me-2"></i>Catat pembelian</button></div>
        <div class="transaction-filters px-4 pb-3"><div><label for="purchase-from" class="form-label small">Dari tanggal</label><input type="date" class="form-control form-control-sm" id="purchase-from"></div><div><label for="purchase-to" class="form-label small">Sampai tanggal</label><input type="date" class="form-control form-control-sm" id="purchase-to"></div><button type="button" class="btn btn-soft btn-sm" id="purchase-reset">Semua tanggal</button></div>
        <div class="px-4 pb-4"><table id="purchase-table" class="table align-middle w-100"><thead><tr><th>No. pembelian</th><th>Tanggal</th><th>Supplier</th><th>Total</th><th>Status</th><th>Rincian</th></tr></thead></table></div>
    </div>
    <div class="transaction-tip"><span class="icon-tile"><i class="bi bi-box-seam"></i></span><div><strong>Beli dus, stok ecer tetap terhitung.</strong><p class="mb-0 small text-muted">Pilih satuan sesuai nota supplier. Isi kemasan otomatis dikonversi ke satuan terkecil barang.</p></div></div>

    <div class="modal fade" id="purchase-modal" tabindex="-1" aria-labelledby="purchase-modal-title" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><form id="purchase-form" novalidate>
        <div class="modal-header"><div><div class="page-eyebrow">TAMBAH PERSEDIAAN</div><h2 class="modal-title fs-5" id="purchase-modal-title">Catat pembelian</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body"><div class="row g-3 mb-4"><div class="col-md-8"><label for="purchase-supplier" class="form-label">Supplier <span class="text-danger">*</span></label><select class="form-select" id="purchase-supplier" name="supplier_id" required><option value="">Pilih supplier</option></select><div class="form-hint">Supplier belum ada? Tambahkan dahulu di menu Supplier.</div></div><div class="col-md-4"><label for="purchase-date" class="form-label">Tanggal penerimaan <span class="text-danger">*</span></label><input type="date" id="purchase-date" name="date" class="form-control" required></div></div>
            <div class="purchase-add-bar mb-3"><div class="flex-grow-1"><label for="purchase-product" class="form-label">Tambahkan barang</label><select id="purchase-product" class="form-select"><option value="">Pilih barang dari katalog</option></select></div><button type="button" class="btn btn-soft" id="purchase-add"><i class="bi bi-plus-lg me-1"></i>Tambah</button></div>
            <div class="table-responsive purchase-items-table"><table class="table align-middle mb-0"><thead><tr><th>Barang</th><th>Satuan beli</th><th>Jumlah</th><th>Harga per satuan</th><th class="text-end">Subtotal</th><th></th></tr></thead><tbody id="purchase-items"><tr><td colspan="6"><div class="empty-state py-4"><i class="bi bi-box-seam fs-2 d-block mb-2"></i>Belum ada barang. Pilih barang untuk mencatat kulakan.</div></td></tr></tbody></table></div>
            <div class="row g-3 mt-3"><div class="col-md-7"><label for="purchase-notes" class="form-label">Catatan <span class="text-muted fw-normal">(opsional)</span></label><textarea id="purchase-notes" name="notes" class="form-control" rows="2" maxlength="1000" placeholder="Nomor nota supplier atau catatan penerimaan"></textarea></div><div class="col-md-5"><div class="purchase-total"><span class="small text-muted">Total pembelian</span><strong id="purchase-total">Rp0</strong><span class="small text-success"><i class="bi bi-check2-circle me-1"></i>Dibayar lunas & barang diterima</span></div></div></div>
            <div id="purchase-validation" class="text-danger small mt-3 d-none" role="alert"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-brand" id="purchase-submit"><i class="bi bi-check2-circle me-1"></i>Simpan & tambah stok</button></div>
    </form></div></div></div>
</div>
@endsection

@push('js')
<script src="{{ asset('scripts/shared/transaction-request.js') }}"></script>
<script src="{{ asset('scripts/pembelian/index.js') }}"></script>
@endpush
