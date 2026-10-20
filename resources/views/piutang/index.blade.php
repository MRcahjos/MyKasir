@extends('layouts.app')
@section('title', 'Piutang pelanggan')
@section('page_title', 'Piutang pelanggan')
@section('page_description', 'Lihat tagihan yang belum lunas dan catat pembayaran pelanggan.')

@push('css')
<link rel="stylesheet" href="{{ asset('css/transactions.css') }}">
@endpush

@section('content')
<div id="receivable-page" data-receivables-url="{{ url('/ajax/receivables') }}" data-sales-url="{{ url('/ajax/sales') }}">
    <div class="debt-policy mb-4"><span class="policy-icon"><i class="bi bi-person-check"></i></span><div><strong>Lunasi dulu, baru berutang lagi.</strong><p class="mb-0 small">Pelanggan yang masih memiliki utang tetap bisa belanja dengan pembayaran lunas. Cicilan boleh dicatat sampai seluruh tagihan lunas.</p></div></div>
    <section class="surface-card"><div class="table-toolbar d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><div class="page-eyebrow">TAGIHAN AKTIF</div><h2 class="section-heading mb-1">Utang pelanggan</h2><p class="small text-muted mb-0">Hanya transaksi dengan sisa utang yang ditampilkan.</p></div><button type="button" class="btn btn-soft" id="receivable-refresh"><i class="bi bi-arrow-clockwise me-1"></i>Perbarui</button></div><div class="px-4 pb-4"><table id="receivable-table" class="table align-middle w-100"><thead><tr><th>Pelanggan</th><th>No. transaksi</th><th>Tanggal</th><th>Total belanja</th><th>Sudah dibayar</th><th>Sisa utang</th><th>Tindakan</th></tr></thead></table></div></section>

    <div class="modal fade" id="payment-modal" tabindex="-1" aria-labelledby="payment-modal-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="payment-form" novalidate><div class="modal-header"><div><div class="page-eyebrow">PEMBAYARAN PIUTANG</div><h2 class="modal-title fs-5" id="payment-modal-title">Catat pembayaran</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body"><div class="payment-customer mb-4"><div class="d-flex justify-content-between gap-2"><div><strong id="payment-customer">—</strong><div class="small text-muted" id="payment-number">—</div></div><span class="badge-soft-warning align-self-start">Belum lunas</span></div><div class="payment-balance"><span>Sisa utang</span><strong id="payment-balance">Rp0</strong></div></div><div class="mb-3"><div class="d-flex justify-content-between align-items-center"><label for="payment-amount" class="form-label">Jumlah pembayaran <span class="text-danger">*</span></label><button type="button" class="btn btn-link btn-sm p-0 mb-2" id="payment-full">Lunasi semua</button></div><div class="input-group"><span class="input-group-text">Rp</span><input type="number" id="payment-amount" name="amount" class="form-control" min="0.01" step="0.01" inputmode="decimal" required></div><div class="form-hint">Bisa dibayar sebagian. Pembayaran tidak boleh melebihi sisa utang.</div></div><div class="row g-3"><div class="col-6"><label for="payment-date" class="form-label">Tanggal</label><input type="date" class="form-control" id="payment-date" name="date" required></div><div class="col-6"><label for="payment-method" class="form-label">Metode</label><select class="form-select" id="payment-method" name="method"><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></select></div></div><div class="mt-3"><label for="payment-notes" class="form-label">Catatan <span class="text-muted fw-normal">(opsional)</span></label><textarea id="payment-notes" name="notes" class="form-control" rows="2" maxlength="1000" placeholder="Contoh: cicilan pertama"></textarea></div><div class="payment-after mt-3"><span>Sisa setelah pembayaran</span><strong id="payment-after">Rp0</strong></div><div class="text-danger small mt-3 d-none" id="payment-validation" role="alert"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-brand" id="payment-submit"><i class="bi bi-check2-circle me-1"></i>Simpan pembayaran</button></div></form></div></div></div>
</div>
@endsection

@push('js')
<script src="{{ asset('scripts/shared/transaction-request.js') }}"></script>
<script src="{{ asset('scripts/piutang/index.js') }}"></script>
@endpush
