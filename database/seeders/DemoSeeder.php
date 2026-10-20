<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductService;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Optional only: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('sku', 'DEMO-RANKONG22')->exists()) {
            $this->command?->info('Barang contoh sudah ada. Tidak ada data yang ditambahkan.');

            return;
        }
        $supplier = Supplier::firstOrCreate(['name' => 'Supplier Contoh'], ['phone' => null]);
        Customer::firstOrCreate(['name' => 'Pelanggan Contoh']);
        $product = app(ProductService::class)->save([
            'sku' => 'DEMO-RANKONG22', 'name' => 'Cup Rankong 22 oz', 'category' => 'Kemasan',
            'base_unit' => 'Cup', 'minimum_stock' => 5, 'active' => true,
            'units' => [
                ['name' => 'Cup', 'multiplier' => 1, 'selling_price' => '17000.00'],
                ['name' => 'Dus', 'multiplier' => 20, 'selling_price' => '330000.00'],
            ],
        ]);
        app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id, 'date' => today()->format('Y-m-d'), 'request_id' => (string) Str::uuid(),
            'notes' => 'Data contoh opsional; hapus database contoh sebelum pemakaian nyata.',
            'items' => [['product_id' => $product->id, 'unit_id' => $product->units->firstWhere('multiplier', 20)->id, 'quantity' => 1, 'unit_price' => '305000.00']],
        ]);
    }
}
