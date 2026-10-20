@extends('layouts.app')
@section('title', 'Supplier')
@section('page_title', 'Supplier')
@section('page_description', 'Simpan rekan kulakan agar pencatatan pembelian berikutnya lebih cepat.')
@section('page_actions')
<button type="button" class="btn btn-brand" id="contact-new"><i class="bi bi-plus-lg me-2"></i>Tambah supplier</button>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/catalog.css') }}">
@endpush
@section('content')
@include('shared.contact-catalog', ['kind' => 'supplier', 'label' => 'supplier', 'endpoint' => url('/ajax/suppliers')])
@endsection
@push('js')
<script src="{{ asset('scripts/shared/catalog.js') }}"></script>
<script src="{{ asset('scripts/supplier/index.js') }}"></script>
@endpush
