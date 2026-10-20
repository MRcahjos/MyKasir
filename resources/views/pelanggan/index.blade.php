@extends('layouts.app')
@section('title', 'Pelanggan')
@section('page_title', 'Pelanggan')
@section('page_description', 'Kenali pelanggan toko dan pantau sisa pembayaran mereka dengan mudah.')
@section('page_actions')
<button type="button" class="btn btn-brand" id="contact-new"><i class="bi bi-plus-lg me-2"></i>Tambah pelanggan</button>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/catalog.css') }}">
@endpush
@section('content')
<div class="catalog-tip mb-4"><span class="icon-tile"><i class="bi bi-person-check"></i></span><div><strong>Utang lama lunas, belanja berutang bisa kembali.</strong><p class="small text-muted mb-0">Pelanggan yang masih memiliki piutang tetap boleh belanja dengan pembayaran lunas. Cicilan dicatat melalui menu Piutang.</p></div></div>
@include('shared.contact-catalog', ['kind' => 'customer', 'label' => 'pelanggan', 'endpoint' => url('/ajax/customers')])
@endsection
@push('js')
<script src="{{ asset('scripts/shared/catalog.js') }}"></script>
<script src="{{ asset('scripts/pelanggan/index.js') }}"></script>
@endpush
