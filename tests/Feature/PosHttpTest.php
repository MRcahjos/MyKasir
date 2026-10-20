<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_requires_authentication(): void
    {
        $this->getJson('/ajax/products')->assertUnauthorized();
        $this->postJson('/ajax/sales', [])->assertUnauthorized();
    }

    public function test_product_catalog_and_datatable_are_validated_and_paginated(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->createProduct();
        $this->createProduct('BOX-02', 'Kotak makanan');

        $this->assertDatabaseHas('products', ['id' => $first['id'], 'stock' => 0]);
        $this->getJson('/ajax/products?'.http_build_query([
            'draw' => 7, 'start' => 0, 'length' => 1,
            'search' => ['value' => 'Rankong'],
            'columns' => [['data' => 'name']],
            'order' => [['column' => 0, 'dir' => 'asc']],
        ]))->assertOk()->assertJsonPath('draw', 7)->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cup Rankong 22 oz')->assertJsonCount(2, 'data.0.units');

        // Client column metadata is never used as an untrusted SQL identifier.
        $this->getJson('/ajax/products?'.http_build_query([
            'columns' => [['data' => 'name desc; drop table products']],
            'order' => [['column' => 0, 'dir' => 'asc']],
        ]))->assertOk()->assertJsonPath('recordsTotal', 2);
        $this->getJson('/ajax/products?length=10000')->assertUnprocessable()->assertJsonValidationErrors('length');
        $this->getJson('/ajax/sales?from=2026-10-18&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/ajax/sales?to=2026-10-18')->assertOk();
    }

    public function test_purchase_credit_sale_and_instalments_use_the_same_stock_and_do_not_repeat_sales(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct();
        $base = collect($product['units'])->firstWhere('multiplier', 1);
        $box = collect($product['units'])->firstWhere('multiplier', 20);
        $supplier = $this->postJson('/ajax/suppliers', ['name' => 'Supplier Utama'])->assertCreated()->json('data');
        $customer = $this->postJson('/ajax/customers', ['name' => 'Budi', 'phone' => '08123456789'])->assertCreated()->json('data');

        $purchase = [
            'supplier_id' => $supplier['id'], 'date' => '2026-10-10', 'request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product['id'], 'unit_id' => $box['id'], 'quantity' => 1, 'unit_price' => 305000]],
        ];
        $this->postJson('/ajax/purchases', $purchase)->assertCreated()->assertJsonPath('data.total', '305000.00');
        $this->postJson('/ajax/purchases', $purchase)->assertCreated();
        $this->assertSame(20, Product::find($product['id'])->stock);
        $this->assertDatabaseCount('purchases', 1);

        $sale = [
            'customer_id' => $customer['id'], 'date' => '2026-10-10', 'request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product['id'], 'unit_id' => $base['id'], 'quantity' => 3]],
            'received_amount' => 0, 'payment_method' => 'cash',
        ];
        $saleId = $this->postJson('/ajax/sales', $sale)->assertCreated()->assertJsonPath('data.balance', '51000.00')->json('data.id');
        $this->postJson('/ajax/sales', $sale)->assertCreated();
        $this->assertSame(17, Product::find($product['id'])->stock);
        $this->assertDatabaseCount('sales', 1);

        $this->postJson('/ajax/sales', [...$sale, 'request_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson('/ajax/receivables/'.$saleId.'/payments', [
            'amount' => 20000, 'date' => '2026-10-11', 'method' => 'transfer', 'request_id' => (string) Str::uuid(),
        ])->assertCreated();
        $this->getJson('/ajax/receivables')->assertOk()->assertJsonPath('data.0.balance', '31000.00')->assertJsonPath('data.0.status', 'partial');
        $this->postJson('/ajax/sales', [...$sale, 'request_id' => (string) Str::uuid()])->assertUnprocessable();

        $this->postJson('/ajax/receivables/'.$saleId.'/payments', [
            'amount' => 31000, 'date' => '2026-10-12', 'method' => 'cash', 'request_id' => (string) Str::uuid(),
        ])->assertCreated();
        $this->getJson('/ajax/sales/'.$saleId)->assertOk()->assertJsonPath('data.status', 'paid')
            ->assertJsonCount(2, 'data.payments')->assertJsonPath('data.cost_total', '45750.00');
        $this->assertSame(17, Product::find($product['id'])->stock);
        $this->assertDatabaseCount('sales', 1);
        $this->getJson('/ajax/receivables')->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->getJson('/ajax/summary?from=2026-10-10&to=2026-10-12')->assertOk()
            ->assertJsonPath('data.gross_profit', '5250.00')->assertJsonPath('data.cash_received', '51000.00');
        $this->postJson('/ajax/sales', [...$sale, 'request_id' => (string) Str::uuid()])->assertCreated();
    }

    public function test_unrelated_units_and_invalid_money_cannot_be_submitted(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct();
        $other = $this->createProduct('OTHER', 'Barang Lain');
        $supplier = $this->postJson('/ajax/suppliers', ['name' => 'Supplier'])->assertCreated()->json('data');

        $this->postJson('/ajax/purchases', [
            'supplier_id' => $supplier['id'], 'date' => '2026-10-10', 'request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product['id'], 'unit_id' => $other['units'][0]['id'], 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('purchases', 0);
        $this->assertSame(0, Product::find($product['id'])->stock);

        $this->postJson('/ajax/expenses', [
            'date' => '2026-10-10', 'category' => 'Listrik', 'description' => 'Token listrik', 'amount' => '1000.001', 'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/ajax/stock-adjustments', [
            'product_id' => $product['id'], 'quantity' => 20, 'date' => '2026-10-10', 'notes' => 'Stok awal', 'request_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_cost');
        $this->getJson('/ajax/sales/999999')->assertNotFound();
    }

    private function createProduct(string $sku = 'CUP-22', string $name = 'Cup Rankong 22 oz'): array
    {
        return $this->postJson('/ajax/products', [
            'sku' => $sku, 'name' => $name, 'base_unit' => 'Cup', 'minimum_stock' => 5, 'active' => true,
            'stock' => 9999,
            'units' => [
                ['name' => 'Cup', 'multiplier' => 1, 'selling_price' => 17000],
                ['name' => 'Dus', 'multiplier' => 20, 'selling_price' => 330000],
            ],
        ])->assertCreated()->json('data');
    }
}
