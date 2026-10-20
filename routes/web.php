<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/setup', [AuthController::class, 'setup'])->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    foreach (['dashboard', 'barang', 'supplier', 'pelanggan', 'pembelian', 'kasir', 'penjualan', 'piutang', 'stok', 'pengeluaran', 'laporan'] as $page) {
        Route::view('/'.$page, $page.'.index')->name($page);
    }
    require __DIR__.'/pos.php';
});
