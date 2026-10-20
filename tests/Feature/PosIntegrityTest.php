<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Decimal;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use App\Services\ReceivableService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
        $this->supplier = Supplier::create(['name' => 'Supplier']);
        $this->product = app(ProductService::class)->save([
            'sku' => 'RK22', 'name' => 'Cup Rankong', 'base_unit' => 'Cup',
            'minimum_stock' => 5, 'active' => true,
            'units' => [
                ['name' => 'Cup', 'multiplier' => 1, 'selling_price' => '17000'],
                ['name' => 'Dus', 'multiplier' => 20, 'selling_price' => '330000'],
            ],
        ]);
    }

    private function item(int $quantity = 1, int $multiplier = 1): array
    {
        return [
            'product_id' => $this->product->id,
            'unit_id' => $this->product->units->firstWhere('multiplier', $multiplier)->id,
            'quantity' => $quantity,
        ];
    }

    private function purchaseData(array $overrides = []): array
    {
        return array_replace([
            'supplier_id' => $this->supplier->id, 'date' => '2026-10-18',
            'request_id' => (string) Str::uuid(),
            'items' => [[...$this->item(1, 20), 'unit_price' => '305000']],
        ], $overrides);
    }

    private function saleData(array $overrides = []): array
    {
        return array_replace([
            'date' => '2026-10-18', 'request_id' => (string) Str::uuid(),
            'items' => [[...$this->item(), 'expected_unit_price' => '17000']],
            'received_amount' => '17000', 'payment_method' => 'cash',
        ], $overrides);
    }

    private function reject(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('The invalid operation should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_stale_price_cannot_silently_create_debt_and_failed_attempt_can_be_corrected(): void
    {
        $customer = Customer::create(['name' => 'Pembeli']);
        $payload = $this->saleData(['customer_id' => $customer->id]);
        $this->reject(fn () => app(SaleService::class)->create($payload), 'items');
        app(PurchaseService::class)->create($this->purchaseData());
        $this->product->units->firstWhere('multiplier', 1)->update(['selling_price' => '19000']);

        $this->reject(fn () => app(SaleService::class)->create($payload), 'items.0.expected_unit_price');
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(20, $this->product->fresh()->stock);
        $this->assertSame(0, DB::table('operation_requests')->where('request_id', $payload['request_id'])->count());

        $payload['items'][0]['expected_unit_price'] = '19000';
        $payload['received_amount'] = '19000';
        $sale = app(SaleService::class)->create($payload);
        $this->assertSame('19000.00', $sale->total);
        $this->assertSame('0.00', $sale->balance);
        $this->assertSame(19, $this->product->fresh()->stock);
    }

    public function test_successful_sale_replay_returns_original_even_when_price_and_product_status_changed(): void
    {
        app(PurchaseService::class)->create($this->purchaseData());
        $payload = $this->saleData();
        $sale = app(SaleService::class)->create($payload);
        $this->product->units->firstWhere('multiplier', 1)->update(['selling_price' => '19000']);
        $this->product->update(['active' => false]);

        $retry = app(SaleService::class)->create($payload);
        $this->assertSame($sale->id, $retry->id);
        $this->assertSame('17000.00', $retry->total);
        $this->assertSame(19, $this->product->fresh()->stock);
        $this->assertSame(1, Sale::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_purchase_cannot_be_inserted_before_later_stock_costs_but_same_day_is_allowed(): void
    {
        app(PurchaseService::class)->create($this->purchaseData());
        $sale = app(SaleService::class)->create($this->saleData(['date' => '2026-10-19']));
        $purchase = $this->purchaseData(['items' => [[...$this->item(1, 20), 'unit_price' => '345000']]]);

        $this->reject(fn () => app(PurchaseService::class)->create($purchase), 'date');
        $this->assertSame(1, Purchase::count());
        $this->assertSame(19, $this->product->fresh()->stock);
        $this->assertSame('15250.000000', $this->product->fresh()->average_cost);
        $this->assertSame(2, StockMovement::count());

        $purchase['date'] = '2026-10-19';
        app(PurchaseService::class)->create($purchase);
        $this->assertSame(39, $this->product->fresh()->stock);
        $this->assertSame('16275.641026', $this->product->fresh()->average_cost);
        $this->assertSame('15250.00', $sale->fresh()->cost_total);
    }

    public function test_sale_and_adjustment_cannot_predate_inventory_and_all_mutations_roll_back(): void
    {
        app(PurchaseService::class)->create($this->purchaseData(['date' => '2026-10-19']));
        $this->reject(fn () => app(SaleService::class)->create($this->saleData()), 'date');
        $this->reject(fn () => app(InventoryService::class)->adjust([
            'product_id' => $this->product->id, 'quantity' => -1, 'date' => '2026-10-18',
            'notes' => 'Rusak', 'request_id' => (string) Str::uuid(),
        ]), 'date');

        $this->assertSame(20, $this->product->fresh()->stock);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, StockMovement::count());
        $this->assertSame(1, DB::table('operation_requests')->count());
    }

    public function test_future_transactions_and_payments_do_not_change_current_balances(): void
    {
        app(PurchaseService::class)->create($this->purchaseData());
        $customer = Customer::create(['name' => 'Pembeli']);
        $sale = app(SaleService::class)->create($this->saleData(['customer_id' => $customer->id, 'received_amount' => '0']));

        $this->reject(fn () => app(PurchaseService::class)->create($this->purchaseData(['date' => '2026-10-20'])), 'date');
        $this->reject(fn () => app(SaleService::class)->create($this->saleData(['date' => '2026-10-20'])), 'date');
        $this->reject(fn () => app(InventoryService::class)->adjust([
            'product_id' => $this->product->id, 'quantity' => -1, 'date' => '2026-10-20', 'notes' => 'Rusak',
        ]), 'date');
        $this->reject(fn () => app(ReceivableService::class)->pay($sale, [
            'date' => '2026-10-20', 'amount' => '17000', 'method' => 'cash', 'request_id' => (string) Str::uuid(),
        ]), 'date');

        $this->assertSame(19, $this->product->fresh()->stock);
        $this->assertSame('17000.00', $sale->fresh()->balance);
        $this->assertSame(1, Purchase::count());
        $this->assertSame(1, Sale::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_combined_large_purchase_lines_are_rejected_before_stock_or_money_can_overflow(): void
    {
        $largeUnit = $this->product->units()->create(['name' => 'Palet', 'multiplier' => 1000000, 'selling_price' => 0]);
        $this->reject(fn () => app(PurchaseService::class)->create($this->purchaseData([
            'items' => [['product_id' => $this->product->id, 'unit_id' => $largeUnit->id, 'quantity' => 1000000, 'unit_price' => '0']],
        ])), 'quantity');

        $this->reject(fn () => app(PurchaseService::class)->create($this->purchaseData([
            'items' => [
                [...$this->item(), 'unit_price' => Decimal::MAX],
                [...$this->item(), 'unit_price' => '0.01'],
            ],
        ])), 'amount');

        $this->assertSame(0, $this->product->fresh()->stock);
        $this->assertSame('0.000000', $this->product->fresh()->average_cost);
        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, DB::table('operation_requests')->count());
    }
}
