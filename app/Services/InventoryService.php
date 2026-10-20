<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public const MAX_STOCK = 2147483647;

    public function adjust(array $data): StockMovement
    {
        $data = Operation::validate($data, [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.Decimal::MAX],
            'notes' => ['required', 'string', 'max:1000'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()],
            'request_id' => ['nullable', 'uuid'],
        ]);
        if ($data['quantity'] > 0 && ! isset($data['unit_cost'])) {
            throw ValidationException::withMessages(['unit_cost' => 'Modal per satuan terkecil wajib diisi untuk penambahan stok.']);
        }
        $create = function (?string $hash = null) use ($data) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            $quantity = (int) $data['quantity'];
            $unitCost = $quantity > 0 ? bcadd((string) $data['unit_cost'], '0', 6) : $product->average_cost;
            $costTotal = Decimal::money(bcmul($unitCost, (string) abs($quantity), 8));
            $this->change($product, $quantity, $data['date'], $quantity > 0 ? $costTotal : null);

            return StockMovement::create([
                'product_id' => $product->id, 'type' => 'adjustment', 'quantity' => $quantity,
                'balance' => $product->stock, 'unit_cost' => $unitCost, 'cost_total' => $costTotal,
                'reference' => Operation::number('ADJ'), 'notes' => $data['notes'], 'date' => $data['date'],
                'request_id' => $data['request_id'] ?? null, 'request_hash' => $hash,
            ]);
        };

        return ! empty($data['request_id'])
            ? Operation::once(StockMovement::class, $data['request_id'], $data, $create)
            : DB::transaction($create, 3);
    }

    /** Caller must hold a product row lock inside its transaction. */
    public function change(Product $product, int $quantity, string $date, ?string $incomingCost = null): void
    {
        // Moving-average costs are captured at posting time. Inserting before a
        // later movement would misstate historical stock and profit without
        // rebuilding every later document, so stock is posted chronologically.
        $latestDate = $product->movements()->orderByDesc('date')->orderByDesc('id')
            ->lockForUpdate()->first(['date'])?->date->toDateString();
        if ($latestDate && $date < $latestDate) {
            throw ValidationException::withMessages([
                'date' => 'Tanggal transaksi '.$product->name.' tidak boleh sebelum pergerakan stok terakhir pada '.$latestDate.'.',
            ]);
        }
        $stock = $product->stock + $quantity;
        if ($stock < 0) {
            throw ValidationException::withMessages(['items' => 'Stok '.$product->name.' tidak cukup. Tersedia '.$product->stock.' '.$product->base_unit.'.']);
        }
        if ($stock > self::MAX_STOCK) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah stok melebihi batas yang diperbolehkan.']);
        }
        if ($quantity > 0 && $incomingCost !== null) {
            $value = bcadd(bcmul($product->average_cost ?? '0', (string) $product->stock, 8), $incomingCost, 8);
            $product->average_cost = Decimal::round(bcdiv($value, (string) $stock, 10), 6);
        }
        $product->stock = $stock;
        $product->save();
    }
}
