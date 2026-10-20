<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PosDataController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('ajax')->name('ajax.')->group(function () {
    Route::get('summary', [PosDataController::class, 'summary'])->name('summary');
    Route::get('lookups', [PosDataController::class, 'lookups'])->name('lookups');

    foreach (['products', 'suppliers', 'customers', 'purchases', 'sales', 'receivables', 'movements', 'expenses'] as $resource) {
        Route::get($resource, [PosDataController::class, $resource])->name($resource.'.index');
    }

    Route::get('purchases/{purchase}', [PosDataController::class, 'purchase'])->whereNumber('purchase')->name('purchases.show');
    Route::get('sales/{sale}', [PosDataController::class, 'sale'])->whereNumber('sale')->name('sales.show');

    Route::post('products', [CatalogController::class, 'storeProduct'])->name('products.store');
    Route::put('products/{product}', [CatalogController::class, 'updateProduct'])->whereNumber('product')->name('products.update');
    Route::post('suppliers', [CatalogController::class, 'storeSupplier'])->name('suppliers.store');
    Route::put('suppliers/{supplier}', [CatalogController::class, 'updateSupplier'])->whereNumber('supplier')->name('suppliers.update');
    Route::post('customers', [CatalogController::class, 'storeCustomer'])->name('customers.store');
    Route::put('customers/{customer}', [CatalogController::class, 'updateCustomer'])->whereNumber('customer')->name('customers.update');
    Route::post('expenses', [CatalogController::class, 'storeExpense'])->name('expenses.store');

    Route::post('purchases', [TransactionController::class, 'purchase'])->name('purchases.store');
    Route::post('sales', [TransactionController::class, 'sale'])->name('sales.store');
    Route::post('receivables/{sale}/payments', [TransactionController::class, 'payment'])->whereNumber('sale')->name('receivables.payments.store');
    Route::post('stock-adjustments', [TransactionController::class, 'adjustment'])->name('stock-adjustments.store');
});
