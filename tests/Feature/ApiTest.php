<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_a_bearer_token_and_always_returns_json_errors(): void
    {
        $this->get('/api/v1/products')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        $this->post('/api/v1/sales')->assertUnauthorized();
        $this->withToken('invalid')->get('/api/v1/user')->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'web');
        $this->get('/api/v1/products')->assertUnauthorized();
        $this->get('/api/v1/missing')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_login_returns_a_hashed_expiring_token_and_logout_only_revokes_that_device(): void
    {
        $owner = User::factory()->create(['password' => 'Password123!']);
        $otherToken = $owner->createToken('Perangkat lain')->plainTextToken;
        $response = $this->postJson('/api/v1/login', [
            'email' => $owner->email, 'password' => 'Password123!', 'device_name' => 'Kasir Android',
        ])->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('data.id', $owner->id)
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');

        $token = $response->json('access_token');
        $stored = $owner->tokens()->where('name', 'Kasir Android')->sole();
        $this->assertSame(hash('sha256', explode('|', $token, 2)[1]), $stored->token);
        $this->assertTrue($stored->expires_at->isFuture());
        $this->assertSame($stored->expires_at->toIso8601String(), $response->json('expires_at'));
        $this->assertGuest('web');

        $this->withToken($token)->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.id', $owner->id);
        $this->postJson('/api/v1/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $stored->id]);

        Auth::forgetGuards();
        $this->getJson('/api/v1/user')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/user')->assertOk();
    }

    public function test_invalid_login_and_expired_tokens_are_rejected(): void
    {
        $owner = User::factory()->create(['password' => 'Password123!']);
        $this->post('/api/v1/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'device_name']);

        foreach ([$owner->email, 'missing@example.test'] as $email) {
            $this->postJson('/api/v1/login', [
                'email' => $email, 'password' => 'wrong-password', 'device_name' => 'Android',
            ])->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $token = $owner->createToken('Expired', ['*'], now()->subMinute())->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/login', [])->assertUnprocessable();
        }

        $this->postJson('/api/v1/login', [])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_authenticated_clients_can_read_catalogs_and_receive_validation_and_binding_errors(): void
    {
        $token = User::factory()->create()->createToken('Android')->plainTextToken;
        $this->withToken($token);

        foreach (['products', 'suppliers', 'customers', 'purchases', 'sales', 'receivables', 'movements', 'expenses'] as $resource) {
            $this->getJson('/api/v1/'.$resource)->assertOk()->assertJsonPath('recordsTotal', 0)->assertJsonCount(0, 'data');
        }
        $this->getJson('/api/v1/summary')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/v1/lookups')->assertOk()->assertJsonStructure(['data' => ['products', 'suppliers', 'customers']]);
        $this->get('/api/v1/products?length=1000')->assertUnprocessable()->assertJsonValidationErrors('length');
        $this->get('/api/v1/sales/999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->get('/api/v1/purchases/not-a-number')->assertNotFound();
        $this->putJson('/api/v1/products/999', [])->assertNotFound();
        $this->post('/api/v1/products', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_api_transactions_preserve_stock_idempotency_and_receivable_rules(): void
    {
        $this->withToken(User::factory()->create()->createToken('Android')->plainTextToken);
        $payload = [
            'sku' => 'API-01', 'name' => 'Cup', 'base_unit' => 'Pcs', 'minimum_stock' => 1, 'active' => true,
            'units' => [['name' => 'Pcs', 'multiplier' => 1, 'selling_price' => 15000]],
        ];
        $product = $this->postJson('/api/v1/products', $payload)->assertCreated()->json('data');
        $this->putJson('/api/v1/products/'.$product['id'], [
            ...$payload, 'name' => 'Cup Baru', 'units' => $product['units'],
        ])->assertOk()->assertJsonPath('data.name', 'Cup Baru');
        $supplier = $this->postJson('/api/v1/suppliers', ['name' => 'Supplier'])->assertCreated()->json('data');
        $customer = $this->postJson('/api/v1/customers', ['name' => 'Pelanggan'])->assertCreated()->json('data');
        $this->putJson('/api/v1/suppliers/'.$supplier['id'], ['name' => 'Supplier Baru'])->assertOk();
        $this->putJson('/api/v1/customers/'.$customer['id'], ['name' => 'Pelanggan Baru'])->assertOk();

        $purchase = [
            'supplier_id' => $supplier['id'], 'date' => '2026-10-29', 'request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product['id'], 'unit_id' => $product['units'][0]['id'], 'quantity' => 10, 'unit_price' => 10000]],
        ];
        $purchaseId = $this->postJson('/api/v1/purchases', $purchase)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/purchases', $purchase)->assertCreated()->assertJsonPath('data.id', $purchaseId);
        $this->getJson('/api/v1/purchases/'.$purchaseId)->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertDatabaseCount('purchases', 1);

        $sale = [
            'customer_id' => $customer['id'], 'date' => '2026-10-29', 'request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product['id'], 'unit_id' => $product['units'][0]['id'], 'quantity' => 2]],
            'received_amount' => 0, 'payment_method' => 'cash',
        ];
        $saleId = $this->postJson('/api/v1/sales', $sale)->assertCreated()->assertJsonPath('data.balance', '30000.00')->json('data.id');
        $this->postJson('/api/v1/sales', $sale)->assertCreated()->assertJsonPath('data.id', $saleId);
        $this->postJson('/api/v1/sales', [...$sale, 'request_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(8, Product::findOrFail($product['id'])->stock);
        $this->assertDatabaseCount('sales', 1);

        $payment = ['amount' => 30000, 'date' => '2026-10-29', 'method' => 'cash', 'request_id' => (string) Str::uuid()];
        $this->postJson('/api/v1/receivables/'.$saleId.'/payments', $payment)->assertCreated();
        $this->postJson('/api/v1/receivables/'.$saleId.'/payments', $payment)->assertCreated();
        $this->getJson('/api/v1/sales/'.$saleId)->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonCount(1, 'data.payments');
        $this->getJson('/api/v1/receivables')->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->assertSame(8, Product::findOrFail($product['id'])->stock);

        $adjustment = [
            'product_id' => $product['id'], 'quantity' => -1, 'notes' => 'Barang rusak',
            'date' => '2026-10-29', 'request_id' => (string) Str::uuid(),
        ];
        $this->postJson('/api/v1/stock-adjustments', $adjustment)->assertCreated();
        $this->postJson('/api/v1/stock-adjustments', $adjustment)->assertCreated();
        $this->assertSame(7, Product::findOrFail($product['id'])->stock);
        $this->postJson('/api/v1/expenses', [
            'date' => '2026-10-29', 'category' => 'Operasional', 'description' => 'Parkir', 'amount' => 2000, 'payment_method' => 'cash',
        ])->assertCreated();
        $this->getJson('/api/v1/summary?from=2026-10-29&to=2026-10-29')->assertOk()->assertJsonPath('data.cash_received', '30000.00');
    }
}
