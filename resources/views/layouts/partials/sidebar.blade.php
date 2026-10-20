<aside class="app-sidebar shadow-none" data-bs-theme="light" aria-label="Navigasi utama">
<div class="sidebar-brand"><a href="{{ route('dashboard') }}" class="brand-link"><span class="brand-mark"><i class="bi bi-shop-window"></i></span><span class="brand-word">my<span>kasir</span><small>TOKO RAPI, USAHA MAJU</small></span></a></div>
<div class="sidebar-wrapper">
<div class="workspace-label"><span class="workspace-avatar"><i class="bi bi-shop"></i></span><div><strong>Toko Saya</strong><span>Ruang usaha pribadi</span></div><span class="workspace-dot"></span></div>
<nav class="sidebar-navigation">
@foreach ([
'UTAMA' => [['dashboard','grid-1x2','Dashboard'],['kasir','bag-plus','Kasir']],
'AKTIVITAS TOKO' => [['penjualan','receipt','Penjualan'],['pembelian','box-arrow-in-down','Pembelian'],['piutang','wallet2','Piutang pelanggan'],['stok','boxes','Persediaan stok']],
'DATA TOKO' => [['barang','box-seam','Barang & satuan'],['supplier','truck','Supplier'],['pelanggan','people','Pelanggan']],
'KEUANGAN' => [['pengeluaran','cash-stack','Pengeluaran'],['laporan','bar-chart-line','Laporan usaha']]
] as $group => $links)
<div class="nav-section-label">{{ $group }}</div><ul class="nav sidebar-menu flex-column">
@foreach ($links as [$route, $icon, $label])
<li class="nav-item"><a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($route)]) @if(request()->routeIs($route)) aria-current="page" @endif><i class="nav-icon bi bi-{{ $icon }}"></i><p>{{ $label }}</p>@if($route === 'kasir')<span class="nav-shortcut">POS</span>@endif</a></li>
@endforeach
</ul>
@endforeach
</nav><div class="sidebar-note"><span class="note-sprout"><i class="bi bi-flower1"></i></span><strong>Usaha kecil, langkah besar.</strong><p>Catat hari ini. Tumbuh esok hari.</p></div>
</div></aside>
