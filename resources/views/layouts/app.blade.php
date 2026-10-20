<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="app-base" content="{{ url('/') }}"><meta name="app-user-id" content="{{ auth()->id() }}"><meta name="app-today" content="{{ now()->toDateString() }}"><meta name="theme-color" content="#dcfce7">
<title>@yield('title', 'Dashboard') · MyKasir</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('assets/plugins/overlayscrollbars/css/overlayscrollbars.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/bootstrap-icons/bootstrap-icons.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/adminlte/css/adminlte.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2-bootstrap-5-theme.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/sweetalert2/sweetalert2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/datatables/css/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">@stack('css')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<a class="skip-link" href="#main-content">Lewati ke konten</a>
<div class="app-wrapper">
@include('layouts.partials.navbar')
@include('layouts.partials.sidebar')
<main class="app-main" id="main-content">
<div class="app-content-header"><div class="container-fluid page-intro"><div><div class="page-eyebrow">RUANG KERJA TOKO ANDA</div><h1>@yield('page_title', 'Dashboard')</h1><p>@yield('page_description', 'Kelola toko dengan lebih mudah, satu langkah setiap hari.')</p></div><div class="page-actions">@yield('page_actions')</div></div></div>
<div class="app-content"><div class="container-fluid">@if(config('app.preview_mode'))<div class="subtle-callout mb-3" role="status"><i class="bi bi-info-circle me-2"></i><strong>Mode demo.</strong> Database contoh terpisah untuk pengujian. Semua transaksi di halaman ini adalah simulasi.</div>@endif @yield('content')</div></div>
</main>
@include('layouts.partials.footer')
</div>
<script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/plugins/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js') }}"></script>
<script src="{{ asset('assets/adminlte/js/adminlte.min.js') }}"></script>
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatables/js/dataTables.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatables/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('scripts/shared/format.js') }}"></script>
<script src="{{ asset('scripts/shared/http.js') }}"></script>
<script src="{{ asset('scripts/layouts/app.js') }}"></script>
@stack('js')
</body></html>
