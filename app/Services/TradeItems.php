<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class TradeItems
{
    public static function rules(bool $purchase): array
    {
        $rules = [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
        if ($purchase) {
            $rules['items.*.unit_price'] = Operation::moneyRules();
        } else {
            $rules['items.*.expected_unit_price'] = ['sometimes', ...Operation::moneyRules()];
        }

        return $rules;
    }

    public static function prepare(array $items, bool $purchase): array
    {
        $products = Product::whereIn('id', array_column($items, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        // A locking read also avoids stale unit prices under MySQL REPEATABLE READ.
        $products->load(['units' => fn ($query) => $query->lockForUpdate()]);
        $lines = [];
        $totals = [];
        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);
            if (! $product || ! $product->active) {
                throw ValidationException::withMessages(["items.$index.product_id" => 'Barang tidak ditemukan atau sudah dinonaktifkan.']);
            }
            $unit = $product->units->firstWhere('id', (int) $item['unit_id']);
            if (! $unit) {
                throw ValidationException::withMessages(["items.$index.unit_id" => 'Satuan tidak dimiliki barang yang dipilih.']);
            }
            $quantity = (int) $item['quantity'];
            $baseQuantity = $quantity * $unit->multiplier;
            $price = Decimal::money((string) ($purchase ? $item['unit_price'] : $unit->selling_price));
            if (! $purchase && isset($item['expected_unit_price']) && bccomp((string) $item['expected_unit_price'], $price, 2) !== 0) {
                throw ValidationException::withMessages([
                    "items.$index.expected_unit_price" => 'Harga '.$product->name.' ('.$unit->name.') sudah berubah. Perbarui daftar barang lalu periksa kembali pembayaran.',
                ]);
            }
            $subtotal = Decimal::money(bcmul($price, (string) $quantity, 2));
            $cost = $purchase ? '0.00' : Decimal::money(bcmul($product->average_cost, (string) $baseQuantity, 8));
            $line = [
                'product_id' => $product->id, 'unit_id' => $unit->id, 'product_name' => $product->name,
                'unit_name' => $unit->name, 'multiplier' => $unit->multiplier, 'quantity' => $quantity,
                'unit_price' => $price, 'subtotal' => $subtotal,
            ];
            if (! $purchase) {
                $line['cost_total'] = $cost;
            }
            $lines[] = $line;
            $totals[$product->id] ??= ['quantity' => 0, 'subtotal' => '0.00', 'cost_total' => '0.00'];
            $totals[$product->id]['quantity'] += $baseQuantity;
            $totals[$product->id]['subtotal'] = Decimal::money(bcadd($totals[$product->id]['subtotal'], $subtotal, 2));
            $totals[$product->id]['cost_total'] = Decimal::money(bcadd($totals[$product->id]['cost_total'], $cost, 2));
        }
        if (! $purchase) {
            foreach ($totals as $id => $total) {
                if ($total['quantity'] > $products[$id]->stock) {
                    throw ValidationException::withMessages(['items' => 'Stok '.$products[$id]->name.' tidak cukup. Tersedia '.$products[$id]->stock.' '.$products[$id]->base_unit.'.']);
                }
            }
        }

        return [$products, $lines, $totals];
    }
}
