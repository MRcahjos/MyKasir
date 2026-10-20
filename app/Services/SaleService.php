<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private InventoryService $inventory) {}

    public function create(array $data): Sale
    {
        $data = Operation::validate($data, [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()],
            'received_amount' => Operation::moneyRules(),
            'payment_method' => ['required', 'in:cash,transfer,qris'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'request_id' => ['required', 'uuid'],
            ...TradeItems::rules(false),
        ]);

        return Operation::once(Sale::class, $data['request_id'], $data, function ($hash) use ($data) {
            // Serialize credit eligibility for each customer, including two concurrent checkouts.
            $customer = ! empty($data['customer_id']) ? Customer::lockForUpdate()->findOrFail($data['customer_id']) : null;
            [$products, $lines, $totals] = TradeItems::prepare($data['items'], false);
            $total = Decimal::money(Decimal::sum(array_column($lines, 'subtotal')));
            $cost = Decimal::money(Decimal::sum(array_column($lines, 'cost_total')));
            $received = Decimal::money((string) $data['received_amount']);
            $overpaid = bccomp($received, $total, 2) > 0;
            if ($overpaid && $data['payment_method'] !== 'cash') {
                throw ValidationException::withMessages(['received_amount' => 'Pembayaran non-tunai tidak boleh melebihi total belanja.']);
            }
            $paid = $overpaid ? $total : $received;
            $balance = bcsub($total, $paid, 2);
            if (bccomp($balance, '0', 2) > 0) {
                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'Pilih pelanggan untuk transaksi yang belum lunas.']);
                }
                if ($customer->sales()->where('balance', '>', 0)->lockForUpdate()->first(['id'])) {
                    throw ValidationException::withMessages(['customer_id' => 'Pelanggan masih memiliki utang. Lunasi utang lama sebelum berutang lagi. Belanja yang dibayar lunas tetap diperbolehkan.']);
                }
            }
            $sale = Sale::create([
                'number' => Operation::number('PJ'), 'customer_id' => $customer?->id, 'date' => $data['date'],
                'total' => $total, 'cost_total' => $cost, 'paid_amount' => $paid, 'balance' => $balance,
                'received_amount' => $received, 'change_amount' => $overpaid ? bcsub($received, $total, 2) : '0.00',
                'payment_method' => $data['payment_method'], 'notes' => $data['notes'] ?? null,
                'request_id' => $data['request_id'], 'request_hash' => $hash,
            ]);
            $sale->items()->createMany($lines);
            if (bccomp($paid, '0', 2) > 0) {
                $sale->payments()->create([
                    'amount' => $paid, 'date' => $data['date'], 'method' => $data['payment_method'],
                    'notes' => 'Pembayaran saat penjualan', 'request_id' => $data['request_id'], 'request_hash' => $hash,
                ]);
            }
            foreach ($totals as $id => $group) {
                $product = $products[$id];
                $unitCost = $product->average_cost;
                $this->inventory->change($product, -$group['quantity'], $data['date']);
                StockMovement::create([
                    'product_id' => $id, 'type' => 'sale', 'quantity' => -$group['quantity'], 'balance' => $product->stock,
                    'unit_cost' => $unitCost, 'cost_total' => $group['cost_total'], 'reference' => $sale->number,
                    'notes' => $data['notes'] ?? null, 'date' => $data['date'],
                ]);
            }

            return $sale;
        })->load(['items', 'customer', 'payments']);
    }
}
