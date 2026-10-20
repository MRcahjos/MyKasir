<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use App\Services\ReceivableService;
use App\Services\ReportService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosDomainTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Supplier $supplier;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = Supplier::create(['name' => 'Supplier Test']);
        $this->customer = Customer::create(['name' => 'Pembeli Test']);
        $this->product = app(ProductService::class)->save($this->productData());
    }

    private function productData(): array
    {
        return [
            'sku' => 'RK22', 'name' => 'Cup Rankong 22 oz', 'category' => 'Kemasan', 'base_unit' => 'Cup',
            'minimum_stock' => 5, 'active' => true,
            'units' => [
                ['name' => 'Cup', 'multiplier' => 1, 'selling_price' => '17000.00'],
                ['name' => 'Dus', 'multiplier' => 20, 'selling_price' => '330000.00'],
            ],
        ];
    }

    private function item(int $quantity = 1, int $multiplier = 1): array
    {
        return ['product_id' => $this->product->id, 'unit_id' => $this->product->units->firstWhere('multiplier', $multiplier)->id, 'quantity' => $quantity];
    }

    private function purchase(string $price = '305000.00', int $quantity = 1): Purchase
    {
        return app(PurchaseService::class)->create([
            'supplier_id' => $this->supplier->id, 'date' => '2026-10-18', 'request_id' => (string) Str::uuid(),
            'items' => [array_merge($this->item($quantity, 20), ['unit_price' => $price])],
        ]);
    }

    private function saleData(array $override = []): array
    {
        return array_replace([
            'date' => '2026-10-18', 'request_id' => (string) Str::uuid(), 'items' => [$this->item()],
            'received_amount' => '17000.00', 'payment_method' => 'cash',
        ], $override);
    }

    private function reject(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Seharusnya permintaan ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_purchase_box_sale_cup_and_special_box_price_share_stock(): void
    {
        $this->purchase();
        $this->assertSame(20, $this->product->fresh()->stock);
        $this->assertSame('15250.000000', $this->product->fresh()->average_cost);
        $sale = app(SaleService::class)->create($this->saleData());
        $this->assertSame('15250.00', $sale->cost_total);
        $this->assertSame('1750.00', bcsub($sale->total, $sale->cost_total, 2));
        $this->assertSame(19, $this->product->fresh()->stock);
        $this->purchase();
        $box = app(SaleService::class)->create($this->saleData(['items' => [$this->item(1, 20)], 'received_amount' => '330000']));
        $this->assertSame('330000.00', $box->total);
        $this->assertSame('305000.00', $box->cost_total);
        $this->assertSame(19, $this->product->fresh()->stock);
    }

    public function test_mixed_units_stock_shortage_rolls_back_entire_sale(): void
    {
        $this->purchase();
        $this->reject(fn () => app(SaleService::class)->create($this->saleData([
            'items' => [$this->item(1, 20), $this->item(1)], 'received_amount' => '347000',
        ])), 'items');
        $this->assertSame(20, $this->product->fresh()->stock);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, StockMovement::count());
        $this->purchase();
        app(SaleService::class)->create($this->saleData([
            'items' => [$this->item(1, 20), $this->item(3)], 'received_amount' => '381000',
        ]));
        $this->assertSame(17, $this->product->fresh()->stock);
    }

    public function test_debt_blocks_new_credit_but_allows_paid_sale_and_partial_repayment(): void
    {
        $this->purchase();
        $creditData = $this->saleData(['customer_id' => $this->customer->id, 'items' => [$this->item(3)], 'received_amount' => '0']);
        $sale = app(SaleService::class)->create($creditData);
        $this->assertSame('51000.00', $sale->balance);
        $this->reject(fn () => app(SaleService::class)->create($this->saleData(['customer_id' => $this->customer->id, 'received_amount' => '10000'])), 'customer_id');
        app(SaleService::class)->create($this->saleData(['customer_id' => $this->customer->id]));
        $this->assertSame(16, $this->product->fresh()->stock);
        app(ReceivableService::class)->pay($sale, [
            'amount' => '20000', 'date' => '2026-10-19', 'method' => 'cash', 'request_id' => (string) Str::uuid(),
        ]);
        $this->assertSame('31000.00', $sale->fresh()->balance);
        $this->reject(fn () => app(SaleService::class)->create($this->saleData(['customer_id' => $this->customer->id, 'received_amount' => '0'])), 'customer_id');
        app(ReceivableService::class)->pay($sale, [
            'amount' => '31000', 'date' => '2026-10-20', 'method' => 'transfer', 'request_id' => (string) Str::uuid(),
        ]);
        $this->assertSame('0.00', $sale->fresh()->balance);
        $this->assertSame(16, $this->product->fresh()->stock);
        app(SaleService::class)->create($this->saleData(['customer_id' => $this->customer->id, 'received_amount' => '0']));
        $this->assertSame(15, $this->product->fresh()->stock);
    }

    public function test_replay_sale_and_payments_do_not_double_post_and_changed_payload_is_rejected(): void
    {
        $this->purchase();
        $data = $this->saleData(['customer_id' => $this->customer->id, 'received_amount' => '0']);
        $sale = app(SaleService::class)->create($data);
        $again = app(SaleService::class)->create($data);
        $this->assertSame($sale->id, $again->id);
        $this->assertSame(19, $this->product->fresh()->stock);
        $this->reject(fn () => app(SaleService::class)->create(array_replace($data, ['received_amount' => '1000'])), 'request_id');
        $paymentData = ['amount' => '7000', 'date' => '2026-10-19', 'method' => 'cash', 'request_id' => (string) Str::uuid()];
        $payment = app(ReceivableService::class)->pay($sale, $paymentData);
        $same = app(ReceivableService::class)->pay($sale, $paymentData);
        $this->assertSame($payment->id, $same->id);
        $this->assertSame('10000.00', $sale->fresh()->balance);
        $this->assertSame(1, Payment::count());
        $this->reject(fn () => app(ReceivableService::class)->pay($sale, array_replace($paymentData, ['amount' => '5000'])), 'request_id');
    }

    public function test_price_and_average_cost_changes_preserve_historical_sale_profit(): void
    {
        $this->purchase();
        $sale = app(SaleService::class)->create($this->saleData());
        $this->purchase('345000.00');
        $this->assertSame('16275.641026', $this->product->fresh()->average_cost);
        $input = $this->productData();
        foreach ($input['units'] as $index => &$unit) {
            $unit['id'] = $this->product->units[$index]->id;
        }
        unset($unit);
        $input['units'][0]['selling_price'] = '19000.00';
        app(ProductService::class)->save($input, $this->product);
        $this->assertSame('17000.00', $sale->fresh()->total);
        $this->assertSame('15250.00', $sale->fresh()->cost_total);
        $this->assertSame('17000.00', $sale->items()->first()->unit_price);
        $this->assertSame('1750.00', app(ReportService::class)->summary('2026-10-18', '2026-10-18')['gross_profit']);
    }

    public function test_profit_and_receipts_use_different_dates_and_losses_are_expensed_once(): void
    {
        $this->purchase();
        $sale = app(SaleService::class)->create($this->saleData(['customer_id' => $this->customer->id, 'received_amount' => '0']));
        Expense::create(['date' => '2026-10-18', 'category' => 'Listrik', 'description' => 'Listrik', 'amount' => '250.00', 'payment_method' => 'cash']);
        app(InventoryService::class)->adjust(['product_id' => $this->product->id, 'quantity' => -1, 'date' => '2026-10-18', 'notes' => 'Pecah', 'request_id' => (string) Str::uuid()]);
        app(ReceivableService::class)->pay($sale, ['amount' => '17000', 'date' => '2026-10-19', 'method' => 'cash', 'request_id' => (string) Str::uuid()]);
        $saleDay = app(ReportService::class)->summary('2026-10-18', '2026-10-18');
        $paidDay = app(ReportService::class)->summary('2026-10-19', '2026-10-19');
        $this->assertSame('17000.00', $saleDay['sales_total']);
        $this->assertSame('0.00', $saleDay['cash_received']);
        $this->assertSame('15250.00', $saleDay['stock_loss_total']);
        $this->assertSame('-13750.00', $saleDay['net_profit']);
        $this->assertSame('0.00', $paidDay['sales_total']);
        $this->assertSame('17000.00', $paidDay['cash_received']);
        $this->assertSame('0.00', $paidDay['outstanding_total']);
    }

    public function test_cash_change_is_not_income_and_non_cash_overpayment_is_rejected(): void
    {
        $this->purchase();
        $sale = app(SaleService::class)->create($this->saleData(['received_amount' => '20000']));
        $this->assertSame('3000.00', $sale->change_amount);
        $this->assertSame('17000.00', $sale->paid_amount);
        $this->assertSame('17000.00', $sale->payments->first()->amount);
        $this->reject(fn () => app(SaleService::class)->create($this->saleData(['received_amount' => '20000', 'payment_method' => 'qris'])), 'received_amount');
    }

    public function test_credit_requires_customer_and_repayment_cannot_exceed_balance(): void
    {
        $this->purchase();
        $this->reject(fn () => app(SaleService::class)->create($this->saleData(['received_amount' => '0'])), 'customer_id');
        $sale = app(SaleService::class)->create($this->saleData(['customer_id' => $this->customer->id, 'received_amount' => '10000']));
        $this->reject(fn () => app(ReceivableService::class)->pay($sale, [
            'amount' => '7001', 'date' => '2026-10-19', 'method' => 'cash', 'request_id' => (string) Str::uuid(),
        ]), 'amount');
        $this->assertSame('7000.00', $sale->fresh()->balance);
    }

    public function test_conversion_is_immutable_after_stock_history_and_unrelated_units_rejected(): void
    {
        $this->purchase();
        $input = $this->productData();
        foreach ($input['units'] as $index => &$unit) {
            $unit['id'] = $this->product->units[$index]->id;
        }
        unset($unit);
        $input['units'][1]['multiplier'] = 24;
        $this->reject(fn () => app(ProductService::class)->save($input, $this->product), 'units.1.multiplier');
        $otherData = $this->productData();
        $otherData['sku'] = 'OTHER';
        $other = app(ProductService::class)->save($otherData);
        $badItem = $this->item();
        $badItem['unit_id'] = $other->units->first()->id;
        $this->reject(fn () => app(SaleService::class)->create($this->saleData(['items' => [$badItem]])), 'items.0.unit_id');
    }

    public function test_purchase_and_adjustment_retry_are_idempotent(): void
    {
        $data = [
            'supplier_id' => $this->supplier->id, 'date' => '2026-10-18', 'request_id' => (string) Str::uuid(),
            'items' => [array_merge($this->item(1, 20), ['unit_price' => '305000'])],
        ];
        $purchase = app(PurchaseService::class)->create($data);
        $this->assertSame($purchase->id, app(PurchaseService::class)->create($data)->id);
        $adjust = ['product_id' => $this->product->id, 'quantity' => -2, 'date' => '2026-10-18', 'notes' => 'Rusak', 'request_id' => (string) Str::uuid()];
        $movement = app(InventoryService::class)->adjust($adjust);
        $this->assertSame($movement->id, app(InventoryService::class)->adjust($adjust)->id);
        $this->assertSame(18, $this->product->fresh()->stock);
        $this->assertSame(1, Purchase::count());
        $this->assertSame(2, StockMovement::count());
    }

    public function test_failed_operation_can_retry_after_stock_is_received_without_leaving_a_reservation(): void
    {
        $data = $this->saleData();
        $this->reject(fn () => app(SaleService::class)->create($data), 'items');
        $this->assertSame(0, DB::table('operation_requests')->where('request_id', $data['request_id'])->count());

        $this->purchase();
        $sale = app(SaleService::class)->create($data);
        $this->assertSame('17000.00', $sale->total);
        $this->assertSame(19, $this->product->fresh()->stock);
        $this->assertSame(1, DB::table('operation_requests')->where('request_id', $data['request_id'])->count());
    }

    public function test_stock_additions_update_weighted_cost_and_depleted_stock_starts_at_new_cost(): void
    {
        $this->purchase();
        app(InventoryService::class)->adjust([
            'product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => '16000',
            'date' => '2026-10-18', 'notes' => 'Stok awal tambahan', 'request_id' => (string) Str::uuid(),
        ]);
        $this->assertSame(30, $this->product->fresh()->stock);
        $this->assertSame('15500.000000', $this->product->fresh()->average_cost);

        app(SaleService::class)->create($this->saleData(['items' => [$this->item(30)], 'received_amount' => '510000']));
        $this->assertSame(0, $this->product->fresh()->stock);
        $this->purchase('345000');
        $this->assertSame('17250.000000', $this->product->fresh()->average_cost);
    }

    public function test_changed_purchase_and_adjustment_replays_are_rejected_without_changing_inventory(): void
    {
        $purchase = [
            'supplier_id' => $this->supplier->id, 'date' => '2026-10-18', 'request_id' => (string) Str::uuid(),
            'items' => [array_merge($this->item(1, 20), ['unit_price' => '305000'])],
        ];
        app(PurchaseService::class)->create($purchase);
        $changed = $purchase;
        $changed['items'][0]['unit_price'] = '345000';
        $this->reject(fn () => app(PurchaseService::class)->create($changed), 'request_id');

        $adjustment = [
            'product_id' => $this->product->id, 'quantity' => -1, 'date' => '2026-10-18',
            'notes' => 'Rusak', 'request_id' => (string) Str::uuid(),
        ];
        app(InventoryService::class)->adjust($adjustment);
        $this->reject(fn () => app(InventoryService::class)->adjust(array_replace($adjustment, ['quantity' => -2])), 'request_id');
        $this->assertSame(19, $this->product->fresh()->stock);
        $this->assertSame('15250.000000', $this->product->fresh()->average_cost);
        $this->assertSame(2, StockMovement::count());
    }

    public function test_repayment_before_sale_and_reused_payment_for_another_sale_are_rejected(): void
    {
        $this->purchase();
        $sale = app(SaleService::class)->create($this->saleData(['customer_id' => $this->customer->id, 'received_amount' => '0']));
        $payment = ['amount' => '17000', 'date' => '2026-10-17', 'method' => 'cash', 'request_id' => (string) Str::uuid()];
        $this->reject(fn () => app(ReceivableService::class)->pay($sale, $payment), 'date');
        $this->assertSame('17000.00', $sale->fresh()->balance);
        $payment['date'] = '2026-10-18';
        app(ReceivableService::class)->pay($sale, $payment);

        $next = app(SaleService::class)->create($this->saleData(['customer_id' => $this->customer->id, 'received_amount' => '0']));
        $this->reject(fn () => app(ReceivableService::class)->pay($next, $payment), 'request_id');
        $this->assertSame('17000.00', $next->fresh()->balance);
        $this->assertSame(1, Payment::count());
    }
}
