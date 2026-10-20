<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\StockMovement;

class PurchaseService
{
    public function __construct(private InventoryService $inventory) {}

    public function create(array $data): Purchase
    {
        $data = Operation::validate($data, [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()],
            'notes' => ['nullable', 'string', 'max:1000'],
            'request_id' => ['required', 'uuid'],
            ...TradeItems::rules(true),
        ]);

        return Operation::once(Purchase::class, $data['request_id'], $data, function ($hash) use ($data) {
            [$products, $lines, $totals] = TradeItems::prepare($data['items'], true);
            $purchase = Purchase::create([
                'number' => Operation::number('BL'), 'supplier_id' => $data['supplier_id'], 'date' => $data['date'],
                'total' => Decimal::money(Decimal::sum(array_column($lines, 'subtotal'))),
                'notes' => $data['notes'] ?? null, 'request_id' => $data['request_id'], 'request_hash' => $hash,
            ]);
            $purchase->items()->createMany($lines);
            foreach ($totals as $id => $total) {
                $product = $products[$id];
                $this->inventory->change($product, $total['quantity'], $data['date'], $total['subtotal']);
                StockMovement::create([
                    'product_id' => $id, 'type' => 'purchase', 'quantity' => $total['quantity'], 'balance' => $product->stock,
                    'unit_cost' => Decimal::round(bcdiv($total['subtotal'], (string) $total['quantity'], 10), 6),
                    'cost_total' => $total['subtotal'], 'reference' => $purchase->number,
                    'notes' => $data['notes'] ?? null, 'date' => $data['date'],
                ]);
            }

            return $purchase;
        })->load(['items', 'supplier']);
    }
}
